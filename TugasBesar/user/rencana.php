<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/../includes/rencana_jadwal.php";
require_once __DIR__ . "/../includes/rencana_bagi.php";
cek_login();

$uid = (int) $_SESSION["user_id"];
pastikan_rencana_hari($conn);
pastikan_rencana_bagi($conn);
$maks_hari = 7;
$orang = max(1, min(50, (int) ($_GET["orang"] ?? $_POST["orang"] ?? 1)));

/* ---------- Proses aksi ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $kembali = BASE_URL . "user/rencana.php?orang=" . $orang;

    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $_SESSION["rencana_pesan"] = array("error", "Token keamanan tidak valid. Silakan coba lagi.");
        header("Location: " . $kembali);
        exit;
    }

    $aksi = $_POST["aksi"] ?? "";

    /* Tautan berbagi hanya-baca: dibuat ulang = token lama langsung tidak berlaku. */
    if ($aksi === "bagikan") {
        $masa = (string) ($_POST["masa"] ?? "rencana");
        if (!isset(masa_berlaku_pilihan()[$masa])) { $masa = "rencana"; }
        $kode = trim((string) ($_POST["kode"] ?? ""));
        if ($kode !== "" && !kode_akses_valid($kode)) {
            $_SESSION["rencana_pesan"] = array("error", "Kode akses harus 4-6 angka.");
        } else {
            $token_baru = buat_token_bagi($conn, $uid, $masa, $kode);
            $_SESSION["rencana_pesan"] = $token_baru !== ""
                ? array("sukses", "Tautan berbagi dibuat. Salin dan kirim ke siapa pun; penerima hanya bisa melihat.")
                : array("error", "Tautan berbagi gagal dibuat. Silakan coba lagi.");
        }
        header("Location: " . $kembali);
        exit;
    }
    if ($aksi === "kode_bagi") {
        $kode = trim((string) ($_POST["kode"] ?? ""));
        if ($kode !== "" && !kode_akses_valid($kode)) {
            $_SESSION["rencana_pesan"] = array("error", "Kode akses harus 4-6 angka.");
        } elseif (simpan_kode_akses_bagi($conn, $uid, $kode)) {
            $_SESSION["rencana_pesan"] = $kode === ""
                ? array("sukses", "Kode akses dihapus; tautan bisa dibuka langsung.")
                : array("sukses", "Kode akses disimpan. Penerima harus memasukkan kode sebelum melihat rencana.");
        } else {
            $_SESSION["rencana_pesan"] = array("error", "Kode akses gagal disimpan. Silakan coba lagi.");
        }
        header("Location: " . $kembali);
        exit;
    }
    if ($aksi === "biaya_bagi") {
        $_SESSION["rencana_pesan"] = simpan_tanpa_biaya_bagi($conn, $uid, !empty($_POST["tanpa"]))
            ? array("sukses", "Pengaturan tampilan biaya disimpan.")
            : array("error", "Pengaturan tampilan biaya gagal disimpan.");
        header("Location: " . $kembali);
        exit;
    }
    if ($aksi === "hentikan_bagi") {
        $_SESSION["rencana_pesan"] = hapus_token_bagi($conn, $uid)
            ? array("sukses", "Tautan berbagi dimatikan.")
            : array("error", "Tautan berbagi gagal dimatikan. Silakan coba lagi.");
        header("Location: " . $kembali);
        exit;
    }

    /* Rapikan urutan tiap hari agar jarak tempuhnya paling pendek. */
    if ($aksi === "rapikan_hari") {
        $data_awal = muat_rencana_pengguna($conn, $uid);
        if (count($data_awal["hari"]) === 0) {
            $_SESSION["rencana_pesan"] = array("error", "Belum ada destinasi yang bisa dirapikan.");
        } else {
            simpan_snapshot_rencana($conn, $uid, "rapikan");
            $km_awal = (float) $data_awal["km"];
            $ada_ubah = false;
            $up = mysqli_prepare($conn, "UPDATE wishlists SET urutan = ? WHERE id = ? AND user_id = ?");
            foreach ($data_awal["hari"] as $h_r => $blok_r) {
                $id_awal = array_map("intval", array_column($blok_r["item"], "id"));
                $baru = urutkan_rute_hari($blok_r["item"]);
                if (array_map("intval", array_column($baru, "id")) !== $id_awal) { $ada_ubah = true; }
                foreach ($baru as $i_r => $it_r) {
                    $nomor_r = $i_r + 1;
                    $id_r = (int) $it_r["id"];
                    mysqli_stmt_bind_param($up, "iii", $nomor_r, $id_r, $uid);
                    mysqli_stmt_execute($up);
                }
            }
            mysqli_stmt_close($up);

            $data_baru = muat_rencana_pengguna($conn, $uid);
            $km_baru = (float) $data_baru["km"];
            if ($ada_ubah) {
                tandai_rencana_berubah($conn, $uid);
                $hemat = $km_awal - $km_baru;
                $pesan_rapi = "Urutan rute dirapikan: " . number_format($km_awal, 1, ",", ".") . " km menjadi "
                    . number_format($km_baru, 1, ",", ".") . " km";
                if ($hemat > 0.05) { $pesan_rapi .= " (hemat " . number_format($hemat, 1, ",", ".") . " km)"; }
                $_SESSION["rencana_pesan"] = array("sukses", $pesan_rapi . ".");
            } else {
                $_SESSION["rencana_pesan"] = array("sukses", "Urutan rute sudah paling efisien ("
                    . number_format($km_baru, 1, ",", ".") . " km).");
            }
        }
        header("Location: " . $kembali);
        exit;
    }

    /* Batalkan aksi terakhir: pulihkan isi rencana dari riwayat. */
    if ($aksi === "batal_aksi") {
        list($ok_batal, $info_batal) = pulihkan_snapshot_rencana($conn, $uid);
        $_SESSION["rencana_pesan"] = $ok_batal
            ? array("sukses", "Rencana dikembalikan ke keadaan sebelum " . $info_batal . ".")
            : array("error", $info_batal);
        header("Location: " . $kembali);
        exit;
    }

    /* Tanggal mulai: hari ke-1..N dipetakan ke hari kalender. */
    if ($aksi === "tanggal_mulai") {
        $tanggal_s = trim((string) ($_POST["tanggal"] ?? ""));
        if (!empty($_POST["kosongkan"]) || $tanggal_s === "") {
            simpan_snapshot_rencana($conn, $uid, "tanggal_mulai");
            hapus_tanggal_mulai($conn, $uid);
            $_SESSION["rencana_pesan"] = array("sukses", "Tanggal mulai dikosongkan. Hari tutup tidak ikut dicek.");
        } elseif (!tanggal_valid($tanggal_s, true)) {
            $_SESSION["rencana_pesan"] = array("error", "Format tanggal tidak valid (contoh 2026-11-30).");
        } elseif (!tanggal_valid($tanggal_s)) {
            $_SESSION["rencana_pesan"] = array("error", "Tanggal mulai sudah lewat. Pilih tanggal hari ini atau setelahnya.");
        } else {
            simpan_snapshot_rencana($conn, $uid, "tanggal_mulai");
            $_SESSION["rencana_pesan"] = simpan_tanggal_mulai($conn, $uid, $tanggal_s)
                ? array("sukses", "Tanggal mulai disimpan: " . tanggal_ringkas($tanggal_s) . ".")
                : array("error", "Tanggal mulai gagal disimpan. Silakan coba lagi.");
        }
        header("Location: " . $kembali);
        exit;
    }

    /* Atur jam mulai satu hari (tidak terikat item tertentu). */
    if ($aksi === "jam_hari") {
        $hari_jam  = (int) ($_POST["hari"] ?? 0);
        $mulai_jam = jam_ke_menit($_POST["jam_mulai"] ?? "");
        if ($hari_jam < 1 || $hari_jam > $maks_hari) {
            $_SESSION["rencana_pesan"] = array("error", "Hari tidak valid.");
        } elseif ($mulai_jam === null) {
            $_SESSION["rencana_pesan"] = array("error", "Format jam mulai tidak valid (contoh 08:00).");
        } else {
            simpan_snapshot_rencana($conn, $uid, "jam_hari");
            $_SESSION["rencana_pesan"] = simpan_jam_mulai_hari($conn, $uid, $hari_jam, $mulai_jam)
                ? array("sukses", "Hari ke-" . $hari_jam . " dimulai pukul " . menit_ke_jam($mulai_jam) . ".")
                : array("error", "Jam mulai gagal disimpan. Silakan coba lagi.");
        }
        header("Location: " . $kembali);
        exit;
    }

    $wid  = (int) ($_POST["id"] ?? 0);

    $st = mysqli_prepare($conn, "SELECT hari_ke FROM wishlists WHERE id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($st, "ii", $wid, $uid);
    mysqli_stmt_execute($st);
    $w = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);

    if (!$w) {
        $_SESSION["rencana_pesan"] = array("error", "Item tidak ditemukan.");
    } elseif ($aksi === "hapus") {
        simpan_snapshot_rencana($conn, $uid, "hapus");
        $st = mysqli_prepare($conn, "DELETE FROM wishlists WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($st, "ii", $wid, $uid);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        tandai_rencana_berubah($conn, $uid);
        $_SESSION["rencana_pesan"] = array("sukses", "Destinasi dihapus dari rencana.");
    } elseif ($aksi === "pindah_hari") {
        $hari = (int) ($_POST["hari"] ?? 0);
        if ($hari < 1 || $hari > $maks_hari) {
            $_SESSION["rencana_pesan"] = array("error", "Hari tidak valid.");
        } else {
            $st = mysqli_prepare($conn, "SELECT COALESCE(MAX(urutan), 0) + 1 AS n FROM wishlists WHERE user_id = ? AND hari_ke = ?");
            mysqli_stmt_bind_param($st, "ii", $uid, $hari);
            mysqli_stmt_execute($st);
            $n = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($st))["n"];
            mysqli_stmt_close($st);

            simpan_snapshot_rencana($conn, $uid, "pindah_hari");
            $st = mysqli_prepare($conn, "UPDATE wishlists SET hari_ke = ?, urutan = ? WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($st, "iiii", $hari, $n, $wid, $uid);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
            tandai_rencana_berubah($conn, $uid);
            $_SESSION["rencana_pesan"] = array("sukses", "Dipindahkan ke Hari ke-" . $hari . ".");
        }
    } elseif ($aksi === "naik" || $aksi === "turun") {
        $hari = (int) $w["hari_ke"];
        $st = mysqli_prepare($conn, "SELECT w.id FROM wishlists w JOIN destinations d ON d.id = w.destination_id WHERE w.user_id = ? AND w.hari_ke = ? AND d.status = 'aktif' ORDER BY w.urutan, w.id");
        mysqli_stmt_bind_param($st, "ii", $uid, $hari);
        mysqli_stmt_execute($st);
        $ids = array_map("intval", array_column(mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC), "id"));
        mysqli_stmt_close($st);

        $pos = array_search($wid, $ids, true);
        $tuj = ($aksi === "naik") ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$tuj])) {
            $tmp = $ids[$pos]; $ids[$pos] = $ids[$tuj]; $ids[$tuj] = $tmp;
        }
        simpan_snapshot_rencana($conn, $uid, "urutkan");
        // Nomori ulang 1..n agar urutan selalu rapi
        $up = mysqli_prepare($conn, "UPDATE wishlists SET urutan = ? WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($up, "iii", $nomor, $idx, $uid);
        foreach ($ids as $i => $idx) {
            $nomor = $i + 1;
            mysqli_stmt_execute($up);
        }
        mysqli_stmt_close($up);
        tandai_rencana_berubah($conn, $uid);
    }
    header("Location: " . $kembali);
    exit;
}

