# Dolan Jogja - Aplikasi Informasi Wisata Yogyakarta

Tugas Besar Pemrograman Web Dasar 2026/2027 (Informatika, UAJY).

**Teknologi:** PHP murni (tanpa framework) + MySQL/MariaDB, frontend XHTML 1.0 Strict, CSS, dan JavaScript.

## Tema
Tampilan mengikuti tema batik Jawa (cokelat soga, krem, emas, indigo; font Fraunces + Plus Jakarta Sans dari Google Fonts, butuh internet; tanpa internet otomatis memakai font cadangan).

## Instalasi (XAMPP)
1. Ekstrak folder `dolan_jogja` ke `C:\xampp\htdocs\`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`, lalu **Import** file `database.sql` (membuat database `db_wisata` beserta data baru).
4. Jika user/password MySQL berbeda, ubah `config/koneksi.php`.
5. Buka `http://localhost/dolan_jogja/`.

Folder `uploads/profil/` dan `uploads/destinasi/` harus dapat ditulis oleh Apache (default XAMPP sudah bisa).

## Akun contoh (dari database.sql)
| Role  | Username / Email                         | Password     |
|-------|------------------------------------------|--------------|
| Admin | `admin` / `admin@dolanjogja.test`        | `Admin#2026` |
| User  | `budi.santoso`, `sari.wulandari`, `andi.pratama`, `maya.kusuma`, `rizky.ramadhan`, `nadia.putri` | `User#2026` |

Akun baru lewat halaman Daftar otomatis berole `user`. Untuk menjadikannya admin, gunakan menu **Admin > Pengguna** (login sebagai admin).

## Pemetaan terhadap ketentuan modul
| Ketentuan modul | Implementasi |
|---|---|
| Backend PHP murni | Seluruh logika di file `.php` (mysqli + prepared statement), tanpa framework |
| Frontend XHTML, CSS, JS | Doctype XHTML 1.0 Strict (`includes/init.php`), `assets/css/style.css`, `assets/js/app.js` |
| Registrasi pengguna | `auth/register.php` |
| Enkripsi password | `password_hash()` (bcrypt) saat registrasi/ubah password, `password_verify()` saat login |
| Login | `auth/login.php` (username atau email), sesi + `session_regenerate_id` |
| Lihat & update profil | `user/profil.php` (data diri, foto, ganti password) |
| CRUD >= 2 | **Destinasi** (admin: tambah/lihat/ubah/hapus + foto), **Kategori** (admin), **Ulasan** (user: tambah/ubah/hapus), **Favorit** (tambah/lihat/hapus), **Pengguna** (admin: lihat/ubah role/hapus) |
| Bonus: UI admin | `admin/index.php` (dashboard statistik, destinasi terpopuler, ulasan terbaru), `admin/pengguna.php` |
| Bonus: geolocation | Halaman `destinasi/terdekat.php` (`api/terdekat.php`, Haversine); Detail: hitung jarak dari lokasi pengguna |
| Bonus: cek username/email | AJAX di form registrasi (`api/cek_user.php`), validasi server tetap berjalan |
| Bonus: foto profil | Unggah/ganti/hapus foto di `user/profil.php` (validasi tipe MIME & ukuran maks. 2 MB) |

## Keamanan yang diterapkan
- Prepared statement di semua query, output di-escape (`htmlspecialchars`).
- Token CSRF di semua form POST; aksi hapus memakai POST (bukan link GET).
- Role dicek dari database pada setiap request; admin tidak bisa mengubah/menghapus akunnya sendiri.
- Upload: cek MIME asli (finfo), nama file diacak, folder `uploads/` dilarang mengeksekusi PHP (`.htaccess`).

## Struktur folder
```
config/koneksi.php      koneksi database
includes/init.php       sesi, helper, template header/footer
index.php               beranda: hero, pencarian, kategori, unggulan, daftar
destinasi/              detail.php (ulasan, favorit, jarak), terdekat.php
auth/                   register, login, logout
user/                   profil, wishlist (favorit)
admin/                  dashboard, destinasi, kategori, pengguna
api/                    terdekat.php, cek_user.php (JSON)
assets/                 css/style.css, js/app.js
uploads/                foto profil & destinasi
database.sql            skema + data baru
```

## Catatan sebelum pengumpulan
- **Data wisata** (harga tiket, jam buka, koordinat) adalah perkiraan. Verifikasi ke sumber resmi sebelum dipresentasikan.
- Validasi setiap halaman di W3C Markup Validation Service (tab *Direct Input* pada halaman yang sudah dirender).
- Nama repository GitHub: `TubesPWD_<Kelas>_<Kelompok>` (contoh: `TubesPWD_A_1`). Batas pengumpulan: 2 Des 2026 (kelas A, D) / 3 Des 2026 (kelas B, C), pukul 23.59 WIB.
- Tambahkan identitas anggota kelompok, dokumentasi, dan hasil pengujian sesuai arahan asisten.
