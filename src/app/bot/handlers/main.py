from __future__ import annotations

import asyncio
from collections.abc import Sequence
from dataclasses import dataclass
from datetime import datetime, timezone
import html
from io import BytesIO
import logging
import subprocess
import time
from pathlib import Path
from typing import TypeVar
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Message, Update
from telegram.constants import ParseMode
from telegram.error import BadRequest
from telegram.ext import (
    Application,
    CallbackQueryHandler,
    CommandHandler,
    ContextTypes,
    MessageHandler,
    filters,
)

from app.bot.services.broadcast_service import (
    BroadcastAttachmentType,
    build_product_ready_broadcast_message,
    broadcast_to_customers,
)
from app.bot.services.complaint_service import (
    COMPLAINT_STATUS_AWAITING_ADMIN_REFUND_TRANSFER,
    COMPLAINT_STATUS_AWAITING_CUSTOMER_REFUND_DETAILS,
    COMPLAINT_STATUS_GROUP_DONE,
    COMPLAINT_STATUS_GROUP_NEW,
    COMPLAINT_STATUS_GROUP_PROCESS,
    COMPLAINT_STATUS_IN_PROCESS,
    COMPLAINT_STATUS_NEW,
    COMPLAINT_STATUS_REJECTED,
    COMPLAINT_STATUS_REFUND_COMPLETED,
    COMPLAINT_STATUS_REPLACEMENT_SENT,
    ComplaintDetail,
    ComplaintListItem,
    ComplaintOrderOption,
    approve_complaint_refund,
    create_customer_complaint,
    get_complaint_detail,
    list_complaints_by_statuses,
    list_customer_order_options_for_complaint,
    mark_complaint_refund_transferred,
    mark_complaint_replacement_sent,
    move_complaint_to_process,
    reject_complaint,
    reopen_done_complaint,
    set_complaint_refund_target_from_customer,
)
from app.bot.services.admin_order_notification_service import (
    build_admin_order_actions_keyboard,
    upsert_admin_order_message,
)
from app.bot.services.catalog_service import (
    add_product,
    add_stock_block,
    delete_product,
    get_available_stock_count,
    get_nearest_awaiting_ready_at,
    get_product,
    list_products,
    suspend_product,
)
from app.bot.services.qris_service import (
    build_dynamic_qris_payload,
    build_dynamic_qris_png,
    clear_qris_static_payload,
    extract_qris_payload_from_image,
    get_qris_static_payload,
    set_qris_static_payload,
)
from app.bot.services.github_pack_service import (
    GITHUB_PACK_SAVE_HOURS,
    add_github_stock,
    add_saved_github_stock,
    delete_github_stock,
    ensure_github_pack_product,
    ensure_github_pack_used_product,
    get_saved_github_stock_detail,
    get_sold_github_stock_detail,
    get_github_pack_awaiting_hours,
    get_github_stock_detail,
    is_github_pack_product,
    list_github_stocks,
    list_ready_saved_github_stocks,
    list_saved_github_stocks,
    list_sold_github_stocks,
    move_saved_github_stock_to_awaiting,
    move_sold_github_stock_to_used_product,
    set_github_pack_awaiting_hours,
    set_github_pack_price,
    set_github_pack_used_price,
)
from app.bot.services.order_service import (
    build_admin_order_message,
    cancel_order_by_admin,
    cancel_order,
    confirm_order_payment_by_admin,
    count_delivered_orders_by_customer,
    create_checkout,
    get_customer_order_detail,
    get_customer_orders_page,
    get_customer_order_status_by_ref,
    get_quick_reorder_target,
    get_order_admin_notification,
    set_admin_message_ref,
    set_checkout_message_ref,
)
from app.bot.services.metrics_service import (
    collect_operational_metrics,
    collect_runtime_telemetry_metrics,
    format_operational_metrics_report,
    format_runtime_telemetry_report,
    reset_operational_metrics,
)
from app.bot.services.notification_retry_service import collect_retry_queue_snapshot, enqueue_notification_retry
from app.bot.services.restock_service import subscribe_restock
from app.bot.services.user_service import get_user_by_telegram_id, upsert_user
from app.common.config import get_settings
from app.common.roles import get_primary_admin_id, is_admin
from app.common.telemetry import elapsed_ms, log_telemetry, monotonic_ms
from app.db.database import get_session
from app.db.models import User

logger = logging.getLogger(__name__)
settings = get_settings()

FLOW_KEY = "flow"
FLOW_DATA_KEY = "flow_data"
FLOW_ADMIN_ADD_PRODUCT = "admin_add_product"
FLOW_ADMIN_ADD_STOCK = "admin_add_stock"
FLOW_ADMIN_BROADCAST = "admin_broadcast"
FLOW_CUSTOMER_MANUAL_QTY = "customer_manual_qty"
FLOW_CUSTOMER_COMPLAINT_COMPOSE = "customer_complaint_compose"
FLOW_CUSTOMER_REFUND_DETAIL = "customer_refund_detail"
FLOW_ADMIN_REFUND_PROOF = "admin_refund_proof"
FLOW_GH_ADD_READY = "gh_add_ready"
FLOW_GH_ADD_AWAIT = "gh_add_await"
FLOW_GH_ADD_USED = "gh_add_used"
FLOW_GH_SAVE_ADD = "gh_save_add"
FLOW_GH_SET_PRICE = "gh_set_price"
FLOW_GH_SET_USED_PRICE = "gh_set_used_price"
FLOW_GH_SET_AWAITING_HOURS = "gh_set_awaiting_hours"
FLOW_PAY_SET_QRIS_PAYLOAD = "pay_set_qris_payload"
AWAIT_QRIS_IMAGE_KEY = "await_qris_image"
CUSTOMER_ORDERS_PAGE_SIZE = 10
ADMIN_LIST_PAGE_SIZE = 10
CHECKOUT_LOCK_UNTIL_KEY = "checkout_lock_until"
CHECKOUT_LOCK_SECONDS = 8.0
CHECKOUT_ACTION_CACHE_KEY = "checkout_action_cache"
CHECKOUT_ACTION_TTL_SECONDS = 20.0
USER_CTX_CACHE_KEY = "user_ctx_cache"
USER_CTX_CACHE_TTL_SECONDS = 120.0
MENU_STATS_CACHE_KEY = "menu_stats_cache"
MENU_STATS_CACHE_TTL_SECONDS = 45.0
COMPLAINT_DRAFT_KEY = "complaint_draft"

ADMIN_PRODUCT_PICKER_TITLES: dict[str, str] = {
    "stk": "📥 Pilih produk untuk ditambah stok.",
    "sup": "⏸️ Pilih produk yang akan disuspend.",
    "uns": "▶️ Pilih produk yang akan diaktifkan.",
    "del": "🗑️ Pilih produk yang akan dihapus.",
}

T = TypeVar("T")


@dataclass(frozen=True)
class UserContext:
    id: int
    telegram_id: int


def _role_for_telegram_id(telegram_id: int) -> str:
    return "admin" if is_admin(telegram_id, settings.role_file_path) else "customer"


def _format_rupiah(amount: int) -> str:
    return f"Rp{amount:,}".replace(",", ".")


def _back_keyboard(target: str = "main") -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        [[InlineKeyboardButton("⬅️ Kembali", callback_data=f"back:{target}")]]
    )


def _main_menu_keyboard(role: str) -> InlineKeyboardMarkup:
    if role == "admin":
        return InlineKeyboardMarkup(
            [
                [InlineKeyboardButton("📦 Katalog Admin", callback_data="adm:cat")],
                [InlineKeyboardButton("🧰 Kelola Komplain", callback_data="adm:cmp")],
                [InlineKeyboardButton("📢 Broadcast", callback_data="adm:bc")],
                [InlineKeyboardButton("💳 Konfigurasi Payment", callback_data="adm:pay")],
                [InlineKeyboardButton("📊 Laporan Operasional", callback_data="adm:ops")],
                [InlineKeyboardButton("🔄 Update Bot Tele", callback_data="adm:upd")],
                [InlineKeyboardButton("ℹ️ Bantuan", callback_data="adm:help")],
            ]
        )

    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("🛍️ Katalog", callback_data="cus:cat")],
            [InlineKeyboardButton("📦 Pesanan Saya", callback_data="cus:ord")],
            [InlineKeyboardButton("🆘 Komplain", callback_data="cus:cmp")],
            [InlineKeyboardButton("ℹ️ Bantuan", callback_data="cus:help")],
        ]
    )


def _admin_catalog_menu_keyboard() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("📋 Lihat Katalog", callback_data="ac:view")],
            [InlineKeyboardButton("🎓 GitHub Student Developer Pack", callback_data="ac:ghpack")],
            [InlineKeyboardButton("➕ Upsert Produk", callback_data="ac:add")],
            [InlineKeyboardButton("📥 Tambah Stok", callback_data="ac:stock")],
            [InlineKeyboardButton("⏸️ Suspend Produk", callback_data="ac:susp")],
            [InlineKeyboardButton("▶️ Unsuspend Produk", callback_data="ac:uns")],
            [InlineKeyboardButton("🗑️ Hapus Produk", callback_data="ac:del")],
            [InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")],
        ]
    )


def _payment_menu_keyboard() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("🖼️ Upload QRIS", callback_data="pay:upload")],
            [InlineKeyboardButton("🧾 Set Payload QRIS", callback_data="pay:payload:set")],
            [InlineKeyboardButton("ℹ️ Status QRIS", callback_data="pay:status")],
            [InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")],
        ]
    )


def _update_menu_keyboard() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("🔍 Cek Update", callback_data="up:check")],
            [InlineKeyboardButton("⬆️ Terapkan Update", callback_data="up:apply")],
            [InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")],
        ]
    )


def _github_pack_menu_keyboard() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("💰 Atur Harga", callback_data="gh:price:set")],
            [InlineKeyboardButton("♻️ Atur Harga GHS Bekas", callback_data="gh:price:used:set")],
            [InlineKeyboardButton("⏱️ Atur Jam Awaiting", callback_data="gh:await:set")],
            [InlineKeyboardButton("📥 Tambah Stok Ready", callback_data="gh:add:ready")],
            [InlineKeyboardButton("⏳ Tambah Stok Awaiting Benefits", callback_data="gh:add:await")],
            [InlineKeyboardButton("🗂️ Simpan Akun", callback_data="gh:save:menu")],
            [InlineKeyboardButton("♻️ Tambah Stok GHS Bekas", callback_data="gh:add:used")],
            [InlineKeyboardButton("📋 Lihat List Akun", callback_data="gh:list")],
            [InlineKeyboardButton("🧾 Akun Terjual", callback_data="gh:sold:list")],
            [InlineKeyboardButton("👁️ Lihat Detail Akun", callback_data="gh:view:list")],
            [InlineKeyboardButton("🗑️ Hapus Akun", callback_data="gh:del:list")],
            [InlineKeyboardButton("⬅️ Kembali", callback_data="back:adm_cat")],
        ]
    )


def _github_saved_account_menu_keyboard() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("➕ Tambah Akun", callback_data="gh:save:add")],
            [InlineKeyboardButton("📋 Lihat List Akun", callback_data="gh:save:list")],
            [InlineKeyboardButton("⏳ Pindahkan ke Awaiting Benefits", callback_data="gh:save:move")],
            [InlineKeyboardButton("⬅️ Kembali ke GitHub Pack", callback_data="ac:ghpack")],
        ]
    )


def _ops_metrics_keyboard() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("♻️ Refresh", callback_data="adm:ops")],
            [InlineKeyboardButton("🧯 Retry Queue", callback_data="adm:ops:retry")],
            [InlineKeyboardButton("🔄 Reset Metrik", callback_data="adm:ops:reset")],
            [InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")],
        ]
    )


def _admin_complaint_menu_keyboard() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("📥 Kelola Komplain", callback_data="cmp:admin:new:list")],
            [InlineKeyboardButton("🔎 Komplain Proses", callback_data="cmp:admin:proc:list")],
            [InlineKeyboardButton("✅ Komplain Selesai", callback_data="cmp:admin:done:list")],
            [InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")],
        ]
    )


def _is_back_text(text: str) -> bool:
    normalized = text.strip().lower()
    return normalized in {"kembali", "back", "menu", "/start"}


def _parse_product_upsert_input(text: str) -> tuple[str, int, str] | None:
    parts = [x.strip() for x in text.split("|")]
    if len(parts) < 2:
        return None

    name = parts[0]
    if not name:
        return None

    try:
        price = int(parts[1])
    except ValueError:
        return None

    if price <= 0:
        return None

    description = parts[2] if len(parts) >= 3 else ""
    return name, price, description


def _set_flow(context: ContextTypes.DEFAULT_TYPE, flow: str, **flow_data: int | str) -> None:
    context.user_data[FLOW_KEY] = flow
    context.user_data[FLOW_DATA_KEY] = flow_data


def _clear_flow(context: ContextTypes.DEFAULT_TYPE) -> None:
    context.user_data.pop(FLOW_KEY, None)
    context.user_data.pop(FLOW_DATA_KEY, None)


def _get_flow(context: ContextTypes.DEFAULT_TYPE) -> tuple[str | None, dict[str, int | str]]:
    flow = context.user_data.get(FLOW_KEY)
    data = context.user_data.get(FLOW_DATA_KEY) or {}
    return flow, data


async def _respond(
    update: Update,
    text: str,
    keyboard: InlineKeyboardMarkup | None = None,
    parse_mode: str | None = None,
) -> None:
    query = update.callback_query
    if query is not None:
        try:
            await query.edit_message_text(text=text, reply_markup=keyboard, parse_mode=parse_mode)
            return
        except BadRequest as exc:
            if "message is not modified" in str(exc).lower():
                return
            if query.message is not None:
                await query.message.reply_text(text=text, reply_markup=keyboard, parse_mode=parse_mode)
                return

    if update.message is not None:
        await update.message.reply_text(text=text, reply_markup=keyboard, parse_mode=parse_mode)


def _now_display_time() -> datetime:
    try:
        return datetime.now(ZoneInfo(settings.display_timezone))
    except ZoneInfoNotFoundError:
        return datetime.now()


def _format_display_time(value: datetime) -> str:
    try:
        tz = ZoneInfo(settings.display_timezone)
    except ZoneInfoNotFoundError:
        tz = None

    display_value = value
    if tz is not None:
        if display_value.tzinfo is None:
            display_value = display_value.replace(tzinfo=timezone.utc)
        display_value = display_value.astimezone(tz)

    return display_value.strftime("%d %b %Y %H:%M")


def _format_display_day_time(value: datetime) -> str:
    try:
        tz = ZoneInfo(settings.display_timezone)
    except ZoneInfoNotFoundError:
        tz = None

    display_value = value
    if tz is not None:
        if display_value.tzinfo is None:
            display_value = display_value.replace(tzinfo=timezone.utc)
        display_value = display_value.astimezone(tz)

    day_map = {
        0: "Senin",
        1: "Selasa",
        2: "Rabu",
        3: "Kamis",
        4: "Jumat",
        5: "Sabtu",
        6: "Minggu",
    }
    day_name = day_map.get(display_value.weekday(), "-")
    return f"{day_name}, {display_value.strftime('%d %b %Y %H:%M')}"


async def _try_delete_callback_message(update: Update) -> None:
    query = update.callback_query
    if query is None or query.message is None:
        return

    try:
        await query.message.delete()
    except BadRequest:
        logger.debug("Pesan callback tidak bisa dihapus.")
    except Exception as exc:
        logger.warning("Gagal menghapus pesan callback: %s", exc)


async def _try_delete_message(message: Message | None) -> None:
    if message is None:
        return

    try:
        await message.delete()
    except BadRequest:
        logger.debug("Pesan loading tidak bisa dihapus.")
    except Exception as exc:
        logger.warning("Gagal menghapus pesan loading: %s", exc)


async def _upsert_customer_checkout_message(
    *,
    context: ContextTypes.DEFAULT_TYPE,
    chat_id: int,
    message_id: int,
    text: str,
    keyboard: InlineKeyboardMarkup | None,
) -> bool:
    try:
        await context.bot.edit_message_text(
            chat_id=chat_id,
            message_id=message_id,
            text=text,
            parse_mode=ParseMode.HTML,
            reply_markup=keyboard,
            disable_web_page_preview=True,
        )
        return True
    except BadRequest as exc:
        if "message is not modified" in str(exc).lower():
            return True
    except Exception:
        pass

    try:
        await context.bot.edit_message_caption(
            chat_id=chat_id,
            message_id=message_id,
            caption=text,
            parse_mode=ParseMode.HTML,
            reply_markup=keyboard,
        )
        return True
    except BadRequest as exc:
        if "message is not modified" in str(exc).lower():
            return True
        logger.warning("Gagal upsert caption checkout message: %s", exc)
    except Exception as exc:
        logger.warning("Gagal upsert checkout message: %s", exc)

    return False


def _acquire_checkout_lock(context: ContextTypes.DEFAULT_TYPE) -> bool:
    now = time.monotonic()
    lock_until = float(context.user_data.get(CHECKOUT_LOCK_UNTIL_KEY, 0.0))
    if lock_until > now:
        return False
    context.user_data[CHECKOUT_LOCK_UNTIL_KEY] = now + CHECKOUT_LOCK_SECONDS
    return True


def _release_checkout_lock(context: ContextTypes.DEFAULT_TYPE) -> None:
    context.user_data.pop(CHECKOUT_LOCK_UNTIL_KEY, None)


def _is_duplicate_checkout_action(context: ContextTypes.DEFAULT_TYPE, signature: str) -> bool:
    now = time.monotonic()
    raw_cache = context.user_data.get(CHECKOUT_ACTION_CACHE_KEY)
    cache: dict[str, float] = raw_cache if isinstance(raw_cache, dict) else {}

    # Purge expired keys to keep user_data small.
    for key in list(cache.keys()):
        if cache[key] <= now:
            cache.pop(key, None)

    if signature in cache and cache[signature] > now:
        context.user_data[CHECKOUT_ACTION_CACHE_KEY] = cache
        return True

    cache[signature] = now + CHECKOUT_ACTION_TTL_SECONDS
    context.user_data[CHECKOUT_ACTION_CACHE_KEY] = cache
    return False


async def _send_checkout_loading(update: Update) -> Message | None:
    query = update.callback_query
    if query is None or query.message is None:
        return None

    loading_text = (
        "⏳ <b>Checkout sedang diproses...</b>\n"
        "Mohon tunggu sebentar dan jangan tekan tombol berulang."
    )

    try:
        await query.edit_message_text(
            text=loading_text,
            parse_mode=ParseMode.HTML,
        )
        return None
    except BadRequest as exc:
        if "message is not modified" not in str(exc).lower():
            logger.debug("Gagal edit pesan produk ke loading: %s", exc)
    except Exception as exc:
        logger.warning("Gagal edit pesan produk ke loading: %s", exc)

    try:
        return await query.message.reply_text(
            "⏳ Checkout sedang diproses, mohon tunggu sebentar..."
        )
    except Exception as exc:
        logger.warning("Gagal kirim fallback loading message: %s", exc)
    return None


def _broadcast_mode_label(attachment_type: BroadcastAttachmentType | None) -> str:
    if attachment_type == "photo":
        return "Foto"
    if attachment_type == "document":
        return "File"
    return "Teks"


def _build_broadcast_progress_text(
    *,
    processed: int,
    total: int,
    sent: int,
    failed: int,
    attachment_type: BroadcastAttachmentType | None,
) -> str:
    return (
        "📡 <b>Broadcast sedang berjalan...</b>\n"
        f"Mode: <b>{_broadcast_mode_label(attachment_type)}</b>\n"
        f"Progress: <b>{processed}/{total}</b>\n"
        f"✅ Sent: <b>{sent}</b>\n"
        f"❌ Failed: <b>{failed}</b>"
    )


def _build_broadcast_done_text(
    *,
    total: int,
    sent: int,
    failed: int,
    attachment_type: BroadcastAttachmentType | None,
) -> str:
    return (
        "✅ <b>Broadcast selesai</b>\n"
        f"Mode: <b>{_broadcast_mode_label(attachment_type)}</b>\n"
        f"Total customer: <b>{total}</b>\n"
        f"✅ Sent: <b>{sent}</b>\n"
        f"❌ Failed: <b>{failed}</b>"
    )


async def _send_broadcast_progress_message(update: Update) -> Message | None:
    text = _build_broadcast_progress_text(
        processed=0,
        total=0,
        sent=0,
        failed=0,
        attachment_type=None,
    )

    query = update.callback_query
    if query is not None and query.message is not None:
        try:
            return await query.message.reply_text(text=text, parse_mode=ParseMode.HTML)
        except Exception as exc:
            logger.warning("Gagal kirim pesan progres broadcast (callback): %s", exc)

    if update.message is not None:
        try:
            return await update.message.reply_text(text=text, parse_mode=ParseMode.HTML)
        except Exception as exc:
            logger.warning("Gagal kirim pesan progres broadcast (message): %s", exc)

    return None


async def _edit_broadcast_progress_message(
    *,
    context: ContextTypes.DEFAULT_TYPE,
    message: Message | None,
    text: str,
) -> None:
    if message is None:
        return

    try:
        await context.bot.edit_message_text(
            chat_id=message.chat_id,
            message_id=message.message_id,
            text=text,
            parse_mode=ParseMode.HTML,
        )
    except BadRequest as exc:
        if "message is not modified" not in str(exc).lower():
            logger.debug("Gagal update pesan progres broadcast: %s", exc)
    except Exception as exc:
        logger.warning("Gagal update pesan progres broadcast: %s", exc)


async def _run_admin_broadcast_with_progress(
    *,
    update: Update,
    context: ContextTypes.DEFAULT_TYPE,
    admin_user_id: int,
    message_text: str,
    attachment_type: BroadcastAttachmentType | None = None,
    attachment_file_id: str | None = None,
) -> tuple[int, int, int]:
    progress_message = await _send_broadcast_progress_message(update)
    total = 0
    last_snapshot: tuple[int, int, int, int] | None = None

    async def _on_progress(processed: int, current_total: int, sent: int, failed: int) -> None:
        nonlocal total, last_snapshot
        total = current_total
        snapshot = (processed, current_total, sent, failed)
        if snapshot == last_snapshot:
            return
        last_snapshot = snapshot

        await _edit_broadcast_progress_message(
            context=context,
            message=progress_message,
            text=_build_broadcast_progress_text(
                processed=processed,
                total=current_total,
                sent=sent,
                failed=failed,
                attachment_type=attachment_type,
            ),
        )

    with get_session() as session:
        sent, failed = await broadcast_to_customers(
            session=session,
            bot=context.bot,
            admin_user_id=admin_user_id,
            message=message_text,
            attachment_type=attachment_type,
            attachment_file_id=attachment_file_id,
            progress_callback=_on_progress,
        )

    final_total = total if total > 0 else (sent + failed)
    await _edit_broadcast_progress_message(
        context=context,
        message=progress_message,
        text=_build_broadcast_done_text(
            total=final_total,
            sent=sent,
            failed=failed,
            attachment_type=attachment_type,
        ),
    )
    return sent, failed, final_total


