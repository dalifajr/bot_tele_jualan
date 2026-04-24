from __future__ import annotations

import json
from dataclasses import dataclass
from datetime import datetime, timedelta

from sqlalchemy import and_, func, or_, select
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
from app.db.models import Order, Product, StockUnit, User

settings = get_settings()
GITHUB_PACK_AWAITING_HOURS_KEY = "github_pack.awaiting_hours"
GITHUB_PACK_USED_PRODUCT_ID_KEY = "github_pack.used_product_id"
DEFAULT_GITHUB_PACK_AWAITING_HOURS = 78
MIN_GITHUB_PACK_AWAITING_HOURS = 1
MAX_GITHUB_PACK_AWAITING_HOURS = 720
GITHUB_PACK_SAVE_HOURS = 80
GITHUB_PACK_NOTIFY_BATCH_WINDOW_MINUTES = 60
DEFAULT_GITHUB_USED_PRODUCT_NAME = "GHS Bekas"
DEFAULT_GITHUB_USED_PRODUCT_PRICE = 20000
GITHUB_PACK_STOCK_STATUS_MOVED_TO_USED = "moved_to_ghs_used"
GITHUB_PACK_STOCK_STATUS_SAVED = "saved_for_verification"
GITHUB_PACK_STOCK_STATUS_SAVED_NOTIFIED = "saved_ready_notified"


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


@dataclass(frozen=True)
class GithubSoldStockSummary:
    stock_id: int
    username: str
    sold_at: datetime
    order_ref: str
    is_moved_to_used: bool


@dataclass(frozen=True)
class GithubSoldStockDetail:
    stock_id: int
    username: str
    sold_at: datetime
    account_age_days: int
    order_ref: str
    buyer_display: str
    buyer_telegram_id: int | None
    raw_text: str
    is_moved_to_used: bool


@dataclass(frozen=True)
class GithubSoldStockMoveResult:
    source_stock_id: int
    source_username: str
    used_product_id: int
    used_product_name: str
    used_stock_id: int


@dataclass(frozen=True)
class GithubSavedStockView:
    stock_id: int
    username: str
    ready_at: datetime
    created_at: datetime
    is_ready: bool
    is_notified: bool
    raw_text: str


@dataclass(frozen=True)
class GithubSavedReadyNotifyCandidate:
    stock_id: int
    username: str
    ready_at: datetime
    created_at: datetime


@dataclass(frozen=True)
class GithubSavedMoveResult:
    moved_count: int
    awaiting_hours: int


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


def _resolve_sold_at(order: Order | None, stock: StockUnit) -> datetime:
    if order is None:
        return stock.created_at
    return order.delivered_at or order.paid_at or order.created_at or stock.created_at


def _resolve_saved_ready_at(stock: StockUnit) -> datetime:
    if stock.available_at is not None:
        return stock.available_at
    return stock.created_at + timedelta(hours=GITHUB_PACK_SAVE_HOURS)


def _buyer_display(user: User | None) -> tuple[str, int | None]:
    if user is None:
        return "-", None

    telegram_id = int(user.telegram_id)
    if user.username:
        return f"@{user.username}", telegram_id
    if user.full_name:
        return user.full_name, telegram_id
    return str(telegram_id), telegram_id


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


def ensure_github_pack_used_product(session: Session) -> Product:
    github_pack_product = ensure_github_pack_product(session)
    configured_id_raw = get_setting(session, key=GITHUB_PACK_USED_PRODUCT_ID_KEY, default="")

    configured_id: int | None = None
    try:
        configured_id = int(str(configured_id_raw).strip()) if str(configured_id_raw).strip() else None
    except (TypeError, ValueError):
        configured_id = None

    product: Product | None = None
    if configured_id is not None:
        existing = session.get(Product, configured_id)
        if existing is not None:
            product = existing

    if product is None:
        product = session.scalar(
            select(Product).where(func.lower(Product.name) == DEFAULT_GITHUB_USED_PRODUCT_NAME.lower())
        )

    if product is None:
        default_price = max(1000, min(int(github_pack_product.price), DEFAULT_GITHUB_USED_PRODUCT_PRICE))
        product = Product(
            name=DEFAULT_GITHUB_USED_PRODUCT_NAME,
            description="Akun GitHub Student Developer Pack bekas untuk dijual ulang.",
            price=default_price,
            is_suspended=False,
        )
        session.add(product)
        session.flush()

    set_setting(session, key=GITHUB_PACK_USED_PRODUCT_ID_KEY, value=str(int(product.id)))
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


