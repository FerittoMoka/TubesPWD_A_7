<?php
/*
 * Tabel "ulasan terbantu" (helpful vote).
 *
 * Dibuat otomatis dengan CREATE TABLE IF NOT EXISTS supaya fitur tetap berjalan pada database
 * yang diimpor sebelum fitur ini ada (kesiapan demo). Tabel yang sama juga ada di database.sql.
 * Dibuat sekali per request (static), dan kegagalan DDL tidak mematikan halaman.
 */
if (!function_exists("pastikan_bantuan_ulasan")) {
    function pastikan_bantuan_ulasan($conn) {
        static $siap = null;
        if ($siap !== null) { return $siap; }

        $sql = "CREATE TABLE IF NOT EXISTS review_helps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unik_bantuan (review_id, user_id),
            FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            $siap = (bool) mysqli_query($conn, $sql);
        } catch (Throwable $e) {
            error_log("Dolan Jogja (includes/ulasan_bantuan.php): " . $e->getMessage());
            $siap = false;
        }
        return $siap;
    }
}