def _track_background_task(task: asyncio.Task[object], label: str) -> None:
    def _on_done(done_task: asyncio.Task[object]) -> None:
        try:
            done_task.result()
        except Exception as exc:
            logger.warning("Background task '%s' gagal: %s", label, exc)

    task.add_done_callback(_on_done)


async def _send_main_menu(
    update: Update,
    role: str,
    welcome: bool = False,
    username: str | None = None,
    total_transaksi: int | None = None,
    context: ContextTypes.DEFAULT_TYPE | None = None,
) -> None:
    tg_user = update.effective_user
    display_name = username
    if not display_name:
        if tg_user is not None:
            display_name = tg_user.username or tg_user.full_name
    if not display_name:
        display_name = "user"

    if total_transaksi is None and context is not None and tg_user is not None:
        cached_stats = context.user_data.get(MENU_STATS_CACHE_KEY)
        now_mono = time.monotonic()
        if isinstance(cached_stats, dict):
            cached_until = float(cached_stats.get("until", 0.0) or 0.0)
            cached_tg = int(cached_stats.get("telegram_id", 0) or 0)
            cached_total = int(cached_stats.get("total_transaksi", 0) or 0)
            if cached_until > now_mono and cached_tg == int(tg_user.id):
                total_transaksi = cached_total

    if total_transaksi is None:
        if tg_user is not None:
            with get_session() as session:
                user = get_user_by_telegram_id(session, tg_user.id)
                if user is not None:
                    total_transaksi = count_delivered_orders_by_customer(session, user.id)

    if total_transaksi is not None and context is not None and tg_user is not None:
        context.user_data[MENU_STATS_CACHE_KEY] = {
            "telegram_id": int(tg_user.id),
            "total_transaksi": int(total_transaksi),
            "until": time.monotonic() + MENU_STATS_CACHE_TTL_SECONDS,
        }

    if total_transaksi is None:
        total_transaksi = 0

    now_text = _format_display_time(_now_display_time())
    total_text = str(total_transaksi)

    text = (
        f"✨ <b>Halo, {html.escape(display_name)}!</b>\n"
        f"🕒 {now_text}\n"
        f"📊 Total transaksi sukses: <b>{total_text}</b>\n\n"
        "<i>created with love by:\n"
        "dzulfikrialifajri_store</i>"
    )

    if welcome:
        text += "\n\nSilakan pilih menu di bawah ya 👇"

    await _respond(update, text, _main_menu_keyboard(role), parse_mode=ParseMode.HTML)


async def _send_help(update: Update, role: str) -> None:
    common = [
        "🧭 Semua interaksi utama berbasis tombol.",
        "↩️ Setiap layar punya tombol kembali.",
        "⌨️ Ketik <code>/start</code> kapan saja untuk kembali ke menu utama.",
    ]
    if role == "admin":
        common.append("🔐 Admin: kelola produk dari menu <b>📦 Katalog Admin</b>.")
        common.append("📊 Cek metrik operasional: <code>/ops_metrics</code>.")
    else:
        common.append("🛍️ Customer: pilih produk dari katalog lalu checkout lewat tombol.")
        common.append("📌 Cek status cepat: <code>/order_status ORD20260101000123</code>.")
        common.append("🔁 Reorder cepat: <code>/reorder ORDER_REF</code>.")

    await _respond(update, "\n".join(common), _back_keyboard("main"), parse_mode=ParseMode.HTML)


async def _send_admin_catalog_menu(update: Update) -> None:
    with get_session() as session:
        ensure_github_pack_product(session)
        ensure_github_pack_used_product(session)

    await _respond(
        update,
        (
            "📦 <b>Menu Katalog Admin</b>\n"
            "Kelola katalog, stok, dan produk khusus dari panel ini.\n\n"
            "👇 <i>Pilih aksi admin lewat tombol di bawah.</i>"
        ),
        _admin_catalog_menu_keyboard(),
        parse_mode=ParseMode.HTML,
    )


def _stock_status_badge(status: str) -> str:
    if status == "awaiting_benefits":
        return "⏳ Awaiting"
    return "✅ Ready"


def _order_status_badge(status: str) -> str:
    mapping = {
        "pending_payment": "🟡 Menunggu Pembayaran",
        "delivered": "✅ Berhasil",
        "cancelled": "❌ Dibatalkan",
        "expired": "⌛ Kedaluwarsa",
    }
    return mapping.get(status, status)


def _format_remaining_text(expires_at: datetime | None) -> str:
    if expires_at is None:
        return "-"

    remaining_seconds = int((expires_at - datetime.utcnow()).total_seconds())
    if remaining_seconds <= 0:
        return "0 menit"

    remaining_minutes = max(1, (remaining_seconds + 59) // 60)
    return f"{remaining_minutes} menit"


def _format_remaining_compact(target_at: datetime | None) -> str:
    if target_at is None:
        return "-"

    target = target_at
    if target.tzinfo is not None:
        target = target.astimezone(timezone.utc).replace(tzinfo=None)

    remaining_seconds = int((target - datetime.utcnow()).total_seconds())
    if remaining_seconds <= 0:
        return "0m"

    days, rem = divmod(remaining_seconds, 86400)
    hours, rem = divmod(rem, 3600)
    minutes = max(1, rem // 60)

    parts: list[str] = []
    if days > 0:
        parts.append(f"{days}d")
    if hours > 0 or days > 0:
        parts.append(f"{hours}h")
    parts.append(f"{minutes}m")
    return " ".join(parts)


def _customer_footer_text() -> str:
    return "👇 <i>Pilih aksi lewat tombol di bawah.</i>"


def _admin_footer_text() -> str:
    return "👇 <i>Pilih aksi admin lewat tombol di bawah.</i>"


def _truncate_text(text: str, limit: int = 180) -> str:
    normalized = text.strip()
    if len(normalized) <= limit:
        return normalized
    return f"{normalized[: limit - 3]}..."


def _mask_qris_payload(payload: str) -> str:
    if not payload:
        return "-"
    if len(payload) <= 28:
        return html.escape(payload)
    return html.escape(f"{payload[:14]}...{payload[-10:]}")


def _build_payment_status_text(
    *,
    dynamic_enabled: bool,
    has_qris_image: bool,
    payload: str,
    dynamic_ready: bool,
    dynamic_error: str,
) -> str:
    image_status = "✅ Tersedia" if has_qris_image else "⚠️ Belum ada"
    payload_status = "✅ Tersedia" if payload else "⚠️ Belum di-set"
    if dynamic_enabled:
        if payload and dynamic_ready:
            dynamic_status = "✅ Aktif (dinamis per nominal)"
        elif payload:
            dynamic_status = "⚠️ Aktif, payload tidak valid"
        else:
            dynamic_status = "⚠️ Aktif, payload belum tersedia"
    else:
        dynamic_status = "⏸️ Nonaktif (pakai QR statis)"

    lines = [
        "💳 <b>Status Payment</b>",
        f"Mode QR dinamis: <b>{dynamic_status}</b>",
        f"Payload QRIS statis: <b>{payload_status}</b>",
        f"File QRIS fallback: <b>{image_status}</b>",
    ]

    if payload:
        lines.append(f"Payload preview: <code>{_mask_qris_payload(payload)}</code>")
    if dynamic_error:
        lines.append(f"Catatan validasi: <i>{html.escape(_truncate_text(dynamic_error))}</i>")

    lines.extend(["", _admin_footer_text()])
    return "\n".join(lines)


def _admin_access_denied_text() -> str:
    return "🚫 <b>Akses Ditolak</b>\nMenu ini khusus admin."


def _format_retry_snapshot_text(snapshot: object) -> str:
    # snapshot is RetryQueueSnapshot from notification_retry_service.
    pending_count = int(getattr(snapshot, "pending_count", 0))
    failed_count = int(getattr(snapshot, "failed_count", 0))
    sent_last_24h = int(getattr(snapshot, "sent_last_24h", 0))
    top_failed_channels = list(getattr(snapshot, "top_failed_channels", []) or [])

    lines = [
        "🧯 <b>Snapshot Retry Queue</b>",
        f"Pending: <b>{pending_count}</b>",
        f"Failed permanen: <b>{failed_count}</b>",
        f"Sent (24 jam): <b>{sent_last_24h}</b>",
        "",
        "Channel gagal terbanyak:",
    ]

    if top_failed_channels:
        for idx, (channel, total) in enumerate(top_failed_channels, start=1):
            lines.append(f"{idx}. <b>{html.escape(str(channel))}</b> - {int(total)}")
    else:
        lines.append("- Tidak ada channel gagal.")

    lines.extend(["", _admin_footer_text()])
    return "\n".join(lines)


async def _respond_admin_only(update: Update, target: str = "main") -> None:
    await _respond(update, _admin_access_denied_text(), _back_keyboard(target), parse_mode=ParseMode.HTML)


def _paginate_rows(rows: Sequence[T], page: int, page_size: int) -> tuple[list[T], int, int]:
    safe_page_size = max(1, page_size)
    total_items = len(rows)
    total_pages = max(1, (total_items + safe_page_size - 1) // safe_page_size)
    safe_page = min(max(1, page), total_pages)
    start = (safe_page - 1) * safe_page_size
    end = start + safe_page_size
    return list(rows[start:end]), safe_page, total_pages


def _pagination_nav_row(page: int, total_pages: int, callback_prefix: str) -> list[InlineKeyboardButton]:
    prev_page = max(1, page - 1)
    next_page = min(total_pages, page + 1)
    return [
        InlineKeyboardButton(
            "[sebelumnya]",
            callback_data=(f"{callback_prefix}:{prev_page}" if page > 1 else "noop"),
        ),
        InlineKeyboardButton(f"[{page}/{total_pages}]", callback_data="noop"),
        InlineKeyboardButton(
            "[berikutnya]",
            callback_data=(f"{callback_prefix}:{next_page}" if page < total_pages else "noop"),
        ),
    ]


def _customer_orders_keyboard(
    *,
    page: int,
    total_pages: int,
    rows: list[tuple[int, str, str]],
) -> InlineKeyboardMarkup:
    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for order_no, order_ref, status in rows:
        label = f"{order_no}. {order_ref} | {_order_status_badge(status)}"
        keyboard_rows.append(
            [InlineKeyboardButton(label, callback_data=f"ord:view:{order_ref}:{page}")]
        )

    keyboard_rows.append(_pagination_nav_row(page, total_pages, "ord:page"))
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")])
    return InlineKeyboardMarkup(keyboard_rows)


def _customer_order_detail_keyboard(
    order_ref: str,
    page: int,
    include_copy: bool,
    include_reorder: bool,
) -> InlineKeyboardMarkup:
    rows: list[list[InlineKeyboardButton]] = []
    if include_reorder:
        rows.append([InlineKeyboardButton("🔁 Pesan Lagi", callback_data=f"ord:reorder:{order_ref}:{page}")])
    if include_copy:
        rows.append([InlineKeyboardButton("📋 Copy Akun", callback_data=f"ord:copy:{order_ref}:{page}")])
    rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data=f"ord:page:{max(1, page)}")])
    return InlineKeyboardMarkup(rows)


def _complaint_status_badge(status: str) -> str:
    mapping = {
        COMPLAINT_STATUS_NEW: "🆕 Baru",
        COMPLAINT_STATUS_IN_PROCESS: "🔎 Proses Investigasi",
        COMPLAINT_STATUS_AWAITING_CUSTOMER_REFUND_DETAILS: "⌛ Menunggu Detail Refund Customer",
        COMPLAINT_STATUS_AWAITING_ADMIN_REFUND_TRANSFER: "💸 Menunggu Transfer Refund Admin",
        COMPLAINT_STATUS_REFUND_COMPLETED: "✅ Refund Selesai",
        COMPLAINT_STATUS_REPLACEMENT_SENT: "✅ Akun Pengganti Dikirim",
        COMPLAINT_STATUS_REJECTED: "❌ Ditolak",
    }
    return mapping.get(status, status)


def _complaint_bucket_title(bucket: str) -> str:
    if bucket == "new":
        return "📥 <b>Kelola Komplain</b>"
    if bucket == "proc":
        return "🔎 <b>Komplain Proses</b>"
    return "✅ <b>Komplain Selesai</b>"


def _complaint_statuses_for_bucket(bucket: str) -> set[str]:
    if bucket == "new":
        return set(COMPLAINT_STATUS_GROUP_NEW)
    if bucket == "proc":
        return set(COMPLAINT_STATUS_GROUP_PROCESS)
    return set(COMPLAINT_STATUS_GROUP_DONE)


def _complaint_back_list_callback(bucket: str, page: int) -> str:
    safe_page = max(1, int(page))
    return f"cmp:admin:{bucket}:list:{safe_page}"


def _build_complaint_compose_keyboard(order_ref: str, source_page: int) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("✅ Kirim Komplain", callback_data="cmp:new:submit")],
            [InlineKeyboardButton("📦 Ganti Nomor Order", callback_data=f"cmp:new:orders:{max(1, source_page)}")],
            [InlineKeyboardButton("❌ Batal", callback_data="cmp:new:cancel")],
        ]
    )


def _clear_complaint_draft(context: ContextTypes.DEFAULT_TYPE) -> None:
    context.user_data.pop(COMPLAINT_DRAFT_KEY, None)


def _get_complaint_draft(context: ContextTypes.DEFAULT_TYPE) -> dict[str, object]:
    draft = context.user_data.get(COMPLAINT_DRAFT_KEY)
    if not isinstance(draft, dict):
        draft = {}
    if not isinstance(draft.get("photo_file_ids"), list):
        draft["photo_file_ids"] = []
    if not isinstance(draft.get("complaint_text"), str):
        draft["complaint_text"] = ""
    context.user_data[COMPLAINT_DRAFT_KEY] = draft
    return draft


def _append_complaint_text(context: ContextTypes.DEFAULT_TYPE, text: str) -> None:
    draft = _get_complaint_draft(context)
    existing = str(draft.get("complaint_text") or "").strip()
    incoming = text.strip()
    if not incoming:
        return
    if existing:
        draft["complaint_text"] = f"{existing}\n{incoming}"
    else:
        draft["complaint_text"] = incoming
    context.user_data[COMPLAINT_DRAFT_KEY] = draft


def _append_complaint_photo(context: ContextTypes.DEFAULT_TYPE, file_id: str) -> None:
    normalized = file_id.strip()
    if not normalized:
        return
    draft = _get_complaint_draft(context)
    photos = [str(x).strip() for x in list(draft.get("photo_file_ids") or []) if str(x).strip()]
    photos.append(normalized)
    draft["photo_file_ids"] = photos
    context.user_data[COMPLAINT_DRAFT_KEY] = draft


def _build_complaint_detail_text(detail: ComplaintDetail, *, include_customer: bool = True) -> str:
    order_date_text = "-"
    if detail.order_created_at is not None:
        order_date_text = html.escape(_format_display_day_time(detail.order_created_at))

    lines = [
        "🧾 <b>Detail Komplain</b>",
        f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>",
        f"Nomor order: <code>{html.escape(detail.order_ref)}</code>",
        f"Tanggal pesanan: <b>{order_date_text}</b>",
        f"Tanggal komplain: <b>{html.escape(_format_display_day_time(detail.complaint_at))}</b>",
    ]
    if include_customer:
        lines.append(f"Pelanggan: <b>{html.escape(detail.customer_display)}</b> ({detail.customer_telegram_id})")
    lines.append(f"Status: <b>{html.escape(_complaint_status_badge(detail.status))}</b>")
    lines.append("")
    lines.append("Isi pesan komplain:")
    lines.append(f"<pre>{html.escape(detail.complaint_text)}</pre>")

    if detail.refund_target_detail:
        lines.append("")
        lines.append("Detail rekening/e-wallet refund:")
        lines.append(f"<pre>{html.escape(detail.refund_target_detail)}</pre>")

    if detail.refund_note:
        lines.append("")
        lines.append(f"Catatan refund admin: <i>{html.escape(detail.refund_note)}</i>")

    lines.append("")
    lines.append(_admin_footer_text())
    return "\n".join(lines)


async def _send_admin_complaint_menu(update: Update) -> None:
    await _respond(
        update,
        (
            "🧰 <b>Kelola Komplain</b>\n"
            "Pilih status komplain untuk menindaklanjuti laporan pelanggan.\n\n"
            f"{_admin_footer_text()}"
        ),
        _admin_complaint_menu_keyboard(),
        parse_mode=ParseMode.HTML,
    )


