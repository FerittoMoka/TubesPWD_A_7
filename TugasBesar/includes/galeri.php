<?php
/*
 * Galeri foto destinasi (multi-slide).
 *
 * Tabel destination_photos menyimpan foto yang diunggah lewat panel admin,
 * sedangkan foto lama di assets/uploads/destinasi/galeri/<id>/ tetap terbaca
 * lewat galeri_destinasi() di includes/fungsi.php. Tabel dibuat otomatis bila
 * belum ada supaya instalasi lama tidak perlu impor ulang database.sql.
 */

if (!function_exists("pastikan_galeri_destinasi")) {
    function pastikan_galeri_destinasi($conn) {
        static $siap = null;
        if ($siap !== null) { return $siap; }
        $siap = false;
        if (!($conn instanceof mysqli)) { return $siap; }

        $sql = "CREATE TABLE IF NOT EXISTS destination_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            destination_id INT NOT NULL,
            nama_file VARCHAR(255) NOT NULL,
            keterangan VARCHAR(150) DEFAULT NULL,
            urutan TINYINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_destinasi_foto (destination_id, urutan),
            CONSTRAINT fk_foto_destinasi FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $siap = (bool) @mysqli_query($conn, $sql);
        if (!$siap) {
            error_log("Dolan Jogja (includes/galeri.php): tabel destination_photos gagal disiapkan - " . mysqli_error($conn));
        }
        return $siap;
    }
}

if (!function_exists("simpan_gambar_teroptimasi")) {
    /*
     * Simpan gambar unggahan sebagai JPEG teroptimasi (maks. $maxLebar px).
     * Memakai GD bawaan PHP; bila GD tidak tersedia file disalin apa adanya.
     */
    function simpan_gambar_teroptimasi($tmp, $tujuan, $maxLebar = 1280, $kualitas = 82) {
        if (!is_file($tmp)) { return false; }

        if (!function_exists("imagecreatefromjpeg")) {
            return @copy($tmp, $tujuan);
        }

        $info = @getimagesize($tmp);
        if (!$info) { return false; }

        $sumber = false;
        if ($info[2] === IMAGETYPE_JPEG) {
            $sumber = @imagecreatefromjpeg($tmp);
        } elseif ($info[2] === IMAGETYPE_PNG) {
            $sumber = @imagecreatefrompng($tmp);
        } elseif ($info[2] === IMAGETYPE_WEBP && function_exists("imagecreatefromwebp")) {
            $sumber = @imagecreatefromwebp($tmp);
        }
        if (!$sumber) { return false; }

        $lebar = imagesx($sumber);
        $tinggi = imagesy($sumber);
        if ($lebar > $maxLebar) {
            $kecil = @imagescale($sumber, $maxLebar, -1, IMG_BICUBIC);
            if ($kecil) {
                imagedestroy($sumber);
                $sumber = $kecil;
                $lebar = imagesx($sumber);
                $tinggi = imagesy($sumber);
            }
        }

        $kanvas = imagecreatetruecolor($lebar, $tinggi);
        if (!$kanvas) { imagedestroy($sumber); return false; }
        $putih = imagecolorallocate($kanvas, 255, 255, 255);
        imagefilledrectangle($kanvas, 0, 0, $lebar, $tinggi, $putih);
        imagecopy($kanvas, $sumber, 0, 0, 0, 0, $lebar, $tinggi);

        $ok = imagejpeg($kanvas, $tujuan, $kualitas);
        imagedestroy($kanvas);
        imagedestroy($sumber);
        return (bool) $ok;
    }
}