/* ---------- Ambil data ---------- */
$data_rencana  = muat_rencana_pengguna($conn, $uid);
$hari_list     = $data_rencana["hari"];
$jadwal_hari   = $data_rencana["jadwal"];
$tanggal_mulai = $data_rencana["tanggal_mulai"];
$total_min     = $data_rencana["min"];
$total_maks    = $data_rencana["maks"];
$jarak_total   = $data_rencana["km"];
$baris_bagi    = ambil_bagi_pemilik($conn, $uid);
$token_bagi    = ($baris_bagi !== null && !bagi_kedaluwarsa($baris_bagi)) ? (string) $baris_bagi["token"] : "";
$tautan_bagi   = $token_bagi !== "" ? url_rencana_publik($token_bagi) : "";
$tautan_langganan = ($token_bagi !== "" && !bagi_terkunci($baris_bagi) && $tanggal_mulai !== "")
    ? url_rencana_ics($token_bagi, true) : "";
$tautan_wa_bagi = $tautan_bagi !== ""
    ? "https://wa.me/?text=" . rawurlencode("Rencana Dolan Jogja: " . $tautan_bagi) : "";

/* Pilihan moda transportasi untuk perkiraan biaya per kilometer. */
$moda_transport = array(
    "motor" => array("label" => "Motor pribadi", "tarif" => 1500),
    "mobil" => array("label" => "Mobil pribadi", "tarif" => 2500),
    "sewa"  => array("label" => "Sewa mobil + driver", "tarif" => 6000),
);

