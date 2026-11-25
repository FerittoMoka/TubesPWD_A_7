<?php
// Menentukan status "Buka sekarang" / "Tutup" dari teks jam buka (zona waktu WIB).
// Format yang dikenali: "06.00 - 17.00", "08.00 - 23.00", "24 jam", dan beberapa rentang dalam satu teks.
function status_buka($jam) {
    if ($jam === null || trim((string) $jam) === '') { return null; }
    if (stripos($jam, '24 jam') !== false) { return 'buka'; }

    if (!preg_match_all('/(\d{1,2})[.:](\d{2})\s*(?:-|\x{2013}|s\/d|sampai)\s*(\d{1,2})[.:](\d{2})/u', $jam, $m, PREG_SET_ORDER)) {
        return null; // teks tidak bisa dibaca, jangan tampilkan badge
    }
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $menit = (int) $now->format('G') * 60 + (int) $now->format('i');

    foreach ($m as $r) {
        $a = (int) $r[1] * 60 + (int) $r[2];
        $b = (int) $r[3] * 60 + (int) $r[4];
        if ($b <= $a) { // melewati tengah malam
            if ($menit >= $a || $menit < $b) { return 'buka'; }
        } elseif ($menit >= $a && $menit < $b) {
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