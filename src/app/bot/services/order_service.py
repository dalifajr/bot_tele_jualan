from __future__ import annotations

import html
import json
import random
import secrets
from dataclasses import dataclass
from datetime import datetime, timedelta

from sqlalchemy import func, or_, select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from app.bot.services.audit_service import append_audit
from app.bot.services.catalog_service import (
    STOCK_STATUS_READY,
    get_available_stock_count,
    promote_awaiting_stocks,
)
from app.bot.services.loyalty_service import (
    allocate_voucher_for_checkout,
    consume_voucher_for_order,
    issue_milestone_voucher,
    release_voucher_for_order,
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


@dataclass
class CustomerOrderSummary:
    order_ref: str
    status: str
    total_amount: int
    created_at: datetime


@dataclass
class CustomerOrdersPage:
    rows: list[CustomerOrderSummary]
    page: int
    total_pages: int
    total_items: int


@dataclass
class CustomerOrderDetail:
    order_ref: str
    status: str
    total_amount: int
    created_at: datetime
    paid_at: datetime | None
    delivered_at: datetime | None
    item_lines: list[str]
    account_blocks: list[str]


@dataclass
class CustomerOrderStatusView:
    order_ref: str
    status: str
    total_amount: int
    payment_ref: str | None
    expected_amount: int | None
    created_at: datetime
    expires_at: datetime | None
    paid_at: datetime | None
    delivered_at: datetime | None
    cancelled_at: datetime | None


@dataclass
class QuickReorderTarget:
    source_order_ref: str
    product_id: int
    product_name: str
    quantity: int


@dataclass
class PaymentReminderCandidate:
    order_ref: str
    customer_telegram_id: int
    expected_amount: int
    expires_at: datetime
    remaining_seconds: int


def _generate_order_ref() -> str:
    # Format: ORD + UTC timestamp (microsecond precision) + secure random tail.
    timestamp = datetime.utcnow().strftime("%Y%m%d%H%M%S%f")
    suffix = secrets.token_hex(4).upper()
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
        "pending_payment": ("🆕 <b>Pesanan Baru</b>", "🟡 Menunggu Pembayaran"),
        "delivered": ("✅ <b>Pesanan Selesai</b>", "✅ Berhasil Terkirim"),
        "cancelled": ("❌ <b>Pesanan Dibatalkan</b>", "❌ Dibatalkan Customer"),
        "expired": ("⌛ <b>Pesanan Kedaluwarsa</b>", "⌛ Melewati Batas Bayar"),
    }
    title_text, status_text = title_map.get(
        notification.status,
        ("🔄 <b>Update Status Pesanan</b>", notification.status),
    )

    safe_name = html.escape(notification.customer_username)
    safe_item = html.escape(notification.item_name)
    safe_order_ref = html.escape(notification.order_ref)
    safe_title = title_text
    safe_status = html.escape(status_text)

    return "\n".join(
        [
            safe_title,
            f"Order Ref: <code>{safe_order_ref}</code>",
            f"Customer: {safe_name} ({notification.customer_telegram_id})",
            f"Item: {safe_item}",
            f"Qty: {notification.quantity}",
            f"Total Bayar: <b>{_format_rupiah(notification.total_amount)}</b>",
            "",
            f"Status: <b>{safe_status}</b>",
            "🕒 Update ini dikirim otomatis dari sistem pembayaran.",
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
        release_voucher_for_order(session, order)
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

    subtotal = product.price * quantity
    voucher_discount = 0

    voucher = allocate_voucher_for_checkout(
        session=session,
        customer_id=customer.id,
        subtotal_amount=subtotal,
    )
    if voucher is not None:
        voucher_discount = min(voucher.discount_amount, max(0, subtotal - 1))

    max_ref_attempts = 7
    for _attempt in range(max_ref_attempts):
        order_ref = _generate_order_ref()
        unique_code = _generate_unique_code()
        total = (subtotal - voucher_discount) + unique_code

        try:
            with session.begin_nested():
                order = Order(
                    order_ref=order_ref,
                    customer_id=customer.id,
                    subtotal=subtotal,
                    unique_code=unique_code,
                    total_amount=total,
                    applied_voucher_id=(voucher.id if voucher is not None else None),
                    voucher_discount_amount=voucher_discount,
                    status="pending_payment",
                    expires_at=_utcnow() + timedelta(minutes=max(1, settings.checkout_expiry_minutes)),
                )
                session.add(order)
                session.flush()

                if voucher is not None:
                    voucher.reserved_order_id = order.id
                    session.add(voucher)

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
                    detail=(
                        f"product_id={product.id}; qty={quantity}; subtotal={subtotal}; "
                        f"voucher_discount={voucher_discount}; total={total}"
                    ),
                )

                session.flush()
                return order, payment
        except IntegrityError as exc:
            error_text = str(exc)
            if (
                "orders.order_ref" not in error_text
                and "payments.payment_ref" not in error_text
            ):
                raise
            continue

    raise ValueError("Sistem sedang sibuk. Silakan coba lagi dalam beberapa detik.")


def list_recent_orders_by_customer(session: Session, customer_id: int, limit: int = 10) -> list[Order]:
    expire_pending_orders(session)
    stmt = (
        select(Order)
        .where(Order.customer_id == customer_id)
        .order_by(Order.id.desc())
        .limit(limit)
    )
    return list(session.scalars(stmt).all())


def get_customer_orders_page(
    session: Session,
    customer_id: int,
    page: int,
    page_size: int = 10,
) -> CustomerOrdersPage:
    expire_pending_orders(session)

    safe_page_size = max(1, int(page_size))
    total_items = (
        session.scalar(
            select(func.count(Order.id)).where(Order.customer_id == customer_id)
        )
        or 0
    )

    total_pages = max(1, (int(total_items) + safe_page_size - 1) // safe_page_size)
    safe_page = max(1, min(int(page), total_pages))

    if total_items <= 0:
        return CustomerOrdersPage(rows=[], page=1, total_pages=1, total_items=0)

    offset = (safe_page - 1) * safe_page_size
    rows = list(
        session.scalars(
            select(Order)
            .where(Order.customer_id == customer_id)
            .order_by(Order.id.desc())
            .offset(offset)
            .limit(safe_page_size)
        ).all()
    )

    return CustomerOrdersPage(
        rows=[
            CustomerOrderSummary(
                order_ref=row.order_ref,
                status=row.status,
                total_amount=row.total_amount,
                created_at=row.created_at,
            )
            for row in rows
        ],
        page=safe_page,
        total_pages=total_pages,
        total_items=int(total_items),
    )


def get_customer_order_detail(
    session: Session,
    customer_id: int,
    order_ref: str,
) -> CustomerOrderDetail | None:
    expire_pending_orders(session)

    order = session.scalar(
        select(Order).where(
            Order.customer_id == customer_id,
            Order.order_ref == order_ref,
        )
    )
    if order is None:
        return None

    item_lines: list[str] = []
    for item in order.items:
        product = session.get(Product, item.product_id)
        product_name = product.name if product is not None else f"Produk #{item.product_id}"
        item_lines.append(f"{product_name} x{item.quantity}")

    account_units = list(
        session.scalars(
            select(StockUnit)
            .where(StockUnit.sold_order_id == order.id)
            .order_by(StockUnit.id.asc())
        ).all()
    )

    return CustomerOrderDetail(
        order_ref=order.order_ref,
        status=order.status,
        total_amount=order.total_amount,
        created_at=order.created_at,
        paid_at=order.paid_at,
        delivered_at=order.delivered_at,
        item_lines=item_lines,
        account_blocks=[unit.raw_text for unit in account_units],
    )


def get_quick_reorder_target(
    session: Session,
    customer_id: int,
    source_order_ref: str,
) -> QuickReorderTarget:
    expire_pending_orders(session)

    order = session.scalar(
        select(Order).where(
            Order.customer_id == customer_id,
            Order.order_ref == source_order_ref,
        )
    )
    if order is None:
        raise ValueError("Order tidak ditemukan.")
    if order.status != "delivered":
        raise ValueError("Quick reorder hanya tersedia untuk pesanan yang sudah berhasil.")

    order_items = list(order.items)
    if not order_items:
        raise ValueError("Item pesanan tidak ditemukan.")
    if len(order_items) != 1:
        raise ValueError("Quick reorder untuk pesanan multi-item belum didukung.")

    item = order_items[0]
    qty = max(1, int(item.quantity))

    product = session.get(Product, item.product_id)
    if product is None:
        raise ValueError("Produk dari pesanan ini sudah tidak tersedia.")
    if product.is_suspended:
        raise ValueError("Produk dari pesanan ini sedang nonaktif.")

    return QuickReorderTarget(
        source_order_ref=order.order_ref,
        product_id=int(product.id),
        product_name=product.name,
        quantity=qty,
    )


def get_customer_order_status_by_ref(
    session: Session,
    customer_id: int,
    order_ref: str,
) -> CustomerOrderStatusView | None:
    expire_pending_orders(session)

    order = session.scalar(
        select(Order).where(
            Order.customer_id == customer_id,
            Order.order_ref == order_ref,
        )
    )
    if order is None:
        return None

    payment = order.payment
    return CustomerOrderStatusView(
        order_ref=order.order_ref,
        status=order.status,
        total_amount=order.total_amount,
        payment_ref=(payment.payment_ref if payment is not None else None),
        expected_amount=(payment.expected_amount if payment is not None else None),
        created_at=order.created_at,
        expires_at=order.expires_at,
        paid_at=order.paid_at,
        delivered_at=order.delivered_at,
        cancelled_at=order.cancelled_at,
    )


def list_orders_for_payment_reminder(
    session: Session,
    minutes_before_expiry: int,
    limit: int = 50,
) -> list[PaymentReminderCandidate]:
    expire_pending_orders(session)

    now = _utcnow()
    remind_before = max(1, int(minutes_before_expiry))
    deadline = now + timedelta(minutes=remind_before)

    rows = list(
        session.execute(
            select(Order.order_ref, User.telegram_id, Payment.expected_amount, Order.expires_at)
            .join(User, User.id == Order.customer_id)
            .join(Payment, Payment.order_id == Order.id)
            .where(
                Order.status == "pending_payment",
                Payment.status == "pending",
                Order.expires_at.is_not(None),
                Order.expires_at > now,
                Order.expires_at <= deadline,
                Order.reminder_sent_at.is_(None),
            )
            .order_by(Order.expires_at.asc())
            .limit(max(1, int(limit)))
        ).all()
    )

    result: list[PaymentReminderCandidate] = []
    for order_ref_val, telegram_id_val, expected_amount_val, expires_at_val in rows:
        if expires_at_val is None:
            continue
        remaining_seconds = int((expires_at_val - now).total_seconds())
        if remaining_seconds <= 0:
            continue

        result.append(
            PaymentReminderCandidate(
                order_ref=str(order_ref_val),
                customer_telegram_id=int(telegram_id_val),
                expected_amount=int(expected_amount_val),
                expires_at=expires_at_val,
                remaining_seconds=remaining_seconds,
            )
        )
    return result


def mark_payment_reminder_sent(
    session: Session,
    order_ref: str,
    sent_at: datetime | None = None,
) -> bool:
    order = session.scalar(
        select(Order).where(
            Order.order_ref == order_ref,
            Order.status == "pending_payment",
            Order.reminder_sent_at.is_(None),
        )
    )
    if order is None:
        return False

    order.reminder_sent_at = sent_at or _utcnow()
    session.add(order)
    return True


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


def _recommend_upsell_products(
    session: Session,
    excluded_product_ids: set[int],
    limit: int = 2,
) -> list[Product]:
    products = list(
        session.scalars(
            select(Product)
            .where(Product.is_suspended.is_(False))
            .order_by(Product.id.asc())
        ).all()
    )

    recommendations: list[Product] = []
    for product in products:
        if product.id in excluded_product_ids:
            continue
        stock = get_available_stock_count(session, product.id)
        if stock <= 0:
            continue
        recommendations.append(product)
        if len(recommendations) >= max(1, int(limit)):
            break

    return recommendations


def _build_delivery_message(
    order: Order,
    units: list[StockUnit],
    recommendations: list[Product],
    issued_voucher_code: str | None = None,
    issued_voucher_discount: int = 0,
) -> str:
    parts = [
        "✅ <b>Pembayaran Berhasil Dikonfirmasi</b>",
        f"Order Ref: <code>{html.escape(order.order_ref)}</code>",
        "",
        "🔐 <b>Detail Akun Pesanan</b>",
    ]

    for idx, unit in enumerate(units, start=1):
        parts.append(f"\n<b>Akun {idx}</b>")
        parts.append(f"<pre>{html.escape(unit.raw_text)}</pre>")

    if recommendations:
        parts.append("")
        parts.append("🎯 <b>Rekomendasi Selanjutnya</b>")
        for product in recommendations:
            parts.append(
                f"• {html.escape(product.name)} - {_format_rupiah(product.price)} "
                f"(<code>/buy {product.id} 1</code>)"
            )

    if issued_voucher_code:
        parts.append("")
        parts.append("🎁 <b>Voucher Loyalti Baru</b>")
        parts.append(
            f"Kode: <code>{html.escape(issued_voucher_code)}</code> | "
            f"Diskon: <b>{_format_rupiah(issued_voucher_discount)}</b>"
        )
        parts.append("Voucher akan dipakai otomatis di checkout berikutnya jika masih aktif.")

    parts.append("")
    parts.append("📌 Simpan data akun ini dengan aman.")
    parts.append("📲 Ketik /start kapan saja untuk kembali ke menu utama.")

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
            release_voucher_for_order(session, picked.order)
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
        release_voucher_for_order(session, picked.order)
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
        release_voucher_for_order(session, order)
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
    consume_voucher_for_order(session, order)

    session.add(order)
    session.add(payment)
    session.flush()

    purchased_product_ids = {item.product_id for item in order_items}
    recommendations = _recommend_upsell_products(session, excluded_product_ids=purchased_product_ids, limit=2)

    issued_voucher = issue_milestone_voucher(
        session=session,
        customer_id=order.customer_id,
        delivered_count=count_delivered_orders_by_customer(session, order.customer_id),
    )

    append_audit(
        session,
        action="payment_confirmed",
        entity_type="payment",
        entity_id=str(payment.id),
        detail=f"amount={amount}; source_app={source_app}",
    )

    delivery_message = _build_delivery_message(
        order,
        delivered_units,
        recommendations=recommendations,
        issued_voucher_code=(issued_voucher.code if issued_voucher is not None else None),
        issued_voucher_discount=(issued_voucher.discount_amount if issued_voucher is not None else 0),
    )
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
    release_voucher_for_order(session, order)
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
