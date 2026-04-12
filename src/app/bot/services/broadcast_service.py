from __future__ import annotations

import html

from telegram import Bot
from sqlalchemy.orm import Session

from app.bot.services.audit_service import append_audit
from app.bot.services.user_service import list_customer_telegram_ids
from app.db.models import BroadcastLog


async def broadcast_to_customers(
    session: Session,
    bot: Bot,
    admin_user_id: int | None,
    message: str,
    parse_mode: str | None = None,
) -> tuple[int, int]:
    recipients = list_customer_telegram_ids(session)
    sent = 0
    failed = 0

    for telegram_id in recipients:
        try:
            await bot.send_message(chat_id=telegram_id, text=message, parse_mode=parse_mode)
            sent += 1
        except Exception:
            failed += 1

    log = BroadcastLog(
        admin_id=admin_user_id,
        message=message,
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
        detail=f"sent={sent};failed={failed}",
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
