from __future__ import annotations

import os
import logging
import time
from pathlib import Path

from sqlalchemy import delete


# Set env before app/settings import.
os.environ["DATABASE_URL"] = "sqlite:///./data/latency_smoke.db"
os.environ["BOT_TOKEN"] = ""
os.environ["LISTENER_SHARED_SECRET"] = "latency-smoke"
os.environ["TELEMETRY_PERSIST_ENABLED"] = "true"

from app.bot.services.catalog_service import (  # noqa: E402
    add_product,
    add_stock_block,
    get_available_stock_count,
    get_nearest_awaiting_ready_at,
    get_product,
    list_products,
)
from app.bot.services.github_pack_service import (  # noqa: E402
    add_github_stock,
    add_saved_github_stock,
    ensure_github_pack_product,
    ensure_github_pack_used_product,
    list_github_stocks,
    list_saved_github_stocks,
    list_sold_github_stocks,
)
from app.bot.services.metrics_service import collect_operational_metrics, collect_runtime_telemetry_metrics  # noqa: E402
from app.bot.services.order_service import count_delivered_orders_by_customer, create_checkout  # noqa: E402
from app.bot.services.user_service import upsert_user  # noqa: E402
from app.common.telemetry import log_telemetry  # noqa: E402
from app.db.bootstrap import init_db  # noqa: E402
from app.db.database import get_session  # noqa: E402
from app.db.models import (  # noqa: E402
    AuditLog,
    BotSetting,
    BroadcastLog,
    ListenerEvent,
    NotificationRetryJob,
    Order,
    OrderItem,
    Payment,
    Product,
    RestockSubscription,
    StockUnit,
    TelemetryEvent,
    User,
)


def _reset_data() -> None:
    with get_session() as session:
        for model in (
            Payment,
            OrderItem,
            Order,
            StockUnit,
            RestockSubscription,
            Product,
            NotificationRetryJob,
            ListenerEvent,
            TelemetryEvent,
            BroadcastLog,
            AuditLog,
            BotSetting,
            User,
        ):
            session.execute(delete(model))


def _benchmark(label: str, fn) -> int:
    started = time.perf_counter()
    fn()
    duration_ms = int((time.perf_counter() - started) * 1000)
    print(f"{label}: {duration_ms} ms")
    return duration_ms


def _seed() -> tuple[int, int]:
    with get_session() as session:
        customer = upsert_user(
            session=session,
            telegram_id=900001,
            username="latency_customer",
            full_name="Latency Customer",
            role="customer",
        )
        product = add_product(
            session=session,
            name="Latency Smoke Product",
            price=10000,
            description="Product for latency smoke",
            actor_id=None,
        )
        for idx in range(8):
            add_stock_block(
                session=session,
                product_id=int(product.id),
                raw_text=f"Latency Smoke Account\nUsername: latency_user_{idx}\nPassword: pass{idx}",
                actor_id=None,
            )

        ensure_github_pack_product(session)
        ensure_github_pack_used_product(session)
        add_github_stock(
            session=session,
            raw_text="GitHub Students Dev Pack\nUsername: gh_latency_ready\nPassword: pass",
            actor_id=None,
            awaiting=False,
        )
        add_saved_github_stock(
            session=session,
            raw_text="GitHub Students Dev Pack\nUsername: gh_latency_saved\nPassword: pass",
            actor_id=None,
        )
        return int(customer.id), int(product.id)


def main() -> None:
    Path("data").mkdir(exist_ok=True)
    init_db()
    _reset_data()
    customer_id, product_id = _seed()

    durations = [
        _benchmark(
            "start_main_menu_data",
            lambda: _count_customer_orders(customer_id),
        ),
        _benchmark("customer_catalog", lambda: _customer_catalog()),
        _benchmark("product_detail", lambda: _product_detail(product_id)),
        _benchmark("checkout_one_product", lambda: _checkout(customer_id, product_id)),
        _benchmark("admin_github_pack_menu_data", lambda: _github_pack_menu_data()),
        _benchmark("ops_metrics", lambda: _ops_metrics()),
    ]

    max_duration = max(durations) if durations else 0
    print(f"max_duration_ms: {max_duration}")
    if max_duration > 1000:
        raise SystemExit("Latency smoke failed: one or more hot paths exceeded 1000 ms.")


def _count_customer_orders(customer_id: int) -> None:
    with get_session() as session:
        count_delivered_orders_by_customer(session, customer_id)


def _customer_catalog() -> None:
    with get_session() as session:
        list_products(session=session, include_suspended=False)


def _product_detail(product_id: int) -> None:
    with get_session() as session:
        get_product(session, product_id)
        get_available_stock_count(session, product_id)
        get_nearest_awaiting_ready_at(session, product_id)


def _checkout(customer_id: int, product_id: int) -> None:
    with get_session() as session:
        customer = session.get(User, customer_id)
        if customer is None:
            raise RuntimeError("Smoke customer not found.")
        create_checkout(session, customer=customer, product_id=product_id, quantity=1)


def _github_pack_menu_data() -> None:
    with get_session() as session:
        ensure_github_pack_product(session)
        ensure_github_pack_used_product(session)
        list_github_stocks(session)
        list_saved_github_stocks(session)
        list_sold_github_stocks(session)


def _ops_metrics() -> None:
    log_telemetry(
        logging.getLogger("latency_smoke"),
        "bot.handler_latency",
        handler="latency_smoke",
        update_type="smoke",
        callback_prefix="",
        duration_ms=1,
        success=True,
        role="customer",
    )
    with get_session() as session:
        collect_operational_metrics(session, window_hours=24)
        collect_runtime_telemetry_metrics(session, window_hours=24)


if __name__ == "__main__":
    main()
