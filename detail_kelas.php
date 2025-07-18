<?php
/**
 * File: detail_kelas.php
 * VERSI FINAL DENGAN 6 TAB + FITUR REVIEW & EDIT MATERI + OPSI KUIS INTERAKTIF
 */

require_once 'config/koneksi.php';

// Proteksi halaman
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    header("Location: login.php");
    exit();
}
$id_guru = $_SESSION['user_id'];
$pesan = '';

// Mengambil pesan status dari URL
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_materi') {
        $pesan = "<div class='alert alert-success'>Materi baru berhasil ditambahkan!</div>";
    }
}

// Validasi & Verifikasi Kepemilikan Kelas
if (!isset($_GET['id'])) {
    header("Location: guru_dashboard.php");
    exit();
}
$id_kelas = intval($_GET['id']);
$stmt_kelas = $koneksi->prepare("SELECT nama_kelas, kode_kelas, mata_pelajaran FROM kelas WHERE id = ? AND id_guru = ?");
$stmt_kelas->bind_param("ii", $id_kelas, $id_guru);
$stmt_kelas->execute();
$result_kelas = $stmt_kelas->get_result();
if ($result_kelas->num_rows == 0) {
    header("Location: guru_dashboard.php?error=not_owner");
    exit();
}
$kelas = $result_kelas->fetch_assoc();
$stmt_kelas->close();


