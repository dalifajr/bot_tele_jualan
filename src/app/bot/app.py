from __future__ import annotations

import html
import logging

from telegram import InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application
from telegram.ext import ContextTypes

from app.bot.services.admin_order_notification_service import upsert_admin_order_message
from app.bot.services.broadcast_service import (
    build_product_ready_broadcast_message,
    broadcast_to_customers,
)
from app.bot.services.catalog_service import list_products, promote_awaiting_stocks
from app.bot.handlers.main import register_handlers
from app.bot.services.housekeeping_service import cleanup_transient_data
from app.bot.services.notification_retry_service import (
    enqueue_notification_retry,
    list_due_notification_retries,
    mark_notification_retry_failed,
    mark_notification_retry_sent,
)
from app.bot.services.order_service import (
    build_admin_order_message,
    expire_pending_orders_with_notifications,
    list_orders_for_payment_reminder,
    mark_payment_reminder_sent,
    set_admin_message_ref,
)
from app.bot.services.restock_service import list_ready_restock_notifications, mark_restock_notified
from app.bot.services.settings_service import get_setting, set_setting
from app.common.config import get_settings
from app.common.roles import get_primary_admin_id
from app.common.telemetry import elapsed_ms, log_telemetry, monotonic_ms
from app.db.database import get_session

logger = logging.getLogger(__name__)
READY_STOCK_BROADCAST_KEY_PREFIX = "ready_stock_broadcast:last_stock:"


def _format_rupiah(amount: int) -> str:
    return f"Rp{amount:,}".replace(",", ".")


def _build_payment_reminder_message(order_ref: str, expected_amount: int, remaining_minutes: int) -> str:
    safe_order_ref = html.escape(order_ref)
    return "\n".join(
        [
            "⏰ <b>Pengingat Pembayaran</b>",
            f"Order Ref: <code>{safe_order_ref}</code>",
            f"Total Bayar: <b>{_format_rupiah(expected_amount)}</b>",
            f"Sisa Waktu: <b>{remaining_minutes} menit</b>",
            "",
            "🧭 <b>Langkah Berikutnya</b>",
            "1. Segera transfer sesuai nominal.",
            f"2. Pantau status: <code>/order_status {safe_order_ref}</code>",
            "3. Ketik /start untuk kembali ke menu utama.",
        ]
    )


def _build_restock_message(product_id: int, product_name: str, product_price: int, stock_available: int) -> str:
    return "\n".join(
        [
            "📦 <b>Stok Kembali Tersedia</b>",
            f"Produk: <b>{html.escape(product_name)}</b>",
            f"Harga: <b>{_format_rupiah(product_price)}</b>",
            f"Stok ready: <b>{stock_available}</b>",
            "",
            f"Checkout cepat: <code>/buy {product_id} 1</code>",
            "Atau ketik /catalog untuk lihat semua produk.",
        ]
    )


def _ready_stock_setting_key(product_id: int) -> str:
    return f"{READY_STOCK_BROADCAST_KEY_PREFIX}{int(product_id)}"


def _to_int(value: str, default: int = 0) -> int:
    try:
        return int(str(value).strip())
    except (TypeError, ValueError, AttributeError):
        return int(default)


async def _promote_awaiting_stock_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    started_ms = monotonic_ms()
    with get_session() as session:
        promoted_count = promote_awaiting_stocks(session)
    if promoted_count > 0:
        logger.info("Promoted %s awaiting stock units to ready", promoted_count)
        log_telemetry(
            logger,
            "job.promote_awaiting_stock",
            duration_ms=elapsed_ms(started_ms),
            promoted_count=promoted_count,
        )


