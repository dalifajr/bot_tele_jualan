from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta

from sqlalchemy import delete
from sqlalchemy.orm import Session

from app.db.models import ListenerEvent, NotificationRetryJob, TelemetryEvent


@dataclass
class HousekeepingCleanupResult:
    deleted_listener_events: int
    deleted_retry_jobs: int
    deleted_telemetry_events: int


def _utcnow() -> datetime:
    return datetime.utcnow()


def cleanup_transient_data(
    session: Session,
    *,
    listener_event_retention_days: int,
    retry_job_retention_days: int,
    telemetry_retention_days: int,
) -> HousekeepingCleanupResult:
    now = _utcnow()
    listener_cutoff = now - timedelta(days=max(1, int(listener_event_retention_days)))
    retry_cutoff = now - timedelta(days=max(1, int(retry_job_retention_days)))
    telemetry_cutoff = now - timedelta(days=max(1, int(telemetry_retention_days)))

    deleted_listener_events = int(
        session.execute(
            delete(ListenerEvent).where(
                ListenerEvent.created_at < listener_cutoff,
                ListenerEvent.status.in_(["processed", "failed"]),
            )
        ).rowcount
        or 0
    )

    deleted_retry_jobs = int(
        session.execute(
            delete(NotificationRetryJob).where(
                NotificationRetryJob.created_at < retry_cutoff,
                NotificationRetryJob.status.in_(["sent", "failed"]),
            )
        ).rowcount
        or 0
    )

    deleted_telemetry_events = int(
        session.execute(
            delete(TelemetryEvent).where(
                TelemetryEvent.created_at < telemetry_cutoff,
            )
        ).rowcount
        or 0
    )

    return HousekeepingCleanupResult(
        deleted_listener_events=deleted_listener_events,
        deleted_retry_jobs=deleted_retry_jobs,
        deleted_telemetry_events=deleted_telemetry_events,
    )
