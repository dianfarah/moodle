<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

echo "=== MERESET KONDISI DEMONSTRASI KE AWAL ===\n";

$courseid = 2;

// 1. Hapus feedback emosi agar Pop-Up Check-In Emosi muncul kembali
$DB->delete_records('acmls_motivation_feedback');
echo "[OK] Seluruh data feedback emosi dibersihkan. Pop-up Check-in Emosi akan muncul untuk semua akun.\n";

// 2. Hapus status motivasi pending
$DB->delete_records('acmls_learner_record', [
    'courseid' => $courseid,
    'record_type' => 'pending_quiz_motivation',
]);
$DB->delete_records('acmls_learner_record', [
    'courseid' => $courseid,
    'record_type' => 'delivered_quiz_motivation',
]);
echo "[OK] Status motivasi pasca-kuis direset.\n";

// 3. Hapus attempt kuis lama untuk siswa1 agar bisa mencoba mengerjakan kuis baru
$DB->delete_records('quiz_attempts', ['quiz' => 1]);
echo "[OK] Attempt kuis lama dibersihkan.\n";

// 4. Reset completion kuis dan AICode untuk siswa1 (userid 3)
$DB->delete_records('course_modules_completion', ['coursemoduleid' => 4]); // Page
$DB->delete_records('course_modules_completion', ['coursemoduleid' => 5]); // AICode
$DB->delete_records('course_modules_completion', ['coursemoduleid' => 3]); // Quiz
echo "[OK] Status penyelesaian modul siswa1 direset (Kuis kembali terkunci sampai latihan selesai).\n";

// 5. Purge Cache
purge_all_caches();
echo "[OK] Seluruh cache Moodle telah dibersihkan.\n";
echo "=== SELESAI: SEMUA AKUN SIAP UNTUK DEMONSTRASI DARI AWAL ===\n";
