from __future__ import annotations

import asyncio
import logging
import subprocess
from pathlib import Path

from telegram import InlineKeyboardButton, InlineKeyboardMarkup, Update
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
from app.bot.services.catalog_service import (
    add_product,
    add_stock_block,
    delete_product,
    get_available_stock_count,
    get_product,
    list_products,
    suspend_product,
)
from app.bot.services.order_service import create_checkout, list_recent_orders_by_customer
from app.bot.services.user_service import get_user_by_telegram_id, upsert_user
from app.common.config import get_settings
from app.common.roles import is_admin
from app.db.database import get_session

logger = logging.getLogger(__name__)
settings = get_settings()

FLOW_KEY = "flow"
FLOW_DATA_KEY = "flow_data"
FLOW_ADMIN_ADD_PRODUCT = "admin_add_product"
FLOW_ADMIN_ADD_STOCK = "admin_add_stock"
FLOW_ADMIN_BROADCAST = "admin_broadcast"
FLOW_CUSTOMER_MANUAL_QTY = "customer_manual_qty"
AWAIT_QRIS_IMAGE_KEY = "await_qris_image"


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
) -> None:
    query = update.callback_query
    if query is not None:
        try:
            await query.edit_message_text(text=text, reply_markup=keyboard)
            return
        except BadRequest:
            if query.message is not None:
                await query.message.reply_text(text=text, reply_markup=keyboard)
                return

    if update.message is not None:
        await update.message.reply_text(text=text, reply_markup=keyboard)


async def _send_main_menu(update: Update, role: str, welcome: bool = False) -> None:
    text = "👋 Selamat datang di bot jualan."
    if role == "admin":
        text = "👋 Selamat datang Admin."

    if welcome:
        text += "\n\nSilakan pilih menu di bawah ini."

    await _respond(update, text, _main_menu_keyboard(role))


async def _send_help(update: Update, role: str) -> None:
    common = [
        "📌 Semua interaksi utama sekarang berbasis tombol.",
        "📌 Setiap aksi punya tombol ⬅️ Kembali.",
        "📌 Ketik 'kembali' kapan saja untuk kembali ke menu utama.",
    ]
    if role == "admin":
        common.append("🔐 Admin: gunakan menu 📦 Katalog Admin untuk CRUD produk.")
    else:
        common.append("🛍️ Customer: pilih produk dari menu katalog lalu checkout via tombol.")

    await _respond(update, "\n".join(common), _back_keyboard("main"))


async def _send_admin_catalog_menu(update: Update) -> None:
    await _respond(
        update,
        "📦 Menu Katalog Admin\nPilih tindakan yang ingin dilakukan.",
        _admin_catalog_menu_keyboard(),
    )


async def _send_admin_catalog_list(update: Update) -> None:
    with get_session() as session:
        products = list_products(session=session, include_suspended=True)

    if not products:
        await _respond(update, "📭 Belum ada produk.", _back_keyboard("adm_cat"))
        return

    lines = ["📋 Daftar produk:"]
    for product in products:
        status = "⏸️ Suspend" if product.is_suspended else "✅ Aktif"
        lines.append(
            f"#{product.id} {product.name} | {_format_rupiah(product.price)} | stok {product.stock_available} | {status}"
        )

    await _respond(update, "\n".join(lines), _back_keyboard("adm_cat"))


async def _send_admin_product_picker(update: Update, action: str, title: str) -> None:
    with get_session() as session:
        products = list_products(session=session, include_suspended=True)

    if not products:
        await _respond(update, "📭 Tidak ada produk untuk dipilih.", _back_keyboard("adm_cat"))
        return

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for product in products:
        label = f"#{product.id} {product.name}"
        keyboard_rows.append(
            [InlineKeyboardButton(label, callback_data=f"ap:{action}:{product.id}")]
        )

    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="back:adm_cat")])
    await _respond(update, title, InlineKeyboardMarkup(keyboard_rows))


