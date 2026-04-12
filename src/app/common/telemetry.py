from __future__ import annotations

import json
import logging
import time
from typing import Any

from app.common.config import get_settings


def monotonic_ms() -> int:
    return int(time.perf_counter() * 1000)


def elapsed_ms(started_ms: int) -> int:
    return max(0, monotonic_ms() - int(started_ms))


def _coerce_int(value: Any) -> int | None:
    if value is None:
        return None
    try:
        return int(value)
    except (TypeError, ValueError):
        return None


def _persist_telemetry_event(payload: dict[str, Any]) -> None:
    settings = get_settings()
    if not settings.telemetry_persist_enabled:
        return

    try:
        from app.db.database import get_session
        from app.db.models import TelemetryEvent

        event_name = str(payload.get("event") or "unknown")[:128]
        duration_ms = _coerce_int(payload.get("duration_ms"))
        success_value = payload.get("success")
        success_bool = success_value if isinstance(success_value, bool) else None
        status_raw = payload.get("status")
        status = str(status_raw)[:64] if status_raw is not None else None

        with get_session() as session:
            session.add(
                TelemetryEvent(
                    event=event_name,
                    duration_ms=duration_ms,
                    success=success_bool,
                    status=status,
                    payload_json=json.dumps(payload, ensure_ascii=True, default=str),
                )
            )
    except Exception:
        # Telemetry persistence must never block business flow.
        return


def log_telemetry(logger: logging.Logger, event: str, **fields: Any) -> None:
    settings = get_settings()
    if not settings.telemetry_enabled:
        return

    payload: dict[str, Any] = {"event": event, **fields}
    try:
        encoded = json.dumps(payload, ensure_ascii=True, sort_keys=True, default=str)
    except Exception:
        fallback: dict[str, str] = {"event": event}
        for key, value in fields.items():
            fallback[str(key)] = repr(value)
        encoded = json.dumps(fallback, ensure_ascii=True, sort_keys=True)
    logger.info("telemetry %s", encoded)
    _persist_telemetry_event(payload)