// Teks ringkasan untuk tombol "Salin"
$teks = array("Rencana Dolan Jogja (" . $orang . " orang)", "");
foreach ($hari_list as $h => $blok) {
    $jad_h_teks = $jadwal_hari[$h];
    $label_hari_teks = $jad_h_teks["tanggal"] !== null
        ? label_hari_pekan($jad_h_teks["pekan"]) . ", " . tanggal_ringkas($jad_h_teks["tanggal"]) . ", "
        : "";
    $teks[] = "Hari ke-" . $h . " (" . $label_hari_teks
            . menit_ke_jam($jad_h_teks["mulai"]) . "-" . menit_ke_jam($jad_h_teks["selesai"])
            . ", " . number_format($jad_h_teks["km"], 1, ",", ".") . " km)";
    foreach ($blok["item"] as $i => $r) {
        $teks[] = ($i + 1) . ". " . menit_ke_jam($jad_h_teks["item"][$i]["tiba"]) . " " . $r["nama"] . " - " . $r["lokasi"]
                . " (" . rencana_harga($r["_min"], $r["_maks"]) . "/orang)";
    }
    $teks[] = "Subtotal: " . rencana_harga($blok["min"] * $orang, $blok["maks"] * $orang);
    $teks[] = "";
}
$teks[] = "Total estimasi tiket: " . rencana_harga($total_min * $orang, $total_maks * $orang);
$teks[] = "Estimasi jarak rute: " . number_format($jarak_total, 1, ",", ".") . " km (belum termasuk perjalanan pulang)";
$ringkasan = implode("\n", $teks);

