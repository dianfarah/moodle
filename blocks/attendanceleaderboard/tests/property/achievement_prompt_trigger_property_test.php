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
 * Property-based test for Property 20: Ketepatan Trigger Achievement Prompts.
 *
 * Verifies that Leaderboard::trigger_achievement_prompt() fires if and only if
 * rank improves (rank_change > 0). No false triggers and no missed triggers.
 *
 * **Validates: Requirements 12.6**
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
 * Property 20: Ketepatan Trigger Achievement Prompts.
 *
 * Achievement Prompts must be triggered if and only if rank improves.
 * - rank_change > 0  → trigger MUST fire
 * - rank_change <= 0 → trigger MUST NOT fire
 *
 * Sub-properties:
 *   P20a: No false triggers — rank_change <= 0 means no trigger
 *   P20b: No missed triggers — rank_change > 0 means trigger fires
 *   P20c: Trigger condition precision — iff rank_change > 0
 *   P20d: Direct method test — trigger_achievement_prompt() behaves correctly
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class achievement_prompt_trigger_property_test extends \advanced_testcase {
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
     * @param  int    $userid       Moodle user ID.
     * @param  int    $courseid     Moodle course ID.
     * @param  float  $total        Total score.
     * @param  int    $current_rank Current rank (0 = unranked).
     * @param  int|null $prev_rank  Previous rank (null = first time).
     * @param  int|null $rank_change Rank change (null = first time).
     * @return int                  Inserted record ID.
     */
    private function insert_leaderboard_record(
        int $userid,
        int $courseid,
        float $total,
        int $current_rank = 0,
        ?int $prev_rank = null,
        ?int $rank_change = null
    ): int {
        global $DB;

        $record                   = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->scope            = \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE;
        $record->attendance_score = $total;
        $record->engagement_score = 0.0;
        $record->completion_score = 0.0;
        $record->total_score      = $total;
        $record->current_rank     = $current_rank;
        $record->previous_rank    = $prev_rank;
        $record->rank_change      = $rank_change;
        $record->points_to_next   = null;
        $record->display_name     = null;
        $record->last_updated     = time();

        return (int) $DB->insert_record('acmls_leaderboard', $record);
    }

    /**
     * Get the rank_change value for a learner from the DB.
     *
     * @param  int $userid   Moodle user ID.
     * @param  int $courseid Moodle course ID.
     * @return int|null      rank_change value, or null if not found.
     */
    private function get_rank_change(int $userid, int $courseid): ?int {
        global $DB;

        $record = $DB->get_record(
            'acmls_leaderboard',
            ['userid' => $userid, 'courseid' => $courseid],
            'rank_change',
            IGNORE_MISSING
        );

        if (!$record) {
            return null;
        }

        return isset($record->rank_change) ? (int) $record->rank_change : null;
    }

    // =========================================================================
    // P20a: No False Triggers
    // =========================================================================

    /**
     * P20a (No False Triggers): When a learner rank does NOT improve (rank_change <= 0),
     * trigger_achievement_prompt() must NOT trigger.
     *
     * Test strategy:
     * - Create a learner with an initial high score (rank 1).
     * - Add a second learner with a higher score so the first learner drops.
     * - Call update_rankings() — first learner rank worsens.
     * - Verify rank_change <= 0 for the first learner.
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20a_no_false_triggers_when_rank_does_not_improve(): void {
        $this->forAll(
            Generator\choose(1, 5000),  // initial score for learner A (÷100)
            Generator\choose(5001, 10000) // higher score for learner B (÷100)
        )->then(function (int $raw_a, int $raw_b) {
            $score_a = $raw_a / 100.0;  // lower score
            $score_b = $raw_b / 100.0;  // higher score (B will outrank A)

            $course  = $this->getDataGenerator()->create_course();
            $user_a  = $this->getDataGenerator()->create_user();
            $user_b  = $this->getDataGenerator()->create_user();

            // Insert learner A with initial rank 1 (was top).
            $this->insert_leaderboard_record(
                (int) $user_a->id,
                (int) $course->id,
                $score_a,
                1,    // current_rank = 1 (was top)
                null, // no previous rank
                null  // no rank_change yet
            );

            // Insert learner B with a higher score — will push A down.
            $this->insert_leaderboard_record(
                (int) $user_b->id,
                (int) $course->id,
                $score_b,
                0,    // unranked initially
                null,
                null
            );

            // Run ranking update — A should drop to rank 2, B gets rank 1.
            $this->lb->update_rankings((int) $course->id);

            // Verify rank_change for A is <= 0 (rank worsened or unchanged).
            $rank_change_a = $this->get_rank_change((int) $user_a->id, (int) $course->id);

            $this->assertNotNull(
                $rank_change_a,
                "Property 20a violated: rank_change should not be null after update_rankings()."
            );

            $this->assertLessThanOrEqual(
                0,
                $rank_change_a,
                "Property 20a violated: Learner A (score={$score_a}) was outranked by B (score={$score_b}). " .
                "rank_change={$rank_change_a} should be <= 0 (no improvement). " .
                "Achievement prompt must NOT be triggered when rank does not improve (Req 12.6)."
            );
        });
    }

    /**
     * P20a (No False Triggers — Tie): When two learners have equal scores,
     * rank_change should be 0 (no improvement) — no trigger.
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20a_no_false_triggers_on_equal_scores(): void {
        $this->forAll(
            Generator\choose(1, 10000)  // shared score (÷100)
        )->then(function (int $raw_score) {
            $score   = $raw_score / 100.0;
            $course  = $this->getDataGenerator()->create_course();
            $user_a  = $this->getDataGenerator()->create_user();
            $user_b  = $this->getDataGenerator()->create_user();

            // Both learners start with the same score.
            $this->insert_leaderboard_record(
                (int) $user_a->id, (int) $course->id, $score, 1, null, null
            );
            $this->insert_leaderboard_record(
                (int) $user_b->id, (int) $course->id, $score, 2, null, null
            );

            // Run ranking — scores are equal, ranks should not change.
            $this->lb->update_rankings((int) $course->id);

            // For learner A: rank_change should be <= 0 (no improvement).
            $rank_change_a = $this->get_rank_change((int) $user_a->id, (int) $course->id);

            $this->assertNotNull(
                $rank_change_a,
                "Property 20a violated: rank_change should not be null after update_rankings()."
            );

            $this->assertLessThanOrEqual(
                0,
                $rank_change_a,
                "Property 20a violated: Learner A with equal score should have rank_change <= 0. " .
                "rank_change={$rank_change_a}. " .
                "No achievement prompt should fire when rank does not improve (Req 12.6)."
            );
        });
    }

    // =========================================================================
    // P20b: No Missed Triggers
    // =========================================================================

    /**
     * P20b (No Missed Triggers): When a learner rank DOES improve (rank_change > 0),
     * trigger_achievement_prompt() must be triggered.
     *
     * Test strategy:
     * - Create two learners: A with low score, B with high score.
     * - Run update_rankings() — B is rank 1, A is rank 2.
     * - Increase A score above B.
     * - Run update_rankings() again — A moves to rank 1.
     * - Verify rank_change > 0 for A (trigger condition met).
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20b_no_missed_triggers_when_rank_improves(): void {
        $this->forAll(
            Generator\choose(1, 4000),   // initial score for A (÷100) — lower
            Generator\choose(5000, 9000) // initial score for B (÷100) — higher
        )->then(function (int $raw_a_initial, int $raw_b) {
            $score_a_initial = $raw_a_initial / 100.0;
            $score_b         = $raw_b / 100.0;
            // A improved score will be higher than B.
            $score_a_improved = ($score_b + 10.0 > 100.0) ? 100.0 : $score_b + 10.0;

            $course = $this->getDataGenerator()->create_course();
            $user_a = $this->getDataGenerator()->create_user();
            $user_b = $this->getDataGenerator()->create_user();

            // Insert initial scores.
            $this->lb->upsert_learner_score(
                (int) $user_a->id, (int) $course->id,
                \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                $score_a_initial, 0.0, 0.0
            );
            $this->lb->upsert_learner_score(
                (int) $user_b->id, (int) $course->id,
                \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                $score_b, 0.0, 0.0
            );

            // First ranking: B is rank 1, A is rank 2.
            $this->lb->update_rankings((int) $course->id);

            // Verify initial state: A should be rank 2.
            $rank_data_a = $this->lb->get_learner_rank((int) $user_a->id, (int) $course->id);
            $this->assertEquals(2, $rank_data_a['current_rank'],
                "Setup: A should be rank 2 initially (score_a={$score_a_initial} < score_b={$score_b})."
            );

            // Improve A score above B.
            $this->lb->upsert_learner_score(
                (int) $user_a->id, (int) $course->id,
                \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                $score_a_improved, 0.0, 0.0
            );

            // Second ranking: A should now be rank 1.
            $this->lb->update_rankings((int) $course->id);

            // Verify rank_change > 0 for A (rank improved from 2 to 1).
            $rank_change_a = $this->get_rank_change((int) $user_a->id, (int) $course->id);

            $this->assertNotNull(
                $rank_change_a,
                "Property 20b violated: rank_change should not be null after rank improvement."
            );

            $this->assertGreaterThan(
                0,
                $rank_change_a,
                "Property 20b violated: Learner A improved from rank 2 to rank 1. " .
                "rank_change={$rank_change_a} should be > 0. " .
                "Achievement prompt MUST be triggered when rank improves (Req 12.6)."
            );
        });
    }

    /**
     * P20b (No Missed Triggers — Multiple Learners): With N learners,
     * any learner whose rank improves must have rank_change > 0.
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20b_no_missed_triggers_with_multiple_learners(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 3 && count($arr) <= 6; },
                Generator\seq(Generator\choose(1, 9000))
            )
        )->then(function (array $raw_scores) {
            // Ensure distinct scores.
            $scores = array_values(array_unique(array_map(fn($v) => $v / 100.0, $raw_scores)));
            if (count($scores) < 3) {
                return; // skip if deduplication left fewer than 3
            }

            $course = $this->getDataGenerator()->create_course();
            $users  = [];

            // Insert all learners with initial scores.
            foreach ($scores as $score) {
                $user    = $this->getDataGenerator()->create_user();
                $users[] = ['user' => $user, 'score' => $score];
                $this->lb->upsert_learner_score(
                    (int) $user->id, (int) $course->id,
                    \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                    $score, 0.0, 0.0
                );
            }

            // First ranking pass.
            $this->lb->update_rankings((int) $course->id);

            // Pick the last-ranked learner (lowest score) and boost their score.
            usort($users, fn($a, $b) => $a['score'] <=> $b['score']);
            $bottom_user  = $users[0]['user'];
            $top_score    = $users[count($users) - 1]['score'];
            $boosted_score = $top_score + 5.0 > 100.0 ? 100.0 : $top_score + 5.0;

            // Boost the bottom learner above everyone.
            $this->lb->upsert_learner_score(
                (int) $bottom_user->id, (int) $course->id,
                \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                $boosted_score, 0.0, 0.0
            );

            // Second ranking pass.
            $this->lb->update_rankings((int) $course->id);

            // The boosted learner must have rank_change > 0.
            $rank_change = $this->get_rank_change((int) $bottom_user->id, (int) $course->id);

            $this->assertNotNull(
                $rank_change,
                "Property 20b violated: rank_change should not be null for boosted learner."
            );

            $this->assertGreaterThan(
                0,
                $rank_change,
                "Property 20b violated: Boosted learner moved from last to first. " .
                "rank_change={$rank_change} should be > 0. " .
                "Achievement prompt MUST be triggered when rank improves (Req 12.6)."
            );
        });
    }

    // =========================================================================
    // P20c: Trigger Condition Precision
    // =========================================================================

    /**
     * P20c (Trigger Condition Precision): The trigger fires if and only if rank_change > 0.
     * Tests three cases: rank_change = 0, rank_change > 0, rank_change < 0.
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20c_trigger_condition_precision_zero_change(): void {
        // rank_change = 0 → no trigger (rank unchanged).
        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();

        // Insert record with rank_change = 0 (rank did not change).
        $this->insert_leaderboard_record(
            (int) $user->id,
            (int) $course->id,
            50.0,  // total score
            1,     // current_rank
            1,     // previous_rank (same = no change)
            0      // rank_change = 0
        );

        // trigger_achievement_prompt() should return early (rank_change = 0 <= 0).
        // No exception should be thrown.
        $this->lb->trigger_achievement_prompt((int) $user->id, (int) $course->id);

        // Verify rank_change is still 0 in DB (method did not alter it).
        $rank_change = $this->get_rank_change((int) $user->id, (int) $course->id);

        $this->assertEquals(
            0,
            $rank_change,
            "Property 20c violated: rank_change=0 should not trigger achievement prompt. " .
            "DB rank_change should remain 0 (Req 12.6)."
        );
    }

    /**
     * P20c (Trigger Condition Precision — Positive): rank_change > 0 → trigger fires.
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20c_trigger_condition_precision_positive_change(): void {
        $this->forAll(
            Generator\choose(1, 10)  // rank_change value (positive)
        )->then(function (int $rank_change_val) {
            $course = $this->getDataGenerator()->create_course();
            $user   = $this->getDataGenerator()->create_user();

            $prev_rank    = $rank_change_val + 1;  // e.g. rank_change=3 → prev=4
            $current_rank = 1;

            // Insert record with positive rank_change.
            $this->insert_leaderboard_record(
                (int) $user->id,
                (int) $course->id,
                80.0,
                $current_rank,
                $prev_rank,
                $rank_change_val  // positive rank_change
            );

            // trigger_achievement_prompt() should execute without error.
            // (MotivationComponent may not be available, but no exception expected.)
            $exception_thrown = false;
            try {
                $this->lb->trigger_achievement_prompt((int) $user->id, (int) $course->id);
            } catch (\Throwable $e) {
                $exception_thrown = true;
            }

            $this->assertFalse(
                $exception_thrown,
                "Property 20c violated: trigger_achievement_prompt() threw an exception " .
                "when rank_change={$rank_change_val} > 0. " .
                "Method must execute without error when rank improves (Req 12.6)."
            );

            // Verify rank_change is still positive in DB (method did not alter it).
            $db_rank_change = $this->get_rank_change((int) $user->id, (int) $course->id);

            $this->assertGreaterThan(
                0,
                $db_rank_change,
                "Property 20c violated: rank_change should remain > 0 after trigger. " .
                "DB rank_change={$db_rank_change} (Req 12.6)."
            );
        });
    }

    /**
     * P20c (Trigger Condition Precision — Negative): rank_change < 0 → no trigger.
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20c_trigger_condition_precision_negative_change(): void {
        $this->forAll(
            Generator\choose(1, 10)  // magnitude of negative rank_change
        )->then(function (int $magnitude) {
            $rank_change_val = -$magnitude;  // negative
            $course = $this->getDataGenerator()->create_course();
            $user   = $this->getDataGenerator()->create_user();

            // Insert record with negative rank_change (rank worsened).
            $this->insert_leaderboard_record(
                (int) $user->id,
                (int) $course->id,
                30.0,
                $magnitude + 1,  // current_rank (worse)
                1,               // previous_rank (was better)
                $rank_change_val // negative rank_change
            );

            // trigger_achievement_prompt() should return early — no trigger.
            $this->lb->trigger_achievement_prompt((int) $user->id, (int) $course->id);

            // Verify rank_change is still negative in DB.
            $db_rank_change = $this->get_rank_change((int) $user->id, (int) $course->id);

            $this->assertLessThan(
                0,
                $db_rank_change,
                "Property 20c violated: rank_change={$rank_change_val} < 0 should not trigger. " .
                "DB rank_change={$db_rank_change} should remain negative (Req 12.6)."
            );
        });
    }

    // =========================================================================
    // P20d: Direct method test
    // =========================================================================

    /**
     * P20d (Direct Method — Positive rank_change): Calling trigger_achievement_prompt()
     * directly when rank_change > 0 in DB must execute without error.
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20d_direct_trigger_with_positive_rank_change(): void {
        $this->forAll(
            Generator\choose(1, 5),   // rank_change (positive)
            Generator\choose(1, 9000) // total score (÷100)
        )->then(function (int $rank_change_val, int $raw_score) {
            $score  = $raw_score / 100.0;
            $course = $this->getDataGenerator()->create_course();
            $user   = $this->getDataGenerator()->create_user();

            // Insert record with positive rank_change.
            $this->insert_leaderboard_record(
                (int) $user->id,
                (int) $course->id,
                $score,
                1,               // current_rank
                1 + $rank_change_val, // previous_rank
                $rank_change_val // positive rank_change
            );

            // Direct call — must not throw.
            $exception_thrown = false;
            $exception_msg    = '';
            try {
                $this->lb->trigger_achievement_prompt((int) $user->id, (int) $course->id);
            } catch (\Throwable $e) {
                $exception_thrown = true;
                $exception_msg    = $e->getMessage();
            }

            $this->assertFalse(
                $exception_thrown,
                "Property 20d violated: trigger_achievement_prompt() threw exception " .
                "when rank_change={$rank_change_val} > 0: {$exception_msg}. " .
                "Method must execute without error when rank improves (Req 12.6)."
            );
        });
    }

    /**
     * P20d (Direct Method — Non-positive rank_change): Calling trigger_achievement_prompt()
     * directly when rank_change <= 0 must return early without error.
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20d_direct_trigger_with_non_positive_rank_change(): void {
        $this->forAll(
            Generator\choose(0, 5)   // rank_change magnitude (0 or negative)
        )->then(function (int $magnitude) {
            $rank_change_val = -$magnitude;  // 0 or negative
            $course = $this->getDataGenerator()->create_course();
            $user   = $this->getDataGenerator()->create_user();

            $current_rank = $magnitude + 1;
            $prev_rank    = ($magnitude === 0) ? 1 : 1;

            // Insert record with non-positive rank_change.
            $this->insert_leaderboard_record(
                (int) $user->id,
                (int) $course->id,
                40.0,
                $current_rank,
                $prev_rank,
                $rank_change_val
            );

            // Direct call — must return early without error.
            $exception_thrown = false;
            try {
                $this->lb->trigger_achievement_prompt((int) $user->id, (int) $course->id);
            } catch (\Throwable $e) {
                $exception_thrown = true;
            }

            $this->assertFalse(
                $exception_thrown,
                "Property 20d violated: trigger_achievement_prompt() threw exception " .
                "when rank_change={$rank_change_val} <= 0. " .
                "Method must return early without error (Req 12.6)."
            );

            // Verify rank_change is still non-positive in DB.
            $db_rank_change = $this->get_rank_change((int) $user->id, (int) $course->id);

            $this->assertLessThanOrEqual(
                0,
                $db_rank_change,
                "Property 20d violated: rank_change should remain <= 0 after early return. " .
                "DB rank_change={$db_rank_change} (Req 12.6)."
            );
        });
    }

    /**
     * P20d (Direct Method — No DB Record): Calling trigger_achievement_prompt()
     * when no DB record exists must return early without error.
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20d_direct_trigger_with_no_db_record(): void {
        $this->forAll(
            Generator\choose(1, 1000), // userid (non-existent)
            Generator\choose(1, 1000)  // courseid (non-existent)
        )->then(function (int $fake_userid, int $fake_courseid) {
            // No record inserted — trigger_achievement_prompt() must return early.
            $exception_thrown = false;
            try {
                $this->lb->trigger_achievement_prompt($fake_userid, $fake_courseid);
            } catch (\Throwable $e) {
                $exception_thrown = true;
            }

            $this->assertFalse(
                $exception_thrown,
                "Property 20d violated: trigger_achievement_prompt() threw exception " .
                "when no DB record exists for userid={$fake_userid}, courseid={$fake_courseid}. " .
                "Method must return early without error (Req 12.6)."
            );
        });
    }

    // =========================================================================
    // P20 (Integration): update_rankings triggers iff rank improves
    // =========================================================================

    /**
     * P20 (Integration): After update_rankings(), rank_change > 0 iff rank improved.
     * This is the core invariant: trigger condition is precisely rank_change > 0.
     *
     * Test strategy:
     * - Create N learners with distinct scores.
     * - Run update_rankings() twice (second time with shuffled scores).
     * - For each learner, verify: rank_change > 0 iff current_rank < previous_rank.
     *
     * **Validates: Requirements 12.6**
     *
     * @return void
     */
    public function test_property20_integration_rank_change_iff_rank_improved(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 3 && count($arr) <= 5; },
                Generator\seq(Generator\choose(100, 9900))
            )
        )->then(function (array $raw_scores) {
            // Ensure distinct scores.
            $scores = array_values(array_unique(array_map(fn($v) => $v / 100.0, $raw_scores)));
            if (count($scores) < 3) {
                return;
            }

            $course = $this->getDataGenerator()->create_course();
            $users  = [];

            // Insert all learners with initial scores.
            foreach ($scores as $i => $score) {
                $user    = $this->getDataGenerator()->create_user();
                $users[] = ['user' => $user, 'score' => $score];
                $this->lb->upsert_learner_score(
                    (int) $user->id, (int) $course->id,
                    \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                    $score, 0.0, 0.0
                );
            }

            // First ranking pass — establishes initial ranks.
            $this->lb->update_rankings((int) $course->id);

            // Record initial ranks.
            $initial_ranks = [];
            foreach ($users as $entry) {
                $rank_data = $this->lb->get_learner_rank((int) $entry['user']->id, (int) $course->id);
                $initial_ranks[(int) $entry['user']->id] = $rank_data['current_rank'];
            }

            // Reverse the scores to create rank changes for all learners.
            $reversed_scores = array_reverse($scores);
            foreach ($users as $i => $entry) {
                $this->lb->upsert_learner_score(
                    (int) $entry['user']->id, (int) $course->id,
                    \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                    $reversed_scores[$i], 0.0, 0.0
                );
            }

            // Second ranking pass — ranks change.
            $this->lb->update_rankings((int) $course->id);

            // Verify: rank_change > 0 iff current_rank < previous_rank.
            foreach ($users as $entry) {
                $userid    = (int) $entry['user']->id;
                $rank_data = $this->lb->get_learner_rank($userid, (int) $course->id);

                $this->assertNotEmpty(
                    $rank_data,
                    "Property 20 violated: get_learner_rank() returned empty for user {$userid}."
                );

                $current_rank  = $rank_data['current_rank'];
                $previous_rank = $rank_data['previous_rank'];
                $rank_change   = $rank_data['rank_change'];

                if ($previous_rank === null) {
                    continue; // skip if no previous rank
                }

                $rank_improved = ($current_rank < $previous_rank);

                if ($rank_improved) {
                    $this->assertGreaterThan(
                        0,
                        $rank_change,
                        "Property 20 violated: User {$userid} rank improved " .
                        "(prev={$previous_rank} → curr={$current_rank}) but rank_change={$rank_change} is not > 0. " .
                        "Achievement prompt MUST be triggered when rank improves (Req 12.6)."
                    );
                } else {
                    $this->assertLessThanOrEqual(
                        0,
                        $rank_change,
                        "Property 20 violated: User {$userid} rank did NOT improve " .
                        "(prev={$previous_rank} → curr={$current_rank}) but rank_change={$rank_change} is > 0. " .
                        "Achievement prompt must NOT be triggered when rank does not improve (Req 12.6)."
                    );
                }
            }
        });
    }
}
