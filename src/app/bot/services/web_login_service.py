"""Service for managing Telegram-based website login tokens.

Flow:
1. Website creates a pending token (via shared DB).
2. User clicks deep-link → Bot receives /start weblogin_<token>.
3. Bot calls verify_web_login() → token status=verified, link_token generated.
4. Bot sends one-time login link to user.
5. User clicks link → Website calls consume_login_link() → gets telegram_id.
6. Website creates session (Auth::login with remember_me=30 days).
"""

from __future__ import annotations

import secrets
from dataclasses import dataclass
from datetime import datetime, timedelta

from sqlalchemy import and_
from sqlalchemy.orm import Session

from app.db.models import TelegramLoginToken


def _utcnow() -> datetime:
    return datetime.utcnow()


@dataclass
class WebLoginResult:
    success: bool
    link_token: str | None = None
    error: str | None = None


def create_login_token(
    session: Session,
    *,
    ttl_minutes: int = 5,
    ip_address: str | None = None,
    user_agent: str | None = None,
) -> str:
    """Create a new pending login token. Called by the website."""
    token = secrets.token_urlsafe(48)
    record = TelegramLoginToken(
        token=token,
        status="pending",
        ip_address=ip_address,
        user_agent=user_agent,
        expires_at=_utcnow() + timedelta(minutes=max(1, ttl_minutes)),
    )
    session.add(record)
    session.flush()
    return token


def validate_web_login_token(
    session: Session,
    token: str,
) -> TelegramLoginToken | None:
    """Check if a login token exists and is still valid (pending + not expired)."""
    now = _utcnow()
    return (
        session.query(TelegramLoginToken)
        .filter(
            and_(
                TelegramLoginToken.token == token,
                TelegramLoginToken.status == "pending",
                TelegramLoginToken.expires_at > now,
            )
        )
        .first()
    )


def verify_web_login(
    session: Session,
    token: str,
    telegram_id: int,
    *,
    link_ttl_minutes: int = 5,
) -> WebLoginResult:
    """Verify a login token and generate a one-time login link token.

    Called by the Bot when user sends /start weblogin_<token>.
    """
    record = validate_web_login_token(session, token)
    if record is None:
        return WebLoginResult(
            success=False,
            error="Token login tidak ditemukan atau sudah kedaluwarsa.",
        )

    link_token = secrets.token_urlsafe(48)
    record.telegram_id = telegram_id
    record.status = "verified"
    record.link_token = link_token
    record.link_expires_at = _utcnow() + timedelta(minutes=max(1, link_ttl_minutes))
    session.flush()

    return WebLoginResult(success=True, link_token=link_token)


def consume_login_link(
    session: Session,
    link_token: str,
) -> int | None:
    """Consume a one-time login link token and return the telegram_id.

    Called by the website when user clicks the login link.
    Returns telegram_id on success, None on failure.
    """
    now = _utcnow()
    record = (
        session.query(TelegramLoginToken)
        .filter(
            and_(
                TelegramLoginToken.link_token == link_token,
                TelegramLoginToken.status == "verified",
                TelegramLoginToken.link_expires_at > now,
                TelegramLoginToken.used_at.is_(None),
            )
        )
        .first()
    )
    if record is None:
        return None

    record.status = "used"
    record.used_at = now
    session.flush()

    return record.telegram_id


def cleanup_expired_login_tokens(
    session: Session,
    *,
    expired_pending_hours: int = 1,
    used_retention_days: int = 7,
) -> int:
    """Delete expired/used login tokens. Called by housekeeping job."""
    now = _utcnow()
    pending_cutoff = now - timedelta(hours=max(1, expired_pending_hours))
    used_cutoff = now - timedelta(days=max(1, used_retention_days))

    deleted = 0

    # Delete expired pending tokens
    result = (
        session.query(TelegramLoginToken)
        .filter(
            and_(
                TelegramLoginToken.status == "pending",
                TelegramLoginToken.expires_at < pending_cutoff,
            )
        )
        .delete(synchronize_session=False)
    )
    deleted += int(result or 0)

    # Delete old used tokens
    result = (
        session.query(TelegramLoginToken)
        .filter(
            and_(
                TelegramLoginToken.status == "used",
                TelegramLoginToken.used_at < used_cutoff,
            )
        )
        .delete(synchronize_session=False)
    )
    deleted += int(result or 0)

    # Delete old verified-but-never-used tokens (link expired)
    result = (
        session.query(TelegramLoginToken)
        .filter(
            and_(
                TelegramLoginToken.status == "verified",
                TelegramLoginToken.link_expires_at < pending_cutoff,
            )
        )
        .delete(synchronize_session=False)
    )
    deleted += int(result or 0)

    return deleted