$pesan = $_SESSION["rencana_pesan"] ?? null;
unset($_SESSION["rencana_pesan"]);
$csrf_token = generate_csrf_token();

function rencana_hidden($token, $orang, $id) {
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '" />'
         . '<input type="hidden" name="orang" value="' . (int) $orang . '" />'
         . '<input type="hidden" name="id" value="' . (int) $id . '" />';
}

$judul = "Rencana Dolan";
$menu_aktif = "rencana";
require_once __DIR__ . "/../includes/header.php";
?>
<div class="wadah">
    <div class="halaman-isi">
        <?php if (count($hari_list) > 0) { ?>
        <div class="cetak-header">
            <div class="cetak-header-isi">
                <div>
                    <p class="cetak-eyebrow">Dolan Jogja</p>
                    <h1>Itinerary Resmi &mdash; Dolan Jogja</h1>
                    <p class="cetak-meta">Dicetak pada <?php echo e(date("d/m/Y H:i")); ?> WIB &middot; <?php echo count($hari_list); ?> hari &middot; <?php echo $orang; ?> orang<?php if ($tanggal_mulai !== "") { ?> &middot; mulai <?php echo e(tanggal_ringkas($tanggal_mulai)); ?><?php } ?></p>
                </div>
                <div class="cetak-qr">
                    <div id="qr-rencana" class="qr-kotak"></div>
                    <span>Pindai untuk membuka rencana</span>
                </div>
            </div>
            <div class="cetak-anggaran">
                <span>Total estimasi tiket (<?php echo $orang; ?> orang): <strong><?php echo e(rencana_harga($total_min * $orang, $total_maks * $orang)); ?></strong></span>
                <span>Patungan tiket per orang: <strong><?php echo e(rencana_harga($total_min, $total_maks)); ?></strong></span>
                <span>Estimasi jarak rute: <strong><?php echo e(number_format($jarak_total, 1, ",", ".")); ?> km</strong></span>
                <span class="cetak-biaya" id="cetak-biaya-transport">Estimasi transport: <strong>-</strong></span>
            </div>
        </div>
        <?php } ?>

        <h1>Rencana Dolan</h1>
        <p class="tidak-dicetak">Susun destinasi favoritmu per hari, lihat estimasi biaya tiket, lalu cetak atau salin ringkasannya.</p>

        <?php if ($pesan) { ?><p class="<?php echo e($pesan[0]); ?>"><?php echo e($pesan[1]); ?></p><?php } ?>

        <?php if (count($hari_list) === 0) { ?>
            <p class="kosong">Belum ada destinasi di rencana Anda. Buka halaman detail destinasi lalu klik &quot;Simpan ke favorit&quot;. <a href="<?php echo BASE_URL; ?>index.php#destinasi">Jelajahi destinasi</a></p>
        <?php } else { ?>

            <form method="post" action="rencana.php" class="jadwal-hari tanggal-mulai tidak-dicetak">
                <div class="inline">
                    <?php echo rencana_hidden($csrf_token, $orang, 0); ?>
                    <input type="hidden" name="aksi" value="tanggal_mulai" />
                    <label for="tanggal-mulai">Tanggal mulai</label>
                    <input type="date" id="tanggal-mulai" name="tanggal" value="<?php echo e($tanggal_mulai); ?>" />
                    <button type="submit" class="btn-kecil">Simpan</button>
                    <?php if ($tanggal_mulai !== "") { ?>
                        <button type="submit" name="kosongkan" value="1" class="btn-kecil">Kosongkan</button>
                    <?php } ?>
                    <span class="kecil">
                        <?php if ($tanggal_mulai !== "") { $info_mulai = info_hari($tanggal_mulai, 1); ?>
                            Hari ke-1 = <strong><?php echo e(label_hari_tanggal($info_mulai)); ?></strong> &middot; hari berikutnya mengikuti otomatis.
                            <?php if (!tanggal_valid($tanggal_mulai)) { ?><em>Tanggal mulai sudah lewat.</em><?php } ?>
                        <?php } else { ?>
                            Isi tanggal agar hari tutup dicek otomatis.
                        <?php } ?>
                    </span>
                </div>
            </form>

            <div class="panel total-rencana">
                <form method="get" action="rencana.php" class="form-isi form-orang tidak-dicetak">
                    <div>
                        <label for="orang">Jumlah orang</label>
                        <input type="number" id="orang" name="orang" min="1" max="50" value="<?php echo $orang; ?>" />
                        <input type="submit" value="Hitung" />
                    </div>
                </form>
                <div class="patungan-grid tidak-dicetak">
                    <div>
                        <label for="moda-transport">Moda transportasi (perkiraan biaya)</label>
                        <select id="moda-transport">
                            <option value="0">Isi biaya transport manual</option>
                            <?php foreach ($moda_transport as $kunci_moda => $m) { ?>
                                <option value="<?php echo (int) $m["tarif"]; ?>" data-moda="<?php echo e($kunci_moda); ?>"><?php echo e($m["label"]); ?> (&plusmn; <?php echo e(rupiah($m["tarif"])); ?>/km)</option>
                            <?php } ?>
                        </select>
                        <p class="kecil moda-keterangan">Estimasi jarak rute: <strong><?php echo e(number_format($jarak_total, 1, ",", ".")); ?> km</strong>. Biaya transport terisi otomatis sesuai moda.</p>
                    </div>
                    <div>
                        <label for="biaya-transport">Estimasi transportasi / bensin (Rp)</label>
                        <input type="number" id="biaya-transport" min="0" step="5000" value="0" />
                    </div>
                    <div>
                        <label for="biaya-konsumsi">Estimasi makan &amp; jajan (Rp)</label>
                        <input type="number" id="biaya-konsumsi" min="0" step="5000" value="0" />
                    </div>
                </div>

                <p class="total-label">Total estimasi tiket untuk <?php echo $orang; ?> orang</p>
                <p class="total-angka"><?php echo e(rencana_harga($total_min * $orang, $total_maks * $orang)); ?></p>

                <div class="patungan-hasil" id="patungan-hasil">
                    <span>Total keseluruhan: <strong id="total-gabungan"><?php echo e(rencana_harga($total_min * $orang, $total_maks * $orang)); ?></strong></span>
                    <span>Patungan per orang: <strong id="patungan-orang"><?php echo e(rencana_harga($total_min, $total_maks)); ?></strong></span>
                </div>
                <p class="kecil">Perkiraan dari data harga tiket; harga bisa berbeda saat hari libur. Belum termasuk transportasi dan makan. Jadwal memakai estimasi durasi kunjungan per kategori dan kecepatan rata-rata 30 km/jam.</p>
                <div class="rencana-aksi tidak-dicetak">
                    <button type="button" class="tombol" onclick="cetakRencana();">Cetak</button>
                    <button type="button" class="tombol tombol-garis" onclick="salinRingkasan();">Salin ringkasan</button>
                    <a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>peta.php?rute=1">Lihat Rute di Peta</a>
                    <?php if ($tanggal_mulai !== "") { ?><a class="tombol tombol-garis" href="<?php echo BASE_URL; ?>rencana_ics.php">Unduh Kalender (.ics)</a><?php } ?>
                    <form method="post" action="rencana.php" class="inline">
                        <?php echo rencana_hidden($csrf_token, $orang, 0); ?>
                        <button type="submit" name="aksi" value="rapikan_hari" class="tombol tombol-garis">Rapikan urutan hari</button>
                    </form>
                    <span id="status-salin" class="kecil"></span>
                </div>
                <p class="kecil">Rapikan urutan hari menyusun ulang destinasi tiap hari agar jarak tempuhnya paling pendek.</p>
                <?php $snap_terakhir = snapshot_terakhir($conn, $uid); ?>
                <?php if ($snap_terakhir !== null) { ?>
                    <div class="undo-rencana kecil tidak-dicetak">
                        <span>Aksi terakhir: <strong><?php echo e(label_aksi_snapshot($snap_terakhir["aksi"])); ?></strong>
                            &middot; <?php echo e(substr($snap_terakhir["dibuat"], 8, 2) . "/" . substr($snap_terakhir["dibuat"], 5, 2) . " " . substr($snap_terakhir["dibuat"], 11, 5)); ?></span>
                        <form method="post" action="rencana.php" class="inline" onsubmit="return confirm('Kembalikan rencana ke keadaan sebelum aksi terakhir?');">
                            <?php echo rencana_hidden($csrf_token, $orang, 0); ?>
                            <button type="submit" name="aksi" value="batal_aksi" class="btn-kecil">Batalkan aksi</button>
                        </form>
                    </div>
                <?php } ?>
                <textarea id="ringkasan-teks" class="sembunyi" rows="6" cols="40" readonly="readonly"><?php echo e($ringkasan); ?></textarea>
            </div>

            <div class="panel tidak-dicetak" id="panel-bagi">
                <h3>Bagikan rencana <span class="kecil">&middot; hanya baca</span></h3>
                <?php if ($token_bagi !== "") { ?>
                    <p class="kecil">Siapa pun yang memiliki tautan ini dapat melihat rencana tanpa akun. Matikan tautan bila sudah tidak dipakai.</p>
                    <div class="bagi-tautan">
                        <input type="text" id="tautan-bagi" readonly="readonly" value="<?php echo e($tautan_bagi); ?>" onclick="this.select();" />
                        <button type="button" class="btn-kecil" data-salin="tautan-bagi">Salin</button>
                        <a class="btn-kecil btn-tautan" href="<?php echo e($tautan_bagi); ?>">Buka</a>
                        <a class="btn-kecil btn-tautan" href="<?php echo e($tautan_wa_bagi); ?>" target="_blank" rel="noopener">WhatsApp</a>
                    </div>
                    <p class="bagi-status">
                        <span>Berlaku: <strong><?php echo $baris_bagi["kedaluwarsa"] !== null ? "sampai " . e(tanggal_ringkas($baris_bagi["kedaluwarsa"])) : "tanpa batas waktu"; ?></strong></span>
                        <span>Kode akses: <strong><?php echo bagi_terkunci($baris_bagi) ? "aktif" : "tidak ada"; ?></strong></span>
                        <span>Dibuka <strong><?php echo (int) $baris_bagi["jumlah_buka"]; ?> kali</strong><?php if ($baris_bagi["terakhir_diakses"] !== null) { ?> &middot; terakhir <?php echo e(tanggal_ringkas(substr($baris_bagi["terakhir_diakses"], 0, 10)) . " " . substr($baris_bagi["terakhir_diakses"], 11, 5)); ?><?php } ?></span>
                    </p>

                    <form method="post" action="rencana.php" class="bagi-kode">
                        <?php echo rencana_hidden($csrf_token, $orang, 0); ?>
                        <label for="kode-bagi">Kode akses</label>
                        <input type="text" id="kode-bagi" name="kode" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off" placeholder="<?php echo bagi_terkunci($baris_bagi) ? "kode baru" : "opsional"; ?>" />
                        <button type="submit" name="aksi" value="kode_bagi" class="btn-kecil">Simpan kode</button>
                        <span class="kecil">4&ndash;6 angka; kosongkan lalu simpan untuk menghapus kode akses.</span>
                    </form>

                    <form method="post" action="rencana.php" class="bagi-kode">
                        <?php echo rencana_hidden($csrf_token, $orang, 0); ?>
                        <label class="kecil"><input type="checkbox" name="tanpa" value="1"<?php echo (int) $baris_bagi["tanpa_biaya"] === 1 ? ' checked="checked"' : ""; ?> /> Sembunyikan perkiraan biaya di halaman publik dan kalender</label>
                        <button type="submit" name="aksi" value="biaya_bagi" class="btn-kecil">Simpan tampilan</button>
                    </form>

                    <?php if ($tautan_langganan !== "") { ?>
                        <p class="kecil">Langganan kalender &mdash; alamat ini selalu mengikuti rencana terbaru:</p>
                        <div class="bagi-tautan">
                            <input type="text" id="tautan-langganan" readonly="readonly" value="<?php echo e($tautan_langganan); ?>" onclick="this.select();" />
                            <button type="button" class="btn-kecil" data-salin="tautan-langganan">Salin</button>
                        </div>
                        <p class="kecil">Tempel di &quot;Tambahkan kalender dari URL&quot; pada Google Calendar atau &quot;Langganan kalender&quot; di iPhone.</p>
                    <?php } elseif ($tanggal_mulai === "") { ?>
                        <p class="kecil">Isi tanggal mulai agar jadwal bisa diekspor ke kalender.</p>
                    <?php } elseif (bagi_terkunci($baris_bagi)) { ?>
                        <p class="kecil">Hapus kode akses bila ingin memakai URL langganan kalender; aplikasi kalender tidak bisa memasukkan kode.</p>
                    <?php } ?>

                    <div class="rencana-aksi">
                        <form method="post" action="rencana.php" class="inline" onsubmit="return confirm('Matikan tautan berbagi? Tautan lama tidak bisa dibuka lagi.');">
                            <?php echo rencana_hidden($csrf_token, $orang, 0); ?>
                            <button type="submit" name="aksi" value="hentikan_bagi" class="btn-kecil btn-bahaya">Hentikan tautan</button>
                        </form>
                    </div>
                <?php } else { ?>
                    <p class="kecil">
                        <?php if ($baris_bagi !== null) { ?>
                            <strong>Tautan sebelumnya sudah berakhir.</strong>
                        <?php } ?>
                        Buat tautan agar rencana ini bisa dibuka tanpa akun. Penerima hanya bisa melihat, tidak bisa mengubah.
                    </p>
                    <form method="post" action="rencana.php" class="bagi-kode">
                        <?php echo rencana_hidden($csrf_token, $orang, 0); ?>
                        <label for="masa-bagi">Masa berlaku</label>
                        <select id="masa-bagi" name="masa">
                            <?php foreach (masa_berlaku_pilihan() as $nilai_masa => $label_masa) { ?>
                                <option value="<?php echo e($nilai_masa); ?>"><?php echo e($label_masa); ?></option>
                            <?php } ?>
                        </select>
                        <label for="kode-bagi-baru">Kode akses</label>
                        <input type="text" id="kode-bagi-baru" name="kode" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off" placeholder="opsional" />
                        <button type="submit" name="aksi" value="bagikan" class="btn-kecil">Buat tautan berbagi</button>
                    </form>
                    <p class="kecil">Kode akses 4&ndash;6 angka membuat penerima harus memasukkannya sebelum melihat rencana. Tanpa kode, tautan bisa dipakai untuk langganan kalender.</p>
                <?php } ?>
            </div>

            <?php foreach ($hari_list as $h => $blok) {
                $jad = $jadwal_hari[$h];
                $bisa_ubah = true;
                require __DIR__ . "/../includes/rencana_hari.php";
            } ?>

            <div class="cetak-catatan">
                <p class="kecil">Rencana ini dibuat dengan Dolan Jogja. Jam buka dan harga tiket dapat berubah; konfirmasi ulang sebelum berangkat.</p>
            </div>

        <?php } ?>
    </div>
