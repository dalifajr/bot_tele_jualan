from __future__ import annotations

import json
import os
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path

from fastapi.testclient import TestClient
from sqlalchemy import delete, select

# Set env before app/settings import.
os.environ["DATABASE_URL"] = "sqlite:///./data/e2e_test.db"
os.environ["LISTENER_SHARED_SECRET"] = "e2e-secret"
os.environ["LISTENER_ALLOW_LEGACY_SECRET"] = "false"
os.environ["LISTENER_REQUIRE_REFERENCE"] = "false"
os.environ["BOT_TOKEN"] = ""

from app.api.main import app  # noqa: E402
from app.api.security import build_signature  # noqa: E402
from app.bot.services.catalog_service import add_product, add_stock_block  # noqa: E402
from app.bot.services.metrics_service import collect_operational_metrics, format_operational_metrics_report  # noqa: E402
from app.bot.services.notification_retry_service import (  # noqa: E402
    enqueue_notification_retry,
    list_due_notification_retries,
    mark_notification_retry_failed,
    mark_notification_retry_sent,
)
from app.bot.services.order_service import (  # noqa: E402
    create_checkout,
    get_customer_order_detail,
    get_customer_order_status_by_ref,
    get_customer_orders_page,
    get_quick_reorder_target,
    reconcile_payment,
)
from app.bot.services.restock_service import (  # noqa: E402
    list_ready_restock_notifications,
    mark_restock_notified,
    subscribe_restock,
)
from app.bot.services.user_service import upsert_user  # noqa: E402
from app.common.roles import is_admin, sync_admin_ids_from_file_to_db  # noqa: E402
from app.db.bootstrap import init_db  # noqa: E402
from app.db.database import get_session  # noqa: E402
from app.db.models import NotificationRetryJob, Order, Payment, Product, StockUnit, User  # noqa: E402


def _reset_db_file() -> None:
    db_path = Path("data/e2e_test.db")
    db_path.parent.mkdir(parents=True, exist_ok=True)
    if db_path.exists():
        db_path.unlink()


def _clear_db() -> None:
    with get_session() as session:
        session.execute(delete(Payment))
        session.execute(delete(Order))
        session.execute(delete(StockUnit))
        session.execute(delete(Product))
        session.execute(delete(User))


def _seed_checkout(suffix: str) -> tuple[int, str, str, int]:
    with get_session() as session:
        user = upsert_user(
            session=session,
            telegram_id=987654321,
            username=f"e2e_user_{suffix}",
            full_name=f"E2E User {suffix}",
            role="customer",
        )

        product = add_product(
            session=session,
            name=f"GitHub Students Dev Pack {suffix}",
            price=50000,
            description="E2E test product",
            actor_id=None,
        )
        add_stock_block(
            session=session,
            product_id=product.id,
            raw_text=(
                "*GitHub Students Dev Pack*\n"
                "Username: hadifirdausk\n"
                "Password: Lq2TJGYDJG9n8mK=\n"
                "F2A: O4RRLUNYNWKN4S6K\n"
            ),
            actor_id=None,
        )
        add_stock_block(
            session=session,
            product_id=product.id,
            raw_text=(
                "*GitHub Students Dev Pack*\n"
                "Username: hadifirdausk_backup\n"
                "Password: jAKN92mNwe92LsE=\n"
                "F2A: 7ND9KQ1MZP4YQ2LR\n"
            ),
            actor_id=None,
        )

        order, payment = create_checkout(
            session=session,
            customer=user,
            product_id=product.id,
            quantity=1,
        )
        return payment.expected_amount, payment.payment_ref, order.order_ref, int(user.id)


def _signed_headers(secret: str, body: bytes, key: str) -> dict[str, str]:
    ts = str(int(time.time()))
    sig = build_signature(secret, ts, body)
    return {
        "Content-Type": "application/json",
        "X-Timestamp": ts,
        "X-Signature": sig,
        "X-Idempotency-Key": key,
    }


