from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from sqlalchemy import and_, func, select
from sqlalchemy.orm import Session

from app.common.config import get_settings
from app.db.models import BotSetting, ListenerEvent, NotificationRetryJob, Order, OrderItem, Payment, Product, TelemetryEvent

settings = get_settings()
METRICS_RESET_AT_KEY = "metrics_reset_at"


@dataclass
class OperationalMetrics:
    window_hours: int
    window_start: datetime
    orders_created: int
    orders_paid: int
    orders_delivered: int
    orders_expired: int
    orders_cancelled: int
    payments_paid: int
    listener_total: int
    listener_failed: int
    retry_pending: int
    retry_failed: int
    payment_match_success_rate: float
    timeout_rate: float
    checkout_to_paid_rate: float
    paid_to_delivered_rate: float
    revenue_today: int
    revenue_yesterday: int
    revenue_this_month: int
    revenue_last_month: int
    revenue_total: int
    top_products: list[tuple[str, int]]


@dataclass
class RuntimeTelemetryMetrics:
    window_hours: int
    listener_total: int
    listener_p95_ms: int | None
    listener_error_rate: float
    checkout_total: int
    checkout_p95_ms: int | None
    checkout_success_rate: float


def _utcnow() -> datetime:
    return datetime.utcnow()


def _safe_ratio(numerator: int, denominator: int) -> float:
    if denominator <= 0:
        return 0.0
    return float(numerator) / float(denominator)


def _percentile_ms(samples: list[int], percentile: int = 95) -> int | None:
    if not samples:
        return None
    ordered = sorted(max(0, int(x)) for x in samples)
    if len(ordered) == 1:
        return ordered[0]

    safe_percentile = min(100, max(1, int(percentile)))
    rank = int(round((safe_percentile / 100.0) * (len(ordered) - 1)))
    return ordered[rank]


def _format_rupiah(amount: int) -> str:
    return f"Rp{int(amount):,}".replace(",", ".")


def _display_now() -> datetime:
    now_utc = datetime.now(timezone.utc)
    try:
        return now_utc.astimezone(ZoneInfo(settings.display_timezone))
    except ZoneInfoNotFoundError:
        return now_utc


def _to_utc_naive(value: datetime) -> datetime:
    if value.tzinfo is None:
        return value
    return value.astimezone(timezone.utc).replace(tzinfo=None)


def _sum_delivered_revenue(
    session: Session,
    start_utc_naive: datetime | None = None,
    end_utc_naive: datetime | None = None,
) -> int:
    stmt = select(func.sum(Order.total_amount)).where(
        Order.status == "delivered",
        Order.delivered_at.is_not(None),
    )
    if start_utc_naive is not None:
        stmt = stmt.where(Order.delivered_at >= start_utc_naive)
    if end_utc_naive is not None:
        stmt = stmt.where(Order.delivered_at < end_utc_naive)
    return int(session.scalar(stmt) or 0)


def get_metrics_reset_at(session: Session) -> datetime | None:
    setting = session.get(BotSetting, METRICS_RESET_AT_KEY)
    if setting is None or not setting.value:
        return None
    try:
        value = datetime.fromisoformat(setting.value)
    except ValueError:
        return None

    if value.tzinfo is not None:
        value = value.astimezone(timezone.utc).replace(tzinfo=None)
    return value


def reset_operational_metrics(session: Session) -> datetime:
    now = _utcnow()
    setting = session.get(BotSetting, METRICS_RESET_AT_KEY)
    if setting is None:
        setting = BotSetting(key=METRICS_RESET_AT_KEY, value=now.isoformat())
    else:
        setting.value = now.isoformat()
    session.add(setting)
    return now


