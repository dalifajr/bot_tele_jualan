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
from app.bot.services.order_service import (  # noqa: E402
    create_checkout,
    get_customer_order_detail,
    get_customer_order_status_by_ref,
    get_customer_orders_page,
)
from app.bot.services.user_service import upsert_user  # noqa: E402
from app.db.bootstrap import init_db  # noqa: E402
from app.db.database import get_session  # noqa: E402
from app.db.models import Order, Payment, Product, StockUnit, User  # noqa: E402


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


def main() -> int:
    _reset_db_file()
    init_db()
    _clear_db()
    client = TestClient(app)

    ok_paid = _run_paid_flow(client)
    ok_expired = _run_expired_flow(client)

    print("\n=== E2E SUMMARY ===")
    print("paid_flow:", ok_paid)
    print("expired_flow:", ok_expired)

    ok = ok_paid and ok_expired

    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