def _run_paid_flow(client: TestClient) -> bool:
    amount, payment_ref, order_ref, customer_id = _seed_checkout("paid")

    payload = {
        "amount": amount,
        "source_app": "TEST_APP",
        "reference": payment_ref,
        "raw_text": f"Pembayaran berhasil Rp{amount}",
        "metadata": {"e2e": True, "scenario": "paid"},
    }
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")

    first = client.post(
        "/listener/payment",
        content=body,
        headers=_signed_headers("e2e-secret", body, "e2e-paid-1"),
    )
    replay = client.post(
        "/listener/payment",
        content=body,
        headers=_signed_headers("e2e-secret", body, "e2e-paid-1"),
    )
    duplicate = client.post(
        "/listener/payment",
        content=body,
        headers=_signed_headers("e2e-secret", body, "e2e-paid-2"),
    )

    with get_session() as session:
        page = get_customer_orders_page(session, customer_id=customer_id, page=1, page_size=10)
        detail = get_customer_order_detail(session, customer_id=customer_id, order_ref=order_ref)
        status_view = get_customer_order_status_by_ref(session, customer_id=customer_id, order_ref=order_ref)

    print("\n=== SCENARIO: PAID FLOW ===")
    print("expected_amount:", amount)
    print("payment_ref:", payment_ref)
    print("order_ref:", order_ref)
    print("first:", first.status_code, first.json())
    print("replay:", replay.status_code, replay.json())
    print("duplicate:", duplicate.status_code, duplicate.json())
    print("page_total:", page.total_items)
    print("detail_status:", (detail.status if detail else None), "accounts:", (len(detail.account_blocks) if detail else None))
    print("status_view:", (status_view.status if status_view else None))

    return (
        first.status_code == 200
        and first.json().get("status") == "paid"
        and replay.status_code == 200
        and replay.json().get("idempotent_replay") is True
        and duplicate.status_code == 200
        and duplicate.json().get("status") == "duplicate"
        and page.total_items >= 1
        and any(row.order_ref == order_ref and row.status == "delivered" for row in page.rows)
        and detail is not None
        and detail.status == "delivered"
        and len(detail.account_blocks) >= 1
        and status_view is not None
        and status_view.status == "delivered"
    )


def _run_expired_flow(client: TestClient) -> bool:
    amount, payment_ref, order_ref, customer_id = _seed_checkout("expired")

    with get_session() as session:
        order = session.scalar(select(Order).where(Order.order_ref == order_ref))
        if order is not None:
            order.expires_at = datetime.now(timezone.utc) - timedelta(minutes=1)
            session.add(order)

    payload = {
        "amount": amount,
        "source_app": "TEST_APP",
        "reference": payment_ref,
        "raw_text": f"Pembayaran terlambat Rp{amount}",
        "metadata": {"e2e": True, "scenario": "expired"},
    }
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")

    expired = client.post(
        "/listener/payment",
        content=body,
        headers=_signed_headers("e2e-secret", body, "e2e-expired-1"),
    )

    with get_session() as session:
        order = session.scalar(select(Order).where(Order.order_ref == order_ref))
        payment = session.scalar(select(Payment).where(Payment.payment_ref == payment_ref))
        status_view = get_customer_order_status_by_ref(session, customer_id=customer_id, order_ref=order_ref)

    print("\n=== SCENARIO: EXPIRED FLOW ===")
    print("expected_amount:", amount)
    print("payment_ref:", payment_ref)
    print("order_ref:", order_ref)
    print("expired:", expired.status_code, expired.json())
    print("order_status_db:", (order.status if order else None))
    print("payment_status_db:", (payment.status if payment else None))
    print("status_view:", (status_view.status if status_view else None))

    return (
        expired.status_code == 200
        and expired.json().get("status") == "expired"
        and order is not None
        and order.status == "expired"
        and payment is not None
        and payment.status in {"expired", "pending"}
        and status_view is not None
        and status_view.status == "expired"
    )