async def _send_customer_catalog(update: Update) -> None:
    with get_session() as session:
        products = list_products(session=session, include_suspended=False)

    if not products:
        await _respond(update, "📭 Katalog masih kosong.", _back_keyboard("main"))
        return

    keyboard_rows: list[list[InlineKeyboardButton]] = []
    for product in products:
        label = f"🛍️ {product.name} | {_format_rupiah(product.price)} | stok {product.stock_available}"
        keyboard_rows.append(
            [InlineKeyboardButton(label, callback_data=f"cp:{product.id}")]
        )

    keyboard_rows.append([InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")])
    await _respond(
        update,
        "🛍️ Katalog Produk\nPilih produk untuk melihat detail dan checkout.",
        InlineKeyboardMarkup(keyboard_rows),
    )


async def _send_customer_orders(update: Update, telegram_id: int) -> None:
    with get_session() as session:
        user = get_user_by_telegram_id(session, telegram_id)
        if user is None:
            await _respond(update, "⚠️ User tidak ditemukan.", _back_keyboard("main"))
            return
        orders = list_recent_orders_by_customer(session, customer_id=user.id, limit=10)

    if not orders:
        await _respond(update, "📭 Belum ada pesanan.", _back_keyboard("main"))
        return

    lines = ["📦 Pesanan terbaru:"]
    for order in orders:
        lines.append(
            f"• {order.order_ref} | {order.status} | {_format_rupiah(order.total_amount)}"
        )
    await _respond(update, "\n".join(lines), _back_keyboard("main"))


async def _send_product_detail(update: Update, product_id: int) -> None:
    with get_session() as session:
        product = get_product(session, product_id)
        if product is None or product.is_suspended:
            await _respond(update, "⚠️ Produk tidak tersedia.", _back_keyboard("cus_cat"))
            return
        stock = get_available_stock_count(session, product_id)

    text = (
        f"🧾 Detail Produk\n"
        f"Nama: {product.name}\n"
        f"Harga: {_format_rupiah(product.price)}\n"
        f"Stok: {stock}\n"
        f"Deskripsi: {product.description or '-'}"
    )

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
            [InlineKeyboardButton("✍️ Input Qty Manual", callback_data=f"buyq:{product_id}")],
            [InlineKeyboardButton("⬅️ Kembali", callback_data="back:cus_cat")],
        ]
    )
    await _respond(update, text, keyboard)


async def _send_checkout_result(
    update: Update,
    telegram_id: int,
    product_id: int,
    qty: int,
) -> None:
    with get_session() as session:
        user = get_user_by_telegram_id(session, telegram_id)
        if user is None:
            await _respond(update, "⚠️ User tidak ditemukan. Jalankan /start lagi.", _back_keyboard("main"))
            return
        try:
            order, payment = create_checkout(
                session,
                customer=user,
                product_id=product_id,
                quantity=qty,
            )
        except ValueError as exc:
            await _respond(update, f"❌ Checkout gagal: {exc}", _back_keyboard("cus_cat"))
            return

    lines = [
        "✅ Checkout berhasil dibuat.",
        f"🧾 Order Ref: {order.order_ref}",
        f"🔖 Payment Ref: {payment.payment_ref}",
        f"💰 Total Bayar: {_format_rupiah(payment.expected_amount)}",
        "📲 Silakan transfer sesuai nominal. Konfirmasi dilakukan otomatis.",
    ]
    result_keyboard = InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("📦 Lihat Pesanan", callback_data="cus:ord")],
            [InlineKeyboardButton("⬅️ Kembali", callback_data="back:cus_cat")],
        ]
    )
    await _respond(update, "\n".join(lines), result_keyboard)

    qris_path = settings.qris_file_path
    if qris_path.exists():
        try:
            if update.callback_query and update.callback_query.message:
                with qris_path.open("rb") as fh:
                    await update.callback_query.message.reply_photo(
                        photo=fh,
                        caption="🧾 QRIS Pembayaran",
                        reply_markup=_back_keyboard("cus_cat"),
                    )
            elif update.message:
                with qris_path.open("rb") as fh:
                    await update.message.reply_photo(
                        photo=fh,
                        caption="🧾 QRIS Pembayaran",
                        reply_markup=_back_keyboard("cus_cat"),
                    )
        except Exception as exc:
            logger.warning("Gagal kirim QRIS: %s", exc)


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


