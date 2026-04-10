from __future__ import annotations

from app.db.database import engine
from app.db.models import Base


def init_db() -> None:
    Base.metadata.create_all(bind=engine)
