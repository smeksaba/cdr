<?php
/**
 * File: get_jawaban_siswa.php
 * File pembantu (helper) untuk mengambil jawaban detail seorang siswa
 * dan menampilkannya dalam format HTML.
 */

require_once 'config/koneksi.php';

// Proteksi, pastikan parameter ada
if (!isset($_GET['id_kuis']) || !isset($_GET['id_siswa'])) {
    die("<div class='alert alert-danger'>Parameter tidak lengkap.</div>");
}

$id_kuis = intval($_GET['id_kuis']);
$id_siswa = intval($_GET['id_siswa']);

// Query untuk mengambil semua pertanyaan di kuis ini dan jawaban siswa terkait
$query = "
    SELECT 
        bs.soal, 
        bs.tipe_soal, 
        bs.opsi_a, bs.opsi_b, bs.opsi_c, bs.opsi_d, bs.opsi_e,
        bs.kunci_jawaban,
        js.jawaban as jawaban_siswa
    FROM kuis_soal ks
    JOIN bank_soal bs ON ks.id_bank_soal = bs.id
    LEFT JOIN jawaban_siswa js ON ks.id_bank_soal = js.id_pertanyaan AND js.id_siswa = ?
    WHERE ks.id_kuis = ?
    ORDER BY ks.id
";

$stmt = $koneksi->prepare($query);
if(!$stmt) die("<div class='alert alert-danger'>Query Gagal: " . $koneksi->error . "</div>");

$stmt->bind_param("ii", $id_siswa, $id_kuis);
$stmt->execute();
$result = $stmt->get_result();

$skor_otomatis = 0;
$jumlah_pg = 0;

// Mulai membuat output HTML
$output = '';

while ($row = $result->fetch_assoc()) {
    $output .= "<div class='mb-4 p-3 border rounded'>";
    $output .= "<h5>" . htmlspecialchars($row['soal']) . "</h5>";

    if ($row['tipe_soal'] == 'pilihan_ganda') {
        $jumlah_pg++;
        $jawaban_siswa = strtoupper(trim($row['jawaban_siswa']));
        $kunci_jawaban = strtoupper(trim($row['kunci_jawaban']));
        
        $output .= "<ul class='list-group'>";
        foreach (['A', 'B', 'C', 'D', 'E'] as $opsi) {
            $class = '';
            $icon = '';
            if ($opsi == $kunci_jawaban) {
                $class = 'list-group-item-success'; // Jawaban benar
                $icon = ' ✔️ (Kunci Jawaban)';
            }
            if ($opsi == $jawaban_siswa && $jawaban_siswa != $kunci_jawaban) {
                $class = 'list-group-item-danger'; // Jawaban siswa yang salah
                $icon = ' ❌ (Jawaban Siswa)';
            } elseif ($opsi == $jawaban_siswa && $jawaban_siswa == $kunci_jawaban) {
                $icon = ' ✔️ (Jawaban Siswa & Kunci)';
            }

            $output .= "<li class='list-group-item $class'>" . $opsi . ". " . htmlspecialchars($row['opsi_' . strtolower($opsi)]) . $icon . "</li>";
        }
        $output .= "</ul>";
        
        if ($jawaban_siswa == $kunci_jawaban) {
            $skor_otomatis++;
        }

    } elseif ($row['tipe_soal'] == 'esai') {
        $output .= "<h6>Jawaban Esai Siswa:</h6>";
        $output .= "<div class='p-3 bg-light border rounded' style='white-space: pre-wrap;'>" . htmlspecialchars($row['jawaban_siswa']) . "</div>";
        $output .= "<div class='mt-2'><label class='form-label'>Beri Nilai Esai:</label><input type='number' class='form-control form-control-sm nilai-esai' style='width:100px;'></div>";
    }
    $output .= "</div>";
}
$stmt->close();
$koneksi->close();

// Hitung skor akhir
$skor_akhir_pg = ($jumlah_pg > 0) ? ($skor_otomatis / $jumlah_pg) * 100 : 0;

// Form untuk submit nilai
$output .= "
<hr>
<h4>Form Penilaian Akhir</h4>
<form action='penilaian.php?id_soal=$id_kuis' method='POST'>
    <input type='hidden' name='id_siswa' value='$id_siswa'>
    <p>Skor Pilihan Ganda (Otomatis): <strong>" . number_format($skor_akhir_pg, 2) . "</strong></p>
    <div class='mb-3'>
        <label for='nilai_final' class='form-label'><strong>Input Nilai Final (0-100)</strong></label>
        <input type='number' step='0.01' class='form-control' id='nilai_final' name='nilai_final' value='" . number_format($skor_akhir_pg, 2) . "' required>
        <small class='form-text text-muted'>Anda bisa menyesuaikan skor akhir setelah mempertimbangkan nilai esai.</small>
    </div>
    <div class='mb-3'>
        <label for='komentar' class='form-label'>Komentar / Feedback untuk Siswa</label>
        <textarea class='form-control' name='komentar' id='komentar' rows='3'></textarea>
    </div>
    <div class='modal-footer'>
        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Tutup</button>
        <button type='submit' name='simpan_nilai' class='btn btn-success'>Simpan Nilai</button>
    </div>
</form>";

// Kirim output HTML kembali ke halaman utama
echo $output;
?>