def _run_orders_pagination_flow() -> bool:
    with get_session() as session:
        user = upsert_user(
            session=session,
            telegram_id=987654399,
            username="e2e_pagination",
            full_name="E2E Pagination",
            role="customer",
        )

        product = add_product(
            session=session,
            name="Pagination Product",
            price=25000,
            description="E2E pagination test",
            actor_id=None,
        )

        for idx in range(20):
            add_stock_block(
                session=session,
                product_id=product.id,
                raw_text=(
                    "*Pagination Product*\n"
                    f"Username: page_user_{idx}\n"
                    "Password: pagetest123\n"
                    "F2A: PAGETEST\n"
                ),
                actor_id=None,
            )

        refs: list[str] = []
        for _ in range(11):
            order, _ = create_checkout(
                session=session,
                customer=user,
                product_id=product.id,
                quantity=1,
            )
            refs.append(order.order_ref)

        page1 = get_customer_orders_page(session, customer_id=int(user.id), page=1, page_size=10)
        page2 = get_customer_orders_page(session, customer_id=int(user.id), page=2, page_size=10)
        page_out_of_range = get_customer_orders_page(session, customer_id=int(user.id), page=999, page_size=10)
        detail = get_customer_order_detail(session, customer_id=int(user.id), order_ref=refs[-1])

    print("\n=== SCENARIO: ORDERS PAGINATION FLOW ===")
    print("total_items:", page1.total_items)
    print("page1:", page1.page, "rows:", len(page1.rows), "total_pages:", page1.total_pages)
    print("page2:", page2.page, "rows:", len(page2.rows), "total_pages:", page2.total_pages)
    print("page_out_of_range:", page_out_of_range.page, "rows:", len(page_out_of_range.rows))
    print("detail_status:", (detail.status if detail else None), "order_ref:", refs[-1])

    return (
        page1.total_items == 11
        and page1.page == 1
        and page1.total_pages == 2
        and len(page1.rows) == 10
        and page2.page == 2
        and len(page2.rows) == 1
        and page_out_of_range.page == 2
        and len(page_out_of_range.rows) == 1
        and detail is not None
        and detail.status == "pending_payment"
    )


def _run_quick_reorder_flow(client: TestClient) -> bool:
    amount, payment_ref, source_order_ref, customer_id = _seed_checkout("reorder")

    payload = {
        "amount": amount,
        "source_app": "TEST_APP",
        "reference": payment_ref,
        "raw_text": f"Pembayaran reorder source Rp{amount}",
        "metadata": {"e2e": True, "scenario": "quick_reorder_source_paid"},
    }
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
    paid = client.post(
        "/listener/payment",
        content=body,
        headers=_signed_headers("e2e-secret", body, "e2e-reorder-source-1"),
    )

    with get_session() as session:
        customer = session.scalar(select(User).where(User.id == customer_id))
        if customer is None:
            print("\n=== SCENARIO: QUICK REORDER FLOW ===")
            print("customer not found")
            return False

        target = get_quick_reorder_target(
            session,
            customer_id=customer_id,
            source_order_ref=source_order_ref,
        )
        reorder_order, reorder_payment = create_checkout(
            session=session,
            customer=customer,
            product_id=target.product_id,
            quantity=target.quantity,
        )
        reorder_status = get_customer_order_status_by_ref(
            session,
            customer_id=customer_id,
            order_ref=reorder_order.order_ref,
        )

    print("\n=== SCENARIO: QUICK REORDER FLOW ===")
    print("source_order_ref:", source_order_ref)
    print("target_product:", target.product_name, "qty:", target.quantity)
    print("paid_source:", paid.status_code, paid.json())
    print("new_order_ref:", reorder_order.order_ref)
    print("new_payment_ref:", reorder_payment.payment_ref)
    print("new_order_status:", reorder_order.status)
    print("new_payment_status:", reorder_payment.status)
    print("new_status_view:", (reorder_status.status if reorder_status else None))

    return (
        paid.status_code == 200
        and paid.json().get("status") == "paid"
        and target.quantity == 1
        and reorder_order.order_ref != source_order_ref
        and reorder_order.status == "pending_payment"
        and reorder_payment.status == "pending"
        and reorder_status is not None
        and reorder_status.status == "pending_payment"
    )


