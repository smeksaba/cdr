<?php
/**
 * File: guru_dashboard.php
 * Versi Penuh dan Final - Halaman utama untuk Guru
 */

require_once 'config/koneksi.php';

// Proteksi halaman
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    header("Location: login.php");
    exit();
}
$id_guru = $_SESSION['user_id'];
$nama_guru = $_SESSION['nama_lengkap'];

// Ambil data kelas dari database
$query_kelas = $koneksi->prepare("SELECT id, nama_kelas, mata_pelajaran, deskripsi, kode_kelas FROM kelas WHERE id_guru = ? ORDER BY tanggal_dibuat DESC");
$query_kelas->bind_param("i", $id_guru);
$query_kelas->execute();
$result_kelas = $query_kelas->get_result();

include_once 'templates/header.php';
?>

<h1 class="mb-4">Dashboard Guru</h1>
<p>Selamat datang, <?php echo htmlspecialchars($nama_guru); ?>!</p>

<?php 
// Notifikasi dinamis setelah aksi berhasil
if (isset($_GET['status'])) {
    $pesan_notif = '';
    switch ($_GET['status']) {
        case 'sukses_buat':
            $pesan_notif = 'Kelas baru berhasil dibuat!';
            break;
        case 'sukses_hapus':
            $pesan_notif = 'Kelas berhasil dihapus.';
            break;
        case 'sukses_copy':
            $pesan_notif = 'Kelas berhasil disalin!';
            break;
    }
    if (!empty($pesan_notif)) {
        echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                $pesan_notif
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}
?>

<h2 class="h4 mt-5">Daftar Kelas Anda</h2>

<div class="row">
    <?php if ($result_kelas->num_rows > 0): ?>
        <?php while($kelas = $result_kelas->fetch_assoc()): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h6 class="card-subtitle mb-2 text-primary fw-bold"><?php echo htmlspecialchars($kelas['mata_pelajaran']); ?></h6>
                    <h5 class="card-title"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></h5>
                    <p class="card-text flex-grow-1"><?php echo htmlspecialchars(substr($kelas['deskripsi'], 0, 100)); ?>...</p>
                    <p class="card-text"><small class="text-muted">Kode Kelas: <strong><?php echo htmlspecialchars($kelas['kode_kelas']); ?></strong></small></p>
                    
                    <div class="mt-auto pt-2">
                        <div class="btn-group w-100" role="group">
                            <a href="detail_kelas.php?id=<?php echo $kelas['id']; ?>" class="btn btn-primary">Kelola</a>
                            <a href="copy_kelas.php?id_sumber=<?php echo $kelas['id']; ?>" class="btn btn-info" onclick="return confirm('Anda yakin ingin menyalin kelas ini?')">Copy</a>
                            <a href="hapus_kelas.php?id=<?php echo $kelas['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('PERINGATAN: Menghapus kelas akan menghilangkan semua data terkait (materi, kuis, nilai) secara permanen. Anda yakin?')">Hapus</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">Anda belum memiliki kelas. Silakan <a href="buat_kelas.php" class="alert-link">buat kelas baru</a>.</div>
        </div>
    <?php endif; ?>
</div>

<?php
$query_kelas->close();
$koneksi->close();
include_once 'templates/footer.php';
?>