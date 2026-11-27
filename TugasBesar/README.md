# Dolan Jogja

Aplikasi web informasi wisata Yogyakarta: katalog destinasi, peta interaktif, ulasan pengunjung, favorit, dan **Rencana Dolan** (itinerary per hari beserta estimasi biaya). Dibangun dengan **PHP native + MySQL (mysqli)**, **HTML/XHTML + CSS + JavaScript**, tanpa framework dan tanpa build tool.

Tugas Besar Pemrograman Web Dasar 2026/2027.

---

## 1. Anggota Kelompok

> Lengkapi tabel ini sebelum dikumpulkan, lalu ganti nama folder menjadi `TubesPWD_<Kelas>_<Kelompok>`.

| No | Nama Lengkap | NIM | Kelas | Bagian yang dikerjakan |
|----|--------------|-----|-------|------------------------|
| 1 | *Nama Anggota 1* | *NIM* | *Kelas* | *mis. beranda, filter, peta* |
| 2 | *Nama Anggota 2* | *NIM* | *Kelas* | *mis. auth, profil, ulasan* |
| 3 | *Nama Anggota 3* | *NIM* | *Kelas* | *mis. admin, database, rencana* |

---

## 2. Akun Uji Coba (siap pakai)

Akun berikut sudah dibuat otomatis oleh `database.sql` (password sama untuk keduanya).

| Peran | Username | Email | Password |
|-------|----------|-------|----------|
| Admin | `admin_demo` | `admin@dolanjogja.test` | `demo1234` |
| Wisatawan | `user_demo` | `user@dolanjogja.test` | `demo1234` |

Selain itu ada tiga akun `contoh_*` yang hanya dipakai untuk mengisi data ulasan contoh dan **tidak bisa dipakai login**.

---

## 3. Cara Menjalankan Secara Lokal

1. Salin folder proyek ke dalam `htdocs` XAMPP, misalnya `C:\xampp\htdocs\TugasBesar`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin` → tab **Import** → pilih `database.sql` → **Go**.
   File ini membuat database `db_wisata`, seluruh tabel, data 10 destinasi, ulasan contoh, dan dua akun demo.
4. Sesuaikan `config/koneksi.php` bila perlu (bawaan: host `localhost`, user `root`, password kosong, database `db_wisata`).
5. Buka `http://localhost/TugasBesar/index.php`.

Gunakan alamat `http://localhost/...`, bukan `file://` atau Live Server, karena halaman membutuhkan PHP.

Jika database belum siap (MySQL mati / `database.sql` belum diimpor), aplikasi menampilkan halaman panduan instalasi, bukan error PHP.

---

## 4. Fitur Utama & Lokasi File

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

### Empat fitur unggulan (usulan poin bonus)

Sesuaikan nomor poinnya dengan ketentuan asisten; yang penting lokasi filenya jelas.

1. **Peta interaktif Leaflet** — `peta.php`
   Marker kustom berwarna per kategori, pencarian & filter di panel kiri, popup kaya informasi, rute rencana perjalanan, serta parameter `peta.php?fokus=<id>` untuk menyorot satu destinasi dan `peta.php?rute=1` untuk langsung menggambar rute.
2. **Rencana Dolan (itinerary + anggaran)** — `user/rencana.php`
   Destinasi disusun per hari, urutan bisa dinaikkan/diturunkan, estimasi tiket dihitung otomatis untuk jumlah orang tertentu, kalkulator patungan, tombol salin ringkasan, serta mode cetak **"Itinerary Resmi — Dolan Jogja"** dengan tanggal cetak, jam buka tiap destinasi, ringkasan anggaran, dan kode QR lokal.
3. **Ulasan interaktif** — `destinasi/detail.php`, `review/vote.php`
   Rating bintang, distribusi rating, filter per bintang, pengurutan (termasuk **Paling Membantu**), tombol **"Membantu (👍)"** dengan basis data `review_helps`, dan badge **Kontributor Terverifikasi** untuk penulis ulasan yang ulasannya dinilai membantu.
4. **Pencarian & filter dinamis tanpa reload** — `index.php`, `assets/js/filter-beranda.js`
   Filter suasana/kategori/wilayah yang bisa dikombinasikan, autocomplete pencarian, animasi skeleton saat memuat, acak destinasi, dan riwayat "terakhir dilihat" lewat `localStorage`.

---

## 5. Struktur Folder

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

---

## 6. Catatan Teknis

- **XHTML 1.0 Strict**: semua tag ditutup sendiri (`<br />`, `<img ... />`), atribut boolean ditulis lengkap (`required="required"`), dan elemen berulang memakai `class` + `data-*` (mis. `data-id`, `data-endpoint`) alih-alih `id` ganda.
- **Gambar**: setiap `<img>` punya `alt` deskriptif serta `width`/`height` agar tata letak tidak bergeser saat gambar dimuat.
- **Ulasan**: teks dinormalisasi di server (baris kosong berlebih dirapatkan) dan dibatasi 600 karakter, dengan penghitung karakter real-time di form.
- **Hapus destinasi**: dilakukan dalam satu transaksi (`mysqli_begin_transaction`) yang juga membersihkan `reviews`, `wishlists`, dan `favorites`, lalu file foto dihapus setelah commit.
- **Tabel `review_helps`** dibuat otomatis saat halaman detail pertama kali dibuka, sehingga database lama tetap kompatibel; definisinya juga ada di `database.sql`.
- **Peta & QR** memakai aset publik: Leaflet dari unpkg (butuh internet) dan `assets/js/qrcode.js` (lokal, tanpa internet).
