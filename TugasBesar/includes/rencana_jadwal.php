<?php
// Jadwal jam pada Rencana Dolan: jam mulai dan tanggal per hari, waktu tiba/keluar tiap destinasi,
// estimasi perjalanan antartitik, serta peringatan jam buka dan hari tutup.
// Hari kunjungan selalu diturunkan dari tanggal mulai, jadi tidak ada penyimpanan hari terpisah.
require_once __DIR__ . "/jam_buka.php";
require_once __DIR__ . "/fungsi.php";

function pastikan_rencana_hari($conn) {
    $ok = (bool) @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rencana_hari (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        hari_ke TINYINT NOT NULL,
        jam_mulai TIME NOT NULL DEFAULT '08:00:00',
        UNIQUE KEY unik_hari_rencana (user_id, hari_ke),
        CONSTRAINT fk_hari_pengguna FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* Satu tanggal mulai per pengguna; hari ke-N dihitung dari sini.
       revisi/diubah dipakai ekspor kalender agar impor ulang memperbarui event yang sama. */
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rencana_meta (
        user_id INT NOT NULL PRIMARY KEY,
        tanggal_mulai DATE DEFAULT NULL,
        revisi INT NOT NULL DEFAULT 0,
        diubah DATETIME DEFAULT NULL,
        CONSTRAINT fk_meta_pengguna FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($conn, "ALTER TABLE rencana_meta ADD COLUMN IF NOT EXISTS revisi INT NOT NULL DEFAULT 0");
    @mysqli_query($conn, "ALTER TABLE rencana_meta ADD COLUMN IF NOT EXISTS diubah DATETIME DEFAULT NULL");

    return $ok;
}

/* Peta hari_ke => "HH:MM" milik satu pengguna. */
function ambil_jam_mulai_hari($conn, $uid) {
    $hasil = array();
    $st = @mysqli_prepare($conn, "SELECT hari_ke, TIME_FORMAT(jam_mulai, '%H:%i') AS jam FROM rencana_hari WHERE user_id = ?");
    if (!$st) { return $hasil; }
    mysqli_stmt_bind_param($st, "i", $uid);
    mysqli_stmt_execute($st);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC) as $b) {
        $hasil[(int) $b["hari_ke"]] = $b["jam"];
    }
    mysqli_stmt_close($st);
    return $hasil;
}

function simpan_jam_mulai_hari($conn, $uid, $hari, $menit) {
    $st = @mysqli_prepare($conn, "INSERT INTO rencana_hari (user_id, hari_ke, jam_mulai) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE jam_mulai = VALUES(jam_mulai)");
    if (!$st) { return false; }
    $jam = jam_input($menit) . ":00";
    mysqli_stmt_bind_param($st, "iis", $uid, $hari, $jam);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    if ($ok) { tandai_rencana_berubah($conn, $uid); }
    return (bool) $ok;
}

/* "Y-m-d" atau "" bila belum diatur. */
function ambil_tanggal_mulai($conn, $uid) {
    $st = @mysqli_prepare($conn, "SELECT DATE_FORMAT(tanggal_mulai, '%Y-%m-%d') AS tgl FROM rencana_meta WHERE user_id = ?");
    if (!$st) { return ""; }
    mysqli_stmt_bind_param($st, "i", $uid);
    mysqli_stmt_execute($st);
    $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    return ($baris && $baris["tgl"]) ? $baris["tgl"] : "";
}

function simpan_tanggal_mulai($conn, $uid, $tanggal) {
    $st = @mysqli_prepare($conn, "INSERT INTO rencana_meta (user_id, tanggal_mulai) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE tanggal_mulai = VALUES(tanggal_mulai)");
    if (!$st) { return false; }
    mysqli_stmt_bind_param($st, "is", $uid, $tanggal);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    if ($ok) { tandai_rencana_berubah($conn, $uid); }
    return (bool) $ok;
}

