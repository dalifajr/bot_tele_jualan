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

    # Minimal validation to avoid unusable stock.
    has_username = any(k.lower() == "username" for k in fields)
    has_password = any(k.lower() == "password" for k in fields)
    if not has_username or not has_password:
        raise ValueError("Format stok harus memuat Username dan Password.")

    return ParsedStockBlock(
        title=title_line,
        fields=fields,
        recovery_codes=recovery_codes,
        notes=notes,
    )
