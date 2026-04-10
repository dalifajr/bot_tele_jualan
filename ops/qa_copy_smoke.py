from __future__ import annotations

from pathlib import Path
import sys

TARGET = Path(__file__).resolve().parents[1] / "src" / "app" / "bot" / "handlers" / "main.py"


def main() -> int:
    text = TARGET.read_text(encoding="utf-8")

    required_tokens = {
        "customer_footer_helper": "_customer_footer_text()",
        "admin_footer_helper": "_admin_footer_text()",
        "customer_menu_pesanan": "📦 <b>Pesanan Saya</b>",
        "copy_akun_button": "📋 Copy Akun",
        "quick_reorder_button": "🔁 Pesan Lagi",
        "restock_button": "🔔 Ingatkan Saat Restock",
        "vouchers_help": "/vouchers",
        "ops_metrics_help": "/ops_metrics",
        "admin_footer_phrase": "Pilih aksi admin lewat tombol di bawah.",
        "customer_footer_phrase": "Pilih aksi lewat tombol di bawah.",
        "empty_state_orders": "📭 <b>Belum Ada Pesanan</b>",
        "empty_state_catalog": "📭 <b>Katalog Kosong</b>",
        "empty_state_admin_catalog": "📭 <b>Produk Belum Tersedia</b>",
        "empty_state_gh_stock": "📭 <b>Stok GitHub Pack Kosong</b>",
    }

    forbidden_tokens = {
        "legacy_denied_1": "🚫 Hanya admin yang bisa akses menu ini.",
        "legacy_denied_2": "🚫 Akses ditolak.",
    }

    missing = [name for name, token in required_tokens.items() if token not in text]
    found_forbidden = [name for name, token in forbidden_tokens.items() if token in text]

    print("[qa_copy_smoke] target:", TARGET)
    print("[qa_copy_smoke] required tokens:", len(required_tokens))
    print("[qa_copy_smoke] forbidden tokens:", len(forbidden_tokens))

    if missing:
        print("\nFAIL: Required token tidak ditemukan:")
        for name in missing:
            print(" -", name)

    if found_forbidden:
        print("\nFAIL: Forbidden token masih ditemukan:")
        for name in found_forbidden:
            print(" -", name)

    if missing or found_forbidden:
        return 1

    print("\nPASS: Copy/CTA smoke-check lolos.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