/* Tanggal dikosongkan tanpa menghapus baris, agar penghitung revisi kalender tetap ada. */
function hapus_tanggal_mulai($conn, $uid) {
    $st = @mysqli_prepare($conn, "UPDATE rencana_meta SET tanggal_mulai = NULL WHERE user_id = ?");
    if (!$st) { return false; }
    mysqli_stmt_bind_param($st, "i", $uid);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    if ($ok) { tandai_rencana_berubah($conn, $uid); }
    return (bool) $ok;
}

/* Tandai rencana berubah: penghitung naik dan waktu ubah dicatat untuk SEQUENCE/LAST-MODIFIED. */
function tandai_rencana_berubah($conn, $uid) {
    $st = @mysqli_prepare($conn, "INSERT INTO rencana_meta (user_id, revisi, diubah) VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE revisi = revisi + 1, diubah = NOW()");
    if (!$st) { return false; }
    mysqli_stmt_bind_param($st, "i", $uid);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return (bool) $ok;
}

/* array("revisi" => int, "diubah" => "Y-m-d H:i:s" atau ""). */
function info_revisi_rencana($conn, $uid) {
    $hasil = array("revisi" => 0, "diubah" => "");
    $st = @mysqli_prepare($conn, "SELECT revisi, DATE_FORMAT(diubah, '%Y-%m-%d %H:%i:%s') AS ubah FROM rencana_meta WHERE user_id = ?");
    if (!$st) { return $hasil; }
    mysqli_stmt_bind_param($st, "i", $uid);
    mysqli_stmt_execute($st);
    $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if ($baris) {
        $hasil["revisi"] = (int) $baris["revisi"];
        $hasil["diubah"] = (string) ($baris["ubah"] ?? "");
    }
    return $hasil;
}

/* Tanggal Y-m-d yang benar-benar ada. $boleh_lampau = false menolak tanggal sebelum hari ini (WIB). */
function tanggal_valid($tanggal, $boleh_lampau = false) {
    $tanggal = (string) $tanggal;
    $t = DateTime::createFromFormat("!Y-m-d", $tanggal);
    if (!$t || $t->format("Y-m-d") !== $tanggal) { return false; }
    if ($boleh_lampau) { return true; }
    $hari_ini = new DateTimeImmutable("now", new DateTimeZone("Asia/Jakarta"));
    return $tanggal >= $hari_ini->format("Y-m-d");
}

/* Tanggal dan hari pekan untuk hari ke-$n, dihitung dari tanggal mulai.
   Hasil: array("tgl" => "Y-m-d", "pekan" => 1-7) atau null bila tanggal mulai tidak valid. */
function info_hari($mulai, $n) {
    $t = DateTime::createFromFormat("!Y-m-d", (string) $mulai);
    if (!$t) { return null; }
    $n = max(1, (int) $n);
    if ($n > 1) { $t->modify("+" . ($n - 1) . " day"); }
    return array("tgl" => $t->format("Y-m-d"), "pekan" => (int) $t->format("N"));
}

/* "2026-11-30" -> "30 Nov 2026". */
function tanggal_ringkas($tanggal) {
    $t = DateTime::createFromFormat("!Y-m-d", (string) $tanggal);
    if (!$t) { return ""; }
    $bulan = array(1 => "Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des");
    return $t->format("j") . " " . $bulan[(int) $t->format("n")] . " " . $t->format("Y");
}

/* Label siap tampil, mis. "Senin, 30 Nov 2026"; kosong bila hari tidak diketahui. */
function label_hari_tanggal($info) {
    if (!$info) { return ""; }
    return label_hari_pekan($info["pekan"]) . ", " . tanggal_ringkas($info["tgl"]);
}

/* "08:00" / "08.00" -> menit sejak 00.00; null bila tidak valid. */
function jam_ke_menit($teks) {
    if (!preg_match('/^(\d{1,2})[.:](\d{2})$/', trim((string) $teks), $m)) { return null; }
    $j = (int) $m[1];
    $i = (int) $m[2];
    if ($j > 23 || $i > 59) { return null; }
    return $j * 60 + $i;
}

/* Nilai untuk <input type="time">. */
function jam_input($menit) {
    $menit = max(0, min(1439, (int) $menit));
    return sprintf("%02d:%02d", intdiv($menit, 60), $menit % 60);
}

