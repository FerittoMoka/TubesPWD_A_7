<?php
/* Partial satu hari Rencana Dolan, dipakai halaman pemilik dan halaman publik.
   Wajib: $h, $blok, $jad.
   Opsional: $orang (default 1), $bisa_ubah (default false), $tampil_biaya (default true).
   Bila $bisa_ubah true: $csrf_token dan $maks_hari harus tersedia. */
if (!isset($h, $blok, $jad)) { http_response_code(404); exit; }
$orang = max(1, (int) ($orang ?? 1));
$bisa_ubah = !empty($bisa_ubah);
$tampil_biaya = !isset($tampil_biaya) ? true : (bool) $tampil_biaya;
$jumlah_item = count($blok["item"]);
?>
<div class="panel">
    <h3>Hari ke-<?php echo (int) $h; ?> <span class="kecil">&middot; <?php if ($jad["tanggal"] !== null) { ?><?php echo e(label_hari_tanggal(array("tgl" => $jad["tanggal"], "pekan" => $jad["pekan"]))); ?> &middot; <?php } ?><?php echo e(menit_ke_jam($jad["mulai"])); ?>&ndash;<?php echo e(menit_ke_jam($jad["selesai"])); ?><?php if ($jad["km"] > 0) { ?> &middot; &plusmn; <?php echo e(number_format($jad["km"], 1, ",", ".")); ?> km<?php } ?><?php if ($tampil_biaya) { ?> &middot; <?php echo $bisa_ubah ? "subtotal " : ""; ?><?php echo e(rencana_harga($blok["min"] * $orang, $blok["maks"] * $orang)); ?><?php echo $bisa_ubah ? "" : "/orang"; ?><?php } ?></span></h3>

    <?php if ($bisa_ubah) { ?>
        <form method="post" action="rencana.php" class="jadwal-hari tidak-dicetak">
            <div class="inline">
                <?php echo rencana_hidden($csrf_token, $orang, 0); ?>
                <input type="hidden" name="hari" value="<?php echo (int) $h; ?>" />
                <label for="jam-hari-<?php echo (int) $h; ?>">Mulai pukul</label>
                <input type="time" id="jam-hari-<?php echo (int) $h; ?>" name="jam_mulai" value="<?php echo e(jam_input($jad["mulai"])); ?>" step="300" />
                <button type="submit" name="aksi" value="jam_hari" class="btn-kecil">Atur jam</button>
                <span class="kecil"><?php echo $jumlah_item; ?> destinasi &middot; &plusmn; <?php echo e(jam_ringkas($jad["menit_kunjungan"])); ?> jam di lokasi<?php if ($jad["menit_jalan"] > 0) { ?> &middot; <?php echo (int) $jad["menit_jalan"]; ?> menit perjalanan<?php } ?></span>
            </div>
        </form>
        <?php
        $tanpa_jam = 0;
        foreach ($blok["item"] as $r_jam) {
            if (count(rentang_jam_buka($r_jam["jam_buka"])) === 0) { $tanpa_jam++; }
        }
        ?>
        <?php if ($tanpa_jam > 0) { ?>
            <p class="jadwal-catatan info"><?php echo $tanpa_jam; ?> destinasi belum punya data jam buka, jadi jam jadwalnya belum bisa dicek.</p>
        <?php } ?>
    <?php } ?>

    <div class="daftar">
        <?php foreach ($blok["item"] as $i => $r) { $jad_i = $jad["item"][$i]; ?>
            <div class="baris baris-rencana">
                <?php echo tampil_foto($r["foto"], $r["kategori"], $r["nama"]); ?>
                <div class="baris-info">
                    <p class="baris-jadwal">
                        <strong><?php echo e(menit_ke_jam($jad_i["tiba"])); ?></strong> tiba
                        &middot; selesai &plusmn; <?php echo e(menit_ke_jam($jad_i["pulang"])); ?>
                        <?php if ($jad_i["menit_jalan"] > 0) { ?>&middot; <?php echo e(number_format($jad_i["km"], 1, ",", ".")); ?> km (<?php echo (int) $jad_i["menit_jalan"]; ?> mnt)<?php } ?>
                    </p>
                    <h3><a href="<?php echo BASE_URL; ?>destinasi/detail.php?id=<?php echo (int) $r["did"]; ?>"><?php echo ($i + 1) . ". " . e($r["nama"]); ?></a></h3>
                    <p class="baris-meta"><?php echo e($r["kategori"]); ?> &middot; <?php echo e($r["lokasi"]); ?><span class="cetak-saja"> &middot; Jam buka: <?php echo e($r["jam_buka"] ?: "belum tersedia"); ?></span></p>
                    <?php foreach ($jad_i["catatan"] as $c_jad) { ?>
                        <p class="jadwal-catatan <?php echo e($c_jad[0]); ?>"><?php echo e($c_jad[1]); ?></p>
                    <?php } ?>
                </div>
                <?php if ($tampil_biaya) { ?>
                    <div class="baris-harga"><?php echo e(rencana_harga($r["_min"], $r["_maks"])); ?><span class="kecil"> /orang</span></div>
                <?php } ?>

                <?php if ($bisa_ubah) { ?>
                    <div class="rencana-aksi tidak-dicetak">
                        <form method="post" action="rencana.php" class="inline">
                            <div class="inline">
                                <?php echo rencana_hidden($csrf_token, $orang, $r["id"]); ?>
                                <select name="hari" title="Pindah ke hari">
                                    <?php for ($n = 1; $n <= $maks_hari; $n++) { ?>
                                        <option value="<?php echo $n; ?>"<?php if ($n === (int) $h) { echo ' selected="selected"'; } ?>>Hari <?php echo $n; ?></option>
                                    <?php } ?>
                                </select>
                                <button type="submit" name="aksi" value="pindah_hari" class="btn-kecil">Pindah</button>
                            </div>
                        </form>
                        <form method="post" action="rencana.php" class="inline">
                            <div class="inline">
                                <?php echo rencana_hidden($csrf_token, $orang, $r["id"]); ?>
                                <button type="submit" name="aksi" value="naik" class="btn-kecil" title="Naikkan urutan"<?php if ($i === 0) { echo ' disabled="disabled"'; } ?>>&uarr;</button>
                                <button type="submit" name="aksi" value="turun" class="btn-kecil" title="Turunkan urutan"<?php if ($i === $jumlah_item - 1) { echo ' disabled="disabled"'; } ?>>&darr;</button>
                            </div>
                        </form>
                        <form method="post" action="rencana.php" class="inline" onsubmit="return confirm('Hapus destinasi ini dari rencana?');">
                            <div class="inline">
                                <?php echo rencana_hidden($csrf_token, $orang, $r["id"]); ?>
                                <button type="submit" name="aksi" value="hapus" class="btn-kecil btn-bahaya">Hapus</button>
                            </div>
                        </form>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
