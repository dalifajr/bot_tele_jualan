from __future__ import annotations

from sqlalchemy import text

from app.common.config import get_settings
from app.common.roles import sync_admin_ids_from_file_to_db
from app.db.database import get_session
from app.db.database import engine
from app.db.models import Base


def _ensure_column(conn, table_name: str, column_name: str, ddl: str) -> None:
    rows = conn.execute(text(f"PRAGMA table_info({table_name})")).mappings().all()
    existing = {row["name"] for row in rows}
    if column_name in existing:
        return
    conn.execute(text(f"ALTER TABLE {table_name} ADD COLUMN {ddl}"))


def _run_compat_migrations() -> None:
    with engine.begin() as conn:
        _ensure_column(conn, "stock_units", "stock_status", "stock_status VARCHAR(32) DEFAULT 'ready'")
        _ensure_column(conn, "stock_units", "available_at", "available_at DATETIME")
        _ensure_column(conn, "stock_units", "username_key", "username_key VARCHAR(255)")

        _ensure_column(conn, "orders", "expires_at", "expires_at DATETIME")
        _ensure_column(conn, "orders", "cancelled_at", "cancelled_at DATETIME")
        _ensure_column(conn, "orders", "cancel_reason", "cancel_reason TEXT")
        _ensure_column(conn, "orders", "checkout_chat_id", "checkout_chat_id BIGINT")
        _ensure_column(conn, "orders", "checkout_message_id", "checkout_message_id BIGINT")
        _ensure_column(conn, "orders", "reminder_sent_at", "reminder_sent_at DATETIME")
        _ensure_column(conn, "orders", "admin_notify_chat_id", "admin_notify_chat_id BIGINT")
        _ensure_column(conn, "orders", "admin_notify_message_id", "admin_notify_message_id BIGINT")

        conn.execute(text(
            "UPDATE stock_units "
            "SET stock_status='ready' "
            "WHERE stock_status IS NULL OR stock_status=''"
        ))
        conn.execute(text(
            "UPDATE orders "
            "SET expires_at = datetime(created_at, '+5 minutes') "
            "WHERE status='pending_payment' AND expires_at IS NULL"
        ))

        conn.execute(text(
            "CREATE INDEX IF NOT EXISTS ix_stock_units_status_unsold "
            "ON stock_units(stock_status, is_sold)"
        ))
        conn.execute(text(
            "CREATE INDEX IF NOT EXISTS ix_stock_units_username_key "
            "ON stock_units(username_key)"
        ))
        conn.execute(text(
            "CREATE INDEX IF NOT EXISTS ix_orders_pending_reminder "
            "ON orders(status, expires_at, reminder_sent_at)"
        ))
        conn.execute(text(
            "CREATE INDEX IF NOT EXISTS ix_retry_jobs_due "
            "ON notification_retry_jobs(status, next_attempt_at, attempt_count)"
        ))
        conn.execute(text(
            "CREATE INDEX IF NOT EXISTS ix_restock_subscriptions_lookup "
            "ON restock_subscriptions(product_id, is_active, customer_id)"
        ))
        conn.execute(text(
            "CREATE INDEX IF NOT EXISTS ix_complaint_cases_status_created "
            "ON complaint_cases(status, created_at)"
        ))
        conn.execute(text(
            "CREATE INDEX IF NOT EXISTS ix_complaint_cases_customer_status "
            "ON complaint_cases(customer_id, status, created_at)"
        ))
        conn.execute(text(
            "CREATE INDEX IF NOT EXISTS ix_complaint_attachments_case "
            "ON complaint_attachments(complaint_id, id)"
        ))


def init_db() -> None:
    Base.metadata.create_all(bind=engine)
    _run_compat_migrations()

    settings = get_settings()
    with get_session() as session:
        sync_admin_ids_from_file_to_db(session=session, role_file=settings.role_file_path)
