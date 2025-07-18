<?php
require_once 'config/koneksi.php';

// Proteksi halaman, hanya untuk guru
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    header("Location: login.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_guru = $_SESSION['user_id'];
    $nama_kelas = sanitize($koneksi, $_POST['nama_kelas']);
    // Ambil data mata pelajaran dari form
    $mata_pelajaran = sanitize($koneksi, $_POST['mata_pelajaran']);
    $deskripsi = sanitize($koneksi, $_POST['deskripsi']);
    $kode_kelas = strtoupper(substr(uniqid(), -6));

    if (empty($nama_kelas) || empty($mata_pelajaran)) {
        $error = "Nama kelas dan mata pelajaran tidak boleh kosong!";
    } else {
        // Perbarui query INSERT untuk menyertakan mata_pelajaran
        $stmt = $koneksi->prepare("INSERT INTO kelas (id_guru, nama_kelas, mata_pelajaran, deskripsi, kode_kelas) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt === false) {
            die("<h3>Error!</h3><p>Gagal menyiapkan perintah SQL. Periksa nama tabel `kelas` dan kolom-kolomnya di database.</p><p>Pesan error: " . htmlspecialchars($koneksi->error) . "</p>");
        }
        
        // Perbarui bind_param dengan tipe data yang sesuai
        $stmt->bind_param("issss", $id_guru, $nama_kelas, $mata_pelajaran, $deskripsi, $kode_kelas);
        if ($stmt->execute()) {
            header("Location: guru_dashboard.php?status=sukses_buat");
            exit();
        } else {
            $error = "Gagal membuat kelas. Silakan coba lagi.";
        }
        $stmt->close();
    }
}
$koneksi->close();

include_once 'templates/header.php';
?>

<h1 class="mb-4">Buat Kelas Baru</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="buat_kelas.php" method="POST">
            <div class="mb-3">
                <label for="nama_kelas" class="form-label">Nama Kelas</label>
                <input type="text" class="form-control" id="nama_kelas" name="nama_kelas" required>
            </div>
            <div class="mb-3">
                <label for="mata_pelajaran" class="form-label">Mata Pelajaran</label>
                <input type="text" class="form-control" id="mata_pelajaran" name="mata_pelajaran" required>
            </div>
            <div class="mb-3">
                <label for="deskripsi" class="form-label">Deskripsi Singkat</label>
                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Kelas</button>
            <a href="guru_dashboard.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include_once 'templates/footer.php'; ?>