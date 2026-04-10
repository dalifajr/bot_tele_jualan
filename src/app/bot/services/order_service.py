from __future__ import annotations

import json
import random
from datetime import datetime, timedelta

from sqlalchemy import or_, select
from sqlalchemy.orm import Session

from app.bot.services.audit_service import append_audit
from app.bot.services.catalog_service import get_available_stock_count
from app.common.config import get_settings
from app.db.models import Order, OrderItem, Payment, Product, StockUnit, User

settings = get_settings()


def _generate_order_ref() -> str:
    timestamp = datetime.utcnow().strftime("%Y%m%d%H%M%S")
    suffix = random.randint(100, 999)
    return f"ORD{timestamp}{suffix}"


def _generate_unique_code() -> int:
    return random.randint(101, 999)


def create_checkout(session: Session, customer: User, product_id: int, quantity: int) -> tuple[Order, Payment]:
    if quantity <= 0:
        raise ValueError("Jumlah pesanan harus lebih dari 0.")

    product = session.get(Product, product_id)
    if product is None:
        raise ValueError("Produk tidak ditemukan.")
    if product.is_suspended:
        raise ValueError("Produk sedang suspend.")

    available_stock = get_available_stock_count(session, product_id)
    if available_stock < quantity:
        raise ValueError(f"Stok tidak cukup. Tersedia {available_stock} unit.")

    order_ref = _generate_order_ref()
    subtotal = product.price * quantity
    unique_code = _generate_unique_code()
    total = subtotal + unique_code

    order = Order(
        order_ref=order_ref,
        customer_id=customer.id,
        subtotal=subtotal,
        unique_code=unique_code,
        total_amount=total,
        status="pending_payment",
    )
    session.add(order)
    session.flush()

    item = OrderItem(
        order_id=order.id,
        product_id=product.id,
        quantity=quantity,
        unit_price=product.price,
    )
    session.add(item)

    payment = Payment(
        order_id=order.id,
        payment_ref=f"PAY-{order_ref}",
        expected_amount=total,
        status="pending",
    )
    session.add(payment)

    append_audit(
        session,
        action="order_create",
        actor_id=customer.id,
        entity_type="order",
        entity_id=str(order.id),
        detail=f"product_id={product.id}; qty={quantity}; total={total}",
    )

    session.flush()
    return order, payment


def list_recent_orders_by_customer(session: Session, customer_id: int, limit: int = 10) -> list[Order]:
    stmt = (
        select(Order)
        .where(Order.customer_id == customer_id)
        .order_by(Order.id.desc())
        .limit(limit)
    )
    return list(session.scalars(stmt).all())


def _allocate_stock_fifo(session: Session, product_id: int, quantity: int, order_id: int) -> list[StockUnit]:
    stmt = (
        select(StockUnit)
        .where(StockUnit.product_id == product_id, StockUnit.is_sold.is_(False))
        .order_by(StockUnit.id.asc())
        .limit(quantity)
    )
    units = list(session.scalars(stmt).all())
    if len(units) < quantity:
        raise ValueError("Stok tidak cukup saat delivery.")

    for unit in units:
        unit.is_sold = True
        unit.sold_order_id = order_id
        session.add(unit)

    return units


def _build_delivery_message(order: Order, units: list[StockUnit]) -> str:
    parts = [
        "Pembayaran berhasil dikonfirmasi.",
        f"Order: {order.order_ref}",
        "Berikut data pesanan Anda:",
    ]

    for idx, unit in enumerate(units, start=1):
        parts.append(f"\n[{idx}]\n{unit.raw_text}")

    return "\n".join(parts)


def _resolve_pending_payment(
    session: Session,
    amount: int,
    reference: str | None,
) -> tuple[str, str, Payment | None]:
    base_stmt = (
        select(Payment)
        .join(Order, Payment.order_id == Order.id)
        .where(Payment.status == "pending", Order.status == "pending_payment")
    )

    if reference:
        candidates = list(
            session.scalars(
                base_stmt.where(or_(Payment.payment_ref == reference, Order.order_ref == reference))
            ).all()
        )
        if not candidates:
            historical_paid = session.scalar(
                select(Payment)
                .join(Order, Payment.order_id == Order.id)
                .where(
                    or_(Payment.payment_ref == reference, Order.order_ref == reference),
                    Payment.status == "paid",
                )
            )
            if historical_paid is not None:
                return "duplicate", "Reference pembayaran sudah diproses sebelumnya.", historical_paid
            return "not_found", "Reference tidak ditemukan pada order pending.", None

        exact_amount = [payment for payment in candidates if payment.expected_amount == amount]
        if not exact_amount:
            return "amount_mismatch", "Reference ditemukan tapi nominal tidak sesuai.", None
        if len(exact_amount) > 1:
            return "ambiguous", "Reference cocok ke lebih dari satu pembayaran pending.", None
        return "matched", "ok", exact_amount[0]

    if settings.listener_require_reference:
        return "reference_required", "Reference wajib dikirim untuk konfirmasi pembayaran.", None

    min_created_at = datetime.utcnow() - timedelta(
        minutes=max(1, settings.listener_payment_match_window_minutes)
    )
    candidates = list(
        session.scalars(
            base_stmt.where(Payment.expected_amount == amount, Order.created_at >= min_created_at)
        ).all()
    )

    if not candidates:
        return "not_found", "Tidak ada order pending yang cocok di window waktu aktif.", None
    if len(candidates) > 1:
        return "ambiguous", "Nominal cocok ke beberapa order. Kirim reference untuk memastikan.", None
    return "matched", "ok", candidates[0]


def reconcile_payment(
    session: Session,
    amount: int,
    source_app: str,
    reference: str | None = None,
    raw_payload: dict | None = None,
) -> tuple[str, str, int | None, str | None]:
    match_status, match_message, payment = _resolve_pending_payment(
        session=session,
        amount=amount,
        reference=reference,
    )
    if payment is None:
        return match_status, match_message, None, None

    order = payment.order
    if order is None:
        return "error", "Order tidak ditemukan untuk pembayaran ini.", None, None

    customer = session.get(User, order.customer_id)
    customer_telegram_id = customer.telegram_id if customer else None

    if payment.status == "paid":
        return "duplicate", "Pembayaran sudah diproses sebelumnya.", customer_telegram_id, None

    order_items = list(order.items)
    delivered_units: list[StockUnit] = []

    for item in order_items:
        units = _allocate_stock_fifo(session, item.product_id, item.quantity, order.id)
        delivered_units.extend(units)

    payload_text = json.dumps(raw_payload or {}, ensure_ascii=False)
    payment.status = "paid"
    payment.received_amount = amount
    payment.source_app = source_app
    payment.payload_json = payload_text
    payment.matched_at = datetime.utcnow()

    order.status = "delivered"
    order.paid_at = datetime.utcnow()
    order.delivered_at = datetime.utcnow()

    session.add(order)
    session.add(payment)

    append_audit(
        session,
        action="payment_confirmed",
        entity_type="payment",
        entity_id=str(payment.id),
        detail=f"amount={amount}; source_app={source_app}",
    )

    delivery_message = _build_delivery_message(order, delivered_units)
    return "paid", "Pembayaran berhasil dan stok terkirim.", customer_telegram_id, delivery_message
