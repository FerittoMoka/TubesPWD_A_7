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
DROP TABLE IF EXISTS favorites, wishlists, reviews, destinations, categories, users;
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