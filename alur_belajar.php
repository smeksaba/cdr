<?php
/**
 * File: alur_belajar.php
 * Halaman untuk guru mengatur alur belajar (urutan, edit, jadwal).
 */
require_once 'config/koneksi.php';

// Proteksi halaman
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    header("Location: login.php");
    exit();
}
$id_guru = $_SESSION['user_id'];
$pesan = '';

// Validasi ID Kelas
if (!isset($_GET['id_kelas'])) {
    header("Location: guru_dashboard.php");
    exit();
}
$id_kelas = intval($_GET['id_kelas']);

// Verifikasi kepemilikan kelas
$stmt_kelas = $koneksi->prepare("SELECT nama_kelas FROM kelas WHERE id = ? AND id_guru = ?");
$stmt_kelas->bind_param("ii", $id_kelas, $id_guru);
$stmt_kelas->execute();
$result_kelas = $stmt_kelas->get_result();
if ($result_kelas->num_rows == 0) {
    header("Location: guru_dashboard.php?error=not_owner");
    exit();
}
$kelas = $result_kelas->fetch_assoc();
$stmt_kelas->close();

// Logika untuk menyimpan perubahan item (judul & waktu buka)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_item'])) {
    $tipe_item = $_POST['tipe_item'];
    $id_item = intval($_POST['id_item']);
    $judul_baru = sanitize($koneksi, $_POST['judul']);
    $waktu_buka_baru = !empty($_POST['waktu_buka']) ? $_POST['waktu_buka'] : NULL;

    if ($tipe_item === 'materi') {
        $stmt_update = $koneksi->prepare("UPDATE materi SET judul = ?, waktu_buka = ? WHERE id = ? AND id_kelas = ?");
    } elseif ($tipe_item === 'kuis') {
        $stmt_update = $koneksi->prepare("UPDATE soal SET judul_soal = ?, waktu_buka = ? WHERE id = ? AND id_kelas = ?");
    }

    if (isset($stmt_update)) {
        $stmt_update->bind_param("ssii", $judul_baru, $waktu_buka_baru, $id_item, $id_kelas);
        if ($stmt_update->execute()) {
            $pesan = "<div class='alert alert-success'>Item berhasil diperbarui.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal memperbarui item.</div>";
        }
        $stmt_update->close();
    }
}


// Query gabungan untuk mendapatkan semua item alur belajar
$query_alur = "
    (SELECT 'materi' as tipe, id, judul, waktu_buka, urutan, tanggal_dibuat FROM materi WHERE id_kelas = ?)
    UNION ALL
    (SELECT 'kuis' as tipe, id, judul_soal as judul, waktu_buka, urutan, waktu_dibuat as tanggal_dibuat FROM soal WHERE id_kelas = ?)
    ORDER BY urutan ASC, tanggal_dibuat ASC
";
$stmt_alur = $koneksi->prepare($query_alur);
$stmt_alur->bind_param("ii", $id_kelas, $id_kelas);
$stmt_alur->execute();
$result_alur = $stmt_alur->get_result();
$alur_belajar = $result_alur->fetch_all(MYSQLI_ASSOC);
$stmt_alur->close();

include_once 'templates/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="guru_dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Alur Belajar: <?php echo htmlspecialchars($kelas['nama_kelas']); ?></li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-signpost-split-fill me-2"></i>Kelola Alur Belajar</h1>
    <a href="detail_kelas.php?id=<?php echo $id_kelas; ?>" class="btn btn-secondary">Kembali ke Kelola Kelas</a>
</div>

<p>Atur urutan materi dan kuis dengan *drag-and-drop*. Klik pada item untuk mengedit judul dan jadwalnya.</p>
<div id="pesan-ajax" class="mb-3"></div>
<?php if(!empty($pesan)) echo $pesan; ?>

<div id="daftar-alur-belajar" class="list-group">
    <?php foreach ($alur_belajar as $item): ?>
        <?php
            $icon = $item['tipe'] == 'materi' ? 'bi-book-fill text-primary' : 'bi-patch-question-fill text-success';
            $waktu_buka_formatted = !empty($item['waktu_buka']) ? date('Y-m-d\TH:i', strtotime($item['waktu_buka'])) : '';
        ?>
        <div class="list-group-item" data-id="<?php echo $item['tipe'] . '_' . $item['id']; ?>">
            <div class="d-flex w-100 justify-content-between">
                <h5 class="mb-1">
                    <i class="bi <?php echo $icon; ?> me-2"></i>
                    <a href="#" class="text-decoration-none" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $item['id']; ?>">
                        <?php echo htmlspecialchars($item['judul']); ?>
                    </a>
                </h5>
                <small class="text-muted"><i class="bi bi-grip-vertical" style="cursor: grab;"></i></small>
            </div>
            <div id="collapse-<?php echo $item['id']; ?>" class="collapse mt-3">
                <form method="POST" action="alur_belajar.php?id_kelas=<?php echo $id_kelas; ?>">
                    <input type="hidden" name="id_item" value="<?php echo $item['id']; ?>">
                    <input type="hidden" name="tipe_item" value="<?php echo $item['tipe']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Judul</label>
                        <input type="text" name="judul" class="form-control" value="<?php echo htmlspecialchars($item['judul']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Waktu Buka (Opsional)</label>
                        <input type="datetime-local" name="waktu_buka" class="form-control" value="<?php echo $waktu_buka_formatted; ?>">
                    </div>
                    <button type="submit" name="simpan_item" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (count($alur_belajar) == 0): ?>
        <p class="text-center text-muted">Belum ada materi atau kuis di kelas ini. Silakan tambahkan melalui menu "Kelola Kelas".</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('daftar-alur-belajar');
    const pesanEl = document.getElementById('pesan-ajax');

    new Sortable(list, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        handle: '.bi-grip-vertical',
        onEnd: function (evt) {
            const urutanItem = [];
            const items = list.querySelectorAll('.list-group-item');
            items.forEach((item, index) => {
                urutanItem.push(item.getAttribute('data-id'));
            });

            // Kirim urutan baru ke server via AJAX
            fetch('simpan_urutan_baru.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id_kelas: <?php echo $id_kelas; ?>,
                    urutan: urutanItem
                }),
            })
            .then(response => response.json())
            .then(data => {
                let alertClass = data.status === 'success' ? 'alert-success' : 'alert-danger';
                pesanEl.innerHTML = `<div class="alert ${alertClass}">${data.message}</div>`;
                setTimeout(() => { pesanEl.innerHTML = ''; }, 3000);
            })
            .catch(error => {
                pesanEl.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan: ${error}</div>`;
            });
        }
    });
});
</script>

<?php
$koneksi->close();
include_once 'templates/footer.php';
?>