if (!function_exists("simpan_galeri_upload")) {
    /*
     * Unggah banyak foto galeri sekaligus (input name="galeri_foto[]" multiple).
     * Mengembalikan array("jumlah" => n, "galat" => array(pesan...)).
     */
    function simpan_galeri_upload($conn, $dest_id, $files, $keterangan = "") {
        $hasil = array("jumlah" => 0, "galat" => array());
        $dest_id = (int) $dest_id;
        if ($dest_id <= 0 || empty($files) || !isset($files["name"])) { return $hasil; }
        if (!pastikan_galeri_destinasi($conn)) {
            $hasil["galat"][] = "Tabel galeri belum siap.";
            return $hasil;
        }

        $folder = __DIR__ . "/../assets/uploads/destinasi/galeri/" . $dest_id;
        if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
            $hasil["galat"][] = "Folder galeri tidak dapat dibuat.";
            return $hasil;
        }

        $keterangan = trim((string) $keterangan);
        $keterangan = function_exists("mb_substr") ? mb_substr($keterangan, 0, 150, "UTF-8") : substr($keterangan, 0, 150);
        if ($keterangan === "") { $keterangan = null; }

        $nama_list = (array) $files["name"];
        $tmp_list  = (array) ($files["tmp_name"] ?? array());
        $err_list  = (array) ($files["error"] ?? array());
        $size_list = (array) ($files["size"] ?? array());

        $st = mysqli_prepare($conn, "SELECT COALESCE(MAX(urutan), 0) AS n FROM destination_photos WHERE destination_id = ?");
        $urutan = 0;
        if ($st) {
            mysqli_stmt_bind_param($st, "i", $dest_id);
            mysqli_stmt_execute($st);
            $urutan = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($st))["n"];
            mysqli_stmt_close($st);
        }

        $batas = 8;
        foreach ($nama_list as $i => $nama_asli) {
            if ($hasil["jumlah"] >= $batas) { break; }

            $err = (int) ($err_list[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) { continue; }
            if ($err !== UPLOAD_ERR_OK) {
                $hasil["galat"][] = basename((string) $nama_asli) . ": foto gagal diunggah.";
                continue;
            }
            if ((int) ($size_list[$i] ?? 0) > 5242880) {
                $hasil["galat"][] = basename((string) $nama_asli) . ": ukuran maksimal 5 MB.";
                continue;
            }

            $tmp = (string) ($tmp_list[$i] ?? "");
            $info = $tmp !== "" ? @getimagesize($tmp) : false;
            if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP), true)) {
                $hasil["galat"][] = basename((string) $nama_asli) . ": format harus JPG, PNG, atau WEBP.";
                continue;
            }

            $nama_file = "destinasi-" . $dest_id . "-" . bin2hex(random_bytes(6)) . ".jpg";
            if (!simpan_gambar_teroptimasi($tmp, $folder . "/" . $nama_file, 1280, 82)) {
                $hasil["galat"][] = basename((string) $nama_asli) . ": foto gagal disimpan.";
                continue;
            }

            $urutan++;
            $st = mysqli_prepare($conn, "INSERT INTO destination_photos (destination_id, nama_file, keterangan, urutan) VALUES (?, ?, ?, ?)");
            if (!$st) {
                @unlink($folder . "/" . $nama_file);
                $hasil["galat"][] = basename((string) $nama_asli) . ": data foto gagal disimpan.";
                continue;
            }
            mysqli_stmt_bind_param($st, "issi", $dest_id, $nama_file, $keterangan, $urutan);
            if (mysqli_stmt_execute($st)) {
                $hasil["jumlah"]++;
            } else {
                @unlink($folder . "/" . $nama_file);
                $hasil["galat"][] = basename((string) $nama_asli) . ": data foto gagal disimpan.";
            }
            mysqli_stmt_close($st);
        }

        return $hasil;
    }
}

if (!function_exists("perbarui_keterangan_galeri")) {
    /* Simpan caption dari input keterangan_foto[<id>] pada form admin. */
    function perbarui_keterangan_galeri($conn, $dest_id, $daftar) {
        $dest_id = (int) $dest_id;
        if ($dest_id <= 0 || !is_array($daftar)) { return; }
        foreach ($daftar as $foto_id => $teks) {
            $foto_id = (int) $foto_id;
            if ($foto_id <= 0) { continue; }
            $teks = trim((string) $teks);
            $teks = function_exists("mb_substr") ? mb_substr($teks, 0, 150, "UTF-8") : substr($teks, 0, 150);
            $nilai = $teks === "" ? null : $teks;
            $st = mysqli_prepare($conn, "UPDATE destination_photos SET keterangan = ? WHERE id = ? AND destination_id = ?");
            if (!$st) { continue; }
            mysqli_stmt_bind_param($st, "sii", $nilai, $foto_id, $dest_id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
        }
    }
}

if (!function_exists("hapus_foto_galeri")) {
    /* Hapus satu baris galeri beserta berkasnya di folder destinasi. */
    function hapus_foto_galeri($conn, $foto_id, $dest_id) {
        $foto_id = (int) $foto_id;
        $dest_id = (int) $dest_id;
        if ($foto_id <= 0 || $dest_id <= 0) { return false; }

        $st = mysqli_prepare($conn, "SELECT nama_file FROM destination_photos WHERE id = ? AND destination_id = ?");
        if (!$st) { return false; }
        mysqli_stmt_bind_param($st, "ii", $foto_id, $dest_id);
        mysqli_stmt_execute($st);
        $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if (!$baris) { return false; }

        $st = mysqli_prepare($conn, "DELETE FROM destination_photos WHERE id = ? AND destination_id = ?");
        if ($st) {
            mysqli_stmt_bind_param($st, "ii", $foto_id, $dest_id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
        }

        $berkas = basename((string) $baris["nama_file"]);
        $jalur = __DIR__ . "/../assets/uploads/destinasi/galeri/" . $dest_id . "/" . $berkas;
        if ($berkas !== "" && is_file($jalur)) { @unlink($jalur); }
        return true;
    }
}
