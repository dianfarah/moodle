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
 * Unit tests for ACMLS Coach.
 *
 * Tests cover:
 *  1.  determine_motivation_intervention() for Low+Low → 'recovery'
 *  2.  determine_motivation_intervention() for Low+Middle → 'persistence'
 *  3.  determine_motivation_intervention() for Middle+Low → 'recovery'
 *  4.  determine_motivation_intervention() for Middle+Middle → 'reinforcement'
 *  5.  determine_motivation_intervention() for High+Any → 'achievement'
 *  6.  recommend_resources() for Low performance returns difficulty=1 resources
 *  7.  recommend_resources() for High performance returns difficulty=2-3 resources
 *  8.  save_decision() inserts correct record into acmls_coach_decision
 *  9.  save_decision() stores reasoning and rules_triggered
 * 10.  get_leaderboard_factor() returns 1.0 when rank improved
 * 11.  get_leaderboard_factor() returns -0.5 when rank declined
 * 12.  get_leaderboard_factor() returns 0.0 when no leaderboard data
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tests;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\coach\coach;
use block_attendanceleaderboard\coach\coach_decision;
use block_attendanceleaderboard\coach\rule_engine;
use block_attendanceleaderboard\profiling\learner_profile;

/**
 * Unit test class for Coach.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_attendanceleaderboard\coach\coach
 */
class coach_test extends \advanced_testcase {

    /** @var coach System under test. */
    private coach $coach;

    /** @var rule_engine Rule engine instance. */
    private rule_engine $rule_engine;

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

        $this->rule_engine = new rule_engine();
        $this->rule_engine->load_default_rules();

