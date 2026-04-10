from __future__ import annotations

from datetime import datetime

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.common.config import get_settings
from app.common.roles import is_admin
from app.db.models import User


def upsert_user(
    session: Session,
    telegram_id: int,
    username: str | None,
    full_name: str | None,
    role: str,
) -> User:
    user = session.scalar(select(User).where(User.telegram_id == telegram_id))
    if user is None:
        user = User(
            telegram_id=telegram_id,
            username=username,
            full_name=full_name,
            role=role,
            last_seen_at=datetime.utcnow(),
        )
        session.add(user)
        session.flush()
        return user

    user.username = username
    user.full_name = full_name
    user.role = role
    user.last_seen_at = datetime.utcnow()
    session.add(user)
    session.flush()
    return user


def get_user_by_telegram_id(session: Session, telegram_id: int) -> User | None:
    return session.scalar(select(User).where(User.telegram_id == telegram_id))


def list_customer_telegram_ids(session: Session) -> list[int]:
    settings = get_settings()
    rows = session.scalars(select(User.telegram_id)).all()
    return [
        int(telegram_id)
        for telegram_id in rows
        if not is_admin(int(telegram_id), settings.role_file_path)
    ]
