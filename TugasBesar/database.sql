-- Import file ini lewat phpMyAdmin (tab Import).
-- Peringatan: tabel lama dengan nama yang sama akan dihapus lalu dibuat ulang.
--
-- SUMBER DATA DESTINASI: artikel "50 Destinasi Wisata Jogja Terbaik 2026" di kesatriyanjogja.com
-- (diakses 13 November 2026). Nama, alamat, harga tiket, dan jam buka mengikuti artikel tersebut.
-- Deskripsi ditulis ulang dengan kalimat sendiri.
-- CATATAN: artikel tidak memuat koordinat, jadi latitude/longitude di bawah adalah PERKIRAAN.
--          Cek ulang lewat Google Maps (klik kanan pada lokasi untuk menyalin koordinat).
--          Harga dan jam buka juga bisa berubah, cek kembali sebelum dikumpulkan.
CREATE DATABASE IF NOT EXISTS db_wisata CHARACTER SET utf8mb4;
USE db_wisata;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS destination_photos, review_photos, review_helps, favorites, wishlists, reviews, destinations, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  no_hp VARCHAR(20) DEFAULT NULL,
  foto VARCHAR(255) DEFAULT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(50) NOT NULL
);

-- harga_tiket  = harga terendah (0 = gratis)
-- harga_maks   = harga tertinggi bila ada rentang (misalnya weekday/weekend), NULL bila harga tunggal
CREATE TABLE destinations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  nama VARCHAR(100) NOT NULL,
  lokasi VARCHAR(200) NOT NULL,
  deskripsi TEXT,
  harga_tiket INT NOT NULL DEFAULT 0,
  harga_maks INT DEFAULT NULL,
  jam_buka VARCHAR(100),
  latitude DECIMAL(10,7),
  longitude DECIMAL(10,7),
  foto VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  destination_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL,
  komentar TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE wishlists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  destination_id INT NOT NULL,
  UNIQUE (user_id, destination_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
);

CREATE TABLE favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  destination_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unik_favorit (user_id, destination_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
);

-- Kategori: 1 Budaya, 2 Pantai, 3 Alam, 4 Viral, 5 Keluarga, 6 Belanja
INSERT INTO categories (nama) VALUES ('Budaya'), ('Pantai'), ('Alam'), ('Viral'), ('Keluarga'), ('Belanja');

INSERT INTO destinations (category_id, nama, lokasi, deskripsi, harga_tiket, harga_maks, jam_buka, latitude, longitude) VALUES
(1, 'Candi Prambanan', 'Jl. Raya Solo - Yogyakarta, Kranggan, Prambanan, Sleman',
 'Kompleks candi Hindu terbesar di Indonesia yang dibangun pada abad ke-9 dan diakui UNESCO sebagai warisan dunia. Harga di sini untuk wisatawan nusantara (WNI).',
 10000, 20000, '06.00 - 17.00', -7.7520000, 110.4915000),
(1, 'Keraton Yogyakarta', 'Jl. Rotowijayan Blok No. 1, Panembahan, Kraton, Kota Yogyakarta',
 'Pusat budaya dan kediaman Sultan Yogyakarta. Sesi sore hanya dibuka bagi pengunjung yang ingin menonton pertunjukan budaya.',
 3500, 10000, '06.30 - 13.30 (sore 16.00 - 18.00 untuk pertunjukan)', -7.8053000, 110.3642000),
(2, 'Pantai Parangtritis', 'Parangtritis, Kretek, Bantul',
 'Pantai selatan yang paling dikenal di Jogja dan cukup dekat dari pusat kota, ramai dikunjungi untuk menikmati suasana laut.',
 5000, 10000, '24 jam', -8.0250000, 110.3300000),
(4, 'HeHa Ocean View', 'Bolang, Girikarto, Panggang, Gunungkidul',
 'Tempat wisata bernuansa laut dengan sky deck, kolam infinity, dan restoran rooftop. Cocok untuk foto dan melihat matahari terbenam.',
 30000, 50000, '08.00 - 20.00', -8.0183000, 110.4044000),
(4, 'Tumpeng Menoreh', 'Sumbersari, Ngargoretno, Samigaluh, Kulon Progo',
 'Wisata panorama 360 derajat di perbukitan Menoreh dengan pemandangan pegunungan dan kabut pagi. Paling bagus saat sunrise dan sunset.',
 50000, NULL, '24 jam', -7.6586000, 110.1706000),
