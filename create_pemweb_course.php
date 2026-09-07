<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/page/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->libdir . '/questionlib.php');

echo "=== MEMBUAT KURSUS BARU: PEMROGRAMAN WEB (UJI COBA ACMLS) ===\n";

// 1. Cek apakah kursus sudah ada atau buat baru
$shortname = 'PEMWEB-DEMO';
$course = $DB->get_record('course', ['shortname' => $shortname]);
if ($course) {
    echo "Kursus lama dengan shortname '$shortname' ditemukan (ID: {$course->id}). Memperbarui...\n";
} else {
    echo "Membuat course baru '$shortname'...\n";
    $course = create_course((object)[
        'fullname' => 'Pemrograman Web (Uji Coba ACMLS)',
        'shortname' => $shortname,
        'category' => 1,
        'format' => 'topics',
        'numsections' => 2,
        'enablecompletion' => 1,
        'summary' => '<p>Kursus demonstrasi pembelajaran adaptif berbasis ACMLS untuk mata kuliah Pemrograman Web.</p>',
        'summaryformat' => FORMAT_HTML,
    ]);
    echo "Kursus berhasil dibuat dengan ID: {$course->id}\n";
}

$coursecontext = context_course::instance($course->id);

// 2. Daftarkan siswa1 ke kursus baru sebagai Student
$studentrole = $DB->get_record('role', ['shortname' => 'student']);
$enrolmanual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
if (!$enrolmanual) {
    $enrolplugin = enrol_get_plugin('manual');
    $enrolid = $enrolplugin->add_instance($course);
    $enrolmanual = $DB->get_record('enrol', ['id' => $enrolid]);
}

$students = $DB->get_records('user', ['deleted' => 0], 'id ASC');
foreach ($students as $u) {
    if (str_starts_with($u->username, 'siswa') || $u->id == 3) {
        if (!is_enrolled($coursecontext, $u->id)) {
            $enrolplugin = enrol_get_plugin('manual');
            $enrolplugin->enrol_user($enrolmanual, $u->id, $studentrole->id);
            echo "Daftarkan {$u->username} (ID: {$u->id}) ke kursus.\n";
        }
    }
}

// 3. Pasang block_attendanceleaderboard di kursus ini
$blockinstance = $DB->get_record('block_instances', [
    'blockname' => 'attendanceleaderboard',
    'parentcontextid' => $coursecontext->id,
]);
if (!$blockinstance) {
    $bi = new stdClass();
    $bi->blockname = 'attendanceleaderboard';
    $bi->parentcontextid = $coursecontext->id;
    $bi->showinsubcontexts = 1;
    $bi->pagetypepattern = '*';
    $bi->subpagepattern = null;
    $bi->defaultregion = 'side-pre';
    $bi->defaultweight = 0;
    $bi->timecreated = time();
    $bi->timemodified = time();
    $bi_id = $DB->insert_record('block_instances', $bi);
    echo "Blok attendanceleaderboard berhasil dipasang (ID: $bi_id).\n";
} else {
    $blockinstance->pagetypepattern = '*';
    $blockinstance->showinsubcontexts = 1;
    $DB->update_record('block_instances', $blockinstance);
    echo "Blok attendanceleaderboard sudah aktif pada kursus.\n";
}

// 4. Buat / Dapatkan Kategori Soal untuk Kursus Ini
$cat = $DB->get_record('question_categories', ['contextid' => $coursecontext->id]);
if (!$cat) {
    $cat = new stdClass();
    $cat->name = 'Kategori Soal Pemrograman Web';
    $cat->contextid = $coursecontext->id;
    $cat->info = 'Bank soal evaluasi bab pemrograman web';
    $cat->infoformat = FORMAT_HTML;
    $cat->stamp = make_unique_id_code();
    $cat->parent = 0;
    $cat->sortorder = 999;
    $cat->id = $DB->insert_record('question_categories', $cat);
    echo "Kategori soal dibuat (ID: {$cat->id}).\n";
}

