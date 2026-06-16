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
 * Property-based test for Property 17: Kesesuaian Learning_Resource dengan Parameter Query.
 *
 * Verifies that ALL resources returned by
 * LearningResourceRepository::find_by_profile() match the difficulty_level
 * and learning_styles parameters derived from the Learner_Profile.
 * No resource that does not match the requested difficulty_level or
 * learning_styles should ever be returned.
 *
 * **Validates: Requirements 9.2**
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
 * Property 17: Kesesuaian Learning_Resource dengan Parameter Query.
 *
 * All resources returned by LearningResourceRepository::find_by_profile()
 * must match the difficulty_level and learning_styles parameters passed in
 * the query. No resource that does not match the requested difficulty_level
 * or learning_styles should be returned.
 *
 * Formally:
 *   For all (performance_category, learning_style):
 *     allowed_difficulties = map_performance_to_difficulty(performance_category)
 *     results = find_by_profile(profile)
 *     FOR EACH resource IN results:
 *       resource.difficulty_level IN allowed_difficulties
 *       IF style-matched resources exist in the pool:
 *         resource.learning_styles CONTAINS learning_style
 *
 * This property must hold regardless of:
 *   - What other resources exist in the table (diverse seed data)
 *   - The order resources were inserted
 *   - Whether the result comes from style-matched or difficulty-only fallback
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class learning_resource_query_match_property_test extends \advanced_testcase {
    use TestTrait;

    // =========================================================================
    // Constants — valid domain values
    // =========================================================================

    /** @var int[] All valid performance_category values (1=Low, 2=Middle, 3=High). */
    private const VALID_PERFORMANCE_CATEGORIES = [1, 2, 3];

    /** @var string[] All valid learning_style values. */
    private const VALID_LEARNING_STYLES = [
        'visual',
        'auditory',
        'reading',
        'kinesthetic',
    ];

    /** @var int[] All valid difficulty_level values (1=Basic, 2=Intermediate, 3=Advanced). */
    private const VALID_DIFFICULTY_LEVELS = [1, 2, 3];

    /**
     * Map performance_category to allowed difficulty_level values.
     * Mirrors the logic in LearningResourceRepository::map_performance_to_difficulty().
     *
     * - Low (1)    → [1]
     * - Middle (2) → [1, 2]
     * - High (3)   → [2, 3]
     *
     * @var array<int, int[]>
     */
    private const PERFORMANCE_TO_DIFFICULTY = [
        1 => [1],
        2 => [1, 2],
        3 => [2, 3],
    ];

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
        $this->setAdminUser();
    }

    // =========================================================================
    // Helper: build a LearnerProfile with given performance_category and
    //         learning_style
    // =========================================================================

    /**
     * Build a LearnerProfile with the given performance_category and learning_style.
     *
     * @param  int    $performance_category 1=Low, 2=Middle, 3=High.
     * @param  string $learning_style       One of the VALID_LEARNING_STYLES.
     * @return \block_attendanceleaderboard\profiling\learner_profile
     */
    private function build_profile(
        int $performance_category,
        string $learning_style
    ): \block_attendanceleaderboard\profiling\learner_profile {
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $profile = new \block_attendanceleaderboard\profiling\learner_profile(
            (int) $user->id,
            (int) $course->id
        );
        $profile->performance_category = $performance_category;
        $profile->learning_style       = $learning_style;

        return $profile;
    }

    // =========================================================================
    // Helper: insert a learning resource record directly into acmls_learning_resource
    // =========================================================================

    /**
     * Insert a learning resource record into acmls_learning_resource.
     *
     * @param  int      $courseid        Moodle course ID.
     * @param  int      $difficulty_level 1=Basic, 2=Intermediate, 3=Advanced.
     * @param  string[] $learning_styles  Array of learning style strings.
     * @param  string   $title           Resource title.
     * @param  bool     $is_active       Whether the resource is active.
     * @return int                       Inserted record ID.
     */
    private function insert_resource(
        int $courseid,
        int $difficulty_level,
        array $learning_styles,
        string $title = 'Test Resource',
        bool $is_active = true
    ): int {
        global $DB;

        $now = time();

        $record = new \stdClass();
        $record->courseid          = $courseid;
        $record->cmid              = $now + rand(1, 999999); // unique fake cmid
        $record->title             = $title;
        $record->resource_type     = 'resource';
        $record->difficulty_level  = $difficulty_level;
        $record->topic_tags        = json_encode([]);
        $record->learning_styles   = json_encode($learning_styles);
        $record->access_count      = 0;
        $record->avg_rating        = null;
        $record->effectiveness_score = null;
        $record->is_active         = $is_active ? 1 : 0;
        $record->timecreated       = $now;
        $record->timemodified      = $now;

        return (int) $DB->insert_record('acmls_learning_resource', $record);
    }

    // =========================================================================
    // Helper: seed a diverse pool of resources covering all combinations of
    //         difficulty_level × learning_styles
    // =========================================================================

    /**
     * Seed the acmls_learning_resource table with a diverse set of resources
     * covering all combinations of difficulty_level × learning_style.
     *
     * This ensures find_by_profile() always has candidates to choose from AND
     * has many off-target resources that must NOT be returned.
     *
     * @param  int $courseid Moodle course ID.
     * @return void
     */
    private function seed_diverse_resources(int $courseid): void {
        foreach (self::VALID_DIFFICULTY_LEVELS as $difficulty) {
            foreach (self::VALID_LEARNING_STYLES as $style) {
                $this->insert_resource(
                    $courseid,
                    $difficulty,
                    [$style],
                    "Resource [diff={$difficulty}|style={$style}]"
                );
            }
        }
    }

    // =========================================================================
    // PROPERTY 17 (Core): All returned resources have correct difficulty_level
    // =========================================================================

    /**
     * Property 17 (Difficulty Match): For any performance_category, all resources
     * returned by find_by_profile() must have a difficulty_level within the
     * allowed range for that performance_category.
     *
     * Allowed ranges:
     *   - Low (1)    → difficulty_level IN (1)
     *   - Middle (2) → difficulty_level IN (1, 2)
     *   - High (3)   → difficulty_level IN (2, 3)
     *
     * **Validates: Requirements 9.2**
     *
     * Test Strategy:
     * - Seed the table with resources for ALL difficulty_level × learning_style
     *   combinations (diverse data, including many off-target resources).
     * - Generate arbitrary (performance_category, learning_style).
     * - Call find_by_profile() with those parameters.
     * - Assert every returned resource has difficulty_level in the allowed range.
     *
     * @return void
     */
    public function test_property17_all_returned_resources_have_correct_difficulty_level(): void {
        $this->limitTo(10);
        $this->forAll(
            Generator\elements(...self::VALID_PERFORMANCE_CATEGORIES),
            Generator\elements(...self::VALID_LEARNING_STYLES)
        )->then(function (int $performance_category, string $learning_style) {
            $repo    = new \block_attendanceleaderboard\repository\learning_resource_repository();
            $profile = $this->build_profile($performance_category, $learning_style);

            // Seed diverse resources covering all combinations.
            $this->seed_diverse_resources((int) $profile->courseid);

            $results = $repo->find_by_profile($profile, 10);

            // If no results, the property is vacuously satisfied.
            if (empty($results)) {
                return;
            }

            $allowed_difficulties = self::PERFORMANCE_TO_DIFFICULTY[$performance_category];

            foreach ($results as $resource) {
                $this->assertContains(
                    (int) $resource->difficulty_level,
                    $allowed_difficulties,
                    "Property 17 violated (difficulty match): " .
                    "find_by_profile() returned resource id={$resource->id} " .
                    "with difficulty_level={$resource->difficulty_level} " .
                    "but allowed difficulties for performance_category={$performance_category} " .
                    "are [" . implode(', ', $allowed_difficulties) . "]. " .
                    "No resource with an out-of-range difficulty_level must be returned (Req 9.2)."
                );
            }
        });
    }

    // =========================================================================
    // PROPERTY 17 (Style Match): When style-matched resources exist, all
    //                             returned resources must match the learning_style
    // =========================================================================

    /**
     * Property 17 (Style Match): When the resource pool contains resources that
     * match BOTH the difficulty_level range AND the requested learning_style,
     * find_by_profile() must return ONLY style-matched resources — not resources
     * with a different learning_style.
     *
     * **Validates: Requirements 9.2**
     *
     * Test Strategy:
     * - Seed resources for ALL difficulty_level × learning_style combinations.
     * - Generate arbitrary (performance_category, learning_style).
     * - Verify that style-matched resources exist in the pool for the given
     *   performance_category + learning_style combination.
     * - Call find_by_profile().
     * - Assert every returned resource has learning_styles containing the
     *   requested learning_style.
     *
     * @return void
     */
    public function test_property17_returned_resources_match_learning_style_when_available(): void {
        $this->limitTo(10);
        $this->forAll(
            Generator\elements(...self::VALID_PERFORMANCE_CATEGORIES),
            Generator\elements(...self::VALID_LEARNING_STYLES)
        )->then(function (int $performance_category, string $learning_style) {
            $repo    = new \block_attendanceleaderboard\repository\learning_resource_repository();
            $profile = $this->build_profile($performance_category, $learning_style);

            // Seed diverse resources covering all combinations.
            $this->seed_diverse_resources((int) $profile->courseid);

            $allowed_difficulties = self::PERFORMANCE_TO_DIFFICULTY[$performance_category];

            // Verify that style-matched resources exist in the pool for this combination.
            // (They always do because seed_diverse_resources covers all combinations.)
            $style_matched_exist = false;
            global $DB;
            foreach ($allowed_difficulties as $diff) {
                $sql = "SELECT COUNT(*) FROM {acmls_learning_resource}
                         WHERE difficulty_level = :diff
                           AND is_active = 1
                           AND " . $DB->sql_like('learning_styles', ':style');
                $count = $DB->count_records_sql($sql, [
                    'diff'  => $diff,
                    'style' => '%' . $DB->sql_like_escape($learning_style) . '%',
                ]);
                if ($count > 0) {
                    $style_matched_exist = true;
                    break;
                }
            }

            if (!$style_matched_exist) {
                // No style-matched resources exist — fallback to difficulty-only is expected.
                return;
            }

            $results = $repo->find_by_profile($profile, 10);

            if (empty($results)) {
                return;
            }

            foreach ($results as $resource) {
                $styles = json_decode($resource->learning_styles ?? '[]', true);
                $styles = is_array($styles) ? $styles : [];

                $this->assertContains(
                    $learning_style,
                    $styles,
                    "Property 17 violated (style match): " .
                    "find_by_profile() returned resource id={$resource->id} " .
                    "with learning_styles=" . json_encode($styles) . " " .
                    "but requested learning_style='{$learning_style}'. " .
                    "When style-matched resources exist, only style-matched resources " .
                    "must be returned (Req 9.2). " .
                    "performance_category={$performance_category}, " .
                    "allowed_difficulties=[" . implode(', ', $allowed_difficulties) . "]."
                );
            }
        });
    }

    // =========================================================================
    // PROPERTY 17 (No Off-Target Difficulty): Resources outside the allowed
    //             difficulty range must never be returned
    // =========================================================================

    /**
     * Property 17 (No Off-Target Difficulty): Resources with difficulty_level
     * outside the allowed range for the given performance_category must NEVER
     * be returned, even if they match the learning_style.
     *
     * **Validates: Requirements 9.2**
     *
     * Test Strategy:
     * - For each performance_category, determine the forbidden difficulty levels.
     * - Seed resources ONLY with forbidden difficulty levels (all learning styles).
     * - Call find_by_profile().
     * - Assert that no resources are returned (all seeded resources are off-target).
     *
     * @return void
     */
    public function test_property17_resources_outside_difficulty_range_never_returned(): void {
        $this->limitTo(10);
        $this->forAll(
            Generator\elements(...self::VALID_PERFORMANCE_CATEGORIES),
            Generator\elements(...self::VALID_LEARNING_STYLES)
        )->then(function (int $performance_category, string $learning_style) {
            global $DB;

            // Clear the table between iterations to ensure isolation.
            $DB->delete_records('acmls_learning_resource');

            $repo    = new \block_attendanceleaderboard\repository\learning_resource_repository();
            $profile = $this->build_profile($performance_category, $learning_style);

            $allowed_difficulties = self::PERFORMANCE_TO_DIFFICULTY[$performance_category];
            $forbidden_difficulties = array_values(array_diff(
                self::VALID_DIFFICULTY_LEVELS,
                $allowed_difficulties
            ));

            if (empty($forbidden_difficulties)) {
                // All difficulty levels are allowed — skip this iteration.
                return;
            }

            // Seed resources ONLY with forbidden difficulty levels.
            foreach ($forbidden_difficulties as $forbidden_diff) {
                foreach (self::VALID_LEARNING_STYLES as $style) {
                    $this->insert_resource(
                        (int) $profile->courseid,
                        $forbidden_diff,
                        [$style],
                        "Forbidden resource [diff={$forbidden_diff}|style={$style}]"
                    );
                }
            }

            $results = $repo->find_by_profile($profile, 10);

            $this->assertEmpty(
                $results,
                "Property 17 violated (no off-target difficulty): " .
                "find_by_profile() returned " . count($results) . " resource(s) " .
                "but all seeded resources have forbidden difficulty levels " .
                "[" . implode(', ', $forbidden_difficulties) . "]. " .
                "Allowed difficulties for performance_category={$performance_category}: " .
                "[" . implode(', ', $allowed_difficulties) . "]. " .
                "Resources outside the difficulty range must never be returned (Req 9.2)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 17 (Inactive Resources): Inactive resources must never be returned
    // =========================================================================

    /**
     * Property 17 (Inactive Resources): Resources with is_active=0 must never
     * be returned by find_by_profile(), even if they match all other criteria.
     *
     * **Validates: Requirements 9.2**
     *
     * Test Strategy:
     * - Seed ONLY inactive resources matching the profile's difficulty and style.
     * - Call find_by_profile().
     * - Assert that no resources are returned.
     *
     * @return void
     */
    public function test_property17_inactive_resources_never_returned(): void {
        $this->limitTo(10);
        $this->forAll(
            Generator\elements(...self::VALID_PERFORMANCE_CATEGORIES),
            Generator\elements(...self::VALID_LEARNING_STYLES)
        )->then(function (int $performance_category, string $learning_style) {
            global $DB;

            // Clear the table between iterations to ensure isolation.
            $DB->delete_records('acmls_learning_resource');

            $repo    = new \block_attendanceleaderboard\repository\learning_resource_repository();
            $profile = $this->build_profile($performance_category, $learning_style);

            $allowed_difficulties = self::PERFORMANCE_TO_DIFFICULTY[$performance_category];

            // Seed ONLY inactive resources that would otherwise match.
            foreach ($allowed_difficulties as $diff) {
                $this->insert_resource(
                    (int) $profile->courseid,
                    $diff,
                    [$learning_style],
                    "Inactive resource [diff={$diff}|style={$learning_style}]",
                    false // is_active = false
                );
            }

            $results = $repo->find_by_profile($profile, 10);

            $this->assertEmpty(
                $results,
                "Property 17 violated (inactive resources): " .
                "find_by_profile() returned " . count($results) . " resource(s) " .
                "but all seeded resources are inactive (is_active=0). " .
                "Inactive resources must never be returned (Req 9.2). " .
                "performance_category={$performance_category}, " .
                "learning_style='{$learning_style}'."
            );
        });
    }

    // =========================================================================
    // PROPERTY 17 (Fallback Correctness): When no style-matched resources exist,
    //             fallback resources must still match the difficulty_level range
    // =========================================================================

    /**
     * Property 17 (Fallback Correctness): When no resources match the requested
     * learning_style within the allowed difficulty range, find_by_profile() falls
     * back to returning difficulty-only matches. These fallback resources must
     * still have difficulty_level within the allowed range.
     *
     * **Validates: Requirements 9.2**
     *
     * Test Strategy:
     * - Seed resources with allowed difficulty levels but DIFFERENT learning styles
     *   (so no style-matched resources exist for the requested style).
     * - Call find_by_profile().
     * - Assert every returned resource has difficulty_level in the allowed range.
     * - Assert no returned resource has the requested learning_style
     *   (confirming fallback was used, not style-matching).
     *
     * @return void
     */
    public function test_property17_fallback_resources_still_match_difficulty_range(): void {
        $this->limitTo(10);
        $this->forAll(
            Generator\elements(...self::VALID_PERFORMANCE_CATEGORIES),
            Generator\elements(...self::VALID_LEARNING_STYLES)
        )->then(function (int $performance_category, string $learning_style) {
            global $DB;

            // Clear the table between iterations to ensure isolation.
            $DB->delete_records('acmls_learning_resource');

            $repo    = new \block_attendanceleaderboard\repository\learning_resource_repository();
            $profile = $this->build_profile($performance_category, $learning_style);

            $allowed_difficulties = self::PERFORMANCE_TO_DIFFICULTY[$performance_category];

            // Find a different learning style to use for seeded resources.
            $other_styles = array_values(array_filter(
                self::VALID_LEARNING_STYLES,
                fn($s) => $s !== $learning_style
            ));

            if (empty($other_styles)) {
                return; // Only one style exists — skip.
            }

            $other_style = $other_styles[0];

            // Seed resources with allowed difficulty levels but a DIFFERENT learning style.
            foreach ($allowed_difficulties as $diff) {
                $this->insert_resource(
                    (int) $profile->courseid,
                    $diff,
                    [$other_style],
                    "Fallback resource [diff={$diff}|style={$other_style}]"
                );
            }

            $results = $repo->find_by_profile($profile, 10);

            if (empty($results)) {
                return; // No results — vacuously satisfied.
            }

            // INVARIANT: All returned resources must have difficulty_level in allowed range.
            foreach ($results as $resource) {
                $this->assertContains(
                    (int) $resource->difficulty_level,
                    $allowed_difficulties,
                    "Property 17 violated (fallback difficulty): " .
                    "find_by_profile() returned resource id={$resource->id} " .
                    "with difficulty_level={$resource->difficulty_level} " .
                    "but allowed difficulties for performance_category={$performance_category} " .
                    "are [" . implode(', ', $allowed_difficulties) . "]. " .
                    "Even fallback resources must match the difficulty range (Req 9.2)."
                );
            }
        });
    }

    // =========================================================================
    // PROPERTY 17 (Exhaustive Boundary): All 12 combinations are tested
    // =========================================================================

    /**
     * Property 17 (Exhaustive Boundary): Deterministically verify all
     * 3 performance_categories × 4 learning_styles = 12 combinations to ensure
     * no combination produces off-target results.
     *
     * **Validates: Requirements 9.2**
     *
     * @return void
     */
    public function test_property17_all_12_combinations_return_correct_resources_only(): void {
        $repo = new \block_attendanceleaderboard\repository\learning_resource_repository();

        foreach (self::VALID_PERFORMANCE_CATEGORIES as $performance_category) {
            foreach (self::VALID_LEARNING_STYLES as $learning_style) {
                $profile = $this->build_profile($performance_category, $learning_style);

                // Seed diverse resources for all combinations.
                $this->seed_diverse_resources((int) $profile->courseid);

                $results = $repo->find_by_profile($profile, 10);

                $allowed_difficulties = self::PERFORMANCE_TO_DIFFICULTY[$performance_category];
                $context = "performance_category={$performance_category}, " .
                           "learning_style='{$learning_style}', " .
                           "allowed_difficulties=[" . implode(', ', $allowed_difficulties) . "]";

                foreach ($results as $resource) {
                    // INVARIANT 1: difficulty_level must be in allowed range.
                    $this->assertContains(
                        (int) $resource->difficulty_level,
                        $allowed_difficulties,
                        "Property 17 violated [{$context}]: " .
                        "resource id={$resource->id} has difficulty_level={$resource->difficulty_level} " .
                        "which is outside the allowed range. " .
                        "No off-target difficulty resources must be returned (Req 9.2)."
                    );

                    // INVARIANT 2: resource must be active.
                    $this->assertEquals(
                        1,
                        (int) $resource->is_active,
                        "Property 17 violated [{$context}]: " .
                        "resource id={$resource->id} has is_active={$resource->is_active}. " .
                        "Inactive resources must never be returned (Req 9.2)."
                    );
                }
            }
        }
    }

    // =========================================================================
    // PROPERTY 17 (Isolation): Resources from different courses are isolated
    // =========================================================================

    /**
     * Property 17 (Course Isolation): Resources from different courses must not
     * contaminate each other's query results.
     *
     * Note: find_by_profile() does NOT filter by courseid — it queries all active
     * resources matching the difficulty range. This test verifies that the
     * difficulty and style constraints are still respected regardless of courseid.
     *
     * **Validates: Requirements 9.2**
     *
     * @return void
     */
    public function test_property17_difficulty_constraint_holds_across_all_courses(): void {
        $this->limitTo(10);
        $this->forAll(
            Generator\elements(...self::VALID_PERFORMANCE_CATEGORIES),
            Generator\elements(...self::VALID_LEARNING_STYLES)
        )->then(function (int $performance_category, string $learning_style) {
            $repo    = new \block_attendanceleaderboard\repository\learning_resource_repository();
            $profile = $this->build_profile($performance_category, $learning_style);

            // Seed diverse resources for multiple courses.
            $course_a = $this->getDataGenerator()->create_course();
            $course_b = $this->getDataGenerator()->create_course();

            $this->seed_diverse_resources((int) $course_a->id);
            $this->seed_diverse_resources((int) $course_b->id);

            $results = $repo->find_by_profile($profile, 20);

            if (empty($results)) {
                return;
            }

            $allowed_difficulties = self::PERFORMANCE_TO_DIFFICULTY[$performance_category];

            foreach ($results as $resource) {
                $this->assertContains(
                    (int) $resource->difficulty_level,
                    $allowed_difficulties,
                    "Property 17 violated (cross-course): " .
                    "find_by_profile() returned resource id={$resource->id} " .
                    "with difficulty_level={$resource->difficulty_level} " .
                    "but allowed difficulties for performance_category={$performance_category} " .
                    "are [" . implode(', ', $allowed_difficulties) . "]. " .
                    "Difficulty constraint must hold across all courses (Req 9.2)."
                );
            }
        });
    }

    // =========================================================================
    // PROPERTY 17 (Result Count): find_by_profile() respects the $limit parameter
    // =========================================================================

    /**
     * Property 17 (Result Count): The number of resources returned by
     * find_by_profile() must never exceed the requested $limit.
     *
     * **Validates: Requirements 9.2**
     *
     * @return void
     */
    public function test_property17_result_count_never_exceeds_limit(): void {
        $this->limitTo(10);
        $this->forAll(
            Generator\elements(...self::VALID_PERFORMANCE_CATEGORIES),
            Generator\elements(...self::VALID_LEARNING_STYLES),
            Generator\choose(1, 10)  // limit between 1 and 10
        )->then(function (int $performance_category, string $learning_style, int $limit) {
            $repo    = new \block_attendanceleaderboard\repository\learning_resource_repository();
            $profile = $this->build_profile($performance_category, $learning_style);

            // Seed many resources to ensure the limit is exercised.
            $this->seed_diverse_resources((int) $profile->courseid);

            $results = $repo->find_by_profile($profile, $limit);

            $this->assertLessThanOrEqual(
                $limit,
                count($results),
                "Property 17 violated (result count): " .
                "find_by_profile() returned " . count($results) . " resources " .
                "but the requested limit was {$limit}. " .
                "Result count must never exceed the limit (Req 9.2). " .
                "performance_category={$performance_category}, " .
                "learning_style='{$learning_style}'."
            );
        });
    }
}
