"""Backup service for exporting all bot data to ZIP files."""

from __future__ import annotations

import io
import json
import logging
import zipfile
from dataclasses import asdict, dataclass
from datetime import datetime
from pathlib import Path
from typing import Any

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.db.models import (
    AuditLog,
    BotSetting,
    BroadcastLog,
    ComplaintAttachment,
    ComplaintCase,
    ListenerEvent,
    NotificationRetryJob,
    Order,
    OrderItem,
    Payment,
    Product,
    RestockSubscription,
    StockUnit,
    TelemetryEvent,
    UpdateHistory,
    User,
)

logger = logging.getLogger(__name__)

BACKUP_VERSION = "1.0"
BACKUP_SCHEMA = {
    "version": BACKUP_VERSION,
    "created_at": None,
    "entities": {
        "users": [],
        "products": [],
        "stock_units": [],
        "orders": [],
        "order_items": [],
        "payments": [],
        "restock_subscriptions": [],
        "complaint_cases": [],
        "complaint_attachments": [],
        "bot_settings": [],
        "broadcast_logs": [],
        "audit_logs": [],
    },
}


@dataclass
class BackupProgress:
    """Progress information for backup process."""

    total_steps: int
    current_step: int
    current_entity: str
    message: str

    def percentage(self) -> int:
        if self.total_steps == 0:
            return 100
        return int((self.current_step / self.total_steps) * 100)


def _serialize_datetime(obj: Any) -> Any:
    """JSON serializer for datetime objects."""
    if isinstance(obj, datetime):
        return obj.isoformat()
    raise TypeError(f"Type {type(obj)} not serializable")


def _model_to_dict(model: Any) -> dict:
    """Convert SQLAlchemy model instance to dictionary."""
    result = {}
    for column in model.__table__.columns:
        value = getattr(model, column.name)
        if isinstance(value, datetime):
            result[column.name] = value.isoformat()
        else:
            result[column.name] = value
    return result


def collect_all_backup_data(session: Session) -> dict[str, Any]:
    """
    Collect all backup data from database.

    Returns:
        Dictionary with all entities organized by type.
    """
    backup_data = {
        "version": BACKUP_VERSION,
        "created_at": datetime.utcnow().isoformat(),
        "entities": {
            "users": [],
            "products": [],
            "stock_units": [],
            "orders": [],
            "order_items": [],
            "payments": [],
            "restock_subscriptions": [],
            "complaint_cases": [],
            "complaint_attachments": [],
            "bot_settings": [],
            "broadcast_logs": [],
            "audit_logs": [],
        },
    }

    try:
        # Collect users
        users = session.query(User).all()
        for user in users:
            user_dict = _model_to_dict(user)
            backup_data["entities"]["users"].append(user_dict)
        logger.info(f"Collected {len(users)} users")

        # Collect products
        products = session.query(Product).all()
        for product in products:
            product_dict = _model_to_dict(product)
            backup_data["entities"]["products"].append(product_dict)
        logger.info(f"Collected {len(products)} products")

        # Collect stock units
        stocks = session.query(StockUnit).all()
        for stock in stocks:
            stock_dict = _model_to_dict(stock)
            backup_data["entities"]["stock_units"].append(stock_dict)
        logger.info(f"Collected {len(stocks)} stock units")

        # Collect orders
        orders = session.query(Order).all()
        for order in orders:
            order_dict = _model_to_dict(order)
            backup_data["entities"]["orders"].append(order_dict)
        logger.info(f"Collected {len(orders)} orders")

        # Collect order items
        order_items = session.query(OrderItem).all()
        for item in order_items:
            item_dict = _model_to_dict(item)
            backup_data["entities"]["order_items"].append(item_dict)
        logger.info(f"Collected {len(order_items)} order items")

        # Collect payments
        payments = session.query(Payment).all()
        for payment in payments:
            payment_dict = _model_to_dict(payment)
            backup_data["entities"]["payments"].append(payment_dict)
        logger.info(f"Collected {len(payments)} payments")

        # Collect restock subscriptions
        subscriptions = session.query(RestockSubscription).all()
        for sub in subscriptions:
            sub_dict = _model_to_dict(sub)
            backup_data["entities"]["restock_subscriptions"].append(sub_dict)
        logger.info(f"Collected {len(subscriptions)} restock subscriptions")

        # Collect complaint cases
        complaints = session.query(ComplaintCase).all()
        for complaint in complaints:
            complaint_dict = _model_to_dict(complaint)
            backup_data["entities"]["complaint_cases"].append(complaint_dict)
        logger.info(f"Collected {len(complaints)} complaint cases")

        # Collect complaint attachments
        attachments = session.query(ComplaintAttachment).all()
        for attachment in attachments:
            attachment_dict = _model_to_dict(attachment)
            backup_data["entities"]["complaint_attachments"].append(attachment_dict)
        logger.info(f"Collected {len(attachments)} complaint attachments")

        # Collect bot settings
        settings = session.query(BotSetting).all()
        for setting in settings:
            setting_dict = _model_to_dict(setting)
            backup_data["entities"]["bot_settings"].append(setting_dict)
        logger.info(f"Collected {len(settings)} bot settings")

        # Collect broadcast logs
        broadcasts = session.query(BroadcastLog).all()
        for broadcast in broadcasts:
            broadcast_dict = _model_to_dict(broadcast)
            backup_data["entities"]["broadcast_logs"].append(broadcast_dict)
        logger.info(f"Collected {len(broadcasts)} broadcast logs")

        # Collect audit logs
        audits = session.query(AuditLog).all()
        for audit in audits:
            audit_dict = _model_to_dict(audit)
            backup_data["entities"]["audit_logs"].append(audit_dict)
        logger.info(f"Collected {len(audits)} audit logs")

    except Exception as e:
        logger.error(f"Error collecting backup data: {e}", exc_info=True)
        raise

    return backup_data


