<?php
/**
 * File: siswa_rekap_nilai.php
 * Halaman untuk siswa melihat rekap nilai mereka sendiri per kelas.
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
$rekap_nilai = [];
$info_kelas_terpilih = null;
$rata_rata = 0;

// Jika siswa sudah memilih kelas
if (isset($_GET['id_kelas']) && !empty($_GET['id_kelas'])) {
    $id_kelas_terpilih = intval($_GET['id_kelas']);

    // Ambil info kelas
    $stmt_info_kelas = $koneksi->prepare("SELECT nama_kelas, mata_pelajaran FROM kelas WHERE id = ?");
    $stmt_info_kelas->bind_param("i", $id_kelas_terpilih);
    $stmt_info_kelas->execute();
    $info_kelas_terpilih = $stmt_info_kelas->get_result()->fetch_assoc();
    $stmt_info_kelas->close();

    // Ambil semua data nilai siswa di kelas ini
    $query_nilai = "
        SELECT s.judul_soal, pn.nilai, pn.komentar
        FROM soal s
        LEFT JOIN penilaian pn ON s.id = pn.id_soal AND pn.id_siswa = ?
        WHERE s.id_kelas = ?
        ORDER BY s.waktu_dibuat ASC
    ";
    $stmt_data_nilai = $koneksi->prepare($query_nilai);
    $stmt_data_nilai->bind_param("ii", $id_siswa, $id_kelas_terpilih);
    $stmt_data_nilai->execute();
    $result_data_nilai = $stmt_data_nilai->get_result();
    
    $total_nilai = 0;
    $jumlah_penilaian = 0;
    while ($row = $result_data_nilai->fetch_assoc()) {
        $rekap_nilai[] = $row;
        if ($row['nilai'] !== null) {
            $total_nilai += $row['nilai'];
            $jumlah_penilaian++;
        }
    }
    if ($jumlah_penilaian > 0) {
        $rata_rata = $total_nilai / $jumlah_penilaian;
    }
    $stmt_data_nilai->close();
}

include_once 'templates/header_siswa.php';
?>

<h1 class="mb-4">Rekapitulasi Nilai Saya</h1>

<div class="card mb-4">
    <div class="card-body">
        <form action="siswa_rekap_nilai.php" method="GET" class="row g-3 align-items-end">
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

<?php if ($id_kelas_terpilih): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            Rekap Nilai untuk Kelas: <strong><?php echo htmlspecialchars($info_kelas_terpilih['nama_kelas']); ?></strong>
            <span class="badge bg-primary rounded-pill fs-6">Rata-rata: <?php echo number_format($rata_rata, 2); ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Kuis/Ujian</th>
                            <th class="text-center">Nilai</th>
                            <th>Komentar dari Guru</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($rekap_nilai) > 0): ?>
                            <?php foreach ($rekap_nilai as $nilai): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($nilai['judul_soal']); ?></td>
                                    <td class="text-center">
                                        <?php 
                                            if ($nilai['nilai'] !== null) {
                                                echo "<strong>" . number_format($nilai['nilai'], 2) . "</strong>";
                                            } else {
                                                echo "<span class='text-muted'>Belum dinilai</span>";
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($nilai['komentar'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">Belum ada kuis/ujian untuk kelas ini.</td>
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