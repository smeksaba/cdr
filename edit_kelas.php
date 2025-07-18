<?php
/**
 * File: edit_kelas.php
 * Halaman untuk guru mengedit detail kelas (nama, mapel, deskripsi).
 */

require_once 'config/koneksi.php';

// Proteksi halaman
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    header("Location: login.php");
    exit();
}
$id_guru = $_SESSION['user_id'];
$pesan = '';

// Validasi ID Kelas dari URL
if (!isset($_GET['id'])) {
    header("Location: guru_dashboard.php");
    exit();
}
$id_kelas = intval($_GET['id']);

// Logika untuk menyimpan perubahan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_perubahan'])) {
    $nama_kelas_baru = sanitize($koneksi, $_POST['nama_kelas']);
    $mapel_baru = sanitize($koneksi, $_POST['mata_pelajaran']);
    $deskripsi_baru = sanitize($koneksi, $_POST['deskripsi']);

    $stmt_update = $koneksi->prepare("UPDATE kelas SET nama_kelas = ?, mata_pelajaran = ?, deskripsi = ? WHERE id = ? AND id_guru = ?");
    if ($stmt_update) {
        $stmt_update->bind_param("sssii", $nama_kelas_baru, $mapel_baru, $deskripsi_baru, $id_kelas, $id_guru);
        if ($stmt_update->execute()) {
            $pesan = "<div class='alert alert-success'>Perubahan detail kelas berhasil disimpan.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan perubahan.</div>";
        }
        $stmt_update->close();
    }
}

// Ambil data kelas terkini untuk ditampilkan di form
$stmt_kelas = $koneksi->prepare("SELECT nama_kelas, mata_pelajaran, deskripsi FROM kelas WHERE id = ? AND id_guru = ?");
if (!$stmt_kelas) die("Error: " . $koneksi->error);
$stmt_kelas->bind_param("ii", $id_kelas, $id_guru);
$stmt_kelas->execute();
$result_kelas = $stmt_kelas->get_result();
if ($result_kelas->num_rows == 0) {
    header("Location: guru_dashboard.php?error=not_found");
    exit();
}
$kelas = $result_kelas->fetch_assoc();
$stmt_kelas->close();

include_once 'templates/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="guru_dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="detail_kelas.php?id=<?php echo $id_kelas; ?>">Kelola Kelas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Detail Kelas</li>
  </ol>
</nav>

<h1><i class="bi bi-pencil-square me-2"></i>Edit Detail Kelas</h1>
<p class="text-muted">Anda dapat mengubah nama, mata pelajaran, dan deskripsi kelas di sini.</p>
<hr>

<?php if(!empty($pesan)) echo $pesan; ?>

<div class="card">
    <div class="card-body">
        <form action="edit_kelas.php?id=<?php echo $id_kelas; ?>" method="POST">
            <div class="mb-3">
                <label for="nama_kelas" class="form-label fw-bold">Nama Kelas</label>
                <input type="text" class="form-control" name="nama_kelas" value="<?php echo htmlspecialchars($kelas['nama_kelas']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="mata_pelajaran" class="form-label fw-bold">Mata Pelajaran</label>
                <input type="text" class="form-control" name="mata_pelajaran" value="<?php echo htmlspecialchars($kelas['mata_pelajaran']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="deskripsi" class="form-label">Deskripsi</label>
                <textarea class="form-control" name="deskripsi" rows="4"><?php echo htmlspecialchars($kelas['deskripsi']); ?></textarea>
            </div>
            <div class="d-flex justify-content-end">
                <a href="detail_kelas.php?id=<?php echo $id_kelas; ?>" class="btn btn-secondary me-2">Kembali</a>
                <button type="submit" name="simpan_perubahan" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php
$koneksi->close();
include_once 'templates/footer.php';
?>