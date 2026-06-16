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
 * Property-based test for Property 18: Akurasi Kalkulasi Skor Leaderboard.
 *
 * Verifies that Leaderboard::calculate_score() produces mathematically accurate
 * composite scores and that rankings are consistent with total scores.
 *
 * **Validates: Requirements 12.1**
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
 * Property 18: Akurasi Kalkulasi Skor Leaderboard.
 *
 * For any valid score inputs and weights, the composite leaderboard score
 * computed by Leaderboard::calculate_score() must be mathematically accurate.
 * Rankings derived from those scores must be consistent. Specifically:
 *
 *   - P18a: total_score = (attendance × w1) + (engagement × w2) + (completion × w3)
 *           for any valid inputs in [0, 100] and weights summing to 1.0
 *   - P18b: Rankings are consistent — if learner A has a higher total_score than
 *           learner B, then A must have a lower (better) rank number than B
 *   - P18c: total_score is always in [0, 100] when inputs are in [0, 100] and
 *           weights sum to 1.0
 *   - P18d: Score calculation is deterministic — same inputs always produce the
 *           same output
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class leaderboard_score_accuracy_property_test extends \advanced_testcase {
    use TestTrait;

    /** @var \block_attendanceleaderboard\leaderboard\leaderboard System under test. */
    private \block_attendanceleaderboard\leaderboard\leaderboard $lb;

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
        $this->lb = new \block_attendanceleaderboard\leaderboard\leaderboard();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Insert a leaderboard record directly into acmls_leaderboard.
     *
     * @param  int    $userid     Moodle user ID.
     * @param  int    $courseid   Moodle course ID.
     * @param  float  $attendance Attendance score (0–100).
     * @param  float  $engagement Engagement score (0–100).
     * @param  float  $completion Completion score (0–100).
     * @param  float  $total      Pre-calculated total score.
     * @param  int    $rank       Current rank (0 = unranked).
     * @return int                Inserted record ID.
     */
    private function insert_leaderboard_record(
        int $userid,
        int $courseid,
        float $attendance,
        float $engagement,
        float $completion,
        float $total,
        int $rank = 0
    ): int {
        global $DB;

        $record                   = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->scope            = \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE;
        $record->attendance_score = $attendance;
        $record->engagement_score = $engagement;
        $record->completion_score = $completion;
        $record->total_score      = $total;
        $record->current_rank     = $rank;
        $record->previous_rank    = null;
        $record->rank_change      = null;
        $record->points_to_next   = null;
        $record->display_name     = null;
        $record->last_updated     = time();

        return (int) $DB->insert_record('acmls_leaderboard', $record);
    }

    // =========================================================================
    // P18a: Mathematical accuracy of composite score formula
    // =========================================================================

    /**
     * P18a (Formula Accuracy): For any valid score inputs in [0, 100] and the
     * default weights (0.4, 0.4, 0.2), calculate_score() must return exactly
     * (attendance × 0.4) + (engagement × 0.4) + (completion × 0.2).
     *
     * **Validates: Requirements 12.1**
     *
     * Test Strategy:
     * - Generate three random integers in [0, 10000] (divide by 100 for [0, 100])
     * - Call calculate_score() with the resulting floats
     * - Verify the result equals the expected weighted sum
     *
     * @return void
     */
    public function test_property18a_score_formula_is_mathematically_accurate(): void {
        $this->forAll(
            Generator\choose(0, 10000),  // attendance raw (÷100 → [0, 100])
            Generator\choose(0, 10000),  // engagement raw (÷100 → [0, 100])
            Generator\choose(0, 10000)   // completion raw (÷100 → [0, 100])
        )->then(function (int $raw_att, int $raw_eng, int $raw_comp) {
            $attendance = $raw_att / 100.0;
            $engagement = $raw_eng / 100.0;
            $completion = $raw_comp / 100.0;

            $w1 = $this->lb->get_weight_attendance();
            $w2 = $this->lb->get_weight_engagement();
            $w3 = $this->lb->get_weight_completion();

            $actual   = $this->lb->calculate_score($attendance, $engagement, $completion);
            $expected = ($attendance * $w1) + ($engagement * $w2) + ($completion * $w3);

            $this->assertEqualsWithDelta(
                $expected,
                $actual,
                0.0001,
                "Property 18a violated: calculate_score({$attendance}, {$engagement}, {$completion}) " .
                "returned {$actual} but expected {$expected}. " .
                "Formula: total = (att × {$w1}) + (eng × {$w2}) + (comp × {$w3}). " .
                "Leaderboard scores must be mathematically accurate (Req 12.1)."
            );
        });
    }

    /**
     * P18a (Weights Sum to 1.0): The default weights loaded by the Leaderboard
     * must sum to exactly 1.0 (within floating-point tolerance).
     *
     * **Validates: Requirements 12.1**
     *
     * @return void
     */
    public function test_property18a_default_weights_sum_to_one(): void {
        $w1 = $this->lb->get_weight_attendance();
        $w2 = $this->lb->get_weight_engagement();
        $w3 = $this->lb->get_weight_completion();

        $sum = $w1 + $w2 + $w3;

        $this->assertEqualsWithDelta(
            1.0,
            $sum,
            0.0001,
            "Property 18a violated: Default weights do not sum to 1.0. " .
            "w1={$w1}, w2={$w2}, w3={$w3}, sum={$sum}. " .
            "Weights must sum to 1.0 for the formula to be valid."
        );
    }

    /**
     * P18a (Known Values): Explicit verification with known score sets to confirm
     * the arithmetic is correct for representative cases.
     *
     * **Validates: Requirements 12.1**
     *
     * @return void
     */
    public function test_property18a_known_values_produce_correct_scores(): void {
        // Default weights: attendance=0.4, engagement=0.4, completion=0.2
        $w1 = \block_attendanceleaderboard\leaderboard\leaderboard::DEFAULT_WEIGHT_ATTENDANCE;
        $w2 = \block_attendanceleaderboard\leaderboard\leaderboard::DEFAULT_WEIGHT_ENGAGEMENT;
        $w3 = \block_attendanceleaderboard\leaderboard\leaderboard::DEFAULT_WEIGHT_COMPLETION;

        $test_cases = [
            // [attendance, engagement, completion, expected_total]
            [100.0, 100.0, 100.0, 100.0],
            [0.0,   0.0,   0.0,   0.0],
            [80.0,  70.0,  60.0,  (80.0 * $w1) + (70.0 * $w2) + (60.0 * $w3)],
            [50.0,  50.0,  50.0,  50.0],
            [100.0, 0.0,   0.0,   $w1 * 100.0],
            [0.0,   100.0, 0.0,   $w2 * 100.0],
            [0.0,   0.0,   100.0, $w3 * 100.0],
        ];

        foreach ($test_cases as [$att, $eng, $comp, $expected]) {
            $actual = $this->lb->calculate_score($att, $eng, $comp);

            $this->assertEqualsWithDelta(
                $expected,
                $actual,
                0.0001,
                "Property 18a violated: calculate_score({$att}, {$eng}, {$comp}) " .
                "returned {$actual} but expected {$expected}."
            );
        }
    }

    // =========================================================================
    // P18b: Rankings are consistent with total scores
    // =========================================================================

    /**
     * P18b (Ranking Consistency): After update_rankings(), if learner A has a
     * strictly higher total_score than learner B, then A must have a strictly
     * lower (better) rank number than B.
     *
     * **Validates: Requirements 12.1**
     *
     * Test Strategy:
     * - Generate 2–10 distinct total scores
     * - Insert them as leaderboard records for different users in the same course
     * - Call update_rankings()
     * - Verify that the rank ordering is the inverse of the score ordering
     *
     * @return void
     */
    public function test_property18b_rankings_are_consistent_with_total_scores(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 2 && count($arr) <= 10; },
                Generator\seq(Generator\choose(0, 10000))
            )
        )->then(function (array $raw_ints) {
            // Convert to float scores in [0, 100] and ensure uniqueness.
            $scores = array_values(array_unique(array_map(fn($v) => $v / 100.0, $raw_ints)));

            if (count($scores) < 2) {
                // Skip if deduplication left fewer than 2 distinct scores.
                return;
            }

            $course = $this->getDataGenerator()->create_course();
            $users  = [];

            // Insert one leaderboard record per distinct score.
            foreach ($scores as $score) {
                $user    = $this->getDataGenerator()->create_user();
                $users[] = $user;
                $this->insert_leaderboard_record(
                    (int) $user->id,
                    (int) $course->id,
                    $score,   // attendance (simplified: use total as all components)
                    0.0,
                    0.0,
                    $score    // total_score
                );
            }

            // Run ranking update.
            $this->lb->update_rankings((int) $course->id);

            // Fetch updated ranks for all users.
            $ranked = [];
            foreach ($users as $i => $user) {
                $rank_data = $this->lb->get_learner_rank((int) $user->id, (int) $course->id);
                $this->assertNotEmpty(
                    $rank_data,
                    "Property 18b violated: get_learner_rank() returned empty for user {$user->id}."
                );
                $ranked[] = [
                    'score'        => $scores[$i],
                    'current_rank' => $rank_data['current_rank'],
                ];
            }

            // Sort by score descending to get expected rank order.
            usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);

            // Verify: higher score → lower (better) rank number.
            for ($i = 0; $i < count($ranked) - 1; $i++) {
                $higher = $ranked[$i];
                $lower  = $ranked[$i + 1];

                if ($higher['score'] > $lower['score']) {
                    $this->assertLessThan(
                        $lower['current_rank'],
                        $higher['current_rank'],
                        "Property 18b violated: Learner with score={$higher['score']} " .
                        "has rank={$higher['current_rank']}, but learner with lower score={$lower['score']} " .
                        "has rank={$lower['current_rank']}. " .
                        "Higher score must always produce a lower (better) rank number. " .
                        "Rankings must be consistent with total scores (Req 12.1)."
                    );
                }
            }
        });
    }

    /**
     * P18b (Rank 1 is Highest Score): After update_rankings(), the learner with
     * the highest total_score must always receive rank 1.
     *
     * **Validates: Requirements 12.1**
     *
     * @return void
     */
    public function test_property18b_rank_1_belongs_to_highest_score(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 2 && count($arr) <= 8; },
                Generator\seq(Generator\choose(1, 10000))
            )
        )->then(function (array $raw_ints) {
            $scores = array_values(array_unique(array_map(fn($v) => $v / 100.0, $raw_ints)));

            if (count($scores) < 2) {
                return;
            }

            $course      = $this->getDataGenerator()->create_course();
            $user_scores = [];

            foreach ($scores as $score) {
                $user                        = $this->getDataGenerator()->create_user();
                $user_scores[$user->id]      = $score;
                $this->insert_leaderboard_record(
                    (int) $user->id,
                    (int) $course->id,
                    $score,
                    0.0,
                    0.0,
                    $score
                );
            }

            $this->lb->update_rankings((int) $course->id);

            // Find the user with the highest score.
            arsort($user_scores);
            $top_userid = (int) array_key_first($user_scores);
            $top_score  = $user_scores[$top_userid];

            $rank_data = $this->lb->get_learner_rank($top_userid, (int) $course->id);

            $this->assertEquals(
                1,
                $rank_data['current_rank'],
                "Property 18b violated: Learner with highest score={$top_score} " .
                "should have rank=1, but got rank={$rank_data['current_rank']}. " .
                "Rank 1 must always belong to the learner with the highest total_score."
            );
        });
    }

    // =========================================================================
    // P18c: total_score is always in [0, 100] for valid inputs
    // =========================================================================

    /**
     * P18c (Range Invariant): For any score inputs in [0, 100] and weights that
     * sum to 1.0, calculate_score() must always return a value in [0, 100].
     *
     * **Validates: Requirements 12.1**
     *
     * @return void
     */
    public function test_property18c_total_score_always_in_valid_range(): void {
        $this->forAll(
            Generator\choose(0, 10000),  // attendance raw (÷100 → [0, 100])
            Generator\choose(0, 10000),  // engagement raw (÷100 → [0, 100])
            Generator\choose(0, 10000)   // completion raw (÷100 → [0, 100])
        )->then(function (int $raw_att, int $raw_eng, int $raw_comp) {
            $attendance = $raw_att / 100.0;
            $engagement = $raw_eng / 100.0;
            $completion = $raw_comp / 100.0;

            $total = $this->lb->calculate_score($attendance, $engagement, $completion);

            $this->assertGreaterThanOrEqual(
                0.0,
                $total,
                "Property 18c violated: calculate_score({$attendance}, {$engagement}, {$completion}) " .
                "returned {$total} which is below 0. " .
                "total_score must always be in [0, 100] for valid inputs (Req 12.1)."
            );

            $this->assertLessThanOrEqual(
                100.0,
                $total,
                "Property 18c violated: calculate_score({$attendance}, {$engagement}, {$completion}) " .
                "returned {$total} which exceeds 100. " .
                "total_score must always be in [0, 100] for valid inputs (Req 12.1)."
            );
        });
    }

    /**
     * P18c (Boundary Values): Verify range invariant holds at the exact boundaries
     * (0 and 100) for all three score components.
     *
     * **Validates: Requirements 12.1**
     *
     * @return void
     */
    public function test_property18c_boundary_values_produce_valid_range(): void {
        $boundary_cases = [
            [0.0,   0.0,   0.0],
            [100.0, 100.0, 100.0],
            [0.0,   100.0, 100.0],
            [100.0, 0.0,   100.0],
            [100.0, 100.0, 0.0],
            [0.0,   0.0,   100.0],
            [0.0,   100.0, 0.0],
            [100.0, 0.0,   0.0],
        ];

        foreach ($boundary_cases as [$att, $eng, $comp]) {
            $total = $this->lb->calculate_score($att, $eng, $comp);

            $this->assertGreaterThanOrEqual(
                0.0,
                $total,
                "Property 18c violated: calculate_score({$att}, {$eng}, {$comp}) " .
                "returned {$total} which is below 0."
            );

            $this->assertLessThanOrEqual(
                100.0,
                $total,
                "Property 18c violated: calculate_score({$att}, {$eng}, {$comp}) " .
                "returned {$total} which exceeds 100."
            );
        }
    }

    // =========================================================================
    // P18d: Score calculation is deterministic
    // =========================================================================

    /**
     * P18d (Determinism): Calling calculate_score() twice with the same inputs
     * must always produce the same output.
     *
     * **Validates: Requirements 12.1**
     *
     * Test Strategy:
     * - Generate three random scores
     * - Call calculate_score() twice with the same inputs
     * - Verify both calls return identical results
     *
     * @return void
     */
    public function test_property18d_score_calculation_is_deterministic(): void {
        $this->forAll(
            Generator\choose(0, 10000),
            Generator\choose(0, 10000),
            Generator\choose(0, 10000)
        )->then(function (int $raw_att, int $raw_eng, int $raw_comp) {
            $attendance = $raw_att / 100.0;
            $engagement = $raw_eng / 100.0;
            $completion = $raw_comp / 100.0;

            $result1 = $this->lb->calculate_score($attendance, $engagement, $completion);
            $result2 = $this->lb->calculate_score($attendance, $engagement, $completion);

            $this->assertSame(
                $result1,
                $result2,
                "Property 18d violated: calculate_score({$attendance}, {$engagement}, {$completion}) " .
                "returned different values on two calls: {$result1} vs {$result2}. " .
                "Score calculation must be deterministic — same inputs must always produce " .
                "the same output (Req 12.1)."
            );
        });
    }

    /**
     * P18d (Determinism across instances): Two separate Leaderboard instances
     * with the same configuration must produce the same score for the same inputs.
     *
     * **Validates: Requirements 12.1**
     *
     * @return void
     */
    public function test_property18d_determinism_across_instances(): void {
        $this->forAll(
            Generator\choose(0, 10000),
            Generator\choose(0, 10000),
            Generator\choose(0, 10000)
        )->then(function (int $raw_att, int $raw_eng, int $raw_comp) {
            $attendance = $raw_att / 100.0;
            $engagement = $raw_eng / 100.0;
            $completion = $raw_comp / 100.0;

            $lb1 = new \block_attendanceleaderboard\leaderboard\leaderboard();
            $lb2 = new \block_attendanceleaderboard\leaderboard\leaderboard();

            $result1 = $lb1->calculate_score($attendance, $engagement, $completion);
            $result2 = $lb2->calculate_score($attendance, $engagement, $completion);

            $this->assertSame(
                $result1,
                $result2,
                "Property 18d violated: Two Leaderboard instances returned different scores " .
                "for the same inputs ({$attendance}, {$engagement}, {$completion}): " .
                "{$result1} vs {$result2}. " .
                "Score calculation must be deterministic across instances."
            );
        });
    }

    // =========================================================================
    // P18 (Integration): upsert + update_rankings round-trip accuracy
    // =========================================================================

    /**
     * P18 (Round-trip): After upsert_learner_score() and update_rankings(),
     * the stored total_score must match the expected formula result and the
     * ranking must be consistent with the stored score.
     *
     * **Validates: Requirements 12.1**
     *
     * @return void
     */
    public function test_property18_upsert_and_ranking_round_trip_is_accurate(): void {
        $this->forAll(
            Generator\choose(0, 10000),
            Generator\choose(0, 10000),
            Generator\choose(0, 10000)
        )->then(function (int $raw_att, int $raw_eng, int $raw_comp) {
            $attendance = $raw_att / 100.0;
            $engagement = $raw_eng / 100.0;
            $completion = $raw_comp / 100.0;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            // Upsert the learner's score.
            $this->lb->upsert_learner_score(
                (int) $user->id,
                (int) $course->id,
                \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                $attendance,
                $engagement,
                $completion
            );

            // Update rankings (single learner → rank 1).
            $this->lb->update_rankings((int) $course->id);

            // Fetch the stored rank data.
            $rank_data = $this->lb->get_learner_rank((int) $user->id, (int) $course->id);

            $this->assertNotEmpty(
                $rank_data,
                "Property 18 violated: get_learner_rank() returned empty after upsert."
            );

            // Verify stored total_score matches the formula.
            $w1 = $this->lb->get_weight_attendance();
            $w2 = $this->lb->get_weight_engagement();
            $w3 = $this->lb->get_weight_completion();
            $expected_total = ($attendance * $w1) + ($engagement * $w2) + ($completion * $w3);

            $this->assertEqualsWithDelta(
                $expected_total,
                $rank_data['total_score'],
                0.0001,
                "Property 18 violated: Stored total_score={$rank_data['total_score']} " .
                "does not match expected formula result={$expected_total}. " .
                "Inputs: att={$attendance}, eng={$engagement}, comp={$completion}. " .
                "Leaderboard scores must be mathematically accurate (Req 12.1)."
            );

            // Single learner must be rank 1.
            $this->assertEquals(
                1,
                $rank_data['current_rank'],
                "Property 18 violated: Single learner should have rank=1 after update_rankings(), " .
                "but got rank={$rank_data['current_rank']}."
            );

            // Stored score must be in [0, 100].
            $this->assertGreaterThanOrEqual(
                0.0,
                $rank_data['total_score'],
                "Property 18 violated: Stored total_score={$rank_data['total_score']} is below 0."
            );

            $this->assertLessThanOrEqual(
                100.0,
                $rank_data['total_score'],
                "Property 18 violated: Stored total_score={$rank_data['total_score']} exceeds 100."
            );
        });
    }
}