def collect_operational_metrics(session: Session, window_hours: int = 24) -> OperationalMetrics:
    safe_window_hours = max(1, int(window_hours))
    now = _utcnow()
    window_start = now - timedelta(hours=safe_window_hours)
    reset_at = get_metrics_reset_at(session)
    effective_window_start = max(window_start, reset_at) if reset_at is not None else window_start

    orders_created = int(
        session.scalar(select(func.count(Order.id)).where(Order.created_at >= effective_window_start)) or 0
    )
    orders_paid = int(
        session.scalar(
            select(func.count(Order.id)).where(Order.paid_at.is_not(None), Order.paid_at >= effective_window_start)
        )
        or 0
    )
    orders_delivered = int(
        session.scalar(
            select(func.count(Order.id)).where(
                Order.delivered_at.is_not(None),
                Order.delivered_at >= effective_window_start,
            )
        )
        or 0
    )
    orders_expired = int(
        session.scalar(
            select(func.count(Order.id)).where(
                Order.status == "expired",
                Order.cancelled_at.is_not(None),
                Order.cancelled_at >= effective_window_start,
            )
        )
        or 0
    )
    orders_cancelled = int(
        session.scalar(
            select(func.count(Order.id)).where(
                and_(
                    Order.status == "cancelled",
                    Order.cancelled_at.is_not(None),
                    Order.cancelled_at >= effective_window_start,
                )
            )
        )
        or 0
    )

    payments_paid = int(
        session.scalar(
            select(func.count(Payment.id)).where(
                Payment.status == "paid",
                Payment.matched_at.is_not(None),
                Payment.matched_at >= effective_window_start,
            )
        )
        or 0
    )

    listener_total = int(
        session.scalar(select(func.count(ListenerEvent.id)).where(ListenerEvent.created_at >= effective_window_start))
        or 0
    )
    listener_failed = int(
        session.scalar(
            select(func.count(ListenerEvent.id)).where(
                ListenerEvent.created_at >= effective_window_start,
                ListenerEvent.status == "failed",
            )
        )
        or 0
    )

    retry_pending = int(
        session.scalar(select(func.count(NotificationRetryJob.id)).where(NotificationRetryJob.status == "pending")) or 0
    )
    retry_failed = int(
        session.scalar(select(func.count(NotificationRetryJob.id)).where(NotificationRetryJob.status == "failed")) or 0
    )

    timeout_denominator = orders_delivered + orders_expired + orders_cancelled

    now_local = _display_now()
    today_start_local = now_local.replace(hour=0, minute=0, second=0, microsecond=0)
    yesterday_start_local = today_start_local - timedelta(days=1)
    month_start_local = now_local.replace(day=1, hour=0, minute=0, second=0, microsecond=0)
    if month_start_local.month == 1:
        next_month_start_local = month_start_local.replace(year=month_start_local.year + 1, month=2)
        last_month_start_local = month_start_local.replace(year=month_start_local.year - 1, month=12)
    elif month_start_local.month == 12:
        next_month_start_local = month_start_local.replace(year=month_start_local.year + 1, month=1)
        last_month_start_local = month_start_local.replace(month=11)
    else:
        next_month_start_local = month_start_local.replace(month=month_start_local.month + 1)
        last_month_start_local = month_start_local.replace(month=month_start_local.month - 1)

    today_start_utc = _to_utc_naive(today_start_local)
    yesterday_start_utc = _to_utc_naive(yesterday_start_local)
    month_start_utc = _to_utc_naive(month_start_local)
    next_month_start_utc = _to_utc_naive(next_month_start_local)
    last_month_start_utc = _to_utc_naive(last_month_start_local)

    effective_today_start = max(today_start_utc, reset_at) if reset_at is not None else today_start_utc
    effective_yesterday_start = max(yesterday_start_utc, reset_at) if reset_at is not None else yesterday_start_utc
    effective_month_start = max(month_start_utc, reset_at) if reset_at is not None else month_start_utc
    effective_last_month_start = max(last_month_start_utc, reset_at) if reset_at is not None else last_month_start_utc

    revenue_today = _sum_delivered_revenue(session, start_utc_naive=effective_today_start)
    revenue_yesterday = _sum_delivered_revenue(
        session,
        start_utc_naive=effective_yesterday_start,
        end_utc_naive=today_start_utc,
    )
    revenue_this_month = _sum_delivered_revenue(
        session,
        start_utc_naive=effective_month_start,
        end_utc_naive=next_month_start_utc,
    )
    revenue_last_month = _sum_delivered_revenue(
        session,
        start_utc_naive=effective_last_month_start,
        end_utc_naive=month_start_utc,
    )
    revenue_total = _sum_delivered_revenue(session, start_utc_naive=reset_at)

    top_products_rows = list(
        session.execute(
            select(
                Product.name,
                func.sum(OrderItem.quantity).label("total_orders"),
            )
            .join(Order, Order.id == OrderItem.order_id)
            .join(Product, Product.id == OrderItem.product_id)
            .where(
                Order.status == "delivered",
                Order.delivered_at.is_not(None),
                Order.delivered_at >= effective_window_start,
            )
            .group_by(Product.id, Product.name)
            .order_by(func.sum(OrderItem.quantity).desc(), Product.name.asc())
            .limit(5)
        ).all()
    )
    top_products = [
        (str(name), int(total_orders or 0))
        for name, total_orders in top_products_rows
    ]

    return OperationalMetrics(
        window_hours=safe_window_hours,
        window_start=window_start,
        orders_created=orders_created,
        orders_paid=orders_paid,
        orders_delivered=orders_delivered,
        orders_expired=orders_expired,
        orders_cancelled=orders_cancelled,
        payments_paid=payments_paid,
        listener_total=listener_total,
        listener_failed=listener_failed,
        retry_pending=retry_pending,
        retry_failed=retry_failed,
        payment_match_success_rate=_safe_ratio(payments_paid, listener_total),
        timeout_rate=_safe_ratio(orders_expired, timeout_denominator),
        checkout_to_paid_rate=_safe_ratio(orders_paid, orders_created),
        paid_to_delivered_rate=_safe_ratio(orders_delivered, orders_paid),
        revenue_today=revenue_today,
        revenue_yesterday=revenue_yesterday,
        revenue_this_month=revenue_this_month,
        revenue_last_month=revenue_last_month,
        revenue_total=revenue_total,
        top_products=top_products,
    )


