from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.bot.services.catalog_service import get_available_stock_count, get_product
from app.db.models import Product, RestockSubscription, User


@dataclass
class RestockNotifyCandidate:
    subscription_id: int
    customer_telegram_id: int
    product_id: int
    product_name: str
    product_price: int
    stock_available: int


def _utcnow() -> datetime:
    return datetime.utcnow()


def subscribe_restock(
    session: Session,
    customer_id: int,
    product_id: int,
) -> tuple[bool, str]:
    product = get_product(session, product_id)
    if product is None:
        return False, "Produk tidak ditemukan."

    active = session.scalar(
        select(RestockSubscription).where(
            RestockSubscription.customer_id == customer_id,
            RestockSubscription.product_id == product_id,
            RestockSubscription.is_active.is_(True),
        )
    )
    if active is not None:
        return False, "Notifikasi restock untuk produk ini sudah aktif."

    sub = RestockSubscription(
        customer_id=customer_id,
        product_id=product_id,
        is_active=True,
    )
    session.add(sub)
    return True, "Notifikasi restock berhasil diaktifkan."


def list_ready_restock_notifications(
    session: Session,
    limit: int = 50,
) -> list[RestockNotifyCandidate]:
    rows = list(
        session.execute(
            select(RestockSubscription, User, Product)
            .join(User, User.id == RestockSubscription.customer_id)
            .join(Product, Product.id == RestockSubscription.product_id)
            .where(RestockSubscription.is_active.is_(True))
            .order_by(RestockSubscription.id.asc())
            .limit(max(1, int(limit)))
        ).all()
    )

    result: list[RestockNotifyCandidate] = []
    for sub, user, product in rows:
        if user.telegram_id is None:
            continue
        if product.is_suspended:
            continue

        stock_available = get_available_stock_count(session, product.id)
        if stock_available <= 0:
            continue

        result.append(
            RestockNotifyCandidate(
                subscription_id=int(sub.id),
                customer_telegram_id=int(user.telegram_id),
                product_id=int(product.id),
                product_name=product.name,
                product_price=product.price,
                stock_available=stock_available,
            )
        )

    return result


def mark_restock_notified(session: Session, subscription_id: int) -> bool:
    sub = session.get(RestockSubscription, subscription_id)
    if sub is None or not sub.is_active:
        return False

    sub.is_active = False
    sub.notified_at = _utcnow()
    session.add(sub)
    session.flush()
    return True
