<?php
require_once 'config/koneksi.php';

// Keamanan: Pastikan hanya ADMIN yang bisa akses
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'admin') {
    header("Location: logout.php");
        exit();
        }

        // Validasi parameter
        if (isset($_GET['id']) && isset($_GET['status_baru'])) {
            $id_pengguna = intval($_GET['id']);
                $status_baru = $_GET['status_baru'];

                    // Validasi nilai status baru
                        if ($status_baru == 'aktif' || $status_baru == 'tidak_aktif') {
                                $stmt = $koneksi->prepare("UPDATE pengguna SET status_akun = ? WHERE id = ?");
                                        $stmt->bind_param("si", $status_baru, $id_pengguna);
                                                $stmt->execute();
                                                        $stmt->close();
                                                            }
                                                            }

                                                            // Kembalikan ke dashboard admin
                                                            header("Location: admin_dashboard.php");
                                                            exit();
                                                            ?>