def serialize_backup_to_zip(backup_data: dict[str, Any], output_path: str | Path) -> str:
    """
    Serialize backup data to ZIP file.

    Args:
        backup_data: Dictionary with all entities
        output_path: Path where ZIP file should be created

    Returns:
        Path to created ZIP file
    """
    output_path = Path(output_path)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    try:
        with zipfile.ZipFile(output_path, "w", zipfile.ZIP_DEFLATED) as zf:
            # Create manifest file
            manifest = {
                "version": backup_data["version"],
                "created_at": backup_data["created_at"],
                "entity_counts": {
                    entity_type: len(entities)
                    for entity_type, entities in backup_data["entities"].items()
                },
            }
            zf.writestr(
                "MANIFEST.json", json.dumps(manifest, indent=2, default=_serialize_datetime)
            )

            # Write each entity type to separate JSON file
            for entity_type, entities in backup_data["entities"].items():
                if entities:
                    filename = f"{entity_type}.json"
                    content = json.dumps(entities, indent=2, default=_serialize_datetime)
                    zf.writestr(filename, content)

            logger.info(f"Backup ZIP created at {output_path}")

    except Exception as e:
        logger.error(f"Error creating backup ZIP: {e}", exc_info=True)
        if output_path.exists():
            output_path.unlink()
        raise

    return str(output_path)


async def send_backup_progress(
    context: Any, message: str, progress: BackupProgress | None = None
) -> None:
    """
    Send backup progress update to user (stub for integration).

    Args:
        context: Update context
        message: Progress message
        progress: Optional progress object with percentage
    """
    logger.info(f"Backup progress: {message}")
    if progress:
        logger.info(f"  Progress: {progress.current_step}/{progress.total_steps} ({progress.percentage()}%)")


def get_backup_summary(backup_data: dict[str, Any]) -> str:
    """
    Generate human-readable summary of backup contents.

    Args:
        backup_data: Dictionary with all entities

    Returns:
        Summary string
    """
    summary_lines = [
        "📊 Backup Summary:",
        f"Created: {backup_data['created_at']}",
        "",
        "Entities backed up:",
    ]

    for entity_type, entities in backup_data["entities"].items():
        if entities:
            count = len(entities)
            summary_lines.append(f"  • {entity_type}: {count}")

    return "\n".join(summary_lines)
