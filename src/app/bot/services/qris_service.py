from __future__ import annotations

from io import BytesIO

from sqlalchemy.orm import Session

from app.bot.services.audit_service import append_audit
from app.bot.services.settings_service import get_setting, set_setting

QRIS_STATIC_PAYLOAD_KEY = "qris_static_payload"


QRField = tuple[str, str]


def get_qris_static_payload(session: Session) -> str:
    raw = get_setting(session, key=QRIS_STATIC_PAYLOAD_KEY, default="")
    return _normalize_payload(raw, allow_empty=True)


def set_qris_static_payload(session: Session, payload: str, actor_id: int | None = None) -> str:
    normalized_input = _normalize_payload(payload)
    normalized, _ = _parse_payload_with_space_fallback(normalized_input)
    # Validate once at save time so checkout can fail fast with clear fallback.
    build_dynamic_qris_payload(normalized, amount=1000)

    set_setting(session, key=QRIS_STATIC_PAYLOAD_KEY, value=normalized)
    append_audit(
        session,
        action="payment_set_qris_payload",
        actor_id=actor_id,
        entity_type="setting",
        entity_id=QRIS_STATIC_PAYLOAD_KEY,
        detail=f"length={len(normalized)}",
    )
    return normalized


def clear_qris_static_payload(session: Session, actor_id: int | None = None) -> None:
    set_setting(session, key=QRIS_STATIC_PAYLOAD_KEY, value="")
    append_audit(
        session,
        action="payment_clear_qris_payload",
        actor_id=actor_id,
        entity_type="setting",
        entity_id=QRIS_STATIC_PAYLOAD_KEY,
        detail="cleared",
    )


def build_dynamic_qris_payload(static_payload: str, amount: int) -> str:
    if amount <= 0:
        raise ValueError("Nominal QRIS harus lebih dari 0.")

    normalized_input = _normalize_payload(static_payload)
    normalized, fields = _parse_payload_with_space_fallback(normalized_input)
    fields_without_crc, original_crc = _strip_crc_field(fields)
    _validate_crc_if_present(fields_without_crc, original_crc)

    fields_without_crc = _upsert_tag(fields_without_crc, tag="01", value="12", preferred_after=("00",))
    fields_without_crc = _upsert_tag(
        fields_without_crc,
        tag="54",
        value=str(int(amount)),
        preferred_after=("53", "52", "01", "00"),
    )

    payload_without_crc = _encode_tlv_fields(fields_without_crc)
    crc_base = payload_without_crc + "6304"
    crc = _crc16_ccitt_false(crc_base.encode("ascii"))
    return crc_base + crc


def build_dynamic_qris_png(static_payload: str, amount: int) -> bytes:
    payload = build_dynamic_qris_payload(static_payload, amount)
    try:
        import qrcode
        from qrcode.image.pure import PyPNGImage
    except ImportError as exc:  # pragma: no cover - dependency gate
        raise RuntimeError("Library qrcode belum terpasang.") from exc

    qr = qrcode.QRCode(
        version=None,
        error_correction=qrcode.constants.ERROR_CORRECT_M,
        box_size=10,
        border=4,
    )
    qr.add_data(payload)
    qr.make(fit=True)

    image = qr.make_image(image_factory=PyPNGImage)
    buffer = BytesIO()
    image.save(buffer)
    return buffer.getvalue()


