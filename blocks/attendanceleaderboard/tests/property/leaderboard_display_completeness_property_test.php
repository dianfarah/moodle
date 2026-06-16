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
 * Property-based test for Property 19: Kelengkapan Informasi Tampilan Leaderboard.
 *
 * Verifies that Leaderboard::get_learner_rank() and display methods return
 * complete information with all required fields for any Learner.
 *
 * **Validates: Requirements 12.3**
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
 * Property 19: Kelengkapan Informasi Tampilan Leaderboard.
 *
 * For any Learner in the leaderboard, the output/display data must contain
 * ALL required fields with non-null values (except rank_change which can be 0).
 *
 * Required fields:
 *   - userid
 *   - courseid
 *   - scope
 *   - attendance_score
 *   - engagement_score
 *   - completion_score
 *   - total_score
 *   - current_rank
 *   - previous_rank
 *   - rank_change
 *   - points_to_next
 *   - display_name
 *   - last_updated
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class leaderboard_display_completeness_property_test extends \advanced_testcase {
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
    // P19: Completeness of leaderboard display information
    // =========================================================================

    /**
     * P19 (Field Completeness): For any Learner with a leaderboard entry,
     * get_learner_rank() must return an array containing ALL required fields.
     *
     * **Validates: Requirements 12.3**
     *
     * Test Strategy:
     * - Generate random scores for a learner
     * - Insert into leaderboard and update rankings
     * - Call get_learner_rank()
     * - Verify all required fields are present in the returned array
     *
     * @return void
     */
    public function test_property19_get_learner_rank_contains_all_required_fields(): void {
        $this->forAll(
            Generator\choose(0, 10000),  // attendance raw (÷100 → [0, 100])
            Generator\choose(0, 10000),  // engagement raw (÷100 → [0, 100])
            Generator\choose(0, 10000)   // completion raw (÷100 → [0, 100])
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

            // Update rankings.
            $this->lb->update_rankings((int) $course->id);

            // Fetch the rank data.
            $rank_data = $this->lb->get_learner_rank((int) $user->id, (int) $course->id);

            // INVARIANT: rank_data must not be empty.
            $this->assertNotEmpty(
                $rank_data,
                "Property 19 violated: get_learner_rank() returned empty array for user {$user->id}."
            );

            // INVARIANT: All required fields must be present.
            $required_fields = [
                'userid',
                'courseid',
                'scope',
                'attendance_score',
                'engagement_score',
                'completion_score',
                'total_score',
                'current_rank',
                'previous_rank',
                'rank_change',
                'points_to_next',
                'display_name',
                'last_updated',
            ];

            foreach ($required_fields as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $rank_data,
                    "Property 19 violated: Required field '{$field}' is missing from " .
                    "get_learner_rank() output for user {$user->id}. " .
                    "Leaderboard output must contain all required fields (Req 12.3)."
                );
            }

            // INVARIANT: userid must match the requested user.
            $this->assertEquals(
                (int) $user->id,
                $rank_data['userid'],
                "Property 19 violated: userid field does not match requested user."
            );

            // INVARIANT: courseid must match the requested course.
            $this->assertEquals(
                (int) $course->id,
                $rank_data['courseid'],
                "Property 19 violated: courseid field does not match requested course."
            );

            // INVARIANT: scope must be set.
            $this->assertNotEmpty(
                $rank_data['scope'],
                "Property 19 violated: scope field is empty."
            );

            // INVARIANT: current_rank must be > 0 after update_rankings().
            $this->assertGreaterThan(
                0,
                $rank_data['current_rank'],
                "Property 19 violated: current_rank must be > 0 after update_rankings()."
            );

            // INVARIANT: display_name must be a string (can be empty if privacy disabled).
            $this->assertIsString(
                $rank_data['display_name'],
                "Property 19 violated: display_name must be a string."
            );
        });
    }

    /**
     * P19 (Non-Null Values): For any Learner with a leaderboard entry,
     * all required fields must have non-null values (except previous_rank,
     * rank_change, and points_to_next which can be null for first-time entries).
     *
     * **Validates: Requirements 12.3**
     *
     * @return void
     */
    public function test_property19_required_fields_have_non_null_values(): void {
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

            $this->lb->upsert_learner_score(
                (int) $user->id,
                (int) $course->id,
                \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                $attendance,
                $engagement,
                $completion
            );

            $this->lb->update_rankings((int) $course->id);

            $rank_data = $this->lb->get_learner_rank((int) $user->id, (int) $course->id);

            // Fields that must NEVER be null.
            $non_nullable_fields = [
                'userid',
                'courseid',
                'scope',
                'attendance_score',
                'engagement_score',
                'completion_score',
                'total_score',
                'current_rank',
                'display_name',
                'last_updated',
            ];

            foreach ($non_nullable_fields as $field) {
                $this->assertNotNull(
                    $rank_data[$field],
                    "Property 19 violated: Required field '{$field}' is null for user {$user->id}. " .
                    "All required fields must have non-null values (Req 12.3)."
                );
            }

            // Fields that CAN be null for first-time entries.
            // (previous_rank, rank_change, points_to_next)
            // We don't assert non-null for these, but they must exist as keys.
            $this->assertArrayHasKey('previous_rank', $rank_data);
            $this->assertArrayHasKey('rank_change', $rank_data);
            $this->assertArrayHasKey('points_to_next', $rank_data);
        });
    }

    /**
     * P19 (Score Fields in Valid Range): For any Learner, all score fields
     * (attendance_score, engagement_score, completion_score, total_score)
     * must be in the valid range [0, 100].
     *
     * **Validates: Requirements 12.3**
     *
     * @return void
     */
    public function test_property19_score_fields_are_in_valid_range(): void {
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

            $this->lb->upsert_learner_score(
                (int) $user->id,
                (int) $course->id,
                \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                $attendance,
                $engagement,
                $completion
            );

            $this->lb->update_rankings((int) $course->id);

            $rank_data = $this->lb->get_learner_rank((int) $user->id, (int) $course->id);

            $score_fields = [
                'attendance_score',
                'engagement_score',
                'completion_score',
                'total_score',
            ];

            foreach ($score_fields as $field) {
                $value = $rank_data[$field];

                $this->assertGreaterThanOrEqual(
                    0.0,
                    $value,
                    "Property 19 violated: {$field}={$value} is below 0. " .
                    "All score fields must be in [0, 100] (Req 12.3)."
                );

                $this->assertLessThanOrEqual(
                    100.0,
                    $value,
                    "Property 19 violated: {$field}={$value} exceeds 100. " .
                    "All score fields must be in [0, 100] (Req 12.3)."
                );
            }
        });
    }

    /**
     * P19 (Multiple Learners): For any set of multiple learners in the same
     * course, get_scope_rankings() must return complete information for all
     * learners with all required fields.
     *
     * **Validates: Requirements 12.3**
     *
     * Test Strategy:
     * - Generate 2-10 learners with random scores
     * - Insert them into the leaderboard
     * - Call update_rankings()
     * - Call get_scope_rankings()
     * - Verify all returned records have all required fields
     *
     * @return void
     */
    public function test_property19_get_scope_rankings_returns_complete_info_for_all_learners(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 2 && count($arr) <= 10; },
                Generator\seq(Generator\choose(0, 10000))
            )
        )->then(function (array $raw_ints) {
            $scores = array_map(fn($v) => $v / 100.0, $raw_ints);

            $course = $this->getDataGenerator()->create_course();
            $users  = [];

            foreach ($scores as $score) {
                $user    = $this->getDataGenerator()->create_user();
                $users[] = $user;

                $this->lb->upsert_learner_score(
                    (int) $user->id,
                    (int) $course->id,
                    \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                    $score,  // attendance
                    $score,  // engagement
                    $score,  // completion
                );
            }

            $this->lb->update_rankings((int) $course->id);

            $rankings = $this->lb->get_scope_rankings((int) $course->id);

            // INVARIANT: Number of returned records must match number of learners.
            $this->assertCount(
                count($users),
                $rankings,
                "Property 19 violated: get_scope_rankings() returned " . count($rankings) .
                " records but expected " . count($users) . " learners."
            );

            $required_fields = [
                'userid',
                'courseid',
                'scope',
                'attendance_score',
                'engagement_score',
                'completion_score',
                'total_score',
                'current_rank',
                'previous_rank',
                'rank_change',
                'points_to_next',
                'display_name',
                'last_updated',
            ];

            foreach ($rankings as $i => $rank_data) {
                foreach ($required_fields as $field) {
                    $this->assertArrayHasKey(
                        $field,
                        $rank_data,
                        "Property 19 violated: Required field '{$field}' is missing from " .
                        "get_scope_rankings() output for learner at index {$i}. " .
                        "All leaderboard entries must contain all required fields (Req 12.3)."
                    );
                }

                // Verify non-nullable fields are not null.
                $non_nullable = [
                    'userid',
                    'courseid',
                    'scope',
                    'attendance_score',
                    'engagement_score',
                    'completion_score',
                    'total_score',
                    'current_rank',
                    'display_name',
                    'last_updated',
                ];

                foreach ($non_nullable as $field) {
                    $this->assertNotNull(
                        $rank_data[$field],
                        "Property 19 violated: Field '{$field}' is null in get_scope_rankings() " .
                        "output for learner at index {$i}."
                    );
                }
            }
        });
    }

    /**
     * P19 (Rank Consistency): For any set of learners, the current_rank field
     * in the output must be consistent with the ordering of total_score.
     *
     * **Validates: Requirements 12.3**
     *
     * @return void
     */
    public function test_property19_current_rank_is_consistent_with_score_ordering(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 2 && count($arr) <= 8; },
                Generator\seq(Generator\choose(0, 10000))
            )
        )->then(function (array $raw_ints) {
            $scores = array_values(array_unique(array_map(fn($v) => $v / 100.0, $raw_ints)));

            if (count($scores) < 2) {
                return;  // Skip if deduplication left fewer than 2 distinct scores.
            }

            $course = $this->getDataGenerator()->create_course();

            foreach ($scores as $score) {
                $user = $this->getDataGenerator()->create_user();
                $this->lb->upsert_learner_score(
                    (int) $user->id,
                    (int) $course->id,
                    \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                    $score,
                    $score,
                    $score
                );
            }

            $this->lb->update_rankings((int) $course->id);

            $rankings = $this->lb->get_scope_rankings((int) $course->id);

            // INVARIANT: Rankings must be ordered by current_rank ASC.
            for ($i = 0; $i < count($rankings) - 1; $i++) {
                $this->assertLessThan(
                    $rankings[$i + 1]['current_rank'],
                    $rankings[$i]['current_rank'],
                    "Property 19 violated: Rankings are not ordered by current_rank ASC. " .
                    "Rank at index {$i} is {$rankings[$i]['current_rank']}, " .
                    "but rank at index " . ($i + 1) . " is {$rankings[$i + 1]['current_rank']}."
                );

                // INVARIANT: Higher rank (lower number) must have higher or equal score.
                $this->assertGreaterThanOrEqual(
                    $rankings[$i + 1]['total_score'],
                    $rankings[$i]['total_score'],
                    "Property 19 violated: Learner with rank {$rankings[$i]['current_rank']} " .
                    "has score {$rankings[$i]['total_score']}, but learner with rank " .
                    "{$rankings[$i + 1]['current_rank']} has higher score {$rankings[$i + 1]['total_score']}. " .
                    "Rank ordering must be consistent with score ordering (Req 12.3)."
                );
            }
        });
    }

    /**
     * P19 (Display Name Format): For any Learner, the display_name field must
     * be a non-empty string when privacy mode is enabled, and must follow the
     * anonymization format (e.g., "J. D.").
     *
     * **Validates: Requirements 12.3, 12.5**
     *
     * @return void
     */
    public function test_property19_display_name_follows_privacy_format_when_enabled(): void {
        // Enable privacy mode.
        set_config('leaderboard_privacy', 1, 'block_attendanceleaderboard');

        $this->forAll(
            Generator\choose(0, 10000)
        )->then(function (int $raw_score) {
            $score = $raw_score / 100.0;

            $user   = $this->getDataGenerator()->create_user([
                'firstname' => 'John',
                'lastname'  => 'Doe',
            ]);
            $course = $this->getDataGenerator()->create_course();

            // Create a new leaderboard instance to pick up the updated config.
            $lb_privacy = new \block_attendanceleaderboard\leaderboard\leaderboard();

            $lb_privacy->upsert_learner_score(
                (int) $user->id,
                (int) $course->id,
                \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                $score,
                $score,
                $score
            );

            $lb_privacy->update_rankings((int) $course->id);

            $rank_data = $lb_privacy->get_learner_rank((int) $user->id, (int) $course->id);

            // INVARIANT: display_name must not be empty when privacy is enabled.
            $this->assertNotEmpty(
                $rank_data['display_name'],
                "Property 19 violated: display_name is empty when privacy mode is enabled. " .
                "Display name must be anonymized (Req 12.5)."
            );

            // INVARIANT: display_name must follow anonymization format (e.g., "J. D.").
            $this->assertMatchesRegularExpression(
                '/^[A-Z]\. [A-Z]\.$/',
                $rank_data['display_name'],
                "Property 19 violated: display_name='{$rank_data['display_name']}' does not " .
                "follow anonymization format 'X. Y.' when privacy mode is enabled (Req 12.5)."
            );
        });

        // Disable privacy mode for subsequent tests.
        set_config('leaderboard_privacy', 0, 'block_attendanceleaderboard');
    }

    /**
     * P19 (Points to Next): For any Learner not in rank 1, the points_to_next
     * field must be a non-negative number representing the score difference to
     * the next higher rank.
     *
     * **Validates: Requirements 12.3**
     *
     * @return void
     */
    public function test_property19_points_to_next_is_accurate_for_non_first_rank(): void {
        $this->forAll(
            Generator\suchThat(
                function ($arr) { return count($arr) >= 2 && count($arr) <= 8; },
                Generator\seq(Generator\choose(1000, 10000))  // Avoid zero scores
            )
        )->then(function (array $raw_ints) {
            $scores = array_values(array_unique(array_map(fn($v) => $v / 100.0, $raw_ints)));

            if (count($scores) < 2) {
                return;
            }

            $course = $this->getDataGenerator()->create_course();
            $users  = [];

            foreach ($scores as $score) {
                $user    = $this->getDataGenerator()->create_user();
                $users[] = $user;
                $this->lb->upsert_learner_score(
                    (int) $user->id,
                    (int) $course->id,
                    \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                    $score,
                    $score,
                    $score
                );
            }

            $this->lb->update_rankings((int) $course->id);

            $rankings = $this->lb->get_scope_rankings((int) $course->id);

            // For all learners except rank 1, verify points_to_next.
            for ($i = 1; $i < count($rankings); $i++) {
                $current = $rankings[$i];
                $above   = $rankings[$i - 1];

                $this->assertNotNull(
                    $current['points_to_next'],
                    "Property 19 violated: points_to_next is null for learner at rank " .
                    "{$current['current_rank']} (not rank 1). Must be a non-negative number (Req 12.3)."
                );

                $this->assertGreaterThanOrEqual(
                    0.0,
                    $current['points_to_next'],
                    "Property 19 violated: points_to_next={$current['points_to_next']} is negative " .
                    "for learner at rank {$current['current_rank']}."
                );

                // INVARIANT: points_to_next must equal the score difference.
                $expected_diff = $above['total_score'] - $current['total_score'];
                $this->assertEqualsWithDelta(
                    $expected_diff,
                    $current['points_to_next'],
                    0.01,
                    "Property 19 violated: points_to_next={$current['points_to_next']} does not " .
                    "match expected score difference={$expected_diff} between rank " .
                    "{$current['current_rank']} (score={$current['total_score']}) and rank " .
                    "{$above['current_rank']} (score={$above['total_score']}) (Req 12.3)."
                );
            }
        });
    }

    /**
     * P19 (Rank 1 Points to Next): For the learner at rank 1, points_to_next
     * must be null (no rank above).
     *
     * **Validates: Requirements 12.3**
     *
     * @return void
     */
    public function test_property19_rank_1_has_null_points_to_next(): void {
        $this->forAll(
            Generator\choose(0, 10000)
        )->then(function (int $raw_score) {
            $score = $raw_score / 100.0;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->lb->upsert_learner_score(
                (int) $user->id,
                (int) $course->id,
                \block_attendanceleaderboard\leaderboard\leaderboard::SCOPE_COURSE,
                $score,
                $score,
                $score
            );

            $this->lb->update_rankings((int) $course->id);

            $rank_data = $this->lb->get_learner_rank((int) $user->id, (int) $course->id);

            // INVARIANT: Single learner must be rank 1.
            $this->assertEquals(
                1,
                $rank_data['current_rank'],
                "Property 19 violated: Single learner should have rank=1."
            );

            // INVARIANT: Rank 1 must have null points_to_next.
            $this->assertNull(
                $rank_data['points_to_next'],
                "Property 19 violated: Learner at rank 1 has points_to_next=" .
                "{$rank_data['points_to_next']}, but it must be null (no rank above) (Req 12.3)."
            );
        });
    }
}
