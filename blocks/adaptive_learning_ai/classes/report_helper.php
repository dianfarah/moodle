<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

defined('MOODLE_INTERNAL') || die();

class adaptive_learning_ai_report_helper {

    /**
     * Mendapatkan list siswa dengan nilai quiz di course
     *
     * @param int $courseid Course ID
     * @return array Array of students with quiz scores
     */
    public static function get_students_quiz_list($courseid) {
        global $DB;

        $sql = "SELECT DISTINCT 
                    u.id,
                    u.firstname,
                    u.lastname,
                    u.email,
                    COUNT(qa.id) as total_attempts,
                    MAX(qa.timefinish) as last_attempt,
                    AVG(CASE WHEN qa.state = 'finished' THEN qa.sumgrades ELSE NULL END) as avg_score,
                    MAX(CASE WHEN qa.state = 'finished' THEN qa.sumgrades ELSE NULL END) as max_score,
                    COUNT(CASE WHEN qa.state = 'finished' THEN 1 END) as finished_count,
                    q.grade as max_grade
                FROM {user} u
                JOIN {user_enrolments} ue ON u.id = ue.userid
                JOIN {enrol} e ON ue.enrolid = e.id
                JOIN {quiz} q ON q.course = e.courseid
                LEFT JOIN {quiz_attempts} qa ON u.id = qa.userid AND qa.quiz = q.id
                WHERE e.courseid = ? AND u.deleted = 0
                GROUP BY u.id, u.firstname, u.lastname, u.email, q.grade
                ORDER BY u.firstname, u.lastname";

        return $DB->get_records_sql($sql, [$courseid]);
    }

    /**
     * Mendapatkan detail quiz attempt untuk siswa
     *
     * @param int $attemptid Quiz attempt ID
     * @return object Quiz attempt detail
     */
    public static function get_quiz_attempt_detail($attemptid) {
        global $DB;

        $sql = "SELECT qa.id, qa.quiz, qa.userid, qa.attempt, qa.sumgrades, qa.timefinish, qa.timestart, qa.state,
                       q.id as quizid, q.name as quiz_name, q.grade as max_grade,
                       u.firstname, u.lastname, u.email
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON qa.quiz = q.id
                JOIN {user} u ON qa.userid = u.id
                WHERE qa.id = ?";

        return $DB->get_record_sql($sql, [$attemptid]);
    }