// Helper untuk membuat Soal TrueFalse
function create_tf_question($catid, $name, $text, $correct_answer, $feedback = '') {
    global $DB, $USER;
    
    // Cek apakah soal sudah ada
    $existing = $DB->get_record('question', ['name' => $name]);
    if ($existing) {
        return $existing->id;
    }

    $q = new stdClass();
    $q->parent = 0;
    $q->name = $name;
    $q->questiontext = $text;
    $q->questiontextformat = FORMAT_HTML;
    $q->generalfeedback = $feedback;
    $q->generalfeedbackformat = FORMAT_HTML;
    $q->defaultmark = 10.0;
    $q->penalty = 1.0;
    $q->qtype = 'truefalse';
    $q->length = 1;
    $q->stamp = make_unique_id_code();
    $q->timecreated = time();
    $q->timemodified = time();
    $q->createdby = 2;
    $q->modifiedby = 2;
    $qid = $DB->insert_record('question', $q);

    // Answers: Benar dan Salah
    $ans_true = new stdClass();
    $ans_true->question = $qid;
    $ans_true->answer = 'Benar';
    $ans_true->answerformat = FORMAT_PLAIN;
    $ans_true->fraction = $correct_answer ? 1.0 : 0.0;
    $ans_true->feedback = $correct_answer ? 'Tepat sekali!' : 'Kurang tepat.';
    $ans_true->feedbackformat = FORMAT_HTML;
    $tid = $DB->insert_record('question_answers', $ans_true);

    $ans_false = new stdClass();
    $ans_false->question = $qid;
    $ans_false->answer = 'Salah';
    $ans_false->answerformat = FORMAT_PLAIN;
    $ans_false->fraction = !$correct_answer ? 1.0 : 0.0;
    $ans_false->feedback = !$correct_answer ? 'Tepat sekali!' : 'Kurang tepat.';
    $ans_false->feedbackformat = FORMAT_HTML;
    $fid = $DB->insert_record('question_answers', $ans_false);

    $tf = new stdClass();
    $tf->question = $qid;
    $tf->trueanswer = $tid;
    $tf->falseanswer = $fid;
    $tf->showstandardinstruction = 1;
    $DB->insert_record('question_truefalse', $tf);

    // Question bank entry & version (Moodle 4+)
    $qbe = new stdClass();
    $qbe->questioncategoryid = $catid;
    $qbe->ownerid = 2;
    $qbeid = $DB->insert_record('question_bank_entries', $qbe);

    $qv = new stdClass();
    $qv->questionbankentryid = $qbeid;
    $qv->version = 1;
    $qv->questionid = $qid;
    $qv->status = 'ready';
    $DB->insert_record('question_versions', $qv);

    return $qid;
}

// Helper untuk membuat Soal Multichoice
function create_mc_question($catid, $name, $text, array $choices, $correct_index, $feedback = '') {
    global $DB;

    $existing = $DB->get_record('question', ['name' => $name]);
    if ($existing) {
        return $existing->id;
    }

    $q = new stdClass();
    $q->parent = 0;
    $q->name = $name;
    $q->questiontext = $text;
    $q->questiontextformat = FORMAT_HTML;
    $q->generalfeedback = $feedback;
    $q->generalfeedbackformat = FORMAT_HTML;
    $q->defaultmark = 10.0;
    $q->penalty = 0.3333333;
    $q->qtype = 'multichoice';
    $q->length = 1;
    $q->stamp = make_unique_id_code();
    $q->timecreated = time();
    $q->timemodified = time();
    $q->createdby = 2;
    $q->modifiedby = 2;
    $qid = $DB->insert_record('question', $q);

    $ans_ids = [];
    foreach ($choices as $idx => $choice_text) {
        $ans = new stdClass();
        $ans->question = $qid;
        $ans->answer = $choice_text;
        $ans->answerformat = FORMAT_HTML;
        $ans->fraction = ($idx === $correct_index) ? 1.0 : 0.0;
        $ans->feedback = ($idx === $correct_index) ? 'Jawaban Anda benar!' : 'Jawaban belum tepat.';
        $ans->feedbackformat = FORMAT_HTML;
        $ans_ids[] = $DB->insert_record('question_answers', $ans);
    }

    $mc = new stdClass();
    $mc->questionid = $qid;
    $mc->layout = 0;
    $mc->single = 1;
    $mc->shuffleanswers = 1;
    $mc->correctfeedback = 'Jawaban Anda benar.';
    $mc->correctfeedbackformat = FORMAT_HTML;
    $mc->partiallycorrectfeedback = 'Jawaban Anda sebagian benar.';
    $mc->partiallycorrectfeedbackformat = FORMAT_HTML;
    $mc->incorrectfeedback = 'Jawaban Anda salah.';
    $mc->incorrectfeedbackformat = FORMAT_HTML;
    $mc->answernumbering = 'abc';
    $mc->shownumcorrect = 1;
    $mc->showstandardinstruction = 0;
    $DB->insert_record('qtype_multichoice_options', $mc);

    // Question bank entry & version (Moodle 4+)
    $qbe = new stdClass();
    $qbe->questioncategoryid = $catid;
    $qbe->ownerid = 2;
    $qbeid = $DB->insert_record('question_bank_entries', $qbe);

    $qv = new stdClass();
    $qv->questionbankentryid = $qbeid;
    $qv->version = 1;
    $qv->questionid = $qid;
    $qv->status = 'ready';
    $DB->insert_record('question_versions', $qv);

    return $qid;
}

