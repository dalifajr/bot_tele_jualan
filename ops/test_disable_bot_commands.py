from __future__ import annotations

import asyncio
import os
import sys
from pathlib import Path
from unittest.mock import AsyncMock, MagicMock, patch

# Ensure src in sys.path
sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "src"))

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8")

# Set required test env
os.environ["DATABASE_URL"] = "sqlite:///./data/test_bot_cmd.db"
os.environ["BOT_TOKEN"] = "123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
os.environ["WEBSITE_ENABLED"] = "true"
os.environ["WEBSITE_DOMAIN"] = "https://toko.example.com"
os.environ["DISABLE_BOT_COMMANDS"] = "false"

from app.common.config import get_settings
from app.db.bootstrap import init_db
from app.db.database import get_session
from app.bot.services.user_service import upsert_user
from app.bot.handlers.main import (
    _send_bot_commands_disabled_notice,
    start_handler,
    help_handler,
    catalog_handler,
    buy_handler,
    my_orders_handler,
    order_status_handler,
    reorder_handler,
    callback_router,
    text_router,
)


def _make_mock_update(user_id: int, username: str = "testuser", text: str | None = None, callback_data: str | None = None):
    update = MagicMock()
    user = MagicMock()
    user.id = user_id
    user.username = username
    user.full_name = f"Full {username}"
    update.effective_user = user

    chat = MagicMock()
    chat.id = user_id
    chat.type = "private"
    update.effective_chat = chat

    if callback_data is not None:
        query = MagicMock()
        query.data = callback_data
        query.message = MagicMock()
        query.message.reply_text = AsyncMock()
        query.message.edit_text = AsyncMock()
        query.edit_message_text = AsyncMock()
        query.answer = AsyncMock()
        update.callback_query = query
        update.message = None
    else:
        update.callback_query = None
        message = MagicMock()
        message.message_id = 100
        message.text = text or ""
        message.photo = []
        message.document = None
        message.reply_text = AsyncMock()
        update.message = message

    return update


def _make_mock_context(args: list[str] | None = None):
    context = MagicMock()
    context.args = args or []
    context.user_data = {}
    context.bot = MagicMock()
    context.bot.send_message = AsyncMock()
    return context


def _get_reply_text(update):
    if update.callback_query:
        if update.callback_query.edit_message_text.called:
            call_args = update.callback_query.edit_message_text.call_args
            return call_args.kwargs.get("text") or (call_args[0][0] if call_args[0] else "")
        if update.callback_query.message and update.callback_query.message.reply_text.called:
            call_args = update.callback_query.message.reply_text.call_args
            return call_args.kwargs.get("text") or (call_args[0][0] if call_args[0] else "")
    if update.message and update.message.reply_text.called:
        call_args = update.message.reply_text.call_args
        return call_args.kwargs.get("text") or (call_args[0][0] if call_args[0] else "")
    return ""


