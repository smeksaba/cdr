<?php
/**
 * File: update_urutan.php
 * Endpoint AJAX untuk memproses pembaruan urutan alur belajar.
 */

require_once 'config/koneksi.php';

// Pastikan hanya guru yang bisa akses
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id_kelas']) && isset($data['urutan'])) {
    $id_kelas = intval($data['id_kelas']);
    $urutan_items = $data['urutan'];
    
    // Mulai transaksi
    $koneksi->begin_transaction();
    
    try {
        $stmt_materi = $koneksi->prepare("UPDATE materi SET urutan = ? WHERE id = ? AND id_kelas = ?");
        $stmt_soal = $koneksi->prepare("UPDATE soal SET urutan = ? WHERE id = ? AND id_kelas = ?");

        foreach ($urutan_items as $item) {
            $id = intval($item['id']);
            $urutan = intval($item['urutan']);
            $tipe = $item['tipe'];
            
            if ($tipe == 'materi') {
                $stmt_materi->bind_param("iii", $urutan, $id, $id_kelas);
                $stmt_materi->execute();
            } elseif ($tipe == 'kuis') {
                $stmt_soal->bind_param("iii", $urutan, $id, $id_kelas);
                $stmt_soal->execute();
            }
        }
        
        $stmt_materi->close();
        $stmt_soal->close();
        
        // Jika semua berhasil, commit transaksi
        $koneksi->commit();
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        // Jika ada error, rollback
        $koneksi->rollback();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
}
?>