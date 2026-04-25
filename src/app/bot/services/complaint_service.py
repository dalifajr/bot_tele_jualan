from __future__ import annotations

import secrets
from dataclasses import dataclass
from datetime import datetime

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.bot.services.audit_service import append_audit
from app.db.models import ComplaintAttachment, ComplaintCase, Order, User

COMPLAINT_STATUS_NEW = "new"
COMPLAINT_STATUS_IN_PROCESS = "in_process"
COMPLAINT_STATUS_AWAITING_CUSTOMER_REFUND_DETAILS = "awaiting_customer_refund_details"
COMPLAINT_STATUS_AWAITING_ADMIN_REFUND_TRANSFER = "awaiting_admin_refund_transfer"
COMPLAINT_STATUS_REFUND_COMPLETED = "refund_completed"
COMPLAINT_STATUS_REPLACEMENT_SENT = "replacement_sent"
COMPLAINT_STATUS_REJECTED = "rejected"

COMPLAINT_STATUS_GROUP_NEW = {COMPLAINT_STATUS_NEW}
COMPLAINT_STATUS_GROUP_PROCESS = {
    COMPLAINT_STATUS_IN_PROCESS,
    COMPLAINT_STATUS_AWAITING_CUSTOMER_REFUND_DETAILS,
    COMPLAINT_STATUS_AWAITING_ADMIN_REFUND_TRANSFER,
}
COMPLAINT_STATUS_GROUP_DONE = {
    COMPLAINT_STATUS_REFUND_COMPLETED,
    COMPLAINT_STATUS_REPLACEMENT_SENT,
    COMPLAINT_STATUS_REJECTED,
}


@dataclass(frozen=True)
class ComplaintOrderOption:
    order_ref: str
    created_at: datetime
    status: str


@dataclass(frozen=True)
class ComplaintListItem:
    complaint_id: int
    complaint_ref: str
    customer_display: str
    customer_telegram_id: int
    order_ref: str
    order_created_at: datetime | None
    complaint_at: datetime
    status: str


@dataclass(frozen=True)
class ComplaintDetail:
    complaint_id: int
    complaint_ref: str
    customer_id: int
    customer_display: str
    customer_telegram_id: int
    order_ref: str
    order_created_at: datetime | None
    complaint_at: datetime
    status: str
    complaint_text: str
    attachment_file_ids: list[str]
    refund_target_detail: str | None
    refund_note: str | None


@dataclass(frozen=True)
class ComplaintCreateResult:
    complaint_id: int
    complaint_ref: str
    order_ref: str
    order_created_at: datetime | None
    complaint_at: datetime
    complaint_text: str
    attachment_count: int


def _utcnow() -> datetime:
    return datetime.utcnow()


def _generate_complaint_ref() -> str:
    ts = _utcnow().strftime("%Y%m%d%H%M%S%f")
    tail = secrets.token_hex(3).upper()
    return f"CMP{ts}{tail}"


def _customer_display(user: User | None, snapshot_username: str | None, telegram_id: int) -> str:
    if user is not None and user.username:
        return f"@{user.username}"
    if snapshot_username:
        return f"@{snapshot_username}"
    if user is not None and user.full_name:
        return user.full_name
    return str(telegram_id)


def list_customer_order_options_for_complaint(
    session: Session,
    *,
    customer_id: int,
) -> list[ComplaintOrderOption]:
    rows = list(
        session.scalars(
            select(Order)
            .where(Order.customer_id == customer_id)
            .order_by(Order.created_at.desc(), Order.id.desc())
        ).all()
    )
    return [
        ComplaintOrderOption(
            order_ref=row.order_ref,
            created_at=row.created_at,
            status=row.status,
        )
        for row in rows
    ]


