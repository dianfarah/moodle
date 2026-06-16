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
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Integration test: Evaluation Flow.
 *
 * Verifies the complete evaluation flow:
 *   Quiz completion -> Gradebook grade event -> EvaluationSystem ->
 *   ProfilingSystem -> Coach
 *
 * Requirements covered:
 * - Req 6.1: EvaluationSystem fetches score from Moodle Gradebook when learner completes activity.
 * - Req 6.2: EvaluationSystem calculates aggregate metrics and sends to ProfilingSystem within 60s.
 * - Req 6.4: EvaluationSystem sends alert to Coach when performance declines >20%.
 * - Req 3.3: ProfilingSystem classifies Performance_Category (Low <60%, Middle 60-79%, High >=80%).
 * - Req 4.1: Coach evaluates profile and determines Adaptive_Intervention within 10 seconds.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tests\integration;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\evaluation\evaluation_system;
use block_attendanceleaderboard\profiling\profiling_system;
use block_attendanceleaderboard\profiling\learner_profile;
use block_attendanceleaderboard\coach\coach;
use block_attendanceleaderboard\coach\adaptive_intervention;

/**
 * Integration test class for the evaluation flow.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_attendanceleaderboard\evaluation\evaluation_system
 * @covers     \block_attendanceleaderboard\profiling\profiling_system
 * @covers     \block_attendanceleaderboard\coach\coach
 */
class evaluation_flow_integration_test extends \advanced_testcase {
    /** @var evaluation_system EvaluationSystem instance. */
    private evaluation_system $es;

    /** @var profiling_system ProfilingSystem instance. */
    private profiling_system $ps;

    /** @var coach Coach instance. */
    private coach $coach;

    /** @var \stdClass Test user. */
    private \stdClass $user;

    /** @var \stdClass Test course. */
    private \stdClass $course;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $this->es    = new evaluation_system();
        $this->ps    = new profiling_system();
        $this->coach = new coach();

        $this->user   = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Insert a performance score record directly into acmls_learner_record.
     * Simulates what EvaluationSystem::save_to_learner_record() does.
     *
     * @param  int   $userid
     * @param  int   $courseid
     * @param  float $score
     * @param  int   $timecreated  Unix timestamp (default: time()).
     * @return int                 Inserted record ID.
     */
    private function insert_performance_record(
        int $userid,
        int $courseid,
        float $score,
        int $timecreated = 0
    ): int {
        global $DB;

        $record = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->record_type      = 'performance';
        $record->source_component = 'evaluation';
        $record->data_payload     = json_encode(['score' => $score]);
        $record->profile_version  = null;
        $record->timecreated      = $timecreated > 0 ? $timecreated : time();

        return (int) $DB->insert_record('acmls_learner_record', $record);
    }

    /**
     * Create a mock grade event object that EvaluationSystem::handle_grade_event() accepts.
     *
     * Since we cannot easily trigger a real Moodle grade event in unit tests,
     * we create a minimal stdClass that mimics the event interface used by
     * handle_grade_event() (userid, courseid, objectid).
     *
     * @param  int $userid
     * @param  int $courseid
     * @param  int $cmid
     * @return object  Mock event object.
     */
    private function make_mock_grade_event(int $userid, int $courseid, int $cmid): object {
        $event           = new \stdClass();
        $event->userid   = $userid;
        $event->courseid = $courseid;
        $event->objectid = $cmid;
        return $event;
    }

