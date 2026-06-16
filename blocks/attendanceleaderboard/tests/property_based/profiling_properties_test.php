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
 * Property-based tests for Profiling System correctness properties.
 *
 * Tests Properties 5, 6, 7, 8:
 * - Property 5: Completeness of Profile Dimension Updates
 * - Property 6: Correctness of Performance_Category Classification
 * - Property 7: Completeness of Profile History Storage
 * - Property 8: Consistency of Learning_Style Classification
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
 * Property-based tests for Profiling System.
 *
 * Uses Eris library for property-based testing to verify correctness properties
 * hold for all possible inputs within the defined domain.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profiling_properties_test extends \advanced_testcase {
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
    // PROPERTY 5: Kelengkapan Pembaruan Dimensi Profil
    // =========================================================================

    /**
     * Property 5: Kelengkapan Pembaruan Dimensi Profil
     *
     * Specification: For any profile update triggered by relevant data arrival,
     * ALL dimensions that have corresponding data must be updated — no dimension
     * should be left unchanged when relevant data is available.
     *
     * This property verifies that:
     * 1. When score data arrives → performance_category AND cognitive_level AND motivation_level are updated
     * 2. When interaction data arrives → learning_style is updated
     * 3. When duration data arrives → engagement_score is updated
     * 4. behavioral_score is always recalculated when any update occurs
     * 5. profile_version is incremented on every update
     * 6. last_updated timestamp is refreshed on every update
     * 7. No dimension is left at its default/initial value when relevant data exists
     *
     * Validates Requirements: 3.1, 3.4, 13.1
     *
     * Test Strategy:
     * - Generate random metrics with all possible data types
     * - Create initial profile with default values
     * - Update profile with generated metrics
     * - Verify ALL relevant dimensions changed from their initial values
     * - Verify dimensions match expected values based on input data
     * - Verify metadata (version, timestamp) was updated
     *
     * @return void
     */
    public function test_property5_profile_dimension_update_completeness() {
        $this->forAll(
            Generator\choose(0, 100),  // Score (0-100)
            Generator\choose(10, 3600),  // Duration in seconds (10s to 1 hour)
            Generator\elements(['video', 'document', 'audio', 'quiz', 'simulation'])  // Resource type
        )->then(function($score, $duration, $resource_type) {
            global $DB;

            // Setup test data.
            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            // Step 1: Create initial profile with default values.
            $initial_profile = $ps->update_profile($user->id, $course->id, []);

            // Capture initial state.
            $initial_cognitive = $initial_profile->cognitive_level;
            $initial_motivation = $initial_profile->motivation_level;
            $initial_performance = $initial_profile->performance_category;
            $initial_learning_style = $initial_profile->learning_style;
            $initial_engagement = $initial_profile->engagement_score;
            $initial_behavioral = $initial_profile->behavioral_score;
            $initial_version = $initial_profile->profile_version;
            $initial_timestamp = $initial_profile->last_updated;

            // Small delay to ensure timestamp changes.
            sleep(1);

            // Step 2: Update profile with ALL types of relevant data.
            $metrics = [
                'score' => $score,
                'duration_seconds' => $duration,
                'resource_type' => $resource_type,
                'interactions' => [$resource_type, $resource_type],  // Repeated to establish pattern
            ];

            $updated_profile = $ps->update_profile($user->id, $course->id, $metrics);

            // ================================================================
            // PROPERTY 5.1: Performance_category MUST be updated when score data arrives
            // ================================================================
            $expected_performance = $this->calculate_expected_performance_category($score);
            $this->assertEquals(
                $expected_performance,
                $updated_profile->performance_category,
                "Property 5 violated: performance_category was not updated correctly. " .
                "Score={$score} should result in category={$expected_performance}, " .
                "but got {$updated_profile->performance_category}. " .
                "All dimensions with relevant data must be updated."
            );

            // ================================================================
            // PROPERTY 5.2: Cognitive_level MUST be updated when score data arrives
            // ================================================================
            // Cognitive level mirrors performance category in current implementation
            $this->assertEquals(
                $expected_performance,
                $updated_profile->cognitive_level,
                "Property 5 violated: cognitive_level was not updated when score data arrived. " .
                "Expected {$expected_performance}, got {$updated_profile->cognitive_level}. " .
                "No dimension should be left unchanged when relevant data is available."
            );

            // ================================================================
            // PROPERTY 5.3: Motivation_level MUST be updated when score data arrives
            // ================================================================
            $this->assertNotEquals(
                $initial_motivation,
                $updated_profile->motivation_level,
                "Property 5 violated: motivation_level was not updated when score data arrived. " .
                "Initial={$initial_motivation}, Updated={$updated_profile->motivation_level}. " .
                "Profile update must cover all dimensions with relevant data."
            );

            // Verify motivation_level is within valid range.
            $this->assertGreaterThanOrEqual(
                0.0,
                $updated_profile->motivation_level,
                "Property 5 violated: motivation_level must be >= 0"
            );

            $this->assertLessThanOrEqual(
                100.0,
                $updated_profile->motivation_level,
                "Property 5 violated: motivation_level must be <= 100"
            );

            // ================================================================
            // PROPERTY 5.4: Learning_style MUST be updated when interaction data arrives
            // ================================================================
            $this->assertNotEquals(
                'unknown',
                $updated_profile->learning_style,
                "Property 5 violated: learning_style was not updated when interaction data arrived. " .
                "Learning style should be classified based on resource_type={$resource_type}, " .
                "but remains 'unknown'. All dimensions must be updated when relevant data exists."
            );

            // Verify learning_style is a valid value.
            $valid_styles = ['visual', 'auditory', 'reading', 'kinesthetic', 'unknown'];
            $this->assertContains(
                $updated_profile->learning_style,
                $valid_styles,
                "Property 5 violated: learning_style '{$updated_profile->learning_style}' is not valid"
            );

            // ================================================================
            // PROPERTY 5.5: Engagement_score MUST be updated when duration data arrives
            // ================================================================
            $this->assertNotEquals(
                $initial_engagement,
                $updated_profile->engagement_score,
                "Property 5 violated: engagement_score was not updated when duration data arrived. " .
                "Initial={$initial_engagement}, Updated={$updated_profile->engagement_score}, " .
                "Duration={$duration}s. No dimension should be left unchanged when relevant data is available."
            );

            // Verify engagement_score is within valid range.
            $this->assertGreaterThanOrEqual(
                0.0,
                $updated_profile->engagement_score,
                "Property 5 violated: engagement_score must be >= 0"
            );

            $this->assertLessThanOrEqual(
                100.0,
                $updated_profile->engagement_score,
                "Property 5 violated: engagement_score must be <= 100"
            );

            // ================================================================
            // PROPERTY 5.6: Behavioral_score MUST be recalculated on every update
            // ================================================================
            $this->assertNotEquals(
                $initial_behavioral,
                $updated_profile->behavioral_score,
                "Property 5 violated: behavioral_score was not recalculated. " .
                "Initial={$initial_behavioral}, Updated={$updated_profile->behavioral_score}. " .
                "Behavioral score must be updated as a composite of engagement and motivation."
            );

            // Verify behavioral_score is calculated correctly as composite.
            $expected_behavioral = round(
                ($updated_profile->engagement_score * 0.4) + ($updated_profile->motivation_level * 0.6),
                2
            );

            $this->assertEqualsWithDelta(
                $expected_behavioral,
                $updated_profile->behavioral_score,
                0.01,
                "Property 5 violated: behavioral_score calculation incorrect. " .
                "Expected {$expected_behavioral}, got {$updated_profile->behavioral_score}"
            );

            // ================================================================
            // PROPERTY 5.7: Profile_version MUST be incremented on every update
            // ================================================================
            $this->assertGreaterThan(
                $initial_version,
                $updated_profile->profile_version,
                "Property 5 violated: profile_version was not incremented. " .
                "Initial version={$initial_version}, Updated version={$updated_profile->profile_version}. " .
                "Version must increment on every profile update."
            );

            // ================================================================
            // PROPERTY 5.8: Last_updated timestamp MUST be refreshed on every update
            // ================================================================
            $this->assertGreaterThan(
                $initial_timestamp,
                $updated_profile->last_updated,
                "Property 5 violated: last_updated timestamp was not refreshed. " .
                "Initial={$initial_timestamp}, Updated={$updated_profile->last_updated}. " .
                "Timestamp must be updated on every profile update."
            );

            // ================================================================
            // PROPERTY 5.9: Verify profile was persisted to database
            // ================================================================
            $db_record = $DB->get_record('acmls_learner_profile', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ]);

            $this->assertNotFalse(
                $db_record,
                "Property 5 violated: Updated profile was not persisted to database"
            );

            // Verify all dimensions in database match the updated profile.
            $this->assertEquals(
                $updated_profile->cognitive_level,
                $db_record->cognitive_level,
                "Property 5 violated: cognitive_level in database does not match updated profile"
            );

            $this->assertEquals(
                $updated_profile->motivation_level,
                $db_record->motivation_level,
                "Property 5 violated: motivation_level in database does not match updated profile"
            );

            $this->assertEquals(
                $updated_profile->performance_category,
                $db_record->performance_category,
                "Property 5 violated: performance_category in database does not match updated profile"
            );

            $this->assertEquals(
                $updated_profile->learning_style,
                $db_record->learning_style,
                "Property 5 violated: learning_style in database does not match updated profile"
            );

            $this->assertEquals(
                $updated_profile->engagement_score,
                $db_record->engagement_score,
                "Property 5 violated: engagement_score in database does not match updated profile"
            );

            $this->assertEquals(
                $updated_profile->behavioral_score,
                $db_record->behavioral_score,
                "Property 5 violated: behavioral_score in database does not match updated profile"
            );
        });
    }

    /**
     * Property 5 Edge Case: Partial data updates.
     *
     * Verifies that when only SOME types of data arrive (e.g., only score, no duration),
     * only the relevant dimensions are updated, while others remain unchanged.
     *
     * @return void
     */
    public function test_property5_partial_data_selective_updates() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        // Create initial profile.
        $initial = $ps->update_profile($user->id, $course->id, []);

        sleep(1);

        // Update with ONLY score data (no duration, no interactions).
        $updated = $ps->update_profile($user->id, $course->id, [
            'score' => 75.0,
        ]);

        // PROPERTY: Score-related dimensions MUST be updated.
        $this->assertNotEquals(
            $initial->performance_category,
            $updated->performance_category,
            "Property 5 violated: performance_category should be updated when score data arrives"
        );

        $this->assertNotEquals(
            $initial->motivation_level,
            $updated->motivation_level,
            "Property 5 violated: motivation_level should be updated when score data arrives"
        );

        // PROPERTY: Non-score dimensions should remain unchanged (no relevant data).
        $this->assertEquals(
            $initial->learning_style,
            $updated->learning_style,
            "Property 5 violated: learning_style should NOT change without interaction data"
        );

        // Note: engagement_score might change slightly due to weighted average with zero duration,
        // but it should not increase significantly.
        // Behavioral score will change because it's a composite of engagement and motivation.
    }

    /**
     * Property 5 Edge Case: Multiple sequential updates.
     *
     * Verifies that each update correctly modifies all relevant dimensions,
     * and that dimensions accumulate changes correctly over multiple updates.
     *
     * @return void
     */
    public function test_property5_sequential_updates_completeness() {
        $this->forAll(
            Generator\choose(2, 10)  // Number of sequential updates
        )->then(function($num_updates) {
            global $DB;

            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            // Create initial profile.
            $profile = $ps->update_profile($user->id, $course->id, []);
            $initial_version = $profile->profile_version;

            // Perform multiple sequential updates.
            for ($i = 0; $i < $num_updates; $i++) {
                $score = rand(0, 100);
                $duration = rand(60, 600);
                $resource_type = ['video', 'document', 'audio'][$i % 3];

                $previous_version = $profile->profile_version;
                $previous_timestamp = $profile->last_updated;

                sleep(1);  // Ensure timestamp changes.

                $profile = $ps->update_profile($user->id, $course->id, [
                    'score' => $score,
                    'duration_seconds' => $duration,
                    'resource_type' => $resource_type,
                ]);

                // PROPERTY: Version must increment on each update.
                $this->assertEquals(
                    $previous_version + 1,
                    $profile->profile_version,
                    "Property 5 violated: Version should increment by 1 on update {$i}. " .
                    "Previous={$previous_version}, Current={$profile->profile_version}"
                );

                // PROPERTY: Timestamp must be refreshed on each update.
                $this->assertGreaterThan(
                    $previous_timestamp,
                    $profile->last_updated,
                    "Property 5 violated: Timestamp should be refreshed on update {$i}"
                );

                // PROPERTY: All score-related dimensions must be updated.
                $expected_performance = $this->calculate_expected_performance_category($score);
                $this->assertEquals(
                    $expected_performance,
                    $profile->performance_category,
                    "Property 5 violated: performance_category not updated correctly on update {$i}"
                );
            }

            // PROPERTY: Final version should equal initial + number of updates.
            $expected_final_version = $initial_version + $num_updates;
            $this->assertEquals(
                $expected_final_version,
                $profile->profile_version,
                "Property 5 violated: Final version mismatch after {$num_updates} updates. " .
                "Expected {$expected_final_version}, got {$profile->profile_version}"
            );
        });
    }

    /**
     * Property 5 Edge Case: Boundary score values.
     *
     * Verifies that profile dimensions are updated correctly for boundary score values
     * (0, 59.99, 60, 79.99, 80, 100).
     *
     * @return void
     */
    public function test_property5_boundary_score_updates() {
        $boundary_scores = [
            0 => 1,      // Low
            59.99 => 1,  // Low (just below threshold)
            60 => 2,     // Middle (exactly at threshold)
            79.99 => 2,  // Middle (just below threshold)
            80 => 3,     // High (exactly at threshold)
            100 => 3,    // High (maximum)
        ];

        foreach ($boundary_scores as $score => $expected_category) {
            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            // Create initial profile.
            $initial = $ps->update_profile($user->id, $course->id, []);

            sleep(1);

            // Update with boundary score.
            $updated = $ps->update_profile($user->id, $course->id, [
                'score' => $score,
            ]);

            // PROPERTY: Performance category must be correct for boundary value.
            $this->assertEquals(
                $expected_category,
                $updated->performance_category,
                "Property 5 violated: Boundary score {$score} should result in category {$expected_category}, " .
                "but got {$updated->performance_category}"
            );

            // PROPERTY: Cognitive level must match performance category.
            $this->assertEquals(
                $expected_category,
                $updated->cognitive_level,
                "Property 5 violated: cognitive_level should match performance_category for score {$score}"
            );

            // PROPERTY: Version must be incremented.
            $this->assertGreaterThan(
                $initial->profile_version,
                $updated->profile_version,
                "Property 5 violated: Version not incremented for boundary score {$score}"
            );
        }
    }

    /**
     * Property 5 Edge Case: Zero duration.
     *
     * Verifies that profile update handles zero duration correctly without
     * leaving engagement_score unchanged or causing errors.
     *
     * @return void
     */
    public function test_property5_zero_duration_handling() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        // Create initial profile with some engagement.
        $initial = $ps->update_profile($user->id, $course->id, [
            'duration_seconds' => 600,  // 10 minutes
        ]);

        $initial_engagement = $initial->engagement_score;

        sleep(1);

        // Update with zero duration.
        $updated = $ps->update_profile($user->id, $course->id, [
            'duration_seconds' => 0,
        ]);

        // PROPERTY: Engagement score should be recalculated (weighted average with 0).
        // It should decrease from initial value due to weighted average.
        $this->assertNotEquals(
                $initial_engagement,
            $updated->engagement_score,
            "Property 5 violated: engagement_score should be updated even with zero duration"
        );

        // PROPERTY: Version must be incremented.
        $this->assertGreaterThan(
            $initial->profile_version,
            $updated->profile_version,
            "Property 5 violated: Version not incremented for zero duration update"
        );
    }

    /**
     * Property 5 Invariant: All dimensions have valid values after update.
     *
     * Verifies that after any update, all profile dimensions contain valid values
     * within their defined ranges and constraints.
     *
     * @return void
     */
    public function test_property5_all_dimensions_valid_after_update() {
        $this->forAll(
            Generator\choose(0, 100),
            Generator\choose(0, 7200),
            Generator\elements(['video', 'document', 'audio', 'quiz', 'simulation'])
        )->then(function($score, $duration, $resource_type) {
            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            $profile = $ps->update_profile($user->id, $course->id, [
                'score' => $score,
                'duration_seconds' => $duration,
                'resource_type' => $resource_type,
            ]);

            // PROPERTY: cognitive_level must be 1, 2, or 3.
            $this->assertContains(
                $profile->cognitive_level,
                [1, 2, 3],
                "Property 5 violated: cognitive_level must be 1, 2, or 3, got {$profile->cognitive_level}"
            );

            // PROPERTY: motivation_level must be in [0, 100].
            $this->assertGreaterThanOrEqual(0.0, $profile->motivation_level);
            $this->assertLessThanOrEqual(100.0, $profile->motivation_level);

            // PROPERTY: performance_category must be 1, 2, or 3.
            $this->assertContains(
                $profile->performance_category,
                [1, 2, 3],
                "Property 5 violated: performance_category must be 1, 2, or 3, got {$profile->performance_category}"
            );

            // PROPERTY: learning_style must be a valid value.
            $this->assertContains(
                $profile->learning_style,
                ['visual', 'auditory', 'reading', 'kinesthetic', 'unknown'],
                "Property 5 violated: Invalid learning_style '{$profile->learning_style}'"
            );

            // PROPERTY: behavioral_score must be in [0, 100].
            $this->assertGreaterThanOrEqual(0.0, $profile->behavioral_score);
            $this->assertLessThanOrEqual(100.0, $profile->behavioral_score);

            // PROPERTY: engagement_score must be in [0, 100].
            $this->assertGreaterThanOrEqual(0.0, $profile->engagement_score);
            $this->assertLessThanOrEqual(100.0, $profile->engagement_score);

            // PROPERTY: profile_version must be >= 1.
            $this->assertGreaterThanOrEqual(
                1,
                $profile->profile_version,
                "Property 5 violated: profile_version must be >= 1"
            );

            // PROPERTY: last_updated must be a valid timestamp.
            $this->assertGreaterThan(
                0,
                $profile->last_updated,
                "Property 5 violated: last_updated must be a valid timestamp"
            );

            // PROPERTY: created_at must be a valid timestamp.
            $this->assertGreaterThan(
                0,
                $profile->created_at,
                "Property 5 violated: created_at must be a valid timestamp"
            );
        });
    }

    // =========================================================================
    // PROPERTY 6: Kebenaran Klasifikasi Performance_Category
    // =========================================================================

    /**
     * Property 6: Kebenaran Klasifikasi Performance_Category
     *
     * Specification: For ALL score values in the range [0, 100], the classification
     * of Performance_Category MUST be consistent with the defined rules:
     *   - score < 60  → Category 1 (Low)
     *   - 60 ≤ score < 80 → Category 2 (Middle)
     *   - score ≥ 80  → Category 3 (High)
     *
     * This property verifies that:
     * 1. Classification is deterministic (same input always produces same output)
     * 2. Classification follows the exact boundary rules
     * 3. No score value produces an invalid category (must be 1, 2, or 3)
     * 4. Boundary values are classified correctly (59.99→Low, 60→Middle, 79.99→Middle, 80→High)
     * 5. Classification is consistent across multiple invocations
     * 6. Edge cases (0, 100, negative, >100) are handled correctly
     *
     * Validates Requirements: 3.2, 3.3, 4.3
     *
     * Test Strategy:
     * - Generate random scores across the entire valid range [0, 100]
     * - Test boundary values explicitly
     * - Verify classification matches the specification rules
     * - Verify determinism (same score always produces same category)
     * - Verify no invalid categories are ever produced
     *
     * @return void
     */
    public function test_property6_performance_category_classification_correctness() {
        $this->forAll(
            Generator\choose(0, 10000)  // Generate integers 0-10000, will divide by 100 to get 0.00-100.00
        )->then(function($score_int) {
            $score = $score_int / 100.0;  // Convert to float with 2 decimal places precision

            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            // Classify the score.
            $category = $ps->classify_performance_category($score);

            // ================================================================
            // PROPERTY 6.1: Category must be one of the valid values (1, 2, or 3)
            // ================================================================
            $this->assertContains(
                $category,
                [1, 2, 3],
                "Property 6 violated: classify_performance_category({$score}) returned invalid category {$category}. " .
                "Valid categories are 1 (Low), 2 (Middle), 3 (High)."
            );

            // ================================================================
            // PROPERTY 6.2: Classification must follow the exact rules
            // ================================================================
            if ($score < 60.0) {
                $this->assertEquals(
                    1,
                    $category,
                    "Property 6 violated: Score {$score} < 60 must be classified as Low (1), but got {$category}. " .
                    "Classification rule: score < 60 → Category 1 (Low)."
                );
            } elseif ($score < 80.0) {
                $this->assertEquals(
                    2,
                    $category,
                    "Property 6 violated: Score {$score} in [60, 80) must be classified as Middle (2), but got {$category}. " .
                    "Classification rule: 60 ≤ score < 80 → Category 2 (Middle)."
                );
            } else {
                $this->assertEquals(
                    3,
                    $category,
                    "Property 6 violated: Score {$score} ≥ 80 must be classified as High (3), but got {$category}. " .
                    "Classification rule: score ≥ 80 → Category 3 (High)."
                );
            }

            // ================================================================
            // PROPERTY 6.3: Classification must be deterministic
            // ================================================================
            $category_second = $ps->classify_performance_category($score);
            $this->assertEquals(
                $category,
                $category_second,
                "Property 6 violated: Classification is not deterministic. " .
                "Score {$score} produced category {$category} on first call, " .
                "but {$category_second} on second call. Same input must always produce same output."
            );

            // ================================================================
            // PROPERTY 6.4: Classification must be consistent with expected category
            // ================================================================
            $expected_category = $this->calculate_expected_performance_category($score);
            $this->assertEquals(
                $expected_category,
                $category,
                "Property 6 violated: Classification inconsistent with specification. " .
                "Score {$score} should be category {$expected_category}, but got {$category}."
            );
        });
    }

    /**
     * Property 6 Critical Test: Boundary values.
     *
     * Explicitly tests the critical boundary values where classification changes.
     * These are the most error-prone cases and must be verified exhaustively.
     *
     * Boundary values:
     * - 0.00 → Low (minimum valid score)
     * - 59.99 → Low (just below Middle threshold)
     * - 60.00 → Middle (exactly at threshold)
     * - 79.99 → Middle (just below High threshold)
     * - 80.00 → High (exactly at threshold)
     * - 100.00 → High (maximum valid score)
     *
     * @return void
     */
    public function test_property6_boundary_values_classification() {
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $boundary_cases = [
            // [score, expected_category, description]
            [0.00, 1, 'Minimum score (0.00) must be Low'],
            [59.99, 1, 'Score 59.99 (just below 60) must be Low'],
            [60.00, 2, 'Score 60.00 (exactly at threshold) must be Middle'],
            [60.01, 2, 'Score 60.01 (just above 60) must be Middle'],
            [79.99, 2, 'Score 79.99 (just below 80) must be Middle'],
            [80.00, 3, 'Score 80.00 (exactly at threshold) must be High'],
            [80.01, 3, 'Score 80.01 (just above 80) must be High'],
            [100.00, 3, 'Maximum score (100.00) must be High'],
        ];

        foreach ($boundary_cases as [$score, $expected_category, $description]) {
            $category = $ps->classify_performance_category($score);

            $this->assertEquals(
                $expected_category,
                $category,
                "Property 6 violated: {$description}. " .
                "Score {$score} should be category {$expected_category}, but got {$category}. " .
                "Boundary values are critical for correct classification."
            );
        }
    }

    /**
     * Property 6 Edge Case: Scores outside valid range.
     *
     * Verifies that the classification function handles edge cases gracefully:
     * - Negative scores
     * - Scores > 100
     * - Very large scores
     *
     * While these are technically invalid inputs, the function should not crash
     * and should produce a reasonable classification.
     *
     * @return void
     */
    public function test_property6_edge_cases_outside_valid_range() {
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $edge_cases = [
            // [score, expected_behavior_description]
            [-10.0, 'Negative score should be classified as Low (1)'],
            [-0.01, 'Slightly negative score should be classified as Low (1)'],
            [100.01, 'Score slightly above 100 should be classified as High (3)'],
            [150.0, 'Score well above 100 should be classified as High (3)'],
            [999.99, 'Very large score should be classified as High (3)'],
        ];

        foreach ($edge_cases as [$score, $description]) {
            // Should not throw exception.
            $category = $ps->classify_performance_category($score);

            // Must return a valid category.
            $this->assertContains(
                $category,
                [1, 2, 3],
                "Property 6 violated: {$description}. " .
                "Score {$score} produced invalid category {$category}. " .
                "Even for edge cases, classification must return valid category (1, 2, or 3)."
            );

            // Verify expected classification based on rules.
            if ($score < 60.0) {
                $this->assertEquals(1, $category, "{$description} - Expected Low (1)");
            } elseif ($score < 80.0) {
                $this->assertEquals(2, $category, "{$description} - Expected Middle (2)");
            } else {
                $this->assertEquals(3, $category, "{$description} - Expected High (3)");
            }
        }
    }

    /**
     * Property 6 Invariant: Classification is monotonic.
     *
     * Verifies that classification is monotonic: if score1 < score2, then
     * category(score1) ≤ category(score2).
     *
     * This ensures that higher scores never result in lower categories,
     * which would violate the fundamental logic of performance classification.
     *
     * @return void
     */
    public function test_property6_classification_is_monotonic() {
        $this->forAll(
            Generator\choose(0, 10000),  // score1
            Generator\choose(0, 10000)   // score2
        )->then(function($score1_int, $score2_int) {
            $score1 = $score1_int / 100.0;
            $score2 = $score2_int / 100.0;

            // Ensure score1 < score2 for this test.
            if ($score1 >= $score2) {
                return;  // Skip this iteration.
            }

            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            $category1 = $ps->classify_performance_category($score1);
            $category2 = $ps->classify_performance_category($score2);

            // PROPERTY: Monotonicity - higher score must not result in lower category.
            $this->assertLessThanOrEqual(
                $category2,
                $category1,
                "Property 6 violated: Classification is not monotonic. " .
                "Score {$score1} → category {$category1}, " .
                "Score {$score2} → category {$category2}. " .
                "Since {$score1} < {$score2}, we must have category({$score1}) ≤ category({$score2}). " .
                "Higher scores must never result in lower categories."
            );
        });
    }

    /**
     * Property 6 Invariant: Classification partitions the score space.
     *
     * Verifies that the three categories partition the score space [0, 100]
     * completely with no gaps or overlaps:
     * - Every score in [0, 60) is Low
     * - Every score in [60, 80) is Middle
     * - Every score in [80, 100] is High
     *
     * @return void
     */
    public function test_property6_classification_partitions_score_space() {
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        // Test Low partition [0, 60).
        for ($score = 0.0; $score < 60.0; $score += 0.5) {
            $category = $ps->classify_performance_category($score);
            $this->assertEquals(
                1,
                $category,
                "Property 6 violated: Score {$score} in [0, 60) must be Low (1), but got {$category}. " .
                "Low partition is [0, 60)."
            );
        }

        // Test Middle partition [60, 80).
        for ($score = 60.0; $score < 80.0; $score += 0.5) {
            $category = $ps->classify_performance_category($score);
            $this->assertEquals(
                2,
                $category,
                "Property 6 violated: Score {$score} in [60, 80) must be Middle (2), but got {$category}. " .
                "Middle partition is [60, 80)."
            );
        }

        // Test High partition [80, 100].
        for ($score = 80.0; $score <= 100.0; $score += 0.5) {
            $category = $ps->classify_performance_category($score);
            $this->assertEquals(
                3,
                $category,
                "Property 6 violated: Score {$score} in [80, 100] must be High (3), but got {$category}. " .
                "High partition is [80, 100]."
            );
        }
    }

    /**
     * Property 6 Stress Test: Classification consistency under high volume.
     *
     * Verifies that classification remains consistent even when called
     * many times in rapid succession (stress test).
     *
     * @return void
     */
    public function test_property6_classification_consistency_under_stress() {
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $test_scores = [0.0, 30.0, 59.99, 60.0, 70.0, 79.99, 80.0, 90.0, 100.0];

        foreach ($test_scores as $score) {
            $expected_category = $this->calculate_expected_performance_category($score);

            // Call classification 100 times for the same score.
            for ($i = 0; $i < 100; $i++) {
                $category = $ps->classify_performance_category($score);

                $this->assertEquals(
                    $expected_category,
                    $category,
                    "Property 6 violated: Classification inconsistent under stress. " .
                    "Score {$score} should always be category {$expected_category}, " .
                    "but got {$category} on iteration {$i}. " .
                    "Classification must be deterministic and consistent."
                );
            }
        }
    }

    /**
     * Property 6 Integration Test: Classification in profile update context.
     *
     * Verifies that when classify_performance_category is used within the
     * profile update workflow, it produces correct results that are persisted
     * to the database.
     *
     * @return void
     */
    public function test_property6_classification_in_profile_update_context() {
        $this->forAll(
            Generator\choose(0, 10000)
        )->then(function($score_int) {
            global $DB;

            $score = $score_int / 100.0;

            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            // Update profile with score.
            $profile = $ps->update_profile($user->id, $course->id, [
                'score' => $score,
            ]);

            // Calculate expected category.
            $expected_category = $this->calculate_expected_performance_category($score);

            // PROPERTY: Profile's performance_category must match classification result.
            $this->assertEquals(
                $expected_category,
                $profile->performance_category,
                "Property 6 violated: Profile performance_category does not match classification. " .
                "Score {$score} should be category {$expected_category}, " .
                "but profile has {$profile->performance_category}."
            );

            // PROPERTY: Database record must match profile.
            $db_record = $DB->get_record('acmls_learner_profile', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ]);

            $this->assertEquals(
                $expected_category,
                $db_record->performance_category,
                "Property 6 violated: Database performance_category does not match classification. " .
                "Score {$score} should be category {$expected_category}, " .
                "but database has {$db_record->performance_category}."
            );

            // PROPERTY: Direct classification call must match profile result.
            $direct_category = $ps->classify_performance_category($score);
            $this->assertEquals(
                $profile->performance_category,
                $direct_category,
                "Property 6 violated: Direct classification call does not match profile update result. " .
                "Score {$score}: profile has category {$profile->performance_category}, " .
                "but direct call returned {$direct_category}."
            );
        });
    }

    // =========================================================================
    // PROPERTY 7: Kelengkapan Penyimpanan Riwayat Profil
    // =========================================================================

    /**
     * Property 7: Kelengkapan Penyimpanan Riwayat Profil
     *
     * Specification: For any sequence of profile updates, ALL profile versions
     * MUST be stored in acmls_learner_record and MUST be retrievable in correct
     * chronological order.
     *
     * This property verifies that:
     * 1. Every profile update creates a snapshot in acmls_learner_record
     * 2. All snapshots are stored with correct profile_version numbers
     * 3. Snapshots are retrievable in chronological order (by timecreated)
     * 4. No profile versions are missing from the history
     * 5. Each snapshot contains complete profile data
     * 6. Timestamps are monotonically increasing
     * 7. Profile versions are monotonically increasing
     * 8. The number of snapshots equals the number of updates performed
     *
     * Validates Requirements: 3.5, 10.2, 10.3
     *
     * Test Strategy:
     * - Generate random number of sequential profile updates (2-20)
     * - Perform updates with varying metrics
     * - Retrieve profile history using get_profile_history()
     * - Verify completeness: count matches number of updates
     * - Verify chronological order: timestamps and versions are monotonic
     * - Verify data integrity: each snapshot contains expected data
     * - Verify no gaps in version sequence
     *
     * @return void
     */
    public function test_property7_profile_history_completeness_and_order() {
        $this->forAll(
            Generator\choose(2, 20)  // Number of sequential updates (2-20)
        )->then(function($num_updates) {
            global $DB;

            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            // Track expected data for each update.
            $expected_versions = [];
            $update_timestamps = [];

            // Perform sequential profile updates.
            for ($i = 0; $i < $num_updates; $i++) {
                // Generate random metrics for this update.
                $score = rand(0, 100);
                $duration = rand(60, 3600);
                $resource_types = ['video', 'document', 'audio', 'quiz', 'simulation'];
                $resource_type = $resource_types[$i % count($resource_types)];

                // Small delay to ensure timestamps are different.
                if ($i > 0) {
                    sleep(1);
                }

                $before_update = time();

                // Perform update.
                $profile = $ps->update_profile($user->id, $course->id, [
                    'score' => $score,
                    'duration_seconds' => $duration,
                    'resource_type' => $resource_type,
                ]);

                $after_update = time();

                // Record expected version and timestamp range.
                $expected_versions[] = [
                    'version' => $profile->profile_version,
                    'score' => $score,
                    'performance_category' => $profile->performance_category,
                    'timestamp_min' => $before_update,
                    'timestamp_max' => $after_update,
                ];

                $update_timestamps[] = $profile->last_updated;
            }

            // ================================================================
            // PROPERTY 7.1: Retrieve profile history
            // ================================================================
            $history = $ps->get_profile_history($user->id, $course->id);

            $this->assertIsArray(
                $history,
                "Property 7 violated: get_profile_history() must return an array"
            );

            // ================================================================
            // PROPERTY 7.2: Completeness - number of snapshots equals number of updates
            // ================================================================
            $this->assertCount(
                $num_updates,
                $history,
                "Property 7 violated: Number of stored snapshots ({count($history)}) does not match " .
                "number of updates performed ({$num_updates}). " .
                "All profile versions must be stored."
            );

            // ================================================================
            // PROPERTY 7.3: Chronological order - timestamps are monotonically increasing
            // ================================================================
            $previous_timestamp = 0;
            foreach ($history as $index => $snapshot) {
                $this->assertArrayHasKey(
                    '_timecreated',
                    $snapshot,
                    "Property 7 violated: Snapshot {$index} missing _timecreated field"
                );

                $current_timestamp = $snapshot['_timecreated'];

                $this->assertGreaterThanOrEqual(
                    $previous_timestamp,
                    $current_timestamp,
                    "Property 7 violated: Timestamps not in chronological order. " .
                    "Snapshot {$index} has timestamp {$current_timestamp}, " .
                    "but previous snapshot had {$previous_timestamp}. " .
                    "Snapshots must be retrievable in correct chronological order."
                );

                $previous_timestamp = $current_timestamp;
            }

            // ================================================================
            // PROPERTY 7.4: Version sequence - profile versions are monotonically increasing
            // ================================================================
            $previous_version = 0;
            foreach ($history as $index => $snapshot) {
                $this->assertArrayHasKey(
                    'profile_version',
                    $snapshot,
                    "Property 7 violated: Snapshot {$index} missing profile_version field"
                );

                $current_version = $snapshot['profile_version'];

                $this->assertGreaterThan(
                    $previous_version,
                    $current_version,
                    "Property 7 violated: Profile versions not monotonically increasing. " .
                    "Snapshot {$index} has version {$current_version}, " .
                    "but previous snapshot had version {$previous_version}. " .
                    "Version sequence must be strictly increasing."
                );

                $previous_version = $current_version;
            }

            // ================================================================
            // PROPERTY 7.5: No gaps in version sequence
            // ================================================================
            $first_version = $history[0]['profile_version'];
            $last_version = $history[count($history) - 1]['profile_version'];
            $expected_version_count = $last_version - $first_version + 1;

            $this->assertEquals(
                $expected_version_count,
                count($history),
                "Property 7 violated: Gap detected in version sequence. " .
                "First version={$first_version}, Last version={$last_version}, " .
                "Expected {$expected_version_count} versions, but found " . count($history) . ". " .
                "No profile versions should be missing from history."
            );

            // ================================================================
            // PROPERTY 7.6: Each snapshot contains complete profile data
            // ================================================================
            $required_fields = [
                'userid',
                'courseid',
                'cognitive_level',
                'motivation_level',
                'performance_category',
                'learning_style',
                'behavioral_score',
                'engagement_score',
                'profile_version',
                'last_updated',
            ];

            foreach ($history as $index => $snapshot) {
                foreach ($required_fields as $field) {
                    $this->assertArrayHasKey(
                        $field,
                        $snapshot,
                        "Property 7 violated: Snapshot {$index} (version {$snapshot['profile_version']}) " .
                        "missing required field '{$field}'. " .
                        "Each snapshot must contain complete profile data."
                    );
                }

                // Verify userid and courseid match.
                $this->assertEquals(
                    $user->id,
                    $snapshot['userid'],
                    "Property 7 violated: Snapshot {$index} has incorrect userid"
                );

                $this->assertEquals(
                    $course->id,
                    $snapshot['courseid'],
                    "Property 7 violated: Snapshot {$index} has incorrect courseid"
                );
            }

            // ================================================================
            // PROPERTY 7.7: Verify snapshots match expected update sequence
            // ================================================================
            foreach ($history as $index => $snapshot) {
                $expected = $expected_versions[$index];

                $this->assertEquals(
                    $expected['version'],
                    $snapshot['profile_version'],
                    "Property 7 violated: Snapshot {$index} has incorrect version. " .
                    "Expected {$expected['version']}, got {$snapshot['profile_version']}"
                );

                $this->assertEquals(
                    $expected['performance_category'],
                    $snapshot['performance_category'],
                    "Property 7 violated: Snapshot {$index} has incorrect performance_category. " .
                    "Expected {$expected['performance_category']}, got {$snapshot['performance_category']}"
                );

                // Verify timestamp is within expected range.
                $this->assertGreaterThanOrEqual(
                    $expected['timestamp_min'],
                    $snapshot['_timecreated'],
                    "Property 7 violated: Snapshot {$index} timestamp too early"
                );

                $this->assertLessThanOrEqual(
                    $expected['timestamp_max'] + 2,  // Allow 2 second tolerance.
                    $snapshot['_timecreated'],
                    "Property 7 violated: Snapshot {$index} timestamp too late"
                );
            }

            // ================================================================
            // PROPERTY 7.8: Verify database records exist and are correct
            // ================================================================
            $db_records = $DB->get_records('acmls_learner_record', [
                'userid' => $user->id,
                'courseid' => $course->id,
                'record_type' => 'profile_snapshot',
                'source_component' => 'profiling',
            ], 'timecreated ASC, id ASC');

            $this->assertCount(
                $num_updates,
                $db_records,
                "Property 7 violated: Number of database records does not match number of updates"
            );

            // Verify each database record has correct structure.
            foreach ($db_records as $record) {
                $this->assertEquals('profile_snapshot', $record->record_type);
                $this->assertEquals('profiling', $record->source_component);
                $this->assertNotEmpty($record->data_payload);

                // Verify data_payload is valid JSON.
                $decoded = json_decode($record->data_payload, true);
                $this->assertIsArray(
                    $decoded,
                    "Property 7 violated: Snapshot data_payload is not valid JSON"
                );

                // Verify profile_version is set.
                $this->assertGreaterThan(
                    0,
                    $record->profile_version,
                    "Property 7 violated: Database record has invalid profile_version"
                );
            }
        });
    }

    /**
     * Property 7 Edge Case: Single update creates exactly one snapshot.
     *
     * Verifies that even a single profile update creates a retrievable snapshot.
     *
     * @return void
     */
    public function test_property7_single_update_creates_snapshot() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        // Perform single update.
        $profile = $ps->update_profile($user->id, $course->id, [
            'score' => 75.0,
        ]);

        // Retrieve history.
        $history = $ps->get_profile_history($user->id, $course->id);

        // PROPERTY: Exactly one snapshot must exist.
        $this->assertCount(
            1,
            $history,
            "Property 7 violated: Single update should create exactly one snapshot, " .
            "but found " . count($history)
        );

        // PROPERTY: Snapshot must contain correct data.
        $snapshot = $history[0];
        $this->assertEquals($user->id, $snapshot['userid']);
        $this->assertEquals($course->id, $snapshot['courseid']);
        $this->assertEquals($profile->profile_version, $snapshot['profile_version']);
        $this->assertEquals($profile->performance_category, $snapshot['performance_category']);
    }

    /**
     * Property 7 Edge Case: Updates with identical data still create separate snapshots.
     *
     * Verifies that even if profile data doesn't change, each update creates
     * a new snapshot with incremented version.
     *
     * @return void
     */
    public function test_property7_identical_updates_create_separate_snapshots() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $num_updates = 5;

        // Perform multiple updates with identical data.
        for ($i = 0; $i < $num_updates; $i++) {
            sleep(1);  // Ensure different timestamps.
            $ps->update_profile($user->id, $course->id, [
                'score' => 80.0,  // Same score every time.
            ]);
        }

        // Retrieve history.
        $history = $ps->get_profile_history($user->id, $course->id);

        // PROPERTY: All updates must create snapshots.
        $this->assertCount(
            $num_updates,
            $history,
            "Property 7 violated: {$num_updates} updates should create {$num_updates} snapshots, " .
            "even with identical data, but found " . count($history)
        );

        // PROPERTY: Versions must be different.
        $versions = array_column($history, 'profile_version');
        $unique_versions = array_unique($versions);
        $this->assertCount(
            $num_updates,
            $unique_versions,
            "Property 7 violated: Each update must create a snapshot with unique version"
        );

        // PROPERTY: Timestamps must be different.
        $timestamps = array_column($history, '_timecreated');
        $unique_timestamps = array_unique($timestamps);
        $this->assertCount(
            $num_updates,
            $unique_timestamps,
            "Property 7 violated: Each update must create a snapshot with unique timestamp"
        );
    }

    /**
     * Property 7 Edge Case: Concurrent updates for different users don't interfere.
     *
     * Verifies that profile history for different users is correctly isolated.
     *
     * @return void
     */
    public function test_property7_user_isolation_in_history() {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $updates_user1 = 3;
        $updates_user2 = 5;

        // Update user1 profile.
        for ($i = 0; $i < $updates_user1; $i++) {
            sleep(1);
            $ps->update_profile($user1->id, $course->id, ['score' => rand(0, 100)]);
        }

        // Update user2 profile.
        for ($i = 0; $i < $updates_user2; $i++) {
            sleep(1);
            $ps->update_profile($user2->id, $course->id, ['score' => rand(0, 100)]);
        }

        // Retrieve histories.
        $history1 = $ps->get_profile_history($user1->id, $course->id);
        $history2 = $ps->get_profile_history($user2->id, $course->id);

        // PROPERTY: Each user's history contains only their snapshots.
        $this->assertCount(
            $updates_user1,
            $history1,
            "Property 7 violated: User1 history should contain {$updates_user1} snapshots"
        );

        $this->assertCount(
            $updates_user2,
            $history2,
            "Property 7 violated: User2 history should contain {$updates_user2} snapshots"
        );

        // PROPERTY: All snapshots in history1 belong to user1.
        foreach ($history1 as $snapshot) {
            $this->assertEquals(
                $user1->id,
                $snapshot['userid'],
                "Property 7 violated: User1 history contains snapshot from different user"
            );
        }

        // PROPERTY: All snapshots in history2 belong to user2.
        foreach ($history2 as $snapshot) {
            $this->assertEquals(
                $user2->id,
                $snapshot['userid'],
                "Property 7 violated: User2 history contains snapshot from different user"
            );
        }
    }

    /**
     * Property 7 Edge Case: Course isolation in history.
     *
     * Verifies that profile history for the same user in different courses
     * is correctly isolated.
     *
     * @return void
     */
    public function test_property7_course_isolation_in_history() {
        $user = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $updates_course1 = 4;
        $updates_course2 = 6;

        // Update profile in course1.
        for ($i = 0; $i < $updates_course1; $i++) {
            sleep(1);
            $ps->update_profile($user->id, $course1->id, ['score' => rand(0, 100)]);
        }

        // Update profile in course2.
        for ($i = 0; $i < $updates_course2; $i++) {
            sleep(1);
            $ps->update_profile($user->id, $course2->id, ['score' => rand(0, 100)]);
        }

        // Retrieve histories.
        $history1 = $ps->get_profile_history($user->id, $course1->id);
        $history2 = $ps->get_profile_history($user->id, $course2->id);

        // PROPERTY: Each course's history contains only its snapshots.
        $this->assertCount(
            $updates_course1,
            $history1,
            "Property 7 violated: Course1 history should contain {$updates_course1} snapshots"
        );

        $this->assertCount(
            $updates_course2,
            $history2,
            "Property 7 violated: Course2 history should contain {$updates_course2} snapshots"
        );

        // PROPERTY: All snapshots in history1 belong to course1.
        foreach ($history1 as $snapshot) {
            $this->assertEquals(
                $course1->id,
                $snapshot['courseid'],
                "Property 7 violated: Course1 history contains snapshot from different course"
            );
        }

        // PROPERTY: All snapshots in history2 belong to course2.
        foreach ($history2 as $snapshot) {
            $this->assertEquals(
                $course2->id,
                $snapshot['courseid'],
                "Property 7 violated: Course2 history contains snapshot from different course"
            );
        }
    }

    /**
     * Property 7 Invariant: History retrieval is idempotent.
     *
     * Verifies that calling get_profile_history() multiple times returns
     * the same results (retrieval doesn't modify the data).
     *
     * @return void
     */
    public function test_property7_history_retrieval_is_idempotent() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        // Perform some updates.
        for ($i = 0; $i < 5; $i++) {
            sleep(1);
            $ps->update_profile($user->id, $course->id, ['score' => rand(0, 100)]);
        }

        // Retrieve history multiple times.
        $history1 = $ps->get_profile_history($user->id, $course->id);
        $history2 = $ps->get_profile_history($user->id, $course->id);
        $history3 = $ps->get_profile_history($user->id, $course->id);

        // PROPERTY: All retrievals must return identical data.
        $this->assertEquals(
            $history1,
            $history2,
            "Property 7 violated: History retrieval is not idempotent. " .
            "First and second retrieval returned different results."
        );

        $this->assertEquals(
            $history1,
            $history3,
            "Property 7 violated: History retrieval is not idempotent. " .
            "First and third retrieval returned different results."
        );

        // PROPERTY: Count must be the same.
        $this->assertCount(5, $history1);
        $this->assertCount(5, $history2);
        $this->assertCount(5, $history3);
    }

    /**
     * Property 7 Stress Test: Large number of updates.
     *
     * Verifies that the system can handle and correctly store a large number
     * of profile updates (50+) without data loss or ordering issues.
     *
     * @return void
     */
    public function test_property7_large_number_of_updates() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $num_updates = 50;

        // Perform many updates.
        for ($i = 0; $i < $num_updates; $i++) {
            // No sleep to test rapid updates.
            $ps->update_profile($user->id, $course->id, [
                'score' => rand(0, 100),
                'duration_seconds' => rand(60, 600),
            ]);
        }

        // Retrieve history.
        $history = $ps->get_profile_history($user->id, $course->id);

        // PROPERTY: All updates must be stored.
        $this->assertCount(
            $num_updates,
            $history,
            "Property 7 violated: Large number of updates not all stored. " .
            "Expected {$num_updates}, found " . count($history)
        );

        // PROPERTY: Versions must be sequential.
        $first_version = $history[0]['profile_version'];
        for ($i = 0; $i < $num_updates; $i++) {
            $expected_version = $first_version + $i;
            $actual_version = $history[$i]['profile_version'];

            $this->assertEquals(
                $expected_version,
                $actual_version,
                "Property 7 violated: Version sequence broken at index {$i}. " .
                "Expected version {$expected_version}, got {$actual_version}"
            );
        }

        // PROPERTY: Timestamps must be monotonic (non-decreasing).
        for ($i = 1; $i < $num_updates; $i++) {
            $this->assertGreaterThanOrEqual(
                $history[$i - 1]['_timecreated'],
                $history[$i]['_timecreated'],
                "Property 7 violated: Timestamp order broken at index {$i}"
            );
        }
    }

    // =========================================================================
    // PROPERTY 8: Konsistensi Klasifikasi Learning_Style
    // =========================================================================

    /**
     * Property 8: Konsistensi Klasifikasi Learning_Style
     *
     * Specification: For ANY interaction pattern (sequence of resource types),
     * the classification of Learning_Style MUST be deterministic and consistent:
     * identical interaction patterns MUST ALWAYS produce the same Learning_Style
     * classification, regardless of when or how many times the classification
     * is performed.
     *
     * This property verifies that:
     * 1. Classification is deterministic (same input → same output)
     * 2. Classification is consistent across multiple invocations
     * 3. Classification is independent of invocation order
     * 4. Classification is consistent regardless of array size
     * 5. Classification follows the defined resource-to-style mapping
     * 6. Empty patterns always return 'unknown'
     * 7. Unknown resource types always return 'unknown'
     * 8. Classification is based on dominant style (highest count)
     * 9. Ties are resolved consistently
     *
     * Validates Requirements: 3.4, 3.7, 13.1
     *
     * Test Strategy:
     * - Generate random interaction patterns (arrays of resource types)
     * - Classify the same pattern multiple times
     * - Verify all classifications produce identical results
     * - Test with various pattern sizes and compositions
     * - Test boundary cases (empty, single element, all same type)
     * - Test tie-breaking behavior
     * - Verify consistency with resource_style_map
     *
     * @return void
     */
    public function test_property8_learning_style_classification_consistency() {
        $this->forAll(
            Generator\choose(1, 50),  // Number of interactions in pattern
            Generator\elements([
                'video', 'audio',           // Auditory
                'document', 'pdf', 'book', 'page',  // Reading
                'image', 'diagram', 'presentation',  // Visual
                'quiz', 'assignment', 'workshop',    // Kinesthetic
            ])
        )->then(function($num_interactions, $base_resource_type) {
            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            // Build interaction pattern by repeating base type and adding some variety.
            $interactions = [];
            for ($i = 0; $i < $num_interactions; $i++) {
                // 70% base type, 30% random variation
                if (rand(1, 100) <= 70) {
                    $interactions[] = $base_resource_type;
                } else {
                    $all_types = ['video', 'audio', 'document', 'pdf', 'book', 'page',
                                  'image', 'diagram', 'presentation', 'quiz', 'assignment', 'workshop'];
                    $interactions[] = $all_types[array_rand($all_types)];
                }
            }

            // ================================================================
            // PROPERTY 8.1: Classification is deterministic
            // ================================================================
            $first_classification = $ps->classify_learning_style($interactions);

            // Classify the same pattern 10 times.
            for ($i = 0; $i < 10; $i++) {
                $classification = $ps->classify_learning_style($interactions);

                $this->assertEquals(
                    $first_classification,
                    $classification,
                    "Property 8 violated: Classification is not deterministic. " .
                    "Same interaction pattern produced different results. " .
                    "First classification: '{$first_classification}', " .
                    "Classification {$i}: '{$classification}'. " .
                    "Pattern: " . json_encode($interactions) . ". " .
                    "Identical interaction patterns must always produce the same Learning_Style classification."
                );
            }

            // ================================================================
            // PROPERTY 8.2: Classification is valid
            // ================================================================
            $valid_styles = ['visual', 'auditory', 'reading', 'kinesthetic', 'unknown'];
            $this->assertContains(
                $first_classification,
                $valid_styles,
                "Property 8 violated: Classification returned invalid learning style '{$first_classification}'. " .
                "Valid styles are: " . implode(', ', $valid_styles)
            );

            // ================================================================
            // PROPERTY 8.3: Classification is independent of array order (for same counts)
            // ================================================================
            // Shuffle the interactions array and verify result is the same.
            $shuffled = $interactions;
            shuffle($shuffled);

            $shuffled_classification = $ps->classify_learning_style($shuffled);

            $this->assertEquals(
                $first_classification,
                $shuffled_classification,
                "Property 8 violated: Classification depends on array order. " .
                "Original order: '{$first_classification}', " .
                "Shuffled order: '{$shuffled_classification}'. " .
                "Classification must be based on counts, not order."
            );

            // ================================================================
            // PROPERTY 8.4: Classification matches expected dominant style
            // ================================================================
            $expected_style = $this->calculate_expected_learning_style($interactions);

            $this->assertEquals(
                $expected_style,
                $first_classification,
                "Property 8 violated: Classification does not match expected dominant style. " .
                "Expected: '{$expected_style}', Got: '{$first_classification}'. " .
                "Pattern: " . json_encode($interactions)
            );
        });
    }

    /**
     * Property 8 Critical Test: Empty pattern always returns 'unknown'.
     *
     * Verifies that an empty interaction pattern consistently returns 'unknown'
     * learning style, as there is no data to classify.
     *
     * @return void
     */
    public function test_property8_empty_pattern_returns_unknown() {
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        // Classify empty pattern multiple times.
        for ($i = 0; $i < 100; $i++) {
            $classification = $ps->classify_learning_style([]);

            $this->assertEquals(
                'unknown',
                $classification,
                "Property 8 violated: Empty interaction pattern must always return 'unknown', " .
                "but got '{$classification}' on iteration {$i}. " .
                "Identical (empty) patterns must produce identical results."
            );
        }
    }

    /**
     * Property 8 Critical Test: Unknown resource types return 'unknown'.
     *
     * Verifies that patterns containing only unrecognized resource types
     * consistently return 'unknown' learning style.
     *
     * @return void
     */
    public function test_property8_unknown_types_return_unknown() {
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $unknown_patterns = [
            ['unknown_type'],
            ['invalid', 'unrecognized'],
            ['foo', 'bar', 'baz'],
            ['', '', ''],
            ['123', '456'],
        ];

        foreach ($unknown_patterns as $pattern) {
            // Classify same pattern multiple times.
            $first_classification = $ps->classify_learning_style($pattern);

            $this->assertEquals(
                'unknown',
                $first_classification,
                "Property 8 violated: Pattern with unknown types must return 'unknown', " .
                "but got '{$first_classification}'. Pattern: " . json_encode($pattern)
            );

            // Verify consistency across multiple calls.
            for ($i = 0; $i < 10; $i++) {
                $classification = $ps->classify_learning_style($pattern);

                $this->assertEquals(
                    $first_classification,
                    $classification,
                    "Property 8 violated: Inconsistent classification for unknown types. " .
                    "Pattern: " . json_encode($pattern) . ", iteration {$i}"
                );
            }
        }
    }

    /**
     * Property 8 Critical Test: Single resource type patterns.
     *
     * Verifies that patterns containing only one type of resource
     * consistently classify to the correct learning style.
     *
     * @return void
     */
    public function test_property8_single_type_patterns_consistent() {
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $single_type_cases = [
            // [resource_type, expected_style]
            ['video', 'auditory'],
            ['audio', 'auditory'],
            ['document', 'reading'],
            ['pdf', 'reading'],
            ['book', 'reading'],
            ['page', 'reading'],
            ['image', 'visual'],
            ['diagram', 'visual'],
            ['presentation', 'visual'],
            ['quiz', 'kinesthetic'],
            ['assignment', 'kinesthetic'],
            ['workshop', 'kinesthetic'],
        ];

        foreach ($single_type_cases as [$resource_type, $expected_style]) {
            // Test with different pattern sizes (1, 5, 10, 50 repetitions).
            foreach ([1, 5, 10, 50] as $count) {
                $pattern = array_fill(0, $count, $resource_type);

                // Classify multiple times.
                $first_classification = $ps->classify_learning_style($pattern);

                $this->assertEquals(
                    $expected_style,
                    $first_classification,
                    "Property 8 violated: Pattern of {$count} '{$resource_type}' resources " .
                    "should classify as '{$expected_style}', but got '{$first_classification}'"
                );

                // Verify consistency across 10 invocations.
                for ($i = 0; $i < 10; $i++) {
                    $classification = $ps->classify_learning_style($pattern);

                    $this->assertEquals(
                        $first_classification,
                        $classification,
                        "Property 8 violated: Inconsistent classification for single-type pattern. " .
                        "Resource: '{$resource_type}', Count: {$count}, Iteration: {$i}"
                    );
                }
            }
        }
    }

    /**
     * Property 8 Edge Case: Patterns with clear dominant style.
     *
     * Verifies that when one style clearly dominates (>50% of interactions),
     * classification is consistent and correct.
     *
     * @return void
     */
    public function test_property8_dominant_style_consistency() {
        $this->forAll(
            Generator\choose(10, 100)  // Total number of interactions
        )->then(function($total_interactions) {
            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            // Create pattern with clear dominant style (70% video, 30% mixed).
            $pattern = [];
            $dominant_count = (int)($total_interactions * 0.7);
            $remaining = $total_interactions - $dominant_count;

            // Add dominant type (video → auditory).
            for ($i = 0; $i < $dominant_count; $i++) {
                $pattern[] = 'video';
            }

            // Add mixed other types.
            $other_types = ['document', 'image', 'quiz'];
            for ($i = 0; $i < $remaining; $i++) {
                $pattern[] = $other_types[$i % count($other_types)];
            }

            // Shuffle to ensure order independence.
            shuffle($pattern);

            // PROPERTY: Classification must be 'auditory' (dominant style).
            $first_classification = $ps->classify_learning_style($pattern);

            $this->assertEquals(
                'auditory',
                $first_classification,
                "Property 8 violated: Pattern with 70% video should classify as 'auditory', " .
                "but got '{$first_classification}'. Total interactions: {$total_interactions}"
            );

            // PROPERTY: Classification must be consistent across multiple calls.
            for ($i = 0; $i < 10; $i++) {
                $classification = $ps->classify_learning_style($pattern);

                $this->assertEquals(
                    $first_classification,
                    $classification,
                    "Property 8 violated: Inconsistent classification for dominant pattern. " .
                    "Iteration {$i}, Total: {$total_interactions}"
                );
            }
        });
    }

    /**
     * Property 8 Edge Case: Tie-breaking consistency.
     *
     * Verifies that when multiple styles have the same count (tie),
     * the classification is consistent across multiple invocations.
     *
     * @return void
     */
    public function test_property8_tie_breaking_consistency() {
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        // Create patterns with ties.
        $tie_patterns = [
            // Equal counts: 2 visual, 2 auditory, 2 reading, 2 kinesthetic.
            ['image', 'diagram', 'video', 'audio', 'document', 'pdf', 'quiz', 'assignment'],

            // Two-way tie: 3 visual, 3 auditory.
            ['image', 'diagram', 'presentation', 'video', 'audio', 'video'],

            // Three-way tie: 2 visual, 2 auditory, 2 reading.
            ['image', 'diagram', 'video', 'audio', 'document', 'pdf'],
        ];

        foreach ($tie_patterns as $index => $pattern) {
            // Classify the pattern once to establish expected result.
            $first_classification = $ps->classify_learning_style($pattern);

            // PROPERTY: Classification must be valid.
            $valid_styles = ['visual', 'auditory', 'reading', 'kinesthetic'];
            $this->assertContains(
                $first_classification,
                $valid_styles,
                "Property 8 violated: Tie pattern {$index} produced invalid style '{$first_classification}'"
            );

            // PROPERTY: Classification must be consistent across 50 invocations.
            for ($i = 0; $i < 50; $i++) {
                $classification = $ps->classify_learning_style($pattern);

                $this->assertEquals(
                    $first_classification,
                    $classification,
                    "Property 8 violated: Tie-breaking is not consistent. " .
                    "Pattern {$index}, Iteration {$i}. " .
                    "First: '{$first_classification}', Current: '{$classification}'. " .
                    "Pattern: " . json_encode($pattern) . ". " .
                    "Even with ties, identical patterns must produce identical results."
                );
            }

            // PROPERTY: Shuffling the pattern should not change the result (order independence).
            $shuffled = $pattern;
            shuffle($shuffled);

            $shuffled_classification = $ps->classify_learning_style($shuffled);

            $this->assertEquals(
                $first_classification,
                $shuffled_classification,
                "Property 8 violated: Tie-breaking depends on array order. " .
                "Pattern {$index}. Original: '{$first_classification}', " .
                "Shuffled: '{$shuffled_classification}'"
            );
        }
    }

    /**
     * Property 8 Invariant: Case insensitivity and whitespace handling.
     *
     * Verifies that classification is case-insensitive and handles
     * whitespace correctly, producing consistent results.
     *
     * @return void
     */
    public function test_property8_case_and_whitespace_consistency() {
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $case_variants = [
            // [pattern1, pattern2, description]
            [
                ['video', 'video', 'video'],
                ['VIDEO', 'Video', 'ViDeO'],
                'Case variations of video',
            ],
            [
                ['document', 'pdf', 'book'],
                ['DOCUMENT', 'PDF', 'BOOK'],
                'Case variations of reading types',
            ],
            [
                ['quiz', 'assignment'],
                ['QUIZ', 'ASSIGNMENT'],
                'Case variations of kinesthetic types',
            ],
            [
                ['image', 'diagram'],
                [' image ', ' diagram '],
                'Whitespace variations',
            ],
            [
                ['video', 'audio'],
                ['  VIDEO  ', '  AUDIO  '],
                'Case and whitespace combined',
            ],
        ];

        foreach ($case_variants as [$pattern1, $pattern2, $description]) {
            $classification1 = $ps->classify_learning_style($pattern1);
            $classification2 = $ps->classify_learning_style($pattern2);

            $this->assertEquals(
                $classification1,
                $classification2,
                "Property 8 violated: {$description}. " .
                "Pattern1: " . json_encode($pattern1) . " → '{$classification1}', " .
                "Pattern2: " . json_encode($pattern2) . " → '{$classification2}'. " .
                "Classification must be case-insensitive and handle whitespace consistently."
            );

            // Verify consistency across multiple calls for both patterns.
            for ($i = 0; $i < 5; $i++) {
                $this->assertEquals(
                    $classification1,
                    $ps->classify_learning_style($pattern1),
                    "Property 8 violated: Inconsistent classification for pattern1, iteration {$i}"
                );

                $this->assertEquals(
                    $classification2,
                    $ps->classify_learning_style($pattern2),
                    "Property 8 violated: Inconsistent classification for pattern2, iteration {$i}"
                );
            }
        }
    }

    /**
     * Property 8 Integration Test: Classification in profile update context.
     *
     * Verifies that when classify_learning_style is used within the
     * profile update workflow, it produces consistent results that are
     * persisted correctly to the database.
     *
     * @return void
     */
    public function test_property8_classification_in_profile_update_context() {
        $this->forAll(
            Generator\choose(5, 30),  // Number of interactions
            Generator\elements(['video', 'document', 'image', 'quiz'])
        )->then(function($num_interactions, $dominant_type) {
            global $DB;

            $user = $this->getDataGenerator()->create_user();
            $course = $this->getDataGenerator()->create_course();
            $ps = new \block_attendanceleaderboard\profiling\profiling_system();

            // Build interaction pattern with dominant type.
            $interactions = array_fill(0, $num_interactions, $dominant_type);

            // Update profile with interactions.
            $profile1 = $ps->update_profile($user->id, $course->id, [
                'interactions' => $interactions,
            ]);

            $learning_style1 = $profile1->learning_style;

            // PROPERTY: Learning style must be valid.
            $valid_styles = ['visual', 'auditory', 'reading', 'kinesthetic', 'unknown'];
            $this->assertContains(
                $learning_style1,
                $valid_styles,
                "Property 8 violated: Profile update produced invalid learning style '{$learning_style1}'"
            );

            // Update profile again with the SAME interactions.
            sleep(1);
            $profile2 = $ps->update_profile($user->id, $course->id, [
                'interactions' => $interactions,
            ]);

            $learning_style2 = $profile2->learning_style;

            // PROPERTY: Identical interactions must produce identical learning style.
            $this->assertEquals(
                $learning_style1,
                $learning_style2,
                "Property 8 violated: Identical interaction patterns produced different learning styles. " .
                "First update: '{$learning_style1}', Second update: '{$learning_style2}'. " .
                "Pattern: " . json_encode($interactions)
            );

            // PROPERTY: Database record must match profile.
            $db_record = $DB->get_record('acmls_learner_profile', [
                'userid' => $user->id,
                'courseid' => $course->id,
            ]);

            $this->assertEquals(
                $learning_style2,
                $db_record->learning_style,
                "Property 8 violated: Database learning_style does not match profile"
            );

            // PROPERTY: Direct classification call must match profile result.
            $direct_classification = $ps->classify_learning_style($interactions);

            $this->assertEquals(
                $learning_style2,
                $direct_classification,
                "Property 8 violated: Direct classification does not match profile update result. " .
                "Profile: '{$learning_style2}', Direct: '{$direct_classification}'"
            );
        });
    }

    /**
     * Property 8 Stress Test: Classification consistency under high volume.
     *
     * Verifies that classification remains consistent even when called
     * many times in rapid succession (stress test).
     *
     * @return void
     */
    public function test_property8_classification_consistency_under_stress() {
        $ps = new \block_attendanceleaderboard\profiling\profiling_system();

        $test_patterns = [
            ['video', 'video', 'video'],
            ['document', 'pdf', 'book', 'page'],
            ['image', 'diagram', 'presentation'],
            ['quiz', 'assignment', 'workshop'],
            ['video', 'document', 'image', 'quiz'],  // Mixed
        ];

        foreach ($test_patterns as $pattern) {
            $expected_style = $ps->classify_learning_style($pattern);

            // Call classification 1000 times for the same pattern.
            for ($i = 0; $i < 1000; $i++) {
                $classification = $ps->classify_learning_style($pattern);

                $this->assertEquals(
                    $expected_style,
                    $classification,
                    "Property 8 violated: Classification inconsistent under stress. " .
                    "Pattern: " . json_encode($pattern) . ", " .
                    "Expected: '{$expected_style}', Got: '{$classification}', " .
                    "Iteration: {$i}. " .
                    "Classification must be deterministic and consistent."
                );
            }
        }
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Calculate expected learning style based on interaction pattern.
     *
     * Mimics the logic in profiling_system::classify_learning_style()
     * to provide expected results for property testing.
     *
     * @param array $interactions Array of resource types.
     * @return string Expected learning style.
     */
    private function calculate_expected_learning_style(array $interactions): string {
        if (empty($interactions)) {
            return 'unknown';
        }

        // Resource type to learning style mapping (matches profiling_system).
        $resource_style_map = [
            'video'        => 'auditory',
            'audio'        => 'auditory',
            'document'     => 'reading',
            'pdf'          => 'reading',
            'book'         => 'reading',
            'page'         => 'reading',
            'image'        => 'visual',
            'diagram'      => 'visual',
            'presentation' => 'visual',
            'quiz'         => 'kinesthetic',
            'assignment'   => 'kinesthetic',
            'workshop'     => 'kinesthetic',
        ];

        $counts = [
            'visual'      => 0,
            'auditory'    => 0,
            'reading'     => 0,
            'kinesthetic' => 0,
        ];

        foreach ($interactions as $resource_type) {
            $type = strtolower(trim((string) $resource_type));
            if (isset($resource_style_map[$type])) {
                $style = $resource_style_map[$type];
                $counts[$style]++;
            }
        }

        $total = array_sum($counts);
        if ($total === 0) {
            return 'unknown';
        }

        // Return the style with the highest count.
        return (string) array_search(max($counts), $counts);
    }

    /**
     * Calculate expected performance category based on score.
     *
     * Rules:
     * - score < 60 → 1 (Low)
     * - 60 ≤ score < 80 → 2 (Middle)
     * - score ≥ 80 → 3 (High)
     *
     * @param float $score Performance score (0-100).
     * @return int Performance category (1, 2, or 3).
     */
    private function calculate_expected_performance_category(float $score): int {
        if ($score < 60.0) {
            return 1;  // Low
        } elseif ($score < 80.0) {
            return 2;  // Middle
        } else {
            return 3;  // High
        }
    }
}