// Helper untuk menambahkan slot pertanyaan ke kuis
function add_question_to_quiz($quizid, $questionid, $slot_num) {
    global $DB;
    
    // Ambil question_bank_entry
    $qv = $DB->get_record('question_versions', ['questionid' => $questionid]);
    if (!$qv) return;

    // Cek apakah slot sudah ada
    $existing_slot = $DB->get_record('quiz_slots', ['quizid' => $quizid, 'slot' => $slot_num]);
    if ($existing_slot) {
        return;
    }

    $slot = new stdClass();
    $slot->quizid = $quizid;
    $slot->slot = $slot_num;
    $slot->page = 1;
    $slot->displaynumber = null;
    $slot->requireprevious = 0;
    $slot->maxmark = 10.0;
    $slotid = $DB->insert_record('quiz_slots', $slot);

    // Reference
    $ref = new stdClass();
    $ref->usingcontextid = 0;
    $ref->component = 'mod_quiz';
    $ref->questionarea = 'slot';
    $ref->itemid = $slotid;
    $ref->questionbankentryid = $qv->questionbankentryid;
    $ref->version = null;
    $DB->insert_record('question_references', $ref);
}

// Pastikan section 1 dan 2 ada
$sec1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
if (!$sec1) {
    $sec1 = new stdClass();
    $sec1->course = $course->id;
    $sec1->section = 1;
    $sec1->name = 'Bab 1: Struktur Dokumen HTML5';
    $sec1->summary = '<p>Mempelajari fondasi dasar penyusunan halaman web dengan HTML5.</p>';
    $sec1->summaryformat = FORMAT_HTML;
    $sec1->visible = 1;
    $sec1->id = $DB->insert_record('course_sections', $sec1);
} else {
    $sec1->name = 'Bab 1: Struktur Dokumen HTML5';
    $DB->update_record('course_sections', $sec1);
}

$sec2 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);
if (!$sec2) {
    $sec2 = new stdClass();
    $sec2->course = $course->id;
    $sec2->section = 2;
    $sec2->name = 'Bab 2: Desain Tata Letak dengan CSS';
    $sec2->summary = '<p>Mempelajari teknik styling, selektor, dan penataan visual elemen web.</p>';
    $sec2->summaryformat = FORMAT_HTML;
    $sec2->visible = 1;
    $sec2->id = $DB->insert_record('course_sections', $sec2);
} else {
    $sec2->name = 'Bab 2: Desain Tata Letak dengan CSS';
    $DB->update_record('course_sections', $sec2);
}

// ─────────────────────────────────────────────────────────────────────────────
// SEKSI 1: BAB 1
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Menyiapkan Bab 1 ---\n";