def create_customer_complaint(
    session: Session,
    *,
    customer: User,
    order_ref: str,
    complaint_text: str,
    attachment_file_ids: list[str],
) -> ComplaintCreateResult:
    normalized_ref = order_ref.strip().upper()
    normalized_text = complaint_text.strip()
    if not normalized_text:
        raise ValueError("Isi komplain tidak boleh kosong.")

    order = session.scalar(
        select(Order).where(
            Order.customer_id == int(customer.id),
            Order.order_ref == normalized_ref,
        )
    )
    if order is None:
        raise ValueError("Nomor order tidak ditemukan untuk akun kamu.")

    complaint = ComplaintCase(
        complaint_ref=_generate_complaint_ref(),
        customer_id=int(customer.id),
        customer_telegram_id=int(customer.telegram_id),
        customer_username_snapshot=(customer.username or ""),
        order_id=int(order.id),
        order_ref_snapshot=order.order_ref,
        order_created_at_snapshot=order.created_at,
        complaint_text=normalized_text,
        status=COMPLAINT_STATUS_NEW,
        created_at=_utcnow(),
    )
    session.add(complaint)
    session.flush()

    sanitized_file_ids = [str(file_id).strip() for file_id in attachment_file_ids if str(file_id).strip()]
    for file_id in sanitized_file_ids:
        session.add(
            ComplaintAttachment(
                complaint_id=int(complaint.id),
                file_id=file_id,
            )
        )

    append_audit(
        session,
        action="complaint_create",
        actor_id=int(customer.id),
        entity_type="complaint_case",
        entity_id=str(complaint.id),
        detail=(
            f"complaint_ref={complaint.complaint_ref}; order_ref={order.order_ref}; "
            f"attachment_count={len(sanitized_file_ids)}"
        ),
    )

    return ComplaintCreateResult(
        complaint_id=int(complaint.id),
        complaint_ref=complaint.complaint_ref,
        order_ref=order.order_ref,
        order_created_at=order.created_at,
        complaint_at=complaint.created_at,
        complaint_text=complaint.complaint_text,
        attachment_count=len(sanitized_file_ids),
    )


def list_complaints_by_statuses(session: Session, *, statuses: set[str]) -> list[ComplaintListItem]:
    if not statuses:
        return []

    rows = list(
        session.scalars(
            select(ComplaintCase)
            .where(ComplaintCase.status.in_(list(statuses)))
            .order_by(ComplaintCase.created_at.desc(), ComplaintCase.id.desc())
        ).all()
    )

    result: list[ComplaintListItem] = []
    for row in rows:
        user = session.get(User, int(row.customer_id))
        result.append(
            ComplaintListItem(
                complaint_id=int(row.id),
                complaint_ref=row.complaint_ref,
                customer_display=_customer_display(
                    user,
                    row.customer_username_snapshot,
                    int(row.customer_telegram_id),
                ),
                customer_telegram_id=int(row.customer_telegram_id),
                order_ref=row.order_ref_snapshot,
                order_created_at=row.order_created_at_snapshot,
                complaint_at=row.created_at,
                status=row.status,
            )
        )
    return result


def get_complaint_detail(session: Session, *, complaint_id: int) -> ComplaintDetail | None:
    complaint = session.get(ComplaintCase, int(complaint_id))
    if complaint is None:
        return None

    user = session.get(User, int(complaint.customer_id))
    attachments = list(
        session.scalars(
            select(ComplaintAttachment)
            .where(ComplaintAttachment.complaint_id == int(complaint.id))
            .order_by(ComplaintAttachment.id.asc())
        ).all()
    )

    return ComplaintDetail(
        complaint_id=int(complaint.id),
        complaint_ref=complaint.complaint_ref,
        customer_id=int(complaint.customer_id),
        customer_display=_customer_display(
            user,
            complaint.customer_username_snapshot,
            int(complaint.customer_telegram_id),
        ),
        customer_telegram_id=int(complaint.customer_telegram_id),
        order_ref=complaint.order_ref_snapshot,
        order_created_at=complaint.order_created_at_snapshot,
        complaint_at=complaint.created_at,
        status=complaint.status,
        complaint_text=complaint.complaint_text,
        attachment_file_ids=[item.file_id for item in attachments],
        refund_target_detail=complaint.refund_target_detail,
        refund_note=complaint.refund_note,
    )


def get_complaint_detail_by_ref(session: Session, *, complaint_ref: str) -> ComplaintDetail | None:
    row = session.scalar(select(ComplaintCase).where(ComplaintCase.complaint_ref == complaint_ref.strip()))
    if row is None:
        return None
    return get_complaint_detail(session, complaint_id=int(row.id))


