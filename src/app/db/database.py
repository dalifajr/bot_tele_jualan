from __future__ import annotations

from contextlib import contextmanager
from pathlib import Path
from typing import Iterator

from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker

from app.common.config import get_settings


def _resolve_sqlite_url(url: str) -> str:
    if not url.startswith("sqlite:///"):
        return url

    settings = get_settings()
    project_root = settings.project_root

    local_path = url.replace("sqlite:///", "", 1)
    if local_path.startswith("./"):
        abs_path = (project_root / local_path[2:]).resolve()
    else:
        abs_path = Path(local_path).resolve()
    abs_path.parent.mkdir(parents=True, exist_ok=True)
    return f"sqlite:///{abs_path.as_posix()}"


settings = get_settings()
DATABASE_URL = _resolve_sqlite_url(settings.database_url)

connect_args = {"check_same_thread": False} if DATABASE_URL.startswith("sqlite") else {}
engine = create_engine(DATABASE_URL, connect_args=connect_args, future=True)
SessionLocal = sessionmaker(bind=engine, autoflush=False, autocommit=False, future=True)


@contextmanager
def get_session() -> Iterator[Session]:
    session = SessionLocal()
    try:
        yield session
        session.commit()
    except Exception:
        session.rollback()
        raise
    finally:
        session.close()
