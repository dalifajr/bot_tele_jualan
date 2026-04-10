from __future__ import annotations

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
) -> tuple[int, int]:
    recipients = list_customer_telegram_ids(session)
    sent = 0
    failed = 0

    for telegram_id in recipients:
        try:
            await bot.send_message(chat_id=telegram_id, text=message)
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
