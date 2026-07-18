<?php

$replacements = [
    '__("$count stok berhasil diunggah.")' => '__(":count stok berhasil diunggah.", ["count" => $count])',
    '__("$count status/produk stok berhasil dipindahkan secara masal.")' => '__(":count status/produk stok berhasil dipindahkan secara masal.", ["count" => $count])',
    '__("$count stok berhasil dihapus secara masal.")' => '__(":count stok berhasil dihapus secara masal.", ["count" => $count])',
    '__("IP Address {$ip} telah diblokir selama {$durationText}.")' => '__("IP Address :ip telah diblokir selama :durationText.", ["ip" => $ip, "durationText" => $durationText])',
    '__("Stok tidak cukup. Hanya tersisa {$availableStockCount} unit.")' => '__("Stok tidak cukup. Hanya tersisa :availableStockCount unit.", ["availableStockCount" => $availableStockCount])',
    '__("Stok tidak mencukupi. Tersedia {$availableStock} unit, dan Anda sudah memiliki {$currentCartQty} unit di keranjang.")' => '__("Stok tidak mencukupi. Tersedia :availableStock unit, dan Anda sudah memiliki :currentCartQty unit di keranjang.", ["availableStock" => $availableStock, "currentCartQty" => $currentCartQty])',
    '__("Stok tidak mencukupi. Tersedia {$availableStock} unit.")' => '__("Stok tidak mencukupi. Tersedia :availableStock unit.", ["availableStock" => $availableStock])',
    '__("Verifikasi dua langkah (2FA) berhasil {$status}.")' => '__("Verifikasi dua langkah (2FA) berhasil :status.", ["status" => $status])',
    '__("$count stok berhasil ditambahkan.")' => '__(":count stok berhasil ditambahkan.", ["count" => $count])',
    '__("Blokir IP Address {$ip} telah dibuka.")' => '__("Blokir IP Address :ip telah dibuka.", ["ip" => $ip])',
    '__("Berhasil mengganti {$replacedCount} akun terpilih.")' => '__("Berhasil mengganti :replacedCount akun terpilih.", ["replacedCount" => $replacedCount])',
    '__("Berhasil me-refund {$refundedCount} akun terpilih.")' => '__("Berhasil me-refund :refundedCount akun terpilih.", ["refundedCount" => $refundedCount])'
];

$dir = new RecursiveDirectoryIterator('d:/bot_tele_jualan/web/app/Http/Controllers');
$ite = new RecursiveIteratorIterator($dir);
$countReplaced = 0;
foreach($ite as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $original = $content;
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        if ($original !== $content) {
            file_put_contents($file->getPathname(), $content);
            $countReplaced++;
        }
    }
}
echo "Replaced strings in $countReplaced files.\n";