// 1. Materi 1 (Page)
$page1_name = 'Materi Bab 1: Dasar HTML5 dan Struktur Halaman Web';
$existing_page1 = $DB->get_record('page', ['course' => $course->id, 'name' => $page1_name]);
if (!$existing_page1) {
    $p1 = new stdClass();
    $p1->course = $course->id;
    $p1->name = $page1_name;
    $p1->intro = '<p>Pelajari anatomi struktur dokumen HTML5 standar.</p>';
    $p1->introformat = FORMAT_HTML;
    $p1->content = '<h3>Struktur Dasar Dokumen HTML5</h3>' .
                   '<p>Dokumen HTML5 diawali dengan deklarasi tipe dokumen dan struktur pembungkus utama:</p>' .
                   '<pre><code>&lt;!DOCTYPE html&gt;\n&lt;html lang="id"&gt;\n  &lt;head&gt;\n    &lt;title&gt;Judul Halaman&lt;/title&gt;\n  &lt;/head&gt;\n  &lt;body&gt;\n    &lt;h1&gt;Selamat Datang&lt;/h1&gt;\n    &lt;p&gt;Ini adalah paragraf pertama saya.&lt;/p&gt;\n  &lt;/body&gt;\n&lt;/html&gt;</code></pre>' .
                   '<p>Pelajari bagian-bagian di atas sebelum beralih ke tugas praktik berikutnya.</p>';
    $p1->contentformat = FORMAT_HTML;
    $p1->timecreated = time();
    $p1->timemodified = time();
    $p1id = $DB->insert_record('page', $p1);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'page']);
    $cm->instance = $p1id;
    $cm->section = 1;
    $cm->visible = 1;
    $cm->completion = 2; // Automatic completion
    $cm->completionview = 1;
    $cm1_id = add_course_module($cm);
    course_add_cm_to_section($course, $cm1_id, 1);
    echo "Materi 1 berhasil dibuat (CM ID: $cm1_id)\n";
} else {
    $cm1 = get_coursemodule_from_instance('page', $existing_page1->id, $course->id);
    $cm1_id = $cm1->id;
    echo "Materi 1 sudah ada (CM ID: $cm1_id)\n";
}

// 2. Tugas 1 (Assign)
$assign1_name = 'Tugas Praktik Bab 1: Membuat Halaman Profil HTML5';
$existing_assign1 = $DB->get_record('assign', ['course' => $course->id, 'name' => $assign1_name]);
if (!$existing_assign1) {
    $a1 = new stdClass();
    $a1->course = $course->id;
    $a1->name = $assign1_name;
    $a1->intro = '<p>Buatlah dokumen HTML5 profil pribadi yang memuat elemen <code>&lt;h1&gt;</code>, <code>&lt;p&gt;</code>, dan daftar hobi <code>&lt;ul&gt;</code>. Masukkan teks kode HTML Anda pada kolom teks yang disediakan, lalu tekan tombol <strong>Simpan perubahan</strong>.</p>';
    $a1->introformat = FORMAT_HTML;
    $a1->alwaysshowdescription = 1;
    $a1->submissiondrafts = 0;
    $a1->requiresubmissionstatement = 0;
    $a1->sendnotifications = 0;
    $a1->sendstudentnotifications = 1;
    $a1->duedate = time() + (7 * 86400);
    $a1->allowsubmissionsfromdate = time() - 3600;
    $a1->grade = 100;
    $a1->timemodified = time();
    $a1->completionsubmit = 1; // Wajib submit untuk complete
    $a1id = $DB->insert_record('assign', $a1);

    // Konfigurasi plugin submission onlinetext
    $cfg = new stdClass();
    $cfg->assignment = $a1id;
    $cfg->plugin = 'onlinetext';
    $cfg->subtype = 'assignsubmission';
    $cfg->name = 'enabled';
    $cfg->value = '1';
    $DB->insert_record('assign_plugin_config', $cfg);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'assign']);
    $cm->instance = $a1id;
    $cm->section = 1;
    $cm->visible = 1;
    $cm->completion = 2; // Automatic completion
    $cm->completionview = 0;
    $cm->completionsubmit = 1;
    $assign1_cmid = add_course_module($cm);
    course_add_cm_to_section($course, $assign1_cmid, 1);
    echo "Tugas Praktik 1 berhasil dibuat (CM ID: $assign1_cmid)\n";
} else {
    $cm = get_coursemodule_from_instance('assign', $existing_assign1->id, $course->id);
    $assign1_cmid = $cm->id;
    echo "Tugas Praktik 1 sudah ada (CM ID: $assign1_cmid)\n";
}

