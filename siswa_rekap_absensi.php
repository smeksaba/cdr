<?php
/**
 * File: siswa_rekap_absensi.php
 * Halaman untuk siswa melihat rekap absensi mereka sendiri per kelas.
 */

require_once 'config/koneksi.php';

// Proteksi halaman, hanya untuk siswa
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'siswa') {
    header("Location: login.php");
    exit();
}
$id_siswa = $_SESSION['user_id'];

// Ambil daftar kelas yang diikuti siswa untuk filter dropdown
$stmt_kelas_list = $koneksi->prepare("SELECT k.id, k.nama_kelas, k.mata_pelajaran FROM pendaftaran_kelas pk JOIN kelas k ON pk.id_kelas = k.id WHERE pk.id_siswa = ? ORDER BY k.nama_kelas");
$stmt_kelas_list->bind_param("i", $id_siswa);
$stmt_kelas_list->execute();
$result_kelas_list = $stmt_kelas_list->get_result();

$id_kelas_terpilih = null;
$rekap_absensi = [];
$info_kelas_terpilih = null;

// Jika siswa sudah memilih kelas dan menekan tombol "Tampilkan"
if (isset($_GET['id_kelas']) && !empty($_GET['id_kelas'])) {
    $id_kelas_terpilih = intval($_GET['id_kelas']);

    // Ambil info kelas untuk judul
    $stmt_info_kelas = $koneksi->prepare("SELECT nama_kelas, mata_pelajaran FROM kelas WHERE id = ?");
    $stmt_info_kelas->bind_param("i", $id_kelas_terpilih);
    $stmt_info_kelas->execute();
    $info_kelas_terpilih = $stmt_info_kelas->get_result()->fetch_assoc();
    $stmt_info_kelas->close();

    // Ambil semua data absensi untuk siswa ini di kelas ini
    $query_absensi = "
        SELECT p.judul_pertemuan, p.tanggal_pertemuan, a.status 
        FROM pertemuan p
        LEFT JOIN absensi a ON p.id = a.id_pertemuan AND a.id_siswa = ?
        WHERE p.id_kelas = ?
        ORDER BY p.tanggal_pertemuan ASC
    ";
    $stmt_data_absensi = $koneksi->prepare($query_absensi);
    $stmt_data_absensi->bind_param("ii", $id_siswa, $id_kelas_terpilih);
    $stmt_data_absensi->execute();
    $result_data_absensi = $stmt_data_absensi->get_result();
    while ($row = $result_data_absensi->fetch_assoc()) {
        $rekap_absensi[] = $row;
    }
    $stmt_data_absensi->close();
}

include_once 'templates/header_siswa.php';
?>

<h1 class="mb-4">Rekapitulasi Absensi Saya</h1>

<div class="card mb-4">
    <div class="card-body">
        <form action="siswa_rekap_absensi.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="id_kelas" class="form-label">Pilih Kelas untuk Ditampilkan</label>
                <select name="id_kelas" id="id_kelas" class="form-select" required>
                    <option value="">-- Pilih Kelas --</option>
                    <?php while($kelas = $result_kelas_list->fetch_assoc()): ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php if($id_kelas_terpilih == $kelas['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']) . " (" . htmlspecialchars($kelas['mata_pelajaran']) . ")"; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary w-100">Tampilkan Rekap</button>
            </div>
        </form>
    </div>
</div>

<?php if ($id_kelas_terpilih): // Tampilkan tabel hanya jika kelas sudah dipilih ?>
    <div class="card">
        <div class="card-header">
            Rekap Absensi untuk Kelas: <strong><?php echo htmlspecialchars($info_kelas_terpilih['nama_kelas']); ?></strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Pertemuan</th>
                            <th>Tanggal</th>
                            <th>Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($rekap_absensi) > 0): ?>
                            <?php foreach ($rekap_absensi as $absen): ?>
                                <tr>
                                    <td class="text-start"><?php echo htmlspecialchars($absen['judul_pertemuan']); ?></td>
                                    <td><?php echo date('d F Y', strtotime($absen['tanggal_pertemuan'])); ?></td>
                                    <td>
                                        <?php 
                                            $status = $absen['status'] ?? 'Belum Diabsen';
                                            $badge_class = 'bg-secondary';
                                            switch ($status) {
                                                case 'Hadir': $badge_class = 'bg-success'; break;
                                                case 'Sakit': $badge_class = 'bg-warning text-dark'; break;
                                                case 'Izin': $badge_class = 'bg-info text-dark'; break;
                                                case 'Alfa': $badge_class = 'bg-danger'; break;
                                            }
                                            echo "<span class='badge $badge_class'>$status</span>";
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">Belum ada data pertemuan untuk kelas ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$koneksi->close();
include_once 'templates/footer.php';
?>