<?php
/**
 * File: simpan_jawaban_interaktif.php
  * Endpoint AJAX untuk menyimpan JAWABAN PER SOAL dari kuis mode interaktif.
   * Ini penting agar ada jejak di tabel 'jawaban_siswa'.
    */

    require_once 'config/koneksi.php';

    // Hanya untuk memastikan sesi berjalan, tidak perlu validasi ketat karena ini helper
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
        }

        // Hanya proses jika ada user_id di sesi
        if (!isset($_SESSION['user_id'])) {
            exit(); 
            }
            $id_siswa = $_SESSION['user_id'];

            // Validasi input
            if (isset($_POST['id_kuis']) && isset($_POST['id_pertanyaan']) && isset($_POST['jawaban'])) {
                $id_kuis = intval($_POST['id_kuis']);
                    $id_pertanyaan = intval($_POST['id_pertanyaan']);
                        $jawaban = sanitize($koneksi, $_POST['jawaban']);

                            // Gunakan INSERT IGNORE untuk efisiensi. Jika siswa mencoba menjawab soal yang sama lagi (meski tidak mungkin di UI), tidak akan terjadi error.
                                $stmt = $koneksi->prepare("INSERT IGNORE INTO jawaban_siswa (id_soal, id_siswa, id_pertanyaan, jawaban) VALUES (?, ?, ?, ?)");
                                    if ($stmt) {
                                            $stmt->bind_param("iiis", $id_kuis, $id_siswa, $id_pertanyaan, $jawaban);
                                                    $stmt->execute();
                                                            $stmt->close();
                                                                }
                                                                }

                                                                $koneksi->close();
                                                                ?>