async def run_tests():
    init_db()
    settings = get_settings()

    # Seed customer and admin
    with get_session() as session:
        customer_user = upsert_user(session, telegram_id=999901, username="cust_unit", full_name="Customer Unit", role="customer")
        admin_user = upsert_user(session, telegram_id=999902, username="admin_unit", full_name="Admin Unit", role="admin")

    # Patch admin check so 999902 is recognized as admin
    admin_id = 999902
    customer_id = 999901

    print("=== TEST 1: Config defaults and toggle ===")
    assert settings.disable_bot_commands is False
    settings.disable_bot_commands = True
    assert settings.disable_bot_commands is True
    print("✓ Test 1 Passed: Settings flag can be set and toggled.")

    with patch("app.bot.handlers.main.is_admin", side_effect=lambda tid, *args, **kwargs: tid == admin_id), \
         patch("app.bot.handlers.main._role_for_telegram_id", side_effect=lambda tid: "admin" if tid == admin_id else "customer"):

        print("\n=== TEST 2: Customer /start with DISABLE_BOT_COMMANDS = True ===")
        settings.disable_bot_commands = True
        up_cust = _make_mock_update(customer_id, "cust_unit", text="/start")
        ctx_cust = _make_mock_context()

        await start_handler(up_cust, ctx_cust)
        # Verify reply_text was called with migration notice
        reply_text = _get_reply_text(up_cust)
        assert "Pemberitahuan Migrasi Layanan" in reply_text
        assert "Website Resmi" in reply_text
        # Verify WebApp button exists
        call_args = up_cust.message.reply_text.call_args
        reply_markup = call_args.kwargs.get("reply_markup") or (call_args[0][1] if len(call_args[0]) > 1 else None)
        buttons = reply_markup.inline_keyboard
        web_app_btn = any(btn.web_app is not None for row in buttons for btn in row)
        assert web_app_btn, "No WebAppInfo button in inline keyboard"
        print("✓ Test 2 Passed: Customer receives friendly migration notice with Mini App button on /start.")

        print("\n=== TEST 3: Admin /start with DISABLE_BOT_COMMANDS = True ===")
        up_adm = _make_mock_update(admin_id, "admin_unit", text="/start")
        ctx_adm = _make_mock_context()
        await start_handler(up_adm, ctx_adm)
        reply_adm = _get_reply_text(up_adm)
        assert "Katalog Admin" in reply_adm or "Halo, admin_unit" in reply_adm
        assert "Pemberitahuan Migrasi Layanan" not in reply_adm
        print("✓ Test 3 Passed: Admin can still access bot normally.")

        print("\n=== TEST 4: Customer /start weblink_ and weblogin_ deep-link works even when disabled ===")
        up_link = _make_mock_update(customer_id, "cust_unit", text="/start weblink_mocktoken")
        ctx_link = _make_mock_context(args=["weblink_mocktoken"])
        with patch("app.bot.handlers.main._handle_web_link_deeplink", new_callable=AsyncMock) as mock_link_handler:
            await start_handler(up_link, ctx_link)
            assert mock_link_handler.called, "weblink_ handler should have been called"

        up_login = _make_mock_update(customer_id, "cust_unit", text="/start weblogin_mocktoken")
        ctx_login = _make_mock_context(args=["weblogin_mocktoken"])
        with patch("app.bot.handlers.main._handle_web_login_deeplink", new_callable=AsyncMock) as mock_login_handler:
            await start_handler(up_login, ctx_login)
            assert mock_login_handler.called, "weblogin_ handler should have been called"
        print("✓ Test 4 Passed: Deeplinks weblink_ and weblogin_ are NOT blocked.")

        print("\n=== TEST 5: Customer commands (/help, /catalog, /buy, /myorders, /order_status, /reorder) ===")
        # /help
        up = _make_mock_update(customer_id, text="/help")
        ctx = _make_mock_context()
        await help_handler(up, ctx)
        assert "Pemberitahuan Migrasi Layanan" in _get_reply_text(up)

        # /catalog
        up = _make_mock_update(customer_id, text="/catalog")
        ctx = _make_mock_context()
        await catalog_handler(up, ctx)
        assert "Pemberitahuan Migrasi Layanan" in _get_reply_text(up)

        # /buy
        up = _make_mock_update(customer_id, text="/buy 1 1")
        ctx = _make_mock_context(args=["1", "1"])
        await buy_handler(up, ctx)
        assert "Pemberitahuan Migrasi Layanan" in _get_reply_text(up)

        # /myorders
        up = _make_mock_update(customer_id, text="/myorders")
        ctx = _make_mock_context()
        await my_orders_handler(up, ctx)
        assert "Pemberitahuan Migrasi Layanan" in _get_reply_text(up)

        # /order_status
        up = _make_mock_update(customer_id, text="/order_status ORD123")
        ctx = _make_mock_context(args=["ORD123"])
        await order_status_handler(up, ctx)
        assert "Pemberitahuan Migrasi Layanan" in _get_reply_text(up)

        # /reorder
        up = _make_mock_update(customer_id, text="/reorder ORD123")
        ctx = _make_mock_context(args=["ORD123"])
        await reorder_handler(up, ctx)
        assert "Pemberitahuan Migrasi Layanan" in _get_reply_text(up)
        print("✓ Test 5 Passed: All customer commands redirect to migration notice.")

        print("\n=== TEST 6: Customer callbacks and text router ===")
        # Callback buy:1:1
        up_cb = _make_mock_update(customer_id, callback_data="buy:1:1")
        ctx_cb = _make_mock_context()
        await callback_router(up_cb, ctx_cb)
        assert "Pemberitahuan Migrasi Layanan" in _get_reply_text(up_cb)
        print("✓ Test 6a Passed: Callback buy: intercepted.")

        # Callback cus:cat
        up_cb = _make_mock_update(customer_id, callback_data="cus:cat")
        ctx_cb = _make_mock_context()
        await callback_router(up_cb, ctx_cb)
        assert "Pemberitahuan Migrasi Layanan" in _get_reply_text(up_cb)
        print("✓ Test 6b Passed: Callback cus:cat intercepted.")

        # Text router fallback
        up_txt = _make_mock_update(customer_id, text="halo apa kabar")
        ctx_txt = _make_mock_context()
        await text_router(up_txt, ctx_txt)
        assert "Pemberitahuan Migrasi Layanan" in _get_reply_text(up_txt)
        print("✓ Test 6c Passed: Text router fallback delivers migration notice.")

        print("\n=== TEST 7: Normal behavior when DISABLE_BOT_COMMANDS = False ===")
        settings.disable_bot_commands = False
        up = _make_mock_update(customer_id, text="/help")
        ctx = _make_mock_context()
        await help_handler(up, ctx)
        assert "Pemberitahuan Migrasi Layanan" not in _get_reply_text(up)
        print("✓ Test 7 Passed: When disabled is False, normal customer commands work.")

    print("\nALL AUTOMATED UNIT & INTEGRATION TESTS PASSED SUCCESSFULLY! 🎉")


if __name__ == "__main__":
    asyncio.run(run_tests())