def _run_upsell_flow() -> bool:
    with get_session() as session:
        user = upsert_user(
            session=session,
            telegram_id=987654555,
            username="e2e_upsell",
            full_name="E2E Upsell",
            role="customer",
        )

        primary_product = add_product(
            session=session,
            name="Upsell Primary Product",
            price=70000,
            description="Primary",
            actor_id=None,
        )
        upsell_product = add_product(
            session=session,
            name="Upsell Companion Product",
            price=45000,
            description="Companion",
            actor_id=None,
        )

        for idx in range(8):
            add_stock_block(
                session=session,
                product_id=primary_product.id,
                raw_text=(
                    "*Upsell Primary Product*\n"
                    f"Username: upsell_primary_{idx}\n"
                    "Password: upsellprimarypass\n"
                    "F2A: UPSELLPRIMARY\n"
                ),
                actor_id=None,
            )

        for idx in range(2):
            add_stock_block(
                session=session,
                product_id=upsell_product.id,
                raw_text=(
                    "*Upsell Companion Product*\n"
                    f"Username: upsell_user_{idx}\n"
                    "Password: upsellpass\n"
                    "F2A: UPSELL\n"
                ),
                actor_id=None,
            )

        last_delivery_message = ""
        paid_ok = True
        for idx in range(3):
            order, payment = create_checkout(
                session=session,
                customer=user,
                product_id=primary_product.id,
                quantity=1,
            )
            result = reconcile_payment(
                session=session,
                amount=payment.expected_amount,
                source_app="TEST_APP",
                reference=payment.payment_ref,
                raw_payload={"scenario": "upsell", "idx": idx},
            )
            if result.status != "paid":
                paid_ok = False
            last_delivery_message = result.delivery_message or ""

        reorder_order, reorder_payment = create_checkout(
            session=session,
            customer=user,
            product_id=primary_product.id,
            quantity=1,
        )

    has_no_voucher_discount = int(reorder_order.total_amount) == int(reorder_order.subtotal) + int(reorder_order.unique_code)
    has_upsell_copy = "🎯 <b>Rekomendasi Selanjutnya</b>" in last_delivery_message
    has_no_voucher_copy = "Voucher Loyalti" not in last_delivery_message

    print("\n=== SCENARIO: UPSELL FLOW ===")
    print("paid_ok:", paid_ok)
    print("reorder_order:", reorder_order.order_ref, "subtotal:", reorder_order.subtotal, "unique_code:", reorder_order.unique_code)
    print("reorder_payment_expected:", reorder_payment.expected_amount)
    print("has_upsell_copy:", has_upsell_copy)
    print("has_no_voucher_copy:", has_no_voucher_copy)

    return (
        paid_ok
        and has_no_voucher_discount
        and has_upsell_copy
        and has_no_voucher_copy
    )


def _run_restock_subscription_flow() -> bool:
    with get_session() as session:
        user = upsert_user(
            session=session,
            telegram_id=987654556,
            username="e2e_restock",
            full_name="E2E Restock",
            role="customer",
        )
        product = add_product(
            session=session,
            name="Restock Product",
            price=15000,
            description="Restock candidate",
            actor_id=None,
        )

        created, _message = subscribe_restock(session, customer_id=int(user.id), product_id=int(product.id))
        ready_before = list_ready_restock_notifications(session, limit=10)

        add_stock_block(
            session=session,
            product_id=product.id,
            raw_text=(
                "*Restock Product*\n"
                "Username: restock_user\n"
                "Password: restockpass\n"
                "F2A: RESTOCK\n"
            ),
            actor_id=None,
        )

        ready_after_stock = list_ready_restock_notifications(session, limit=10)
        target_ready = [
            row for row in ready_after_stock
            if row.product_id == int(product.id) and row.customer_telegram_id == int(user.telegram_id)
        ]
        marked = False
        if target_ready:
            marked = mark_restock_notified(session, target_ready[0].subscription_id)
        ready_after_mark = list_ready_restock_notifications(session, limit=10)
        target_after_mark = [
            row for row in ready_after_mark
            if row.product_id == int(product.id) and row.customer_telegram_id == int(user.telegram_id)
        ]

    print("\n=== SCENARIO: RESTOCK SUBSCRIPTION FLOW ===")
    print("created:", created)
    print("ready_before:", len(ready_before))
    print("ready_after_stock:", len(target_ready))
    print("marked:", marked)
    print("ready_after_mark:", len(target_after_mark))

    return created and len(ready_before) == 0 and len(target_ready) >= 1 and marked and len(target_after_mark) == 0


