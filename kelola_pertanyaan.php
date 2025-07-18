<?php
/**
 * File: kelola_pertanyaan.php
 * VERSI PENUH DAN FINAL - DENGAN PENGECEKAN SOAL DUPLIKAT & PERBAIKAN SCROLL
 * Halaman untuk mengelola isi pertanyaan dalam sebuah kuis,
 * termasuk mengimpor dari bank soal.
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

// Ambil data kuis untuk verifikasi kepemilikan guru
$stmt_kuis = $koneksi->prepare("SELECT s.judul_soal, k.id as id_kelas FROM soal s JOIN kelas k ON s.id_kelas = k.id WHERE s.id = ? AND k.id_guru = ?");
if(!$stmt_kuis) { die("Error: Gagal menyiapkan query verifikasi kuis."); }
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

// Logika untuk memproses form import dari bank soal
if (isset($_POST['import_dari_bank'])) {
    if (!empty($_POST['id_bank_soal'])) {
        $id_soal_terpilih = $_POST['id_bank_soal'];
        $berhasil = 0;
        $gagal = 0;
        $stmt_import = $koneksi->prepare("INSERT INTO kuis_soal (id_kuis, id_bank_soal) VALUES (?, ?)");
        if ($stmt_import) {
            foreach ($id_soal_terpilih as $id_bank_soal) {
                $id_bank_soal_int = intval($id_bank_soal);
                // Mencegah duplikasi dengan @ (supress error)
                $stmt_import->bind_param("ii", $id_kuis, $id_bank_soal_int);
                if (@$stmt_import->execute()) {
                    $berhasil++;
                } else {
                    $gagal++;
                }
            }
            $stmt_import->close();
            if ($berhasil > 0) {
                 $pesan = "<div class='alert alert-success'>Berhasil mengimpor $berhasil soal.</div>";
            }
            if ($gagal > 0) { // Pesan ini sekarang tidak terlalu relevan karena duplikat sudah dicegah di UI, tapi kita biarkan sebagai fallback
                $pesan .= "<div class='alert alert-warning'>$gagal soal gagal diimpor (kemungkinan sudah ada di kuis ini).</div>";
            }
        }
    } else {
        $pesan = "<div class='alert alert-warning'>Tidak ada soal yang dipilih untuk diimpor.</div>";
    }
}

// Logika untuk menghapus pertanyaan dari kuis
if (isset($_GET['aksi']) && $_GET['aksi'] == 'hapus' && isset($_GET['id_kuis_soal'])) {
    $id_kuis_soal = intval($_GET['id_kuis_soal']);
    $stmt_hapus = $koneksi->prepare("DELETE FROM kuis_soal WHERE id = ? AND id_kuis = ?");
    if ($stmt_hapus) {
        $stmt_hapus->bind_param("ii", $id_kuis_soal, $id_kuis);
        if ($stmt_hapus->execute()) {
            $pesan = "<div class='alert alert-info'>Pertanyaan berhasil dihapus dari kuis ini.</div>";
        }
        $stmt_hapus->close();
    }
}

include_once 'templates/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="guru_dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="detail_kelas.php?id=<?php echo $id_kelas; ?>">Kelola Kelas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Kelola Pertanyaan Kuis</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>Kelola Pertanyaan</h1>
        <h5 class="text-muted">Untuk Kuis: "<?php echo htmlspecialchars($kuis['judul_soal']); ?>"</h5>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importSoalModal">
        <i class="bi bi-journal-plus me-2"></i>Import dari Bank Soal
    </button>
</div>

<?php if(!empty($pesan)) echo $pesan; ?>

<div class="card">
    <div class="card-header">Daftar Pertanyaan dalam Kuis Ini</div>
    <div class="card-body">
        <ul class="list-group">
            <?php
            $stmt_list = $koneksi->prepare("SELECT ks.id, bs.soal, bs.tipe_soal FROM kuis_soal ks JOIN bank_soal bs ON ks.id_bank_soal = bs.id WHERE ks.id_kuis = ? ORDER BY ks.id");
            if ($stmt_list) {
                $stmt_list->bind_param("i", $id_kuis);
                $stmt_list->execute();
                $result_list = $stmt_list->get_result();
                if ($result_list->num_rows > 0) {
                    $no_urut = 1;
                    while($item = $result_list->fetch_assoc()) {
                        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                        echo "<div><strong>" . $no_urut++ . ".</strong> <span class='badge bg-info me-2'>".$item['tipe_soal']."</span> ".htmlspecialchars($item['soal'])."</div>";
                        echo "<a href='kelola_pertanyaan.php?id_soal=$id_kuis&aksi=hapus&id_kuis_soal=".$item['id']."' class='btn btn-sm btn-outline-danger' onclick='return confirm(\"Yakin ingin menghapus soal ini dari kuis?\")'>Hapus</a>";
                        echo "</li>";
                    }
                } else {
                    echo "<li class='list-group-item text-center'>Belum ada pertanyaan di kuis ini. Silakan impor dari bank soal.</li>";
                }
                $stmt_list->close();
            } else {
                 echo "<li class='list-group-item text-center text-danger'>Gagal memuat daftar pertanyaan.</li>";
            }
            ?>
        </ul>
    </div>
</div>


<div class="modal fade" id="importSoalModal" tabindex="-1" aria-labelledby="importSoalModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl"> 
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="importSoalModalLabel">Pilih Soal dari Bank Soal Anda</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="kelola_pertanyaan.php?id_soal=<?php echo $id_kuis; ?>" method="POST">
        <input type="hidden" name="import_dari_bank" value="1">
        <div class="modal-body">
            <p>Pilih soal-soal yang ingin Anda masukkan ke dalam kuis ini.</p>
            <div id="soal-list-container" style="max-height: 60vh; overflow-y: auto; border: 1px solid #dee2e6; padding: 1rem;">
                <div class="list-group">
                    <?php
                    // ======================= PERUBAHAN DIMULAI DI SINI =======================
                    
                    // 1. Ambil ID semua soal yang sudah ada di kuis ini
                    $existing_soal_ids = [];
                    $stmt_existing = $koneksi->prepare("SELECT id_bank_soal FROM kuis_soal WHERE id_kuis = ?");
                    $stmt_existing->bind_param("i", $id_kuis);
                    $stmt_existing->execute();
                    $result_existing = $stmt_existing->get_result();
                    while ($row = $result_existing->fetch_assoc()) {
                        $existing_soal_ids[] = $row['id_bank_soal'];
                    }
                    $stmt_existing->close();

                    // 2. Ambil SEMUA soal dari bank soal milik guru
                    $query_bank = "SELECT id, soal, tipe_soal, mapel FROM bank_soal WHERE id_guru = ? ORDER BY mapel, dibuat_pada DESC";
                    $stmt_bank = $koneksi->prepare($query_bank);

                    if ($stmt_bank === false) {
                        echo "<div class='list-group-item text-center text-danger'><strong>Error:</strong> Gagal memuat data bank soal.</div>";
                    } else {
                        $stmt_bank->bind_param("i", $id_guru);
                        $stmt_bank->execute();
                        $result_bank = $stmt_bank->get_result();
                        if ($result_bank->num_rows > 0) {
                            while($soal_bank = $result_bank->fetch_assoc()) {
                                // 3. Logika untuk menandai soal duplikat
                                $is_existing = in_array($soal_bank['id'], $existing_soal_ids);
                                $label_class = $is_existing ? 'list-group-item-danger' : '';
                                $input_disabled = $is_existing ? 'disabled' : '';
                                $extra_info = $is_existing ? "<span class='badge bg-dark float-end'>Soal ada yang sama (sudah ada di kuis ini)</span>" : '';

                                echo "<label class='list-group-item {$label_class}'>";
                                echo "<input class='form-check-input me-2' type='checkbox' name='id_bank_soal[]' value='{$soal_bank['id']}' {$input_disabled}>";
                                echo "<span class='badge bg-secondary me-2'>" . htmlspecialchars($soal_bank['tipe_soal']) . "</span>";
                                if (!empty($soal_bank['mapel'])) {
                                    echo "<strong>[" . htmlspecialchars($soal_bank['mapel']) . "]</strong> ";
                                }
                                echo htmlspecialchars($soal_bank['soal']);
                                echo $extra_info; // Tampilkan pemberitahuan jika duplikat
                                echo "</label>";
                            }
                        } else {
                            echo "<div class='list-group-item text-center text-muted'>Bank soal Anda masih kosong.</div>";
                        }
                        $stmt_bank->close();
                    }
                    // ======================== PERUBAHAN BERAKHIR DI SINI ========================
                    ?>
                </div>
            </div>
        </div>
        <div class="modal-footer justify-content-between">
            <div>
                <button type="button" id="scrollToTopBtn" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-up"></i> Scroll ke Atas
                </button>
                <button type="button" id="scrollToBottomBtn" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-down"></i> Scroll ke Bawah
                </button>
            </div>
            <div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Import Soal Terpilih</button>
            </div>
        </div>
      </form>
    </div>
  </div>
</div>


<?php
$koneksi->close();
include_once 'templates/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const importModal = document.getElementById('importSoalModal');
    if (importModal) {
        const soalContainer = importModal.querySelector('#soal-list-container');
        const scrollToTopBtn = importModal.querySelector('#scrollToTopBtn');
        const scrollToBottomBtn = importModal.querySelector('#scrollToBottomBtn');

        scrollToTopBtn.addEventListener('click', function() {
            soalContainer.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        scrollToBottomBtn.addEventListener('click', function() {
            soalContainer.scrollTo({
                top: soalContainer.scrollHeight,
                behavior: 'smooth'
            });
        });
    }
});
</script>