async def _send_customer_complaint_order_picker(update: Update, customer_id: int, page: int = 1) -> None:
    with get_session() as session:
        orders = list_customer_order_options_for_complaint(session, customer_id=customer_id)

    if not orders:
        await _respond(
            update,
            (
                "📭 <b>Belum Ada Pesanan</b>\n"
                "Kamu belum punya order untuk diajukan komplain.\n\n"
                f"{_customer_footer_text()}"
            ),
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    paged_rows, safe_page, total_pages = _paginate_rows(orders, page, CUSTOMER_ORDERS_PAGE_SIZE)
    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for row in paged_rows:
        label = f"{row.order_ref} | {html.escape(_format_display_time(row.created_at))}"
        keyboard_rows.append([InlineKeyboardButton(label, callback_data=f"cmp:new:ord:{row.order_ref}:{safe_page}")])

    if total_pages > 1:
        keyboard_rows.append(_pagination_nav_row(safe_page, total_pages, "cmp:new:orders"))
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")])

    await _respond(
        update,
        (
            "🆘 <b>Buat Komplain</b>\n"
            f"Halaman <b>{safe_page}/{total_pages}</b> • Total order: <b>{len(orders)}</b>\n\n"
            "Pilih nomor order yang bermasalah.\n\n"
            f"{_customer_footer_text()}"
        ),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_admin_complaint_list(update: Update, *, bucket: str, page: int = 1) -> None:
    statuses = _complaint_statuses_for_bucket(bucket)
    with get_session() as session:
        rows = list_complaints_by_statuses(session, statuses=statuses)

    if not rows:
        await _respond(
            update,
            (
                f"{_complaint_bucket_title(bucket)}\n"
                "Belum ada data komplain pada kategori ini.\n\n"
                f"{_admin_footer_text()}"
            ),
            _admin_complaint_menu_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    paged_rows, safe_page, total_pages = _paginate_rows(rows, page, ADMIN_LIST_PAGE_SIZE)
    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for row in paged_rows:
        button_text = f"{row.customer_display} | {html.escape(_format_display_time(row.complaint_at))}"
        keyboard_rows.append(
            [
                InlineKeyboardButton(
                    button_text,
                    callback_data=f"cmp:admin:view:{bucket}:{row.complaint_id}:{safe_page}",
                )
            ]
        )

    if total_pages > 1:
        keyboard_rows.append(_pagination_nav_row(safe_page, total_pages, f"cmp:admin:{bucket}:list"))
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="adm:cmp")])

    await _respond(
        update,
        (
            f"{_complaint_bucket_title(bucket)}\n"
            f"Halaman <b>{safe_page}/{total_pages}</b> • Total komplain: <b>{len(rows)}</b>\n\n"
            "Pilih komplain untuk lihat detail dan aksi.\n\n"
            f"{_admin_footer_text()}"
        ),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_admin_complaint_detail(
    update: Update,
    *,
    bucket: str,
    complaint_id: int,
    page: int,
) -> None:
    with get_session() as session:
        detail = get_complaint_detail(session, complaint_id=complaint_id)

    if detail is None:
        await _respond(update, "⚠️ Komplain tidak ditemukan.", _admin_complaint_menu_keyboard())
        return

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    if bucket == "new" and detail.status == COMPLAINT_STATUS_NEW:
        keyboard_rows.append(
            [InlineKeyboardButton("▶️ Proses", callback_data=f"cmp:admin:new:process:{detail.complaint_id}:{max(1, page)}")]
        )
        keyboard_rows.append(
            [InlineKeyboardButton("❌ Tolak", callback_data=f"cmp:admin:new:reject:{detail.complaint_id}:{max(1, page)}")]
        )

    if bucket == "proc":
        if detail.status == COMPLAINT_STATUS_IN_PROCESS:
            keyboard_rows.append(
                [InlineKeyboardButton("💸 Setujui Refund", callback_data=f"cmp:admin:proc:refund:{detail.complaint_id}:{max(1, page)}")]
            )
            keyboard_rows.append(
                [InlineKeyboardButton("🔁 Kirim Akun Pengganti", callback_data=f"cmp:admin:proc:replace:{detail.complaint_id}:{max(1, page)}")]
            )
        elif detail.status == COMPLAINT_STATUS_AWAITING_ADMIN_REFUND_TRANSFER:
            keyboard_rows.append(
                [InlineKeyboardButton("💸 Kirim Dana Refund", callback_data=f"cmp:admin:proc:pay:{detail.complaint_id}:{max(1, page)}")]
            )
        elif detail.status == COMPLAINT_STATUS_AWAITING_CUSTOMER_REFUND_DETAILS:
            keyboard_rows.append([InlineKeyboardButton("⌛ Menunggu Detail Refund Customer", callback_data="noop")])

    if bucket == "done" and detail.status in COMPLAINT_STATUS_GROUP_DONE:
        keyboard_rows.append(
            [InlineKeyboardButton("♻️ Buka Kembali Komplain", callback_data=f"cmp:admin:done:reopen:{detail.complaint_id}:{max(1, page)}")]
        )

    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data=_complaint_back_list_callback(bucket, page))])
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali ke Kelola Komplain", callback_data="adm:cmp")])

    await _respond(
        update,
        _build_complaint_detail_text(detail, include_customer=True),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _submit_customer_complaint_from_draft(
    update: Update,
    context: ContextTypes.DEFAULT_TYPE,
    db_user_ctx: UserContext,
) -> bool:
    flow, flow_data = _get_flow(context)
    if flow != FLOW_CUSTOMER_COMPLAINT_COMPOSE:
        await _respond(update, "⚠️ Draft komplain tidak ditemukan. Ulangi dari menu komplain.", _back_keyboard("main"))
        return False

    draft = _get_complaint_draft(context)
    order_ref = str(flow_data.get("order_ref") or draft.get("order_ref") or "").strip().upper()
    source_page = int(flow_data.get("source_page") or draft.get("source_page") or 1)
    complaint_text = str(draft.get("complaint_text") or "").strip()
    photo_ids = [str(x).strip() for x in list(draft.get("photo_file_ids") or []) if str(x).strip()]

    if not order_ref:
        await _respond(update, "⚠️ Nomor order tidak ditemukan pada draft komplain.", _back_keyboard("main"))
        return False
    if not complaint_text:
        await _respond(
            update,
            "⚠️ Isi komplain belum ada. Silakan kirim penjelasan keluhan terlebih dahulu.",
            _build_complaint_compose_keyboard(order_ref, max(1, source_page)),
        )
        return False

    with get_session() as session:
        customer = session.get(User, db_user_ctx.id)
        if customer is None:
            await _respond(update, "⚠️ User tidak ditemukan. Silakan /start lalu ulangi.", _back_keyboard("main"))
            return False
        try:
            complaint_result = create_customer_complaint(
                session,
                customer=customer,
                order_ref=order_ref,
                complaint_text=complaint_text,
                attachment_file_ids=photo_ids,
            )
        except ValueError as exc:
            await _respond(update, f"❌ {exc}", _build_complaint_compose_keyboard(order_ref, max(1, source_page)))
            return False

    admin_id = get_primary_admin_id(settings.role_file_path)
    if admin_id is not None:
        order_date_text = "-"
        if complaint_result.order_created_at is not None:
            order_date_text = _format_display_day_time(complaint_result.order_created_at)
        admin_message = (
            "🆕 <b>Komplain Baru</b>\n"
            f"No. komplain: <b>{html.escape(complaint_result.complaint_ref)}</b>\n"
            f"Nomor order: <code>{html.escape(complaint_result.order_ref)}</code>\n"
            f"Tanggal pesanan: <b>{html.escape(order_date_text)}</b>\n"
            f"Tanggal komplain: <b>{html.escape(_format_display_day_time(complaint_result.complaint_at))}</b>\n"
            f"Isi pesan komplain:\n<pre>{html.escape(complaint_result.complaint_text)}</pre>"
        )
        try:
            await context.bot.send_message(
                chat_id=admin_id,
                text=admin_message,
                parse_mode=ParseMode.HTML,
                reply_markup=InlineKeyboardMarkup(
                    [[InlineKeyboardButton("🧰 Buka Kelola Komplain", callback_data="adm:cmp")]]
                ),
                disable_web_page_preview=True,
            )
            for idx, file_id in enumerate(photo_ids, start=1):
                caption_text = None
                if idx == 1:
                    caption_text = (
                        f"📎 Bukti komplain {html.escape(complaint_result.complaint_ref)} "
                        f"({len(photo_ids)} lampiran)"
                    )
                await context.bot.send_photo(
                    chat_id=admin_id,
                    photo=file_id,
                    caption=caption_text,
                    parse_mode=ParseMode.HTML,
                )
        except Exception as exc:
            logger.warning("Gagal kirim notifikasi komplain baru ke admin: %s", exc)

    _clear_flow(context)
    _clear_complaint_draft(context)
    await _respond(
        update,
        (
            "✅ <b>Komplain berhasil dikirim ke admin</b>\n"
            f"No. komplain: <b>{html.escape(complaint_result.complaint_ref)}</b>\n"
            f"Nomor order: <code>{html.escape(complaint_result.order_ref)}</code>\n"
            f"Lampiran: <b>{complaint_result.attachment_count}</b> file"
        ),
        _back_keyboard("main"),
        parse_mode=ParseMode.HTML,
    )
    return True


def _build_accounts_copy_text(order_ref: str, account_blocks: list[str]) -> str:
    parts = [f"Order: {order_ref}"]
    for idx, raw in enumerate(account_blocks, start=1):
        parts.append(f"[AKUN {idx}]\n{raw}")
    return "\n\n".join(parts)


def _split_message_chunks(text: str, max_len: int = 3500) -> list[str]:
    if len(text) <= max_len:
        return [text]

    chunks: list[str] = []
    current = ""
    for line in text.splitlines(keepends=True):
        if len(current) + len(line) > max_len and current:
            chunks.append(current.rstrip())
            current = line
        else:
            current += line

    if current:
        chunks.append(current.rstrip())
    return chunks


async def _send_github_pack_menu(update: Update) -> None:
    with get_session() as session:
        product = ensure_github_pack_product(session)
        used_product = ensure_github_pack_used_product(session)
        stocks = list_github_stocks(session)
        saved_stocks = list_saved_github_stocks(session)
        sold_stocks = list_sold_github_stocks(session)
        used_ready_count = get_available_stock_count(session, int(used_product.id))
        awaiting_hours = get_github_pack_awaiting_hours(session)

    ready_count = sum(1 for x in stocks if x.status == "ready")
    awaiting_count = sum(1 for x in stocks if x.status == "awaiting_benefits")
    saved_ready_count = sum(1 for x in saved_stocks if x.is_ready)
    saved_waiting_count = max(0, len(saved_stocks) - saved_ready_count)
    moved_count = sum(1 for x in sold_stocks if x.is_moved_to_used)
    await _respond(
        update,
        (
            f"🎓 <b>{html.escape(product.name)}</b>\n"
            f"💰 Harga utama: <b>{_format_rupiah(product.price)}</b>\n"
            f"✅ Ready: <b>{ready_count}</b> akun\n"
            f"⏳ Awaiting benefits: <b>{awaiting_count}</b> akun\n\n"
            f"🗂️ Simpan akun: <b>{len(saved_stocks)}</b> akun\n"
            f"🔔 Siap diajukan verifikasi: <b>{saved_ready_count}</b> akun\n"
            f"⏱️ Menunggu 80 jam: <b>{saved_waiting_count}</b> akun\n\n"
            f"🧾 Akun terjual: <b>{len(sold_stocks)}</b> akun\n"
            f"♻️ Harga GHS Bekas: <b>{_format_rupiah(used_product.price)}</b>\n"
            f"♻️ Sudah dipindah ke GHS Bekas: <b>{moved_count}</b> akun\n"
            f"🛍️ Stok GHS Bekas Ready: <b>{used_ready_count}</b> akun\n\n"
            f"⏱️ Durasi awaiting: <b>{awaiting_hours} jam</b>\n\n"
            f"{_admin_footer_text()}"
        ),
        _github_pack_menu_keyboard(),
        parse_mode=ParseMode.HTML,
    )


def _saved_account_status_label(*, is_ready: bool, is_notified: bool) -> str:
    if not is_ready:
        return "⏳ Menunggu 80 jam"
    if is_notified:
        return "✅ Siap diajukan (notifikasi terkirim)"
    return "🔔 Siap diajukan"


def _saved_account_button_label(username: str) -> str:
    label = username.strip() or "unknown"
    if len(label) > 28:
        return f"{label[:25]}..."
    return label


async def _send_github_saved_account_menu(update: Update) -> None:
    with get_session() as session:
        saved_stocks = list_saved_github_stocks(session)

    ready_count = sum(1 for stock in saved_stocks if stock.is_ready)
    waiting_count = max(0, len(saved_stocks) - ready_count)
    nearest_ready = min((stock.ready_at for stock in saved_stocks if not stock.is_ready), default=None)

    nearest_line = ""
    if nearest_ready is not None:
        nearest_line = (
            f"Ready terdekat: <b>{html.escape(_format_display_day_time(nearest_ready))}</b> "
            f"(sisa <b>{_format_remaining_compact(nearest_ready)}</b>)\n"
        )

    await _respond(
        update,
        (
            "🗂️ <b>Simpan Akun GitHub Fresh</b>\n"
            f"Mode simpan: <b>{GITHUB_PACK_SAVE_HOURS} jam</b> sebelum diajukan verifikasi.\n\n"
            f"Total akun simpan: <b>{len(saved_stocks)}</b> akun\n"
            f"Siap diajukan: <b>{ready_count}</b> akun\n"
            f"Masih menunggu: <b>{waiting_count}</b> akun\n"
            f"{nearest_line}\n"
            "Gunakan tombol di bawah untuk tambah akun, lihat list, atau pindahkan akun siap ke awaiting benefits.\n\n"
            f"{_admin_footer_text()}"
        ),
        _github_saved_account_menu_keyboard(),
        parse_mode=ParseMode.HTML,
    )


async def _send_github_saved_stock_list(update: Update, page: int = 1) -> None:
    with get_session() as session:
        saved_stocks = list_saved_github_stocks(session)

    if not saved_stocks:
        await _respond(
            update,
            (
                "📭 <b>Belum Ada Akun Tersimpan</b>\n"
                "Tambahkan akun fresh dari menu Simpan Akun.\n\n"
                f"{_admin_footer_text()}"
            ),
            _github_saved_account_menu_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    paged_rows, safe_page, total_pages = _paginate_rows(saved_stocks, page, ADMIN_LIST_PAGE_SIZE)
    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for stock in paged_rows:
        status_icon = "🔔" if stock.is_ready else "⏳"
        button_text = f"{status_icon} {_saved_account_button_label(stock.username)}"
        keyboard_rows.append(
            [InlineKeyboardButton(button_text, callback_data=f"gh:save:view:{stock.stock_id}:{safe_page}")]
        )

    if total_pages > 1:
        keyboard_rows.append(_pagination_nav_row(safe_page, total_pages, "gh:save:list"))
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="gh:save:menu")])

    await _respond(
        update,
        (
            "📋 <b>List Simpan Akun GitHub</b>\n"
            f"Halaman <b>{safe_page}/{total_pages}</b> • Total akun: <b>{len(saved_stocks)}</b>\n\n"
            "Klik username untuk lihat detail akun.\n\n"
            f"{_admin_footer_text()}"
        ),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_github_saved_move_picker(update: Update, page: int = 1) -> None:
    with get_session() as session:
        ready_stocks = list_ready_saved_github_stocks(session)

    if not ready_stocks:
        await _respond(
            update,
            (
                "📭 <b>Belum Ada Akun Siap Pindah</b>\n"
                "Akun akan muncul di sini setelah melewati masa simpan 80 jam.\n\n"
                f"{_admin_footer_text()}"
            ),
            _github_saved_account_menu_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    paged_rows, safe_page, total_pages = _paginate_rows(ready_stocks, page, ADMIN_LIST_PAGE_SIZE)

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for stock in paged_rows:
        button_text = f"⏳ {_saved_account_button_label(stock.username)}"
        keyboard_rows.append(
            [InlineKeyboardButton(button_text, callback_data=f"gh:save:move:pick:{stock.stock_id}:{safe_page}")]
        )

    if total_pages > 1:
        keyboard_rows.append(_pagination_nav_row(safe_page, total_pages, "gh:save:move:list"))
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="gh:save:menu")])

    await _respond(
        update,
        (
            "⏳ <b>Pilih Akun untuk Dipindahkan ke Awaiting Benefits</b>\n"
            f"Halaman <b>{safe_page}/{total_pages}</b> • Total akun siap: <b>{len(ready_stocks)}</b>\n\n"
            "List ini hanya menampilkan akun yang sudah tersimpan minimal 80 jam.\n\n"
            f"{_admin_footer_text()}"
        ),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_github_saved_stock_detail(
    update: Update,
    *,
    stock_id: int,
    source_mode: str,
    page: int = 1,
) -> None:
    with get_session() as session:
        stock = get_saved_github_stock_detail(session, stock_id)

    if stock is None:
        fallback_keyboard = _github_saved_account_menu_keyboard()
        if source_mode == "view":
            fallback_keyboard = InlineKeyboardMarkup(
                [[InlineKeyboardButton("⬅️ Kembali", callback_data=f"gh:save:list:{max(1, page)}")]]
            )
        if source_mode == "move":
            fallback_keyboard = InlineKeyboardMarkup(
                [[InlineKeyboardButton("⬅️ Kembali", callback_data=f"gh:save:move:list:{max(1, page)}")]]
            )
        await _respond(update, "⚠️ Akun simpan tidak ditemukan.", fallback_keyboard)
        return

    status_text = _saved_account_status_label(is_ready=stock.is_ready, is_notified=stock.is_notified)
    remaining_line = ""
    if not stock.is_ready:
        remaining_line = f"Sisa masa simpan: <b>{_format_remaining_compact(stock.ready_at)}</b>\n"

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    if source_mode == "move":
        keyboard_rows.append(
            [
                InlineKeyboardButton(
                    "✅ Konfirmasi Pindahkan",
                    callback_data=f"gh:save:move:do:{stock.stock_id}:{max(1, page)}",
                )
            ]
        )
        keyboard_rows.append(
            [InlineKeyboardButton("⬅️ Kembali", callback_data=f"gh:save:move:list:{max(1, page)}")]
        )
    else:
        keyboard_rows.append(
            [
                InlineKeyboardButton(
                    "⏳ Pindahkan ke Awaiting Benefits",
                    callback_data=f"gh:save:move:confirm:{stock.stock_id}:{max(1, page)}",
                )
            ]
        )
        keyboard_rows.append(
            [InlineKeyboardButton("⬅️ Kembali", callback_data=f"gh:save:list:{max(1, page)}")]
        )

    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali ke Simpan Akun", callback_data="gh:save:menu")])

    await _respond(
        update,
        (
            "🗂️ <b>Detail Akun Simpan</b>\n"
            f"Username akun: <b>{html.escape(stock.username)}</b>\n"
            f"ID stok: <b>#{stock.stock_id}</b>\n"
            f"Status: <b>{status_text}</b>\n"
            f"Waktu simpan: <b>{html.escape(_format_display_day_time(stock.created_at))}</b>\n"
            f"Siap verifikasi: <b>{html.escape(_format_display_day_time(stock.ready_at))}</b>\n"
            f"{remaining_line}\n"
            f"<pre>{html.escape(stock.raw_text)}</pre>\n\n"
            f"{_admin_footer_text()}"
        ),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_github_sold_stock_picker(update: Update, page: int = 1) -> None:
    with get_session() as session:
        sold_stocks = list_sold_github_stocks(session)

    if not sold_stocks:
        await _respond(
            update,
            (
                "📭 <b>Belum Ada Akun Terjual</b>\n"
                "Akun GitHub Pack yang berhasil terjual akan muncul di sini.\n\n"
                f"{_admin_footer_text()}"
            ),
            _github_pack_menu_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    paged_rows, safe_page, total_pages = _paginate_rows(sold_stocks, page, ADMIN_LIST_PAGE_SIZE)

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for row in paged_rows:
        username_label = row.username
        if len(username_label) > 20:
            username_label = f"{username_label[:17]}..."
        sold_label = _format_display_time(row.sold_at)
        button_label = f"{username_label} | {sold_label}"
        keyboard_rows.append(
            [InlineKeyboardButton(button_label, callback_data=f"gh:sold:view:{row.stock_id}:{safe_page}")]
        )

    if total_pages > 1:
        keyboard_rows.append(_pagination_nav_row(safe_page, total_pages, "gh:sold:list"))
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="ac:ghpack")])

    await _respond(
        update,
        (
            "🧾 <b>Akun Terjual - GitHub Pack</b>\n"
            f"Halaman <b>{safe_page}/{total_pages}</b> • Total akun: <b>{len(sold_stocks)}</b>\n\n"
            "Pilih akun untuk lihat detail dan pindahkan ke produk <b>GHS Bekas</b>."
        ),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_github_sold_stock_detail(update: Update, stock_id: int, page: int = 1) -> None:
    with get_session() as session:
        detail = get_sold_github_stock_detail(session, stock_id)

    if detail is None:
        await _respond(update, "⚠️ Akun terjual tidak ditemukan.", _github_pack_menu_keyboard())
        return

    buyer_line = html.escape(detail.buyer_display)
    if detail.buyer_telegram_id is not None:
        buyer_line = f"{buyer_line} ({detail.buyer_telegram_id})"

    moved_line = "✅ Sudah dipindahkan ke GHS Bekas" if detail.is_moved_to_used else "⏳ Belum dipindahkan ke GHS Bekas"

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    if detail.is_moved_to_used:
        keyboard_rows.append([InlineKeyboardButton("✅ Sudah Masuk GHS Bekas", callback_data="noop")])
    else:
        keyboard_rows.append(
            [InlineKeyboardButton("♻️ Masukkan ke Produk GHS Bekas", callback_data=f"gh:sold:move:{detail.stock_id}:{max(1, page)}")]
        )
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali ke Akun Terjual", callback_data=f"gh:sold:list:{max(1, page)}")])
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali ke GitHub Pack", callback_data="ac:ghpack")])

    await _respond(
        update,
        (
            "🧾 <b>Detail Akun Terjual</b>\n"
            f"Username akun: <b>{html.escape(detail.username)}</b>\n"
            f"Tanggal terjual: <b>{html.escape(_format_display_day_time(detail.sold_at))}</b>\n"
            f"Umur akun: <b>{detail.account_age_days} hari</b>\n"
            f"Nomor reff: <code>{html.escape(detail.order_ref)}</code>\n"
            f"Pemesan: <b>{buyer_line}</b>\n"
            f"Status relist: {moved_line}\n\n"
            f"<pre>{html.escape(detail.raw_text)}</pre>\n\n"
            f"{_admin_footer_text()}"
        ),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_github_stock_list(update: Update, page: int = 1) -> None:
    with get_session() as session:
        stocks = list_github_stocks(session)

    if not stocks:
        await _respond(
            update,
            (
                "📭 <b>Stok GitHub Pack Kosong</b>\n"
                "Belum ada akun yang bisa ditampilkan.\n\n"
                f"{_admin_footer_text()}"
            ),
            _github_pack_menu_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    paged_stocks, safe_page, total_pages = _paginate_rows(stocks, page, ADMIN_LIST_PAGE_SIZE)
    start_no = (safe_page - 1) * ADMIN_LIST_PAGE_SIZE + 1

    lines = [
        "📋 <b>List Akun GitHub Pack</b>",
        f"Halaman <b>{safe_page}/{total_pages}</b> • Total akun: <b>{len(stocks)}</b>",
        "",
    ]
    for idx, stock in enumerate(paged_stocks, start=start_no):
        status_text = _stock_status_badge(stock.status)
        line = f"{idx}. <b>#{stock.id}</b> {html.escape(stock.username)} | {status_text}"
        if stock.status == "awaiting_benefits" and stock.available_at is not None:
            line += f" | Ready at <b>{html.escape(_format_display_day_time(stock.available_at))}</b>"
            line += f" | Sisa <b>{_format_remaining_compact(stock.available_at)}</b>"
        lines.append(line)
    lines.append("")
    lines.append(_admin_footer_text())

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    if total_pages > 1:
        keyboard_rows.append(_pagination_nav_row(safe_page, total_pages, "gh:list"))
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="ac:ghpack")])

    await _respond(update, "\n".join(lines), InlineKeyboardMarkup(keyboard_rows), parse_mode=ParseMode.HTML)


async def _send_github_stock_picker(update: Update, mode: str, page: int = 1) -> None:
    with get_session() as session:
        stocks = list_github_stocks(session)

    if not stocks:
        await _respond(
            update,
            (
                "📭 <b>Stok GitHub Pack Kosong</b>\n"
                "Belum ada akun yang bisa ditampilkan.\n\n"
                f"{_admin_footer_text()}"
            ),
            _github_pack_menu_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    prefix = "gh:view" if mode == "view" else "gh:del"
    title = "👁️ <b>Pilih Akun untuk Melihat Detail</b>" if mode == "view" else "🗑️ <b>Pilih Akun yang Ingin Dihapus</b>"
    page_prefix = "gh:view:page" if mode == "view" else "gh:del:page"
    paged_stocks, safe_page, total_pages = _paginate_rows(stocks, page, ADMIN_LIST_PAGE_SIZE)

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for stock in paged_stocks:
        label = f"{stock.username} | {_stock_status_badge(stock.status)}"
        keyboard_rows.append([InlineKeyboardButton(label, callback_data=f"{prefix}:{stock.id}")])
    if total_pages > 1:
        keyboard_rows.append(_pagination_nav_row(safe_page, total_pages, page_prefix))
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="ac:ghpack")])

    await _respond(
        update,
        f"{title}\nHalaman <b>{safe_page}/{total_pages}</b> • Total akun: <b>{len(stocks)}</b>\n\n{_admin_footer_text()}",
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_admin_catalog_list(update: Update, page: int = 1) -> None:
    with get_session() as session:
        ensure_github_pack_product(session)
        ensure_github_pack_used_product(session)
        products = list_products(session=session, include_suspended=True)

    if not products:
        await _respond(
            update,
            (
                "📭 <b>Produk Belum Tersedia</b>\n"
                "Tambahkan produk baru untuk mulai berjualan.\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("adm_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    paged_products, safe_page, total_pages = _paginate_rows(products, page, ADMIN_LIST_PAGE_SIZE)
    start_no = (safe_page - 1) * ADMIN_LIST_PAGE_SIZE + 1

    lines = [
        "📋 <b>Daftar Produk</b>",
        f"Halaman <b>{safe_page}/{total_pages}</b> • Total produk: <b>{len(products)}</b>",
    ]
    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for idx, product in enumerate(paged_products, start=start_no):
        status = "⏸️ Suspend" if product.is_suspended else "✅ Aktif"
        lines.append(
            f"{idx}. <b>#{product.id}</b> {html.escape(product.name)} | {_format_rupiah(product.price)} | stok {product.stock_available} | {status}"
        )
        keyboard_rows.append([InlineKeyboardButton(f"Buka #{product.id} {product.name}", callback_data=f"acp:{product.id}")])

    lines.append("")
    lines.append(_admin_footer_text())
    if total_pages > 1:
        keyboard_rows.append(_pagination_nav_row(safe_page, total_pages, "ac:list"))
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="back:adm_cat")])
    await _respond(update, "\n".join(lines), InlineKeyboardMarkup(keyboard_rows), parse_mode=ParseMode.HTML)