(4, 'Tebing Breksi', 'Jl. Desa Lengkong, Sambirejo, Sleman',
 'Bekas tambang batu kapur yang kini jadi geoheritage dan terkenal dengan tebing berukir serta pemandangan lampu kota di malam hari. Harga lebih murah di hari kerja.',
 10000, 15000, '08.00 - 23.00', -7.7846000, 110.5054000),
(3, 'Hutan Pinus Mangunan', 'Jl. Hutan Pinus Nganjir, Mangunan, Dlingo, Bantul',
 'Hutan pinus yang sejuk dengan gardu pandang dan area camping. Favorit untuk jalan santai dan berburu sunrise.',
 10000, 15000, '06.00 - 18.00', -7.9400000, 110.4330000),
(3, 'Goa Pindul', 'Bejiharjo, Karangmojo, Gunungkidul',
 'Wisata menyusuri sungai bawah tanah dengan ban pelampung (cave tubing). Tiket masuk Rp 10.000, sewa ban Rp 70.000 sudah termasuk pemandu.',
 10000, NULL, '07.00 - 17.00', -7.9264000, 110.6433000),
(5, 'Gembira Loka Zoo', 'Jl. Kebun Raya No. 2, Rejowinangun, Kotagede, Kota Yogyakarta',
 'Kebun binatang tertua dan terbesar di Yogyakarta sekaligus pusat konservasi satwa. Harga lebih tinggi di akhir pekan dan hari libur.',
 60000, 75000, '08.30 - 16.00', -7.8063000, 110.3969000),
(6, 'Jalan Malioboro', 'Jl. Malioboro, Sosromenduran, Gedong Tengen, Kota Yogyakarta',
 'Jantung kota Yogyakarta yang selalu ramai, tempat belanja batik, kerajinan, oleh-oleh, dan jajanan khas Jogja.',
 0, NULL, NULL, -7.7929000, 110.3658000);

-- DATA CONTOH (tidak nyata): pengguna dan ulasan agar rating tampil di beranda.
-- Akun ini tidak bisa dipakai login. Hapus bagian ini sebelum dikumpulkan.
INSERT INTO users (username, email, password, nama_lengkap) VALUES
('contoh_sari', 'sari@contoh.test', '-', 'Sari Contoh'),
('contoh_budi', 'budi@contoh.test', '-', 'Budi Contoh'),
('contoh_dewi', 'dewi@contoh.test', '-', 'Dewi Contoh');

INSERT INTO reviews (destination_id, user_id, rating, komentar) VALUES
(1, 1, 5, 'Candinya megah, paling enak datang sore hari.'),
(1, 2, 5, 'Layak dikunjungi, suasananya tenang.'),
(1, 3, 5, 'Arsitekturnya bagus sekali.'),
(2, 1, 4, 'Banyak cerita sejarah, sebaiknya ikut pemandu.'),
(2, 3, 5, 'Pertunjukan budayanya bagus.'),
(3, 2, 4, 'Sunsetnya bagus, ombaknya besar jadi hati-hati.'),
(3, 3, 4, 'Ramai tapi tetap menyenangkan.'),
(4, 1, 5, 'Spot fotonya banyak dan pemandangannya luas.'),
(4, 2, 4, 'Agak ramai saat akhir pekan.'),
(5, 3, 5, 'Sunrise-nya tidak mengecewakan.'),
(6, 2, 4, 'Malam hari lampu kotanya cantik.'),
(7, 1, 5, 'Sejuk dan cocok untuk jalan santai.'),
(7, 3, 4, 'Datang pagi supaya tidak terlalu ramai.'),
(8, 2, 4, 'Seru, pemandunya ramah.'),
(9, 1, 4, 'Cocok untuk keluarga dengan anak.'),
(10, 2, 5, 'Malam hari paling hidup, jajanannya banyak.'),
(10, 3, 4, 'Ramai sekali, sabar saat menyeberang.');

ALTER TABLE wishlists
  ADD COLUMN hari_ke TINYINT NOT NULL DEFAULT 1 AFTER destination_id,
  ADD COLUMN urutan INT NOT NULL DEFAULT 0 AFTER hari_ke;

ALTER TABLE destinations
  ADD COLUMN tag VARCHAR(100) DEFAULT NULL AFTER jam_buka;

