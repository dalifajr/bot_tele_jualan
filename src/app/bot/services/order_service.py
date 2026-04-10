from __future__ import annotations

import html
import json
import random
from dataclasses import dataclass
from datetime import datetime, timedelta

from sqlalchemy import func, or_, select
from sqlalchemy.orm import Session

from app.bot.services.audit_service import append_audit
from app.bot.services.catalog_service import (
    STOCK_STATUS_READY,
    get_available_stock_count,
    promote_awaiting_stocks,
)
from app.common.config import get_settings
from app.db.models import Order, OrderItem, Payment, Product, StockUnit, User

settings = get_settings()


@dataclass
class AdminOrderNotification:
    order_ref: str
    customer_username: str
    customer_telegram_id: int
    item_name: str
    quantity: int
    total_amount: int
    status: str
    admin_chat_id: int | None = None
    admin_message_id: int | None = None


@dataclass
class ReconcileResult:
    status: str
    message: str
    customer_chat_id: int | None
    delivery_message: str | None
    checkout_chat_id: int | None = None
    checkout_message_id: int | None = None
    admin_notification: AdminOrderNotification | None = None


@dataclass
class CancelOrderResult:
    ok: bool
    message: str
    admin_notification: AdminOrderNotification | None = None


def _generate_order_ref() -> str:
    timestamp = datetime.utcnow().strftime("%Y%m%d%H%M%S")
    suffix = random.randint(100, 999)
    return f"ORD{timestamp}{suffix}"


def _generate_unique_code() -> int:
    return random.randint(1, 100)


def _utcnow() -> datetime:
    return datetime.utcnow()


def _format_rupiah(amount: int) -> str:
    return f"Rp{amount:,}".replace(",", ".")


def _normalize_customer_name(customer: User | None) -> str:
    if customer is None:
        return "-"
    if customer.username:
        return f"@{customer.username}"
    if customer.full_name:
        return customer.full_name
    return "-"


def _build_admin_order_notification(
    session: Session,
    order: Order,
    status_override: str | None = None,
) -> AdminOrderNotification:
    customer = session.get(User, order.customer_id)
    customer_name = _normalize_customer_name(customer)
    customer_telegram_id = int(customer.telegram_id) if customer is not None else 0

    order_items = list(order.items)
    total_qty = sum(item.quantity for item in order_items)
    item_name = "-"
    if order_items:
        first_item = order_items[0]
        product = session.get(Product, first_item.product_id)
        item_name = product.name if product is not None else f"Produk #{first_item.product_id}"
        if len(order_items) > 1:
            item_name = f"{item_name} (+{len(order_items) - 1} item)"

    return AdminOrderNotification(
        order_ref=order.order_ref,
        customer_username=customer_name,
        customer_telegram_id=customer_telegram_id,
        item_name=item_name,
        quantity=total_qty,
        total_amount=order.total_amount,
        status=status_override or order.status,
        admin_chat_id=order.admin_notify_chat_id,
        admin_message_id=order.admin_notify_message_id,
    )


def build_admin_order_message(notification: AdminOrderNotification) -> str:
    title_map: dict[str, tuple[str, str]] = {
        "pending_payment": ("Pesanan baru diterima!", "Waiting for payment"),
        "delivered": ("Pesanan telah selesai!", "Success delivered"),
        "cancelled": ("Pesanan dibatalkan!", "Cancelled by customer"),
        "expired": ("Pesanan kedaluwarsa!", "Payment timeout"),
    }
    title_text, status_text = title_map.get(
        notification.status,
        ("Update status pesanan", notification.status),
    )

    safe_name = html.escape(notification.customer_username)
    safe_item = html.escape(notification.item_name)
    safe_title = html.escape(title_text)
    safe_status = html.escape(status_text)

    return "\n".join(
        [
            f"<b><i>{safe_title}</i></b>",
            f"Nama: {safe_name}, {notification.customer_telegram_id}",
            f"Item; {safe_item}",
            f"Qty: {notification.quantity}",
            f"Pembayaran: {_format_rupiah(notification.total_amount)}",
            "",
            f"Status: <b><i>{safe_status}</i></b>",
        ]
    )


def get_order_admin_notification(
    session: Session,
    order_ref: str,
    status_override: str | None = None,
) -> AdminOrderNotification | None:
    order = session.scalar(select(Order).where(Order.order_ref == order_ref))
    if order is None:
        return None
    return _build_admin_order_notification(session, order, status_override=status_override)


