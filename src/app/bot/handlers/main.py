from __future__ import annotations

import asyncio
import logging
import subprocess
from pathlib import Path

from telegram import ReplyKeyboardMarkup, Update
from telegram.ext import (
    Application,
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

ADMIN_MENU = [["Katalog", "Broadcast"], ["Konfigurasi Payment", "Update Bot Tele"]]
CUSTOMER_MENU = [["Katalog", "Pesanan Saya"]]


def _role_for_telegram_id(telegram_id: int) -> str:
    return "admin" if is_admin(telegram_id, settings.role_file_path) else "customer"


def _format_rupiah(amount: int) -> str:
    return f"Rp{amount:,}".replace(",", ".")


def _menu_for_role(role: str) -> ReplyKeyboardMarkup:
    menu = ADMIN_MENU if role == "admin" else CUSTOMER_MENU
    return ReplyKeyboardMarkup(menu, resize_keyboard=True)


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

    text = (
        "Selamat datang Admin."
        if role == "admin"
        else "Selamat datang di bot toko kami."
    )
    text += "\nGunakan menu atau /help untuk melihat command."

    if update.message:
        await update.message.reply_text(text, reply_markup=_menu_for_role(role))


async def help_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    _, role = await _ensure_user(update)
    common = [
        "/start - Mulai bot",
        "/catalog - Lihat katalog",
        "/buy <product_id> <qty> - Checkout",
        "/myorders - Riwayat pesanan",
    ]

    admin = [
        "/admin_catalog - Katalog admin",
        "/product_add Nama|Harga|Deskripsi",
        "/stock_add <product_id> lalu kirim blok stok",
        "/product_suspend <product_id>",
        "/product_unsuspend <product_id>",
        "/product_delete <product_id>",
        "/broadcast <pesan>",
        "/set_qris - lalu kirim foto QRIS",
        "/update_check - cek commit terbaru",
        "/update_apply - update bot",
    ]

    lines = common + (admin if role == "admin" else [])
    await update.message.reply_text("\n".join(lines))


async def catalog_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return
    await _ensure_user(update)

    with get_session() as session:
        products = list_products(session=session, include_suspended=False)

    if not products:
        await update.message.reply_text("Belum ada produk aktif.")
        return

    lines = ["Katalog produk:"]
    for product in products:
        lines.append(
            f"ID {product.id} | {product.name} | {_format_rupiah(product.price)} | stok {product.stock_available}"
        )
    lines.append("\nCheckout: /buy <product_id> <qty>")

    await update.message.reply_text("\n".join(lines))


async def admin_catalog_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return
    await _ensure_user(update)

    if not _ensure_admin(update):
        await update.message.reply_text("Akses ditolak. Menu ini hanya untuk admin.")
        return

    with get_session() as session:
        products = list_products(session=session, include_suspended=True)

    if not products:
        await update.message.reply_text("Belum ada produk.")
        return

    lines = ["Katalog admin:"]
    for product in products:
        status = "SUSPEND" if product.is_suspended else "AKTIF"
        lines.append(
            f"ID {product.id} | {product.name} | {_format_rupiah(product.price)} | stok {product.stock_available} | {status}"
        )
    await update.message.reply_text("\n".join(lines))


async def product_add_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    db_user, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await update.message.reply_text("Akses ditolak.")
        return

    if not context.args:
        await update.message.reply_text("Format: /product_add Nama|Harga|Deskripsi")
        return

    raw = " ".join(context.args)
    parts = [x.strip() for x in raw.split("|")]
    if len(parts) < 2:
        await update.message.reply_text("Format salah. Contoh: /product_add Netflix|50000|Akun 1 bulan")
        return

    name = parts[0]
    try:
        price = int(parts[1])
    except ValueError:
        await update.message.reply_text("Harga harus angka.")
        return

    description = parts[2] if len(parts) >= 3 else ""

    with get_session() as session:
        product = add_product(session, name=name, price=price, description=description, actor_id=db_user.id)

    await update.message.reply_text(f"Produk ditambahkan dengan ID {product.id}.")


async def stock_add_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    await _ensure_user(update)
    if not _ensure_admin(update):
        await update.message.reply_text("Akses ditolak.")
        return

    if not context.args:
        await update.message.reply_text("Format: /stock_add <product_id>")
        return

    try:
        product_id = int(context.args[0])
    except ValueError:
        await update.message.reply_text("product_id harus angka.")
        return

    context.user_data["await_stock_product_id"] = product_id
    await update.message.reply_text(
        "Kirim blok stok sekarang.\n"
        "Satu pesan blok stok akan disimpan sebagai satu unit stok."
    )


async def product_suspend_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    db_user, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await update.message.reply_text("Akses ditolak.")
        return

    if not context.args:
        await update.message.reply_text("Format: /product_suspend <product_id>")
        return

    try:
        product_id = int(context.args[0])
    except ValueError:
        await update.message.reply_text("product_id harus angka.")
        return

    with get_session() as session:
        try:
            product = suspend_product(session, product_id=product_id, suspended=True, actor_id=db_user.id)
        except ValueError as exc:
            await update.message.reply_text(str(exc))
            return

    await update.message.reply_text(f"Produk {product.id} disuspend.")


async def product_unsuspend_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    db_user, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await update.message.reply_text("Akses ditolak.")
        return

    if not context.args:
        await update.message.reply_text("Format: /product_unsuspend <product_id>")
        return

    try:
        product_id = int(context.args[0])
    except ValueError:
        await update.message.reply_text("product_id harus angka.")
        return

    with get_session() as session:
        try:
            product = suspend_product(session, product_id=product_id, suspended=False, actor_id=db_user.id)
        except ValueError as exc:
            await update.message.reply_text(str(exc))
            return

    await update.message.reply_text(f"Produk {product.id} diaktifkan kembali.")


async def product_delete_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    db_user, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await update.message.reply_text("Akses ditolak.")
        return

    if not context.args:
        await update.message.reply_text("Format: /product_delete <product_id>")
        return

    try:
        product_id = int(context.args[0])
    except ValueError:
        await update.message.reply_text("product_id harus angka.")
        return

    with get_session() as session:
        try:
            delete_product(session, product_id=product_id, actor_id=db_user.id)
        except ValueError as exc:
            await update.message.reply_text(str(exc))
            return

    await update.message.reply_text(f"Produk {product_id} dihapus.")


async def buy_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    db_user, role = await _ensure_user(update)
    if role != "customer":
        await update.message.reply_text("Admin tidak bisa checkout sebagai customer.")
        return

    if len(context.args) < 2:
        await update.message.reply_text("Format: /buy <product_id> <qty>")
        return

    try:
        product_id = int(context.args[0])
        qty = int(context.args[1])
    except ValueError:
        await update.message.reply_text("product_id dan qty harus angka.")
        return

    with get_session() as session:
        user = get_user_by_telegram_id(session, db_user.telegram_id)
        if user is None:
            await update.message.reply_text("User tidak ditemukan. Coba /start lagi.")
            return
        try:
            order, payment = create_checkout(session, customer=user, product_id=product_id, quantity=qty)
        except ValueError as exc:
            await update.message.reply_text(str(exc))
            return

    lines = [
        "Checkout berhasil dibuat.",
        f"Order Ref: {order.order_ref}",
        f"Payment Ref: {payment.payment_ref}",
        f"Total bayar: {_format_rupiah(payment.expected_amount)}",
        "Silakan transfer sesuai nominal. Sistem akan konfirmasi otomatis.",
    ]
    await update.message.reply_text("\n".join(lines))

    qris_path = settings.qris_file_path
    if qris_path.exists():
        try:
            with qris_path.open("rb") as fh:
                await update.message.reply_photo(photo=fh, caption="QRIS pembayaran")
        except Exception as exc:
            logger.warning("Gagal kirim QRIS: %s", exc)


async def my_orders_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    db_user, _ = await _ensure_user(update)
    with get_session() as session:
        user = get_user_by_telegram_id(session, db_user.telegram_id)
        if user is None:
            await update.message.reply_text("User tidak ditemukan.")
            return
        orders = list_recent_orders_by_customer(session, customer_id=user.id, limit=10)

    if not orders:
        await update.message.reply_text("Belum ada pesanan.")
        return

    lines = ["Pesanan terbaru:"]
    for order in orders:
        lines.append(
            f"{order.order_ref} | status={order.status} | total={_format_rupiah(order.total_amount)}"
        )
    await update.message.reply_text("\n".join(lines))


async def broadcast_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    db_user, _ = await _ensure_user(update)
    if not _ensure_admin(update):
        await update.message.reply_text("Akses ditolak.")
        return

    if not context.args:
        await update.message.reply_text("Format: /broadcast <pesan>")
        return

    message = " ".join(context.args)
    with get_session() as session:
        sent, failed = await broadcast_to_customers(
            session=session,
            bot=context.bot,
            admin_user_id=db_user.id,
            message=message,
        )

    await update.message.reply_text(f"Broadcast selesai. Sent={sent}, Failed={failed}")


async def set_qris_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    await _ensure_user(update)
    if not _ensure_admin(update):
        await update.message.reply_text("Akses ditolak.")
        return

    context.user_data["await_qris_image"] = True
    await update.message.reply_text("Kirim gambar QRIS sekarang (sebagai foto).")


async def update_check_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    await _ensure_user(update)
    if not _ensure_admin(update):
        await update.message.reply_text("Akses ditolak.")
        return

    script_path = Path(settings.project_root / "ops" / "update_manager.sh")
    if not script_path.exists():
        await update.message.reply_text("Script update tidak ditemukan.")
        return

    proc = await asyncio.to_thread(
        subprocess.run,
        ["bash", str(script_path), "check"],
        cwd=str(settings.project_root),
        capture_output=True,
        text=True,
        check=False,
    )

    output = (proc.stdout or proc.stderr or "Tidak ada output").strip()
    if len(output) > 3500:
        output = output[:3500] + "\n..."
    await update.message.reply_text(output)


async def update_apply_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None:
        return

    await _ensure_user(update)
    if not _ensure_admin(update):
        await update.message.reply_text("Akses ditolak.")
        return

    script_path = Path(settings.project_root / "ops" / "update_manager.sh")
    if not script_path.exists():
        await update.message.reply_text("Script update tidak ditemukan.")
        return

    await update.message.reply_text("Menjalankan update. Bot bisa restart otomatis.")
    await asyncio.to_thread(
        subprocess.run,
        ["bash", str(script_path), "update"],
        cwd=str(settings.project_root),
        capture_output=True,
        text=True,
        check=False,
    )


async def text_router(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None or update.message.text is None:
        return

    db_user, role = await _ensure_user(update)
    text = update.message.text.strip()

    if role == "admin" and "await_stock_product_id" in context.user_data:
        product_id = int(context.user_data.pop("await_stock_product_id"))
        with get_session() as session:
            try:
                stock = add_stock_block(session, product_id=product_id, raw_text=text, actor_id=db_user.id)
            except ValueError as exc:
                await update.message.reply_text(f"Gagal menambah stok: {exc}")
                return
        await update.message.reply_text(f"Stok ditambahkan. ID stok: {stock.id}")
        return

    if text.lower() == "katalog":
        await catalog_handler(update, context)
        return
    if text.lower() == "pesanan saya":
        await my_orders_handler(update, context)
        return
    if role == "admin" and text.lower() == "broadcast":
        await update.message.reply_text("Gunakan /broadcast <pesan>")
        return
    if role == "admin" and text.lower() == "konfigurasi payment":
        await update.message.reply_text("Gunakan /set_qris untuk upload gambar QRIS")
        return
    if role == "admin" and text.lower() == "update bot tele":
        await update.message.reply_text("Gunakan /update_check atau /update_apply")
        return


async def photo_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if update.message is None or not update.message.photo:
        return

    await _ensure_user(update)
    if not _ensure_admin(update):
        return

    if not context.user_data.get("await_qris_image"):
        return

    context.user_data["await_qris_image"] = False
    settings.qris_file_path.parent.mkdir(parents=True, exist_ok=True)

    image = update.message.photo[-1]
    telegram_file = await image.get_file()
    await telegram_file.download_to_drive(custom_path=str(settings.qris_file_path))
    await update.message.reply_text("QRIS berhasil disimpan.")


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

    application.add_handler(MessageHandler(filters.PHOTO, photo_handler))
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, text_router))
