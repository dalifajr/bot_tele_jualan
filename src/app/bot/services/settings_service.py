from __future__ import annotations

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.db.models import BotSetting


def get_setting(session: Session, key: str, default: str = "") -> str:
    row = session.scalar(select(BotSetting).where(BotSetting.key == key))
    if row is None:
        return default
    return row.value


def set_setting(session: Session, key: str, value: str) -> None:
    row = session.scalar(select(BotSetting).where(BotSetting.key == key))
    if row is None:
        row = BotSetting(key=key, value=value)
    else:
        row.value = value
    session.add(row)