def set_github_pack_used_price(session: Session, new_price: int, actor_id: int | None) -> Product:
    if new_price <= 0:
        raise ValueError("Harga harus lebih dari 0.")

    product = ensure_github_pack_used_product(session)
    old_price = product.price
    product.price = int(new_price)
    session.add(product)

    append_audit(
        session,
        action="github_pack_set_used_price",
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


def add_saved_github_stock(session: Session, raw_text: str, actor_id: int | None) -> GithubSavedStockView:
    product = ensure_github_pack_product(session)
    parsed = parse_stock_block(raw_text)

    username = "unknown"
    for key, value in parsed.fields.items():
        if key.strip().lower() == "username":
            username = value.strip() or "unknown"
            break

    ready_at = datetime.utcnow() + timedelta(hours=GITHUB_PACK_SAVE_HOURS)
    stock = StockUnit(
        product_id=product.id,
        raw_text=raw_text.strip(),
        parsed_json=parsed.as_json(),
        stock_status=GITHUB_PACK_STOCK_STATUS_SAVED,
        available_at=ready_at,
        username_key=_normalize_username(username),
        is_sold=False,
        sold_order_id=None,
    )
    session.add(stock)
    session.flush()

    append_audit(
        session,
        action="github_saved_stock_add",
        actor_id=actor_id,
        entity_type="stock_unit",
        entity_id=str(stock.id),
        detail=f"ready_at={ready_at.isoformat()}; username={username}",
    )

    return GithubSavedStockView(
        stock_id=int(stock.id),
        username=username,
        ready_at=ready_at,
        created_at=stock.created_at,
        is_ready=False,
        is_notified=False,
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


def list_saved_github_stocks(session: Session) -> list[GithubSavedStockView]:
    product = ensure_github_pack_product(session)
    now = datetime.utcnow()

    rows = list(
        session.scalars(
            select(StockUnit)
            .where(
                StockUnit.product_id == product.id,
                StockUnit.is_sold.is_(False),
                StockUnit.stock_status.in_(
                    [
                        GITHUB_PACK_STOCK_STATUS_SAVED,
                        GITHUB_PACK_STOCK_STATUS_SAVED_NOTIFIED,
                    ]
                ),
            )
            .order_by(func.coalesce(StockUnit.available_at, StockUnit.created_at).asc(), StockUnit.id.asc())
        ).all()
    )

    result: list[GithubSavedStockView] = []
    for row in rows:
        ready_at = _resolve_saved_ready_at(row)
        result.append(
            GithubSavedStockView(
                stock_id=int(row.id),
                username=_extract_username_from_parsed_json(row.parsed_json),
                ready_at=ready_at,
                created_at=row.created_at,
                is_ready=(ready_at <= now),
                is_notified=(row.stock_status == GITHUB_PACK_STOCK_STATUS_SAVED_NOTIFIED),
                raw_text=row.raw_text,
            )
        )
    return result


def list_saved_github_ready_notification_batch(
    session: Session,
    *,
    now: datetime | None = None,
    batch_window_minutes: int = GITHUB_PACK_NOTIFY_BATCH_WINDOW_MINUTES,
) -> list[GithubSavedReadyNotifyCandidate]:
    current = now or datetime.utcnow()
    window_minutes = max(1, int(batch_window_minutes))
    product = ensure_github_pack_product(session)

    rows = list(
        session.scalars(
            select(StockUnit)
            .where(
                StockUnit.is_sold.is_(False),
                StockUnit.stock_status == GITHUB_PACK_STOCK_STATUS_SAVED,
                StockUnit.product_id == product.id,
            )
            .order_by(func.coalesce(StockUnit.available_at, StockUnit.created_at).asc(), StockUnit.id.asc())
        ).all()
    )
    if not rows:
        return []

    earliest_ready_at = _resolve_saved_ready_at(rows[0])
    cluster_deadline = earliest_ready_at + timedelta(minutes=window_minutes)

    cluster_rows: list[StockUnit] = []
    for row in rows:
        ready_at = _resolve_saved_ready_at(row)
        if ready_at <= cluster_deadline:
            cluster_rows.append(row)
        else:
            break

    if not cluster_rows:
        return []

    latest_cluster_ready_at = max(_resolve_saved_ready_at(row) for row in cluster_rows)
    if latest_cluster_ready_at > current:
        return []

    result: list[GithubSavedReadyNotifyCandidate] = []
    for row in cluster_rows:
        ready_at = _resolve_saved_ready_at(row)
        if ready_at > current:
            continue
        result.append(
            GithubSavedReadyNotifyCandidate(
                stock_id=int(row.id),
                username=_extract_username_from_parsed_json(row.parsed_json),
                ready_at=ready_at,
                created_at=row.created_at,
            )
        )
    return result


def mark_saved_github_ready_notified(
    session: Session,
    *,
    stock_ids: list[int],
    actor_id: int | None,
) -> int:
    if not stock_ids:
        return 0

    rows = list(
        session.scalars(
            select(StockUnit)
            .where(
                StockUnit.id.in_(stock_ids),
                StockUnit.is_sold.is_(False),
                StockUnit.stock_status == GITHUB_PACK_STOCK_STATUS_SAVED,
            )
            .order_by(StockUnit.id.asc())
        ).all()
    )
    for row in rows:
        row.stock_status = GITHUB_PACK_STOCK_STATUS_SAVED_NOTIFIED
        session.add(row)

    if rows:
        append_audit(
            session,
            action="github_saved_stock_notify_marked",
            actor_id=actor_id,
            entity_type="stock_unit",
            entity_id=str(rows[0].id),
            detail=f"count={len(rows)}; stock_ids={[int(x.id) for x in rows]}",
        )

    return len(rows)


def move_ready_saved_github_stocks_to_awaiting(
    session: Session,
    *,
    actor_id: int | None,
) -> GithubSavedMoveResult:
    product = ensure_github_pack_product(session)
    awaiting_hours = get_github_pack_awaiting_hours(session)
    now = datetime.utcnow()
    hold_threshold = now - timedelta(hours=GITHUB_PACK_SAVE_HOURS)

    rows = list(
        session.scalars(
            select(StockUnit)
            .where(
                StockUnit.product_id == product.id,
                StockUnit.is_sold.is_(False),
                StockUnit.stock_status.in_(
                    [
                        GITHUB_PACK_STOCK_STATUS_SAVED,
                        GITHUB_PACK_STOCK_STATUS_SAVED_NOTIFIED,
                    ]
                ),
                or_(
                    StockUnit.available_at <= now,
                    and_(
                        StockUnit.available_at.is_(None),
                        StockUnit.created_at <= hold_threshold,
                    ),
                ),
            )
            .order_by(func.coalesce(StockUnit.available_at, StockUnit.created_at).asc(), StockUnit.id.asc())
        ).all()
    )

    if not rows:
        raise ValueError("Belum ada akun simpan yang mencapai 80 jam.")

    awaiting_ready_at = now + timedelta(hours=awaiting_hours)
    for row in rows:
        row.stock_status = STOCK_STATUS_AWAITING
        row.available_at = awaiting_ready_at
        session.add(row)

    append_audit(
        session,
        action="github_saved_stock_move_to_awaiting",
        actor_id=actor_id,
        entity_type="stock_unit",
        entity_id=str(rows[0].id),
        detail=(
            f"count={len(rows)}; awaiting_hours={awaiting_hours}; target_ready_at={awaiting_ready_at.isoformat()}; "
            f"stock_ids={[int(x.id) for x in rows]}"
        ),
    )

    return GithubSavedMoveResult(moved_count=len(rows), awaiting_hours=awaiting_hours)


def list_sold_github_stocks(session: Session) -> list[GithubSoldStockSummary]:
    product = ensure_github_pack_product(session)

    rows = list(
        session.execute(
            select(StockUnit, Order)
            .join(Order, Order.id == StockUnit.sold_order_id)
            .where(
                StockUnit.product_id == product.id,
                StockUnit.is_sold.is_(True),
                StockUnit.sold_order_id.is_not(None),
            )
            .order_by(
                func.coalesce(Order.delivered_at, Order.paid_at, Order.created_at, StockUnit.created_at).desc(),
                StockUnit.id.desc(),
            )
        ).all()
    )

    result: list[GithubSoldStockSummary] = []
    for stock, order in rows:
        sold_at = _resolve_sold_at(order, stock)
        result.append(
            GithubSoldStockSummary(
                stock_id=int(stock.id),
                username=_extract_username_from_parsed_json(stock.parsed_json),
                sold_at=sold_at,
                order_ref=order.order_ref,
                is_moved_to_used=(stock.stock_status == GITHUB_PACK_STOCK_STATUS_MOVED_TO_USED),
            )
        )
    return result


def get_sold_github_stock_detail(session: Session, stock_id: int) -> GithubSoldStockDetail | None:
    product = ensure_github_pack_product(session)

    row = session.execute(
        select(StockUnit, Order, User)
        .join(Order, Order.id == StockUnit.sold_order_id)
        .outerjoin(User, User.id == Order.customer_id)
        .where(
            StockUnit.id == stock_id,
            StockUnit.product_id == product.id,
            StockUnit.is_sold.is_(True),
            StockUnit.sold_order_id.is_not(None),
        )
    ).first()
    if row is None:
        return None

    stock, order, user = row
    sold_at = _resolve_sold_at(order, stock)
    account_age_days = max(0, int((sold_at - stock.created_at).total_seconds() // 86400))
    buyer_display, buyer_telegram_id = _buyer_display(user)

    return GithubSoldStockDetail(
        stock_id=int(stock.id),
        username=_extract_username_from_parsed_json(stock.parsed_json),
        sold_at=sold_at,
        account_age_days=account_age_days,
        order_ref=order.order_ref,
        buyer_display=buyer_display,
        buyer_telegram_id=buyer_telegram_id,
        raw_text=stock.raw_text,
        is_moved_to_used=(stock.stock_status == GITHUB_PACK_STOCK_STATUS_MOVED_TO_USED),
    )


def move_sold_github_stock_to_used_product(
    session: Session,
    sold_stock_id: int,
    actor_id: int | None,
) -> GithubSoldStockMoveResult:
    github_pack_product = ensure_github_pack_product(session)
    used_product = ensure_github_pack_used_product(session)

    row = session.execute(
        select(StockUnit, Order)
        .join(Order, Order.id == StockUnit.sold_order_id)
        .where(
            StockUnit.id == sold_stock_id,
            StockUnit.product_id == github_pack_product.id,
            StockUnit.is_sold.is_(True),
            StockUnit.sold_order_id.is_not(None),
        )
    ).first()
    if row is None:
        raise ValueError("Akun terjual tidak ditemukan.")

    sold_stock, sold_order = row
    if sold_stock.stock_status == GITHUB_PACK_STOCK_STATUS_MOVED_TO_USED:
        raise ValueError("Akun ini sudah dipindahkan ke produk GHS Bekas.")

    copied_username = _extract_username_from_parsed_json(sold_stock.parsed_json)
    relisted_stock = StockUnit(
        product_id=used_product.id,
        raw_text=sold_stock.raw_text,
        parsed_json=sold_stock.parsed_json,
        stock_status=STOCK_STATUS_READY,
        available_at=None,
        username_key=_normalize_username(copied_username),
        is_sold=False,
        sold_order_id=None,
    )
    session.add(relisted_stock)

    sold_stock.stock_status = GITHUB_PACK_STOCK_STATUS_MOVED_TO_USED
    session.add(sold_stock)
    session.flush()

    append_audit(
        session,
        action="github_sold_stock_move_to_used",
        actor_id=actor_id,
        entity_type="stock_unit",
        entity_id=str(sold_stock.id),
        detail=(
            f"from_product={github_pack_product.id}; to_product={used_product.id}; "
            f"new_stock_id={relisted_stock.id}; order_ref={sold_order.order_ref}; username={copied_username}"
        ),
    )

    return GithubSoldStockMoveResult(
        source_stock_id=int(sold_stock.id),
        source_username=copied_username,
        used_product_id=int(used_product.id),
        used_product_name=used_product.name,
        used_stock_id=int(relisted_stock.id),
    )


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
