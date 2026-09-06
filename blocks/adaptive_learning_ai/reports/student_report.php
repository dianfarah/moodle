<?php
require_once('../../../config.php');
require_once($CFG->dirroot . '/blocks/adaptive_learning_ai/classes/report_helper.php');

global $OUTPUT, $USER, $DB;

$courseid  = required_param('courseid', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);

// ── FIX: Gunakan is_enrolled() bukan require_capability ──────────────
// Siswa yang enrolled otomatis bisa akses — tidak perlu capability tinggi
if (!is_enrolled($context, $USER->id, '', true)) {
    redirect(new moodle_url('/'), get_string('notenrolled', 'block_adaptive_learning_ai'));
}

// Siswa hanya bisa lihat laporan miliknya sendiri
// Guru/admin bisa lihat semua (diarahkan ke teacher_report)
$is_teacher = has_capability('moodle/course:update', $context) ||
              has_capability('mod/quiz:viewreports', $context);

if ($is_teacher && $USER->id !== optional_param('userid', $USER->id, PARAM_INT)) {
    redirect(new moodle_url('/blocks/adaptive_learning_ai/reports/teacher_report.php',
        ['courseid' => $courseid]));
}

$PAGE->set_url(new moodle_url('/blocks/adaptive_learning_ai/reports/student_report.php',
    ['courseid' => $courseid, 'attemptid' => $attemptid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('report_student', 'block_adaptive_learning_ai'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('report_student', 'block_adaptive_learning_ai'));
$PAGE->requires->css(new moodle_url('/blocks/adaptive_learning_ai/styles/report.css'));

echo $OUTPUT->header();

// ── WRAPPER ──────────────────────────────────────────────────────────
echo html_writer::start_div('adaptive-learning-ai-student-report');
echo html_writer::tag('div',
    html_writer::tag('h1', get_string('report_student', 'block_adaptive_learning_ai')),
    ['class' => 'alai-report-header']
);

// ── INFO SISWA ───────────────────────────────────────────────────────
$userpic = $OUTPUT->user_picture($USER, ['size' => 40, 'link' => false]);
echo html_writer::div(
    $userpic .
    html_writer::div(
        html_writer::tag('strong', fullname($USER)) .
        html_writer::tag('span', ' — ' . format_string($course->fullname),
            ['style' => 'color:#64748b; font-size:13px;']),
        'alai-user-info-text'
    ),
    'alai-user-info-bar',
    ['style' => 'display:flex; align-items:center; gap:12px; padding:14px 20px;
                 background:linear-gradient(135deg,#eff6ff,#dbeafe);
                 border-radius:12px; margin-bottom:20px;
                 border:1px solid #bfdbfe;']
);

echo html_writer::start_div('alai-report-container');

// ── AMBIL DATA ATTEMPTS ──────────────────────────────────────────────
$attempts        = adaptive_learning_ai_report_helper::get_student_quiz_attempts($courseid, $USER->id);
$selectedattempt = null;

if ($attemptid) {
    foreach ($attempts as $attempt) {
        if ($attempt->id == $attemptid) {
            $selectedattempt = $attempt;
            break;
        }
    }
}

if (!$selectedattempt && !empty($attempts)) {
    foreach ($attempts as $attempt) {
        if ($attempt->state === 'finished') {
            $selectedattempt = $attempt;
            break;
        }
    }
    if (!$selectedattempt) {
        $selectedattempt = reset($attempts);
    }
}

// ── TIDAK ADA DATA ───────────────────────────────────────────────────
if (empty($attempts)) {
    echo html_writer::div(
        html_writer::tag('div', '📋', ['style' => 'font-size:40px; margin-bottom:10px;']) .
        html_writer::tag('p', get_string('no_quiz_data', 'block_adaptive_learning_ai'),
            ['style' => 'color:#64748b; font-size:14px;']),
        'alert alert-info text-center',
        ['style' => 'text-align:center; padding:30px;']
    );
} else {

    // ── RINGKASAN NILAI ──────────────────────────────────────────────
    $finished_attempts = array_filter($attempts, fn($a) => $a->state === 'finished' && $a->sumgrades !== null);
    if (!empty($finished_attempts)) {
        $scores    = array_map(fn($a) => adaptive_learning_ai_report_helper::calculate_percentage($a->sumgrades, $a->max_grade), $finished_attempts);
        $avg_score = round(array_sum($scores) / count($scores));
        $max_score = max($scores);
        $min_score = min($scores);
        $total     = count($attempts);

        // Tentukan level dari rata-rata
        if ($avg_score >= 90)     { $lvl = 'HIGH';   $lvl_color = '#059669'; $lvl_bg = '#dcfce7'; $lvl_icon = '🏆'; }
        elseif ($avg_score >= 70) { $lvl = 'MEDIUM'; $lvl_color = '#2563eb'; $lvl_bg = '#dbeafe'; $lvl_icon = '📈'; }
        else                      { $lvl = 'LOW';    $lvl_color = '#d97706'; $lvl_bg = '#fef3c7'; $lvl_icon = '📚'; }

        echo html_writer::div(
            // Stat 1
            html_writer::div(
                html_writer::div('🎯', 'alai-stat-icon') .
                html_writer::div($avg_score . '%', 'alai-stat-value') .
                html_writer::div('Rata-rata Nilai', 'alai-stat-label'),
                'alai-stat-card'
            ) .
            // Stat 2
            html_writer::div(
                html_writer::div('⭐', 'alai-stat-icon') .
                html_writer::div($max_score . '%', 'alai-stat-value') .
                html_writer::div('Nilai Tertinggi', 'alai-stat-label'),
                'alai-stat-card'
            ) .
            // Stat 3
            html_writer::div(
                html_writer::div('📝', 'alai-stat-icon') .
                html_writer::div($min_score . '%', 'alai-stat-value') .
                html_writer::div('Nilai Terendah', 'alai-stat-label'),
                'alai-stat-card'
            ) .
            // Stat 4
            html_writer::div(
                html_writer::div($lvl_icon, 'alai-stat-icon') .
                html_writer::div(
                    html_writer::tag('span', $lvl,
                        ['style' => "color:{$lvl_color}; background:{$lvl_bg}; padding:2px 10px;
                                     border-radius:20px; font-size:13px; font-weight:700;"]),
                    'alai-stat-value'
                ) .
                html_writer::div('Level Saat Ini', 'alai-stat-label'),
                'alai-stat-card'
            ) .
            // Stat 5
            html_writer::div(
                html_writer::div('📋', 'alai-stat-icon') .
                html_writer::div($total, 'alai-stat-value') .
                html_writer::div('Total Quiz', 'alai-stat-label'),
                'alai-stat-card'
            ),
            'alai-stats-summary',
            ['style' => 'display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr));
                         gap:10px; margin-bottom:24px;']
        );
    }

    // ── TABEL DAFTAR ATTEMPTS ────────────────────────────────────────
    echo html_writer::tag('h3', '📋 Riwayat Quiz Saya', ['class' => 'alai-list-title',
        'style' => 'font-size:16px; font-weight:700; color:#1e40af; margin-bottom:12px;']);

    $table             = new html_table();
    $table->attributes = ['class' => 'alai-attempts-table generaltable'];
    $table->head       = [
        get_string('quiz_detail',    'block_adaptive_learning_ai'),
        get_string('student_score',  'block_adaptive_learning_ai'),
        'Level',
        get_string('quiz_status',    'block_adaptive_learning_ai'),
        get_string('quiz_date_time', 'block_adaptive_learning_ai'),
    ];
    $table->data = [];

    foreach ($attempts as $attempt) {
        $pct    = $attempt->sumgrades !== null
                  ? adaptive_learning_ai_report_helper::calculate_percentage($attempt->sumgrades, $attempt->max_grade)
                  : null;
        $score  = $pct !== null ? $pct . '%' : '-';
        $status = adaptive_learning_ai_report_helper::get_attempt_status($attempt->state);
        $date   = adaptive_learning_ai_report_helper::format_time($attempt->timefinish ?: $attempt->timestart);

        // Badge level berdasarkan nama quiz
        $name_lower = strtolower($attempt->quiz_name);
        if (strpos($name_lower, 'high') !== false)         { $badge = html_writer::tag('span', '🏆 High',   ['style' => 'background:#dcfce7;color:#059669;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;']); }
        elseif (strpos($name_lower, 'medium') !== false)   { $badge = html_writer::tag('span', '📈 Medium', ['style' => 'background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;']); }
        elseif (strpos($name_lower, 'low') !== false)      { $badge = html_writer::tag('span', '📚 Low',    ['style' => 'background:#fef3c7;color:#d97706;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;']); }
        elseif (strpos($name_lower, 'remidial') !== false ||
                strpos($name_lower, 'remedial') !== false)  { $badge = html_writer::tag('span', '⚠️ Remidial',['style' => 'background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;']); }
        else                                                { $badge = html_writer::tag('span', '—',         ['style' => 'color:#94a3b8;font-size:11px;']); }

        $link          = new moodle_url('/blocks/adaptive_learning_ai/reports/student_report.php',
                            ['courseid' => $courseid, 'attemptid' => $attempt->id]);
        $table->data[] = [html_writer::link($link, format_string($attempt->quiz_name)), $score, $badge, $status, $date];
    }
    echo html_writer::table($table);

    // ── DETAIL JAWABAN ATTEMPT TERPILIH ─────────────────────────────
    if ($selectedattempt) {
        echo html_writer::tag('h4',
            '📝 Detail Jawaban — ' . format_string($selectedattempt->quiz_name),
            ['class' => 'alai-detail-title',
             'style' => 'font-size:15px; font-weight:700; color:#1e40af; margin:24px 0 12px;']);

        $responses = adaptive_learning_ai_report_helper::get_question_responses($selectedattempt->id);

        if (empty($responses)) {
            echo html_writer::tag('div',
                get_string('no_quiz_data', 'block_adaptive_learning_ai'),
                ['class' => 'alert alert-warning']);
        } else {
            $detailtable             = new html_table();
            $detailtable->attributes = ['class' => 'alai-responses-table generaltable'];
            $detailtable->head       = [
                '#',
                get_string('question_text',   'block_adaptive_learning_ai'),
                get_string('student_answer',  'block_adaptive_learning_ai'),
                get_string('correct_answer',  'block_adaptive_learning_ai'),
                get_string('question_score',  'block_adaptive_learning_ai'),
            ];
            $detailtable->data = [];
            $no = 1;

            foreach ($responses as $response) {
                $pct_q     = adaptive_learning_ai_report_helper::calculate_percentage($response->fraction, 1);
                $is_correct = $pct_q >= 100;
                $score_badge = html_writer::tag('span',
                    ($is_correct ? '✅ ' : '❌ ') . $pct_q . '%',
                    ['style' => $is_correct
                        ? 'color:#059669; font-weight:700;'
                        : 'color:#dc2626; font-weight:700;']
                );

                $detailtable->data[] = [
                    $no++,
                    format_text($response->questiontext, FORMAT_HTML),
                    s($response->responsesummary),
                    s($response->rightanswer),
                    $score_badge,
                ];
            }
            echo html_writer::table($detailtable);
        }
    }
}

// ── TOMBOL KEMBALI ───────────────────────────────────────────────────
echo html_writer::div(
    html_writer::link(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        '← Kembali ke Kursus',
        ['class' => 'btn btn-secondary',
         'style' => 'margin-top:20px; padding:8px 20px; border-radius:8px;']
    ),
    '',
    ['style' => 'margin-top:16px;']
);

echo html_writer::end_div(); // alai-report-container
echo html_writer::end_div(); // adaptive-learning-ai-student-report
echo $OUTPUT->footer();