async def _expire_pending_orders_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    started_ms = monotonic_ms()
    admin_id = get_primary_admin_id(get_settings().role_file_path)
    upsert_success_count = 0
    upsert_failed_count = 0

    with get_session() as session:
        notifications = expire_pending_orders_with_notifications(session)
        if not notifications:
            return

        if admin_id is None:
            logger.warning("Order expired terdeteksi, tapi admin utama belum diset.")
            log_telemetry(
                logger,
                "job.expire_pending_orders",
                duration_ms=elapsed_ms(started_ms),
                expired_count=len(notifications),
                admin_configured=False,
            )
            return

        for notification in notifications:
            message_text = build_admin_order_message(notification)
            upsert_result = await upsert_admin_order_message(
                bot=context.bot,
                admin_chat_id=admin_id,
                message_text=message_text,
                existing_chat_id=notification.admin_chat_id,
                existing_message_id=notification.admin_message_id,
            )
            if upsert_result is None:
                upsert_failed_count += 1
                continue

            set_admin_message_ref(
                session=session,
                order_ref=notification.order_ref,
                chat_id=upsert_result[0],
                message_id=upsert_result[1],
            )
            upsert_success_count += 1

        log_telemetry(
            logger,
            "job.expire_pending_orders",
            duration_ms=elapsed_ms(started_ms),
            expired_count=len(notifications),
            admin_configured=True,
            upsert_success_count=upsert_success_count,
            upsert_failed_count=upsert_failed_count,
        )