def _run_notification_retry_flow() -> bool:
    with get_session() as session:
        first_job_id = enqueue_notification_retry(
            session=session,
            channel="e2e",
            chat_id=123456789,
            payload_text="retry message",
            parse_mode="HTML",
            max_attempts=2,
            delay_seconds=0,
        )
        due_first = list_due_notification_retries(session, limit=10)

        mark_notification_retry_failed(
            session=session,
            job_id=first_job_id,
            error="e2e fail 1",
            backoff_seconds=1,
        )

        first_job = session.get(NotificationRetryJob, first_job_id)
        if first_job is not None:
            first_job.next_attempt_at = datetime.now(timezone.utc) - timedelta(seconds=1)
            session.add(first_job)

        due_second = list_due_notification_retries(session, limit=10)

        mark_notification_retry_failed(
            session=session,
            job_id=first_job_id,
            error="e2e fail 2",
            backoff_seconds=1,
        )

        failed_job = session.get(NotificationRetryJob, first_job_id)

        sent_job_id = enqueue_notification_retry(
            session=session,
            channel="e2e",
            chat_id=123456780,
            payload_text="retry sent",
            parse_mode="HTML",
            max_attempts=2,
            delay_seconds=0,
        )
        mark_notification_retry_sent(session=session, job_id=sent_job_id)
        sent_job = session.get(NotificationRetryJob, sent_job_id)

    print("\n=== SCENARIO: NOTIFICATION RETRY FLOW ===")
    print("due_first:", len(due_first))
    print("due_second:", len(due_second))
    print("failed_job_status:", (failed_job.status if failed_job else None), "attempt:", (failed_job.attempt_count if failed_job else None))
    print("sent_job_status:", (sent_job.status if sent_job else None))

    return (
        any(job.id == first_job_id for job in due_first)
        and any(job.id == first_job_id for job in due_second)
        and failed_job is not None
        and failed_job.status == "failed"
        and int(failed_job.attempt_count) == 2
        and sent_job is not None
        and sent_job.status == "sent"
    )


def _run_rbac_db_flow() -> bool:
    role_file = Path("data/e2e_role_rbac.txt")
    role_file.parent.mkdir(parents=True, exist_ok=True)
    role_file.write_text("# e2e role file\nadmin:555123\n", encoding="utf-8")

    with get_session() as session:
        sync_admin_ids_from_file_to_db(session=session, role_file=role_file)
        db_user = session.scalar(select(User).where(User.telegram_id == 555123))

    resolved_admin = is_admin(555123, role_file)

    print("\n=== SCENARIO: RBAC DB FLOW ===")
    print("resolved_admin:", resolved_admin)
    print("db_role:", (db_user.role if db_user else None))

    return resolved_admin and db_user is not None and db_user.role == "admin"


def _run_metrics_flow() -> bool:
    with get_session() as session:
        metrics = collect_operational_metrics(session, window_hours=24)
        report = format_operational_metrics_report(metrics)

    print("\n=== SCENARIO: METRICS FLOW ===")
    print("orders_created:", metrics.orders_created)
    print("listener_total:", metrics.listener_total)
    print("retry_pending:", metrics.retry_pending)
    print("report_title:", "📊 <b>Laporan Operasional</b>" in report)
    print("report_revenue:", "💵 <b>Pendapatan</b>" in report)
    print("report_yesterday:", "Kemarin:" in report)
    print("report_top_product:", "Produk terlaris" in report)

    return (
        metrics.orders_created >= 1
        and metrics.listener_total >= 1
        and metrics.retry_pending >= 0
        and "📊 <b>Laporan Operasional</b>" in report
        and "💵 <b>Pendapatan</b>" in report
        and "Kemarin:" in report
        and "Produk terlaris" in report
    )


def main() -> int:
    _reset_db_file()
    init_db()
    _clear_db()
    client = TestClient(app)

    ok_paid = _run_paid_flow(client)
    ok_expired = _run_expired_flow(client)
    ok_pagination = _run_orders_pagination_flow()
    ok_quick_reorder = _run_quick_reorder_flow(client)
    ok_upsell = _run_upsell_flow()
    ok_restock = _run_restock_subscription_flow()
    ok_retry_queue = _run_notification_retry_flow()
    ok_rbac_db = _run_rbac_db_flow()
    ok_metrics = _run_metrics_flow()

    print("\n=== E2E SUMMARY ===")
    print("paid_flow:", ok_paid)
    print("expired_flow:", ok_expired)
    print("pagination_flow:", ok_pagination)
    print("quick_reorder_flow:", ok_quick_reorder)
    print("upsell_flow:", ok_upsell)
    print("restock_subscription_flow:", ok_restock)
    print("retry_queue_flow:", ok_retry_queue)
    print("rbac_db_flow:", ok_rbac_db)
    print("metrics_flow:", ok_metrics)

    ok = (
        ok_paid
        and ok_expired
        and ok_pagination
        and ok_quick_reorder
        and ok_upsell
        and ok_restock
        and ok_retry_queue
        and ok_rbac_db
        and ok_metrics
    )

    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