// =================================================================//
// === BAGIAN LOGIKA PHP UNTUK MEMPROSES SEMUA AKSI FORM (POST) === //
// =================================================================//
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // BARU: Aksi dari tab Materi (Edit Materi)
    if(isset($_POST['edit_materi'])) {
        $id_materi_edit = intval($_POST['id_materi_edit']);
        $judul_edit = sanitize($koneksi, $_POST['judul_edit']);
        $konten_edit = $_POST['konten_editor_edit'];

        if(!empty($judul_edit) && !empty($konten_edit)) {
            $stmt_edit = $koneksi->prepare("UPDATE materi SET judul = ?, konten = ? WHERE id = ? AND id_kelas = ?");
            $stmt_edit->bind_param("ssii", $judul_edit, $konten_edit, $id_materi_edit, $id_kelas);
            if($stmt_edit->execute()){
                $pesan = "<div class='alert alert-success'>Materi berhasil diperbarui.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal memperbarui materi.</div>";
            }
            $stmt_edit->close();
        } else {
            $pesan = "<div class='alert alert-warning'>Judul dan Konten tidak boleh kosong.</div>";
        }
    }
    // Aksi dari tab Alur Belajar (Simpan Item)
    elseif (isset($_POST['simpan_item_alur'])) {
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
                $pesan = "<div class='alert alert-success'>Item pada alur belajar berhasil diperbarui.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal memperbarui item.</div>";
            }
            $stmt_update->close();
        }
    }
    // Aksi dari tab Tambah Siswa
    elseif (isset($_POST['tambah_siswa_checklist'])) {
        if (!empty($_POST['id_siswa'])) {
            $id_siswa_terpilih = $_POST['id_siswa'];
            $berhasil = 0; $gagal = 0;
            $stmt_tambah = $koneksi->prepare("INSERT IGNORE INTO pendaftaran_kelas (id_siswa, id_kelas) VALUES (?, ?)");
            foreach ($id_siswa_terpilih as $id_siswa) {
                $stmt_tambah->bind_param("ii", $id_siswa, $id_kelas);
                if ($stmt_tambah->execute()) $berhasil++; else $gagal++;
            }
            $stmt_tambah->close();
            $pesan = "<div class='alert alert-success'>Berhasil menambahkan $berhasil siswa. ($gagal siswa mungkin sudah terdaftar).</div>";
        } else {
            $pesan = "<div class='alert alert-warning'>Tidak ada siswa yang dipilih.</div>";
        }
    }
    // Aksi dari tab Tambah Materi
    elseif (isset($_POST['tambah_materi'])) {
        $judul = sanitize($koneksi, $_POST['judul']);
        $konten_final = $_POST['konten_editor'] ?? '';
        if (!empty($judul) && !empty($konten_final)) {
            $stmt_materi = $koneksi->prepare("INSERT INTO materi (id_kelas, judul, tipe_materi, konten) VALUES (?, ?, 'teks', ?)");
            $stmt_materi->bind_param("iss", $id_kelas, $judul, $konten_final);
            if($stmt_materi->execute()){
                $stmt_materi->close();
                header("Location: detail_kelas.php?id=" . $id_kelas . "&tab=materi&status=sukses_materi");
                exit();
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal menyimpan materi.</div>";
                $stmt_materi->close();
            }
        }
    }
    // Aksi dari tab Buat Soal
    elseif (isset($_POST['buat_soal'])) {
        $judul_soal = sanitize($koneksi, $_POST['judul_soal']);
        $deskripsi_soal = sanitize($koneksi, $_POST['deskripsi_soal']);
        $batas_waktu = !empty($_POST['batas_waktu']) ? $_POST['batas_waktu'] : NULL;
        // Ambil data baru
        $mode_kuis = sanitize($koneksi, $_POST['mode_kuis']);
        $waktu_per_soal = ($mode_kuis == 'interaktif' && !empty($_POST['waktu_per_soal'])) ? intval($_POST['waktu_per_soal']) : NULL;
        $acak_soal = (isset($_POST['acak_soal'])) ? 1 : 0;

        if (!empty($judul_soal)) {
            $stmt_soal = $koneksi->prepare("INSERT INTO soal (id_kelas, judul_soal, deskripsi, batas_waktu, mode_kuis, waktu_per_soal, acak_soal) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_soal->bind_param("issssis", $id_kelas, $judul_soal, $deskripsi_soal, $batas_waktu, $mode_kuis, $waktu_per_soal, $acak_soal);
            if ($stmt_soal->execute()) { $pesan = "<div class='alert alert-success'>Kuis baru berhasil dibuat!</div>"; } 
            else { $pesan = "<div class='alert alert-danger'>Gagal membuat kuis. Error: " . $stmt_soal->error . "</div>"; }
            $stmt_soal->close();
        }
    }
    // Aksi dari tab Absensi
    elseif (isset($_POST['buat_pertemuan'])) {
        $judul_pertemuan = sanitize($koneksi, $_POST['judul_pertemuan']);
        $tanggal_pertemuan = sanitize($koneksi, $_POST['tanggal_pertemuan']);
        if (!empty($judul_pertemuan) && !empty($tanggal_pertemuan)) {
            $stmt_pertemuan = $koneksi->prepare("INSERT INTO pertemuan (id_kelas, judul_pertemuan, tanggal_pertemuan) VALUES (?, ?, ?)");
            $stmt_pertemuan->bind_param("iss", $id_kelas, $judul_pertemuan, $tanggal_pertemuan);
            if ($stmt_pertemuan->execute()) { $pesan = "<div class='alert alert-success'>Sesi pertemuan baru berhasil dibuat.</div>"; } 
            else { $pesan = "<div class='alert alert-danger'>Gagal membuat sesi pertemuan.</div>"; }
            $stmt_pertemuan->close();
        }
    }
}


// ==============================================================//
// === BAGIAN LOGIKA PHP UNTUK MEMPROSES AKSI DARI URL (GET) === //
// ==============================================================//
if (isset($_GET['aksi'])) {
    $aksi = $_GET['aksi'];
    // Hapus Siswa
    if ($aksi == 'keluarkan_siswa' && isset($_GET['id_pendaftaran'])) {
        $stmt = $koneksi->prepare("DELETE FROM pendaftaran_kelas WHERE id = ? AND id_kelas = ?");
        $stmt->bind_param("ii", $_GET['id_pendaftaran'], $id_kelas);
        if ($stmt->execute()) $pesan = "<div class='alert alert-success'>Siswa berhasil dikeluarkan.</div>";
        $stmt->close();
    }
    // Hapus Materi
    if ($aksi == 'hapus_materi' && isset($_GET['id_materi'])) {
        $stmt = $koneksi->prepare("DELETE FROM materi WHERE id = ? AND id_kelas = ?");
        $stmt->bind_param("ii", $_GET['id_materi'], $id_kelas);
        if ($stmt->execute()) $pesan = "<div class='alert alert-success'>Materi berhasil dihapus.</div>";
        $stmt->close();
    }
    // Hapus Kuis/Soal
    if ($aksi == 'hapus_soal' && isset($_GET['id_soal'])) {
        $stmt = $koneksi->prepare("DELETE FROM soal WHERE id = ? AND id_kelas = ?");
        $stmt->bind_param("ii", $_GET['id_soal'], $id_kelas);
        if ($stmt->execute()) $pesan = "<div class='alert alert-success'>Kuis berhasil dihapus.</div>";
        $stmt->close();
    }
    // Hapus Pertemuan
    if ($aksi == 'hapus_pertemuan' && isset($_GET['id_pertemuan'])) {
        $stmt = $koneksi->prepare("DELETE FROM pertemuan WHERE id = ? AND id_kelas = ?");
        $stmt->bind_param("ii", $_GET['id_pertemuan'], $id_kelas);
        if ($stmt->execute()) $pesan = "<div class='alert alert-success'>Sesi pertemuan berhasil dihapus.</div>";
        $stmt->close();
    }
}


