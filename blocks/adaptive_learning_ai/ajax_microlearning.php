<?php
// =============================================================
// Adaptive Learning AI — AJAX Microlearning Handler
// FIX: MOODLE_INTERNAL, API key dari gemini_api.php, no duplicate
// =============================================================
define('AJAX_SCRIPT', true);
require_once('../../config.php');

// FIX: require_once SEBELUM cek MOODLE_INTERNAL
// gemini_api.php butuh MOODLE_INTERNAL — sudah di-set oleh config.php
require_once($CFG->dirroot . '/blocks/adaptive_learning_ai/gemini_api.php');

require_login();

header('Content-Type: application/json; charset=utf-8');

// --- Input ---
$raw      = file_get_contents('php://input');
$data     = json_decode($raw, true) ?: [];
$courseid = intval($data['courseid'] ?? 0);
$message  = trim($data['message'] ?? '');
$score    = intval($data['score'] ?? 0);
$level    = in_array($data['level'] ?? '', ['LOW', 'MEDIUM', 'HIGH']) ? $data['level'] : 'MEDIUM';

if (!$courseid || !$message) {
    echo json_encode(['success' => false, 'reply' => 'Parameter tidak valid.']);
    exit;
}

global $DB, $USER;

// Pastikan user terdaftar di course
$context = context_course::instance($courseid, IGNORE_MISSING);
if (!$context || !is_enrolled($context, $USER->id)) {
    echo json_encode(['success' => false, 'reply' => 'Akses ditolak.']);
    exit;
}

// ============================================================
// STEP: Buka materi sesuai level & week otomatis
// Dipanggil setiap AJAX request supaya selalu sinkron
// ============================================================
try {
    adaptive_learning_ai_unlock_materials($courseid, $USER->id, $level, $DB, $CFG);
} catch (Exception $e) {
    // silent — jangan hentikan proses microlearning
}

