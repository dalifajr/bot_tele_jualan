from __future__ import annotations

from datetime import datetime
from pathlib import Path
from typing import Iterable

from sqlalchemy import select

from app.common.config import get_settings
from app.db.database import get_session
from app.db.models import User


def _load_admin_ids_from_file(role_file: Path) -> set[int]:
    admin_ids: set[int] = set()
    if not role_file.exists():
        return admin_ids

    for raw_line in role_file.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#"):
            continue
        if not line.lower().startswith("admin:"):
            continue
        _, value = line.split(":", maxsplit=1)
        value = value.strip()
        if value.isdigit():
            admin_ids.add(int(value))
    return admin_ids


def _load_admin_ids_from_db() -> set[int]:
    try:
        with get_session() as session:
            rows = session.scalars(select(User.telegram_id).where(User.role == "admin")).all()
            return {int(x) for x in rows if x is not None}
    except Exception:
        return set()


def load_admin_ids(role_file: Path) -> set[int]:
    settings = get_settings()

    file_ids = _load_admin_ids_from_file(role_file)
    if not settings.rbac_use_database:
        return file_ids

    db_ids = _load_admin_ids_from_db()
    if settings.rbac_fallback_to_file:
        return db_ids | file_ids
    return db_ids


def is_admin(telegram_id: int, role_file: Path) -> bool:
    return telegram_id in load_admin_ids(role_file)


def replace_admin_ids(role_file: Path, telegram_ids: Iterable[int]) -> None:
    cleaned = sorted({int(x) for x in telegram_ids})
    role_file.parent.mkdir(parents=True, exist_ok=True)

    lines = [
        "# Format: admin:<telegram_user_id>",
        "# Dikelola oleh panel jualan",
    ]
    lines.extend([f"admin:{telegram_id}" for telegram_id in cleaned])
    role_file.write_text("\n".join(lines) + "\n", encoding="utf-8")

    settings = get_settings()
    if not settings.rbac_use_database:
        return

    try:
        with get_session() as session:
            existing_admins = list(
                session.scalars(select(User).where(User.role == "admin")).all()
            )
            cleaned_set = set(cleaned)

            for user in existing_admins:
                if int(user.telegram_id) not in cleaned_set:
                    user.role = "customer"
                    session.add(user)

            for telegram_id in cleaned:
                user = session.scalar(select(User).where(User.telegram_id == telegram_id))
                if user is None:
                    user = User(
                        telegram_id=telegram_id,
                        username=None,
                        full_name=None,
                        role="admin",
                        last_seen_at=datetime.utcnow(),
                    )
                else:
                    user.role = "admin"
                session.add(user)
    except Exception:
        # DB sync bersifat best-effort agar kompatibel dengan instalasi lama.
        return


def get_primary_admin_id(role_file: Path) -> int | None:
    settings = get_settings()
    if settings.rbac_use_database:
        db_ids = sorted(_load_admin_ids_from_db())
        if db_ids:
            return db_ids[0]

    for admin_id in sorted(_load_admin_ids_from_file(role_file)):
        return admin_id
    return None


def sync_admin_ids_from_file_to_db(session, role_file: Path) -> None:
    settings = get_settings()
    if not settings.rbac_use_database:
        return

    file_ids = _load_admin_ids_from_file(role_file)
    if not file_ids:
        return

    existing_admin_ids = {
        int(x)
        for x in session.scalars(select(User.telegram_id).where(User.role == "admin")).all()
        if x is not None
    }
    for telegram_id in sorted(file_ids):
        if telegram_id in existing_admin_ids:
            continue
        user = session.scalar(select(User).where(User.telegram_id == telegram_id))
        if user is None:
            user = User(
                telegram_id=telegram_id,
                username=None,
                full_name=None,
                role="admin",
                last_seen_at=datetime.utcnow(),
            )
        else:
            user.role = "admin"
        session.add(user)

    session.flush()