/* Tampilan jam gaya data destinasi, mis. "08.00"; lewat tengah malam diberi tanda "(besok)". */
function menit_ke_jam($menit) {
    $menit = (int) $menit;
    $lebih = intdiv($menit, 1440);
    $sisa = (($menit % 1440) + 1440) % 1440;
    $teks = sprintf("%02d.%02d", intdiv($sisa, 60), $sisa % 60);
    return $lebih > 0 ? $teks . " (besok)" : $teks;
}

/* Peringatan jam buka untuk satu kunjungan (kosong bila aman atau data jam belum ada). */
function catatan_jam_buka($teks_jam, $tiba, $pulang) {
    $rentang = rentang_jam_buka($teks_jam);
    if (count($rentang) === 0) { return array(); }

    foreach ($rentang as $r) {
        if ($tiba >= $r["mulai"] && $tiba < $r["selesai"]) {
            if ($r["selesai"] < 1440 && $pulang > $r["selesai"]) {
                return array(array("peringatan", "Perkiraan selesai pukul " . menit_ke_jam($pulang)
                    . ", melewati jam tutup " . menit_ke_jam($r["selesai"]) . "."));
            }
            return array();
        }
    }

    $pertama = $rentang[0];
    if ($tiba < $pertama["mulai"]) {
        return array(array("peringatan", "Tiba pukul " . menit_ke_jam($tiba)
            . ", destinasi baru buka pukul " . menit_ke_jam($pertama["mulai"]) . "."));
    }

    /* Tiba di sela dua rentang (mis. jam istirahat): sebutkan semua rentangnya. */
    $daftar = array();
    foreach ($rentang as $r) {
        $daftar[] = menit_ke_jam($r["mulai"]) . "\u{2013}" . menit_ke_jam($r["selesai"]);
    }
    return array(array("peringatan", "Tiba pukul " . menit_ke_jam($tiba)
        . ", di luar jam buka. Jam buka: " . implode(", ", $daftar) . "."));
}

/* Susun jadwal satu hari dari daftar item berurutan.
   $hari = array("tgl" => ..., "pekan" => ...) dari info_hari(), atau null bila tanggal belum diisi.
   Perkiraan perjalanan memakai kecepatan rata-rata 30 km/jam, dibulatkan ke 5 menit. */
function susun_jadwal_hari($items, $mulai, $hari = null) {
    $pekan = $hari ? (int) $hari["pekan"] : 0;
    $jadwal = array(
        "mulai" => $mulai, "selesai" => $mulai, "km" => 0.0,
        "pekan" => $pekan, "tanggal" => $hari ? $hari["tgl"] : null,
        "menit_jalan" => 0, "menit_kunjungan" => 0, "item" => array(),
    );
    $waktu = $mulai;
    $sebelum = null;

    foreach ($items as $i => $it) {
        $km = 0.0;
        if ($sebelum !== null
            && $it["latitude"] !== null && $it["longitude"] !== null
            && $sebelum["latitude"] !== null && $sebelum["longitude"] !== null) {
            $km = jarak_km($sebelum["latitude"], $sebelum["longitude"], $it["latitude"], $it["longitude"]);
        }
        $menit_jalan = $km > 0 ? max(5, (int) (round(($km / 30) * 60 / 5) * 5)) : 0;
        $waktu += $menit_jalan;

        list($dmin, $dmax) = durasi_kunjungan_menit($it["kategori"] ?? "");
        $tiba = $waktu;
        $pulang = $tiba + $dmax;

        /* Hari tutup lebih menentukan daripada jam: bila jatuh di hari tutup, jam tidak dicek lagi. */
        $tutup = hari_tutup_slug($it["hari_tutup"] ?? "");
        if ($pekan > 0 && in_array(slug_hari_pekan($pekan), $tutup, true)) {
            $catatan = array(array("peringatan", "Tutup setiap " . label_hari_tutup($it["hari_tutup"] ?? "")
                . " \u{2014} ganti tanggal mulai atau pilih destinasi lain."));
        } else {
            $catatan = catatan_jam_buka($it["jam_buka"] ?? "", $tiba, $pulang);
        }

        $jadwal["item"][$i] = array(
            "tiba" => $tiba,
            "pulang" => $pulang,
            "km" => $km,
            "menit_jalan" => $menit_jalan,
            "catatan" => $catatan,
        );
        $jadwal["km"] += $km;
        $jadwal["menit_jalan"] += $menit_jalan;
        $jadwal["menit_kunjungan"] += $dmin;
        $waktu = $tiba + $dmin;
        $sebelum = $it;
    }

    if (count($jadwal["item"]) > 0) {
        $akhir = end($jadwal["item"]);
        $jadwal["selesai"] = $akhir["pulang"];
    }
    return $jadwal;
}

