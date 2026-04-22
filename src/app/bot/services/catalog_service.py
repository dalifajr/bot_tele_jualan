from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta

from sqlalchemy import Select, func, or_, select, update
from sqlalchemy.orm import Session

from app.bot.services.audit_service import append_audit
from app.bot.services.stock_parser import parse_stock_block
from app.db.models import Product, StockUnit

STOCK_STATUS_READY = "ready"
STOCK_STATUS_AWAITING = "awaiting_benefits"


@dataclass
class ProductView:
    id: int
    name: str
    description: str
    price: int
    is_suspended: bool
    stock_available: int


@dataclass(frozen=True)
class AwaitingReadyAtView:
    available_at: datetime
    account_count: int


def _build_stock_count_query() -> Select:
    return (
        select(StockUnit.product_id, func.count(StockUnit.id).label("stock_count"))
        .where(
            StockUnit.is_sold.is_(False),
            StockUnit.sold_order_id.is_(None),
            or_(
                StockUnit.stock_status == STOCK_STATUS_READY,
                StockUnit.stock_status.is_(None),
            ),
        )
        .group_by(StockUnit.product_id)
    )


def promote_awaiting_stocks(session: Session, product_id: int | None = None) -> int:
    stmt = (
        update(StockUnit)
        .where(
            StockUnit.is_sold.is_(False),
            StockUnit.stock_status == STOCK_STATUS_AWAITING,
            StockUnit.available_at.is_not(None),
            StockUnit.available_at <= datetime.utcnow(),
        )
        .values(stock_status=STOCK_STATUS_READY, available_at=None)
    )
    if product_id is not None:
        stmt = stmt.where(StockUnit.product_id == product_id)

    result = session.execute(stmt)
    return int(result.rowcount or 0)


def _to_view(product: Product, stock_available: int) -> ProductView:
    return ProductView(
        id=product.id,
        name=product.name,
        description=product.description,
        price=product.price,
        is_suspended=product.is_suspended,
        stock_available=stock_available,
    )


def list_products(session: Session, include_suspended: bool = False) -> list[ProductView]:
    promote_awaiting_stocks(session)
    stock_counts = {pid: count for pid, count in session.execute(_build_stock_count_query()).all()}

    stmt = select(Product).order_by(Product.id.asc())
    if not include_suspended:
        stmt = stmt.where(Product.is_suspended.is_(False))

    products = session.scalars(stmt).all()
    result: list[ProductView] = []
    for product in products:
        result.append(_to_view(product, stock_counts.get(product.id, 0)))
    return result


def get_product(session: Session, product_id: int) -> Product | None:
    return session.get(Product, product_id)


def get_available_stock_count(session: Session, product_id: int) -> int:
    promote_awaiting_stocks(session, product_id=product_id)
    return (
        session.scalar(
            select(func.count(StockUnit.id)).where(
                StockUnit.product_id == product_id,
                StockUnit.is_sold.is_(False),
                StockUnit.sold_order_id.is_(None),
                or_(
                    StockUnit.stock_status == STOCK_STATUS_READY,
                    StockUnit.stock_status.is_(None),
                ),
            )
        )
        or 0
    )


def get_nearest_awaiting_ready_at(session: Session, product_id: int) -> AwaitingReadyAtView | None:
    promote_awaiting_stocks(session, product_id=product_id)
    now = datetime.utcnow()
    awaiting_rows = [
        value
        for value in session.scalars(
            select(StockUnit.available_at)
            .where(
                StockUnit.product_id == product_id,
                StockUnit.is_sold.is_(False),
                StockUnit.stock_status == STOCK_STATUS_AWAITING,
                StockUnit.available_at.is_not(None),
            )
            .order_by(StockUnit.available_at.asc(), StockUnit.id.asc())
        ).all()
        if value is not None
    ]
    if not awaiting_rows:
        return None

    future_rows = [value for value in awaiting_rows if value >= now]
    nearest_start = future_rows[0] if future_rows else min(
        awaiting_rows,
        key=lambda value: abs((value - now).total_seconds()),
    )
    window_end = nearest_start + timedelta(days=1)
    window_rows = [value for value in awaiting_rows if nearest_start <= value <= window_end]
    if not window_rows:
        window_rows = [nearest_start]

    estimate_at = max(window_rows)
    account_count = len(window_rows)

    return AwaitingReadyAtView(
        available_at=estimate_at,
        account_count=max(1, int(account_count)),
    )


def add_product(session: Session, name: str, price: int, description: str, actor_id: int | None) -> Product:
    normalized_name = name.strip()
    normalized_description = description.strip()

    product = session.scalar(
        select(Product).where(func.lower(Product.name) == normalized_name.lower())
    )

    if product is None:
        product = Product(name=normalized_name, price=price, description=normalized_description)
        session.add(product)
        session.flush()
        append_audit(
            session,
            action="product_add",
            actor_id=actor_id,
            entity_type="product",
            entity_id=str(product.id),
            detail=f"name={product.name}; price={product.price}",
        )
        return product

    old_price = product.price
    old_description = product.description
    product.price = price
    product.description = normalized_description
    session.add(product)
    session.flush()
    append_audit(
        session,
        action="product_upsert",
        actor_id=actor_id,
        entity_type="product",
        entity_id=str(product.id),
        detail=(
            f"name={product.name}; old_price={old_price}; new_price={product.price}; "
            f"old_desc={old_description}; new_desc={product.description}"
        ),
    )
    return product


def suspend_product(session: Session, product_id: int, suspended: bool, actor_id: int | None) -> Product:
    product = session.get(Product, product_id)
    if product is None:
        raise ValueError("Produk tidak ditemukan.")
    product.is_suspended = suspended
    session.add(product)
    append_audit(
        session,
        action="product_suspend" if suspended else "product_unsuspend",
        actor_id=actor_id,
        entity_type="product",
        entity_id=str(product.id),
    )
    return product


def delete_product(session: Session, product_id: int, actor_id: int | None) -> None:
    product = session.get(Product, product_id)
    if product is None:
        raise ValueError("Produk tidak ditemukan.")
    session.delete(product)
    append_audit(
        session,
        action="product_delete",
        actor_id=actor_id,
        entity_type="product",
        entity_id=str(product_id),
    )


def add_stock_block(session: Session, product_id: int, raw_text: str, actor_id: int | None) -> StockUnit:
    product = session.get(Product, product_id)
    if product is None:
        raise ValueError("Produk tidak ditemukan.")

    parsed = parse_stock_block(raw_text)
    stock = StockUnit(
        product_id=product_id,
        raw_text=raw_text.strip(),
        parsed_json=parsed.as_json(),
        stock_status=STOCK_STATUS_READY,
        username_key=(parsed.fields.get("Username") or parsed.fields.get("username") or "").strip().lower() or None,
    )
    session.add(stock)
    session.flush()

    append_audit(
        session,
        action="stock_add",
        actor_id=actor_id,
        entity_type="stock_unit",
        entity_id=str(stock.id),
        detail=f"product_id={product_id}",
    )
    return stock
