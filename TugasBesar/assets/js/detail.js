// Memuat detail destinasi dari database lewat destinasi/get.php?id=...
var PLACEHOLDER = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400">' +
    '<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6c5b8b"/>' +
    '<stop offset="1" stop-color="#e3a678"/></linearGradient></defs>' +
    '<rect width="600" height="400" fill="url(#g)"/></svg>');

function fotoSrc(f) {
    if (!f) return PLACEHOLDER;
    if (/^(https?:|\/|assets\/)/.test(f)) return f;
    return "assets/img/" + f;
}
function setImg(el, f, alt) {
    el.onerror = function () { el.onerror = null; el.src = PLACEHOLDER; };
    el.src = fotoSrc(f);
    el.alt = alt;
}
function rupiah(n) { return "Rp " + Number(n).toLocaleString("id-ID"); }
function hargaText(d) {
    var min = Number(d.harga_tiket), max = d.harga_maks ? Number(d.harga_maks) : null;
    if (min === 0 && !max) return "Gratis";
    return max ? rupiah(min) + " - " + rupiah(max) : rupiah(min);
}
function el(id) { return document.getElementById(id); }

var itemId = new URLSearchParams(window.location.search).get("id") || "1";
var current = null;
var btn = el("btn-action");

function tampilkanError(pesan) {
    el("detail-title").textContent = "Data tidak ditemukan";
    el("detail-desc").textContent = pesan;
    el("crumb-title").textContent = "-";
    btn.disabled = true;
}

function renderButton() {
    var saved = Dolan.isFav(itemId);
    btn.textContent = saved ? "\u2713 Tersimpan (klik untuk hapus)" : "Pilih / Simpan";
    btn.className = saved ? "btn-primary saved" : "btn-primary";
}

function renderDetail(d) {
    document.title = d.nama + " - Dolan Jogja";
    el("crumb-cat").textContent = d.kategori;
    el("crumb-title").textContent = d.nama;
    el("detail-category").textContent = d.kategori;
    el("detail-title").textContent = d.nama;

    var rating = d.rating ? "\u2605 " + d.rating + " (" + d.jumlah_ulasan + " ulasan)" : "Belum ada ulasan";
    el("detail-meta").textContent = d.lokasi + " \u2022 " + rating + " \u2022 " + hargaText(d);
    el("detail-desc").textContent = d.deskripsi || "Belum ada deskripsi.";
    setImg(el("detail-img"), d.foto, d.nama);

    var poin = [
        "Kategori: " + d.kategori,
        "Lokasi: " + d.lokasi,
        "Harga tiket: " + hargaText(d),
        "Jam buka: " + (d.jam_buka || "Tidak tercantum")
    ];
    var list = el("detail-list");
    list.innerHTML = "";
    poin.forEach(function (t) {
        var li = document.createElement("li");
        li.textContent = t;
        list.appendChild(li);
    });
}

function renderTerkait(items) {
    var grid = el("related-grid");
    grid.innerHTML = "";
    items.forEach(function (d) {
        var a = document.createElement("a");
        a.className = "related-card";
        a.href = "detail.xhtml?id=" + encodeURIComponent(d.id);
        var img = document.createElement("img");
        setImg(img, d.foto, d.nama);
        var box = document.createElement("div");
        var t = document.createElement("strong");
        t.textContent = d.nama;
        var c = document.createElement("span");
        c.textContent = d.kategori;
        box.appendChild(t);
        box.appendChild(c);
        a.appendChild(img);
        a.appendChild(box);
        grid.appendChild(a);
    });
}

btn.addEventListener("click", function () {
    if (!current) return;
    var nowSaved = Dolan.toggleFav(itemId);
    renderButton();
    Dolan.toast(nowSaved ? '"' + current.nama + '" disimpan ke favorit.'
                         : '"' + current.nama + '" dihapus dari favorit.',
                nowSaved ? "success" : "info");
});

fetch("destinasi/get.php?id=" + encodeURIComponent(itemId))
    .then(function (res) {
        return res.json().then(function (data) { return { ok: res.ok, data: data }; });
    })
    .then(function (r) {
        if (!r.ok) {
            tampilkanError(r.data.error || "Silakan kembali ke beranda dan pilih destinasi yang tersedia.");
            return;
        }
        current = r.data.destinasi;
        renderDetail(current);
        renderTerkait(r.data.terkait);
        renderButton();
    })
    .catch(function () {
        tampilkanError("Gagal memuat data. Pastikan halaman dibuka lewat server PHP (Apache/XAMPP), bukan Live Server.");
    });
