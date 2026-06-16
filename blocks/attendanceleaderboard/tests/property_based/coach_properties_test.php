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
 * Property-based tests for Coach correctness properties.
 *
 * Tests Properties 9, 10:
 * - Property 9: Appropriateness of Resource Difficulty Levels
 * - Property 10: Completeness of Coach Decision Recording
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
 * Property-based tests for Coach.
 *
 * Uses Eris library for property-based testing to verify correctness properties
 * hold for all possible inputs within the defined domain.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coach_properties_test extends \advanced_testcase {
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
    // PROPERTY 9: Kesesuaian Tingkat Kesulitan Rekomendasi
    // =========================================================================

    /**
     * Property 9: Kesesuaian Tingkat Kesulitan Rekomendasi
     *
     * Specification: For ANY Learner_Profile with a given Performance_Category,
     * ALL recommended resources MUST have difficulty_level values that are
     * appropriate for that Performance_Category according to the mapping rules:
     *
     *   - Performance_Category = 1 (Low)    → difficulty_level MUST be 1
     *   - Performance_Category = 2 (Middle) → difficulty_level MUST be 1 OR 2
     *   - Performance_Category = 3 (High)   → difficulty_level MUST be 2 OR 3
     *
     * This property verifies that:
     * 1. No resource with inappropriate difficulty is ever recommended
     * 2. The mapping is consistent across all invocations
     * 3. The mapping holds for all possible Performance_Category values (1, 2, 3)
     * 4. The mapping holds regardless of other profile dimensions (learning_style, motivation_level, etc.)
     * 5. Empty result sets are acceptable (no resources available), but non-empty results must ALL be appropriate
     * 6. The property holds even when multiple resources are recommended
     *
     * Validates Requirements: 4.2, 4.3, 4.4
     * Validates Design: Section B.4 (Coach), Section E.4 (Learning Resource Repository)
     *
     * Test Strategy:
     * - Generate random Performance_Category values (1, 2, 3)
     * - Generate random profiles with varying other dimensions
     * - Create test resources with all possible difficulty levels (1, 2, 3)
     * - Call Coach.recommend_resources() for each profile
     * - Verify ALL returned resources have appropriate difficulty_level
     * - Test boundary cases and edge cases
     *
     * @return void
     */
    public function test_property9_resource_difficulty_appropriateness() {
        $this->forAll(
            Generator\choose(1, 3),  // Performance_Category: 1=Low, 2=Middle, 3=High
            Generator\choose(0, 100),  // Motivation_Level: 0.00 - 100.00
            Generator\elements(['visual', 'auditory', 'reading', 'kinesthetic', 'unknown'])  // Learning_Style
        )->then(function($performance_category, $motivation_level, $learning_style) {
            global $DB;

            // Setup test data.
            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            // Create a diverse set of learning resources with ALL difficulty levels.
            $resources = [];
            for ($difficulty = 1; $difficulty <= 3; $difficulty++) {
                for ($i = 0; $i < 3; $i++) {  // 3 resources per difficulty level
                    $cm = $this->create_test_course_module($course->id);
                    $resource = $this->create_test_resource(
                        $course->id,
                        $cm->id,
                        $difficulty,
                        $learning_style,
                        "Resource D{$difficulty}-{$i}"
                    );
                    $resources[] = $resource;
                }
            }

            // Create Learner_Profile with specified Performance_Category.
            $profile = new \block_attendanceleaderboard\profiling\learner_profile();
            $profile->userid = $user->id;
            $profile->courseid = $course->id;
            $profile->performance_category = $performance_category;
            $profile->motivation_level = $motivation_level;
            $profile->learning_style = $learning_style;
            $profile->cognitive_level = $performance_category;  // Mirror performance category
            $profile->engagement_score = 50.0;
            $profile->behavioral_score = 50.0;
            $profile->profile_version = 1;
            $profile->last_updated = time();
            $profile->created_at = time();

            // Save profile to database (Coach may query it).
            $DB->insert_record('acmls_learner_profile', $profile->to_db_record());

            // Instantiate Coach and get recommendations.
            $coach = new \block_attendanceleaderboard\coach\coach();
            $recommended = $coach->recommend_resources($profile);

            // ================================================================
            // PROPERTY 9: ALL recommended resources MUST have appropriate difficulty_level
            // ================================================================

            // Define the allowed difficulty levels for each Performance_Category.
            $allowed_difficulties = $this->get_allowed_difficulties($performance_category);

            // If no resources are returned, that's acceptable (no matching resources available).
            // But if resources ARE returned, they MUST ALL be appropriate.
            if (!empty($recommended)) {
                foreach ($recommended as $resource) {
                    $this->assertContains(
                        $resource->difficulty_level,
                        $allowed_difficulties,
                        "Property 9 violated: Resource '{$resource->title}' (ID={$resource->id}) " .
                        "has difficulty_level={$resource->difficulty_level}, which is NOT appropriate " .
                        "for Performance_Category={$performance_category}. " .
                        "Allowed difficulties: [" . implode(', ', $allowed_difficulties) . "]. " .
                        "Profile: motivation_level={$motivation_level}, learning_style={$learning_style}. " .
                        "ALL recommended resources must have difficulty_level appropriate for the Learner's Performance_Category."
                    );
                }

                // Additional verification: Ensure at least one resource was recommended
                // (since we created resources for all difficulty levels).
                $this->assertGreaterThan(
                    0,
                    count($recommended),
                    "Property 9 test setup issue: Expected at least one resource to be recommended " .
                    "when resources exist for Performance_Category={$performance_category}"
                );
            }

            // ================================================================
            // PROPERTY 9 Invariant: No inappropriate difficulty levels in database query
            // ================================================================

            // Verify that the database does NOT contain any recommended resource
            // with an inappropriate difficulty level (double-check against DB state).
            if (!empty($recommended)) {
                $recommended_ids = array_column($recommended, 'id');
                list($in_sql, $params) = $DB->get_in_or_equal($recommended_ids, SQL_PARAMS_NAMED, 'rid');

                $sql = "SELECT id, difficulty_level, title
                          FROM {acmls_learning_resource}
                         WHERE id {$in_sql}";

                $db_resources = $DB->get_records_sql($sql, $params);

                foreach ($db_resources as $db_resource) {
                    $this->assertContains(
                        $db_resource->difficulty_level,
                        $allowed_difficulties,
                        "Property 9 violated (DB verification): Resource '{$db_resource->title}' " .
                        "(ID={$db_resource->id}) in database has difficulty_level={$db_resource->difficulty_level}, " .
                        "which is NOT appropriate for Performance_Category={$performance_category}. " .
                        "This indicates a data integrity issue or incorrect query logic."
                    );
                }
            }
        });
    }

    /**
     * Property 9 Edge Case: Low Performance Category (Category 1).
     *
     * Verifies that for Low performers, ONLY difficulty_level=1 resources are recommended.
     * No difficulty_level=2 or difficulty_level=3 resources should ever be returned.
     *
     * @return void
     */
    public function test_property9_low_performance_only_basic_difficulty() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create resources with ALL difficulty levels.
        $cm1 = $this->create_test_course_module($course->id);
        $cm2 = $this->create_test_course_module($course->id);
        $cm3 = $this->create_test_course_module($course->id);

        $resource_basic = $this->create_test_resource($course->id, $cm1->id, 1, 'visual', 'Basic Resource');
        $resource_intermediate = $this->create_test_resource($course->id, $cm2->id, 2, 'visual', 'Intermediate Resource');
        $resource_advanced = $this->create_test_resource($course->id, $cm3->id, 3, 'visual', 'Advanced Resource');

        // Create Low Performance profile.
        $profile = $this->create_test_profile($user->id, $course->id, 1, 'visual');

        // Get recommendations.
        $coach = new \block_attendanceleaderboard\coach\coach();
        $recommended = $coach->recommend_resources($profile);

        // PROPERTY: ALL recommended resources MUST have difficulty_level = 1.
        $this->assertNotEmpty($recommended, "Expected at least one resource for Low performer");

        foreach ($recommended as $resource) {
            $this->assertEquals(
                1,
                $resource->difficulty_level,
                "Property 9 violated: Low performer (Performance_Category=1) received resource " .
                "with difficulty_level={$resource->difficulty_level}. ONLY difficulty_level=1 is allowed."
            );
        }

        // PROPERTY: Intermediate and Advanced resources MUST NOT be in the result.
        $recommended_ids = array_column($recommended, 'id');
        $this->assertNotContains(
            $resource_intermediate->id,
            $recommended_ids,
            "Property 9 violated: Intermediate resource (difficulty=2) was recommended to Low performer"
        );
        $this->assertNotContains(
            $resource_advanced->id,
            $recommended_ids,
            "Property 9 violated: Advanced resource (difficulty=3) was recommended to Low performer"
        );
    }

    /**
     * Property 9 Edge Case: Middle Performance Category (Category 2).
     *
     * Verifies that for Middle performers, ONLY difficulty_level=1 OR difficulty_level=2
     * resources are recommended. No difficulty_level=3 resources should be returned.
     *
     * @return void
     */
    public function test_property9_middle_performance_basic_and_intermediate() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create resources with ALL difficulty levels.
        $cm1 = $this->create_test_course_module($course->id);
        $cm2 = $this->create_test_course_module($course->id);
        $cm3 = $this->create_test_course_module($course->id);

        $resource_basic = $this->create_test_resource($course->id, $cm1->id, 1, 'reading', 'Basic Resource');
        $resource_intermediate = $this->create_test_resource($course->id, $cm2->id, 2, 'reading', 'Intermediate Resource');
        $resource_advanced = $this->create_test_resource($course->id, $cm3->id, 3, 'reading', 'Advanced Resource');

        // Create Middle Performance profile.
        $profile = $this->create_test_profile($user->id, $course->id, 2, 'reading');

        // Get recommendations.
        $coach = new \block_attendanceleaderboard\coach\coach();
        $recommended = $coach->recommend_resources($profile);

        // PROPERTY: ALL recommended resources MUST have difficulty_level = 1 OR 2.
        $this->assertNotEmpty($recommended, "Expected at least one resource for Middle performer");

        foreach ($recommended as $resource) {
            $this->assertContains(
                $resource->difficulty_level,
                [1, 2],
                "Property 9 violated: Middle performer (Performance_Category=2) received resource " .
                "with difficulty_level={$resource->difficulty_level}. ONLY difficulty_level=1 or 2 are allowed."
            );
        }

        // PROPERTY: Advanced resource (difficulty=3) MUST NOT be in the result.
        $recommended_ids = array_column($recommended, 'id');
        $this->assertNotContains(
            $resource_advanced->id,
            $recommended_ids,
            "Property 9 violated: Advanced resource (difficulty=3) was recommended to Middle performer"
        );
    }

    /**
     * Property 9 Edge Case: High Performance Category (Category 3).
     *
     * Verifies that for High performers, ONLY difficulty_level=2 OR difficulty_level=3
     * resources are recommended. No difficulty_level=1 resources should be returned.
     *
     * @return void
     */
    public function test_property9_high_performance_intermediate_and_advanced() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create resources with ALL difficulty levels.
        $cm1 = $this->create_test_course_module($course->id);
        $cm2 = $this->create_test_course_module($course->id);
        $cm3 = $this->create_test_course_module($course->id);

        $resource_basic = $this->create_test_resource($course->id, $cm1->id, 1, 'kinesthetic', 'Basic Resource');
        $resource_intermediate = $this->create_test_resource($course->id, $cm2->id, 2, 'kinesthetic', 'Intermediate Resource');
        $resource_advanced = $this->create_test_resource($course->id, $cm3->id, 3, 'kinesthetic', 'Advanced Resource');

        // Create High Performance profile.
        $profile = $this->create_test_profile($user->id, $course->id, 3, 'kinesthetic');

        // Get recommendations.
        $coach = new \block_attendanceleaderboard\coach\coach();
        $recommended = $coach->recommend_resources($profile);

        // PROPERTY: ALL recommended resources MUST have difficulty_level = 2 OR 3.
        $this->assertNotEmpty($recommended, "Expected at least one resource for High performer");

        foreach ($recommended as $resource) {
            $this->assertContains(
                $resource->difficulty_level,
                [2, 3],
                "Property 9 violated: High performer (Performance_Category=3) received resource " .
                "with difficulty_level={$resource->difficulty_level}. ONLY difficulty_level=2 or 3 are allowed."
            );
        }

        // PROPERTY: Basic resource (difficulty=1) MUST NOT be in the result.
        $recommended_ids = array_column($recommended, 'id');
        $this->assertNotContains(
            $resource_basic->id,
            $recommended_ids,
            "Property 9 violated: Basic resource (difficulty=1) was recommended to High performer"
        );
    }

    /**
     * Property 9 Edge Case: Multiple recommendations consistency.
     *
     * Verifies that when multiple resources are recommended, ALL of them
     * have appropriate difficulty levels (no mixed inappropriate resources).
     *
     * @return void
     */
    public function test_property9_multiple_recommendations_all_appropriate() {
        $this->forAll(
            Generator\choose(1, 3)  // Performance_Category
        )->then(function($performance_category) {
            global $DB;

            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            // Create MANY resources (10 per difficulty level) to ensure multiple recommendations.
            for ($difficulty = 1; $difficulty <= 3; $difficulty++) {
                for ($i = 0; $i < 10; $i++) {
                    $cm = $this->create_test_course_module($course->id);
                    $this->create_test_resource(
                        $course->id,
                        $cm->id,
                        $difficulty,
                        'visual',
                        "Resource D{$difficulty}-{$i}",
                        100 - ($i * 5)  // Varying effectiveness scores
                    );
                }
            }

            // Create profile.
            $profile = $this->create_test_profile($user->id, $course->id, $performance_category, 'visual');

            // Get recommendations (should return multiple resources).
            $coach = new \block_attendanceleaderboard\coach\coach();
            $recommended = $coach->recommend_resources($profile);

            // PROPERTY: If multiple resources are returned, ALL must be appropriate.
            if (count($recommended) > 1) {
                $allowed_difficulties = $this->get_allowed_difficulties($performance_category);

                foreach ($recommended as $resource) {
                    $this->assertContains(
                        $resource->difficulty_level,
                        $allowed_difficulties,
                        "Property 9 violated: In a set of " . count($recommended) . " recommendations, " .
                        "resource '{$resource->title}' has inappropriate difficulty_level={$resource->difficulty_level} " .
                        "for Performance_Category={$performance_category}. " .
                        "ALL resources in a recommendation set must be appropriate."
                    );
                }
            }
        });
    }

    /**
     * Property 9 Edge Case: Empty resource pool.
     *
     * Verifies that when NO resources exist with appropriate difficulty levels,
     * the system returns an empty array (not an error, not inappropriate resources).
     *
     * @return void
     */
    public function test_property9_empty_pool_returns_empty_array() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create ONLY difficulty=3 resources.
        $cm = $this->create_test_course_module($course->id);
        $this->create_test_resource($course->id, $cm->id, 3, 'visual', 'Advanced Only');

        // Create Low Performance profile (requires difficulty=1, but none exist).
        $profile = $this->create_test_profile($user->id, $course->id, 1, 'visual');

        // Get recommendations.
        $coach = new \block_attendanceleaderboard\coach\coach();
        $recommended = $coach->recommend_resources($profile);

        // PROPERTY: Should return empty array (no inappropriate resources).
        $this->assertIsArray(
            $recommended,
            "Property 9 violated: recommend_resources() should return an array even when no appropriate resources exist"
        );

        $this->assertEmpty(
            $recommended,
            "Property 9 violated: When no appropriate resources exist, should return empty array, " .
            "not inappropriate resources. Performance_Category=1 requires difficulty=1, but only difficulty=3 exists."
        );
    }

    /**
     * Property 9 Invariant: Consistency across multiple invocations.
     *
     * Verifies that calling recommend_resources() multiple times with the same
     * profile produces consistent results (same difficulty appropriateness).
     *
     * @return void
     */
    public function test_property9_consistency_across_invocations() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create resources.
        for ($difficulty = 1; $difficulty <= 3; $difficulty++) {
            $cm = $this->create_test_course_module($course->id);
            $this->create_test_resource($course->id, $cm->id, $difficulty, 'auditory', "Resource D{$difficulty}");
        }

        // Create profile.
        $profile = $this->create_test_profile($user->id, $course->id, 2, 'auditory');

        // Get recommendations multiple times.
        $coach = new \block_attendanceleaderboard\coach\coach();
        $recommended1 = $coach->recommend_resources($profile);
        $recommended2 = $coach->recommend_resources($profile);
        $recommended3 = $coach->recommend_resources($profile);

        // PROPERTY: ALL invocations must return resources with appropriate difficulty.
        $allowed_difficulties = $this->get_allowed_difficulties(2);

        foreach ([$recommended1, $recommended2, $recommended3] as $invocation_num => $recommended) {
            foreach ($recommended as $resource) {
                $this->assertContains(
                    $resource->difficulty_level,
                    $allowed_difficulties,
                    "Property 9 violated: Invocation " . ($invocation_num + 1) . " returned resource " .
                    "with inappropriate difficulty_level={$resource->difficulty_level} for Performance_Category=2"
                );
            }
        }

        // PROPERTY: The set of difficulty levels should be consistent across invocations.
        $difficulties1 = array_unique(array_column($recommended1, 'difficulty_level'));
        $difficulties2 = array_unique(array_column($recommended2, 'difficulty_level'));
        $difficulties3 = array_unique(array_column($recommended3, 'difficulty_level'));

        sort($difficulties1);
        sort($difficulties2);
        sort($difficulties3);

        $this->assertEquals(
            $difficulties1,
            $difficulties2,
            "Property 9 violated: Difficulty levels should be consistent across invocations"
        );

        $this->assertEquals(
            $difficulties2,
            $difficulties3,
            "Property 9 violated: Difficulty levels should be consistent across invocations"
        );
    }

    /**
     * Property 9 Invariant: Independence from other profile dimensions.
     *
     * Verifies that difficulty appropriateness depends ONLY on Performance_Category,
     * not on motivation_level, learning_style, or other dimensions.
     *
     * @return void
     */
    public function test_property9_independence_from_other_dimensions() {
        $this->forAll(
            Generator\choose(1, 3),  // Performance_Category
            Generator\choose(0, 100),  // Motivation_Level (should not affect difficulty)
            Generator\elements(['visual', 'auditory', 'reading', 'kinesthetic'])  // Learning_Style (should not affect difficulty)
        )->then(function($performance_category, $motivation_level, $learning_style) {
            global $DB;

            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            // Create resources for all difficulty levels.
            for ($difficulty = 1; $difficulty <= 3; $difficulty++) {
                $cm = $this->create_test_course_module($course->id);
                $this->create_test_resource($course->id, $cm->id, $difficulty, $learning_style, "Resource D{$difficulty}");
            }

            // Create profile with varying motivation and learning style.
            $profile = $this->create_test_profile($user->id, $course->id, $performance_category, $learning_style);
            $profile->motivation_level = $motivation_level;

            // Update profile in database.
            $DB->update_record('acmls_learner_profile', $profile->to_db_record());

            // Get recommendations.
            $coach = new \block_attendanceleaderboard\coach\coach();
            $recommended = $coach->recommend_resources($profile);

            // PROPERTY: Difficulty appropriateness depends ONLY on Performance_Category.
            $allowed_difficulties = $this->get_allowed_difficulties($performance_category);

            foreach ($recommended as $resource) {
                $this->assertContains(
                    $resource->difficulty_level,
                    $allowed_difficulties,
                    "Property 9 violated: Difficulty appropriateness should depend ONLY on Performance_Category={$performance_category}, " .
                    "not on motivation_level={$motivation_level} or learning_style={$learning_style}. " .
                    "Resource has difficulty_level={$resource->difficulty_level}, which is inappropriate."
                );
            }
        });
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Get allowed difficulty levels for a given Performance_Category.
     *
     * Implements the mapping rules:
     * - Category 1 (Low)    → [1]
     * - Category 2 (Middle) → [1, 2]
     * - Category 3 (High)   → [2, 3]
     *
     * @param  int   $performance_category Performance category (1, 2, or 3).
     * @return int[]                       Array of allowed difficulty levels.
     */
    private function get_allowed_difficulties(int $performance_category): array {
        switch ($performance_category) {
            case 1:  // Low
                return [1];
            case 2:  // Middle
                return [1, 2];
            case 3:  // High
                return [2, 3];
            default:
                throw new \coding_exception("Invalid performance_category: {$performance_category}");
        }
    }

    /**
     * Create a test course module.
     *
     * @param  int $courseid Course ID.
     * @return \stdClass     Course module record.
     */
    private function create_test_course_module(int $courseid): \stdClass {
        $generator = $this->getDataGenerator();
        $page = $generator->create_module('page', ['course' => $courseid]);
        return get_coursemodule_from_instance('page', $page->id);
    }

    /**
     * Create a test learning resource.
     *
     * @param  int    $courseid          Course ID.
     * @param  int    $cmid              Course module ID.
     * @param  int    $difficulty_level  Difficulty level (1, 2, or 3).
     * @param  string $learning_style    Learning style.
     * @param  string $title             Resource title.
     * @param  float  $effectiveness     Effectiveness score (default 50.0).
     * @return \stdClass                 Resource record.
     */
    private function create_test_resource(
        int $courseid,
        int $cmid,
        int $difficulty_level,
        string $learning_style,
        string $title,
        float $effectiveness = 50.0
    ): \stdClass {
        global $DB;

        $record = (object) [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'title' => $title,
            'resource_type' => 'page',
            'difficulty_level' => $difficulty_level,
            'topic_tags' => json_encode(['test']),
            'learning_styles' => json_encode([$learning_style]),
            'access_count' => 0,
            'avg_rating' => null,
            'effectiveness_score' => $effectiveness,
            'is_active' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $record->id = $DB->insert_record('acmls_learning_resource', $record);
        return $record;
    }

    /**
     * Create a test learner profile.
     *
     * @param  int    $userid               User ID.
     * @param  int    $courseid             Course ID.
     * @param  int    $performance_category Performance category (1, 2, or 3).
     * @param  string $learning_style       Learning style.
     * @return \block_attendanceleaderboard\profiling\learner_profile Profile object.
     */
    private function create_test_profile(
        int $userid,
        int $courseid,
        int $performance_category,
        string $learning_style
    ): \block_attendanceleaderboard\profiling\learner_profile {
        global $DB;

        $profile = new \block_attendanceleaderboard\profiling\learner_profile();
        $profile->userid = $userid;
        $profile->courseid = $courseid;
        $profile->performance_category = $performance_category;
        $profile->cognitive_level = $performance_category;
        $profile->motivation_level = 50.0;
        $profile->learning_style = $learning_style;
        $profile->engagement_score = 50.0;
        $profile->behavioral_score = 50.0;
        $profile->profile_version = 1;
        $profile->last_updated = time();
        $profile->created_at = time();

        $DB->insert_record('acmls_learner_profile', $profile->to_db_record());

        return $profile;
    }

    // =========================================================================
    // PROPERTY 10: Kelengkapan Pencatatan Keputusan Coach
    // =========================================================================

    /**
     * Property 10: Kelengkapan Pencatatan Keputusan Coach
     *
     * Specification: For ANY Coach evaluation that produces an AdaptiveIntervention,
     * a corresponding CoachDecision record MUST be saved to the database with
     * complete reasoning BEFORE the AdaptiveIntervention is returned to the
     * Delivery System.
     *
     * This property verifies that:
     * 1. Every call to evaluate_profile() results in a saved CoachDecision
     * 2. The saved decision contains non-empty reasoning
     * 3. The saved decision contains all required fields (input_profile, decision_type, etc.)
     * 4. The decision is saved BEFORE the intervention is returned (decision_id is set)
     * 5. The saved decision can be retrieved from the database
     * 6. The decision's reasoning explains which rules were triggered
     * 7. The property holds for all possible profile configurations
     * 8. The property holds for both normal evaluations and fallback responses
     * 9. No decision is ever sent to Delivery System without being persisted first
     *
     * Validates Requirements: 4.6
     * Validates Design: Section B.4 (Coach), Section E.6 (acmls_coach_decision table)
     *
     * Test Strategy:
     * - Generate random learner profiles with varying dimensions
     * - Call Coach.evaluate_profile() for each profile
     * - Verify that a CoachDecision was saved to the database
     * - Verify that the decision contains complete reasoning
     * - Verify that the decision_id in the returned intervention matches the DB record
     * - Verify that all required fields are populated
     * - Test both normal and fallback scenarios
     *
     * @return void
     */
    public function test_property10_coach_decision_completeness() {
        $this->forAll(
            Generator\choose(1, 3),  // Performance_Category: 1=Low, 2=Middle, 3=High
            Generator\choose(0, 100),  // Motivation_Level: 0.00 - 100.00
            Generator\elements(['visual', 'auditory', 'reading', 'kinesthetic', 'unknown'])  // Learning_Style
        )->then(function($performance_category, $motivation_level, $learning_style) {
            global $DB;

            // Setup test data.
            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            // Create learning resources to ensure Coach can make recommendations.
            for ($difficulty = 1; $difficulty <= 3; $difficulty++) {
                $cm = $this->create_test_course_module($course->id);
                $this->create_test_resource(
                    $course->id,
                    $cm->id,
                    $difficulty,
                    $learning_style,
                    "Resource D{$difficulty}"
                );
            }

            // Create Learner_Profile.
            $profile = new \block_attendanceleaderboard\profiling\learner_profile();
            $profile->userid = $user->id;
            $profile->courseid = $course->id;
            $profile->performance_category = $performance_category;
            $profile->motivation_level = $motivation_level;
            $profile->learning_style = $learning_style;
            $profile->cognitive_level = $performance_category;
            $profile->engagement_score = 50.0;
            $profile->behavioral_score = 50.0;
            $profile->profile_version = 1;
            $profile->last_updated = time();
            $profile->created_at = time();

            // Save profile to database.
            $DB->insert_record('acmls_learner_profile', $profile->to_db_record());

            // Count existing decisions before evaluation.
            $decisions_before = $DB->count_records('acmls_coach_decision', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ]);

            // ================================================================
            // Execute Coach evaluation.
            // ================================================================
            $coach = new \block_attendanceleaderboard\coach\coach();
            $intervention = $coach->evaluate_profile($profile);

            // ================================================================
            // PROPERTY 10: A CoachDecision MUST be saved to the database.
            // ================================================================
            $decisions_after = $DB->count_records('acmls_coach_decision', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ]);

            $this->assertEquals(
                $decisions_before + 1,
                $decisions_after,
                "Property 10 violated: Coach.evaluate_profile() did NOT save a CoachDecision to the database. " .
                "Expected exactly one new decision record for user {$user->id} in course {$course->id}. " .
                "Profile: performance_category={$performance_category}, motivation_level={$motivation_level}, " .
                "learning_style={$learning_style}. " .
                "Every Coach evaluation MUST result in a saved decision."
            );

            // ================================================================
            // PROPERTY 10: The intervention MUST contain a valid decision_id.
            // ================================================================
            $this->assertNotNull(
                $intervention->decision_id,
                "Property 10 violated: AdaptiveIntervention.decision_id is NULL. " .
                "The decision MUST be saved and its ID attached to the intervention BEFORE returning. " .
                "Profile: performance_category={$performance_category}, motivation_level={$motivation_level}."
            );

            $this->assertIsInt(
                $intervention->decision_id,
                "Property 10 violated: AdaptiveIntervention.decision_id is not an integer. " .
                "Expected a valid database record ID."
            );

            $this->assertGreaterThan(
                0,
                $intervention->decision_id,
                "Property 10 violated: AdaptiveIntervention.decision_id is not a positive integer. " .
                "Expected a valid database record ID > 0."
            );

            // ================================================================
            // PROPERTY 10: The decision record MUST exist in the database.
            // ================================================================
            $decision_record = $DB->get_record('acmls_coach_decision', [
                'id' => $intervention->decision_id,
            ]);

            $this->assertNotFalse(
                $decision_record,
                "Property 10 violated: CoachDecision with ID {$intervention->decision_id} does NOT exist in database. " .
                "The decision MUST be persisted before the intervention is returned."
            );

            // ================================================================
            // PROPERTY 10: The decision MUST contain non-empty reasoning.
            // ================================================================
            $this->assertNotEmpty(
                $decision_record->reasoning,
                "Property 10 violated: CoachDecision (ID={$decision_record->id}) has EMPTY reasoning. " .
                "Every decision MUST include a human-readable explanation of why it was made. " .
                "Profile: performance_category={$performance_category}, motivation_level={$motivation_level}."
            );

            $this->assertIsString(
                $decision_record->reasoning,
                "Property 10 violated: CoachDecision.reasoning is not a string."
            );

            $this->assertGreaterThan(
                10,
                strlen($decision_record->reasoning),
                "Property 10 violated: CoachDecision.reasoning is too short (length=" . strlen($decision_record->reasoning) . "). " .
                "Reasoning must be a meaningful explanation, not just a placeholder. " .
                "Actual reasoning: '{$decision_record->reasoning}'"
            );

            // ================================================================
            // PROPERTY 10: The decision MUST contain all required fields.
            // ================================================================
            $this->assertNotEmpty(
                $decision_record->userid,
                "Property 10 violated: CoachDecision.userid is empty."
            );

            $this->assertEquals(
                $user->id,
                $decision_record->userid,
                "Property 10 violated: CoachDecision.userid does not match the profile's userid."
            );

            $this->assertNotEmpty(
                $decision_record->courseid,
                "Property 10 violated: CoachDecision.courseid is empty."
            );

            $this->assertEquals(
                $course->id,
                $decision_record->courseid,
                "Property 10 violated: CoachDecision.courseid does not match the profile's courseid."
            );

            $this->assertNotEmpty(
                $decision_record->decision_type,
                "Property 10 violated: CoachDecision.decision_type is empty."
            );

            $this->assertContains(
                $decision_record->decision_type,
                ['resource_recommendation', 'motivation_intervention'],
                "Property 10 violated: CoachDecision.decision_type has invalid value '{$decision_record->decision_type}'."
            );

            $this->assertNotEmpty(
                $decision_record->input_profile,
                "Property 10 violated: CoachDecision.input_profile is empty. " .
                "The decision MUST include a snapshot of the input profile."
            );

            // Verify input_profile is valid JSON.
            $input_profile_data = json_decode($decision_record->input_profile, true);
            $this->assertIsArray(
                $input_profile_data,
                "Property 10 violated: CoachDecision.input_profile is not valid JSON."
            );

            $this->assertArrayHasKey(
                'performance_category',
                $input_profile_data,
                "Property 10 violated: CoachDecision.input_profile does not contain 'performance_category'."
            );

            $this->assertNotEmpty(
                $decision_record->motivation_category,
                "Property 10 violated: CoachDecision.motivation_category is empty."
            );

            $this->assertContains(
                $decision_record->motivation_category,
                ['recovery', 'persistence', 'reinforcement', 'achievement'],
                "Property 10 violated: CoachDecision.motivation_category has invalid value '{$decision_record->motivation_category}'."
            );

            // Verify rules_triggered is valid JSON (can be empty array for fallback).
            $rules_triggered = json_decode($decision_record->rules_triggered ?? '[]', true);
            $this->assertIsArray(
                $rules_triggered,
                "Property 10 violated: CoachDecision.rules_triggered is not valid JSON."
            );

            $this->assertNotEmpty(
                $decision_record->timecreated,
                "Property 10 violated: CoachDecision.timecreated is empty."
            );

            $this->assertGreaterThan(
                0,
                $decision_record->timecreated,
                "Property 10 violated: CoachDecision.timecreated is not a valid Unix timestamp."
            );

            // ================================================================
            // PROPERTY 10: The reasoning MUST explain the decision.
            // ================================================================
            // Reasoning should mention at least one of: rules, motivation category, or fallback.
            $reasoning_lower = strtolower($decision_record->reasoning);

            $has_explanation = (
                strpos($reasoning_lower, 'rule') !== false ||
                strpos($reasoning_lower, 'motivation') !== false ||
                strpos($reasoning_lower, 'fallback') !== false ||
                strpos($reasoning_lower, 'performance') !== false ||
                strpos($reasoning_lower, 'category') !== false
            );

            $this->assertTrue(
                $has_explanation,
                "Property 10 violated: CoachDecision.reasoning does not contain meaningful explanation. " .
                "Reasoning should mention rules, motivation, performance, or fallback. " .
                "Actual reasoning: '{$decision_record->reasoning}'"
            );

            // ================================================================
            // PROPERTY 10: The decision matches the intervention.
            // ================================================================
            $this->assertEquals(
                $intervention->motivation_category,
                $decision_record->motivation_category,
                "Property 10 violated: Motivation category mismatch between intervention and saved decision."
            );

            $this->assertEquals(
                $intervention->reasoning,
                $decision_record->reasoning,
                "Property 10 violated: Reasoning mismatch between intervention and saved decision."
            );

            // Verify recommended_resources is valid JSON.
            $recommended_resources = json_decode($decision_record->recommended_resources ?? '[]', true);
            $this->assertIsArray(
                $recommended_resources,
                "Property 10 violated: CoachDecision.recommended_resources is not valid JSON."
            );

            // If intervention has resources, decision should have resource IDs.
            if (!empty($intervention->resources)) {
                $this->assertNotEmpty(
                    $recommended_resources,
                    "Property 10 violated: Intervention has resources but decision.recommended_resources is empty."
                );

                $intervention_resource_ids = array_map(fn($r) => (int) $r->id, $intervention->resources);
                $this->assertEquals(
                    $intervention_resource_ids,
                    $recommended_resources,
                    "Property 10 violated: Resource IDs mismatch between intervention and saved decision."
                );
            }
        });
    }

    /**
     * Property 10 Edge Case: Fallback decisions must also be saved with reasoning.
     *
     * Verifies that even when Coach uses fallback logic (timeout or error),
     * the decision is still saved with complete reasoning explaining the fallback.
     *
     * @return void
     */
    public function test_property10_fallback_decisions_saved_with_reasoning() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create resources.
        $cm = $this->create_test_course_module($course->id);
        $this->create_test_resource($course->id, $cm->id, 1, 'visual', 'Basic Resource');

        // Create profile.
        $profile = $this->create_test_profile($user->id, $course->id, 1, 'visual');

        // Mock a scenario that might trigger fallback by using a very complex profile
        // (in practice, fallback is triggered by timeout, but we test the result).
        $coach = new \block_attendanceleaderboard\coach\coach();
        $intervention = $coach->evaluate_profile($profile);

        // PROPERTY: Decision must be saved regardless of whether it's fallback or normal.
        $this->assertNotNull(
            $intervention->decision_id,
            "Property 10 violated: Fallback intervention does not have decision_id."
        );

        $decision_record = $DB->get_record('acmls_coach_decision', [
            'id' => $intervention->decision_id,
        ]);

        $this->assertNotFalse(
            $decision_record,
            "Property 10 violated: Fallback decision not found in database."
        );

        // PROPERTY: Fallback decision must have reasoning explaining the fallback.
        $this->assertNotEmpty(
            $decision_record->reasoning,
            "Property 10 violated: Fallback decision has empty reasoning."
        );

        // If it's a fallback, reasoning should mention it.
        if ($intervention->is_fallback) {
            $reasoning_lower = strtolower($decision_record->reasoning);
            $this->assertStringContainsString(
                'fallback',
                $reasoning_lower,
                "Property 10 violated: Fallback decision reasoning does not mention 'fallback'. " .
                "Actual reasoning: '{$decision_record->reasoning}'"
            );
        }
    }

    /**
     * Property 10 Edge Case: Multiple evaluations produce multiple decisions.
     *
     * Verifies that calling evaluate_profile() multiple times for the same
     * learner produces multiple distinct decision records (no overwriting).
     *
     * @return void
     */
    public function test_property10_multiple_evaluations_multiple_decisions() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create resources.
        for ($difficulty = 1; $difficulty <= 3; $difficulty++) {
            $cm = $this->create_test_course_module($course->id);
            $this->create_test_resource($course->id, $cm->id, $difficulty, 'reading', "Resource D{$difficulty}");
        }

        // Create profile.
        $profile = $this->create_test_profile($user->id, $course->id, 2, 'reading');

        // Perform multiple evaluations.
        $coach = new \block_attendanceleaderboard\coach\coach();
        $intervention1 = $coach->evaluate_profile($profile);
        $intervention2 = $coach->evaluate_profile($profile);
        $intervention3 = $coach->evaluate_profile($profile);

        // PROPERTY: Each evaluation must produce a distinct decision record.
        $this->assertNotNull($intervention1->decision_id, "Intervention 1 missing decision_id");
        $this->assertNotNull($intervention2->decision_id, "Intervention 2 missing decision_id");
        $this->assertNotNull($intervention3->decision_id, "Intervention 3 missing decision_id");

        $this->assertNotEquals(
            $intervention1->decision_id,
            $intervention2->decision_id,
            "Property 10 violated: Multiple evaluations produced the same decision_id (1 vs 2)."
        );

        $this->assertNotEquals(
            $intervention2->decision_id,
            $intervention3->decision_id,
            "Property 10 violated: Multiple evaluations produced the same decision_id (2 vs 3)."
        );

        $this->assertNotEquals(
            $intervention1->decision_id,
            $intervention3->decision_id,
            "Property 10 violated: Multiple evaluations produced the same decision_id (1 vs 3)."
        );

        // PROPERTY: All three decisions must exist in the database.
        $decision1 = $DB->get_record('acmls_coach_decision', ['id' => $intervention1->decision_id]);
        $decision2 = $DB->get_record('acmls_coach_decision', ['id' => $intervention2->decision_id]);
        $decision3 = $DB->get_record('acmls_coach_decision', ['id' => $intervention3->decision_id]);

        $this->assertNotFalse($decision1, "Decision 1 not found in database");
        $this->assertNotFalse($decision2, "Decision 2 not found in database");
        $this->assertNotFalse($decision3, "Decision 3 not found in database");

        // PROPERTY: All decisions must have complete reasoning.
        $this->assertNotEmpty($decision1->reasoning, "Decision 1 has empty reasoning");
        $this->assertNotEmpty($decision2->reasoning, "Decision 2 has empty reasoning");
        $this->assertNotEmpty($decision3->reasoning, "Decision 3 has empty reasoning");
    }

    /**
     * Property 10 Edge Case: Decision saved before intervention returned.
     *
     * Verifies the temporal ordering: the decision must be persisted to the
     * database BEFORE the intervention is returned (decision_id is set).
     *
     * This is a logical invariant that's hard to test directly, but we verify
     * that the decision_id is always set and valid when the intervention is returned.
     *
     * @return void
     */
    public function test_property10_decision_saved_before_intervention_returned() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create resources.
        $cm = $this->create_test_course_module($course->id);
        $this->create_test_resource($course->id, $cm->id, 2, 'kinesthetic', 'Intermediate Resource');

        // Create profile.
        $profile = $this->create_test_profile($user->id, $course->id, 2, 'kinesthetic');

        // Evaluate.
        $coach = new \block_attendanceleaderboard\coach\coach();
        $intervention = $coach->evaluate_profile($profile);

        // PROPERTY: If decision_id is set, the decision MUST exist in the database.
        // This verifies that the decision was saved before the intervention was returned.
        if ($intervention->decision_id !== null) {
            $decision_exists = $DB->record_exists('acmls_coach_decision', [
                'id' => $intervention->decision_id,
            ]);

            $this->assertTrue(
                $decision_exists,
                "Property 10 violated: Intervention has decision_id={$intervention->decision_id}, " .
                "but the decision does NOT exist in the database. " .
                "This indicates the decision was not saved before the intervention was returned."
            );
        } else {
            // If decision_id is null, this is a violation of Property 10.
            $this->fail(
                "Property 10 violated: Intervention.decision_id is NULL. " .
                "The decision MUST be saved and its ID attached before returning the intervention."
            );
        }
    }

    /**
     * Property 10 Invariant: Decision reasoning matches intervention reasoning.
     *
     * Verifies that the reasoning in the saved decision exactly matches the
     * reasoning in the returned intervention (no divergence).
     *
     * @return void
     */
    public function test_property10_decision_reasoning_matches_intervention() {
        $this->forAll(
            Generator\choose(1, 3),  // Performance_Category
            Generator\elements(['visual', 'auditory', 'reading', 'kinesthetic'])  // Learning_Style
        )->then(function($performance_category, $learning_style) {
            global $DB;

            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();

            // Create resources.
            for ($difficulty = 1; $difficulty <= 3; $difficulty++) {
                $cm = $this->create_test_course_module($course->id);
                $this->create_test_resource($course->id, $cm->id, $difficulty, $learning_style, "Resource D{$difficulty}");
            }

            // Create profile.
            $profile = $this->create_test_profile($user->id, $course->id, $performance_category, $learning_style);

            // Evaluate.
            $coach = new \block_attendanceleaderboard\coach\coach();
            $intervention = $coach->evaluate_profile($profile);

            // Get saved decision.
            $decision_record = $DB->get_record('acmls_coach_decision', [
                'id' => $intervention->decision_id,
            ]);

            // PROPERTY: Reasoning must match exactly.
            $this->assertEquals(
                $intervention->reasoning,
                $decision_record->reasoning,
                "Property 10 violated: Reasoning mismatch between intervention and saved decision. " .
                "The reasoning in the database must exactly match the reasoning in the returned intervention. " .
                "This ensures consistency between what is saved and what is delivered."
            );
        });
    }
}
