<?php
require_once __DIR__ . "/../includes/init.php";
wajib_admin();
$me = (int) $USER["id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cek_csrf();
    $aksi = $_POST["aksi"] ?? "";
    $id   = (int) ($_POST["id"] ?? 0);

    if ($id === $me) {
        flash("error", "Anda tidak dapat mengubah atau menghapus akun sendiri dari halaman ini.");
    } elseif ($aksi === "role") {
        $role = ($_POST["role"] ?? "") === "admin" ? "admin" : "user";
        $s = $conn->prepare("UPDATE users SET role=? WHERE id=?");
        $s->bind_param("si", $role, $id);
        $s->execute();
        catat_log_admin($conn, $me, "role_pengguna", "pengguna #" . $id . " menjadi " . $role);
        flash("success", "Role pengguna diperbarui.");
    } elseif ($aksi === "hapus") {
        $s = $conn->prepare("SELECT foto FROM users WHERE id=?");
        $s->bind_param("i", $id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        if ($row) {
            $s = $conn->prepare("DELETE FROM users WHERE id=?");
            $s->bind_param("i", $id);
            $s->execute();
            hapus_gambar("profil", $row["foto"]);
            catat_log_admin($conn, $me, "hapus_pengguna", "pengguna #" . $id);
            flash("success", "Pengguna dihapus.");
        }
    }
    redirect("admin/pengguna.php");
}

$rows = $conn->query("SELECT u.id, u.username, u.email, u.nama_lengkap, u.role, u.created_at,
        (SELECT COUNT(*) FROM reviews r WHERE r.user_id=u.id) AS n_ulasan,
        (SELECT COUNT(*) FROM wishlists w WHERE w.user_id=u.id) AS n_fav
        FROM users u ORDER BY u.created_at DESC, u.id DESC");
$self = url("admin/pengguna.php");

header_web("Kelola Pengguna");
?>
<div class="page-heading">
  <span class="eyebrow">Administrator</span>
  <h1>Kelola pengguna</h1>
  <p>Daftar akun terdaftar, pengaturan role, dan penghapusan akun.</p>
</div>
<?php subnav_admin("pengguna"); ?>
<div class="panel table-wrap">
  <table>
    <thead><tr><th>Nama</th><th>Username</th><th>Email</th><th>Ulasan</th><th>Favorit</th><th>Bergabung</th><th>Role</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php while ($u = $rows->fetch_assoc()) { ?>
      <tr>
        <td><?php echo e($u["nama_lengkap"]); ?></td>
        <td><?php echo e($u["username"]); ?></td>
        <td><?php echo e($u["email"]); ?></td>
        <td><?php echo (int) $u["n_ulasan"]; ?></td>
        <td><?php echo (int) $u["n_fav"]; ?></td>
        <td><?php echo e(tanggal($u["created_at"])); ?></td>
        <?php if ((int) $u["id"] === $me) { ?>
        <td><span class="tag"><?php echo e($u["role"]); ?></span></td>
        <td class="muted">(akun Anda)</td>
        <?php } else { ?>
        <td>
          <form class="inline" method="post" action="<?php echo $self; ?>">
            <div>
              <?php echo csrf_field(); ?>
              <input type="hidden" name="aksi" value="role" />
              <input type="hidden" name="id" value="<?php echo (int) $u["id"]; ?>" />
              <select name="role" title="Role pengguna">
                <option value="user"<?php echo $u["role"] === "user" ? ' selected="selected"' : ""; ?>>user</option>
                <option value="admin"<?php echo $u["role"] === "admin" ? ' selected="selected"' : ""; ?>>admin</option>
              </select>
              <button type="submit" class="link-btn">Simpan</button>
            </div>
          </form>
        </td>
        <td class="aksi"><?php echo form_aksi($self, "hapus", $u["id"], "Hapus", "Hapus pengguna ini beserta ulasan dan favoritnya?"); ?></td>
        <?php } ?>
      </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
<?php footer_web(); ?>
