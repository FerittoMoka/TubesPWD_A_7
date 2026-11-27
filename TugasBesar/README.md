# Dolan Jogja

## 1. Anggota Kelompok

> Lengkapi tabel ini sebelum dikumpulkan, lalu ganti nama folder menjadi `TubesPWD_<Kelas>_<Kelompok>`.

| No | Nama Lengkap | NPM | Bagian yang dikerjakan |
|----|--------------|-----|-------|------------------------|
| 1 | *Daniel Valentino Yonathan* | *250713197* | *Register, proses register, dan bonus* |
| 2 | *Nama Anggota 2* | *NPM* | *mis. auth, profil, ulasan* |
| 3 | *Nama Anggota 3* | *NPM* | *mis. admin, database, rencana* |
| 4 | *Nama Anggota 4* | *NPM* | *mis. admin, database, rencana* |
|----|--------------|-----|------------------------|

---

## 2. Akun Uji Coba


| Peran | Username | Email | Password |
|-------|----------|-------|----------|
| Admin | `admin_demo` | `admin@dolanjogja.test` | `demo1234` |
| Wisatawan | `user_demo` | `user@dolanjogja.test` | `demo1234` |

---

## 3. Fitur Utama & Lokasi File

| Fitur | Keterangan singkat | Lokasi file |
|-------|--------------------|-------------|
| Beranda & pencarian | Hero, pencarian dengan autocomplete, chip filter suasana/kategori/wilayah, acak destinasi | `index.php`, `assets/js/main.js`, `assets/js/filter-beranda.js`, `includes/mood.php`, `includes/wilayah.php` |
| Detail destinasi | Rating & distribusi bintang, ulasan, favorit, kartu tiket + estimasi rombongan | `destinasi/detail.php`, `destinasi/get.php` |
| Ulasan | Tulis/ubah/hapus ulasan, filter bintang, urut terbaru/rating, vote "Membantu" & badge kontributor | `review/simpan.php`, `review/hapus.php`, `review/vote.php`, `includes/ulasan_bantuan.php` |
| Peta interaktif | Leaflet + OpenStreetMap, filter kategori, pencarian, rute rencana, sorot destinasi dari halaman detail | `peta.php` |
| Rencana Dolan | Simpan destinasi, atur hari & urutan, estimasi tiket, kalkulator patungan, cetak itinerary + QR | `user/rencana.php`, `user/toggle_rencana.php`, `assets/js/qrcode.js` |
| Favorit | Tandai/lepas favorit tanpa reload, badge jumlah favorit | `user/toggle_favorit.php`, `user/wishlist.php` |
| Terdekat | Geolocation browser + jarak Haversine | `destinasi/terdekat.php` |
| Akun | Registrasi (cek username/email real-time), login, logout, profil & foto, ganti password | `auth/*`, `user/profil.php` |
| Admin | CRUD destinasi, kategori, moderasi ulasan, daftar pengguna | `admin/*`, `includes/init.php` |
| Keamanan | Password `password_hash`, semua query `mysqli_prepare`, token CSRF di setiap form, escape output `e()` | `includes/auth.php`, `includes/csrf.php` |

### Empat fitur unggulan

1. **Peta interaktif Leaflet** — `peta.php`
   Marker kustom berwarna per kategori, pencarian & filter di panel kiri, popup kaya informasi, rute rencana perjalanan, serta parameter `peta.php?fokus=<id>` untuk menyorot satu destinasi dan `peta.php?rute=1` untuk langsung menggambar rute.
2. **Rencana Dolan (itinerary + anggaran)** — `user/rencana.php`
   Destinasi disusun per hari, urutan bisa dinaikkan/diturunkan, estimasi tiket dihitung otomatis untuk jumlah orang tertentu, kalkulator patungan, tombol salin ringkasan, serta mode cetak **"Itinerary Resmi — Dolan Jogja"** dengan tanggal cetak, jam buka tiap destinasi, ringkasan anggaran, dan kode QR lokal.
3. **Ulasan interaktif** — `destinasi/detail.php`, `review/vote.php`
   Rating bintang, distribusi rating, filter per bintang, pengurutan (termasuk **Paling Membantu**), tombol **"Membantu (👍)"** dengan basis data `review_helps`, dan badge **Kontributor Terverifikasi** untuk penulis ulasan yang ulasannya dinilai membantu.
4. **Pencarian & filter dinamis tanpa reload** — `index.php`, `assets/js/filter-beranda.js`
   Filter suasana/kategori/wilayah yang bisa dikombinasikan, autocomplete pencarian, animasi skeleton saat memuat, acak destinasi, dan riwayat "terakhir dilihat" lewat `localStorage`.

---

## 4. Struktur Folder

```
TugasBesar/
├── index.php                 beranda
├── peta.php                  peta interaktif
├── database.sql              skema + data contoh + akun demo
├── config/koneksi.php        koneksi MySQL + halaman panduan instalasi
├── includes/                 header, footer, auth, CSRF, helper (e, harga, foto), dll.
├── auth/                     register, login, logout, cek ketersediaan username/email
├── destinasi/                detail destinasi, API get.php, halaman terdekat
├── review/                   simpan, hapus, vote ulasan
├── user/                     profil, rencana dolan, favorit, wishlist
├── admin/                    panel admin (destinasi, kategori, ulasan, pengguna)
└── assets/                   css, js, dan upload gambar (dibuat otomatis saat unggah)
```

