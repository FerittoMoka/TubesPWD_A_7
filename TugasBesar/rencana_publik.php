<?php
// Halaman publik hanya-baca untuk Rencana Dolan yang dibagikan lewat token.
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/rencana_jadwal.php";
require_once __DIR__ . "/includes/rencana_bagi.php";

// Halaman ini berisi tautan berbagi: jangan diindeks mesin pencari, dan token
// di URL tidak boleh ikut terkirim sebagai Referer saat pengunjung pindah halaman.
header("X-Robots-Tag: noindex, nofollow");
header("Referrer-Policy: no-referrer");

pastikan_rencana_hari($conn);
pastikan_rencana_bagi($conn);

$token = trim((string) ($_GET["token"] ?? ""));
$baris = ambil_baris_bagi($conn, $token);

if ($baris === null) {
    http_response_code(404);
    $judul = "Tautan tidak ditemukan";
    require_once __DIR__ . "/includes/header.php";
    ?>
    <div class="wadah">
        <div class="halaman-isi">
            <div class="panel">
                <h1>Tautan tidak ditemukan</h1>
                <p>Tautan berbagi ini tidak valid, sudah dimatikan, atau sudah kedaluwarsa.</p>
                <p><a class="tombol" href="<?php echo BASE_URL; ?>index.php">Ke Beranda</a></p>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . "/includes/footer.php";
    exit;
}

/* ---------- Salin rencana ke akun pengunjung ---------- */
$pesan_salin = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "salin") {
    if (!sudah_login()) {
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $pesan_salin = "Sesi sudah berakhir. Muat ulang halaman lalu coba lagi.";
    } elseif (!bagi_sudah_terbuka($baris)) {
        $pesan_salin = "Masukkan kode akses dulu sebelum menyalin rencana ini.";
    } else {
        list($ok_salin, $info_salin) = salin_rencana_bagi($conn, $token, (int) $_SESSION["user_id"]);
        if ($ok_salin) {
            $_SESSION["rencana_pesan"] = array("sukses", "Rencana disalin ke akun Anda (" . (int) $info_salin
                . " destinasi). Rencana sebelumnya bisa dikembalikan lewat tombol Batalkan aksi.");
            header("Location: " . BASE_URL . "user/rencana.php");
            exit;
        }
        $pesan_salin = $info_salin;
    }
}

/* ---------- Kode akses ---------- */
$pesan_kode = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") !== "salin" && bagi_terkunci($baris)) {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $pesan_kode = "Sesi sudah berakhir. Muat ulang halaman lalu coba lagi.";
    } elseif (bagi_terlalu_banyak_coba($token)) {
        http_response_code(429);
        $pesan_kode = "Terlalu banyak percobaan. Tunggu satu menit lalu coba lagi.";
    } elseif (password_verify((string) ($_POST["kode"] ?? ""), (string) $baris["kode_hash"])) {
        buka_bagi($baris);
        reset_coba_kode($token);
        header("Location: " . url_rencana_publik($token));
        exit;
    } else {
        catat_coba_kode($token);
        $pesan_kode = "Kode akses salah. Periksa lagi angka dari pemilik rencana.";
    }
}

if (!bagi_sudah_terbuka($baris)) {
    $judul = "Rencana Dolan (dibagikan)";
    require_once __DIR__ . "/includes/header.php";
    ?>
    <div class="wadah">
        <div class="halaman-isi">
            <div class="bagi-kepala">
                <p class="eyebrow">Rencana dibagikan</p>
                <h1>Kode akses diperlukan</h1>
                <p class="kecil">Pemilik rencana memasang kode akses untuk tautan ini.</p>
            </div>
            <div class="panel">
                <?php if ($pesan_kode !== "") { ?><p class="error"><?php echo e($pesan_kode); ?></p><?php } ?>
                <form method="post" action="<?php echo e(url_rencana_publik($token)); ?>" class="bagi-kode bagi-kode-lebar">
                    <?php echo csrf_field(); ?>
                    <label for="kode-akses">Kode akses</label>
                    <input type="text" id="kode-akses" name="kode" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off" required="required" />
                    <button type="submit" class="tombol">Buka rencana</button>
                </form>
                <p class="kecil">Minta kode 4&ndash;6 angka kepada pemilik rencana, lalu masukkan di sini. Halaman ini tidak bisa diubah dari sisi pengunjung.</p>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . "/includes/footer.php";
    exit;
}

/* Kunjungan dihitung sekali per sesi supaya angka di panel pemilik tidak menggelembung. */
if (empty($_SESSION["bagi_dihitung"][$token])) {
    catat_akses_bagi($conn, $token);
    $_SESSION["bagi_dihitung"][$token] = true;
}

$data = muat_rencana_pengguna($conn, (int) $baris["user_id"]);
$tampil_biaya = (int) $baris["tanpa_biaya"] === 0;