    /**
     * Mendapatkan jawaban siswa untuk setiap pertanyaan di attempt
     *
     * @param int $attemptid Quiz attempt ID
     * @return array Array of question attempts
     */
    public static function get_question_responses($attemptid) {
        global $DB;

        $sql = "SELECT qat.id,
                       qat.questionid,
                       qat.sequencenumber,
                       qat.responsesummary,
                       qat.rightanswer,
                       qat.fraction,
                       qat.state,
                       q.name as question_text,
                       q.questiontext,
                       q.qtype,
                       q.id as question_id
                FROM {question_attempts} qat
                JOIN {question} q ON qat.questionid = q.id
                WHERE qat.questionusageid = (
                    SELECT qa.questionusageid
                    FROM {quiz_attempts} qa
                    WHERE qa.id = ?
                    LIMIT 1
                )
                ORDER BY qat.sequencenumber";

        try {
            return $DB->get_records_sql($sql, [$attemptid]);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Mendapatkan siswa terdaftar di course dengan peran student
     *
     * @param int $courseid Course ID
     * @return array Array of students
     */
    public static function get_course_students($courseid) {
        global $DB;

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email
                FROM {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {context} ctx ON ra.contextid = ctx.id AND ctx.contextlevel = ? AND ctx.instanceid = ?
                JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                WHERE u.deleted = 0 AND u.suspended = 0
                ORDER BY u.lastname, u.firstname";

        return $DB->get_records_sql($sql, [CONTEXT_COURSE, $courseid]);
    }

    /**
     * Mendapatkan rata-rata nilai siswa di course
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @return float|null Rata-rata persentase nilai atau null jika tidak ada data
     */
    public static function get_student_average_score($courseid, $userid) {
        global $DB;

        $sql = "SELECT AVG(CASE WHEN q.grade > 0 THEN (qa.sumgrades / q.grade) * 100 ELSE NULL END) as average_percentage
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON qa.quiz = q.id
                WHERE q.course = ? AND qa.userid = ? AND qa.state = 'finished'";

        $average = $DB->get_field_sql($sql, [$courseid, $userid]);
        return $average !== null ? round($average, 2) : null;
    }

    /**
     * Mendapatkan semua quiz attempts siswa tertentu di course
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @return array Array of quiz attempts
     */
    public static function get_student_quiz_attempts($courseid, $userid) {
        global $DB;

        $sql = "SELECT qa.id, qa.quiz, qa.userid, qa.attempt, qa.sumgrades, qa.timefinish, qa.timestart, qa.state,
                       q.id as quizid, q.name as quiz_name, q.grade as max_grade
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON qa.quiz = q.id
                WHERE q.course = ? AND qa.userid = ?
                ORDER BY qa.timefinish DESC";

        return $DB->get_records_sql($sql, [$courseid, $userid]);
    }

    /**
     * Mendapatkan detail question steps untuk mendapatkan jawaban siswa
     *
     * @param int $questionattemptid Question attempt ID
     * @return array Array of question steps
     */
    public static function get_question_steps($questionattemptid) {
        global $DB;

        $sql = "SELECT qas.id, qas.sequencenumber, qas.state, qas.fraction, qas.timecreated,
                       GROUP_CONCAT(qad.name, '=', qad.value SEPARATOR '&') as answer_data
                FROM {question_attempt_steps} qas
                LEFT JOIN {question_attempt_step_data} qad ON qas.id = qad.attemptstepid
                WHERE qas.questionattemptid = ?
                GROUP BY qas.id, qas.sequencenumber, qas.state, qas.fraction, qas.timecreated
                ORDER BY qas.sequencenumber DESC
                LIMIT 1";

        return $DB->get_records_sql($sql, [$questionattemptid]);
    }

    /**
     * Mendapatkan nilai siswa untuk display
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @return object Object dengan score info
     */
    public static function get_student_score_info($courseid, $userid) {
        global $DB;

        $sql = "SELECT qa.sumgrades, q.grade as maxgrade, qa.state, qa.timefinish, qa.timestart
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON qa.quiz = q.id
                WHERE q.course = ? AND qa.userid = ? AND qa.state = 'finished'
                ORDER BY qa.timefinish DESC
                LIMIT 1";

        return $DB->get_record_sql($sql, [$courseid, $userid]);
    }

    /**
     * Mendapatkan status quiz attempt
     *
     * @param string $state Quiz attempt state
     * @return string Status text
     */
    public static function get_attempt_status($state) {
        $status = [
            'inprogress' => 'Sedang Dikerjakan',
            'finished' => 'Selesai',
            'abandoned' => 'Ditinggalkan'
        ];
        return $status[$state] ?? $state;
    }

    /**
     * Menghitung waktu pengerjaan quiz
     *
     * @param int $timestart Waktu mulai
     * @param int $timefinish Waktu selesai
     * @return string Durasi dalam format readable
     */
    public static function calculate_duration($timestart, $timefinish) {
        if (!$timefinish) {
            return '-';
        }
        $duration = $timefinish - $timestart;
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m {$seconds}s";
        } elseif ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        } else {
            return "{$seconds}s";
        }
    }

    /**
     * Format waktu unix ke format readable
     *
     * @param int $timestamp Unix timestamp
     * @return string Formatted date and time
     */
    public static function format_time($timestamp) {
        if (!$timestamp) {
            return '-';
        }
        return date('d/m/Y H:i:s', $timestamp);
    }

    /**
     * Menghitung score percentage
     *
     * @param float $score Nilai siswa
     * @param float $maxscore Nilai maksimal
     * @return float Persentase
     */
    public static function calculate_percentage($score, $maxscore) {
        if (!$maxscore) {
            return 0;
        }
        return round(($score / $maxscore) * 100, 2);
    }

    public static function get_score_level($score, $maxscore) {
        $percentage = self::calculate_percentage($score, $maxscore);
        if ($percentage < 70) {
            return 'low';
        }
        if ($percentage >= 90) {
            return 'high';
        }
        return 'medium';
    }

    public static function get_score_level_text($score, $maxscore) {
        $level = self::get_score_level($score, $maxscore);
        return get_string('level_' . $level, 'block_adaptive_learning_ai');
    }

    /**
     * Mendapatkan color grade berdasarkan score
     *
     * @param float $percentage Persentase score
     * @return string CSS class untuk color
     */
    public static function get_grade_color($percentage) {
        if ($percentage >= 85) {
            return 'success'; // Hijau
        } elseif ($percentage >= 70) {
            return 'warning'; // Orange
        } else {
            return 'danger'; // Merah
        }
    }
}
