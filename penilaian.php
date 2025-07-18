<?php
/**
 * File: penilaian.php
 * Halaman utama untuk guru melihat daftar siswa yang sudah mengerjakan
 * dan memberikan penilaian. VERSI DENGAN PERBAIKAN QUERY FINAL.
 */

require_once 'config/koneksi.php';

// Proteksi halaman, hanya untuk guru
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    header("Location: login.php");
    exit();
}
$id_guru = $_SESSION['user_id'];
$pesan = '';

// Validasi ID Kuis dari URL
if (!isset($_GET['id_soal'])) {
    header("Location: guru_dashboard.php");
    exit();
}
$id_kuis = intval($_GET['id_soal']);

// Ambil data kuis untuk verifikasi kepemilikan
$stmt_kuis = $koneksi->prepare("SELECT s.judul_soal, k.id as id_kelas FROM soal s JOIN kelas k ON s.id_kelas = k.id WHERE s.id = ? AND k.id_guru = ?");
if (!$stmt_kuis) die("Error preparing statement: " . $koneksi->error);
$stmt_kuis->bind_param("ii", $id_kuis, $id_guru);
$stmt_kuis->execute();
$result_kuis = $stmt_kuis->get_result();
if ($result_kuis->num_rows == 0) {
    header("Location: guru_dashboard.php?error=not_found");
    exit();
}
$kuis = $result_kuis->fetch_assoc();
$id_kelas = $kuis['id_kelas'];
$stmt_kuis->close();

// Logika untuk menyimpan/update nilai dari modal
if (isset($_POST['simpan_nilai'])) {
    $id_siswa = intval($_POST['id_siswa']);
    $nilai_final = floatval($_POST['nilai_final']);
    $komentar = sanitize($koneksi, $_POST['komentar']);

    $sql_nilai = "INSERT INTO penilaian (id_soal, id_siswa, nilai, komentar) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), komentar = VALUES(komentar)";
    $stmt_nilai = $koneksi->prepare($sql_nilai);
    if ($stmt_nilai) {
        $stmt_nilai->bind_param("iids", $id_kuis, $id_siswa, $nilai_final, $komentar);
        if ($stmt_nilai->execute()) {
            $pesan = "<div class='alert alert-success'>Nilai untuk siswa berhasil disimpan!</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan nilai.</div>";
        }
        $stmt_nilai->close();
    }
}

include_once 'templates/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="guru_dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="detail_kelas.php?id=<?php echo $id_kelas; ?>">Kelola Kelas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Penilaian Kuis</li>
  </ol>
</nav>

<h1>Penilaian Kuis</h1>
<h5 class="text-muted">"<?php echo htmlspecialchars($kuis['judul_soal']); ?>"</h5>
<hr>

<?php echo $pesan; ?>

<div class="card">
    <div class="card-header">Daftar Siswa yang Telah Mengerjakan</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nama Siswa</th>
                        <th>Waktu Submit Terakhir</th>
                        <th>Nilai Saat Ini</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // ======================= PERBAIKAN QUERY FINAL DI SINI =======================
                    // Query ini mengambil semua siswa yang terdaftar di kelas, lalu mencocokkan
                    // apakah mereka punya entri di tabel 'jawaban_siswa' ATAU 'penilaian'
                    // untuk kuis ini.
                    $query_siswa = "
                        SELECT
                            u.id as id_siswa,
                            u.nama_lengkap,
                            p.nilai,
                            p.komentar,
                            MAX(js.waktu_submit) as waktu_terakhir
                        FROM pendaftaran_kelas pk
                        JOIN pengguna u ON pk.id_siswa = u.id
                        LEFT JOIN jawaban_siswa js ON pk.id_siswa = js.id_siswa AND js.id_soal = ?
                        LEFT JOIN penilaian p ON pk.id_siswa = p.id_siswa AND p.id_soal = ?
                        WHERE pk.id_kelas = ? AND (js.id IS NOT NULL OR p.id IS NOT NULL)
                        GROUP BY u.id, u.nama_lengkap, p.nilai, p.komentar
                        ORDER BY u.nama_lengkap ASC
                    ";
                    
                    $stmt_siswa = $koneksi->prepare($query_siswa);
                    // Ada 3 placeholder '?' dalam query, kita bind dengan ID kuis dan ID kelas.
                    $stmt_siswa->bind_param("iii", $id_kuis, $id_kuis, $id_kelas);
                    $stmt_siswa->execute();
                    $result_siswa_list = $stmt_siswa->get_result();
                    // ======================= AKHIR DARI PERBAIKAN QUERY =======================

                    if ($result_siswa_list->num_rows > 0) {
                        while ($siswa = $result_siswa_list->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($siswa['nama_lengkap']) . "</td>";
                            echo "<td>" . ($siswa['waktu_terakhir'] ? htmlspecialchars(date('d F Y, H:i', strtotime($siswa['waktu_terakhir']))) : 'Otomatis (Interaktif)') . "</td>";
                            echo "<td><strong>" . ($siswa['nilai'] !== null ? number_format($siswa['nilai'], 2) : 'Belum Dinilai') . "</strong></td>";
                            echo "<td><button class='btn btn-primary btn-sm btn-nilai' data-bs-toggle='modal' data-bs-target='#penilaianModal' data-id-siswa='" . $siswa['id_siswa'] . "' data-nama-siswa='" . htmlspecialchars($siswa['nama_lengkap']) . "'>Lihat & Nilai</button></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center'>Belum ada siswa yang mengerjakan kuis ini.</td></tr>";
                    }
                    $stmt_siswa->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="penilaianModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="penilaianModalLabel">Jawaban Siswa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="detail-jawaban-loading" class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p>Memuat jawaban siswa...</p>
        </div>
        <div id="detail-jawaban-content">
            </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const penilaianModal = document.getElementById('penilaianModal');
    const detailJawabanContent = document.getElementById('detail-jawaban-content');
    const detailJawabanLoading = document.getElementById('detail-jawaban-loading');

    penilaianModal.addEventListener('show.bs.modal', function (event) {
        detailJawabanContent.innerHTML = '';
        detailJawabanLoading.style.display = 'block';

        const button = event.relatedTarget;
        const idSiswa = button.getAttribute('data-id-siswa');
        const namaSiswa = button.getAttribute('data-nama-siswa');
        const idKuis = <?php echo $id_kuis; ?>;
        
        const modalTitle = penilaianModal.querySelector('.modal-title');
        modalTitle.textContent = 'Jawaban dari: ' + namaSiswa;

        fetch(`get_jawaban_siswa.php?id_kuis=${idKuis}&id_siswa=${idSiswa}`)
            .then(response => {
                if (!response.ok) { throw new Error('Network response was not ok'); }
                return response.text();
            })
            .then(html => {
                detailJawabanLoading.style.display = 'none';
                detailJawabanContent.innerHTML = html;
            })
            .catch(error => {
                detailJawabanLoading.style.display = 'none';
                detailJawabanContent.innerHTML = `<div class='alert alert-danger'>Gagal memuat data jawaban. ${error.message}</div>`;
            });
    });
});
</script>

<?php
$koneksi->close();
include_once 'templates/footer.php';
?>