async def _ensure_user(update: Update):
    tg_user = update.effective_user
    if tg_user is None:
        raise ValueError("User Telegram tidak ditemukan.")

    role = _role_for_telegram_id(tg_user.id)
    with get_session() as session:
        db_user = upsert_user(
            session=session,
            telegram_id=tg_user.id,
            username=tg_user.username,
            full_name=tg_user.full_name,
            role=role,
        )
    return db_user, role


def _ensure_admin(update: Update) -> bool:
    tg_user = update.effective_user
    if tg_user is None:
        return False
    return _role_for_telegram_id(tg_user.id) == "admin"


async def start_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, role = await _ensure_user(update)
    _clear_flow(context)
    context.user_data.pop(AWAIT_QRIS_IMAGE_KEY, None)
    await _send_main_menu(update, role=role, welcome=True)


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
        await _respond(update, "🚫 Akses ditolak. Menu ini hanya untuk admin.", _back_keyboard("main"))
        return
    _clear_flow(context)
    await _send_admin_catalog_menu(update)


async def product_add_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
        return
    _set_flow(context, FLOW_ADMIN_ADD_PRODUCT)
    await _respond(
        update,
        "➕ Upsert Produk\nKirim data dengan format:\nNama|Harga|Deskripsi",
        _back_keyboard("adm_cat"),
    )


async def stock_add_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
        return

    await _send_admin_product_picker(
        update,
        action="stk",
        title="📥 Pilih produk untuk ditambah stok.",
    )


async def product_suspend_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
        return

    await _send_admin_product_picker(
        update,
        action="sup",
        title="⏸️ Pilih produk yang akan disuspend.",
    )


async def product_unsuspend_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
        return

    await _send_admin_product_picker(
        update,
        action="uns",
        title="▶️ Pilih produk yang akan diaktifkan kembali.",
    )


async def product_delete_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
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
        await _respond(update, "Format: /buy <product_id> <qty>", _back_keyboard("cus_cat"))
        return

    try:
        product_id = int(context.args[0])
        qty = int(context.args[1])
    except ValueError:
        await _respond(update, "product_id dan qty harus angka.", _back_keyboard("cus_cat"))
        return

    await _send_checkout_result(update, db_user.telegram_id, product_id, qty)


async def my_orders_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    db_user, _ = await _ensure_user(update)
    await _send_customer_orders(update, db_user.telegram_id)


async def broadcast_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    _, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
        return

    _set_flow(context, FLOW_ADMIN_BROADCAST)
    await _respond(
        update,
        "📢 Kirim pesan broadcast sekarang.\nPesan Anda akan dikirim ke semua customer.",
        _back_keyboard("main"),
    )


async def set_qris_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
        return

    context.user_data[AWAIT_QRIS_IMAGE_KEY] = True
    await _respond(
        update,
        "🖼️ Kirim gambar QRIS sekarang (sebagai foto).",
        _back_keyboard("pay"),
    )


async def update_check_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
        return

    output = await _run_update_script("check")
    await _respond(update, f"🔍 Hasil cek update:\n{output}", _back_keyboard("upd"))


