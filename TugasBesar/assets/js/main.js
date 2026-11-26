function toggleMenu() {
    document.getElementById("menu").classList.toggle("buka");
}

function konfirmasiHapus(nama) {
    return confirm("Yakin ingin menghapus " + nama + "?");
}

function isiKoordinat() {
    if (!navigator.geolocation) {
        alert("Browser tidak mendukung geolocation.");
        return;
    }
    navigator.geolocation.getCurrentPosition(function (pos) {
        document.getElementById("latitude").value = pos.coords.latitude.toFixed(7);
        document.getElementById("longitude").value = pos.coords.longitude.toFixed(7);
    }, function () {
        alert("Lokasi tidak bisa diambil. Isi koordinat secara manual.");
    });
}

function tampilSkeleton(idWadah, jumlah) {
    var wadah = document.getElementById(idWadah);
    if (!wadah) { return; }
    var html = "";
    for (var i = 0; i < jumlah; i++) {
        html += '<div class="sk-baris">' +
                '<div class="skeleton sk-foto"></div>' +
                '<div class="sk-teks">' +
                '<div class="skeleton sk-garis" style="width:55%"></div>' +
                '<div class="skeleton sk-garis" style="width:35%"></div>' +
                '</div></div>';
    }
    wadah.innerHTML = html;
    wadah.setAttribute("aria-busy", "true");
}

document.addEventListener("click", function (e) {
    var tombol = e.target.closest(".tombol");
    if (!tombol) { return; }
    var kotak = tombol.getBoundingClientRect();
    var ukuran = Math.max(kotak.width, kotak.height);
    var riak = document.createElement("span");
    riak.className = "riak";
    riak.style.width = ukuran + "px";
    riak.style.height = ukuran + "px";
    riak.style.left = (e.clientX - kotak.left - ukuran / 2) + "px";
    riak.style.top = (e.clientY - kotak.top - ukuran / 2) + "px";
    tombol.appendChild(riak);
    setTimeout(function () { riak.remove(); }, 650);
});

window.addEventListener("load", function () {

    var kartu = document.querySelectorAll(".kartu-klik");
    for (var i = 0; i < kartu.length; i++) {
        kartu[i].addEventListener("click", function (e) {
            if (e.target.closest("a, button")) { return; }
            var tautan = this.querySelector("a[href]");
            if (tautan) { window.location.href = tautan.href; }
        });
    }

    var foto = document.querySelectorAll(".foto-bingkai img");
    for (var j = 0; j < foto.length; j++) {
        (function (img) {
            if (img.complete) { return; }
            var bingkai = img.parentNode;
            bingkai.classList.add("memuat");
            var selesai = function () { bingkai.classList.remove("memuat"); };
            img.addEventListener("load", selesai);
            img.addEventListener("error", selesai);
        })(foto[j]);
    }

        // Hanya tampilkan skeleton jika halaman tidak memakai filter dinamis sisi klien
    if (!document.querySelector("a.chip[data-grup]")) {
        var form = document.querySelector(".form-cari");
        if (form) {
            form.addEventListener("submit", function () { tampilSkeleton("daftar-hasil", 4); });
        }
        var chip = document.querySelectorAll(".chip");
        for (var k = 0; k < chip.length; k++) {
            chip[k].addEventListener("click", function () { tampilSkeleton("daftar-hasil", 4); });
        }
    }

    var pesan = document.querySelectorAll(".sukses, .error");
    setTimeout(function () {
        for (var m = 0; m < pesan.length; m++) { pesan[m].style.display = "none"; }
    }, 5000);

        // Sinkronisasi label skor widget bintang pada halaman detail
    var labelSkor = document.getElementById("labelSkorBintang");
    var radioBintang = document.querySelectorAll('.bintang-pilih input[name="rating"]');
    if (labelSkor && radioBintang.length > 0) {
        var ketBintang = {
            "5": "5/5 \u00B7 Luar biasa",
            "4": "4/5 \u00B7 Sangat bagus",
            "3": "3/5 \u00B7 Cukup bagus",
            "2": "2/5 \u00B7 Kurang",
            "1": "1/5 \u00B7 Mengecewakan"
        };
        for (var b = 0; b < radioBintang.length; b++) {
            radioBintang[b].addEventListener("change", function () {
                if (ketBintang[this.value]) {
                    labelSkor.textContent = ketBintang[this.value];
                }
            });
        }
    }

    // Filter & urutkan ulasan tanpa reload halaman (fetch + DOMParser)
    var wadahUlasan = document.getElementById("wadah-ulasan");
    if (wadahUlasan && window.fetch && window.DOMParser) {
        wadahUlasan.addEventListener("click", function (e) {
            var link = e.target.closest ? e.target.closest(".ulasan-filter a.chip") : null;
            if (!link) { return; }
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
            e.preventDefault();
            wadahUlasan.style.opacity = "0.6";
            fetch(link.href)
                .then(function (res) { return res.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, "text/html");
                    var baru = doc.getElementById("wadah-ulasan");
                    if (baru) {
                        wadahUlasan.innerHTML = baru.innerHTML;
                        try { history.replaceState(null, "", link.href); } catch (err) {}
                    }
                    wadahUlasan.style.opacity = "1";
                })
                .catch(function () {
                    window.location.href = link.href;
                });
        });
    }
});