// ============================================================
// OFFLINE MICROLEARNING UNITS (fallback jika Gemini timeout)
// ============================================================
$offlineUnits = [
    'variable' => [
        'title'  => 'Variable &amp; Tipe Data',
        'icon'   => '📦',
        'time'   => '5 menit',
        'level'  => 'LOW',
        'konsep' => 'Variable adalah <b>wadah untuk menyimpan data</b>. JavaScript memiliki 3 cara deklarasi.',
        'kode'   => "let nama = 'Budi';       // String\nlet usia = 20;           // Number\nlet aktif = true;        // Boolean\nconst PI = 3.14;         // Konstanta\n\nconsole.log(nama, usia); // Output: Budi 20",
        'quiz'   => '<b>❓ Mini Quiz:</b> Apa perbedaan <code>let</code> dan <code>const</code>?<br><span style="color:#6ee7b7">✅ const</span> tidak bisa diubah, <span style="color:#6ee7b7">let</span> bisa diubah nilainya.',
        'tip'    => 'Gunakan <b>const</b> secara default, ganti ke <b>let</b> hanya jika nilainya berubah.',
    ],
    'if-else' => [
        'title'  => 'Kondisi If-Else',
        'icon'   => '🔀',
        'time'   => '5 menit',
        'level'  => 'MEDIUM',
        'konsep' => '<b>If-Else</b> digunakan untuk membuat keputusan dalam program berdasarkan kondisi tertentu.',
        'kode'   => "let nilai = 85;\n\nif (nilai >= 90) {\n    console.log('Level Advanced');\n} else if (nilai >= 70) {\n    console.log('Level Standard');\n} else {\n    console.log('Level Remedial');\n}\n// Output: Level Standard",
        'quiz'   => '<b>❓ Mini Quiz:</b> Operator mana yang benar untuk "lebih besar atau sama dengan"?<br><span style="color:#6ee7b7">✅ >=</span> adalah operator yang tepat.',
        'tip'    => 'Gunakan <b>===</b> (strict equality) bukan <b>==</b> untuk perbandingan yang aman.',
    ],
    'loop' => [
        'title'  => 'Loop &amp; Perulangan',
        'icon'   => '🔁',
        'time'   => '5 menit',
        'level'  => 'MEDIUM',
        'konsep' => '<b>Loop</b> mengulang kode berkali-kali. Ada 3 jenis utama: <b>for</b>, <b>while</b>, dan <b>forEach</b>.',
        'kode'   => "// For Loop\nfor (let i = 1; i <= 5; i++) {\n    console.log('Perulangan ke-' + i);\n}\n\n// forEach (untuk array)\nlet buah = ['Apel', 'Mangga', 'Jeruk'];\nbuah.forEach(b => console.log(b));",
        'quiz'   => '<b>❓ Mini Quiz:</b> Kapan menggunakan <code>while</code> vs <code>for</code>?<br><span style="color:#6ee7b7">✅ for</span>: jumlah iterasi diketahui. <span style="color:#6ee7b7">while</span>: berhenti saat kondisi terpenuhi.',
        'tip'    => 'Hati-hati <b>infinite loop</b>! Pastikan kondisi berhenti selalu tercapai.',
    ],
    'array' => [
        'title'  => 'Array',
        'icon'   => '📋',
        'time'   => '5 menit',
        'level'  => 'MEDIUM',
        'konsep' => '<b>Array</b> adalah kumpulan nilai dalam satu variabel. Index dimulai dari <b>0</b>.',
        'kode'   => "let siswa = ['Ani', 'Budi', 'Cici'];\n\nconsole.log(siswa[0]);     // 'Ani'\nconsole.log(siswa.length); // 3\n\nsiswa.push('Dodi');        // Tambah akhir\nsiswa.pop();               // Hapus akhir\nlet nilai = [80, 70, 90];\nlet rata  = nilai.reduce((sum,x) => sum+x, 0) / nilai.length;",
        'quiz'   => '<b>❓ Mini Quiz:</b> Apa output dari <code>[10,20,30][1]</code>?<br><span style="color:#6ee7b7">✅ 20</span> — index ke-1 adalah elemen kedua.',
        'tip'    => 'Pelajari <b>map()</b>, <b>filter()</b>, <b>reduce()</b> — 3 method array paling powerful!',
    ],
    'function' => [
        'title'  => 'Function &amp; Arrow Function',
        'icon'   => '⚙️',
        'time'   => '7 menit',
        'level'  => 'HIGH',
        'konsep' => '<b>Function</b> adalah blok kode reusable. <b>Arrow function</b> adalah sintaks modern yang lebih singkat.',
        'kode'   => "function hitung(a, b) {\n    return a + b;\n}\n\nconst kali   = (a, b) => a * b;\nconst angka  = [1,2,3,4,5];\nconst genap  = angka.filter(n => n % 2 === 0); // [2,4]\nconst double = angka.map(n => n * 2);           // [2,4,6,8,10]",
        'quiz'   => '<b>❓ Mini Quiz:</b> Apa perbedaan function dan arrow function terkait <code>this</code>?<br><span style="color:#6ee7b7">✅ Arrow function</span> tidak memiliki <code>this</code> sendiri.',
        'tip'    => 'Arrow function sangat berguna sebagai <b>callback</b> dalam map/filter/reduce.',
    ],
    'object' => [
        'title'  => 'Object &amp; OOP',
        'icon'   => '🧩',
        'time'   => '7 menit',
        'level'  => 'HIGH',
        'konsep' => '<b>Object</b> adalah kumpulan properti (key-value pair). Class adalah blueprint untuk membuat object.',
        'kode'   => "class Siswa {\n    constructor(nama, nilai) {\n        this.nama  = nama;\n        this.nilai = nilai;\n    }\n    getLevel() {\n        return this.nilai >= 90 ? 'Advanced' : 'Standard';\n    }\n}\n\nconst s = new Siswa('Ani', 95);\nconsole.log(s.getLevel()); // 'Advanced'",
        'quiz'   => '<b>❓ Mini Quiz:</b> Apa fungsi <code>constructor()</code> dalam class?<br><span style="color:#6ee7b7">✅</span> Dipanggil saat object dibuat, untuk inisialisasi properti.',
        'tip'    => 'Gunakan <b>destructuring</b>: <code>const {nama, nilai} = siswa;</code> untuk akses cepat.',
    ],
    'html' => [
        'title'  => 'Dasar HTML',
        'icon'   => '🌐',
        'time'   => '5 menit',
        'level'  => 'LOW',
        'konsep' => '<b>HTML</b> adalah bahasa untuk membuat struktur halaman web menggunakan tag.',
        'kode'   => "<!DOCTYPE html>\n<html>\n<head><title>Halaman Saya</title></head>\n<body>\n    <h1>Judul Utama</h1>\n    <p>Ini paragraf.</p>\n    <a href=\"https://moodle.org\">Link Moodle</a>\n</body>\n</html>",
        'quiz'   => '<b>❓ Mini Quiz:</b> Tag apa untuk heading terbesar?<br><span style="color:#6ee7b7">✅ &lt;h1&gt;</span>',
        'tip'    => 'Gunakan <b>semantic HTML</b>: &lt;header&gt;, &lt;main&gt;, &lt;footer&gt; untuk struktur lebih baik.',
    ],
    'css' => [
        'title'  => 'Dasar CSS',
        'icon'   => '🎨',
        'time'   => '5 menit',
        'level'  => 'LOW',
        'konsep' => '<b>CSS</b> mengatur tampilan elemen HTML: warna, ukuran, layout, animasi.',
        'kode'   => ".kartu {\n    background: white;\n    border-radius: 12px;\n    padding: 20px;\n    box-shadow: 0 4px 12px rgba(0,0,0,0.1);\n}\n\n.tombol {\n    background: #3b82f6;\n    color: white;\n    padding: 10px 20px;\n    border-radius: 8px;\n}",
        'quiz'   => '<b>❓ Mini Quiz:</b> Perbedaan <code>margin</code> dan <code>padding</code>?<br><span style="color:#6ee7b7">✅ margin</span>: luar elemen. <span style="color:#6ee7b7">padding</span>: dalam elemen.',
        'tip'    => 'Pelajari <b>Flexbox</b> dan <b>CSS Grid</b> untuk layout responsif modern!',
    ],
    'async' => [
        'title'  => 'Async/Await &amp; Fetch',
        'icon'   => '⏳',
        'time'   => '8 menit',
        'level'  => 'HIGH',
        'konsep' => '<b>Async/Await</b> memudahkan kode asynchronous. <b>Fetch API</b> untuk request HTTP.',
        'kode'   => "async function ambilData(url) {\n    try {\n        const response = await fetch(url);\n        if (!response.ok) throw new Error('HTTP ' + response.status);\n        const data = await response.json();\n        return data;\n    } catch (error) {\n        console.error('Error:', error.message);\n    }\n}",
        'quiz'   => '<b>❓ Mini Quiz:</b> Kegunaan <code>try...catch</code> dalam async/await?<br><span style="color:#6ee7b7">✅</span> Menangkap error dari Promise yang gagal.',
        'tip'    => 'Selalu gunakan <b>try/catch</b> dalam async function untuk menangani error jaringan!',
    ],
];

