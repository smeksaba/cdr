<?php
/**
 * File: rekap_absensi.php
 * Versi dengan penambahan Tombol Print
 */

require_once 'config/koneksi.php';

// Proteksi halaman, hanya untuk guru
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    header("Location: login.php");
    exit();
}
$id_guru = $_SESSION['user_id'];

// Ambil daftar kelas milik guru untuk filter dropdown
$stmt_kelas_list = $koneksi->prepare("SELECT id, nama_kelas, mata_pelajaran FROM kelas WHERE id_guru = ? ORDER BY nama_kelas");
$stmt_kelas_list->bind_param("i", $id_guru);
$stmt_kelas_list->execute();
$result_kelas_list = $stmt_kelas_list->get_result();

$id_kelas_terpilih = null;
$pertemuan_list = [];
$siswa_list = [];
$data_absensi = [];
$info_kelas_terpilih = null;

// Jika guru sudah memilih kelas dan menekan tombol "Tampilkan"
if (isset($_GET['id_kelas']) && !empty($_GET['id_kelas'])) {
    $id_kelas_terpilih = intval($_GET['id_kelas']);

    // Ambil info kelas untuk judul
    $stmt_info_kelas = $koneksi->prepare("SELECT nama_kelas, mata_pelajaran FROM kelas WHERE id = ?");
    $stmt_info_kelas->bind_param("i", $id_kelas_terpilih);
    $stmt_info_kelas->execute();
    $info_kelas_terpilih = $stmt_info_kelas->get_result()->fetch_assoc();
    $stmt_info_kelas->close();

    // 1. Ambil daftar pertemuan untuk kelas terpilih
    $stmt_pertemuan = $koneksi->prepare("SELECT id, judul_pertemuan, tanggal_pertemuan FROM pertemuan WHERE id_kelas = ? ORDER BY tanggal_pertemuan ASC");
    $stmt_pertemuan->bind_param("i", $id_kelas_terpilih);
    $stmt_pertemuan->execute();
    $result_pertemuan = $stmt_pertemuan->get_result();
    while ($row = $result_pertemuan->fetch_assoc()) {
        $pertemuan_list[] = $row;
    }
    $stmt_pertemuan->close();

    // 2. Ambil daftar siswa di kelas terpilih
    $stmt_siswa = $koneksi->prepare("SELECT u.id, u.nama_lengkap FROM pendaftaran_kelas pk JOIN pengguna u ON pk.id_siswa = u.id WHERE pk.id_kelas = ? ORDER BY u.nama_lengkap ASC");
    $stmt_siswa->bind_param("i", $id_kelas_terpilih);
    $stmt_siswa->execute();
    $result_siswa = $stmt_siswa->get_result();
    while ($row = $result_siswa->fetch_assoc()) {
        $siswa_list[] = $row;
    }
    $stmt_siswa->close();

    // 3. Ambil semua data absensi untuk kelas ini
    $query_absensi = "SELECT a.id_siswa, a.id_pertemuan, a.status FROM absensi a JOIN pertemuan p ON a.id_pertemuan = p.id WHERE p.id_kelas = ?";
    $stmt_data_absensi = $koneksi->prepare($query_absensi);
    $stmt_data_absensi->bind_param("i", $id_kelas_terpilih);
    $stmt_data_absensi->execute();
    $result_data_absensi = $stmt_data_absensi->get_result();
    while ($row = $result_data_absensi->fetch_assoc()) {
        $data_absensi[$row['id_siswa']][$row['id_pertemuan']] = $row['status'];
    }
    $stmt_data_absensi->close();
}


include_once 'templates/header.php';
?>

<h1 class="mb-4 no-print">Rekapitulasi Absensi</h1>

<div class="card mb-4 no-print">
    <div class="card-body">
        <form action="rekap_absensi.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="id_kelas" class="form-label">Pilih Kelas untuk Ditampilkan</label>
                <select name="id_kelas" id="id_kelas" class="form-select" required>
                    <option value="">-- Pilih Kelas --</option>
                    <?php
                    // Reset pointer result set untuk loop kedua
                    $result_kelas_list->data_seek(0);
                    while($kelas = $result_kelas_list->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php if($id_kelas_terpilih == $kelas['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']) . " (" . htmlspecialchars($kelas['mata_pelajaran']) . ")"; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary w-100">Tampilkan Rekap</button>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-secondary w-100" onclick="window.print();">
                    <i class="bi bi-printer-fill me-2"></i>Cetak
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($id_kelas_terpilih): // Tampilkan tabel hanya jika kelas sudah dipilih ?>
    <div class="printable-area">
        <div class="card">
            <div class="card-header">
                <h4 class="text-center">Rekap Absensi</h4>
                <h5 class="text-center"><?php echo htmlspecialchars($info_kelas_terpilih['nama_kelas']) . " - " . htmlspecialchars($info_kelas_terpilih['mata_pelajaran']); ?></h5>
                <p class="text-center mb-0">Tanggal Cetak: <?php echo date('d F Y'); ?></p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover text-center">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start">Nama Siswa</th>
                                <?php foreach ($pertemuan_list as $pertemuan): ?>
                                    <th><?php echo htmlspecialchars($pertemuan['judul_pertemuan']); ?><br><small class="fw-normal"><?php echo date('d/m/y', strtotime($pertemuan['tanggal_pertemuan'])); ?></small></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($siswa_list) > 0): ?>
                                <?php foreach ($siswa_list as $siswa): ?>
                                    <tr>
                                        <td class="text-start"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                        <?php foreach ($pertemuan_list as $pertemuan): ?>
                                            <td>
                                                <?php 
                                                    $status = $data_absensi[$siswa['id']][$pertemuan['id']] ?? 'N/A';
                                                    $badge_class = 'bg-secondary';
                                                    switch ($status) {
                                                        case 'Hadir': $badge_class = 'bg-success'; break;
                                                        case 'Sakit': $badge_class = 'bg-warning text-dark'; break;
                                                        case 'Izin': $badge_class = 'bg-info text-dark'; break;
                                                        case 'Alfa': $badge_class = 'bg-danger'; break;
                                                    }
                                                    // Saat print, badge tidak akan terlihat bagus, jadi kita tampilkan teks saja.
                                                    echo "<span class='badge $badge_class no-print'>$status</span>"; // Badge untuk tampilan web
                                                    echo "<span class='d-none d-print-inline'>$status</span>";     // Teks biasa untuk print
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($pertemuan_list) + 1; ?>" class="text-center">Tidak ada siswa di kelas ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$koneksi->close();
include_once 'templates/footer.php';
?>