// 3. Kuis 1 (Quiz) dengan Restrict Access Tugas 1
$quiz1_name = 'Kuis Evaluasi Bab 1: Pemahaman HTML5';
$existing_quiz1 = $DB->get_record('quiz', ['course' => $course->id, 'name' => $quiz1_name]);
if (!$existing_quiz1) {
    $q1 = new stdClass();
    $q1->course = $course->id;
    $q1->name = $quiz1_name;
    $q1->intro = '<p>Uji pemahaman konsep dasar HTML5 Anda melalui 3 soal evaluasi berikut.</p>';
    $q1->introformat = FORMAT_HTML;
    $q1->timeopen = time() - 3600;
    $q1->timeclose = time() + (14 * 86400);
    $q1->timelimit = 1800;
    $q1->overduehandling = 'autoabandon';
    $q1->preferredbehaviour = 'deferredfeedback';
    $q1->attempts = 3;
    $q1->grademethod = 1;
    $q1->decimalpoints = 2;
    $q1->questiondecimalpoints = -1;
    $q1->sumgrades = 30.0;
    $q1->grade = 100.0;
    $q1->timecreated = time();
    $q1->timemodified = time();
    $quiz1_id = $DB->insert_record('quiz', $q1);

    // Default section heading
    $qsec = new stdClass();
    $qsec->quizid = $quiz1_id;
    $qsec->firstslot = 1;
    $qsec->heading = '';
    $qsec->shufflequestions = 0;
    $DB->insert_record('quiz_sections', $qsec);

    // Tambahkan 3 soal Bab 1
    $q1_1 = create_mc_question($cat->id, 'B1_Q1: Wadah Utama Konten', 
        '<p>Tag manakah yang berfungsi sebagai wadah utama seluruh konten yang terlihat oleh pengguna di jendela browser?</p>',
        ['&lt;body&gt;', '&lt;head&gt;', '&lt;title&gt;', '&lt;meta&gt;'], 0);
    $q1_2 = create_tf_question($cat->id, 'B1_Q2: Hierarki Heading',
        '<p>Elemen heading <code>&lt;h1&gt;</code> memiliki hierarki judul yang lebih tinggi dan ukuran standar lebih besar daripada <code>&lt;h3&gt;</code>.</p>', true);
    $q1_3 = create_mc_question($cat->id, 'B1_Q3: Atribut Link Anchor',
        '<p>Atribut manakah yang digunakan pada elemen hyperlink <code>&lt;a&gt;</code> untuk menentukan alamat URL tujuan?</p>',
        ['href', 'src', 'link', 'target'], 0);

    add_question_to_quiz($quiz1_id, $q1_1, 1);
    add_question_to_quiz($quiz1_id, $q1_2, 2);
    add_question_to_quiz($quiz1_id, $q1_3, 3);

    // Restrict access: hanya jika Tugas 1 complete
    $availability = json_encode([
        'op' => '&',
        'c' => [
            ['type' => 'completion', 'cm' => (int)$assign1_cmid, 'e' => COMPLETION_COMPLETE]
        ],
        'showc' => [true],
    ]);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'quiz']);
    $cm->instance = $quiz1_id;
    $cm->section = 1;
    $cm->visible = 1;
    $cm->completion = 1;
    $cm->availability = $availability;
    $quiz1_cmid = add_course_module($cm);
    course_add_cm_to_section($course, $quiz1_cmid, 1);
    echo "Kuis 1 berhasil dibuat (CM ID: $quiz1_cmid) dengan 3 soal & Restrict Access Tugas 1.\n";
} else {
    $cm = get_coursemodule_from_instance('quiz', $existing_quiz1->id, $course->id);
    $quiz1_cmid = $cm->id;
    echo "Kuis 1 sudah ada (CM ID: $quiz1_cmid)\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// SEKSI 2: BAB 2
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Menyiapkan Bab 2 ---\n";

// 1. Materi 2 (Page)
$page2_name = 'Materi Bab 2: Pengenalan CSS dan Selektor Dasar';
$existing_page2 = $DB->get_record('page', ['course' => $course->id, 'name' => $page2_name]);
if (!$existing_page2) {
    $p2 = new stdClass();
    $p2->course = $course->id;
    $p2->name = $page2_name;
    $p2->intro = '<p>Pelajari cara mempercantik tampilan halaman web menggunakan Cascading Style Sheets (CSS).</p>';
    $p2->introformat = FORMAT_HTML;
    $p2->content = '<h3>Konsep Dasar CSS</h3>' .
                   '<p>CSS digunakan untuk mengatur gaya visual elemen HTML. Struktur aturan CSS terdiri dari selektor dan blok deklarasi:</p>' .
                   '<pre><code>/* Selektor Element */\np {\n  color: #333333;\n  font-size: 16px;\n}\n\n/* Selektor Class */\n.highlight {\n  background-color: #fef08a;\n}</code></pre>' .
                   '<p>Tiga selektor utama yang sering digunakan adalah Selektor Elemen, Selektor Class (diawali tanda titik), dan Selektor ID (diawali tanda pagar).</p>';
    $p2->contentformat = FORMAT_HTML;
    $p2->timecreated = time();
    $p2->timemodified = time();
    $p2id = $DB->insert_record('page', $p2);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'page']);
    $cm->instance = $p2id;
    $cm->section = 2;
    $cm->visible = 1;
    $cm->completion = 2;
    $cm->completionview = 1;
    $cm2_id = add_course_module($cm);
    course_add_cm_to_section($course, $cm2_id, 2);
    echo "Materi 2 berhasil dibuat (CM ID: $cm2_id)\n";
} else {
    $cm2 = get_coursemodule_from_instance('page', $existing_page2->id, $course->id);
    $cm2_id = $cm2->id;
    echo "Materi 2 sudah ada (CM ID: $cm2_id)\n";
}

