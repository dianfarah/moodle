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
 * Property-based test for Property 13: Kesesuaian Kategori Encouragement_Content.
 *
 * Verifies that MotivationComponent::get_intervention_category() always returns
 * a category that matches the intervention mapping table for any Learner_Profile
 * (any combination of Performance_Category and Motivation_Level).
 *
 * **Validates: Requirements 7.1**
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
 * Property 13: Kesesuaian Kategori Encouragement_Content dengan Profil Learner.
 *
 * For any Learner_Profile (any combination of Performance_Category ∈ {1,2,3}
 * and Motivation_Level ∈ [0.0, 100.0]), the category returned by
 * MotivationComponent::get_intervention_category() must:
 *
 *   1. Always be one of the four valid categories:
 *      'reinforcement', 'achievement', 'recovery', 'persistence'
 *
 *   2. Match the expected category from the intervention mapping table:
 *      | Performance_Category | Motivation_Level  | Category      |
 *      |----------------------|-------------------|---------------|
 *      | Low  (1)             | Low  (<40)        | recovery      |
 *      | Low  (1)             | Middle (40-69)    | persistence   |
 *      | Middle (2)           | Low  (<40)        | recovery      |
 *      | Middle (2)           | Middle (40-69)    | reinforcement |
 *      | High (3)             | Any               | achievement   |
 *      | Any                  | High (>=70)       | achievement   |
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class encouragement_category_property_test extends \advanced_testcase {
    use TestTrait;

    // =========================================================================
    // Valid categories
    // =========================================================================

    /** @var string[] All valid Encouragement_Content categories. */
    private const VALID_CATEGORIES = [
        'reinforcement',
        'achievement',
        'recovery',
        'persistence',
    ];

    // =========================================================================
    // Motivation level boundaries (mirrors MotivationComponent constants)
    // =========================================================================

    /** @var float Motivation level boundary: Low/Middle threshold. */
    private const MOTIVATION_LOW_BOUNDARY = 40.0;

    /** @var float Motivation level boundary: Middle/High threshold. */
    private const MOTIVATION_HIGH_BOUNDARY = 70.0;

    // =========================================================================
    // Performance category constants (mirrors LearnerProfile constants)
    // =========================================================================

    /** @var int Performance category: Low. */
    private const PERFORMANCE_LOW = 1;

    /** @var int Performance category: Middle. */
    private const PERFORMANCE_MIDDLE = 2;

    /** @var int Performance category: High. */
    private const PERFORMANCE_HIGH = 3;

    // =========================================================================
    // PHPUnit / Eris compatibility
    // =========================================================================

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
    }

    // =========================================================================
    // Helper: compute expected category from the mapping table
    // =========================================================================

    /**
     * Compute the expected intervention category from the mapping table.
     *
     * This is an independent reference implementation of the mapping table
     * defined in design.md / MotivationComponent, used to cross-check the
     * actual implementation.
     *
     * Mapping table:
     * | Performance_Category | Motivation_Level  | Category      |
     * |----------------------|-------------------|---------------|
     * | Low  (1)             | Low  (<40)        | recovery      |
     * | Low  (1)             | Middle (40-69)    | persistence   |
     * | Middle (2)           | Low  (<40)        | recovery      |
     * | Middle (2)           | Middle (40-69)    | reinforcement |
     * | High (3)             | Any               | achievement   |
     * | Any                  | High (>=70)       | achievement   |
     *
     * @param  int   $performance_category 1=Low, 2=Middle, 3=High.
     * @param  float $motivation_level     0.0–100.0.
     * @return string                      Expected category.
     */
    private function compute_expected_category(int $performance_category, float $motivation_level): string {
        // High motivation (>=70) → achievement regardless of performance.
        if ($motivation_level >= self::MOTIVATION_HIGH_BOUNDARY) {
            return 'achievement';
        }

        // High performance → achievement regardless of motivation.
        if ($performance_category === self::PERFORMANCE_HIGH) {
            return 'achievement';
        }

        $is_low_motivation = ($motivation_level < self::MOTIVATION_LOW_BOUNDARY);

        if ($performance_category === self::PERFORMANCE_LOW) {
            return $is_low_motivation ? 'recovery' : 'persistence';
        }

        if ($performance_category === self::PERFORMANCE_MIDDLE) {
            return $is_low_motivation ? 'recovery' : 'reinforcement';
        }

        // Default fallback (should not be reached for valid inputs).
        return 'reinforcement';
    }

    // =========================================================================
    // PROPERTY 13 (Core): Category is always one of the four valid categories
    // =========================================================================

    /**
     * Property 13 (Valid Category): For any Performance_Category ∈ {1,2,3} and
     * any Motivation_Level ∈ [0.0, 100.0], the category returned by
     * get_intervention_category() must always be one of the four valid categories:
     * 'reinforcement', 'achievement', 'recovery', 'persistence'.
     *
     * **Validates: Requirements 7.1**
     *
     * Test Strategy:
     * - Generate random Performance_Category from {1, 2, 3}
     * - Generate random Motivation_Level in [0, 10000] (divide by 100 for [0.0, 100.0])
     * - Call MotivationComponent::get_intervention_category()
     * - Assert the result is one of the four valid categories
     *
     * @return void
     */
    public function test_property13_category_is_always_valid(): void {
        $this->forAll(
            Generator\elements(
                self::PERFORMANCE_LOW,
                self::PERFORMANCE_MIDDLE,
                self::PERFORMANCE_HIGH
            ),
            Generator\choose(0, 10000)  // raw integer → motivation_level in [0.0, 100.0]
        )->then(function (int $performance_category, int $raw_motivation) {
            $motivation_level = $raw_motivation / 100.0;

            $category = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                $performance_category,
                $motivation_level
            );

            $this->assertContains(
                $category,
                self::VALID_CATEGORIES,
                "Property 13 violated: get_intervention_category(performance_category={$performance_category}, " .
                "motivation_level={$motivation_level}) returned '{$category}', " .
                "which is not one of the valid categories: [" .
                implode(', ', self::VALID_CATEGORIES) . "]. " .
                "Generated content category must always be a valid Encouragement_Content category (Req 7.1)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 13 (Mapping): Category matches the intervention mapping table
    // =========================================================================

    /**
     * Property 13 (Mapping Correctness): For any Performance_Category ∈ {1,2,3}
     * and any Motivation_Level ∈ [0.0, 100.0], the category returned by
     * get_intervention_category() must match the expected category from the
     * intervention mapping table defined in design.md.
     *
     * **Validates: Requirements 7.1**
     *
     * Test Strategy:
     * - Generate random Performance_Category from {1, 2, 3}
     * - Generate random Motivation_Level in [0, 10000] (divide by 100 for [0.0, 100.0])
     * - Compute expected category using the reference mapping table
     * - Call MotivationComponent::get_intervention_category()
     * - Assert the result matches the expected category
     *
     * @return void
     */
    public function test_property13_category_matches_intervention_mapping_table(): void {
        $this->forAll(
            Generator\elements(
                self::PERFORMANCE_LOW,
                self::PERFORMANCE_MIDDLE,
                self::PERFORMANCE_HIGH
            ),
            Generator\choose(0, 10000)  // raw integer → motivation_level in [0.0, 100.0]
        )->then(function (int $performance_category, int $raw_motivation) {
            $motivation_level = $raw_motivation / 100.0;

            $expected_category = $this->compute_expected_category($performance_category, $motivation_level);

            $actual_category = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                $performance_category,
                $motivation_level
            );

            $this->assertEquals(
                $expected_category,
                $actual_category,
                "Property 13 violated: For performance_category={$performance_category} " .
                "and motivation_level={$motivation_level}, " .
                "expected category='{$expected_category}' but got '{$actual_category}'. " .
                "The generated content category must match the intervention mapping table " .
                "for any Learner_Profile (Req 7.1)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 13 (High Motivation): Any profile with high motivation → achievement
    // =========================================================================

    /**
     * Property 13 (High Motivation Rule): For any Performance_Category and any
     * Motivation_Level >= 70, the category must always be 'achievement'.
     *
     * This verifies the "Any + High (>=70) → achievement" row of the mapping table.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property13_high_motivation_always_yields_achievement(): void {
        $this->forAll(
            Generator\elements(
                self::PERFORMANCE_LOW,
                self::PERFORMANCE_MIDDLE,
                self::PERFORMANCE_HIGH
            ),
            // Motivation_Level in [70.0, 100.0]: raw integer in [7000, 10000]
            Generator\choose(7000, 10000)
        )->then(function (int $performance_category, int $raw_motivation) {
            $motivation_level = $raw_motivation / 100.0;

            $category = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                $performance_category,
                $motivation_level
            );

            $this->assertEquals(
                'achievement',
                $category,
                "Property 13 violated: For any performance_category={$performance_category} " .
                "with high motivation_level={$motivation_level} (>=70), " .
                "category must be 'achievement' but got '{$category}'. " .
                "High motivation always maps to achievement regardless of performance (Req 7.1)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 13 (High Performance): High performance → achievement for any motivation
    // =========================================================================

    /**
     * Property 13 (High Performance Rule): For Performance_Category=High (3) and
     * any Motivation_Level ∈ [0.0, 100.0], the category must always be 'achievement'.
     *
     * This verifies the "High + Any → achievement" row of the mapping table.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property13_high_performance_always_yields_achievement(): void {
        $this->forAll(
            Generator\choose(0, 10000)  // raw integer → motivation_level in [0.0, 100.0]
        )->then(function (int $raw_motivation) {
            $motivation_level = $raw_motivation / 100.0;

            $category = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                self::PERFORMANCE_HIGH,
                $motivation_level
            );

            $this->assertEquals(
                'achievement',
                $category,
                "Property 13 violated: For performance_category=High (3) " .
                "with any motivation_level={$motivation_level}, " .
                "category must be 'achievement' but got '{$category}'. " .
                "High performance always maps to achievement regardless of motivation (Req 7.1)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 13 (Low Performance + Low Motivation): → recovery
    // =========================================================================

    /**
     * Property 13 (Low+Low Rule): For Performance_Category=Low (1) and
     * Motivation_Level < 40, the category must always be 'recovery'.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property13_low_performance_low_motivation_yields_recovery(): void {
        $this->forAll(
            // Motivation_Level in [0.0, 39.99]: raw integer in [0, 3999]
            Generator\choose(0, 3999)
        )->then(function (int $raw_motivation) {
            $motivation_level = $raw_motivation / 100.0;

            $category = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                self::PERFORMANCE_LOW,
                $motivation_level
            );

            $this->assertEquals(
                'recovery',
                $category,
                "Property 13 violated: For performance_category=Low (1) " .
                "with low motivation_level={$motivation_level} (<40), " .
                "category must be 'recovery' but got '{$category}'. " .
                "Low performance + Low motivation → recovery (Req 7.1)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 13 (Low Performance + Middle Motivation): → persistence
    // =========================================================================

    /**
     * Property 13 (Low+Middle Rule): For Performance_Category=Low (1) and
     * Motivation_Level in [40.0, 69.99], the category must always be 'persistence'.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property13_low_performance_middle_motivation_yields_persistence(): void {
        $this->forAll(
            // Motivation_Level in [40.0, 69.99]: raw integer in [4000, 6999]
            Generator\choose(4000, 6999)
        )->then(function (int $raw_motivation) {
            $motivation_level = $raw_motivation / 100.0;

            $category = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                self::PERFORMANCE_LOW,
                $motivation_level
            );

            $this->assertEquals(
                'persistence',
                $category,
                "Property 13 violated: For performance_category=Low (1) " .
                "with middle motivation_level={$motivation_level} (40-69.99), " .
                "category must be 'persistence' but got '{$category}'. " .
                "Low performance + Middle motivation → persistence (Req 7.1)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 13 (Middle Performance + Low Motivation): → recovery
    // =========================================================================

    /**
     * Property 13 (Middle+Low Rule): For Performance_Category=Middle (2) and
     * Motivation_Level < 40, the category must always be 'recovery'.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property13_middle_performance_low_motivation_yields_recovery(): void {
        $this->forAll(
            // Motivation_Level in [0.0, 39.99]: raw integer in [0, 3999]
            Generator\choose(0, 3999)
        )->then(function (int $raw_motivation) {
            $motivation_level = $raw_motivation / 100.0;

            $category = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                self::PERFORMANCE_MIDDLE,
                $motivation_level
            );

            $this->assertEquals(
                'recovery',
                $category,
                "Property 13 violated: For performance_category=Middle (2) " .
                "with low motivation_level={$motivation_level} (<40), " .
                "category must be 'recovery' but got '{$category}'. " .
                "Middle performance + Low motivation → recovery (Req 7.1)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 13 (Middle Performance + Middle Motivation): → reinforcement
    // =========================================================================

    /**
     * Property 13 (Middle+Middle Rule): For Performance_Category=Middle (2) and
     * Motivation_Level in [40.0, 69.99], the category must always be 'reinforcement'.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property13_middle_performance_middle_motivation_yields_reinforcement(): void {
        $this->forAll(
            // Motivation_Level in [40.0, 69.99]: raw integer in [4000, 6999]
            Generator\choose(4000, 6999)
        )->then(function (int $raw_motivation) {
            $motivation_level = $raw_motivation / 100.0;

            $category = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                self::PERFORMANCE_MIDDLE,
                $motivation_level
            );

            $this->assertEquals(
                'reinforcement',
                $category,
                "Property 13 violated: For performance_category=Middle (2) " .
                "with middle motivation_level={$motivation_level} (40-69.99), " .
                "category must be 'reinforcement' but got '{$category}'. " .
                "Middle performance + Middle motivation → reinforcement (Req 7.1)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 13 (Boundary): Exact boundary values of motivation level
    // =========================================================================

    /**
     * Property 13 (Boundary Values): Verify the exact boundary values of
     * Motivation_Level (0.0, 39.99, 40.0, 69.99, 70.0, 100.0) produce the
     * correct categories for each Performance_Category.
     *
     * This is a deterministic test that covers all 18 boundary combinations
     * (6 boundary values × 3 performance categories).
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property13_boundary_motivation_values_produce_correct_categories(): void {
        // [performance_category, motivation_level, expected_category]
        $boundary_cases = [
            // Low performance (1)
            [self::PERFORMANCE_LOW,    0.0,   'recovery'],     // Min motivation
            [self::PERFORMANCE_LOW,    39.99, 'recovery'],     // Just below Low/Middle boundary
            [self::PERFORMANCE_LOW,    40.0,  'persistence'],  // Exactly at Low/Middle boundary
            [self::PERFORMANCE_LOW,    69.99, 'persistence'],  // Just below Middle/High boundary
            [self::PERFORMANCE_LOW,    70.0,  'achievement'],  // Exactly at Middle/High boundary
            [self::PERFORMANCE_LOW,    100.0, 'achievement'],  // Max motivation

            // Middle performance (2)
            [self::PERFORMANCE_MIDDLE, 0.0,   'recovery'],     // Min motivation
            [self::PERFORMANCE_MIDDLE, 39.99, 'recovery'],     // Just below Low/Middle boundary
            [self::PERFORMANCE_MIDDLE, 40.0,  'reinforcement'],// Exactly at Low/Middle boundary
            [self::PERFORMANCE_MIDDLE, 69.99, 'reinforcement'],// Just below Middle/High boundary
            [self::PERFORMANCE_MIDDLE, 70.0,  'achievement'],  // Exactly at Middle/High boundary
            [self::PERFORMANCE_MIDDLE, 100.0, 'achievement'],  // Max motivation

            // High performance (3) — always achievement
            [self::PERFORMANCE_HIGH,   0.0,   'achievement'],  // Min motivation
            [self::PERFORMANCE_HIGH,   39.99, 'achievement'],  // Low motivation
            [self::PERFORMANCE_HIGH,   40.0,  'achievement'],  // Middle motivation start
            [self::PERFORMANCE_HIGH,   69.99, 'achievement'],  // Middle motivation end
            [self::PERFORMANCE_HIGH,   70.0,  'achievement'],  // High motivation start
            [self::PERFORMANCE_HIGH,   100.0, 'achievement'],  // Max motivation
        ];

        foreach ($boundary_cases as [$perf_cat, $motivation, $expected]) {
            $actual = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                $perf_cat,
                $motivation
            );

            $this->assertEquals(
                $expected,
                $actual,
                "Property 13 violated (boundary): " .
                "get_intervention_category(performance_category={$perf_cat}, " .
                "motivation_level={$motivation}) " .
                "expected='{$expected}' but got='{$actual}'. " .
                "Boundary values must produce correct categories per mapping table (Req 7.1)."
            );
        }
    }

    // =========================================================================
    // PROPERTY 13 (Determinism): Same inputs always produce the same category
    // =========================================================================

    /**
     * Property 13 (Determinism): For any fixed Learner_Profile, calling
     * get_intervention_category() multiple times must always return the same
     * category — the mapping is deterministic.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property13_mapping_is_deterministic(): void {
        $this->forAll(
            Generator\elements(
                self::PERFORMANCE_LOW,
                self::PERFORMANCE_MIDDLE,
                self::PERFORMANCE_HIGH
            ),
            Generator\choose(0, 10000)  // raw integer → motivation_level in [0.0, 100.0]
        )->then(function (int $performance_category, int $raw_motivation) {
            $motivation_level = $raw_motivation / 100.0;

            // Call the mapping function three times with the same inputs.
            $category_1 = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                $performance_category,
                $motivation_level
            );
            $category_2 = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                $performance_category,
                $motivation_level
            );
            $category_3 = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                $performance_category,
                $motivation_level
            );

            $this->assertEquals(
                $category_1,
                $category_2,
                "Property 13 violated (determinism): " .
                "get_intervention_category(performance_category={$performance_category}, " .
                "motivation_level={$motivation_level}) returned different results " .
                "on consecutive calls: '{$category_1}' vs '{$category_2}'. " .
                "The mapping must be deterministic (Req 7.1)."
            );

            $this->assertEquals(
                $category_1,
                $category_3,
                "Property 13 violated (determinism): " .
                "get_intervention_category(performance_category={$performance_category}, " .
                "motivation_level={$motivation_level}) returned different results " .
                "on consecutive calls: '{$category_1}' vs '{$category_3}'. " .
                "The mapping must be deterministic (Req 7.1)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 13 (Exhaustive): All 9 cells of the mapping table are covered
    // =========================================================================

    /**
     * Property 13 (Exhaustive Coverage): Verify all 9 cells of the
     * Performance_Category × Motivation_Level mapping table produce the
     * correct category. This is a deterministic test that exhaustively
     * covers the entire mapping table.
     *
     * Mapping table (9 cells):
     * | Performance | Motivation | Expected      |
     * |-------------|------------|---------------|
     * | Low  (1)    | Low  (<40) | recovery      |
     * | Low  (1)    | Mid (40-69)| persistence   |
     * | Low  (1)    | High (>=70)| achievement   |
     * | Mid  (2)    | Low  (<40) | recovery      |
     * | Mid  (2)    | Mid (40-69)| reinforcement |
     * | Mid  (2)    | High (>=70)| achievement   |
     * | High (3)    | Low  (<40) | achievement   |
     * | High (3)    | Mid (40-69)| achievement   |
     * | High (3)    | High (>=70)| achievement   |
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property13_all_mapping_table_cells_are_correct(): void {
        // Representative values for each motivation zone.
        $low_motivation_samples    = [0.0, 10.0, 20.0, 30.0, 39.0];
        $middle_motivation_samples = [40.0, 50.0, 60.0, 69.0];
        $high_motivation_samples   = [70.0, 80.0, 90.0, 100.0];

        $test_cases = [];

        // Low performance (1).
        foreach ($low_motivation_samples as $m) {
            $test_cases[] = [self::PERFORMANCE_LOW, $m, 'recovery'];
        }
        foreach ($middle_motivation_samples as $m) {
            $test_cases[] = [self::PERFORMANCE_LOW, $m, 'persistence'];
        }
        foreach ($high_motivation_samples as $m) {
            $test_cases[] = [self::PERFORMANCE_LOW, $m, 'achievement'];
        }

        // Middle performance (2).
        foreach ($low_motivation_samples as $m) {
            $test_cases[] = [self::PERFORMANCE_MIDDLE, $m, 'recovery'];
        }
        foreach ($middle_motivation_samples as $m) {
            $test_cases[] = [self::PERFORMANCE_MIDDLE, $m, 'reinforcement'];
        }
        foreach ($high_motivation_samples as $m) {
            $test_cases[] = [self::PERFORMANCE_MIDDLE, $m, 'achievement'];
        }

        // High performance (3) — always achievement.
        foreach (array_merge($low_motivation_samples, $middle_motivation_samples, $high_motivation_samples) as $m) {
            $test_cases[] = [self::PERFORMANCE_HIGH, $m, 'achievement'];
        }

        foreach ($test_cases as [$perf_cat, $motivation, $expected]) {
            $actual = \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category(
                $perf_cat,
                $motivation
            );

            $this->assertEquals(
                $expected,
                $actual,
                "Property 13 violated (exhaustive): " .
                "get_intervention_category(performance_category={$perf_cat}, " .
                "motivation_level={$motivation}) " .
                "expected='{$expected}' but got='{$actual}'. " .
                "All cells of the intervention mapping table must be correct (Req 7.1)."
            );
        }
    }
}
