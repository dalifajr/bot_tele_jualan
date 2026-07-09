import os
import logging
from app.bot.app import create_bot_application
from app.common.logging import configure_logging
from app.db.bootstrap import init_db

logger = logging.getLogger(__name__)

def main() -> None:
    configure_logging()
    init_db()

    app = create_bot_application()
    
    port = int(os.environ.get("WEBHOOK_PORT", 8001))
    domain = os.environ.get("WEBHOOK_DOMAIN", "ini.belajaridn.id")
    webhook_url = f"https://{domain}/webhook/bot"
    
    logger.info(f"Starting webhook on port {port}, URL: {webhook_url}")
    
    app.run_webhook(
        listen="127.0.0.1",
        port=port,
        url_path="/webhook/bot",
        webhook_url=webhook_url,
        drop_pending_updates=False
    )

if __name__ == "__main__":
    main()
