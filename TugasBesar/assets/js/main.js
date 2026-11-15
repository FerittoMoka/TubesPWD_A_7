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

    var form = document.querySelector(".form-cari");
    if (form) {
        form.addEventListener("submit", function () { tampilSkeleton("daftar-hasil", 4); });
    }
    var chip = document.querySelectorAll(".chip");
    for (var k = 0; k < chip.length; k++) {
        chip[k].addEventListener("click", function () { tampilSkeleton("daftar-hasil", 4); });
    }

    var pesan = document.querySelectorAll(".sukses, .error");
    setTimeout(function () {
        for (var m = 0; m < pesan.length; m++) { pesan[m].style.display = "none"; }
    }, 5000);
});

window.addEventListener("pageshow", function (e) {
    if (e.persisted) { window.location.reload(); }
});
