<?php
require_once 'config/koneksi.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = sanitize($koneksi, $_POST['nama_lengkap']);
    $email = sanitize($koneksi, $_POST['email']);
    $password = $_POST['password'];
    $peran = sanitize($koneksi, $_POST['peran']); // 'guru' atau 'siswa'

    if (empty($nama_lengkap) || empty($email) || empty($password) || empty($peran)) {
        $error = "Semua field wajib diisi!";
    } else {
        // Hash password sebelum disimpan
        $hashed_password = hash('sha256', $password);

        $stmt = $koneksi->prepare("INSERT INTO pengguna (nama_lengkap, email, password, peran) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nama_lengkap, $email, $hashed_password, $peran);

        if ($stmt->execute()) {
            header("Location: login.php?status=sukses_daftar");
            exit();
        } else {
            $error = "Pendaftaran gagal, email mungkin sudah terdaftar.";
        }
        $stmt->close();
    }
}
$koneksi->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - CAKAP DIGITAL INDONESIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center" style="margin-top: 100px;">
        <div class="col-md-5">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title text-center mb-4">Daftar Akun Baru</h3>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form action="daftar.php" method="POST">
                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Alamat Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                             <label for="peran" class="form-label">Daftar sebagai:</label>
                             <select class="form-select" name="peran" id="peran" required>
                                 <option value="" disabled selected>-- Pilih Peran --</option>
                                 <option value="guru">Guru</option>
                                 <option value="siswa">Siswa</option>
                             </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Daftar</button>
                        </div>
                        <p class="text-center mt-3">Sudah punya akun? <a href="login.php">Login di sini</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>