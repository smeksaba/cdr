<?php
/**
 * File: edit_kuis.php
 * VERSI PENUH DAN FINAL DENGAN OPSI INTERAKTIF
 * Halaman untuk guru mengedit detail kuis (judul, deskripsi, batas waktu, mode).
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

// Logika untuk menyimpan perubahan (saat form disubmit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_perubahan'])) {
    $judul_baru = sanitize($koneksi, $_POST['judul_soal']);
    $deskripsi_baru = sanitize($koneksi, $_POST['deskripsi_soal']);
    $batas_waktu_baru = !empty($_POST['batas_waktu']) ? $_POST['batas_waktu'] : NULL;
    $mode_kuis_baru = sanitize($koneksi, $_POST['mode_kuis']);
    $waktu_per_soal_baru = ($mode_kuis_baru == 'interaktif' && !empty($_POST['waktu_per_soal'])) ? intval($_POST['waktu_per_soal']) : NULL;
    $acak_soal_baru = (isset($_POST['acak_soal'])) ? 1 : 0;

    // UPDATE data kuis di database
    $stmt_update = $koneksi->prepare("UPDATE soal SET judul_soal = ?, deskripsi = ?, batas_waktu = ?, mode_kuis = ?, waktu_per_soal = ?, acak_soal = ? WHERE id = ? AND id IN (SELECT id FROM kelas WHERE id_guru = ?)");
    if ($stmt_update) {
        $stmt_update->bind_param("ssssiiii", $judul_baru, $deskripsi_baru, $batas_waktu_baru, $mode_kuis_baru, $waktu_per_soal_baru, $acak_soal_baru, $id_kuis, $id_guru);
        if ($stmt_update->execute()) {
            $pesan = "<div class='alert alert-success'>Perubahan detail kuis berhasil disimpan.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan perubahan.</div>";
        }
        $stmt_update->close();
    }
}

// Ambil data kuis terkini untuk ditampilkan di form dan verifikasi kepemilikan
// Tambahkan kolom baru (mode_kuis, waktu_per_soal, acak_soal)
$stmt_kuis = $koneksi->prepare("SELECT s.judul_soal, s.deskripsi, s.batas_waktu, s.mode_kuis, s.waktu_per_soal, s.acak_soal, k.id as id_kelas FROM soal s JOIN kelas k ON s.id_kelas = k.id WHERE s.id = ? AND k.id_guru = ?");
if (!$stmt_kuis) die("Error: " . $koneksi->error);
$stmt_kuis->bind_param("ii", $id_kuis, $id_guru);
$stmt_kuis->execute();
$result_kuis = $stmt_kuis->get_result();
if ($result_kuis->num_rows == 0) {
    // Jika kuis tidak ada atau bukan milik guru ini, tendang ke dashboard
    header("Location: guru_dashboard.php?error=not_found");
    exit();
}
$kuis = $result_kuis->fetch_assoc();
$id_kelas = $kuis['id_kelas']; // Simpan id_kelas untuk link "Kembali"
$stmt_kuis->close();

include_once 'templates/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="guru_dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="detail_kelas.php?id=<?php echo $id_kelas; ?>">Kelola Kelas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Kuis</li>
  </ol>
</nav>

<h1><i class="bi bi-pencil-square me-2"></i>Edit Kuis</h1>
<p class="text-muted">Anda dapat mengubah judul, deskripsi, dan pengaturan lainnya di sini.</p>
<hr>

<?php if(!empty($pesan)) echo $pesan; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="edit_kuis.php?id_soal=<?php echo $id_kuis; ?>" method="POST" id="formEditKuis">
            <div class="mb-3">
                <label for="judul_soal" class="form-label fw-bold">Judul Kuis</label>
                <input type="text" class="form-control" name="judul_soal" value="<?php echo htmlspecialchars($kuis['judul_soal']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="deskripsi_soal" class="form-label">Deskripsi</label>
                <textarea class="form-control" name="deskripsi_soal" rows="4"><?php echo htmlspecialchars($kuis['deskripsi']); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Mode Kuis</label>
                <select class="form-select" name="mode_kuis" id="mode_kuis_edit">
                    <option value="klasik" <?php echo ($kuis['mode_kuis'] == 'klasik') ? 'selected' : ''; ?>>Klasik (Pengerjaan Standar)</option>
                    <option value="interaktif" <?php echo ($kuis['mode_kuis'] == 'interaktif') ? 'selected' : ''; ?>>Interaktif (Real-time & Otomatis)</option>
                </select>
            </div>

            <div id="opsi_klasik_edit" style="display: <?php echo ($kuis['mode_kuis'] == 'klasik') ? 'block' : 'none'; ?>;">
                <div class="mb-3">
                    <label for="batas_waktu" class="form-label">Batas Waktu Pengumpulan</label>
                    <?php
                    $batas_waktu_value = !empty($kuis['batas_waktu']) ? date('Y-m-d\TH:i', strtotime($kuis['batas_waktu'])) : '';
                    ?>
                    <input type="datetime-local" class="form-control" name="batas_waktu" id="batas_waktu" value="<?php echo $batas_waktu_value; ?>">
                    <small class="form-text text-muted">Kosongkan jika tidak ada batas waktu (untuk mode Klasik).</small>
                </div>
            </div>

            <div id="opsi_interaktif_edit" style="display: <?php echo ($kuis['mode_kuis'] == 'interaktif') ? 'block' : 'none'; ?>;">
                 <div class="alert alert-info">Mode Interaktif cocok untuk Pilihan Ganda. Kuis akan berjalan otomatis dan nilai dihitung langsung.</div>
                 <div class="mb-3">
                    <label class="form-label">Waktu per Soal (detik)</label>
                    <input type="number" class="form-control" name="waktu_per_soal" placeholder="Contoh: 60" value="<?php echo htmlspecialchars($kuis['waktu_per_soal']); ?>">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="acak_soal" value="1" id="acak_soal_edit" <?php echo ($kuis['acak_soal'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="acak_soal_edit">
                        Acak Urutan Soal untuk Setiap Siswa
                    </label>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <a href="detail_kelas.php?id=<?php echo $id_kelas; ?>&tab=soal" class="btn btn-secondary me-2">Kembali</a>
                <button type="submit" name="simpan_perubahan" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modeKuisSelect = document.getElementById('mode_kuis_edit');
    const opsiKlasikDiv = document.getElementById('opsi_klasik_edit');
    const opsiInteraktifDiv = document.getElementById('opsi_interaktif_edit');

    modeKuisSelect.addEventListener('change', function() {
        if(this.value === 'interaktif') {
            opsiKlasikDiv.style.display = 'none';
            opsiInteraktifDiv.style.display = 'block';
        } else {
            opsiKlasikDiv.style.display = 'block';
            opsiInteraktifDiv.style.display = 'none';
        }
    });
});
</script>

<?php
$koneksi->close();
include_once 'templates/footer.php';
?>