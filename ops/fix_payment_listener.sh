#!/usr/bin/env bash
# ==============================================================================
# Fix Payment Listener (502 Bad Gateway -> Laravel Native Handler)
# Menghapus blok proxy_pass /listener/ yang membajak rute Laravel ke port mati
# ==============================================================================

set -euo pipefail

echo "========================================================="
echo "   FIX PAYMENT LISTENER (502 BAD GATEWAY RESOLVER)      "
echo "========================================================="

if [ "$(id -u)" -ne 0 ]; then
    echo "[!] Skrip ini harus dijalankan dengan hak akses root (sudo)."
    exit 1
fi

echo "[1/3] Memeriksa konfigurasi Nginx aktif di VPS..."
python3 -c "
import glob, re

targets = glob.glob('/etc/nginx/sites-available/*') + glob.glob('/etc/nginx/sites-enabled/*') + glob.glob('/etc/nginx/conf.d/*')
found = 0

for p in sorted(set(targets)):
    try:
        with open(p, 'r') as f:
            content = f.read()
        if 'location /listener' in content:
            found += 1
            print(f'[+] Ditemukan blok /listener/ pada file: {p}')
            # Hapus blok location /listener/ { ... }
            new_content = re.sub(r'#?[^\n]*\n?\s*location\s+/listener/?\s*\{[^}]*\}', '', content)
            with open(p, 'w') as f:
                f.write(new_content)
            print(f'[OK] Blok /listener/ berhasil dihapus dari {p}. Rute kini diteruskan ke Laravel (PHP-FPM).')
    except Exception as e:
        print(f'[-] Gagal memproses {p}: {e}')

if found == 0:
    print('[INFO] Tidak ditemukan blok location /listener/ di Nginx.')
"

echo "[2/3] Memvalidasi sintaks Nginx..."
nginx -t

echo "[3/3] Me-reload Nginx daemon..."
systemctl reload nginx

echo "========================================================="
echo "[BERHASIL] Rute /listener/payment kini aktif dilayani Laravel!"
echo "Uji coba respon sekarang via:"
echo "curl -i -X POST http://127.0.0.1/listener/payment"
echo "========================================================="
