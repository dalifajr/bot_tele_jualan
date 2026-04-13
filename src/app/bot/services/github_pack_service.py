from __future__ import annotations

import json
from dataclasses import dataclass
from datetime import datetime, timedelta

from sqlalchemy import func, or_, select
from sqlalchemy.orm import Session

from app.bot.services.audit_service import append_audit
from app.bot.services.settings_service import get_setting, set_setting
from app.bot.services.catalog_service import (
    STOCK_STATUS_AWAITING,
    STOCK_STATUS_READY,
    promote_awaiting_stocks,
)
from app.bot.services.stock_parser import parse_stock_block
from app.common.config import get_settings
from app.db.models import Product, StockUnit

settings = get_settings()
GITHUB_PACK_AWAITING_HOURS_KEY = "github_pack.awaiting_hours"
DEFAULT_GITHUB_PACK_AWAITING_HOURS = 78
MIN_GITHUB_PACK_AWAITING_HOURS = 1
MAX_GITHUB_PACK_AWAITING_HOURS = 720


@dataclass
class GithubStockView:
    id: int
    username: str
    status: str
    available_at: datetime | None
    created_at: datetime
    raw_text: str


@dataclass(frozen=True)
class GithubAwaitingHoursUpdateResult:
    old_hours: int
    new_hours: int
    delta_hours: int
    adjusted_stock_count: int


def _extract_username_from_parsed_json(parsed_json: str | None) -> str:
    if not parsed_json:
        return "unknown"
    try:
        data = json.loads(parsed_json)
    except json.JSONDecodeError:
        return "unknown"

    fields = data.get("fields") or {}
    if isinstance(fields, dict):
        for key, value in fields.items():
            if str(key).strip().lower() == "username":
                username = str(value).strip()
                if username:
                    return username
    return "unknown"


def _normalize_username(value: str) -> str:
    return value.strip().lower()


def ensure_github_pack_product(session: Session) -> Product:
    target_name = settings.github_pack_name.strip()
    product = session.scalar(
        select(Product).where(func.lower(Product.name) == target_name.lower())
    )
    if product is None:
        product = Product(
            name=target_name,
            description="Produk khusus GitHub Student Developer Pack",
            price=25000,
            is_suspended=False,
        )
        session.add(product)
        session.flush()
    return product


def set_github_pack_price(session: Session, new_price: int, actor_id: int | None) -> Product:
    if new_price <= 0:
        raise ValueError("Harga harus lebih dari 0.")

    product = ensure_github_pack_product(session)
    old_price = product.price
    product.price = int(new_price)
    session.add(product)

    append_audit(
        session,
        action="github_pack_set_price",
        actor_id=actor_id,
        entity_type="product",
        entity_id=str(product.id),
        detail=f"old_price={old_price}; new_price={product.price}",
    )
    return product


def is_github_pack_product(session: Session, product_id: int) -> bool:
    product = ensure_github_pack_product(session)
    return int(product.id) == int(product_id)


def _sanitize_awaiting_hours(raw: str) -> int:
    try:
        parsed = int(str(raw).strip())
    except (TypeError, ValueError):
        return int(DEFAULT_GITHUB_PACK_AWAITING_HOURS)

    if parsed < MIN_GITHUB_PACK_AWAITING_HOURS:
        return int(MIN_GITHUB_PACK_AWAITING_HOURS)
    if parsed > MAX_GITHUB_PACK_AWAITING_HOURS:
        return int(MAX_GITHUB_PACK_AWAITING_HOURS)
    return int(parsed)


def get_github_pack_awaiting_hours(session: Session) -> int:
    raw = get_setting(
        session,
        key=GITHUB_PACK_AWAITING_HOURS_KEY,
        default=str(DEFAULT_GITHUB_PACK_AWAITING_HOURS),
    )
    return _sanitize_awaiting_hours(raw)


def _apply_awaiting_delta_to_existing_stocks(
    session: Session,
    *,
    product_id: int,
    delta_hours: int,
) -> int:
    if delta_hours == 0:
        return 0

    delta = timedelta(hours=delta_hours)
    awaiting_rows = list(
        session.scalars(
            select(StockUnit)
            .where(
                StockUnit.product_id == product_id,
                StockUnit.is_sold.is_(False),
                StockUnit.stock_status == STOCK_STATUS_AWAITING,
                StockUnit.available_at.is_not(None),
            )
            .order_by(StockUnit.id.asc())
        ).all()
    )

    adjusted_count = 0
    for row in awaiting_rows:
        if row.available_at is None:
            continue
        row.available_at = row.available_at + delta
        session.add(row)
        adjusted_count += 1

    return adjusted_count


