<?php
// Jadwal jam pada Rencana Dolan: jam mulai dan hari per hari, waktu tiba/keluar tiap destinasi,
// estimasi perjalanan antartitik, serta peringatan jam buka dan hari tutup.
require_once __DIR__ . "/jam_buka.php";
require_once __DIR__ . "/fungsi.php";

/* Tabel pengaturan per hari. Dibuat otomatis seperti tabel fitur lain (galeri, foto ulasan).
   Tabel lama yang belum punya kolom hari_pekan ikut ditambahi saat halaman dibuka. */
function pastikan_rencana_hari($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS rencana_hari (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        hari_ke TINYINT NOT NULL,
        jam_mulai TIME NOT NULL DEFAULT '08:00:00',
        hari_pekan TINYINT DEFAULT NULL,
        UNIQUE KEY unik_hari_rencana (user_id, hari_ke),
        CONSTRAINT fk_hari_pengguna FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $ok = (bool) @mysqli_query($conn, $sql);
    $kolom = @mysqli_query($conn, "SHOW COLUMNS FROM rencana_hari LIKE 'hari_pekan'");
    if ($kolom && mysqli_num_rows($kolom) === 0) {
        @mysqli_query($conn, "ALTER TABLE rencana_hari ADD COLUMN hari_pekan TINYINT DEFAULT NULL AFTER jam_mulai");
    }
    return $ok;
}

/* Pengaturan satu pengguna: hari_ke => array("jam" => "HH:MM", "pekan" => 1-7 atau 0). */
function ambil_pengaturan_hari($conn, $uid) {
    $hasil = array();
    $st = @mysqli_prepare($conn, "SELECT hari_ke, TIME_FORMAT(jam_mulai, '%H:%i') AS jam, hari_pekan FROM rencana_hari WHERE user_id = ?");
    if (!$st) { return $hasil; }
    mysqli_stmt_bind_param($st, "i", $uid);
    mysqli_stmt_execute($st);
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC) as $b) {
        $hasil[(int) $b["hari_ke"]] = array(
            "jam" => $b["jam"],
            "pekan" => (int) ($b["hari_pekan"] ?? 0),
        );
    }
    mysqli_stmt_close($st);
    return $hasil;
}

/* $pekan = 1 (Senin) sampai 7 (Minggu), 0/null bila hari tidak ditentukan. */
function simpan_jam_mulai_hari($conn, $uid, $hari, $menit, $pekan = null) {
    $jam = jam_input($menit) . ":00";
    $pekan = ($pekan === null || (int) $pekan < 1 || (int) $pekan > 7) ? null : (int) $pekan;
    $st = @mysqli_prepare($conn, "INSERT INTO rencana_hari (user_id, hari_ke, jam_mulai, hari_pekan) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE jam_mulai = VALUES(jam_mulai), hari_pekan = VALUES(hari_pekan)");
    if (!$st) { return false; }
    mysqli_stmt_bind_param($st, "iisi", $uid, $hari, $jam, $pekan);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return (bool) $ok;
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
   $pekan = hari kunjungan (1 Senin .. 7 Minggu, 0 = belum ditentukan) untuk cek hari tutup.
   Perkiraan perjalanan memakai kecepatan rata-rata 30 km/jam, dibulatkan ke 5 menit. */
function susun_jadwal_hari($items, $mulai, $pekan = 0) {
    $jadwal = array(
        "mulai" => $mulai, "selesai" => $mulai, "km" => 0.0, "pekan" => (int) $pekan,
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
        if ((int) $pekan > 0 && in_array(slug_hari_pekan($pekan), $tutup, true)) {
            $catatan = array(array("peringatan", "Tutup setiap " . label_hari_tutup($it["hari_tutup"] ?? "")
                . " \u{2014} ganti hari kunjungan atau pilih destinasi lain."));
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
