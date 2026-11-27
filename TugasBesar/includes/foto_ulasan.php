<?php
/*
 * Foto kunjungan dari pengunjung ("Foto dari Pengunjung").
 *
 * Satu ulasan boleh melampirkan satu foto pengalaman kunjungan. Foto disimpan
 * di assets/uploads/ulasan/<id_destinasi>/ dan dicatat pada tabel review_photos.
 * Tabel dibuat otomatis (CREATE TABLE IF NOT EXISTS) supaya database lama tetap
 * berjalan tanpa impor ulang; tabel yang sama juga ada di database.sql.
 */

require_once __DIR__ . "/galeri.php"; // memakai simpan_gambar_teroptimasi()

if (!function_exists("pastikan_foto_ulasan")) {
    function pastikan_foto_ulasan($conn) {
        static $siap = null;
        if ($siap !== null) { return $siap; }
        $siap = false;
        if (!($conn instanceof mysqli)) { return $siap; }

        $sql = "CREATE TABLE IF NOT EXISTS review_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            nama_file VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unik_foto_ulasan (review_id),
            CONSTRAINT fk_foto_ulasan FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $siap = (bool) @mysqli_query($conn, $sql);
        if (!$siap) {
            error_log("Dolan Jogja (includes/foto_ulasan.php): tabel review_photos gagal disiapkan - " . mysqli_error($conn));
        }
        return $siap;
    }
}

if (!function_exists("folder_foto_ulasan")) {
    function folder_foto_ulasan($dest_id) {
        return __DIR__ . "/../assets/uploads/ulasan/" . (int) $dest_id;
    }
}

if (!function_exists("url_foto_ulasan")) {
    function url_foto_ulasan($dest_id, $nama_file) {
        return BASE_URL . "assets/uploads/ulasan/" . (int) $dest_id . "/" . rawurlencode(basename((string) $nama_file));
    }
}

/*
 * Peta review_id => foto untuk satu destinasi, dipakai saat menampilkan daftar ulasan.
 * Hasil: array(review_id => array("nama_file" => ..., "url" => ...)).
 */
if (!function_exists("foto_ulasan_per_ulasan")) {
    function foto_ulasan_per_ulasan($conn, $dest_id) {
        $hasil = array();
        $dest_id = (int) $dest_id;
        if ($dest_id <= 0 || !($conn instanceof mysqli) || !pastikan_foto_ulasan($conn)) { return $hasil; }

        $st = mysqli_prepare($conn, "SELECT p.review_id, p.nama_file
            FROM review_photos p JOIN reviews r ON r.id = p.review_id
            WHERE r.destination_id = ? ORDER BY p.id DESC");
        if (!$st) { return $hasil; }
        mysqli_stmt_bind_param($st, "i", $dest_id);
        mysqli_stmt_execute($st);
        foreach (mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC) as $baris) {
            $nama_file = basename((string) $baris["nama_file"]);
            if ($nama_file === "") { continue; }
            $hasil[(int) $baris["review_id"]] = array(
                "nama_file" => $nama_file,
                "url"       => url_foto_ulasan($dest_id, $nama_file),
            );
        }
        mysqli_stmt_close($st);
        return $hasil;
    }
}

/* Daftar foto kunjungan terbaru untuk galeri komunitas di halaman detail. */
if (!function_exists("foto_ulasan_destinasi")) {
    function foto_ulasan_destinasi($conn, $dest_id, $batas = 8) {
        $hasil = array();
        $dest_id = (int) $dest_id;
        $batas = max(1, min(24, (int) $batas));
        if ($dest_id <= 0 || !($conn instanceof mysqli) || !pastikan_foto_ulasan($conn)) { return $hasil; }

        $st = mysqli_prepare($conn, "SELECT p.nama_file, r.id AS review_id, r.rating, r.created_at, u.username
            FROM review_photos p
            JOIN reviews r ON r.id = p.review_id
            JOIN users u ON u.id = r.user_id
            WHERE r.destination_id = ?
            ORDER BY p.id DESC LIMIT ?");
        if (!$st) { return $hasil; }
        mysqli_stmt_bind_param($st, "ii", $dest_id, $batas);
        mysqli_stmt_execute($st);
        foreach (mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC) as $baris) {
            $nama_file = basename((string) $baris["nama_file"]);
            if ($nama_file === "") { continue; }
            $baris["nama_file"] = $nama_file;
            $baris["url"] = url_foto_ulasan($dest_id, $nama_file);
            $hasil[] = $baris;
        }
        mysqli_stmt_close($st);
        return $hasil;
    }
}

/* Nama berkas foto milik satu ulasan (null bila belum ada). */
if (!function_exists("foto_ulasan_review")) {
    function foto_ulasan_review($conn, $review_id) {
        $review_id = (int) $review_id;
        if ($review_id <= 0 || !($conn instanceof mysqli) || !pastikan_foto_ulasan($conn)) { return null; }
        $st = mysqli_prepare($conn, "SELECT nama_file FROM review_photos WHERE review_id = ? LIMIT 1");
        if (!$st) { return null; }
        mysqli_stmt_bind_param($st, "i", $review_id);
        mysqli_stmt_execute($st);
        $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        return $baris ? basename((string) $baris["nama_file"]) : null;
    }
}