def move_complaint_to_process(session: Session, *, complaint_id: int, actor_id: int | None) -> ComplaintDetail:
    complaint = session.get(ComplaintCase, int(complaint_id))
    if complaint is None:
        raise ValueError("Komplain tidak ditemukan.")
    if complaint.status != COMPLAINT_STATUS_NEW:
        raise ValueError("Komplain ini tidak berada pada status baru.")

    complaint.status = COMPLAINT_STATUS_IN_PROCESS
    complaint.updated_at = _utcnow()
    session.add(complaint)

    append_audit(
        session,
        action="complaint_mark_process",
        actor_id=actor_id,
        entity_type="complaint_case",
        entity_id=str(complaint.id),
        detail=f"complaint_ref={complaint.complaint_ref}",
    )
    detail = get_complaint_detail(session, complaint_id=int(complaint.id))
    if detail is None:
        raise ValueError("Komplain tidak ditemukan.")
    return detail


def reject_complaint(session: Session, *, complaint_id: int, actor_id: int | None, reason: str = "") -> ComplaintDetail:
    complaint = session.get(ComplaintCase, int(complaint_id))
    if complaint is None:
        raise ValueError("Komplain tidak ditemukan.")
    if complaint.status not in COMPLAINT_STATUS_GROUP_NEW | COMPLAINT_STATUS_GROUP_PROCESS:
        raise ValueError("Komplain ini tidak bisa ditolak pada status saat ini.")

    complaint.status = COMPLAINT_STATUS_REJECTED
    complaint.rejected_reason = reason.strip() or None
    complaint.closed_at = _utcnow()
    complaint.updated_at = _utcnow()
    session.add(complaint)

    append_audit(
        session,
        action="complaint_reject",
        actor_id=actor_id,
        entity_type="complaint_case",
        entity_id=str(complaint.id),
        detail=f"complaint_ref={complaint.complaint_ref}",
    )
    detail = get_complaint_detail(session, complaint_id=int(complaint.id))
    if detail is None:
        raise ValueError("Komplain tidak ditemukan.")
    return detail


def approve_complaint_refund(session: Session, *, complaint_id: int, actor_id: int | None) -> ComplaintDetail:
    complaint = session.get(ComplaintCase, int(complaint_id))
    if complaint is None:
        raise ValueError("Komplain tidak ditemukan.")
    if complaint.status != COMPLAINT_STATUS_IN_PROCESS:
        raise ValueError("Komplain harus berstatus proses untuk disetujui refund.")

    complaint.status = COMPLAINT_STATUS_AWAITING_CUSTOMER_REFUND_DETAILS
    complaint.refund_requested_at = _utcnow()
    complaint.updated_at = _utcnow()
    session.add(complaint)

    append_audit(
        session,
        action="complaint_refund_approved",
        actor_id=actor_id,
        entity_type="complaint_case",
        entity_id=str(complaint.id),
        detail=f"complaint_ref={complaint.complaint_ref}",
    )
    detail = get_complaint_detail(session, complaint_id=int(complaint.id))
    if detail is None:
        raise ValueError("Komplain tidak ditemukan.")
    return detail


def set_complaint_refund_target_from_customer(
    session: Session,
    *,
    complaint_id: int,
    customer_id: int,
    detail_text: str,
) -> ComplaintDetail:
    complaint = session.get(ComplaintCase, int(complaint_id))
    if complaint is None:
        raise ValueError("Komplain tidak ditemukan.")
    if int(complaint.customer_id) != int(customer_id):
        raise ValueError("Komplain tidak ditemukan untuk akun kamu.")
    if complaint.status != COMPLAINT_STATUS_AWAITING_CUSTOMER_REFUND_DETAILS:
        raise ValueError("Komplain ini belum meminta detail refund.")

    normalized_text = detail_text.strip()
    if not normalized_text:
        raise ValueError("Detail rekening/e-wallet tidak boleh kosong.")

    complaint.refund_target_detail = normalized_text
    complaint.refund_detail_received_at = _utcnow()
    complaint.status = COMPLAINT_STATUS_AWAITING_ADMIN_REFUND_TRANSFER
    complaint.updated_at = _utcnow()
    session.add(complaint)

    append_audit(
        session,
        action="complaint_refund_target_set",
        actor_id=int(customer_id),
        entity_type="complaint_case",
        entity_id=str(complaint.id),
        detail=f"complaint_ref={complaint.complaint_ref}",
    )
    detail = get_complaint_detail(session, complaint_id=int(complaint.id))
    if detail is None:
        raise ValueError("Komplain tidak ditemukan.")
    return detail