$teks_wa = "Rencana Dolan Jogja";
if (count($data["hari"]) > 0) {
    $teks_wa .= " (" . count($data["hari"]) . " hari, " . (int) $data["jumlah_item"] . " destinasi)";
    if ($data["tanggal_mulai"] !== "") { $teks_wa .= " mulai " . tanggal_ringkas($data["tanggal_mulai"]); }
}
$teks_wa .= ": " . url_rencana_publik($token);
if (bagi_terkunci($baris)) { $teks_wa .= " (kode akses diminta saat dibuka)"; }
$tautan_wa = "https://wa.me/?text=" . rawurlencode($teks_wa);
$tautan_hari_ini = url_absolut("rencana_hari_ini.php") . "?token=" . rawurlencode((string) $token);
$boleh_salin = sudah_login() && (int) $_SESSION["user_id"] !== (int) $baris["user_id"];

$judul = "Rencana Dolan (dibagikan)";
require_once __DIR__ . "/includes/header.php";
?>
<div class="wadah">
    <div class="halaman-isi">
        <div class="bagi-kepala">
            <p class="eyebrow">Rencana dibagikan</p>
            <h1>Rencana Dolan</h1>
            <?php if (count($data["hari"]) > 0) { ?>
                <p class="kecil">
                    <?php echo count($data["hari"]); ?> hari &middot; <?php echo (int) $data["jumlah_item"]; ?> destinasi
                    <?php if ($data["tanggal_mulai"] !== "") { ?> &middot; mulai <?php echo e(tanggal_ringkas($data["tanggal_mulai"])); ?><?php } ?>
                </p>
                <div class="rencana-aksi tidak-dicetak">
                    <button type="button" class="tombol" onclick="window.print();">Cetak</button>
                    <?php if ($data["tanggal_mulai"] !== "") { ?>
                        <a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>rencana_ics.php?token=<?php echo rawurlencode($token); ?>">Simpan ke Kalender (.ics)</a>
                    <?php } else { ?>
                        <span class="kecil">Pemilik belum mengisi tanggal mulai, jadi ekspor kalender belum tersedia.</span>
                    <?php } ?>
                    <a class="tombol tombol-garis" href="<?php echo e($tautan_wa); ?>" target="_blank" rel="noopener">Bagikan ke WhatsApp</a>
                    <a class="tombol tombol-garis" href="<?php echo e($tautan_hari_ini); ?>">Mode Perjalanan</a>
                    <?php if ($boleh_salin) { ?>
                        <form method="post" action="<?php echo e(url_rencana_publik($token)); ?>" class="inline" onsubmit="return confirm('Rencana di akun Anda akan diganti dengan salinan rencana ini. Lanjutkan?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="aksi" value="salin" />
                            <button type="submit" class="tombol tombol-garis">Salin ke Rencana Saya</button>
                        </form>
                    <?php } elseif (!sudah_login()) { ?>
                        <a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>auth/login.php">Masuk untuk menyalin</a>
                    <?php } ?>
                </div>
                <?php if ($pesan_salin !== "") { ?><p class="error"><?php echo e($pesan_salin); ?></p><?php } ?>
                <p class="kecil">Mode hanya baca &mdash; tidak perlu akun dan rencana tidak bisa diubah dari halaman ini. Anggota yang masuk bisa menyalinnya ke rencana sendiri.</p>
            <?php } ?>
        </div>

        <?php if (count($data["hari"]) === 0) { ?>
            <div class="panel">
                <h2>Rencana masih kosong</h2>
                <p>Pemilik belum menambahkan destinasi ke rencana ini. Coba buka tautannya lagi nanti.</p>
                <p><a class="tombol" href="<?php echo BASE_URL; ?>index.php">Jelajahi Destinasi</a></p>
            </div>
        <?php } else { ?>
            <?php foreach ($data["hari"] as $h => $blok) {
                $jad = $data["jadwal"][$h];
                $bisa_ubah = false;
                require __DIR__ . "/includes/rencana_hari.php";
            } ?>

            <div class="panel">
                <h3>Ringkasan</h3>
                <?php if ($tampil_biaya) { ?>
                    <p>Total estimasi tiket: <strong><?php echo e(rencana_harga($data["min"], $data["maks"])); ?></strong> <span class="kecil">(1 orang)</span></p>
                <?php } ?>
                <p>Estimasi jarak rute: <strong><?php echo e(number_format($data["km"], 1, ",", ".")); ?> km</strong> <span class="kecil">(belum termasuk perjalanan pulang)</span></p>
                <?php if ($tampil_biaya) { ?>
                    <p class="kecil">Perkiraan dari data harga tiket. Jam buka dan harga bisa berubah; konfirmasi ulang sebelum berangkat.</p>
                <?php } else { ?>
                    <p class="kecil">Pemilik rencana tidak menampilkan perkiraan biaya. Jam buka dan harga bisa berubah; konfirmasi ulang sebelum berangkat.</p>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
