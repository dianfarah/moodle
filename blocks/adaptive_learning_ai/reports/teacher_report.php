<?php
require_once('../../../config.php');
require_once($CFG->dirroot . '/blocks/adaptive_learning_ai/classes/report_helper.php');

global $OUTPUT, $DB, $USER;

$courseid = required_param('courseid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);

if (!has_capability('moodle/course:update', $context) && !has_capability('mod/quiz:viewreports', $context)) {
    redirect(new moodle_url('/blocks/adaptive_learning_ai/reports/student_report.php', ['courseid' => $courseid]));
}

$PAGE->set_url(new moodle_url('/blocks/adaptive_learning_ai/reports/teacher_report.php', ['courseid' => $courseid, 'userid' => $userid, 'attemptid' => $attemptid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('report_teacher', 'block_adaptive_learning_ai'));
$PAGE->set_heading(format_string($course->fullname));

$PAGE->navbar->add(get_string('report_teacher', 'block_adaptive_learning_ai'));

$PAGE->requires->css(new moodle_url('/blocks/adaptive_learning_ai/styles/report.css'));

echo $OUTPUT->header();

echo html_writer::start_div('adaptive-learning-ai-teacher-report');

echo html_writer::tag('div', html_writer::tag('h1', get_string('report_teacher', 'block_adaptive_learning_ai')), ['class' => 'alai-report-header']);

echo html_writer::start_div('alai-report-container');

$students = adaptive_learning_ai_report_helper::get_course_students($courseid);
$student_labels = [];
$student_data = [];

foreach ($students as $student) {
    $student_name = fullname($student);
    $student_labels[] = $student_name;
    $average = adaptive_learning_ai_report_helper::get_student_average_score($courseid, $student->id);
    $student_data[] = $average !== null ? $average : 0;
}

if (!empty($student_labels)) {
    $chart = new \core\chart_line(); // <-- ubah di sini
    $series = new \core\chart_series(get_string('average_score', 'block_adaptive_learning_ai') . ' (%)', $student_data);
    $chart->add_series($series);
    $chart->set_labels($student_labels);
    $chart->set_title(get_string('report_graph_title', 'block_adaptive_learning_ai'));

    echo html_writer::start_div('alai-chart-section');
    echo html_writer::tag('h3', get_string('report_graph_title', 'block_adaptive_learning_ai'), ['class' => 'alai-section-title']);
    echo html_writer::tag('div', $OUTPUT->render($chart), ['class' => 'alai-chart-wrapper']);
    echo html_writer::end_div();
}

if ($userid) {
    $student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id,firstname,lastname,email', MUST_EXIST);

    if (!is_enrolled($context, $student, '', true)) {
        print_error('errorunenrolleduser', 'moodle');
    }

    $attempts = adaptive_learning_ai_report_helper::get_student_quiz_attempts($courseid, $userid);
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

    echo html_writer::tag('h3', fullname($student), ['class' => 'alai-student-name']);

    if (empty($attempts)) {
        echo html_writer::tag('div', get_string('no_quiz_data', 'block_adaptive_learning_ai'), ['class' => 'alert alert-info']);
    } else {
        // Student progress chart
        $quiz_names = [];
        $quiz_scores = [];
        foreach ($attempts as $attempt) {
            if ($attempt->state === 'finished' && $attempt->sumgrades !== null) {
                $quiz_names[] = format_string($attempt->quiz_name);
                $quiz_scores[] = adaptive_learning_ai_report_helper::calculate_percentage($attempt->sumgrades, $attempt->max_grade);
            }
        }
    
        if (!empty($quiz_scores)) {
        // Urutkan attempts berdasarkan timestart (yang dikerjakan lebih dulu di kiri)
        $sorted_attempts = [];
        foreach ($attempts as $attempt) {
            if ($attempt->state === 'finished' && $attempt->sumgrades !== null) {
                $sorted_attempts[] = $attempt;
            }
        }
        usort($sorted_attempts, function($a, $b) {
            return $a->timestart - $b->timestart;
        });

        $quiz_names = [];
        $quiz_scores = [];
        foreach ($sorted_attempts as $attempt) {
            $score_pct  = adaptive_learning_ai_report_helper::calculate_percentage($attempt->sumgrades, $attempt->max_grade);
            $quiz_name  = format_string($attempt->quiz_name);
            $name_lower = strtolower($quiz_name);

            // Ambil level dari nama quiz (bukan dari nilai)
            if (strpos($name_lower, 'high') !== false) {
                $materi_level = 'Materi High';
            } elseif (strpos($name_lower, 'medium') !== false) {
                $materi_level = 'Materi Medium';
            } elseif (strpos($name_lower, 'low') !== false) {
                $materi_level = 'Materi Low';
            } else {
                $materi_level = null; // Tidak ada level di nama quiz
            }

            // Label: "Quiz 1 High (Materi High)" atau "Quiz Final" jika tidak ada level
            $quiz_names[] = $materi_level ? $quiz_name . ' (' . $materi_level . ')' : $quiz_name;
            $quiz_scores[] = $score_pct;
        }

            $student_chart = new \core\chart_line();
            $student_chart->set_smooth(true); // <-- garis melengkung/smooth

            $student_series = new \core\chart_series(get_string('student_score', 'block_adaptive_learning_ai') . ' (%)', $quiz_scores);
            $student_series->set_color('#6a0dad'); // warna ungu seperti screenshot

            $student_chart->add_series($student_series);
            $student_chart->set_labels($quiz_names);
            $student_chart->set_title(get_string('student_progress_chart', 'block_adaptive_learning_ai'));

            // Sumbu Y: skala 10 sampai 100
            $yaxis = new \core\chart_axis();
            $yaxis->set_min(10);
            $yaxis->set_max(100);
            $student_chart->set_yaxis($yaxis);

            // Buat tabel ringkasan level per quiz untuk ditampilkan di bawah chart
            $level_summary_rows = '';
            foreach ($sorted_attempts as $attempt) {
                $pct = adaptive_learning_ai_report_helper::calculate_percentage($attempt->sumgrades, $attempt->max_grade);
                if ($pct >= 90) {
                    $badge_style = 'background:#dcfce7;color:#059669;border:1px solid #a7f3d0;';
                    $badge_text  = '🏆 High';
                    $next_label  = 'Materi High dibuka minggu berikutnya';
                } elseif ($pct >= 70) {
                    $badge_style = 'background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe;';
                    $badge_text  = '📈 Medium';
                    $next_label  = 'Materi Medium dibuka minggu berikutnya';
                } else {
                    $badge_style = 'background:#fef3c7;color:#d97706;border:1px solid #fde68a;';
                    $badge_text  = '📚 Low';
                    $next_label  = 'Materi Low dibuka + Mode Remedial';
                }
                $badge = html_writer::tag('span', $badge_text, [
                    'style' => $badge_style . 'padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;'
                ]);
                $level_summary_rows .= html_writer::tag('tr',
                    html_writer::tag('td', format_string($attempt->quiz_name), ['style' => 'padding:7px 12px;font-size:13px;color:#334155;']) .
                    html_writer::tag('td', $pct . '%', ['style' => 'padding:7px 12px;font-size:13px;font-weight:700;color:#6a0dad;text-align:center;']) .
                    html_writer::tag('td', $badge, ['style' => 'padding:7px 12px;text-align:center;']) .
                    html_writer::tag('td', $next_label, ['style' => 'padding:7px 12px;font-size:12px;color:#64748b;']),
                    ['style' => 'border-bottom:1px solid #f1f5f9;']
                );
            }

            $level_legend_html = '
            <div style="margin-top:14px;background:#fff;border:1px solid #e8f0fe;border-radius:12px;overflow:hidden;">
                <div style="background:linear-gradient(135deg,#f8faff,#eff6ff);padding:10px 16px;border-bottom:1px solid #e8f0fe;display:flex;align-items:center;gap:8px;">
                    <span style="font-size:14px;">🎯</span>
                    <strong style="font-size:13px;color:#1e40af;">Status Level Materi Adaptif</strong>
                    <span style="margin-left:auto;font-size:11px;color:#64748b;">Level ditentukan otomatis dari nilai quiz</span>
                </div>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8faff;">
                            <th style="padding:8px 12px;font-size:11px;color:#64748b;font-weight:600;text-align:left;border-bottom:1px solid #e8f0fe;">Quiz</th>
                            <th style="padding:8px 12px;font-size:11px;color:#64748b;font-weight:600;text-align:center;border-bottom:1px solid #e8f0fe;">Nilai</th>
                            <th style="padding:8px 12px;font-size:11px;color:#64748b;font-weight:600;text-align:center;border-bottom:1px solid #e8f0fe;">Level Materi</th>
                            <th style="padding:8px 12px;font-size:11px;color:#64748b;font-weight:600;text-align:left;border-bottom:1px solid #e8f0fe;">Aksi Adaptif</th>
                        </tr>
                    </thead>
                    <tbody>' . $level_summary_rows . '</tbody>
                </table>
                <div style="padding:10px 16px;background:#f8faff;border-top:1px solid #e8f0fe;display:flex;gap:16px;flex-wrap:wrap;">
                    <span style="font-size:11px;display:flex;align-items:center;gap:5px;">
                        <span style="background:#dcfce7;color:#059669;padding:2px 8px;border-radius:10px;font-weight:700;font-size:10px;border:1px solid #a7f3d0;">🏆 High</span>
                        Nilai 90–100
                    </span>
                    <span style="font-size:11px;display:flex;align-items:center;gap:5px;">
                        <span style="background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:10px;font-weight:700;font-size:10px;border:1px solid #bfdbfe;">📈 Medium</span>
                        Nilai 70–89
                    </span>
                    <span style="font-size:11px;display:flex;align-items:center;gap:5px;">
                        <span style="background:#fef3c7;color:#d97706;padding:2px 8px;border-radius:10px;font-weight:700;font-size:10px;border:1px solid #fde68a;">📚 Low</span>
                        Nilai 0–69
                    </span>
                </div>
            </div>';

            echo html_writer::start_div('alai-student-chart-section');
            echo html_writer::tag('h4', get_string('student_progress_chart', 'block_adaptive_learning_ai'), ['class' => 'alai-student-chart-title']);
            echo html_writer::tag('div', $OUTPUT->render($student_chart), ['class' => 'alai-student-chart-wrapper']);
            echo $level_legend_html;
            echo html_writer::end_div();
        }

        $attempttable = new html_table();
        $attempttable->attributes['class'] = 'alai-attempts-table generaltable';
        $attempttable->head = [get_string('quiz_detail', 'block_adaptive_learning_ai'), get_string('student_score', 'block_adaptive_learning_ai'), get_string('quiz_status', 'block_adaptive_learning_ai'), get_string('quiz_date_time', 'block_adaptive_learning_ai')];
        $attempttable->data = [];

        foreach ($attempts as $attempt) {
            $score = $attempt->sumgrades !== null ? adaptive_learning_ai_report_helper::calculate_percentage($attempt->sumgrades, $attempt->max_grade) . '%' : '-';
            $status = adaptive_learning_ai_report_helper::get_attempt_status($attempt->state);
            $date = adaptive_learning_ai_report_helper::format_time($attempt->timefinish ?: $attempt->timestart);
            $link = new moodle_url('/blocks/adaptive_learning_ai/reports/teacher_report.php', ['courseid' => $courseid, 'userid' => $userid, 'attemptid' => $attempt->id]);
            $attempttable->data[] = [html_writer::link($link, format_string($attempt->quiz_name)), $score, $status, $date];
        }

        echo html_writer::table($attempttable);

        if ($selectedattempt) {
            echo html_writer::tag('h4', get_string('quiz_detail', 'block_adaptive_learning_ai'), ['class' => 'alai-detail-title']);
            $responses = adaptive_learning_ai_report_helper::get_question_responses($selectedattempt->id);

            if (empty($responses)) {
                echo html_writer::tag('div', get_string('no_quiz_data', 'block_adaptive_learning_ai'), ['class' => 'alert alert-warning']);
            } else {
                $detailtable = new html_table();
                $detailtable->attributes['class'] = 'alai-responses-table generaltable';
                $detailtable->head = [get_string('question_text', 'block_adaptive_learning_ai'), get_string('student_answer', 'block_adaptive_learning_ai'), get_string('correct_answer', 'block_adaptive_learning_ai'), get_string('question_score', 'block_adaptive_learning_ai')];
                $detailtable->data = [];

                foreach ($responses as $response) {
                    $detailtable->data[] = [format_text($response->questiontext, FORMAT_HTML), s($response->responsesummary), s($response->rightanswer), adaptive_learning_ai_report_helper::calculate_percentage($response->fraction, 1) . '%'];
                }

                echo html_writer::table($detailtable);
            }
        }
    }

    echo html_writer::start_div('alai-back-button-wrapper');
    echo html_writer::link(new moodle_url('/blocks/adaptive_learning_ai/reports/teacher_report.php', ['courseid' => $courseid]), get_string('back_to_list', 'block_adaptive_learning_ai'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_div();
} else {
    $students = adaptive_learning_ai_report_helper::get_course_students($courseid);

    $table = new html_table();
    $table->head = [get_string('student_name', 'block_adaptive_learning_ai'), get_string('student_score', 'block_adaptive_learning_ai'), get_string('average_score', 'block_adaptive_learning_ai'), get_string('student_level', 'block_adaptive_learning_ai'), get_string('student_status', 'block_adaptive_learning_ai')];
    $table->data = [];

    foreach ($students as $student) {
        $scoreinfo = adaptive_learning_ai_report_helper::get_student_score_info($courseid, $student->id);
        $average = adaptive_learning_ai_report_helper::get_student_average_score($courseid, $student->id);
        $scoretxt = get_string('no_quiz_data', 'block_adaptive_learning_ai');
        $averagetxt = get_string('no_quiz_data', 'block_adaptive_learning_ai');
        $leveltxt = '-';
        $status = '-';

        if ($scoreinfo && $scoreinfo->sumgrades !== null && $scoreinfo->maxgrade !== null) {
            $percentage = adaptive_learning_ai_report_helper::calculate_percentage($scoreinfo->sumgrades, $scoreinfo->maxgrade);
            $scoretxt = $percentage . '%';
            $status = adaptive_learning_ai_report_helper::get_attempt_status($scoreinfo->state);
        }

        if ($average !== null) {
            $averagetxt = $average . '%';
            $leveltxt = adaptive_learning_ai_report_helper::get_score_level_text($average, 100);
        }

        $link = new moodle_url('/blocks/adaptive_learning_ai/reports/teacher_report.php', ['courseid' => $courseid, 'userid' => $student->id]);
        $table->data[] = [html_writer::link($link, fullname($student)), $scoretxt, $averagetxt, $leveltxt, $status];
    }

    echo html_writer::table($table);
}

echo html_writer::end_div();
echo $OUTPUT->footer();