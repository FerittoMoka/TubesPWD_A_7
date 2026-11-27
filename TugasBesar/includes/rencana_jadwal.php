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

    /* Satu tanggal mulai per pengguna; hari ke-N dihitung dari sini. */
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rencana_meta (
        user_id INT NOT NULL PRIMARY KEY,
        tanggal_mulai DATE DEFAULT NULL,
        CONSTRAINT fk_meta_pengguna FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
    return (bool) $ok;
}

function hapus_tanggal_mulai($conn, $uid) {
    $st = @mysqli_prepare($conn, "DELETE FROM rencana_meta WHERE user_id = ?");
    if (!$st) { return false; }
    mysqli_stmt_bind_param($st, "i", $uid);
    $ok = mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
    return (bool) $ok;
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