        $this->coach  = new coach($this->rule_engine);
        $this->user   = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
    }

    // =========================================================================
    // Helper: build a LearnerProfile with specific performance + motivation.
    // =========================================================================

    /**
     * Build a LearnerProfile with the given performance_category and motivation_level.
     *
     * @param  int    $performance_category 1=Low, 2=Middle, 3=High.
     * @param  float  $motivation_level     0.0–100.0.
     * @param  string $learning_style       Learning style constant.
     * @return learner_profile
     */
    private function make_profile(
        int $performance_category,
        float $motivation_level,
        string $learning_style = learner_profile::STYLE_UNKNOWN
    ): learner_profile {
        $profile = new learner_profile((int) $this->user->id, (int) $this->course->id);
        $profile->performance_category = $performance_category;
        $profile->motivation_level     = $motivation_level;
        $profile->learning_style       = $learning_style;
        $profile->cognitive_level      = learner_profile::COGNITIVE_MIDDLE;
        return $profile;
    }

    // =========================================================================
    // Tests 1–5: determine_motivation_intervention()
    // =========================================================================

    /**
     * Test 1: Low performance + Low motivation → 'recovery'.
     *
     * @covers \block_attendanceleaderboard\coach\coach::determine_motivation_intervention
     */
    public function test_motivation_low_perf_low_motivation_is_recovery(): void {
        // Low motivation threshold default = 40; use 20 (clearly below).
        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, 20.0);
        $result  = $this->coach->determine_motivation_intervention($profile);
        $this->assertEquals(rule_engine::MOTIVATION_RECOVERY, $result,
            'Low performance + Low motivation should return recovery.');
    }

    /**
     * Test 2: Low performance + Middle motivation → 'persistence'.
     *
     * @covers \block_attendanceleaderboard\coach\coach::determine_motivation_intervention
     */
    public function test_motivation_low_perf_middle_motivation_is_persistence(): void {
        // Middle motivation: between threshold (40) and 70; use 55.
        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, 55.0);
        $result  = $this->coach->determine_motivation_intervention($profile);
        $this->assertEquals(rule_engine::MOTIVATION_PERSISTENCE, $result,
            'Low performance + Middle motivation should return persistence.');
    }

    /**
     * Test 3: Middle performance + Low motivation → 'recovery'.
     *
     * @covers \block_attendanceleaderboard\coach\coach::determine_motivation_intervention
     */
    public function test_motivation_middle_perf_low_motivation_is_recovery(): void {
        $profile = $this->make_profile(learner_profile::PERFORMANCE_MIDDLE, 25.0);
        $result  = $this->coach->determine_motivation_intervention($profile);
        $this->assertEquals(rule_engine::MOTIVATION_RECOVERY, $result,
            'Middle performance + Low motivation should return recovery.');
    }

    /**
     * Test 4: Middle performance + Middle motivation → 'reinforcement'.
     *
     * @covers \block_attendanceleaderboard\coach\coach::determine_motivation_intervention
     */
    public function test_motivation_middle_perf_middle_motivation_is_reinforcement(): void {
        $profile = $this->make_profile(learner_profile::PERFORMANCE_MIDDLE, 60.0);
        $result  = $this->coach->determine_motivation_intervention($profile);
        $this->assertEquals(rule_engine::MOTIVATION_REINFORCEMENT, $result,
            'Middle performance + Middle motivation should return reinforcement.');
    }

    /**
     * Test 5a: High performance + Low motivation → 'achievement'.
     *
     * @covers \block_attendanceleaderboard\coach\coach::determine_motivation_intervention
     */
    public function test_motivation_high_perf_any_motivation_is_achievement(): void {
        $profile = $this->make_profile(learner_profile::PERFORMANCE_HIGH, 20.0);
        $result  = $this->coach->determine_motivation_intervention($profile);
        $this->assertEquals(rule_engine::MOTIVATION_ACHIEVEMENT, $result,
            'High performance + any motivation should return achievement.');
    }

    /**
     * Test 5b: High performance + High motivation → 'achievement'.
     *
     * @covers \block_attendanceleaderboard\coach\coach::determine_motivation_intervention
     */
    public function test_motivation_high_perf_high_motivation_is_achievement(): void {
        $profile = $this->make_profile(learner_profile::PERFORMANCE_HIGH, 90.0);
        $result  = $this->coach->determine_motivation_intervention($profile);
        $this->assertEquals(rule_engine::MOTIVATION_ACHIEVEMENT, $result,
            'High performance + High motivation should return achievement.');
    }

    /**
     * Test 5c: Any performance + High motivation (>70) → 'achievement'.
     *
     * @covers \block_attendanceleaderboard\coach\coach::determine_motivation_intervention
     */
    public function test_motivation_any_perf_high_motivation_is_achievement(): void {
        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, 80.0);
        $result  = $this->coach->determine_motivation_intervention($profile);
        $this->assertEquals(rule_engine::MOTIVATION_ACHIEVEMENT, $result,
            'Any performance + High motivation (>70) should return achievement.');
    }

    // =========================================================================
    // Tests 6–7: recommend_resources()
    // =========================================================================

    /**
     * Test 6: recommend_resources() for Low performance returns difficulty=1 resources.
     *
     * @covers \block_attendanceleaderboard\coach\coach::recommend_resources
     */
    public function test_recommend_resources_low_performance_returns_difficulty_1(): void {
        global $DB;

        $courseid = (int) $this->course->id;

        // Insert resources with different difficulty levels.
        $this->insert_resource($courseid, 1, 'Resource Easy 1');
        $this->insert_resource($courseid, 1, 'Resource Easy 2');
        $this->insert_resource($courseid, 2, 'Resource Medium');
        $this->insert_resource($courseid, 3, 'Resource Hard');

        $profile   = $this->make_profile(learner_profile::PERFORMANCE_LOW, 50.0);
        $resources = $this->coach->recommend_resources($profile);

        $this->assertNotEmpty($resources, 'Should return at least one resource for Low performance.');

        foreach ($resources as $resource) {
            $this->assertEquals(1, (int) $resource->difficulty_level,
                'All resources for Low performance should have difficulty_level=1.');
        }
    }

    /**
     * Test 7: recommend_resources() for High performance returns difficulty=2-3 resources.
     *
     * @covers \block_attendanceleaderboard\coach\coach::recommend_resources
     */
    public function test_recommend_resources_high_performance_returns_difficulty_2_or_3(): void {
        global $DB;

        $courseid = (int) $this->course->id;

        // Insert resources with different difficulty levels.
        $this->insert_resource($courseid, 1, 'Resource Easy');
        $this->insert_resource($courseid, 2, 'Resource Medium 1');
        $this->insert_resource($courseid, 2, 'Resource Medium 2');
        $this->insert_resource($courseid, 3, 'Resource Hard 1');
        $this->insert_resource($courseid, 3, 'Resource Hard 2');

        $profile   = $this->make_profile(learner_profile::PERFORMANCE_HIGH, 80.0);
        $resources = $this->coach->recommend_resources($profile);

        $this->assertNotEmpty($resources, 'Should return at least one resource for High performance.');

        foreach ($resources as $resource) {
            $this->assertContains((int) $resource->difficulty_level, [2, 3],
                'All resources for High performance should have difficulty_level 2 or 3.');
        }
    }

    /**
     * Additional: recommend_resources() for Middle performance returns difficulty=1-2.
     *
     * @covers \block_attendanceleaderboard\coach\coach::recommend_resources
     */
    public function test_recommend_resources_middle_performance_returns_difficulty_1_or_2(): void {
        $courseid = (int) $this->course->id;

        $this->insert_resource($courseid, 1, 'Resource Easy');
        $this->insert_resource($courseid, 2, 'Resource Medium');
        $this->insert_resource($courseid, 3, 'Resource Hard');

        $profile   = $this->make_profile(learner_profile::PERFORMANCE_MIDDLE, 55.0);
        $resources = $this->coach->recommend_resources($profile);

        $this->assertNotEmpty($resources, 'Should return at least one resource for Middle performance.');

        foreach ($resources as $resource) {
            $this->assertContains((int) $resource->difficulty_level, [1, 2],
                'All resources for Middle performance should have difficulty_level 1 or 2.');
        }
    }

    // =========================================================================
    // Tests 8–9: save_decision()
    // =========================================================================

    /**
     * Test 8: save_decision() inserts correct record into acmls_coach_decision.
     *
     * @covers \block_attendanceleaderboard\coach\coach::save_decision
     */
    public function test_save_decision_inserts_correct_record(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $decision                        = new coach_decision($userid, $courseid, coach_decision::TYPE_RESOURCE);
        $decision->input_profile         = ['userid' => $userid, 'performance_category' => 1];
        $decision->recommended_resources = [10, 20, 30];
        $decision->motivation_category   = rule_engine::MOTIVATION_PERSISTENCE;
        $decision->reasoning             = 'Test reasoning.';
        $decision->rules_triggered       = ['rule_4'];

        $before = time();
        $id     = $this->coach->save_decision($decision);
        $after  = time();

        $this->assertGreaterThan(0, $id, 'save_decision() should return a positive record ID.');
        $this->assertEquals($id, $decision->id, 'Decision id property should be updated after save.');

        $record = $DB->get_record('acmls_coach_decision', ['id' => $id]);
        $this->assertNotFalse($record, 'Record should exist in acmls_coach_decision.');

        $this->assertEquals($userid,   (int) $record->userid);
        $this->assertEquals($courseid, (int) $record->courseid);
        $this->assertEquals(coach_decision::TYPE_RESOURCE, $record->decision_type);
        $this->assertEquals(rule_engine::MOTIVATION_PERSISTENCE, $record->motivation_category);

        $this->assertGreaterThanOrEqual($before, (int) $record->timecreated);
        $this->assertLessThanOrEqual($after,     (int) $record->timecreated);

        // Verify JSON fields.
        $profile_data = json_decode($record->input_profile, true);
        $this->assertEquals($userid, $profile_data['userid']);

        $resources = json_decode($record->recommended_resources, true);
        $this->assertEquals([10, 20, 30], $resources);
    }

    /**
     * Test 9: save_decision() stores reasoning and rules_triggered correctly.
     *
     * @covers \block_attendanceleaderboard\coach\coach::save_decision
     */
    public function test_save_decision_stores_reasoning_and_rules_triggered(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $reasoning       = 'Rule rule_1 triggered (actions: difficulty_level=1, filter_learning_style=visual). ' .
                           'Motivation category: recovery. Leaderboard factor: 0.';
        $rules_triggered = ['rule_1', 'rule_4'];

        $decision                  = new coach_decision($userid, $courseid, coach_decision::TYPE_RESOURCE);
        $decision->reasoning       = $reasoning;
        $decision->rules_triggered = $rules_triggered;

        $id = $this->coach->save_decision($decision);

        $record = $DB->get_record('acmls_coach_decision', ['id' => $id]);
        $this->assertNotFalse($record);

        $this->assertEquals($reasoning, $record->reasoning,
            'Reasoning should be stored verbatim.');

        $stored_rules = json_decode($record->rules_triggered, true);
        $this->assertEquals($rules_triggered, $stored_rules,
            'rules_triggered should be stored as JSON array.');
    }

    // =========================================================================
    // Tests 10–12: get_leaderboard_factor()
    // =========================================================================

    /**
     * Test 10: get_leaderboard_factor() returns 1.0 when rank improved.
     *
     * @covers \block_attendanceleaderboard\coach\coach::get_leaderboard_factor
     */
    public function test_get_leaderboard_factor_returns_1_when_rank_improved(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Insert leaderboard record with positive rank_change.
        $this->insert_leaderboard_record($userid, $courseid, 3); // rank_change = +3 (improved).

        $factor = $this->coach->get_leaderboard_factor($userid, $courseid);
        $this->assertEqualsWithDelta(1.0, $factor, 0.001,
            'get_leaderboard_factor() should return 1.0 when rank improved (rank_change > 0).');
    }

    /**
     * Test 11: get_leaderboard_factor() returns -0.5 when rank declined.
     *
     * @covers \block_attendanceleaderboard\coach\coach::get_leaderboard_factor
     */
    public function test_get_leaderboard_factor_returns_negative_0_5_when_rank_declined(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Insert leaderboard record with negative rank_change.
        $this->insert_leaderboard_record($userid, $courseid, -2); // rank_change = -2 (declined).

        $factor = $this->coach->get_leaderboard_factor($userid, $courseid);
        $this->assertEqualsWithDelta(-0.5, $factor, 0.001,
            'get_leaderboard_factor() should return -0.5 when rank declined (rank_change < 0).');
    }

    /**
     * Test 12: get_leaderboard_factor() returns 0.0 when no leaderboard data.
     *
     * @covers \block_attendanceleaderboard\coach\coach::get_leaderboard_factor
     */
    public function test_get_leaderboard_factor_returns_0_when_no_data(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // No leaderboard record inserted.
        $factor = $this->coach->get_leaderboard_factor($userid, $courseid);
        $this->assertEqualsWithDelta(0.0, $factor, 0.001,
            'get_leaderboard_factor() should return 0.0 when no leaderboard data exists.');
    }

    /**
     * Additional: get_leaderboard_factor() returns 0.0 when rank_change = 0.
     *
     * @covers \block_attendanceleaderboard\coach\coach::get_leaderboard_factor
     */
    public function test_get_leaderboard_factor_returns_0_when_rank_unchanged(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $this->insert_leaderboard_record($userid, $courseid, 0); // rank_change = 0.

        $factor = $this->coach->get_leaderboard_factor($userid, $courseid);
        $this->assertEqualsWithDelta(0.0, $factor, 0.001,
            'get_leaderboard_factor() should return 0.0 when rank_change = 0.');
    }

    // =========================================================================
    // Additional: CoachDecision serialisation round-trip.
    // =========================================================================

    /**
     * Test that CoachDecision::to_db_record() and from_db_record() round-trip correctly.
     *
     * @covers \block_attendanceleaderboard\coach\coach_decision::to_db_record
     * @covers \block_attendanceleaderboard\coach\coach_decision::from_db_record
     */
    public function test_coach_decision_db_record_round_trip(): void {
        $original = new coach_decision(42, 7, coach_decision::TYPE_MOTIVATION);
        $original->id                    = 99;
        $original->input_profile         = ['userid' => 42, 'performance_category' => 2];
        $original->recommended_resources = [1, 2, 3];
        $original->motivation_category   = rule_engine::MOTIVATION_REINFORCEMENT;
        $original->reasoning             = 'Test reasoning for round-trip.';
        $original->rules_triggered       = ['rule_3', 'rule_6'];
        $original->learner_response      = coach_decision::RESPONSE_ACCESSED;
        $original->response_time         = 1700000000;
        $original->timecreated           = 1699999999;

        $db_record = $original->to_db_record();
        $restored  = coach_decision::from_db_record($db_record);

        $this->assertEquals($original->id,                    $restored->id);
        $this->assertEquals($original->userid,                $restored->userid);
        $this->assertEquals($original->courseid,              $restored->courseid);
        $this->assertEquals($original->decision_type,         $restored->decision_type);
        $this->assertEquals($original->input_profile,         $restored->input_profile);
        $this->assertEquals($original->recommended_resources, $restored->recommended_resources);
        $this->assertEquals($original->motivation_category,   $restored->motivation_category);
        $this->assertEquals($original->reasoning,             $restored->reasoning);
        $this->assertEquals($original->rules_triggered,       $restored->rules_triggered);
        $this->assertEquals($original->learner_response,      $restored->learner_response);
        $this->assertEquals($original->response_time,         $restored->response_time);
        $this->assertEquals($original->timecreated,           $restored->timecreated);
    }

    // =========================================================================
    // Additional: RuleEngine evaluate() tests.
    // =========================================================================

    /**
     * Test that RuleEngine matches Rule 1 for Low+visual profile.
     *
     * @covers \block_attendanceleaderboard\coach\rule_engine::evaluate
     */
    public function test_rule_engine_matches_rule_1_for_low_visual(): void {
        $profile = $this->make_profile(
            learner_profile::PERFORMANCE_LOW,
            50.0,
            learner_profile::STYLE_VISUAL
        );

        $matched  = $this->rule_engine->evaluate($profile);
        $rule_ids = array_column($matched, 'id');

        $this->assertContains('rule_1', $rule_ids,
            'Rule 1 should match for Low performance + visual learning style.');
    }

    /**
     * Test that RuleEngine matches Rule 2 for High+cognitive_level=3 profile.
     *
     * @covers \block_attendanceleaderboard\coach\rule_engine::evaluate
     */
    public function test_rule_engine_matches_rule_2_for_high_cognitive(): void {
        $profile = $this->make_profile(learner_profile::PERFORMANCE_HIGH, 80.0);
        $profile->cognitive_level = learner_profile::COGNITIVE_HIGH;

        $matched  = $this->rule_engine->evaluate($profile);
        $rule_ids = array_column($matched, 'id');

        $this->assertContains('rule_2', $rule_ids,
            'Rule 2 should match for High performance + cognitive_level >= 3.');
    }

    /**
     * Test that RuleEngine matches Rule 3 for low motivation + declining rank.
     *
     * @covers \block_attendanceleaderboard\coach\rule_engine::evaluate
     */
    public function test_rule_engine_matches_rule_3_for_low_motivation_declining_rank(): void {
        $profile = $this->make_profile(learner_profile::PERFORMANCE_MIDDLE, 30.0);

        $matched  = $this->rule_engine->evaluate($profile, ['rank_change' => -1]);
        $rule_ids = array_column($matched, 'id');

        $this->assertContains('rule_3', $rule_ids,
            'Rule 3 should match for motivation_level < 50 + rank_change < 0.');
    }

    /**
     * Test that get_resource_query_params returns difficulty=1 for Low profile.
     *
     * @covers \block_attendanceleaderboard\coach\rule_engine::get_resource_query_params
     */
    public function test_rule_engine_query_params_low_performance(): void {
        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, 50.0);
        $params  = $this->rule_engine->get_resource_query_params($profile);

        $this->assertArrayHasKey('difficulty_level', $params);
        $this->assertEquals(1, $params['difficulty_level'],
            'Low performance should produce difficulty_level=1 query params.');
    }

    /**
     * Test that get_resource_query_params returns difficulty=3 for High profile.
     *
     * @covers \block_attendanceleaderboard\coach\rule_engine::get_resource_query_params
     */
    public function test_rule_engine_query_params_high_performance(): void {
        $profile = $this->make_profile(learner_profile::PERFORMANCE_HIGH, 80.0);
        $params  = $this->rule_engine->get_resource_query_params($profile);

        $this->assertArrayHasKey('difficulty_level', $params);
        $this->assertEquals(3, $params['difficulty_level'],
            'High performance should produce difficulty_level=3 query params.');
    }

    // =========================================================================
    // Private helpers.
    // =========================================================================

    /**
     * Insert a learning resource record into acmls_learning_resource.
     *
     * @param  int    $courseid         Course ID.
     * @param  int    $difficulty_level Difficulty level (1-3).
     * @param  string $title            Resource title.
     * @param  string $learning_style   Learning style (default: 'reading').
     * @return int                      Inserted record ID.
     */
    private function insert_resource(
        int $courseid,
        int $difficulty_level,
        string $title,
        string $learning_style = 'reading'
    ): int {
        global $DB;

        // Use a unique cmid to avoid duplicate key errors across tests.
        static $cmid_counter = 1000;
        $cmid_counter++;

        $record = new \stdClass();
        $record->courseid          = $courseid;
        $record->cmid              = $cmid_counter;
        $record->title             = $title;
        $record->resource_type     = 'document';
        $record->difficulty_level  = $difficulty_level;
        $record->topic_tags        = json_encode([]);
        $record->learning_styles   = json_encode([$learning_style]);
        $record->access_count      = 0;
        $record->avg_rating        = null;
        $record->effectiveness_score = null;
        $record->is_active         = 1;
        $record->timecreated       = time();
        $record->timemodified      = time();

        return (int) $DB->insert_record('acmls_learning_resource', $record);
    }

    /**
     * Insert a leaderboard record into acmls_leaderboard.
     *
     * @param  int $userid      Moodle user ID.
     * @param  int $courseid    Moodle course ID.
     * @param  int $rank_change Rank change value (positive = improved, negative = declined).
     * @return int              Inserted record ID.
     */
    private function insert_leaderboard_record(int $userid, int $courseid, int $rank_change): int {
        global $DB;

        $record = new \stdClass();
        $record->userid            = $userid;
        $record->courseid          = $courseid;
        $record->scope             = 'course';
        $record->attendance_score  = 50.0;
        $record->engagement_score  = 50.0;
        $record->completion_score  = 50.0;
        $record->total_score       = 50.0;
        $record->current_rank      = 5;
        $record->previous_rank     = 5 - $rank_change;
        $record->rank_change       = $rank_change;
        $record->points_to_next    = 10.0;
        $record->display_name      = 'Test User';
        $record->last_updated      = time();

        return (int) $DB->insert_record('acmls_leaderboard', $record);
    }
}
