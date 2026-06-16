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
 * Integration test: End-to-End Learner Workflow.
 *
 * Verifies the complete flow:
 *   Login Learner → Event Observer → Activity_Log → Profiling → Coach → Delivery
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tests\integration;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\tracking\tracking_system;
use block_attendanceleaderboard\profiling\profiling_system;
use block_attendanceleaderboard\profiling\learner_profile;
use block_attendanceleaderboard\coach\coach;
use block_attendanceleaderboard\coach\adaptive_intervention;
use block_attendanceleaderboard\delivery\delivery_system;

/**
 * Integration test class for the complete end-to-end learner workflow.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_attendanceleaderboard\tracking\tracking_system
 * @covers     \block_attendanceleaderboard\profiling\profiling_system
 * @covers     \block_attendanceleaderboard\coach\coach
 * @covers     \block_attendanceleaderboard\delivery\delivery_system
 */
class end_to_end_learner_workflow_test extends \advanced_testcase {

    /** @var \stdClass Test user. */
    private \stdClass $user;

    /** @var \stdClass Test course. */
    private \stdClass $course;

    /** @var tracking_system Tracking system instance. */
    private tracking_system $ts;

    /** @var profiling_system Profiling system instance. */
    private profiling_system $ps;

    /** @var coach Coach instance. */
    private coach $coach;

    /** @var delivery_system Delivery system instance. */
    private delivery_system $ds;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest(true);

