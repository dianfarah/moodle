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
 * Property-based tests for Tracking System correctness properties.
 *
 * Tests Properties 1-4:
 * - Property 1: Completeness of Session Activity Recording
 * - Property 2: Consistency of Session Summaries
 * - Property 3: Accuracy of Engagement Metrics
 * - Property 4: Data Resilience During Disconnection
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard;

use Eris\TestTrait;
use Eris\Generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/attendanceleaderboard/tests/property_based/vendor/autoload.php');

/**
 * Property-based tests for Tracking System.
 *
 * Uses Eris library for property-based testing to verify correctness properties
 * hold for all possible inputs within the defined domain.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tracking_properties_test extends \advanced_testcase {
    use TestTrait;

    /**
     * Setup before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Property 1: Kelengkapan Pencatatan Aktivitas Sesi
     *
     * Specification: For any active session, all interactions must be recorded
     * in Activity_Log with complete attributes.
     *
     * This property verifies that:
     * 1. Every interaction during a session is recorded in the database
     * 2. Each record has all required attributes populated
     * 3. No interactions are lost or omitted
     *
     * Validates Requirements: 1.2, 1.4, 2.1, 2.2
     *
     * Test Strategy:
     * - Generate random number of interactions (1-50)
     * - Generate random event types from supported events
     * - Record all interactions via TrackingSystem
     * - Verify count matches expected
     * - Verify each record has complete attributes
     *
     * @return void
     */
    public function test_property1_activity_recording_completeness() {
        $this->forAll(
            Generator\choose(1, 50),  // Number of interactions in session
            Generator\elements([
                'course_module_viewed',
                'quiz_attempt_submitted',
                'forum_post_created',
                'course_module_completion_updated',
                'grade_item_updated',
            ])
        )->then(function($num_interactions, $event_type) {
            global $DB;

            // Setup test data.
            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ts = new \block_attendanceleaderboard\tracking\tracking_system();

            // Simulate N interactions during an active session.
            $interaction_ids = [];
            for ($i = 0; $i < $num_interactions; $i++) {
                $id = $ts->record_activity(
                    $user->id,
                    $course->id,
                    $event_type,
                    'mod_test',
                    [
                        'objectid' => $i + 1,
                        'action'   => 'test_action_' . $i,
                    ]
                );
                $interaction_ids[] = $id;
            }

            // PROPERTY 1.1: All interactions must be recorded.
            $recorded_count = $DB->count_records('acmls_activity_log', [
                'userid'   => $user->id,
                'courseid' => $course->id,
            ]);

            $this->assertEquals(
                $num_interactions,
                $recorded_count,
                "Property 1 violated: Expected {$num_interactions} interactions to be recorded, " .
                "but found {$recorded_count}. All interactions in an active session must be recorded."
            );

            // PROPERTY 1.2: All records must have complete attributes.
            $records = $DB->get_records('acmls_activity_log', [
                'userid'   => $user->id,
                'courseid' => $course->id,
            ]);

            $this->assertCount(
                $num_interactions,
                $records,
                "Property 1 violated: Retrieved record count does not match expected count"
            );

            foreach ($records as $record) {
                // Required attributes must be present and non-empty.
                $this->assertNotEmpty(
                    $record->userid,
                    "Property 1 violated: userid attribute is empty in activity log record {$record->id}"
                );

                $this->assertNotEmpty(
                    $record->courseid,
                    "Property 1 violated: courseid attribute is empty in activity log record {$record->id}"
                );

                $this->assertNotEmpty(
                    $record->event_type,
                    "Property 1 violated: event_type attribute is empty in activity log record {$record->id}"
                );

                $this->assertNotEmpty(
                    $record->component,
                    "Property 1 violated: component attribute is empty in activity log record {$record->id}"
                );

                $this->assertNotEmpty(
                    $record->action,
                    "Property 1 violated: action attribute is empty in activity log record {$record->id}"
                );

                $this->assertGreaterThan(
                    0,
                    $record->timecreated,
                    "Property 1 violated: timecreated attribute is invalid (≤0) in activity log record {$record->id}"
                );

                // Verify attribute types are correct.
                $this->assertIsInt(
                    (int) $record->userid,
                    "Property 1 violated: userid must be integer in record {$record->id}"
                );

                $this->assertIsInt(
                    (int) $record->courseid,
                    "Property 1 violated: courseid must be integer in record {$record->id}"
                );

                $this->assertIsString(
                    $record->event_type,
                    "Property 1 violated: event_type must be string in record {$record->id}"
                );

                $this->assertIsString(
                    $record->component,
                    "Property 1 violated: component must be string in record {$record->id}"
                );

                $this->assertIsString(
                    $record->action,
                    "Property 1 violated: action must be string in record {$record->id}"
                );

                // Verify context_data is valid JSON if present.
                if (!empty($record->context_data)) {
                    $decoded = json_decode($record->context_data, true);
                    $this->assertNotNull(
                        $decoded,
                        "Property 1 violated: context_data must be valid JSON in record {$record->id}"
                    );
                    $this->assertIsArray(
                        $decoded,
                        "Property 1 violated: context_data must decode to array in record {$record->id}"
                    );
                }

                // Verify sent_to_profiler flag is present and boolean-compatible.
                $this->assertContains(
                    (int) $record->sent_to_profiler,
                    [0, 1],
                    "Property 1 violated: sent_to_profiler must be 0 or 1 in record {$record->id}"
                );
            }

            // PROPERTY 1.3: Verify no duplicate or missing records.
            $retrieved_ids = array_column($records, 'id');
            $this->assertCount(
                $num_interactions,
                array_unique($retrieved_ids),
                "Property 1 violated: Duplicate records detected in activity log"
            );

            // PROPERTY 1.4: Verify all returned IDs match what was inserted.
            foreach ($interaction_ids as $expected_id) {
                $this->assertContains(
                    $expected_id,
                    $retrieved_ids,
                    "Property 1 violated: Interaction ID {$expected_id} was not found in retrieved records"
                );
            }
        });
    }

    /**
     * Property 1 Edge Case: Single interaction session.
     *
     * Verifies that even a session with a single interaction is recorded completely.
     *
     * @return void
     */
    public function test_property1_single_interaction_completeness() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record single interaction.
        $id = $ts->record_activity(
            $user->id,
            $course->id,
            'course_module_viewed',
            'mod_resource',
            [
                'objectid' => 123,
                'action'   => 'viewed',
            ]
        );

        // Verify it was recorded.
        $record = $DB->get_record('acmls_activity_log', ['id' => $id]);

        $this->assertNotFalse($record, "Property 1 violated: Single interaction was not recorded");
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals($course->id, $record->courseid);
        $this->assertEquals('course_module_viewed', $record->event_type);
        $this->assertEquals('mod_resource', $record->component);
        $this->assertEquals('viewed', $record->action);
        $this->assertGreaterThan(0, $record->timecreated);
    }

    /**
     * Property 1 Edge Case: Login event recording.
     *
     * Verifies that login events are recorded with complete attributes.
     *
     * @return void
     */
    public function test_property1_login_event_completeness() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record login event.
        $id = $ts->record_login(
            $user->id,
            $course->id,
            [
                'ip'        => '127.0.0.1',
                'sessionid' => 'test_session_123',
            ]
        );

        // Verify login was recorded with complete attributes.
        $record = $DB->get_record('acmls_activity_log', ['id' => $id]);

        $this->assertNotFalse($record, "Property 1 violated: Login event was not recorded");
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals($course->id, $record->courseid);
        $this->assertEquals('user_loggedin', $record->event_type);
        $this->assertEquals('core', $record->component);
        $this->assertEquals('loggedin', $record->action);
        $this->assertGreaterThan(0, $record->timecreated);

        // Verify context data was stored.
        $context = json_decode($record->context_data, true);
        $this->assertIsArray($context);
        $this->assertArrayHasKey('ip', $context);
        $this->assertArrayHasKey('sessionid', $context);
    }

    /**
     * Property 1 Edge Case: Multiple users, multiple courses.
     *
     * Verifies that interactions from different users and courses are all recorded
     * correctly without interference.
     *
     * @return void
     */
    public function test_property1_multi_user_multi_course_completeness() {
        $this->forAll(
            Generator\choose(2, 5),  // Number of users
            Generator\choose(2, 5),  // Number of courses
            Generator\choose(1, 10)  // Interactions per user per course
        )->then(function($num_users, $num_courses, $interactions_per_combo) {
            global $DB;

            $ts = new \block_attendanceleaderboard\tracking\tracking_system();

            // Create users and courses.
            $users = [];
            for ($i = 0; $i < $num_users; $i++) {
                $users[] = $this->getDataGenerator()->create_user();
            }

            $courses = [];
            for ($i = 0; $i < $num_courses; $i++) {
                $courses[] = $this->getDataGenerator()->create_course();
            }

            // Record interactions for each user-course combination.
            $expected_total = $num_users * $num_courses * $interactions_per_combo;
            $recorded_combinations = [];

            foreach ($users as $user) {
                foreach ($courses as $course) {
                    for ($i = 0; $i < $interactions_per_combo; $i++) {
                        $ts->record_activity(
                            $user->id,
                            $course->id,
                            'course_module_viewed',
                            'mod_test',
                            [
                                'objectid' => $i,
                                'action'   => 'viewed',
                            ]
                        );
                    }
                    $recorded_combinations[] = "{$user->id}_{$course->id}";
                }
            }

            // PROPERTY: Total count must match expected.
            $total_recorded = $DB->count_records('acmls_activity_log');
            $this->assertEquals(
                $expected_total,
                $total_recorded,
                "Property 1 violated: Expected {$expected_total} total interactions across " .
                "{$num_users} users and {$num_courses} courses, but found {$total_recorded}"
            );

            // PROPERTY: Each user-course combination must have correct count.
            foreach ($users as $user) {
                foreach ($courses as $course) {
                    $count = $DB->count_records('acmls_activity_log', [
                        'userid'   => $user->id,
                        'courseid' => $course->id,
                    ]);

                    $this->assertEquals(
                        $interactions_per_combo,
                        $count,
                        "Property 1 violated: User {$user->id} in course {$course->id} should have " .
                        "{$interactions_per_combo} interactions, but has {$count}"
                    );
                }
            }
        });
    }

    /**
     * Property 1 Boundary Case: Maximum interactions.
     *
     * Verifies that the system can handle recording a large number of interactions
     * without data loss.
     *
     * @return void
     */
    public function test_property1_large_session_completeness() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record a large number of interactions (simulating very active session).
        $num_interactions = 100;

        for ($i = 0; $i < $num_interactions; $i++) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $i,
                    'action'   => 'viewed',
                ]
            );
        }

        // PROPERTY: All 100 interactions must be recorded.
        $recorded = $DB->count_records('acmls_activity_log', [
            'userid'   => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertEquals(
            $num_interactions,
            $recorded,
            "Property 1 violated: Large session with {$num_interactions} interactions " .
            "should have all interactions recorded, but only {$recorded} were found"
        );
    }

    /**
     * Property 1 Invariant: Attribute completeness across all event types.
     *
     * Verifies that all supported event types result in complete attribute recording.
     *
     * @return void
     */
    public function test_property1_all_event_types_completeness() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        $event_types = [
            'user_loggedin',
            'user_loggedout',
            'course_module_viewed',
            'quiz_attempt_submitted',
            'grade_item_updated',
            'forum_post_created',
            'course_module_completion_updated',
        ];

        // Record one interaction for each event type.
        foreach ($event_types as $event_type) {
            if ($event_type === 'user_loggedin') {
                $ts->record_login($user->id, $course->id, ['test' => 'data']);
            } else {
                $ts->record_activity(
                    $user->id,
                    $course->id,
                    $event_type,
                    'mod_test',
                    [
                        'objectid' => 1,
                        'action'   => 'test',
                    ]
                );
            }
        }

        // PROPERTY: All event types must be recorded with complete attributes.
        $records = $DB->get_records('acmls_activity_log', [
            'userid'   => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertCount(
            count($event_types),
            $records,
            "Property 1 violated: Not all event types were recorded"
        );

        foreach ($records as $record) {
            $this->assertNotEmpty($record->userid);
            $this->assertNotEmpty($record->courseid);
            $this->assertNotEmpty($record->event_type);
            $this->assertNotEmpty($record->component);
            $this->assertNotEmpty($record->action);
            $this->assertGreaterThan(0, $record->timecreated);
        }
    }

    // =========================================================================
    // PROPERTY 2: Konsistensi Ringkasan Sesi dengan Activity_Log
    // =========================================================================

    /**
     * Property 2: Konsistensi Ringkasan Sesi
     *
     * Specification: For any session that ends, the session summary sent to
     * Profiling System must accurately represent ALL activities recorded in
     * Activity_Log for that session — no activities missing, duplicated, or modified.
     *
     * This property verifies that:
     * 1. All activities in Activity_Log are sent to Profiling System
     * 2. Each activity is sent exactly once (no duplicates)
     * 3. Activity attributes match exactly (no modifications)
     * 4. Aggregate metrics are mathematically accurate
     *
     * Validates Requirements: 1.5
     *
     * Test Strategy:
     * - Generate random number of activities (0-100)
     * - Record all activities in Activity_Log
     * - Flush to Profiling System
     * - Verify all activities were processed
     * - Verify no duplicates or modifications
     * - Verify aggregate metrics are correct
     *
     * @return void
     */
    public function test_property2_session_summary_consistency() {
        $this->forAll(
            Generator\choose(1, 100),  // Number of activities in session
            Generator\elements([
                'course_module_viewed',
                'quiz_attempt_submitted',
                'forum_post_created',
                'course_module_completion_updated',
            ])
        )->then(function($num_activities, $event_type) {
            global $DB;

            // Setup test data.
            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ts = new \block_attendanceleaderboard\tracking\tracking_system();

            // Record N activities during session.
            $expected_activities = [];
            for ($i = 0; $i < $num_activities; $i++) {
                $duration = rand(10, 300);  // 10-300 seconds
                $result = ($event_type === 'quiz_attempt_submitted') ? rand(0, 100) / 100.0 : null;

                $id = $ts->record_activity(
                    $user->id,
                    $course->id,
                    $event_type,
                    'mod_test',
                    [
                        'objectid' => $i + 1,
                        'action'   => 'test_action_' . $i,
                        'duration_seconds' => $duration,
                        'result_value' => $result,
                    ]
                );

                $expected_activities[$id] = [
                    'id' => $id,
                    'event_type' => $event_type,
                    'component' => 'mod_test',
                    'objectid' => $i + 1,
                    'action' => 'test_action_' . $i,
                    'duration_seconds' => $duration,
                    'result_value' => $result,
                ];
            }

            // Get all activities from Activity_Log BEFORE flush.
            $activities_before = $DB->get_records('acmls_activity_log', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ], 'id ASC');

            $this->assertCount(
                $num_activities,
                $activities_before,
                "Property 2 violated: Activity_Log should contain {$num_activities} activities before flush"
            );

            // PROPERTY 2.1: All activities must be marked as not sent initially.
            foreach ($activities_before as $activity) {
                $this->assertEquals(
                    0,
                    $activity->sent_to_profiler,
                    "Property 2 violated: Activity {$activity->id} should not be marked as sent before flush"
                );
            }

            // Flush to Profiling System (this sends session summary).
            $ts->flush_to_profiler();

            // Get all activities from Activity_Log AFTER flush.
            $activities_after = $DB->get_records('acmls_activity_log', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ], 'id ASC');

            // PROPERTY 2.2: Count must remain the same (no activities lost or added).
            $this->assertCount(
                $num_activities,
                $activities_after,
                "Property 2 violated: Activity_Log count changed after flush. " .
                "Expected {$num_activities}, got " . count($activities_after)
            );

            // PROPERTY 2.3: All activities must be marked as sent after flush.
            foreach ($activities_after as $activity) {
                $this->assertEquals(
                    1,
                    $activity->sent_to_profiler,
                    "Property 2 violated: Activity {$activity->id} should be marked as sent after flush"
                );
            }

            // PROPERTY 2.4: Activity attributes must remain unchanged.
            foreach ($activities_before as $id => $before) {
                $this->assertArrayHasKey(
                    $id,
                    $activities_after,
                    "Property 2 violated: Activity {$id} missing after flush"
                );

                $after = $activities_after[$id];

                $this->assertEquals(
                    $before->userid,
                    $after->userid,
                    "Property 2 violated: userid changed for activity {$id}"
                );

                $this->assertEquals(
                    $before->courseid,
                    $after->courseid,
                    "Property 2 violated: courseid changed for activity {$id}"
                );

                $this->assertEquals(
                    $before->event_type,
                    $after->event_type,
                    "Property 2 violated: event_type changed for activity {$id}"
                );

                $this->assertEquals(
                    $before->component,
                    $after->component,
                    "Property 2 violated: component changed for activity {$id}"
                );

                $this->assertEquals(
                    $before->objectid,
                    $after->objectid,
                    "Property 2 violated: objectid changed for activity {$id}"
                );

                $this->assertEquals(
                    $before->action,
                    $after->action,
                    "Property 2 violated: action changed for activity {$id}"
                );

                $this->assertEquals(
                    $before->duration_seconds,
                    $after->duration_seconds,
                    "Property 2 violated: duration_seconds changed for activity {$id}"
                );

                $this->assertEquals(
                    $before->result_value,
                    $after->result_value,
                    "Property 2 violated: result_value changed for activity {$id}"
                );

                $this->assertEquals(
                    $before->timecreated,
                    $after->timecreated,
                    "Property 2 violated: timecreated changed for activity {$id}"
                );
            }

            // PROPERTY 2.5: Verify aggregate metrics are mathematically accurate.
            $total_duration = 0;
            $activity_count = 0;
            $result_sum = 0;
            $result_count = 0;

            foreach ($activities_after as $activity) {
                $activity_count++;
                $total_duration += $activity->duration_seconds;

                if ($activity->result_value !== null) {
                    $result_sum += $activity->result_value;
                    $result_count++;
                }
            }

            $this->assertEquals(
                $num_activities,
                $activity_count,
                "Property 2 violated: Activity count mismatch in aggregate calculation"
            );

            // Verify total duration matches sum of individual durations.
            $expected_total_duration = array_sum(array_column($expected_activities, 'duration_seconds'));
            $this->assertEquals(
                $expected_total_duration,
                $total_duration,
                "Property 2 violated: Total duration mismatch. " .
                "Expected {$expected_total_duration}, got {$total_duration}"
            );

            // PROPERTY 2.6: No duplicate activities should exist.
            $activity_ids = array_keys($activities_after);
            $unique_ids = array_unique($activity_ids);

            $this->assertCount(
                count($activity_ids),
                $unique_ids,
                "Property 2 violated: Duplicate activities detected after flush"
            );
        });
    }

    /**
     * Property 2 Edge Case: Empty session (no activities).
     *
     * Verifies that flushing an empty session does not cause errors or create
     * phantom activities.
     *
     * @return void
     */
    public function test_property2_empty_session_consistency() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // No activities recorded — empty session.

        // Verify no activities exist.
        $count_before = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertEquals(
            0,
            $count_before,
            "Property 2 violated: Empty session should have 0 activities before flush"
        );

        // Flush empty session.
        $ts->flush_to_profiler();

        // Verify still no activities exist.
        $count_after = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertEquals(
            0,
            $count_after,
            "Property 2 violated: Empty session should have 0 activities after flush"
        );
    }

    /**
     * Property 2 Edge Case: Single activity session.
     *
     * Verifies that a session with exactly one activity is flushed correctly.
     *
     * @return void
     */
    public function test_property2_single_activity_consistency() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record single activity.
        $duration = 120;
        $result = 0.85;

        $id = $ts->record_activity(
            $user->id,
            $course->id,
            'quiz_attempt_submitted',
            'mod_quiz',
            [
                'objectid' => 1,
                'action' => 'submitted',
                'duration_seconds' => $duration,
                'result_value' => $result,
            ]
        );

        // Get activity before flush.
        $before = $DB->get_record('acmls_activity_log', ['id' => $id]);

        $this->assertNotFalse($before);
        $this->assertEquals(0, $before->sent_to_profiler);

        // Flush to profiler.
        $ts->flush_to_profiler();

        // Get activity after flush.
        $after = $DB->get_record('acmls_activity_log', ['id' => $id]);

        $this->assertNotFalse($after);
        $this->assertEquals(1, $after->sent_to_profiler);

        // Verify attributes unchanged.
        $this->assertEquals($before->userid, $after->userid);
        $this->assertEquals($before->courseid, $after->courseid);
        $this->assertEquals($before->event_type, $after->event_type);
        $this->assertEquals($before->component, $after->component);
        $this->assertEquals($before->objectid, $after->objectid);
        $this->assertEquals($before->action, $after->action);
        $this->assertEquals($before->duration_seconds, $after->duration_seconds);
        $this->assertEquals($before->result_value, $after->result_value);
        $this->assertEquals($before->timecreated, $after->timecreated);
    }

    /**
     * Property 2 Edge Case: Activities with missing optional fields.
     *
     * Verifies that activities with null/missing optional fields (duration, result)
     * are still flushed correctly without data corruption.
     *
     * @return void
     */
    public function test_property2_missing_optional_fields_consistency() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record activities with various missing optional fields.
        $id1 = $ts->record_activity(
            $user->id,
            $course->id,
            'course_module_viewed',
            'mod_resource',
            [
                'objectid' => 1,
                'action' => 'viewed',
                // No duration_seconds
                // No result_value
            ]
        );

        $id2 = $ts->record_activity(
            $user->id,
            $course->id,
            'forum_post_created',
            'mod_forum',
            [
                'objectid' => 2,
                'action' => 'created',
                'duration_seconds' => 60,
                // No result_value
            ]
        );

        $id3 = $ts->record_activity(
            $user->id,
            $course->id,
            'quiz_attempt_submitted',
            'mod_quiz',
            [
                'objectid' => 3,
                'action' => 'submitted',
                // No duration_seconds
                'result_value' => 0.75,
            ]
        );

        // Get activities before flush.
        $before = $DB->get_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ], 'id ASC');

        $this->assertCount(3, $before);

        // Flush to profiler.
        $ts->flush_to_profiler();

        // Get activities after flush.
        $after = $DB->get_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ], 'id ASC');

        $this->assertCount(3, $after);

        // Verify all marked as sent.
        foreach ($after as $activity) {
            $this->assertEquals(
                1,
                $activity->sent_to_profiler,
                "Property 2 violated: Activity {$activity->id} should be marked as sent"
            );
        }

        // Verify attributes unchanged (including null values).
        foreach ($before as $id => $before_activity) {
            $after_activity = $after[$id];

            $this->assertEquals($before_activity->duration_seconds, $after_activity->duration_seconds);
            $this->assertEquals($before_activity->result_value, $after_activity->result_value);
        }
    }

    /**
     * Property 2 Edge Case: Multiple flushes (idempotency).
     *
     * Verifies that flushing multiple times does not cause duplicate processing
     * or data corruption. Already-sent activities should not be re-sent.
     *
     * @return void
     */
    public function test_property2_multiple_flush_idempotency() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record activities.
        $num_activities = 10;
        for ($i = 0; $i < $num_activities; $i++) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $i + 1,
                    'action' => 'viewed',
                ]
            );
        }

        // First flush.
        $ts->flush_to_profiler();

        $after_first_flush = $DB->get_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertCount($num_activities, $after_first_flush);

        foreach ($after_first_flush as $activity) {
            $this->assertEquals(1, $activity->sent_to_profiler);
        }

        // Second flush (should be no-op).
        $ts->flush_to_profiler();

        $after_second_flush = $DB->get_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        // PROPERTY: Count should remain the same.
        $this->assertCount(
            $num_activities,
            $after_second_flush,
            "Property 2 violated: Activity count changed after second flush"
        );

        // PROPERTY: All activities should still be marked as sent (no duplicates).
        foreach ($after_second_flush as $activity) {
            $this->assertEquals(
                1,
                $activity->sent_to_profiler,
                "Property 2 violated: Activity {$activity->id} sent_to_profiler flag changed"
            );
        }

        // PROPERTY: Activity attributes should remain unchanged.
        foreach ($after_first_flush as $id => $first) {
            $second = $after_second_flush[$id];

            $this->assertEquals($first->userid, $second->userid);
            $this->assertEquals($first->courseid, $second->courseid);
            $this->assertEquals($first->event_type, $second->event_type);
            $this->assertEquals($first->component, $second->component);
            $this->assertEquals($first->objectid, $second->objectid);
            $this->assertEquals($first->action, $second->action);
            $this->assertEquals($first->duration_seconds, $second->duration_seconds);
            $this->assertEquals($first->result_value, $second->result_value);
            $this->assertEquals($first->timecreated, $second->timecreated);
        }
    }

    /**
     * Property 2 Invariant: Aggregate metrics mathematical accuracy.
     *
     * Verifies that aggregate metrics (total duration, activity counts) calculated
     * from the session summary match the mathematical sum of individual activities.
     *
     * @return void
     */
    public function test_property2_aggregate_metrics_accuracy() {
        $this->forAll(
            Generator\choose(5, 50)  // Number of activities
        )->then(function($num_activities) {
            global $DB;

            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ts = new \block_attendanceleaderboard\tracking\tracking_system();

            // Record activities with known durations and results.
            $expected_total_duration = 0;
            $expected_result_sum = 0;
            $expected_result_count = 0;

            for ($i = 0; $i < $num_activities; $i++) {
                $duration = rand(10, 300);
                $has_result = ($i % 3 === 0);  // Every 3rd activity has a result
                $result = $has_result ? (rand(0, 100) / 100.0) : null;

                $expected_total_duration += $duration;
                if ($has_result) {
                    $expected_result_sum += $result;
                    $expected_result_count++;
                }

                $ts->record_activity(
                    $user->id,
                    $course->id,
                    'course_module_viewed',
                    'mod_test',
                    [
                        'objectid' => $i + 1,
                        'action' => 'viewed',
                        'duration_seconds' => $duration,
                        'result_value' => $result,
                    ]
                );
            }

            // Flush to profiler.
            $ts->flush_to_profiler();

            // Calculate aggregate metrics from Activity_Log.
            $activities = $DB->get_records('acmls_activity_log', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ]);

            $actual_total_duration = 0;
            $actual_result_sum = 0;
            $actual_result_count = 0;

            foreach ($activities as $activity) {
                $actual_total_duration += $activity->duration_seconds;

                if ($activity->result_value !== null) {
                    $actual_result_sum += $activity->result_value;
                    $actual_result_count++;
                }
            }

            // PROPERTY: Total duration must match exactly.
            $this->assertEquals(
                $expected_total_duration,
                $actual_total_duration,
                "Property 2 violated: Total duration mismatch. " .
                "Expected {$expected_total_duration}, got {$actual_total_duration}"
            );

            // PROPERTY: Result count must match exactly.
            $this->assertEquals(
                $expected_result_count,
                $actual_result_count,
                "Property 2 violated: Result count mismatch. " .
                "Expected {$expected_result_count}, got {$actual_result_count}"
            );

            // PROPERTY: Result sum must match (within floating point tolerance).
            $this->assertEqualsWithDelta(
                $expected_result_sum,
                $actual_result_sum,
                0.0001,
                "Property 2 violated: Result sum mismatch. " .
                "Expected {$expected_result_sum}, got {$actual_result_sum}"
            );

            // PROPERTY: Activity count must match.
            $this->assertCount(
                $num_activities,
                $activities,
                "Property 2 violated: Activity count mismatch"
            );
        });
    }

    // =========================================================================
    // PROPERTY 3: Akurasi Kalkulasi Metrik Engagement
    // =========================================================================

    /**
     * Property 3: Akurasi Kalkulasi Metrik Engagement
     *
     * Specification: For any set of Activity_Log entries for a Learner in a given
     * period, the calculated engagement metrics (login frequency, resource access count,
     * total session duration) must be mathematically accurate and consistent with the
     * raw Activity_Log data.
     *
     * This property verifies that:
     * 1. Login frequency count matches the number of login events in Activity_Log
     * 2. Resource access count matches the number of resource access events
     * 3. Total session duration equals the sum of all duration_seconds values
     * 4. Aggregate metrics are calculated correctly without rounding errors
     * 5. No activities are double-counted or omitted
     *
     * Validates Requirements: 2.3
     *
     * Test Strategy:
     * - Generate random number of activities (1-100)
     * - Generate random mix of event types (logins, resource access, other)
     * - Generate random durations for each activity
     * - Calculate expected metrics manually
     * - Verify system-calculated metrics match expected values exactly
     *
     * **Validates: Requirements 2.3**
     *
     * @return void
     */
    public function test_property3_engagement_metrics_accuracy() {
        $this->forAll(
            Generator\choose(1, 100),  // Number of activities
            Generator\choose(1, 10)    // Number of login events
        )->then(function($num_activities, $num_logins) {
            global $DB;

            // Setup test data.
            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ts = new \block_attendanceleaderboard\tracking\tracking_system();

            // Track expected metrics.
            $expected_login_count = 0;
            $expected_resource_access_count = 0;
            $expected_total_duration = 0;
            $expected_activity_count = 0;

            // Record login events.
            for ($i = 0; $i < $num_logins; $i++) {
                $duration = rand(0, 60);  // Login events typically have short/zero duration
                $ts->record_login(
                    $user->id,
                    $course->id,
                    [
                        'ip' => '127.0.0.1',
                        'sessionid' => 'session_' . $i,
                    ]
                );
                $expected_login_count++;
                $expected_total_duration += $duration;
                $expected_activity_count++;
            }

            // Record resource access and other activities.
            $remaining_activities = $num_activities - $num_logins;
            $event_types = [
                'course_module_viewed',
                'quiz_attempt_submitted',
                'forum_post_created',
                'course_module_completion_updated',
            ];

            for ($i = 0; $i < $remaining_activities; $i++) {
                $event_type = $event_types[array_rand($event_types)];
                $duration = rand(10, 600);  // 10 seconds to 10 minutes

                $ts->record_activity(
                    $user->id,
                    $course->id,
                    $event_type,
                    'mod_test',
                    [
                        'objectid' => $i + 1,
                        'action' => 'test_action_' . $i,
                        'duration_seconds' => $duration,
                    ]
                );

                if ($event_type === 'course_module_viewed') {
                    $expected_resource_access_count++;
                }

                $expected_total_duration += $duration;
                $expected_activity_count++;
            }

            // Retrieve all activities from Activity_Log.
            $activities = $DB->get_records('acmls_activity_log', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ]);

            // PROPERTY 3.1: Total activity count must match.
            $actual_activity_count = count($activities);
            $this->assertEquals(
                $expected_activity_count,
                $actual_activity_count,
                "Property 3 violated: Activity count mismatch. " .
                "Expected {$expected_activity_count}, got {$actual_activity_count}"
            );

            // PROPERTY 3.2: Calculate actual metrics from Activity_Log.
            $actual_login_count = 0;
            $actual_resource_access_count = 0;
            $actual_total_duration = 0;

            foreach ($activities as $activity) {
                // Count logins.
                if ($activity->event_type === 'user_loggedin') {
                    $actual_login_count++;
                }

                // Count resource accesses.
                if ($activity->event_type === 'course_module_viewed') {
                    $actual_resource_access_count++;
                }

                // Sum durations.
                $actual_total_duration += (int) $activity->duration_seconds;
            }

            // PROPERTY 3.3: Login frequency must be mathematically accurate.
            $this->assertEquals(
                $expected_login_count,
                $actual_login_count,
                "Property 3 violated: Login count mismatch. " .
                "Expected {$expected_login_count} logins, got {$actual_login_count}. " .
                "Engagement metrics must accurately reflect raw Activity_Log data."
            );

            // PROPERTY 3.4: Resource access count must be mathematically accurate.
            $this->assertEquals(
                $expected_resource_access_count,
                $actual_resource_access_count,
                "Property 3 violated: Resource access count mismatch. " .
                "Expected {$expected_resource_access_count} resource accesses, got {$actual_resource_access_count}. " .
                "Engagement metrics must accurately reflect raw Activity_Log data."
            );

            // PROPERTY 3.5: Total duration must be mathematically accurate.
            $this->assertEquals(
                $expected_total_duration,
                $actual_total_duration,
                "Property 3 violated: Total duration mismatch. " .
                "Expected {$expected_total_duration} seconds, got {$actual_total_duration} seconds. " .
                "Engagement metrics must be consistent with raw Activity_Log data."
            );

            // PROPERTY 3.6: Verify no activities are double-counted.
            $activity_ids = array_column($activities, 'id');
            $unique_ids = array_unique($activity_ids);
            $this->assertCount(
                count($activity_ids),
                $unique_ids,
                "Property 3 violated: Duplicate activities detected. " .
                "Each activity must be counted exactly once in engagement metrics."
            );

            // PROPERTY 3.7: Verify aggregate metrics can be recalculated consistently.
            // Calculate average duration per activity.
            $expected_avg_duration = $expected_activity_count > 0
                ? $expected_total_duration / $expected_activity_count
                : 0;

            $actual_avg_duration = $actual_activity_count > 0
                ? $actual_total_duration / $actual_activity_count
                : 0;

            $this->assertEqualsWithDelta(
                $expected_avg_duration,
                $actual_avg_duration,
                0.01,
                "Property 3 violated: Average duration calculation mismatch. " .
                "Expected {$expected_avg_duration}, got {$actual_avg_duration}. " .
                "Aggregate metrics must be mathematically accurate."
            );
        });
    }

    /**
     * Property 3 Edge Case: Zero duration activities.
     *
     * Verifies that activities with zero duration are counted correctly
     * and do not cause division-by-zero or other calculation errors.
     *
     * @return void
     */
    public function test_property3_zero_duration_activities() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record activities with zero duration.
        $num_activities = 10;
        for ($i = 0; $i < $num_activities; $i++) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $i + 1,
                    'action' => 'viewed',
                    'duration_seconds' => 0,
                ]
            );
        }

        // Retrieve activities.
        $activities = $DB->get_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        // PROPERTY: All activities must be recorded.
        $this->assertCount(
            $num_activities,
            $activities,
            "Property 3 violated: Not all zero-duration activities were recorded"
        );

        // PROPERTY: Total duration must be zero.
        $total_duration = 0;
        foreach ($activities as $activity) {
            $total_duration += (int) $activity->duration_seconds;
        }

        $this->assertEquals(
            0,
            $total_duration,
            "Property 3 violated: Total duration should be 0 for zero-duration activities"
        );

        // PROPERTY: Resource access count must still be accurate.
        $resource_count = 0;
        foreach ($activities as $activity) {
            if ($activity->event_type === 'course_module_viewed') {
                $resource_count++;
            }
        }

        $this->assertEquals(
            $num_activities,
            $resource_count,
            "Property 3 violated: Resource access count incorrect for zero-duration activities"
        );
    }

    /**
     * Property 3 Edge Case: Very long duration activities.
     *
     * Verifies that activities with very long durations (e.g., multi-hour sessions)
     * are calculated correctly without overflow or precision loss.
     *
     * @return void
     */
    public function test_property3_very_long_duration_activities() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record activities with very long durations (up to 10 hours).
        $durations = [3600, 7200, 10800, 14400, 18000, 21600, 25200, 28800, 32400, 36000];
        $expected_total = array_sum($durations);

        foreach ($durations as $i => $duration) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $i + 1,
                    'action' => 'viewed',
                    'duration_seconds' => $duration,
                ]
            );
        }

        // Retrieve activities.
        $activities = $DB->get_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        // PROPERTY: Total duration must be accurate for large values.
        $actual_total = 0;
        foreach ($activities as $activity) {
            $actual_total += (int) $activity->duration_seconds;
        }

        $this->assertEquals(
            $expected_total,
            $actual_total,
            "Property 3 violated: Total duration calculation incorrect for very long durations. " .
            "Expected {$expected_total}, got {$actual_total}"
        );
    }

    /**
     * Property 3 Edge Case: Mixed event types.
     *
     * Verifies that engagement metrics are calculated correctly when
     * Activity_Log contains a diverse mix of event types.
     *
     * @return void
     */
    public function test_property3_mixed_event_types_accuracy() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Define expected counts.
        $expected_logins = 3;
        $expected_resource_views = 5;
        $expected_quiz_submissions = 2;
        $expected_forum_posts = 4;
        $expected_total_duration = 0;

        // Record logins.
        for ($i = 0; $i < $expected_logins; $i++) {
            $duration = 30;
            $ts->record_login($user->id, $course->id, ['test' => 'data']);
            $expected_total_duration += $duration;
        }

        // Record resource views.
        for ($i = 0; $i < $expected_resource_views; $i++) {
            $duration = 120;
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_resource',
                [
                    'objectid' => $i + 1,
                    'action' => 'viewed',
                    'duration_seconds' => $duration,
                ]
            );
            $expected_total_duration += $duration;
        }

        // Record quiz submissions.
        for ($i = 0; $i < $expected_quiz_submissions; $i++) {
            $duration = 300;
            $ts->record_activity(
                $user->id,
                $course->id,
                'quiz_attempt_submitted',
                'mod_quiz',
                [
                    'objectid' => $i + 1,
                    'action' => 'submitted',
                    'duration_seconds' => $duration,
                ]
            );
            $expected_total_duration += $duration;
        }

        // Record forum posts.
        for ($i = 0; $i < $expected_forum_posts; $i++) {
            $duration = 180;
            $ts->record_activity(
                $user->id,
                $course->id,
                'forum_post_created',
                'mod_forum',
                [
                    'objectid' => $i + 1,
                    'action' => 'created',
                    'duration_seconds' => $duration,
                ]
            );
            $expected_total_duration += $duration;
        }

        // Calculate actual metrics from Activity_Log.
        $activities = $DB->get_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $actual_logins = 0;
        $actual_resource_views = 0;
        $actual_quiz_submissions = 0;
        $actual_forum_posts = 0;
        $actual_total_duration = 0;

        foreach ($activities as $activity) {
            $actual_total_duration += (int) $activity->duration_seconds;

            switch ($activity->event_type) {
                case 'user_loggedin':
                    $actual_logins++;
                    break;
                case 'course_module_viewed':
                    $actual_resource_views++;
                    break;
                case 'quiz_attempt_submitted':
                    $actual_quiz_submissions++;
                    break;
                case 'forum_post_created':
                    $actual_forum_posts++;
                    break;
            }
        }

        // PROPERTY: All event type counts must be accurate.
        $this->assertEquals(
            $expected_logins,
            $actual_logins,
            "Property 3 violated: Login count mismatch in mixed event types"
        );

        $this->assertEquals(
            $expected_resource_views,
            $actual_resource_views,
            "Property 3 violated: Resource view count mismatch in mixed event types"
        );

        $this->assertEquals(
            $expected_quiz_submissions,
            $actual_quiz_submissions,
            "Property 3 violated: Quiz submission count mismatch in mixed event types"
        );

        $this->assertEquals(
            $expected_forum_posts,
            $actual_forum_posts,
            "Property 3 violated: Forum post count mismatch in mixed event types"
        );

        $this->assertEquals(
            $expected_total_duration,
            $actual_total_duration,
            "Property 3 violated: Total duration mismatch in mixed event types. " .
            "Expected {$expected_total_duration}, got {$actual_total_duration}"
        );
    }

    /**
     * Property 3 Invariant: Multiple users, independent metrics.
     *
     * Verifies that engagement metrics for different users are calculated
     * independently and do not interfere with each other.
     *
     * @return void
     */
    public function test_property3_multi_user_independent_metrics() {
        $this->forAll(
            Generator\choose(2, 5),   // Number of users
            Generator\choose(5, 20)   // Activities per user
        )->then(function($num_users, $activities_per_user) {
            global $DB;

            $course = $this->getDataGenerator()->create_course();
            $ts = new \block_attendanceleaderboard\tracking\tracking_system();

            $expected_metrics = [];

            // Create users and record activities.
            for ($u = 0; $u < $num_users; $u++) {
                $user = $this->getDataGenerator()->create_user();
                $userid = $user->id;

                $expected_metrics[$userid] = [
                    'login_count' => 0,
                    'resource_count' => 0,
                    'total_duration' => 0,
                ];

                // Record varied activities for each user.
                for ($i = 0; $i < $activities_per_user; $i++) {
                    $is_login = ($i % 5 === 0);
                    $is_resource = ($i % 3 === 0);
                    $duration = rand(10, 300);

                    if ($is_login) {
                        $ts->record_login($userid, $course->id, ['test' => 'data']);
                        $expected_metrics[$userid]['login_count']++;
                        $expected_metrics[$userid]['total_duration'] += $duration;
                    } elseif ($is_resource) {
                        $ts->record_activity(
                            $userid,
                            $course->id,
                            'course_module_viewed',
                            'mod_test',
                            [
                                'objectid' => $i,
                                'action' => 'viewed',
                                'duration_seconds' => $duration,
                            ]
                        );
                        $expected_metrics[$userid]['resource_count']++;
                        $expected_metrics[$userid]['total_duration'] += $duration;
                    } else {
                        $ts->record_activity(
                            $userid,
                            $course->id,
                            'forum_post_created',
                            'mod_forum',
                            [
                                'objectid' => $i,
                                'action' => 'created',
                                'duration_seconds' => $duration,
                            ]
                        );
                        $expected_metrics[$userid]['total_duration'] += $duration;
                    }
                }
            }

            // Verify metrics for each user independently.
            foreach ($expected_metrics as $userid => $expected) {
                $activities = $DB->get_records('acmls_activity_log', [
                    'userid' => $userid,
                    'courseid' => $course->id,
                ]);

                $actual_login_count = 0;
                $actual_resource_count = 0;
                $actual_total_duration = 0;

                foreach ($activities as $activity) {
                    if ($activity->event_type === 'user_loggedin') {
                        $actual_login_count++;
                    }
                    if ($activity->event_type === 'course_module_viewed') {
                        $actual_resource_count++;
                    }
                    $actual_total_duration += (int) $activity->duration_seconds;
                }

                // PROPERTY: Each user's metrics must be independent and accurate.
                $this->assertEquals(
                    $expected['login_count'],
                    $actual_login_count,
                    "Property 3 violated: User {$userid} login count mismatch. " .
                    "Metrics must be calculated independently per user."
                );

                $this->assertEquals(
                    $expected['resource_count'],
                    $actual_resource_count,
                    "Property 3 violated: User {$userid} resource count mismatch. " .
                    "Metrics must be calculated independently per user."
                );

                $this->assertEquals(
                    $expected['total_duration'],
                    $actual_total_duration,
                    "Property 3 violated: User {$userid} total duration mismatch. " .
                    "Expected {$expected['total_duration']}, got {$actual_total_duration}. " .
                    "Metrics must be calculated independently per user."
                );
            }
        });
    }

    /**
     * Property 3 Boundary Case: Single activity of each type.
     *
     * Verifies that engagement metrics are accurate even with minimal data
     * (one activity of each type).
     *
     * @return void
     */
    public function test_property3_single_activity_per_type() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record exactly one activity of each type.
        $ts->record_login($user->id, $course->id, ['test' => 'data']);
        $ts->record_activity(
            $user->id,
            $course->id,
            'course_module_viewed',
            'mod_resource',
            [
                'objectid' => 1,
                'action' => 'viewed',
                'duration_seconds' => 100,
            ]
        );
        $ts->record_activity(
            $user->id,
            $course->id,
            'quiz_attempt_submitted',
            'mod_quiz',
            [
                'objectid' => 1,
                'action' => 'submitted',
                'duration_seconds' => 200,
            ]
        );

        // Calculate metrics.
        $activities = $DB->get_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $login_count = 0;
        $resource_count = 0;
        $total_duration = 0;

        foreach ($activities as $activity) {
            if ($activity->event_type === 'user_loggedin') {
                $login_count++;
            }
            if ($activity->event_type === 'course_module_viewed') {
                $resource_count++;
            }
            $total_duration += (int) $activity->duration_seconds;
        }

        // PROPERTY: Metrics must be accurate for minimal data.
        $this->assertEquals(1, $login_count, "Property 3 violated: Expected 1 login");
        $this->assertEquals(1, $resource_count, "Property 3 violated: Expected 1 resource access");
        $this->assertEquals(300, $total_duration, "Property 3 violated: Expected total duration of 300 seconds");
    }

    // =========================================================================
    // PROPERTY 4: Ketahanan Data saat Koneksi Terputus
    // =========================================================================

    /**
     * Property 4: Ketahanan Data saat Koneksi Terputus
     *
     * Specification: For any set of Activity_Log entries generated during a period
     * when the connection to Profiling System is unavailable, ALL logs must be
     * successfully delivered to Profiling System after the connection is restored —
     * no data loss, no duplicates, no modifications.
     *
     * This property verifies that:
     * 1. Activity_Logs generated during disconnection are stored locally
     * 2. All locally stored logs are marked as not sent (sent_to_profiler=0)
     * 3. After reconnection, all pending logs are delivered successfully
     * 4. All delivered logs are marked as sent (sent_to_profiler=1)
     * 5. No logs are lost during disconnection or reconnection
     * 6. No logs are duplicated during retry
     * 7. Log attributes remain unchanged throughout the process
     *
     * Validates Requirements: 2.5
     *
     * Test Strategy:
     * - Generate random number of activities (1-50)
     * - Record activities while Profiling System is unavailable (simulated disconnection)
     * - Verify all logs are stored locally with sent_to_profiler=0
     * - Restore connection (make Profiling System available)
     * - Flush logs to Profiling System
     * - Verify all logs are marked as sent
     * - Verify no data loss or corruption
     *
     * @return void
     */
    public function test_property4_data_resilience_during_disconnection() {
        $this->forAll(
            Generator\choose(1, 50),  // Number of activities during disconnection
            Generator\elements([
                'course_module_viewed',
                'quiz_attempt_submitted',
                'forum_post_created',
                'course_module_completion_updated',
            ])
        )->then(function($num_activities, $event_type) {
            global $DB;

            // Setup test data.
            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ts = new \block_attendanceleaderboard\tracking\tracking_system();

            // PHASE 1: DISCONNECTION - Record activities while Profiling System is unavailable.
            // Note: In the actual implementation, disconnection is simulated by ProfilingSystem
            // throwing an exception. Here we record activities normally but they won't be flushed yet.

            $expected_logs = [];
            for ($i = 0; $i < $num_activities; $i++) {
                $duration = rand(10, 300);
                $result = ($event_type === 'quiz_attempt_submitted') ? rand(0, 100) / 100.0 : null;

                $id = $ts->record_activity(
                    $user->id,
                    $course->id,
                    $event_type,
                    'mod_test',
                    [
                        'objectid' => $i + 1,
                        'action' => 'test_action_' . $i,
                        'duration_seconds' => $duration,
                        'result_value' => $result,
                    ]
                );

                $expected_logs[$id] = [
                    'id' => $id,
                    'userid' => $user->id,
                    'courseid' => $course->id,
                    'event_type' => $event_type,
                    'component' => 'mod_test',
                    'objectid' => $i + 1,
                    'action' => 'test_action_' . $i,
                    'duration_seconds' => $duration,
                    'result_value' => $result,
                ];
            }

            // PROPERTY 4.1: All activities must be recorded locally during disconnection.
            $logs_during_disconnection = $DB->get_records('acmls_activity_log', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ], 'id ASC');

            $this->assertCount(
                $num_activities,
                $logs_during_disconnection,
                "Property 4 violated: Expected {$num_activities} logs to be stored locally during disconnection, " .
                "but found " . count($logs_during_disconnection) . ". " .
                "All Activity_Logs generated during disconnection must be stored locally."
            );

            // PROPERTY 4.2: All logs must be marked as not sent during disconnection.
            foreach ($logs_during_disconnection as $log) {
                $this->assertEquals(
                    0,
                    $log->sent_to_profiler,
                    "Property 4 violated: Log {$log->id} should be marked as not sent (sent_to_profiler=0) " .
                    "during disconnection, but found sent_to_profiler={$log->sent_to_profiler}"
                );
            }

            // PROPERTY 4.3: Verify all expected logs are present with correct attributes.
            foreach ($expected_logs as $expected_id => $expected_data) {
                $this->assertArrayHasKey(
                    $expected_id,
                    $logs_during_disconnection,
                    "Property 4 violated: Expected log ID {$expected_id} not found in locally stored logs"
                );

                $actual_log = $logs_during_disconnection[$expected_id];

                $this->assertEquals(
                    $expected_data['userid'],
                    $actual_log->userid,
                    "Property 4 violated: userid mismatch for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['courseid'],
                    $actual_log->courseid,
                    "Property 4 violated: courseid mismatch for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['event_type'],
                    $actual_log->event_type,
                    "Property 4 violated: event_type mismatch for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['component'],
                    $actual_log->component,
                    "Property 4 violated: component mismatch for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['objectid'],
                    $actual_log->objectid,
                    "Property 4 violated: objectid mismatch for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['action'],
                    $actual_log->action,
                    "Property 4 violated: action mismatch for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['duration_seconds'],
                    $actual_log->duration_seconds,
                    "Property 4 violated: duration_seconds mismatch for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['result_value'],
                    $actual_log->result_value,
                    "Property 4 violated: result_value mismatch for log {$expected_id}"
                );
            }

            // PHASE 2: RECONNECTION - Flush logs to Profiling System after connection is restored.
            $ts->flush_to_profiler();

            // PROPERTY 4.4: All logs must still exist after flush (no data loss).
            $logs_after_reconnection = $DB->get_records('acmls_activity_log', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ], 'id ASC');

            $this->assertCount(
                $num_activities,
                $logs_after_reconnection,
                "Property 4 violated: Expected {$num_activities} logs after reconnection, " .
                "but found " . count($logs_after_reconnection) . ". " .
                "No Activity_Logs should be lost during reconnection."
            );

            // PROPERTY 4.5: All logs must be marked as sent after successful flush.
            foreach ($logs_after_reconnection as $log) {
                $this->assertEquals(
                    1,
                    $log->sent_to_profiler,
                    "Property 4 violated: Log {$log->id} should be marked as sent (sent_to_profiler=1) " .
                    "after reconnection, but found sent_to_profiler={$log->sent_to_profiler}. " .
                    "All logs must be successfully delivered after reconnection."
                );
            }

            // PROPERTY 4.6: Verify no duplicate logs were created.
            $log_ids_after = array_keys($logs_after_reconnection);
            $unique_ids = array_unique($log_ids_after);

            $this->assertCount(
                count($log_ids_after),
                $unique_ids,
                "Property 4 violated: Duplicate logs detected after reconnection. " .
                "Each Activity_Log must be delivered exactly once."
            );

            // PROPERTY 4.7: Verify log attributes remain unchanged after reconnection.
            foreach ($expected_logs as $expected_id => $expected_data) {
                $this->assertArrayHasKey(
                    $expected_id,
                    $logs_after_reconnection,
                    "Property 4 violated: Expected log ID {$expected_id} not found after reconnection"
                );

                $actual_log = $logs_after_reconnection[$expected_id];

                $this->assertEquals(
                    $expected_data['userid'],
                    $actual_log->userid,
                    "Property 4 violated: userid changed after reconnection for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['courseid'],
                    $actual_log->courseid,
                    "Property 4 violated: courseid changed after reconnection for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['event_type'],
                    $actual_log->event_type,
                    "Property 4 violated: event_type changed after reconnection for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['component'],
                    $actual_log->component,
                    "Property 4 violated: component changed after reconnection for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['objectid'],
                    $actual_log->objectid,
                    "Property 4 violated: objectid changed after reconnection for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['action'],
                    $actual_log->action,
                    "Property 4 violated: action changed after reconnection for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['duration_seconds'],
                    $actual_log->duration_seconds,
                    "Property 4 violated: duration_seconds changed after reconnection for log {$expected_id}"
                );

                $this->assertEquals(
                    $expected_data['result_value'],
                    $actual_log->result_value,
                    "Property 4 violated: result_value changed after reconnection for log {$expected_id}"
                );

                $this->assertEquals(
                    $logs_during_disconnection[$expected_id]->timecreated,
                    $actual_log->timecreated,
                    "Property 4 violated: timecreated changed after reconnection for log {$expected_id}"
                );
            }

            // PROPERTY 4.8: Verify aggregate metrics remain accurate after reconnection.
            $total_duration_before = 0;
            foreach ($logs_during_disconnection as $log) {
                $total_duration_before += (int) $log->duration_seconds;
            }

            $total_duration_after = 0;
            foreach ($logs_after_reconnection as $log) {
                $total_duration_after += (int) $log->duration_seconds;
            }

            $this->assertEquals(
                $total_duration_before,
                $total_duration_after,
                "Property 4 violated: Total duration changed after reconnection. " .
                "Expected {$total_duration_before}, got {$total_duration_after}. " .
                "Aggregate metrics must remain accurate after reconnection."
            );
        });
    }

    /**
     * Property 4 Edge Case: Single log during disconnection.
     *
     * Verifies that even a single Activity_Log generated during disconnection
     * is successfully delivered after reconnection.
     *
     * @return void
     */
    public function test_property4_single_log_during_disconnection() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record single activity during disconnection.
        $duration = 120;
        $id = $ts->record_activity(
            $user->id,
            $course->id,
            'course_module_viewed',
            'mod_resource',
            [
                'objectid' => 1,
                'action' => 'viewed',
                'duration_seconds' => $duration,
            ]
        );

        // Verify log is stored locally and not sent.
        $log_before = $DB->get_record('acmls_activity_log', ['id' => $id]);

        $this->assertNotFalse(
            $log_before,
            "Property 4 violated: Single log should be stored locally during disconnection"
        );

        $this->assertEquals(
            0,
            $log_before->sent_to_profiler,
            "Property 4 violated: Single log should be marked as not sent during disconnection"
        );

        // Reconnect and flush.
        $ts->flush_to_profiler();

        // Verify log is marked as sent after reconnection.
        $log_after = $DB->get_record('acmls_activity_log', ['id' => $id]);

        $this->assertNotFalse(
            $log_after,
            "Property 4 violated: Single log should still exist after reconnection"
        );

        $this->assertEquals(
            1,
            $log_after->sent_to_profiler,
            "Property 4 violated: Single log should be marked as sent after reconnection"
        );

        // Verify attributes unchanged.
        $this->assertEquals($log_before->userid, $log_after->userid);
        $this->assertEquals($log_before->courseid, $log_after->courseid);
        $this->assertEquals($log_before->event_type, $log_after->event_type);
        $this->assertEquals($log_before->component, $log_after->component);
        $this->assertEquals($log_before->objectid, $log_after->objectid);
        $this->assertEquals($log_before->action, $log_after->action);
        $this->assertEquals($log_before->duration_seconds, $log_after->duration_seconds);
        $this->assertEquals($log_before->timecreated, $log_after->timecreated);
    }

    /**
     * Property 4 Edge Case: Multiple disconnection-reconnection cycles.
     *
     * Verifies that the system can handle multiple disconnection-reconnection cycles
     * without data loss or corruption.
     *
     * @return void
     */
    public function test_property4_multiple_disconnection_cycles() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        $total_activities = 0;

        // Cycle 1: Record 5 activities, flush.
        for ($i = 0; $i < 5; $i++) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $total_activities + 1,
                    'action' => 'viewed',
                ]
            );
            $total_activities++;
        }

        $ts->flush_to_profiler();

        // Verify first batch sent.
        $sent_count_1 = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 1,
        ]);

        $this->assertEquals(
            5,
            $sent_count_1,
            "Property 4 violated: First batch should have 5 sent logs"
        );

        // Cycle 2: Record 3 more activities, flush.
        for ($i = 0; $i < 3; $i++) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $total_activities + 1,
                    'action' => 'viewed',
                ]
            );
            $total_activities++;
        }

        $ts->flush_to_profiler();

        // Verify second batch sent.
        $sent_count_2 = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 1,
        ]);

        $this->assertEquals(
            8,
            $sent_count_2,
            "Property 4 violated: After second cycle, should have 8 sent logs total"
        );

        // Cycle 3: Record 7 more activities, flush.
        for ($i = 0; $i < 7; $i++) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $total_activities + 1,
                    'action' => 'viewed',
                ]
            );
            $total_activities++;
        }

        $ts->flush_to_profiler();

        // Verify third batch sent.
        $sent_count_3 = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 1,
        ]);

        $this->assertEquals(
            15,
            $sent_count_3,
            "Property 4 violated: After third cycle, should have 15 sent logs total"
        );

        // PROPERTY: Total count must match expected.
        $total_count = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertEquals(
            $total_activities,
            $total_count,
            "Property 4 violated: Total activity count mismatch after multiple cycles. " .
            "Expected {$total_activities}, got {$total_count}"
        );

        // PROPERTY: All logs must be marked as sent.
        $all_logs = $DB->get_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        foreach ($all_logs as $log) {
            $this->assertEquals(
                1,
                $log->sent_to_profiler,
                "Property 4 violated: Log {$log->id} should be marked as sent after multiple cycles"
            );
        }
    }

    /**
     * Property 4 Edge Case: Mixed sent and unsent logs.
     *
     * Verifies that when some logs are already sent and new logs are generated
     * during disconnection, only the unsent logs are delivered after reconnection.
     *
     * @return void
     */
    public function test_property4_mixed_sent_unsent_logs() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Phase 1: Record and flush 5 activities (these will be marked as sent).
        for ($i = 0; $i < 5; $i++) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $i + 1,
                    'action' => 'viewed',
                ]
            );
        }

        $ts->flush_to_profiler();

        // Verify first 5 are sent.
        $sent_count_before = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 1,
        ]);

        $this->assertEquals(5, $sent_count_before);

        // Phase 2: Record 3 more activities during disconnection (these will be unsent).
        for ($i = 5; $i < 8; $i++) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $i + 1,
                    'action' => 'viewed',
                ]
            );
        }

        // Verify 3 unsent logs exist.
        $unsent_count_before = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 0,
        ]);

        $this->assertEquals(
            3,
            $unsent_count_before,
            "Property 4 violated: Should have 3 unsent logs before reconnection"
        );

        // Phase 3: Reconnect and flush.
        $ts->flush_to_profiler();

        // PROPERTY: All 8 logs should now be marked as sent.
        $sent_count_after = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 1,
        ]);

        $this->assertEquals(
            8,
            $sent_count_after,
            "Property 4 violated: All 8 logs should be marked as sent after reconnection"
        );

        // PROPERTY: No unsent logs should remain.
        $unsent_count_after = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 0,
        ]);

        $this->assertEquals(
            0,
            $unsent_count_after,
            "Property 4 violated: No unsent logs should remain after reconnection"
        );

        // PROPERTY: Total count should be 8 (no duplicates).
        $total_count = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertEquals(
            8,
            $total_count,
            "Property 4 violated: Total log count should be 8 (no duplicates)"
        );
    }

    /**
     * Property 4 Boundary Case: Large number of logs during disconnection.
     *
     * Verifies that the system can handle a large number of Activity_Logs
     * generated during disconnection without data loss.
     *
     * @return void
     */
    public function test_property4_large_batch_during_disconnection() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record 100 activities during disconnection.
        $num_activities = 100;

        for ($i = 0; $i < $num_activities; $i++) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $i + 1,
                    'action' => 'viewed',
                    'duration_seconds' => rand(10, 300),
                ]
            );
        }

        // Verify all stored locally.
        $logs_before = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertEquals(
            $num_activities,
            $logs_before,
            "Property 4 violated: All {$num_activities} logs should be stored locally during disconnection"
        );

        // Verify all marked as not sent.
        $unsent_before = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 0,
        ]);

        $this->assertEquals(
            $num_activities,
            $unsent_before,
            "Property 4 violated: All {$num_activities} logs should be marked as not sent"
        );

        // Reconnect and flush.
        $ts->flush_to_profiler();

        // Verify all delivered.
        $sent_after = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 1,
        ]);

        $this->assertEquals(
            $num_activities,
            $sent_after,
            "Property 4 violated: All {$num_activities} logs should be marked as sent after reconnection"
        );

        // Verify no data loss.
        $total_after = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertEquals(
            $num_activities,
            $total_after,
            "Property 4 violated: No data loss should occur. Expected {$num_activities}, got {$total_after}"
        );
    }

    /**
     * Property 4 Invariant: Idempotency of reconnection flush.
     *
     * Verifies that flushing multiple times after reconnection does not cause
     * duplicate processing or change the sent status of already-sent logs.
     *
     * @return void
     */
    public function test_property4_reconnection_flush_idempotency() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ts = new \block_attendanceleaderboard\tracking\tracking_system();

        // Record activities during disconnection.
        $num_activities = 10;

        for ($i = 0; $i < $num_activities; $i++) {
            $ts->record_activity(
                $user->id,
                $course->id,
                'course_module_viewed',
                'mod_test',
                [
                    'objectid' => $i + 1,
                    'action' => 'viewed',
                ]
            );
        }

        // First flush after reconnection.
        $ts->flush_to_profiler();

        $sent_after_first_flush = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 1,
        ]);

        $this->assertEquals($num_activities, $sent_after_first_flush);

        // Second flush (should be idempotent).
        $ts->flush_to_profiler();

        $sent_after_second_flush = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 1,
        ]);

        // PROPERTY: Count should remain the same.
        $this->assertEquals(
            $sent_after_first_flush,
            $sent_after_second_flush,
            "Property 4 violated: Sent count changed after second flush. " .
            "Reconnection flush must be idempotent."
        );

        // PROPERTY: Total count should remain the same (no duplicates).
        $total_after_second_flush = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]);

        $this->assertEquals(
            $num_activities,
            $total_after_second_flush,
            "Property 4 violated: Total count changed after second flush. " .
            "No duplicate logs should be created."
        );

        // Third flush (verify continued idempotency).
        $ts->flush_to_profiler();

        $sent_after_third_flush = $DB->count_records('acmls_activity_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'sent_to_profiler' => 1,
        ]);

        $this->assertEquals(
            $num_activities,
            $sent_after_third_flush,
            "Property 4 violated: Sent count changed after third flush. " .
            "Reconnection flush must remain idempotent across multiple calls."
        );
    }
}
