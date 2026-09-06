/**
 * Lightweight RFC 6238 TOTP Engine & Auto-Updater
 * No external dependencies. Supports Web Crypto API & Fallback.
 */

(function () {
    'use strict';

    // Base32 RFC 4648 Alphabet
    const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // Calculate server time offset to prevent client clock drift
    let serverTimeOffset = 0;
    const metaServerTime = document.querySelector('meta[name="server-time"]');
    if (metaServerTime && metaServerTime.content) {
        const serverTimestamp = parseInt(metaServerTime.content, 10);
        if (!isNaN(serverTimestamp)) {
            serverTimeOffset = serverTimestamp - Math.floor(Date.now() / 1000);
        }
    }

    /**
     * Get accurate synced timestamp in seconds
     */
    function getNow() {
        return Math.floor(Date.now() / 1000) + serverTimeOffset;
    }

    /**
     * Decode a Base32 string to Uint8Array
     */
    function base32Decode(str) {
        if (!str) return new Uint8Array(0);
        const clean = str.toUpperCase().replace(/[^A-Z2-7]/g, '');
        let bits = '';
        for (let i = 0; i < clean.length; i++) {
            const val = BASE32_CHARS.indexOf(clean.charAt(i));
            if (val === -1) continue;
            bits += val.toString(2).padStart(5, '0');
        }
        const bytes = [];
        for (let i = 0; i + 8 <= bits.length; i += 8) {
            bytes.push(parseInt(bits.substr(i, 8), 2));
        }
        return new Uint8Array(bytes);
    }

    /**
     * Fallback pure JS SHA1 implementation if Web Crypto is unavailable (e.g. non-secure context)
     */
    function sha1(bytes) {
        // Standard SHA-1 hash function
        function rotateLeft(n, s) { return (n << s) | (n >>> (32 - s)); }
        
        let words = [];
        for (let i = 0; i < bytes.length; i++) {
            words[i >> 2] |= (bytes[i] & 0xff) << (24 - (i % 4) * 8);
        }
        const bitLen = bytes.length * 8;
        words[bitLen >> 5] |= 0x80 << (24 - (bitLen % 32));
        words[(((bitLen + 64) >> 9) << 4) + 15] = bitLen;

        let H0 = 0x67452301, H1 = 0xefcdab89, H2 = 0x98badcfe, H3 = 0x10325476, H4 = 0xc3d2e1f0;
        let W = new Array(80);

        for (let i = 0; i < words.length; i += 16) {
            let A = H0, B = H1, C = H2, D = H3, E = H4;
            for (let t = 0; t < 80; t++) {
                if (t < 16) {
                    W[t] = words[i + t] | 0;
                } else {
                    W[t] = rotateLeft(W[t - 3] ^ W[t - 8] ^ W[t - 14] ^ W[t - 16], 1);
                }
                let temp, f, K;
                if (t < 20) {
                    f = (B & C) | ((~B) & D);
                    K = 0x5a827999;
                } else if (t < 40) {
                    f = B ^ C ^ D;
                    K = 0x6ed9eba1;
                } else if (t < 60) {
                    f = (B & C) | (B & D) | (C & D);
                    K = 0x8f1bbcdc;
                } else {
                    f = B ^ C ^ D;
                    K = 0xca62c1d6;
                }
                temp = (rotateLeft(A, 5) + f + E + K + W[t]) | 0;
                E = D; D = C; C = rotateLeft(B, 30); B = A; A = temp;
            }
            H0 = (H0 + A) | 0;
            H1 = (H1 + B) | 0;
            H2 = (H2 + C) | 0;
            H3 = (H3 + D) | 0;
            H4 = (H4 + E) | 0;
        }

        const out = new Uint8Array(20);
        const H = [H0, H1, H2, H3, H4];
        for (let i = 0; i < 5; i++) {
            out[i * 4] = (H[i] >>> 24) & 0xff;
            out[i * 4 + 1] = (H[i] >>> 16) & 0xff;
            out[i * 4 + 2] = (H[i] >>> 8) & 0xff;
            out[i * 4 + 3] = H[i] & 0xff;
        }
        return out;
    }

    /**
     * Compute HMAC-SHA1 using pure JS fallback
     */
    function hmacSha1(keyBytes, msgBytes) {
        const blockSize = 64;
        let key = keyBytes;
        if (key.length > blockSize) {
            key = sha1(key);
        }
        const kPad = new Uint8Array(blockSize);
        kPad.set(key);

        const iPad = new Uint8Array(blockSize);
        const oPad = new Uint8Array(blockSize);
        for (let i = 0; i < blockSize; i++) {
            iPad[i] = kPad[i] ^ 0x36;
            oPad[i] = kPad[i] ^ 0x5c;
        }

        const inner = new Uint8Array(blockSize + msgBytes.length);
        inner.set(iPad);
        inner.set(msgBytes, blockSize);
        const innerHash = sha1(inner);

        const outer = new Uint8Array(blockSize + innerHash.length);
        outer.set(oPad);
        outer.set(innerHash, blockSize);
        return sha1(outer);
    }

    /**
     * Calculate TOTP code for a Base32 secret string (sync)
     */
    function computeTotp(secret, timeInSeconds) {
        if (!secret) return null;
        // Clean secret
        let cleanSecret = secret.trim();
        if (cleanSecret.toLowerCase().startsWith('otpauth://')) {
            try {
                const url = new URL(cleanSecret);
                cleanSecret = url.searchParams.get('secret') || cleanSecret;
            } catch (e) {}
        }
        cleanSecret = cleanSecret.toUpperCase().replace(/[^A-Z2-7]/g, '');
        if (cleanSecret.length < 8) return null;

        const keyBytes = base32Decode(cleanSecret);
        if (keyBytes.length === 0) return null;

        const t = timeInSeconds !== undefined ? timeInSeconds : getNow();
        const counter = Math.floor(t / 30);

        // 8 bytes big endian
        const timeBytes = new Uint8Array(8);
        let temp = counter;
        for (let i = 7; i >= 0; i--) {
            timeBytes[i] = temp & 0xff;
            temp = Math.floor(temp / 256);
        }

        const hmac = hmacSha1(keyBytes, timeBytes);
        const offset = hmac[19] & 0x0f;
        const code = ((hmac[offset] & 0x7f) << 24)
                   | ((hmac[offset + 1] & 0xff) << 16)
                   | ((hmac[offset + 2] & 0xff) << 8)
                   | (hmac[offset + 3] & 0xff);

        const otp = code % 1000000;
        return otp.toString().padStart(6, '0');
    }

    /**
     * Update all TOTP elements on page
     */
    function updateAllTotpElements() {
        const now = getNow();
        const remaining = 30 - (now % 30);

        // 1. Elements with data-totp-container
        const containers = document.querySelectorAll('[data-totp-container]');
        containers.forEach(container => {
            const secret = container.getAttribute('data-totp-secret');
            if (!secret) return;

            const codeSpan = container.querySelector('.totp-code');
            const timerSpan = container.querySelector('.totp-timer');

            const code = computeTotp(secret, now);
            if (code && codeSpan) {
                codeSpan.textContent = code;
            }
            if (timerSpan) {
                timerSpan.textContent = remaining + 's';
                // Highlight red if less than 6 seconds left
                if (remaining <= 5) {
                    timerSpan.classList.remove('bg-success', 'bg-secondary-subtle', 'text-secondary');
                    timerSpan.classList.add('bg-danger', 'text-white');
                } else {
                    timerSpan.classList.remove('bg-danger');
                    timerSpan.classList.add('bg-success', 'text-white');
                }
            }
        });

        // 2. Circular / SVG timer bars on standalone 2FA generator page if present
        const generatorRing = document.getElementById('totp-timer-ring');
        const generatorSeconds = document.getElementById('totp-seconds-left');
        if (generatorRing) {
            const circumference = 2 * Math.PI * 45; // r=45
            const offset = circumference - (remaining / 30) * circumference;
            generatorRing.style.strokeDashoffset = offset;
            if (remaining <= 5) {
                generatorRing.style.stroke = '#dc3545';
            } else {
                generatorRing.style.stroke = '#0d6efd';
            }
        }
        if (generatorSeconds) {
            generatorSeconds.textContent = remaining + 's';
        }
    }

    // Run immediately and set 1-second interval
    document.addEventListener('DOMContentLoaded', function () {
        updateAllTotpElements();
        setInterval(updateAllTotpElements, 1000);
    });

    // Global copy function for 2FA code
    window.copyTotpCode = function (btnElement, secret) {
        let code = '';
        const container = btnElement.closest('[data-totp-container]');
        if (container) {
            const codeSpan = container.querySelector('.totp-code');
            if (codeSpan) {
                code = codeSpan.textContent.trim();
            }
        }

        // If code couldn't be extracted from span, compute on the fly
        if (!code || code === '------') {
            code = computeTotp(secret, getNow());
        }

        if (!code) {
            alert('Gagal mendapatkan kode 2FA.');
            return;
        }

        // Copy to clipboard
        navigator.clipboard.writeText(code).then(() => {
            const icon = btnElement.querySelector('i');
            if (icon) {
                const originalClass = icon.className;
                icon.className = 'fas fa-check text-success';
                setTimeout(() => {
                    icon.className = originalClass;
                }, 1500);
            }

            // Optional toast if SweetAlert2 is present
            if (typeof Swal !== 'undefined') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: 'Kode ' + code + ' disalin!'
                });
            }
        }).catch(() => {
            // Fallback
            prompt('Salin kode 2FA:', code);
        });
    };

    // Export to window for standalone generator page
    window.TotpEngine = {
        compute: computeTotp,
        base32Decode: base32Decode,
        getNow: getNow,
        getRemainingSeconds: function () {
            return 30 - (getNow() % 30);
        }
    };
})();
