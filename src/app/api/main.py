from __future__ import annotations

import hmac
import logging
import time
from typing import Any

from fastapi import FastAPI, Header, HTTPException, Request
from pydantic import BaseModel, Field, ValidationError
from telegram import Bot, InlineKeyboardButton, InlineKeyboardMarkup

from app.api.listener_events import (
    create_event,
    get_event_by_key,
    parse_cached_response,
    update_event_response,
)
from app.api.security import (
    make_response_payload,
    normalize_idempotency_key,
    request_hash,
    verify_signed_headers_or_raise,
)
from app.bot.services.order_service import reconcile_payment
from app.common.config import get_settings
from app.common.logging import configure_logging
from app.common.roles import get_primary_admin_id
from app.db.bootstrap import init_db
from app.db.database import get_session

logger = logging.getLogger(__name__)
settings = get_settings()
app = FastAPI(title="Bot Jualan Listener API", version="0.1.0")


class PaymentListenerPayload(BaseModel):
    secret: str | None = None
    amount: int = Field(gt=0)
    source_app: str = Field(default="unknown")
    reference: str | None = None
    raw_text: str | None = None
    metadata: dict[str, Any] | None = None


class ConnectionTestPayload(BaseModel):
    secret: str | None = None
    device_id: str | None = None
    app_version: str | None = None


def _verify_auth(
    *,
    payload_secret: str | None,
    x_signature: str,
    x_timestamp: str,
    raw_body: bytes,
) -> None:
    has_headers = bool(x_signature and x_timestamp)
    if has_headers:
        verify_signed_headers_or_raise(
            shared_secret=settings.listener_shared_secret,
            signature=x_signature,
            timestamp=x_timestamp,
            raw_body=raw_body,
            ttl_seconds=settings.listener_signature_ttl_seconds,
        )
        return

    if settings.listener_allow_legacy_secret:
        if payload_secret and hmac.compare_digest(payload_secret, settings.listener_shared_secret):
            return

    raise HTTPException(
        status_code=401,
        detail="Autentikasi gagal. Gunakan header X-Signature + X-Timestamp.",
    )


@app.on_event("startup")
def startup_event() -> None:
    configure_logging()
    init_db()


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/listener/test-connection")
async def test_connection(
    request: Request,
    x_signature: str = Header(default="", alias="X-Signature"),
    x_timestamp: str = Header(default="", alias="X-Timestamp"),
) -> dict[str, Any]:
    raw_body = await request.body()
    try:
        payload = ConnectionTestPayload.model_validate_json(raw_body or b"{}")
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc

    _verify_auth(
        payload_secret=payload.secret,
        x_signature=x_signature,
        x_timestamp=x_timestamp,
        raw_body=raw_body or b"{}",
    )

    return {
        "status": "ok",
        "message": "Koneksi listener valid",
        "server_time": int(time.time()),
    }


@app.post("/listener/payment")
async def payment_listener(
    request: Request,
    x_idempotency_key: str = Header(default="", alias="X-Idempotency-Key"),
    x_signature: str = Header(default="", alias="X-Signature"),
    x_timestamp: str = Header(default="", alias="X-Timestamp"),
) -> dict[str, Any]:
    raw_body = await request.body()
    try:
        payload = PaymentListenerPayload.model_validate_json(raw_body)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc

    _verify_auth(
        payload_secret=payload.secret,
        x_signature=x_signature,
        x_timestamp=x_timestamp,
        raw_body=raw_body,
    )

    idempotency_key = normalize_idempotency_key(x_idempotency_key)
    body_hash = request_hash(raw_body)

    request_payload = payload.model_dump()

    with get_session() as session:
        existing_event = get_event_by_key(session, idempotency_key)
        if existing_event is not None:
            if existing_event.request_hash and existing_event.request_hash != body_hash:
                raise HTTPException(
                    status_code=409,
                    detail="Idempotency key sudah dipakai untuk payload berbeda.",
                )
            cached = parse_cached_response(existing_event)
            cached["idempotent_replay"] = True
            return cached

        event = create_event(session, idempotency_key, body_hash)

        reconcile_result = reconcile_payment(
            session=session,
            amount=payload.amount,
            source_app=payload.source_app,
            reference=payload.reference,
            raw_payload=request_payload,
        )

        status = reconcile_result.status
        message = reconcile_result.message
        customer_chat_id = reconcile_result.customer_chat_id
        delivery_message = reconcile_result.delivery_message

        notify_sent = False
        notify_error = ""

        if status == "paid" and customer_chat_id and delivery_message:
            if not settings.bot_token:
                logger.error("BOT_TOKEN kosong, tidak bisa kirim notifikasi sukses")
                notify_error = "BOT_TOKEN kosong"
            else:
                try:
                    bot = Bot(token=settings.bot_token)

                    if reconcile_result.checkout_chat_id and reconcile_result.checkout_message_id:
                        try:
                            await bot.delete_message(
                                chat_id=reconcile_result.checkout_chat_id,
                                message_id=reconcile_result.checkout_message_id,
                            )
                        except Exception as exc:
                            logger.warning("Gagal hapus pesan checkout lama: %s", exc)

                    reply_markup = None
                    admin_id = get_primary_admin_id(settings.role_file_path)
                    if admin_id is not None:
                        reply_markup = InlineKeyboardMarkup(
                            [[InlineKeyboardButton("💬 Hubungi Admin", url=f"tg://user?id={admin_id}")]]
                        )

                    await bot.send_message(
                        chat_id=customer_chat_id,
                        text=delivery_message,
                        reply_markup=reply_markup,
                    )
                    notify_sent = True
                except Exception as exc:
                    logger.exception("Gagal kirim notifikasi ke customer: %s", exc)
                    notify_error = str(exc)

        extras = {
            "idempotent_replay": False,
            "idempotency_key": idempotency_key,
        }
        if status == "paid":
            extras.update({
                "notify_sent": notify_sent,
                "notify_error": notify_error,
            })

        response_payload = make_response_payload(
            status=status,
            message=message,
            matched_chat_id=customer_chat_id,
            extras=extras,
        )

        event_status = "processed"
        if status == "error":
            event_status = "failed"

        update_event_response(
            session=session,
            event=event,
            status=event_status,
            response_payload=response_payload,
        )

    return response_payload
