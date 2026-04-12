from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta

from sqlalchemy import func, or_, select
from sqlalchemy.orm import Session

from app.common.config import get_settings
from app.db.models import NotificationRetryJob

settings = get_settings()


@dataclass
class NotificationRetryCandidate:
    id: int
    channel: str
    chat_id: int
    payload_text: str
    parse_mode: str | None
    attempt_count: int
    max_attempts: int


@dataclass
class RetryQueueSnapshot:
    pending_count: int
    failed_count: int
    sent_last_24h: int
    top_failed_channels: list[tuple[str, int]]


def _utcnow() -> datetime:
    return datetime.utcnow()


def enqueue_notification_retry(
    session: Session,
    channel: str,
    chat_id: int,
    payload_text: str,
    parse_mode: str | None = "HTML",
    max_attempts: int | None = None,
    delay_seconds: int = 0,
) -> int:
    now = _utcnow()
    safe_max_attempts = max(1, int(max_attempts or settings.notification_retry_max_attempts))
    next_attempt_at = now + timedelta(seconds=max(0, int(delay_seconds)))

    job = NotificationRetryJob(
        channel=channel,
        chat_id=chat_id,
        payload_text=payload_text,
        parse_mode=parse_mode,
        status="pending",
        attempt_count=0,
        max_attempts=safe_max_attempts,
        next_attempt_at=next_attempt_at,
    )
    session.add(job)
    session.flush()
    return int(job.id)


def list_due_notification_retries(
    session: Session,
    limit: int | None = None,
) -> list[NotificationRetryCandidate]:
    now = _utcnow()
    batch_size = max(1, int(limit or settings.notification_retry_batch_size))

    rows = list(
        session.scalars(
            select(NotificationRetryJob)
            .where(
                NotificationRetryJob.status == "pending",
                NotificationRetryJob.attempt_count < NotificationRetryJob.max_attempts,
                or_(
                    NotificationRetryJob.next_attempt_at.is_(None),
                    NotificationRetryJob.next_attempt_at <= now,
                ),
            )
            .order_by(NotificationRetryJob.id.asc())
            .limit(batch_size)
        ).all()
    )

    return [
        NotificationRetryCandidate(
            id=int(row.id),
            channel=row.channel,
            chat_id=int(row.chat_id),
            payload_text=row.payload_text,
            parse_mode=row.parse_mode,
            attempt_count=int(row.attempt_count),
            max_attempts=int(row.max_attempts),
        )
        for row in rows
    ]


def mark_notification_retry_sent(session: Session, job_id: int) -> bool:
    job = session.get(NotificationRetryJob, job_id)
    if job is None:
        return False

    job.status = "sent"
    job.sent_at = _utcnow()
    job.last_error = ""
    session.add(job)
    return True


def mark_notification_retry_failed(
    session: Session,
    job_id: int,
    error: str,
    backoff_seconds: int | None = None,
) -> bool:
    job = session.get(NotificationRetryJob, job_id)
    if job is None:
        return False

    now = _utcnow()
    safe_backoff = max(1, int(backoff_seconds or settings.notification_retry_backoff_seconds))

    job.attempt_count = int(job.attempt_count) + 1
    job.last_error = error[:2000]

    if job.attempt_count >= job.max_attempts:
        job.status = "failed"
        job.next_attempt_at = None
    else:
        job.status = "pending"
        job.next_attempt_at = now + timedelta(seconds=safe_backoff)

    session.add(job)
    return True


def collect_retry_queue_snapshot(
    session: Session,
    *,
    recent_hours: int = 24,
    top_n: int = 5,
) -> RetryQueueSnapshot:
    now = _utcnow()
    since = now - timedelta(hours=max(1, int(recent_hours)))

    pending_count = int(
        session.scalar(
            select(func.count(NotificationRetryJob.id)).where(NotificationRetryJob.status == "pending")
        )
        or 0
    )
    failed_count = int(
        session.scalar(
            select(func.count(NotificationRetryJob.id)).where(NotificationRetryJob.status == "failed")
        )
        or 0
    )
    sent_last_24h = int(
        session.scalar(
            select(func.count(NotificationRetryJob.id)).where(
                NotificationRetryJob.status == "sent",
                NotificationRetryJob.sent_at.is_not(None),
                NotificationRetryJob.sent_at >= since,
            )
        )
        or 0
    )

    top_rows = list(
        session.execute(
            select(
                NotificationRetryJob.channel,
                func.count(NotificationRetryJob.id).label("failed_total"),
            )
            .where(NotificationRetryJob.status == "failed")
            .group_by(NotificationRetryJob.channel)
            .order_by(func.count(NotificationRetryJob.id).desc(), NotificationRetryJob.channel.asc())
            .limit(max(1, int(top_n)))
        ).all()
    )

    top_failed_channels = [(str(channel), int(total or 0)) for channel, total in top_rows]

    return RetryQueueSnapshot(
        pending_count=pending_count,
        failed_count=failed_count,
        sent_last_24h=sent_last_24h,
        top_failed_channels=top_failed_channels,
    )
