<?php
/**
 * File: copy_kelas.php
  * Aksi untuk menyalin/menduplikat sebuah kelas beserta materi dan kuisnya.
   */

   require_once 'config/koneksi.php';

   // 1. Keamanan: Pastikan yang akses adalah guru yang sudah login
   if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
       header("Location: login.php");
           exit();
           }
           $id_guru = $_SESSION['user_id'];

           // 2. Keamanan: Pastikan ada ID kelas sumber yang dikirim
           if (!isset($_GET['id_sumber'])) {
               header("Location: guru_dashboard.php");
                   exit();
                   }
                   $id_kelas_sumber = intval($_GET['id_sumber']);

                   // Mulai Database Transaction untuk memastikan semua proses berhasil atau tidak sama sekali.
                   $koneksi->begin_transaction();

                   try {
                       // 3. Ambil data kelas sumber dan verifikasi kepemilikan
                           $stmt_sumber = $koneksi->prepare("SELECT * FROM kelas WHERE id = ? AND id_guru = ?");
                               $stmt_sumber->bind_param("ii", $id_kelas_sumber, $id_guru);
                                   $stmt_sumber->execute();
                                       $result_sumber = $stmt_sumber->get_result();
                                           if ($result_sumber->num_rows == 0) {
                                                   throw new Exception("Kelas sumber tidak ditemukan atau bukan milik Anda.");
                                                       }
                                                           $kelas_sumber = $result_sumber->fetch_assoc();
                                                               $stmt_sumber->close();

                                                                   // 4. Buat data untuk kelas baru
                                                                       $nama_kelas_baru = $kelas_sumber['nama_kelas'] . " (Salinan)";
                                                                           $kode_kelas_baru = strtoupper(substr(uniqid(), -6));

                                                                               // 5. Masukkan kelas baru ke database
                                                                                   $stmt_kelas_baru = $koneksi->prepare("INSERT INTO kelas (id_guru, nama_kelas, mata_pelajaran, deskripsi, kode_kelas) VALUES (?, ?, ?, ?, ?)");
                                                                                       $stmt_kelas_baru->bind_param("issss", $id_guru, $nama_kelas_baru, $kelas_sumber['mata_pelajaran'], $kelas_sumber['deskripsi'], $kode_kelas_baru);
                                                                                           $stmt_kelas_baru->execute();
                                                                                               $id_kelas_baru = $koneksi->insert_id; // Dapatkan ID dari kelas yang baru saja dibuat
                                                                                                   $stmt_kelas_baru->close();

                                                                                                       // 6. Salin semua materi dari kelas sumber ke kelas baru
                                                                                                           $stmt_materi_sumber = $koneksi->prepare("SELECT * FROM materi WHERE id_kelas = ?");
                                                                                                               $stmt_materi_sumber->bind_param("i", $id_kelas_sumber);
                                                                                                                   $stmt_materi_sumber->execute();
                                                                                                                       $result_materi = $stmt_materi_sumber->get_result();
                                                                                                                           
                                                                                                                               $stmt_materi_baru = $koneksi->prepare("INSERT INTO materi (id_kelas, judul, tipe_materi, konten) VALUES (?, ?, ?, ?)");
                                                                                                                                   while ($materi = $result_materi->fetch_assoc()) {
                                                                                                                                           $stmt_materi_baru->bind_param("isss", $id_kelas_baru, $materi['judul'], $materi['tipe_materi'], $materi['konten']);
                                                                                                                                                   $stmt_materi_baru->execute();
                                                                                                                                                       }
                                                                                                                                                           $stmt_materi_sumber->close();
                                                                                                                                                               $stmt_materi_baru->close();

                                                                                                                                                                   // 7. Salin semua kuis (soal) dan hubungannya dengan bank soal
                                                                                                                                                                       $stmt_kuis_sumber = $koneksi->prepare("SELECT * FROM soal WHERE id_kelas = ?");
                                                                                                                                                                           $stmt_kuis_sumber->bind_param("i", $id_kelas_sumber);
                                                                                                                                                                               $stmt_kuis_sumber->execute();
                                                                                                                                                                                   $result_kuis = $stmt_kuis_sumber->get_result();
                                                                                                                                                                                       
                                                                                                                                                                                           $stmt_kuis_baru = $koneksi->prepare("INSERT INTO soal (id_kelas, judul_soal, deskripsi, batas_waktu) VALUES (?, ?, ?, ?)");
                                                                                                                                                                                               $stmt_link_kuis_baru = $koneksi->prepare("INSERT INTO kuis_soal (id_kuis, id_bank_soal) VALUES (?, ?)");

                                                                                                                                                                                                   while ($kuis = $result_kuis->fetch_assoc()) {
                                                                                                                                                                                                           // Buat kuis baru
                                                                                                                                                                                                                   $stmt_kuis_baru->bind_param("isss", $id_kelas_baru, $kuis['judul_soal'], $kuis['deskripsi'], $kuis['batas_waktu']);
                                                                                                                                                                                                                           $stmt_kuis_baru->execute();
                                                                                                                                                                                                                                   $id_kuis_baru = $koneksi->insert_id;

                                                                                                                                                                                                                                           // Salin hubungan pertanyaan dari kuis lama ke kuis baru
                                                                                                                                                                                                                                                   $stmt_pertanyaan_sumber = $koneksi->prepare("SELECT id_bank_soal FROM kuis_soal WHERE id_kuis = ?");
                                                                                                                                                                                                                                                           $stmt_pertanyaan_sumber->bind_param("i", $kuis['id']);
                                                                                                                                                                                                                                                                   $stmt_pertanyaan_sumber->execute();
                                                                                                                                                                                                                                                                           $result_pertanyaan = $stmt_pertanyaan_sumber->get_result();
                                                                                                                                                                                                                                                                                   while ($pertanyaan = $result_pertanyaan->fetch_assoc()) {
                                                                                                                                                                                                                                                                                               $stmt_link_kuis_baru->bind_param("ii", $id_kuis_baru, $pertanyaan['id_bank_soal']);
                                                                                                                                                                                                                                                                                                           $stmt_link_kuis_baru->execute();
                                                                                                                                                                                                                                                                                                                   }
                                                                                                                                                                                                                                                                                                                           $stmt_pertanyaan_sumber->close();
                                                                                                                                                                                                                                                                                                                               }
                                                                                                                                                                                                                                                                                                                                   $stmt_kuis_sumber->close();
                                                                                                                                                                                                                                                                                                                                       $stmt_kuis_baru->close();
                                                                                                                                                                                                                                                                                                                                           $stmt_link_kuis_baru->close();

                                                                                                                                                                                                                                                                                                                                               // Jika semua proses berhasil, simpan semua perubahan ke database
                                                                                                                                                                                                                                                                                                                                                   $koneksi->commit();
                                                                                                                                                                                                                                                                                                                                                       header("Location: guru_dashboard.php?status=sukses_copy");
                                                                                                                                                                                                                                                                                                                                                           exit();

                                                                                                                                                                                                                                                                                                                                                           } catch (Exception $e) {
                                                                                                                                                                                                                                                                                                                                                               // Jika ada satu saja proses yang gagal, batalkan semua perubahan
                                                                                                                                                                                                                                                                                                                                                                   $koneksi->rollback();
                                                                                                                                                                                                                                                                                                                                                                       // Tampilkan pesan error (opsional, bisa juga redirect ke halaman error)
                                                                                                                                                                                                                                                                                                                                                                           die("Proses penyalinan kelas gagal: " . $e->getMessage());
                                                                                                                                                                                                                                                                                                                                                                           }

                                                                                                                                                                                                                                                                                                                                                                           ?>