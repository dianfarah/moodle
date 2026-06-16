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
 * Unit tests for ACMLS Leaderboard.
 *
 * Tests cover:
 *  1.  calculate_score() with default weights (0.4, 0.4, 0.2): 80*0.4+60*0.4+100*0.2 = 76.0
 *  2.  calculate_score() with custom weights (0.5, 0.3, 0.2): 100*0.5+80*0.3+60*0.2 = 86.0
 *  3.  calculate_score() with all zeros → 0.0
 *  4.  update_rankings() assigns rank 1 to highest score
 *  5.  update_rankings() calculates rank_change correctly (positive when rank improved)
 *  6.  update_rankings() calculates points_to_next correctly
 *  7.  get_learner_rank() returns correct data for existing learner
 *  8.  get_learner_rank() returns empty array for non-existent learner
 *  9.  anonymize_display() returns initials when privacy enabled
 * 10.  anonymize_display() returns full name when privacy disabled
 * 11.  trigger_achievement_prompt() is called when rank improves
 * 12.  Rankings are consistent with total scores (higher score = lower rank number)
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tests;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\leaderboard\leaderboard;

/**
 * Unit test class for Leaderboard.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_attendanceleaderboard\leaderboard\leaderboard
 */
class leaderboard_test extends \advanced_testcase {

    /** @var leaderboard System under test. */
    private leaderboard $leaderboard;

    /** @var \stdClass Test course. */
    private \stdClass $course;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Use default weights (no custom config set).
        $this->leaderboard = new leaderboard();
        $this->course      = $this->getDataGenerator()->create_course();
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Insert a leaderboard record directly into the DB.
     *
     * @param  int    $userid           Moodle user ID.
     * @param  int    $courseid         Moodle course ID.
     * @param  string $scope            Scope string.
     * @param  float  $attendance_score Attendance score.
     * @param  float  $engagement_score Engagement score.
     * @param  float  $completion_score Completion score.
     * @param  float  $total_score      Total composite score.
     * @param  int    $current_rank     Current rank (0 = unranked).
     * @param  int|null $previous_rank  Previous rank.
     * @param  int|null $rank_change    Rank change.
     * @return int                      Inserted record ID.
     */
    private function insert_leaderboard_record(
        int $userid,
        int $courseid,
        string $scope,
        float $attendance_score,
        float $engagement_score,
        float $completion_score,
        float $total_score,
        int $current_rank = 0,
        ?int $previous_rank = null,
        ?int $rank_change = null
    ): int {
        global $DB;

        $record                   = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->scope            = $scope;
        $record->attendance_score = $attendance_score;
        $record->engagement_score = $engagement_score;
        $record->completion_score = $completion_score;
        $record->total_score      = $total_score;
        $record->current_rank     = $current_rank;
        $record->previous_rank    = $previous_rank;
        $record->rank_change      = $rank_change;
        $record->points_to_next   = null;
        $record->display_name     = null;
        $record->last_updated     = time();

        return (int) $DB->insert_record(leaderboard::TABLE, $record);
    }

    // =========================================================================
    // Test 1: calculate_score() with default weights
    // =========================================================================

    /**
     * Test calculate_score() with default weights (0.4, 0.4, 0.2).
     *
     * Expected: 80*0.4 + 60*0.4 + 100*0.2 = 32 + 24 + 20 = 76.0
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::calculate_score
     */
    public function test_calculate_score_default_weights(): void {
        $score = $this->leaderboard->calculate_score(80.0, 60.0, 100.0);
        $this->assertEqualsWithDelta(76.0, $score, 0.001, 'Default weights: 80*0.4+60*0.4+100*0.2 should equal 76.0');
    }

    // =========================================================================
    // Test 2: calculate_score() with custom weights
    // =========================================================================

    /**
     * Test calculate_score() with custom weights (0.5, 0.3, 0.2).
     *
     * Expected: 100*0.5 + 80*0.3 + 60*0.2 = 50 + 24 + 12 = 86.0
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::calculate_score
     */
    public function test_calculate_score_custom_weights(): void {
        // Set custom weights via plugin config.
        set_config('weight_attendance', '0.5', 'block_attendanceleaderboard');
        set_config('weight_engagement', '0.3', 'block_attendanceleaderboard');
        set_config('weight_completion', '0.2', 'block_attendanceleaderboard');

        // Re-instantiate to pick up new config.
        $lb = new leaderboard();

        $score = $lb->calculate_score(100.0, 80.0, 60.0);
        $this->assertEqualsWithDelta(86.0, $score, 0.001, 'Custom weights: 100*0.5+80*0.3+60*0.2 should equal 86.0');
    }

