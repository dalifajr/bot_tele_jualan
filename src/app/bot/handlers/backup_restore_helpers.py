"""
Backup and Restore helper functions for main.py handler.
This file contains the helper functions that will be inserted into main.py
"""

# These functions should be inserted in main.py after line 1449 (after _send_admin_complaint_detail)

async def _send_admin_backup_restore_menu(update: Update) -> None:
    """Send admin backup/restore menu."""
    await _respond(
        update,
        (
            "💾 <b>Backup & Restore Data</b>\n\n"
            "<b>Backup:</b> Ekspor semua data penjualan (produk, akun, order, komplain) ke file ZIP\n"
            "<b>Restore:</b> Impor data dari file backup dengan deteksi duplikat otomatis\n\n"
            f"{_admin_footer_text()}"
        ),
        _backup_restore_menu_keyboard(),
        parse_mode=ParseMode.HTML,
    )


def _backup_restore_menu_keyboard() -> InlineKeyboardMarkup:
    """Build backup/restore menu keyboard."""
    return InlineKeyboardMarkup(
        [
            [InlineKeyboardButton("💾 Mulai Backup", callback_data="adm:bak:backup:start")],
            [InlineKeyboardButton("📥 Mulai Restore", callback_data="adm:bak:restore:start")],
            [InlineKeyboardButton("⬅️ Kembali", callback_data="back:main")],
        ]
    )


async def _handle_backup_start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Handle backup start callback."""
    query = update.callback_query
    if query is None:
        return

    try:
        # Send progress message
        progress_msg = await context.bot.send_message(
            chat_id=query.message.chat_id,
            text="⏳ <b>Memulai Backup Data...</b>\n\n0% selesai",
            parse_mode=ParseMode.HTML,
        )

        # Collect all data
        with get_session() as session:
            backup_data = collect_all_backup_data(session)

        # Update progress
        await context.bot.edit_message_text(
            chat_id=query.message.chat_id,
            message_id=progress_msg.message_id,
            text="⏳ <b>Mengompresi Data ke ZIP...</b>\n\n50% selesai",
            parse_mode=ParseMode.HTML,
        )

        # Create ZIP file in temp directory
        from tempfile import TemporaryDirectory
        import os

        with TemporaryDirectory() as tmpdir:
            zip_path = os.path.join(tmpdir, f"backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}.zip")
            serialize_backup_to_zip(backup_data, zip_path)

            # Update progress to 90%
            await context.bot.edit_message_text(
                chat_id=query.message.chat_id,
                message_id=progress_msg.message_id,
                text="⏳ <b>Mengirim File Backup...</b>\n\n90% selesai",
                parse_mode=ParseMode.HTML,
            )

            # Send the backup file
            with open(zip_path, "rb") as backup_file:
                await context.bot.send_document(
                    chat_id=query.message.chat_id,
                    document=backup_file,
                    caption="📦 <b>Backup Data Selesai</b>\n\n" + get_backup_summary(backup_data),
                    parse_mode=ParseMode.HTML,
                )

            # Delete progress message
            await context.bot.delete_message(
                chat_id=query.message.chat_id,
                message_id=progress_msg.message_id,
            )

            # Send completion message
            await context.bot.send_message(
                chat_id=query.message.chat_id,
                text="✅ <b>Backup Selesai</b>\n\nFile backup siap diunduh di atas.",
                reply_markup=_back_keyboard("main"),
                parse_mode=ParseMode.HTML,
            )

    except Exception as e:
        logger.error(f"Error during backup: {e}", exc_info=True)
        await _respond(
            update,
            f"❌ <b>Backup Gagal</b>\n\nError: {str(e)}",
            _back_keyboard("main"),
            parse_mode=ParseMode.HTML,
        )


async def _handle_restore_start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Handle restore start - ask user to upload backup file."""
    query = update.callback_query
    if query is None:
        return

    _set_flow(context, "admin_restore_upload", {})

    await context.bot.send_message(
        chat_id=query.message.chat_id,
        text=(
            "📥 <b>Upload File Backup</b>\n\n"
            "Silakan upload file ZIP backup yang ingin di-restore.\n"
            "Sistem akan otomatis mendeteksi dan melewatkan data duplikat."
        ),
        parse_mode=ParseMode.HTML,
    )

    # Acknowledge the callback
    if query:
        await query.answer()