// ============================================================
// DETECT: topik dari dropdown atau pertanyaan bebas
// ============================================================
$isTopicKey = array_key_exists($message, $offlineUnits);
$reply      = '';
$pathType   = 'standard';
if ($level === 'LOW')  $pathType = 'remedial';
if ($level === 'HIGH') $pathType = 'advanced';

// Badge level HTML
$levelBadge = [
    'LOW'    => '<span style="background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.3);padding:3px 10px;border-radius:12px;font-size:.65rem;font-weight:700">🔴 REMEDIAL</span>',
    'MEDIUM' => '<span style="background:rgba(245,158,11,.15);color:#fcd34d;border:1px solid rgba(245,158,11,.3);padding:3px 10px;border-radius:12px;font-size:.65rem;font-weight:700">🟡 STANDARD</span>',
    'HIGH'   => '<span style="background:rgba(16,185,129,.15);color:#6ee7b7;border:1px solid rgba(16,185,129,.3);padding:3px 10px;border-radius:12px;font-size:.65rem;font-weight:700">🟢 ADVANCED</span>',
][$level] ?? '';

// ============================================================
// HANDLE TOPIC SELECT (dari dropdown)
// ============================================================
if ($isTopicKey) {
    $unit = $offlineUnits[$message];

    // Coba Gemini dulu — API key sudah dari gemini_api.php (ALAI_GEMINI_API_KEY)
    $geminiContent = alai_generate_microlearning($unit['title'], $level, $score);

    if ($geminiContent) {
        $reply = '<strong><i class="fas fa-robot"></i> Adaptive AI</strong> ' . $levelBadge . '
        <span style="background:rgba(34,197,94,.12);color:#86efac;border:1px solid rgba(34,197,94,.25);padding:2px 8px;border-radius:8px;font-size:.6rem;margin-left:4px">✨ AI Generated</span>
        <div style="margin-top:10px">' . $geminiContent . '</div>';
    } else {
        // Fallback offline unit
        $reply = '<strong><i class="fas fa-robot"></i> Adaptive AI</strong> ' . $levelBadge . '
        <div style="margin-top:12px">
            <div style="font-weight:700;color:#93c5fd;font-size:.85rem;margin-bottom:4px">' . $unit['icon'] . ' ' . $unit['title'] . '</div>
            <div style="color:rgba(255,255,255,.5);font-size:.62rem;margin-bottom:10px">⏱️ ' . $unit['time'] . ' &nbsp;|&nbsp; Level: ' . $unit['level'] . '</div>
            <div style="font-size:.78rem;color:rgba(255,255,255,.8);margin-bottom:10px">' . $unit['konsep'] . '</div>
            <pre style="background:rgba(15,23,42,.85);border:1px solid rgba(99,160,255,.15);border-radius:10px;padding:12px;font-size:.7rem;overflow-x:auto;color:#e2e8f0;line-height:1.7;white-space:pre-wrap">' . htmlspecialchars($unit['kode']) . '</pre>
            <div style="margin-top:10px;font-size:.75rem;color:rgba(255,255,255,.7)">' . $unit['quiz'] . '</div>
            <div style="background:rgba(96,165,250,.1);border-left:3px solid #60a5fa;border-radius:0 8px 8px 0;padding:8px 12px;margin-top:10px;font-size:.72rem;color:rgba(255,255,255,.7)">
                <i class="fas fa-lightbulb" style="color:#fbbf24"></i> <strong>Tips:</strong> ' . $unit['tip'] . '
            </div>
        </div>';
    }

// ============================================================
// HANDLE FREE QUESTION (pertanyaan bebas siswa)
// ============================================================
} else {
    $aiAnswer = alai_answer_question($message, $level, $score);

    if ($aiAnswer) {
        $reply = '<strong><i class="fas fa-robot"></i> Adaptive AI</strong>
        <span style="background:rgba(99,102,241,.15);color:#a5b4fc;border:1px solid rgba(99,102,241,.25);padding:2px 8px;border-radius:8px;font-size:.6rem;margin-left:4px">🤖 AI Answer</span>
        <div style="margin-top:10px;font-size:.78rem;color:rgba(255,255,255,.8);line-height:1.65">' . $aiAnswer . '</div>
        <div style="margin-top:10px;font-size:.65rem;color:rgba(255,255,255,.4)">
            Level: <strong style="color:#93c5fd">' . $level . '</strong> | Skor: <strong style="color:#fbbf24">' . $score . '%</strong>
        </div>';
    } else {
        // Keyword fallback
        $matched    = null;
        $msgLower   = strtolower($message);
        $keywordMap = [
            'variable' => ['variabel', 'variable', 'var', 'let', 'const', 'tipe data'],
            'if-else'  => ['if', 'else', 'kondisi', 'percabangan', 'ternary'],
            'loop'     => ['loop', 'for', 'while', 'perulangan', 'foreach', 'ulang'],
            'array'    => ['array', 'list', 'arr', 'indeks'],
            'function' => ['function', 'fungsi', 'arrow', 'method'],
            'object'   => ['object', 'objek', 'class', 'oop'],
            'html'     => ['html', 'tag', 'elemen', 'webpage'],
            'css'      => ['css', 'style', 'warna', 'flexbox', 'grid'],
            'async'    => ['async', 'await', 'fetch', 'promise', 'api'],
        ];

        foreach ($keywordMap as $topicKey => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($msgLower, $kw) !== false) {
                    $matched = $topicKey;
                    break 2;
                }
            }
        }

        if ($matched && isset($offlineUnits[$matched])) {
            $u     = $offlineUnits[$matched];
            $reply = '<strong><i class="fas fa-robot"></i> Adaptive AI</strong>
            <div style="margin-top:10px;font-size:.75rem;color:rgba(255,255,255,.5);margin-bottom:8px">
                Materi terkait "<b style="color:#93c5fd">' . $matched . '</b>"
            </div>
            <div style="font-size:.78rem;color:rgba(255,255,255,.8)">' . $u['konsep'] . '</div>
            <pre style="background:rgba(15,23,42,.85);border:1px solid rgba(99,160,255,.15);border-radius:10px;padding:12px;font-size:.7rem;overflow-x:auto;color:#e2e8f0;line-height:1.7;white-space:pre-wrap">' . htmlspecialchars($u['kode']) . '</pre>
            <div style="background:rgba(96,165,250,.1);border-left:3px solid #60a5fa;border-radius:0 8px 8px 0;padding:8px 12px;margin-top:8px;font-size:.72rem;color:rgba(255,255,255,.7)">
                💡 ' . $u['tip'] . '
            </div>';
        } else {
            $reply = '<strong><i class="fas fa-robot"></i> Adaptive AI</strong>
            <div style="margin-top:8px;font-size:.78rem;color:rgba(255,255,255,.65)">
                Saya belum bisa menjawab itu secara offline. Coba pilih topik dari dropdown, atau pastikan koneksi internet aktif.<br><br>
                <span style="color:#fbbf24">Level:</span> <b style="color:#93c5fd">' . $level . '</b> | 
                <span style="color:#fbbf24">Skor:</span> <b>' . $score . '%</b>
            </div>';
        }
    }
}

