from __future__ import annotations

import logging

from telegram.ext import Application
from telegram.ext import ContextTypes

from app.bot.services.catalog_service import promote_awaiting_stocks
from app.bot.handlers.main import register_handlers
from app.common.config import get_settings
from app.db.database import get_session

logger = logging.getLogger(__name__)


async def _promote_awaiting_stock_job(context: ContextTypes.DEFAULT_TYPE) -> None:
    with get_session() as session:
        promoted_count = promote_awaiting_stocks(session)
    if promoted_count > 0:
        logger.info("Promoted %s awaiting stock units to ready", promoted_count)


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
    else:
        logger.warning(
            "JobQueue tidak tersedia. Auto-promote awaiting stock tetap berjalan saat operasi katalog/checkout dipanggil."
        )
    return application