async def _payment_reminder_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    started_ms = monotonic_ms()
    settings = get_settings()
    sent_count = 0
    retry_enqueued_count = 0

    with get_session() as session:
        candidates = list_orders_for_payment_reminder(
            session=session,
            minutes_before_expiry=settings.payment_reminder_minutes_before_expiry,
            limit=50,
        )

    for candidate in candidates:
        remaining_minutes = max(1, candidate.remaining_seconds // 60)
        text = _build_payment_reminder_message(
            order_ref=candidate.order_ref,
            expected_amount=candidate.expected_amount,
            remaining_minutes=remaining_minutes,
        )

        try:
            await context.bot.send_message(
                chat_id=candidate.customer_telegram_id,
                text=text,
                parse_mode="HTML",
            )
        except Exception as exc:
            logger.warning(
                "Gagal kirim reminder payment untuk %s: %s",
                candidate.order_ref,
                exc,
            )
            with get_session() as session:
                enqueue_notification_retry(
                    session=session,
                    channel="payment_reminder",
                    chat_id=candidate.customer_telegram_id,
                    payload_text=text,
                    parse_mode="HTML",
                )
                retry_enqueued_count += 1
            continue

        with get_session() as session:
            mark_payment_reminder_sent(session, candidate.order_ref)
        sent_count += 1

    if candidates:
        log_telemetry(
            logger,
            "job.payment_reminder",
            duration_ms=elapsed_ms(started_ms),
            candidate_count=len(candidates),
            sent_count=sent_count,
            retry_enqueued_count=retry_enqueued_count,
        )


async def _restock_subscription_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    started_ms = monotonic_ms()
    sent_count = 0
    retry_enqueued_count = 0
    with get_session() as session:
        candidates = list_ready_restock_notifications(session, limit=50)

    for candidate in candidates:
        text = _build_restock_message(
            product_id=candidate.product_id,
            product_name=candidate.product_name,
            product_price=candidate.product_price,
            stock_available=candidate.stock_available,
        )
        keyboard = InlineKeyboardMarkup(
            [
                [InlineKeyboardButton("🛍️ Buka Detail Produk", callback_data=f"cp:{candidate.product_id}")],
                [InlineKeyboardButton("🏠 /start Menu Utama", callback_data="back:main")],
            ]
        )

        try:
            await context.bot.send_message(
                chat_id=candidate.customer_telegram_id,
                text=text,
                parse_mode="HTML",
                reply_markup=keyboard,
                disable_web_page_preview=True,
            )
        except Exception as exc:
            logger.warning("Gagal kirim notifikasi restock untuk sub#%s: %s", candidate.subscription_id, exc)
            with get_session() as session:
                enqueue_notification_retry(
                    session=session,
                    channel="restock_notify",
                    chat_id=candidate.customer_telegram_id,
                    payload_text=text,
                    parse_mode="HTML",
                )
                retry_enqueued_count += 1
            continue

        with get_session() as session:
            mark_restock_notified(session, candidate.subscription_id)
        sent_count += 1

    if candidates:
        log_telemetry(
            logger,
            "job.restock_subscription",
            duration_ms=elapsed_ms(started_ms),
            candidate_count=len(candidates),
            sent_count=sent_count,
            retry_enqueued_count=retry_enqueued_count,
        )


async def _ready_stock_broadcast_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    started_ms = monotonic_ms()
    settings = get_settings()
    admin_chat_id = get_primary_admin_id(settings.role_file_path)

    initialized_count = 0
    events: list[tuple[int, str, int]] = []
    with get_session() as session:
        products = list_products(session, include_suspended=False)
        for product in products:
            product_id = int(product.id)
            current_stock = max(0, int(product.stock_available))
            key = _ready_stock_setting_key(product_id)
            previous_raw = get_setting(session, key, default="__unset__")

            # First seen baseline: set current value without broadcasting.
            if previous_raw == "__unset__":
                set_setting(session, key, str(current_stock))
                initialized_count += 1
                continue

            previous_stock = _to_int(previous_raw, default=0)
            if previous_stock <= 0 and current_stock > 0:
                events.append((product_id, product.name, current_stock))

            if previous_stock != current_stock:
                set_setting(session, key, str(current_stock))

    if not events:
        if initialized_count > 0:
            log_telemetry(
                logger,
                "job.ready_stock_broadcast",
                duration_ms=elapsed_ms(started_ms),
                initialized_count=initialized_count,
                event_count=0,
            )
        return

    sent_total = 0
    failed_total = 0
    admin_notified_count = 0
    retry_enqueued_count = 0

    for product_id, product_name, ready_count in events:
        message = build_product_ready_broadcast_message(product_name, ready_count)

        with get_session() as session:
            sent, failed = await broadcast_to_customers(
                session=session,
                bot=context.bot,
                admin_user_id=None,
                message=message,
                parse_mode="HTML",
            )
        sent_total += sent
        failed_total += failed

        if admin_chat_id is not None:
            admin_text = "\n".join(
                [
                    message,
                    "",
                    f"Auto broadcast ke customer: ✅ <b>{sent}</b> | ❌ <b>{failed}</b>",
                ]
            )
            admin_keyboard = InlineKeyboardMarkup(
                [[InlineKeyboardButton("📢 Broadcast Notifikasi", callback_data=f"adm:rb:bc:{product_id}")]]
            )
            try:
                await context.bot.send_message(
                    chat_id=admin_chat_id,
                    text=admin_text,
                    parse_mode="HTML",
                    reply_markup=admin_keyboard,
                    disable_web_page_preview=True,
                )
                admin_notified_count += 1
            except Exception as exc:
                logger.warning(
                    "Gagal kirim notifikasi ready-stock ke admin untuk produk#%s: %s",
                    product_id,
                    exc,
                )
                with get_session() as session:
                    enqueue_notification_retry(
                        session=session,
                        channel="admin_ready_stock_notify",
                        chat_id=admin_chat_id,
                        payload_text=admin_text,
                        parse_mode="HTML",
                    )
                retry_enqueued_count += 1

    log_telemetry(
        logger,
        "job.ready_stock_broadcast",
        duration_ms=elapsed_ms(started_ms),
        initialized_count=initialized_count,
        event_count=len(events),
        customer_sent_count=sent_total,
        customer_failed_count=failed_total,
        admin_notified_count=admin_notified_count,
        retry_enqueued_count=retry_enqueued_count,
    )


async def _notification_retry_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    started_ms = monotonic_ms()
    settings = get_settings()
    sent_count = 0
    failed_count = 0
    with get_session() as session:
        jobs = list_due_notification_retries(
            session=session,
            limit=settings.notification_retry_batch_size,
        )

    if not jobs:
        return

    for job in jobs:
        try:
            await context.bot.send_message(
                chat_id=job.chat_id,
                text=job.payload_text,
                parse_mode=job.parse_mode,
                disable_web_page_preview=True,
            )
        except Exception as exc:
            logger.warning("Retry notif gagal untuk job#%s: %s", job.id, exc)
            with get_session() as session:
                mark_notification_retry_failed(
                    session=session,
                    job_id=job.id,
                    error=str(exc),
                    backoff_seconds=settings.notification_retry_backoff_seconds,
                )
            failed_count += 1
            continue

        with get_session() as session:
            mark_notification_retry_sent(session=session, job_id=job.id)
        sent_count += 1

    log_telemetry(
        logger,
        "job.notification_retry",
        duration_ms=elapsed_ms(started_ms),
        due_job_count=len(jobs),
        sent_count=sent_count,
        failed_count=failed_count,
    )


async def _housekeeping_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    settings = get_settings()
    if not settings.housekeeping_enabled:
        return

    started_ms = monotonic_ms()
    with get_session() as session:
        result = cleanup_transient_data(
            session,
            listener_event_retention_days=settings.listener_event_retention_days,
            retry_job_retention_days=settings.retry_job_retention_days,
            telemetry_retention_days=settings.telemetry_retention_days,
        )

    deleted_total = (
        result.deleted_listener_events
        + result.deleted_retry_jobs
        + result.deleted_telemetry_events
    )

    if deleted_total > 0:
        logger.info(
            "Housekeeping cleanup selesai: listener=%s retry=%s telemetry=%s",
            result.deleted_listener_events,
            result.deleted_retry_jobs,
            result.deleted_telemetry_events,
        )

    log_telemetry(
        logger,
        "job.housekeeping",
        duration_ms=elapsed_ms(started_ms),
        deleted_listener_events=result.deleted_listener_events,
        deleted_retry_jobs=result.deleted_retry_jobs,
        deleted_telemetry_events=result.deleted_telemetry_events,
    )


def create_bot_application() -> Application:
    settings = get_settings()
    if not settings.bot_token:
        raise RuntimeError("BOT_TOKEN belum diisi di .env")

    application = Application.builder().token(settings.bot_token).build()
    register_handlers(application)
    if application.job_queue is not None:
        application.job_queue.run_repeating(
            _promote_awaiting_stock_job,
            interval=300,
            first=60,
            name="promote-awaiting-stock",
        )
        application.job_queue.run_repeating(
            _expire_pending_orders_job,
            interval=60,
            first=30,
            name="expire-pending-orders",
        )
        application.job_queue.run_repeating(
            _payment_reminder_job,
            interval=max(10, settings.payment_reminder_job_interval_seconds),
            first=20,
            name="payment-reminder",
        )
        application.job_queue.run_repeating(
            _restock_subscription_job,
            interval=60,
            first=25,
            name="restock-subscription",
        )
        application.job_queue.run_repeating(
            _ready_stock_broadcast_job,
            interval=60,
            first=35,
            name="ready-stock-broadcast",
        )
        application.job_queue.run_repeating(
            _notification_retry_job,
            interval=max(5, settings.notification_retry_job_interval_seconds),
            first=5,
            name="notification-retry",
        )
        if settings.housekeeping_enabled:
            application.job_queue.run_repeating(
                _housekeeping_job,
                interval=max(900, settings.housekeeping_interval_minutes * 60),
                first=90,
                name="housekeeping",
            )
    else:
        logger.warning(
            "JobQueue tidak tersedia. Auto-promote awaiting stock tetap berjalan saat operasi katalog/checkout dipanggil."
        )
    return application