-- Tag dipisah koma TANPA spasi. Pilihan: sunset, sunrise, keluarga, hidden-gem, malam
UPDATE destinations SET tag = 'sunset,keluarga'           WHERE nama = 'Candi Prambanan';
UPDATE destinations SET tag = 'keluarga'                  WHERE nama = 'Keraton Yogyakarta';
UPDATE destinations SET tag = 'sunset'                    WHERE nama = 'Pantai Parangtritis';
UPDATE destinations SET tag = 'sunset,malam'              WHERE nama = 'HeHa Ocean View';
UPDATE destinations SET tag = 'sunrise,sunset,hidden-gem' WHERE nama = 'Tumpeng Menoreh';
UPDATE destinations SET tag = 'sunset,malam'              WHERE nama = 'Tebing Breksi';
UPDATE destinations SET tag = 'sunrise,hidden-gem'        WHERE nama = 'Hutan Pinus Mangunan';
UPDATE destinations SET tag = 'hidden-gem'                WHERE nama = 'Goa Pindul';
UPDATE destinations SET tag = 'keluarga'                  WHERE nama = 'Gembira Loka Zoo';
UPDATE destinations SET tag = 'malam,keluarga'            WHERE nama = 'Jalan Malioboro';

-- Akun demo siap login (passwordnya: demo1234)
INSERT INTO users (username, email, password, nama_lengkap, role) VALUES
('admin_demo', 'admin@dolanjogja.test', '$2y$10$gXYSLETR5zDfrUb7u2zOPu08t6xVE3qbi79/OxZMIW4JvEue.jQL6', 'Admin Dolan Jogja', 'admin'),
('user_demo',  'user@dolanjogja.test',  '$2y$10$gXYSLETR5zDfrUb7u2zOPu08t6xVE3qbi79/OxZMIW4JvEue.jQL6', 'Wisatawan Demo',    'user');

ALTER TABLE destinations
  ADD COLUMN wilayah ENUM('Kota Yogyakarta','Sleman','Bantul','Gunungkidul','Kulon Progo') DEFAULT NULL AFTER lokasi,
  ADD COLUMN status ENUM('aktif','arsip') NOT NULL DEFAULT 'aktif' AFTER foto;

UPDATE destinations SET wilayah = 'Sleman'          WHERE nama IN ('Candi Prambanan', 'Tebing Breksi');
UPDATE destinations SET wilayah = 'Kota Yogyakarta' WHERE nama IN ('Keraton Yogyakarta', 'Gembira Loka Zoo', 'Jalan Malioboro');
UPDATE destinations SET wilayah = 'Bantul'          WHERE nama IN ('Pantai Parangtritis', 'Hutan Pinus Mangunan');
UPDATE destinations SET wilayah = 'Gunungkidul'     WHERE nama IN ('HeHa Ocean View', 'Goa Pindul');
UPDATE destinations SET wilayah = 'Kulon Progo'     WHERE nama = 'Tumpeng Menoreh';

CREATE INDEX idx_reviews_dest ON reviews (destination_id);
CREATE INDEX idx_wishlists_user ON wishlists (user_id);
CREATE INDEX idx_dest_kategori ON destinations (category_id);

CREATE TABLE IF NOT EXISTS favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  destination_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unik_favorit (user_id, destination_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
);

-- ===== Fitur "ulasan terbantu" (helpful vote pada ulasan) =====
-- Dibuat juga otomatis oleh includes/ulasan_bantuan.php saat halaman detail dibuka.
CREATE TABLE IF NOT EXISTS review_helps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unik_bantuan (review_id, user_id),
  FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Pastikan kategori di bawah sudah ada di tabel `categories` (atau sesuaikan `category_id`-nya):
-- 1: Budaya & Sejarah | 2: Alam & Hutan | 3: Pantai | 4: Edukasi & Keluarga | 5: Wisata Kota & Kuliner