def expire_pending_orders_with_notifications(session: Session) -> list[AdminOrderNotification]:
    now = _utcnow()
    orders = list(
        session.scalars(
            select(Order).where(
                Order.status == "pending_payment",
                Order.expires_at.is_not(None),
                Order.expires_at <= now,
            )
        ).all()
    )

    notifications: list[AdminOrderNotification] = []
    for order in orders:
        order.status = "expired"
        order.cancelled_at = now
        order.cancel_reason = "payment_timeout"
        payment = order.payment
        if payment is not None and payment.status == "pending":
            payment.status = "expired"
            session.add(payment)
        session.add(order)
        notifications.append(_build_admin_order_notification(session, order, status_override="expired"))

    return notifications


def expire_pending_orders(session: Session) -> int:
    return len(expire_pending_orders_with_notifications(session))


def create_checkout(session: Session, customer: User, product_id: int, quantity: int) -> tuple[Order, Payment]:
    if quantity <= 0:
        raise ValueError("Jumlah pesanan harus lebih dari 0.")

    product = session.get(Product, product_id)
    if product is None:
        raise ValueError("Produk tidak ditemukan.")
    if product.is_suspended:
        raise ValueError("Produk sedang suspend.")

    promote_awaiting_stocks(session, product_id=product_id)
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
        expires_at=_utcnow() + timedelta(minutes=max(1, settings.checkout_expiry_minutes)),
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
    expire_pending_orders(session)
    stmt = (
        select(Order)
        .where(Order.customer_id == customer_id)
        .order_by(Order.id.desc())
        .limit(limit)
    )
    return list(session.scalars(stmt).all())


def count_delivered_orders_by_customer(session: Session, customer_id: int) -> int:
    return (
        session.scalar(
            select(func.count(Order.id)).where(
                Order.customer_id == customer_id,
                Order.status == "delivered",
            )
        )
        or 0
    )


