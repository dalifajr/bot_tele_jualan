from __future__ import annotations

import asyncio
from collections.abc import Sequence
from dataclasses import dataclass
from datetime import datetime, timezone
import html
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

from app.bot.services.broadcast_service import broadcast_to_customers
from app.bot.services.admin_order_notification_service import upsert_admin_order_message
from app.bot.services.catalog_service import (
    add_product,
    add_stock_block,
    delete_product,
    get_available_stock_count,
    get_product,
    list_products,
    suspend_product,
)
from app.bot.services.github_pack_service import (
    add_github_stock,
    delete_github_stock,
    ensure_github_pack_product,
    get_github_stock_detail,
    is_github_pack_product,
    list_github_stocks,
    set_github_pack_price,
)
from app.bot.services.order_service import (
    build_admin_order_message,
    cancel_order,
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
from app.bot.services.loyalty_service import list_customer_vouchers
from app.bot.services.metrics_service import collect_operational_metrics, format_operational_metrics_report
from app.bot.services.restock_service import subscribe_restock
from app.bot.services.user_service import get_user_by_telegram_id, upsert_user
from app.common.config import get_settings
from app.common.roles import get_primary_admin_id, is_admin
from app.db.database import get_session

logger = logging.getLogger(__name__)
settings = get_settings()

FLOW_KEY = "flow"
FLOW_DATA_KEY = "flow_data"
FLOW_ADMIN_ADD_PRODUCT = "admin_add_product"
FLOW_ADMIN_ADD_STOCK = "admin_add_stock"
FLOW_ADMIN_BROADCAST = "admin_broadcast"
FLOW_CUSTOMER_MANUAL_QTY = "customer_manual_qty"
FLOW_GH_ADD_READY = "gh_add_ready"
FLOW_GH_ADD_AWAIT = "gh_add_await"
FLOW_GH_SET_PRICE = "gh_set_price"
AWAIT_QRIS_IMAGE_KEY = "await_qris_image"
CUSTOMER_ORDERS_PAGE_SIZE = 10
ADMIN_LIST_PAGE_SIZE = 10
CHECKOUT_LOCK_UNTIL_KEY = "checkout_lock_until"
CHECKOUT_LOCK_SECONDS = 8.0
CHECKOUT_ACTION_CACHE_KEY = "checkout_action_cache"
CHECKOUT_ACTION_TTL_SECONDS = 20.0
USER_CTX_CACHE_KEY = "user_ctx_cache"
USER_CTX_CACHE_TTL_SECONDS = 20.0

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
                [InlineKeyboardButton("📢 Broadcast", callback_data="adm:bc")],
                [InlineKeyboardButton("💳 Konfigurasi Payment", callback_data="adm:pay")],
                [InlineKeyboardButton("🔄 Update Bot Tele", callback_data="adm:upd")],
                [InlineKeyboardButton("ℹ️ Bantuan", callback_data="adm:help")],
            ]
        )

    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("🛍️ Katalog", callback_data="cus:cat")],
            [InlineKeyboardButton("📦 Pesanan Saya", callback_data="cus:ord")],
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
            [InlineKeyboardButton("📥 Tambah Stok Ready", callback_data="gh:add:ready")],
            [InlineKeyboardButton("⏳ Tambah Stok Awaiting Benefits", callback_data="gh:add:await")],
            [InlineKeyboardButton("📋 Lihat List Akun", callback_data="gh:list")],
            [InlineKeyboardButton("👁️ Lihat Detail Akun", callback_data="gh:view:list")],
            [InlineKeyboardButton("🗑️ Hapus Akun", callback_data="gh:del:list")],
            [InlineKeyboardButton("⬅️ Kembali", callback_data="back:adm_cat")],
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
        except BadRequest:
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
) -> None:
    display_name = username
    if not display_name:
        tg_user = update.effective_user
        if tg_user is not None:
            display_name = tg_user.username or tg_user.full_name
    if not display_name:
        display_name = "user"

    if total_transaksi is None:
        tg_user = update.effective_user
        if tg_user is not None:
            with get_session() as session:
                user = get_user_by_telegram_id(session, tg_user.id)
                if user is not None:
                    total_transaksi = count_delivered_orders_by_customer(session, user.id)
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
        common.append("🎟️ Lihat voucher aktif: <code>/vouchers</code>.")

    await _respond(update, "\n".join(common), _back_keyboard("main"), parse_mode=ParseMode.HTML)