async def _send_admin_product_picker(update: Update, action: str, title: str, page: int = 1) -> None:
    with get_session() as session:
        products = list_products(session=session, include_suspended=True)

    if not products:
        await _respond(
            update,
            (
                "📭 <b>Tidak Ada Produk untuk Dipilih</b>\n"
                "Tambahkan produk terlebih dahulu atau ubah filter katalog.\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("adm_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    paged_products, safe_page, total_pages = _paginate_rows(products, page, ADMIN_LIST_PAGE_SIZE)

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for product in paged_products:
        label = f"#{product.id} {product.name}"
        keyboard_rows.append(
            [InlineKeyboardButton(label, callback_data=f"ap:{action}:{product.id}")]
        )

    if total_pages > 1:
        keyboard_rows.append(_pagination_nav_row(safe_page, total_pages, f"apl:{action}"))
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="back:adm_cat")])
    await _respond(
        update,
        (
            f"<b>{html.escape(title)}</b>\n"
            f"Halaman <b>{safe_page}/{total_pages}</b> • Total produk: <b>{len(products)}</b>\n\n"
            f"{_admin_footer_text()}"
        ),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_customer_catalog(update: Update) -> None:
    with get_session() as session:
        github_product = ensure_github_pack_product(session)
        github_used_product = ensure_github_pack_used_product(session)
        github_product_id = int(github_product.id)
        github_used_product_id = int(github_used_product.id)
        products = list_products(session=session, include_suspended=False)

    if not products:
        await _respond(
            update,
            (
                "📭 <b>Katalog Kosong</b>\n"
                "Produk belum tersedia saat ini.\n\n"
                f"{_customer_footer_text()}"
            ),
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    products = sorted(
        products,
        key=lambda x: (0 if x.id == github_product_id else 1 if x.id == github_used_product_id else 2, x.id),
    )

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for product in products:
        label = f"🛍️ {product.name} | {_format_rupiah(product.price)} | stok {product.stock_available}"
        keyboard_rows.append(
            [InlineKeyboardButton(label, callback_data=f"cp:{product.id}")]
        )

    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")])
    await _respond(
        update,
        (
            "🛍️ <b>Katalog Produk</b>\n"
            "Pilih produk favorit kamu untuk lihat detail dan checkout.\n\n"
            f"{_customer_footer_text()}"
        ),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_customer_orders(update: Update, telegram_id: int, page: int = 1) -> None:
    with get_session() as session:
        user = get_user_by_telegram_id(session, telegram_id)
        if user is None:
            await _respond(
                update,
                "⚠️ <b>User tidak ditemukan</b>\nSilakan mulai ulang dari menu utama.",
                _back_keyboard("main"),
                parse_mode=ParseMode.HTML,
            )
            return
        orders_page = get_customer_orders_page(
            session,
            customer_id=user.id,
            page=page,
            page_size=CUSTOMER_ORDERS_PAGE_SIZE,
        )

    if orders_page.total_items <= 0:
        await _respond(
            update,
            (
                "📭 <b>Belum Ada Pesanan</b>\n"
                "Kamu belum punya riwayat order. Yuk checkout produk dulu.\n\n"
                f"{_customer_footer_text()}"
            ),
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    start_no = (orders_page.page - 1) * CUSTOMER_ORDERS_PAGE_SIZE + 1
    rows = [
        (start_no + idx, row.order_ref, row.status)
        for idx, row in enumerate(orders_page.rows)
    ]

    await _respond(
        update,
        (
            "📦 <b>Pesanan Saya</b>\n"
            "Klik nomor order untuk lihat detail pesanan dan akun.\n\n"
            f"{_customer_footer_text()}"
        ),
        _customer_orders_keyboard(
            page=orders_page.page,
            total_pages=orders_page.total_pages,
            rows=rows,
        ),
        parse_mode=ParseMode.HTML,
    )


async def _send_customer_order_detail(update: Update, telegram_id: int, order_ref: str, page: int) -> None:
    with get_session() as session:
        user = get_user_by_telegram_id(session, telegram_id)
        if user is None:
            await _respond(
                update,
                "⚠️ <b>User tidak ditemukan</b>\nSilakan mulai ulang dari menu utama.",
                _back_keyboard("main"),
                parse_mode=ParseMode.HTML,
            )
            return
        detail = get_customer_order_detail(
            session,
            customer_id=user.id,
            order_ref=order_ref,
        )

    if detail is None:
        await _respond(
            update,
            "⚠️ <b>Pesanan tidak ditemukan</b>\nPastikan nomor order sudah benar.",
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    lines = [
        "🧾 <b>Detail Pesanan</b>",
        f"Order: <code>{html.escape(detail.order_ref)}</code>",
        f"Status: {_order_status_badge(detail.status)}",
        f"Total: <b>{_format_rupiah(detail.total_amount)}</b>",
    ]

    if detail.item_lines:
        lines.append("🛍️ Item:")
        for item_line in detail.item_lines:
            lines.append(f"• {html.escape(item_line)}")

    include_reorder = detail.status == "delivered" and bool(detail.item_lines)
    include_copy = detail.status == "delivered" and bool(detail.account_blocks)
    if include_copy:
        lines.append("")
        lines.append("🔐 <b>Detail Akun:</b>")
        for idx, raw in enumerate(detail.account_blocks, start=1):
            lines.append(f"\n<b>Akun {idx}</b>")
            lines.append(f"<pre>{html.escape(raw)}</pre>")
        lines.append("")
        lines.append("📋 Gunakan tombol <b>Copy Akun</b> untuk menyalin data akun dengan cepat.")
    elif detail.status == "delivered":
        lines.append("")
        lines.append("⚠️ Status berhasil, tapi detail akun belum tersedia. Silakan hubungi admin.")
    else:
        lines.append("")
        lines.append("ℹ️ Detail akun akan muncul setelah pesanan berstatus berhasil.")

    lines.append("")
    lines.append(_customer_footer_text())

    await _respond(
        update,
        "\n".join(lines),
        _customer_order_detail_keyboard(
            detail.order_ref,
            page,
            include_copy=include_copy,
            include_reorder=include_reorder,
        ),
        parse_mode=ParseMode.HTML,
    )


async def _send_customer_order_copy(update: Update, telegram_id: int, order_ref: str, page: int) -> None:
    with get_session() as session:
        user = get_user_by_telegram_id(session, telegram_id)
        if user is None:
            await _respond(
                update,
                "⚠️ <b>User tidak ditemukan</b>\nSilakan mulai ulang dari menu utama.",
                _back_keyboard("main"),
                parse_mode=ParseMode.HTML,
            )
            return
        detail = get_customer_order_detail(
            session,
            customer_id=user.id,
            order_ref=order_ref,
        )

    if detail is None:
        await _respond(
            update,
            "⚠️ <b>Pesanan tidak ditemukan</b>\nPastikan nomor order sudah benar.",
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return
    if detail.status != "delivered" or not detail.account_blocks:
        await _send_customer_order_detail(update, telegram_id, order_ref, page)
        return

    payload = _build_accounts_copy_text(detail.order_ref, detail.account_blocks)
    query = update.callback_query
    if query is not None and query.message is not None:
        await query.message.reply_text("✅ Data akun siap disalin. Berikut detail lengkapnya:")
        for chunk in _split_message_chunks(payload):
            await query.message.reply_text(chunk)

    await _send_customer_order_detail(update, telegram_id, order_ref, page)


async def _send_customer_order_status(update: Update, telegram_id: int, order_ref: str) -> None:
    with get_session() as session:
        user = get_user_by_telegram_id(session, telegram_id)
        if user is None:
            await _respond(
                update,
                "⚠️ <b>User tidak ditemukan</b>\nSilakan mulai ulang dari menu utama.",
                _back_keyboard("main"),
                parse_mode=ParseMode.HTML,
            )
            return
        status_view = get_customer_order_status_by_ref(
            session,
            customer_id=user.id,
            order_ref=order_ref,
        )

    if status_view is None:
        await _respond(
            update,
            "⚠️ <b>Order tidak ditemukan</b>\nCek kembali nomor order kamu atau buka menu Pesanan Saya.",
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    lines = [
        "📌 <b>Status Pesanan</b>",
        f"Order Ref: <code>{html.escape(status_view.order_ref)}</code>",
        f"Status: {_order_status_badge(status_view.status)}",
        f"Total Bayar: <b>{_format_rupiah(status_view.total_amount)}</b>",
    ]

    if status_view.payment_ref:
        lines.append(f"Payment Ref: <code>{html.escape(status_view.payment_ref)}</code>")

    lines.extend(
        [
            "",
            "🕒 <b>Timeline</b>",
            f"• Dibuat: {_format_display_time(status_view.created_at)}",
        ]
    )

    if status_view.status == "pending_payment":
        if status_view.expires_at is not None:
            lines.append(f"• Batas bayar: {_format_display_time(status_view.expires_at)}")
            lines.append(f"• Sisa waktu: {_format_remaining_text(status_view.expires_at)}")
        lines.append("• Aksi: lakukan pembayaran sesuai nominal sebelum waktu habis.")

    if status_view.paid_at is not None:
        lines.append(f"• Dibayar: {_format_display_time(status_view.paid_at)}")
    if status_view.delivered_at is not None:
        lines.append(f"• Dikirim: {_format_display_time(status_view.delivered_at)}")
    if status_view.cancelled_at is not None:
        lines.append(f"• Dibatalkan/Kedaluwarsa: {_format_display_time(status_view.cancelled_at)}")

    lines.append("")
    lines.append(_customer_footer_text())

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    if status_view.status == "pending_payment":
        keyboard_rows.append(
            [InlineKeyboardButton("❌ Batalkan Pesanan", callback_data=f"ord:cancel:{status_view.order_ref}")]
        )
    keyboard_rows.append([InlineKeyboardButton("📦 Pesanan Saya", callback_data="cus:ord")])
    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")])

    await _respond(
        update,
        "\n".join(lines),
        InlineKeyboardMarkup(keyboard_rows),
        parse_mode=ParseMode.HTML,
    )


async def _send_product_detail(update: Update, product_id: int, qty: int = 1) -> None:
    qty = max(1, min(qty, 20))
    nearest_ready_at = None
    with get_session() as session:
        product = get_product(session, product_id)
        if product is None or product.is_suspended:
            await _respond(
                update,
                "⚠️ <b>Produk tidak tersedia</b>\nPilih produk lain yang masih aktif.",
                _back_keyboard("cus_cat"),
                parse_mode=ParseMode.HTML,
            )
            return
        stock = get_available_stock_count(session, product_id)
        product_name = product.name
        product_price = product.price
        product_description = product.description or "-"
        github_mode = is_github_pack_product(session, product_id)
        nearest_ready_at = get_nearest_awaiting_ready_at(session, product_id)

    text = (
        "🧾 <b>Detail Produk</b>\n"
        f"📦 Nama: <b>{html.escape(product_name)}</b>\n"
        f"💰 Harga: <b>{_format_rupiah(product_price)}</b>\n"
        f"📦 Stok: {stock}\n"
        f"📝 Deskripsi: {html.escape(product_description)}\n\n"
        f"{_customer_footer_text()}"
    )

    if stock <= 0:
        empty_lines = [
            text,
            "⚠️ <b>Stok sedang habis.</b>",
        ]
        if nearest_ready_at is not None:
            ready_at_text = html.escape(_format_display_day_time(nearest_ready_at.available_at))
            empty_lines.extend(
                [
                    "Ready kembali pada:",
                    f"<b>{ready_at_text}</b> • <b>{nearest_ready_at.account_count} accounts</b>",
                ]
            )
        empty_lines.append("Aktifkan notifikasi agar kamu dapat info saat stok tersedia lagi.")

        await _respond(
            update,
            "\n".join(empty_lines),
            InlineKeyboardMarkup(
                [
                    [InlineKeyboardButton("🔔 Ingatkan Saat Restock", callback_data=f"restock:sub:{product_id}")],
                    [InlineKeyboardButton("⬅️ Kembali", callback_data="back:cus_cat")],
                ]
            ),
            parse_mode=ParseMode.HTML,
        )
        return

    if github_mode:
        keyboard = InlineKeyboardMarkup(
            [
                [
                    InlineKeyboardButton("➖", callback_data=f"qty:dec:{product_id}:{qty}"),
                    InlineKeyboardButton(f"{qty}", callback_data="noop"),
                    InlineKeyboardButton("➕", callback_data=f"qty:inc:{product_id}:{qty}"),
                ],
                [InlineKeyboardButton("🛒 Pesan Sekarang", callback_data=f"buy:{product_id}:{qty}")],
                [InlineKeyboardButton(f"🧺 Take All ({stock})", callback_data=f"buyall:{product_id}")],
                [InlineKeyboardButton("⬅️ Kembali", callback_data="back:cus_cat")],
            ]
        )
    else:
        keyboard = InlineKeyboardMarkup(
            [
                [
                    InlineKeyboardButton("🛒 Beli 1", callback_data=f"buy:{product_id}:1"),
                    InlineKeyboardButton("🛒 Beli 2", callback_data=f"buy:{product_id}:2"),
                ],
                [
                    InlineKeyboardButton("🛒 Beli 3", callback_data=f"buy:{product_id}:3"),
                    InlineKeyboardButton("🛒 Beli 5", callback_data=f"buy:{product_id}:5"),
                ],
                [InlineKeyboardButton(f"🧺 Take All ({stock})", callback_data=f"buyall:{product_id}")],
                [InlineKeyboardButton("✍️ Input Qty Manual", callback_data=f"buyq:{product_id}")],
                [InlineKeyboardButton("⬅️ Kembali", callback_data="back:cus_cat")],
            ]
        )
    await _respond(update, text, keyboard, parse_mode=ParseMode.HTML)


async def _upsert_admin_order_notification(update: Update, order_ref: str) -> None:
    admin_id = get_primary_admin_id(settings.role_file_path)
    if admin_id is None:
        return

    with get_session() as session:
        notification = get_order_admin_notification(session, order_ref=order_ref)

    if notification is None:
        return

    message_text = build_admin_order_message(notification)
    reply_markup = build_admin_order_actions_keyboard(notification.order_ref, notification.status)
    upsert_result = await upsert_admin_order_message(
        bot=update.get_bot(),
        admin_chat_id=admin_id,
        message_text=message_text,
        reply_markup=reply_markup,
        existing_chat_id=notification.admin_chat_id,
        existing_message_id=notification.admin_message_id,
    )

    if upsert_result is None:
        return

    with get_session() as session:
        set_admin_message_ref(
            session=session,
            order_ref=order_ref,
            chat_id=upsert_result[0],
            message_id=upsert_result[1],
        )


async def _send_checkout_result(
    update: Update,
    telegram_id: int,
    product_id: int,
    qty: int,
    source_order_ref: str | None = None,
    source_product_name: str | None = None,
) -> bool:
    started_ms = monotonic_ms()
    order_ref_for_log = ""
    send_mode = "none"
    result_reason = "unknown"
    success = False
    has_qris_payload = False
    qris_dynamic_used = False
    qris_dynamic_ready = False
    qris_dynamic_error = ""

    try:
        with get_session() as session:
            user = get_user_by_telegram_id(session, telegram_id)
            if user is None:
                result_reason = "user_not_found"
                await _respond(
                    update,
                    "⚠️ <b>User tidak ditemukan</b>\nSilakan jalankan /start lalu coba lagi.",
                    _back_keyboard("main"),
                    parse_mode=ParseMode.HTML,
                )
                return False
            try:
                order, payment = create_checkout(
                    session,
                    customer=user,
                    product_id=product_id,
                    quantity=qty,
                )
            except ValueError as exc:
                result_reason = "create_checkout_failed"
                await _respond(update, f"❌ Checkout gagal: {exc}", _back_keyboard("cus_cat"))
                return False
            order_ref = order.order_ref
            order_ref_for_log = order_ref
            payment_ref = payment.payment_ref
            expected_amount = payment.expected_amount
            expires_at = order.expires_at

        admin_task = asyncio.create_task(
            _upsert_admin_order_notification(update, order_ref),
            name=f"admin-checkout:{order_ref}",
        )
        _track_background_task(admin_task, f"admin-checkout:{order_ref}")

        expires_text = "-"
        if expires_at is not None:
            expires_text = _format_display_time(expires_at)

        lines = [
            "✅ <b>Checkout berhasil dibuat</b>",
            f"🧾 Order Ref: <code>{html.escape(order_ref)}</code>",
            f"🔖 Payment Ref: <code>{html.escape(payment_ref)}</code>",
            f"💰 Total Bayar: <b>{_format_rupiah(expected_amount)}</b>",
            f"⏱️ Batas bayar: <b>{expires_text}</b>",
        ]

        if source_order_ref is not None:
            lines.append(f"🔁 Reorder dari: <code>{html.escape(source_order_ref)}</code>")
            if source_product_name:
                lines.append(f"📦 Produk: <b>{html.escape(source_product_name)}</b>")

        lines.extend(
            [
                "",
                "🧭 <b>Langkah Berikutnya</b>",
                "1. Transfer sesuai nominal di atas.",
                "2. Pantau status order secara berkala.",
                "3. Jika batal, gunakan tombol Batalkan Pesanan.",
                f"🔎 Cek status cepat: <code>/order_status {html.escape(order_ref)}</code>",
                "",
                _customer_footer_text(),
            ]
        )
        result_keyboard = InlineKeyboardMarkup(
            [
                [InlineKeyboardButton("❌ Batalkan Pesanan", callback_data=f"ord:cancel:{order_ref}")],
                [InlineKeyboardButton("📦 Lihat Pesanan", callback_data="cus:ord")],
                [InlineKeyboardButton("⬅️ Kembali", callback_data="back:cus_cat")],
            ]
        )

        sent_message = None
        payload_text = "\n".join(lines)

        reply_target: Message | None = None
        if update.callback_query and update.callback_query.message:
            reply_target = update.callback_query.message
        elif update.message:
            reply_target = update.message

        if reply_target is None:
            result_reason = "missing_reply_target"
            logger.error("Checkout %s gagal: target pesan Telegram tidak tersedia", order_ref)
            return False

        qris_path = settings.qris_file_path
        dynamic_qris_png: bytes | None = None
        with get_session() as session:
            qris_static_payload = get_qris_static_payload(session)

        has_qris_payload = bool(qris_static_payload)
        if settings.qris_dynamic_enabled and has_qris_payload:
            try:
                dynamic_qris_png = build_dynamic_qris_png(qris_static_payload, expected_amount)
                qris_dynamic_ready = True
            except Exception as exc:
                qris_dynamic_error = str(exc)
                logger.warning("Gagal generate QRIS dinamis checkout %s: %s", order_ref, exc)
        elif settings.qris_dynamic_enabled:
            qris_dynamic_error = "Payload QRIS belum di-set"

        if dynamic_qris_png is not None:
            try:
                dynamic_photo = BytesIO(dynamic_qris_png)
                dynamic_photo.name = f"qris-{order_ref}.png"
                sent_message = await reply_target.reply_photo(
                    photo=dynamic_photo,
                    caption=payload_text,
                    reply_markup=result_keyboard,
                    parse_mode=ParseMode.HTML,
                )
                send_mode = "dynamic_photo"
                qris_dynamic_used = True
                logger.info("Checkout %s dikirim sebagai QR dinamis", order_ref)
            except Exception as exc:
                qris_dynamic_error = str(exc)
                logger.warning("Gagal kirim QRIS dinamis checkout %s: %s", order_ref, exc)

        if sent_message is None and qris_path.exists():
            try:
                with qris_path.open("rb") as fh:
                    sent_message = await reply_target.reply_photo(
                        photo=fh,
                        caption=payload_text,
                        reply_markup=result_keyboard,
                        parse_mode=ParseMode.HTML,
                    )
                send_mode = "photo"
                logger.info("Checkout %s dikirim sebagai QR+caption tunggal", order_ref)
            except Exception as exc:
                logger.warning("Gagal kirim QRIS checkout %s: %s", order_ref, exc)

        if sent_message is None:
            send_mode = "text"
            sent_message = await reply_target.reply_text(
                payload_text,
                reply_markup=result_keyboard,
                parse_mode=ParseMode.HTML,
            )
            if qris_path.exists():
                logger.info("Checkout %s fallback ke pesan teks karena kirim QR gagal", order_ref)
            else:
                logger.info("Checkout %s dikirim sebagai teks karena file QRIS tidak tersedia", order_ref)

        if sent_message is not None:
            with get_session() as session:
                set_checkout_message_ref(
                    session=session,
                    order_ref=order_ref,
                    chat_id=int(sent_message.chat_id),
                    message_id=int(sent_message.message_id),
                )
            success = True
            result_reason = "ok"
            return True

        result_reason = "send_failed"
        return False
    finally:
        log_telemetry(
            logger,
            "bot.checkout_result",
            duration_ms=elapsed_ms(started_ms),
            success=success,
            reason=result_reason,
            order_ref=order_ref_for_log,
            telegram_id=telegram_id,
            product_id=product_id,
            qty=qty,
            send_mode=send_mode,
            has_qris_file=settings.qris_file_path.exists(),
            has_qris_payload=has_qris_payload,
            qris_dynamic_enabled=settings.qris_dynamic_enabled,
            qris_dynamic_used=qris_dynamic_used,
            qris_dynamic_ready=qris_dynamic_ready,
            qris_dynamic_error=_truncate_text(qris_dynamic_error),
            source_reorder=bool(source_order_ref),
        )


async def _send_quick_reorder_result(
    update: Update,
    telegram_id: int,
    source_order_ref: str,
) -> bool:
    with get_session() as session:
        user = get_user_by_telegram_id(session, telegram_id)
        if user is None:
            await _respond(
                update,
                "⚠️ <b>User tidak ditemukan</b>\nSilakan jalankan /start lalu coba lagi.",
                _back_keyboard("main"),
                parse_mode=ParseMode.HTML,
            )
            return False

        try:
            target = get_quick_reorder_target(
                session,
                customer_id=user.id,
                source_order_ref=source_order_ref,
            )
        except ValueError as exc:
            await _respond(update, f"⚠️ {exc}", _back_keyboard("main"))
            return False

    return await _send_checkout_result(
        update,
        telegram_id=telegram_id,
        product_id=target.product_id,
        qty=target.quantity,
        source_order_ref=target.source_order_ref,
        source_product_name=target.product_name,
    )


async def _run_update_script_with_code(action: str) -> tuple[int, str]:
    script_path = Path(settings.project_root / "ops" / "update_manager.sh")
    if not script_path.exists():
        return 1, "Script update tidak ditemukan."

    proc = await asyncio.to_thread(
        subprocess.run,
        ["bash", str(script_path), action],
        cwd=str(settings.project_root),
        capture_output=True,
        text=True,
        check=False,
    )

    stdout = (proc.stdout or "").strip()
    stderr = (proc.stderr or "").strip()
    if stdout and stderr:
        output = f"{stdout}\n{stderr}"
    else:
        output = stdout or stderr or "Tidak ada output"

    if len(output) > 3500:
        output = output[:3500] + "\n..."
    return int(proc.returncode), output


async def _run_update_script(action: str) -> str:
    _code, output = await _run_update_script_with_code(action)
    return output


def _detect_update_state(output: str) -> str:
    text = output.lower()
    if "status: update available" in text:
        return "update_available"
    if "status: up-to-date" in text or "status: up to date" in text:
        return "up_to_date"
    return "unknown"


def _extract_update_summary_line(output: str) -> str | None:
    for line in output.splitlines():
        candidate = line.strip()
        if candidate.lower().startswith("update selesai"):
            return candidate
    return None


def _build_update_check_user_message(return_code: int, output: str) -> tuple[str, bool | None]:
    if return_code != 0:
        return (
            "❌ <b>Cek update gagal</b>\n"
            "Bot belum bisa mengecek update saat ini. Coba lagi sebentar lagi.",
            None,
        )

    state = _detect_update_state(output)
    if state == "up_to_date":
        return (
            "✅ <b>Bot sudah versi terbaru</b>\n"
            "Saat ini tidak ada update baru.",
            False,
        )

    if state == "update_available":
        return (
            "⬆️ <b>Update baru ditemukan</b>\n"
            "Bot akan menerapkan update secara otomatis.",
            True,
        )

    return (
        "ℹ️ <b>Status update belum bisa dipastikan</b>\n"
        "Silakan coba lagi dalam beberapa saat.",
        None,
    )


def _build_update_apply_user_message(return_code: int, output: str) -> str:
    if return_code != 0:
        return (
            "❌ <b>Update gagal diterapkan</b>\n"
            "Perubahan belum diterapkan. Silakan coba lagi nanti."
        )

    summary_line = _extract_update_summary_line(output)
    if summary_line and "tidak ada commit baru" in summary_line.lower():
        return (
            "✅ <b>Bot sudah versi terbaru</b>\n"
            "Tidak ada commit baru, jadi tidak perlu update."
        )

    lines = [
        "✅ <b>Update berhasil diterapkan</b>",
        "Bot sudah diperbarui dan service sudah direstart.",
    ]
    if summary_line:
        lines.append(f"Info: <i>{html.escape(summary_line)}</i>")
    return "\n".join(lines)


async def _run_auto_update_flow() -> str:
    check_code, check_output = await _run_update_script_with_code("check")
    check_message, should_apply = _build_update_check_user_message(check_code, check_output)

    if should_apply is not True:
        return check_message

    apply_code, apply_output = await _run_update_script_with_code("update")
    apply_message = _build_update_apply_user_message(apply_code, apply_output)
    return f"{check_message}\n\n{apply_message}"


async def _ensure_user(
    update: Update,
    context: ContextTypes.DEFAULT_TYPE | None = None,
):
    tg_user = update.effective_user
    if tg_user is None:
        raise ValueError("User Telegram tidak ditemukan.")

    role = _role_for_telegram_id(tg_user.id)

    if context is not None:
        cached = context.user_data.get(USER_CTX_CACHE_KEY)
        now = time.monotonic()
        if isinstance(cached, dict):
            cached_until = float(cached.get("until", 0.0))
            cached_role = str(cached.get("role", ""))
            cached_tg = int(cached.get("telegram_id", 0) or 0)
            cached_id = int(cached.get("id", 0) or 0)
            if cached_until > now and cached_role == role and cached_tg == tg_user.id and cached_id > 0:
                return UserContext(id=cached_id, telegram_id=cached_tg), role

    with get_session() as session:
        db_user = upsert_user(
            session=session,
            telegram_id=tg_user.id,
            username=tg_user.username,
            full_name=tg_user.full_name,
            role=role,
        )
        if db_user.id is None:
            raise ValueError("Gagal menyimpan user Telegram.")
        user_ctx = UserContext(id=int(db_user.id), telegram_id=int(db_user.telegram_id))

    if context is not None:
        context.user_data[USER_CTX_CACHE_KEY] = {
            "id": user_ctx.id,
            "telegram_id": user_ctx.telegram_id,
            "role": role,
            "until": time.monotonic() + USER_CTX_CACHE_TTL_SECONDS,
        }

    return user_ctx, role


def _ensure_admin(update: Update) -> bool:
    tg_user = update.effective_user
    if tg_user is None:
        return False
    return _role_for_telegram_id(tg_user.id) == "admin"


async def start_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    db_user, role = await _ensure_user(update)
    tg_user = update.effective_user
    username = "customer"
    if tg_user is not None:
        username = tg_user.username or tg_user.full_name or "customer"

    with get_session() as session:
        total_transaksi = count_delivered_orders_by_customer(session, db_user.id)

    _clear_flow(context)
    context.user_data.pop(AWAIT_QRIS_IMAGE_KEY, None)
    await _send_main_menu(
        update,
        role=role,
        welcome=True,
        username=username,
        total_transaksi=total_transaksi,
        context=context,
    )


async def help_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, role = await _ensure_user(update)
    await _send_help(update, role)


async def catalog_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, role = await _ensure_user(update)
    _clear_flow(context)
    if role == "admin":
        await _send_admin_catalog_menu(update)
        return
    await _send_customer_catalog(update)


async def admin_catalog_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)

    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return
    _clear_flow(context)
    await _send_admin_catalog_menu(update)


async def product_add_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return
    _set_flow(context, FLOW_ADMIN_ADD_PRODUCT)
    await _respond(
        update,
        (
            "➕ <b>Upsert Produk</b>\n"
            "Kirim data dengan format:\n"
            "<code>Nama|Harga|Deskripsi</code>\n\n"
            f"{_admin_footer_text()}"
        ),
        _back_keyboard("adm_cat"),
        parse_mode=ParseMode.HTML,
    )


async def stock_add_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return

    await _send_admin_product_picker(
        update,
        action="stk",
        title="📥 Pilih produk untuk ditambah stok.",
    )


async def product_suspend_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return

    await _send_admin_product_picker(
        update,
        action="sup",
        title="⏸️ Pilih produk yang akan disuspend.",
    )


async def product_unsuspend_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return

    await _send_admin_product_picker(
        update,
        action="uns",
        title="▶️ Pilih produk yang akan diaktifkan kembali.",
    )


async def product_delete_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return

    await _send_admin_product_picker(
        update,
        action="del",
        title="🗑️ Pilih produk yang akan dihapus.",
    )


async def buy_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    db_user, role = await _ensure_user(update)
    if role != "customer":
        await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
        return

    if len(context.args) < 2:
        await _respond(
            update,
            "Gunakan format: <code>/buy PRODUCT_ID QTY</code>\nContoh: <code>/buy 2 1</code>",
            _back_keyboard("cus_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    try:
        product_id = int(context.args[0])
        qty = int(context.args[1])
    except ValueError:
        await _respond(
            update,
            "⚠️ PRODUCT_ID dan QTY harus berupa angka.",
            _back_keyboard("cus_cat"),
        )
        return

    await _send_checkout_result(update, db_user.telegram_id, product_id, qty)


async def my_orders_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    db_user, _ = await _ensure_user(update)
    await _send_customer_orders(update, db_user.telegram_id, page=1)


async def order_status_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    db_user, role = await _ensure_user(update)
    if role == "admin":
        await _respond(update, "🚫 Admin tidak menggunakan command ini.", _back_keyboard("main"))
        return

    if not context.args:
        await _respond(
            update,
            (
                "📌 <b>Cek Status Pesanan</b>\n"
                "Gunakan format: <code>/order_status ORDER_REF</code>\n"
                "Contoh: <code>/order_status ORD20260101000123</code>"
            ),
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    order_ref = context.args[0].strip().upper()
    await _send_customer_order_status(update, db_user.telegram_id, order_ref)


async def ops_metrics_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return

    with get_session() as session:
        metrics = collect_operational_metrics(
            session,
            window_hours=settings.metrics_report_window_hours,
        )
        runtime_metrics = collect_runtime_telemetry_metrics(
            session,
            window_hours=settings.metrics_report_window_hours,
        )

    report_text = "\n\n".join(
        [
            format_operational_metrics_report(metrics),
            format_runtime_telemetry_report(runtime_metrics),
        ]
    )
    await _respond(
        update,
        report_text,
        _ops_metrics_keyboard(),
        parse_mode=ParseMode.HTML,
    )


async def reorder_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    db_user, role = await _ensure_user(update)
    if role == "admin":
        await _respond(update, "🚫 Admin tidak menggunakan command ini.", _back_keyboard("main"))
        return

    if not context.args:
        await _respond(
            update,
            (
                "🔁 <b>Quick Reorder</b>\n"
                "Gunakan format: <code>/reorder ORDER_REF</code>\n"
                "Contoh: <code>/reorder ORD20260101000123</code>"
            ),
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    source_order_ref = context.args[0].strip().upper()
    await _send_quick_reorder_result(update, db_user.telegram_id, source_order_ref)


async def broadcast_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return

    context.user_data[AWAIT_QRIS_IMAGE_KEY] = False
    _set_flow(context, FLOW_ADMIN_BROADCAST)
    await _respond(
        update,
        (
            "📢 <b>Broadcast ke Customer</b>\n"
            "Kirim <b>teks</b>, <b>foto</b>, atau <b>file</b> sekarang.\n"
            "Untuk foto/file, caption opsional.\n\n"
            f"{_admin_footer_text()}"
        ),
        _back_keyboard("main"),
        parse_mode=ParseMode.HTML,
    )


async def set_qris_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return

    _clear_flow(context)
    context.user_data[AWAIT_QRIS_IMAGE_KEY] = True
    await _respond(
        update,
        (
            "🖼️ <b>Upload QRIS</b>\n"
            "Kirim gambar QRIS sekarang dalam format foto.\n"
            "Payload akan dicoba diekstrak otomatis dari gambar.\n\n"
            f"{_admin_footer_text()}"
        ),
        _back_keyboard("pay"),
        parse_mode=ParseMode.HTML,
    )


async def update_check_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return

    check_code, check_output = await _run_update_script_with_code("check")
    check_message, _ = _build_update_check_user_message(check_code, check_output)
    await _respond(update, check_message, _back_keyboard("main"), parse_mode=ParseMode.HTML)


async def update_apply_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return

    apply_code, apply_output = await _run_update_script_with_code("update")
    apply_message = _build_update_apply_user_message(apply_code, apply_output)
    await _respond(update, apply_message, _back_keyboard("main"), parse_mode=ParseMode.HTML)


async def callback_router(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    query = update.callback_query
    if query is None:
        return

    tg_user = update.effective_user
    if tg_user is None:
        return

    role = _role_for_telegram_id(tg_user.id)
    telegram_id = int(tg_user.id)
    cached_user_ctx: UserContext | None = None

    async def _require_db_user_ctx() -> UserContext:
        nonlocal cached_user_ctx
        if cached_user_ctx is None:
            cached_user_ctx, _ = await _ensure_user(update, context)
        return cached_user_ctx

    data = query.data or ""
    if data.startswith("buy:") or data.startswith("buyall:") or data.startswith("ord:reorder:"):
        await query.answer("⏳ Memproses checkout...", show_alert=False)
    else:
        await query.answer()

    admin_only_prefixes = ("adm:", "ac:", "acp:", "gh:", "ap:", "pay:", "up:")
    if role != "admin" and any(data.startswith(prefix) for prefix in admin_only_prefixes):
        await _respond_admin_only(update)
        return

    if data.startswith("back:"):
        target = data.split(":", maxsplit=1)[1]
        _clear_flow(context)
        context.user_data.pop(AWAIT_QRIS_IMAGE_KEY, None)
        _clear_complaint_draft(context)
        if target == "main":
            await _send_main_menu(update, role, context=context)
            return
        if target == "adm_cat":
            await _send_admin_catalog_menu(update)
            return
        if target == "cus_cat":
            await _send_customer_catalog(update)
            return
        if target == "pay":
            await _respond(
                update,
                f"💳 <b>Konfigurasi Payment</b>\n\n{_admin_footer_text()}",
                _payment_menu_keyboard(),
                parse_mode=ParseMode.HTML,
            )
            return
        if target == "gh_save":
            await _send_github_saved_account_menu(update)
            return
        if target == "upd":
            await _respond(
                update,
                f"🔄 <b>Menu Update Bot</b>\n\n{_admin_footer_text()}",
                _update_menu_keyboard(),
                parse_mode=ParseMode.HTML,
            )
            return
        await _send_main_menu(update, role, context=context)
        return

    if data == "adm:cat":
        if role != "admin":
            await _respond_admin_only(update)
            return
        _clear_flow(context)
        await _send_admin_catalog_menu(update)
        return

    if data == "adm:bc":
        if role != "admin":
            await _respond_admin_only(update)
            return
        context.user_data[AWAIT_QRIS_IMAGE_KEY] = False
        _set_flow(context, FLOW_ADMIN_BROADCAST)
        await _respond(
            update,
            (
                "📢 <b>Broadcast ke Customer</b>\n"
                "Kirim <b>teks</b>, <b>foto</b>, atau <b>file</b> sekarang.\n"
                "Untuk foto/file, caption opsional.\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data.startswith("adm:rb:bc:"):
        if role != "admin":
            await _respond_admin_only(update)
            return

        try:
            product_id = int(data.split(":", maxsplit=3)[3])
        except ValueError:
            await _respond(update, "⚠️ Produk untuk broadcast tidak valid.", _back_keyboard("main"))
            return

        with get_session() as session:
            product = get_product(session, product_id)
            if product is None or product.is_suspended:
                await _respond(update, "⚠️ Produk tidak ditemukan atau tidak aktif.", _back_keyboard("main"))
                return

            product_name = product.name
            ready_count = get_available_stock_count(session, product_id)
            if ready_count <= 0:
                await _respond(
                    update,
                    "⚠️ Stok ready saat ini belum tersedia untuk produk ini.",
                    _back_keyboard("main"),
                )
                return

            broadcast_message = build_product_ready_broadcast_message(product_name, ready_count)
            db_user_ctx = await _require_db_user_ctx()
            sent, failed = await broadcast_to_customers(
                session=session,
                bot=context.bot,
                admin_user_id=db_user_ctx.id,
                message=broadcast_message,
                parse_mode=ParseMode.HTML,
            )

        await _respond(
            update,
            (
                "✅ <b>Broadcast notifikasi ketersediaan terkirim</b>\n"
                f"Produk: <b>{html.escape(product_name)}</b>\n"
                f"Jumlah ready: <b>{ready_count}</b>\n"
                f"✅ Sent: <b>{sent}</b>\n"
                f"❌ Failed: <b>{failed}</b>"
            ),
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "adm:pay":
        if role != "admin":
            await _respond_admin_only(update)
            return
        _clear_flow(context)
        context.user_data[AWAIT_QRIS_IMAGE_KEY] = False
        await _respond(
            update,
            f"💳 <b>Konfigurasi Payment</b>\n\n{_admin_footer_text()}",
            _payment_menu_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "adm:upd":
        if role != "admin":
            await _respond_admin_only(update)
            return
        _clear_flow(context)
        await _respond(
            update,
            "🔄 <b>Mengecek update bot...</b>\nMohon tunggu sebentar.",
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        auto_message = await _run_auto_update_flow()
        await _respond(
            update,
            auto_message,
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "adm:help":
        await _send_help(update, role)
        return

    if data == "adm:cmp":
        if role != "admin":
            await _respond_admin_only(update)
            return
        _clear_flow(context)
        _clear_complaint_draft(context)
        await _send_admin_complaint_menu(update)
        return

    if data == "adm:ops":
        if role != "admin":
            await _respond_admin_only(update)
            return

        with get_session() as session:
            metrics = collect_operational_metrics(
                session,
                window_hours=settings.metrics_report_window_hours,
            )
            runtime_metrics = collect_runtime_telemetry_metrics(
                session,
                window_hours=settings.metrics_report_window_hours,
            )

        await _respond(
            update,
            "\n\n".join(
                [
                    format_operational_metrics_report(metrics),
                    format_runtime_telemetry_report(runtime_metrics),
                ]
            ),
            _ops_metrics_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "adm:ops:retry":
        if role != "admin":
            await _respond_admin_only(update)
            return

        with get_session() as session:
            snapshot = collect_retry_queue_snapshot(session, recent_hours=24, top_n=5)

        await _respond(
            update,
            _format_retry_snapshot_text(snapshot),
            _ops_metrics_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "adm:ops:reset":
        if role != "admin":
            await _respond_admin_only(update)
            return

        with get_session() as session:
            reset_at = reset_operational_metrics(session)
            metrics = collect_operational_metrics(
                session,
                window_hours=settings.metrics_report_window_hours,
            )
            runtime_metrics = collect_runtime_telemetry_metrics(
                session,
                window_hours=settings.metrics_report_window_hours,
            )

        reset_text = _format_display_day_time(reset_at)
        await _respond(
            update,
            (
                f"✅ Metrik direset pada <b>{html.escape(reset_text)}</b>.\n\n"
                f"{format_operational_metrics_report(metrics)}\n\n"
                f"{format_runtime_telemetry_report(runtime_metrics)}"
            ),
            _ops_metrics_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "cus:cat":
        _clear_flow(context)
        await _send_customer_catalog(update)
        return

    if data == "cus:ord":
        _clear_flow(context)
        await _send_customer_orders(update, telegram_id, page=1)
        return

    if data == "cus:help":
        await _send_help(update, role)
        return

    if data == "cus:cmp":
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa membuat komplain customer.", _back_keyboard("main"))
            return
        db_user_ctx = await _require_db_user_ctx()
        _clear_flow(context)
        _clear_complaint_draft(context)
        await _send_customer_complaint_order_picker(update, customer_id=db_user_ctx.id, page=1)
        return

    if data.startswith("cmp:new:orders"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa membuat komplain customer.", _back_keyboard("main"))
            return
        db_user_ctx = await _require_db_user_ctx()
        page = 1
        if data.startswith("cmp:new:orders:"):
            try:
                page = int(data.split(":", maxsplit=3)[3])
            except ValueError:
                page = 1
        await _send_customer_complaint_order_picker(update, customer_id=db_user_ctx.id, page=page)
        return

    if data.startswith("cmp:new:ord:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa membuat komplain customer.", _back_keyboard("main"))
            return
        parts = data.split(":", maxsplit=4)
        if len(parts) != 5:
            await _respond(update, "⚠️ Data komplain tidak valid.", _back_keyboard("main"))
            return
        order_ref = parts[3].strip().upper()
        try:
            source_page = int(parts[4])
        except ValueError:
            source_page = 1

        db_user_ctx = await _require_db_user_ctx()
        with get_session() as session:
            detail = get_customer_order_detail(session, customer_id=db_user_ctx.id, order_ref=order_ref)
        if detail is None:
            await _respond(update, "⚠️ Nomor order tidak ditemukan.", _back_keyboard("main"))
            return

        _set_flow(
            context,
            FLOW_CUSTOMER_COMPLAINT_COMPOSE,
            order_ref=order_ref,
            source_page=max(1, source_page),
        )
        context.user_data[COMPLAINT_DRAFT_KEY] = {
            "order_ref": order_ref,
            "source_page": max(1, source_page),
            "complaint_text": "",
            "photo_file_ids": [],
        }
        await _respond(
            update,
            (
                "📝 <b>Tulis Keluhan Kamu</b>\n"
                f"Order: <code>{html.escape(order_ref)}</code>\n"
                "Kirim penjelasan keluhan (boleh beberapa pesan).\n"
                "Jika perlu, kirim foto bukti (bisa lebih dari satu).\n"
                "Setelah selesai, tekan tombol <b>Kirim Komplain</b>."
            ),
            _build_complaint_compose_keyboard(order_ref, max(1, source_page)),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "cmp:new:submit":
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa membuat komplain customer.", _back_keyboard("main"))
            return
        db_user_ctx = await _require_db_user_ctx()
        await _submit_customer_complaint_from_draft(update, context, db_user_ctx)
        return

    if data == "cmp:new:cancel":
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa membuat komplain customer.", _back_keyboard("main"))
            return
        _clear_flow(context)
        _clear_complaint_draft(context)
        await _send_main_menu(update, role, context=context)
        return

    if data.startswith("cmp:cus:refund:fill:"):
        if role == "admin":
            await _respond(update, "🚫 Hanya customer terkait yang bisa mengisi detail refund.", _back_keyboard("main"))
            return
        try:
            complaint_id = int(data.split(":", maxsplit=4)[4])
        except ValueError:
            await _respond(update, "⚠️ Data komplain refund tidak valid.", _back_keyboard("main"))
            return

        db_user_ctx = await _require_db_user_ctx()
        with get_session() as session:
            detail = get_complaint_detail(session, complaint_id=complaint_id)
        if detail is None or int(detail.customer_id) != int(db_user_ctx.id):
            await _respond(update, "⚠️ Komplain tidak ditemukan untuk akun kamu.", _back_keyboard("main"))
            return
        if detail.status != COMPLAINT_STATUS_AWAITING_CUSTOMER_REFUND_DETAILS:
            await _respond(update, "⚠️ Komplain ini belum meminta detail refund.", _back_keyboard("main"))
            return

        _set_flow(context, FLOW_CUSTOMER_REFUND_DETAIL, complaint_id=complaint_id)
        await _respond(
            update,
            (
                "💳 <b>Kirim Detail Refund</b>\n"
                f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>\n\n"
                "Kirim detail rekening/e-wallet tujuan refund beserta nama pemilik."
            ),
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data.startswith("cmp:admin:"):
        if role != "admin":
            await _respond_admin_only(update)
            return

        if data.startswith("cmp:admin:new:list"):
            page = 1
            if data.startswith("cmp:admin:new:list:"):
                try:
                    page = int(data.split(":", maxsplit=4)[4])
                except ValueError:
                    page = 1
            await _send_admin_complaint_list(update, bucket="new", page=page)
            return

        if data.startswith("cmp:admin:proc:list"):
            page = 1
            if data.startswith("cmp:admin:proc:list:"):
                try:
                    page = int(data.split(":", maxsplit=4)[4])
                except ValueError:
                    page = 1
            await _send_admin_complaint_list(update, bucket="proc", page=page)
            return

        if data.startswith("cmp:admin:done:list"):
            page = 1
            if data.startswith("cmp:admin:done:list:"):
                try:
                    page = int(data.split(":", maxsplit=4)[4])
                except ValueError:
                    page = 1
            await _send_admin_complaint_list(update, bucket="done", page=page)
            return

        if data.startswith("cmp:admin:view:"):
            parts = data.split(":", maxsplit=5)
            if len(parts) != 6:
                await _respond(update, "⚠️ Data detail komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            bucket = parts[3]
            if bucket not in {"new", "proc", "done"}:
                await _respond(update, "⚠️ Kategori komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            try:
                complaint_id = int(parts[4])
                page = int(parts[5])
            except ValueError:
                await _respond(update, "⚠️ Data detail komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            await _send_admin_complaint_detail(update, bucket=bucket, complaint_id=complaint_id, page=page)
            return

        db_user_ctx = await _require_db_user_ctx()

        if data.startswith("cmp:admin:new:process:"):
            parts = data.split(":", maxsplit=5)
            if len(parts) != 6:
                await _respond(update, "⚠️ Data proses komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            try:
                complaint_id = int(parts[4])
                page = int(parts[5])
            except ValueError:
                await _respond(update, "⚠️ Data proses komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            with get_session() as session:
                try:
                    detail = move_complaint_to_process(session, complaint_id=complaint_id, actor_id=db_user_ctx.id)
                except ValueError as exc:
                    await _respond(update, f"⚠️ {exc}", _admin_complaint_menu_keyboard())
                    return
            await _send_admin_complaint_detail(update, bucket="proc", complaint_id=detail.complaint_id, page=max(1, page))
            return

        if data.startswith("cmp:admin:new:reject:"):
            parts = data.split(":", maxsplit=5)
            if len(parts) != 6:
                await _respond(update, "⚠️ Data tolak komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            try:
                complaint_id = int(parts[4])
                page = int(parts[5])
            except ValueError:
                await _respond(update, "⚠️ Data tolak komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            with get_session() as session:
                try:
                    detail = reject_complaint(session, complaint_id=complaint_id, actor_id=db_user_ctx.id)
                except ValueError as exc:
                    await _respond(update, f"⚠️ {exc}", _admin_complaint_menu_keyboard())
                    return

            try:
                await context.bot.send_message(
                    chat_id=detail.customer_telegram_id,
                    text=(
                        "❌ <b>Komplain Ditolak</b>\n"
                        f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>\n"
                        "Admin menolak komplain ini. Jika ada data tambahan, silakan ajukan komplain baru."
                    ),
                    parse_mode=ParseMode.HTML,
                )
            except Exception as exc:
                logger.warning("Gagal kirim notifikasi penolakan komplain ke customer: %s", exc)

            await _send_admin_complaint_detail(update, bucket="done", complaint_id=detail.complaint_id, page=max(1, page))
            return

        if data.startswith("cmp:admin:proc:refund:"):
            parts = data.split(":", maxsplit=5)
            if len(parts) != 6:
                await _respond(update, "⚠️ Data refund komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            try:
                complaint_id = int(parts[4])
                page = int(parts[5])
            except ValueError:
                await _respond(update, "⚠️ Data refund komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            with get_session() as session:
                try:
                    detail = approve_complaint_refund(session, complaint_id=complaint_id, actor_id=db_user_ctx.id)
                except ValueError as exc:
                    await _respond(update, f"⚠️ {exc}", _admin_complaint_menu_keyboard())
                    return

            try:
                await context.bot.send_message(
                    chat_id=detail.customer_telegram_id,
                    text=(
                        "💸 <b>Pengajuan Refund Disetujui</b>\n"
                        f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>\n"
                        "Kirim detail rekening/e-wallet tujuan refund beserta nama pemilik melalui tombol di bawah."
                    ),
                    parse_mode=ParseMode.HTML,
                    reply_markup=InlineKeyboardMarkup(
                        [[InlineKeyboardButton("💳 Kirim Detail Refund", callback_data=f"cmp:cus:refund:fill:{detail.complaint_id}")]]
                    ),
                )
            except Exception as exc:
                logger.warning("Gagal kirim permintaan detail refund ke customer: %s", exc)

            await _send_admin_complaint_detail(update, bucket="proc", complaint_id=detail.complaint_id, page=max(1, page))
            return

        if data.startswith("cmp:admin:proc:replace:"):
            parts = data.split(":", maxsplit=5)
            if len(parts) != 6:
                await _respond(update, "⚠️ Data akun pengganti tidak valid.", _admin_complaint_menu_keyboard())
                return
            try:
                complaint_id = int(parts[4])
                page = int(parts[5])
            except ValueError:
                await _respond(update, "⚠️ Data akun pengganti tidak valid.", _admin_complaint_menu_keyboard())
                return
            with get_session() as session:
                try:
                    detail = mark_complaint_replacement_sent(
                        session,
                        complaint_id=complaint_id,
                        actor_id=db_user_ctx.id,
                    )
                except ValueError as exc:
                    await _respond(update, f"⚠️ {exc}", _admin_complaint_menu_keyboard())
                    return

            try:
                await context.bot.send_message(
                    chat_id=detail.customer_telegram_id,
                    text=(
                        "✅ <b>Komplain Ditindaklanjuti</b>\n"
                        f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>\n"
                        "Admin menginformasikan akun pengganti sudah dikirim."
                    ),
                    parse_mode=ParseMode.HTML,
                )
            except Exception as exc:
                logger.warning("Gagal kirim notifikasi akun pengganti ke customer: %s", exc)

            await _send_admin_complaint_detail(update, bucket="done", complaint_id=detail.complaint_id, page=max(1, page))
            return

        if data.startswith("cmp:admin:proc:pay:"):
            parts = data.split(":", maxsplit=5)
            if len(parts) != 6:
                await _respond(update, "⚠️ Data transfer refund tidak valid.", _admin_complaint_menu_keyboard())
                return
            try:
                complaint_id = int(parts[4])
                page = int(parts[5])
            except ValueError:
                await _respond(update, "⚠️ Data transfer refund tidak valid.", _admin_complaint_menu_keyboard())
                return

            with get_session() as session:
                detail = get_complaint_detail(session, complaint_id=complaint_id)
            if detail is None:
                await _respond(update, "⚠️ Komplain tidak ditemukan.", _admin_complaint_menu_keyboard())
                return
            if detail.status != COMPLAINT_STATUS_AWAITING_ADMIN_REFUND_TRANSFER:
                await _respond(update, "⚠️ Komplain belum siap transfer refund.", _admin_complaint_menu_keyboard())
                return

            _set_flow(context, FLOW_ADMIN_REFUND_PROOF, complaint_id=complaint_id, source_page=max(1, page))
            await _respond(
                update,
                (
                    "💸 <b>Kirim Dana Refund</b>\n"
                    f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>\n"
                    "Segera transfer dana refund, lalu kirim screenshot bukti transfer.\n"
                    "Tambahkan catatan pada caption foto jika diperlukan."
                ),
                InlineKeyboardMarkup(
                    [[InlineKeyboardButton("⬅️ Kembali", callback_data=f"cmp:admin:view:proc:{complaint_id}:{max(1, page)}")]]
                ),
                parse_mode=ParseMode.HTML,
            )
            return

        if data.startswith("cmp:admin:done:reopen:"):
            parts = data.split(":", maxsplit=5)
            if len(parts) != 6:
                await _respond(update, "⚠️ Data buka kembali komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            try:
                complaint_id = int(parts[4])
                page = int(parts[5])
            except ValueError:
                await _respond(update, "⚠️ Data buka kembali komplain tidak valid.", _admin_complaint_menu_keyboard())
                return
            with get_session() as session:
                try:
                    detail = reopen_done_complaint(session, complaint_id=complaint_id, actor_id=db_user_ctx.id)
                except ValueError as exc:
                    await _respond(update, f"⚠️ {exc}", _admin_complaint_menu_keyboard())
                    return

            try:
                await context.bot.send_message(
                    chat_id=detail.customer_telegram_id,
                    text=(
                        "♻️ <b>Komplain Dibuka Kembali</b>\n"
                        f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>\n"
                        "Admin membuka kembali kasus komplain untuk investigasi lanjutan."
                    ),
                    parse_mode=ParseMode.HTML,
                )
            except Exception as exc:
                logger.warning("Gagal kirim notifikasi reopen komplain ke customer: %s", exc)

            await _send_admin_complaint_detail(update, bucket="proc", complaint_id=detail.complaint_id, page=max(1, page))
            return

        await _respond(update, "⚠️ Aksi komplain admin tidak dikenali.", _admin_complaint_menu_keyboard())
        return

    if data == "noop":
        return

    if data.startswith("adm:ord:"):
        if role != "admin":
            await _respond_admin_only(update)
            return

        parts = data.split(":", maxsplit=3)
        if len(parts) != 4:
            await _respond(update, "⚠️ Aksi order admin tidak valid.", _back_keyboard("main"))
            return

        action = parts[2]
        order_ref = parts[3].strip().upper()

        if action == "paid":
            db_user_ctx = await _require_db_user_ctx()
            with get_session() as session:
                reconcile_result = confirm_order_payment_by_admin(
                    session=session,
                    order_ref=order_ref,
                    admin_user_id=db_user_ctx.id,
                )

            if reconcile_result.status == "paid":
                if reconcile_result.customer_chat_id and reconcile_result.delivery_message:
                    keyboard_rows: list[list[InlineKeyboardButton]] = [
                        [InlineKeyboardButton("🏠 /start Menu Utama", callback_data="back:main")]
                    ]
                    admin_id = get_primary_admin_id(settings.role_file_path)
                    if admin_id is not None:
                        keyboard_rows.append(
                            [InlineKeyboardButton("💬 Hubungi Admin", url=f"tg://user?id={admin_id}")]
                        )
                    await context.bot.send_message(
                        chat_id=reconcile_result.customer_chat_id,
                        text=reconcile_result.delivery_message,
                        parse_mode=ParseMode.HTML,
                        reply_markup=InlineKeyboardMarkup(keyboard_rows),
                        disable_web_page_preview=True,
                    )

                await _upsert_admin_order_notification(update, order_ref)
                await _respond(
                    update,
                    "✅ Pembayaran berhasil dikonfirmasi. Stok sudah dikirim ke customer.",
                    _back_keyboard("main"),
                )
                return

            if reconcile_result.status in {"duplicate", "expired"}:
                await _upsert_admin_order_notification(update, order_ref)

            await _respond(
                update,
                f"⚠️ {reconcile_result.message}",
                _back_keyboard("main"),
            )
            return

        if action == "cancel":
            db_user_ctx = await _require_db_user_ctx()
            with get_session() as session:
                cancel_result = cancel_order_by_admin(
                    session=session,
                    order_ref=order_ref,
                    admin_user_id=db_user_ctx.id,
                )

            warning_text = ""
            if cancel_result.ok:
                await _upsert_admin_order_notification(update, order_ref)

                admin_notification = cancel_result.admin_notification
                customer_chat_id = int(admin_notification.customer_telegram_id) if admin_notification is not None else 0
                if customer_chat_id > 0:
                    customer_text = "\n".join(
                        [
                            "❌ <b>Pesanan Dibatalkan</b>",
                            f"Order Ref: <code>{html.escape(order_ref)}</code>",
                            "Pesanan kamu dibatalkan oleh admin.",
                            "",
                            f"🔎 Cek status: <code>/order_status {html.escape(order_ref)}</code>",
                            "Jika butuh bantuan, hubungi admin.",
                        ]
                    )
                    customer_keyboard = InlineKeyboardMarkup(
                        [
                            [InlineKeyboardButton("📦 Cek Pesanan", callback_data="cus:ord")],
                            [InlineKeyboardButton("🏠 /start Menu Utama", callback_data="back:main")],
                        ]
                    )
                    checkout_chat_id = int(admin_notification.checkout_chat_id) if (
                        admin_notification is not None and admin_notification.checkout_chat_id is not None
                    ) else 0
                    checkout_message_id = int(admin_notification.checkout_message_id) if (
                        admin_notification is not None and admin_notification.checkout_message_id is not None
                    ) else 0
                    try:
                        if checkout_chat_id > 0 and checkout_message_id > 0:
                            upserted = await _upsert_customer_checkout_message(
                                context=context,
                                chat_id=checkout_chat_id,
                                message_id=checkout_message_id,
                                text=customer_text,
                                keyboard=customer_keyboard,
                            )
                            if not upserted:
                                raise RuntimeError("upsert checkout message gagal")
                        else:
                            sent = await context.bot.send_message(
                                chat_id=customer_chat_id,
                                text=customer_text,
                                parse_mode=ParseMode.HTML,
                                reply_markup=customer_keyboard,
                                disable_web_page_preview=True,
                            )
                            with get_session() as session:
                                set_checkout_message_ref(
                                    session=session,
                                    order_ref=order_ref,
                                    chat_id=int(sent.chat_id),
                                    message_id=int(sent.message_id),
                                )
                    except Exception as exc:
                        logger.warning("Gagal upsert notifikasi cancel ke customer: %s", exc)
                        try:
                            sent = await context.bot.send_message(
                                chat_id=customer_chat_id,
                                text=customer_text,
                                parse_mode=ParseMode.HTML,
                                reply_markup=customer_keyboard,
                                disable_web_page_preview=True,
                            )
                            with get_session() as session:
                                set_checkout_message_ref(
                                    session=session,
                                    order_ref=order_ref,
                                    chat_id=int(sent.chat_id),
                                    message_id=int(sent.message_id),
                                )
                        except Exception as send_exc:
                            logger.warning("Fallback kirim notifikasi cancel ke customer juga gagal: %s", send_exc)
                            with get_session() as session:
                                enqueue_notification_retry(
                                    session=session,
                                    channel="customer_cancelled_by_admin",
                                    chat_id=customer_chat_id,
                                    payload_text=customer_text,
                                    parse_mode="HTML",
                                )
                            warning_text = "\n⚠️ Notifikasi ke customer gagal dikirim langsung, sudah masuk retry queue."

            await _respond(update, f"{cancel_result.message}{warning_text}", _back_keyboard("main"))
            return

        await _respond(update, "⚠️ Aksi order admin tidak dikenali.", _back_keyboard("main"))
        return

    if data == "ac:view":
        await _send_admin_catalog_list(update, page=1)
        return

    if data.startswith("ac:list:"):
        try:
            page = int(data.split(":", maxsplit=2)[2])
        except ValueError:
            await _respond(update, "⚠️ Halaman katalog tidak valid.", _back_keyboard("adm_cat"))
            return
        await _send_admin_catalog_list(update, page=page)
        return

    if data == "ac:ghpack":
        if role != "admin":
            await _respond_admin_only(update)
            return
        _clear_flow(context)
        await _send_github_pack_menu(update)
        return

    if data.startswith("acp:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        try:
            product_id = int(data.split(":", maxsplit=1)[1])
        except ValueError:
            await _respond(update, "⚠️ Produk tidak valid.", _back_keyboard("adm_cat"))
            return

        is_ghpack = False
        missing_product = False
        with get_session() as session:
            product = get_product(session, product_id)
            if product is None:
                missing_product = True
            else:
                if is_github_pack_product(session, product_id):
                    is_ghpack = True
                stock_count = get_available_stock_count(session, product_id)
                status = "⏸️ Suspend" if product.is_suspended else "✅ Aktif"
                product_name = product.name
                product_price = product.price
                product_desc = product.description or "-"

        if missing_product:
            await _respond(update, "⚠️ Produk tidak ditemukan.", _back_keyboard("adm_cat"))
            return

        if is_ghpack:
            await _send_github_pack_menu(update)
            return

        await _respond(
            update,
            (
                "🧾 <b>Detail Produk</b>\n"
                f"Nama: <b>{html.escape(product_name)}</b>\n"
                f"Harga: <b>{_format_rupiah(product_price)}</b>\n"
                f"Stok Ready: {stock_count}\n"
                f"Status: {status}\n"
                f"Deskripsi: {html.escape(product_desc)}\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("adm_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "gh:add:ready":
        if role != "admin":
            await _respond_admin_only(update)
            return
        _set_flow(context, FLOW_GH_ADD_READY)
        await _respond(
            update,
            (
                "📥 <b>Tambah Stok GitHub Pack (READY)</b>\n"
                "Kirim blok data akun. Satu pesan = satu akun.\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("adm_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "gh:add:await":
        if role != "admin":
            await _respond_admin_only(update)
            return
        with get_session() as session:
            awaiting_hours = get_github_pack_awaiting_hours(session)
        _set_flow(context, FLOW_GH_ADD_AWAIT)
        await _respond(
            update,
            (
                "⏳ <b>Tambah Stok GitHub Pack (AWAITING)</b>\n"
                f"Kirim blok data akun. Stok otomatis pindah ke READY setelah {awaiting_hours} jam.\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("adm_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "gh:add:used":
        if role != "admin":
            await _respond_admin_only(update)
            return
        with get_session() as session:
            used_product = ensure_github_pack_used_product(session)
        _set_flow(context, FLOW_GH_ADD_USED)
        await _respond(
            update,
            (
                "♻️ <b>Tambah Stok GHS Bekas</b>\n"
                f"Produk tujuan: <b>{html.escape(used_product.name)}</b>\n"
                "Kirim blok data akun. Satu pesan = satu akun ready.\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("adm_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "gh:save:menu":
        if role != "admin":
            await _respond_admin_only(update)
            return
        _clear_flow(context)
        await _send_github_saved_account_menu(update)
        return

    if data == "gh:save:add":
        if role != "admin":
            await _respond_admin_only(update)
            return
        _set_flow(context, FLOW_GH_SAVE_ADD)
        await _respond(
            update,
            (
                "➕ <b>Simpan Akun GitHub Fresh</b>\n"
                f"Kirim blok data akun. Akun akan ditahan selama <b>{GITHUB_PACK_SAVE_HOURS} jam</b> sebelum siap diajukan verifikasi.\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("gh_save"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "gh:save:list":
        if role != "admin":
            await _respond_admin_only(update)
            return
        await _send_github_saved_stock_list(update, page=1)
        return

    if data.startswith("gh:save:list:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        try:
            page = int(data.split(":", maxsplit=3)[3])
        except ValueError:
            await _respond(update, "⚠️ Halaman list akun simpan tidak valid.", _github_saved_account_menu_keyboard())
            return
        await _send_github_saved_stock_list(update, page=page)
        return

    if data.startswith("gh:save:view:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        parts = data.split(":", maxsplit=4)
        if len(parts) != 5:
            await _respond(update, "⚠️ Data akun simpan tidak valid.", _github_saved_account_menu_keyboard())
            return
        try:
            stock_id = int(parts[3])
            page = int(parts[4])
        except ValueError:
            await _respond(update, "⚠️ Data akun simpan tidak valid.", _github_saved_account_menu_keyboard())
            return
        await _send_github_saved_stock_detail(
            update,
            stock_id=stock_id,
            source_mode="view",
            page=page,
        )
        return

    if data == "gh:save:move":
        if role != "admin":
            await _respond_admin_only(update)
            return
        await _send_github_saved_move_picker(update, page=1)
        return

    if data.startswith("gh:save:move:list:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        try:
            page = int(data.split(":", maxsplit=4)[4])
        except ValueError:
            await _respond(update, "⚠️ Halaman akun siap pindah tidak valid.", _github_saved_account_menu_keyboard())
            return
        await _send_github_saved_move_picker(update, page=page)
        return

    if data.startswith("gh:save:move:pick:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        parts = data.split(":", maxsplit=5)
        if len(parts) != 6:
            await _respond(update, "⚠️ Data akun simpan tidak valid.", _github_saved_account_menu_keyboard())
            return
        try:
            stock_id = int(parts[4])
            page = int(parts[5])
        except ValueError:
            await _respond(update, "⚠️ Data akun simpan tidak valid.", _github_saved_account_menu_keyboard())
            return
        await _send_github_saved_stock_detail(
            update,
            stock_id=stock_id,
            source_mode="move",
            page=page,
        )
        return

    if data.startswith("gh:save:move:confirm:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        parts = data.split(":", maxsplit=5)
        if len(parts) != 6:
            await _respond(update, "⚠️ Data akun simpan tidak valid.", _github_saved_account_menu_keyboard())
            return
        try:
            stock_id = int(parts[4])
            page = int(parts[5])
        except ValueError:
            await _respond(update, "⚠️ Data akun simpan tidak valid.", _github_saved_account_menu_keyboard())
            return
        await _send_github_saved_stock_detail(
            update,
            stock_id=stock_id,
            source_mode="move",
            page=page,
        )
        return

    if data.startswith("gh:save:move:do:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        parts = data.split(":", maxsplit=5)
        if len(parts) != 6:
            await _respond(update, "⚠️ Data akun simpan tidak valid.", _github_saved_account_menu_keyboard())
            return
        try:
            stock_id = int(parts[4])
            page = int(parts[5])
        except ValueError:
            await _respond(update, "⚠️ Data akun simpan tidak valid.", _github_saved_account_menu_keyboard())
            return

        db_user_ctx = await _require_db_user_ctx()
        with get_session() as session:
            try:
                move_result = move_saved_github_stock_to_awaiting(
                    session,
                    stock_id=stock_id,
                    actor_id=db_user_ctx.id,
                )
            except ValueError as exc:
                await _respond(
                    update,
                    f"⚠️ {exc}",
                    InlineKeyboardMarkup(
                        [
                            [InlineKeyboardButton("⬅️ Kembali", callback_data=f"gh:save:move:list:{max(1, page)}")],
                            [InlineKeyboardButton("⬅️ Kembali ke Simpan Akun", callback_data="gh:save:menu")],
                        ]
                    ),
                )
                return

        await _respond(
            update,
            (
                "✅ <b>Akun berhasil dipindahkan ke Awaiting Benefits</b>\n"
                f"Username: <b>{html.escape(move_result.username)}</b>\n"
                f"ID stok: <b>#{move_result.stock_id}</b>\n"
                f"Durasi awaiting: <b>{move_result.awaiting_hours} jam</b>\n"
                f"Estimasi ready: <b>{html.escape(_format_display_day_time(move_result.awaiting_ready_at))}</b>"
            ),
            InlineKeyboardMarkup(
                [
                    [InlineKeyboardButton("⏳ Lihat List Siap Pindah", callback_data=f"gh:save:move:list:{max(1, page)}")],
                    [InlineKeyboardButton("⬅️ Kembali ke Simpan Akun", callback_data="gh:save:menu")],
                ]
            ),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "gh:list":
        if role != "admin":
            await _respond_admin_only(update)
            return
        await _send_github_stock_list(update, page=1)
        return

    if data.startswith("gh:list:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        try:
            page = int(data.split(":", maxsplit=2)[2])
        except ValueError:
            await _respond(update, "⚠️ Halaman list akun tidak valid.", _github_pack_menu_keyboard())
            return
        await _send_github_stock_list(update, page=page)
        return

    if data == "gh:sold:list":
        if role != "admin":
            await _respond_admin_only(update)
            return
        await _send_github_sold_stock_picker(update, page=1)
        return

    if data.startswith("gh:sold:list:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        try:
            page = int(data.split(":", maxsplit=3)[3])
        except ValueError:
            await _respond(update, "⚠️ Halaman akun terjual tidak valid.", _github_pack_menu_keyboard())
            return
        await _send_github_sold_stock_picker(update, page=page)
        return

    if data.startswith("gh:sold:view:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        parts = data.split(":", maxsplit=4)
        if len(parts) != 5:
            await _respond(update, "⚠️ Data akun terjual tidak valid.", _github_pack_menu_keyboard())
            return
        try:
            stock_id = int(parts[3])
            page = int(parts[4])
        except ValueError:
            await _respond(update, "⚠️ Data akun terjual tidak valid.", _github_pack_menu_keyboard())
            return
        await _send_github_sold_stock_detail(update, stock_id=stock_id, page=page)
        return

    if data.startswith("gh:sold:move:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        parts = data.split(":", maxsplit=4)
        if len(parts) != 5:
            await _respond(update, "⚠️ Data pemindahan akun tidak valid.", _github_pack_menu_keyboard())
            return
        try:
            sold_stock_id = int(parts[3])
            source_page = int(parts[4])
        except ValueError:
            await _respond(update, "⚠️ Data pemindahan akun tidak valid.", _github_pack_menu_keyboard())
            return

        db_user_ctx = await _require_db_user_ctx()
        with get_session() as session:
            try:
                move_result = move_sold_github_stock_to_used_product(
                    session=session,
                    sold_stock_id=sold_stock_id,
                    actor_id=db_user_ctx.id,
                )
            except ValueError as exc:
                await _respond(update, f"❌ {exc}", _github_pack_menu_keyboard())
                return

        await _respond(
            update,
            (
                "✅ <b>Akun berhasil dipindahkan ke GHS Bekas</b>\n"
                f"Username: <b>{html.escape(move_result.source_username)}</b>\n"
                f"Produk tujuan: <b>{html.escape(move_result.used_product_name)}</b>\n"
                f"ID stok baru: <b>{move_result.used_stock_id}</b>"
            ),
            InlineKeyboardMarkup(
                [
                    [InlineKeyboardButton("🧾 Kembali ke Akun Terjual", callback_data=f"gh:sold:list:{max(1, source_page)}")],
                    [InlineKeyboardButton("🛍️ Lihat Produk GHS Bekas", callback_data=f"acp:{move_result.used_product_id}")],
                    [InlineKeyboardButton("⬅️ Kembali", callback_data="ac:ghpack")],
                ]
            ),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "gh:price:set":
        if role != "admin":
            await _respond_admin_only(update)
            return

        with get_session() as session:
            product = ensure_github_pack_product(session)
            current_price = product.price

        _set_flow(context, FLOW_GH_SET_PRICE)
        await _respond(
            update,
            (
                "💰 <b>Atur Harga GitHub Pack</b>\n"
                f"Harga saat ini: <b>{_format_rupiah(current_price)}</b>\n\n"
                "Kirim angka harga baru (contoh: <code>35000</code>)."
            ),
            _back_keyboard("adm_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "gh:price:used:set":
        if role != "admin":
            await _respond_admin_only(update)
            return

        with get_session() as session:
            used_product = ensure_github_pack_used_product(session)
            current_price = used_product.price

        _set_flow(context, FLOW_GH_SET_USED_PRICE)
        await _respond(
            update,
            (
                "♻️ <b>Atur Harga GHS Bekas</b>\n"
                f"Produk: <b>{html.escape(used_product.name)}</b>\n"
                f"Harga saat ini: <b>{_format_rupiah(current_price)}</b>\n\n"
                "Kirim angka harga baru (contoh: <code>20000</code>)."
            ),
            _back_keyboard("adm_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "gh:await:set":
        if role != "admin":
            await _respond_admin_only(update)
            return

        with get_session() as session:
            current_hours = get_github_pack_awaiting_hours(session)

        _set_flow(context, FLOW_GH_SET_AWAITING_HOURS)
        await _respond(
            update,
            (
                "⏱️ <b>Atur Jam Awaiting Benefits</b>\n"
                f"Durasi saat ini: <b>{current_hours} jam</b>\n\n"
                "Kirim angka durasi baru dalam jam (contoh: <code>84</code>).\n"
                "Rentang yang diizinkan: <b>1 - 720</b> jam."
            ),
            _back_keyboard("adm_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "gh:view:list":
        if role != "admin":
            await _respond_admin_only(update)
            return
        await _send_github_stock_picker(update, mode="view", page=1)
        return

    if data == "gh:del:list":
        if role != "admin":
            await _respond_admin_only(update)
            return
        await _send_github_stock_picker(update, mode="delete", page=1)
        return

    if data.startswith("gh:view:page:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        try:
            page = int(data.split(":", maxsplit=3)[3])
        except ValueError:
            await _respond(update, "⚠️ Halaman detail akun tidak valid.", _github_pack_menu_keyboard())
            return
        await _send_github_stock_picker(update, mode="view", page=page)
        return

    if data.startswith("gh:del:page:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        try:
            page = int(data.split(":", maxsplit=3)[3])
        except ValueError:
            await _respond(update, "⚠️ Halaman hapus akun tidak valid.", _github_pack_menu_keyboard())
            return
        await _send_github_stock_picker(update, mode="delete", page=page)
        return

    if data.startswith("gh:view:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        try:
            stock_id = int(data.split(":", maxsplit=2)[2])
        except ValueError:
            await _respond(update, "⚠️ ID stok tidak valid.", _github_pack_menu_keyboard())
            return

        with get_session() as session:
            detail = get_github_stock_detail(session, stock_id)

        if detail is None:
            await _respond(update, "⚠️ Stok tidak ditemukan.", _github_pack_menu_keyboard())
            return

        ready_at_line = ""
        if detail.status == "awaiting_benefits" and detail.available_at is not None:
            ready_at_line = f"Ready at: <b>{html.escape(_format_display_day_time(detail.available_at))}</b>\n"

        await _respond(
            update,
            (
                f"👤 Username: <b>{html.escape(detail.username)}</b>\n"
                f"Status: {_stock_status_badge(detail.status)}\n"
                f"ID Stok: <b>{detail.id}</b>\n\n"
                f"{ready_at_line}"
                f"<pre>{html.escape(detail.raw_text)}</pre>\n\n"
                f"{_admin_footer_text()}"
            ),
            _github_pack_menu_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    if data.startswith("gh:del:"):
        if role != "admin":
            await _respond_admin_only(update)
            return
        try:
            stock_id = int(data.split(":", maxsplit=2)[2])
        except ValueError:
            await _respond(update, "⚠️ ID stok tidak valid.", _github_pack_menu_keyboard())
            return

        db_user_ctx = await _require_db_user_ctx()
        with get_session() as session:
            try:
                delete_github_stock(session, stock_id=stock_id, actor_id=db_user_ctx.id)
            except ValueError as exc:
                await _respond(update, f"❌ {exc}", _github_pack_menu_keyboard())
                return

        await _respond(update, "✅ Stok akun berhasil dihapus.", _github_pack_menu_keyboard())
        return

    if data == "ac:add":
        _set_flow(context, FLOW_ADMIN_ADD_PRODUCT)
        await _respond(
            update,
            (
                "➕ <b>Upsert Produk</b>\n"
                "Kirim data dengan format:\n"
                "<code>Nama|Harga|Deskripsi</code>\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("adm_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "ac:stock":
        await _send_admin_product_picker(update, action="stk", title=ADMIN_PRODUCT_PICKER_TITLES["stk"], page=1)
        return

    if data == "ac:susp":
        await _send_admin_product_picker(update, action="sup", title=ADMIN_PRODUCT_PICKER_TITLES["sup"], page=1)
        return

    if data == "ac:uns":
        await _send_admin_product_picker(update, action="uns", title=ADMIN_PRODUCT_PICKER_TITLES["uns"], page=1)
        return

    if data == "ac:del":
        await _send_admin_product_picker(update, action="del", title=ADMIN_PRODUCT_PICKER_TITLES["del"], page=1)
        return

    if data.startswith("apl:"):
        if role != "admin":
            await _respond_admin_only(update)
            return

        parts = data.split(":", maxsplit=2)
        if len(parts) != 3:
            await _respond(update, "⚠️ Halaman picker produk tidak valid.", _back_keyboard("adm_cat"))
            return

        action = parts[1]
        title = ADMIN_PRODUCT_PICKER_TITLES.get(action)
        if title is None:
            await _respond(update, "⚠️ Aksi picker produk tidak valid.", _back_keyboard("adm_cat"))
            return

        try:
            page = int(parts[2])
        except ValueError:
            await _respond(update, "⚠️ Halaman picker produk tidak valid.", _back_keyboard("adm_cat"))
            return

        await _send_admin_product_picker(update, action=action, title=title, page=page)
        return

    if data.startswith("ap:"):
        if role != "admin":
            await _respond_admin_only(update)
            return

        parts = data.split(":")
        if len(parts) != 3:
            await _respond(update, "⚠️ Aksi produk tidak valid.", _back_keyboard("adm_cat"))
            return

        action = parts[1]
        try:
            product_id = int(parts[2])
        except ValueError:
            await _respond(update, "⚠️ ID produk tidak valid.", _back_keyboard("adm_cat"))
            return

        if action == "stk":
            _set_flow(context, FLOW_ADMIN_ADD_STOCK, product_id=product_id)
            await _respond(
                update,
                (
                    f"📥 <b>Tambah Stok Produk #{product_id}</b>\n"
                    "Kirim blok data stok. Satu pesan = satu unit stok.\n\n"
                    f"{_admin_footer_text()}"
                ),
                _back_keyboard("adm_cat"),
                parse_mode=ParseMode.HTML,
            )
            return

        db_user_ctx = await _require_db_user_ctx()
        with get_session() as session:
            try:
                if action == "sup":
                    suspend_product(session, product_id=product_id, suspended=True, actor_id=db_user_ctx.id)
                    await _respond(update, f"⏸️ Produk #{product_id} berhasil disuspend.", _back_keyboard("adm_cat"))
                elif action == "uns":
                    suspend_product(session, product_id=product_id, suspended=False, actor_id=db_user_ctx.id)
                    await _respond(update, f"▶️ Produk #{product_id} berhasil diaktifkan.", _back_keyboard("adm_cat"))
                elif action == "del":
                    delete_product(session, product_id=product_id, actor_id=db_user_ctx.id)
                    await _respond(update, f"🗑️ Produk #{product_id} berhasil dihapus.", _back_keyboard("adm_cat"))
                else:
                    await _respond(update, "⚠️ Aksi produk tidak dikenali.", _back_keyboard("adm_cat"))
            except ValueError as exc:
                await _respond(update, f"❌ {exc}", _back_keyboard("adm_cat"))
        return

    if data.startswith("cp:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return

        try:
            product_id = int(data.split(":", maxsplit=1)[1])
        except ValueError:
            await _respond(update, "⚠️ Data produk tidak valid. Pilih ulang produk dari katalog.", _back_keyboard("cus_cat"))
            return

        await _send_product_detail(update, product_id)
        return

    if data.startswith("restock:sub:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return

        try:
            product_id = int(data.split(":", maxsplit=2)[2])
        except ValueError:
            await _respond(update, "⚠️ Produk tidak valid untuk notifikasi restock.", _back_keyboard("cus_cat"))
            return

        db_user_ctx = await _require_db_user_ctx()
        with get_session() as session:
            product = get_product(session, product_id)
            if product is None:
                await _respond(update, "⚠️ Produk tidak ditemukan.", _back_keyboard("cus_cat"))
                return

            created, message = subscribe_restock(
                session,
                customer_id=db_user_ctx.id,
                product_id=product_id,
            )

        title = "🔔 <b>Notifikasi Restock</b>"
        details = f"Produk: <b>{html.escape(product.name)}</b>"
        icon = "✅" if created else "ℹ️"
        await _respond(
            update,
            f"{title}\n{details}\n{icon} {html.escape(message)}\n\n{_customer_footer_text()}",
            _back_keyboard("cus_cat"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data.startswith("qty:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return

        parts = data.split(":")
        if len(parts) != 4:
            await _respond(update, "⚠️ Aksi jumlah tidak valid. Buka lagi detail produk.", _back_keyboard("cus_cat"))
            return

        action = parts[1]
        try:
            product_id = int(parts[2])
            current_qty = int(parts[3])
        except ValueError:
            await _respond(update, "⚠️ Aksi jumlah tidak valid. Buka lagi detail produk.", _back_keyboard("cus_cat"))
            return

        if action == "inc":
            current_qty += 1
        elif action == "dec":
            current_qty -= 1

        await _send_product_detail(update, product_id, qty=current_qty)
        return

    if data.startswith("buy:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return

        if not _acquire_checkout_lock(context):
            if query.message is not None:
                await query.message.reply_text("⏳ Checkout sebelumnya masih diproses. Mohon tunggu sebentar.")
            return

        loading_message = await _send_checkout_loading(update)
        checkout_ok = False

        parts = data.split(":")
        if len(parts) != 3:
            await _respond(update, "⚠️ Data checkout tidak valid. Silakan pilih ulang dari detail produk.", _back_keyboard("cus_cat"))
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)
            return

        try:
            product_id = int(parts[1])
            qty = int(parts[2])
        except ValueError:
            await _respond(update, "⚠️ Data checkout tidak valid. Silakan pilih ulang dari detail produk.", _back_keyboard("cus_cat"))
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)
            return

        message_id = int(query.message.message_id) if query.message is not None else 0
        action_signature = f"buy:{product_id}:{qty}:{message_id}"
        if _is_duplicate_checkout_action(context, action_signature):
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)
            await query.answer("Permintaan checkout ini sudah diproses.", show_alert=False)
            return

        try:
            checkout_ok = await _send_checkout_result(update, telegram_id, product_id, qty)
        finally:
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)

        if checkout_ok:
            await _try_delete_callback_message(update)
        return

    if data.startswith("buyall:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return

        if not _acquire_checkout_lock(context):
            await query.answer("⏳ Checkout sebelumnya masih diproses.", show_alert=False)
            return

        loading_message = await _send_checkout_loading(update)
        checkout_ok = False

        try:
            product_id = int(data.split(":", maxsplit=1)[1])
        except ValueError:
            await _respond(update, "⚠️ Data checkout tidak valid. Silakan pilih ulang dari detail produk.", _back_keyboard("cus_cat"))
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)
            return

        with get_session() as session:
            product = get_product(session, product_id)
            if product is None or product.is_suspended:
                await _respond(update, "⚠️ Produk tidak tersedia.", _back_keyboard("cus_cat"))
                _release_checkout_lock(context)
                await _try_delete_message(loading_message)
                return
            qty = get_available_stock_count(session, product_id)

        if qty <= 0:
            await _respond(update, "⚠️ Stok produk sudah habis.", _back_keyboard("cus_cat"))
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)
            return

        message_id = int(query.message.message_id) if query.message is not None else 0
        action_signature = f"buyall:{product_id}:{qty}:{message_id}"
        if _is_duplicate_checkout_action(context, action_signature):
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)
            await query.answer("Permintaan checkout ini sudah diproses.", show_alert=False)
            return

        try:
            checkout_ok = await _send_checkout_result(update, telegram_id, product_id, qty)
        finally:
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)

        if checkout_ok:
            await _try_delete_callback_message(update)
        return

    if data.startswith("buyq:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return

        try:
            product_id = int(data.split(":", maxsplit=1)[1])
        except ValueError:
            await _respond(update, "⚠️ Data produk tidak valid. Pilih ulang produk dari katalog.", _back_keyboard("cus_cat"))
            return

        _set_flow(context, FLOW_CUSTOMER_MANUAL_QTY, product_id=product_id)
        await _respond(
            update,
            "✍️ Masukkan jumlah pembelian (angka).",
            _back_keyboard("cus_cat"),
        )
        return

    if data.startswith("ord:page:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return
        try:
            page = int(data.split(":", maxsplit=2)[2])
        except ValueError:
            await _respond(update, "⚠️ Halaman pesanan tidak valid. Buka lagi menu Pesanan Saya.", _back_keyboard("main"))
            return

        await _send_customer_orders(update, telegram_id, page=page)
        return

    if data.startswith("ord:view:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return
        parts = data.split(":", maxsplit=3)
        if len(parts) != 4:
            await _respond(update, "⚠️ Data pesanan tidak valid. Buka lagi menu Pesanan Saya.", _back_keyboard("main"))
            return

        order_ref = parts[2]
        try:
            page = int(parts[3])
        except ValueError:
            page = 1

        await _send_customer_order_detail(update, telegram_id, order_ref=order_ref, page=page)
        return

    if data.startswith("ord:copy:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return
        parts = data.split(":", maxsplit=3)
        if len(parts) != 4:
            await _respond(update, "⚠️ Data pesanan tidak valid. Buka lagi menu Pesanan Saya.", _back_keyboard("main"))
            return

        order_ref = parts[2]
        try:
            page = int(parts[3])
        except ValueError:
            page = 1

        await _send_customer_order_copy(update, telegram_id, order_ref=order_ref, page=page)
        return

    if data.startswith("ord:reorder:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return

        if not _acquire_checkout_lock(context):
            await query.answer("⏳ Checkout sebelumnya masih diproses.", show_alert=False)
            return

        loading_message = await _send_checkout_loading(update)
        checkout_ok = False

        parts = data.split(":", maxsplit=3)
        if len(parts) != 4:
            await _respond(update, "⚠️ Data quick reorder tidak valid.", _back_keyboard("main"))
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)
            return

        order_ref = parts[2]
        message_id = int(query.message.message_id) if query.message is not None else 0
        action_signature = f"reorder:{order_ref}:{message_id}"
        if _is_duplicate_checkout_action(context, action_signature):
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)
            await query.answer("Permintaan checkout ini sudah diproses.", show_alert=False)
            return

        try:
            checkout_ok = await _send_quick_reorder_result(update, telegram_id, order_ref)
        finally:
            _release_checkout_lock(context)
            await _try_delete_message(loading_message)

        if checkout_ok:
            await _try_delete_callback_message(update)
        return

    if data.startswith("ord:cancel:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return
        order_ref = data.split(":", maxsplit=2)[2]

        db_user_ctx = await _require_db_user_ctx()
        with get_session() as session:
            cancel_result = cancel_order(session, order_ref=order_ref, customer_id=db_user_ctx.id)

        if cancel_result.ok:
            await _upsert_admin_order_notification(update, order_ref)

        keyboard = _back_keyboard("cus_cat") if cancel_result.ok else _back_keyboard("main")
        await _respond(update, cancel_result.message, keyboard)
        return

    if data == "pay:upload":
        if role != "admin":
            await _respond_admin_only(update)
            return
        _clear_flow(context)
        context.user_data[AWAIT_QRIS_IMAGE_KEY] = True
        await _respond(
            update,
            (
                "🖼️ <b>Upload QRIS</b>\n"
                "Kirim gambar QRIS sekarang dalam format foto.\n"
                "Payload akan dicoba diekstrak otomatis dari gambar.\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("pay"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "pay:payload:set":
        if role != "admin":
            await _respond_admin_only(update)
            return
        context.user_data[AWAIT_QRIS_IMAGE_KEY] = False
        _set_flow(context, FLOW_PAY_SET_QRIS_PAYLOAD)
        await _respond(
            update,
            (
                "🧾 <b>Set Payload QRIS Statis</b>\n"
                "Kirim payload EMV QRIS dalam 1 pesan (boleh mengandung spasi/baris baru, akan dibersihkan otomatis).\n"
                "Ketik <code>hapus</code> untuk mengosongkan payload.\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("pay"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "pay:status":
        if role != "admin":
            await _respond_admin_only(update)
            return
        context.user_data[AWAIT_QRIS_IMAGE_KEY] = False

        with get_session() as session:
            payload = get_qris_static_payload(session)

        has_qris_image = settings.qris_file_path.exists()
        dynamic_ready = False
        dynamic_error = ""

        if settings.qris_dynamic_enabled and payload:
            try:
                build_dynamic_qris_payload(payload, amount=1000)
                dynamic_ready = True
            except Exception as exc:
                dynamic_error = str(exc)

        await _respond(
            update,
            _build_payment_status_text(
                dynamic_enabled=settings.qris_dynamic_enabled,
                has_qris_image=has_qris_image,
                payload=payload,
                dynamic_ready=dynamic_ready,
                dynamic_error=dynamic_error,
            ),
            _back_keyboard("pay"),
            parse_mode=ParseMode.HTML,
        )

        if has_qris_image:
            if query.message:
                try:
                    with settings.qris_file_path.open("rb") as fh:
                        await query.message.reply_photo(photo=fh, caption="🧾 Preview QRIS", reply_markup=_back_keyboard("pay"))
                except Exception as exc:
                    logger.warning("Gagal kirim preview QRIS: %s", exc)
        return

    if data == "up:check":
        if role != "admin":
            await _respond_admin_only(update)
            return
        check_code, check_output = await _run_update_script_with_code("check")
        check_message, _ = _build_update_check_user_message(check_code, check_output)
        await _respond(
            update,
            check_message,
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "up:apply":
        if role != "admin":
            await _respond_admin_only(update)
            return
        apply_code, apply_output = await _run_update_script_with_code("update")
        apply_message = _build_update_apply_user_message(apply_code, apply_output)
        await _respond(
            update,
            apply_message,
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    await _respond(update, "⚠️ Tombol tidak dikenali. Silakan kembali ke menu utama.", _back_keyboard("main"))


async def text_router(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None or update.message.text is None:
        return

    role = "customer"
    try:
        db_user, role = await _ensure_user(update, context)
        text = update.message.text.strip()
        flow, flow_data = _get_flow(context)

        if _is_back_text(text):
            _clear_flow(context)
            context.user_data.pop(AWAIT_QRIS_IMAGE_KEY, None)
            _clear_complaint_draft(context)
            await _send_main_menu(update, role, context=context)
            return

        if flow == FLOW_CUSTOMER_COMPLAINT_COMPOSE:
            if role == "admin":
                await _respond(update, "🚫 Admin tidak bisa membuat komplain customer.", _back_keyboard("main"))
                _clear_flow(context)
                _clear_complaint_draft(context)
                return

            normalized = text.strip().lower()
            if normalized in {"kirim", "submit", "selesai", "enter"}:
                db_user_ctx = await _require_db_user_ctx()
                await _submit_customer_complaint_from_draft(update, context, db_user_ctx)
                return

            _append_complaint_text(context, text)
            draft = _get_complaint_draft(context)
            order_ref = str(flow_data.get("order_ref") or draft.get("order_ref") or "").strip().upper()
            source_page = int(flow_data.get("source_page") or draft.get("source_page") or 1)
            total_photos = len([str(x).strip() for x in list(draft.get("photo_file_ids") or []) if str(x).strip()])
            await _respond(
                update,
                (
                    "✅ Pesan keluhan sudah disimpan ke draft komplain.\n"
                    f"Lampiran saat ini: <b>{total_photos}</b> file\n"
                    "Kamu bisa kirim tambahan foto/pesan lagi, lalu tekan Kirim Komplain saat sudah final."
                ),
                _build_complaint_compose_keyboard(order_ref, max(1, source_page)),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow == FLOW_CUSTOMER_REFUND_DETAIL:
            if role == "admin":
                await _respond(update, "🚫 Admin tidak bisa mengisi detail refund customer.", _back_keyboard("main"))
                _clear_flow(context)
                return

            complaint_id_raw = flow_data.get("complaint_id")
            try:
                complaint_id = int(str(complaint_id_raw))
            except ValueError:
                _clear_flow(context)
                await _respond(update, "⚠️ Data komplain refund tidak valid.", _back_keyboard("main"))
                return

            db_user_ctx = await _require_db_user_ctx()
            with get_session() as session:
                try:
                    detail = set_complaint_refund_target_from_customer(
                        session,
                        complaint_id=complaint_id,
                        customer_id=db_user_ctx.id,
                        detail_text=text,
                    )
                except ValueError as exc:
                    await _respond(update, f"❌ {exc}", _back_keyboard("main"))
                    return

            _clear_flow(context)
            await _respond(
                update,
                (
                    "✅ <b>Detail refund berhasil dikirim ke admin</b>\n"
                    f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>"
                ),
                _back_keyboard("main"),
                parse_mode=ParseMode.HTML,
            )

            admin_id = get_primary_admin_id(settings.role_file_path)
            if admin_id is not None:
                try:
                    await context.bot.send_message(
                        chat_id=admin_id,
                        text=(
                            "💳 <b>Detail Refund Diterima</b>\n"
                            f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>\n"
                            f"Nomor order: <code>{html.escape(detail.order_ref)}</code>\n"
                            f"Detail rekening/e-wallet:\n<pre>{html.escape(detail.refund_target_detail or '-')}</pre>"
                        ),
                        parse_mode=ParseMode.HTML,
                        reply_markup=InlineKeyboardMarkup(
                            [[InlineKeyboardButton("💸 Kirim Dana Refund", callback_data=f"cmp:admin:proc:pay:{detail.complaint_id}:1")]]
                        ),
                    )
                except Exception as exc:
                    logger.warning("Gagal kirim detail refund customer ke admin: %s", exc)
            return

        if flow == FLOW_ADMIN_REFUND_PROOF:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            await _respond(
                update,
                "⚠️ Untuk menyelesaikan refund, kirim screenshot bukti transfer sebagai foto. Catatan opsional di caption.",
                _back_keyboard("main"),
            )
            return

        if flow == FLOW_ADMIN_ADD_PRODUCT:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            parsed = _parse_product_upsert_input(text)
            if parsed is None:
                await _respond(
                    update,
                    (
                        "⚠️ <b>Format tidak valid</b>\n"
                        "Gunakan format: <code>Nama|Harga|Deskripsi</code>\n\n"
                        f"{_admin_footer_text()}"
                    ),
                    _back_keyboard("adm_cat"),
                    parse_mode=ParseMode.HTML,
                )
                return

            name, price, description = parsed
            with get_session() as session:
                product = add_product(session, name=name, price=price, description=description, actor_id=db_user.id)
                product_id = int(product.id)

            _clear_flow(context)
            await _respond(
                update,
                (
                    f"✅ <b>Produk berhasil disimpan</b>\n"
                    f"ID Produk: <b>#{product_id}</b>\n\n"
                    f"{_admin_footer_text()}"
                ),
                _back_keyboard("adm_cat"),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow == FLOW_ADMIN_ADD_STOCK:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            product_id_raw = flow_data.get("product_id")
            try:
                product_id = int(str(product_id_raw))
            except ValueError:
                _clear_flow(context)
                await _respond(update, "⚠️ Data produk tidak valid.", _back_keyboard("adm_cat"))
                return

            with get_session() as session:
                try:
                    stock = add_stock_block(
                        session,
                        product_id=product_id,
                        raw_text=text,
                        actor_id=db_user.id,
                    )
                    stock_id = int(stock.id)
                except ValueError as exc:
                    await _respond(update, f"❌ Gagal menambah stok: {exc}", _back_keyboard("adm_cat"))
                    return

            _clear_flow(context)
            await _respond(
                update,
                (
                    f"✅ <b>Stok berhasil ditambahkan</b>\n"
                    f"ID Stok: <b>{stock_id}</b>\n\n"
                    f"{_admin_footer_text()}"
                ),
                _back_keyboard("adm_cat"),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow == FLOW_GH_ADD_USED:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            with get_session() as session:
                used_product = ensure_github_pack_used_product(session)
                try:
                    stock = add_stock_block(
                        session,
                        product_id=int(used_product.id),
                        raw_text=text,
                        actor_id=db_user.id,
                    )
                except ValueError as exc:
                    await _respond(update, f"❌ Gagal tambah stok GHS Bekas: {exc}", _github_pack_menu_keyboard())
                    return

            _clear_flow(context)
            await _respond(
                update,
                (
                    "✅ <b>Stok GHS Bekas berhasil ditambahkan</b>\n"
                    f"Produk: <b>{html.escape(used_product.name)}</b>\n"
                    f"ID Stok: <b>{int(stock.id)}</b>\n\n"
                    f"{_admin_footer_text()}"
                ),
                _github_pack_menu_keyboard(),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow in {FLOW_GH_ADD_READY, FLOW_GH_ADD_AWAIT}:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            awaiting = flow == FLOW_GH_ADD_AWAIT
            with get_session() as session:
                try:
                    stock = add_github_stock(
                        session=session,
                        raw_text=text,
                        actor_id=db_user.id,
                        awaiting=awaiting,
                    )
                except ValueError as exc:
                    await _respond(update, f"❌ Gagal tambah stok GitHub Pack: {exc}", _github_pack_menu_keyboard())
                    return

            _clear_flow(context)
            status_text = "AWAITING BENEFITS" if awaiting else "READY"
            awaiting_info_line = ""
            if awaiting and stock.available_at is not None:
                awaiting_info_line = f"Estimasi ready: <b>{html.escape(_format_display_day_time(stock.available_at))}</b>\n"
            await _respond(
                update,
                (
                    "✅ <b>Stok GitHub Pack berhasil ditambahkan</b>\n"
                    f"ID: <b>{stock.id}</b>\n"
                    f"Username: <b>{html.escape(stock.username)}</b>\n"
                    f"Status: <b>{status_text}</b>\n"
                    f"{awaiting_info_line}\n"
                    f"{_admin_footer_text()}"
                ),
                _github_pack_menu_keyboard(),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow == FLOW_GH_SAVE_ADD:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            with get_session() as session:
                try:
                    saved_stock = add_saved_github_stock(
                        session=session,
                        raw_text=text,
                        actor_id=db_user.id,
                    )
                except ValueError as exc:
                    await _respond(update, f"❌ Gagal simpan akun GitHub: {exc}", _github_saved_account_menu_keyboard())
                    return

            _clear_flow(context)
            await _respond(
                update,
                (
                    "✅ <b>Akun berhasil disimpan</b>\n"
                    f"ID: <b>#{saved_stock.stock_id}</b>\n"
                    f"Username: <b>{html.escape(saved_stock.username)}</b>\n"
                    f"Siap diajukan verifikasi: <b>{html.escape(_format_display_day_time(saved_stock.ready_at))}</b>\n"
                    f"Estimasi sisa: <b>{_format_remaining_compact(saved_stock.ready_at)}</b>"
                ),
                _github_saved_account_menu_keyboard(),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow == FLOW_GH_SET_PRICE:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            normalized = (
                text.lower()
                .replace("rp", "")
                .replace(".", "")
                .replace(",", "")
                .strip()
            )
            try:
                new_price = int(normalized)
            except ValueError:
                await _respond(
                    update,
                    "⚠️ Format harga tidak valid. Kirim angka saja, contoh: 35000",
                    _back_keyboard("adm_cat"),
                )
                return

            with get_session() as session:
                try:
                    product = set_github_pack_price(session, new_price=new_price, actor_id=db_user.id)
                except ValueError as exc:
                    await _respond(update, f"❌ {exc}", _back_keyboard("adm_cat"))
                    return

            _clear_flow(context)
            await _respond(
                update,
                (
                    f"✅ Harga <b>{html.escape(product.name)}</b> berhasil diperbarui.\n"
                    f"Harga baru: <b>{_format_rupiah(product.price)}</b>"
                ),
                _github_pack_menu_keyboard(),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow == FLOW_GH_SET_USED_PRICE:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            normalized = (
                text.lower()
                .replace("rp", "")
                .replace(".", "")
                .replace(",", "")
                .strip()
            )
            try:
                new_price = int(normalized)
            except ValueError:
                await _respond(
                    update,
                    "⚠️ Format harga tidak valid. Kirim angka saja, contoh: 20000",
                    _back_keyboard("adm_cat"),
                )
                return

            with get_session() as session:
                try:
                    used_product = set_github_pack_used_price(
                        session,
                        new_price=new_price,
                        actor_id=db_user.id,
                    )
                except ValueError as exc:
                    await _respond(update, f"❌ {exc}", _back_keyboard("adm_cat"))
                    return

            _clear_flow(context)
            await _respond(
                update,
                (
                    f"✅ Harga <b>{html.escape(used_product.name)}</b> berhasil diperbarui.\n"
                    f"Harga baru: <b>{_format_rupiah(used_product.price)}</b>"
                ),
                _github_pack_menu_keyboard(),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow == FLOW_GH_SET_AWAITING_HOURS:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            normalized = text.lower().replace("jam", "").strip()
            try:
                new_hours = int(normalized)
            except ValueError:
                await _respond(
                    update,
                    "⚠️ Format jam tidak valid. Kirim angka saja, contoh: 84",
                    _back_keyboard("adm_cat"),
                )
                return

            with get_session() as session:
                try:
                    update_result = set_github_pack_awaiting_hours(
                        session,
                        hours=new_hours,
                        actor_id=db_user.id,
                    )
                except ValueError as exc:
                    await _respond(update, f"❌ {exc}", _back_keyboard("adm_cat"))
                    return

            _clear_flow(context)
            delta_hours = update_result.delta_hours
            if delta_hours > 0:
                delta_text = f"+{delta_hours} jam"
            elif delta_hours < 0:
                delta_text = f"{delta_hours} jam"
            else:
                delta_text = "0 jam"

            await _respond(
                update,
                (
                    "✅ <b>Durasi awaiting berhasil diperbarui</b>\n"
                    f"Nilai lama: <b>{update_result.old_hours} jam</b>\n"
                    f"Nilai baru: <b>{update_result.new_hours} jam</b>\n"
                    f"Delta diterapkan: <b>{delta_text}</b>\n"
                    "Stok awaiting lama disesuaikan: "
                    f"<b>{update_result.adjusted_stock_count} akun</b>."
                ),
                _github_pack_menu_keyboard(),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow == FLOW_PAY_SET_QRIS_PAYLOAD:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            command = text.strip().lower()
            with get_session() as session:
                if command in {"hapus", "clear", "kosong"}:
                    clear_qris_static_payload(session, actor_id=db_user.id)
                    _clear_flow(context)
                    await _respond(
                        update,
                        (
                            "✅ <b>Payload QRIS berhasil dihapus</b>\n"
                            f"{_admin_footer_text()}"
                        ),
                        _back_keyboard("pay"),
                        parse_mode=ParseMode.HTML,
                    )
                    return

                try:
                    saved_payload = set_qris_static_payload(session, payload=text, actor_id=db_user.id)
                    sample_payload = build_dynamic_qris_payload(saved_payload, amount=1000)
                except ValueError as exc:
                    await _respond(
                        update,
                        (
                            "⚠️ <b>Payload QRIS tidak valid</b>\n"
                            f"{html.escape(str(exc))}\n\n"
                            "Kirim ulang payload EMV lengkap atau ketik <code>hapus</code>."
                        ),
                        _back_keyboard("pay"),
                        parse_mode=ParseMode.HTML,
                    )
                    return

            _clear_flow(context)
            await _respond(
                update,
                (
                    "✅ <b>Payload QRIS tersimpan</b>\n"
                    f"Panjang payload: <b>{len(saved_payload)}</b> karakter\n"
                    f"Payload tersimpan: <code>{_mask_qris_payload(saved_payload)}</code>\n"
                    f"Contoh dinamis nominal 1000: <code>{_mask_qris_payload(sample_payload)}</code>\n\n"
                    f"{_admin_footer_text()}"
                ),
                _back_keyboard("pay"),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow == FLOW_ADMIN_BROADCAST:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            if len(text) > 4096:
                await _respond(
                    update,
                    "⚠️ Pesan broadcast terlalu panjang. Maksimal 4096 karakter untuk mode teks.",
                    _back_keyboard("main"),
                )
                return

            sent, failed, total = await _run_admin_broadcast_with_progress(
                update=update,
                context=context,
                admin_user_id=db_user.id,
                message_text=text,
            )

            _clear_flow(context)
            await _respond(
                update,
                (
                    "📢 <b>Broadcast selesai</b>\n"
                    "Mode: <b>Teks</b>\n"
                    f"Total customer: <b>{total}</b>\n"
                    f"✅ Sent: <b>{sent}</b>\n"
                    f"❌ Failed: <b>{failed}</b>\n\n"
                    f"{_admin_footer_text()}"
                ),
                _back_keyboard("main"),
                parse_mode=ParseMode.HTML,
            )
            return

        if flow == FLOW_CUSTOMER_MANUAL_QTY:
            if role == "admin":
                _clear_flow(context)
                await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
                return

            try:
                qty = int(text)
            except ValueError:
                await _respond(update, "⚠️ Jumlah harus berupa angka, contoh: 2", _back_keyboard("cus_cat"))
                return

            product_id_raw = flow_data.get("product_id")
            try:
                product_id = int(str(product_id_raw))
            except ValueError:
                _clear_flow(context)
                await _respond(update, "⚠️ Data produk tidak valid. Pilih ulang produk dari katalog.", _back_keyboard("cus_cat"))
                return

            _clear_flow(context)
            await _send_checkout_result(update, db_user.telegram_id, product_id, qty)
            return

        # Fallback: admin can directly send upsert format even if flow state was lost.
        if role == "admin":
            parsed = _parse_product_upsert_input(text)
            if parsed is not None:
                name, price, description = parsed
                with get_session() as session:
                    product = add_product(session, name=name, price=price, description=description, actor_id=db_user.id)
                    product_id = int(product.id)

                _clear_flow(context)
                await _respond(
                    update,
                    f"✅ Produk tersimpan (upsert fallback) dengan ID #{product_id}.",
                    _back_keyboard("adm_cat"),
                )
                return

        await _respond(
            update,
            "👉 Silakan gunakan tombol menu untuk navigasi.",
            _main_menu_keyboard(role),
        )
    except Exception as exc:
        logger.exception("Unhandled error in text_router: %s", exc)
        _clear_flow(context)
        context.user_data.pop(AWAIT_QRIS_IMAGE_KEY, None)
        await _respond(
            update,
            "❌ Terjadi error saat memproses pesan. Silakan coba lagi atau tekan /start.",
            _main_menu_keyboard(role),
        )


async def photo_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None or not update.message.photo:
        return

    db_user, role = await _ensure_user(update, context)
    flow, flow_data = _get_flow(context)

    if role != "admin" and flow == FLOW_CUSTOMER_COMPLAINT_COMPOSE:
        photo = update.message.photo[-1]
        _append_complaint_photo(context, photo.file_id)

        caption = (update.message.caption or "").strip()
        if caption:
            _append_complaint_text(context, caption)

        draft = _get_complaint_draft(context)
        order_ref = str(flow_data.get("order_ref") or draft.get("order_ref") or "").strip().upper()
        source_page = int(flow_data.get("source_page") or draft.get("source_page") or 1)
        total_photos = len([str(x).strip() for x in list(draft.get("photo_file_ids") or []) if str(x).strip()])
        await _respond(
            update,
            (
                "✅ Foto bukti ditambahkan ke draft komplain.\n"
                f"Total lampiran: <b>{total_photos}</b> file\n"
                "Jika masih ada foto lain, kirim lagi.\n"
                "Kalau sudah selesai, kirim teks keluhan atau tekan tombol Kirim Komplain."
            ),
            _build_complaint_compose_keyboard(order_ref, max(1, source_page)),
            parse_mode=ParseMode.HTML,
        )
        return

    if role != "admin":
        return

    if flow == FLOW_ADMIN_REFUND_PROOF:
        complaint_id_raw = flow_data.get("complaint_id")
        source_page_raw = flow_data.get("source_page")
        try:
            complaint_id = int(str(complaint_id_raw))
            source_page = int(str(source_page_raw)) if source_page_raw is not None else 1
        except ValueError:
            _clear_flow(context)
            await _respond(update, "⚠️ Data refund tidak valid. Ulangi dari menu komplain proses.", _admin_complaint_menu_keyboard())
            return

        proof_photo = update.message.photo[-1]
        note = (update.message.caption or "").strip()

        with get_session() as session:
            try:
                detail = mark_complaint_refund_transferred(
                    session,
                    complaint_id=complaint_id,
                    actor_id=db_user.id,
                    proof_file_id=proof_photo.file_id,
                    note=note,
                )
            except ValueError as exc:
                await _respond(update, f"❌ {exc}", _admin_complaint_menu_keyboard())
                return

        _clear_flow(context)

        customer_caption = (
            "💸 <b>Refund Sudah Ditransfer</b>\n"
            f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>\n"
            "Berikut bukti transfer dari admin."
        )
        if note:
            customer_caption += f"\nCatatan admin: <i>{html.escape(note)}</i>"

        try:
            await context.bot.send_photo(
                chat_id=detail.customer_telegram_id,
                photo=proof_photo.file_id,
                caption=customer_caption,
                parse_mode=ParseMode.HTML,
            )
        except Exception as exc:
            logger.warning("Gagal kirim bukti transfer refund ke customer: %s", exc)

        await _respond(
            update,
            (
                "✅ <b>Bukti transfer refund terkirim ke customer</b>\n"
                f"No. komplain: <b>{html.escape(detail.complaint_ref)}</b>\n"
                "Case ditandai selesai dan dipindahkan ke Komplain Selesai."
            ),
            InlineKeyboardMarkup(
                [
                    [InlineKeyboardButton("✅ Buka Komplain Selesai", callback_data=f"cmp:admin:done:list:{max(1, source_page)}")],
                    [InlineKeyboardButton("⬅️ Kembali ke Kelola Komplain", callback_data="adm:cmp")],
                ]
            ),
            parse_mode=ParseMode.HTML,
        )
        return

    awaiting_qris = bool((context.user_data or {}).get(AWAIT_QRIS_IMAGE_KEY))
    if not awaiting_qris:
        if flow != FLOW_ADMIN_BROADCAST:
            return

        caption = (update.message.caption or "").strip()
        if len(caption) > 1024:
            await _respond(
                update,
                "⚠️ Caption broadcast foto terlalu panjang. Maksimal 1024 karakter.",
                _back_keyboard("main"),
            )
            return

        photo = update.message.photo[-1]
        sent, failed, total = await _run_admin_broadcast_with_progress(
            update=update,
            context=context,
            admin_user_id=db_user.id,
            message_text=caption,
            attachment_type="photo",
            attachment_file_id=photo.file_id,
        )
        _clear_flow(context)
        await _respond(
            update,
            (
                "📢 <b>Broadcast selesai</b>\n"
                "Mode: <b>Foto</b>\n"
                f"Total customer: <b>{total}</b>\n"
                f"✅ Sent: <b>{sent}</b>\n"
                f"❌ Failed: <b>{failed}</b>\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    context.user_data[AWAIT_QRIS_IMAGE_KEY] = False
    settings.qris_file_path.parent.mkdir(parents=True, exist_ok=True)

    image = update.message.photo[-1]
    telegram_file = await image.get_file()
    image_data = await telegram_file.download_as_bytearray()
    image_bytes = bytes(image_data)

    if not image_bytes:
        await _respond(
            update,
            "❌ Gagal membaca file gambar QRIS. Coba kirim ulang foto QRIS.",
            _back_keyboard("pay"),
        )
        return

    settings.qris_file_path.write_bytes(image_bytes)

    extracted_payload = ""
    extraction_error = ""
    with get_session() as session:
        try:
            extracted_payload = extract_qris_payload_from_image(image_bytes)
            set_qris_static_payload(session, payload=extracted_payload, actor_id=db_user.id)
        except Exception as exc:
            extraction_error = str(exc)

    if extracted_payload:
        await _respond(
            update,
            (
                "✅ <b>QRIS berhasil disimpan</b>\n"
                "✅ Payload QRIS berhasil diekstrak otomatis.\n"
                f"Payload: <code>{_mask_qris_payload(extracted_payload)}</code>\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("pay"),
            parse_mode=ParseMode.HTML,
        )
        return

    await _respond(
        update,
        (
            "✅ <b>QRIS berhasil disimpan</b>\n"
            "⚠️ Payload QRIS belum bisa diekstrak otomatis.\n"
            f"Detail: <i>{html.escape(_truncate_text(extraction_error or 'Tidak ada detail error'))}</i>\n"
            "Gunakan tombol <b>Set Payload QRIS</b> untuk isi payload manual.\n\n"
            f"{_admin_footer_text()}"
        ),
        _back_keyboard("pay"),
        parse_mode=ParseMode.HTML,
    )


async def document_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None or update.message.document is None:
        return

    db_user, role = await _ensure_user(update, context)
    if role != "admin":
        return

    awaiting_qris = bool((context.user_data or {}).get(AWAIT_QRIS_IMAGE_KEY))
    if awaiting_qris:
        await _respond(
            update,
            "⚠️ Upload QRIS harus berupa foto, bukan file dokumen.",
            _back_keyboard("pay"),
        )
        return

    flow, _ = _get_flow(context)
    if flow != FLOW_ADMIN_BROADCAST:
        return

    caption = (update.message.caption or "").strip()
    if len(caption) > 1024:
        await _respond(
            update,
            "⚠️ Caption broadcast file terlalu panjang. Maksimal 1024 karakter.",
            _back_keyboard("main"),
        )
        return

    sent, failed, total = await _run_admin_broadcast_with_progress(
        update=update,
        context=context,
        admin_user_id=db_user.id,
        message_text=caption,
        attachment_type="document",
        attachment_file_id=update.message.document.file_id,
    )
    _clear_flow(context)
    await _respond(
        update,
        (
            "📢 <b>Broadcast selesai</b>\n"
            "Mode: <b>File</b>\n"
            f"Total customer: <b>{total}</b>\n"
            f"✅ Sent: <b>{sent}</b>\n"
            f"❌ Failed: <b>{failed}</b>\n\n"
            f"{_admin_footer_text()}"
        ),
        _back_keyboard("main"),
        parse_mode=ParseMode.HTML,
    )


def register_handlers(application: Application) -> None:
    application.add_handler(CommandHandler("start", start_handler))
    application.add_handler(CommandHandler("help", help_handler))

    application.add_handler(CommandHandler("catalog", catalog_handler))
    application.add_handler(CommandHandler("buy", buy_handler))
    application.add_handler(CommandHandler("myorders", my_orders_handler))
    application.add_handler(CommandHandler("order_status", order_status_handler))
    application.add_handler(CommandHandler("reorder", reorder_handler))

    application.add_handler(CommandHandler("admin_catalog", admin_catalog_handler))
    application.add_handler(CommandHandler("product_add", product_add_handler))
    application.add_handler(CommandHandler("stock_add", stock_add_handler))
    application.add_handler(CommandHandler("product_suspend", product_suspend_handler))
    application.add_handler(CommandHandler("product_unsuspend", product_unsuspend_handler))
    application.add_handler(CommandHandler("product_delete", product_delete_handler))
    application.add_handler(CommandHandler("broadcast", broadcast_handler))
    application.add_handler(CommandHandler("set_qris", set_qris_handler))
    application.add_handler(CommandHandler("ops_metrics", ops_metrics_handler))
    application.add_handler(CommandHandler("update_check", update_check_handler))
    application.add_handler(CommandHandler("update_apply", update_apply_handler))

    application.add_handler(CallbackQueryHandler(callback_router))
    application.add_handler(MessageHandler(filters.PHOTO, photo_handler))
    application.add_handler(MessageHandler(filters.Document.ALL, document_handler))
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, text_router))