window.addEventListener("pageshow", function (e) {
    if (e.persisted) { window.location.reload(); }
});


/* ---------- Toggle AJAX Favorit & Rencana Dolan ---------- */
(function () {
    "use strict";
    document.addEventListener("click", function (e) {
        var btnFav = e.target.closest ? e.target.closest(".btn-favorit") : null;
        var btnRcn = e.target.closest ? e.target.closest(".btn-rencana-toggle") : null;
        var btn = btnFav || btnRcn;
        if (!btn) { return; }

        var endpoint = btn.getAttribute("data-endpoint");
        var id = btn.getAttribute("data-id");
        var csrf = btn.getAttribute("data-csrf");
        if (!endpoint || !id || !csrf) { return; }

        btn.disabled = true;
        var body = new URLSearchParams();
        body.append("destination_id", id);
        body.append("csrf_token", csrf);

        fetch(endpoint, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                if (!res || !res.ok) { return; }

                if (btnFav) {
                    var aktif = res.status === "added";
                    btn.classList.toggle("aktif", aktif);
                    var ikon = btn.querySelector(".ikon-hati");
                    var teks = btn.querySelector(".teks-fav");
                    var hitung = btn.querySelector(".hitung-fav");
                    if (ikon) { ikon.innerHTML = aktif ? "&#9829;" : "&#9825;"; }
                    if (teks) { teks.textContent = aktif ? "Difavoritkan" : "Favorit"; }
                    if (hitung) { hitung.textContent = res.total_favorit; }
                }

                if (btnRcn) {
                    var tersimpan = res.status === "added";
                    btn.classList.toggle("tombol-garis", tersimpan);
                    btn.innerHTML = tersimpan ? "&#10003; Tersimpan di Rencana" : "+ Rencana Dolan";
                    var badge = document.getElementById("badge-rencana");
                    if (badge) {
                        badge.textContent = res.total_rencana;
                        badge.classList.toggle("sembunyi", res.total_rencana <= 0);
                    }
                }
            })
            .catch(function () { btn.disabled = false; });
    });
})();