def _allocate_stock_fifo(session: Session, product_id: int, quantity: int, order_id: int) -> list[StockUnit]:
    promote_awaiting_stocks(session, product_id=product_id)
    stmt = (
        select(StockUnit)
        .where(
            StockUnit.product_id == product_id,
            StockUnit.is_sold.is_(False),
            or_(
                StockUnit.stock_status == STOCK_STATUS_READY,
                StockUnit.stock_status.is_(None),
            ),
        )
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
    expire_pending_orders(session)
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
            historical_payment = session.scalar(
                select(Payment)
                .join(Order, Payment.order_id == Order.id)
                .where(
                    or_(Payment.payment_ref == reference, Order.order_ref == reference),
                    Payment.status.in_(["paid", "expired", "cancelled"]),
                )
            )
            if historical_payment is not None:
                if historical_payment.status == "paid":
                    return "duplicate", "Reference pembayaran sudah diproses sebelumnya.", historical_payment
                return "expired", "Order untuk reference ini sudah tidak aktif.", None
            return "not_found", "Reference tidak ditemukan pada order pending.", None

        exact_amount = [payment for payment in candidates if payment.expected_amount == amount]
        if not exact_amount:
            return "amount_mismatch", "Reference ditemukan tapi nominal tidak sesuai.", None
        if len(exact_amount) > 1:
            return "ambiguous", "Reference cocok ke lebih dari satu pembayaran pending.", None
        picked = exact_amount[0]
        if picked.order and picked.order.expires_at and picked.order.expires_at <= _utcnow():
            picked.order.status = "expired"
            picked.order.cancelled_at = _utcnow()
            picked.order.cancel_reason = "payment_timeout"
            picked.status = "expired"
            session.add(picked.order)
            session.add(picked)
            return "expired", "Order sudah kedaluwarsa (lebih dari 5 menit).", None
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

    picked = candidates[0]
    if picked.order and picked.order.expires_at and picked.order.expires_at <= _utcnow():
        picked.order.status = "expired"
        picked.order.cancelled_at = _utcnow()
        picked.order.cancel_reason = "payment_timeout"
        picked.status = "expired"
        session.add(picked.order)
        session.add(picked)
        return "expired", "Order sudah kedaluwarsa (lebih dari 5 menit).", None

    return "matched", "ok", candidates[0]


def reconcile_payment(
    session: Session,
    amount: int,
    source_app: str,
    reference: str | None = None,
    raw_payload: dict | None = None,
) -> ReconcileResult:
    match_status, match_message, payment = _resolve_pending_payment(
        session=session,
        amount=amount,
        reference=reference,
    )
    if payment is None:
        return ReconcileResult(match_status, match_message, None, None)

    order = payment.order
    if order is None:
        return ReconcileResult("error", "Order tidak ditemukan untuk pembayaran ini.", None, None)

    customer = session.get(User, order.customer_id)
    customer_telegram_id = customer.telegram_id if customer else None

    if payment.status == "paid":
        return ReconcileResult(
            "duplicate",
            "Pembayaran sudah diproses sebelumnya.",
            customer_telegram_id,
            None,
            checkout_chat_id=order.checkout_chat_id,
            checkout_message_id=order.checkout_message_id,
        )

    if order.status in {"cancelled", "expired"}:
        return ReconcileResult(
            "expired",
            "Order tidak aktif (dibatalkan atau kedaluwarsa).",
            customer_telegram_id,
            None,
            checkout_chat_id=order.checkout_chat_id,
            checkout_message_id=order.checkout_message_id,
            admin_notification=_build_admin_order_notification(session, order),
        )

    if order.expires_at and order.expires_at <= _utcnow():
        order.status = "expired"
        order.cancelled_at = _utcnow()
        order.cancel_reason = "payment_timeout"
        if payment.status == "pending":
            payment.status = "expired"
            session.add(payment)
        session.add(order)
        return ReconcileResult(
            "expired",
            "Order sudah kedaluwarsa (lebih dari 5 menit).",
            customer_telegram_id,
            None,
            checkout_chat_id=order.checkout_chat_id,
            checkout_message_id=order.checkout_message_id,
            admin_notification=_build_admin_order_notification(session, order, status_override="expired"),
        )

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
    payment.matched_at = _utcnow()

    order.status = "delivered"
    order.paid_at = _utcnow()
    order.delivered_at = _utcnow()

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
    return ReconcileResult(
        "paid",
        "Pembayaran berhasil dan stok terkirim.",
        customer_telegram_id,
        delivery_message,
        checkout_chat_id=order.checkout_chat_id,
        checkout_message_id=order.checkout_message_id,
        admin_notification=_build_admin_order_notification(session, order, status_override="delivered"),
    )


def set_checkout_message_ref(session: Session, order_ref: str, chat_id: int, message_id: int) -> None:
    order = session.scalar(select(Order).where(Order.order_ref == order_ref))
    if order is None:
        return
    order.checkout_chat_id = chat_id
    order.checkout_message_id = message_id
    session.add(order)


def set_admin_message_ref(session: Session, order_ref: str, chat_id: int, message_id: int) -> None:
    order = session.scalar(select(Order).where(Order.order_ref == order_ref))
    if order is None:
        return
    order.admin_notify_chat_id = chat_id
    order.admin_notify_message_id = message_id
    session.add(order)


def cancel_order(session: Session, order_ref: str, customer_id: int) -> CancelOrderResult:
    expire_pending_orders(session)
    order = session.scalar(
        select(Order).where(
            Order.order_ref == order_ref,
            Order.customer_id == customer_id,
        )
    )
    if order is None:
        return CancelOrderResult(False, "Order tidak ditemukan.")

    if order.status == "delivered":
        return CancelOrderResult(False, "Order sudah delivered dan tidak bisa dibatalkan.")
    if order.status == "cancelled":
        return CancelOrderResult(False, "Order sudah dibatalkan sebelumnya.")
    if order.status == "expired":
        return CancelOrderResult(False, "Order sudah kedaluwarsa.")

    order.status = "cancelled"
    order.cancelled_at = _utcnow()
    order.cancel_reason = "cancelled_by_customer"
    session.add(order)

    payment = order.payment
    if payment is not None and payment.status == "pending":
        payment.status = "cancelled"
        session.add(payment)

    append_audit(
        session,
        action="order_cancelled",
        actor_id=customer_id,
        entity_type="order",
        entity_id=str(order.id),
        detail=f"order_ref={order_ref}",
    )

    return CancelOrderResult(
        True,
        "✅ Pesanan berhasil dibatalkan.",
        admin_notification=_build_admin_order_notification(session, order, status_override="cancelled"),
    )
