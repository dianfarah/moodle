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
 * Unit tests for ACMLS EvaluationSystem.
 *
 * Tests cover:
 * 1. calculate_aggregate_metrics() returns correct average for known scores.
 * 2. detect_performance_decline() returns true when decline > 20%.
 * 3. detect_performance_decline() returns false when decline = exactly 20% (boundary: >20% not >=20%).
 * 4. detect_performance_decline() returns false when decline < 20%.
 * 5. detect_performance_decline() returns false when no historical data exists.
 * 6. save_to_learner_record() inserts correct record into DB.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tests;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\evaluation\evaluation_system;

/**
 * Unit test class for EvaluationSystem.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_attendanceleaderboard\evaluation\evaluation_system
 */
class evaluation_system_test extends \advanced_testcase {

    /** @var evaluation_system System under test. */
    private evaluation_system $es;

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

        $this->es     = new evaluation_system();
        $this->user   = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
    }

    // =========================================================================
    // Helper: insert performance records directly into acmls_learner_record
    // =========================================================================

    /**
     * Insert a performance score record into acmls_learner_record.
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

    // =========================================================================
    // Test 1: calculate_aggregate_metrics() — correct average for known scores
    // =========================================================================

    /**
     * Test that calculate_aggregate_metrics() returns the correct average score.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::calculate_aggregate_metrics
     */
    public function test_calculate_aggregate_metrics_returns_correct_average(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Insert 4 known scores: 80, 70, 90, 60 → average = 75.
        $base_time = time() - 400;
        $this->insert_performance_record($userid, $courseid, 80.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 70.0, $base_time + 100);
        $this->insert_performance_record($userid, $courseid, 90.0, $base_time + 200);
        $this->insert_performance_record($userid, $courseid, 60.0, $base_time + 300);

        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);

        $this->assertEquals(75.0, $metrics['average_score'],
            'Average of [80, 70, 90, 60] should be 75.0.');
        $this->assertEquals(4, $metrics['score_count'],
            'Score count should be 4.');
        $this->assertEquals(60.0, $metrics['latest_score'],
            'Latest score should be 60.0 (last inserted).');
    }

    /**
     * Test that calculate_aggregate_metrics() returns correct trend for improving scores.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::calculate_aggregate_metrics
     */
    public function test_calculate_aggregate_metrics_trend_improving(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $base_time = time() - 200;
        $this->insert_performance_record($userid, $courseid, 60.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 80.0, $base_time + 100);

        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);

        $this->assertEquals('improving', $metrics['trend'],
            'Trend should be improving when latest score > previous score.');
    }

    /**
     * Test that calculate_aggregate_metrics() returns correct trend for declining scores.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::calculate_aggregate_metrics
     */
    public function test_calculate_aggregate_metrics_trend_declining(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $base_time = time() - 200;
        $this->insert_performance_record($userid, $courseid, 80.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 60.0, $base_time + 100);

        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);

        $this->assertEquals('declining', $metrics['trend'],
            'Trend should be declining when latest score < previous score.');
    }

    /**
     * Test that calculate_aggregate_metrics() returns insufficient_data when no records exist.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::calculate_aggregate_metrics
     */
    public function test_calculate_aggregate_metrics_no_data(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);

        $this->assertEquals(0, $metrics['score_count']);
        $this->assertEquals(0.0, $metrics['average_score']);
        $this->assertNull($metrics['latest_score']);
        $this->assertEquals('insufficient_data', $metrics['trend']);
    }

    /**
     * Test that calculate_aggregate_metrics() computes class average correctly.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::calculate_aggregate_metrics
     */
    public function test_calculate_aggregate_metrics_class_average(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Learner scores: 80, 60 → average = 70.
        $base_time = time() - 200;
        $this->insert_performance_record($userid, $courseid, 80.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 60.0, $base_time + 100);

        // Another learner in the same course with score 90.
        $other_user = $this->getDataGenerator()->create_user();
        $this->insert_performance_record((int) $other_user->id, $courseid, 90.0, $base_time + 50);

        $metrics = $this->es->calculate_aggregate_metrics($userid, $courseid);

        // Class scores: [80, 60, 90] → class average = 76.67.
        $this->assertEqualsWithDelta(76.67, $metrics['class_average'], 0.1,
            'Class average should be approximately 76.67.');

        // Learner average (70) vs class average (76.67) → vs_class ≈ -6.67.
        $this->assertEqualsWithDelta(-6.67, $metrics['vs_class'], 0.1,
            'vs_class should be approximately -6.67.');
    }

    // =========================================================================
    // Test 2: detect_performance_decline() — true when decline > 20%
    // =========================================================================

    /**
     * Test that detect_performance_decline() returns true when decline is > 20%.
     *
     * Historical average = 80, current = 60 → decline = 25% > 20%.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_detect_performance_decline_returns_true_when_decline_exceeds_threshold(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Historical scores: [80, 80, 80, 80, 80] → historical average = 80.
        $base_time = time() - 600;
        for ($i = 0; $i < 5; $i++) {
            $this->insert_performance_record($userid, $courseid, 80.0, $base_time + ($i * 100));
        }

        // Current score: 60 → decline = (80 - 60) / 80 * 100 = 25% > 20%.
        $this->insert_performance_record($userid, $courseid, 60.0, $base_time + 500);

        $result = $this->es->detect_performance_decline($userid, $courseid);

        $this->assertTrue($result,
            'Should return true when decline is 25% (> 20% threshold).');
    }

    /**
     * Test with a more extreme decline (50%).
     *
     * Historical average = 100, current = 50 → decline = 50% > 20%.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_detect_performance_decline_returns_true_for_large_decline(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $base_time = time() - 300;
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time + 100);
        $this->insert_performance_record($userid, $courseid, 50.0, $base_time + 200);

        $result = $this->es->detect_performance_decline($userid, $courseid);

        $this->assertTrue($result,
            'Should return true when decline is 50% (> 20% threshold).');
    }

    // =========================================================================
    // Test 3: detect_performance_decline() — false when decline = exactly 20%
    // =========================================================================

    /**
     * Test that detect_performance_decline() returns false when decline is exactly 20%.
     *
     * The threshold is strictly > 20%, so exactly 20% should NOT trigger an alert.
     *
     * Historical average = 100, current = 80 → decline = 20.0% (not > 20%).
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_detect_performance_decline_returns_false_when_decline_is_exactly_threshold(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Historical scores: [100, 100, 100] → historical average = 100.
        $base_time = time() - 400;
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time + 100);
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time + 200);

        // Current score: 80 → decline = (100 - 80) / 100 * 100 = 20.0% (not > 20%).
        $this->insert_performance_record($userid, $courseid, 80.0, $base_time + 300);

        $result = $this->es->detect_performance_decline($userid, $courseid);

        $this->assertFalse($result,
            'Should return false when decline is exactly 20% (threshold is strictly > 20%).');
    }

    // =========================================================================
    // Test 4: detect_performance_decline() — false when decline < 20%
    // =========================================================================

    /**
     * Test that detect_performance_decline() returns false when decline is < 20%.
     *
     * Historical average = 100, current = 85 → decline = 15% < 20%.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_detect_performance_decline_returns_false_when_decline_below_threshold(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $base_time = time() - 300;
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time + 100);

        // Current score: 85 → decline = 15% < 20%.
        $this->insert_performance_record($userid, $courseid, 85.0, $base_time + 200);

        $result = $this->es->detect_performance_decline($userid, $courseid);

        $this->assertFalse($result,
            'Should return false when decline is 15% (< 20% threshold).');
    }

    /**
     * Test that detect_performance_decline() returns false when score is improving.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_detect_performance_decline_returns_false_when_score_improving(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $base_time = time() - 300;
        $this->insert_performance_record($userid, $courseid, 60.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 70.0, $base_time + 100);
        $this->insert_performance_record($userid, $courseid, 90.0, $base_time + 200);

        $result = $this->es->detect_performance_decline($userid, $courseid);

        $this->assertFalse($result,
            'Should return false when score is improving.');
    }

    // =========================================================================
    // Test 5: detect_performance_decline() — false when no historical data
    // =========================================================================

    /**
     * Test that detect_performance_decline() returns false when no records exist.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_detect_performance_decline_returns_false_when_no_data(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $result = $this->es->detect_performance_decline($userid, $courseid);

        $this->assertFalse($result,
            'Should return false when there are no performance records.');
    }

    /**
     * Test that detect_performance_decline() returns false when only one score exists.
     *
     * With only one score there is no historical data to compare against.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_detect_performance_decline_returns_false_with_single_score(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $this->insert_performance_record($userid, $courseid, 50.0);

        $result = $this->es->detect_performance_decline($userid, $courseid);

        $this->assertFalse($result,
            'Should return false when only one score exists (no historical data).');
    }

    // =========================================================================
    // Test 6: save_to_learner_record() — inserts correct record into DB
    // =========================================================================

    /**
     * Test that save_to_learner_record() inserts a record with correct fields.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::save_to_learner_record
     */
    public function test_save_to_learner_record_inserts_correct_record(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $data = [
            'score'      => 85.5,
            'cmid'       => 42,
            'event_type' => 'grade_item_updated',
            'source'     => 'grade_event',
        ];

        $before = time();
        $id = $this->es->save_to_learner_record($userid, $courseid, $data);
        $after = time();

        $this->assertGreaterThan(0, $id, 'save_to_learner_record() should return a positive ID.');

        $record = $DB->get_record('acmls_learner_record', ['id' => $id]);
        $this->assertNotFalse($record, 'Record should exist in acmls_learner_record.');

        $this->assertEquals($userid, (int) $record->userid);
        $this->assertEquals($courseid, (int) $record->courseid);
        $this->assertEquals('performance', $record->record_type,
            'Default record_type should be "performance".');
        $this->assertEquals('evaluation', $record->source_component,
            'source_component should be "evaluation".');

        $payload = json_decode($record->data_payload, true);
        $this->assertEquals(85.5, $payload['score']);
        $this->assertEquals(42, $payload['cmid']);
        $this->assertEquals('grade_event', $payload['source']);

        $this->assertGreaterThanOrEqual($before, (int) $record->timecreated);
        $this->assertLessThanOrEqual($after, (int) $record->timecreated);
    }

    /**
     * Test that save_to_learner_record() supports custom record_type.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::save_to_learner_record
     */
    public function test_save_to_learner_record_supports_custom_type(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $id = $this->es->save_to_learner_record(
            $userid,
            $courseid,
            ['alert_type' => 'performance_decline'],
            'performance_alert'
        );

        $record = $DB->get_record('acmls_learner_record', ['id' => $id]);
        $this->assertEquals('performance_alert', $record->record_type,
            'Custom record_type should be stored correctly.');
    }

    /**
     * Test that save_to_learner_record() stores data_payload as valid JSON.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::save_to_learner_record
     */
    public function test_save_to_learner_record_stores_valid_json_payload(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $data = [
            'score'    => 72.3,
            'metadata' => ['key' => 'value', 'nested' => [1, 2, 3]],
        ];

        $id = $this->es->save_to_learner_record($userid, $courseid, $data);

        $record = $DB->get_record('acmls_learner_record', ['id' => $id]);
        $decoded = json_decode($record->data_payload, true);

        $this->assertIsArray($decoded, 'data_payload should decode to an array.');
        $this->assertEquals(72.3, $decoded['score']);
        $this->assertEquals('value', $decoded['metadata']['key']);
        $this->assertEquals([1, 2, 3], $decoded['metadata']['nested']);
    }

    // =========================================================================
    // Additional edge-case tests
    // =========================================================================

    /**
     * Test that send_alert_to_coach() stores a performance_alert record in DB.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::send_alert_to_coach
     */
    public function test_send_alert_to_coach_stores_alert_record(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $metrics = [
            'average_score' => 60.0,
            'latest_score'  => 45.0,
            'trend'         => 'declining',
        ];

        // When Coach class is not available, send_alert_to_coach() calls debugging().
        // Call the method first, then assert the debugging message was triggered.
        $this->es->send_alert_to_coach($userid, $courseid, $metrics);
        $this->assertDebuggingCalled();

        $count = $DB->count_records('acmls_learner_record', [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'record_type' => 'performance_alert',
        ]);

        $this->assertEquals(1, $count,
            'send_alert_to_coach() should insert one performance_alert record.');
    }

    /**
     * Test that detect_performance_decline() uses only the last N=5 historical scores.
     *
     * If there are more than 5 historical scores, only the most recent 5 should be
     * used as the historical window.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_detect_performance_decline_uses_last_n_historical_scores(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Insert 3 very old low scores that should NOT be in the historical window.
        $old_time = time() - 1000;
        $this->insert_performance_record($userid, $courseid, 10.0, $old_time);
        $this->insert_performance_record($userid, $courseid, 10.0, $old_time + 10);
        $this->insert_performance_record($userid, $courseid, 10.0, $old_time + 20);

        // Insert 5 recent high scores that form the historical window.
        $recent_time = time() - 600;
        for ($i = 0; $i < 5; $i++) {
            $this->insert_performance_record($userid, $courseid, 90.0, $recent_time + ($i * 100));
        }

        // Current score: 72 → decline from 90 = 20% (not > 20%, so should be false).
        $this->insert_performance_record($userid, $courseid, 72.0, $recent_time + 500);

        $result = $this->es->detect_performance_decline($userid, $courseid);

        $this->assertFalse($result,
            'With historical window of [90,90,90,90,90] and current=72, decline=20% should be false (not > 20%).');
    }

    /**
     * Test that the decline threshold can be configured via plugin settings.
     *
     * @covers \block_attendanceleaderboard\evaluation\evaluation_system::detect_performance_decline
     */
    public function test_decline_threshold_is_configurable(): void {
        // Set a custom threshold of 10%.
        set_config('performance_decline_threshold', '10', 'block_attendanceleaderboard');

        $es_custom = new evaluation_system();

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Historical average = 100, current = 88 → decline = 12% > 10%.
        $base_time = time() - 300;
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time);
        $this->insert_performance_record($userid, $courseid, 100.0, $base_time + 100);
        $this->insert_performance_record($userid, $courseid, 88.0, $base_time + 200);

        $result = $es_custom->detect_performance_decline($userid, $courseid);

        $this->assertTrue($result,
            'With threshold=10%, a 12% decline should return true.');
    }
}
