<?php
define('CLI_SCRIPT', true);
require_once('config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/blocks/moodleblock.class.php');
require_once($CFG->dirroot.'/blocks/attendanceleaderboard/block_attendanceleaderboard.php');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== ACMLS Dummy Data Injector CLI ===\n";

try {
    global $DB, $CFG;

    // ----------------------------------------------------------------
    // 1. Get or create Course
    // ----------------------------------------------------------------
    $course = $DB->get_record('course', ['shortname' => 'ACMLS_TEST']);
    if (!$course) {
        $coursedata = new stdClass();
        $coursedata->fullname = 'Kursus Uji Coba ACMLS';
        $coursedata->shortname = 'ACMLS_TEST';
        $coursedata->category = 1; // default category
        $coursedata->summary = 'Kursus untuk menguji coba plugin ACMLS';
        $coursedata->format = 'topics';
        $coursedata->summaryformat = FORMAT_HTML;
        $coursedata->newsitems = 5;
        $coursedata->showgrades = 1;
        $coursedata->showreports = 1;
        $coursedata->maxbytes = 0;
        $course = create_course($coursedata);
        echo "Created Course: " . $course->fullname . " (ID: " . $course->id . ")\n";
    } else {
        echo "Found Course: " . $course->fullname . " (ID: " . $course->id . ")\n";
    }
    $courseid = $course->id;

    // ----------------------------------------------------------------
    // 2. Get or create Student users
    // ----------------------------------------------------------------
    $students = [];
    $student_details = [
        ['username' => 'siswa1', 'firstname' => 'Budi', 'lastname' => 'Santoso', 'email' => 'budi@acmls.test'],
        ['username' => 'siswa2', 'firstname' => 'Siti', 'lastname' => 'Aminah', 'email' => 'siti@acmls.test'],
        ['username' => 'siswa3', 'firstname' => 'Joko', 'lastname' => 'Susilo', 'email' => 'joko@acmls.test'],
        ['username' => 'siswa4', 'firstname' => 'Dewi', 'lastname' => 'Lestari', 'email' => 'dewi@acmls.test'],
        ['username' => 'siswa5', 'firstname' => 'Andi', 'lastname' => 'Prabowo', 'email' => 'andi@acmls.test'],
    ];

    foreach ($student_details as $detail) {
        $user = $DB->get_record('user', ['username' => $detail['username']]);
        if (!$user) {
            $userdata = new stdClass();
            $userdata->username = $detail['username'];
            $userdata->firstname = $detail['firstname'];
            $userdata->lastname = $detail['lastname'];
            $userdata->email = $detail['email'];
            $userdata->auth = 'manual';
            $userdata->password = 'Moodle123!';
            $userdata->confirmed = 1;
            $userdata->mnethostid = $CFG->mnet_localhost_id;
            $userdata->lang = 'en';
            $userid = user_create_user($userdata);
            $user = $DB->get_record('user', ['id' => $userid]);
            echo "Created Student User: " . $user->username . " (ID: " . $user->id . ")\n";
        } else {
            echo "Found Student User: " . $user->username . " (ID: " . $user->id . "). Resetting password...\n";
            $DB->set_field('user', 'password', hash_internal_user_password('Moodle123!'), ['id' => $user->id]);
        }
        $students[$detail['username']] = $user;
    }

    // Enrol users
    $studentrole = $DB->get_record('role', ['shortname' => 'student']);
    if (!$studentrole) {
        throw new Exception("Student role 'student' not found in database.");
    }
    $roleid = $studentrole->id;

    $enrol = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual']);
    if (!$instance) {
        $enrolid = $enrol->add_instance($course);
        $instance = $DB->get_record('enrol', ['id' => $enrolid]);
    }

    foreach ($students as $student) {
        if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $student->id])) {
            $enrol->enrol_user($instance, $student->id, $roleid);
            echo "Enrolled User: " . $student->username . "\n";
        }
    }

    // ----------------------------------------------------------------
    // 3. Get or create Course Modules (Assignment and Quiz)
    // ----------------------------------------------------------------
    $assignmodule = $DB->get_record('modules', ['name' => 'assign']);
    $quizmodule = $DB->get_record('modules', ['name' => 'quiz']);

    // Create Assignment
    $assign = $DB->get_record('assign', ['course' => $courseid, 'name' => 'Tugas Uji Coba ACMLS']);
    $duedate = time() - 3600; // Due date was 1 hour ago
    if (!$assign) {
        $assign = new stdClass();
        $assign->course = $courseid;
        $assign->name = 'Tugas Uji Coba ACMLS';
        $assign->intro = 'Silakan kumpulkan tugas Anda di sini.';
        $assign->introformat = 1;
        $assign->alwaysshowdescription = 1;
        $assign->nosubmissions = 0;
        $assign->submissiondrafts = 0;
        $assign->sendnotifications = 0;
        $assign->sendlatenotifications = 0;
        $assign->duedate = $duedate;
        $assign->allowsubmissionsfromdate = time() - 86400;
        $assign->grade = 100;
        $assign->timemodified = time();
        $assign->id = $DB->insert_record('assign', $assign);
        
        $cm = new stdClass();
        $cm->course = $courseid;
        $cm->module = $assignmodule->id;
        $cm->instance = $assign->id;
        $cm->section = 1;
        $cm->visible = 1;
        $cm->completion = 1; // completion tracking
        $cm->id = $DB->insert_record('course_modules', $cm);
        
        echo "Created Assignment Module: " . $assign->name . " (CM ID: " . $cm->id . ")\n";
    } else {
        echo "Found Assignment Module: " . $assign->name . "\n";
        $cm = $DB->get_record('course_modules', ['course' => $courseid, 'module' => $assignmodule->id, 'instance' => $assign->id]);
    }
    $assigncmid = $cm->id;

    // Create Quiz
    $quiz = $DB->get_record('quiz', ['course' => $courseid, 'name' => 'Kuis Uji Coba ACMLS']);
    if (!$quiz) {
        $quiz = new stdClass();
        $quiz->course = $courseid;
        $quiz->name = 'Kuis Uji Coba ACMLS';
        $quiz->intro = 'Kuis uji coba keterlibatan kognitif.';
        $quiz->introformat = 1;
        $quiz->timeopen = time() - 86400;
        $quiz->timeclose = time() + 86400;
        $quiz->timelimit = 1800;
        $quiz->attempts = 3;
        $quiz->grade = 10;
        $quiz->sumgrades = 10;
        $quiz->timecreated = time();
        $quiz->timemodified = time();
        $quiz->id = $DB->insert_record('quiz', $quiz);
        
        $cm = new stdClass();
        $cm->course = $courseid;
        $cm->module = $quizmodule->id;
        $cm->instance = $quiz->id;
        $cm->section = 1;
        $cm->visible = 1;
        $cm->completion = 1; // completion tracking
        $cm->id = $DB->insert_record('course_modules', $cm);
        
        echo "Created Quiz Module: " . $quiz->name . " (CM ID: " . $cm->id . ")\n";
    } else {
        echo "Found Quiz Module: " . $quiz->name . "\n";
        $cm = $DB->get_record('course_modules', ['course' => $courseid, 'module' => $quizmodule->id, 'instance' => $quiz->id]);
    }
    $quizcmid = $cm->id;

    rebuild_course_cache($courseid, true);

    // Helper closures to inject data
    $insert_accesses = function($userid, $courseid, $count) use ($DB) {
        $DB->delete_records('acmls_activity_log', ['userid' => $userid, 'courseid' => $courseid, 'event_type' => 'user_loggedin']);
        $DB->delete_records('acmls_learner_record', ['userid' => $userid, 'courseid' => $courseid, 'record_type' => 'interaction']);
        for ($i = 0; $i < $count; $i++) {
            $log = new stdClass();
            $log->userid = $userid;
            $log->courseid = $courseid;
            $log->event_type = 'user_loggedin';
            $log->timecreated = time() - ($i * 3600);
            $DB->insert_record('acmls_activity_log', $log);

            $interaction = new stdClass();
            $interaction->userid = $userid;
            $interaction->courseid = $courseid;
            $interaction->record_type = 'interaction';
            $interaction->source_component = 'profiling';
            $interaction->data_payload = json_encode(['resource_id' => 1, 'resource_type' => 'file']);
            $interaction->timecreated = time() - ($i * 3600);
            $DB->insert_record('acmls_learner_record', $interaction);
        }
    };

    $insert_completion = function($userid, $cmid, $state) use ($DB) {
        $DB->delete_records('course_modules_completion', ['userid' => $userid, 'coursemoduleid' => $cmid]);
        if ($state) {
            $completion = new stdClass();
            $completion->coursemoduleid = $cmid;
            $completion->userid = $userid;
            $completion->completionstate = 1; // Completed
            $completion->timemodified = time() - 3600;
            $DB->insert_record('course_modules_completion', $completion);
        }
    };

    $insert_submission = function($userid, $assignid, $submitted, $timemodified) use ($DB) {
        $DB->delete_records('assign_submission', ['userid' => $userid, 'assignment' => $assignid]);
        if ($submitted) {
            $sub = new stdClass();
            $sub->assignment = $assignid;
            $sub->userid = $userid;
            $sub->status = 'submitted';
            $sub->timemodified = $timemodified;
            $sub->timecreated = $timemodified - 3600;
            $sub->groupid = 0;
            $sub->attemptnumber = 0;
            $sub->latest = 1;
            $DB->insert_record('assign_submission', $sub);
        }
    };

    $insert_quiz_grades = function($userid, $quizid, $grade, $attempts) use ($DB) {
        $DB->delete_records('quiz_grades', ['userid' => $userid, 'quiz' => $quizid]);
        $DB->delete_records('quiz_attempts', ['userid' => $userid, 'quiz' => $quizid]);
        
        $qg = new stdClass();
        $qg->quiz = $quizid;
        $qg->userid = $userid;
        $qg->grade = $grade;
        $qg->timemodified = time() - 1800;
        $DB->insert_record('quiz_grades', $qg);
        
        for ($i = 1; $i <= $attempts; $i++) {
            $qa = new stdClass();
            $qa->quiz = $quizid;
            $qa->userid = $userid;
            $qa->attempt = $i;
            $qa->state = 'finished';
            $qa->sumgrades = $grade;
            $qa->timecreated = time() - ($i * 7200);
            $qa->timemodified = time() - ($i * 7200) + 1800;
            $qa->layout = '1';
            $qa->uniqueid = rand(10000, 99999);
            $qa->preview = 0;
            $DB->insert_record('quiz_attempts', $qa);
        }
    };

    $insert_feedback = function($userid, $courseid, $e1, $e2, $e3) use ($DB) {
        $DB->delete_records('acmls_motivation_feedback', ['userid' => $userid, 'courseid' => $courseid]);
        $fb = new stdClass();
        $fb->userid = $userid;
        $fb->courseid = $courseid;
        $fb->sentenceid = null;
        $fb->category = 'reinforcement';
        $fb->source = 'template';
        $fb->feeling_key = 'neutral';
        $fb->feeling_score = (int) round(($e1 + $e2 + $e3) / 3.0);
        $fb->e1_val = $e1;
        $fb->e2_val = $e2;
        $fb->e3_val = $e3;
        $fb->reflection_note = 'Dummy data injection';
        $fb->message_content = 'Semangat terus belajarnya!';
        $fb->timecreated = time() - 3600;
        $DB->insert_record('acmls_motivation_feedback', $fb);
    };

    // --- 3.5. Inject explicit consent = 1 for all dummy students so Gemini is allowed ---
    $DB->delete_records('acmls_learner_consent', ['courseid' => $courseid]);
    foreach ($students as $student) {
        $consent = new stdClass();
        $consent->userid = $student->id;
        $consent->courseid = $courseid;
        $consent->consent_given = 1;
        $consent->consent_timestamp = time();
        $consent->timecreated = time();
        $consent->timemodified = time();
        $DB->insert_record('acmls_learner_consent', $consent);
    }
    echo "Injected explicit consent for all dummy students.\n";

    // ----------------------------------------------------------------
    // 4. Inject specific learner behaviors and values
    // ----------------------------------------------------------------

    // Budi: High Engagement (Access = 25, Completions = 2/2, Assignment = On-time, Quiz = 9/10 (1 attempt))
    echo "Injecting data for Budi (siswa1)...\n";
    $budi = $students['siswa1'];
    $insert_accesses($budi->id, $courseid, 25);
    $insert_completion($budi->id, $assigncmid, true);
    $insert_completion($budi->id, $quizcmid, true);
    $insert_submission($budi->id, $assign->id, true, $duedate - 7200); // 2 hours before due date (On-time)
    $insert_quiz_grades($budi->id, $quiz->id, 9.0, 1);
    $insert_feedback($budi->id, $courseid, 5, 5, 4);

    // Siti: Middle Engagement (Access = 12, Completions = 1/2, Assignment = Late, Quiz = 7/10 (2 attempts))
    echo "Injecting data for Siti (siswa2)...\n";
    $siti = $students['siswa2'];
    $insert_accesses($siti->id, $courseid, 12);
    $insert_completion($siti->id, $assigncmid, true);
    $insert_completion($siti->id, $quizcmid, false);
    $insert_submission($siti->id, $assign->id, true, $duedate + 600); // 10 minutes late
    $insert_quiz_grades($siti->id, $quiz->id, 7.0, 2);
    $insert_feedback($siti->id, $courseid, 4, 3, 3);

    // Joko: Low Engagement (Access = 2, Completions = 0/2, Assignment = Not submitted, Quiz = 4/10 (3 attempts))
    echo "Injecting data for Joko (siswa3)...\n";
    $joko = $students['siswa3'];
    $insert_accesses($joko->id, $courseid, 2);
    $insert_completion($joko->id, $assigncmid, false);
    $insert_completion($joko->id, $quizcmid, false);
    $insert_submission($joko->id, $assign->id, false, 0); // Not submitted
    $insert_quiz_grades($joko->id, $quiz->id, 4.0, 3);
    $insert_feedback($joko->id, $courseid, 2, 2, 1);

    // Dewi: Mixed (High Quiz, Low behavioral) (Access = 4, Completions = 1/2, Assignment = Not submitted, Quiz = 9.5 (1 attempt))
    echo "Injecting data for Dewi (siswa4)...\n";
    $dewi = $students['siswa4'];
    $insert_accesses($dewi->id, $courseid, 4);
    $insert_completion($dewi->id, $assigncmid, false);
    $insert_completion($dewi->id, $quizcmid, true);
    $insert_submission($dewi->id, $assign->id, false, 0); // Not submitted
    $insert_quiz_grades($dewi->id, $quiz->id, 9.5, 1);
    $insert_feedback($dewi->id, $courseid, 3, 4, 3);

    // Andi: Mixed (Low Quiz, High behavioral) (Access = 22, Completions = 2/2, Assignment = On-time, Quiz = 5.0 (3 attempts))
    echo "Injecting data for Andi (siswa5)...\n";
    $andi = $students['siswa5'];
    $insert_accesses($andi->id, $courseid, 22);
    $insert_completion($andi->id, $assigncmid, true);
    $insert_completion($andi->id, $quizcmid, true);
    $insert_submission($andi->id, $assign->id, true, $duedate - 1200); // On-time
    $insert_quiz_grades($andi->id, $quiz->id, 5.0, 3);
    $insert_feedback($andi->id, $courseid, 4, 4, 4);

    // ----------------------------------------------------------------
    // 5. Trigger Profiler Updates
    // ----------------------------------------------------------------
    echo "Triggering Profiler updates for all dummy students...\n";
    $profiler = new \block_attendanceleaderboard\profiling\profiling_system();

    $DB->delete_records('acmls_learner_profile', ['courseid' => $courseid]);

    foreach ($students as $username => $student) {
        // Fetch current feedback values to inject as metrics parameters
        $fb = $DB->get_record('acmls_motivation_feedback', ['userid' => $student->id, 'courseid' => $courseid]);
        $metrics = [];
        if ($fb) {
            $metrics['e1'] = $fb->e1_val;
            $metrics['e2'] = $fb->e2_val;
            $metrics['e3'] = $fb->e3_val;
        }

        $profile = $profiler->update_profile($student->id, $courseid, $metrics);
        echo "\n=== Profile untuk " . $username . " (" . $student->firstname . " " . $student->lastname . ") ===\n";
        echo "  Behavioral Score: " . $profile->behavioral_score . " (Accesses: " . $profile->b1_access_count . ", Completions: " . $profile->b2_completion_count . ", Punctuality: " . $profile->b3_punctual_count . ")\n";
        echo "  Cognitive Score : " . $profile->cognitive_score . " (Quiz Avg: " . $profile->c1_quiz_avg . "%, Quiz Attempts: " . $profile->c2_quiz_attempts . ")\n";
        echo "  Emotional Score : " . $profile->emotional_score . " (e1: " . $profile->e1_score . ", e2: " . $profile->e2_score . ", e3: " . $profile->e3_score . ")\n";
        echo "  Motivation Level: " . $profile->motivation_level . " (Category: " . $profile->performance_category . ")\n";
    }

    echo "\n=== Injection Completed Successfully! ===\n";

} catch (Throwable $e) {
    echo "ERROR during injection: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
?>
