<?php

$newKeys = json_decode(file_get_contents('d:/bot_tele_jualan/web/new_blade_keys.json'), true);
$enJsonPath = 'd:/bot_tele_jualan/web/lang/en.json';
$existing = file_exists($enJsonPath) ? json_decode(file_get_contents($enJsonPath), true) : [];

// A dictionary of common words/phrases to translate
$commonTranslations = [
    'Manajemen Produk' => 'Product Management',
    'Kelola katalog produk digital' => 'Manage digital product catalog',
    'Tambah Produk' => 'Add Product',
    'Edit Produk' => 'Edit Product',
    'Hapus Produk' => 'Delete Product',
    'Nama Produk' => 'Product Name',
    'Deskripsi' => 'Description',
    'Status' => 'Status',
    'Dibuat' => 'Created At',
    'Aksi' => 'Actions',
    'Batal' => 'Cancel',
    'Simpan' => 'Save',
    'Hapus' => 'Delete',
    'Tutup' => 'Close',
    'Ubah' => 'Edit',
    'Pilih' => 'Select',
    'Cari' => 'Search',
    'Semua' => 'All',
    'Tidak' => 'No',
    'Ya' => 'Yes',
    'Aktif' => 'Active',
    'Nonaktif' => 'Inactive',
    'Suspended' => 'Suspended',
    'Kembali' => 'Back',
    'Kelola' => 'Manage',
    'Pengaturan' => 'Settings',
    'Pesanan' => 'Orders',
    'Produk' => 'Products',
    'Pengguna' => 'Users',
    'Riwayat' => 'History',
    'Pelanggan' => 'Customers',
    'Detail' => 'Detail',
    'Katalog' => 'Catalog',
    'Harga' => 'Price',
    'Total' => 'Total',
    'Tanggal' => 'Date',
    'Stok' => 'Stock',
    'Unit' => 'Unit',
    'Selesai' => 'Done',
    'Berhasil' => 'Success',
    'Gagal' => 'Failed',
    'Tambah' => 'Add',
    'Perhatian' => 'Attention',
    'Ya, Hapus' => 'Yes, Delete',
    'Tambahkan' => 'Add',
    'hari' => 'days',
    'Pilih Protokol' => 'Select Protocol',
    'Durasi / Masa Aktif' => 'Duration / Active Period'
];

$addedCount = 0;

foreach ($newKeys as $key) {
    if (!isset($existing[$key])) {
        // Find if we have a direct match in common translations
        if (isset($commonTranslations[$key])) {
            $existing[$key] = $commonTranslations[$key];
        } else {
            // Default to itself
            $existing[$key] = $key;
        }
        $addedCount++;
    }
}

file_put_contents($enJsonPath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Successfully added $addedCount new keys to en.json!\n";
