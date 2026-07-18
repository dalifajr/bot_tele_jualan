<?php

$translations = [
    '$count stok berhasil ditambahkan.' => ':count stock successfully added.',
    '$count status/produk stok berhasil dipindahkan secara masal.' => ':count stock statuses/products successfully moved in bulk.',
    '$count stok berhasil dihapus secara masal.' => ':count stock successfully deleted in bulk.',
    'IP Address {$ip} telah diblokir selama {$durationText}.' => 'IP Address :ip has been blocked for :durationText.',
    'Blokir IP Address {$ip} telah dibuka.' => 'IP Address :ip block has been removed.',
    'Berhasil me-refund {$refundedCount} akun terpilih.' => 'Successfully refunded :refundedCount selected accounts.',
    'Verifikasi dua langkah (2FA) berhasil {$status}.' => 'Two-factor authentication (2FA) successfully :status.',
    'Stok tidak mencukupi. Tersedia {$availableStock} unit, dan Anda sudah memiliki {$currentCartQty} unit di keranjang.' => 'Insufficient stock. :availableStock units available, and you already have :currentCartQty units in your cart.',
    'Stok tidak mencukupi. Tersedia {$availableStock} unit.' => 'Insufficient stock. :availableStock units available.',
    'Stok tidak cukup. Hanya tersisa {$availableStockCount} unit.' => 'Insufficient stock. Only :availableStockCount units left.',
    '$count stok berhasil diunggah.' => ':count stock successfully uploaded.'
];

$enJsonPath = 'd:/bot_tele_jualan/web/lang/en.json';
$existing = file_exists($enJsonPath) ? json_decode(file_get_contents($enJsonPath), true) : [];

foreach ($translations as $key => $value) {
    // Overwrite the empty string ones
    $existing[$key] = $value;
}

file_put_contents($enJsonPath, json_encode($existing, JSON_PRETTY_PRINT));
echo "en.json has been updated!\n";
