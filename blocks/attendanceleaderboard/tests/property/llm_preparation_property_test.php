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
 * Property-based test for Property 15: Pencegahan Duplikasi Encouragement_Content.
 *
 * Verifies that MotivationSentenceRepository::check_duplicate() correctly
 * detects duplicate content within the 7-day window, and that no identical
 * content is generated for the same Learner within that window.
 *
 * **Validates: Requirements 7.7**
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
 * Property 15: Pencegahan Duplikasi Encouragement_Content.
 *
 * For any Learner and any Encouragement_Content that was already sent within
 * the last 7 days, the system must NOT generate/return identical content again
 * for that same Learner.
 *
 * Specifically:
 *   - MotivationSentenceRepository::check_duplicate() must return TRUE when
 *     the same content hash was recorded in the delivery history within 7 days.
 *   - check_duplicate() must return FALSE when the content was NOT sent within
 *     the 7-day window (e.g., sent 8+ days ago, or never sent).
 *   - The 7-day boundary is inclusive: content sent exactly 7 days ago (within
 *     the window) is still a duplicate; content sent 8+ days ago is not.
 *   - Duplicate detection is per-user: content sent to user A does not affect
 *     duplicate detection for user B.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_attendanceleaderboard
 */
class llm_preparation_property_test extends \advanced_testcase {
    use TestTrait;

    // =========================================================================
    // Constants
    // =========================================================================