    /**
     * Test that a configured zero weight is honoured and does not fall back to defaults.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::calculate_score
     */
    public function test_calculate_score_honours_zero_weight(): void {
        set_config('weight_attendance', '0', 'block_attendanceleaderboard');
        set_config('weight_engagement', '0.5', 'block_attendanceleaderboard');
        set_config('weight_completion', '0.5', 'block_attendanceleaderboard');

        $lb = new leaderboard();

        $score = $lb->calculate_score(100.0, 80.0, 60.0);
        $this->assertEqualsWithDelta(
            70.0,
            $score,
            0.001,
            'A zero attendance weight should stay at 0 and produce 80*0.5 + 60*0.5 = 70.0'
        );
    }

    // =========================================================================
    // Test 3: calculate_score() with all zeros
    // =========================================================================

    /**
     * Test calculate_score() with all zero inputs returns 0.0.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::calculate_score
     */
    public function test_calculate_score_all_zeros(): void {
        $score = $this->leaderboard->calculate_score(0.0, 0.0, 0.0);
        $this->assertEqualsWithDelta(0.0, $score, 0.001, 'All-zero inputs should produce score 0.0');
    }

    // =========================================================================
    // Test 4: update_rankings() assigns rank 1 to highest score
    // =========================================================================

    /**
     * Test that update_rankings() assigns rank 1 to the learner with the highest total_score.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::update_rankings
     */
    public function test_update_rankings_assigns_rank1_to_highest_score(): void {
        global $DB;

        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        $courseid = (int) $this->course->id;

        // User A has higher score.
        $this->insert_leaderboard_record((int) $user_a->id, $courseid, 'course', 90, 80, 70, 83.0);
        // User B has lower score.
        $this->insert_leaderboard_record((int) $user_b->id, $courseid, 'course', 50, 40, 30, 43.0);

        $this->leaderboard->update_rankings($courseid, 'course');

        $rank_a = $DB->get_field(leaderboard::TABLE, 'current_rank', ['userid' => $user_a->id, 'courseid' => $courseid]);
        $rank_b = $DB->get_field(leaderboard::TABLE, 'current_rank', ['userid' => $user_b->id, 'courseid' => $courseid]);

        $this->assertSame(1, (int) $rank_a, 'User A (highest score) should be rank 1');
        $this->assertSame(2, (int) $rank_b, 'User B (lower score) should be rank 2');
    }

    // =========================================================================
    // Test 5: update_rankings() calculates rank_change correctly
    // =========================================================================

    /**
     * Test that update_rankings() calculates rank_change correctly.
     *
     * A learner who was rank 3 and is now rank 1 should have rank_change = +2.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::update_rankings
     */
    public function test_update_rankings_calculates_rank_change_correctly(): void {
        global $DB;

        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        $courseid = (int) $this->course->id;

        // User A was rank 3, now has highest score → should become rank 1 → rank_change = +2.
        $this->insert_leaderboard_record((int) $user_a->id, $courseid, 'course', 90, 90, 90, 90.0, 3, 3, 0);
        // User B was rank 1, now has lower score → should become rank 2 → rank_change = -1.
        $this->insert_leaderboard_record((int) $user_b->id, $courseid, 'course', 50, 50, 50, 50.0, 1, 1, 0);

        $this->leaderboard->update_rankings($courseid, 'course');

        $rank_change_a = (int) $DB->get_field(leaderboard::TABLE, 'rank_change', ['userid' => $user_a->id, 'courseid' => $courseid]);
        $rank_change_b = (int) $DB->get_field(leaderboard::TABLE, 'rank_change', ['userid' => $user_b->id, 'courseid' => $courseid]);

        $this->assertSame(2, $rank_change_a, 'User A moved from rank 3 to rank 1: rank_change should be +2');
        $this->assertSame(-1, $rank_change_b, 'User B moved from rank 1 to rank 2: rank_change should be -1');
    }