async def _send_admin_catalog_menu(update: Update) -> None:
    with get_session() as session:
        ensure_github_pack_product(session)

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


def _voucher_status_badge(status: str) -> str:
    mapping = {
        "active": "🟢 Aktif",
        "reserved": "🟡 Dialokasikan",
        "used": "✅ Terpakai",
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


def _customer_footer_text() -> str:
    return "👇 <i>Pilih aksi lewat tombol di bawah.</i>"


def _admin_footer_text() -> str:
    return "👇 <i>Pilih aksi admin lewat tombol di bawah.</i>"


def _admin_access_denied_text() -> str:
    return "🚫 <b>Akses Ditolak</b>\nMenu ini khusus admin."


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
        stocks = list_github_stocks(session)

    ready_count = sum(1 for x in stocks if x.status == "ready")
    awaiting_count = sum(1 for x in stocks if x.status == "awaiting_benefits")
    await _respond(
        update,
        (
            f"🎓 <b>{html.escape(product.name)}</b>\n"
            f"✅ Ready: <b>{ready_count}</b> akun\n"
            f"⏳ Awaiting benefits: <b>{awaiting_count}</b> akun\n\n"
            f"{_admin_footer_text()}"
        ),
        _github_pack_menu_keyboard(),
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
        lines.append(f"{idx}. <b>#{stock.id}</b> {html.escape(stock.username)} | {_stock_status_badge(stock.status)}")
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
        github_product_id = int(github_product.id)
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
        key=lambda x: (0 if x.id == github_product_id else 1, x.id),
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

    text = (
        "🧾 <b>Detail Produk</b>\n"
        f"📦 Nama: <b>{html.escape(product_name)}</b>\n"
        f"💰 Harga: <b>{_format_rupiah(product_price)}</b>\n"
        f"📦 Stok: {stock}\n"
        f"📝 Deskripsi: {html.escape(product_description)}\n\n"
        f"{_customer_footer_text()}"
    )

    if stock <= 0:
        await _respond(
            update,
            (
                f"{text}\n"
                "⚠️ <b>Stok sedang habis.</b>\n"
                "Aktifkan notifikasi agar kamu dapat info saat stok tersedia lagi."
            ),
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
    upsert_result = await upsert_admin_order_message(
        bot=update.get_bot(),
        admin_chat_id=admin_id,
        message_text=message_text,
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
            order, payment = create_checkout(
                session,
                customer=user,
                product_id=product_id,
                quantity=qty,
            )
        except ValueError as exc:
            await _respond(update, f"❌ Checkout gagal: {exc}", _back_keyboard("cus_cat"))
            return False
        order_ref = order.order_ref
        payment_ref = payment.payment_ref
        expected_amount = payment.expected_amount
        expires_at = order.expires_at
        subtotal_amount = order.subtotal
        voucher_discount_amount = max(0, int(order.voucher_discount_amount or 0))

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

    if voucher_discount_amount > 0:
        lines.append(f"🎟️ Voucher otomatis: -<b>{_format_rupiah(voucher_discount_amount)}</b>")
        lines.append(f"Subtotal sebelum voucher: {_format_rupiah(subtotal_amount)}")

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

    qris_path = settings.qris_file_path
    if qris_path.exists():
        try:
            if update.callback_query and update.callback_query.message:
                with qris_path.open("rb") as fh:
                    sent_message = await update.callback_query.message.reply_photo(
                        photo=fh,
                        caption=payload_text,
                        reply_markup=result_keyboard,
                        parse_mode=ParseMode.HTML,
                    )
            elif update.message:
                with qris_path.open("rb") as fh:
                    sent_message = await update.message.reply_photo(
                        photo=fh,
                        caption=payload_text,
                        reply_markup=result_keyboard,
                        parse_mode=ParseMode.HTML,
                    )
        except Exception as exc:
            logger.warning("Gagal kirim QRIS: %s", exc)

    if sent_message is None:
        if update.callback_query and update.callback_query.message:
            sent_message = await update.callback_query.message.reply_text(
                payload_text,
                reply_markup=result_keyboard,
                parse_mode=ParseMode.HTML,
            )
        elif update.message:
            sent_message = await update.message.reply_text(
                payload_text,
                reply_markup=result_keyboard,
                parse_mode=ParseMode.HTML,
            )

    if sent_message is not None:
        with get_session() as session:
            set_checkout_message_ref(
                session=session,
                order_ref=order_ref,
                chat_id=int(sent_message.chat_id),
                message_id=int(sent_message.message_id),
            )

    return True


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


async def _run_update_script(action: str) -> str:
    script_path = Path(settings.project_root / "ops" / "update_manager.sh")
    if not script_path.exists():
        return "❌ Script update tidak ditemukan."

    proc = await asyncio.to_thread(
        subprocess.run,
        ["bash", str(script_path), action],
        cwd=str(settings.project_root),
        capture_output=True,
        text=True,
        check=False,
    )
    output = (proc.stdout or proc.stderr or "Tidak ada output").strip()
    if len(output) > 3500:
        output = output[:3500] + "\n..."
    return output


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


async def vouchers_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    db_user, role = await _ensure_user(update)
    if role == "admin":
        await _respond(update, "🚫 Admin tidak menggunakan command ini.", _back_keyboard("main"))
        return

    with get_session() as session:
        vouchers = list_customer_vouchers(session, customer_id=db_user.id, include_used=False)

    if not vouchers:
        await _respond(
            update,
            (
                "🎟️ <b>Voucher Kamu</b>\n"
                "Belum ada voucher aktif saat ini.\n"
                "Teruskan transaksi sukses untuk membuka reward loyalti."
            ),
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )
        return

    lines = ["🎟️ <b>Voucher Kamu</b>"]
    for idx, voucher in enumerate(vouchers, start=1):
        expiry_text = "-"
        if voucher.expires_at is not None:
            expiry_text = _format_display_time(voucher.expires_at)
        lines.extend(
            [
                "",
                f"{idx}. <code>{html.escape(voucher.code)}</code>",
                f"Status: {_voucher_status_badge(voucher.status)}",
                f"Diskon: <b>{_format_rupiah(voucher.discount_amount)}</b>",
                f"Min. order: {_format_rupiah(voucher.min_order_amount)}",
                f"Berlaku sampai: {expiry_text}",
            ]
        )

    lines.extend([
        "",
        "Voucher aktif dipakai otomatis saat checkout jika memenuhi syarat.",
    ])

    await _respond(
        update,
        "\n".join(lines),
        _back_keyboard("main"),
        parse_mode=ParseMode.HTML,
    )


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

    await _respond(
        update,
        format_operational_metrics_report(metrics),
        _back_keyboard("main"),
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

    _set_flow(context, FLOW_ADMIN_BROADCAST)
    await _respond(
        update,
        (
            "📢 <b>Broadcast ke Customer</b>\n"
            "Kirim isi pesan sekarang, lalu bot akan kirim ke semua customer.\n\n"
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

    context.user_data[AWAIT_QRIS_IMAGE_KEY] = True
    await _respond(
        update,
        (
            "🖼️ <b>Upload QRIS</b>\n"
            "Kirim gambar QRIS sekarang dalam format foto.\n\n"
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

    output = await _run_update_script("check")
    await _respond(update, f"🔍 Hasil cek update:\n{output}", _back_keyboard("upd"))


async def update_apply_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond_admin_only(update)
        return

    output = await _run_update_script("update")
    await _respond(
        update,
        f"⬆️ Update dijalankan:\n{output}",
        _back_keyboard("main"),
    )


async def callback_router(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    query = update.callback_query
    if query is None:
        return

    data = query.data or ""
    if data.startswith("buy:") or data.startswith("buyall:") or data.startswith("ord:reorder:"):
        await query.answer("⏳ Memproses checkout...", show_alert=False)
    else:
        await query.answer()

    db_user, role = await _ensure_user(update, context)

    admin_only_prefixes = ("adm:", "ac:", "acp:", "gh:", "ap:", "pay:", "up:")
    if role != "admin" and any(data.startswith(prefix) for prefix in admin_only_prefixes):
        await _respond_admin_only(update)
        return

    if data.startswith("back:"):
        target = data.split(":", maxsplit=1)[1]
        _clear_flow(context)
        if target == "main":
            await _send_main_menu(update, role)
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
        if target == "upd":
            await _respond(
                update,
                f"🔄 <b>Menu Update Bot</b>\n\n{_admin_footer_text()}",
                _update_menu_keyboard(),
                parse_mode=ParseMode.HTML,
            )
            return
        await _send_main_menu(update, role)
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
        _set_flow(context, FLOW_ADMIN_BROADCAST)
        await _respond(
            update,
            (
                "📢 <b>Broadcast ke Customer</b>\n"
                "Kirim isi pesan sekarang, lalu bot akan kirim ke semua customer.\n\n"
                f"{_admin_footer_text()}"
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
            f"🔄 <b>Menu Update Bot</b>\n\n{_admin_footer_text()}",
            _update_menu_keyboard(),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "adm:help":
        await _send_help(update, role)
        return

    if data == "cus:cat":
        _clear_flow(context)
        await _send_customer_catalog(update)
        return

    if data == "cus:ord":
        _clear_flow(context)
        await _send_customer_orders(update, db_user.telegram_id, page=1)
        return

    if data == "cus:help":
        await _send_help(update, role)
        return

    if data == "noop":
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
        _set_flow(context, FLOW_GH_ADD_AWAIT)
        await _respond(
            update,
            (
                "⏳ <b>Tambah Stok GitHub Pack (AWAITING)</b>\n"
                "Kirim blok data akun. Stok otomatis pindah ke READY setelah 72 jam.\n\n"
                f"{_admin_footer_text()}"
            ),
            _back_keyboard("adm_cat"),
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

        with get_session() as session:
            try:
                delete_github_stock(session, stock_id=stock_id, actor_id=db_user.id)
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

        with get_session() as session:
            try:
                if action == "sup":
                    suspend_product(session, product_id=product_id, suspended=True, actor_id=db_user.id)
                    await _respond(update, f"⏸️ Produk #{product_id} berhasil disuspend.", _back_keyboard("adm_cat"))
                elif action == "uns":
                    suspend_product(session, product_id=product_id, suspended=False, actor_id=db_user.id)
                    await _respond(update, f"▶️ Produk #{product_id} berhasil diaktifkan.", _back_keyboard("adm_cat"))
                elif action == "del":
                    delete_product(session, product_id=product_id, actor_id=db_user.id)
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

        with get_session() as session:
            product = get_product(session, product_id)
            if product is None:
                await _respond(update, "⚠️ Produk tidak ditemukan.", _back_keyboard("cus_cat"))
                return

            created, message = subscribe_restock(
                session,
                customer_id=db_user.id,
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
            checkout_ok = await _send_checkout_result(update, db_user.telegram_id, product_id, qty)
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
            checkout_ok = await _send_checkout_result(update, db_user.telegram_id, product_id, qty)
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

        await _send_customer_orders(update, db_user.telegram_id, page=page)
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

        await _send_customer_order_detail(update, db_user.telegram_id, order_ref=order_ref, page=page)
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

        await _send_customer_order_copy(update, db_user.telegram_id, order_ref=order_ref, page=page)
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
            checkout_ok = await _send_quick_reorder_result(update, db_user.telegram_id, order_ref)
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

        with get_session() as session:
            cancel_result = cancel_order(session, order_ref=order_ref, customer_id=db_user.id)

        if cancel_result.ok:
            await _upsert_admin_order_notification(update, order_ref)

        keyboard = _back_keyboard("cus_cat") if cancel_result.ok else _back_keyboard("main")
        await _respond(update, cancel_result.message, keyboard)
        return

    if data == "pay:upload":
        if role != "admin":
            await _respond_admin_only(update)
            return
        context.user_data[AWAIT_QRIS_IMAGE_KEY] = True
        await _respond(
            update,
            (
                "🖼️ <b>Upload QRIS</b>\n"
                "Kirim gambar QRIS sekarang dalam format foto.\n\n"
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

        if settings.qris_file_path.exists():
            await _respond(
                update,
                f"✅ <b>QRIS sudah tersimpan</b>\n\n{_admin_footer_text()}",
                _back_keyboard("pay"),
                parse_mode=ParseMode.HTML,
            )
            if query.message:
                try:
                    with settings.qris_file_path.open("rb") as fh:
                        await query.message.reply_photo(photo=fh, caption="🧾 Preview QRIS", reply_markup=_back_keyboard("pay"))
                except Exception as exc:
                    logger.warning("Gagal kirim preview QRIS: %s", exc)
        else:
            await _respond(
                update,
                f"⚠️ <b>QRIS belum diupload</b>\n\n{_admin_footer_text()}",
                _back_keyboard("pay"),
                parse_mode=ParseMode.HTML,
            )
        return

    if data == "up:check":
        if role != "admin":
            await _respond_admin_only(update)
            return
        output = await _run_update_script("check")
        await _respond(
            update,
            f"🔍 <b>Hasil Cek Update</b>\n{html.escape(output)}\n\n{_admin_footer_text()}",
            _back_keyboard("upd"),
            parse_mode=ParseMode.HTML,
        )
        return

    if data == "up:apply":
        if role != "admin":
            await _respond_admin_only(update)
            return
        output = await _run_update_script("update")
        await _respond(
            update,
            f"⬆️ <b>Update Dijalankan</b>\n{html.escape(output)}\n\n{_admin_footer_text()}",
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
            await _send_main_menu(update, role)
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
            await _respond(
                update,
                (
                    "✅ <b>Stok GitHub Pack berhasil ditambahkan</b>\n"
                    f"ID: <b>{stock.id}</b>\n"
                    f"Username: <b>{html.escape(stock.username)}</b>\n"
                    f"Status: <b>{status_text}</b>\n\n"
                    f"{_admin_footer_text()}"
                ),
                _github_pack_menu_keyboard(),
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

        if flow == FLOW_ADMIN_BROADCAST:
            if role != "admin":
                await _respond_admin_only(update)
                _clear_flow(context)
                return

            with get_session() as session:
                sent, failed = await broadcast_to_customers(
                    session=session,
                    bot=context.bot,
                    admin_user_id=db_user.id,
                    message=text,
                )

            _clear_flow(context)
            await _respond(
                update,
                (
                    "📢 <b>Broadcast selesai</b>\n"
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

    await _ensure_user(update, context)
    if not _ensure_admin(update):
        return

    if not context.user_data.get(AWAIT_QRIS_IMAGE_KEY):
        return

    context.user_data[AWAIT_QRIS_IMAGE_KEY] = False
    settings.qris_file_path.parent.mkdir(parents=True, exist_ok=True)

    image = update.message.photo[-1]
    telegram_file = await image.get_file()
    await telegram_file.download_to_drive(custom_path=str(settings.qris_file_path))
    await update.message.reply_text(
        "✅ QRIS berhasil disimpan.",
        reply_markup=_back_keyboard("pay"),
    )


def register_handlers(application: Application) -> None:
    application.add_handler(CommandHandler("start", start_handler))
    application.add_handler(CommandHandler("help", help_handler))

    application.add_handler(CommandHandler("catalog", catalog_handler))
    application.add_handler(CommandHandler("buy", buy_handler))
    application.add_handler(CommandHandler("myorders", my_orders_handler))
    application.add_handler(CommandHandler("order_status", order_status_handler))
    application.add_handler(CommandHandler("vouchers", vouchers_handler))
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
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, text_router))
