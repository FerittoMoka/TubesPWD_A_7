# Dolan Jogja

---

## 1. Anggota Kelompok

> `TubesPWD_<Kelas>_<Kelompok>`.

| Nama | NPM | Bagian |
|-------|----------|-------|
| Daniel Valentino Y | 250713197 | Register, proses register, bonus |
| Nama | NPM | bagian |


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
| Beranda & pencarian | Hero, pencarian dengan autocomplete, chip filter suasana/kategori, kartu wilayah, acak destinasi, tombol bandingkan 2 destinasi | `index.php`, `assets/js/main.js`, `assets/js/filter-beranda.js`, `assets/js/bandingkan.js`, `includes/mood.php`, `includes/wilayah.php` |
| Detail destinasi | Galeri horizontal (swipe/drag, thumbnail, caption, lightbox, keyboard), strip info praktis, galeri foto komunitas, rating & distribusi bintang, ulasan, favorit, kartu tiket + estimasi rombongan | `destinasi/detail.php`, `includes/fungsi.php`, `includes/galeri.php`, `includes/foto_ulasan.php`, `assets/uploads/destinasi/galeri/<id>/` |
| Ulasan | Tulis/ubah/hapus ulasan + lampiran 1 foto kunjungan, filter bintang, urut terbaru/rating, vote "Membantu" & badge kontributor | `review/simpan.php`, `review/hapus.php`, `review/vote.php`, `includes/ulasan_bantuan.php`, `includes/foto_ulasan.php` |
| Peta interaktif | Leaflet + OpenStreetMap, filter kategori, pencarian, rute rencana, sorot destinasi dari halaman detail, tandai posisi lewat klik peta atau koordinat manual | `peta.php` |
| Rencana Dolan | Simpan destinasi, atur hari & urutan, jam mulai per hari dengan jadwal tiba/keluar + peringatan jam buka, estimasi tiket, estimasi jarak & biaya transport, kalkulator patungan, cetak itinerary + QR | `user/rencana.php`, `user/toggle_rencana.php`, `includes/rencana_jadwal.php`, `assets/js/qrcode.js` |
| Favorit | Tandai/lepas favorit tanpa reload, badge jumlah favorit | `user/toggle_favorit.php`, `user/wishlist.php` |
| Terdekat | Geolocation dua tahap + fallback Tugu Jogja, titik keberangkatan populer, input koordinat manual, hasil tanpa reload (AJAX), jarak Haversine | `destinasi/terdekat.php`, `assets/js/geolokasi.js` |
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


---

## 5. Catatan fitur terbaru

### Galeri foto horizontal destinasi
- Galeri multi-slide di `destinasi/detail.php`: geser dengan sentuhan/mouse, tombol panah, thumbnail, penghitung `1 / 3`, caption foto, dan lightbox layar penuh (klik foto, tombol `Esc` untuk menutup).
- Panah keyboard kiri/kanan aktif saat galeri terlihat, posisi slide tersimpan di URL hash (`#foto-2`), dan slide tetangga dipreload agar transisi tidak kosong.
- Sumber foto: tabel `destination_photos` (unggahan admin, punya caption) lalu berkas di `assets/uploads/destinasi/galeri/<id>/`. Bila keduanya kosong dipakai kolom `foto`; bila file itu juga tidak ada, tampil kartu monogram.
- Contoh folder yang sudah terisi: Candi Prambanan (id 1), Pantai Parangtritis (id 3), Tebing Breksi (id 6), dan Jalan Malioboro (id 10).
- Di bawah galeri ada strip info praktis: tiket masuk, jam operasional, estimasi durasi kunjungan, dan akses/parkir (durasi & akses diturunkan dari kategori).

### Kelola galeri dari panel admin (`admin/destinasi.php`)
- Input `galeri_foto[]` bisa memilih banyak foto sekaligus (JPG/PNG/WEBP, maks. 5 MB/foto). Foto otomatis diperkecil ke lebar maks. 1280 px dan dikompres JPEG kualitas 82 lewat GD (`simpan_gambar_teroptimasi()`).
- Setiap foto punya kolom keterangan (caption) dan centang **Hapus foto ini** yang diproses saat menyimpan.
- Tabel `admin/index.php` menampilkan lencana jumlah foto: hijau `3+ Foto`, kuning `1-2 Foto`, merah `0 Foto`.
- Tabel `destination_photos` dibuat otomatis oleh `includes/galeri.php` bila belum ada, dan juga tersedia di `database.sql` untuk impor bersih.

### Foto dari pengunjung (community photo)
- Form ulasan di `destinasi/detail.php` punya input opsional `foto_ulasan` (JPG/PNG/WEBP, maks. 5 MB): satu foto pengalaman kunjungan per ulasan, otomatis diperkecil ke lebar maks. 1280 px lewat GD.
- Foto tampil sebagai lampiran di ulasan pemiliknya dan dikumpulkan di strip **Foto dari pengunjung** di bawah galeri resmi (maks. 8 foto terbaru).
- Foto bisa diganti dengan mengunggah file baru atau dihapus lewat centang **Hapus foto kunjungan**; berkas di `assets/uploads/ulasan/<id>/` ikut terhapus saat ulasan dihapus.
- Data disimpan pada tabel `review_photos` (`review_id` unik, cascade saat ulasan dihapus), dibuat otomatis oleh `includes/foto_ulasan.php` dan tersedia di `database.sql`.

