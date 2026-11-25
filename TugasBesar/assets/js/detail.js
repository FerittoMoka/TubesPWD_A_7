// Gambar SVG inline (data URI) supaya tidak perlu file .jpg terpisah
function svgGambar(isiSvg) {
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400">' + isiSvg + '</svg>';
    return "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(svg);
}

var dataPilihan = {
    "pilihan-1": {
        kategori: "Budaya",
        judul: "Candi Prambanan",
        meta: "Kranggan, Prambanan, Sleman \u2022 \u2605 5.0 (3) \u2022 Rp 10.000 - 20.000",
        gambar: svgGambar(
            '<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1">' +
            '<stop offset="0" stop-color="#6c5b8b"/><stop offset="0.55" stop-color="#b97b7a"/>' +
            '<stop offset="1" stop-color="#e3a678"/></linearGradient></defs>' +
            '<rect width="600" height="400" fill="url(#g)"/>' +
            '<polygon points="300,40 282,95 318,95" fill="#2e211c"/>' +
            '<rect x="274" y="95" width="52" height="40" fill="#362721"/>' +
            '<rect x="258" y="135" width="84" height="50" fill="#2b1e19"/>' +
            '<rect x="238" y="185" width="124" height="60" fill="#35251f"/>' +
            '<rect x="215" y="245" width="170" height="85" fill="#261a15"/>' +
            '<rect x="190" y="330" width="220" height="70" fill="#1e1410"/>'
        ),
        deskripsi: "Candi Prambanan adalah kompleks candi Hindu yang berada di perbatasan Sleman dan Klaten. " +
            "Candi ini terkenal dengan menara-menaranya yang menjulang dan relief yang menceritakan kisah Ramayana. " +
            "Waktu yang populer untuk berkunjung adalah sore hari menjelang senja.",
        poin: [
            "Kategori: Budaya",
            "Lokasi: Jl. Raya Solo - Yogyakarta, Kranggan, Prambanan, Sleman",
            "Harga tiket: Rp 10.000 - 20.000",
            "Rating pengunjung: 5.0 dari 3 ulasan"
        ]
    },
    "pilihan-2": {
        kategori: "Viral",
        judul: "Tumpeng Menoreh",
        meta: "Samigaluh, Kulon Progo \u2022 \u2605 5.0 (1) \u2022 Rp 50.000",
        gambar: svgGambar(
            '<rect width="600" height="400" fill="#e69168"/>' +
            '<polygon points="0,290 220,150 450,270 600,190 600,400 0,400" fill="#3d525c"/>' +
            '<polygon points="180,285 300,185 420,285" fill="#f6efe6"/>' +
            '<rect x="210" y="285" width="180" height="55" fill="#d9cab8"/>'
        ),
        deskripsi: "Tumpeng Menoreh adalah destinasi viral di perbukitan Menoreh, Kulon Progo, " +
            "yang ramai dikunjungi karena pemandangan dan spot fotonya. " +
            "Cocok untuk pengunjung yang suka berfoto dan menikmati suasana pegunungan.",
        poin: [
            "Kategori: Viral",
            "Lokasi: Sumbersari, Ngargoretno, Samigaluh, Kulon Progo",
            "Harga: Rp 50.000",
            "Rating pengunjung: 5.0 dari 1 ulasan"
        ]
    },
    "pilihan-3": {
        kategori: "Belanja",
        judul: "Jalan Malioboro",
        meta: "Gedong Tengen, Kota Yogyakarta \u2022 \u2605 4.5 (2) \u2022 Gratis",
        gambar: svgGambar(
            '<rect width="600" height="400" fill="#2d3b28"/>' +
            '<rect y="290" width="600" height="110" fill="#3b3632"/>' +
            '<circle cx="170" cy="150" r="90" fill="#5b7a3d"/>' +
            '<circle cx="430" cy="140" r="100" fill="#4f6d34"/>' +
            '<rect x="288" y="120" width="24" height="170" fill="#1e241b"/>' +
            '<circle cx="300" cy="110" r="28" fill="#ffdf78"/>' +
            '<rect x="90" y="240" width="110" height="60" fill="#c94b38"/>'
        ),
        deskripsi: "Jalan Malioboro adalah pusat belanja dan wisata kota di Yogyakarta. " +
            "Di sepanjang jalan ini banyak pedagang oleh-oleh, batik, dan kuliner khas. " +
            "Tidak ada tiket masuk, sehingga cocok untuk jalan santai kapan saja.",
        poin: [
            "Kategori: Belanja",
            "Lokasi: Jl. Malioboro, Sosromenduran, Gedong Tengen, Kota Yogyakarta",
            "Harga: Gratis (tanpa tiket masuk)",
            "Rating pengunjung: 4.5 dari 2 ulasan"
        ]
    }
};

var params = new URLSearchParams(window.location.search);
var itemKey = params.get("item") || "pilihan-1";
var item = dataPilihan[itemKey];

if (item) {
    document.title = item.judul + " - Dolan Jogja";
    document.getElementById("crumb-cat").textContent = item.kategori;
    document.getElementById("crumb-title").textContent = item.judul;
    document.getElementById("detail-category").textContent = item.kategori;
    document.getElementById("detail-title").textContent = item.judul;
    document.getElementById("detail-meta").textContent = item.meta;
    document.getElementById("detail-img").src = item.gambar;
    document.getElementById("detail-img").alt = item.judul;
    document.getElementById("detail-desc").textContent = item.deskripsi;

    var listEl = document.getElementById("detail-list");
    listEl.innerHTML = "";
    item.poin.forEach(function (teks) {
        var li = document.createElement("li");
        li.textContent = teks;
        listEl.appendChild(li);
    });
} else {
    document.getElementById("detail-title").textContent = "Data tidak ditemukan";
    document.getElementById("detail-desc").textContent =
        "Silakan kembali ke beranda dan pilih item yang tersedia.";
}

var btn = document.getElementById("btn-action");

function renderButton() {
    var saved = Dolan.isFav(itemKey);
    btn.textContent = saved ? "\u2713 Tersimpan (klik untuk hapus)" : "Pilih / Simpan";
    btn.className = saved ? "btn-primary saved" : "btn-primary";
}

btn.addEventListener("click", function () {
    if (!item) {
        Dolan.toast("Item tidak ditemukan.", "error");
        return;
    }
    var nowSaved = Dolan.toggleFav(itemKey);
    renderButton();
    Dolan.toast(nowSaved ? '"' + item.judul + '" disimpan ke favorit.'
                         : '"' + item.judul + '" dihapus dari favorit.',
                nowSaved ? "success" : "info");
});
renderButton();

// Pilihan terkait: utamakan kategori yang sama, maksimal 3
(function () {
    var grid = document.getElementById("related-grid");
    var sama = [], lain = [];
    Object.keys(dataPilihan).forEach(function (k) {
        if (k === itemKey) return;
        (item && dataPilihan[k].kategori === item.kategori ? sama : lain).push(k);
    });
    sama.concat(lain).slice(0, 3).forEach(function (k) {
        var d = dataPilihan[k];
        var a = document.createElement("a");
        a.className = "related-card";
        a.href = "detail.xhtml?item=" + k;
        var img = document.createElement("img");
        img.src = d.gambar;
        img.alt = d.judul;
        var box = document.createElement("div");
        var t = document.createElement("strong");
        t.textContent = d.judul;
        var c = document.createElement("span");
        c.textContent = d.kategori;
        box.appendChild(t);
        box.appendChild(c);
        a.appendChild(img);
        a.appendChild(box);
        grid.appendChild(a);
    });
})();