def collect_runtime_telemetry_metrics(session: Session, window_hours: int = 24) -> RuntimeTelemetryMetrics:
    safe_window_hours = max(1, int(window_hours))
    window_start = _utcnow() - timedelta(hours=safe_window_hours)

    rows = list(
        session.execute(
            select(
                TelemetryEvent.event,
                TelemetryEvent.duration_ms,
                TelemetryEvent.success,
                TelemetryEvent.status,
            ).where(
                TelemetryEvent.created_at >= window_start,
                TelemetryEvent.event.in_(["api.payment_listener", "bot.checkout_result"]),
            )
        ).all()
    )

    listener_durations: list[int] = []
    listener_total = 0
    listener_error = 0

    checkout_durations: list[int] = []
    checkout_total = 0
    checkout_success = 0

    for event_name, duration_ms, success, status in rows:
        name = str(event_name or "")

        if name == "api.payment_listener":
            listener_total += 1
            if duration_ms is not None:
                listener_durations.append(int(duration_ms))
            if str(status or "").lower() == "error":
                listener_error += 1
            continue

        if name == "bot.checkout_result":
            checkout_total += 1
            if duration_ms is not None:
                checkout_durations.append(int(duration_ms))
            if success is True:
                checkout_success += 1

    return RuntimeTelemetryMetrics(
        window_hours=safe_window_hours,
        listener_total=listener_total,
        listener_p95_ms=_percentile_ms(listener_durations, percentile=95),
        listener_error_rate=_safe_ratio(listener_error, listener_total),
        checkout_total=checkout_total,
        checkout_p95_ms=_percentile_ms(checkout_durations, percentile=95),
        checkout_success_rate=_safe_ratio(checkout_success, checkout_total),
    )


def format_runtime_telemetry_report(metrics: RuntimeTelemetryMetrics) -> str:
    def pct(value: float) -> str:
        return f"{value * 100:.1f}%"

    listener_p95 = f"{metrics.listener_p95_ms} ms" if metrics.listener_p95_ms is not None else "-"
    checkout_p95 = f"{metrics.checkout_p95_ms} ms" if metrics.checkout_p95_ms is not None else "-"

    return "\n".join(
        [
            "🚀 <b>Runtime KPI (Telemetry)</b>",
            f"Window: {metrics.window_hours} jam terakhir",
            f"Listener total sample: <b>{metrics.listener_total}</b>",
            f"Listener p95 latency: <b>{listener_p95}</b>",
            f"Listener error rate: <b>{pct(metrics.listener_error_rate)}</b>",
            "",
            f"Checkout total sample: <b>{metrics.checkout_total}</b>",
            f"Checkout p95 latency: <b>{checkout_p95}</b>",
            f"Checkout success rate: <b>{pct(metrics.checkout_success_rate)}</b>",
        ]
    )


def format_operational_metrics_report(metrics: OperationalMetrics) -> str:
    def pct(value: float) -> str:
        return f"{value * 100:.1f}%"

    top_lines: list[str] = ["Produk terlaris"]
    if metrics.top_products:
        for idx, (name, total_orders) in enumerate(metrics.top_products, start=1):
            top_lines.append(f"{idx}. <b>{name}</b> <b>{total_orders}</b> order")
    else:
        top_lines.append("- Belum ada data produk terlaris.")

    return "\n".join(
        [
            "📊 <b>Laporan Operasional</b>",
            f"Window: {metrics.window_hours} jam terakhir",
            "",
            "🧾 <b>Order Funnel</b>",
            f"Checkout dibuat: <b>{metrics.orders_created}</b>",
            f"Paid: <b>{metrics.orders_paid}</b> (checkout->paid {pct(metrics.checkout_to_paid_rate)})",
            f"Delivered: <b>{metrics.orders_delivered}</b> (paid->delivered {pct(metrics.paid_to_delivered_rate)})",
            f"Expired: <b>{metrics.orders_expired}</b>",
            f"Cancelled: <b>{metrics.orders_cancelled}</b>",
            f"Timeout rate: <b>{pct(metrics.timeout_rate)}</b>",
            "",
            "💳 <b>Payment Match</b>",
            f"Listener total: <b>{metrics.listener_total}</b>",
            f"Listener failed: <b>{metrics.listener_failed}</b>",
            f"Payments paid: <b>{metrics.payments_paid}</b>",
            f"Match success: <b>{pct(metrics.payment_match_success_rate)}</b>",
            "",
            "📣 <b>Notification Retry Queue</b>",
            f"Pending: <b>{metrics.retry_pending}</b>",
            f"Failed permanen: <b>{metrics.retry_failed}</b>",
            "",
            "💵 <b>Pendapatan</b>",
            f"Hari ini: <b>{_format_rupiah(metrics.revenue_today)}</b>",
            f"Kemarin: <b>{_format_rupiah(metrics.revenue_yesterday)}</b>",
            f"Bulan ini: <b>{_format_rupiah(metrics.revenue_this_month)}</b>",
            f"Bulan Kemarin: <b>{_format_rupiah(metrics.revenue_last_month)}</b>",
            f"Total Pendapatan: <b>{_format_rupiah(metrics.revenue_total)}</b>",
            "",
            *top_lines,
        ]
    )