// 2. Tugas 2 (Assign)
$assign2_name = 'Tugas Praktik Bab 2: Menghias Halaman dengan CSS';
$existing_assign2 = $DB->get_record('assign', ['course' => $course->id, 'name' => $assign2_name]);
if (!$existing_assign2) {
    $a2 = new stdClass();
    $a2->course = $course->id;
    $a2->name = $assign2_name;
    $a2->intro = '<p>Buatlah aturan CSS sederhana yang mengatur warna teks, jenis font, serta padding pada elemen halaman profil Anda. Kumpulkan kode CSS Anda pada kolom teks yang disediakan.</p>';
    $a2->introformat = FORMAT_HTML;
    $a2->alwaysshowdescription = 1;
    $a2->submissiondrafts = 0;
    $a2->requiresubmissionstatement = 0;
    $a2->sendnotifications = 0;
    $a2->sendstudentnotifications = 1;
    $a2->duedate = time() + (7 * 86400);
    $a2->allowsubmissionsfromdate = time() - 3600;
    $a2->grade = 100;
    $a2->timemodified = time();
    $a2->completionsubmit = 1;
    $a2id = $DB->insert_record('assign', $a2);

    $cfg = new stdClass();
    $cfg->assignment = $a2id;
    $cfg->plugin = 'onlinetext';
    $cfg->subtype = 'assignsubmission';
    $cfg->name = 'enabled';
    $cfg->value = '1';
    $DB->insert_record('assign_plugin_config', $cfg);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'assign']);
    $cm->instance = $a2id;
    $cm->section = 2;
    $cm->visible = 1;
    $cm->completion = 2;
    $cm->completionview = 0;
    $cm->completionsubmit = 1;
    $assign2_cmid = add_course_module($cm);
    course_add_cm_to_section($course, $assign2_cmid, 2);
    echo "Tugas Praktik 2 berhasil dibuat (CM ID: $assign2_cmid)\n";
} else {
    $cm = get_coursemodule_from_instance('assign', $existing_assign2->id, $course->id);
    $assign2_cmid = $cm->id;
    echo "Tugas Praktik 2 sudah ada (CM ID: $assign2_cmid)\n";
}

