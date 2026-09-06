<?php
// =============================================================
// Adaptive Learning AI — Gemini API Integration
// FIX: Hapus defined(MOODLE_INTERNAL)||die() supaya bisa
//      dipanggil dari ajax_microlearning.php (AJAX_SCRIPT=true)
//      config.php sudah set MOODLE_INTERNAL sebelum file ini.
// =============================================================

// Cukup cek MOODLE_INTERNAL tanpa die() agar tidak crash di AJAX
if (!defined('MOODLE_INTERNAL') && !defined('AJAX_SCRIPT')) {
    die('Direct access not permitted');
}

// ============================================================
// KONFIGURASI API — satu tempat, tidak perlu duplikasi
// ============================================================
if (!defined('ALAI_GEMINI_API_KEY')) {
    define('ALAI_GEMINI_API_KEY', 'AIzaSyAbrc6AJ-emlxlWXfqzApien8EqkNh1fGk');
}
if (!defined('ALAI_GEMINI_MODEL')) {
    define('ALAI_GEMINI_MODEL', 'gemini-1.5-flash');
}
if (!defined('ALAI_GEMINI_ENDPOINT')) {
    define('ALAI_GEMINI_ENDPOINT',
        'https://generativelanguage.googleapis.com/v1beta/models/' .
        ALAI_GEMINI_MODEL . ':generateContent?key=' . ALAI_GEMINI_API_KEY
    );
}

// -------------------------------------------------------------
// CORE: Call Gemini API
// -------------------------------------------------------------
function alai_call_gemini($prompt, $maxTokens = 300, $temperature = 0.7) {
    $data = [
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'maxOutputTokens' => $maxTokens,
            'temperature'     => $temperature,
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ],
    ];

    $ch = curl_init(ALAI_GEMINI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => 'cURL: ' . $curlError, 'text' => ''];
    }
    if ($httpCode !== 200) {
        $errBody = json_decode($response, true);
        $errMsg  = $errBody['error']['message'] ?? ('HTTP ' . $httpCode);
        return ['success' => false, 'error' => $errMsg, 'text' => ''];
    }

    $result = json_decode($response, true);
    $text   = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

    return ['success' => true, 'text' => trim($text), 'error' => ''];
}

// -------------------------------------------------------------
// 1. LEARNING RECOMMENDATION — berdasarkan nilai & level
// -------------------------------------------------------------
function alai_get_recommendation($score, $level, $quizName = '', $topic = '') {
    $quizInfo = $quizName ? "Quiz '$quizName'" : 'Quiz';

    if ($level === 'LOW') {
        $prompt = "Kamu AI tutor di Moodle. Siswa mendapat nilai {$score}% pada {$quizInfo} (Level REMEDIAL).
Berikan dalam HTML:
1. <b>Analisis singkat</b>: kenapa nilai bisa rendah (2 kalimat)
2. <b>3 Tips belajar spesifik</b> untuk topik '{$topic}'
3. <b>Motivasi</b>: 1 kalimat penyemangat
Format: paragraf pendek, gunakan <b> untuk penekanan, bahasa Indonesia, max 120 kata.";
    } elseif ($level === 'HIGH') {
        $prompt = "Kamu AI tutor di Moodle. Siswa mendapat nilai {$score}% pada {$quizInfo} (Level ADVANCED).
Berikan dalam HTML:
1. <b>Pujian spesifik</b> atas pencapaian (1 kalimat)
2. <b>2 Tantangan pengayaan</b> untuk topik '{$topic}'
3. <b>Proyek mini</b>: 1 ide proyek untuk dipraktikkan
Format: paragraf pendek, gunakan <b> untuk penekanan, bahasa Indonesia, max 100 kata.";
    } else {
        $prompt = "Kamu AI tutor di Moodle. Siswa mendapat nilai {$score}% pada {$quizInfo} (Level STANDARD).
Berikan dalam HTML:
1. <b>Topik yang perlu diperkuat</b> (2 kalimat)
2. <b>2 Strategi belajar</b> untuk naik ke level Advanced (nilai ≥90%)
3. <b>Latihan fokus</b>: 1 latihan spesifik
Format: paragraf pendek, gunakan <b> untuk penekanan, bahasa Indonesia, max 110 kata.";
    }

    $result = alai_call_gemini($prompt, 250, 0.7);
    return $result['success'] ? $result['text'] : '';
}

