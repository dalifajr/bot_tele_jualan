from __future__ import annotations

from app.bot.app import create_bot_application
from app.common.logging import configure_logging
from app.db.bootstrap import init_db


def main() -> None:
    configure_logging()
    init_db()

    app = create_bot_application()
    app.run_polling(drop_pending_updates=False)


if __name__ == "__main__":
    main()