/* ---------- Format harga dan data rencana bersama ---------- */
if (!function_exists("rencana_rp")) {
    function rencana_rp($n) { return "Rp " . number_format((int) $n, 0, ",", "."); }
}
if (!function_exists("rencana_harga")) {
    function rencana_harga($min, $maks) {
        if ($maks <= 0) { return "Gratis"; }
        return $min === $maks ? rencana_rp($min) : rencana_rp($min) . " - " . rencana_rp($maks);
    }
}

/* Satu sumber data untuk halaman pemilik, tautan publik, dan ekspor .ics.
   Mengembalikan item per hari, subtotal per orang, jadwal jam, jarak, dan tanggal mulai. */
function muat_rencana_pengguna($conn, $uid) {
    $st = mysqli_prepare($conn, "SELECT w.id, w.hari_ke, d.id AS did, d.nama, d.lokasi, d.jam_buka, d.hari_tutup, d.harga_tiket, d.harga_maks, d.foto, d.latitude, d.longitude, c.nama AS kategori
        FROM wishlists w
        JOIN destinations d ON d.id = w.destination_id
        JOIN categories c ON c.id = d.category_id
        WHERE w.user_id = ? AND d.status = 'aktif'
        ORDER BY w.hari_ke, w.urutan, w.id");
    mysqli_stmt_bind_param($st, "i", $uid);
    mysqli_stmt_execute($st);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC);
    mysqli_stmt_close($st);

    $hari_list = array();
    $total_min = 0;
    $total_maks = 0;
    foreach ($rows as $r) {
        $min = (int) $r["harga_tiket"];
        $mx  = ($r["harga_maks"] !== null) ? max($min, (int) $r["harga_maks"]) : $min;
        $h   = max(1, (int) $r["hari_ke"]);
        if (!isset($hari_list[$h])) { $hari_list[$h] = array("item" => array(), "min" => 0, "maks" => 0); }
        $r["_min"] = $min;
        $r["_maks"] = $mx;
        $hari_list[$h]["item"][] = $r;
        $hari_list[$h]["min"]  += $min;
        $hari_list[$h]["maks"] += $mx;
        $total_min  += $min;
        $total_maks += $mx;
    }
    ksort($hari_list);

    $jam_mulai_hari = ambil_jam_mulai_hari($conn, $uid);
    $tanggal_mulai = ambil_tanggal_mulai($conn, $uid);
    $jadwal_hari = array();
    $jarak_total = 0.0;
    foreach ($hari_list as $h => $blok) {
        $mulai = jam_ke_menit($jam_mulai_hari[$h] ?? "08:00");
        if ($mulai === null) { $mulai = 8 * 60; }
        $info = $tanggal_mulai !== "" ? info_hari($tanggal_mulai, $h) : null;
        $jadwal_hari[$h] = susun_jadwal_hari($blok["item"], $mulai, $info);
        $jarak_total += $jadwal_hari[$h]["km"];
    }

    $revisi = info_revisi_rencana($conn, $uid);
    return array(
        "hari" => $hari_list,
        "jadwal" => $jadwal_hari,
        "min" => $total_min,
        "maks" => $total_maks,
        "km" => round($jarak_total, 1),
        "tanggal_mulai" => $tanggal_mulai,
        "jumlah_item" => count($rows),
        "revisi" => $revisi["revisi"],
        "diubah" => $revisi["diubah"],
    );
}