def mark_complaint_refund_transferred(
    session: Session,
    *,
    complaint_id: int,
    actor_id: int | None,
    proof_file_id: str,
    note: str,
) -> ComplaintDetail:
    complaint = session.get(ComplaintCase, int(complaint_id))
    if complaint is None:
        raise ValueError("Komplain tidak ditemukan.")
    if complaint.status != COMPLAINT_STATUS_AWAITING_ADMIN_REFUND_TRANSFER:
        raise ValueError("Komplain belum siap untuk proses transfer refund.")
    normalized_file_id = proof_file_id.strip()
    if not normalized_file_id:
        raise ValueError("Bukti transfer wajib berupa screenshot.")

    complaint.refund_proof_file_id = normalized_file_id
    complaint.refund_note = note.strip() or None
    complaint.refund_transferred_at = _utcnow()
    complaint.status = COMPLAINT_STATUS_REFUND_COMPLETED
    complaint.closed_at = _utcnow()
    complaint.updated_at = _utcnow()
    session.add(complaint)

    append_audit(
        session,
        action="complaint_refund_transferred",
        actor_id=actor_id,
        entity_type="complaint_case",
        entity_id=str(complaint.id),
        detail=f"complaint_ref={complaint.complaint_ref}",
    )
    detail = get_complaint_detail(session, complaint_id=int(complaint.id))
    if detail is None:
        raise ValueError("Komplain tidak ditemukan.")
    return detail


def mark_complaint_replacement_sent(session: Session, *, complaint_id: int, actor_id: int | None) -> ComplaintDetail:
    complaint = session.get(ComplaintCase, int(complaint_id))
    if complaint is None:
        raise ValueError("Komplain tidak ditemukan.")
    if complaint.status != COMPLAINT_STATUS_IN_PROCESS:
        raise ValueError("Komplain harus berstatus proses untuk kirim akun pengganti.")

    complaint.status = COMPLAINT_STATUS_REPLACEMENT_SENT
    complaint.closed_at = _utcnow()
    complaint.updated_at = _utcnow()
    session.add(complaint)

    append_audit(
        session,
        action="complaint_replacement_sent",
        actor_id=actor_id,
        entity_type="complaint_case",
        entity_id=str(complaint.id),
        detail=f"complaint_ref={complaint.complaint_ref}",
    )
    detail = get_complaint_detail(session, complaint_id=int(complaint.id))
    if detail is None:
        raise ValueError("Komplain tidak ditemukan.")
    return detail


def reopen_done_complaint(session: Session, *, complaint_id: int, actor_id: int | None) -> ComplaintDetail:
    complaint = session.get(ComplaintCase, int(complaint_id))
    if complaint is None:
        raise ValueError("Komplain tidak ditemukan.")
    if complaint.status not in COMPLAINT_STATUS_GROUP_DONE:
        raise ValueError("Hanya komplain selesai yang bisa dibuka kembali.")

    complaint.status = COMPLAINT_STATUS_IN_PROCESS
    complaint.closed_at = None
    complaint.updated_at = _utcnow()
    session.add(complaint)

    append_audit(
        session,
        action="complaint_reopen",
        actor_id=actor_id,
        entity_type="complaint_case",
        entity_id=str(complaint.id),
        detail=f"complaint_ref={complaint.complaint_ref}",
    )
    detail = get_complaint_detail(session, complaint_id=int(complaint.id))
    if detail is None:
        raise ValueError("Komplain tidak ditemukan.")
    return detail
