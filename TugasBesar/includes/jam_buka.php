<?php
// Membaca teks jam buka (zona waktu WIB) menjadi rentang menit, lalu menentukan status buka.
// Format yang dikenali: "06.00 - 17.00", "08.00 - 23.00", "24 jam", dan beberapa rentang dalam satu teks.

/* Rentang jam buka dalam menit sejak 00.00: array(array("mulai" => 360, "selesai" => 1020), ...).
   Rentang yang melewati tengah malam memakai "selesai" > 1440. Kosong bila teks tidak terbaca. */
function rentang_jam_buka($jam) {
    if ($jam === null || trim((string) $jam) === '') { return array(); }
    if (stripos($jam, '24 jam') !== false) {
        return array(array("mulai" => 0, "selesai" => 1440));
    }
    if (!preg_match_all('/(\d{1,2})[.:](\d{2})\s*(?:-|\x{2013}|s\/d|sampai)\s*(\d{1,2})[.:](\d{2})/u', $jam, $m, PREG_SET_ORDER)) {
        return array(); // teks tidak bisa dibaca
    }
    $hasil = array();
    foreach ($m as $r) {
        $a = (int) $r[1] * 60 + (int) $r[2];
        $b = (int) $r[3] * 60 + (int) $r[4];
        if ($a > 1440 || $b > 1440) { continue; }
        if ($b <= $a) { $b += 1440; } // melewati tengah malam
        $hasil[] = array("mulai" => $a, "selesai" => $b);
    }
    usort($hasil, function ($x, $y) { return $x["mulai"] <=> $y["mulai"]; });
    return $hasil;
}

function status_buka($jam) {
    $rentang = rentang_jam_buka($jam);
    if (count($rentang) === 0) { return null; }

    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $menit = (int) $now->format('G') * 60 + (int) $now->format('i');

    foreach ($rentang as $r) {
        if ($r["selesai"] > 1440) { // melewati tengah malam
            if ($menit >= $r["mulai"] || $menit < ($r["selesai"] - 1440)) { return 'buka'; }
        } elseif ($menit >= $r["mulai"] && $menit < $r["selesai"]) {
            return 'buka';
        }
    }
    return 'tutup';
}

function badge_buka($jam) {
    $s = status_buka($jam);
    if ($s === null) { return ''; }
    return $s === 'buka'
        ? '<span class="badge-buka buka" title="Berdasarkan waktu WIB">Buka sekarang</span>'
        : '<span class="badge-buka tutup" title="Berdasarkan waktu WIB">Tutup</span>';
}
