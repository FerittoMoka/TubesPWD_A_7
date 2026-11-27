/*
 * Dolan Jogja - Kartu perbandingan 2 destinasi.
 *
 * Tombol "Bandingkan" pada setiap kartu destinasi beranda menyimpan pilihan
 * (maksimal 2) ke localStorage. Panel perbandingan menampilkan foto utama,
 * kategori, wilayah, harga tiket, jam buka, rating, dan jarak dari lokasi
 * pengunjung (dihitung saat tombol lokasi di panel ditekan).
 */
(function () {
    "use strict";

    var KUNCI = "dolan_banding";
    var kartu = document.querySelectorAll("#daftar-hasil .baris[data-b-id]");
    var tray = document.getElementById("bandingTray");
    var wadahPilih = document.getElementById("bandingPilih");
    var info = document.getElementById("bandingInfo");
    var btnBuka = document.getElementById("bandingBuka");
    var btnBersih = document.getElementById("bandingBersih");
    var panel = document.getElementById("bandingPanel");
    var isi = document.getElementById("bandingIsi");
    var btnTutup = document.getElementById("bandingTutup");
    var btnLokasi = document.getElementById("bandingLokasi");
    var statusJarak = document.getElementById("bandingJarakStatus");
    if (!tray || !wadahPilih || !isi || kartu.length === 0) { return; }

    var data = {};
    Array.prototype.forEach.call(kartu, function (k) {
        var id = String(k.getAttribute("data-b-id") || "");
        if (!id) { return; }
        data[id] = {
            id: id,
            nama: k.getAttribute("data-b-nama") || "",
            kategori: k.getAttribute("data-b-kategori") || "",
            wilayah: k.getAttribute("data-b-wilayah") || "",
            harga: k.getAttribute("data-b-harga") || "",
            jam: k.getAttribute("data-b-jam") || "",
            rating: k.getAttribute("data-b-rating") || "",
            ulasan: k.getAttribute("data-b-ulasan") || "0",
            foto: k.getAttribute("data-b-foto") || "",
            lat: k.getAttribute("data-b-lat") || "",
            lng: k.getAttribute("data-b-lng") || "",
            url: k.getAttribute("data-b-url") || ""
        };
    });

    var terpilih = [];
    try {
        var simpanan = JSON.parse(window.localStorage.getItem(KUNCI) || "[]");
        if (Array.isArray(simpanan)) {
            terpilih = simpanan.map(String).filter(function (id) {
                return data.hasOwnProperty(id);
            }).slice(0, 2);
        }
    } catch (e) { terpilih = []; }

    function esc(s) {
        return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function simpan() {
        try { window.localStorage.setItem(KUNCI, JSON.stringify(terpilih)); } catch (e) {}
    }

    function inisial(teks) {
        var t = String(teks || "?").replace(/^\s+/, "");
        return t ? t.charAt(0).toUpperCase() : "?";
    }

    function sinkronTombol() {
        var tombol = document.querySelectorAll(".btn-banding[data-banding]");
        Array.prototype.forEach.call(tombol, function (b) {
            var aktif = terpilih.indexOf(String(b.getAttribute("data-banding"))) !== -1;
            b.className = aktif ? "btn-banding aktif" : "btn-banding";
            b.setAttribute("aria-pressed", aktif ? "true" : "false");
            b.innerHTML = aktif ? "&#10003; Dipilih" : "Bandingkan";
        });
    }

    function renderTray() {
        if (terpilih.length === 0) {
            tray.className = "banding-tray sembunyi";
            wadahPilih.innerHTML = "";
            return;
        }
        tray.className = "banding-tray";

        wadahPilih.innerHTML = terpilih.map(function (id) {
            var d = data[id];
            return '<span class="banding-chip">' + esc(d.nama) +
                ' <button type="button" class="banding-hapus" data-hapus="' + esc(id) +
                '" title="Keluarkan dari perbandingan" aria-label="Keluarkan ' + esc(d.nama) + '">&times;</button></span>';
        }).join("");

        if (info) {
            info.textContent = terpilih.length < 2
                ? "Pilih 1 destinasi lagi untuk dibandingkan."
                : "2 destinasi siap dibandingkan.";
        }
        if (btnBuka) { btnBuka.disabled = terpilih.length < 2; }
        sinkronTombol();
    }

    function barisFakta(label, nilai, atribut) {
        return '<div class="banding-fakta"><span>' + esc(label) + "</span><strong" +
            (atribut ? " " + atribut : "") + ">" + esc(nilai) + "</strong></div>";
    }

    function renderPanel() {
        isi.innerHTML = terpilih.map(function (id) {
            var d = data[id];
            var foto = d.foto !== ""
                ? '<img src="' + esc(d.foto) + '" alt="Foto ' + esc(d.nama) + '" width="300" height="200" />'
                : '<div class="banding-foto-kosong"><span>' + esc(inisial(d.nama)) + "</span></div>";
            var rating = d.rating !== "" ? d.rating + " / 5 (" + d.ulasan + " ulasan)" : "Belum ada ulasan";
            return '<div class="banding-kolom">' +
                foto +
                '<h3><a href="' + esc(d.url) + '">' + esc(d.nama) + "</a></h3>" +
                barisFakta("Kategori", d.kategori) +
                barisFakta("Wilayah", d.wilayah) +
                barisFakta("Harga tiket", d.harga) +
                barisFakta("Jam buka", d.jam || "Tidak tersedia") +
                barisFakta("Rating ulasan", rating) +
                barisFakta("Jarak dari lokasi", "Belum dihitung", 'data-jarak="' + esc(id) + '"') +
                "</div>";
        }).join("");
    }

    function bukaPanel() {
        if (terpilih.length < 2 || !panel) { return; }
        renderPanel();
        if (statusJarak) { statusJarak.textContent = ""; }
        panel.className = "banding-panel buka";
        panel.setAttribute("aria-hidden", "false");
        if (document.body.className.indexOf("tanpa-scroll") === -1) {
            document.body.className = (document.body.className + " tanpa-scroll").replace(/^\s+/, "");
        }
        if (btnTutup) { btnTutup.focus(); }
    }

    function tutupPanel() {
        if (!panel) { return; }
        panel.className = "banding-panel";
        panel.setAttribute("aria-hidden", "true");
        document.body.className = document.body.className.replace(/\s*tanpa-scroll/, "");
        if (btnBuka) { btnBuka.focus(); }
    }

    function tambah(id) {
        id = String(id);
        if (!data.hasOwnProperty(id)) { return; }
        var posisi = terpilih.indexOf(id);
        if (posisi !== -1) {
            terpilih.splice(posisi, 1);
        } else {
            if (terpilih.length >= 2) { terpilih.shift(); }
            terpilih.push(id);
        }
        simpan();
        renderTray();
    }

    document.addEventListener("click", function (e) {
        var tambahBtn = e.target.closest ? e.target.closest(".btn-banding[data-banding]") : null;
        if (tambahBtn) {
            e.preventDefault();
            tambah(tambahBtn.getAttribute("data-banding"));
            return;
        }
        var hapusBtn = e.target.closest ? e.target.closest(".banding-hapus[data-hapus]") : null;
        if (hapusBtn) {
            e.preventDefault();
            tambah(hapusBtn.getAttribute("data-hapus"));
        }
    });

    if (btnBuka) { btnBuka.addEventListener("click", bukaPanel); }
    if (btnBersih) {
        btnBersih.addEventListener("click", function () {
            terpilih = [];
            simpan();
            renderTray();
        });
    }
    if (btnTutup) { btnTutup.addEventListener("click", tutupPanel); }
    if (panel) {
        panel.addEventListener("click", function (e) { if (e.target === panel) { tutupPanel(); } });
    }
    document.addEventListener("keydown", function (e) {
        if ((e.key === "Escape" || e.keyCode === 27) && panel && panel.className.indexOf("buka") !== -1) {
            tutupPanel();
        }
    });

    /* Haversine (km), konsisten dengan includes/fungsi.php:jarak_km(). */
    function jarakKm(lat1, lng1, lat2, lng2) {
        var r = 6371;
        var rad = Math.PI / 180;
        var dLat = (lat2 - lat1) * rad;
        var dLng = (lng2 - lng1) * rad;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        a = Math.min(1, Math.max(0, a));
        return r * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    if (btnLokasi) {
        btnLokasi.addEventListener("click", function () {
            if (!navigator.geolocation) {
                if (statusJarak) { statusJarak.textContent = "Browser ini tidak mendukung geolokasi."; }
                return;
            }
            btnLokasi.disabled = true;
            if (statusJarak) { statusJarak.textContent = "Mendeteksi lokasi perangkat..."; }

            navigator.geolocation.getCurrentPosition(function (pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                var sel = isi.querySelectorAll("[data-jarak]");
                Array.prototype.forEach.call(sel, function (el) {
                    var d = data[el.getAttribute("data-jarak")];
                    if (!d || d.lat === "" || d.lng === "") {
                        el.textContent = "Koordinat belum tersedia";
                        return;
                    }
                    var km = jarakKm(lat, lng, parseFloat(d.lat), parseFloat(d.lng));
                    el.textContent = "± " + km.toLocaleString("id-ID", { maximumFractionDigits: 1 }) + " km";
                });
                if (statusJarak) { statusJarak.textContent = "Jarak dihitung dari lokasi perangkat Anda."; }
                btnLokasi.disabled = false;
            }, function (err) {
                var kode = err && err.code ? err.code : 0;
                if (statusJarak) {
                    statusJarak.textContent = kode === 1
                        ? "Izin lokasi diblokir browser, jarak tidak dapat dihitung."
                        : "Lokasi perangkat tidak tersedia, jarak tidak dapat dihitung.";
                }
                btnLokasi.disabled = false;
            }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
        });
    }

    renderTray();
})();