def extract_qris_payload_from_image(image_bytes: bytes) -> str:
    if not image_bytes:
        raise ValueError("Gambar QRIS kosong.")

    try:
        import cv2
        import numpy as np
    except ImportError as exc:  # pragma: no cover - dependency gate
        raise RuntimeError("Library opencv-python-headless belum terpasang.") from exc

    data = np.frombuffer(image_bytes, dtype=np.uint8)
    image = cv2.imdecode(data, cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError("Gagal membaca gambar QRIS.")

    for candidate in _build_decode_candidates(image, cv2):
        decoded = _decode_qr_text(candidate, cv2)
        if decoded:
            normalized_input = _normalize_payload(decoded)
            normalized, _ = _parse_payload_with_space_fallback(normalized_input)
            return normalized

    raise ValueError("QR code tidak terdeteksi pada gambar.")


def _normalize_payload(payload: str, allow_empty: bool = False) -> str:
    normalized = str(payload).strip().replace("\r", "").replace("\n", "").replace("\t", "")
    if not normalized:
        if allow_empty:
            return ""
        raise ValueError("Payload QRIS tidak boleh kosong.")
    if any(ord(ch) > 127 for ch in normalized):
        raise ValueError("Payload QRIS harus ASCII.")
    return normalized


def _parse_payload_with_space_fallback(payload: str) -> tuple[str, list[QRField]]:
    try:
        return payload, _parse_tlv(payload)
    except ValueError as original_exc:
        if " " not in payload:
            raise

        compact_payload = payload.replace(" ", "")
        try:
            fields = _parse_tlv(compact_payload)
        except ValueError:
            raise original_exc

        return compact_payload, fields


def _build_decode_candidates(image: object, cv2: object) -> list[object]:
    candidates: list[object] = [image]

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    candidates.append(gray)

    _, otsu = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    candidates.append(otsu)

    enlarged = cv2.resize(gray, None, fx=1.8, fy=1.8, interpolation=cv2.INTER_CUBIC)
    candidates.append(enlarged)

    return candidates


def _decode_qr_text(image: object, cv2: object) -> str:
    detector = cv2.QRCodeDetector()
    data, points, _ = detector.detectAndDecode(image)
    if points is not None and data:
        return str(data)

    try:
        ok, decoded_list, _, _ = detector.detectAndDecodeMulti(image)
    except Exception:
        return ""

    if not ok or not decoded_list:
        return ""

    for item in decoded_list:
        if item:
            return str(item)

    return ""


def _parse_tlv(payload: str) -> list[QRField]:
    fields: list[QRField] = []
    index = 0
    while index < len(payload):
        if index + 4 > len(payload):
            raise ValueError("Payload QRIS tidak valid: header TLV terpotong.")

        tag = payload[index : index + 2]
        length_text = payload[index + 2 : index + 4]
        if not tag.isdigit() or not length_text.isdigit():
            raise ValueError("Payload QRIS tidak valid: format TLV salah.")

        value_length = int(length_text)
        value_start = index + 4
        value_end = value_start + value_length
        if value_end > len(payload):
            raise ValueError("Payload QRIS tidak valid: panjang field melebihi payload.")

        value = payload[value_start:value_end]
        fields.append((tag, value))
        index = value_end

    return fields


def _strip_crc_field(fields: list[QRField]) -> tuple[list[QRField], str | None]:
    crc_indexes = [idx for idx, (tag, _) in enumerate(fields) if tag == "63"]
    if not crc_indexes:
        return fields, None

    if len(crc_indexes) > 1 or crc_indexes[0] != len(fields) - 1:
        raise ValueError("Payload QRIS tidak valid: field CRC harus berada di akhir.")

    crc_value = fields[-1][1].upper()
    if len(crc_value) != 4:
        raise ValueError("Payload QRIS tidak valid: panjang CRC harus 4 karakter.")
    if any(ch not in "0123456789ABCDEF" for ch in crc_value):
        raise ValueError("Payload QRIS tidak valid: CRC harus heksadesimal.")

    return fields[:-1], crc_value


def _validate_crc_if_present(fields_without_crc: list[QRField], original_crc: str | None) -> None:
    if original_crc is None:
        return

    crc_base = _encode_tlv_fields(fields_without_crc) + "6304"
    expected_crc = _crc16_ccitt_false(crc_base.encode("ascii"))
    if expected_crc != original_crc:
        raise ValueError("Payload QRIS tidak valid: CRC tidak cocok.")


def _upsert_tag(
    fields: list[QRField],
    *,
    tag: str,
    value: str,
    preferred_after: tuple[str, ...],
) -> list[QRField]:
    cleaned = [(field_tag, field_value) for field_tag, field_value in fields if field_tag != tag]

    insert_at = len(cleaned)
    for preferred_tag in preferred_after:
        for idx, (field_tag, _) in enumerate(cleaned):
            if field_tag == preferred_tag:
                insert_at = idx + 1
                break
        if insert_at != len(cleaned):
            break

    cleaned.insert(insert_at, (tag, value))
    return cleaned


def _encode_tlv_fields(fields: list[QRField]) -> str:
    parts: list[str] = []
    for tag, value in fields:
        if len(tag) != 2 or not tag.isdigit():
            raise ValueError("Payload QRIS tidak valid: tag harus 2 digit.")
        if any(ord(ch) > 127 for ch in value):
            raise ValueError("Payload QRIS tidak valid: value harus ASCII.")
        if len(value) > 99:
            raise ValueError(f"Payload QRIS tidak valid: panjang field {tag} melebihi 99.")
        parts.append(f"{tag}{len(value):02d}{value}")
    return "".join(parts)


def _crc16_ccitt_false(data: bytes) -> str:
    crc = 0xFFFF
    for byte in data:
        crc ^= byte << 8
        for _ in range(8):
            if crc & 0x8000:
                crc = ((crc << 1) ^ 0x1021) & 0xFFFF
            else:
                crc = (crc << 1) & 0xFFFF
    return f"{crc:04X}"
