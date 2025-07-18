<?php
/**
 * File: logout.php
 * Versi Penuh dan Benar
 * Fungsi: Menghancurkan sesi dan mengarahkan ke halaman login.
 */

// Selalu mulai sesi di awal untuk bisa menghancurkannya
session_start();

// Hapus semua variabel sesi
$_SESSION = array();

// Hancurkan cookie sesi
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan sesi di server
session_destroy();

// Alihkan ke halaman login
header("Location: login.php");
exit();
?>