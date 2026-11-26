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
