<?php
/**
 * File: kerjakan_kuis.php
 * Versi Final dengan Pengecekan Status Pengerjaan dari tabel jawaban_siswa
 */
require_once 'config/koneksi.php';

// Proteksi halaman
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] != 'siswa') {
    header("Location: login.php");
    exit();
}
$id_siswa = $_SESSION['user_id'];

// Validasi ID Kuis
if (!isset($_GET['id_kuis'])) {
    header("Location: siswa_dashboard.php");
    exit();
}
$id_kuis = intval($_GET['id_kuis']);

// Verifikasi dan Cek Detail Kuis (termasuk kolom baru)
$query_verifikasi = "SELECT s.judul_soal, s.batas_waktu, s.mode_kuis, s.waktu_per_soal, s.acak_soal, s.deskripsi, pk.id as id_pendaftaran, k.id as id_kelas FROM soal s JOIN kelas k ON s.id_kelas = k.id JOIN pendaftaran_kelas pk ON s.id_kelas = pk.id_kelas WHERE s.id = ? AND pk.id_siswa = ?";
$stmt_verifikasi = $koneksi->prepare($query_verifikasi);
$stmt_verifikasi->bind_param("ii", $id_kuis, $id_siswa);
$stmt_verifikasi->execute();
$result_verifikasi = $stmt_verifikasi->get_result();
if ($result_verifikasi->num_rows == 0) {
    header("Location: siswa_dashboard.php?error=not_allowed");
    exit();
}
$kuis = $result_verifikasi->fetch_assoc();
$stmt_verifikasi->close();

// Logika khusus untuk kuis mode Klasik
if ($kuis['mode_kuis'] == 'klasik') {
    if (!empty($kuis['batas_waktu'])) {
        if (new DateTime() > new DateTime($kuis['batas_waktu'])) {
            header("Location: ruang_kelas_siswa.php?id_kelas=" . $kuis['id_kelas'] . "&error=deadline_passed");
            exit();
        }
    }
}

// ==== PERUBAHAN LOGIKA DI SINI: Cek ke tabel jawaban_siswa ====
// Cek apakah siswa sudah pernah mengerjakan kuis ini
$stmt_cek_submit = $koneksi->prepare("SELECT id FROM jawaban_siswa WHERE id_soal = ? AND id_siswa = ? LIMIT 1");
$stmt_cek_submit->bind_param("ii", $id_kuis, $id_siswa);
$stmt_cek_submit->execute();
$sudah_submit = $stmt_cek_submit->get_result()->num_rows > 0;
$stmt_cek_submit->close();

if ($sudah_submit) {
    include_once 'templates/header_siswa.php';
    echo "<div class='container mt-4'><div class='alert alert-warning text-center'><h4 class='alert-heading'>Soal Sudah Dikerjakan</h4><p>Anda sudah pernah mengumpulkan jawaban untuk kuis ini dan tidak dapat mengerjakannya kembali.</p><hr><a href='ruang_kelas_siswa.php?id_kelas=" . $kuis['id_kelas'] . "' class='btn btn-primary'>Kembali ke Ruang Kelas</a></div></div>";
    include_once 'templates/footer.php';
    exit(); // Hentikan eksekusi skrip lebih lanjut
}

// Logika untuk menyimpan jawaban (Hanya untuk mode Klasik)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kumpulkan_jawaban'])) {
    // Memulai transaksi
    $koneksi->begin_transaction();
    
    try {
        $stmt_simpan = $koneksi->prepare("INSERT INTO jawaban_siswa (id_soal, id_siswa, id_pertanyaan, jawaban) VALUES (?, ?, ?, ?)");
        
        $bound_id_pertanyaan = 0;
        $bound_jawaban = '';
        $stmt_simpan->bind_param("iiis", $id_kuis, $id_siswa, $bound_id_pertanyaan, $bound_jawaban);
        
        if (isset($_POST['jawaban']) && is_array($_POST['jawaban'])) {
            foreach ($_POST['jawaban'] as $id_pertanyaan => $jawaban) {
                $bound_id_pertanyaan = intval($id_pertanyaan);
                $bound_jawaban = sanitize($koneksi, $jawaban);
                $stmt_simpan->execute();
            }
        }
        $stmt_simpan->close();
        
        // Commit transaksi jika semua berhasil
        $koneksi->commit();
        header("Location: ruang_kelas_siswa.php?id_kelas=" . $_POST['id_kelas_hidden'] . "&status=sukses_submit");
        exit();

    } catch (Exception $e) {
        // Rollback jika terjadi error
        $koneksi->rollback();
        // Handle error, misalnya dengan menampilkan pesan
        die("Terjadi kesalahan saat menyimpan jawaban Anda. Silakan coba lagi.");
    }
}

