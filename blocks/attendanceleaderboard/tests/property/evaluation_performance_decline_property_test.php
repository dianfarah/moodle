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
 * Property-based test for Property 12: Ketepatan Deteksi Penurunan Performa.
 *
 * Verifies that EvaluationSystem::detect_performance_decline() correctly
 * identifies performance decline with no false positives and no false negatives.
 *
 * **Property 12 — Ketepatan Deteksi Penurunan Performa:**
 * Alert must be sent if and only if decline exceeds 20% — no false positives
 * or false negatives.
 *
 * Formally:
 *   - IF (previous_average - current_score) / previous_average > 0.20
 *     → detect_performance_decline() MUST return true (alert MUST be sent)
 *   - IF (previous_average - current_score) / previous_average ≤ 0.20
 *     → detect_performance_decline() MUST return false (alert MUST NOT be sent)
 *   - Boundary: exactly 20% decline → NO alert (only strictly greater than 20%)
 *
 * **Validates: Requirements 6.4**
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */

namespace block_attendanceleaderboard;

use Eris\TestTrait;
use Eris\Generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/attendanceleaderboard/vendor/autoload.php');

/**
 * Property 12: Ketepatan Deteksi Penurunan Performa.
 *
 * For any combination of previous_average and current_score, the
 * detect_performance_decline() method must:
 *
 *   - Return TRUE  (and trigger alert) when decline > 20%  — no false negatives
 *   - Return FALSE (no alert)          when decline ≤ 20%  — no false positives
 *   - Return FALSE at the exact 20% boundary               — boundary correctness
 *   - Return FALSE when score improves or stays the same   — improvement case
 *   - Return the same result on repeated calls             — determinism/consistency
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class evaluation_performance_decline_property_test extends \advanced_testcase {
    use TestTrait;

    /** @var \block_attendanceleaderboard\evaluation\evaluation_system System under test. */
    private \block_attendanceleaderboard\evaluation\evaluation_system $es;

    /**
     * Override Eris annotation lookup to be compatible with PHPUnit 10+/11+.
     *
     * PHPUnit 10+ removed PHPUnit\Util\Test::parseTestMethodAnnotations() and
     * the getAnnotations() method from TestCase. This override returns an empty
     * array so Eris uses its defaults (100 iterations, rand method).
     *
     * @return array
     */
    public function getTestCaseAnnotations(): array {
        return [];
    }

    /**
     * Setup before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->es = new \block_attendanceleaderboard\evaluation\evaluation_system();
    }

    // =========================================================================
    // Helper: insert performance score records into acmls_learner_record
    // =========================================================================

    /**
     * Insert a performance score record into acmls_learner_record.
     *
     * @param  int   $userid
     * @param  int   $courseid
     * @param  float $score
     * @param  int   $timecreated  Unix timestamp (0 = use current time).
     * @return int                 Inserted record ID.
     */
    private function insert_score(int $userid, int $courseid, float $score, int $timecreated = 0): int {
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
     * Insert a sequence of historical scores followed by a current score.
     *
     * The historical scores are inserted with ascending timestamps so that
     * detect_performance_decline() treats them as the history window, and the
     * current score is inserted last (most recent).
     *
     * @param  int   $userid
     * @param  int   $courseid
     * @param  float $historical_score  Single value used for all history records.
     * @param  float $current_score     The most recent score.
     * @param  int   $history_count     Number of historical records to insert (default 3).
     * @return void
     */
    private function insert_history_and_current(
        int $userid,
        int $courseid,
        float $historical_score,
        float $current_score,
        int $history_count = 3
    ): void {
        $base_time = time() - (($history_count + 1) * 10);

        for ($i = 0; $i < $history_count; $i++) {
            $this->insert_score($userid, $courseid, $historical_score, $base_time + ($i * 10));
        }

        // Current score is the last (most recent) record.
        $this->insert_score($userid, $courseid, $current_score, $base_time + ($history_count * 10));
    }

    // =========================================================================
    // PROPERTY 12 (No False Negatives): Alert sent when decline > 20%
    // =========================================================================

    /**
     * Property 12 (No False Negatives): For any previous_average in [1, 100]
     * and any decline_percentage strictly greater than 20%, detect_performance_decline()
     * MUST return true — no false negatives allowed.
     *
     * Test Strategy:
     * - Generate previous_average in [1, 100] (integers scaled to floats)
     * - Generate decline_percentage in (20, 100] as integer percent (21..100)
     * - Compute current_score = previous_average * (1 - decline_percentage/100)
     * - Insert historical scores equal to previous_average, then current_score
     * - Assert detect_performance_decline() returns true
     *
     * **Validates: Requirements 6.4**
     *
     * @return void
     */
    public function test_property12_no_false_negatives_alert_sent_when_decline_exceeds_20_percent(): void {
        $this->forAll(
            Generator\choose(1, 100),   // previous_average as integer in [1, 100]
            Generator\choose(21, 100)   // decline_percentage as integer percent in (20, 100]
        )->then(function (int $prev_avg_int, int $decline_pct_int) {
            $previous_average = (float) $prev_avg_int;
            $decline_fraction = $decline_pct_int / 100.0;  // e.g. 21 -> 0.21

            // current_score is previous_average reduced by decline_fraction.
            $current_score = $previous_average * (1.0 - $decline_fraction);
            // Clamp to [0, 100] to stay in valid range.
            $current_score = max(0.0, min(100.0, $current_score));

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->insert_history_and_current(
                (int) $user->id,
                (int) $course->id,
                $previous_average,
                $current_score
            );

            $result = $this->es->detect_performance_decline((int) $user->id, (int) $course->id);

            $this->assertTrue(
                $result,
                "Property 12 violated (No False Negatives): " .
                "previous_average={$previous_average}, " .
                "decline={$decline_pct_int}% (>{$this->get_threshold()}%), " .
                "current_score={$current_score}. " .
                "detect_performance_decline() returned false but MUST return true " .
                "when decline exceeds {$this->get_threshold()}%. " .
                "Req 6.4: alert must be sent for significant performance decline."
            );
        });
    }

    // =========================================================================
    // PROPERTY 12 (No False Positives): No alert when decline ≤ 20%
    // =========================================================================

    /**
     * Property 12 (No False Positives): For any previous_average in [1, 100]
     * and any decline_percentage at most 20%, detect_performance_decline()
     * MUST return false — no false positives allowed.
     *
     * Test Strategy:
     * - Generate previous_average in [1, 100]
     * - Generate decline_percentage in [0, 20] as integer percent
     * - Compute current_score = previous_average * (1 - decline_percentage/100)
     * - Insert historical scores equal to previous_average, then current_score
     * - Assert detect_performance_decline() returns false
     *
     * **Validates: Requirements 6.4**
     *
     * @return void
     */
    public function test_property12_no_false_positives_no_alert_when_decline_at_most_20_percent(): void {
        $this->forAll(
            Generator\choose(1, 100),   // previous_average as integer in [1, 100]
            Generator\choose(0, 20)     // decline_percentage as integer percent in [0, 20]
        )->then(function (int $prev_avg_int, int $decline_pct_int) {
            $previous_average = (float) $prev_avg_int;
            $decline_fraction = $decline_pct_int / 100.0;  // e.g. 20 -> 0.20

            // current_score is previous_average reduced by decline_fraction.
            $current_score = $previous_average * (1.0 - $decline_fraction);
            // Clamp to [0, 100].
            $current_score = max(0.0, min(100.0, $current_score));

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->insert_history_and_current(
                (int) $user->id,
                (int) $course->id,
                $previous_average,
                $current_score
            );

            $result = $this->es->detect_performance_decline((int) $user->id, (int) $course->id);

            $this->assertFalse(
                $result,
                "Property 12 violated (No False Positives): " .
                "previous_average={$previous_average}, " .
                "decline={$decline_pct_int}% (≤{$this->get_threshold()}%), " .
                "current_score={$current_score}. " .
                "detect_performance_decline() returned true but MUST return false " .
                "when decline does not exceed {$this->get_threshold()}%. " .
                "Req 6.4: alert must NOT be sent for non-significant decline."
            );
        });
    }

    // =========================================================================
    // PROPERTY 12 (Boundary): Exactly 20% decline → no alert
    // =========================================================================

    /**
     * Property 12 (Boundary Correctness): At the exact 20% boundary,
     * detect_performance_decline() MUST return false.
     *
     * The threshold is strictly greater than 20%, so exactly 20% must NOT
     * trigger an alert.
     *
     * Test Strategy:
     * - Generate previous_average in [1, 100]
     * - Compute current_score = previous_average * 0.80 (exactly 20% decline)
     * - Insert historical scores equal to previous_average, then current_score
     * - Assert detect_performance_decline() returns false
     *
     * **Validates: Requirements 6.4**
     *
     * @return void
     */
    public function test_property12_boundary_exactly_20_percent_decline_no_alert(): void {
        $this->forAll(
            Generator\choose(1, 100)    // previous_average as integer in [1, 100]
        )->then(function (int $prev_avg_int) {
            $previous_average = (float) $prev_avg_int;

            // Exactly 20% decline: current = previous * 0.80.
            $current_score = $previous_average * 0.80;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->insert_history_and_current(
                (int) $user->id,
                (int) $course->id,
                $previous_average,
                $current_score
            );

            $result = $this->es->detect_performance_decline((int) $user->id, (int) $course->id);

            $this->assertFalse(
                $result,
                "Property 12 violated (Boundary): " .
                "previous_average={$previous_average}, " .
                "current_score={$current_score} (exactly 20% decline). " .
                "detect_performance_decline() returned true but MUST return false " .
                "at the exact 20% boundary (threshold is strictly greater than 20%). " .
                "Req 6.4: boundary case must not trigger alert."
            );
        });
    }

    // =========================================================================
    // PROPERTY 12 (Score Improvement): No alert when score improves
    // =========================================================================

    /**
     * Property 12 (Score Improvement): When the current score is greater than
     * or equal to the previous average, detect_performance_decline() MUST
     * return false — improvement never triggers an alert.
     *
     * Test Strategy:
     * - Generate previous_average in [1, 99]
     * - Generate current_score in [previous_average, 100]
     * - Insert historical scores equal to previous_average, then current_score
     * - Assert detect_performance_decline() returns false
     *
     * **Validates: Requirements 6.4**
     *
     * @return void
     */
    public function test_property12_score_improvement_never_triggers_alert(): void {
        $this->forAll(
            Generator\choose(1, 99),    // previous_average as integer in [1, 99]
            Generator\choose(0, 100)    // raw current_score integer (will be adjusted below)
        )->then(function (int $prev_avg_int, int $current_raw) {
            $previous_average = (float) $prev_avg_int;

            // Ensure current_score >= previous_average (improvement or stable).
            // Map current_raw [0, 100] to [previous_average, 100].
            $range = 100 - $prev_avg_int;
            $current_score = $range > 0
                ? $previous_average + ($current_raw / 100.0) * $range
                : $previous_average;
            $current_score = min(100.0, $current_score);

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->insert_history_and_current(
                (int) $user->id,
                (int) $course->id,
                $previous_average,
                $current_score
            );

            $result = $this->es->detect_performance_decline((int) $user->id, (int) $course->id);

            $this->assertFalse(
                $result,
                "Property 12 violated (Score Improvement): " .
                "previous_average={$previous_average}, " .
                "current_score={$current_score} (improvement or stable). " .
                "detect_performance_decline() returned true but MUST return false " .
                "when score improves or stays the same. " .
                "Req 6.4: no alert for non-declining performance."
            );
        });
    }

    // =========================================================================
    // PROPERTY 12 (Determinism): Same inputs always produce same output
    // =========================================================================

    /**
     * Property 12 (Determinism/Consistency): Calling detect_performance_decline()
     * twice with the same inputs must always return the same result.
     *
     * This verifies that the detection logic is pure and deterministic —
     * no side effects or randomness should affect the outcome.
     *
     * Test Strategy:
     * - Generate previous_average and current_score in [0, 100]
     * - Insert historical scores and current score
     * - Call detect_performance_decline() twice
     * - Assert both calls return the same result
     *
     * **Validates: Requirements 6.4**
     *
     * @return void
     */
    public function test_property12_determinism_same_inputs_always_same_output(): void {
        $this->forAll(
            Generator\choose(1, 100),   // previous_average as integer in [1, 100]
            Generator\choose(0, 100)    // current_score as integer in [0, 100]
        )->then(function (int $prev_avg_int, int $current_int) {
            $previous_average = (float) $prev_avg_int;
            $current_score    = (float) $current_int;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->insert_history_and_current(
                (int) $user->id,
                (int) $course->id,
                $previous_average,
                $current_score
            );

            // Call detect_performance_decline() twice with the same data.
            $result_first  = $this->es->detect_performance_decline((int) $user->id, (int) $course->id);
            $result_second = $this->es->detect_performance_decline((int) $user->id, (int) $course->id);

            $this->assertSame(
                $result_first,
                $result_second,
                "Property 12 violated (Determinism): " .
                "previous_average={$previous_average}, current_score={$current_score}. " .
                "First call returned " . ($result_first ? 'true' : 'false') . " but " .
                "second call returned " . ($result_second ? 'true' : 'false') . ". " .
                "detect_performance_decline() must be deterministic — same inputs must " .
                "always produce the same output. Req 6.4."
            );
        });
    }

    // =========================================================================
    // PROPERTY 12 (Alert Integration): send_alert_to_coach called iff decline > 20%
    // =========================================================================

    /**
     * Property 12 (Alert Integration — No False Negatives): When decline > 20%,
     * send_alert_to_coach() MUST be called.
     *
     * Uses a partial mock of EvaluationSystem to verify that send_alert_to_coach()
     * is invoked when detect_performance_decline() returns true.
     *
     * **Validates: Requirements 6.4**
     *
     * @return void
     */
    public function test_property12_alert_integration_send_alert_called_when_decline_exceeds_20_percent(): void {
        $this->forAll(
            Generator\choose(10, 100),  // previous_average in [10, 100]
            Generator\choose(21, 100)   // decline_percentage in (20, 100]
        )->then(function (int $prev_avg_int, int $decline_pct_int) {
            $previous_average = (float) $prev_avg_int;
            $decline_fraction = $decline_pct_int / 100.0;
            $current_score    = max(0.0, $previous_average * (1.0 - $decline_fraction));

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->insert_history_and_current(
                (int) $user->id,
                (int) $course->id,
                $previous_average,
                $current_score
            );

            // Create a partial mock that spies on send_alert_to_coach().
            $mock_es = $this->getMockBuilder(\block_attendanceleaderboard\evaluation\evaluation_system::class)
                ->onlyMethods(['send_alert_to_coach'])
                ->getMock();

            $mock_es->expects($this->once())
                ->method('send_alert_to_coach')
                ->with(
                    (int) $user->id,
                    (int) $course->id,
                    $this->isType('array')
                );

            // Simulate the alert dispatch logic: detect decline, then send alert.
            if ($mock_es->detect_performance_decline((int) $user->id, (int) $course->id)) {
                $metrics = $mock_es->calculate_aggregate_metrics((int) $user->id, (int) $course->id);
                $mock_es->send_alert_to_coach((int) $user->id, (int) $course->id, $metrics);
            } else {
                // Force the expectation to fail with a clear message.
                $this->fail(
                    "Property 12 violated (Alert Integration — No False Negatives): " .
                    "previous_average={$previous_average}, decline={$decline_pct_int}%, " .
                    "current_score={$current_score}. " .
                    "detect_performance_decline() returned false but MUST return true, " .
                    "so send_alert_to_coach() was never called. Req 6.4."
                );
            }
        });
    }

    /**
     * Property 12 (Alert Integration — No False Positives): When decline ≤ 20%,
     * send_alert_to_coach() MUST NOT be called.
     *
     * Uses a partial mock of EvaluationSystem to verify that send_alert_to_coach()
     * is NOT invoked when detect_performance_decline() returns false.
     *
     * **Validates: Requirements 6.4**
     *
     * @return void
     */
    public function test_property12_alert_integration_no_alert_when_decline_at_most_20_percent(): void {
        $this->forAll(
            Generator\choose(10, 100),  // previous_average in [10, 100]
            Generator\choose(0, 20)     // decline_percentage in [0, 20]
        )->then(function (int $prev_avg_int, int $decline_pct_int) {
            $previous_average = (float) $prev_avg_int;
            $decline_fraction = $decline_pct_int / 100.0;
            $current_score    = max(0.0, $previous_average * (1.0 - $decline_fraction));

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->insert_history_and_current(
                (int) $user->id,
                (int) $course->id,
                $previous_average,
                $current_score
            );

            // Create a partial mock that verifies send_alert_to_coach() is NEVER called.
            $mock_es = $this->getMockBuilder(\block_attendanceleaderboard\evaluation\evaluation_system::class)
                ->onlyMethods(['send_alert_to_coach'])
                ->getMock();

            $mock_es->expects($this->never())
                ->method('send_alert_to_coach');

            // Simulate the alert dispatch logic.
            if ($mock_es->detect_performance_decline((int) $user->id, (int) $course->id)) {
                // If detect returns true here, it's a false positive — call send_alert
                // so the mock's "never" expectation fails with a clear PHPUnit message.
                $metrics = $mock_es->calculate_aggregate_metrics((int) $user->id, (int) $course->id);
                $mock_es->send_alert_to_coach((int) $user->id, (int) $course->id, $metrics);
            }
            // If detect returns false, send_alert_to_coach is not called — correct behaviour.
        });
    }

    // =========================================================================
    // Private helper
    // =========================================================================

    /**
     * Return the configured decline threshold (default 20.0).
     *
     * Used in assertion messages to make them self-documenting.
     *
     * @return float
     */
    private function get_threshold(): float {
        $configured = get_config('block_attendanceleaderboard', 'performance_decline_threshold');
        return ($configured !== false && is_numeric($configured)) ? (float) $configured : 20.0;
    }
}