/* ---------- Hero Single-Slide Carousel + Auto-Slide ---------- */
(function () {
    "use strict";
    var showcase = document.getElementById("heroShowcase");
    var track = document.getElementById("heroTrack");
    var btnPrev = document.getElementById("heroPrev");
    var btnNext = document.getElementById("heroNext");
    var dots = document.querySelectorAll(".hero-dot");
    if (!track || !showcase) { return; }

    var slides = track.querySelectorAll(".hero-slide");
    var total = slides.length;
    var indexAktif = 0;
    var timer = null;
    var isDown = false;
    var startX = 0;
    var scrollLeft = 0;

    function setDot(idx) {
        indexAktif = idx;
        Array.prototype.forEach.call(dots, function (d, i) {
            d.classList.toggle("aktif", i === idx);
        });
    }

    function keSlide(idx) {
        if (total === 0) { return; }
        var target = ((idx % total) + total) % total;
        track.scrollTo({ left: target * track.clientWidth, behavior: "smooth" });
        setDot(target);
    }

    function mulaiAuto() {
        hentiAuto();
        if (total <= 1 || document.hidden) { return; }
        timer = setInterval(function () { keSlide(indexAktif + 1); }, 5000);
    }
    function hentiAuto() {
        if (timer) { clearInterval(timer); timer = null; }
    }

    track.addEventListener("scroll", function () {
        if (track.clientWidth > 0) {
            var idx = Math.round(track.scrollLeft / track.clientWidth);
            if (idx !== indexAktif && idx >= 0 && idx < total) { setDot(idx); }
        }
    });

    Array.prototype.forEach.call(dots, function (d) {
        d.addEventListener("click", function () {
            keSlide(parseInt(d.getAttribute("data-slide"), 10) || 0);
        });
    });

    if (btnPrev) { btnPrev.addEventListener("click", function () { keSlide(indexAktif - 1); }); }
    if (btnNext) { btnNext.addEventListener("click", function () { keSlide(indexAktif + 1); }); }

    track.addEventListener("mousedown", function (e) {
        isDown = true;
        track.classList.add("sedang-geser");
        startX = e.pageX - track.offsetLeft;
        scrollLeft = track.scrollLeft;
        hentiAuto();
    });
    window.addEventListener("mouseup", function () {
        if (!isDown) { return; }
        isDown = false;
        track.classList.remove("sedang-geser");
        mulaiAuto();
    });
    track.addEventListener("mousemove", function (e) {
        if (!isDown) { return; }
        e.preventDefault();
        var walk = (e.pageX - track.offsetLeft - startX) * 1.3;
        track.scrollLeft = scrollLeft - walk;
    });

    showcase.addEventListener("mouseenter", hentiAuto);
    showcase.addEventListener("mouseleave", mulaiAuto);
    showcase.addEventListener("touchstart", hentiAuto, { passive: true });
    showcase.addEventListener("touchend", mulaiAuto);
    document.addEventListener("visibilitychange", function () {
        if (document.hidden) { hentiAuto(); } else { mulaiAuto(); }
    });

    mulaiAuto();
})();

/* ---------- Autocomplete Dropdown Form Cari Hero ---------- */
(function () {
    "use strict";
    var input = document.getElementById("cari-input");
    var box = document.getElementById("cari-saran");
    var rows = document.querySelectorAll("#daftar-hasil .baris[data-teks]");
    if (!input || !box || rows.length === 0) { return; }

    var data = [];
    Array.prototype.forEach.call(rows, function (r) {
        var judul = r.querySelector("h3");
        var meta = r.querySelector(".baris-meta");
        var harga = r.querySelector(".baris-harga");
        var link = r.querySelector("a[href]");
        if (judul && link) {
            data.push({
                nama: judul.textContent.trim(),
                meta: meta ? meta.textContent.trim() : "",
                harga: harga ? harga.textContent.trim() : "",
                url: link.getAttribute("href"),
                teks: r.getAttribute("data-teks") || ""
            });
        }
    });

    function esc(s) {
        return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    input.addEventListener("input", function () {
        var q = input.value.trim().toLowerCase();
        if (q.length < 2) {
            box.classList.add("sembunyi");
            box.innerHTML = "";
            return;
        }
        var cocok = data.filter(function (d) { return d.teks.indexOf(q) !== -1; }).slice(0, 5);
        if (cocok.length === 0) {
            box.classList.add("sembunyi");
            box.innerHTML = "";
            return;
        }
        box.innerHTML = cocok.map(function (d) {
            return '<a class="cari-saran-item" href="' + esc(d.url) + '">' +
                '<div><strong>' + esc(d.nama) + '</strong><span>' + esc(d.meta) + '</span></div>' +
                '<span>' + esc(d.harga) + '</span>' +
            '</a>';
        }).join("");
        box.classList.remove("sembunyi");
    });

    document.addEventListener("click", function (e) {
        if (!box.contains(e.target) && e.target !== input) {
            box.classList.add("sembunyi");
        }
    });
})();
