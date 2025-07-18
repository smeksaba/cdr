<?php
// Selalu mulai sesi di awal, bisa diletakkan di koneksi.php atau di sini
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - Cakap Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .printable-area { /* Style untuk menyembunyikan elemen saat tidak di-print */ }
        @media print {
            .no-print { display: none !important; }
            .printable-area { display: block; }
        }
        /* BARU: Style untuk item yang di-drag */
        .sortable-ghost {
            background: #e3f2fd;
            opacity: 0.8;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm no-print">
  <div class="container">
    <a class="navbar-brand fw-bold" href="guru_dashboard.php">CAKAP DIGITAL - GURU</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="guru_dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="buat_kelas.php">Buat Kelas</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="bank_soal.php">Bank Soal</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Rekapitulasi</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="rekap_absensi.php">Rekap Absensi</a></li>
            <li><a class="dropdown-item" href="rekap_nilai.php">Rekap Nilai</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="logout.php">Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="container mt-4">