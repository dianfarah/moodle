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
 * Property-based test for Property 11: Akurasi Kalkulasi Metrik Performa Agregat.
 *
 * Verifies that EvaluationSystem::calculate_aggregate_metrics() produces
 * mathematically accurate results for any arbitrary set of scores.
 *
 * **Validates: Requirements 6.2**
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
 * Property 11: Akurasi Kalkulasi Metrik Performa Agregat.
 *
 * For any set of scores, the aggregate metrics computed by
 * EvaluationSystem::calculate_aggregate_metrics() must be mathematically
 * accurate. Specifically:
 *
 *   - average_score  = sum(scores) / count(scores)  (arithmetic mean)
 *   - latest_score   = last element in chronological order
 *   - score_count    = number of score records
 *   - result is always in [0, 100] for valid inputs
 *   - empty set returns 0 / null (no error)
 *   - single score: aggregate equals that score
 *   - commutativity: insertion order does not affect average
 *   - monotonicity: adding a higher score increases (or maintains) the average;
 *                   adding a lower score decreases (or maintains) it
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class evaluation_aggregate_metrics_property_test extends \advanced_testcase {
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
    // Helper: insert a performance record directly into acmls_learner_record
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

    // =========================================================================
    // PROPERTY 11 (Core): Mathematical accuracy of average_score
    // =========================================================================

    /**
     * Property 11 (Accuracy): For any non-empty array of scores in [0, 100],
     * the average_score returned by calculate_aggregate_metrics() must equal
     * the arithmetic mean of those scores (rounded to 2 decimal places).
     *
     * **Validates: Requirements 6.2**
     *
     * Test Strategy:
     * - Generate a random list of 1-20 scores, each in [0, 100]
     * - Insert them into acmls_learner_record in chronological order
     * - Call calculate_aggregate_metrics()
     * - Verify average_score == round(sum / count, 2)
     * - Verify score_count == count of inserted scores
     * - Verify latest_score == last inserted score
     *
     * @return void
     */
    public function test_property11_average_score_is_arithmetic_mean(): void {
        $this->forAll(
            // Generate a non-empty sequence of integers in [0, 10000] (divide by 100 for [0, 100])
            Generator\suchThat(
                function ($arr) { return count($arr) >= 1 && count($arr) <= 20; },
                Generator\seq(Generator\choose(0, 10000))
            )
        )->then(function (array $raw_ints) {
            // Convert raw integers to float scores in [0, 100].
            $scores = array_map(fn($v) => $v / 100.0, $raw_ints);

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $base_time = time() - (count($scores) * 10);
            foreach ($scores as $i => $score) {
                $this->insert_score((int)$user->id, (int)$course->id, $score, $base_time + ($i * 10));
            }

            $metrics = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);

            // INVARIANT: score_count must equal the number of inserted scores.
            $this->assertEquals(
                count($scores),
                $metrics['score_count'],
                "Property 11 violated: score_count={$metrics['score_count']} does not match " .
                "the number of inserted scores=" . count($scores) . ". " .
                "Scores: [" . implode(', ', $scores) . "]"
            );

            // INVARIANT: average_score must equal the arithmetic mean.
            $expected_average = round(array_sum($scores) / count($scores), 2);
            $this->assertEqualsWithDelta(
                $expected_average,
                $metrics['average_score'],
                0.01,
                "Property 11 violated: average_score={$metrics['average_score']} does not equal " .
                "arithmetic mean={$expected_average}. " .
                "Scores: [" . implode(', ', $scores) . "]. " .
                "Aggregate performance metrics must be mathematically accurate (Req 6.2)."
            );

            // INVARIANT: latest_score must equal the last score in chronological order.
            $expected_latest = round(end($scores), 2);
            $this->assertEqualsWithDelta(
                $expected_latest,
                $metrics['latest_score'],
                0.01,
                "Property 11 violated: latest_score={$metrics['latest_score']} does not equal " .
                "the last inserted score={$expected_latest}. " .
                "Scores: [" . implode(', ', $scores) . "]"
            );
        });
    }

    // =========================================================================
    // PROPERTY 11 (Boundary): Result is always in [0, 100] for valid inputs
    // =========================================================================

    /**
     * Property 11 (Boundary Correctness): For any set of scores in [0, 100],
     * the average_score returned must also be in [0, 100].
     *
     * **Validates: Requirements 6.2**
     *
     * @return void
     */
    public function test_property11_average_score_always_in_valid_range(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 1 && count($arr) <= 15; },
                Generator\seq(Generator\choose(0, 10000))
            )
        )->then(function (array $raw_ints) {
            $scores = array_map(fn($v) => $v / 100.0, $raw_ints);

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $base_time = time() - (count($scores) * 10);
            foreach ($scores as $i => $score) {
                $this->insert_score((int)$user->id, (int)$course->id, $score, $base_time + ($i * 10));
            }

            $metrics = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);

            $this->assertGreaterThanOrEqual(
                0.0,
                $metrics['average_score'],
                "Property 11 violated: average_score={$metrics['average_score']} is below 0. " .
                "Result must always be in [0, 100] for valid inputs."
            );

            $this->assertLessThanOrEqual(
                100.0,
                $metrics['average_score'],
                "Property 11 violated: average_score={$metrics['average_score']} exceeds 100. " .
                "Result must always be in [0, 100] for valid inputs."
            );

            // latest_score must also be in [0, 100].
            $this->assertGreaterThanOrEqual(
                0.0,
                $metrics['latest_score'],
                "Property 11 violated: latest_score={$metrics['latest_score']} is below 0."
            );

            $this->assertLessThanOrEqual(
                100.0,
                $metrics['latest_score'],
                "Property 11 violated: latest_score={$metrics['latest_score']} exceeds 100."
            );
        });
    }

    // =========================================================================
    // PROPERTY 11 (Empty Set): Empty score array returns 0 / null (no error)
    // =========================================================================

    /**
     * Property 11 (Empty Set Handling): When no performance records exist,
     * calculate_aggregate_metrics() must return safe zero/null defaults
     * without throwing an error.
     *
     * **Validates: Requirements 6.2**
     *
     * @return void
     */
    public function test_property11_empty_score_set_returns_safe_defaults(): void {
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // No records inserted -- empty set.
        $metrics = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);

        $this->assertEquals(
            0,
            $metrics['score_count'],
            "Property 11 violated: score_count should be 0 for empty set."
        );

        $this->assertEquals(
            0.0,
            $metrics['average_score'],
            "Property 11 violated: average_score should be 0.0 for empty set."
        );

        $this->assertNull(
            $metrics['latest_score'],
            "Property 11 violated: latest_score should be null for empty set."
        );

        $this->assertEquals(
            'insufficient_data',
            $metrics['trend'],
            "Property 11 violated: trend should be 'insufficient_data' for empty set."
        );
    }

    // =========================================================================
    // PROPERTY 11 (Single Score): Aggregate of a single score equals that score
    // =========================================================================

    /**
     * Property 11 (Single Score): When exactly one score exists, the average
     * must equal that score and the latest_score must also equal that score.
     *
     * **Validates: Requirements 6.2**
     *
     * @return void
     */
    public function test_property11_single_score_aggregate_equals_score(): void {
        $this->forAll(
            Generator\choose(0, 10000)  // raw integer -> score in [0, 100]
        )->then(function (int $raw_int) {
            $score = $raw_int / 100.0;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->insert_score((int)$user->id, (int)$course->id, $score);

            $metrics = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);

            $this->assertEquals(
                1,
                $metrics['score_count'],
                "Property 11 violated: score_count should be 1 for single score."
            );

            $this->assertEqualsWithDelta(
                round($score, 2),
                $metrics['average_score'],
                0.01,
                "Property 11 violated: For a single score={$score}, " .
                "average_score={$metrics['average_score']} must equal that score. " .
                "Aggregate of a single score must equal the score itself."
            );

            $this->assertEqualsWithDelta(
                round($score, 2),
                $metrics['latest_score'],
                0.01,
                "Property 11 violated: For a single score={$score}, " .
                "latest_score={$metrics['latest_score']} must equal that score."
            );
        });
    }

    // =========================================================================
    // PROPERTY 11 (Commutativity): Order of scores does not affect average
    // =========================================================================

    /**
     * Property 11 (Commutativity): The arithmetic mean is commutative --
     * inserting scores in any order must produce the same average_score.
     *
     * **Validates: Requirements 6.2**
     *
     * Test Strategy:
     * - Generate a list of 2-10 scores
     * - Insert them in original order for user A
     * - Insert them in reversed order for user B (same course)
     * - Verify both users have the same average_score
     *
     * @return void
     */
    public function test_property11_average_is_commutative_order_independent(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 2 && count($arr) <= 10; },
                Generator\seq(Generator\choose(0, 10000))
            )
        )->then(function (array $raw_ints) {
            $scores = array_map(fn($v) => $v / 100.0, $raw_ints);

            $course = $this->getDataGenerator()->create_course();
            $user_a = $this->getDataGenerator()->create_user();
            $user_b = $this->getDataGenerator()->create_user();

            $base_time = time() - (count($scores) * 10);

            // Insert scores in original order for user A.
            foreach ($scores as $i => $score) {
                $this->insert_score((int)$user_a->id, (int)$course->id, $score, $base_time + ($i * 10));
            }

            // Insert scores in reversed order for user B.
            $reversed = array_reverse($scores);
            foreach ($reversed as $i => $score) {
                $this->insert_score((int)$user_b->id, (int)$course->id, $score, $base_time + ($i * 10));
            }

            $metrics_a = $this->es->calculate_aggregate_metrics((int)$user_a->id, (int)$course->id);
            $metrics_b = $this->es->calculate_aggregate_metrics((int)$user_b->id, (int)$course->id);

            $this->assertEqualsWithDelta(
                $metrics_a['average_score'],
                $metrics_b['average_score'],
                0.01,
                "Property 11 violated: average_score is NOT commutative. " .
                "Original order average={$metrics_a['average_score']}, " .
                "reversed order average={$metrics_b['average_score']}. " .
                "Scores: [" . implode(', ', $scores) . "]. " .
                "Order of scores must not affect the aggregate average."
            );

            $this->assertEquals(
                $metrics_a['score_count'],
                $metrics_b['score_count'],
                "Property 11 violated: score_count differs between orderings. " .
                "This indicates records were lost or duplicated."
            );
        });
    }

    // =========================================================================
    // PROPERTY 11 (Monotonicity): Adding a higher/lower score changes average
    // =========================================================================

    /**
     * Property 11 (Monotonicity -- Higher Score): Adding a score that is strictly
     * higher than the current average must increase (or maintain) the average.
     *
     * **Validates: Requirements 6.2**
     *
     * @return void
     */
    public function test_property11_adding_higher_score_increases_or_maintains_average(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 1 && count($arr) <= 10; },
                Generator\seq(Generator\choose(0, 10000))
            )
        )->then(function (array $raw_ints) {
            $scores = array_map(fn($v) => $v / 100.0, $raw_ints);

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $base_time = time() - (count($scores) * 10 + 20);
            foreach ($scores as $i => $score) {
                $this->insert_score((int)$user->id, (int)$course->id, $score, $base_time + ($i * 10));
            }

            $metrics_before = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);
            $avg_before = $metrics_before['average_score'];

            // Add a score strictly higher than the current average (capped at 100).
            $higher_score = min(100.0, $avg_before + 10.0);
            $this->insert_score((int)$user->id, (int)$course->id, $higher_score, $base_time + (count($scores) * 10));

            $metrics_after = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);
            $avg_after = $metrics_after['average_score'];

            $this->assertGreaterThanOrEqual(
                $avg_before - 0.01,  // tolerance for floating-point rounding
                $avg_after,
                "Property 11 violated (Monotonicity): Adding a higher score={$higher_score} " .
                "to a set with average={$avg_before} should increase or maintain the average, " .
                "but average decreased to {$avg_after}. " .
                "Scores: [" . implode(', ', $scores) . "]"
            );
        });
    }

    /**
     * Property 11 (Monotonicity -- Lower Score): Adding a score that is strictly
     * lower than the current average must decrease (or maintain) the average.
     *
     * **Validates: Requirements 6.2**
     *
     * @return void
     */
    public function test_property11_adding_lower_score_decreases_or_maintains_average(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 1 && count($arr) <= 10; },
                Generator\seq(Generator\choose(0, 10000))
            )
        )->then(function (array $raw_ints) {
            $scores = array_map(fn($v) => $v / 100.0, $raw_ints);

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $base_time = time() - (count($scores) * 10 + 20);
            foreach ($scores as $i => $score) {
                $this->insert_score((int)$user->id, (int)$course->id, $score, $base_time + ($i * 10));
            }

            $metrics_before = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);
            $avg_before = $metrics_before['average_score'];

            // Add a score strictly lower than the current average (floored at 0).
            $lower_score = max(0.0, $avg_before - 10.0);
            $this->insert_score((int)$user->id, (int)$course->id, $lower_score, $base_time + (count($scores) * 10));

            $metrics_after = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);
            $avg_after = $metrics_after['average_score'];

            $this->assertLessThanOrEqual(
                $avg_before + 0.01,  // tolerance for floating-point rounding
                $avg_after,
                "Property 11 violated (Monotonicity): Adding a lower score={$lower_score} " .
                "to a set with average={$avg_before} should decrease or maintain the average, " .
                "but average increased to {$avg_after}. " .
                "Scores: [" . implode(', ', $scores) . "]"
            );
        });
    }

    // =========================================================================
    // PROPERTY 11 (All-Zero Scores): Edge case -- all scores are zero
    // =========================================================================

    /**
     * Property 11 (All-Zero Scores): When all scores are 0, the average must be 0.
     *
     * **Validates: Requirements 6.2**
     *
     * @return void
     */
    public function test_property11_all_zero_scores_average_is_zero(): void {
        $this->forAll(
            Generator\choose(1, 20)  // Number of zero scores
        )->then(function (int $count) {
            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $base_time = time() - ($count * 10);
            for ($i = 0; $i < $count; $i++) {
                $this->insert_score((int)$user->id, (int)$course->id, 0.0, $base_time + ($i * 10));
            }

            $metrics = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);

            $this->assertEquals(
                0.0,
                $metrics['average_score'],
                "Property 11 violated: All-zero scores must produce average_score=0.0, " .
                "but got {$metrics['average_score']}. count={$count}"
            );

            $this->assertEquals(
                $count,
                $metrics['score_count'],
                "Property 11 violated: score_count should be {$count} for all-zero set."
            );
        });
    }

    // =========================================================================
    // PROPERTY 11 (All-Max Scores): Edge case -- all scores are 100
    // =========================================================================

    /**
     * Property 11 (All-Max Scores): When all scores are 100, the average must be 100.
     *
     * **Validates: Requirements 6.2**
     *
     * @return void
     */
    public function test_property11_all_max_scores_average_is_100(): void {
        $this->forAll(
            Generator\choose(1, 20)  // Number of max scores
        )->then(function (int $count) {
            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $base_time = time() - ($count * 10);
            for ($i = 0; $i < $count; $i++) {
                $this->insert_score((int)$user->id, (int)$course->id, 100.0, $base_time + ($i * 10));
            }

            $metrics = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);

            $this->assertEquals(
                100.0,
                $metrics['average_score'],
                "Property 11 violated: All-max scores (100) must produce average_score=100.0, " .
                "but got {$metrics['average_score']}. count={$count}"
            );

            $this->assertEquals(
                $count,
                $metrics['score_count'],
                "Property 11 violated: score_count should be {$count} for all-max set."
            );
        });
    }

    // =========================================================================
    // PROPERTY 11 (Mixed Scores): Explicit verification with known values
    // =========================================================================

    /**
     * Property 11 (Mixed Scores): Explicit verification with known score sets
     * to confirm the arithmetic is correct for representative cases.
     *
     * **Validates: Requirements 6.2**
     *
     * @return void
     */
    public function test_property11_mixed_scores_known_expected_values(): void {
        $test_cases = [
            // [scores, expected_average, expected_latest]
            [[50.0],                                    50.0,  50.0],
            [[0.0, 100.0],                              50.0,  100.0],
            [[80.0, 70.0, 90.0, 60.0],                 75.0,  60.0],
            [[100.0, 100.0, 100.0],                     100.0, 100.0],
            [[0.0, 0.0, 0.0],                           0.0,   0.0],
            [[33.33, 66.67, 100.0],                     66.67, 100.0],
            [[10.0, 20.0, 30.0, 40.0, 50.0],           30.0,  50.0],
        ];

        foreach ($test_cases as [$scores, $expected_avg, $expected_latest]) {
            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $base_time = time() - (count($scores) * 10);
            foreach ($scores as $i => $score) {
                $this->insert_score((int)$user->id, (int)$course->id, $score, $base_time + ($i * 10));
            }

            $metrics = $this->es->calculate_aggregate_metrics((int)$user->id, (int)$course->id);

            $this->assertEqualsWithDelta(
                $expected_avg,
                $metrics['average_score'],
                0.01,
                "Property 11 violated: Scores=[" . implode(', ', $scores) . "] " .
                "expected average={$expected_avg}, got {$metrics['average_score']}."
            );

            $this->assertEqualsWithDelta(
                $expected_latest,
                $metrics['latest_score'],
                0.01,
                "Property 11 violated: Scores=[" . implode(', ', $scores) . "] " .
                "expected latest={$expected_latest}, got {$metrics['latest_score']}."
            );

            $this->assertEquals(
                count($scores),
                $metrics['score_count'],
                "Property 11 violated: Scores=[" . implode(', ', $scores) . "] " .
                "expected count=" . count($scores) . ", got {$metrics['score_count']}."
            );
        }
    }

    // =========================================================================
    // PROPERTY 11 (Isolation): Scores from different users/courses are isolated
    // =========================================================================

    /**
     * Property 11 (Isolation): Scores from different users or courses must not
     * contaminate each other's aggregate metrics.
     *
     * **Validates: Requirements 6.2**
     *
     * @return void
     */
    public function test_property11_scores_are_isolated_per_user_and_course(): void {
        $this->forAll(
            Generator\choose(0, 10000),  // Score for user A
            Generator\choose(0, 10000)   // Score for user B
        )->then(function (int $raw_a, int $raw_b) {
            $score_a = $raw_a / 100.0;
            $score_b = $raw_b / 100.0;

            $course   = $this->getDataGenerator()->create_course();
            $user_a   = $this->getDataGenerator()->create_user();
            $user_b   = $this->getDataGenerator()->create_user();

            $this->insert_score((int)$user_a->id, (int)$course->id, $score_a);
            $this->insert_score((int)$user_b->id, (int)$course->id, $score_b);

            $metrics_a = $this->es->calculate_aggregate_metrics((int)$user_a->id, (int)$course->id);
            $metrics_b = $this->es->calculate_aggregate_metrics((int)$user_b->id, (int)$course->id);

            // User A's average must equal score_a (only one score).
            $this->assertEqualsWithDelta(
                round($score_a, 2),
                $metrics_a['average_score'],
                0.01,
                "Property 11 violated (Isolation): User A's average={$metrics_a['average_score']} " .
                "does not equal their own score={$score_a}. " .
                "User B's score={$score_b} must not contaminate User A's metrics."
            );

            // User B's average must equal score_b (only one score).
            $this->assertEqualsWithDelta(
                round($score_b, 2),
                $metrics_b['average_score'],
                0.01,
                "Property 11 violated (Isolation): User B's average={$metrics_b['average_score']} " .
                "does not equal their own score={$score_b}. " .
                "User A's score={$score_a} must not contaminate User B's metrics."
            );

            // Each user must have exactly 1 score.
            $this->assertEquals(1, $metrics_a['score_count'],
                "Property 11 violated (Isolation): User A should have exactly 1 score.");
            $this->assertEquals(1, $metrics_b['score_count'],
                "Property 11 violated (Isolation): User B should have exactly 1 score.");
        });
    }
}
