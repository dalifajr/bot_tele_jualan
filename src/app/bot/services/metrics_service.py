from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from sqlalchemy import and_, func, select
from sqlalchemy.orm import Session

from app.common.config import get_settings
from app.db.models import ListenerEvent, NotificationRetryJob, Order, Payment

settings = get_settings()


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
    revenue_this_month: int
    revenue_last_month: int
    revenue_total: int


def _utcnow() -> datetime:
    return datetime.utcnow()


def _safe_ratio(numerator: int, denominator: int) -> float:
    if denominator <= 0:
        return 0.0
    return float(numerator) / float(denominator)


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


def collect_operational_metrics(session: Session, window_hours: int = 24) -> OperationalMetrics:
    safe_window_hours = max(1, int(window_hours))
    now = _utcnow()
    window_start = now - timedelta(hours=safe_window_hours)

    orders_created = int(
        session.scalar(select(func.count(Order.id)).where(Order.created_at >= window_start)) or 0
    )
    orders_paid = int(
        session.scalar(select(func.count(Order.id)).where(Order.paid_at.is_not(None), Order.paid_at >= window_start)) or 0
    )
    orders_delivered = int(
        session.scalar(select(func.count(Order.id)).where(Order.delivered_at.is_not(None), Order.delivered_at >= window_start)) or 0
    )
    orders_expired = int(
        session.scalar(
            select(func.count(Order.id)).where(
                Order.status == "expired",
                Order.cancelled_at.is_not(None),
                Order.cancelled_at >= window_start,
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
                    Order.cancelled_at >= window_start,
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
                Payment.matched_at >= window_start,
            )
        )
        or 0
    )

    listener_total = int(
        session.scalar(select(func.count(ListenerEvent.id)).where(ListenerEvent.created_at >= window_start)) or 0
    )
    listener_failed = int(
        session.scalar(
            select(func.count(ListenerEvent.id)).where(
                ListenerEvent.created_at >= window_start,
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
    month_start_utc = _to_utc_naive(month_start_local)
    next_month_start_utc = _to_utc_naive(next_month_start_local)
    last_month_start_utc = _to_utc_naive(last_month_start_local)

    revenue_today = _sum_delivered_revenue(session, start_utc_naive=today_start_utc)
    revenue_this_month = _sum_delivered_revenue(
        session,
        start_utc_naive=month_start_utc,
        end_utc_naive=next_month_start_utc,
    )
    revenue_last_month = _sum_delivered_revenue(
        session,
        start_utc_naive=last_month_start_utc,
        end_utc_naive=month_start_utc,
    )
    revenue_total = _sum_delivered_revenue(session)

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
        revenue_this_month=revenue_this_month,
        revenue_last_month=revenue_last_month,
        revenue_total=revenue_total,
    )


def format_operational_metrics_report(metrics: OperationalMetrics) -> str:
    def pct(value: float) -> str:
        return f"{value * 100:.1f}%"

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
            f"Bulan ini: <b>{_format_rupiah(metrics.revenue_this_month)}</b>",
            f"Bulan Kemarin: <b>{_format_rupiah(metrics.revenue_last_month)}</b>",
            f"Total Pendapatan: <b>{_format_rupiah(metrics.revenue_total)}</b>",
        ]
    )