    // =========================================================================
    // Test 6: update_rankings() calculates points_to_next correctly
    // =========================================================================

    /**
     * Test that update_rankings() calculates points_to_next correctly.
     *
     * Rank 1 should have points_to_next = null.
     * Rank 2 should have points_to_next = score_of_rank1 - score_of_rank2.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::update_rankings
     */
    public function test_update_rankings_calculates_points_to_next_correctly(): void {
        global $DB;

        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        $courseid = (int) $this->course->id;

        // User A: total_score = 90.0 → rank 1.
        $this->insert_leaderboard_record((int) $user_a->id, $courseid, 'course', 90, 90, 90, 90.0);
        // User B: total_score = 70.0 → rank 2.
        $this->insert_leaderboard_record((int) $user_b->id, $courseid, 'course', 70, 70, 70, 70.0);

        $this->leaderboard->update_rankings($courseid, 'course');

        $points_a = $DB->get_field(leaderboard::TABLE, 'points_to_next', ['userid' => $user_a->id, 'courseid' => $courseid]);
        $points_b = $DB->get_field(leaderboard::TABLE, 'points_to_next', ['userid' => $user_b->id, 'courseid' => $courseid]);

        $this->assertNull($points_a, 'Rank 1 should have points_to_next = null');
        $this->assertEqualsWithDelta(20.0, (float) $points_b, 0.01, 'Rank 2 should have points_to_next = 90 - 70 = 20.0');
    }

    // =========================================================================
    // Test 7: get_learner_rank() returns correct data for existing learner
    // =========================================================================

    /**
     * Test that get_learner_rank() returns correct data for an existing learner.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::get_learner_rank
     */
    public function test_get_learner_rank_returns_correct_data(): void {
        $user     = $this->getDataGenerator()->create_user();
        $courseid = (int) $this->course->id;

        $this->insert_leaderboard_record(
            (int) $user->id, $courseid, 'course',
            80.0, 60.0, 100.0, 76.0,
            2, 3, 1
        );

        $result = $this->leaderboard->get_learner_rank((int) $user->id, $courseid, 'course');

        $this->assertNotEmpty($result, 'get_learner_rank() should return data for existing learner');
        $this->assertSame((int) $user->id, $result['userid']);
        $this->assertSame($courseid, $result['courseid']);
        $this->assertSame('course', $result['scope']);
        $this->assertSame(2, $result['current_rank']);
        $this->assertSame(3, $result['previous_rank']);
        $this->assertSame(1, $result['rank_change']);
        $this->assertEqualsWithDelta(76.0, $result['total_score'], 0.01);
        $this->assertEqualsWithDelta(80.0, $result['attendance_score'], 0.01);
        $this->assertEqualsWithDelta(60.0, $result['engagement_score'], 0.01);
        $this->assertEqualsWithDelta(100.0, $result['completion_score'], 0.01);
    }

    // =========================================================================
    // Test 8: get_learner_rank() returns empty array for non-existent learner
    // =========================================================================

    /**
     * Test that get_learner_rank() returns an empty array when no record exists.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::get_learner_rank
     */
    public function test_get_learner_rank_returns_empty_array_for_nonexistent_learner(): void {
        $result = $this->leaderboard->get_learner_rank(99999, (int) $this->course->id, 'course');
        $this->assertIsArray($result, 'get_learner_rank() should return an array');
        $this->assertEmpty($result, 'get_learner_rank() should return empty array for non-existent learner');
    }

    // =========================================================================
    // Test 9: anonymize_display() returns initials when privacy enabled
    // =========================================================================