/* Hapus berkas foto kunjungan dari folder destinasi. */
if (!function_exists("hapus_berkas_foto_ulasan")) {
    function hapus_berkas_foto_ulasan($nama_file, $dest_id) {
        $nama_file = basename((string) $nama_file);
        if ($nama_file === "" || $nama_file === "." || $nama_file === "..") { return false; }
        $jalur = folder_foto_ulasan($dest_id) . "/" . $nama_file;
        return is_file($jalur) ? (bool) @unlink($jalur) : false;
    }
}

/*
 * Simpan (atau ganti) foto kunjungan milik satu ulasan.
 * Hasil: array("ok" => bool, "pesan" => string). pesan kosong = pengguna tidak
 * mengunggah apa pun (bukan galat).
 */
if (!function_exists("simpan_foto_ulasan")) {
    function simpan_foto_ulasan($conn, $review_id, $dest_id, $file) {
        $hasil = array("ok" => false, "pesan" => "");
        $review_id = (int) $review_id;
        $dest_id = (int) $dest_id;
        if ($review_id <= 0 || $dest_id <= 0) {
            $hasil["pesan"] = "Ulasan tidak dikenali.";
            return $hasil;
        }
        if (!is_array($file) || !isset($file["error"])) { return $hasil; }

        $err = (int) $file["error"];
        if ($err === UPLOAD_ERR_NO_FILE) { return $hasil; }
        if ($err !== UPLOAD_ERR_OK) {
            $hasil["pesan"] = "Foto gagal diunggah (kode " . $err . ").";
            return $hasil;
        }
        if ((int) ($file["size"] ?? 0) > 5242880) {
            $hasil["pesan"] = "Ukuran foto maksimal 5 MB.";
            return $hasil;
        }

        $tmp = (string) ($file["tmp_name"] ?? "");
        $info = $tmp !== "" ? @getimagesize($tmp) : false;
        if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP), true)) {
            $hasil["pesan"] = "Format foto harus JPG, PNG, atau WEBP.";
            return $hasil;
        }
        if (!pastikan_foto_ulasan($conn)) {
            $hasil["pesan"] = "Tabel foto ulasan belum siap.";
            return $hasil;
        }

        $folder = folder_foto_ulasan($dest_id);
        if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
            $hasil["pesan"] = "Folder foto ulasan tidak dapat dibuat.";
            return $hasil;
        }

        /* Tanpa GD berkas disalin apa adanya, jadi ekstensi mengikuti format aslinya. */
        $ext = "jpg";
        if (!function_exists("imagecreatefromjpeg")) {
            $peta_ext = array(IMAGETYPE_JPEG => "jpg", IMAGETYPE_PNG => "png", IMAGETYPE_WEBP => "webp");
            $ext = isset($peta_ext[$info[2]]) ? $peta_ext[$info[2]] : "jpg";
        }
        $nama_file = "ulasan-" . $review_id . "-" . bin2hex(random_bytes(6)) . "." . $ext;
        if (!simpan_gambar_teroptimasi($tmp, $folder . "/" . $nama_file, 1280, 82)) {
            $hasil["pesan"] = "Foto gagal disimpan.";
            return $hasil;
        }

        $lama = foto_ulasan_review($conn, $review_id);

        $st = mysqli_prepare($conn, "DELETE FROM review_photos WHERE review_id = ?");
        if ($st) {
            mysqli_stmt_bind_param($st, "i", $review_id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
        }

        $st = mysqli_prepare($conn, "INSERT INTO review_photos (review_id, nama_file) VALUES (?, ?)");
        if (!$st) {
            @unlink($folder . "/" . $nama_file);
            $hasil["pesan"] = "Data foto gagal disimpan.";
            return $hasil;
        }
        mysqli_stmt_bind_param($st, "is", $review_id, $nama_file);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        if (!$ok) {
            @unlink($folder . "/" . $nama_file);
            $hasil["pesan"] = "Data foto gagal disimpan.";
            return $hasil;
        }

        if ($lama !== null && $lama !== $nama_file) { hapus_berkas_foto_ulasan($lama, $dest_id); }
        $hasil["ok"] = true;
        return $hasil;
    }
}

/* Hapus foto kunjungan milik satu ulasan (baris database + berkas). */
if (!function_exists("hapus_foto_ulasan")) {
    function hapus_foto_ulasan($conn, $review_id, $dest_id) {
        $review_id = (int) $review_id;
        $dest_id = (int) $dest_id;
        if ($review_id <= 0 || $dest_id <= 0) { return false; }

        $lama = foto_ulasan_review($conn, $review_id);
        if ($lama === null) { return false; }

        $st = mysqli_prepare($conn, "DELETE FROM review_photos WHERE review_id = ?");
        if ($st) {
            mysqli_stmt_bind_param($st, "i", $review_id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
        }
        hapus_berkas_foto_ulasan($lama, $dest_id);
        return true;
    }
}
