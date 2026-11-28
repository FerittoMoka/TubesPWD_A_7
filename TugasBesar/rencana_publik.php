<?php
// Halaman publik hanya-baca untuk Rencana Dolan yang dibagikan lewat token.
require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/rencana_jadwal.php";
require_once __DIR__ . "/includes/rencana_bagi.php";

pastikan_rencana_hari($conn);
pastikan_rencana_bagi($conn);

$token = trim((string) ($_GET["token"] ?? ""));
$pemilik = cari_pemilik_token($conn, $token);

if ($pemilik === 0) {
    http_response_code(404);
    $judul = "Tautan tidak ditemukan";
    require_once __DIR__ . "/includes/header.php";
    ?>
    <div class="wadah">
        <div class="halaman-isi">
            <div class="panel">
                <h1>Tautan tidak ditemukan</h1>
                <p>Tautan berbagi ini tidak valid atau sudah dimatikan pemiliknya.</p>
                <p><a class="tombol" href="<?php echo BASE_URL; ?>index.php">Ke Beranda</a></p>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . "/includes/footer.php";
    exit;
}

$data = muat_rencana_pengguna($conn, $pemilik);
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
                </div>
                <p class="kecil">Mode hanya baca &mdash; tidak perlu akun dan rencana tidak bisa diubah dari halaman ini.</p>
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
                <p>Total estimasi tiket: <strong><?php echo e(rencana_harga($data["min"], $data["maks"])); ?></strong> <span class="kecil">(1 orang)</span></p>
                <p>Estimasi jarak rute: <strong><?php echo e(number_format($data["km"], 1, ",", ".")); ?> km</strong> <span class="kecil">(belum termasuk perjalanan pulang)</span></p>
                <p class="kecil">Perkiraan dari data harga tiket. Jam buka dan harga bisa berubah; konfirmasi ulang sebelum berangkat.</p>
            </div>
        <?php } ?>
    </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