async def update_apply_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await _ensure_user(update)
    if not _ensure_admin(update):
        await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
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

    await query.answer()
    db_user, role = await _ensure_user(update)
    data = query.data or ""

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
            await _respond(update, "💳 Konfigurasi Payment", _payment_menu_keyboard())
            return
        if target == "upd":
            await _respond(update, "🔄 Menu Update Bot", _update_menu_keyboard())
            return
        await _send_main_menu(update, role)
        return

    if data == "adm:cat":
        if role != "admin":
            await _respond(update, "🚫 Hanya admin yang bisa akses menu ini.", _back_keyboard("main"))
            return
        _clear_flow(context)
        await _send_admin_catalog_menu(update)
        return

    if data == "adm:bc":
        if role != "admin":
            await _respond(update, "🚫 Hanya admin yang bisa akses menu ini.", _back_keyboard("main"))
            return
        _set_flow(context, FLOW_ADMIN_BROADCAST)
        await _respond(
            update,
            "📢 Kirim pesan broadcast sekarang.\nPesan Anda akan dikirim ke semua customer.",
            _back_keyboard("main"),
        )
        return

    if data == "adm:pay":
        if role != "admin":
            await _respond(update, "🚫 Hanya admin yang bisa akses menu ini.", _back_keyboard("main"))
            return
        _clear_flow(context)
        await _respond(update, "💳 Konfigurasi Payment", _payment_menu_keyboard())
        return

    if data == "adm:upd":
        if role != "admin":
            await _respond(update, "🚫 Hanya admin yang bisa akses menu ini.", _back_keyboard("main"))
            return
        _clear_flow(context)
        await _respond(update, "🔄 Menu Update Bot", _update_menu_keyboard())
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
        await _send_customer_orders(update, db_user.telegram_id)
        return

    if data == "cus:help":
        await _send_help(update, role)
        return

    if data == "ac:view":
        await _send_admin_catalog_list(update)
        return

    if data == "ac:add":
        _set_flow(context, FLOW_ADMIN_ADD_PRODUCT)
        await _respond(
            update,
            "➕ Upsert Produk\nKirim data dengan format:\nNama|Harga|Deskripsi",
            _back_keyboard("adm_cat"),
        )
        return

    if data == "ac:stock":
        await _send_admin_product_picker(update, action="stk", title="📥 Pilih produk untuk ditambah stok.")
        return

    if data == "ac:susp":
        await _send_admin_product_picker(update, action="sup", title="⏸️ Pilih produk yang akan disuspend.")
        return

    if data == "ac:uns":
        await _send_admin_product_picker(update, action="uns", title="▶️ Pilih produk yang akan diaktifkan.")
        return

    if data == "ac:del":
        await _send_admin_product_picker(update, action="del", title="🗑️ Pilih produk yang akan dihapus.")
        return

    if data.startswith("ap:"):
        if role != "admin":
            await _respond(update, "🚫 Hanya admin yang bisa akses menu ini.", _back_keyboard("main"))
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
                f"📥 Kirim blok stok untuk produk #{product_id}.\nSatu pesan = satu unit stok.",
                _back_keyboard("adm_cat"),
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
            await _respond(update, "⚠️ Produk tidak valid.", _back_keyboard("cus_cat"))
            return

        await _send_product_detail(update, product_id)
        return

    if data.startswith("buy:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return

        parts = data.split(":")
        if len(parts) != 3:
            await _respond(update, "⚠️ Data checkout tidak valid.", _back_keyboard("cus_cat"))
            return

        try:
            product_id = int(parts[1])
            qty = int(parts[2])
        except ValueError:
            await _respond(update, "⚠️ Data checkout tidak valid.", _back_keyboard("cus_cat"))
            return

        await _send_checkout_result(update, db_user.telegram_id, product_id, qty)
        return

    if data.startswith("buyq:"):
        if role == "admin":
            await _respond(update, "🚫 Admin tidak bisa checkout sebagai customer.", _back_keyboard("main"))
            return

        try:
            product_id = int(data.split(":", maxsplit=1)[1])
        except ValueError:
            await _respond(update, "⚠️ Produk tidak valid.", _back_keyboard("cus_cat"))
            return

        _set_flow(context, FLOW_CUSTOMER_MANUAL_QTY, product_id=product_id)
        await _respond(
            update,
            "✍️ Masukkan jumlah pembelian (angka).",
            _back_keyboard("cus_cat"),
        )
        return

    if data == "pay:upload":
        if role != "admin":
            await _respond(update, "🚫 Hanya admin yang bisa akses menu ini.", _back_keyboard("main"))
            return
        context.user_data[AWAIT_QRIS_IMAGE_KEY] = True
        await _respond(
            update,
            "🖼️ Kirim gambar QRIS sekarang (sebagai foto).",
            _back_keyboard("pay"),
        )
        return

    if data == "pay:status":
        if role != "admin":
            await _respond(update, "🚫 Hanya admin yang bisa akses menu ini.", _back_keyboard("main"))
            return

        if settings.qris_file_path.exists():
            await _respond(update, "✅ QRIS sudah tersimpan.", _back_keyboard("pay"))
            if query.message:
                try:
                    with settings.qris_file_path.open("rb") as fh:
                        await query.message.reply_photo(photo=fh, caption="🧾 Preview QRIS", reply_markup=_back_keyboard("pay"))
                except Exception as exc:
                    logger.warning("Gagal kirim preview QRIS: %s", exc)
        else:
            await _respond(update, "⚠️ QRIS belum diupload.", _back_keyboard("pay"))
        return

    if data == "up:check":
        if role != "admin":
            await _respond(update, "🚫 Hanya admin yang bisa akses menu ini.", _back_keyboard("main"))
            return
        output = await _run_update_script("check")
        await _respond(update, f"🔍 Hasil cek update:\n{output}", _back_keyboard("upd"))
        return

    if data == "up:apply":
        if role != "admin":
            await _respond(update, "🚫 Hanya admin yang bisa akses menu ini.", _back_keyboard("main"))
            return
        output = await _run_update_script("update")
        await _respond(update, f"⬆️ Update dijalankan:\n{output}", _back_keyboard("main"))
        return

    await _respond(update, "⚠️ Tombol tidak dikenali. Silakan kembali ke menu utama.", _back_keyboard("main"))


async def text_router(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None or update.message.text is None:
        return

    role = "customer"
    try:
        db_user, role = await _ensure_user(update)
        text = update.message.text.strip()
        flow, flow_data = _get_flow(context)

        if _is_back_text(text):
            _clear_flow(context)
            context.user_data.pop(AWAIT_QRIS_IMAGE_KEY, None)
            await _send_main_menu(update, role)
            return

        if flow == FLOW_ADMIN_ADD_PRODUCT:
            if role != "admin":
                await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
                _clear_flow(context)
                return

            parsed = _parse_product_upsert_input(text)
            if parsed is None:
                await _respond(
                    update,
                    "⚠️ Format salah. Gunakan: Nama|Harga|Deskripsi",
                    _back_keyboard("adm_cat"),
                )
                return

            name, price, description = parsed
            with get_session() as session:
                product = add_product(session, name=name, price=price, description=description, actor_id=db_user.id)

            _clear_flow(context)
            await _respond(
                update,
                f"✅ Produk tersimpan (upsert) dengan ID #{product.id}.",
                _back_keyboard("adm_cat"),
            )
            return

        if flow == FLOW_ADMIN_ADD_STOCK:
            if role != "admin":
                await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
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
                except ValueError as exc:
                    await _respond(update, f"❌ Gagal menambah stok: {exc}", _back_keyboard("adm_cat"))
                    return

            _clear_flow(context)
            await _respond(
                update,
                f"✅ Stok berhasil ditambahkan. ID stok: {stock.id}",
                _back_keyboard("adm_cat"),
            )
            return

        if flow == FLOW_ADMIN_BROADCAST:
            if role != "admin":
                await _respond(update, "🚫 Akses ditolak.", _back_keyboard("main"))
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
                f"📢 Broadcast selesai.\n✅ Sent: {sent}\n❌ Failed: {failed}",
                _back_keyboard("main"),
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
                await _respond(update, "⚠️ Jumlah harus angka.", _back_keyboard("cus_cat"))
                return

            product_id_raw = flow_data.get("product_id")
            try:
                product_id = int(str(product_id_raw))
            except ValueError:
                _clear_flow(context)
                await _respond(update, "⚠️ Data produk tidak valid.", _back_keyboard("cus_cat"))
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

                _clear_flow(context)
                await _respond(
                    update,
                    f"✅ Produk tersimpan (upsert fallback) dengan ID #{product.id}.",
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

    await _ensure_user(update)
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

    application.add_handler(CommandHandler("admin_catalog", admin_catalog_handler))
    application.add_handler(CommandHandler("product_add", product_add_handler))
    application.add_handler(CommandHandler("stock_add", stock_add_handler))
    application.add_handler(CommandHandler("product_suspend", product_suspend_handler))
    application.add_handler(CommandHandler("product_unsuspend", product_unsuspend_handler))
    application.add_handler(CommandHandler("product_delete", product_delete_handler))
    application.add_handler(CommandHandler("broadcast", broadcast_handler))
    application.add_handler(CommandHandler("set_qris", set_qris_handler))
    application.add_handler(CommandHandler("update_check", update_check_handler))
    application.add_handler(CommandHandler("update_apply", update_apply_handler))

    application.add_handler(CallbackQueryHandler(callback_router))
    application.add_handler(MessageHandler(filters.PHOTO, photo_handler))
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, text_router))