    /** @var string[] All valid Encouragement_Content categories. */
    private const VALID_CATEGORIES = [
        'reinforcement',
        'achievement',
        'recovery',
        'persistence',
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
    // Helper: insert a delivery record into acmls_learner_record
    // =========================================================================

    /**
     * Insert a motivation delivery record into acmls_learner_record to simulate
     * that a specific content was sent to a user at a given time.
     *
     * @param  int    $userid       Moodle user ID.
     * @param  string $content_hash MD5 hash of the content that was delivered.
     * @param  int    $timecreated  Unix timestamp of when the content was delivered.
     * @return int                  Inserted record ID.
     */
    private function insert_delivery_record(int $userid, string $content_hash, int $timecreated): int {
        global $DB;

        $record = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = 1; // Arbitrary course ID for delivery records.
        $record->record_type      = \block_attendanceleaderboard\motivation\motivation_sentence_repository::RECORD_TYPE_DELIVERY;
        $record->source_component = 'motivation';
        $record->data_payload     = json_encode(['content_hash' => $content_hash]);
        $record->profile_version  = null;
        $record->timecreated      = $timecreated;

        return (int) $DB->insert_record('acmls_learner_record', $record);
    }

    // =========================================================================
    // PROPERTY 15 (Core): check_duplicate() returns TRUE for content sent within 7 days
    // =========================================================================

    /**
     * Property 15 (Duplicate Detection): For any userid and any content hash,
     * if a delivery record exists within the last N days, check_duplicate()
     * must return TRUE.
     *
     * **Validates: Requirements 7.7**
     *
     * Test Strategy:
     * - Generate a random userid (positive integer)
     * - Generate a random content string (non-empty)
     * - Generate days_ago in [0, 6] (within the 7-day window)
     * - Insert a delivery record with timecreated = now - days_ago * DAYSECS
     * - Call check_duplicate() with the same userid and content hash
     * - Assert the result is TRUE (duplicate detected)
     *
     * @return void
     */
    public function test_property15_check_duplicate_returns_true_for_content_within_7_days(): void {
        $this->forAll(
            // Generate days_ago in [0, 6] â€” content sent within the last 7 days.
            Generator\choose(0, 6),
            // Generate a content string index to pick from predefined content strings.
            Generator\choose(0, 3)
        )->then(function (int $days_ago, int $content_index) {
            $user = $this->getDataGenerator()->create_user();

            // Use predefined content strings to ensure non-empty, valid content.
            $content_strings = [
                'Teruslah belajar dengan penuh semangat untuk meraih prestasi terbaik.',
                'Ketekunan adalah kunci keberhasilan dalam perjalanan akademik Anda.',
                'Setiap langkah kecil membawa Anda lebih dekat ke tujuan belajar.',
                'Konsistensi dalam belajar akan membawa Anda ke tingkat yang lebih tinggi.',
            ];
            $content      = $content_strings[$content_index];
            $content_hash = md5($content);

            // Simulate content sent $days_ago days ago (within the 7-day window).
            $delivery_time = time() - ($days_ago * DAYSECS) - 60; // 60 seconds buffer.

            $this->insert_delivery_record((int) $user->id, $content_hash, $delivery_time);

            $repo   = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $result = $repo->check_duplicate((int) $user->id, $content_hash, 7);

            $this->assertTrue(
                $result,
                "Property 15 violated: check_duplicate() returned FALSE for content sent " .
                "{$days_ago} day(s) ago (within the 7-day window). " .
                "userid={$user->id}, content_hash={$content_hash}, days_ago={$days_ago}. " .
                "No content identical to what was sent in the last 7 days must be generated " .
                "for the same Learner (Req 7.7)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 15 (Negative): check_duplicate() returns FALSE for content NOT sent within 7 days
    // =========================================================================

    /**
     * Property 15 (No False Positives): For any userid and any content hash,
     * if NO delivery record exists within the last 7 days, check_duplicate()
     * must return FALSE.
     *
     * **Validates: Requirements 7.7**
     *
     * Test Strategy:
     * - Generate a random userid
     * - Generate a random content string
     * - Do NOT insert any delivery record
     * - Call check_duplicate() with the userid and content hash
     * - Assert the result is FALSE (no duplicate)
     *
     * @return void
     */
    public function test_property15_check_duplicate_returns_false_when_no_delivery_history(): void {
        $this->forAll(
            Generator\choose(0, 3)  // content index
        )->then(function (int $content_index) {
            $user = $this->getDataGenerator()->create_user();

            $content_strings = [
                'Teruslah belajar dengan penuh semangat untuk meraih prestasi terbaik.',
                'Ketekunan adalah kunci keberhasilan dalam perjalanan akademik Anda.',
                'Setiap langkah kecil membawa Anda lebih dekat ke tujuan belajar.',
                'Konsistensi dalam belajar akan membawa Anda ke tingkat yang lebih tinggi.',
            ];
            $content      = $content_strings[$content_index];
            $content_hash = md5($content);

            // No delivery record inserted â€” content was never sent to this user.
            $repo   = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $result = $repo->check_duplicate((int) $user->id, $content_hash, 7);

            $this->assertFalse(
                $result,
                "Property 15 violated: check_duplicate() returned TRUE when no delivery " .
                "history exists for this user and content. " .
                "userid={$user->id}, content_hash={$content_hash}. " .
                "Content that was never sent must not be flagged as a duplicate (Req 7.7)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 15 (Boundary): Content sent exactly 7 days ago is still a duplicate
    // =========================================================================

    /**
     * Property 15 (7-Day Boundary â€” Inclusive): Content sent exactly 7 days ago
     * (i.e., at time = now - 7 * DAYSECS + epsilon) must still be detected as
     * a duplicate (within the window).
     *
     * **Validates: Requirements 7.7**
     *
     * @return void
     */
    public function test_property15_content_sent_exactly_7_days_ago_is_duplicate(): void {
        $user         = $this->getDataGenerator()->create_user();
        $content      = 'Kalimat motivasi untuk uji batas 7 hari yang tepat.';
        $content_hash = md5($content);

        // Sent exactly 7 days ago minus 60 seconds (still within the window).
        $delivery_time = time() - (7 * DAYSECS) + 60;

        $this->insert_delivery_record((int) $user->id, $content_hash, $delivery_time);

        $repo   = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
        $result = $repo->check_duplicate((int) $user->id, $content_hash, 7);

        $this->assertTrue(
            $result,
            "Property 15 violated: check_duplicate() returned FALSE for content sent " .
            "within the 7-day boundary (7 days ago + 60 seconds). " .
            "Content sent exactly at the boundary must still be considered a duplicate (Req 7.7)."
        );
    }

    // =========================================================================
    // PROPERTY 15 (Boundary): Content sent 8+ days ago is NOT a duplicate
    // =========================================================================

    /**
     * Property 15 (Beyond 7-Day Window): Content sent 8 or more days ago must
     * NOT be detected as a duplicate â€” it is outside the 7-day window.
     *
     * **Validates: Requirements 7.7**
     *
     * Test Strategy:
     * - Generate days_ago in [8, 30] (outside the 7-day window)
     * - Insert a delivery record with timecreated = now - days_ago * DAYSECS
     * - Call check_duplicate() with the same userid and content hash
     * - Assert the result is FALSE (not a duplicate â€” outside window)
     *
     * @return void
     */
    public function test_property15_content_sent_8_or_more_days_ago_is_not_duplicate(): void {
        $this->forAll(
            // Generate days_ago in [8, 30] â€” outside the 7-day window.
            Generator\choose(8, 30)
        )->then(function (int $days_ago) {
            $user         = $this->getDataGenerator()->create_user();
            $content      = 'Kalimat motivasi untuk uji batas di luar jendela 7 hari.';
            $content_hash = md5($content);

            // Sent $days_ago days ago â€” outside the 7-day window.
            $delivery_time = time() - ($days_ago * DAYSECS) - 60;

            $this->insert_delivery_record((int) $user->id, $content_hash, $delivery_time);

            $repo   = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $result = $repo->check_duplicate((int) $user->id, $content_hash, 7);

            $this->assertFalse(
                $result,
                "Property 15 violated: check_duplicate() returned TRUE for content sent " .
                "{$days_ago} day(s) ago (outside the 7-day window). " .
                "userid={$user->id}, content_hash={$content_hash}, days_ago={$days_ago}. " .
                "Content sent more than 7 days ago must NOT be flagged as a duplicate (Req 7.7)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 15 (Isolation): Duplicate detection is per-user
    // =========================================================================

    /**
     * Property 15 (Per-User Isolation): Duplicate detection must be per-user.
     * Content sent to user A must NOT affect duplicate detection for user B,
     * even if the content is identical.
     *
     * **Validates: Requirements 7.7**
     *
     * Test Strategy:
     * - Create two users (A and B)
     * - Insert a delivery record for user A with content X
     * - Call check_duplicate() for user B with the same content X
     * - Assert the result is FALSE (user B has not received this content)
     *
     * @return void
     */
    public function test_property15_duplicate_detection_is_per_user(): void {
        $this->forAll(
            Generator\choose(0, 3)  // content index
        )->then(function (int $content_index) {
            $user_a = $this->getDataGenerator()->create_user();
            $user_b = $this->getDataGenerator()->create_user();

            $content_strings = [
                'Teruslah belajar dengan penuh semangat untuk meraih prestasi terbaik.',
                'Ketekunan adalah kunci keberhasilan dalam perjalanan akademik Anda.',
                'Setiap langkah kecil membawa Anda lebih dekat ke tujuan belajar.',
                'Konsistensi dalam belajar akan membawa Anda ke tingkat yang lebih tinggi.',
            ];
            $content      = $content_strings[$content_index];
            $content_hash = md5($content);

            // Insert delivery record for user A only.
            $delivery_time = time() - (2 * DAYSECS); // 2 days ago â€” within window.
            $this->insert_delivery_record((int) $user_a->id, $content_hash, $delivery_time);

            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

            // User A should detect duplicate.
            $result_a = $repo->check_duplicate((int) $user_a->id, $content_hash, 7);
            $this->assertTrue(
                $result_a,
                "Property 15 violated: check_duplicate() returned FALSE for user A " .
                "who received the content 2 days ago. " .
                "userid_a={$user_a->id}, content_hash={$content_hash}."
            );

            // User B should NOT detect duplicate (never received this content).
            $result_b = $repo->check_duplicate((int) $user_b->id, $content_hash, 7);
            $this->assertFalse(
                $result_b,
                "Property 15 violated: check_duplicate() returned TRUE for user B " .
                "who has never received this content. " .
                "Duplicate detection must be per-user â€” content sent to user A must not " .
                "affect user B's duplicate detection. " .
                "userid_b={$user_b->id}, content_hash={$content_hash} (Req 7.7)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 15 (Multiple Deliveries): Multiple records within window â†’ duplicate
    // =========================================================================

    /**
     * Property 15 (Multiple Deliveries): When the same content has been delivered
     * multiple times within the 7-day window, check_duplicate() must still return
     * TRUE (not fail or return incorrect results).
     *
     * **Validates: Requirements 7.7**
     *
     * @return void
     */
    public function test_property15_multiple_deliveries_within_window_still_detected_as_duplicate(): void {
        $this->forAll(
            // Number of delivery records to insert (2-5).
            Generator\choose(2, 5)
        )->then(function (int $delivery_count) {
            $user         = $this->getDataGenerator()->create_user();
            $content      = 'Kalimat motivasi yang dikirim beberapa kali dalam 7 hari.';
            $content_hash = md5($content);

            // Insert multiple delivery records within the 7-day window.
            for ($i = 0; $i < $delivery_count; $i++) {
                $delivery_time = time() - ($i * DAYSECS) - 60; // 0, 1, 2, ... days ago.
                $this->insert_delivery_record((int) $user->id, $content_hash, $delivery_time);
            }

            $repo   = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $result = $repo->check_duplicate((int) $user->id, $content_hash, 7);

            $this->assertTrue(
                $result,
                "Property 15 violated: check_duplicate() returned FALSE even though " .
                "{$delivery_count} delivery records exist within the 7-day window. " .
                "userid={$user->id}, content_hash={$content_hash}, " .
                "delivery_count={$delivery_count} (Req 7.7)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 15 (Different Content): Different content hash â†’ no duplicate
    // =========================================================================

    /**
     * Property 15 (Different Content): If a different content was sent within
     * the 7-day window, check_duplicate() must return FALSE for the new content
     * (different hash = different content = not a duplicate).
     *
     * **Validates: Requirements 7.7**
     *
     * @return void
     */
    public function test_property15_different_content_hash_is_not_duplicate(): void {
        $this->forAll(
            Generator\choose(0, 1)  // index to pick which content is "sent" and which is "new"
        )->then(function (int $idx) {
            $user = $this->getDataGenerator()->create_user();

            $content_a = 'Kalimat motivasi pertama yang sudah dikirim sebelumnya.';
            $content_b = 'Kalimat motivasi kedua yang belum pernah dikirim sebelumnya.';

            $hash_a = md5($content_a);
            $hash_b = md5($content_b);

            // Insert delivery record for content A (sent 2 days ago).
            $delivery_time = time() - (2 * DAYSECS);
            $this->insert_delivery_record((int) $user->id, $hash_a, $delivery_time);

            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

            // Content A should be detected as duplicate.
            $result_a = $repo->check_duplicate((int) $user->id, $hash_a, 7);
            $this->assertTrue(
                $result_a,
                "Property 15 violated: check_duplicate() returned FALSE for content A " .
                "which was sent 2 days ago. userid={$user->id}, hash_a={$hash_a}."
            );

            // Content B (different hash) should NOT be detected as duplicate.
            $result_b = $repo->check_duplicate((int) $user->id, $hash_b, 7);
            $this->assertFalse(
                $result_b,
                "Property 15 violated: check_duplicate() returned TRUE for content B " .
                "which was never sent to this user. " .
                "Different content must not be flagged as a duplicate. " .
                "userid={$user->id}, hash_b={$hash_b} (Req 7.7)."
            );
        });
    }

    // =========================================================================
    // PROPERTY 15 (find_relevant Exclusion): find_relevant() excludes recently sent content
    // =========================================================================

    /**
     * Property 15 (Repository Exclusion): When content has been delivered to a
     * Learner within the last 7 days, find_relevant() must NOT return that same
     * content again â€” it must be excluded from the results.
     *
     * **Validates: Requirements 7.7**
     *
     * Test Strategy:
     * - Seed the repository with exactly one sentence for a given category/target.
     * - Insert a delivery record for that sentence for user A.
     * - Call find_relevant() for user A.
     * - Assert the result is NULL (no non-duplicate content available).
     * - Call find_relevant() for user B (who has no delivery history).
     * - Assert the result is NOT NULL (content is available for user B).
     *
     * @return void
     */
    public function test_property15_find_relevant_excludes_recently_sent_content(): void {
        $this->forAll(
            Generator\elements(...self::VALID_CATEGORIES),
            Generator\elements(1, 2, 3),  // performance_target
            Generator\elements(1, 2, 3)   // motivation_target
        )->then(function (string $category, int $performance_target, int $motivation_target) {
            $user_a = $this->getDataGenerator()->create_user();
            $user_b = $this->getDataGenerator()->create_user();

            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

            // Seed exactly one sentence for this category/target combination.
            $content = "Kalimat motivasi unik untuk {$category} perf={$performance_target} motiv={$motivation_target}.";
            $repo->save($content, [
                'category'           => $category,
                'performance_target' => $performance_target,
                'motivation_target'  => $motivation_target,
                'source'             => 'template',
            ]);

            // Simulate that user A received this content 2 days ago.
            $content_hash  = md5($content);
            $delivery_time = time() - (2 * DAYSECS);
            $this->insert_delivery_record((int) $user_a->id, $content_hash, $delivery_time);

            // User A: find_relevant() should return NULL (only content was recently sent).
            $result_a = $repo->find_relevant(
                (int) $user_a->id,
                $category,
                $performance_target,
                $motivation_target
            );

            $this->assertNull(
                $result_a,
                "Property 15 violated: find_relevant() returned content for user A " .
                "even though the only available content was sent 2 days ago. " .
                "Recently sent content must be excluded from find_relevant() results. " .
                "userid_a={$user_a->id}, category={$category}, " .
                "performance_target={$performance_target}, " .
                "motivation_target={$motivation_target} (Req 7.7)."
            );

            // User B: find_relevant() should return the content (no delivery history).
            $result_b = $repo->find_relevant(
                (int) $user_b->id,
                $category,
                $performance_target,
                $motivation_target
            );

            $this->assertNotNull(
                $result_b,
                "Property 15 violated: find_relevant() returned NULL for user B " .
                "who has no delivery history. Content should be available for user B. " .
                "userid_b={$user_b->id}, category={$category}, " .
                "performance_target={$performance_target}, " .
                "motivation_target={$motivation_target} (Req 7.7)."
            );

            $this->assertEquals(
                $content,
                $result_b,
                "Property 15 violated: find_relevant() returned different content for user B. " .
                "Expected the seeded content to be returned for a user with no delivery history."
            );
        });
    }

    // =========================================================================
    // PROPERTY 15 (Boundary â€” Exactly 7 Days): Boundary test for find_relevant
    // =========================================================================

    /**
     * Property 15 (find_relevant Boundary): Content sent exactly at the boundary
     * of the 7-day window (within the window) must be excluded by find_relevant().
     * Content sent just outside the window (8+ days ago) must be included.
     *
     * **Validates: Requirements 7.7**
     *
     * @return void
     */
    public function test_property15_find_relevant_boundary_7_day_window(): void {
        $user_within  = $this->getDataGenerator()->create_user();
        $user_outside = $this->getDataGenerator()->create_user();

        $repo    = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
        $content = 'Kalimat motivasi untuk uji batas jendela 7 hari pada find_relevant.';

        // Seed the repository with this content.
        $repo->save($content, [
            'category'           => 'reinforcement',
            'performance_target' => 2,
            'motivation_target'  => 2,
            'source'             => 'template',
        ]);

        $content_hash = md5($content);

        // User "within": content sent 6 days ago (within the 7-day window).
        $time_within = time() - (6 * DAYSECS) - 60;
        $this->insert_delivery_record((int) $user_within->id, $content_hash, $time_within);

        // User "outside": content sent 8 days ago (outside the 7-day window).
        $time_outside = time() - (8 * DAYSECS) - 60;
        $this->insert_delivery_record((int) $user_outside->id, $content_hash, $time_outside);

        // User "within": find_relevant() should return NULL (content is within window).
        $result_within = $repo->find_relevant((int) $user_within->id, 'reinforcement', 2, 2);
        $this->assertNull(
            $result_within,
            "Property 15 violated: find_relevant() returned content for user_within " .
            "whose delivery was 6 days ago (within the 7-day window). " .
            "Content within the window must be excluded (Req 7.7)."
        );

        // User "outside": find_relevant() should return the content (outside window).
        $result_outside = $repo->find_relevant((int) $user_outside->id, 'reinforcement', 2, 2);
        $this->assertNotNull(
            $result_outside,
            "Property 15 violated: find_relevant() returned NULL for user_outside " .
            "whose delivery was 8 days ago (outside the 7-day window). " .
            "Content outside the window must be available again (Req 7.7)."
        );
    }

    // =========================================================================
    // PROPERTY 15 (get_delivery_history): History only includes records within window
    // =========================================================================

    /**
     * Property 15 (Delivery History Accuracy): get_delivery_history() must return
     * only the content hashes from delivery records within the specified window.
     * Records outside the window must not be included.
     *
     * **Validates: Requirements 7.7**
     *
     * Test Strategy:
     * - Insert delivery records at various times (some within, some outside window)
     * - Call get_delivery_history() with window=7
     * - Assert only hashes from within-window records are returned
     *
     * @return void
     */
    public function test_property15_get_delivery_history_only_includes_records_within_window(): void {
        $this->forAll(
            // Number of within-window records (1-3).
            Generator\choose(1, 3),
            // Number of outside-window records (1-3).
            Generator\choose(1, 3)
        )->then(function (int $within_count, int $outside_count) {
            $user = $this->getDataGenerator()->create_user();
            $repo = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

            $within_hashes  = [];
            $outside_hashes = [];

            // Insert within-window records (1-6 days ago).
            for ($i = 0; $i < $within_count; $i++) {
                $content      = "Konten dalam jendela ke-{$i}: " . uniqid('within_', true);
                $hash         = md5($content);
                $within_hashes[] = $hash;
                $delivery_time = time() - (($i + 1) * DAYSECS) + 60; // 1-3 days ago.
                $this->insert_delivery_record((int) $user->id, $hash, $delivery_time);
            }

            // Insert outside-window records (8-10 days ago).
            for ($i = 0; $i < $outside_count; $i++) {
                $content      = "Konten di luar jendela ke-{$i}: " . uniqid('outside_', true);
                $hash         = md5($content);
                $outside_hashes[] = $hash;
                $delivery_time = time() - ((8 + $i) * DAYSECS) - 60; // 8-10 days ago.
                $this->insert_delivery_record((int) $user->id, $hash, $delivery_time);
            }

            $history = $repo->get_delivery_history((int) $user->id, 7);

            // All within-window hashes must be in the history.
            foreach ($within_hashes as $hash) {
                $this->assertContains(
                    $hash,
                    $history,
                    "Property 15 violated: get_delivery_history() did not include hash={$hash} " .
                    "which was delivered within the 7-day window. " .
                    "userid={$user->id} (Req 7.7)."
                );
            }

            // All outside-window hashes must NOT be in the history.
            foreach ($outside_hashes as $hash) {
                $this->assertNotContains(
                    $hash,
                    $history,
                    "Property 15 violated: get_delivery_history() included hash={$hash} " .
                    "which was delivered outside the 7-day window (8+ days ago). " .
                    "Only records within the window must be included. " .
                    "userid={$user->id} (Req 7.7)."
                );
            }
        });
    }
}
