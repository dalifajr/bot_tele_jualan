"""Restore service for importing backup data from ZIP files."""

from __future__ import annotations

import json
import logging
import zipfile
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any

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


@dataclass
class RestoreProgress:
    """Progress information for restore process."""

    total_steps: int
    current_step: int
    current_entity: str
    message: str

    def percentage(self) -> int:
        if self.total_steps == 0:
            return 100
        return int((self.current_step / self.total_steps) * 100)


@dataclass
class RestoreStatistics:
    """Statistics for restore operation."""

    entity_type: str
    total_in_backup: int
    imported: int = 0
    skipped: int = 0
    errors: int = 0
    error_details: list[str] = None

    def __post_init__(self):
        if self.error_details is None:
            self.error_details = []


class BackupValidationError(Exception):
    """Raised when backup ZIP is invalid."""

    pass


def validate_backup_zip(zip_path: str | Path) -> dict[str, Any]:
    """
    Validate backup ZIP file structure and schema.

    Args:
        zip_path: Path to ZIP file

    Returns:
        Dictionary with manifest and validation results

    Raises:
        BackupValidationError: If backup is invalid
    """
    zip_path = Path(zip_path)

    if not zip_path.exists():
        raise BackupValidationError(f"Backup file not found: {zip_path}")

    if not zipfile.is_zipfile(zip_path):
        raise BackupValidationError(f"Invalid ZIP file: {zip_path}")

    try:
        with zipfile.ZipFile(zip_path, "r") as zf:
            # Check for manifest
            if "MANIFEST.json" not in zf.namelist():
                raise BackupValidationError("Missing MANIFEST.json in backup")

            # Read and parse manifest
            manifest_content = zf.read("MANIFEST.json").decode("utf-8")
            manifest = json.loads(manifest_content)

            # Validate manifest structure
            if "version" not in manifest:
                raise BackupValidationError("Invalid manifest: missing version")
            if "entity_counts" not in manifest:
                raise BackupValidationError("Invalid manifest: missing entity_counts")

            logger.info(f"Backup validation passed. Version: {manifest['version']}")
            logger.info(f"Entities in backup: {manifest['entity_counts']}")

            return manifest

    except json.JSONDecodeError as e:
        raise BackupValidationError(f"Invalid JSON in manifest: {e}")
    except Exception as e:
        raise BackupValidationError(f"Error validating backup: {e}")


def parse_backup_zip(zip_path: str | Path) -> dict[str, Any]:
    """
    Parse backup ZIP file and extract all entities.

    Args:
        zip_path: Path to ZIP file

    Returns:
        Dictionary with manifest and parsed entities

    Raises:
        BackupValidationError: If backup cannot be parsed
    """
    zip_path = Path(zip_path)
    manifest = validate_backup_zip(zip_path)

    backup_data = {
        "manifest": manifest,
        "entities": {},
    }

    try:
        with zipfile.ZipFile(zip_path, "r") as zf:
            # List of entity files to read
            entity_files = [
                "users.json",
                "products.json",
                "stock_units.json",
                "orders.json",
                "order_items.json",
                "payments.json",
                "restock_subscriptions.json",
                "complaint_cases.json",
                "complaint_attachments.json",
                "bot_settings.json",
                "broadcast_logs.json",
                "audit_logs.json",
            ]

            for entity_file in entity_files:
                entity_type = entity_file.replace(".json", "")
                if entity_file in zf.namelist():
                    content = zf.read(entity_file).decode("utf-8")
                    entities = json.loads(content)
                    backup_data["entities"][entity_type] = entities
                    logger.info(f"Parsed {len(entities)} {entity_type} from backup")
                else:
                    backup_data["entities"][entity_type] = []

    except Exception as e:
        raise BackupValidationError(f"Error parsing backup: {e}")

    return backup_data


def _get_existing_user_ids(session: Session) -> set[int]:
    """Get set of existing user telegram IDs for duplicate detection."""
    users = session.query(User.telegram_id).all()
    return {user[0] for user in users}


def _get_existing_product_names(session: Session) -> set[str]:
    """Get set of existing product names for duplicate detection."""
    products = session.query(Product.name).all()
    return {product[0] for product in products}


def _get_existing_order_refs(session: Session) -> set[str]:
    """Get set of existing order references for duplicate detection."""
    orders = session.query(Order.order_ref).all()
    return {order[0] for order in orders}


def _get_existing_complaint_refs(session: Session) -> set[str]:
    """Get set of existing complaint references for duplicate detection."""
    complaints = session.query(ComplaintCase.complaint_ref).all()
    return {complaint[0] for complaint in complaints}


