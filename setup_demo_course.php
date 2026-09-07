<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/page/lib.php');
if (file_exists($CFG->dirroot . '/mod/aicode/lib.php')) {
    require_once($CFG->dirroot . '/mod/aicode/lib.php');
}

echo "=== MEMERIKSA STATUS KURSUS & MODUL ===\n";

// 1. Periksa Course ID 2
$course = $DB->get_record('course', ['id' => 2]);
if (!$course) {
    echo "Course ID 2 tidak ditemukan. Membuat course baru...\n";
    $course = create_course((object)[
        'fullname' => 'Pemrograman Web JavaScript (5 Bab)',
        'shortname' => 'JS-5BAB',
        'category' => 1,
        'format' => 'topics',
        'numsections' => 5,
        'enablecompletion' => 1,
    ]);
} else {
    echo "Course ditemukan: " . $course->fullname . " (ID: " . $course->id . ")\n";
    if (empty($course->enablecompletion)) {
        $course->enablecompletion = 1;
        $DB->update_record('course', $course);
        echo "Activity completion diaktifkan untuk course.\n";
    }
}

// 2. Pastikan block_attendanceleaderboard ada di course
$context = context_course::instance($course->id);
$blockinstance = $DB->get_record('block_instances', [
    'blockname' => 'attendanceleaderboard',
    'parentcontextid' => $context->id,
]);
if (!$blockinstance) {
    echo "Menambahkan blok attendanceleaderboard ke kursus...\n";
    $bi = new stdClass();
    $bi->blockname = 'attendanceleaderboard';
    $bi->parentcontextid = $context->id;
    $bi->showinsubcontexts = 0;
    $bi->pagetypepattern = 'course-view-*';
    $bi->subpagepattern = null;
    $bi->defaultregion = 'side-pre';
    $bi->defaultweight = 0;
    $bi->timecreated = time();
    $bi->timemodified = time();
    $DB->insert_record('block_instances', $bi);
    echo "Blok attendanceleaderboard berhasil ditambahkan.\n";
} else {
    echo "Blok attendanceleaderboard sudah terpasang di kursus.\n";
}

// 3. Tambahkan modul Page Materi di Section 1 jika belum ada
$existing_page = $DB->get_record('page', ['course' => $course->id]);
if (!$existing_page) {
    echo "Membuat modul Materi Pembelajaran Bab 1 (mod_page)...\n";
    $page = new stdClass();
    $page->course = $course->id;
    $page->name = 'Materi Bab 1: Dasar Fungsi JavaScript';
    $page->intro = '<p>Pelajari konsep dasar function dan return value pada JavaScript.</p>';
    $page->introformat = FORMAT_HTML;
    $page->content = '<h3>Konsep Fungsi JavaScript</h3><p>Fungsi adalah blok kode yang dirancang untuk melakukan tugas tertentu. Sintaks dasar:</p><pre><code>function jumlahkan(a, b) {\n    return a + b;\n}</code></pre><p>Setelah memahami materi ini, silakan lanjutkan ke latihan pemrograman di bawah.</p>';
    $page->contentformat = FORMAT_HTML;
    $page->timecreated = time();
    $page->timemodified = time();
    $pageid = $DB->insert_record('page', $page);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'page']);
    $cm->instance = $pageid;
    $cm->section = 1;
    $cm->visible = 1;
    $cm->completion = 1; // Tracking completion manual/view
    $cmid = add_course_module($cm);
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
    course_add_cm_to_section($course, $cmid, 1);
    echo "Materi Bab 1 berhasil dibuat (CM ID: $cmid)\n";
} else {
    echo "Materi Bab 1 sudah ada (Page ID: $existing_page->id)\n";
}