// ==========================================================
// TAMPILAN UNTUK MODE INTERAKTIF
// ==========================================================
if ($kuis['mode_kuis'] == 'interaktif') {
    // Logika untuk mode interaktif tetap sama seperti sebelumnya
    $query_pertanyaan = "SELECT bs.id, bs.soal, bs.tipe_soal, bs.opsi_a, bs.opsi_b, bs.opsi_c, bs.opsi_d, bs.opsi_e, bs.kunci_jawaban, bs.skor FROM kuis_soal ks JOIN bank_soal bs ON ks.id_bank_soal = bs.id WHERE ks.id_kuis = ?";
    $stmt_pertanyaan = $koneksi->prepare($query_pertanyaan);
    $stmt_pertanyaan->bind_param("i", $id_kuis);
    $stmt_pertanyaan->execute();
    $result_pertanyaan = $stmt_pertanyaan->get_result();
    $questions = [];
    while($row = $result_pertanyaan->fetch_assoc()){
        $questions[] = $row;
    }
    if($kuis['acak_soal'] == 1){
        shuffle($questions);
    }
    $stmt_pertanyaan->close();
    
    include_once 'templates/header_siswa.php';
    ?>
    <style>
        #quiz-container { transition: opacity 0.3s; }
        .option-item { cursor: pointer; }
        .option-item:hover { background-color: #f0f0f0; }
    </style>
    <div class="container py-4">
        <h1 class="mb-2">Kuis Interaktif: <?php echo htmlspecialchars($kuis['judul_soal']); ?></h1>
        <p><?php echo htmlspecialchars($kuis['deskripsi']); ?></p>
        <hr>
        <div id="quiz-area">
            <div id="start-screen" class="text-center">
                <h2>Anda akan memulai kuis interaktif.</h2>
                <p>Setelah dimulai, waktu akan berjalan dan tidak bisa dijeda.</p>
                <button id="start-quiz-btn" class="btn btn-primary btn-lg">Mulai Kerjakan</button>
            </div>
            <div id="quiz-container" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div id="question-counter" class="fs-5"></div>
                    <div id="timer-per-soal" class="fs-4 fw-bold text-danger"></div>
                </div>
                <div class="progress mb-4" style="height: 10px;">
                    <div id="timer-bar" class="progress-bar bg-danger" role="progressbar" style="width: 100%;"></div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h4 id="question-text" class="card-title mb-4" style="min-height: 80px;"></h4>
                        <div id="options-list" class="list-group"></div>
                    </div>
                </div>
            </div>
            <div id="result-screen" class="d-none text-center">
                <h2 class="display-4">Kuis Selesai!</h2>
                <p class="lead">Skor Akhir Anda:</p>
                <h1 id="final-score" class="display-1 fw-bold text-success mb-4">0</h1>
                <a href="ruang_kelas_siswa.php?id_kelas=<?php echo $kuis['id_kelas']; ?>" class="btn btn-primary">Kembali ke Ruang Kelas</a>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Script untuk mode interaktif tetap sama
        const startScreen = document.getElementById('start-screen');
        const startBtn = document.getElementById('start-quiz-btn');
        const quizContainer = document.getElementById('quiz-container');
        const resultScreen = document.getElementById('result-screen');
        const questionCounterEl = document.getElementById('question-counter');
        const timerPerSoalEl = document.getElementById('timer-per-soal');
        const timerBarEl = document.getElementById('timer-bar');
        const questionTextEl = document.getElementById('question-text');
        const optionsListEl = document.getElementById('options-list');
        const finalScoreEl = document.getElementById('final-score');
        const questions = <?php echo json_encode($questions); ?>;
        const waktuPerSoal = <?php echo json_encode($kuis['waktu_per_soal'] ?? 0); ?>;
        const id_kuis = <?php echo $id_kuis; ?>;
        let currentQuestionIndex = 0;
        let score = 0;
        let questionTimer;
        let timeLeft;
        startBtn.addEventListener('click', startQuiz);
        function startQuiz() {
            startScreen.classList.add('d-none');
            quizContainer.classList.remove('d-none');
            showNextQuestion();
        }
        function showNextQuestion() {
            resetTimer();
            if (currentQuestionIndex >= questions.length) {
                endQuiz();
                return;
            }
            quizContainer.style.opacity = '0';
            setTimeout(() => {
                const question = questions[currentQuestionIndex];
                questionCounterEl.textContent = `Soal ${currentQuestionIndex + 1} dari ${questions.length}`;
                questionTextEl.innerHTML = question.soal;
                optionsListEl.innerHTML = '';
                const options = ['a', 'b', 'c', 'd', 'e'];
                options.forEach(opt => {
                    if (question['opsi_' + opt]) {
                        const optionEl = document.createElement('a');
                        optionEl.href = '#';
                        optionEl.className = 'list-group-item list-group-item-action option-item fs-5';
                        optionEl.innerHTML = `<b>${opt.toUpperCase()}.</b> ${question['opsi_' + opt]}`;
                        optionEl.addEventListener('click', (e) => {
                            e.preventDefault();
                            selectAnswer(opt.toUpperCase(), question.kunci_jawaban, question.skor);
                        });
                        optionsListEl.appendChild(optionEl);
                    }
                });
                quizContainer.style.opacity = '1';
                startTimer();
            }, 300);
        }
        function selectAnswer(selected, correct, points) {
            resetTimer();
            if (selected === correct) {
                score += parseInt(points);
            }
            // Simpan jawaban per soal ke database secara real-time
            const formData = new FormData();
            formData.append('id_kuis', id_kuis);
            formData.append('id_pertanyaan', questions[currentQuestionIndex].id);
            formData.append('jawaban', selected || ''); // Kirim jawaban kosong jika waktu habis
            fetch('simpan_jawaban_interaktif.php', { method: 'POST', body: formData });
            
            currentQuestionIndex++;
            showNextQuestion();
        }
        function startTimer() {
            if (waktuPerSoal <= 0) return;
            timeLeft = waktuPerSoal;
            timerPerSoalEl.textContent = timeLeft;
            timerBarEl.style.transition = 'none';
            timerBarEl.style.width = '100%';
            setTimeout(() => {
                timerBarEl.style.transition = `width ${waktuPerSoal}s linear`;
                timerBarEl.style.width = '0%';
            }, 100);
            questionTimer = setInterval(() => {
                timeLeft--;
                timerPerSoalEl.textContent = timeLeft;
                if (timeLeft <= 0) {
                    selectAnswer(null, '', 0);
                }
            }, 1000);
        }
        function resetTimer() {
            clearInterval(questionTimer);
        }
        function endQuiz() {
            quizContainer.classList.add('d-none');
            resultScreen.classList.remove('d-none');
            finalScoreEl.textContent = score;
            const formData = new FormData();
            formData.append('id_kuis', id_kuis);
            formData.append('skor', score);
            fetch('simpan_nilai_interaktif.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.status !== 'success'){
                    console.error('Gagal menyimpan skor:', data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        }
    });
    </script>
    <?php
    include_once 'templates/footer.php';
    exit();
}

// ==========================================================
// TAMPILAN UNTUK MODE KLASIK (KODE LAMA)
// ==========================================================
$query_pertanyaan = "SELECT bs.id, bs.soal, bs.tipe_soal, bs.opsi_a, bs.opsi_b, bs.opsi_c, bs.opsi_d, bs.opsi_e FROM kuis_soal ks JOIN bank_soal bs ON ks.id_bank_soal = bs.id WHERE ks.id_kuis = ? ORDER BY ks.id";
$stmt_pertanyaan = $koneksi->prepare($query_pertanyaan);
$stmt_pertanyaan->bind_param("i", $id_kuis);
$stmt_pertanyaan->execute();
$result_pertanyaan = $stmt_pertanyaan->get_result();

include_once 'templates/header_siswa.php';
?>

<h1 class="mb-2">Kerjakan Kuis: <?php echo htmlspecialchars($kuis['judul_soal']); ?></h1>
<?php if (!empty($kuis['batas_waktu'])): ?>
    <p class="text-danger"><strong>Batas Waktu: <?php echo date('d F Y, H:i', strtotime($kuis['batas_waktu'])); ?></strong></p>
<?php endif; ?>
<hr>

<form action="kerjakan_kuis.php?id_kuis=<?php echo $id_kuis; ?>" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengumpulkan jawaban? Jawaban tidak dapat diubah setelah dikumpulkan.')">
    <input type="hidden" name="id_kelas_hidden" value="<?php echo $kuis['id_kelas']; ?>">

    <?php
    if ($result_pertanyaan->num_rows > 0) {
        $no_soal = 1;
        while ($pertanyaan = $result_pertanyaan->fetch_assoc()) {
            echo "<div class='card mb-4'><div class='card-header'><strong>Pertanyaan #" . $no_soal++ . "</strong></div><div class='card-body'><p class='fs-5'>" . nl2br(htmlspecialchars($pertanyaan['soal'])) . "</p>";
            $id_pertanyaan = $pertanyaan['id'];
            if ($pertanyaan['tipe_soal'] == 'pilihan_ganda') {
                echo "<div class='ms-3'>";
                foreach(['A', 'B', 'C', 'D', 'E'] as $opsi) {
                    if (!empty($pertanyaan['opsi_' . strtolower($opsi)])) {
                        echo "<div class='form-check'><input class='form-check-input' type='radio' name='jawaban[$id_pertanyaan]' id='opsi_{$id_pertanyaan}_{$opsi}' value='$opsi' required><label class='form-check-label' for='opsi_{$id_pertanyaan}_{$opsi}'> " . htmlspecialchars($pertanyaan['opsi_' . strtolower($opsi)]) . "</label></div>";
                    }
                }
                echo "</div>";
            } elseif ($pertanyaan['tipe_soal'] == 'esai') {
                echo "<div class='ms-3'><textarea name='jawaban[$id_pertanyaan]' class='form-control' rows='5' placeholder='Ketik jawaban esai Anda di sini...' required></textarea></div>";
            }
            echo "</div></div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Guru belum menambahkan pertanyaan untuk kuis ini.</div>";
    }
    ?>
    
    <?php if ($result_pertanyaan->num_rows > 0): ?>
    <div class="d-grid mt-4"><button type="submit" name="kumpulkan_jawaban" class="btn btn-success btn-lg"><i class="bi bi-check-circle-fill me-2"></i>Kumpulkan Semua Jawaban</button></div>
    <?php endif; ?>
</form>

<?php
$koneksi->close();
include_once 'templates/footer.php';
?>