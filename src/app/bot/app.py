from __future__ import annotations

import html
import logging

from telegram.ext import Application
from telegram.ext import ContextTypes

from app.bot.services.admin_order_notification_service import upsert_admin_order_message
from app.bot.services.catalog_service import promote_awaiting_stocks
from app.bot.handlers.main import register_handlers
from app.bot.services.order_service import (
    build_admin_order_message,
    expire_pending_orders_with_notifications,
    list_orders_for_payment_reminder,
    mark_payment_reminder_sent,
    set_admin_message_ref,
)
from app.common.config import get_settings
from app.common.roles import get_primary_admin_id
from app.db.database import get_session

logger = logging.getLogger(__name__)


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


async def _promote_awaiting_stock_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    with get_session() as session:
        promoted_count = promote_awaiting_stocks(session)
    if promoted_count > 0:
        logger.info("Promoted %s awaiting stock units to ready", promoted_count)


async def _expire_pending_orders_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    admin_id = get_primary_admin_id(get_settings().role_file_path)

    with get_session() as session:
        notifications = expire_pending_orders_with_notifications(session)
        if not notifications:
            return

        if admin_id is None:
            logger.warning("Order expired terdeteksi, tapi admin utama belum diset.")
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
                continue

            set_admin_message_ref(
                session=session,
                order_ref=notification.order_ref,
                chat_id=upsert_result[0],
                message_id=upsert_result[1],
            )


async def _payment_reminder_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    settings = get_settings()

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
            continue

        with get_session() as session:
            mark_payment_reminder_sent(session, candidate.order_ref)


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
    else:
        logger.warning(
            "JobQueue tidak tersedia. Auto-promote awaiting stock tetap berjalan saat operasi katalog/checkout dipanggil."
        )
    return application