</div>

<?php if (count($hari_list) > 0) { ?>
<script type="text/javascript" src="<?php echo BASE_URL; ?>assets/js/qrcode.js"></script>
<?php } ?>
<script type="text/javascript">
//<![CDATA[
function cetakRencana() { window.print(); }

function salinRingkasan() {
    var teks = document.getElementById("ringkasan-teks").value;
    var status = document.getElementById("status-salin");
    function ok() { status.textContent = "Ringkasan disalin."; }
    function gagal() { status.textContent = "Gagal menyalin. Gunakan tombol Cetak."; }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(teks).then(ok, gagal);
        return;
    }
    var t = document.createElement("textarea");
    t.value = teks;
    document.body.appendChild(t);
    t.select();
    try { document.execCommand("copy") ? ok() : gagal(); } catch (e) { gagal(); }
    document.body.removeChild(t);
}

(function () {
    var inTrans = document.getElementById("biaya-transport");
    var inKons  = document.getElementById("biaya-konsumsi");
    var moda    = document.getElementById("moda-transport");
    var elTotal = document.getElementById("total-gabungan");
    var elOrang = document.getElementById("patungan-orang");
    var cetakBiaya = document.getElementById("cetak-biaya-transport");
    if (!inTrans || !inKons || !elTotal || !elOrang) { return; }

    var tiketMin = <?php echo (int) ($total_min * $orang); ?>;
    var tiketMaks = <?php echo (int) ($total_maks * $orang); ?>;
    var jmlOrang = <?php echo (int) $orang; ?>;
    var jarakKm = <?php echo json_encode((float) $jarak_total); ?>;

    function rp(n) { return "Rp " + Math.round(n).toLocaleString("id-ID"); }
    function fmt(a, b) { return a <= 0 && b <= 0 ? "Gratis" : (a === b ? rp(a) : rp(a) + " - " + rp(b)); }

    function update() {
        var trans = Math.max(0, parseInt(inTrans.value, 10) || 0);
        var kons = Math.max(0, parseInt(inKons.value, 10) || 0);
        var totMin = tiketMin + trans + kons;
        var totMaks = tiketMaks + trans + kons;
        elTotal.textContent = fmt(totMin, totMaks);
        elOrang.textContent = fmt(totMin / jmlOrang, totMaks / jmlOrang);
        if (cetakBiaya) {
            cetakBiaya.innerHTML = "Estimasi transport: <strong>" + (trans > 0 ? rp(trans) : "-") + "</strong>";
        }
    }

    /* Moda transportasi: biaya = jarak rute x tarif per km. */
    if (moda) {
        moda.addEventListener("change", function () {
            var tarif = parseInt(moda.value, 10) || 0;
            if (tarif > 0) {
                inTrans.value = Math.round(jarakKm * tarif);
            }
            update();
        });
    }

    inTrans.addEventListener("input", update);
    inKons.addEventListener("input", update);
    update();
})();

/* Kode QR lokal untuk header cetak: dibuat di browser, tanpa layanan pihak ketiga */
(function () {
    var wadah = document.getElementById("qr-rencana");
    if (!wadah || typeof qrcode === "undefined") { return; }
    try {
        var qr = qrcode(0, "M");
        qr.addData(window.location.href);
        qr.make();
        wadah.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 0 });
    } catch (e) {
        wadah.className += " qr-gagal";
        wadah.innerHTML = '<span class="kecil">QR tidak tersedia</span>';
    }
})();
//]]>
</script>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
