<?php
/**
 * File: siswa_dashboard.php
 * Versi Penuh dan Final
 */
require_once 'config/koneksi.php';

// Proteksi halaman
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'siswa') {
    header("Location: login.php");
    exit();
}

$id_siswa = $_SESSION['user_id'];
$nama_siswa = $_SESSION['nama_lengkap'];

// Logika untuk mengambil data kelas yang diikuti siswa
$query_kelas = "
    SELECT 
        k.id, 
        k.nama_kelas, 
        k.mata_pelajaran,
        k.deskripsi, 
        g.nama_lengkap AS nama_guru
    FROM pendaftaran_kelas pk
    JOIN kelas k ON pk.id_kelas = k.id
    JOIN pengguna g ON k.id_guru = g.id
    WHERE pk.id_siswa = ?
    ORDER BY k.nama_kelas
";

$stmt_kelas = $koneksi->prepare($query_kelas);
$stmt_kelas->bind_param("i", $id_siswa);
$stmt_kelas->execute();
$result_kelas = $stmt_kelas->get_result();

include_once 'templates/header_siswa.php';
?>

<h1 class="mb-4">Kelas Saya</h1>
<p>Selamat datang, <?php echo htmlspecialchars($nama_siswa); ?>! Berikut adalah daftar kelas yang Anda ikuti.</p>

<div class="row">
    <?php if ($result_kelas->num_rows > 0): ?>
        <?php while($kelas = $result_kelas->fetch_assoc()): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h6 class="card-subtitle mb-2 text-primary fw-bold"><?php echo htmlspecialchars($kelas['mata_pelajaran']); ?></h6>
                    <h5 class="card-title"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></h5>
                    <p class="card-text"><small class="text-muted">Guru: <?php echo htmlspecialchars($kelas['nama_guru']); ?></small></p>
                    <p class="card-text flex-grow-1"><?php echo htmlspecialchars(substr($kelas['deskripsi'], 0, 100)); ?>...</p>
                    <a href="ruang_kelas_siswa.php?id_kelas=<?php echo $kelas['id']; ?>" class="btn btn-primary mt-auto">Masuk Kelas</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">Anda belum terdaftar di kelas mana pun. Silakan hubungi guru Anda untuk mendapatkan kode kelas.</div>
        </div>
    <?php endif; ?>
</div>

<?php
$stmt_kelas->close();
$koneksi->close();
include_once 'templates/footer.php';
?><?php
/**
 * File: siswa_dashboard.php
 * Versi Penuh dan Final
 */
require_once 'config/koneksi.php';

// Proteksi halaman
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'siswa') {
    header("Location: login.php");
    exit();
}

$id_siswa = $_SESSION['user_id'];
$nama_siswa = $_SESSION['nama_lengkap'];

// Logika untuk mengambil data kelas yang diikuti siswa
$query_kelas = "
    SELECT 
        k.id, 
        k.nama_kelas, 
        k.mata_pelajaran,
        k.deskripsi, 
        g.nama_lengkap AS nama_guru
    FROM pendaftaran_kelas pk
    JOIN kelas k ON pk.id_kelas = k.id
    JOIN pengguna g ON k.id_guru = g.id
    WHERE pk.id_siswa = ?
    ORDER BY k.nama_kelas
";

$stmt_kelas = $koneksi->prepare($query_kelas);
$stmt_kelas->bind_param("i", $id_siswa);
$stmt_kelas->execute();
$result_kelas = $stmt_kelas->get_result();

include_once 'templates/header_siswa.php';
?>

<h1 class="mb-4">Kelas Saya</h1>
<p>Selamat datang, <?php echo htmlspecialchars($nama_siswa); ?>! Berikut adalah daftar kelas yang Anda ikuti.</p>

<div class="row">
    <?php if ($result_kelas->num_rows > 0): ?>
        <?php while($kelas = $result_kelas->fetch_assoc()): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h6 class="card-subtitle mb-2 text-primary fw-bold"><?php echo htmlspecialchars($kelas['mata_pelajaran']); ?></h6>
                    <h5 class="card-title"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></h5>
                    <p class="card-text"><small class="text-muted">Guru: <?php echo htmlspecialchars($kelas['nama_guru']); ?></small></p>
                    <p class="card-text flex-grow-1"><?php echo htmlspecialchars(substr($kelas['deskripsi'], 0, 100)); ?>...</p>
                    <a href="ruang_kelas_siswa.php?id_kelas=<?php echo $kelas['id']; ?>" class="btn btn-primary mt-auto">Masuk Kelas</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">Anda belum terdaftar di kelas mana pun. Silakan hubungi guru Anda untuk mendapatkan kode kelas.</div>
        </div>
    <?php endif; ?>
</div>

<?php
$stmt_kelas->close();
$koneksi->close();
include_once 'templates/footer.php';
?>