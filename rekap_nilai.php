<?php
/**
 * File: rekap_nilai.php
 * Halaman untuk guru melihat rekapitulasi nilai per kelas.
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
$kuis_list = [];
$siswa_list = [];
$data_nilai = [];
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

    // 1. Ambil daftar kuis untuk kelas terpilih
    $stmt_kuis = $koneksi->prepare("SELECT id, judul_soal FROM soal WHERE id_kelas = ? ORDER BY waktu_dibuat ASC");
    $stmt_kuis->bind_param("i", $id_kelas_terpilih);
    $stmt_kuis->execute();
    $result_kuis = $stmt_kuis->get_result();
    while ($row = $result_kuis->fetch_assoc()) {
        $kuis_list[] = $row;
    }
    $stmt_kuis->close();

    // 2. Ambil daftar siswa di kelas terpilih
    $stmt_siswa = $koneksi->prepare("SELECT u.id, u.nama_lengkap FROM pendaftaran_kelas pk JOIN pengguna u ON pk.id_siswa = u.id WHERE pk.id_kelas = ? ORDER BY u.nama_lengkap ASC");
    $stmt_siswa->bind_param("i", $id_kelas_terpilih);
    $stmt_siswa->execute();
    $result_siswa = $stmt_siswa->get_result();
    while ($row = $result_siswa->fetch_assoc()) {
        $siswa_list[] = $row;
    }
    $stmt_siswa->close();

    // 3. Ambil semua data nilai untuk kelas ini
    $query_nilai = "
        SELECT pn.id_siswa, pn.id_soal, pn.nilai 
        FROM penilaian pn
        JOIN soal s ON pn.id_soal = s.id
        WHERE s.id_kelas = ?
    ";
    $stmt_data_nilai = $koneksi->prepare($query_nilai);
    $stmt_data_nilai->bind_param("i", $id_kelas_terpilih);
    $stmt_data_nilai->execute();
    $result_data_nilai = $stmt_data_nilai->get_result();
    while ($row = $result_data_nilai->fetch_assoc()) {
        $data_nilai[$row['id_siswa']][$row['id_soal']] = $row['nilai'];
    }
    $stmt_data_nilai->close();
}


include_once 'templates/header.php';
?>

<h1 class="mb-4 no-print">Rekapitulasi Nilai</h1>

<div class="card mb-4 no-print">
    <div class="card-body">
        <form action="rekap_nilai.php" method="GET" class="row g-3 align-items-end">
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
            <div class="col-auto">
                <button type="button" class="btn btn-secondary w-100" onclick="window.print();">
                    <i class="bi bi-printer-fill me-2"></i>Cetak
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($id_kelas_terpilih): ?>
    <div class="printable-area">
        <div class="card">
            <div class="card-header">
                <h4 class="text-center">Rekap Nilai</h4>
                <h5 class="text-center"><?php echo htmlspecialchars($info_kelas_terpilih['nama_kelas']) . " - " . htmlspecialchars($info_kelas_terpilih['mata_pelajaran']); ?></h5>
                <p class="text-center mb-0">Tanggal Cetak: <?php echo date('d F Y'); ?></p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover text-center">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start">Nama Siswa</th>
                                <?php foreach ($kuis_list as $kuis): ?>
                                    <th><?php echo htmlspecialchars($kuis['judul_soal']); ?></th>
                                <?php endforeach; ?>
                                <th>Rata-rata</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($siswa_list) > 0): ?>
                                <?php foreach ($siswa_list as $siswa): 
                                    $total_nilai = 0;
                                    $jumlah_kuis_dikerjakan = 0;
                                ?>
                                    <tr>
                                        <td class="text-start"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                        <?php foreach ($kuis_list as $kuis): ?>
                                            <td>
                                                <?php 
                                                    $nilai = $data_nilai[$siswa['id']][$kuis['id']] ?? null;
                                                    if ($nilai !== null) {
                                                        echo number_format($nilai, 2);
                                                        $total_nilai += $nilai;
                                                        $jumlah_kuis_dikerjakan++;
                                                    } else {
                                                        echo '-'; // Tanda jika belum mengerjakan
                                                    }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <?php
                                                $rata_rata = ($jumlah_kuis_dikerjakan > 0) ? $total_nilai / $jumlah_kuis_dikerjakan : 0;
                                                echo '<strong>' . number_format($rata_rata, 2) . '</strong>';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($kuis_list) + 2; ?>" class="text-center">Tidak ada siswa di kelas ini.</td>
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