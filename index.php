<?php
// PASTIKAN INI ADALAH BARIS PERTAMA
require_once 'config/koneksi.php';

// Proteksi halaman, jika belum login, tendang ke halaman login
// Pengecekan ini baru bisa berjalan benar jika sesi sudah dimulai dari koneksi.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$peran = $_SESSION['peran'];

// Arahkan ke dashboard yang sesuai
if ($peran == 'admin') {
    include 'admin_dashboard.php';
} elseif ($peran == 'guru') {
    // Ini bagian yang relevan untuk Anda
    include 'guru_dashboard.php';
} elseif ($peran == 'siswa') {
    include 'siswa_dashboard.php';
} else {
    // Jika peran tidak dikenali, logout saja untuk keamanan
    header("Location: logout.php");
    exit();
}
?>