from __future__ import annotations

import random
import string
from dataclasses import dataclass
from datetime import datetime, timedelta

from sqlalchemy import or_, select
from sqlalchemy.orm import Session

from app.common.config import get_settings
from app.db.models import LoyaltyVoucher, Order

settings = get_settings()


@dataclass
class CustomerVoucherView:
    code: str
    discount_amount: int
    min_order_amount: int
    status: str
    expires_at: datetime | None


def _utcnow() -> datetime:
    return datetime.utcnow()


def _generate_voucher_code(prefix: str = "LOY") -> str:
    suffix = "".join(random.choice(string.ascii_uppercase + string.digits) for _ in range(8))
    return f"{prefix}-{suffix}"


def _expire_outdated_vouchers(session: Session, customer_id: int | None = None) -> None:
    now = _utcnow()
    stmt = select(LoyaltyVoucher).where(
        LoyaltyVoucher.status.in_(["active", "reserved"]),
        LoyaltyVoucher.expires_at.is_not(None),
        LoyaltyVoucher.expires_at <= now,
    )
    if customer_id is not None:
        stmt = stmt.where(LoyaltyVoucher.customer_id == customer_id)

    rows = list(session.scalars(stmt).all())
    for voucher in rows:
        voucher.status = "expired"
        voucher.reserved_order_id = None
        session.add(voucher)


def list_customer_vouchers(
    session: Session,
    customer_id: int,
    include_used: bool = False,
) -> list[CustomerVoucherView]:
    _expire_outdated_vouchers(session, customer_id=customer_id)

    stmt = select(LoyaltyVoucher).where(LoyaltyVoucher.customer_id == customer_id)
    if not include_used:
        stmt = stmt.where(LoyaltyVoucher.status.in_(["active", "reserved"]))

    vouchers = list(session.scalars(stmt.order_by(LoyaltyVoucher.id.desc())).all())
    return [
        CustomerVoucherView(
            code=v.code,
            discount_amount=v.discount_amount,
            min_order_amount=v.min_order_amount,
            status=v.status,
            expires_at=v.expires_at,
        )
        for v in vouchers
    ]


def allocate_voucher_for_checkout(
    session: Session,
    customer_id: int,
    subtotal_amount: int,
) -> LoyaltyVoucher | None:
    if not settings.loyalty_enabled:
        return None

    _expire_outdated_vouchers(session, customer_id=customer_id)

    now = _utcnow()
    voucher = session.scalar(
        select(LoyaltyVoucher)
        .where(
            LoyaltyVoucher.customer_id == customer_id,
            LoyaltyVoucher.status == "active",
            LoyaltyVoucher.min_order_amount <= max(0, int(subtotal_amount)),
            or_(
                LoyaltyVoucher.expires_at.is_(None),
                LoyaltyVoucher.expires_at > now,
            ),
        )
        .order_by(LoyaltyVoucher.discount_amount.desc(), LoyaltyVoucher.id.asc())
    )

    if voucher is None:
        return None

    voucher.status = "reserved"
    session.add(voucher)
    return voucher


def release_voucher_for_order(session: Session, order: Order) -> None:
    if order.applied_voucher_id is None:
        return

    voucher = session.get(LoyaltyVoucher, order.applied_voucher_id)
    if voucher is None:
        return

    if voucher.status == "reserved" and (voucher.reserved_order_id is None or voucher.reserved_order_id == order.id):
        voucher.status = "active"
        voucher.reserved_order_id = None
        session.add(voucher)


def consume_voucher_for_order(session: Session, order: Order) -> None:
    if order.applied_voucher_id is None:
        return

    voucher = session.get(LoyaltyVoucher, order.applied_voucher_id)
    if voucher is None:
        return

    if voucher.status in {"active", "reserved"}:
        voucher.status = "used"
        voucher.reserved_order_id = None
        voucher.used_order_id = order.id
        voucher.used_at = _utcnow()
        session.add(voucher)


def issue_milestone_voucher(
    session: Session,
    customer_id: int,
    delivered_count: int,
) -> LoyaltyVoucher | None:
    if not settings.loyalty_enabled:
        return None

    milestone = max(1, int(settings.loyalty_milestone_orders))
    if delivered_count <= 0 or delivered_count % milestone != 0:
        return None

    marker = f"milestone:{delivered_count}"
    exists = session.scalar(
        select(LoyaltyVoucher).where(
            LoyaltyVoucher.customer_id == customer_id,
            LoyaltyVoucher.note == marker,
        )
    )
    if exists is not None:
        return None

    expiry_days = max(1, int(settings.loyalty_voucher_expiry_days))
    discount_amount = max(100, int(settings.loyalty_voucher_discount_amount))
    expires_at = _utcnow() + timedelta(days=expiry_days)

    voucher = LoyaltyVoucher(
        customer_id=customer_id,
        code=_generate_voucher_code(),
        discount_amount=discount_amount,
        min_order_amount=0,
        status="active",
        source="milestone",
        note=marker,
        expires_at=expires_at,
    )
    session.add(voucher)
    session.flush()
    return voucher
