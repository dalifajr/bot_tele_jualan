from __future__ import annotations

from collections.abc import Awaitable, Callable
import html
from typing import Literal

from telegram import Bot
from sqlalchemy.orm import Session

from app.bot.services.audit_service import append_audit
from app.bot.services.user_service import list_customer_telegram_ids
from app.db.models import BroadcastLog

BroadcastAttachmentType = Literal["photo", "document"]
BroadcastProgressCallback = Callable[[int, int, int, int], Awaitable[None]]


async def _send_broadcast_payload(
    *,
    bot: Bot,
    telegram_id: int,
    message: str,
    parse_mode: str | None,
    attachment_type: BroadcastAttachmentType | None,
    attachment_file_id: str | None,
) -> None:
    if attachment_type is None:
        await bot.send_message(chat_id=telegram_id, text=message, parse_mode=parse_mode)
        return

    caption = message or None
    if attachment_type == "photo":
        await bot.send_photo(
            chat_id=telegram_id,
            photo=attachment_file_id,
            caption=caption,
            parse_mode=parse_mode,
        )
        return

    await bot.send_document(
        chat_id=telegram_id,
        document=attachment_file_id,
        caption=caption,
        parse_mode=parse_mode,
    )


def _should_emit_progress(*, processed: int, total: int, progress_every: int) -> bool:
    if processed <= 0 or processed >= total:
        return True
    return processed % progress_every == 0


async def broadcast_to_customers(
    session: Session,
    bot: Bot,
    admin_user_id: int | None,
    message: str,
    parse_mode: str | None = None,
    attachment_type: BroadcastAttachmentType | None = None,
    attachment_file_id: str | None = None,
    progress_callback: BroadcastProgressCallback | None = None,
    progress_every: int = 20,
) -> tuple[int, int]:
    if attachment_type is not None and attachment_file_id is None:
        raise ValueError("attachment_file_id wajib diisi jika attachment_type digunakan.")

    progress_step = max(1, int(progress_every))
    recipients = list_customer_telegram_ids(session)
    total = len(recipients)
    sent = 0
    failed = 0

    if progress_callback is not None:
        await progress_callback(0, total, sent, failed)

    for telegram_id in recipients:
        try:
            await _send_broadcast_payload(
                bot=bot,
                telegram_id=telegram_id,
                message=message,
                parse_mode=parse_mode,
                attachment_type=attachment_type,
                attachment_file_id=attachment_file_id,
            )
            sent += 1
        except Exception:
            failed += 1

        if progress_callback is not None:
            processed = sent + failed
            if _should_emit_progress(processed=processed, total=total, progress_every=progress_step):
                await progress_callback(processed, total, sent, failed)

    attachment_label = attachment_type or "text"
    log_message = message.strip()
    if not log_message:
        log_message = f"[attachment:{attachment_label}]"

    log = BroadcastLog(
        admin_id=admin_user_id,
        message=log_message,
        sent_count=sent,
        failed_count=failed,
    )
    session.add(log)
    session.flush()

    append_audit(
        session,
        action="broadcast",
        actor_id=admin_user_id,
        entity_type="broadcast",
        entity_id=str(log.id),
        detail=f"sent={sent};failed={failed};type={attachment_label}",
    )

    session.flush()
    return sent, failed


def build_product_ready_broadcast_message(product_name: str, ready_count: int) -> str:
    safe_name = html.escape(str(product_name or "Produk"))
    safe_count = max(0, int(ready_count))
    return "\n".join(
        [
            "🎉 <b>Produk kembali tersedia</b>",
            f"Produk: <b>{safe_name}</b>",
            f"Jumlah: <b>{safe_count} akun ready</b>",
            "",
            "Silakan diorder sebelum kehabisan stok, Happy Shopping 😄",
        ]
    )
