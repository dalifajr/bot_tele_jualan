from __future__ import annotations

import json
from datetime import datetime
from typing import Any

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.db.models import ListenerEvent


def get_event_by_key(session: Session, key: str) -> ListenerEvent | None:
    return session.scalar(select(ListenerEvent).where(ListenerEvent.idempotency_key == key))


def create_event(session: Session, key: str, req_hash: str) -> ListenerEvent:
    event = ListenerEvent(
        idempotency_key=key,
        request_hash=req_hash,
        status="received",
    )
    session.add(event)
    session.flush()
    return event


def update_event_response(
    session: Session,
    event: ListenerEvent,
    *,
    status: str,
    response_payload: dict[str, Any],
) -> None:
    event.status = status
    event.response_json = json.dumps(response_payload, ensure_ascii=False)
    event.processed_at = datetime.utcnow()
    session.add(event)


def parse_cached_response(event: ListenerEvent) -> dict[str, Any]:
    if not event.response_json:
        return {
            "status": event.status,
            "message": "Replay diterima, tetapi response lama tidak tersedia.",
            "matched_chat_id": None,
        }
    try:
        payload = json.loads(event.response_json)
        if isinstance(payload, dict):
            return payload
    except Exception:
        pass
    return {
        "status": event.status,
        "message": "Replay diterima, tetapi response lama rusak.",
        "matched_chat_id": None,
    }