/* ---------- Optimasi urutan rute satu hari ---------- */

/* Jarak total (km) bila item dikunjungi berurutan seperti sekarang. */
function total_jarak_urut($items) {
    $total = 0.0;
    $sebelum = null;
    foreach ($items as $it) {
        if ($sebelum !== null) {
            $total += jarak_km($sebelum["latitude"], $sebelum["longitude"], $it["latitude"], $it["longitude"]);
        }
        $sebelum = $it;
    }
    return $total;
}

/* Biaya satu urutan indeks pada matriks jarak. */
function biaya_urutan($rute, $jarak) {
    $total = 0.0;
    $n = count($rute);
    for ($i = 0; $i < $n - 1; $i++) { $total += $jarak[$rute[$i]][$rute[$i + 1]]; }
    return $total;
}

/* Perbaikan 2-opt untuk jalur terbuka: balik potongan bila jaraknya memendek. */
function perbaiki_urutan_2opt($rute, $jarak) {
    $n = count($rute);
    for ($ulang = 0; $ulang < 40; $ulang++) {
        $ada_perbaikan = false;
        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $kiri = $i - 1;
                $kanan = $j + 1;
                $lama = 0.0;
                $baru = 0.0;
                if ($kiri >= 0) {
                    $lama += $jarak[$rute[$kiri]][$rute[$i]];
                    $baru += $jarak[$rute[$kiri]][$rute[$j]];
                }
                if ($kanan < $n) {
                    $lama += $jarak[$rute[$j]][$rute[$kanan]];
                    $baru += $jarak[$rute[$i]][$rute[$kanan]];
                }
                if ($baru + 1e-9 < $lama) {
                    $potong = array_reverse(array_slice($rute, $i, $j - $i + 1));
                    array_splice($rute, $i, $j - $i + 1, $potong);
                    $ada_perbaikan = true;
                }
            }
        }
        if (!$ada_perbaikan) { break; }
    }
    return $rute;
}

/* Urutkan item satu hari agar jarak tempuhnya paling pendek.
   Titik awal dicoba satu per satu (nearest neighbor), lalu diperbaiki 2-opt.
   Item tanpa koordinat ditaruh di akhir dengan urutan aslinya. */
function urutkan_rute_hari($items) {
    $items = array_values($items);
    if (count($items) < 3) { return $items; }

    $berkoordinat = array();
    $lain = array();
    foreach ($items as $it) {
        if ($it["latitude"] !== null && $it["longitude"] !== null
            && (string) $it["latitude"] !== "" && (string) $it["longitude"] !== "") {
            $berkoordinat[] = $it;
        } else {
            $lain[] = $it;
        }
    }
    $m = count($berkoordinat);
    if ($m < 3) { return $items; }

    $jarak = array();
    for ($i = 0; $i < $m; $i++) {
        $jarak[$i] = array();
        for ($j = 0; $j < $m; $j++) {
            $jarak[$i][$j] = ($i === $j) ? 0.0 : jarak_km(
                $berkoordinat[$i]["latitude"], $berkoordinat[$i]["longitude"],
                $berkoordinat[$j]["latitude"], $berkoordinat[$j]["longitude"]
            );
        }
    }

    $terbaik = null;
    $biaya_terbaik = INF;
    for ($awal = 0; $awal < $m; $awal++) {
        $belum = array_values(array_diff(range(0, $m - 1), array($awal)));
        $rute = array($awal);
        $posisi = $awal;
        while (count($belum) > 0) {
            $pilih = 0;
            $terdekat = INF;
            foreach ($belum as $k => $idx) {
                if ($jarak[$posisi][$idx] < $terdekat) {
                    $terdekat = $jarak[$posisi][$idx];
                    $pilih = $k;
                }
            }
            $posisi = $belum[$pilih];
            $rute[] = $posisi;
            unset($belum[$pilih]);
            $belum = array_values($belum);
        }
        $rute = perbaiki_urutan_2opt($rute, $jarak);
        $biaya = biaya_urutan($rute, $jarak);
        if ($biaya < $biaya_terbaik) {
            $biaya_terbaik = $biaya;
            $terbaik = $rute;
        }
    }

    $hasil = array();
    foreach ($terbaik as $idx) { $hasil[] = $berkoordinat[$idx]; }
    foreach ($lain as $it) { $hasil[] = $it; }
    return $hasil;
}

