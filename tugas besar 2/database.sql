-- ============================================================
--  DOLAN JOGJA - Database (MySQL / MariaDB)
--  Import lewat phpMyAdmin atau: mysql -u root < database.sql
--  Catatan: file ini menghapus & membuat ulang tabel (data baru).
-- ============================================================
CREATE DATABASE IF NOT EXISTS db_wisata CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_wisata;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS wishlists, reviews, destinations, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(30)  NOT NULL UNIQUE,
  email        VARCHAR(100) NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,              -- hash bcrypt (password_hash)
  nama_lengkap VARCHAR(100) NOT NULL,
  no_hp        VARCHAR(20)  DEFAULT NULL,
  foto         VARCHAR(255) DEFAULT NULL,          -- nama file di uploads/profil/
  role         ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE destinations (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  nama        VARCHAR(100) NOT NULL,
  lokasi      VARCHAR(200) NOT NULL,
  deskripsi   TEXT,
  harga_tiket INT NOT NULL DEFAULT 0,
  harga_maks  INT DEFAULT NULL,
  jam_buka    VARCHAR(100) DEFAULT NULL,
  latitude    DECIMAL(10,7) DEFAULT NULL,
  longitude   DECIMAL(10,7) DEFAULT NULL,
  foto        VARCHAR(255) DEFAULT NULL,           -- nama file di uploads/destinasi/
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_destination_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  destination_id INT NOT NULL,
  user_id        INT NOT NULL,
  rating         TINYINT NOT NULL,
  komentar       TEXT,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review (destination_id, user_id),
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_review_destination FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE wishlists (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  destination_id INT NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wishlist (user_id, destination_id),
  CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_destination FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- DATA: kategori ----------
INSERT INTO categories (nama) VALUES
('Budaya'),
('Sejarah & Museum'),
('Pantai'),
('Alam'),
('Petualangan'),
('Keluarga'),
('Spot Foto'),
('Belanja & Kuliner');

-- ---------- DATA: pengguna ----------
-- Admin  : admin / Admin#2026
-- User   : budi.santoso, sari.wulandari, andi.pratama, maya.kusuma, rizky.ramadhan, nadia.putri / User#2026
INSERT INTO users (username,email,password,nama_lengkap,no_hp,role,created_at) VALUES
('admin','admin@dolanjogja.test','$2y$10$33L23eFcJjky8D/2rcZO7uDaJhJ3rMIc.we9VCi7q3hCQIeHdV1oq','Administrator Dolan Jogja','081200000001','admin','2026-09-01 08:00:00'),
('budi.santoso','budi@example.com','$2y$10$WG7.V754hoy0zm6CKFWqxukD3b6dmK1z0PaceI3.ct52p.CWUSk.q','Budi Santoso','081200000002','user','2026-09-10 10:15:00'),
('sari.wulandari','sari@example.com','$2y$10$FirDnyPdQ6LdxPhP7eQwL.i8I8KBOQcJFnzBjL8Uj10pH8HfSAxDy','Sari Wulandari','081200000003','user','2026-09-12 13:40:00'),
('andi.pratama','andi@example.com','$2y$10$LMLckW.DL4o8spCiyfKNGOsQyJJUtYbz.F0omFRrdRZwI2xdv6hvi','Andi Pratama','081200000004','user','2026-09-20 09:05:00'),
('maya.kusuma','maya@example.com','$2y$10$V//kyYRolnARYqvMTFGgCuZpyzEXLEGGao22dlod1ZfonhCjvDWQe','Maya Kusuma','081200000005','user','2026-10-02 16:20:00'),
('rizky.ramadhan','rizky@example.com','$2y$10$.mMhID1F31wRQWRjqC/Sm.t1AcsSckfoG3n09T.HhMCCsZSrA9EGO','Rizky Ramadhan','081200000006','user','2026-10-08 11:00:00'),
('nadia.putri','nadia@example.com','$2y$10$2vZcqQh8EZe44RsjEHZQvu2oEfANfQj4TKkQeNAsAs.8BttWMoXK.','Nadia Putri','081200000007','user','2026-10-15 19:30:00');

-- ---------- DATA: destinasi (harga & jam buka = perkiraan, verifikasi sebelum dikumpulkan) ----------
INSERT INTO destinations (category_id,nama,lokasi,deskripsi,harga_tiket,harga_maks,jam_buka,latitude,longitude) VALUES
(1,'Candi Prambanan','Bokoharjo, Prambanan, Sleman','Kompleks candi Hindu abad ke-9 dengan Candi Siwa sebagai bangunan terbesarnya. Waktu terbaik berkunjung adalah pagi atau sore hari; pada jadwal tertentu tersedia pertunjukan Sendratari Ramayana.',50000,75000,'06.30 - 17.00',-7.7520,110.4915),
(2,'Candi Ratu Boko','Bokoharjo, Prambanan, Sleman','Situs bekas kompleks istana di atas bukit yang terkenal sebagai tempat menikmati matahari terbenam dengan latar Candi Prambanan dan Gunung Merapi.',50000,75000,'06.00 - 17.00',-7.7705,110.4890),
(1,'Keraton Yogyakarta','Jl. Rotowijayan, Kraton, Kota Yogyakarta','Istana Kesultanan Ngayogyakarta Hadiningrat sekaligus pusat kebudayaan Jawa. Pengunjung dapat melihat bangunan, koleksi benda pusaka, dan pertunjukan seni pada hari tertentu.',15000,25000,'08.30 - 14.00',-7.8053,110.3642),
(2,'Taman Sari','Patehan, Kraton, Kota Yogyakarta','Bekas taman dan tempat peristirahatan keluarga sultan dengan kolam pemandian, lorong, dan bangunan yang memadukan gaya Jawa dan Eropa.',15000,NULL,'09.00 - 15.00',-7.8100,110.3594),
(2,'Museum Benteng Vredeburg','Jl. Margo Mulyo No. 6, Ngupasan, Gondomanan, Kota Yogyakarta','Benteng masa kolonial Belanda yang kini menjadi museum sejarah perjuangan bangsa dengan koleksi diorama dan arsip.',5000,NULL,'08.30 - 16.00',-7.8002,110.3664),
(2,'Museum Ullen Sentalu','Kaliurang, Hargobinangun, Pakem, Sleman','Museum seni dan budaya Jawa di kawasan Kaliurang yang menampilkan batik, busana, dan kisah kerabat keraton. Kunjungan dilakukan bersama pemandu.',50000,100000,'08.30 - 16.00',-7.5985,110.4252),
(3,'Pantai Parangtritis','Parangtritis, Kretek, Bantul','Pantai selatan yang populer dengan hamparan pasir gelap, ombak besar, dan suasana senja. Perhatikan rambu peringatan dan hindari berenang terlalu jauh dari tepi.',10000,15000,'24 jam',-8.0253,110.3317),
(3,'Pantai Indrayanti','Tepus, Gunungkidul','Pantai berpasir putih di Gunungkidul dengan deretan warung makan seafood dan area bersantai di tepi pantai.',10000,20000,'07.00 - 18.00',-8.1505,110.6125),
(3,'Pantai Baron','Kemadang, Tanjungsari, Gunungkidul','Pantai berteluk yang diapit tebing karang dan menjadi tempat nelayan mendaratkan hasil tangkapan. Mudah dijangkau dan dekat dengan beberapa pantai lain.',10000,15000,'07.00 - 18.00',-8.1244,110.5493),
(4,'Gumuk Pasir Parangkusumo','Parangkusumo, Kretek, Bantul','Bentang gumuk pasir pesisir yang jarang ditemukan di Indonesia. Aktivitas seperti sandboarding biasanya ditawarkan penyedia jasa setempat.',0,NULL,'Area publik',-8.0236,110.3292),
(4,'Hutan Pinus Mangunan','Mangunan, Dlingo, Bantul','Hutan pinus berudara sejuk dengan area santai, spot foto, dan akses ke beberapa titik pandang di kawasan Dlingo.',10000,15000,'06.00 - 18.00',-7.9400,110.4330),
(5,'Goa Pindul','Bejiharjo, Karangmojo, Gunungkidul','Wisata susur gua dengan ban pelampung (cave tubing) menyusuri sungai di dalam gua.',50000,75000,'07.00 - 17.00',-7.9264,110.6433),
(7,'Tebing Breksi','Sambirejo, Prambanan, Sleman','Bekas tambang batu breksi yang ditata menjadi taman wisata dengan dinding tebing berukir dan panggung terbuka. Ramai pada sore dan malam hari.',10000,15000,'08.00 - 23.00',-7.7846,110.5054),
(7,'HeHa Ocean View','Girikarto, Panggang, Gunungkidul','Destinasi di perbukitan Panggang dengan area pandang ke laut selatan, banyak spot foto, dan tempat makan.',30000,50000,'08.00 - 20.00',-8.0183,110.4044),
(7,'Hutan Wisata Kalibiru','Hargowilis, Kokap, Kulon Progo','Kawasan hutan di perbukitan Menoreh dengan titik pandang ke perbukitan dan waduk di sekitarnya serta wahana foto di antara pepohonan.',10000,50000,'07.00 - 17.00',-7.8187,110.1558),
(6,'Gembira Loka Zoo','Rejowinangun, Kotagede, Kota Yogyakarta','Kebun binatang dengan beragam satwa dan area rekreasi keluarga yang cocok untuk anak-anak.',60000,75000,'08.30 - 16.00',-7.8063,110.3969),
(6,'Taman Pintar Yogyakarta','Jl. Panembahan Senopati No. 1-3, Ngupasan, Gondomanan, Kota Yogyakarta','Taman sains interaktif dengan wahana edukasi dan area eksperimen untuk anak serta keluarga.',10000,40000,'08.00 - 16.00',-7.8003,110.3677),
(6,'Alun-Alun Kidul','Patehan, Kraton, Kota Yogyakarta','Lapangan di selatan Keraton yang ramai pada malam hari, dengan sepasang pohon beringin dan sewa becak lampu hias.',0,NULL,'Area publik (ramai malam hari)',-7.8118,110.3630),
(8,'Jalan Malioboro','Sosromenduran, Gedong Tengen, Kota Yogyakarta','Jalan utama kawasan kota dengan pertokoan, pedagang suvenir, kuliner, dan akses ke berbagai titik wisata di sekitar Keraton dan Tugu.',0,NULL,'Area publik',-7.7929,110.3658),
(8,'Pasar Beringharjo','Jl. Margo Mulyo No. 16, Ngupasan, Gondomanan, Kota Yogyakarta','Pasar tradisional di kawasan Malioboro yang terkenal dengan batik, jajanan pasar, dan aneka kebutuhan sehari-hari.',0,NULL,'08.00 - 17.00',-7.7987,110.3659);

-- ---------- DATA: ulasan ----------
INSERT INTO reviews (destination_id,user_id,rating,komentar,created_at) VALUES
(1,2,5,'Candinya megah sekali. Datang pagi hari lebih sejuk dan belum terlalu ramai.','2026-10-05 09:12:00'),
(1,3,4,'Kompleksnya luas, siapkan topi dan air minum. Fotonya bagus terutama saat sore.','2026-10-11 15:30:00'),
(1,5,5,'Cocok untuk belajar sejarah. Area parkir dan fasilitasnya cukup rapi.','2026-10-19 10:05:00'),
(2,4,5,'Matahari terbenamnya luar biasa. Sebaiknya tiba satu jam sebelum sunset.','2026-10-07 17:45:00'),
(2,6,4,'Pemandangannya indah, tetapi jalannya agak menanjak untuk lansia.','2026-10-22 16:10:00'),
(3,3,4,'Banyak koleksi menarik. Lebih seru jika ikut dipandu agar paham ceritanya.','2026-10-09 11:20:00'),
(3,7,5,'Suasana budayanya terasa kuat. Pertunjukan seninya layak ditonton.','2026-10-24 10:40:00'),
(4,2,4,'Lorong dan kolamnya unik. Cocok dikunjungi bersama teman atau keluarga.','2026-10-12 13:15:00'),
(4,5,3,'Menarik, tetapi beberapa lorong membingungkan jika tidak ditemani pemandu.','2026-10-26 14:00:00'),
(5,4,4,'Diorama dan koleksinya informatif. Tiket sangat terjangkau.','2026-10-14 10:25:00'),
(5,6,5,'Museum yang nyaman dan teduh, pas untuk belajar sejarah perjuangan.','2026-10-28 11:50:00'),
(6,3,5,'Pemandunya komunikatif dan koleksinya tertata sangat apik.','2026-10-16 10:10:00'),
(6,7,4,'Udara Kaliurang sejuk. Kunjungan berkelompok sehingga perlu datang lebih awal.','2026-10-30 09:30:00'),
(7,2,4,'Pantainya luas dan sunsetnya cantik. Ombaknya besar, jadi tetap waspada.','2026-10-06 17:20:00'),
(7,4,3,'Pemandangannya bagus, tetapi cukup ramai saat akhir pekan.','2026-10-18 16:45:00'),
(7,6,4,'Cocok untuk bersantai sore hari. Banyak warung makan di sekitar pantai.','2026-11-01 17:00:00'),
(8,5,5,'Pasirnya putih dan bersih. Seafood di warung sekitarnya enak.','2026-10-20 12:30:00'),
(8,3,4,'Pantai yang indah, tetapi perjalanannya cukup jauh dari kota.','2026-11-02 13:10:00'),
(9,6,4,'Teluknya tenang dan pemandangannya bagus. Cocok sebagai persinggahan.','2026-10-25 14:20:00'),
(10,7,4,'Seru untuk foto dan mencoba sandboarding. Bawa kacamata agar pasir tidak masuk mata.','2026-11-03 15:55:00'),
(11,2,5,'Sejuk dan teduh, nyaman untuk bersantai. Pagi hari paling enak.','2026-10-08 08:40:00'),
(11,5,4,'Suasananya tenang dan banyak spot foto. Jalan menuju lokasi cukup berkelok.','2026-10-27 09:15:00'),
(12,4,5,'Susur guanya seru dan pemandu ramah. Siapkan baju ganti.','2026-10-10 10:50:00'),
(12,6,5,'Pengalaman yang berkesan, cocok untuk rombongan teman.','2026-11-04 11:20:00'),
(13,3,4,'Dinding tebingnya berukir indah. Malam hari lampunya cantik.','2026-10-13 19:10:00'),
(13,7,5,'Tempatnya luas dan nyaman untuk bersantai sambil menunggu senja.','2026-11-05 17:30:00'),
(14,2,3,'Pemandangannya bagus, tetapi antrean spot foto cukup lama saat ramai.','2026-10-21 16:40:00'),
(15,5,4,'Udara sejuk dan pemandangannya menenangkan. Jalan menuju lokasi menanjak.','2026-10-29 10:00:00'),
(16,3,4,'Satwanya beragam dan area bermainnya luas. Anak-anak senang.','2026-10-15 11:35:00'),
(16,5,5,'Cocok untuk liburan keluarga. Datang pagi agar tidak terlalu panas.','2026-11-06 10:20:00'),
(17,4,4,'Wahananya edukatif dan interaktif, pas untuk anak sekolah.','2026-10-23 13:05:00'),
(18,7,4,'Ramai dan seru di malam hari. Cobalah naik becak lampu.','2026-11-07 20:15:00'),
(19,2,5,'Selalu ramai dan hidup. Banyak pilihan oleh-oleh dan kuliner.','2026-10-04 19:00:00'),
(19,6,4,'Enak untuk jalan kaki sore hari. Siapkan waktu agar tidak terburu-buru.','2026-11-08 18:10:00'),
(20,3,5,'Pilihan batiknya banyak dan harganya bisa ditawar. Datang pagi lebih nyaman.','2026-10-17 09:50:00'),
(20,4,4,'Jajanan pasarnya lengkap. Lantainya agak sempit, hati-hati saat ramai.','2026-11-09 10:35:00');

-- ---------- DATA: favorit ----------
INSERT INTO wishlists (user_id,destination_id) VALUES
(2,1),
(2,12),
(2,11),
(3,2),
(3,13),
(3,20),
(4,12),
(4,2),
(5,8),
(5,19),
(6,7),
(6,1),
(7,6),
(7,16);
