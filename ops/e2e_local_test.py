from __future__ import annotations

import json
import os
import time
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
from app.bot.services.order_service import create_checkout  # noqa: E402
from app.bot.services.user_service import upsert_user  # noqa: E402
from app.db.bootstrap import init_db  # noqa: E402
from app.db.database import get_session  # noqa: E402
from app.db.models import Order, Payment, Product, StockUnit, User  # noqa: E402


def _reset_db_file() -> None:
    db_path = Path("data/e2e_test.db")
    db_path.parent.mkdir(parents=True, exist_ok=True)
    if db_path.exists():
        db_path.unlink()


def _seed_checkout() -> tuple[int, str, str]:
    with get_session() as session:
        session.execute(delete(Payment))
        session.execute(delete(Order))
        session.execute(delete(StockUnit))
        session.execute(delete(Product))
        session.execute(delete(User))

        user = upsert_user(
            session=session,
            telegram_id=987654321,
            username="e2e_user",
            full_name="E2E User",
            role="customer",
        )

        product = add_product(
            session=session,
            name="GitHub Students Dev Pack",
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
        return payment.expected_amount, payment.payment_ref, order.order_ref


def _signed_headers(secret: str, body: bytes, key: str) -> dict[str, str]:
    ts = str(int(time.time()))
    sig = build_signature(secret, ts, body)
    return {
        "Content-Type": "application/json",
        "X-Timestamp": ts,
        "X-Signature": sig,
        "X-Idempotency-Key": key,
    }


def main() -> int:
    _reset_db_file()
    init_db()

    amount, payment_ref, order_ref = _seed_checkout()

    payload = {
        "amount": amount,
        "source_app": "TEST_APP",
        "reference": payment_ref,
        "raw_text": f"Pembayaran berhasil Rp{amount}",
        "metadata": {"e2e": True},
    }
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")

    client = TestClient(app)

    # 1) First call should process payment and deliver stock.
    first = client.post(
        "/listener/payment",
        content=body,
        headers=_signed_headers("e2e-secret", body, "e2e-key-1"),
    )

    # 2) Replay same idempotency key should return cached response.
    replay = client.post(
        "/listener/payment",
        content=body,
        headers=_signed_headers("e2e-secret", body, "e2e-key-1"),
    )

    # 3) New idempotency key + same reference should become duplicate.
    duplicate = client.post(
        "/listener/payment",
        content=body,
        headers=_signed_headers("e2e-secret", body, "e2e-key-2"),
    )

    print("=== E2E SUMMARY ===")
    print("expected_amount:", amount)
    print("payment_ref:", payment_ref)
    print("order_ref:", order_ref)
    print("first:", first.status_code, first.json())
    print("replay:", replay.status_code, replay.json())
    print("duplicate:", duplicate.status_code, duplicate.json())

    ok = (
        first.status_code == 200
        and first.json().get("status") == "paid"
        and replay.status_code == 200
        and replay.json().get("idempotent_replay") is True
        and duplicate.status_code == 200
        and duplicate.json().get("status") == "duplicate"
    )

    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