/* ---------- Riwayat singkat rencana (batalkan aksi terakhir) ---------- */

function pastikan_rencana_snapshot($conn) {
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rencana_snapshot (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        aksi VARCHAR(40) NOT NULL DEFAULT '',
        dibuat DATETIME NOT NULL,
        isi LONGTEXT NOT NULL,
        KEY kunci_snapshot_pengguna (user_id, id),
        CONSTRAINT fk_snapshot_pengguna FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* Salin keadaan rencana SEBELUM diubah. Maksimal 5 riwayat per pengguna. */
function simpan_snapshot_rencana($conn, $uid, $aksi) {
    pastikan_rencana_snapshot($conn);
    $isi = array("item" => array(), "jam" => array(), "tanggal" => "");

    $st = @mysqli_prepare($conn, "SELECT destination_id, hari_ke, urutan FROM wishlists WHERE user_id = ? ORDER BY hari_ke, urutan, id");
    if ($st) {
        mysqli_stmt_bind_param($st, "i", $uid);
        mysqli_stmt_execute($st);
        foreach (mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC) as $b) {
            $isi["item"][] = array("d" => (int) $b["destination_id"], "h" => (int) $b["hari_ke"], "u" => (int) $b["urutan"]);
        }
        mysqli_stmt_close($st);
    }

    $st = @mysqli_prepare($conn, "SELECT hari_ke, TIME_FORMAT(jam_mulai, '%H:%i') AS jam FROM rencana_hari WHERE user_id = ?");
    if ($st) {
        mysqli_stmt_bind_param($st, "i", $uid);
        mysqli_stmt_execute($st);
        foreach (mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC) as $b) {
            $isi["jam"][] = array("h" => (int) $b["hari_ke"], "jam" => (string) $b["jam"]);
        }
        mysqli_stmt_close($st);
    }
    $isi["tanggal"] = ambil_tanggal_mulai($conn, $uid);

    $st = @mysqli_prepare($conn, "INSERT INTO rencana_snapshot (user_id, aksi, dibuat, isi) VALUES (?, ?, NOW(), ?)");
    if (!$st) { return false; }
    $json = json_encode($isi);
    $aksi = substr((string) $aksi, 0, 40);
    mysqli_stmt_bind_param($st, "iss", $uid, $aksi, $json);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);

    /* Hanya 5 riwayat terakhir yang disimpan. */
    @mysqli_query($conn, "DELETE FROM rencana_snapshot WHERE user_id = " . (int) $uid
        . " AND id NOT IN (SELECT id FROM (SELECT id FROM rencana_snapshot WHERE user_id = " . (int) $uid
        . " ORDER BY id DESC LIMIT 5) AS simpan)");
    return (bool) $ok;
}

