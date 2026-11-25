// Util bersama: favorit (localStorage), badge navbar, dan toast
var Dolan = (function () {
    var KEY = "dolanFavorites";

    function getFavs() {
        try { return JSON.parse(localStorage.getItem(KEY)) || []; }
        catch (e) { return []; }
    }
    function setFavs(list) {
        try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {}
    }
    function isFav(id) { return getFavs().indexOf(id) !== -1; }

    // Mengembalikan true jika sekarang tersimpan, false jika dihapus
    function toggleFav(id) {
        var list = getFavs();
        var idx = list.indexOf(id);
        if (idx === -1) { list.push(id); } else { list.splice(idx, 1); }
        setFavs(list);
        updateBadge();
        return idx === -1;
    }

    function updateBadge() {
        var n = getFavs().length;
        var badges = document.querySelectorAll(".fav-badge");
        for (var i = 0; i < badges.length; i++) {
            badges[i].textContent = n;
            badges[i].style.display = n > 0 ? "inline-block" : "none";
        }
    }

    function toast(message, type) {
        var box = document.getElementById("toast-container");
        if (!box) {
            box = document.createElement("div");
            box.id = "toast-container";
            document.body.appendChild(box);
        }
        var t = document.createElement("div");
        t.className = "toast toast-" + (type || "success");
        t.textContent = message;
        box.appendChild(t);
        setTimeout(function () { t.classList.add("show"); }, 10);
        setTimeout(function () {
            t.classList.remove("show");
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 300);
        }, 2800);
    }

    document.addEventListener("DOMContentLoaded", updateBadge);
    return { getFavs: getFavs, isFav: isFav, toggleFav: toggleFav, updateBadge: updateBadge, toast: toast };
})();