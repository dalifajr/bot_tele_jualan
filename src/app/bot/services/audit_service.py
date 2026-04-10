from __future__ import annotations

from sqlalchemy.orm import Session

from app.db.models import AuditLog


def append_audit(
    session: Session,
    action: str,
    actor_id: int | None = None,
    entity_type: str = "",
    entity_id: str = "",
    detail: str = "",
) -> None:
    row = AuditLog(
        actor_id=actor_id,
        action=action,
        entity_type=entity_type,
        entity_id=entity_id,
        detail=detail,
    )
    session.add(row)