include_once 'templates/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="guru_dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></li>
  </ol>
</nav>
<div class="d-flex justify-content-between align-items-center mb-2">
    <div>
        <h1 class="mb-0"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></h1>
        <h5 class="text-muted fw-normal"><?php echo htmlspecialchars($kelas['mata_pelajaran']); ?></h5>
    </div>
    <a href="edit_kelas.php?id=<?php echo $id_kelas; ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil-square me-2"></i>Edit Detail Kelas</a>
</div>
<p class="text-muted">Kode Kelas: <strong><?php echo htmlspecialchars($kelas['kode_kelas']); ?></strong></p>
<?php if(!empty($pesan)) echo "<div class='mb-3'>$pesan</div>"; ?>

<ul class="nav nav-tabs mb-4" id="kelasTab" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link" id="absen-tab" data-bs-toggle="tab" data-bs-target="#absen-content" type="button">📅 Daftar Hadir</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" id="materi-tab" data-bs-toggle="tab" data-bs-target="#materi-content" type="button">📚 Materi</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link active" id="alur-belajar-tab" data-bs-toggle="tab" data-bs-target="#alur-belajar-content" type="button">🚀 Alur Belajar</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" id="soal-tab" data-bs-toggle="tab" data-bs-target="#soal-content" type="button">📝 Soal & Penilaian</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" id="siswa-tab" data-bs-toggle="tab" data-bs-target="#siswa-content" type="button">👨‍🎓 Siswa</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" id="progres-tab" data-bs-toggle="tab" data-bs-target="#progres-content" type="button">📊 Progres Siswa</button></li>
</ul>

