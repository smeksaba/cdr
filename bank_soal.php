<?php
/**
 * File: bank_soal.php
 * VERSI FINAL DENGAN INPUT SKOR
 */

require_once 'config/koneksi.php';

// Proteksi halaman, hanya untuk guru
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    header("Location: login.php");
    exit();
}
$id_guru = $_SESSION['user_id'];
$pesan = '';

// =================================================================//
// ===         BAGIAN LOGIKA PHP UNTUK MEMPROSES SEMUA FORM       === //
// =================================================================//
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Proses Tambah Soal via Import Teks
    if (isset($_POST['import_teks'])) {
        $teks_soal = trim($_POST['teks_soal']); $mapel = sanitize($koneksi, $_POST['mapel']);
        if(empty($teks_soal)){ $pesan = "<div class='alert alert-warning'>Kotak isian soal tidak boleh kosong.</div>"; }
        else {
            $soal_array = preg_split('/\n\s*\n/', $teks_soal); $berhasil = 0; $gagal = 0;
            // Tambahkan `skor` dengan nilai default 10 saat import
            $stmt_pg = $koneksi->prepare("INSERT INTO bank_soal (id_guru, mapel, tipe_soal, soal, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci_jawaban, skor) VALUES (?, ?, 'pilihan_ganda', ?, ?, ?, ?, ?, ?, ?, 10)");
            $stmt_esai = $koneksi->prepare("INSERT INTO bank_soal (id_guru, mapel, tipe_soal, soal) VALUES (?, ?, 'esai', ?)");
            foreach ($soal_array as $blok_soal) {
                $blok_soal = trim($blok_soal); if (empty($blok_soal)) continue;
                $baris_soal = explode("\n", $blok_soal); $baris_pertama = trim($baris_soal[0]);
                if (str_starts_with(strtoupper($baris_pertama), '::ESSAY::')) {
                    $teks_pertanyaan_esai = trim(substr($baris_pertama, 9)); if (!empty($teks_pertanyaan_esai)) { $stmt_esai->bind_param("iss", $id_guru, $mapel, $teks_pertanyaan_esai); if ($stmt_esai->execute()) $berhasil++; else $gagal++; } else { $gagal++; }
                } else {
                    $pertanyaan = ''; $opsi = []; $kunci = '';
                    foreach ($baris_soal as $index => $baris) { $baris = trim($baris); if ($index == 0) { $pertanyaan = $baris; } elseif (preg_match('/^([A-E])\.(.*)/i', $baris, $matches)) { $opsi[strtoupper($matches[1])] = trim($matches[2]); } elseif (preg_match('/^KUNCI\s*:\s*(.*)/i', $baris, $matches)) { $kunci = strtoupper(trim($matches[1])); } }
                    if (!empty($pertanyaan) && count($opsi) >= 2 && in_array($kunci, ['A', 'B', 'C', 'D', 'E'])) {
                        $opsi_a = $opsi['A'] ?? ''; $opsi_b = $opsi['B'] ?? ''; $opsi_c = $opsi['C'] ?? ''; $opsi_d = $opsi['D'] ?? ''; $opsi_e = $opsi['E'] ?? '';
                        $stmt_pg->bind_param("issssssss", $id_guru, $mapel, $pertanyaan, $opsi_a, $opsi_b, $opsi_c, $opsi_d, $opsi_e, $kunci); if ($stmt_pg->execute()) $berhasil++; else $gagal++;
                    } else { $gagal++; }
                }
            }
            $stmt_pg->close(); $stmt_esai->close(); $pesan = "<div class='alert alert-info'>Proses import selesai. Berhasil: $berhasil soal, Gagal: $gagal soal.</div>";
        }
    }
    // Proses Tambah Soal via Input Manual
    elseif (isset($_POST['simpan_manual'])) {
        $mapel = sanitize($koneksi, $_POST['mapel_manual']); $tipe_soal = sanitize($koneksi, $_POST['tipe_soal_manual']); $soal = $_POST['soal_manual'];
        if(empty($mapel) || empty($tipe_soal) || empty($soal)) { $pesan = "<div class='alert alert-warning'>Mapel, Tipe Soal, dan Pertanyaan wajib diisi.</div>"; }
        else {
            if ($tipe_soal === 'pilihan_ganda') {
                $opsi_a = sanitize($koneksi, $_POST['opsi_a']); $opsi_b = sanitize($koneksi, $_POST['opsi_b']); $opsi_c = sanitize($koneksi, $_POST['opsi_c']); $opsi_d = sanitize($koneksi, $_POST['opsi_d']); $opsi_e = sanitize($koneksi, $_POST['opsi_e']); $kunci_jawaban = sanitize($koneksi, $_POST['kunci_jawaban']);
                $skor = intval($_POST['skor']); // Ambil nilai skor
                if(empty($opsi_a) || empty($opsi_b) || empty($kunci_jawaban)) { $pesan = "<div class='alert alert-warning'>Untuk Pilihan Ganda, Opsi A, B dan Kunci Jawaban wajib diisi.</div>"; }
                else {
                    $stmt = $koneksi->prepare("INSERT INTO bank_soal (id_guru, mapel, tipe_soal, soal, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci_jawaban, skor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssssssssi", $id_guru, $mapel, $tipe_soal, $soal, $opsi_a, $opsi_b, $opsi_c, $opsi_d, $opsi_e, $kunci_jawaban, $skor);
                    if ($stmt->execute()) { $pesan = "<div class='alert alert-success'>Soal Pilihan Ganda berhasil disimpan.</div>"; } else { $pesan = "<div class='alert alert-danger'>Gagal menyimpan soal.</div>"; } $stmt->close();
                }
            } elseif ($tipe_soal === 'esai') {
                $stmt = $koneksi->prepare("INSERT INTO bank_soal (id_guru, mapel, tipe_soal, soal) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $id_guru, $mapel, $tipe_soal, $soal);
                if ($stmt->execute()) { $pesan = "<div class='alert alert-success'>Soal Esai berhasil disimpan.</div>"; } else { $pesan = "<div class='alert alert-danger'>Gagal menyimpan soal.</div>"; } $stmt->close();
            }
        }
    }
    // Proses Tambah Soal via Soal Berbasis Konteks
    elseif (isset($_POST['simpan_konteks'])) {
        $mapel = sanitize($koneksi, $_POST['mapel_konteks']); $soal = $_POST['soal_konteks'];
        if(empty($mapel) || empty($soal)) { $pesan = "<div class='alert alert-warning'>Mapel dan Isi Soal tidak boleh kosong.</div>"; }
        else {
            $stmt = $koneksi->prepare("INSERT INTO bank_soal (id_guru, mapel, tipe_soal, soal) VALUES (?, ?, 'esai', ?)");
            $stmt->bind_param("iss", $id_guru, $mapel, $soal);
            if ($stmt->execute()) { $pesan = "<div class='alert alert-success'>Soal Berbasis Konteks berhasil disimpan.</div>"; } else { $pesan = "<div class='alert alert-danger'>Gagal menyimpan soal.</div>"; } $stmt->close();
        }
    }
    // Proses Edit Soal dari Modal
    elseif (isset($_POST['edit_soal_submit'])) {
        $id_soal_edit = intval($_POST['id_soal_edit']);
        $mapel_edit = sanitize($koneksi, $_POST['mapel_edit']);
        $tipe_soal_edit = sanitize($koneksi, $_POST['tipe_soal_edit']);
        $soal_edit = $_POST['soal_edit'];

        if ($tipe_soal_edit === 'pilihan_ganda') {
            $opsi_a_edit = sanitize($koneksi, $_POST['opsi_a_edit']); $opsi_b_edit = sanitize($koneksi, $_POST['opsi_b_edit']); $opsi_c_edit = sanitize($koneksi, $_POST['opsi_c_edit']); $opsi_d_edit = sanitize($koneksi, $_POST['opsi_d_edit']); $opsi_e_edit = sanitize($koneksi, $_POST['opsi_e_edit']); $kunci_edit = sanitize($koneksi, $_POST['kunci_jawaban_edit']);
            $skor_edit = intval($_POST['skor_edit']); // Ambil skor edit
            $stmt_update = $koneksi->prepare("UPDATE bank_soal SET mapel=?, soal=?, opsi_a=?, opsi_b=?, opsi_c=?, opsi_d=?, opsi_e=?, kunci_jawaban=?, skor=? WHERE id=? AND id_guru=?");
            $stmt_update->bind_param("ssssssssiii", $mapel_edit, $soal_edit, $opsi_a_edit, $opsi_b_edit, $opsi_c_edit, $opsi_d_edit, $opsi_e_edit, $kunci_edit, $skor_edit, $id_soal_edit, $id_guru);
        } else { // Esai atau Konteks
            $stmt_update = $koneksi->prepare("UPDATE bank_soal SET mapel=?, soal=? WHERE id=? AND id_guru=?");
            $stmt_update->bind_param("ssii", $mapel_edit, $soal_edit, $id_soal_edit, $id_guru);
        }
        if ($stmt_update->execute()) { $pesan = "<div class='alert alert-success'>Soal berhasil diperbarui.</div>"; } else { $pesan = "<div class='alert alert-danger'>Gagal memperbarui soal.</div>"; }
        $stmt_update->close();
    }
}

