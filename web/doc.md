# Dokumentasi Web Panel Bot Telegram Jualan

## 1. Deskripsi Program
Web panel ini adalah antarmuka berbasis web (Dashboard) yang dibangun menggunakan framework Laravel untuk melengkapi sistem **Bot Telegram Jualan**. Sistem web ini memberikan kemudahan visual bagi **Customer** untuk melihat katalog dan melacak pesanan, serta bagi **Admin** untuk mengelola produk, stok, pesanan, pengguna, hingga memantau laporan operasional tanpa harus bergantung pada antarmuka teks/command di Telegram. Data disinkronisasi secara langsung karena web panel ini menggunakan basis data yang sama (berbagi database SQLite/MySQL) dengan backend Bot Telegram utama.

## 2. Fungsi Program

### Fungsi untuk Customer:
- **Autentikasi Telegram**: Login dan registrasi yang mulus (seamless) terintegrasi langsung dengan akun Telegram.
- **Katalog Produk**: Melihat daftar produk digital yang tersedia, lengkap dengan deskripsi, ketersediaan stok, dan harga.
- **Checkout & Pesanan**: Melakukan pemesanan produk dan melihat riwayat pesanan (Orders) beserta update status terkini (menunggu pembayaran, selesai, dll).
- **Profil Pengguna**: Manajemen profil dasar dan integrasi akun Telegram.

### Fungsi untuk Admin:
- **Manajemen Produk**: Menambah, mengedit, menghapus, serta mengatur status tayang (suspend/unsuspend) dari produk digital.
- **Manajemen Stok**: Menambah stok berupa data teks/akun digital, mengelola ketersediaan, serta melihat pergerakan alokasi stok.
- **Manajemen Pesanan (Orders)**: Memantau pesanan yang masuk, memperbarui status pembayaran, serta menyetujui atau menolak pesanan.
- **Manajemen Pengguna**: Melihat daftar pengguna, mengatur hak akses (role), memantau login, dan melakukan *suspend* terhadap pengguna bermasalah.
- **Komplain & Notifikasi**: Menangani tiket komplain dari pelanggan dan memantau notifikasi sistem (seperti notifikasi order baru).
- **Broadcast**: Mengirim pesan massal/pengumuman ke seluruh pelanggan.
- **Pengaturan & Laporan**: Mengonfigurasi parameter web, sistem pembayaran (upload/hapus payload QRIS), serta melihat grafik dan laporan analitik penjualan operasional.

## 3. Arsitektur Sistem
Sistem ini dibangun dengan arsitektur **MVC (Model-View-Controller)** standar dari framework Laravel.
- **Model (Eloquent ORM)**: Mengelola struktur dan interaksi data dengan database. Model mewakili entitas bisnis seperti `User`, `Product`, `Order`, `StockUnit`, dll.
- **View (Blade Templating)**: Lapisan presentasi (UI) yang merender data menjadi tampilan HTML dinamis. Dipisahkan menjadi view khusus publik, *Customer dashboard*, dan *Admin panel*.
- **Controller**: Lapisan logika aplikasi yang menerima *Request* HTTP, memproses data melalui Model, dan mengirim hasil balasan (Response) ke View. (Contoh: `AdminController`, `CheckoutController`, `TelegramAuthController`).
- **Middleware**: Lapisan filter request untuk keamanan rute. Contohnya `EnsureTelegramAuthenticated` untuk memastikan pengguna sudah login, dan middleware khusus `admin` untuk melindungi akses rute administratif.

## 4. Alur Aktor

### Aktor 1: Customer
1. Customer mengakses halaman utama / halaman Login Web.
2. Melakukan otentikasi menggunakan integrasi Telegram Login.
3. Setelah login berhasil, Customer diarahkan ke halaman **Dashboard**.
4. Customer membuka **Catalog** untuk melihat produk yang dijual.
5. Customer memilih produk dan melakukan **Checkout**, yang akan mencatat Order baru di database.
6. Customer membuka halaman **Orders** untuk memantau status pembayaran dan pengiriman barang digital.