INSERT INTO destinations (
    category_id, nama, lokasi, harga_tiket, harga_maks, deskripsi, latitude, longitude, foto
) VALUES
(5, 'Tugu Pal Putih Yogyakarta', 'Jetis, Kota Yogyakarta', 0, 0, 'Landmark bersejarah dan titik acuan utama Kota Yogyakarta yang ramai dikunjungi warga maupun wisatawan sepanjang hari.', -7.782889, 110.367083, 'tugu-jogja.jpg'),
(1, 'Keraton Ngayogyakarta Hadiningrat', 'Kraton, Kota Yogyakarta', 15000, 25000, 'Istana resmi Kesultanan Yogyakarta yang menyimpan warisan arsitektur Jawa, museum, dan agenda budaya tradisional.', -7.805285, 110.364203, 'keraton-jogja.jpg'),
(1, 'Taman Sari Yogyakarta', 'Kraton, Kota Yogyakarta', 15000, 25000, 'Situs bekas taman air kerajaan dengan lorong bawah tanah, kolam pemandian, dan bangunan bersejarah bernilai tinggi.', -7.809986, 110.359297, 'taman-sari.jpg'),
(1, 'Museum Benteng Vredeburg', 'Ngupasan, Gondomanan, Kota Yogyakarta', 5000, 10000, 'Benteng peninggalan kolonial di kawasan Malioboro yang kini difungsikan sebagai museum sejarah perjuangan bangsa.', -7.800257, 110.366266, 'vredeburg.jpg'),
(4, 'Taman Pintar Yogyakarta', 'Ngupasan, Gondomanan, Kota Yogyakarta', 12000, 25000, 'Pusat wisata edukasi sains dan teknologi dengan berbagai zona peraga interaktif yang cocok untuk pelajar dan keluarga.', -7.800547, 110.367641, 'taman-pintar.jpg'),
(5, 'Pasar Beringharjo', 'Ngupasan, Gondomanan, Kota Yogyakarta', 0, 0, 'Pasar tradisional ikonik di jantung kota untuk mencari batik, jamu, jajanan pasar, dan beragam buah tangan.', -7.798841, 110.365692, 'beringharjo.jpg'),
(5, 'Jalan Malioboro', 'Sosromenduran, Gedongtengen, Kota Yogyakarta', 0, 0, 'Kawasan wisata belanja dan kuliner malam paling populer di Yogyakarta dengan trotoar lebar dan nuansa khas kota.', -7.792570, 110.365836, 'malioboro.jpg'),
(5, 'Alun-Alun Kidul Yogyakarta', 'Patehan, Kraton, Kota Yogyakarta', 0, 0, 'Ruang terbuka bersejarah di selatan Keraton yang menjadi tempat berkumpul, kuliner malam, dan bersantai.', -7.811776, 110.363198, 'alkid-jogja.jpg'),
(4, 'Gembira Loka Zoo', 'Rejowinangun, Kotagede, Kota Yogyakarta', 60000, 75000, 'Kebun binatang keluarga di timur kota dengan beragam koleksi satwa, taman tematik, dan wahana perahu.', -7.806449, 110.396331, 'gembira-loka.jpg'),
(1, 'Candi Sambisari', 'Purwomartani, Kalasan, Sleman', 10000, 15000, 'Candi Hindu bercorak klasik yang terletak beberapa meter di bawah permukaan tanah dengan halaman asri.', -7.762535, 110.447029, 'candi-sambisari.jpg'),
(1, 'Candi Ratu Boko', 'Bokoharjo, Prambanan, Sleman', 40000, 50000, 'Kompleks situs purbakala di atas bukit yang menawarkan lanskap luas dan pemandangan senja yang menawan.', -7.770492, 110.489435, 'ratu-boko.jpg'),
(1, 'Candi Ijo', 'Sambirejo, Prambanan, Sleman', 10000, 15000, 'Candi Hindu dengan letak paling tinggi di Yogyakarta, menyuguhkan panorama perbukitan dan langit senja.', -7.784130, 110.512126, 'candi-ijo.jpg'),
(2, 'Tebing Breksi', 'Sambirejo, Prambanan, Sleman', 10000, 15000, 'Destinasi wisata alam dan geowisata dengan dinding batuan kapur berukir serta panggung terbuka.', -7.781728, 110.504493, 'tebing-breksi.jpg'),
(2, 'Kalibiru', 'Hargowilis, Kokap, Kulon Progo', 15000, 25000, 'Kawasan wisata alam di Pegunungan Menoreh dengan hawa sejuk dan pemandangan perbukitan hijau.', -7.801207, 110.129248, 'kalibiru.jpg'),
(2, 'Waduk Sermo', 'Hargowilis, Kokap, Kulon Progo', 10000, 15000, 'Waduk yang dikelilingi perbukitan rindang di Kulon Progo, cocok untuk wisata santai dan menikmati udara segar.', -7.818003, 110.118744, 'waduk-sermo.jpg'),
(3, 'Pantai Glagah', 'Glagah, Temon, Kulon Progo', 10000, 15000, 'Pantai selatan Kulon Progo dengan pemecah ombak batu tetrapod, pasir hitam, dan area laguna.', -7.912548, 110.071856, 'pantai-glagah.jpg'),
(2, 'Hutan Pinus Mangunan', 'Mangunan, Dlingo, Bantul', 5000, 10000, 'Hutan pinus sejuk di kawasan Dlingo yang rindang, nyaman untuk berjalan santai dan berfoto.', -7.926798, 110.431775, 'pinus-mangunan.jpg'),
(2, 'Kebun Buah Mangunan', 'Mangunan, Dlingo, Bantul', 10000, 15000, 'Kawasan perbukitan di Bantul dengan pemandangan lembah Sungai Oyo dan udara pagi yang menyegarkan.', -7.943734, 110.424667, 'kebun-buah-mangunan.jpg'),
(2, 'Puncak Becici', 'Mangunan, Dlingo, Bantul', 5000, 10000, 'Spot wisata alam hutan pinus dan gardu pandang di Dlingo dengan suasana tenang dan pepohonan tinggi.', -7.919163, 110.439185, 'puncak-becici.jpg'),
(3, 'Pantai Parangtritis', 'Kretek, Bantul', 15000, 20000, 'Pantai ikonik di selatan Yogyakarta dengan garis pantai panjang, ombak besar, dan panorama sunset.', -8.025852, 110.333022, 'parangtritis.jpg'),
(3, 'Gumuk Pasir Parangkusumo', 'Gadingharjo, Sanden, Bantul', 10000, 15000, 'Fenomena alam gumuk pasir pesisir yang unik di Bantul dan menjadi lokasi aktivitas papan selancar pasir.', -8.015386, 110.315644, 'gumuk-pasir.jpg'),
(3, 'Pantai Indrayanti', 'Tepus, Gunungkidul', 15000, 20000, 'Pantai berpasir putih di pesisir Gunungkidul dengan air laut jernih dan fasilitas rumah makan tepi pantai.', -8.150583, 110.613022, 'pantai-indrayanti.jpg'),
(3, 'Pantai Pok Tunggal', 'Tepus, Gunungkidul', 15000, 20000, 'Pantai berpasir putih di Gunungkidul yang dikenal dengan pohon duras di pinggir pantai dan tebing kapur.', -8.149421, 110.577244, 'pok-tunggal.jpg'),
(3, 'Pantai Wediombo', 'Jepitu, Girisubo, Gunungkidul', 10000, 15000, 'Pantai selatan Gunungkidul berbentuk teluk dengan pasir putih, batu karang, dan suasana alami.', -8.187718, 110.678831, 'pantai-wediombo.jpg'),
(2, 'Goa Pindul', 'Bejiharjo, Karangmojo, Gunungkidul', 50000, 100000, 'Wisata susur sungai di dalam gua kapur menggunakan ban pelampung dengan pengalaman petualangan yang seru.', -7.928848, 110.646336, 'goa-pindul.jpg'),
(2, 'Air Terjun Sri Gethuk', 'Bleberan, Playen, Gunungkidul', 20000, 30000, 'Air terjun di aliran Sungai Oyo, Gunungkidul, dengan suasana tebing alami dan akses wisata yang khas.', -7.893556, 110.547194, 'sri-gethuk.jpg');

-- ===== Indeks tambahan untuk pencarian, filter wilayah, dan relasi antar tabel =====
CREATE INDEX idx_reviews_user ON reviews (user_id);
CREATE INDEX idx_wishlists_destination ON wishlists (destination_id);
CREATE INDEX idx_favorites_user ON favorites (user_id);
CREATE INDEX idx_favorites_destination ON favorites (destination_id);
CREATE INDEX idx_dest_wilayah ON destinations (wilayah);
-- ===== Galeri foto multi-slide (detail destinasi) =====
-- Tabel ini juga dibuat otomatis oleh includes/galeri.php saat halaman detail/admin dibuka.
CREATE TABLE IF NOT EXISTS destination_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    keterangan VARCHAR(150) DEFAULT NULL,
    urutan TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_destinasi_foto (destination_id, urutan),
    CONSTRAINT fk_foto_destinasi FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== Foto kunjungan dari pengunjung (satu foto per ulasan) =====
-- Tabel ini juga dibuat otomatis oleh includes/foto_ulasan.php saat halaman detail dibuka.
CREATE TABLE IF NOT EXISTS review_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unik_foto_ulasan (review_id),
    CONSTRAINT fk_foto_ulasan FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