// Logika Hapus Soal
if(isset($_GET['aksi']) && $_GET['aksi'] == 'hapus' && isset($_GET['id_soal'])){
    $id_soal_hapus = intval($_GET['id_soal']);
    $stmt_hapus = $koneksi->prepare("DELETE FROM bank_soal WHERE id = ? AND id_guru = ?");
    $stmt_hapus->bind_param("ii", $id_soal_hapus, $id_guru);
    if($stmt_hapus->execute()){ $pesan = "<div class='alert alert-success'>Soal berhasil dihapus.</div>"; }
    $stmt_hapus->close();
}

// Ambil semua data soal untuk ditampilkan di tabel (tambahkan `skor`)
$query_daftar = "SELECT id, mapel, tipe_soal, soal, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci_jawaban, skor FROM bank_soal WHERE id_guru = ? ORDER BY dibuat_pada DESC";
$stmt_daftar = $koneksi->prepare($query_daftar);
$stmt_daftar->bind_param("i", $id_guru); $stmt_daftar->execute(); $result_daftar = $stmt_daftar->get_result();

include_once 'templates/header.php';
?>
<script src="https://cdn.ckeditor.com/4.22.1/full-all/ckeditor.js"></script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-archive-fill me-2"></i>Bank Soal Anda</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahSoalModal"><i class="bi bi-plus-lg me-2"></i>Tambah Soal Baru</button>
</div>

