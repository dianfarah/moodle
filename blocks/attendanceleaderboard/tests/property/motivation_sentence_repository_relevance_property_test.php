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
 * Property-based test for Property 16: Relevansi Encouragement_Content yang
 * Dikembalikan Repository.
 *
 * Verifies that ALL content returned by
 * MotivationSentenceRepository::find_relevant() matches the requested
 * category AND performance_target parameters. No off-target content should
 * ever be returned.
 *
 * **Validates: Requirements 8.1, 8.2**
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
 * Property 16: Relevansi Encouragement_Content yang Dikembalikan Repository.
 *
 * All content returned by MotivationSentenceRepository::find_relevant() must
 * match the requested category AND performance_target parameters. No content
 * with a different category or performance_target should ever be returned.
 *
 * Formally:
 *   For all (category, performance_target, motivation_target):
 *     result = find_relevant(userid, category, performance_target, motivation_target)
 *     IF result IS NOT NULL THEN
 *       the record in acmls_motivation_sentence whose content = result
 *       MUST have category = requested_category
 *       AND performance_target = requested_performance_target
 *
 * This property must hold regardless of:
 *   - What other records exist in the table (diverse seed data)
 *   - The motivation_target value
 *   - The order records were inserted
 *   - Whether the result comes from a low-usage or high-usage record
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class motivation_sentence_repository_relevance_property_test extends \advanced_testcase {
    use TestTrait;

    // =========================================================================
    // Constants — valid domain values
    // =========================================================================

    /** @var string[] All valid Encouragement_Content categories. */
    private const VALID_CATEGORIES = [
        'reinforcement',
        'achievement',
        'recovery',
        'persistence',
    ];

    /** @var int[] All valid performance_target values (1=Low, 2=Middle, 3=High). */
    private const VALID_PERFORMANCE_TARGETS = [1, 2, 3];

    /** @var int[] All valid motivation_target values (1=Low, 2=Middle, 3=High). */
    private const VALID_MOTIVATION_TARGETS = [1, 2, 3];

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
    // Helper: seed diverse records covering all category × performance_target
    // =========================================================================

    /**
     * Seed the acmls_motivation_sentence table with a diverse set of records
     * covering all combinations of category × performance_target × motivation_target.
     *
     * This ensures find_relevant() always has candidates to choose from AND
     * has many off-target records that must NOT be returned.
     *
     * @param  \block_attendanceleaderboard\motivation\motivation_sentence_repository $repo
     * @return array  Map of [category][performance_target][motivation_target] => content string
     */
    private function seed_diverse_records(
        \block_attendanceleaderboard\motivation\motivation_sentence_repository $repo
    ): array {
        $seeded = [];

        foreach (self::VALID_CATEGORIES as $cat) {
            $seeded[$cat] = [];
            foreach (self::VALID_PERFORMANCE_TARGETS as $perf) {
                $seeded[$cat][$perf] = [];
                foreach (self::VALID_MOTIVATION_TARGETS as $motiv) {
                    // Insert 2 records per combination to give find_relevant() choices.
                    for ($i = 1; $i <= 2; $i++) {
                        $content = "Konten motivasi [{$cat}|perf={$perf}|motiv={$motiv}|v{$i}]: " .
                                   "Semangat belajar Anda adalah kunci keberhasilan akademik.";

                        $repo->save($content, [
                            'category'           => $cat,
                            'performance_target' => $perf,
                            'motivation_target'  => $motiv,
                            'source'             => 'template',
                        ]);

                        $seeded[$cat][$perf][$motiv] = $content;
                    }
                }
            }
        }

        return $seeded;
    }

    /**
     * Given a returned content string, look up its stored record from the DB
     * and return it as an associative array.
     *
     * Uses sql_compare_text() because the 'content' column is LONGTEXT and
     * Moodle's DBAL does not allow direct equality comparisons on TEXT columns.
     *
     * @param  string $content The content string returned by find_relevant().
     * @return array|null      The matching DB record, or null if not found.
     */
    private function find_record_by_content(string $content): ?array {
        global $DB;

        $sql = "SELECT *
                  FROM {acmls_motivation_sentence}
                 WHERE " . $DB->sql_compare_text('content') . " = " . $DB->sql_compare_text(':content');

        $records = $DB->get_records_sql($sql, ['content' => $content]);

        if (empty($records)) {
            return null;
        }

        return (array) reset($records);
    }

    // =========================================================================
    // PROPERTY 16 (Core): Returned content always matches requested category
    //                      AND performance_target
    // =========================================================================

    /**
     * Property 16 (Core Relevance): For any combination of category,
     * performance_target, and motivation_target, when find_relevant() returns
     * a non-null result, the returned content MUST belong to a record whose
     * category equals the requested category AND whose performance_target
     * equals the requested performance_target.
     *
     * **Validates: Requirements 8.1, 8.2**
     *
     * Test Strategy:
     * - Seed the table with records for ALL category × performance_target ×
     *   motivation_target combinations (diverse data, including many off-target
     *   records that must NOT be returned).
     * - Generate arbitrary (category, performance_target, motivation_target).
     * - Call find_relevant() with those parameters.
     * - If a non-null result is returned, look up the record in the DB.
     * - Assert the record's category == requested category.
     * - Assert the record's performance_target == requested performance_target.
     *
     * @return void
     */
    public function test_property16_returned_content_matches_requested_category_and_performance_target(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS),
            Generator\elements(...self::VALID_MOTIVATION_TARGETS)
        )->then(function (
            string $requested_category,
            int $requested_performance_target,
            int $requested_motivation_target
        ) {
            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $user = $this->getDataGenerator()->create_user();

            // Seed diverse records covering all combinations.
            $this->seed_diverse_records($repo);

            // Call find_relevant() with the generated parameters.
            $result = $repo->find_relevant(
                (int) $user->id,
                $requested_category,
                $requested_performance_target,
                $requested_motivation_target
            );

            // If null is returned, the property is vacuously satisfied
            // (no off-target content was returned).
            if ($result === null) {
                // This is acceptable — no content available for this combination.
                return;
            }

            // Look up the returned content in the DB to verify its metadata.
            $record = $this->find_record_by_content($result);

            $this->assertNotNull(
                $record,
                "Property 16 violated: find_relevant() returned content that does not exist " .
                "in the acmls_motivation_sentence table. " .
                "requested_category='{$requested_category}', " .
                "requested_performance_target={$requested_performance_target}, " .
                "requested_motivation_target={$requested_motivation_target}. " .
                "Returned content: '{$result}'"
            );

            // INVARIANT 1: category must match the requested category.
            $this->assertEquals(
                $requested_category,
                $record['category'],
                "Property 16 violated: find_relevant() returned content with " .
                "category='{$record['category']}' but requested category='{$requested_category}'. " .
                "No off-target content should ever be returned (Req 8.1, 8.2). " .
                "requested_performance_target={$requested_performance_target}, " .
                "requested_motivation_target={$requested_motivation_target}."
            );

            // INVARIANT 2: performance_target must match the requested performance_target.
            $this->assertEquals(
                $requested_performance_target,
                (int) $record['performance_target'],
                "Property 16 violated: find_relevant() returned content with " .
                "performance_target={$record['performance_target']} but " .
                "requested performance_target={$requested_performance_target}. " .
                "No off-target content should ever be returned (Req 8.1, 8.2). " .
                "requested_category='{$requested_category}', " .
                "requested_motivation_target={$requested_motivation_target}."
            );
        });
    }

    // =========================================================================
    // PROPERTY 16 (No Cross-Category Contamination): Records from other
    // categories must never be returned
    // =========================================================================

    /**
     * Property 16 (No Cross-Category Contamination): When the table contains
     * records for multiple categories, find_relevant() must NEVER return a
     * record from a category other than the one requested.
     *
     * **Validates: Requirements 8.1, 8.2**
     *
     * Test Strategy:
     * - For each requested category, seed records for ALL categories.
     * - Call find_relevant() requesting a specific category.
     * - Assert the returned content (if any) belongs to the requested category only.
     *
     * @return void
     */
    public function test_property16_no_cross_category_contamination(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS),
            Generator\elements(...self::VALID_MOTIVATION_TARGETS)
        )->then(function (
            string $requested_category,
            int $requested_performance_target,
            int $requested_motivation_target
        ) {
            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $user = $this->getDataGenerator()->create_user();

            // Seed records for ALL categories (including off-target ones).
            $this->seed_diverse_records($repo);

            $result = $repo->find_relevant(
                (int) $user->id,
                $requested_category,
                $requested_performance_target,
                $requested_motivation_target
            );

            if ($result === null) {
                return; // Vacuously satisfied.
            }

            $record = $this->find_record_by_content($result);

            $this->assertNotNull(
                $record,
                "Property 16 violated (cross-category): returned content not found in DB. " .
                "requested_category='{$requested_category}'."
            );

            // The returned record must NOT belong to any other category.
            $other_categories = array_filter(
                self::VALID_CATEGORIES,
                fn($c) => $c !== $requested_category
            );

            $this->assertNotContains(
                $record['category'],
                array_values($other_categories),
                "Property 16 violated (cross-category contamination): " .
                "find_relevant() returned content from category='{$record['category']}' " .
                "but requested category='{$requested_category}'. " .
                "Content from other categories must never be returned (Req 8.1, 8.2)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 16 (No Cross-Performance Contamination): Records from other
    // performance_target values must never be returned
    // =========================================================================

    /**
     * Property 16 (No Cross-Performance Contamination): When the table contains
     * records for multiple performance_target values, find_relevant() must NEVER
     * return a record with a different performance_target than requested.
     *
     * **Validates: Requirements 8.1, 8.2**
     *
     * @return void
     */
    public function test_property16_no_cross_performance_target_contamination(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS),
            Generator\elements(...self::VALID_MOTIVATION_TARGETS)
        )->then(function (
            string $requested_category,
            int $requested_performance_target,
            int $requested_motivation_target
        ) {
            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $user = $this->getDataGenerator()->create_user();

            // Seed records for ALL performance_target values (including off-target ones).
            $this->seed_diverse_records($repo);

            $result = $repo->find_relevant(
                (int) $user->id,
                $requested_category,
                $requested_performance_target,
                $requested_motivation_target
            );

            if ($result === null) {
                return; // Vacuously satisfied.
            }

            $record = $this->find_record_by_content($result);

            $this->assertNotNull(
                $record,
                "Property 16 violated (cross-performance): returned content not found in DB. " .
                "requested_performance_target={$requested_performance_target}."
            );

            // The returned record must NOT have a different performance_target.
            $other_performance_targets = array_filter(
                self::VALID_PERFORMANCE_TARGETS,
                fn($p) => $p !== $requested_performance_target
            );

            $this->assertNotContains(
                (int) $record['performance_target'],
                array_values($other_performance_targets),
                "Property 16 violated (cross-performance contamination): " .
                "find_relevant() returned content with performance_target={$record['performance_target']} " .
                "but requested performance_target={$requested_performance_target}. " .
                "Content with a different performance_target must never be returned (Req 8.1, 8.2). " .
                "requested_category='{$requested_category}', " .
                "requested_motivation_target={$requested_motivation_target}."
            );
        });
    }

    // =========================================================================
    // PROPERTY 16 (Exhaustive Boundary): All 36 combinations are tested
    // =========================================================================

    /**
     * Property 16 (Exhaustive Boundary): Deterministically verify all
     * 4 categories × 3 performance_targets × 3 motivation_targets = 36
     * combinations to ensure no combination produces off-target results.
     *
     * **Validates: Requirements 8.1, 8.2**
     *
     * @return void
     */
    public function test_property16_all_36_combinations_return_relevant_content_only(): void {
        $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
        $user = $this->getDataGenerator()->create_user();

        // Seed diverse records for all combinations.
        $this->seed_diverse_records($repo);

        foreach (self::VALID_CATEGORIES as $requested_category) {
            foreach (self::VALID_PERFORMANCE_TARGETS as $requested_performance_target) {
                foreach (self::VALID_MOTIVATION_TARGETS as $requested_motivation_target) {
                    $result = $repo->find_relevant(
                        (int) $user->id,
                        $requested_category,
                        $requested_performance_target,
                        $requested_motivation_target
                    );

                    if ($result === null) {
                        continue; // No content available — vacuously satisfied.
                    }

                    $record = $this->find_record_by_content($result);

                    $context = "category='{$requested_category}', " .
                               "performance_target={$requested_performance_target}, " .
                               "motivation_target={$requested_motivation_target}";

                    $this->assertNotNull(
                        $record,
                        "Property 16 violated [{$context}]: returned content not found in DB."
                    );

                    $this->assertEquals(
                        $requested_category,
                        $record['category'],
                        "Property 16 violated [{$context}]: " .
                        "returned record has category='{$record['category']}' " .
                        "but requested category='{$requested_category}'. " .
                        "No off-target content should ever be returned (Req 8.1, 8.2)."
                    );

                    $this->assertEquals(
                        $requested_performance_target,
                        (int) $record['performance_target'],
                        "Property 16 violated [{$context}]: " .
                        "returned record has performance_target={$record['performance_target']} " .
                        "but requested performance_target={$requested_performance_target}. " .
                        "No off-target content should ever be returned (Req 8.1, 8.2)."
                    );
                }
            }
        }
    }

    // =========================================================================
    // PROPERTY 16 (Isolation): Different users get relevant content independently
    // =========================================================================

    /**
     * Property 16 (User Isolation): When multiple users request content with
     * different parameters, each user must receive content relevant to their
     * own requested category and performance_target — not each other's.
     *
     * **Validates: Requirements 8.1, 8.2**
     *
     * @return void
     */
    public function test_property16_different_users_get_independently_relevant_content(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS)
        )->then(function (
            string $category_a,
            string $category_b,
            int $performance_a,
            int $performance_b
        ) {
            $repo   = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $user_a = $this->getDataGenerator()->create_user();
            $user_b = $this->getDataGenerator()->create_user();

            // Seed diverse records for all combinations.
            $this->seed_diverse_records($repo);

            $motivation_target = 1; // Fixed for simplicity.

            $result_a = $repo->find_relevant(
                (int) $user_a->id,
                $category_a,
                $performance_a,
                $motivation_target
            );

            $result_b = $repo->find_relevant(
                (int) $user_b->id,
                $category_b,
                $performance_b,
                $motivation_target
            );

            // Verify user A's result is relevant to user A's request.
            if ($result_a !== null) {
                $record_a = $this->find_record_by_content($result_a);

                $this->assertNotNull(
                    $record_a,
                    "Property 16 violated (isolation): User A's returned content not found in DB."
                );

                $this->assertEquals(
                    $category_a,
                    $record_a['category'],
                    "Property 16 violated (isolation): User A received content with " .
                    "category='{$record_a['category']}' but requested '{$category_a}'. " .
                    "Each user must receive content relevant to their own request (Req 8.1, 8.2)."
                );

                $this->assertEquals(
                    $performance_a,
                    (int) $record_a['performance_target'],
                    "Property 16 violated (isolation): User A received content with " .
                    "performance_target={$record_a['performance_target']} but requested {$performance_a}. " .
                    "Each user must receive content relevant to their own request (Req 8.1, 8.2)."
                );
            }

            // Verify user B's result is relevant to user B's request.
            if ($result_b !== null) {
                $record_b = $this->find_record_by_content($result_b);

                $this->assertNotNull(
                    $record_b,
                    "Property 16 violated (isolation): User B's returned content not found in DB."
                );

                $this->assertEquals(
                    $category_b,
                    $record_b['category'],
                    "Property 16 violated (isolation): User B received content with " .
                    "category='{$record_b['category']}' but requested '{$category_b}'. " .
                    "Each user must receive content relevant to their own request (Req 8.1, 8.2)."
                );

                $this->assertEquals(
                    $performance_b,
                    (int) $record_b['performance_target'],
                    "Property 16 violated (isolation): User B received content with " .
                    "performance_target={$record_b['performance_target']}' but requested {$performance_b}. " .
                    "Each user must receive content relevant to their own request (Req 8.1, 8.2)."
                );
            }
        });
    }

    // =========================================================================
    // PROPERTY 16 (Null Safety): When no matching content exists, null is
    // returned — not off-target content
    // =========================================================================

    /**
     * Property 16 (Null Safety): When no active records exist for the requested
     * category + performance_target + motivation_target combination, find_relevant()
     * must return null — it must NOT fall back to returning off-target content.
     *
     * **Validates: Requirements 8.1, 8.2**
     *
     * Test Strategy:
     * - Clear the table at the start of each iteration.
     * - Insert records ONLY for a specific combination (e.g., 'recovery', perf=1, motiv=1).
     * - Request a DIFFERENT combination (e.g., 'achievement', perf=2, motiv=2).
     * - Assert that null is returned (not the off-target 'recovery' content).
     *
     * Note: The table is cleared between iterations to prevent records from
     * previous iterations from contaminating the null-safety check.
     *
     * @return void
     */
    public function test_property16_returns_null_not_off_target_content_when_no_match(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS),
            Generator\elements(...self::VALID_MOTIVATION_TARGETS)
        )->then(function (
            string $seeded_category,
            int $seeded_performance_target,
            int $seeded_motivation_target
        ) {
            global $DB;

            // Clear the table between iterations to ensure isolation.
            $DB->delete_records('acmls_motivation_sentence');

            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $user = $this->getDataGenerator()->create_user();

            // Seed records ONLY for the seeded combination.
            $seeded_content = "Konten khusus untuk [{$seeded_category}|perf={$seeded_performance_target}|motiv={$seeded_motivation_target}].";
            $repo->save($seeded_content, [
                'category'           => $seeded_category,
                'performance_target' => $seeded_performance_target,
                'motivation_target'  => $seeded_motivation_target,
                'source'             => 'template',
            ]);

            // Find a DIFFERENT category to request (if possible).
            $other_categories = array_values(array_filter(
                self::VALID_CATEGORIES,
                fn($c) => $c !== $seeded_category
            ));

            if (empty($other_categories)) {
                return; // Only one category exists — skip.
            }

            $requested_category = $other_categories[0];

            // Find a DIFFERENT performance_target to request (if possible).
            $other_performance_targets = array_values(array_filter(
                self::VALID_PERFORMANCE_TARGETS,
                fn($p) => $p !== $seeded_performance_target
            ));

            if (empty($other_performance_targets)) {
                return; // Only one performance_target exists — skip.
            }

            $requested_performance_target = $other_performance_targets[0];

            // Request a combination that has NO seeded records.
            $result = $repo->find_relevant(
                (int) $user->id,
                $requested_category,
                $requested_performance_target,
                $seeded_motivation_target
            );

            // Must return null — NOT the off-target seeded content.
            $this->assertNull(
                $result,
                "Property 16 violated (null safety): find_relevant() returned non-null content " .
                "when no matching records exist for the requested combination. " .
                "requested_category='{$requested_category}', " .
                "requested_performance_target={$requested_performance_target}. " .
                "Only seeded combination: category='{$seeded_category}', " .
                "performance_target={$seeded_performance_target}. " .
                "Off-target content must NEVER be returned (Req 8.1, 8.2). " .
                "Returned: '{$result}'"
            );
        });
    }

    // =========================================================================
    // PROPERTY 16 (Active-Only): Inactive records must never be returned
    // =========================================================================

    /**
     * Property 16 (Active-Only): find_relevant() must only return content from
     * records with is_active = 1. Inactive records (is_active = 0) must never
     * be returned, even if they match the requested category and performance_target.
     *
     * **Validates: Requirements 8.1, 8.2**
     *
     * @return void
     */
    public function test_property16_inactive_records_are_never_returned(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS),
            Generator\elements(...self::VALID_MOTIVATION_TARGETS)
        )->then(function (
            string $category,
            int $performance_target,
            int $motivation_target
        ) {
            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $user = $this->getDataGenerator()->create_user();

            // Save a record and then soft-delete it (is_active = 0).
            $inactive_content = "Konten tidak aktif [{$category}|perf={$performance_target}|motiv={$motivation_target}].";
            $id = $repo->save($inactive_content, [
                'category'           => $category,
                'performance_target' => $performance_target,
                'motivation_target'  => $motivation_target,
                'source'             => 'template',
            ]);

            // Soft-delete the record.
            $repo->crud_delete($id);

            // find_relevant() must return null (no active records for this combination).
            $result = $repo->find_relevant(
                (int) $user->id,
                $category,
                $performance_target,
                $motivation_target
            );

            $this->assertNull(
                $result,
                "Property 16 violated (active-only): find_relevant() returned content from " .
                "an inactive record (is_active=0). " .
                "category='{$category}', performance_target={$performance_target}, " .
                "motivation_target={$motivation_target}. " .
                "Inactive records must never be returned (Req 8.1, 8.2). " .
                "Returned: '{$result}'"
            );
        });
    }
}
