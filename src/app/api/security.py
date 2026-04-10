from __future__ import annotations

import hashlib
import hmac
import time
from typing import Any

from fastapi import HTTPException


def request_hash(body: bytes) -> str:
    return hashlib.sha256(body).hexdigest()


def build_signature(shared_secret: str, timestamp: str, body: bytes) -> str:
    message = timestamp.encode("utf-8") + b"." + body
    return hmac.new(shared_secret.encode("utf-8"), message, hashlib.sha256).hexdigest()


def normalize_idempotency_key(value: str) -> str:
    key = (value or "").strip()
    if not key:
        raise HTTPException(status_code=400, detail="X-Idempotency-Key wajib diisi")
    if len(key) > 128:
        raise HTTPException(status_code=400, detail="X-Idempotency-Key terlalu panjang")
    return key


def verify_signed_headers_or_raise(
    *,
    shared_secret: str,
    signature: str,
    timestamp: str,
    raw_body: bytes,
    ttl_seconds: int,
) -> None:
    if not signature or not timestamp:
        raise HTTPException(status_code=401, detail="Header signature belum lengkap")

    if not timestamp.isdigit():
        raise HTTPException(status_code=401, detail="X-Timestamp harus unix timestamp")

    now = int(time.time())
    ts = int(timestamp)
    if abs(now - ts) > max(1, ttl_seconds):
        raise HTTPException(status_code=401, detail="X-Timestamp kadaluarsa")

    expected = build_signature(shared_secret=shared_secret, timestamp=timestamp, body=raw_body)
    if not hmac.compare_digest(expected, signature):
        raise HTTPException(status_code=401, detail="Signature tidak valid")


def make_response_payload(
    *,
    status: str,
    message: str,
    matched_chat_id: int | None,
    extras: dict[str, Any] | None = None,
) -> dict[str, Any]:
    payload: dict[str, Any] = {
        "status": status,
        "message": message,
        "matched_chat_id": matched_chat_id,
    }
    if extras:
        payload.update(extras)
    return payload