### Kartu perbandingan 2 destinasi
- Setiap kartu di beranda punya tombol **Bandingkan**; pilihan (maks. 2) disimpan di `localStorage` sehingga tidak hilang saat filter/pencarian berubah.
- Panel perbandingan menampilkan foto utama, kategori, wilayah, harga tiket, jam buka, rating ulasan, dan jarak dari lokasi pengunjung (dihitung saat tombol **Hitung jarak dari lokasi saya** ditekan, rumus Haversine).
- Logika ada di `assets/js/bandingkan.js`; data kartu dikirim lewat atribut `data-b-*` di `index.php`, gaya di `assets/css/style.css`.

### Geolocation "Gunakan lokasi saya"
- `assets/js/geolokasi.js` memakai dua tahap: lokasi jaringan (cepat) lalu akurasi tinggi, lengkap dengan pesan error spesifik dan tombol fallback Tugu Jogja.
- Fitur ini hanya aktif di `http://localhost` / `http://127.0.0.1` atau HTTPS. Hasil terdekat dimuat tanpa reload halaman (JSON `terdekat.php?ajax=1`).
- Desktop tanpa GPS (atau izin lokasi ditolak) tetap bisa memakai fitur: di halaman **Terdekat** isi Latitude/Longitude lalu **Hitung dari koordinat** (form juga bekerja tanpa JavaScript), dan di halaman **Peta** klik titik mana pun atau isi koordinat lalu **Tandai**. Angka bisa disalin dari Google Maps (klik kanan lokasi) dan boleh memakai koma desimal.
- Untuk demo: DevTools > More tools > Sensors > Location = `-7.782889, 110.367083`.

### Jadwal jam & validasi jam buka (Rencana Dolan)
- Setiap hari punya jam mulai sendiri (disimpan di tabel `rencana_hari`, dibuat otomatis oleh `includes/rencana_jadwal.php`).
- Dari jam mulai itu, halaman menghitung perkiraan **waktu tiba dan selesai** tiap destinasi memakai estimasi durasi per kategori dan kecepatan rata-rata 30 km/jam, termasuk jarak tempuh antartitik.
- Jadwal dibandingkan dengan teks `jam_buka`: muncul peringatan bila tiba sebelum destinasi buka, tiba setelah tutup, atau perkiraan selesai melewati jam tutup. Destinasi tanpa data jam buka ditandai sekali per hari, bukan berulang di setiap baris.
- Seluruh destinasi kini punya teks `jam_buka`. Data batch kedua diisi lewat blok `UPDATE` di akhir `database.sql` yang hanya menyentuh kolom kosong; nilainya perkiraan dari informasi publik (November 2026) dan sebaiknya dicek ulang sebelum dipakai sungguhan.
- **Hari tutup** disimpan di kolom `destinations.hari_tutup` (SET hari, mis. `senin`; kosong = buka setiap hari) dan bisa diatur lewat centang di `admin/destinasi.php`. Halaman detail menampilkan "Tutup setiap …", sedangkan Rencana Dolan memberi peringatan bila hari kunjungan jatuh di hari tutup — bukan hanya membandingkan jam.
- **Tanggal mulai** (`rencana_meta.tanggal_mulai`, satu nilai per pengguna) adalah satu-satunya sumber hari: hari ke-N = tanggal mulai + (N−1), sehingga peringatan hari tutup muncul otomatis tanpa memilih hari tiap hari. Tanggal ikut tampil di judul hari, teks salin, dan header cetak. Tanggal tidak valid atau sudah lewat ditolak saat disimpan, dan tombol **Kosongkan** menghapusnya.
- Jam mulai ikut tercetak di itinerary dan di teks tombol **Salin ringkasan**.

### Estimasi jarak & biaya transport (Rencana Dolan)
- Jarak rute dihitung dari koordinat destinasi yang berurutan tiap hari (rumus Haversine), lalu dikalikan tarif moda: Motor Rp 1.500/km, Mobil Rp 2.500/km, Sewa mobil + driver Rp 6.000/km.

### Pencegahan destinasi duplikat
- Seed hanya memuat satu baris per destinasi. Tujuh baris kembar di batch kedua (Keraton, Malioboro, Gembira Loka, Hutan Pinus, Parangtritis, Tebing Breksi, Goa Pindul) sudah dihapus; foto/harga/jam/koordinat terbarunya digabung ke baris batch pertama yang menyimpan ulasan.
- `destinations.nama` memakai `UNIQUE KEY unik_nama_destinasi`, sehingga impor ulang atau penambahan lewat admin tidak bisa membuat nama yang sama.
- Form admin juga menolak nama yang mirip (mis. "Keraton Yogyakarta" vs "Keraton Ngayogyakarta Hadiningrat") lewat `destinasi_kembar()` di `includes/fungsi.php`, dengan pesan yang menyebut destinasi yang sudah ada.

### Indeks database
- `database.sql` menambahkan indeks `idx_reviews_user`, `idx_wishlists_destination`, `idx_favorites_user`, `idx_favorites_destination`, dan `idx_dest_wilayah` untuk mempercepat join, filter wilayah, dan urutan rating.
