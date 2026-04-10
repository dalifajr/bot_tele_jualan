from __future__ import annotations

import logging

from telegram import Bot
from telegram import InlineKeyboardButton, InlineKeyboardMarkup
from telegram.constants import ParseMode
from telegram.error import BadRequest

logger = logging.getLogger(__name__)


def build_admin_order_actions_keyboard(order_ref: str, status: str) -> InlineKeyboardMarkup | None:
    if status != "pending_payment":
        return None

    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("✅ Pembayaran Diterima", callback_data=f"adm:ord:paid:{order_ref}")],
            [InlineKeyboardButton("❌ Batalkan Pesanan", callback_data=f"adm:ord:cancel:{order_ref}")],
        ]
    )


async def upsert_admin_order_message(
    *,
    bot: Bot,
    admin_chat_id: int,
    message_text: str,
    reply_markup: InlineKeyboardMarkup | None = None,
    existing_chat_id: int | None,
    existing_message_id: int | None,
) -> tuple[int, int] | None:
    if existing_chat_id is not None and existing_message_id is not None:
        try:
            await bot.edit_message_text(
                chat_id=existing_chat_id,
                message_id=existing_message_id,
                text=message_text,
                reply_markup=reply_markup,
                parse_mode=ParseMode.HTML,
                disable_web_page_preview=True,
            )
            return int(existing_chat_id), int(existing_message_id)
        except BadRequest as exc:
            if "message is not modified" in str(exc).lower():
                return int(existing_chat_id), int(existing_message_id)
            logger.warning("Gagal edit notifikasi admin, fallback kirim baru: %s", exc)
        except Exception as exc:
            logger.warning("Error edit notifikasi admin, fallback kirim baru: %s", exc)

    try:
        sent = await bot.send_message(
            chat_id=admin_chat_id,
            text=message_text,
            reply_markup=reply_markup,
            parse_mode=ParseMode.HTML,
            disable_web_page_preview=True,
        )
        return int(sent.chat_id), int(sent.message_id)
    except Exception as exc:
        logger.exception("Gagal kirim notifikasi order ke admin: %s", exc)
        return None
