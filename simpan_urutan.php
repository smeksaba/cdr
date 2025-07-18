<?php
/**
 * File: simpan_urutan.php
 * Aksi backend untuk menyimpan urutan baru dari alur belajar via AJAX.
 */

require_once 'config/koneksi.php';
header('Content-Type: application/json');

// Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit();
}
$id_guru = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id_kelas']) || !isset($input['urutan']) || !is_array($input['urutan'])) {
    echo json_encode(['status' => 'error', 'message' => 'Data yang dikirim tidak valid.']);
    exit();
}

$id_kelas = intval($input['id_kelas']);
$urutan_items = $input['urutan'];

// Verifikasi kepemilikan kelas
$stmt_verif = $koneksi->prepare("SELECT id FROM kelas WHERE id = ? AND id_guru = ?");
$stmt_verif->bind_param("ii", $id_kelas, $id_guru);
$stmt_verif->execute();
if($stmt_verif->get_result()->num_rows == 0){
    echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses ke kelas ini.']);
    exit();
}
$stmt_verif->close();


$koneksi->begin_transaction();
try {
    $stmt_materi = $koneksi->prepare("UPDATE materi SET urutan = ? WHERE id = ? AND id_kelas = ?");
    $stmt_soal = $koneksi->prepare("UPDATE soal SET urutan = ? WHERE id = ? AND id_kelas = ?");

    foreach ($urutan_items as $index => $item_id_string) {
        $urutan_baru = $index + 1;
        list($tipe, $id) = explode('_', $item_id_string);
        $id = intval($id);

        if ($tipe === 'materi') {
            $stmt_materi->bind_param("iii", $urutan_baru, $id, $id_kelas);
            $stmt_materi->execute();
        } elseif ($tipe === 'kuis') {
            $stmt_soal->bind_param("iii", $urutan_baru, $id, $id_kelas);
            $stmt_soal->execute();
        }
    }
    
    $stmt_materi->close();
    $stmt_soal->close();
    $koneksi->commit();
    
    echo json_encode(['status' => 'success', 'message' => 'Urutan alur belajar berhasil disimpan!']);

} catch (Exception $e) {
    $koneksi->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan urutan: ' . $e->getMessage()]);
}
$koneksi->close();
?>