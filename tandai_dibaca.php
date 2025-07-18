<?php
/**
 * File: tandai_dibaca.php
 * Aksi backend untuk menandai materi telah dibaca oleh siswa.
 */

require_once 'config/koneksi.php';

// Pastikan siswa sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'siswa') {
    // Keluar jika tidak sah, tanpa pesan error
    exit();
}

// Pastikan id_materi dikirim
if (isset($_POST['id_materi'])) {
    $id_siswa = $_SESSION['user_id'];
    $id_materi = intval($_POST['id_materi']);

    // Gunakan INSERT IGNORE untuk mencegah error jika data sudah ada
    // Ini lebih efisien daripada SELECT lalu INSERT
    $stmt = $koneksi->prepare("INSERT IGNORE INTO status_baca_materi (id_siswa, id_materi) VALUES (?, ?)");
    $stmt->bind_param("ii", $id_siswa, $id_materi);
    $stmt->execute();
    $stmt->close();
    
    // Kirim respons sukses kembali ke JavaScript
    echo json_encode(['status' => 'success']);
}
$koneksi->close();
?>