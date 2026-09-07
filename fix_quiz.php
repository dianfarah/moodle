<?php
// Skrip perbaikan referensi soal kuis Moodle 4.x+
if (php_sapi_name() === 'cli') {
    define('CLI_SCRIPT', true);
    require(__DIR__ . '/config.php');
} else {
    require(__DIR__ . '/config.php');
    require_login();
    require_capability('moodle/site:config', context_system::instance());
}

echo "<h2>Memperbaiki Referensi Soal Kuis (Question References)...</h2>";

$sql = "
    UPDATE {question_references} qr
    JOIN {quiz_slots} qs ON qs.id = qr.itemid
    JOIN {quiz} q ON q.id = qs.quizid
    JOIN {modules} m ON m.name = 'quiz'
    JOIN {course_modules} cm ON cm.instance = q.id AND cm.module = m.id
    JOIN {context} ctx ON ctx.contextlevel = 70 AND ctx.instanceid = cm.id
    SET qr.usingcontextid = ctx.id
    WHERE qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
";

$DB->execute($sql);

purge_all_caches();

echo "<p style='color: green; font-weight: bold;'>Berhasil! Semua relasi soal kuis telah diperbaiki dan cache telah dibersihkan.</p>";
echo "<p>Silakan kembali ke halaman kuis dan refresh.</p>";
