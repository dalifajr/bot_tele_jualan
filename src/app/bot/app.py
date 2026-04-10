from __future__ import annotations

import logging

from telegram.ext import Application
from telegram.ext import ContextTypes

from app.bot.services.admin_order_notification_service import upsert_admin_order_message
from app.bot.services.catalog_service import promote_awaiting_stocks
from app.bot.handlers.main import register_handlers
from app.bot.services.order_service import (
    build_admin_order_message,
    expire_pending_orders_with_notifications,
    set_admin_message_ref,
)
from app.common.config import get_settings
from app.common.roles import get_primary_admin_id
from app.db.database import get_session

logger = logging.getLogger(__name__)


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
    else:
        logger.warning(
            "JobQueue tidak tersedia. Auto-promote awaiting stock tetap berjalan saat operasi katalog/checkout dipanggil."
        )
    return application
