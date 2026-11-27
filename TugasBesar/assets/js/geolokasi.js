/*
 * Dolan Jogja - helper geolocation.
 *
 * Strategi dua tahap:
 *   1. Cepat lewat jaringan/Wi-Fi (enableHighAccuracy: false) supaya laptop/PC
 *      tanpa GPS tetap dapat koordinat perkiraan.
 *   2. Bila gagal karena sinyal/waktu habis, coba sekali lagi dengan akurasi tinggi.
 * Jika tetap gagal (atau halaman tidak dibuka lewat localhost/HTTPS), pengguna
 * ditawari titik acuan Tugu Yogyakarta agar fitur terdekat tidak buntu.
 */
(function (global) {
    "use strict";

    var PUSAT = { lat: -7.782889, lng: 110.367083, nama: "Tugu Yogyakarta" };

    function punyaGeolokasi() {
        return typeof navigator !== "undefined" && "geolocation" in navigator;
    }

    function konteksAman() {
        if (typeof window.isSecureContext === "boolean" && window.isSecureContext) { return true; }
        var host = String(location.hostname || "");
        return host === "localhost" || host === "127.0.0.1" || host === "::1";
    }

    function alasan(err) {
        var kode = err && err.code ? err.code : 0;
        if (kode === 1) {
            return "Izin lokasi diblokir browser. Klik ikon gembok/info di sebelah alamat situs, lalu izinkan akses Location.";
        }
        if (kode === 2) {
            return "Sinyal lokasi perangkat tidak tersedia (umum pada PC desktop tanpa GPS).";
        }
        if (kode === 3) {
            return "Waktu deteksi lokasi habis.";
        }
        return "Lokasi perangkat tidak dapat diambil.";
    }

    function cari(opsi) {
        opsi = opsi || {};
        var statusEl = opsi.status || null;
        var btn = opsi.tombol || null;
        var teksTombol = btn ? btn.textContent : "";
        var tanpaFallback = opsi.tanpaFallback === true;
        var sudahSelesai = false;

        function status(html, tipe) {
            if (!statusEl) { return; }
            statusEl.className = "kecil terdekat-status" + (tipe ? " status-" + tipe : "");
            statusEl.innerHTML = html;
        }

        function loading(aktif) {
            if (!btn) { return; }
            btn.disabled = aktif;
            btn.textContent = aktif ? "Mendeteksi lokasi..." : teksTombol;
        }

        function sukses(lat, lng, sumber) {
            if (sudahSelesai) { return; }
            sudahSelesai = true;
            loading(false);
            status("Lokasi ditemukan (" + sumber + ").", "sukses");
            if (typeof opsi.onSukses === "function") {
                opsi.onSukses(lat, lng, sumber);
            }
        }

        function gagal(err) {
            if (sudahSelesai) { return; }
            loading(false);
            var tombol = "";
            if (!tanpaFallback) {
                tombol = ' <button type="button" class="btn-kecil btn-titik-acuan" data-lat="' + PUSAT.lat +
                    '" data-lng="' + PUSAT.lng + '" data-label="' + PUSAT.nama + '">Gunakan pusat kota Jogja</button>';
            }
            status(alasan(err) + tombol, "error");
            if (typeof opsi.onGagal === "function") {
                opsi.onGagal(err);
            }
        }

        if (!konteksAman()) {
            status("Fitur lokasi browser hanya aktif pada <strong>localhost</strong> atau <strong>HTTPS</strong>. Memakai titik acuan Tugu Yogyakarta.", "peringatan");
            if (typeof opsi.onSukses === "function") {
                opsi.onSukses(PUSAT.lat, PUSAT.lng, "Acuan Tugu Yogyakarta");
            }
            return;
        }

        if (!punyaGeolokasi()) {
            status("Browser ini tidak mendukung Geolocation. Memakai titik acuan Tugu Yogyakarta.", "peringatan");
            if (typeof opsi.onSukses === "function") {
                opsi.onSukses(PUSAT.lat, PUSAT.lng, "Acuan Tugu Yogyakarta");
            }
            return;
        }

        loading(true);
        status("Meminta izin dan mendeteksi koordinat perangkat...", "info");

        /* Tahap 1: cepat, cocok untuk laptop/desktop yang memakai lokasi jaringan. */
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                sukses(pos.coords.latitude, pos.coords.longitude, "lokasi perangkat");
            },
            function (err) {
                /* Izin ditolak: tidak ada gunanya mencoba mode akurasi tinggi. */
                if (err && err.code === 1) {
                    gagal(err);
                    return;
                }
                status("Sinyal lemah, mencoba mode akurasi tinggi...", "info");
                /* Tahap 2: akurasi tinggi, sekali lagi. */
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        sukses(pos.coords.latitude, pos.coords.longitude, "akurasi tinggi");
                    },
                    gagal,
                    { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
                );
            },
            { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 }
        );
    }

    global.DolanLokasi = {
        cari: cari,
        pusat: PUSAT,
        konteksAman: konteksAman,
        alasan: alasan
    };
})(window);