    /**
     * Test that anonymize_display() returns initials when privacy mode is enabled.
     *
     * Example: "John Doe" → "J. D."
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::anonymize_display
     */
    public function test_anonymize_display_returns_initials_when_privacy_enabled(): void {
        // Enable privacy mode.
        set_config('leaderboard_privacy', '1', 'block_attendanceleaderboard');
        $lb = new leaderboard();

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'John',
            'lastname'  => 'Doe',
        ]);

        $result = $lb->anonymize_display((int) $user->id);

        $this->assertSame('J. D.', $result, 'Privacy enabled: should return initials "J. D."');
    }

    // =========================================================================
    // Test 10: anonymize_display() returns full name when privacy disabled
    // =========================================================================

    /**
     * Test that anonymize_display() returns the full name when privacy mode is disabled.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::anonymize_display
     */
    public function test_anonymize_display_returns_full_name_when_privacy_disabled(): void {
        // Ensure privacy mode is disabled (default).
        set_config('leaderboard_privacy', '', 'block_attendanceleaderboard');
        $lb = new leaderboard();

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Jane',
            'lastname'  => 'Smith',
        ]);

        $result = $lb->anonymize_display((int) $user->id);

        // fullname() returns "Jane Smith" by default.
        $this->assertStringContainsString('Jane', $result, 'Privacy disabled: full name should contain firstname');
        $this->assertStringContainsString('Smith', $result, 'Privacy disabled: full name should contain lastname');
    }

    // =========================================================================
    // Test 11: trigger_achievement_prompt() is called when rank improves
    // =========================================================================

    /**
     * Test that trigger_achievement_prompt() executes without error when rank has improved.
     *
     * Since trigger_achievement_prompt() calls debugging() internally, we verify
     * it runs without throwing exceptions and that the leaderboard record reflects
     * a positive rank_change.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::trigger_achievement_prompt
     */
    public function test_trigger_achievement_prompt_when_rank_improves(): void {
        global $DB;

        $user     = $this->getDataGenerator()->create_user();
        $courseid = (int) $this->course->id;

        // Insert a record with positive rank_change (rank improved).
        $this->insert_leaderboard_record(
            (int) $user->id, $courseid, 'course',
            90, 90, 90, 90.0,
            1,  // current_rank
            3,  // previous_rank
            2   // rank_change = +2 (improved)
        );

        // Should not throw any exception.
        $this->leaderboard->trigger_achievement_prompt((int) $user->id, $courseid);

        // Verify the record still exists and rank_change is positive.
        $record = $DB->get_record(leaderboard::TABLE, ['userid' => $user->id, 'courseid' => $courseid]);
        $this->assertNotFalse($record, 'Leaderboard record should still exist after trigger');
        $this->assertGreaterThan(0, (int) $record->rank_change, 'rank_change should be positive for improved rank');
    }

    // =========================================================================
    // Test 12: Rankings are consistent with total scores
    // =========================================================================

    /**
     * Test that rankings are consistent: higher total_score always gets a lower rank number.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::update_rankings
     */
    public function test_rankings_consistent_with_total_scores(): void {
        global $DB;

        $courseid = (int) $this->course->id;

        // Create 4 users with different scores.
        $users  = [];
        $scores = [55.0, 90.0, 72.0, 38.0];

        foreach ($scores as $score) {
            $user    = $this->getDataGenerator()->create_user();
            $users[] = ['user' => $user, 'score' => $score];
            $this->insert_leaderboard_record(
                (int) $user->id, $courseid, 'course',
                $score, $score, $score, $score
            );
        }

        $this->leaderboard->update_rankings($courseid, 'course');

        // Fetch all updated records.
        $records = $DB->get_records(leaderboard::TABLE, ['courseid' => $courseid, 'scope' => 'course']);

        // Build a map of userid → rank.
        $rank_map = [];
        foreach ($records as $record) {
            $rank_map[(int) $record->userid] = [
                'rank'  => (int) $record->current_rank,
                'score' => (float) $record->total_score,
            ];
        }

        // Verify: for every pair, higher score → lower rank number.
        $rank_list = array_values($rank_map);
        for ($i = 0; $i < count($rank_list); $i++) {
            for ($j = $i + 1; $j < count($rank_list); $j++) {
                $a = $rank_list[$i];
                $b = $rank_list[$j];

                if ($a['score'] > $b['score']) {
                    $this->assertLessThan(
                        $b['rank'],
                        $a['rank'],
                        "User with score {$a['score']} should have lower rank number than user with score {$b['score']}"
                    );
                } elseif ($a['score'] < $b['score']) {
                    $this->assertGreaterThan(
                        $b['rank'],
                        $a['rank'],
                        "User with score {$a['score']} should have higher rank number than user with score {$b['score']}"
                    );
                }
            }
        }
    }

    // =========================================================================
    // Additional: upsert_learner_score() inserts new record
    // =========================================================================

    /**
     * Test that upsert_learner_score() inserts a new record when none exists.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::upsert_learner_score
     */
    public function test_upsert_learner_score_inserts_new_record(): void {
        global $DB;

        $user     = $this->getDataGenerator()->create_user();
        $courseid = (int) $this->course->id;

        $this->leaderboard->upsert_learner_score(
            (int) $user->id, $courseid, 'course',
            80.0, 60.0, 100.0
        );

        $record = $DB->get_record(leaderboard::TABLE, ['userid' => $user->id, 'courseid' => $courseid]);

        $this->assertNotFalse($record, 'upsert_learner_score() should insert a new record');
        $this->assertEqualsWithDelta(80.0, (float) $record->attendance_score, 0.01);
        $this->assertEqualsWithDelta(60.0, (float) $record->engagement_score, 0.01);
        $this->assertEqualsWithDelta(100.0, (float) $record->completion_score, 0.01);
        $this->assertEqualsWithDelta(76.0, (float) $record->total_score, 0.01, '80*0.4+60*0.4+100*0.2 = 76.0');
        $this->assertSame(0, (int) $record->current_rank, 'New record should have current_rank = 0');
        $this->assertNull($record->previous_rank, 'New record should have previous_rank = null');
    }

    // =========================================================================
    // Additional: upsert_learner_score() updates existing record
    // =========================================================================

    /**
     * Test that upsert_learner_score() updates an existing record.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::upsert_learner_score
     */
    public function test_upsert_learner_score_updates_existing_record(): void {
        global $DB;

        $user     = $this->getDataGenerator()->create_user();
        $courseid = (int) $this->course->id;

        // Insert initial record.
        $this->insert_leaderboard_record((int) $user->id, $courseid, 'course', 50, 50, 50, 50.0, 2);

        // Upsert with new scores.
        $this->leaderboard->upsert_learner_score(
            (int) $user->id, $courseid, 'course',
            90.0, 80.0, 70.0
        );

        $record = $DB->get_record(leaderboard::TABLE, ['userid' => $user->id, 'courseid' => $courseid]);

        $this->assertEqualsWithDelta(90.0, (float) $record->attendance_score, 0.01, 'attendance_score should be updated');
        $this->assertEqualsWithDelta(80.0, (float) $record->engagement_score, 0.01, 'engagement_score should be updated');
        $this->assertEqualsWithDelta(70.0, (float) $record->completion_score, 0.01, 'completion_score should be updated');
        // 90*0.4 + 80*0.4 + 70*0.2 = 36 + 32 + 14 = 82.0
        $this->assertEqualsWithDelta(82.0, (float) $record->total_score, 0.01, 'total_score should be recalculated');
    }

    // =========================================================================
    // Additional: scope support — program and institution scopes
    // =========================================================================

    /**
     * Test that leaderboard supports program and institution scopes independently.
     *
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::update_rankings
     * @covers \block_attendanceleaderboard\leaderboard\leaderboard::get_learner_rank
     */
    public function test_scope_support_program_and_institution(): void {
        global $DB;

        $user     = $this->getDataGenerator()->create_user();
        $courseid = (int) $this->course->id;

        // Insert records for different scopes.
        $this->insert_leaderboard_record((int) $user->id, $courseid, 'program', 80, 80, 80, 80.0);
        $this->insert_leaderboard_record((int) $user->id, $courseid, 'institution', 70, 70, 70, 70.0);

        $this->leaderboard->update_rankings($courseid, 'program');
        $this->leaderboard->update_rankings($courseid, 'institution');

        $rank_program     = $this->leaderboard->get_learner_rank((int) $user->id, $courseid, 'program');
        $rank_institution = $this->leaderboard->get_learner_rank((int) $user->id, $courseid, 'institution');

        $this->assertNotEmpty($rank_program, 'Should find rank for program scope');
        $this->assertNotEmpty($rank_institution, 'Should find rank for institution scope');
        $this->assertSame('program', $rank_program['scope']);
        $this->assertSame('institution', $rank_institution['scope']);
        $this->assertSame(1, $rank_program['current_rank'], 'Only learner in program scope should be rank 1');
        $this->assertSame(1, $rank_institution['current_rank'], 'Only learner in institution scope should be rank 1');
    }
}