def set_github_pack_awaiting_hours(
    session: Session,
    hours: int,
    actor_id: int | None,
) -> GithubAwaitingHoursUpdateResult:
    normalized = _sanitize_awaiting_hours(str(hours))
    if int(hours) != normalized:
        raise ValueError(
            f"Durasi awaiting harus antara {MIN_GITHUB_PACK_AWAITING_HOURS} sampai {MAX_GITHUB_PACK_AWAITING_HOURS} jam."
        )

    product = ensure_github_pack_product(session)
    old_hours = get_github_pack_awaiting_hours(session)
    delta_hours = int(normalized) - int(old_hours)
    adjusted_stock_count = _apply_awaiting_delta_to_existing_stocks(
        session,
        product_id=int(product.id),
        delta_hours=delta_hours,
    )
    set_setting(session, key=GITHUB_PACK_AWAITING_HOURS_KEY, value=str(normalized))

    append_audit(
        session,
        action="github_pack_set_awaiting_hours",
        actor_id=actor_id,
        entity_type="setting",
        entity_id=GITHUB_PACK_AWAITING_HOURS_KEY,
        detail=(
            f"old_hours={old_hours}; new_hours={normalized}; "
            f"delta_hours={delta_hours}; adjusted_stock_count={adjusted_stock_count}"
        ),
    )
    return GithubAwaitingHoursUpdateResult(
        old_hours=int(old_hours),
        new_hours=int(normalized),
        delta_hours=int(delta_hours),
        adjusted_stock_count=int(adjusted_stock_count),
    )


def add_github_stock(session: Session, raw_text: str, actor_id: int | None, awaiting: bool) -> GithubStockView:
    product = ensure_github_pack_product(session)
    parsed = parse_stock_block(raw_text)

    username = "unknown"
    for key, value in parsed.fields.items():
        if key.strip().lower() == "username":
            username = value.strip() or "unknown"
            break

    stock_status = STOCK_STATUS_AWAITING if awaiting else STOCK_STATUS_READY
    awaiting_hours = get_github_pack_awaiting_hours(session)
    available_at = datetime.utcnow() + timedelta(hours=awaiting_hours) if awaiting else None

    stock = StockUnit(
        product_id=product.id,
        raw_text=raw_text.strip(),
        parsed_json=parsed.as_json(),
        stock_status=stock_status,
        available_at=available_at,
        username_key=_normalize_username(username),
        is_sold=False,
    )
    session.add(stock)
    session.flush()

    append_audit(
        session,
        action="github_stock_add",
        actor_id=actor_id,
        entity_type="stock_unit",
        entity_id=str(stock.id),
        detail=f"status={stock_status}; username={username}",
    )

    return GithubStockView(
        id=int(stock.id),
        username=username,
        status=stock_status,
        available_at=stock.available_at,
        created_at=stock.created_at,
        raw_text=stock.raw_text,
    )


def list_github_stocks(session: Session) -> list[GithubStockView]:
    product = ensure_github_pack_product(session)
    promote_awaiting_stocks(session, product_id=product.id)

    rows = list(
        session.scalars(
            select(StockUnit)
            .where(
                StockUnit.product_id == product.id,
                StockUnit.is_sold.is_(False),
                or_(
                    StockUnit.stock_status == STOCK_STATUS_READY,
                    StockUnit.stock_status == STOCK_STATUS_AWAITING,
                    StockUnit.stock_status.is_(None),
                ),
            )
            .order_by(StockUnit.id.asc())
        ).all()
    )

    result: list[GithubStockView] = []
    for row in rows:
        username = _extract_username_from_parsed_json(row.parsed_json)
        result.append(
            GithubStockView(
                id=int(row.id),
                username=username,
                status=(row.stock_status or STOCK_STATUS_READY),
                available_at=row.available_at,
                created_at=row.created_at,
                raw_text=row.raw_text,
            )
        )
    return result


def get_github_stock_detail(session: Session, stock_id: int) -> GithubStockView | None:
    product = ensure_github_pack_product(session)
    promote_awaiting_stocks(session, product_id=product.id)

    stock = session.scalar(
        select(StockUnit).where(
            StockUnit.id == stock_id,
            StockUnit.product_id == product.id,
            StockUnit.is_sold.is_(False),
        )
    )
    if stock is None:
        return None

    return GithubStockView(
        id=int(stock.id),
        username=_extract_username_from_parsed_json(stock.parsed_json),
        status=(stock.stock_status or STOCK_STATUS_READY),
        available_at=stock.available_at,
        created_at=stock.created_at,
        raw_text=stock.raw_text,
    )


def delete_github_stock(session: Session, stock_id: int, actor_id: int | None) -> None:
    product = ensure_github_pack_product(session)
    stock = session.scalar(
        select(StockUnit).where(
            StockUnit.id == stock_id,
            StockUnit.product_id == product.id,
            StockUnit.is_sold.is_(False),
        )
    )
    if stock is None:
        raise ValueError("Stok tidak ditemukan atau sudah terjual.")

    username = _extract_username_from_parsed_json(stock.parsed_json)
    session.delete(stock)
    append_audit(
        session,
        action="github_stock_delete",
        actor_id=actor_id,
        entity_type="stock_unit",
        entity_id=str(stock_id),
        detail=f"username={username}",
    )
