<?php

$dir = new RecursiveDirectoryIterator('d:/bot_tele_jualan/web/resources/views');
$ite = new RecursiveIteratorIterator($dir);
$count = 0;
foreach($ite as $file) {
    if($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        
        // Remove content inside {{ ... }}, {!! !!}, @php ... @endphp, <script>...</script>, <style>...</style>, and HTML comments
        $clean = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}|@php.*?@endphp|<!--.*?-->|<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>/s', '', $content);
        
        // Look for typical Indonesian words that are not in tags
        $textNodes = strip_tags($clean);
        
        if (preg_match('/(?:^|\s)(Kembali|Simpan|Batal|Hapus|Ubah|Tambah|Kelola|Pengaturan|Pilih|Cari|Semua|Status|Nama|Harga|Aksi|Tidak|Ada|Tutup|Atur|Pesanan|Produk|Pengguna|Riwayat|Toko|Pembayaran|Pelanggan|Detail|Katalog)(?:$|\s)/i', $textNodes, $matches)) {
            echo str_replace('d:/bot_tele_jualan/web/resources/views\\', '', $file->getPathname()) . ' contains untranslated: ' . $matches[1] . "\n";
            $count++;
        }
    }
}
echo "Total files with hardcoded words: $count\n";
