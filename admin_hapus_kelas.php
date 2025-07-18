<?php
/**
 * File: admin_hapus_kelas.php
  * Aksi untuk Admin menghapus kelas.
   */

   require_once 'config/koneksi.php';

   // Keamanan: Pastikan hanya ADMIN yang bisa akses
   if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'admin') {
       header("Location: logout.php");
           exit();
           }

           // Validasi ID
           if (!isset($_GET['id'])) {
               header("Location: admin_manajemen_kelas.php");
                   exit();
                   }
                   $id_kelas = intval($_GET['id']);

                   // Admin bisa menghapus kelas mana saja tanpa perlu cek kepemilikan
                   $stmt_hapus = $koneksi->prepare("DELETE FROM kelas WHERE id = ?");
                   $stmt_hapus->bind_param("i", $id_kelas);
                   $stmt_hapus->execute();
                   $stmt_hapus->close();
                   $koneksi->close();

                   // Kembalikan ke halaman manajemen kelas dengan pesan sukses
                   header("Location: admin_manajemen_kelas.php?status=sukses_hapus");
                   exit();
                   ?>