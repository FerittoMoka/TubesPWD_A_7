/* Dolan Jogja - JavaScript frontend (tanpa library) */
(function () {
    "use strict";

    function $(id) { return document.getElementById(id); }

    function setStatus(el, teks, kelas) {
        if (!el) { return; }
        el.textContent = teks;
        el.className = "status" + (kelas ? " " + kelas : "");
    }

    function ambilJSON(url, sukses, gagal) {
        var x = new XMLHttpRequest();
        x.onreadystatechange = function () {
            if (x.readyState !== 4) { return; }
            if (x.status === 200) {
                try { sukses(JSON.parse(x.responseText)); } catch (err) { if (gagal) { gagal(); } }
            } else if (gagal) { gagal(); }
        };
        x.open("GET", url, true);
        x.send(null);
    }

    /* ---------- Konfirmasi aksi hapus (dipakai di atribut onsubmit) ---------- */
    window.konfirmasi = function (pesan) { return window.confirm(pesan); };

    /* ---------- Menu navigasi (mobile) ---------- */
    var tombolMenu = $("tombol-menu"), menu = $("menu");
    if (tombolMenu && menu) {
        tombolMenu.onclick = function () {
            var buka = menu.className.indexOf("buka") === -1;
            menu.className = buka ? "menu buka" : "menu";
        };
    }

    /* ---------- Registrasi: cek username/email + validasi password ---------- */
    var formReg = $("form-register");
    if (formReg) {
        var apiCek = $("api-cek").value;
        var timer = {};

        var cekField = function (nama, formatOk, pesanFormat) {
            var input = $(nama), status = $("status-" + nama);
            var nilai = input.value.replace(/^\s+|\s+$/g, "");
            input.setAttribute("data-valid", "");
            if (nilai === "") { setStatus(status, "", ""); return; }
            if (!formatOk(nilai)) { setStatus(status, pesanFormat, "bad"); return; }
            setStatus(status, "Memeriksa...", "");
            ambilJSON(apiCek + "?" + nama + "=" + encodeURIComponent(nilai), function (res) {
                if (input.value.replace(/^\s+|\s+$/g, "") !== nilai) { return; } /* input sudah berubah */
                if (res[nama] === "tersedia") { setStatus(status, "Tersedia", "ok"); input.setAttribute("data-valid", "1"); }
                else if (res[nama] === "dipakai") { setStatus(status, nama === "email" ? "Email sudah terdaftar." : "Username sudah dipakai.", "bad"); }
                else { setStatus(status, pesanFormat, "bad"); }
            }, function () { setStatus(status, "Tidak dapat memeriksa saat ini.", ""); });
        };

        var jadwalkan = function (nama, formatOk, pesan) {
            var el = $(nama);
            var jalankan = function () { cekField(nama, formatOk, pesan); };
            el.onkeyup = function () { clearTimeout(timer[nama]); timer[nama] = setTimeout(jalankan, 400); };
            el.onblur = jalankan;
        };
        jadwalkan("username", function (v) { return /^[A-Za-z0-9_.]{3,30}$/.test(v); }, "Username 3-30 karakter: huruf, angka, titik, atau underscore.");
        jadwalkan("email", function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }, "Format email tidak valid.");

        var cekPassword = function () {
            var p1 = $("password").value, p2 = $("password2").value, st = $("status-password");
            if (p2 === "") { setStatus(st, "", ""); return; }
            if (p1 !== p2) { setStatus(st, "Konfirmasi password belum sama.", "bad"); }
            else { setStatus(st, "Password cocok.", "ok"); }
        };
        $("password").onkeyup = cekPassword;
        $("password2").onkeyup = cekPassword;

        window.validasiRegister = function () {
            var pesan = [];
            if ($("nama").value.replace(/^\s+|\s+$/g, "") === "") { pesan.push("Nama lengkap wajib diisi."); }
            if ($("status-username").className.indexOf("bad") !== -1 || $("username").value === "") { pesan.push("Periksa kembali username Anda."); }
            if ($("status-email").className.indexOf("bad") !== -1 || $("email").value === "") { pesan.push("Periksa kembali email Anda."); }
            if ($("password").value.length < 8) { pesan.push("Password minimal 8 karakter."); }
            if ($("password").value !== $("password2").value) { pesan.push("Konfirmasi password tidak sama."); }
            var box = $("pesan-form");
            if (pesan.length) {
                box.className = "alert alert-error";
                box.textContent = pesan.join(" ");
                return false;
            }
            return true;
        };
    }

    /* ---------- Pratinjau & validasi foto (profil / destinasi) ---------- */
    var inputFoto = $("input-foto");
    if (inputFoto) {
        inputFoto.onchange = function () {
            var prev = $("preview-foto"), st = $("status-foto");
            var f = inputFoto.files && inputFoto.files[0];
            if (!f) { prev.className = "preview hidden"; setStatus(st, "", ""); return; }
            if (!/^image\/(jpeg|png|webp)$/.test(f.type)) {
                inputFoto.value = ""; prev.className = "preview hidden";
                setStatus(st, "Format harus JPG, PNG, atau WEBP.", "bad"); return;
            }
            if (f.size > 2 * 1024 * 1024) {
                inputFoto.value = ""; prev.className = "preview hidden";
                setStatus(st, "Ukuran foto maksimal 2 MB.", "bad"); return;
            }
            prev.src = window.URL.createObjectURL(f);
            prev.className = "preview";
            setStatus(st, f.name, "ok");
        };
    }

    /* ---------- Geolocation ---------- */
    function pesanGeo(err) {
        if (err && err.code === 1) { return "Izin lokasi ditolak. Aktifkan izin lokasi pada browser."; }
        if (err && err.code === 3) { return "Waktu pencarian lokasi habis. Coba lagi."; }
        return "Lokasi tidak dapat ditentukan.";
    }
    var opsiGeo = { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 };

    /* (a) Beranda: destinasi terdekat */
    var btnDekat = $("btn-terdekat");
    if (btnDekat) {
        btnDekat.onclick = function () {
            var status = $("status-terdekat"), hasil = $("hasil-terdekat");
            if (!navigator.geolocation) { setStatus(status, "Browser tidak mendukung geolocation.", "bad"); return false; }
            setStatus(status, "Mencari lokasimu...", "");
            tampilSkeleton("hasil-terdekat", 3);
            var apiUrl = btnDekat.getAttribute("href");
            navigator.geolocation.getCurrentPosition(function (pos) {
                var q = "?lat=" + pos.coords.latitude + "&lng=" + pos.coords.longitude;
                ambilJSON(apiUrl + q, function (data) {
                    hasil.innerHTML = "";
                    var base = apiUrl.replace("api/terdekat.php", "");
                    for (var i = 0; i < data.length; i++) { hasil.appendChild(buatBaris(data[i], base)); }
                    setStatus(status, "Menampilkan " + data.length + " destinasi terdekat dari lokasimu.", "ok");
                }, function () { hasil.innerHTML = ""; setStatus(status, "Gagal mengambil data destinasi.", "bad"); });
            }, function (err) { hasil.innerHTML = ""; setStatus(status, pesanGeo(err), "bad"); }, opsiGeo);
            return false;
        };
    }

    function el(tag, kelas, teks) {
        var n = document.createElement(tag);
        if (kelas) { n.className = kelas; }
        if (teks !== undefined) { n.appendChild(document.createTextNode(teks)); }
        return n;
    }
    function buatBaris(d, base) {
        var slug = d.kategori.toLowerCase().replace(/[^a-z0-9]/g, "");
        var baris = el("div", "baris kartu-klik");
        var bingkai = el("div", "foto-bingkai");
        var ph = el("div", "ph ph-" + slug);
        ph.appendChild(el("span", "", d.nama.charAt(0).toUpperCase()));
        bingkai.appendChild(ph);
        var info = el("div", "baris-info");
        info.appendChild(el("h3", "", d.nama));
        info.appendChild(el("p", "baris-meta", d.kategori + " \u00B7 " + d.lokasi));
        var rating = el("span", "rating");
        if (d.rating !== null) {
            rating.appendChild(el("span", "bintang"));
            rating.appendChild(document.createTextNode(d.rating.toFixed(1) + " "));
        } else { rating.className = "rating redup"; rating.appendChild(document.createTextNode("Belum ada ulasan ")); }
        rating.appendChild(el("span", "jarak-teks", "\u00B7 " + d.jarak + " km dari kamu"));
        info.appendChild(rating);
        var harga = el("div", "baris-harga", d.harga);
        var a = el("a", "tombol tombol-garis", "Detail");
        a.setAttribute("href", base + "destinasi/detail.php?id=" + d.id);
        baris.appendChild(bingkai); baris.appendChild(info); baris.appendChild(harga); baris.appendChild(a);
        return baris;
    }

    /* (b) Detail destinasi: jarak garis lurus dari lokasi pengguna (Haversine) */
    var btnJarak = $("btn-jarak");
    if (btnJarak) {
        btnJarak.onclick = function () {
            var status = $("hasil-jarak");
            if (!navigator.geolocation) { setStatus(status, "Browser tidak mendukung geolocation.", "bad"); return; }
            setStatus(status, "Mencari lokasimu...", "");
            var lat2 = parseFloat($("dest-lat").textContent), lng2 = parseFloat($("dest-lng").textContent);
            navigator.geolocation.getCurrentPosition(function (pos) {
                var r = Math.PI / 180, R = 6371;
                var dLat = (lat2 - pos.coords.latitude) * r, dLng = (lng2 - pos.coords.longitude) * r;
                var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                        Math.cos(pos.coords.latitude * r) * Math.cos(lat2 * r) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
                var km = 2 * R * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                setStatus(status, "Sekitar " + km.toFixed(1) + " km dari lokasimu (garis lurus).", "ok");
            }, function (err) { setStatus(status, pesanGeo(err), "bad"); }, opsiGeo);
        };
    }

    /* ---------- Skeleton shimmer ---------- */
    function tampilSkeleton(idWadah, jumlah) {
        var wadah = $(idWadah);
        if (!wadah) { return; }
        var html = "";
        for (var i = 0; i < jumlah; i++) {
            html += '<div class="sk-baris"><div class="skeleton sk-foto"></div><div class="sk-teks">' +
                    '<div class="skeleton sk-garis" style="width:55%"></div>' +
                    '<div class="skeleton sk-garis" style="width:35%"></div></div></div>';
        }
        wadah.innerHTML = html;
        wadah.setAttribute("aria-busy", "true");
    }

    /* ---------- Efek riak pada tombol ---------- */
    document.addEventListener("click", function (ev) {
        var tombol = ev.target.closest ? ev.target.closest(".tombol") : null;
        if (!tombol) { return; }
        var kotak = tombol.getBoundingClientRect();
        var ukuran = Math.max(kotak.width, kotak.height);
        var riak = document.createElement("span");
        riak.className = "riak";
        riak.style.width = ukuran + "px";
        riak.style.height = ukuran + "px";
        riak.style.left = (ev.clientX - kotak.left - ukuran / 2) + "px";
        riak.style.top = (ev.clientY - kotak.top - ukuran / 2) + "px";
        tombol.appendChild(riak);
        setTimeout(function () { if (riak.parentNode) { riak.parentNode.removeChild(riak); } }, 650);
    });

    window.addEventListener("load", function () {
        /* Kartu yang bisa diklik seluruh area-nya */
        document.addEventListener("click", function (ev) {
            var kartu = ev.target.closest ? ev.target.closest(".kartu-klik") : null;
            if (!kartu || ev.target.closest("a, button")) { return; }
            var tautan = kartu.querySelector("a[href]");
            if (tautan) { window.location.href = tautan.href; }
        });

        /* Shimmer saat foto masih dimuat */
        var foto = document.querySelectorAll(".foto-bingkai img");
        for (var j = 0; j < foto.length; j++) {
            (function (img) {
                if (img.complete) { return; }
                var bingkai = img.parentNode;
                bingkai.className += " memuat";
                var selesai = function () { bingkai.className = bingkai.className.replace(" memuat", ""); };
                img.addEventListener("load", selesai);
                img.addEventListener("error", selesai);
            })(foto[j]);
        }

        /* Skeleton saat mencari / memilih kategori di beranda */
        var form = document.querySelector(".form-cari");
        if (form) { form.addEventListener("submit", function () { tampilSkeleton("daftar-hasil", 4); }); }
        var chip = document.querySelectorAll(".chip");
        for (var k = 0; k < chip.length; k++) {
            chip[k].addEventListener("click", function () { tampilSkeleton("daftar-hasil", 4); });
        }

        /* Pesan sukses/error hilang otomatis setelah 5 detik */
        var pesan = document.querySelectorAll(".alert");
        setTimeout(function () {
            for (var m = 0; m < pesan.length; m++) { if (pesan[m].id !== "pesan-form") { pesan[m].style.display = "none"; } }
        }, 5000);
    });

    window.addEventListener("pageshow", function (ev) { if (ev.persisted) { window.location.reload(); } });
})();