### Aktor 2: Admin
1. Admin login menggunakan akun yang memiliki *role* `admin`.
2. Admin diarahkan ke **Admin Dashboard** yang memuat statistik ringkas sistem.
3. Melalui sidebar navigasi, Admin dapat:
   - Ke menu **Products** & **Stock** untuk mengupdate ketersediaan jualan.
   - Ke menu **Orders** untuk memvalidasi pembayaran secara manual (jika diperlukan) atau mengecek riwayat pesanan.
   - Ke menu **Settings** untuk memperbarui gambar QRIS atau konfigurasi web.
   - Ke menu **Broadcast** untuk memicu pesan massal.

## 5. Alur Sistem (System Flow)
1. **Routing**: Permintaan HTTP dari browser masuk melalui definisi rute di `routes/web.php`.
2. **Middleware Validation**: Request dicegat oleh Middleware. Jika belum otentikasi, sistem me-redirect ke `/login`. Jika mencoba akses route `/admin` tanpa hak akses, request ditolak.
3. **Controller Execution**: Request yang valid disalurkan ke Controller yang sesuai fungsinya.
4. **Data Retrieval/Mutation**: Controller berkomunikasi dengan Model untuk mengambil kumpulan baris data, menyimpan Order, memotong stok, dsb.
5. **View Composer & Rendering**: Sebelum View dirender, View Composer (seperti `NotificationComposer`) dapat menyisipkan data global (misalnya notifikasi belum terbaca di header). Blade merakit HTML final.
6. **HTTP Response**: Sistem mengirim respons HTTP kembali ke browser dalam bentuk UI lengkap.

## 6. Hierarki Folder Utama
Struktur direktori merujuk pada konvensi Laravel modern:
```text
web/
├── app/
│   ├── Http/
│   │   ├── Controllers/   # Memuat logika pemrosesan utama (Auth, Admin, Dashboard, Checkout)
│   │   ├── Middleware/    # Filter untuk pengamanan akses rute 
│   │   └── View/Composers/# Class penyedia data global untuk views (contoh: notifikasi)
│   └── Models/            # Definisi entitas database (User, Product, Order, dsb)
├── bootstrap/             # File inisialisasi awal framework Laravel
├── config/                # Berisi file konfigurasi aplikasi, auth, database, sistem file
├── database/              # Menyimpan file Migrations, Seeders, dan database SQLite (jika lokal)
├── public/                # Document root, tempat meletakkan aset statis (CSS, JS, Images, file index.php)
├── resources/
│   └── views/             # Direktori khusus file tampilan Blade (HTML)
│       ├── admin/         # Komponen dan halaman untuk panel Admin
│       ├── auth/          # Halaman otentikasi
│       └── ... 
├── routes/
│   └── web.php            # Titik entri pemetaan URL ke Controller
└── .env                   # Variabel lingkungan untuk konfigurasi spesifik mesin deployment
```

## 7. Penjelasan-Penjelasan Lain
- **Integrasi Hybrid Seamless**: Web Panel ini dirancang untuk bekerja secara berdampingan (*co-exist*) dengan Bot Telegram Python. Operasi potong stok, persetujuan pesanan, atau blokir pengguna yang dilakukan di Web akan seketika terbaca oleh sistem Bot karena keduanya mengacu pada basis data tunggal yang sama.
- **Validasi Kriptografi Auth**: Login berbasis Telegram mengandalkan validasi kriptografi menggunakan algoritma *hash* SHA-256 terhadap data dari widget otentikasi. Ini mencegah serangan *spoofing* identitas dan memastikan login hanya berasal dari server otentikasi resmi Telegram.
- **Pengelolaan Data Terpusat**: Panel administrator mengurangi kebutuhan mengetik perintah teks panjang di Telegram, menyederhanakan tugas kompleks seperti pengaturan teks broadcast berformat, pengunggahan QRIS, dan penanganan lampiran komplain yang rumit.
- **Responsivitas**: Tampilan (*Views*) disusun menggunakan pendekatan yang responsif, memastikan kenyamanan penggunaan baik saat panel diakses melalui ponsel cerdas maupun melalui komputer *desktop*.