/* Riwayat terakhir: array("id" =>, "aksi" =>, "dibuat" => "Y-m-d H:i") atau null. */
function snapshot_terakhir($conn, $uid) {
    pastikan_rencana_snapshot($conn);
    $st = @mysqli_prepare($conn, "SELECT id, aksi, DATE_FORMAT(dibuat, '%Y-%m-%d %H:%i') AS dibuat FROM rencana_snapshot WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    if (!$st) { return null; }
    mysqli_stmt_bind_param($st, "i", $uid);
    mysqli_stmt_execute($st);
    $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    return $baris ?: null;
}

function label_aksi_snapshot($aksi) {
    $label = array(
        "hapus"         => "hapus destinasi dari rencana",
        "pindah_hari"   => "pindah destinasi ke hari lain",
        "urutkan"       => "ubah urutan destinasi",
        "rapikan"       => "rapikan urutan rute",
        "jam_hari"      => "ubah jam mulai hari",
        "tanggal_mulai" => "ubah tanggal mulai",
        "tambah_rencana" => "tambah destinasi ke rencana",
    );
    return $label[(string) $aksi] ?? "ubah rencana";
}

/* Kembalikan isi rencana dari riwayat terakhir (sekali pakai). */
function pulihkan_snapshot_rencana($conn, $uid) {
    pastikan_rencana_hari($conn);
    pastikan_rencana_snapshot($conn);

    $st = @mysqli_prepare($conn, "SELECT id, aksi, isi FROM rencana_snapshot WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    if (!$st) { return array(false, "Riwayat rencana tidak bisa dibaca."); }
    mysqli_stmt_bind_param($st, "i", $uid);
    mysqli_stmt_execute($st);
    $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$baris) { return array(false, "Belum ada aksi yang bisa dibatalkan."); }

    $isi = json_decode((string) $baris["isi"], true);
    if (!is_array($isi) || !isset($isi["item"])) { return array(false, "Riwayat rencana tidak valid."); }

    /* Destinasi yang sudah dihapus admin tidak ikut dipulihkan. */
    $ada = array();
    $q = @mysqli_query($conn, "SELECT id FROM destinations");
    while ($q && ($d = mysqli_fetch_assoc($q))) { $ada[(int) $d["id"]] = true; }

    mysqli_begin_transaction($conn);
    try {
        $del = mysqli_prepare($conn, "DELETE FROM wishlists WHERE user_id = ?");
        mysqli_stmt_bind_param($del, "i", $uid);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);

        $ins = mysqli_prepare($conn, "INSERT INTO wishlists (user_id, destination_id, hari_ke, urutan) VALUES (?, ?, ?, ?)");
        foreach ($isi["item"] as $it) {
            $did = (int) ($it["d"] ?? 0);
            if (!isset($ada[$did])) { continue; }
            $hari = max(1, min(7, (int) ($it["h"] ?? 1)));
            $urut = max(1, (int) ($it["u"] ?? 1));
            mysqli_stmt_bind_param($ins, "iiii", $uid, $did, $hari, $urut);
            mysqli_stmt_execute($ins);
        }
        mysqli_stmt_close($ins);

        $del = mysqli_prepare($conn, "DELETE FROM rencana_hari WHERE user_id = ?");
        mysqli_stmt_bind_param($del, "i", $uid);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);

        $ins = mysqli_prepare($conn, "INSERT INTO rencana_hari (user_id, hari_ke, jam_mulai) VALUES (?, ?, ?)");
        foreach ((array) ($isi["jam"] ?? array()) as $j) {
            $jam = (string) ($j["jam"] ?? "08:00");
            if (jam_ke_menit($jam) === null) { continue; }
            $hari = max(1, min(7, (int) ($j["h"] ?? 1)));
            $jam = jam_input(jam_ke_menit($jam)) . ":00";
            mysqli_stmt_bind_param($ins, "iis", $uid, $hari, $jam);
            mysqli_stmt_execute($ins);
        }
        mysqli_stmt_close($ins);

        $tanggal = (string) ($isi["tanggal"] ?? "");
        $tgl_db = tanggal_valid($tanggal, true) ? $tanggal : null;
        $st = mysqli_prepare($conn, "INSERT INTO rencana_meta (user_id, tanggal_mulai) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE tanggal_mulai = VALUES(tanggal_mulai)");
        mysqli_stmt_bind_param($st, "is", $uid, $tgl_db);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        $hapus = mysqli_prepare($conn, "DELETE FROM rencana_snapshot WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($hapus, "ii", $baris["id"], $uid);
        mysqli_stmt_execute($hapus);
        mysqli_stmt_close($hapus);

        mysqli_commit($conn);
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        error_log("Dolan Jogja (pulihkan_snapshot_rencana): " . $ex->getMessage());
        return array(false, "Rencana gagal dipulihkan; tidak ada data yang berubah.");
    }

    tandai_rencana_berubah($conn, $uid);
    return array(true, label_aksi_snapshot($baris["aksi"]));
}