def _get_existing_payment_refs(session: Session) -> set[str]:
    """Get set of existing payment references for duplicate detection."""
    payments = session.query(Payment.payment_ref).all()
    return {payment[0] for payment in payments}


def _get_existing_stock_usernames(session: Session) -> dict[int, set[str]]:
    """Get set of existing stock username keys per product for duplicate detection."""
    stocks = session.query(StockUnit.product_id, StockUnit.username_key).all()
    result = {}
    for product_id, username_key in stocks:
        if product_id not in result:
            result[product_id] = set()
        if username_key:
            result[product_id].add(username_key)
    return result


def detect_duplicates(
    session: Session, backup_data: dict[str, Any]
) -> dict[str, list[dict[str, Any]]]:
    """
    Detect duplicate entities in backup that already exist in database.

    Args:
        session: Database session
        backup_data: Parsed backup data

    Returns:
        Dictionary with duplicate entities organized by type
    """
    duplicates = {
        "users": [],
        "products": [],
        "orders": [],
        "order_items": [],
        "payments": [],
        "stock_units": [],
        "complaint_cases": [],
    }

    # Check users by telegram_id
    existing_telegram_ids = _get_existing_user_ids(session)
    for user in backup_data["entities"].get("users", []):
        if user.get("telegram_id") in existing_telegram_ids:
            duplicates["users"].append(user)
            logger.info(f"Duplicate user detected: {user.get('username')} (ID: {user.get('telegram_id')})")

    # Check products by name
    existing_product_names = _get_existing_product_names(session)
    for product in backup_data["entities"].get("products", []):
        if product.get("name") in existing_product_names:
            duplicates["products"].append(product)
            logger.info(f"Duplicate product detected: {product.get('name')}")

    # Check orders by order_ref
    existing_order_refs = _get_existing_order_refs(session)
    for order in backup_data["entities"].get("orders", []):
        if order.get("order_ref") in existing_order_refs:
            duplicates["orders"].append(order)
            logger.info(f"Duplicate order detected: {order.get('order_ref')}")

    # Check payments by payment_ref
    existing_payment_refs = _get_existing_payment_refs(session)
    for payment in backup_data["entities"].get("payments", []):
        if payment.get("payment_ref") in existing_payment_refs:
            duplicates["payments"].append(payment)
            logger.info(f"Duplicate payment detected: {payment.get('payment_ref')}")

    # Check complaints by complaint_ref
    existing_complaint_refs = _get_existing_complaint_refs(session)
    for complaint in backup_data["entities"].get("complaint_cases", []):
        if complaint.get("complaint_ref") in existing_complaint_refs:
            duplicates["complaint_cases"].append(complaint)
            logger.info(f"Duplicate complaint detected: {complaint.get('complaint_ref')}")

    # Check stock units by username_key and product_id
    existing_stock_usernames = _get_existing_stock_usernames(session)
    for stock in backup_data["entities"].get("stock_units", []):
        product_id = stock.get("product_id")
        username_key = stock.get("username_key")
        if product_id and username_key:
            if product_id in existing_stock_usernames and username_key in existing_stock_usernames[product_id]:
                duplicates["stock_units"].append(stock)
                logger.info(f"Duplicate stock detected: {username_key} (product_id: {product_id})")

    logger.info(f"Total duplicates detected: {sum(len(v) for v in duplicates.values())}")
    return duplicates


async def send_restore_progress(
    context: Any, message: str, progress: RestoreProgress | None = None
) -> None:
    """
    Send restore progress update to user (stub for integration).

    Args:
        context: Update context
        message: Progress message
        progress: Optional progress object with percentage
    """
    logger.info(f"Restore progress: {message}")
    if progress:
        logger.info(f"  Progress: {progress.current_step}/{progress.total_steps} ({progress.percentage()}%)")


def get_restore_summary(statistics: list[RestoreStatistics]) -> str:
    """
    Generate human-readable summary of restore operation.

    Args:
        statistics: List of restore statistics per entity type

    Returns:
        Summary string
    """
    summary_lines = ["📊 Restore Summary:"]
    total_imported = 0
    total_skipped = 0
    total_errors = 0

    for stat in statistics:
        if stat.total_in_backup > 0:
            status = "✓" if stat.errors == 0 else "⚠"
            summary_lines.append(
                f"{status} {stat.entity_type}: {stat.imported} imported, {stat.skipped} skipped"
            )
            if stat.error_details:
                for detail in stat.error_details[:3]:
                    summary_lines.append(f"    - {detail}")
            total_imported += stat.imported
            total_skipped += stat.skipped
            total_errors += stat.errors

    summary_lines.append("")
    summary_lines.append(f"Total: {total_imported} imported, {total_skipped} skipped, {total_errors} errors")

    return "\n".join(summary_lines)