// -------------------------------------------------------------
// 2. QUIZ FEEDBACK — feedback otomatis setelah quiz
// -------------------------------------------------------------
function alai_get_quiz_feedback($score, $correctCount, $totalQuestions, $wrongTopics = []) {
    $percentage  = $score . '%';
    $wrongTopStr = !empty($wrongTopics) ? implode(', ', $wrongTopics) : 'tidak ada data';

    $prompt = "Kamu AI tutor Moodle. Siswa baru selesai quiz dengan hasil:
- Nilai: {$percentage}
- Benar: {$correctCount} dari {$totalQuestions} soal
- Topik yang salah: {$wrongTopStr}

Berikan feedback dalam HTML (max 100 kata):
1. <b>Hasil</b>: evaluasi singkat nilai ini
2. <b>Kelemahan</b>: topik yang perlu diperbaiki
3. <b>Langkah</b>: apa yang harus dilakukan selanjutnya
Gunakan bahasa Indonesia yang ramah dan memotivasi.";

    $result = alai_call_gemini($prompt, 200, 0.6);
    return $result['success'] ? $result['text'] : 'Feedback tidak tersedia.';
}

// -------------------------------------------------------------
// 3. WEAKNESS ANALYSIS — analisis kelemahan siswa
// -------------------------------------------------------------
function alai_analyze_weakness($userid, $courseid, $scores = []) {
    global $DB;

    if (empty($scores)) {
        $sql = "SELECT qa.sumgrades, q.grade AS maxgrade, q.name AS quizname
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON qa.quiz = q.id
                WHERE q.course = ? AND qa.userid = ? AND qa.state = 'finished'
                ORDER BY qa.timefinish ASC";
        $records = $DB->get_records_sql($sql, [$courseid, $userid]);
        foreach ($records as $r) {
            $max      = $r->maxgrade ?: 100;
            $scores[] = [
                'quiz'  => $r->quizname,
                'score' => round(($r->sumgrades / $max) * 100),
            ];
        }
    }

    if (empty($scores)) {
        return 'Belum ada data quiz untuk dianalisis.';
    }

    $scoreList = '';
    foreach ($scores as $s) {
        $scoreList .= "- {$s['quiz']}: {$s['score']}%\n";
    }

    $prompt = "Kamu AI tutor Moodle. Analisis riwayat nilai quiz siswa berikut:
{$scoreList}

Berikan dalam HTML (max 120 kata):
1. <b>Pola kelemahan</b>: topik/soal yang konsisten rendah
2. <b>Tren belajar</b>: apakah meningkat atau menurun?
3. <b>Rekomendasi prioritas</b>: 2 hal utama yang harus diperbaiki
Bahasa Indonesia, gunakan <b> untuk penekanan.";

    $result = alai_call_gemini($prompt, 250, 0.5);
    return $result['success'] ? $result['text'] : 'Analisis tidak tersedia.';
}

// -------------------------------------------------------------
// 4. MICROLEARNING CONTENT — generate konten 5-menit
// Dipanggil dari ajax_microlearning.php
// -------------------------------------------------------------
function alai_generate_microlearning($topic, $level, $score) {
    $levelDesc = [
        'LOW'    => 'pemula — fokus konsep dasar, contoh sederhana, bahasa mudah',
        'MEDIUM' => 'menengah — contoh praktis, latihan sederhana, tantangan kecil',
        'HIGH'   => 'advanced — konsep mendalam, best practice, pola desain',
    ][$level] ?? 'menengah';

    $prompt = "Buat modul microlearning 5 menit tentang '{$topic}' untuk level {$level} ({$levelDesc}).
Siswa skor saat ini: {$score}%.

Format output HTML (max 200 kata):
<b>🎯 Topik:</b> [nama topik]<br>
<b>⏱️ Durasi:</b> 5 menit<br><br>
<b>📖 Konsep Utama:</b><br>
[penjelasan 2-3 kalimat]<br><br>
<b>💻 Contoh Kode:</b><br>
<pre style='background:rgba(15,23,42,.8);padding:10px;border-radius:8px;font-size:.72rem;overflow-x:auto'>[kode contoh]</pre>
<b>✅ Mini Quiz:</b><br>
[1 pertanyaan pilihan ganda dengan jawaban]<br><br>
<b>💡 Tips:</b> [1 tips praktis]";

    $result = alai_call_gemini($prompt, 400, 0.8);
    return $result['success'] ? $result['text'] : null;
}

// -------------------------------------------------------------
// 5. CHAT ANSWER — jawab pertanyaan bebas siswa
// Dipanggil dari ajax_microlearning.php
// -------------------------------------------------------------
function alai_answer_question($question, $level, $score, $topic = '') {
    $context = $topic ? " dalam konteks topik '{$topic}'" : '';

    $prompt = "Kamu AI tutor pemrograman web di Moodle. Siswa level {$level} (skor {$score}%) bertanya{$context}:
\"{$question}\"

Jawab dalam HTML (max 150 kata):
- Gunakan <b> untuk penekanan
- Sertakan contoh kode dalam <pre> jika relevan
- Bahasa Indonesia yang jelas dan ramah
- Sesuaikan kedalaman penjelasan dengan level {$level}";

    $result = alai_call_gemini($prompt, 300, 0.7);

    if ($result['success'] && $result['text']) {
        return $result['text'];
    }

    return null; // kembalikan null supaya ajax_microlearning bisa fallback ke offline
}