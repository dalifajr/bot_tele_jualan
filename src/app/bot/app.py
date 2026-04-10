from __future__ import annotations

from telegram.ext import Application

from app.bot.handlers.main import register_handlers
from app.common.config import get_settings


def create_bot_application() -> Application:
    settings = get_settings()
    if not settings.bot_token:
        raise RuntimeError("BOT_TOKEN belum diisi di .env")

    application = Application.builder().token(settings.bot_token).build()
    register_handlers(application)
    return application
