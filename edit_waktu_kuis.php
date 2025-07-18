<?php
require_once 'config/koneksi.php';
session_start();

// Hanya guru yang bisa akses
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'guru') {
    header("Location: login.php");
    exit();
}

$id_guru = $_SESSION['user_id'];
$pesan = '';

if (!isset($_GET['id_soal'])) {
    header("Location: guru_dashboard.php");
    exit();
}

$id_kuis = intval($_GET['id_soal']);

// Ambil data kuis (validasi kepemilikan)
$stmt = $koneksi->prepare("SELECT s.judul_soal, s.batas_waktu FROM soal s JOIN kelas k ON s.id_kelas = k.id WHERE s.id = ? AND k.id_guru = ?");
$stmt->bind_param("ii", $id_kuis, $id_guru);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Data tidak ditemukan atau Anda tidak memiliki akses.";
    exit();
}

$data = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batas_waktu'])) {
    $batas_waktu_baru = $_POST['batas_waktu'];

    $stmt_update = $koneksi->prepare("UPDATE soal SET batas_waktu = ? WHERE id = ?");
    $stmt_update->bind_param("si", $batas_waktu_baru, $id_kuis);
    if ($stmt_update->execute()) {
        $pesan = "<div class='alert alert-success'>Waktu kuis berhasil diperbarui.</div>";
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal memperbarui waktu.</div>";
    }
    $stmt_update->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Waktu Kuis</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <h3>Edit Waktu Kuis: <?= htmlspecialchars($data['judul_soal']) ?></h3>
    <?= $pesan ?>
    <form method="POST">
        <div class="mb-3">
            <label for="batas_waktu" class="form-label">Batas Waktu (format: YYYY-MM-DD HH:MM:SS)</label>
            <input type="datetime-local" name="batas_waktu" class="form-control" required value="<?= str_replace(' ', 'T', $data['batas_waktu']) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="guru_dashboard.php" class="btn btn-secondary">Kembali</a>
    </form>
</body>
</html>
