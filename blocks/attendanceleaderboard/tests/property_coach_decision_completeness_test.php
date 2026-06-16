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
 * Property-based test for Property 10: Kelengkapan Pencatatan Keputusan Coach.
 *
 * Verifies that every Coach decision is saved with reasoning BEFORE being sent
 * to the Delivery System — no decision should reach the Delivery System without
 * first being persisted with its reasoning.
 *
 * **Validates: Requirements 4.6**
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
require_once($CFG->dirroot . '/blocks/attendanceleaderboard/tests/property_based/vendor/autoload.php');

/**
 * Property 10: Kelengkapan Pencatatan Keputusan Coach.
 *
 * For any valid Learner_Profile input, every Coach decision that is sent to the
 * Delivery System must have been previously saved to the database
 * (acmls_coach_decision table) with a non-empty reasoning field.
 *
 * Key invariants tested:
 * 1. save_decision() is always called BEFORE the decision is returned/sent to
 *    Delivery System — the returned AdaptiveIntervention carries a decision_id
 *    that already exists in the database.
 * 2. Every saved decision has a non-empty `reasoning` field.
 * 3. Every saved decision has a non-empty `rules_triggered` field (or at least
 *    a non-null one — fallback decisions may have an empty array but must still
 *    have reasoning).
 * 4. The decision saved to DB matches the decision sent to Delivery System
 *    (same userid, courseid, decision_type).
 * 5. For any combination of Performance_Category (Low/Middle/High) ×
 *    Learning_Style (visual/auditory/reading/kinesthetic), the Coach always
 *    saves before delivering.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class property_coach_decision_completeness_test extends \advanced_testcase {
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

    // =========================================================================
    // PROPERTY 10: Kelengkapan Pencatatan Keputusan Coach
    // =========================================================================

    /**
     * Property 10 (Core): Every Coach decision sent to Delivery System must
     * have been previously saved to the database with a non-empty reasoning.
     *
     * **Validates: Requirements 4.6**
     *
     * Specification: For ANY valid Learner_Profile, when Coach.evaluate_profile()
     * returns an AdaptiveIntervention (which is what gets sent to Delivery System),
     * the following invariants MUST hold:
     *
     *   1. The returned intervention carries a non-null decision_id.
     *   2. A record with that decision_id EXISTS in acmls_coach_decision.
     *   3. The saved record has a non-empty `reasoning` field.
     *   4. The saved record has a non-null `rules_triggered` field.
     *   5. The saved record's userid and courseid match the profile.
     *
     * This is the "save-before-send" invariant: the decision is persisted BEFORE
     * the intervention is returned to the caller (Delivery System).
     *
     * Test Strategy:
     * - Generate random Performance_Category (1=Low, 2=Middle, 3=High)
     * - Generate random Learning_Style (visual/auditory/reading/kinesthetic)
     * - Generate random Motivation_Level (0–100)
     * - Create a Learner_Profile with those values
     * - Call Coach.evaluate_profile()
     * - Verify the returned intervention has a decision_id
     * - Verify the decision_id exists in acmls_coach_decision
     * - Verify the saved record has non-empty reasoning
     *
     * @return void
     */
    public function test_property10_save_before_send_invariant() {
        $this->forAll(
            Generator\choose(1, 3),  // Performance_Category: 1=Low, 2=Middle, 3=High
            Generator\elements(['visual', 'auditory', 'reading', 'kinesthetic']),  // Learning_Style
            Generator\choose(0, 100)  // Motivation_Level
        )->then(function ($performance_category, $learning_style, $motivation_level) {
            global $DB;

            // Setup test user and course.
            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            // Create learning resources so Coach has something to recommend.
            $this->create_resources_for_all_difficulties($course->id, $learning_style);

            // Build a Learner_Profile with the generated values.
            $profile = $this->create_test_profile(
                $user->id,
                $course->id,
                $performance_category,
                $learning_style,
                (float) $motivation_level
            );

            // Count decisions BEFORE calling evaluate_profile().
            $count_before = $DB->count_records('acmls_coach_decision', [
                'userid'   => $user->id,
                'courseid' => $course->id,
            ]);

            // Call evaluate_profile() — this is what the Delivery System receives.
            $coach        = new \block_attendanceleaderboard\coach\coach();
            $intervention = $coach->evaluate_profile($profile);

            // ================================================================
            // INVARIANT 1: The returned intervention must carry a decision_id.
            // ================================================================
            $this->assertNotNull(
                $intervention->decision_id,
                "Property 10 violated: evaluate_profile() returned an AdaptiveIntervention " .
                "with decision_id=null. Every decision sent to Delivery System must have " .
                "been saved first (Req 4.6). " .
                "Profile: performance_category={$performance_category}, " .
                "learning_style={$learning_style}, motivation_level={$motivation_level}."
            );

            // ================================================================
            // INVARIANT 2: A record with that decision_id must exist in DB.
            // ================================================================
            $saved_record = $DB->get_record(
                'acmls_coach_decision',
                ['id' => $intervention->decision_id],
                '*',
                IGNORE_MISSING
            );

            $this->assertNotFalse(
                $saved_record,
                "Property 10 violated: decision_id={$intervention->decision_id} returned by " .
                "evaluate_profile() does NOT exist in acmls_coach_decision table. " .
                "The decision must be saved BEFORE being sent to Delivery System (Req 4.6). " .
                "Profile: performance_category={$performance_category}, " .
                "learning_style={$learning_style}, motivation_level={$motivation_level}."
            );

            // ================================================================
            // INVARIANT 3: The saved record must have a non-empty reasoning.
            // ================================================================
            $this->assertNotEmpty(
                $saved_record->reasoning,
                "Property 10 violated: Decision ID={$intervention->decision_id} was saved to " .
                "acmls_coach_decision but has an EMPTY reasoning field. " .
                "Every saved decision must include reasoning for audit purposes (Req 4.6). " .
                "Profile: performance_category={$performance_category}, " .
                "learning_style={$learning_style}, motivation_level={$motivation_level}."
            );

            // ================================================================
            // INVARIANT 4: The saved record must have a non-null rules_triggered.
            // ================================================================
            $this->assertNotNull(
                $saved_record->rules_triggered,
                "Property 10 violated: Decision ID={$intervention->decision_id} has " .
                "rules_triggered=null. The field must be set (even if empty array '[]') " .
                "to document which rules were evaluated (Req 4.6). " .
                "Profile: performance_category={$performance_category}, " .
                "learning_style={$learning_style}, motivation_level={$motivation_level}."
            );

            // ================================================================
            // INVARIANT 5: The saved record must match the profile's userid/courseid.
            // ================================================================
            $this->assertEquals(
                $user->id,
                (int) $saved_record->userid,
                "Property 10 violated: Saved decision has userid={$saved_record->userid} " .
                "but profile has userid={$user->id}. The decision must belong to the correct Learner."
            );

            $this->assertEquals(
                $course->id,
                (int) $saved_record->courseid,
                "Property 10 violated: Saved decision has courseid={$saved_record->courseid} " .
                "but profile has courseid={$course->id}. The decision must belong to the correct course."
            );

            // ================================================================
            // INVARIANT 6: Exactly one new decision was saved (no duplicates).
            // ================================================================
            $count_after = $DB->count_records('acmls_coach_decision', [
                'userid'   => $user->id,
                'courseid' => $course->id,
            ]);

            $this->assertEquals(
                $count_before + 1,
                $count_after,
                "Property 10 violated: Expected exactly 1 new decision to be saved, " .
                "but count changed from {$count_before} to {$count_after}. " .
                "Profile: performance_category={$performance_category}, " .
                "learning_style={$learning_style}, motivation_level={$motivation_level}."
            );
        });
    }

    /**
     * Property 10 (Reasoning Non-Empty): For all Performance_Category ×
     * Learning_Style combinations, the reasoning field is always non-empty.
     *
     * **Validates: Requirements 4.6**
     *
     * This test exhaustively covers all 12 combinations of the 3 performance
     * categories and 4 learning styles to ensure no combination produces an
     * empty reasoning string.
     *
     * @return void
     */
    public function test_property10_reasoning_always_non_empty_all_combinations() {
        $performance_categories = [1, 2, 3];  // Low, Middle, High
        $learning_styles        = ['visual', 'auditory', 'reading', 'kinesthetic'];

        foreach ($performance_categories as $performance_category) {
            foreach ($learning_styles as $learning_style) {
                global $DB;

                $user   = $this->getDataGenerator()->create_user();
                $course = $this->getDataGenerator()->create_course();

                $this->create_resources_for_all_difficulties($course->id, $learning_style);

                $profile = $this->create_test_profile(
                    $user->id,
                    $course->id,
                    $performance_category,
                    $learning_style,
                    50.0
                );

                $coach        = new \block_attendanceleaderboard\coach\coach();
                $intervention = $coach->evaluate_profile($profile);

                // Fetch the saved decision.
                $this->assertNotNull(
                    $intervention->decision_id,
                    "Property 10 violated: No decision_id for " .
                    "performance_category={$performance_category}, learning_style={$learning_style}"
                );

                $saved = $DB->get_record(
                    'acmls_coach_decision',
                    ['id' => $intervention->decision_id],
                    'reasoning, rules_triggered',
                    MUST_EXIST
                );

                $this->assertNotEmpty(
                    $saved->reasoning,
                    "Property 10 violated: Empty reasoning for " .
                    "performance_category={$performance_category}, learning_style={$learning_style}. " .
                    "All 12 combinations must produce non-empty reasoning (Req 4.6)."
                );

                $this->assertNotNull(
                    $saved->rules_triggered,
                    "Property 10 violated: Null rules_triggered for " .
                    "performance_category={$performance_category}, learning_style={$learning_style}."
                );
            }
        }
    }

    /**
     * Property 10 (DB Matches Intervention): The decision saved to DB must
     * match the decision sent to Delivery System.
     *
     * **Validates: Requirements 4.6**
     *
     * Verifies that the decision_type, userid, and courseid in the database
     * record are consistent with the AdaptiveIntervention returned to the
     * Delivery System.
     *
     * @return void
     */
    public function test_property10_db_record_matches_intervention() {
        $this->forAll(
            Generator\choose(1, 3),  // Performance_Category
            Generator\elements(['visual', 'auditory', 'reading', 'kinesthetic'])  // Learning_Style
        )->then(function ($performance_category, $learning_style) {
            global $DB;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->create_resources_for_all_difficulties($course->id, $learning_style);

            $profile = $this->create_test_profile(
                $user->id,
                $course->id,
                $performance_category,
                $learning_style,
                50.0
            );

            $coach        = new \block_attendanceleaderboard\coach\coach();
            $intervention = $coach->evaluate_profile($profile);

            $this->assertNotNull(
                $intervention->decision_id,
                "Property 10 violated: No decision_id in returned intervention."
            );

            $saved = $DB->get_record(
                'acmls_coach_decision',
                ['id' => $intervention->decision_id],
                '*',
                MUST_EXIST
            );

            // The DB record's userid must match the profile's userid.
            $this->assertEquals(
                $user->id,
                (int) $saved->userid,
                "Property 10 violated: DB record userid={$saved->userid} does not match " .
                "profile userid={$user->id}. Decision sent to Delivery System must match DB record."
            );

            // The DB record's courseid must match the profile's courseid.
            $this->assertEquals(
                $course->id,
                (int) $saved->courseid,
                "Property 10 violated: DB record courseid={$saved->courseid} does not match " .
                "profile courseid={$course->id}. Decision sent to Delivery System must match DB record."
            );

            // The DB record's decision_type must be a valid type.
            $valid_types = [
                \block_attendanceleaderboard\coach\coach_decision::TYPE_RESOURCE,
                \block_attendanceleaderboard\coach\coach_decision::TYPE_MOTIVATION,
            ];
            $this->assertContains(
                $saved->decision_type,
                $valid_types,
                "Property 10 violated: DB record has invalid decision_type='{$saved->decision_type}'. " .
                "Must be one of: " . implode(', ', $valid_types)
            );

            // The reasoning in the DB must match the reasoning in the intervention.
            $this->assertEquals(
                $intervention->reasoning,
                $saved->reasoning,
                "Property 10 violated: Reasoning in DB does not match reasoning in AdaptiveIntervention. " .
                "The decision sent to Delivery System must be identical to what was saved."
            );
        });
    }

    /**
     * Property 10 (Temporal Ordering): The decision must be saved BEFORE the
     * intervention is returned — verified by checking that the DB record exists
     * at the moment evaluate_profile() returns.
     *
     * **Validates: Requirements 4.6**
     *
     * This test verifies the temporal ordering guarantee: when evaluate_profile()
     * returns, the decision is already in the database. There is no window where
     * the Delivery System could receive the intervention before the DB write.
     *
     * @return void
     */
    public function test_property10_temporal_ordering_save_before_return() {
        $this->forAll(
            Generator\choose(1, 3),  // Performance_Category
            Generator\choose(0, 100)  // Motivation_Level
        )->then(function ($performance_category, $motivation_level) {
            global $DB;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->create_resources_for_all_difficulties($course->id, 'visual');

            $profile = $this->create_test_profile(
                $user->id,
                $course->id,
                $performance_category,
                'visual',
                (float) $motivation_level
            );

            // Record the timestamp just before calling evaluate_profile().
            $time_before = time();

            $coach        = new \block_attendanceleaderboard\coach\coach();
            $intervention = $coach->evaluate_profile($profile);

            // Record the timestamp just after evaluate_profile() returns.
            $time_after = time();

            $this->assertNotNull(
                $intervention->decision_id,
                "Property 10 violated: No decision_id returned."
            );

            // The DB record must exist NOW (immediately after evaluate_profile returns).
            $saved = $DB->get_record(
                'acmls_coach_decision',
                ['id' => $intervention->decision_id],
                'id, timecreated, reasoning',
                MUST_EXIST
            );

            // The timecreated must be within the window [time_before, time_after].
            $this->assertGreaterThanOrEqual(
                $time_before,
                (int) $saved->timecreated,
                "Property 10 violated: Decision timecreated={$saved->timecreated} is BEFORE " .
                "evaluate_profile() was called (time_before={$time_before}). " .
                "This indicates a stale or incorrect record was matched."
            );

            $this->assertLessThanOrEqual(
                $time_after + 1,  // +1 second tolerance for clock skew
                (int) $saved->timecreated,
                "Property 10 violated: Decision timecreated={$saved->timecreated} is AFTER " .
                "evaluate_profile() returned (time_after={$time_after}). " .
                "The decision must be saved BEFORE the intervention is returned."
            );

            // The reasoning must be non-empty at the time of return.
            $this->assertNotEmpty(
                $saved->reasoning,
                "Property 10 violated: Decision was saved but reasoning is empty at return time. " .
                "Reasoning must be populated before the decision is returned to Delivery System."
            );
        });
    }

    /**
     * Property 10 (No Decision Without Persistence): evaluate_profile() must
     * NEVER return an intervention with a null decision_id, regardless of
     * profile values.
     *
     * **Validates: Requirements 4.6**
     *
     * This is the strongest form of the property: even in edge cases (extreme
     * motivation values, unknown learning style, etc.), the decision_id must
     * always be set.
     *
     * @return void
     */
    public function test_property10_no_decision_without_persistence() {
        $this->forAll(
            Generator\choose(1, 3),   // Performance_Category
            Generator\choose(0, 100), // Motivation_Level (full range including extremes)
            Generator\choose(1, 3),   // Cognitive_Level
            Generator\elements(['visual', 'auditory', 'reading', 'kinesthetic', 'unknown'])  // All styles including unknown
        )->then(function ($performance_category, $motivation_level, $cognitive_level, $learning_style) {
            global $DB;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            // Create resources for all difficulties.
            $this->create_resources_for_all_difficulties($course->id, 'visual');

            // Build profile with all generated values.
            $profile = new \block_attendanceleaderboard\profiling\learner_profile(
                $user->id,
                $course->id
            );
            $profile->performance_category = $performance_category;
            $profile->motivation_level     = (float) $motivation_level;
            $profile->cognitive_level      = $cognitive_level;
            $profile->learning_style       = $learning_style;
            $profile->engagement_score     = 50.0;
            $profile->behavioral_score     = 50.0;
            $profile->profile_version      = 1;
            $profile->last_updated         = time();
            $profile->created_at           = time();

            $DB->insert_record('acmls_learner_profile', $profile->to_db_record());

            $coach        = new \block_attendanceleaderboard\coach\coach();
            $intervention = $coach->evaluate_profile($profile);

            // CORE INVARIANT: decision_id must NEVER be null.
            $this->assertNotNull(
                $intervention->decision_id,
                "Property 10 violated: evaluate_profile() returned intervention with " .
                "decision_id=null for profile: " .
                "performance_category={$performance_category}, " .
                "motivation_level={$motivation_level}, " .
                "cognitive_level={$cognitive_level}, " .
                "learning_style={$learning_style}. " .
                "No decision should reach Delivery System without first being persisted (Req 4.6)."
            );

            // Verify the record actually exists in DB.
            $exists = $DB->record_exists('acmls_coach_decision', ['id' => $intervention->decision_id]);

            $this->assertTrue(
                $exists,
                "Property 10 violated: decision_id={$intervention->decision_id} does not exist " .
                "in acmls_coach_decision. The decision must be persisted before being returned."
            );

            // Verify reasoning is non-empty.
            $saved = $DB->get_record(
                'acmls_coach_decision',
                ['id' => $intervention->decision_id],
                'reasoning',
                MUST_EXIST
            );

            $this->assertNotEmpty(
                $saved->reasoning,
                "Property 10 violated: Saved decision has empty reasoning for " .
                "performance_category={$performance_category}, " .
                "motivation_level={$motivation_level}, " .
                "learning_style={$learning_style}."
            );
        });
    }

    /**
     * Property 10 (Input Profile Snapshot): The saved decision must include
     * a non-empty input_profile snapshot that captures the Learner_Profile
     * state at decision time.
     *
     * **Validates: Requirements 4.6**
     *
     * The input_profile snapshot is essential for audit and research — it
     * records the exact profile state that led to the decision.
     *
     * @return void
     */
    public function test_property10_input_profile_snapshot_saved() {
        $this->forAll(
            Generator\choose(1, 3),  // Performance_Category
            Generator\elements(['visual', 'auditory', 'reading', 'kinesthetic'])  // Learning_Style
        )->then(function ($performance_category, $learning_style) {
            global $DB;

            $user   = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            $this->create_resources_for_all_difficulties($course->id, $learning_style);

            $profile = $this->create_test_profile(
                $user->id,
                $course->id,
                $performance_category,
                $learning_style,
                60.0
            );

            $coach        = new \block_attendanceleaderboard\coach\coach();
            $intervention = $coach->evaluate_profile($profile);

            $this->assertNotNull(
                $intervention->decision_id,
                "Property 10 violated: No decision_id returned."
            );

            $saved = $DB->get_record(
                'acmls_coach_decision',
                ['id' => $intervention->decision_id],
                'input_profile',
                MUST_EXIST
            );

            // The input_profile must be a non-empty JSON string.
            $this->assertNotEmpty(
                $saved->input_profile,
                "Property 10 violated: Saved decision has empty input_profile. " .
                "The profile snapshot must be saved for audit purposes (Req 4.6)."
            );

            // The input_profile must be valid JSON.
            $decoded = json_decode($saved->input_profile, true);
            $this->assertNotNull(
                $decoded,
                "Property 10 violated: input_profile is not valid JSON. " .
                "Value: '{$saved->input_profile}'"
            );

            // The snapshot must contain the userid and courseid.
            $this->assertEquals(
                $user->id,
                $decoded['userid'] ?? null,
                "Property 10 violated: input_profile snapshot does not contain correct userid."
            );

            $this->assertEquals(
                $course->id,
                $decoded['courseid'] ?? null,
                "Property 10 violated: input_profile snapshot does not contain correct courseid."
            );

            // The snapshot must contain the performance_category used for the decision.
            $this->assertEquals(
                $performance_category,
                $decoded['performance_category'] ?? null,
                "Property 10 violated: input_profile snapshot has wrong performance_category. " .
                "Expected {$performance_category}, got " . ($decoded['performance_category'] ?? 'null')
            );
        });
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create a test Learner_Profile and persist it to the database.
     *
     * @param  int    $userid               Moodle user ID.
     * @param  int    $courseid             Moodle course ID.
     * @param  int    $performance_category Performance category (1=Low, 2=Middle, 3=High).
     * @param  string $learning_style       Learning style.
     * @param  float  $motivation_level     Motivation level (0.00–100.00).
     * @return \block_attendanceleaderboard\profiling\learner_profile Populated profile.
     */
    private function create_test_profile(
        int $userid,
        int $courseid,
        int $performance_category,
        string $learning_style,
        float $motivation_level = 50.0
    ): \block_attendanceleaderboard\profiling\learner_profile {
        global $DB;

        $profile = new \block_attendanceleaderboard\profiling\learner_profile(
            $userid,
            $courseid
        );
        $profile->performance_category = $performance_category;
        $profile->cognitive_level      = $performance_category;  // Mirror performance for simplicity.
        $profile->motivation_level     = $motivation_level;
        $profile->learning_style       = $learning_style;
        $profile->engagement_score     = 50.0;
        $profile->behavioral_score     = 50.0;
        $profile->profile_version      = 1;
        $profile->last_updated         = time();
        $profile->created_at           = time();

        $id = $DB->insert_record('acmls_learner_profile', $profile->to_db_record());
        $profile->id = $id;

        return $profile;
    }

    /**
     * Create learning resources for all three difficulty levels.
     *
     * Ensures the Coach has resources to recommend for any Performance_Category.
     *
     * @param  int    $courseid      Course ID.
     * @param  string $learning_style Learning style to tag the resources with.
     * @return void
     */
    private function create_resources_for_all_difficulties(int $courseid, string $learning_style): void {
        global $DB;

        for ($difficulty = 1; $difficulty <= 3; $difficulty++) {
            $cm = $this->create_test_course_module($courseid);

            $record = (object) [
                'courseid'           => $courseid,
                'cmid'               => $cm->id,
                'title'              => "Test Resource D{$difficulty} ({$learning_style})",
                'resource_type'      => 'page',
                'difficulty_level'   => $difficulty,
                'topic_tags'         => json_encode(['test']),
                'learning_styles'    => json_encode([$learning_style]),
                'access_count'       => 0,
                'avg_rating'         => null,
                'effectiveness_score' => 50.0,
                'is_active'          => 1,
                'timecreated'        => time(),
                'timemodified'       => time(),
            ];

            $DB->insert_record('acmls_learning_resource', $record);
        }
    }

    /**
     * Create a test course module (page activity).
     *
     * @param  int       $courseid Course ID.
     * @return \stdClass           Course module record.
     */
    private function create_test_course_module(int $courseid): \stdClass {
        $generator = $this->getDataGenerator();
        $page      = $generator->create_module('page', ['course' => $courseid]);
        return get_coursemodule_from_instance('page', $page->id);
    }
}