<?php if(!empty($pesan)) echo $pesan; ?>

<div class="card shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle">
    <thead class="table-light"><tr><th style="width: 5%;">No</th><th>Mata Pelajaran</th><th>Tipe</th><th style="width: 40%;">Pertanyaan</th><th>Skor</th><th style="width: 20%;">Aksi</th></tr></thead>
    <tbody>
        <?php
        if ($result_daftar->num_rows > 0) {
            $no = 1;
            while ($soal = $result_daftar->fetch_assoc()) {
                $data_soal_json = htmlspecialchars(json_encode($soal), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($soal['mapel']); ?></td>
                    <td><span class='badge bg-secondary'><?php echo str_replace('_', ' ', $soal['tipe_soal']); ?></span></td>
                    <td><?php echo htmlspecialchars(strip_tags(substr($soal['soal'], 0, 100))); ?>...</td>
                    <td><span class="badge bg-success"><?php echo ($soal['tipe_soal'] == 'pilihan_ganda') ? $soal['skor'] : '-'; ?></span></td>
                    <td>
                        <div class='btn-group btn-group-sm'>
                            <button class='btn btn-info' data-bs-toggle='modal' data-bs-target='#viewSoalModal' data-soal='<?php echo $data_soal_json; ?>'>View</button>
                            <button class='btn btn-warning' data-bs-toggle='modal' data-bs-target='#editSoalModal' data-soal='<?php echo $data_soal_json; ?>'>Edit</button>
                            <a href='bank_soal.php?aksi=hapus&id_soal=<?php echo $soal['id']; ?>' class='btn btn-danger' onclick='return confirm("Yakin ingin menghapus soal ini secara permanen?")'>Delete</a>
                        </div>
                    </td>
                </tr>
                <?php
            }
        } else { echo "<tr><td colspan='6' class='text-center'>Bank soal Anda masih kosong.</td></tr>"; }
        $stmt_daftar->close();
        ?>
    </tbody>
</table></div></div></div>

<div class="modal fade" id="tambahSoalModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Tambah Soal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><ul class="nav nav-tabs" id="myTab" role="tablist"><li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#import-content">Import dari Teks</button></li><li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#manual-content">Input Manual</button></li><li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#konteks-content">Soal Berbasis Konteks</button></li></ul><div class="tab-content pt-3"><div class="tab-pane fade show active" id="import-content" role="tabpanel"><form action="bank_soal.php" method="POST"><input type="hidden" name="import_teks" value="1"><div class="mb-3"><label class="form-label">Mata Pelajaran</label><input type="text" class="form-control" name="mapel"></div><div class="alert alert-warning"><h5 class="alert-heading">Contoh Format Wajib!</h5><p>Salin soal dari Word/PDF dan tempel di bawah. Pastikan formatnya sama persis seperti contoh. Pisahkan setiap soal dengan <strong>satu baris kosong</strong>.</p><hr><strong>Format Pilihan Ganda (A, B, C, D, E):</strong><pre>Ibukota negara Indonesia adalah...\nA. Surabaya\nB. Bandung\nC. Jakarta\nD. Medan\nE. Makassar\nKUNCI: C\n\nSiapakah presiden pertama Republik Indonesia?\nA. Soeharto\nB. B.J. Habibie\nC. Megawati Soekarnoputri\nD. Soekarno\nE. Joko Widodo\nKUNCI: D</pre><strong>Format Esai:</strong><pre>::ESSAY:: Jelaskan apa yang dimaksud dengan pasar digital!</pre></div><div class="mb-3"><label class="form-label">Tempel Teks Soal:</label><textarea name="teks_soal" class="form-control" rows="15" required></textarea></div><div class="text-end"><button type="submit" class="btn btn-primary">Import Soal</button></div></form></div><div class="tab-pane fade" id="manual-content" role="tabpanel"><form action="bank_soal.php" method="POST" id="form-manual"><input type="hidden" name="simpan_manual" value="1"><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Mata Pelajaran</label><input type="text" name="mapel_manual" class="form-control" required></div><div class="col-md-6 mb-3"><label class="form-label">Tipe Soal</label><select name="tipe_soal_manual" id="tipe_soal_manual" class="form-select" required><option value="" disabled selected>Pilih Tipe</option><option value="pilihan_ganda">Pilihan Ganda</option><option value="esai">Esai</option></select></div></div><div class="mb-3"><label class="form-label">Pertanyaan</label><textarea name="soal_manual" id="editor_manual_tambah" class="form-control" rows="8"></textarea></div><div id="kolom-pilihan-ganda-tambah" style="display: none;"><hr><div class="row g-3"><div class="col-md-6"><label class="form-label">Opsi A</label><input type="text" name="opsi_a" class="form-control"></div><div class="col-md-6"><label class="form-label">Opsi B</label><input type="text" name="opsi_b" class="form-control"></div><div class="col-md-6"><label class="form-label">Opsi C</label><input type="text" name="opsi_c" class="form-control"></div><div class="col-md-6"><label class="form-label">Opsi D</label><input type="text" name="opsi_d" class="form-control"></div><div class="col-md-6"><label class="form-label">Opsi E</label><input type="text" name="opsi_e" class="form-control"></div><div class="col-md-4"><label class="form-label">Kunci Jawaban</label><select name="kunci_jawaban" class="form-select"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option><option value="E">E</option></select></div><div class="col-md-2"><label class="form-label">Skor</label><input type="number" name="skor" class="form-control" value="10" required></div></div></div><div class="text-end mt-4"><button type="submit" class="btn btn-primary">Simpan Soal</button></div></form></div><div class="tab-pane fade" id="konteks-content" role="tabpanel"><form action="bank_soal.php" method="POST" id="form-konteks"><input type="hidden" name="simpan_konteks" value="1"><div class="mb-3"><label class="form-label">Mata Pelajaran</label><input type="text" name="mapel_konteks" class="form-control" required></div><div class="alert alert-info">Gunakan menu ini untuk soal kompleks (studi kasus, naskah panjang, tabel, dll). Semua konten di bawah akan disimpan sebagai satu soal esai.</div><div class="mb-3"><label class="form-label">Isi Soal Lengkap (termasuk naskah, tabel, dan pertanyaan)</label><textarea name="soal_konteks" id="editor_konteks_tambah" class="form-control" rows="15"></textarea></div><div class="text-end mt-4"><button type="submit" class="btn btn-primary">Simpan Soal</button></div></form></div></div></div></div></div></div>

<div class="modal fade" id="viewSoalModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">View Soal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><h6 id="viewMapel" class="text-muted"></h6><hr><div id="viewSoalContent"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div></div></div></div>

<div class="modal fade" id="editSoalModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><form action="bank_soal.php" method="POST" id="form-edit"><div class="modal-header"><h5 class="modal-title">Edit Soal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="edit_soal_submit" value="1"><input type="hidden" name="id_soal_edit" id="id_soal_edit"><input type="hidden" name="tipe_soal_edit" id="tipe_soal_edit"><div class="mb-3"><label class="form-label">Mata Pelajaran</label><input type="text" name="mapel_edit" id="mapel_edit" class="form-control" required></div><div class="mb-3"><label class="form-label">Pertanyaan</label><textarea name="soal_edit" id="editor_edit" class="form-control" rows="8"></textarea></div><div id="kolom-pilihan-ganda-edit" style="display: none;"><hr><div class="row g-3"><div class="col-md-6"><label class="form-label">Opsi A</label><input type="text" name="opsi_a_edit" id="opsi_a_edit" class="form-control"></div><div class="col-md-6"><label class="form-label">Opsi B</label><input type="text" name="opsi_b_edit" id="opsi_b_edit" class="form-control"></div><div class="col-md-6"><label class="form-label">Opsi C</label><input type="text" name="opsi_c_edit" id="opsi_c_edit" class="form-control"></div><div class="col-md-6"><label class="form-label">Opsi D</label><input type="text" name="opsi_d_edit" id="opsi_d_edit" class="form-control"></div><div class="col-md-6"><label class="form-label">Opsi E</label><input type="text" name="opsi_e_edit" id="opsi_e_edit" class="form-control"></div><div class="col-md-4"><label class="form-label">Kunci Jawaban</label><select name="kunci_jawaban_edit" id="kunci_jawaban_edit" class="form-select"></select></div><div class="col-md-2"><label class="form-label">Skor</label><input type="number" name="skor_edit" id="skor_edit" class="form-control" required></div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div></form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Inisialisasi semua editor CKEditor
    ['editor_manual_tambah', 'editor_konteks_tambah', 'editor_edit'].forEach(id => {
        if (document.getElementById(id)) {
            CKEDITOR.replace(id, {
                fullPage: false, allowedContent: true, height: 250
            });
        }
    });

    // Update textarea sebelum form disubmit
    document.querySelector('#form-manual').addEventListener('submit', () => CKEDITOR.instances.editor_manual_tambah.updateElement());
    document.querySelector('#form-konteks').addEventListener('submit', () => CKEDITOR.instances.editor_konteks_tambah.updateElement());
    document.querySelector('#form-edit').addEventListener('submit', () => CKEDITOR.instances.editor_edit.updateElement());

    // Event listener untuk dropdown Tipe Soal di form TAMBAH
    const tipeSoalTambah = document.getElementById('tipe_soal_manual');
    if(tipeSoalTambah) {
        tipeSoalTambah.addEventListener('change', function() {
            document.getElementById('kolom-pilihan-ganda-tambah').style.display = (this.value === 'pilihan_ganda') ? 'block' : 'none';
        });
    }

    // Event listener untuk modal VIEW
    const viewModal = document.getElementById('viewSoalModal');
    if(viewModal) {
        viewModal.addEventListener('show.bs.modal', function(event) {
            const data = JSON.parse(event.relatedTarget.getAttribute('data-soal'));
            viewModal.querySelector('#viewMapel').textContent = 'Mata Pelajaran: ' + data.mapel;
            let content = `<div>${data.soal}</div>`;
            if (data.tipe_soal === 'pilihan_ganda') {
                content += '<hr><h6>Opsi Jawaban:</h6><ul class="list-group">';
                ['a', 'b', 'c', 'd', 'e'].forEach(opt => {
                    if(data['opsi_' + opt]) {
                        let itemClass = (data.kunci_jawaban.toUpperCase() === opt.toUpperCase()) ? 'list-group-item-success' : '';
                        content += `<li class="list-group-item ${itemClass}"><b>${opt.toUpperCase()}:</b> ${data['opsi_' + opt]}</li>`;
                    }
                });
                content += '</ul>';
                content += `<div class="mt-3"><strong>Skor:</strong> <span class="badge bg-success">${data.skor}</span></div>`;
            }
            viewModal.querySelector('#viewSoalContent').innerHTML = content;
        });
    }

    // Event listener untuk modal EDIT
    const editModal = document.getElementById('editSoalModal');
    if(editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const data = JSON.parse(event.relatedTarget.getAttribute('data-soal'));
            editModal.querySelector('#id_soal_edit').value = data.id;
            editModal.querySelector('#mapel_edit').value = data.mapel;
            editModal.querySelector('#tipe_soal_edit').value = data.tipe_soal;
            CKEDITOR.instances.editor_edit.setData(data.soal);

            const kolomPGEdit = document.getElementById('kolom-pilihan-ganda-edit');
            if (data.tipe_soal === 'pilihan_ganda') {
                kolomPGEdit.style.display = 'block';
                ['a', 'b', 'c', 'd', 'e'].forEach(opt => {
                    document.getElementById('opsi_' + opt + '_edit').value = data['opsi_' + opt] || '';
                });
                document.getElementById('skor_edit').value = data.skor; // Set skor
                const kunciSelect = document.getElementById('kunci_jawaban_edit');
                kunciSelect.innerHTML = '';
                ['A', 'B', 'C', 'D', 'E'].forEach(opt => {
                    if(data['opsi_' + opt.toLowerCase()]){
                        const option = new Option(opt, opt, false, data.kunci_jawaban === opt);
                        kunciSelect.add(option);
                    }
                });
            } else {
                kolomPGEdit.style.display = 'none';
            }
        });
    }
});
</script>

<?php
$koneksi->close();
include_once 'templates/footer.php';
?>