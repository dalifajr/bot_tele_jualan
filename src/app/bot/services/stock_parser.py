from __future__ import annotations

import json
import re
from dataclasses import dataclass


@dataclass
class ParsedStockBlock:
    title: str
    fields: dict[str, str]
    recovery_codes: list[str]
    notes: list[str]

    def as_json(self) -> str:
        return json.dumps(
            {
                "title": self.title,
                "fields": self.fields,
                "recovery_codes": self.recovery_codes,
                "notes": self.notes,
            },
            ensure_ascii=False,
        )


def _normalize_lines(raw_text: str) -> list[str]:
    normalized = raw_text.replace("\r\n", "\n").replace("\r", "\n")
    return [line.rstrip() for line in normalized.split("\n")]


def split_stock_blocks(raw_text: str) -> list[str]:
    """
    Split raw bulk stock text into individual clean account blocks.
    Supports:
    1. Line dividers (===..., ___..., ---..., ###...)
    2. Double-newlines (\n\s*\n)
    """
    if not raw_text or not raw_text.strip():
        return []

    normalized = raw_text.replace("\r\n", "\n").replace("\r", "\n")

    # Check if raw text contains divider lines (e.g. 3 or more '=', '_', '-', or '#')
    if re.search(r"^\s*[=_#-]{3,}\s*$", normalized, flags=re.MULTILINE):
        raw_blocks = re.split(r"(?:\n|^)\s*[=_#-]{3,}\s*(?:\n|$)", normalized)
    else:
        raw_blocks = re.split(r"\n\s*\n", normalized)

    clean_blocks: list[str] = []
    for block in raw_blocks:
        trimmed = block.strip()
        trimmed = re.sub(r"^\s*[=_#-]{3,}\s*\n?|\n?\s*[=_#-]{3,}\s*$", "", trimmed, flags=re.MULTILINE).strip()
        if trimmed:
            clean_blocks.append(trimmed)

    return clean_blocks


def parse_stock_block(raw_text: str) -> ParsedStockBlock:
    lines = _normalize_lines(raw_text)
    non_empty = [x.strip() for x in lines if x.strip()]
    if not non_empty:
        raise ValueError("Stok kosong. Kirim blok data stok yang valid.")

    title_line = non_empty[0].strip("*").strip()
    fields: dict[str, str] = {}
    recovery_codes: list[str] = []
    notes: list[str] = []

    in_recovery = False
    kv_pattern = re.compile(r"^([^:]+):\s*(.*)$")

    for line in non_empty[1:]:
        if line.lower().startswith("recovery codes"):
            in_recovery = True
            continue

        if in_recovery:
            if ":" in line:
                in_recovery = False
            else:
                recovery_codes.append(line)
                continue

        matched = kv_pattern.match(line)
        if matched:
            key = matched.group(1).strip()
            value = matched.group(2).strip()
            fields[key] = value
        else:
            notes.append(line)

    # Minimal validation to avoid unusable stock (supports aliases: username/user/login/email & password/pass/sandi/pw).
    has_username = any(k.lower() in ("username", "user", "email", "login") for k in fields) or any(k.lower().startswith(("username", "user", "email", "login")) for k in fields)
    has_password = any(k.lower() in ("password", "pass", "sandi", "pw") for k in fields) or any(k.lower().startswith(("password", "pass", "sandi", "pw")) for k in fields)
    
    # Check if first line itself contains kv (e.g. user: mansur.2019@test.com)
    if not has_username or not has_password:
        first_match = kv_pattern.match(title_line)
        if first_match:
            key = first_match.group(1).strip()
            value = first_match.group(2).strip()
            fields[key] = value
            has_username = any(k.lower() in ("username", "user", "email", "login") for k in fields)
            has_password = any(k.lower() in ("password", "pass", "sandi", "pw") for k in fields)

    if not has_username or not has_password:
        raise ValueError("Format stok harus memuat Username/User dan Password/Pass.")

    return ParsedStockBlock(
        title=title_line,
        fields=fields,
        recovery_codes=recovery_codes,
        notes=notes,
    )