// 4. Periksa apakah modul aicode sudah ada di course
$existing_aicode = $DB->get_record('aicode', ['course' => $course->id]);
if (!$existing_aicode) {
    echo "Membuat aktivitas Latihan Coding AICode di Section 1...\n";
    $aicode = new stdClass();
    $aicode->course = $course->id;
    $aicode->name = 'Latihan Coding Bab 1: Fungsi Penjumlahan';
    $aicode->intro = '<p>Selesaikan tantangan coding ini hingga seluruh test cases bernilai BENAR untuk membuka kuis.</p>';
    $aicode->introformat = FORMAT_HTML;
    $aicode->description = 'Buatlah fungsi jumlahkan(a, b) yang mengembalikan hasil a + b.';
    $aicode->language = 'javascript';
    $aicode->startercode = "// Lengkapi fungsi di bawah ini\nfunction jumlahkan(a, b) {\n    // Tulis kode Anda di sini\n    return a + b;\n}\n";
    $aicode->testcases = json_encode([
        ["input" => "5, 3", "expected" => "8"],
        ["input" => "10, 20", "expected" => "30"],
        ["input" => "-5, 5", "expected" => "0"],
    ]);
    $aicode->allow_training = 0;
    $aicode->mode = 'training';
    $aicode->timecreated = time();
    $aicode->timemodified = time();

    $aicodeid = aicode_add_instance($aicode);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'aicode']);
    $cm->instance = $aicodeid;
    $cm->section = 1;
    $cm->visible = 1;
    $cm->completion = 2; // Automatic completion
    $cm->completionview = 1;
    $cmid = add_course_module($cm);
    course_add_cm_to_section($course, $cmid, 1);
    echo "Aktivitas AICode berhasil dibuat (CM ID: $cmid)\n";
    $aicode_cmid = $cmid;
} else {
    echo "Aktivitas AICode sudah ada (AICode ID: $existing_aicode->id)\n";
    $aicode_cm = get_coursemodule_from_instance('aicode', $existing_aicode->id, $course->id);
    $aicode_cmid = $aicode_cm ? $aicode_cm->id : 0;
}

// 5. Periksa Kuis di course dan pasang Restrict Access jika modul aicode ada
$existing_quiz = $DB->get_record('quiz', ['course' => $course->id]);
if ($existing_quiz) {
    echo "Kuis ditemukan: $existing_quiz->name (ID: $existing_quiz->id)\n";
    $quiz_cm = get_coursemodule_from_instance('quiz', $existing_quiz->id, $course->id);
    if ($quiz_cm && $aicode_cmid > 0) {
        // Pastikan kuis ada di sequence section 1
        $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $seq = explode(',', $section1->sequence);
        if (!in_array((string)$quiz_cm->id, $seq)) {
            $seq[] = (string)$quiz_cm->id;
            $section1->sequence = implode(',', array_filter($seq));
            $DB->update_record('course_sections', $section1);
        }

        // Pastikan default quiz_sections ada
        if (!$DB->record_exists('quiz_sections', ['quizid' => $existing_quiz->id])) {
            $qsec = new stdClass();
            $qsec->quizid = $existing_quiz->id;
            $qsec->firstslot = 1;
            $qsec->heading = '';
            $qsec->shufflequestions = 0;
            $DB->insert_record('quiz_sections', $qsec);
        }

        // Pasang restriction: kuis hanya terbuka jika aicode tuntas
        $availability = json_encode([
            'op' => '&',
            'c' => [
                [
                    'type' => 'completion',
                    'cm' => (int)$aicode_cmid,
                    'e' => COMPLETION_COMPLETE,
                ]
            ],
            'showc' => [true],
        ]);
        $DB->set_field('course_modules', 'availability', $availability, ['id' => $quiz_cm->id]);
        echo "Restrict Access berhasil dipasang pada Kuis (Terkunci sebelum AICode CM $aicode_cmid selesai).\n";
    }
}

// Purge cache Moodle
purge_all_caches();
echo "Cache Moodle berhasil dibersihkan.\n";
echo "=== SELESAI ===\n";
