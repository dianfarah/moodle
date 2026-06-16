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
 * Property-based test for Property 14: Kelengkapan Metadata Encouragement_Content.
 *
 * Verifies that every Encouragement_Content record saved to the
 * Motivation_Sentence_Repository contains all required metadata fields
 * with valid, non-empty values - regardless of source (LLM or template)
 * or input combination.
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
 * Property 14: Kelengkapan Metadata Encouragement_Content.
 *
 * For any generated Encouragement_Content - regardless of source (LLM or
 * template) - when it is saved to the Motivation_Sentence_Repository, the
 * stored record MUST contain all required metadata fields with valid,
 * non-empty values:
 *
 *   - category        - must be one of: 'reinforcement', 'achievement',
 *                       'recovery', 'persistence'
 *   - performance_target - must be 1, 2, or 3
 *   - motivation_target  - must be 1, 2, or 3
 *   - source          - must be 'llm' or 'template'
 *   - timecreated     - must be a valid Unix timestamp (positive integer)
 *   - content         - must be a non-empty string
 *
 * The property must hold for ALL valid combinations of inputs:
 * any category x any performance_target x any motivation_target x any source.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class encouragement_content_metadata_property_test extends \advanced_testcase {
    use TestTrait;

    // =========================================================================
    // Constants - valid domain values
    // =========================================================================

    /** @var string[] All valid Encouragement_Content categories. */
    private const VALID_CATEGORIES = [
        'reinforcement',
        'achievement',
        'recovery',
        'persistence',
    ];

    /** @var int[] All valid performance_target values. */
    private const VALID_PERFORMANCE_TARGETS = [1, 2, 3];

    /** @var int[] All valid motivation_target values. */
    private const VALID_MOTIVATION_TARGETS = [1, 2, 3];

    /** @var string[] All valid source values. */
    private const VALID_SOURCES = ['llm', 'template'];

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
    // Helper: assert all required metadata fields are present and valid
    // =========================================================================

    /**
     * Assert that a retrieved record contains all required metadata fields
     * with valid, non-empty values.
     *
     * Required fields and their validity constraints:
     * - category        : one of VALID_CATEGORIES
     * - performance_target : one of {1, 2, 3}
     * - motivation_target  : one of {1, 2, 3}
     * - source          : one of {'llm', 'template'}
     * - timecreated     : positive integer (Unix timestamp)
     * - content         : non-empty string
     *
     * @param  array  $record  Associative array from crud_read().
     * @param  string $context Human-readable context for assertion messages.
     * @return void
     */
    private function assert_metadata_complete(array $record, string $context = ''): void {
        $ctx = $context ? " [{$context}]" : '';

        // --- category ---
        $this->assertArrayHasKey(
            'category',
            $record,
            "Property 14 violated{$ctx}: 'category' field is missing from stored record."
        );
        $this->assertNotEmpty(
            $record['category'],
            "Property 14 violated{$ctx}: 'category' must not be empty."
        );
        $this->assertContains(
            $record['category'],
            self::VALID_CATEGORIES,
            "Property 14 violated{$ctx}: 'category'='{$record['category']}' is not one of the " .
            "valid categories: [" . implode(', ', self::VALID_CATEGORIES) . "]. " .
            "All generated content must be stored with a valid category (Req 7.1)."
        );

        // --- performance_target ---
        $this->assertArrayHasKey(
            'performance_target',
            $record,
            "Property 14 violated{$ctx}: 'performance_target' field is missing from stored record."
        );
        $this->assertContains(
            (int) $record['performance_target'],
            self::VALID_PERFORMANCE_TARGETS,
            "Property 14 violated{$ctx}: 'performance_target'={$record['performance_target']} " .
            "is not one of the valid values: [1, 2, 3]. " .
            "All generated content must be stored with a valid performance_target (Req 7.1)."
        );

        // --- motivation_target ---
        $this->assertArrayHasKey(
            'motivation_target',
            $record,
            "Property 14 violated{$ctx}: 'motivation_target' field is missing from stored record."
        );
        $this->assertContains(
            (int) $record['motivation_target'],
            self::VALID_MOTIVATION_TARGETS,
            "Property 14 violated{$ctx}: 'motivation_target'={$record['motivation_target']} " .
            "is not one of the valid values: [1, 2, 3]. " .
            "All generated content must be stored with a valid motivation_target (Req 7.1)."
        );

        // --- source ---
        $this->assertArrayHasKey(
            'source',
            $record,
            "Property 14 violated{$ctx}: 'source' field is missing from stored record."
        );
        $this->assertNotEmpty(
            $record['source'],
            "Property 14 violated{$ctx}: 'source' must not be empty."
        );
        $this->assertContains(
            $record['source'],
            self::VALID_SOURCES,
            "Property 14 violated{$ctx}: 'source'='{$record['source']}' is not one of the " .
            "valid sources: [llm, template]. " .
            "All generated content must be stored with a valid source (Req 7.1)."
        );

        // --- timecreated ---
        $this->assertArrayHasKey(
            'timecreated',
            $record,
            "Property 14 violated{$ctx}: 'timecreated' field is missing from stored record."
        );
        $this->assertNotNull(
            $record['timecreated'],
            "Property 14 violated{$ctx}: 'timecreated' must not be null. " .
            "All generated content must be stored with a timestamp (Req 7.1)."
        );
        $this->assertGreaterThan(
            0,
            (int) $record['timecreated'],
            "Property 14 violated{$ctx}: 'timecreated'={$record['timecreated']} must be a " .
            "positive Unix timestamp. All generated content must be stored with a valid " .
            "generation timestamp (Req 7.1)."
        );

        // --- content ---
        $this->assertArrayHasKey(
            'content',
            $record,
            "Property 14 violated{$ctx}: 'content' field is missing from stored record."
        );
        $this->assertNotEmpty(
            $record['content'],
            "Property 14 violated{$ctx}: 'content' must not be empty. " .
            "All generated content must be stored with non-empty content (Req 7.1)."
        );
        $this->assertIsString(
            $record['content'],
            "Property 14 violated{$ctx}: 'content' must be a string."
        );
    }

    // =========================================================================
    // PROPERTY 14 (Core): All metadata fields present for any valid combination
    // =========================================================================

    /**
     * Property 14 (Core Completeness): For any valid combination of category,
     * performance_target, motivation_target, and source, when content is saved
     * via MotivationSentenceRepository::save(), the stored record must contain
     * all required metadata fields with valid values.
     *
     * **Validates: Requirements 7.1**
     *
     * Test Strategy:
     * - Generate random category from VALID_CATEGORIES
     * - Generate random performance_target from {1, 2, 3}
     * - Generate random motivation_target from {1, 2, 3}
     * - Generate random source from {'llm', 'template'}
     * - Save content with these metadata values
     * - Read back the stored record
     * - Assert all required metadata fields are present and valid
     *
     * @return void
     */
    public function test_property14_all_metadata_fields_present_for_any_valid_combination(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS),
            Generator\elements(...self::VALID_MOTIVATION_TARGETS),
            Generator\elements(...self::VALID_SOURCES)
        )->then(function (
            string $category,
            int $performance_target,
            int $motivation_target,
            string $source
        ) {
            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

            $content = "Kalimat motivasi untuk kategori {$category}, " .
                       "performa {$performance_target}, motivasi {$motivation_target}.";

            $id = $repo->save($content, [
                'category'           => $category,
                'performance_target' => $performance_target,
                'motivation_target'  => $motivation_target,
                'source'             => $source,
            ]);

            $this->assertGreaterThan(
                0,
                $id,
                "Property 14 violated: save() must return a positive record ID. " .
                "category={$category}, performance_target={$performance_target}, " .
                "motivation_target={$motivation_target}, source={$source}."
            );

            $record = $repo->crud_read($id);

            $this->assertNotNull(
                $record,
                "Property 14 violated: crud_read({$id}) returned null - record was not persisted. " .
                "category={$category}, performance_target={$performance_target}, " .
                "motivation_target={$motivation_target}, source={$source}."
            );

            $context = "category={$category}, perf={$performance_target}, " .
                       "motiv={$motivation_target}, source={$source}";
            $this->assert_metadata_complete($record, $context);
        });
    }

    // =========================================================================
    // PROPERTY 14 (LLM Source): LLM-sourced content has complete metadata
    // =========================================================================

    /**
     * Property 14 (LLM Source): For any valid category x performance_target x
     * motivation_target combination, content saved with source='llm' must have
     * all required metadata fields populated.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property14_llm_sourced_content_has_complete_metadata(): void {
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

            $content = "Konten LLM untuk {$category} dengan performa {$performance_target}.";

            $id = $repo->save($content, [
                'category'           => $category,
                'performance_target' => $performance_target,
                'motivation_target'  => $motivation_target,
                'source'             => 'llm',
                'llm_model'          => 'test-model-v1',
            ]);

            $record = $repo->crud_read($id);

            $this->assertNotNull($record, "Property 14 violated: LLM record not persisted.");

            $context = "LLM source, category={$category}, perf={$performance_target}, motiv={$motivation_target}";
            $this->assert_metadata_complete($record, $context);

            // Additionally verify source is exactly 'llm'.
            $this->assertEquals(
                'llm',
                $record['source'],
                "Property 14 violated [{$context}]: source must be 'llm' for LLM-generated content."
            );
        });
    }

    // =========================================================================
    // PROPERTY 14 (Template Source): Template-sourced content has complete metadata
    // =========================================================================

    /**
     * Property 14 (Template Source): For any valid category x performance_target x
     * motivation_target combination, content saved with source='template' must have
     * all required metadata fields populated.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property14_template_sourced_content_has_complete_metadata(): void {
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

            $content = "Template motivasi untuk {$category} dengan performa {$performance_target}.";

            $id = $repo->save($content, [
                'category'           => $category,
                'performance_target' => $performance_target,
                'motivation_target'  => $motivation_target,
                'source'             => 'template',
            ]);

            $record = $repo->crud_read($id);

            $this->assertNotNull($record, "Property 14 violated: template record not persisted.");

            $context = "template source, category={$category}, perf={$performance_target}, motiv={$motivation_target}";
            $this->assert_metadata_complete($record, $context);

            // Additionally verify source is exactly 'template'.
            $this->assertEquals(
                'template',
                $record['source'],
                "Property 14 violated [{$context}]: source must be 'template' for template content."
            );
        });
    }

    // =========================================================================
    // PROPERTY 14 (Timestamp): timecreated is always a valid Unix timestamp
    // =========================================================================

    /**
     * Property 14 (Timestamp Validity): For any saved content, the timecreated
     * field must be a valid Unix timestamp - a positive integer that is close
     * to the current time (within a reasonable window).
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property14_timecreated_is_valid_unix_timestamp(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS),
            Generator\elements(...self::VALID_MOTIVATION_TARGETS)
        )->then(function (
            string $category,
            int $performance_target,
            int $motivation_target
        ) {
            $before_save = time();

            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

            $id = $repo->save(
                "Konten untuk timestamp test: {$category}.",
                [
                    'category'           => $category,
                    'performance_target' => $performance_target,
                    'motivation_target'  => $motivation_target,
                    'source'             => 'llm',
                ]
            );

            $after_save = time();

            $record = $repo->crud_read($id);

            $this->assertNotNull($record, "Property 14 violated: record not persisted.");

            $timecreated = (int) $record['timecreated'];

            // Must be a positive integer.
            $this->assertGreaterThan(
                0,
                $timecreated,
                "Property 14 violated: timecreated={$timecreated} must be a positive Unix timestamp. " .
                "category={$category}, perf={$performance_target}, motiv={$motivation_target}."
            );

            // Must be within the save window (not in the future, not too old).
            $this->assertGreaterThanOrEqual(
                $before_save,
                $timecreated,
                "Property 14 violated: timecreated={$timecreated} is before the save started " .
                "(before_save={$before_save}). Timestamp must reflect the generation time (Req 7.1)."
            );

            $this->assertLessThanOrEqual(
                $after_save,
                $timecreated,
                "Property 14 violated: timecreated={$timecreated} is after the save completed " .
                "(after_save={$after_save}). Timestamp must not be in the future."
            );
        });
    }

    // =========================================================================
    // PROPERTY 14 (Content Non-Empty): content is always a non-empty string
    // =========================================================================

    /**
     * Property 14 (Content Non-Empty): For any valid input combination, the
     * content stored in the repository must be a non-empty string - the
     * repository must not silently truncate or discard content.
     *
     * **Validates: Requirements 7.1**
     *
     * Test Strategy:
     * - Generate content strings of varying lengths (10-200 chars)
     * - Save with all valid metadata combinations
     * - Assert stored content equals the original content
     *
     * @return void
     */
    public function test_property14_content_is_stored_as_non_empty_string(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS),
            Generator\elements(...self::VALID_MOTIVATION_TARGETS),
            // Generate content length in [10, 200] characters.
            Generator\choose(10, 200)
        )->then(function (
            string $category,
            int $performance_target,
            int $motivation_target,
            int $content_length
        ) {
            // Build a content string of the specified length.
            $base    = "Motivasi akademik untuk mahasiswa dengan semangat belajar yang tinggi. ";
            $content = substr(str_repeat($base, (int) ceil($content_length / strlen($base))), 0, $content_length);

            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

            $id = $repo->save($content, [
                'category'           => $category,
                'performance_target' => $performance_target,
                'motivation_target'  => $motivation_target,
                'source'             => 'llm',
            ]);

            $record = $repo->crud_read($id);

            $this->assertNotNull($record, "Property 14 violated: record not persisted.");

            $this->assertNotEmpty(
                $record['content'],
                "Property 14 violated: stored content is empty. " .
                "Original content length={$content_length}, category={$category}."
            );

            $this->assertEquals(
                $content,
                $record['content'],
                "Property 14 violated: stored content does not match original. " .
                "The repository must preserve content exactly (Req 7.1)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 14 (Boundary): All boundary values of targets produce valid metadata
    // =========================================================================

    /**
     * Property 14 (Boundary Values): Verify that boundary values for
     * performance_target (1, 2, 3) and motivation_target (1, 2, 3) all
     * produce records with complete metadata.
     *
     * This is a deterministic test covering all 4 categories x 3 performance
     * targets x 3 motivation targets x 2 sources = 72 combinations.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property14_all_boundary_combinations_produce_complete_metadata(): void {
        $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

        foreach (self::VALID_CATEGORIES as $category) {
            foreach (self::VALID_PERFORMANCE_TARGETS as $performance_target) {
                foreach (self::VALID_MOTIVATION_TARGETS as $motivation_target) {
                    foreach (self::VALID_SOURCES as $source) {
                        $content = "Kalimat motivasi: {$category}, " .
                                   "performa={$performance_target}, " .
                                   "motivasi={$motivation_target}, " .
                                   "sumber={$source}.";

                        $id = $repo->save($content, [
                            'category'           => $category,
                            'performance_target' => $performance_target,
                            'motivation_target'  => $motivation_target,
                            'source'             => $source,
                        ]);

                        $record = $repo->crud_read($id);

                        $this->assertNotNull(
                            $record,
                            "Property 14 violated: record not persisted for " .
                            "category={$category}, perf={$performance_target}, " .
                            "motiv={$motivation_target}, source={$source}."
                        );

                        $context = "boundary: category={$category}, perf={$performance_target}, " .
                                   "motiv={$motivation_target}, source={$source}";
                        $this->assert_metadata_complete($record, $context);
                    }
                }
            }
        }
    }

    // =========================================================================
    // PROPERTY 14 (Persistence): Metadata survives a read-back cycle
    // =========================================================================

    /**
     * Property 14 (Persistence): Metadata values written via save() must be
     * retrievable via crud_read() with exactly the same values - no field
     * should be silently altered, defaulted, or lost during persistence.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property14_metadata_values_survive_read_back_cycle(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(...self::VALID_PERFORMANCE_TARGETS),
            Generator\elements(...self::VALID_MOTIVATION_TARGETS),
            Generator\elements(...self::VALID_SOURCES)
        )->then(function (
            string $category,
            int $performance_target,
            int $motivation_target,
            string $source
        ) {
            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

            $content = "Konten persistensi: {$category} perf={$performance_target} motiv={$motivation_target}.";

            $id = $repo->save($content, [
                'category'           => $category,
                'performance_target' => $performance_target,
                'motivation_target'  => $motivation_target,
                'source'             => $source,
            ]);

            $record = $repo->crud_read($id);

            $this->assertNotNull($record, "Property 14 violated: record not persisted.");

            // Verify each metadata field survives the write-read cycle exactly.
            $this->assertEquals(
                $category,
                $record['category'],
                "Property 14 violated: 'category' changed during persistence. " .
                "Written='{$category}', read='{$record['category']}'."
            );

            $this->assertEquals(
                $performance_target,
                (int) $record['performance_target'],
                "Property 14 violated: 'performance_target' changed during persistence. " .
                "Written={$performance_target}, read={$record['performance_target']}."
            );

            $this->assertEquals(
                $motivation_target,
                (int) $record['motivation_target'],
                "Property 14 violated: 'motivation_target' changed during persistence. " .
                "Written={$motivation_target}, read={$record['motivation_target']}."
            );

            $this->assertEquals(
                $source,
                $record['source'],
                "Property 14 violated: 'source' changed during persistence. " .
                "Written='{$source}', read='{$record['source']}'."
            );

            $this->assertEquals(
                $content,
                $record['content'],
                "Property 14 violated: 'content' changed during persistence. " .
                "Content must be stored exactly as provided (Req 7.1)."
            );

            // timecreated must be a positive integer after read-back.
            $this->assertGreaterThan(
                0,
                (int) $record['timecreated'],
                "Property 14 violated: 'timecreated' is not a positive integer after read-back."
            );
        });
    }

    // =========================================================================
    // PROPERTY 14 (Multiple Records): Each record has independent complete metadata
    // =========================================================================

    /**
     * Property 14 (Multiple Records Independence): When multiple Encouragement_Content
     * records are saved in sequence, each record must independently contain all
     * required metadata fields - metadata from one record must not contaminate
     * or overwrite another.
     *
     * **Validates: Requirements 7.1**
     *
     * @return void
     */
    public function test_property14_multiple_records_each_have_independent_complete_metadata(): void {
        $this->forAll(
            // Generate 2-5 records to save.
            Generator\choose(2, 5)
        )->then(function (int $record_count) {
            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

            $saved_ids      = [];
            $saved_metadata = [];

            // Save multiple records with different metadata combinations.
            for ($i = 0; $i < $record_count; $i++) {
                $category           = self::VALID_CATEGORIES[$i % count(self::VALID_CATEGORIES)];
                $performance_target = self::VALID_PERFORMANCE_TARGETS[$i % count(self::VALID_PERFORMANCE_TARGETS)];
                $motivation_target  = self::VALID_MOTIVATION_TARGETS[$i % count(self::VALID_MOTIVATION_TARGETS)];
                $source             = self::VALID_SOURCES[$i % count(self::VALID_SOURCES)];

                $content = "Konten ke-{$i}: {$category}, perf={$performance_target}, motiv={$motivation_target}.";

                $id = $repo->save($content, [
                    'category'           => $category,
                    'performance_target' => $performance_target,
                    'motivation_target'  => $motivation_target,
                    'source'             => $source,
                ]);

                $saved_ids[]      = $id;
                $saved_metadata[] = [
                    'category'           => $category,
                    'performance_target' => $performance_target,
                    'motivation_target'  => $motivation_target,
                    'source'             => $source,
                    'content'            => $content,
                ];
            }

            // Verify each record independently has complete metadata.
            foreach ($saved_ids as $idx => $id) {
                $record = $repo->crud_read($id);

                $this->assertNotNull(
                    $record,
                    "Property 14 violated: record #{$idx} (id={$id}) not persisted."
                );

                $context = "record #{$idx} of {$record_count}, id={$id}";
                $this->assert_metadata_complete($record, $context);

                // Verify metadata matches what was written for this specific record.
                $expected = $saved_metadata[$idx];

                $this->assertEquals(
                    $expected['category'],
                    $record['category'],
                    "Property 14 violated [{$context}]: category mismatch. " .
                    "Expected='{$expected['category']}', got='{$record['category']}'."
                );

                $this->assertEquals(
                    $expected['performance_target'],
                    (int) $record['performance_target'],
                    "Property 14 violated [{$context}]: performance_target mismatch."
                );

                $this->assertEquals(
                    $expected['motivation_target'],
                    (int) $record['motivation_target'],
                    "Property 14 violated [{$context}]: motivation_target mismatch."
                );

                $this->assertEquals(
                    $expected['source'],
                    $record['source'],
                    "Property 14 violated [{$context}]: source mismatch."
                );
            }
        });
    }
}
