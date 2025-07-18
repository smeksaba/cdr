<?php
/**
 * File: hapus_kelas.php
  * Aksi untuk menghapus kelas secara aman.
   */

   require_once 'config/koneksi.php';

   // 1. Keamanan: Pastikan yang akses adalah guru yang sudah login
   if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
       header("Location: login.php");
           exit();
           }
           $id_guru = $_SESSION['user_id'];

           // 2. Keamanan: Pastikan ada ID kelas yang dikirim
           if (!isset($_GET['id'])) {
               header("Location: guru_dashboard.php");
                   exit();
                   }
                   $id_kelas = intval($_GET['id']);

                   // 3. Keamanan: Verifikasi bahwa kelas ini adalah milik guru yang sedang login
                   $stmt_verifikasi = $koneksi->prepare("SELECT id FROM kelas WHERE id = ? AND id_guru = ?");
                   if ($stmt_verifikasi === false) { die("Query verifikasi gagal."); }
                   $stmt_verifikasi->bind_param("ii", $id_kelas, $id_guru);
                   $stmt_verifikasi->execute();
                   $result_verifikasi = $stmt_verifikasi->get_result();

                   // Jika jumlah baris adalah 0, berarti kelas itu tidak ada atau bukan milik guru ini
                   if ($result_verifikasi->num_rows == 0) {
                       // Tendang kembali ke dashboard
                           header("Location: guru_dashboard.php?error=forbidden");
                               exit();
                               }
                               $stmt_verifikasi->close();


                               // 4. Jika semua pemeriksaan keamanan lolos, lakukan proses HAPUS
                               $stmt_hapus = $koneksi->prepare("DELETE FROM kelas WHERE id = ?");
                               if ($stmt_hapus === false) { die("Query hapus gagal."); }
                               $stmt_hapus->bind_param("i", $id_kelas);
                               $stmt_hapus->execute();
                               $stmt_hapus->close();
                               $koneksi->close();

                               // 5. Kembalikan ke dashboard dengan pesan sukses
                               header("Location: guru_dashboard.php?status=sukses_hapus");
                               exit();
                               ?>