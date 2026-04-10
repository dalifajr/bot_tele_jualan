from __future__ import annotations

from pathlib import Path
from typing import Iterable


def load_admin_ids(role_file: Path) -> set[int]:
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


def get_primary_admin_id(role_file: Path) -> int | None:
    if not role_file.exists():
        return None

    for raw_line in role_file.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#"):
            continue
        if not line.lower().startswith("admin:"):
            continue
        _, value = line.split(":", maxsplit=1)
        value = value.strip()
        if value.isdigit():
            return int(value)
    return None