// ============================================================
// LOG ke tabel block_adaptive_scores (jika ada)
// ============================================================
try {
    $existing = $DB->get_record('block_adaptive_scores', ['userid' => $USER->id, 'courseid' => $courseid]);
    $record   = [
        'userid'      => $USER->id,
        'courseid'    => $courseid,
        'score'       => $score,
        'level'       => $level,
        'pathtype'    => $pathType,
        'timecreated' => time(),
    ];
    if ($existing) {
        $record['id'] = $existing->id;
        $DB->update_record('block_adaptive_scores', (object) $record);
    } else {
        $DB->insert_record('block_adaptive_scores', (object) $record);
    }
} catch (Exception $e) {
    // Silent — tabel mungkin belum ada
}

echo json_encode([
    'success'   => true,
    'reply'     => $reply,
    'level'     => $level,
    'pathType'  => $pathType,
    'topic'     => $message,
    'score'     => $score,
    'timestamp' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);

// ============================================================
// FUNGSI: Unlock materi sesuai level & week
// Dipanggil di atas, setiap request AJAX
//
// LOGIKA:
// - Section 0 (Placement) → selalu terbuka semua
// - Section N (Week N) → buka modul berlevel sesuai nilai quiz
//   di section sebelumnya (N-1), kunci level lain
// - Modul tanpa tag Low/Medium/High → selalu terbuka
//
// NAMA MODUL YANG DIDUKUNG:
//   "Materi Week 1 Low"   → hanya terbuka jika level LOW
//   "Quiz Week 1 Medium"  → hanya terbuka jika level MEDIUM
//   "Pengantar Week 1"    → selalu terbuka (tidak berlevel)
// ============================================================
function adaptive_learning_ai_unlock_materials($courseid, $userid, $currentLevel, $DB, $CFG) {

    // Bangun peta level per section dari riwayat quiz siswa
    $sectionLevelMap = adaptive_learning_ai_build_level_map($courseid, $userid, $DB);

    // Ambil semua section course
    $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');

    $changed = false;

    foreach ($sections as $section) {
        $secNum = intval($section->section);

        // Section 0 = Placement: biarkan semua terbuka
        if ($secNum === 0) continue;

        if (empty($section->sequence)) continue;

        $cmIds = array_filter(array_map('intval', explode(',', $section->sequence)));

        // Level efektif untuk section ini = nilai quiz di section sebelumnya
        // Section 1 (Week 1) → nilai dari section 0 (Placement)
        // Section 2 (Week 2) → nilai dari section 1 (Week 1), dst
        $prevSec        = $secNum - 1;
        $effectiveLevel = $sectionLevelMap[$prevSec] ?? null;

        foreach ($cmIds as $cmId) {
            if ($cmId <= 0) continue;

            $cm = $DB->get_record('course_modules', ['id' => $cmId], 'id,module,instance,visible');
            if (!$cm) continue;

            $modType  = $DB->get_field('modules', 'name', ['id' => $cm->module]);
            $modTitle = adaptive_learning_ai_get_mod_title($modType, $cm->instance, $DB);
            $levelTag = adaptive_learning_ai_detect_level($modTitle);

            // Tentukan visible baru
            if ($levelTag === null) {
                // Modul umum → selalu terbuka
                $newVisible = 1;
            } elseif ($effectiveLevel === null) {
                // Belum ada nilai quiz sebelumnya → kunci semua modul berlevel
                $newVisible = 0;
            } elseif ($levelTag === $effectiveLevel) {
                // Level cocok → BUKA
                $newVisible = 1;
            } else {
                // Level tidak cocok → KUNCI
                $newVisible = 0;
            }

            // Hanya update jika ada perubahan (hemat query)
            if ((int)$cm->visible !== $newVisible) {
                $DB->set_field('course_modules', 'visible', $newVisible, ['id' => $cmId]);
                $changed = true;
            }
        }
    }

    // Rebuild cache hanya jika ada perubahan
    if ($changed) {
        rebuild_course_cache($courseid, true);
    }
}

// -------------------------------------------------------------
// Bangun peta: [section_number => 'LOW'|'MEDIUM'|'HIGH']
// Berdasarkan nilai quiz TERBAIK per section
// -------------------------------------------------------------
function adaptive_learning_ai_build_level_map($courseid, $userid, $DB) {
    $map = [];

    $sql = "SELECT cs.section AS secnum,
                   MAX(qa.sumgrades / NULLIF(q.grade, 0) * 100) AS bestscore
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            JOIN {course_modules} cm
                ON cm.instance = q.id
                AND cm.course = :courseid
                AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
            JOIN {course_sections} cs ON cs.id = cm.section
            WHERE qa.userid = :userid
              AND qa.state = 'finished'
              AND q.grade > 0
            GROUP BY cs.section";

    $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

    foreach ($rows as $row) {
        $secNum = intval($row->secnum);
        $score  = round((float) $row->bestscore);

        if ($score >= 90)     $map[$secNum] = 'HIGH';
        elseif ($score >= 70) $map[$secNum] = 'MEDIUM';
        else                  $map[$secNum] = 'LOW';
    }

    return $map;
}

// -------------------------------------------------------------
// Ambil judul modul dari tabelnya
// -------------------------------------------------------------
function adaptive_learning_ai_get_mod_title($modType, $instanceId, $DB) {
    $supported = ['quiz','page','resource','url','folder','label','assign','forum','scorm','h5pactivity','hvp'];
    if (!in_array($modType, $supported)) return '';
    try {
        return $DB->get_field($modType, 'name', ['id' => $instanceId]) ?: '';
    } catch (Exception $e) {
        return '';
    }
}

// -------------------------------------------------------------
// Deteksi tag level dari nama modul
// Return: 'LOW' | 'MEDIUM' | 'HIGH' | null
// -------------------------------------------------------------
function adaptive_learning_ai_detect_level($title) {
    if (empty($title)) return null;
    $t = strtolower($title);

    if (preg_match('/\bhigh\b/', $t) || strpos($t, 'advanced') !== false) return 'HIGH';
    if (preg_match('/\bmedium\b/', $t) || strpos($t, 'standard') !== false) return 'MEDIUM';
    if (preg_match('/\blow\b/', $t) || strpos($t, 'remedial') !== false)    return 'LOW';

    return null; // modul umum
}