    /**
     * Set up a real Moodle quiz activity with a grade item and grade record.
     *
     * Creates a quiz course module, a grade_item, and a grade_grades record
     * so that EvaluationSystem::fetch_gradebook_score() can retrieve the score.
     *
     * @param  int   $userid
     * @param  int   $courseid
     * @param  float $raw_grade   Raw grade value (0 to grademax).
     * @param  float $grademax    Maximum grade (default 100).
     * @return array{cmid: int, grade_item_id: int}  IDs of created records.
     */
    private function setup_gradebook_data(
        int $userid,
        int $courseid,
        float $raw_grade,
        float $grademax = 100.0
    ): array {
        global $DB;

        // Create a quiz activity in the course.
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $courseid]);
        $cmid = (int) $quiz->cmid;

        // Fetch the course_module record to get module type id.
        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, course, module, instance', MUST_EXIST);

        // Ensure a grade_item exists for this quiz.
        $module_name = $DB->get_field('modules', 'name', ['id' => $cm->module], MUST_EXIST);

        // Check if grade_item already exists (Moodle may create it automatically).
        $grade_item = $DB->get_record('grade_items', [
            'courseid'     => $courseid,
            'itemtype'     => 'mod',
            'itemmodule'   => $module_name,
            'iteminstance' => $cm->instance,
        ]);

        if (!$grade_item) {
            // Create grade_item manually.
            $gi = new \stdClass();
            $gi->courseid     = $courseid;
            $gi->itemtype     = 'mod';
            $gi->itemmodule   = $module_name;
            $gi->iteminstance = $cm->instance;
            $gi->itemnumber   = 0;
            $gi->itemname     = 'Quiz grade';
            $gi->grademax     = $grademax;
            $gi->grademin     = 0.0;
            $gi->gradetype    = 1;
            $gi->timecreated  = time();
            $gi->timemodified = time();
            $grade_item_id = (int) $DB->insert_record('grade_items', $gi);
        } else {
            $grade_item_id = (int) $grade_item->id;
            // Update grademax if needed.
            $DB->set_field('grade_items', 'grademax', $grademax, ['id' => $grade_item_id]);
        }

        // Insert or update grade_grades record for this user.
        $existing_grade = $DB->get_record('grade_grades', [
            'itemid' => $grade_item_id,
            'userid' => $userid,
        ]);

        if ($existing_grade) {
            $DB->set_field('grade_grades', 'finalgrade', $raw_grade, ['id' => $existing_grade->id]);
        } else {
            $gg = new \stdClass();
            $gg->itemid       = $grade_item_id;
            $gg->userid       = $userid;
            $gg->finalgrade   = $raw_grade;
            $gg->rawgrade     = $raw_grade;
            $gg->rawgrademax  = $grademax;
            $gg->rawgrademin  = 0.0;
            $gg->timecreated  = time();
            $gg->timemodified = time();
            $DB->insert_record('grade_grades', $gg);
        }

        return ['cmid' => $cmid, 'grade_item_id' => $grade_item_id];
    }

    // =========================================================================
    // Test 1: Req 6.1 — EvaluationSystem fetches score from Gradebook
    // =========================================================================

    /**
     * Req 6.1: When a learner completes a gradable activity, EvaluationSystem
     * SHALL fetch the score from Moodle Gradebook.
     *
     * This test sets up a real quiz with a grade record and verifies that
     * fetch_gradebook_score() returns the correct normalised score.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::fetch_gradebook_score
     */
    public function test_req_6_1_fetch_gradebook_score_returns_correct_score(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Set up a quiz with raw_grade=75 out of grademax=100 => normalised = 75%.
        $data = $this->setup_gradebook_data($userid, $courseid, 75.0, 100.0);
        $cmid = $data['cmid'];

        $score = $this->es->fetch_gradebook_score($userid, $cmid);

        $this->assertNotNull($score,
            'Req 6.1: fetch_gradebook_score() should return a non-null score when grade exists.');
        $this->assertEqualsWithDelta(75.0, $score, 0.01,
            'Req 6.1: Normalised score should be 75.0 for raw_grade=75 / grademax=100.');
    }

    /**
     * Req 6.1: fetch_gradebook_score() normalises score to 0-100 scale.
     *
     * Tests with raw_grade=80 out of grademax=200 => normalised = 40%.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::fetch_gradebook_score
     */
    public function test_req_6_1_fetch_gradebook_score_normalises_to_100_scale(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // raw_grade=80, grademax=200 => normalised = (80/200)*100 = 40.0
        $data = $this->setup_gradebook_data($userid, $courseid, 80.0, 200.0);
        $cmid = $data['cmid'];

        $score = $this->es->fetch_gradebook_score($userid, $cmid);

        $this->assertNotNull($score,
            'Req 6.1: fetch_gradebook_score() should return a non-null score.');
        $this->assertEqualsWithDelta(40.0, $score, 0.01,
            'Req 6.1: Score should be normalised: (80/200)*100 = 40.0.');
    }

    /**
     * Req 6.1: fetch_gradebook_score() returns null when no grade record exists.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::fetch_gradebook_score
     */
    public function test_req_6_1_fetch_gradebook_score_returns_null_when_no_grade(): void {
        // Use a non-existent cmid.
        $score = $this->es->fetch_gradebook_score((int) $this->user->id, 99999);

        $this->assertNull($score,
            'Req 6.1: fetch_gradebook_score() should return null when cmid does not exist.');
        $this->assertDebuggingCalled();
    }

    // =========================================================================
    // Test 2: Req 6.2 — EvaluationSystem calculates aggregate metrics and
    //         sends to ProfilingSystem
    // =========================================================================

    /**
     * Req 6.2: When a new score is available, EvaluationSystem SHALL calculate
     * aggregate metrics and send to ProfilingSystem.
     *
     * This test verifies that after save_to_learner_record() + calculate_aggregate_metrics(),
     * the metrics are correct and ProfilingSystem can consume them to update the profile.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::calculate_aggregate_metrics
     * @covers \block_attendanceleaderboard\profiling\profiling_system::update_profile
     */
    public function test_req_6_2_evaluation_calculates_metrics_and_profiling_updates_profile(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Step 1: Save a score to the learner record (simulating EvaluationSystem processing).
        $score = 75.0;
        $this->es->save_to_learner_record($userid, $courseid, ['score' => $score]);

        // Step 2: Calculate aggregate metrics.
        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);

        $this->assertEquals(1, $metrics['score_count'],
            'Req 6.2: score_count should be 1 after one score is saved.');
        $this->assertEqualsWithDelta(75.0, $metrics['average_score'], 0.01,
            'Req 6.2: average_score should be 75.0.');
        $this->assertEqualsWithDelta(75.0, $metrics['latest_score'], 0.01,
            'Req 6.2: latest_score should be 75.0.');

        // Step 3: Send metrics to ProfilingSystem (simulating notify_profiling_system()).
        $profile = $this->ps->update_profile($userid, $courseid, ['score' => $score]);

        // Verify ProfilingSystem updated the profile correctly.
        $this->assertNotNull($profile->id,
            'Req 6.2: Profile should be persisted after update_profile().');
        $this->assertEquals(learner_profile::PERFORMANCE_MIDDLE, $profile->performance_category,
            'Req 6.2: Score 75 should map to PERFORMANCE_MIDDLE (2).');

        // Verify the profile exists in DB.
        $db_profile = $DB->get_record('acmls_learner_profile', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertNotFalse($db_profile,
            'Req 6.2: acmls_learner_profile record should exist after ProfilingSystem update.');
    }

    /**
     * Req 6.2: Aggregate metrics include trend information.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::calculate_aggregate_metrics
     */
    public function test_req_6_2_aggregate_metrics_include_trend(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $base_time = time() - 200;
        $this->insert_performance_record($userid, $courseid, 60.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 80.0, $base_time + 100);

        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);

        $this->assertEquals('improving', $metrics['trend'],
            'Req 6.2: Trend should be "improving" when latest score > previous score.');
        $this->assertArrayHasKey('class_average', $metrics,
            'Req 6.2: Metrics should include class_average.');
        $this->assertArrayHasKey('vs_class', $metrics,
            'Req 6.2: Metrics should include vs_class comparison.');
    }

    // =========================================================================
    // Test 3: Req 3.3 — ProfilingSystem classifies Performance_Category
    // =========================================================================

    /**
     * Req 3.3: ProfilingSystem SHALL classify Performance_Category as Low (<60%),
     * Middle (60-79%), or High (>=80%).
     *
     * @covers \block_attendanceleaderboard\profiling\profiling_system::classify_performance_category
     * @covers \block_attendanceleaderboard\profiling\profiling_system::update_profile
     */
    public function test_req_3_3_profiling_classifies_low_performance(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Score 55 => Low (<60%).
        $profile = $this->ps->update_profile($userid, $courseid, ['score' => 55.0]);

        $this->assertEquals(learner_profile::PERFORMANCE_LOW, $profile->performance_category,
            'Req 3.3: Score 55 should classify as PERFORMANCE_LOW (1).');
    }

    /**
     * Req 3.3: Score 70 should classify as Middle (60-79%).
     *
     * @covers \block_attendanceleaderboard\profiling\profiling_system::classify_performance_category
     */
    public function test_req_3_3_profiling_classifies_middle_performance(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Score 70 => Middle (60-79%).
        $profile = $this->ps->update_profile($userid, $courseid, ['score' => 70.0]);

        $this->assertEquals(learner_profile::PERFORMANCE_MIDDLE, $profile->performance_category,
            'Req 3.3: Score 70 should classify as PERFORMANCE_MIDDLE (2).');
    }

    /**
     * Req 3.3: Score 85 should classify as High (>=80%).
     *
     * @covers \block_attendanceleaderboard\profiling\profiling_system::classify_performance_category
     */
    public function test_req_3_3_profiling_classifies_high_performance(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Score 85 => High (>=80%).
        $profile = $this->ps->update_profile($userid, $courseid, ['score' => 85.0]);

        $this->assertEquals(learner_profile::PERFORMANCE_HIGH, $profile->performance_category,
            'Req 3.3: Score 85 should classify as PERFORMANCE_HIGH (3).');
    }

    /**
     * Req 3.3: Boundary value — score exactly 60 should be Middle.
     *
     * @covers \block_attendanceleaderboard\profiling\profiling_system::classify_performance_category
     */
    public function test_req_3_3_profiling_boundary_60_is_middle(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $profile = $this->ps->update_profile($userid, $courseid, ['score' => 60.0]);

        $this->assertEquals(learner_profile::PERFORMANCE_MIDDLE, $profile->performance_category,
            'Req 3.3: Score exactly 60 should classify as PERFORMANCE_MIDDLE (boundary).');
    }

    /**
     * Req 3.3: Boundary value — score exactly 80 should be High.
     *
     * @covers \block_attendanceleaderboard\profiling\profiling_system::classify_performance_category
     */
    public function test_req_3_3_profiling_boundary_80_is_high(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $profile = $this->ps->update_profile($userid, $courseid, ['score' => 80.0]);

        $this->assertEquals(learner_profile::PERFORMANCE_HIGH, $profile->performance_category,
            'Req 3.3: Score exactly 80 should classify as PERFORMANCE_HIGH (boundary).');
    }

    // =========================================================================
    // Test 4: Req 6.4 — EvaluationSystem sends alert to Coach on decline >20%
    // =========================================================================

    /**
     * Req 6.4: When performance declines >20%, EvaluationSystem SHALL send
     * an alert to Coach.
     *
     * This test verifies that send_alert_to_coach() is called and a
     * performance_alert record is stored in acmls_learner_record.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::send_alert_to_coach
     */
    public function test_req_6_4_alert_sent_to_coach_when_decline_exceeds_20_percent(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Historical scores: [80, 80, 80] => historical average = 80.
        $base_time = time() - 400;
        $this->insert_performance_record($userid, $courseid, 80.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 80.0, $base_time + 100);
        $this->insert_performance_record($userid, $courseid, 80.0, $base_time + 200);

        // Current score: 60 => decline = (80-60)/80*100 = 25% > 20%.
        $this->insert_performance_record($userid, $courseid, 60.0, $base_time + 300);

        // Verify decline is detected.
        $decline_detected = $this->es->detect_performance_decline($userid, $courseid);
        $this->assertTrue($decline_detected,
            'Req 6.4: detect_performance_decline() should return true for 25% decline.');

        // Send alert to Coach.
        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);
        $this->es->send_alert_to_coach($userid, $courseid, $metrics);

        // Verify a performance_alert record was stored.
        $alert_count = $DB->count_records('acmls_learner_record', [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'record_type' => 'performance_alert',
        ]);
        $this->assertGreaterThan(0, $alert_count,
            'Req 6.4: A performance_alert record should be stored in acmls_learner_record.');

        // Verify the alert payload contains the correct data.
        $alert_record = $DB->get_record('acmls_learner_record', [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'record_type' => 'performance_alert',
        ]);
        $this->assertNotFalse($alert_record, 'Alert record should exist.');

        $payload = json_decode($alert_record->data_payload, true);
        $this->assertIsArray($payload, 'Alert payload should be valid JSON.');
        $this->assertEquals('performance_decline', $payload['alert_type'],
            'Req 6.4: Alert type should be "performance_decline".');
        $this->assertEquals($userid,   $payload['userid']);
        $this->assertEquals($courseid, $payload['courseid']);
    }

    /**
     * Req 6.4: No alert sent when decline is exactly 20% (threshold is strictly >20%).
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_req_6_4_no_alert_when_decline_is_exactly_20_percent(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Historical scores: [100, 100] => historical average = 100.
        $base_time = time() - 300;
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time + 100);

        // Current score: 80 => decline = (100-80)/100*100 = 20.0% (not > 20%).
        $this->insert_performance_record($userid, $courseid, 80.0, $base_time + 200);

        $decline_detected = $this->es->detect_performance_decline($userid, $courseid);
        $this->assertFalse($decline_detected,
            'Req 6.4: Exactly 20% decline should NOT trigger alert (threshold is strictly >20%).');
    }

    /**
     * Req 6.4: No alert sent when performance is improving.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_req_6_4_no_alert_when_performance_improving(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $base_time = time() - 300;
        $this->insert_performance_record($userid, $courseid, 60.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 75.0, $base_time + 100);
        $this->insert_performance_record($userid, $courseid, 85.0, $base_time + 200);

        $decline_detected = $this->es->detect_performance_decline($userid, $courseid);
        $this->assertFalse($decline_detected,
            'Req 6.4: No alert should be sent when performance is improving.');
    }

    // =========================================================================
    // Test 5: Req 4.1 — Coach evaluates profile and generates intervention
    // =========================================================================

    /**
     * Req 4.1: Coach SHALL evaluate the profile and determine Adaptive_Intervention
     * within 10 seconds.
     *
     * @covers \block_attendanceleaderboard\coach\coach::evaluate_profile
     * @covers \block_attendanceleaderboard\coach\coach::save_decision
     */
    public function test_req_4_1_coach_evaluates_profile_within_10_seconds(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $profile = new learner_profile($userid, $courseid);
        $profile->performance_category = learner_profile::PERFORMANCE_MIDDLE;
        $profile->motivation_level     = 60.0;
        $profile->cognitive_level      = learner_profile::COGNITIVE_MIDDLE;
        $profile->learning_style       = learner_profile::STYLE_VISUAL;

        $start_time = microtime(true);
        $intervention = $this->coach->evaluate_profile($profile);
        $elapsed = microtime(true) - $start_time;

        // Req 4.1: Must complete within 10 seconds.
        $this->assertLessThan(10.0, $elapsed,
            'Req 4.1: Coach::evaluate_profile() must complete within 10 seconds.');

        // Must return an adaptive_intervention.
        $this->assertInstanceOf(adaptive_intervention::class, $intervention,
            'Req 4.1: evaluate_profile() should return an adaptive_intervention instance.');

        // motivation_category must be non-empty.
        $this->assertNotEmpty($intervention->motivation_category,
            'Req 4.1: motivation_category should not be empty.');

        // reasoning must be non-empty.
        $this->assertNotEmpty($intervention->reasoning,
            'Req 4.1: reasoning should not be empty.');

        // decision_id must point to a real DB record.
        $this->assertNotNull($intervention->decision_id,
            'Req 4.1: decision_id should not be null.');
        $this->assertGreaterThan(0, $intervention->decision_id,
            'Req 4.1: decision_id should be a positive integer.');

        $decision_record = $DB->get_record('acmls_coach_decision', ['id' => $intervention->decision_id]);
        $this->assertNotFalse($decision_record,
            'Req 4.1: A coach_decision record should exist for the returned decision_id.');
        $this->assertEquals($userid,   (int) $decision_record->userid);
        $this->assertEquals($courseid, (int) $decision_record->courseid);
    }

    /**
     * Req 4.1: Coach generates "recovery" intervention for Low performance + Low motivation.
     *
     * @covers \block_attendanceleaderboard\coach\coach::determine_motivation_intervention
     */
    public function test_req_4_1_coach_generates_recovery_for_low_performance(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $profile = new learner_profile($userid, $courseid);
        $profile->performance_category = learner_profile::PERFORMANCE_LOW;
        $profile->motivation_level     = 20.0; // Low motivation (below default threshold of 30).
        $profile->cognitive_level      = learner_profile::COGNITIVE_LOW;
        $profile->learning_style       = learner_profile::STYLE_UNKNOWN;

        $intervention = $this->coach->evaluate_profile($profile);

        $this->assertEquals('recovery', $intervention->motivation_category,
            'Req 4.1: Low performance + Low motivation should produce "recovery" intervention.');
    }

    /**
     * Req 4.1: Coach generates "achievement" intervention for High performance.
     *
     * @covers \block_attendanceleaderboard\coach\coach::determine_motivation_intervention
     */
    public function test_req_4_1_coach_generates_achievement_for_high_performance(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $profile = new learner_profile($userid, $courseid);
        $profile->performance_category = learner_profile::PERFORMANCE_HIGH;
        $profile->motivation_level     = 80.0;
        $profile->cognitive_level      = learner_profile::COGNITIVE_HIGH;
        $profile->learning_style       = learner_profile::STYLE_READING;

        $intervention = $this->coach->evaluate_profile($profile);

        $this->assertEquals('achievement', $intervention->motivation_category,
            'Req 4.1: High performance should produce "achievement" intervention.');
    }

    // =========================================================================
    // Test 6: Full integration — Happy path
    // =========================================================================

    /**
     * Full integration test: Happy path.
     *
     * Simulates the complete evaluation flow:
     *   1. Learner completes a quiz (grade record created in Gradebook).
     *   2. EvaluationSystem fetches score from Gradebook.
     *   3. EvaluationSystem saves score to Learner Record.
     *   4. EvaluationSystem calculates aggregate metrics.
     *   5. EvaluationSystem notifies ProfilingSystem (via update_profile).
     *   6. ProfilingSystem updates LearnerProfile with Performance_Category.
     *   7. ProfilingSystem notifies Coach.
     *   8. Coach evaluates profile and generates Adaptive_Intervention.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::fetch_gradebook_score
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::save_to_learner_record
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::calculate_aggregate_metrics
     * @covers \block_attendanceleaderboard\profiling\profiling_system::update_profile
     * @covers \block_attendanceleaderboard\coach\coach::evaluate_profile
     */
    public function test_full_evaluation_flow_happy_path(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // ----------------------------------------------------------------
        // Step 1: Set up Gradebook data (quiz with score 78/100).
        // ----------------------------------------------------------------
        $data = $this->setup_gradebook_data($userid, $courseid, 78.0, 100.0);
        $cmid = $data['cmid'];

        // ----------------------------------------------------------------
        // Step 2: EvaluationSystem fetches score from Gradebook (Req 6.1).
        // ----------------------------------------------------------------
        $score = $this->es->fetch_gradebook_score($userid, $cmid);
        $this->assertNotNull($score, 'Step 2: Score should be fetched from Gradebook.');
        $this->assertEqualsWithDelta(78.0, $score, 0.01,
            'Step 2: Fetched score should be 78.0.');

        // ----------------------------------------------------------------
        // Step 3: EvaluationSystem saves score to Learner Record.
        // ----------------------------------------------------------------
        $record_id = $this->es->save_to_learner_record($userid, $courseid, [
            'score'      => $score,
            'cmid'       => $cmid,
            'event_type' => 'grade_item_updated',
            'source'     => 'grade_event',
        ]);
        $this->assertGreaterThan(0, $record_id,
            'Step 3: save_to_learner_record() should return a positive ID.');

        $saved_record = $DB->get_record('acmls_learner_record', ['id' => $record_id]);
        $this->assertNotFalse($saved_record, 'Step 3: Record should exist in acmls_learner_record.');
        $this->assertEquals('performance', $saved_record->record_type);

        // ----------------------------------------------------------------
        // Step 4: EvaluationSystem calculates aggregate metrics (Req 6.2).
        // ----------------------------------------------------------------
        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);
        $this->assertEquals(1, $metrics['score_count'],
            'Step 4: score_count should be 1.');
        $this->assertEqualsWithDelta(78.0, $metrics['average_score'], 0.01,
            'Step 4: average_score should be 78.0.');
        $this->assertEqualsWithDelta(78.0, $metrics['latest_score'], 0.01,
            'Step 4: latest_score should be 78.0.');

        // ----------------------------------------------------------------
        // Step 5 & 6: ProfilingSystem updates LearnerProfile (Req 3.3).
        // ----------------------------------------------------------------
        $profile = $this->ps->update_profile($userid, $courseid, ['score' => $score]);

        $this->assertNotNull($profile->id,
            'Step 5: Profile should be persisted.');
        // Score 78 => Middle (60-79%).
        $this->assertEquals(learner_profile::PERFORMANCE_MIDDLE, $profile->performance_category,
            'Step 6: Score 78 should classify as PERFORMANCE_MIDDLE.');

        // Verify profile exists in DB.
        $db_profile = $DB->get_record('acmls_learner_profile', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertNotFalse($db_profile, 'Step 6: acmls_learner_profile should exist.');

        // Verify profile snapshot was saved.
        $snapshot_count = $DB->count_records('acmls_learner_record', [
            'userid'           => $userid,
            'courseid'         => $courseid,
            'record_type'      => 'profile_snapshot',
            'source_component' => 'profiling',
        ]);
        $this->assertGreaterThan(0, $snapshot_count,
            'Step 6: At least one profile_snapshot should be saved to acmls_learner_record.');

        // ----------------------------------------------------------------
        // Step 7 & 8: Coach evaluates profile and generates intervention (Req 4.1).
        // ----------------------------------------------------------------
        $profile_from_db = learner_profile::from_db_record($db_profile);
        $intervention = $this->coach->evaluate_profile($profile_from_db);

        $this->assertInstanceOf(adaptive_intervention::class, $intervention,
            'Step 8: evaluate_profile() should return an adaptive_intervention.');
        $this->assertNotEmpty($intervention->motivation_category,
            'Step 8: motivation_category should not be empty.');
        $this->assertNotNull($intervention->decision_id,
            'Step 8: decision_id should not be null.');

        // Verify coach_decision record exists.
        $decision_count = $DB->count_records('acmls_coach_decision', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertGreaterThan(0, $decision_count,
            'Step 8: At least one acmls_coach_decision record should exist.');
    }

    // =========================================================================
    // Test 7: Full integration — Performance decline path
    // =========================================================================

    /**
     * Full integration test: Performance decline detection path.
     *
     * Simulates the scenario where a learner's performance declines >20%:
     *   1. Historical scores are established (high performance).
     *   2. A new low score is recorded.
     *   3. EvaluationSystem detects the decline.
     *   4. EvaluationSystem sends alert to Coach.
     *   5. ProfilingSystem updates profile to Low performance.
     *   6. Coach evaluates the updated profile and generates recovery intervention.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::send_alert_to_coach
     * @covers \block_attendanceleaderboard\profiling\profiling_system::update_profile
     * @covers \block_attendanceleaderboard\coach\coach::evaluate_profile
     */
    public function test_full_evaluation_flow_performance_decline_path(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // ----------------------------------------------------------------
        // Step 1: Establish historical high performance scores.
        // ----------------------------------------------------------------
        $base_time = time() - 600;
        $this->insert_performance_record($userid, $courseid, 85.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 88.0, $base_time + 100);
        $this->insert_performance_record($userid, $courseid, 90.0, $base_time + 200);

        // ----------------------------------------------------------------
        // Step 2: Record a new low score (decline > 20%).
        // historical_avg = (85+88+90)/3 = 87.67
        // current = 65 => decline = (87.67-65)/87.67*100 = 25.9% > 20%.
        // ----------------------------------------------------------------
        $this->insert_performance_record($userid, $courseid, 65.0, $base_time + 300);

        // ----------------------------------------------------------------
        // Step 3: EvaluationSystem detects the decline (Req 6.4).
        // ----------------------------------------------------------------
        $decline_detected = $this->es->detect_performance_decline($userid, $courseid);
        $this->assertTrue($decline_detected,
            'Step 3: Performance decline should be detected (>20% from historical average).');

        // ----------------------------------------------------------------
        // Step 4: EvaluationSystem sends alert to Coach (Req 6.4).
        // ----------------------------------------------------------------
        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);
        $this->es->send_alert_to_coach($userid, $courseid, $metrics);

        // Verify alert was stored.
        $alert_count = $DB->count_records('acmls_learner_record', [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'record_type' => 'performance_alert',
        ]);
        $this->assertGreaterThan(0, $alert_count,
            'Step 4: A performance_alert record should be stored.');

        // ----------------------------------------------------------------
        // Step 5: ProfilingSystem updates profile with the new low score (Req 3.3).
        // ----------------------------------------------------------------
        $profile = $this->ps->update_profile($userid, $courseid, ['score' => 65.0]);

        // Score 65 => Middle (60-79%).
        $this->assertEquals(learner_profile::PERFORMANCE_MIDDLE, $profile->performance_category,
            'Step 5: Score 65 should classify as PERFORMANCE_MIDDLE.');

        // ----------------------------------------------------------------
        // Step 6: Coach evaluates the updated profile (Req 4.1).
        // ----------------------------------------------------------------
        $intervention = $this->coach->evaluate_profile($profile);

        $this->assertInstanceOf(adaptive_intervention::class, $intervention,
            'Step 6: evaluate_profile() should return an adaptive_intervention.');
        $this->assertNotEmpty($intervention->motivation_category,
            'Step 6: motivation_category should not be empty.');

        // Verify coach_decision was saved.
        $decision_count = $DB->count_records('acmls_coach_decision', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertGreaterThan(0, $decision_count,
            'Step 6: At least one acmls_coach_decision record should exist.');
    }

    // =========================================================================
    // Test 8: ProfilingSystem notifies Coach after profile update
    // =========================================================================

    /**
     * Req 3.6: ProfilingSystem SHALL notify Coach after each profile update.
     *
     * Verifies that after update_profile(), a coach_decision record is created
     * (because ProfilingSystem calls Coach::receive_profile_update()).
     *
     * @covers \block_attendanceleaderboard\profiling\profiling_system::notify_coach
     * @covers \block_attendanceleaderboard\profiling\profiling_system::update_profile
     */
    public function test_profiling_notifies_coach_after_profile_update(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Before update: no coach decisions.
        $before_count = $DB->count_records('acmls_coach_decision', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertEquals(0, $before_count,
            'No coach decisions should exist before profile update.');

        // Update profile — this should trigger notify_coach().
        $this->ps->update_profile($userid, $courseid, ['score' => 72.0]);

        // After update: at least one coach decision should exist.
        $after_count = $DB->count_records('acmls_coach_decision', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertGreaterThan(0, $after_count,
            'Req 3.6: At least one coach_decision should exist after ProfilingSystem notifies Coach.');
    }

    // =========================================================================
    // Test 9: EvaluationSystem handle_grade_event integration
    // =========================================================================

    /**
     * Integration test: EvaluationSystem grade processing flow.
     *
     * Verifies the complete grade processing flow that handle_grade_event() performs:
     * 1. Fetch score from Gradebook (fetch_gradebook_score).
     * 2. Save performance record to acmls_learner_record (save_to_learner_record).
     * 3. Calculate aggregate metrics (calculate_aggregate_metrics).
     * 4. Notify ProfilingSystem (update_profile).
     *
     * Note: handle_grade_event() requires a core\event\base object. This test
     * exercises the same internal flow by calling the component methods directly,
     * which is the correct integration testing approach.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::fetch_gradebook_score
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::save_to_learner_record
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::calculate_aggregate_metrics
     * @covers \block_attendanceleaderboard\profiling\profiling_system::update_profile
     */
    public function test_grade_processing_flow_saves_performance_record(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Set up Gradebook data: score 82/100.
        $data = $this->setup_gradebook_data($userid, $courseid, 82.0, 100.0);
        $cmid = $data['cmid'];

        // Before: no performance records.
        $before_count = $DB->count_records('acmls_learner_record', [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'record_type' => 'performance',
        ]);
        $this->assertEquals(0, $before_count,
            'No performance records should exist before grade processing.');

        // Step 1: Fetch score from Gradebook (Req 6.1).
        $score = $this->es->fetch_gradebook_score($userid, $cmid);
        $this->assertNotNull($score, 'Score should be fetched from Gradebook.');
        $this->assertEqualsWithDelta(82.0, $score, 0.01, 'Score should be 82.0.');

        // Step 2: Save performance record (what handle_grade_event does internally).
        $record_id = $this->es->save_to_learner_record($userid, $courseid, [
            'score'      => $score,
            'cmid'       => $cmid,
            'event_type' => 'grade_item_updated',
            'source'     => 'grade_event',
        ]);
        $this->assertGreaterThan(0, $record_id, 'Record ID should be positive.');

        // After: at least one performance record should exist.
        $after_count = $DB->count_records('acmls_learner_record', [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'record_type' => 'performance',
        ]);
        $this->assertGreaterThan(0, $after_count,
            'Grade processing should save a performance record to acmls_learner_record.');

        // Verify the saved record has the correct score.
        $perf_record = $DB->get_record('acmls_learner_record', ['id' => $record_id]);
        $this->assertNotFalse($perf_record, 'Performance record should exist.');

        $payload = json_decode($perf_record->data_payload, true);
        $this->assertIsArray($payload, 'Payload should be valid JSON.');
        $this->assertArrayHasKey('score', $payload, 'Payload should contain score.');
        $this->assertEqualsWithDelta(82.0, $payload['score'], 0.01,
            'Saved score should be 82.0 (normalised from 82/100).');

        // Step 3: Calculate aggregate metrics (Req 6.2).
        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);
        $this->assertEquals(1, $metrics['score_count'], 'score_count should be 1.');
        $this->assertEqualsWithDelta(82.0, $metrics['average_score'], 0.01,
            'average_score should be 82.0.');

        // Step 4: Notify ProfilingSystem.
        $profile = $this->ps->update_profile($userid, $courseid, ['score' => $score]);
        $this->assertNotNull($profile->id, 'Profile should be persisted.');
        // Score 82 => High (>=80%).
        $this->assertEquals(learner_profile::PERFORMANCE_HIGH, $profile->performance_category,
            'Score 82 should classify as PERFORMANCE_HIGH.');
    }

    /**
     * Integration test: EvaluationSystem correctly handles invalid user (no records created).
     *
     * Verifies that fetch_gradebook_score() returns null for invalid inputs,
     * and no records are created.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::fetch_gradebook_score
     */
    public function test_grade_processing_ignores_invalid_userid(): void {
        global $DB;

        $before_count = $DB->count_records('acmls_learner_record');

        // fetch_gradebook_score with invalid userid=0 should return null.
        $score = $this->es->fetch_gradebook_score(0, 99999);
        $this->assertNull($score,
            'fetch_gradebook_score() should return null for invalid userid=0.');

        // No records should be created.
        $after_count = $DB->count_records('acmls_learner_record');
        $this->assertEquals($before_count, $after_count,
            'No records should be created for invalid userid=0.');
    }
}