        $this->user   = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id);

        $this->ts    = new tracking_system();
        $this->ps    = new profiling_system();
        $this->coach = new coach();
        $this->ds    = new delivery_system();

        // Minimal PAGE setup so any template rendering does not crash.
        $this->setUser($this->user);
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/');
    }

    // =========================================================================
    // Test 1: record_login() creates an activity log with event_type='user_loggedin'
    // =========================================================================

    /**
     * Verify that calling tracking_system::record_login() creates a record in
     * acmls_activity_log with event_type='user_loggedin'.
     *
     * @covers \block_attendanceleaderboard\tracking\tracking_system::record_login
     */
    public function test_login_creates_activity_log(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $id = $this->ts->record_login($userid, $courseid, ['ip' => '127.0.0.1']);

        $this->assertGreaterThan(0, $id, 'record_login() should return a positive record ID.');

        $record = $DB->get_record('acmls_activity_log', ['id' => $id]);
        $this->assertNotFalse($record, 'A record should exist in acmls_activity_log.');
        $this->assertEquals($userid,         (int) $record->userid);
        $this->assertEquals($courseid,       (int) $record->courseid);
        $this->assertEquals('user_loggedin', $record->event_type);
        $this->assertEquals(0,               (int) $record->sent_to_profiler,
            'Newly created log should not be marked as sent to profiler.');
    }

    // =========================================================================
    // Test 2: Full flow — record → flush → logs marked sent → profile exists
    // =========================================================================

    /**
     * Verify the full flow:
     *   record_login() → record_activity() → flush_to_profiler()
     *   → logs marked sent_to_profiler=1 → acmls_learner_profile record exists.
     *
     * @covers \block_attendanceleaderboard\tracking\tracking_system::record_login
     * @covers \block_attendanceleaderboard\tracking\tracking_system::record_activity
     * @covers \block_attendanceleaderboard\tracking\tracking_system::flush_to_profiler
     * @covers \block_attendanceleaderboard\profiling\profiling_system::process_activity_log
     */
    public function test_activity_log_flushed_to_profiling(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Record login and a resource-access activity.
        $login_id    = $this->ts->record_login($userid, $courseid);
        $activity_id = $this->ts->record_activity(
            $userid,
            $courseid,
            'course_module_viewed',
            'mod_resource',
            ['action' => 'viewed', 'objectid' => 1]
        );

        // Both logs should be pending.
        $pending = $this->ts->get_pending_logs();
        $this->assertCount(2, $pending, 'Two logs should be pending before flush.');

        // Flush to profiler.
        $this->ts->flush_to_profiler();

        // Logs should now be marked as sent.
        $log1 = $DB->get_record('acmls_activity_log', ['id' => $login_id]);
        $log2 = $DB->get_record('acmls_activity_log', ['id' => $activity_id]);
        $this->assertEquals(1, (int) $log1->sent_to_profiler,
            'Login log should be marked sent_to_profiler=1 after flush.');
        $this->assertEquals(1, (int) $log2->sent_to_profiler,
            'Activity log should be marked sent_to_profiler=1 after flush.');

        // A learner profile should have been created by the flush.
        $profile_record = $DB->get_record('acmls_learner_profile', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertNotFalse($profile_record,
            'A learner profile should exist in acmls_learner_profile after flush.');
    }

    // =========================================================================
    // Test 3: update_profile() creates profile, saves snapshot, notifies coach
    // =========================================================================

    /**
     * Verify that profiling_system::update_profile() creates/updates
     * acmls_learner_profile, saves a snapshot to acmls_learner_record with
     * record_type='profile_snapshot', and (since Coach is available) saves a
     * acmls_coach_decision record.
     *
     * @covers \block_attendanceleaderboard\profiling\profiling_system::update_profile
     * @covers \block_attendanceleaderboard\profiling\profiling_system::save_profile_snapshot
     * @covers \block_attendanceleaderboard\profiling\profiling_system::notify_coach
     */
    public function test_profiling_creates_profile_and_notifies_coach(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $profile = $this->ps->update_profile($userid, $courseid, ['score' => 75.0]);

        // Profile should be persisted.
        $this->assertNotNull($profile->id, 'Profile should have a DB id after update_profile().');
        $profile_record = $DB->get_record('acmls_learner_profile', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertNotFalse($profile_record, 'acmls_learner_profile record should exist.');
        $this->assertEquals(learner_profile::PERFORMANCE_MIDDLE, (int) $profile_record->performance_category,
            'Score 75 should map to PERFORMANCE_MIDDLE (2).');

        // A profile_snapshot should be saved to acmls_learner_record.
        $snapshot = $DB->get_record('acmls_learner_record', [
            'userid'           => $userid,
            'courseid'         => $courseid,
            'record_type'      => 'profile_snapshot',
            'source_component' => 'profiling',
        ]);
        $this->assertNotFalse($snapshot,
            'A profile_snapshot record should exist in acmls_learner_record.');

        // Coach should have been notified → a coach_decision record should exist.
        $decision_count = $DB->count_records('acmls_coach_decision', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertGreaterThan(0, $decision_count,
            'At least one acmls_coach_decision record should exist after coach notification.');
    }

    // =========================================================================
    // Test 4: coach::evaluate_profile() returns adaptive_intervention with decision_id
    // =========================================================================

    /**
     * Verify that given a learner profile, coach::evaluate_profile() returns an
     * adaptive_intervention with non-empty motivation_category and reasoning,
     * and a decision_id that exists in acmls_coach_decision.
     *
     * @covers \block_attendanceleaderboard\coach\coach::evaluate_profile
     * @covers \block_attendanceleaderboard\coach\coach::save_decision
     */
    public function test_coach_evaluates_profile_and_saves_decision(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Build a learner profile.
        $profile = new learner_profile($userid, $courseid);
        $profile->performance_category = learner_profile::PERFORMANCE_MIDDLE;
        $profile->motivation_level     = 55.0;
        $profile->cognitive_level      = learner_profile::COGNITIVE_MIDDLE;
        $profile->learning_style       = learner_profile::STYLE_READING;

        $intervention = $this->coach->evaluate_profile($profile);

        // Should return an adaptive_intervention.
        $this->assertInstanceOf(adaptive_intervention::class, $intervention,
            'evaluate_profile() should return an adaptive_intervention instance.');

        // motivation_category must be non-empty.
        $this->assertNotEmpty($intervention->motivation_category,
            'motivation_category should not be empty.');

        // reasoning must be non-empty.
        $this->assertNotEmpty($intervention->reasoning,
            'reasoning should not be empty.');

        // decision_id must be set and point to a real DB record.
        $this->assertNotNull($intervention->decision_id,
            'decision_id should not be null after evaluate_profile().');
        $this->assertGreaterThan(0, $intervention->decision_id,
            'decision_id should be a positive integer.');

        $decision_record = $DB->get_record('acmls_coach_decision', ['id' => $intervention->decision_id]);
        $this->assertNotFalse($decision_record,
            'A record with the returned decision_id should exist in acmls_coach_decision.');
        $this->assertEquals($userid,   (int) $decision_record->userid);
        $this->assertEquals($courseid, (int) $decision_record->courseid);
    }

    // =========================================================================
    // Test 5: delivery_system::record_interaction() inserts interaction record
    // =========================================================================

    /**
     * Verify that delivery_system::record_interaction() inserts a record into
     * acmls_learner_record with record_type='interaction' and
     * source_component='delivery'.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_delivery_records_interaction(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', ['resourceid' => 42]);

        $record = $DB->get_record('acmls_learner_record', [
            'userid'           => $userid,
            'courseid'         => $courseid,
            'record_type'      => 'interaction',
            'source_component' => 'delivery',
        ]);

        $this->assertNotFalse($record,
            'An interaction record should exist in acmls_learner_record.');
        $this->assertEquals('interaction', $record->record_type);
        $this->assertEquals('delivery',    $record->source_component);

        $payload = json_decode($record->data_payload, true);
        $this->assertIsArray($payload, 'data_payload should be valid JSON.');
        $this->assertEquals('resource_accessed', $payload['interaction_type']);
    }

    // =========================================================================
    // Test 6: Complete end-to-end workflow
    // =========================================================================

    /**
     * Main integration test that chains all steps:
     *   Create user + course + enrol → record_login → record_activity →
     *   flush_to_profiler → verify profile → evaluate_profile →
     *   verify coach_decision → record_interaction → verify all 3 key tables.
     *
     * @covers \block_attendanceleaderboard\tracking\tracking_system::record_login
     * @covers \block_attendanceleaderboard\tracking\tracking_system::record_activity
     * @covers \block_attendanceleaderboard\tracking\tracking_system::flush_to_profiler
     * @covers \block_attendanceleaderboard\profiling\profiling_system::update_profile
     * @covers \block_attendanceleaderboard\coach\coach::evaluate_profile
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_complete_end_to_end_workflow(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // ----------------------------------------------------------------
        // Step 1: Record login via tracking_system::record_login().
        // ----------------------------------------------------------------
        $login_id = $this->ts->record_login($userid, $courseid, ['ip' => '10.0.0.1']);
        $this->assertGreaterThan(0, $login_id, 'Step 1: login activity log should be created.');

        $login_log = $DB->get_record('acmls_activity_log', ['id' => $login_id]);
        $this->assertNotFalse($login_log, 'Step 1: login log record must exist.');
        $this->assertEquals('user_loggedin', $login_log->event_type);

        // ----------------------------------------------------------------
        // Step 2: Record resource access via tracking_system::record_activity().
        // ----------------------------------------------------------------
        $activity_id = $this->ts->record_activity(
            $userid,
            $courseid,
            'course_module_viewed',
            'mod_resource',
            ['action' => 'viewed', 'objectid' => 10, 'duration_seconds' => 120]
        );
        $this->assertGreaterThan(0, $activity_id, 'Step 2: activity log should be created.');

        $activity_log = $DB->get_record('acmls_activity_log', ['id' => $activity_id]);
        $this->assertNotFalse($activity_log, 'Step 2: activity log record must exist.');
        $this->assertEquals('course_module_viewed', $activity_log->event_type);

        // ----------------------------------------------------------------
        // Step 3: Flush to profiler.
        // ----------------------------------------------------------------
        $this->ts->flush_to_profiler();

        // ----------------------------------------------------------------
        // Step 4: Verify logs are marked as sent and profile was created.
        // ----------------------------------------------------------------
        $login_log_after = $DB->get_record('acmls_activity_log', ['id' => $login_id]);
        $this->assertEquals(1, (int) $login_log_after->sent_to_profiler,
            'Step 4: login log should be marked sent_to_profiler=1.');

        $activity_log_after = $DB->get_record('acmls_activity_log', ['id' => $activity_id]);
        $this->assertEquals(1, (int) $activity_log_after->sent_to_profiler,
            'Step 4: activity log should be marked sent_to_profiler=1.');

        $profile_record = $DB->get_record('acmls_learner_profile', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertNotFalse($profile_record,
            'Step 4: acmls_learner_profile should exist after flush.');

        // ----------------------------------------------------------------
        // Step 5: Load profile from DB, create learner_profile object,
        //         call coach::evaluate_profile().
        // ----------------------------------------------------------------
        $profile = learner_profile::from_db_record($profile_record);

        $intervention = $this->coach->evaluate_profile($profile);

        $this->assertInstanceOf(adaptive_intervention::class, $intervention,
            'Step 5: evaluate_profile() should return an adaptive_intervention.');
        $this->assertNotNull($intervention->decision_id,
            'Step 5: decision_id should not be null.');

        // ----------------------------------------------------------------
        // Step 6: Verify acmls_coach_decision record exists.
        // ----------------------------------------------------------------
        $decision_record = $DB->get_record('acmls_coach_decision', [
            'id' => $intervention->decision_id,
        ]);
        $this->assertNotFalse($decision_record,
            'Step 6: acmls_coach_decision record should exist for the returned decision_id.');
        $this->assertEquals($userid,   (int) $decision_record->userid);
        $this->assertEquals($courseid, (int) $decision_record->courseid);

        // ----------------------------------------------------------------
        // Step 7: Call delivery_system::record_interaction().
        // ----------------------------------------------------------------
        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', [
            'resourceid'  => 10,
            'decision_id' => $intervention->decision_id,
        ]);

        $interaction_record = $DB->get_record('acmls_learner_record', [
            'userid'           => $userid,
            'courseid'         => $courseid,
            'record_type'      => 'interaction',
            'source_component' => 'delivery',
        ]);
        $this->assertNotFalse($interaction_record,
            'Step 7: interaction record should exist in acmls_learner_record.');

        // ----------------------------------------------------------------
        // Step 8: Assert the full chain produced data in all 3 key tables.
        // ----------------------------------------------------------------
        $activity_log_count = $DB->count_records('acmls_activity_log', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertGreaterThanOrEqual(2, $activity_log_count,
            'Step 8: acmls_activity_log should have at least 2 records (login + activity).');

        $profile_count = $DB->count_records('acmls_learner_profile', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertEquals(1, $profile_count,
            'Step 8: acmls_learner_profile should have exactly 1 record.');

        $decision_count = $DB->count_records('acmls_coach_decision', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertGreaterThanOrEqual(1, $decision_count,
            'Step 8: acmls_coach_decision should have at least 1 record.');
    }

    // =========================================================================
    // Test 7: Observer pattern — tracking_system dispatches correctly
    // =========================================================================

    /**
     * Verify that the observer pattern works: directly call
     * tracking_system::record_login() (simulating what the observer does) and
     * verify the activity log is created. Also test record_activity() for
     * course_module_viewed event type.
     *
     * @covers \block_attendanceleaderboard\tracking\tracking_system::record_login
     * @covers \block_attendanceleaderboard\tracking\tracking_system::record_activity
     */
    public function test_event_observer_dispatches_to_tracking_system(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Simulate what observer::user_loggedin() does internally:
        // it creates a tracking_system and calls handle_event(), which calls record_login().
        $ts = new tracking_system();
        $login_id = $ts->record_login($userid, $courseid, [
            'ip'        => '192.168.0.1',
            'sessionid' => 'test-session-id',
        ]);

        $this->assertGreaterThan(0, $login_id,
            'Observer-simulated login should create an activity log.');

        $login_record = $DB->get_record('acmls_activity_log', ['id' => $login_id]);
        $this->assertNotFalse($login_record, 'Login activity log record must exist.');
        $this->assertEquals('user_loggedin', $login_record->event_type);
        $this->assertEquals('core',          $login_record->component);
        $this->assertEquals('loggedin',      $login_record->action);

        // Simulate what observer::course_module_viewed() does internally:
        // it creates a tracking_system and calls handle_event(), which calls record_activity().
        $activity_id = $ts->record_activity(
            $userid,
            $courseid,
            'course_module_viewed',
            'mod_resource',
            ['action' => 'viewed', 'objectid' => 5]
        );

        $this->assertGreaterThan(0, $activity_id,
            'Observer-simulated course_module_viewed should create an activity log.');

        $activity_record = $DB->get_record('acmls_activity_log', ['id' => $activity_id]);
        $this->assertNotFalse($activity_record, 'Activity log record must exist.');
        $this->assertEquals('course_module_viewed', $activity_record->event_type);
        $this->assertEquals('mod_resource',         $activity_record->component);
        $this->assertEquals('viewed',               $activity_record->action);
        $this->assertEquals(5,                      (int) $activity_record->objectid);

        // Both logs should be pending (not yet sent to profiler).
        $pending = $ts->get_pending_logs();
        $pending_ids = array_map(fn($l) => $l->id, $pending);
        $this->assertContains($login_id,    $pending_ids, 'Login log should be pending.');
        $this->assertContains($activity_id, $pending_ids, 'Activity log should be pending.');
    }

    // =========================================================================
    // Test 8: update_profile() twice increments profile_version and saves 2 snapshots
    // =========================================================================

    /**
     * Verify that calling update_profile() twice increments profile_version from
     * 1 to 2, and that 2 snapshots exist in acmls_learner_record.
     *
     * @covers \block_attendanceleaderboard\profiling\profiling_system::update_profile
     * @covers \block_attendanceleaderboard\profiling\profiling_system::save_profile_snapshot
     */
    public function test_profiling_updates_profile_version_on_subsequent_updates(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // First update — creates the profile at version 1.
        $profile_v1 = $this->ps->update_profile($userid, $courseid, ['score' => 60.0]);
        $this->assertEquals(1, $profile_v1->profile_version,
            'First update should produce profile_version=1.');

        // Second update — should increment to version 2.
        $profile_v2 = $this->ps->update_profile($userid, $courseid, ['score' => 70.0]);
        $this->assertEquals(2, $profile_v2->profile_version,
            'Second update should produce profile_version=2.');

        // The DB record should reflect version 2.
        $db_record = $DB->get_record('acmls_learner_profile', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);
        $this->assertNotFalse($db_record, 'Profile record should exist in DB.');
        $this->assertEquals(2, (int) $db_record->profile_version,
            'DB profile_version should be 2 after two updates.');

        // Two profile_snapshot records should exist in acmls_learner_record.
        $snapshot_count = $DB->count_records('acmls_learner_record', [
            'userid'           => $userid,
            'courseid'         => $courseid,
            'record_type'      => 'profile_snapshot',
            'source_component' => 'profiling',
        ]);
        $this->assertEquals(2, $snapshot_count,
            'Two profile_snapshot records should exist after two update_profile() calls.');

        // Verify the snapshots have the correct profile_version values.
        $snapshots = $DB->get_records('acmls_learner_record', [
            'userid'           => $userid,
            'courseid'         => $courseid,
            'record_type'      => 'profile_snapshot',
            'source_component' => 'profiling',
        ], 'timecreated ASC, id ASC');

        $snapshots = array_values($snapshots);
        $this->assertEquals(1, (int) $snapshots[0]->profile_version,
            'First snapshot should have profile_version=1.');
        $this->assertEquals(2, (int) $snapshots[1]->profile_version,
            'Second snapshot should have profile_version=2.');
    }
}
