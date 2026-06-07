from __future__ import annotations

from datetime import datetime, timezone as py_timezone
import time
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from app.common.config import get_settings

settings = get_settings()

_cached_timezone = None
_cached_time = 0.0


def get_system_timezone() -> str:
    global _cached_timezone, _cached_time
    now = time.time()
    # Cache dynamic DB query for 10 seconds to keep bot fast
    if _cached_timezone is not None and now - _cached_time < 10.0:
        return _cached_timezone

    try:
        from app.db.database import SessionLocal
        from app.db.models import BotSetting
        from sqlalchemy import select

        with SessionLocal() as session:
            row = session.scalar(select(BotSetting).where(BotSetting.key == "system_timezone"))
            if row and row.value:
                _cached_timezone = str(row.value).strip()
                _cached_time = now
                return _cached_timezone
    except Exception:
        pass

    # Fallback to configured settings default
    _cached_timezone = settings.display_timezone
    _cached_time = now
    return _cached_timezone


def get_now_local() -> datetime:
    tz_str = get_system_timezone()
    try:
        return datetime.now(ZoneInfo(tz_str))
    except ZoneInfoNotFoundError:
        return datetime.now()


def format_local_time(value: datetime) -> str:
    tz_str = get_system_timezone()
    try:
        tz = ZoneInfo(tz_str)
    except ZoneInfoNotFoundError:
        tz = None

    display_value = value
    if tz is not None:
        if display_value.tzinfo is None:
            display_value = display_value.replace(tzinfo=py_timezone.utc)
        display_value = display_value.astimezone(tz)

    return display_value.strftime("%d %b %Y %H:%M")