<div class="tab-content" id="kelasTabContent">
  <div class="tab-pane fade" id="absen-content" role="tabpanel">
    <div class="row g-4">
        <div class="col-md-5">
            <h4>Buat Sesi Pertemuan Baru</h4>
            <div class="card"><div class="card-body"><form action="detail_kelas.php?id=<?php echo $id_kelas; ?>&tab=absen" method="POST"><input type="hidden" name="buat_pertemuan" value="1"><div class="mb-3"><label class="form-label">Judul Pertemuan</label><input type="text" class="form-control" name="judul_pertemuan" required></div><div class="mb-3"><label class="form-label">Tanggal</label><input type="date" class="form-control" name="tanggal_pertemuan" value="<?php echo date('Y-m-d'); ?>" required></div><button type="submit" class="btn btn-primary w-100">Buat Sesi</button></form></div></div>
        </div>
        <div class="col-md-7">
            <h4>Daftar Sesi Pertemuan</h4>
            <div class="list-group">
            <?php
            $stmt_list_pertemuan = $koneksi->prepare("SELECT id, judul_pertemuan, tanggal_pertemuan FROM pertemuan WHERE id_kelas = ? ORDER BY tanggal_pertemuan DESC, waktu_dibuat DESC");
            $stmt_list_pertemuan->bind_param("i", $id_kelas); $stmt_list_pertemuan->execute(); $result_pertemuan = $stmt_list_pertemuan->get_result();
            if ($result_pertemuan->num_rows > 0) {
                while($p = $result_pertemuan->fetch_assoc()) { echo "<div class='list-group-item d-flex justify-content-between align-items-center'><div><h5 class='mb-1'>".htmlspecialchars($p['judul_pertemuan'])."</h5><small>".date('d F Y', strtotime($p['tanggal_pertemuan']))."</small></div><div class='btn-group'><a href='kelola_absensi.php?id_pertemuan=".$p['id']."' class='btn btn-primary btn-sm'>Kelola Absensi</a><a href='detail_kelas.php?id=$id_kelas&tab=absen&aksi=hapus_pertemuan&id_pertemuan=".$p['id']."' class='btn btn-outline-danger btn-sm' onclick='return confirm(\"Yakin?\")'>Hapus</a></div></div>"; }
            } else { echo "<div class='list-group-item text-center'>Belum ada sesi pertemuan.</div>"; } $stmt_list_pertemuan->close();
            ?>
            </div>
        </div>
    </div>
  </div>
  
  <div class="tab-pane fade" id="materi-content" role="tabpanel">
    <div class="row g-4">
        <div class="col-md-7">
            <h4>Tambah Materi Baru</h4>
            <div class="card">
                <div class="card-body">
                    <form id="form-materi-baru" action="detail_kelas.php?id=<?php echo $id_kelas; ?>&tab=materi" method="POST">
                        <input type="hidden" name="tambah_materi" value="1">
                        <div class="mb-3"><label class="form-label fw-bold">Judul Materi</label><input type="text" class="form-control" name="judul" required></div>
                        <div class="mb-3"><label class="form-label">Isi Materi</label><textarea name="konten_editor" id="editor_baru" class="form-control" rows="10"></textarea></div>
                        <button type="submit" class="btn btn-primary w-100">Simpan Materi Baru</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <h4>Daftar Materi</h4>
            <div class="list-group">
                <?php
                // MODIFIKASI: Ambil juga konten untuk modal
                $stmt_list_materi = $koneksi->prepare("SELECT id, judul, konten FROM materi WHERE id_kelas = ? ORDER BY tanggal_dibuat DESC");
                $stmt_list_materi->bind_param("i", $id_kelas); $stmt_list_materi->execute(); $result_materi = $stmt_list_materi->get_result();
                if ($result_materi->num_rows > 0) {
                    while($materi = $result_materi->fetch_assoc()):
                        // Data untuk modal
                        $data_judul = htmlspecialchars($materi['judul'], ENT_QUOTES, 'UTF-8');
                        $data_konten = htmlspecialchars($materi['konten'], ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class='list-group-item d-flex justify-content-between align-items-center'>
                            <span class="flex-grow-1"><?php echo htmlspecialchars($materi['judul']); ?></span>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#reviewMateriModal" data-judul="<?php echo $data_judul; ?>" data-kontenraw='<?php echo $materi['konten']; ?>'>Review</button>
                                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editMateriModal" data-id="<?php echo $materi['id']; ?>" data-judul="<?php echo $data_judul; ?>" data-konten="<?php echo $data_konten; ?>">Edit</button>
                                <a href='detail_kelas.php?id=<?php echo $id_kelas; ?>&tab=materi&aksi=hapus_materi&id_materi=<?php echo $materi['id']; ?>' class='btn btn-danger' onclick='return confirm("Yakin?")'>Hapus</a>
                            </div>
                        </div>
                        <?php
                    endwhile;
                } else {
                    echo "<div class='list-group-item text-center'>Belum ada materi.</div>";
                }
                $stmt_list_materi->close();
                ?>
            </div>
        </div>
    </div>
  </div>

  <div class="tab-pane fade show active" id="alur-belajar-content" role="tabpanel">
    <h4>Kelola Alur Belajar</h4>
    <p>Atur urutan materi dan kuis dengan *drag-and-drop*. Klik pada judul item untuk mengeditnya.</p>
    <div id="pesan-ajax" class="mb-3"></div>
    <div id="daftar-alur-belajar" class="list-group">
        <?php
        $query_alur = "(SELECT 'materi' as tipe, id, judul, waktu_buka, urutan, tanggal_dibuat FROM materi WHERE id_kelas = ?) UNION ALL (SELECT 'kuis' as tipe, id, judul_soal as judul, waktu_buka, urutan, waktu_dibuat as tanggal_dibuat FROM soal WHERE id_kelas = ?) ORDER BY urutan ASC, tanggal_dibuat ASC";
        $stmt_alur = $koneksi->prepare($query_alur); $stmt_alur->bind_param("ii", $id_kelas, $id_kelas); $stmt_alur->execute(); $result_alur = $stmt_alur->get_result();
        $alur_belajar = $result_alur->fetch_all(MYSQLI_ASSOC); $stmt_alur->close();
        if (count($alur_belajar) > 0) {
            foreach ($alur_belajar as $item) {
                $icon = $item['tipe'] == 'materi' ? 'bi-book-fill text-primary' : 'bi-patch-question-fill text-success';
                $waktu_buka_formatted = !empty($item['waktu_buka']) ? date('Y-m-d\TH:i', strtotime($item['waktu_buka'])) : '';
                ?>
                <div class="list-group-item" data-id="<?php echo $item['tipe'] . '_' . $item['id']; ?>">
                    <div class="d-flex w-100 justify-content-between align-items-center">
                        <div class="flex-grow-1"><h5 class="mb-1"><i class="bi <?php echo $icon; ?> me-2"></i><a href="#" class="text-decoration-none text-dark" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $item['tipe'].'-'.$item['id']; ?>"><?php echo htmlspecialchars($item['judul']); ?></a></h5><?php if(!empty($item['waktu_buka'])): ?><small class="text-danger">Akan Buka: <?php echo date('d M Y, H:i', strtotime($item['waktu_buka'])); ?></small><?php endif; ?></div><i class="bi bi-grip-vertical" style="cursor: grab; font-size: 1.2rem;"></i>
                    </div>
                    <div id="collapse-<?php echo $item['tipe'].'-'.$item['id']; ?>" class="collapse mt-3">
                        <form method="POST" action="detail_kelas.php?id=<?php echo $id_kelas; ?>&tab=alur-belajar"><input type="hidden" name="id_item" value="<?php echo $item['id']; ?>"><input type="hidden" name="tipe_item" value="<?php echo $item['tipe']; ?>"><div class="mb-3"><label class="form-label fw-bold">Judul</label><input type="text" name="judul" class="form-control" value="<?php echo htmlspecialchars($item['judul']); ?>" required></div><div class="mb-3"><label class="form-label fw-bold">Jadwalkan Waktu Buka (Opsional)</label><input type="datetime-local" name="waktu_buka" class="form-control" value="<?php echo $waktu_buka_formatted; ?>"></div><button type="submit" name="simpan_item_alur" class="btn btn-primary btn-sm">Simpan Perubahan</button></form>
                    </div>
                </div>
                <?php
            }
        } else { echo "<div class='list-group-item text-center text-muted'>Belum ada materi atau kuis di kelas ini.</div>"; }
        ?>
    </div>
  </div>

  <div class="tab-pane fade" id="soal-content" role="tabpanel">
     <div class="row g-4">
        <div class="col-md-5">
            <h4>Buat Kuis Baru</h4>
            <div class="card"><div class="card-body">
                <form action="detail_kelas.php?id=<?php echo $id_kelas; ?>&tab=soal" method="POST" id="formBuatKuis">
                    <input type="hidden" name="buat_soal" value="1">
                    <div class="mb-3"><label class="form-label fw-bold">Judul Kuis</label><input type="text" class="form-control" name="judul_soal" required></div>
                    <div class="mb-3"><label class="form-label">Deskripsi</label><textarea class="form-control" name="deskripsi_soal" rows="3"></textarea></div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mode Kuis</label>
                        <select class="form-select" name="mode_kuis" id="mode_kuis">
                            <option value="klasik">Klasik (Pengerjaan Standar)</option>
                            <option value="interaktif">Interaktif (Real-time & Otomatis)</option>
                        </select>
                    </div>

                    <div id="opsi_klasik">
                        <div class="mb-3">
                            <label class="form-label">Batas Waktu Keseluruhan</label>
                            <input type="datetime-local" class="form-control" name="batas_waktu">
                            <small class="form-text text-muted">Untuk mode Klasik, ini adalah batas akhir pengumpulan.</small>
                        </div>
                    </div>

                    <div id="opsi_interaktif" style="display: none;">
                        <div class="alert alert-info">Mode Interaktif cocok untuk Pilihan Ganda. Kuis akan berjalan otomatis dan nilai dihitung langsung.</div>
                        <div class="mb-3">
                            <label class="form-label">Waktu per Soal (detik)</label>
                            <input type="number" class="form-control" name="waktu_per_soal" placeholder="Contoh: 60">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="acak_soal" value="1" id="acak_soal">
                            <label class="form-check-label" for="acak_soal">
                                Acak Urutan Soal untuk Setiap Siswa
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Buat Kuis</button>
                </form>
            </div></div>
        </div>
        <div class="col-md-7">
            <h4>Daftar Kuis</h4>
            <div class="list-group">
            <?php
            // Tambahkan `mode_kuis` saat mengambil data
            $stmt_list_soal = $koneksi->prepare("SELECT id, judul_soal, batas_waktu, mode_kuis FROM soal WHERE id_kelas = ? ORDER BY waktu_dibuat DESC");
            $stmt_list_soal->bind_param("i", $id_kelas); $stmt_list_soal->execute(); $result_soal = $stmt_list_soal->get_result();
            if ($result_soal->num_rows > 0) { 
                while($soal_item = $result_soal->fetch_assoc()) { 
                    $badge_mode = ($soal_item['mode_kuis'] == 'interaktif') ? "<span class='badge bg-info'>Interaktif</span>" : "<span class='badge bg-secondary'>Klasik</span>";
                    echo "<div class='list-group-item'><h5>".htmlspecialchars($soal_item['judul_soal'])." ".$badge_mode."</h5>"; 
                    if(!empty($soal_item['batas_waktu'])) echo "<p class='mb-2 text-danger'><small><strong>Batas Waktu:</strong> ".date('d F Y, H:i', strtotime($soal_item['batas_waktu']))."</small></p>"; 
                    echo "<div class='btn-group btn-group-sm'><a href='edit_kuis.php?id_soal=".$soal_item['id']."' class='btn btn-warning'>Edit</a><a href='kelola_pertanyaan.php?id_soal=".$soal_item['id']."' class='btn btn-secondary'>Kelola Pertanyaan</a><a href='penilaian.php?id_soal=".$soal_item['id']."' class='btn btn-success'>Penilaian</a><a href='detail_kelas.php?id=$id_kelas&tab=soal&aksi=hapus_soal&id_soal=".$soal_item['id']."' class='btn btn-danger' onclick='return confirm(\"Yakin?\")'>Hapus</a></div></div>"; 
                }
            } else { echo "<div class='list-group-item text-center'>Belum ada kuis.</div>"; } $stmt_list_soal->close();
            ?>
            </div>
        </div>
    </div>
  </div>

  <div class="tab-pane fade" id="siswa-content" role="tabpanel">
    <div class="row g-4">
        <div class="col-md-7">
            <h4>Pilih Siswa untuk Ditambahkan</h4>
            <form action="detail_kelas.php?id=<?php echo $id_kelas; ?>&tab=siswa" method="POST"><input type="hidden" name="tambah_siswa_checklist" value="1"><div class="card"><div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
            <?php
            $stmt_semua_siswa = $koneksi->prepare("SELECT id, nama_lengkap, email FROM pengguna WHERE peran = 'siswa' AND id NOT IN (SELECT id_siswa FROM pendaftaran_kelas WHERE id_kelas = ?) ORDER BY nama_lengkap");
            $stmt_semua_siswa->bind_param("i", $id_kelas); $stmt_semua_siswa->execute(); $result_semua_siswa = $stmt_semua_siswa->get_result();
            if ($result_semua_siswa->num_rows > 0) { while($siswa = $result_semua_siswa->fetch_assoc()) { echo "<label class='list-group-item'><input class='form-check-input me-2' type='checkbox' name='id_siswa[]' value='".$siswa['id']."'>".htmlspecialchars($siswa['nama_lengkap'])." <small class='text-muted'>(".htmlspecialchars($siswa['email']).")</small></label>"; } } else { echo "<div class='list-group-item text-center text-muted'>Semua siswa sudah ada di kelas ini.</div>"; } $stmt_semua_siswa->close();
            ?>
            </div><?php if ($result_semua_siswa->num_rows > 0): ?><div class="card-footer text-end"><button type="submit" class="btn btn-primary">Tambahkan</button></div><?php endif; ?></div></form>
        </div>
        <div class="col-md-5">
            <h4>Siswa di Kelas Ini</h4>
            <div class="list-group">
            <?php
            $stmt_list_siswa = $koneksi->prepare("SELECT p.id, u.nama_lengkap FROM pendaftaran_kelas p JOIN pengguna u ON p.id_siswa = u.id WHERE p.id_kelas = ? ORDER BY u.nama_lengkap");
            $stmt_list_siswa->bind_param("i", $id_kelas); $stmt_list_siswa->execute(); $result_siswa = $stmt_list_siswa->get_result();
            if ($result_siswa->num_rows > 0) { while($siswa_item = $result_siswa->fetch_assoc()) { echo "<div class='list-group-item d-flex justify-content-between align-items-center'>".htmlspecialchars($siswa_item['nama_lengkap'])."<a href='detail_kelas.php?id=$id_kelas&tab=siswa&aksi=keluarkan_siswa&id_pendaftaran=".$siswa_item['id']."' class='btn btn-outline-danger btn-sm' onclick='return confirm(\"Yakin?\")' title='Keluarkan'>×</a></div>"; } } else { echo "<div class='list-group-item text-center text-muted'>Belum ada siswa di kelas ini.</div>"; } $stmt_list_siswa->close();
            ?>
            </div>
        </div>
    </div>
  </div>

  <div class="tab-pane fade" id="progres-content" role="tabpanel">
    <h4>Pantau Progres Siswa</h4>
    <div class="table-responsive"><table class="table table-bordered table-hover"><thead class="table-dark"><tr><th>Nama Siswa</th><th>Kuis Dikerjakan</th><th>Total Nilai</th><th>Rata-rata Nilai</th></tr></thead><tbody>
    <?php
    $stmt_progres = $koneksi->prepare("SELECT u.id, u.nama_lengkap FROM pengguna u JOIN pendaftaran_kelas pk ON u.id = pk.id_siswa WHERE pk.id_kelas = ? AND u.peran = 'siswa' ORDER BY u.nama_lengkap");
    $stmt_progres->bind_param("i", $id_kelas); $stmt_progres->execute(); $result_progres = $stmt_progres->get_result();
    $total_kuis_dikelas_res = $koneksi->query("SELECT COUNT(id) as total FROM soal WHERE id_kelas = $id_kelas");
    $total_kuis_dikelas = ($total_kuis_dikelas_res) ? $total_kuis_dikelas_res->fetch_assoc()['total'] : 0;
    if($result_progres->num_rows > 0) {
        while($progres_siswa = $result_progres->fetch_assoc()) {
            $id_siswa_progres = $progres_siswa['id'];
            $kuis_dikerjakan_res = $koneksi->query("SELECT COUNT(DISTINCT id_soal) as jumlah FROM jawaban_siswa WHERE id_siswa = $id_siswa_progres AND id_soal IN (SELECT id FROM soal WHERE id_kelas = $id_kelas)");
            $kuis_dikerjakan = ($kuis_dikerjakan_res) ? $kuis_dikerjakan_res->fetch_assoc()['jumlah'] : 0;
            
            // Perbaikan untuk nilai
            $nilai_res = $koneksi->query("SELECT SUM(nilai) as total, COUNT(id) as jumlah_penilaian FROM penilaian WHERE id_siswa = $id_siswa_progres AND id_soal IN (SELECT id FROM soal WHERE id_kelas = $id_kelas)");
            $nilai_data = ($nilai_res) ? $nilai_res->fetch_assoc() : ['total' => 0, 'jumlah_penilaian' => 0];

            $total_nilai = $nilai_data['total'] ?? 0;
            $jumlah_penilaian = $nilai_data['jumlah_penilaian'] ?? 0;
            $rata_rata = $jumlah_penilaian > 0 ? $total_nilai / $jumlah_penilaian : 0;
            echo "<tr><td>" . htmlspecialchars($progres_siswa['nama_lengkap']) . "</td><td>" . $kuis_dikerjakan . " / " . $total_kuis_dikelas . "</td><td>" . number_format($total_nilai, 2) . "</td><td><strong>" . number_format($rata_rata, 2) . "</strong></td></tr>";
        }
    } else { echo "<tr><td colspan='4' class='text-center'>Belum ada siswa di kelas ini.</td></tr>"; } $stmt_progres->close();
    ?>
    </tbody></table></div>
  </div>

</div> <div class="modal fade" id="reviewMateriModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="reviewMateriModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="reviewMateriContent"></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>

<div class="modal fade" id="editMateriModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form action="detail_kelas.php?id=<?php echo $id_kelas; ?>&tab=materi" method="POST" id="form-edit-materi">
        <div class="modal-header"><h5 class="modal-title">Edit Materi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="edit_materi" value="1">
            <input type="hidden" name="id_materi_edit" id="id_materi_edit">
            <div class="mb-3">
                <label class="form-label fw-bold">Judul Materi</label>
                <input type="text" name="judul_edit" id="judul_edit" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Isi Materi</label>
                <textarea name="konten_editor_edit" id="editor_edit" class="form-control" rows="10"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi CKEditor untuk form tambah dan edit
        if (document.getElementById('editor_baru')) { CKEDITOR.replace('editor_baru'); }
        if (document.getElementById('editor_edit')) { CKEDITOR.replace('editor_edit'); }

        // Update textarea sebelum form disubmit
        document.querySelector('#form-materi-baru')?.addEventListener('submit', () => CKEDITOR.instances.editor_baru.updateElement());
        document.querySelector('#form-edit-materi')?.addEventListener('submit', () => CKEDITOR.instances.editor_edit.updateElement());
        
        // Script untuk mengaktifkan tab yang benar
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        const tabToShow = tabParam ? document.querySelector('#' + tabParam + '-tab') : document.querySelector('#alur-belajar-tab');
        if (tabToShow) { new bootstrap.Tab(tabToShow).show(); }

        // Inisialisasi SortableJS untuk Alur Belajar
        const list = document.getElementById('daftar-alur-belajar');
        if(list) {
            const pesanEl = document.getElementById('pesan-ajax');
            new Sortable(list, {
                animation: 150, ghostClass: 'sortable-ghost', handle: '.bi-grip-vertical',
                onEnd: function (evt) {
                    const urutanItem = Array.from(list.querySelectorAll('.list-group-item')).map(item => item.getAttribute('data-id'));
                    fetch('simpan_urutan.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id_kelas: <?php echo $id_kelas; ?>, urutan: urutanItem }) })
                    .then(response => response.json()).then(data => {
                        let alertClass = data.status === 'success' ? 'alert-success' : 'alert-danger';
                        pesanEl.innerHTML = `<div class="alert ${alertClass} alert-dismissible fade show">${data.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
                        setTimeout(() => { if(pesanEl) { pesanEl.innerHTML = ''; } }, 4000);
                    }).catch(error => { pesanEl.innerHTML = `<div class="alert alert-danger">Error: ${error}</div>`; });
                }
            });
        }

        // Script untuk Modal Review Materi
        const reviewMateriModal = document.getElementById('reviewMateriModal');
        if(reviewMateriModal) {
            reviewMateriModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const judul = button.getAttribute('data-judul');
                const konten = button.getAttribute('data-kontenraw'); // Ambil konten HTML mentah
                reviewMateriModal.querySelector('.modal-title').textContent = judul;
                reviewMateriModal.querySelector('.modal-body').innerHTML = konten; // Render sebagai HTML
            });
        }

        // Script untuk Modal Edit Materi
        const editMateriModal = document.getElementById('editMateriModal');
        if(editMateriModal) {
            editMateriModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const judul = button.getAttribute('data-judul');
                const konten = button.getAttribute('data-konten');
                document.getElementById('id_materi_edit').value = id;
                document.getElementById('judul_edit').value = judul;
                CKEDITOR.instances.editor_edit.setData(konten);
            });
        }
        
        // Script untuk menampilkan/menyembunyikan opsi kuis
        const modeKuisSelect = document.getElementById('mode_kuis');
        const opsiKlasikDiv = document.getElementById('opsi_klasik');
        const opsiInteraktifDiv = document.getElementById('opsi_interaktif');
        if(modeKuisSelect) {
            modeKuisSelect.addEventListener('change', function() {
                if(this.value === 'interaktif') {
                    opsiKlasikDiv.style.display = 'none';
                    opsiInteraktifDiv.style.display = 'block';
                } else {
                    opsiKlasikDiv.style.display = 'block';
                    opsiInteraktifDiv.style.display = 'none';
                }
            });
        }
    });
</script>
<?php
$koneksi->close();
include_once 'templates/footer.php';
?>