// 3. Kuis 2 (Quiz) dengan Restrict Access Tugas 2
$quiz2_name = 'Kuis Evaluasi Bab 2: Pemahaman CSS';
$existing_quiz2 = $DB->get_record('quiz', ['course' => $course->id, 'name' => $quiz2_name]);
if (!$existing_quiz2) {
    $q2 = new stdClass();
    $q2->course = $course->id;
    $q2->name = $quiz2_name;
    $q2->intro = '<p>Uji pemahaman konsep CSS dan selektor Anda melalui 3 pertanyaan berikut.</p>';
    $q2->introformat = FORMAT_HTML;
    $q2->timeopen = time() - 3600;
    $q2->timeclose = time() + (14 * 86400);
    $q2->timelimit = 1800;
    $q2->overduehandling = 'autoabandon';
    $q2->preferredbehaviour = 'deferredfeedback';
    $q2->attempts = 3;
    $q2->grademethod = 1;
    $q2->decimalpoints = 2;
    $q2->questiondecimalpoints = -1;
    $q2->sumgrades = 30.0;
    $q2->grade = 100.0;
    $q2->timecreated = time();
    $q2->timemodified = time();
    $quiz2_id = $DB->insert_record('quiz', $q2);

    $qsec = new stdClass();
    $qsec->quizid = $quiz2_id;
    $qsec->firstslot = 1;
    $qsec->heading = '';
    $qsec->shufflequestions = 0;
    $DB->insert_record('quiz_sections', $qsec);

    // Tambahkan 3 soal Bab 2
    $q2_1 = create_mc_question($cat->id, 'B2_Q1: Simbol Selektor Class',
        '<p>Karakter simbol apakah yang digunakan dalam sintaks CSS untuk mendefinisikan selektor class?</p>',
        ['. (titik)', '# (tanda pagar)', '* (bintang)', '@ (at)'], 0);
    $q2_2 = create_tf_question($cat->id, 'B2_Q2: Fungsi Padding',
        '<p>Properti <code>padding</code> dalam model kotak (box model) CSS digunakan untuk mengatur jarak ruang di antara konten elemen dengan batas tepinya (border).</p>', true);
    $q2_3 = create_mc_question($cat->id, 'B2_Q3: Properti Warna Latar',
        '<p>Properti CSS manakah yang digunakan untuk mengubah warna latar belakang (background) dari suatu elemen?</p>',
        ['background-color', 'color', 'bgcolor', 'canvas-color'], 0);

    add_question_to_quiz($quiz2_id, $q2_1, 1);
    add_question_to_quiz($quiz2_id, $q2_2, 2);
    add_question_to_quiz($quiz2_id, $q2_3, 3);

    $availability = json_encode([
        'op' => '&',
        'c' => [
            ['type' => 'completion', 'cm' => (int)$assign2_cmid, 'e' => COMPLETION_COMPLETE]
        ],
        'showc' => [true],
    ]);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $DB->get_field('modules', 'id', ['name' => 'quiz']);
    $cm->instance = $quiz2_id;
    $cm->section = 2;
    $cm->visible = 1;
    $cm->completion = 1;
    $cm->availability = $availability;
    $quiz2_cmid = add_course_module($cm);
    course_add_cm_to_section($course, $quiz2_cmid, 2);
    echo "Kuis 2 berhasil dibuat (CM ID: $quiz2_cmid) dengan 3 soal & Restrict Access Tugas 2.\n";
} else {
    $cm = get_coursemodule_from_instance('quiz', $existing_quiz2->id, $course->id);
    $quiz2_cmid = $cm->id;
    echo "Kuis 2 sudah ada (CM ID: $quiz2_cmid)\n";
}

// Rebuild course cache
rebuild_course_cache($course->id, true);
purge_all_caches();

echo "\n=== KURSUS PEMROGRAMAN WEB SIAP DIGUNAKAN ===\n";
echo "Course ID: {$course->id}\n";
echo "URL: {$CFG->wwwroot}/course/view.php?id={$course->id}\n";
