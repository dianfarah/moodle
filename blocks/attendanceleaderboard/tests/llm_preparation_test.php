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
 * Unit tests for ACMLS LLMPreparation.
 *
 * Tests cover:
 *  1.  anonymize_profile() removes userid, courseid, timestamps
 *  2.  anonymize_profile() rounds motivation_level to nearest 10
 *  3.  anonymize_profile() keeps performance_category, learning_style, cognitive_level
 *  4.  validate_content() returns true for valid content
 *  5.  validate_content() returns false for empty content
 *  6.  validate_content() returns false for content < 10 chars
 *  7.  validate_content() returns false for content > 500 chars
 *  8.  validate_content() returns false for content with URL
 *  9.  generate_encouragement() returns content from mock provider when available
 * 10.  generate_encouragement() falls back to template when provider throws timeout
 * 11.  generate_encouragement() falls back to template when provider throws auth error
 * 12.  fallback_to_template() returns non-empty string for any category + performance_category
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tests;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\motivation\llm_preparation;
use block_attendanceleaderboard\motivation\motivation_sentence_repository;
use block_attendanceleaderboard\motivation\providers\llm_provider_interface;
use block_attendanceleaderboard\motivation\providers\llm_auth_exception;
use block_attendanceleaderboard\motivation\providers\llm_rate_limit_exception;
use block_attendanceleaderboard\motivation\providers\llm_timeout_exception;
use block_attendanceleaderboard\profiling\learner_profile;

// ---------------------------------------------------------------------------
// Mock LLM Provider
// ---------------------------------------------------------------------------

/**
 * Mock LLM provider for unit testing.
 *
 * Allows tests to control the return value or exception thrown by generate().
 */
class mock_llm_provider implements llm_provider_interface {

    /** @var string|\Throwable The value to return or exception to throw. */
    private $response;

    /** @var bool Whether the provider reports itself as available. */
    private bool $available;

    /**
     * Constructor.
     *
     * @param string|\Throwable $response  Return value or exception to throw from generate().
     * @param bool              $available Whether is_available() returns true.
     */
    public function __construct($response = 'Teruslah belajar dengan penuh semangat akademik.', bool $available = true) {
        $this->response  = $response;
        $this->available = $available;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(string $prompt, array $options = []): string {
        if ($this->response instanceof \Throwable) {
            throw $this->response;
        }
        return (string) $this->response;
    }

    /**
     * {@inheritdoc}
     */
    public function is_available(): bool {
        return $this->available;
    }
}

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------

/**
 * Unit test class for LLMPreparation.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_attendanceleaderboard\motivation\llm_preparation
 */
class llm_preparation_test extends \advanced_testcase {

    /** @var motivation_sentence_repository Repository instance. */
    private motivation_sentence_repository $repository;

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

        $this->repository = new motivation_sentence_repository();
        $this->user       = $this->getDataGenerator()->create_user();
        $this->course     = $this->getDataGenerator()->create_course();
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Build a LearnerProfile with the given properties.
     *
     * @param  int    $performance_category 1=Low, 2=Middle, 3=High.
     * @param  float  $motivation_level     0.0–100.0.
     * @param  string $learning_style       Learning style string.
     * @param  int    $cognitive_level      1=Low, 2=Middle, 3=High.
     * @param  float  $engagement_score     0.0–100.0.
     * @return learner_profile
     */
    private function make_profile(
        int $performance_category = 1,
        float $motivation_level = 50.0,
        string $learning_style = 'visual',
        int $cognitive_level = 2,
        float $engagement_score = 65.0
    ): learner_profile {
        $profile = new learner_profile((int) $this->user->id, (int) $this->course->id);
        $profile->performance_category = $performance_category;
        $profile->motivation_level     = $motivation_level;
        $profile->learning_style       = $learning_style;
        $profile->cognitive_level      = $cognitive_level;
        $profile->engagement_score     = $engagement_score;
        $profile->behavioral_score     = 55.0;
        $profile->profile_version      = 3;
        return $profile;
    }

    // =========================================================================
    // Test 1: anonymize_profile() removes userid, courseid, timestamps
    // =========================================================================

    /**
     * Test that anonymize_profile() removes userid, courseid, and timestamp fields.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::anonymize_profile
     */
    public function test_anonymize_profile_removes_pii_fields(): void {
        $llm     = new llm_preparation(null, $this->repository);
        $profile = $this->make_profile();

        $result = $llm->anonymize_profile($profile);

        $this->assertArrayNotHasKey('userid', $result, 'userid must be removed');
        $this->assertArrayNotHasKey('courseid', $result, 'courseid must be removed');
        $this->assertArrayNotHasKey('id', $result, 'id must be removed');
        $this->assertArrayNotHasKey('last_updated', $result, 'last_updated must be removed');
        $this->assertArrayNotHasKey('created_at', $result, 'created_at must be removed');
        $this->assertArrayNotHasKey('profile_version', $result, 'profile_version must be removed');
        $this->assertArrayNotHasKey('behavioral_score', $result, 'behavioral_score must be removed');
    }

    // =========================================================================
    // Test 2: anonymize_profile() rounds motivation_level to nearest 10
    // =========================================================================

    /**
     * Test that anonymize_profile() rounds motivation_level to the nearest 10.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::anonymize_profile
     */
    public function test_anonymize_profile_rounds_motivation_level(): void {
        $llm = new llm_preparation(null, $this->repository);

        // 53 → rounds to 50.
        $profile = $this->make_profile(1, 53.0);
        $result  = $llm->anonymize_profile($profile);
        $this->assertSame(50.0, $result['motivation_level'], '53 should round to 50');

        // 75 → rounds to 80.
        $profile = $this->make_profile(1, 75.0);
        $result  = $llm->anonymize_profile($profile);
        $this->assertSame(80.0, $result['motivation_level'], '75 should round to 80');

        // 45 → rounds to 50.
        $profile = $this->make_profile(1, 45.0);
        $result  = $llm->anonymize_profile($profile);
        $this->assertSame(50.0, $result['motivation_level'], '45 should round to 50');

        // 100 → rounds to 100.
        $profile = $this->make_profile(1, 100.0);
        $result  = $llm->anonymize_profile($profile);
        $this->assertSame(100.0, $result['motivation_level'], '100 should round to 100');

        // 0 → rounds to 0.
        $profile = $this->make_profile(1, 0.0);
        $result  = $llm->anonymize_profile($profile);
        $this->assertSame(0.0, $result['motivation_level'], '0 should round to 0');
    }

    // =========================================================================
    // Test 3: anonymize_profile() keeps performance_category, learning_style, cognitive_level
    // =========================================================================

    /**
     * Test that anonymize_profile() retains non-PII fields.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::anonymize_profile
     */
    public function test_anonymize_profile_keeps_non_pii_fields(): void {
        $llm     = new llm_preparation(null, $this->repository);
        $profile = $this->make_profile(
            performance_category: 3,
            motivation_level: 80.0,
            learning_style: 'auditory',
            cognitive_level: 3,
            engagement_score: 72.6
        );

        $result = $llm->anonymize_profile($profile);

        $this->assertArrayHasKey('performance_category', $result);
        $this->assertArrayHasKey('learning_style', $result);
        $this->assertArrayHasKey('cognitive_level', $result);
        $this->assertArrayHasKey('motivation_level', $result);
        $this->assertArrayHasKey('engagement_score', $result);

        $this->assertSame(3, $result['performance_category']);
        $this->assertSame('auditory', $result['learning_style']);
        $this->assertSame(3, $result['cognitive_level']);
        // engagement_score should be rounded to nearest integer.
        $this->assertSame(73.0, $result['engagement_score']);
    }

    // =========================================================================
    // Test 4: validate_content() returns true for valid content
    // =========================================================================

    /**
     * Test that validate_content() returns true for appropriate academic content.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::validate_content
     */
    public function test_validate_content_returns_true_for_valid_content(): void {
        $llm = new llm_preparation(null, $this->repository);

        $valid_contents = [
            'Teruslah belajar dengan penuh semangat untuk meraih prestasi terbaik Anda.',
            'Ketekunan dan konsistensi adalah kunci keberhasilan akademik.',
            'Setiap usaha yang Anda lakukan hari ini adalah investasi untuk masa depan yang lebih cerah.',
            'Jangan menyerah, karena setiap langkah kecil membawa Anda lebih dekat ke tujuan.',
        ];

        foreach ($valid_contents as $content) {
            $this->assertTrue(
                $llm->validate_content($content),
                "Expected valid content to pass validation: {$content}"
            );
        }
    }

    // =========================================================================
    // Test 5: validate_content() returns false for empty content
    // =========================================================================

    /**
     * Test that validate_content() returns false for empty content.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::validate_content
     */
    public function test_validate_content_returns_false_for_empty_content(): void {
        $llm = new llm_preparation(null, $this->repository);

        $this->assertFalse($llm->validate_content(''));
    }

    // =========================================================================
    // Test 6: validate_content() returns false for content < 10 chars
    // =========================================================================

    /**
     * Test that validate_content() returns false for content shorter than 10 characters.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::validate_content
     */
    public function test_validate_content_returns_false_for_short_content(): void {
        $llm = new llm_preparation(null, $this->repository);

        // Exactly 9 characters — should fail.
        $this->assertFalse($llm->validate_content('Belajar!!'));

        // Exactly 10 characters (non-numeric, no URL/email/phone) — should pass.
        $this->assertTrue($llm->validate_content('Belajarlah'));
    }

    // =========================================================================
    // Test 7: validate_content() returns false for content > 500 chars
    // =========================================================================

    /**
     * Test that validate_content() returns false for content longer than 500 characters.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::validate_content
     */
    public function test_validate_content_returns_false_for_long_content(): void {
        $llm = new llm_preparation(null, $this->repository);

        // Exactly 500 characters — should pass.
        $content_500 = str_repeat('a', 500);
        $this->assertTrue($llm->validate_content($content_500));

        // 501 characters — should fail.
        $content_501 = str_repeat('a', 501);
        $this->assertFalse($llm->validate_content($content_501));
    }

    // =========================================================================
    // Test 8: validate_content() returns false for content with URL
    // =========================================================================

    /**
     * Test that validate_content() returns false for content containing URLs.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::validate_content
     */
    public function test_validate_content_returns_false_for_url(): void {
        $llm = new llm_preparation(null, $this->repository);

        $this->assertFalse(
            $llm->validate_content('Kunjungi https://example.com untuk belajar lebih lanjut.'),
            'Content with https:// URL should fail'
        );

        $this->assertFalse(
            $llm->validate_content('Kunjungi http://example.com untuk belajar lebih lanjut.'),
            'Content with http:// URL should fail'
        );

        $this->assertFalse(
            $llm->validate_content('Kunjungi www.example.com untuk belajar lebih lanjut.'),
            'Content with www. URL should fail'
        );
    }

    // =========================================================================
    // Test 9: generate_encouragement() returns content from mock provider
    // =========================================================================

    /**
     * Test that generate_encouragement() returns the LLM-generated content when provider is available.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::generate_encouragement
     */
    public function test_generate_encouragement_returns_provider_content(): void {
        $expected_content = 'Teruslah belajar dengan penuh semangat dan ketekunan akademik.';
        $mock_provider    = new mock_llm_provider($expected_content, true);
        $llm              = new llm_preparation($mock_provider, $this->repository);
        $profile          = $this->make_profile(2, 50.0);

        $result = $llm->generate_encouragement($profile, 'reinforcement');

        $this->assertSame($expected_content, $result);
    }

    // =========================================================================
    // Test 10: generate_encouragement() falls back to template on timeout
    // =========================================================================

    /**
     * Test that generate_encouragement() falls back to a template when provider throws timeout.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::generate_encouragement
     */
    public function test_generate_encouragement_falls_back_on_timeout(): void {
        $mock_provider = new mock_llm_provider(new llm_timeout_exception('Timeout'), true);
        $llm           = new llm_preparation($mock_provider, $this->repository);
        $profile       = $this->make_profile(1, 30.0);

        // Seed templates so fallback has content to return.
        $this->repository->seed_static_templates();

        $result = $llm->generate_encouragement($profile, 'recovery');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertGreaterThanOrEqual(10, strlen($result));
    }

    // =========================================================================
    // Test 11: generate_encouragement() falls back to template on auth error
    // =========================================================================

    /**
     * Test that generate_encouragement() falls back to a template when provider throws auth error.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::generate_encouragement
     */
    public function test_generate_encouragement_falls_back_on_auth_error(): void {
        $mock_provider = new mock_llm_provider(new llm_auth_exception('Invalid API key'), true);
        $llm           = new llm_preparation($mock_provider, $this->repository);
        $profile       = $this->make_profile(2, 55.0);

        // Seed templates so fallback has content to return.
        $this->repository->seed_static_templates();

        $result = $llm->generate_encouragement($profile, 'reinforcement');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertGreaterThanOrEqual(10, strlen($result));
    }

    // =========================================================================
    // Test 12: fallback_to_template() returns non-empty string for any combination
    // =========================================================================

    /**
     * Test that fallback_to_template() returns a non-empty string for all
     * category × performance_category combinations.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::fallback_to_template
     */
    public function test_fallback_to_template_returns_non_empty_for_all_combinations(): void {
        $llm = new llm_preparation(null, $this->repository);

        $categories            = ['recovery', 'persistence', 'reinforcement', 'achievement'];
        $performance_categories = [1, 2, 3];

        foreach ($categories as $category) {
            foreach ($performance_categories as $performance_category) {
                $result = $llm->fallback_to_template($category, $performance_category);

                $this->assertIsString($result, "Result should be a string for {$category}/{$performance_category}");
                $this->assertNotEmpty($result, "Result should not be empty for {$category}/{$performance_category}");
                $this->assertGreaterThanOrEqual(
                    10,
                    strlen($result),
                    "Result should be at least 10 chars for {$category}/{$performance_category}"
                );
            }
        }
    }

    // =========================================================================
    // Additional: validate_content() returns false for email addresses
    // =========================================================================

    /**
     * Test that validate_content() returns false for content containing email addresses.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::validate_content
     */
    public function test_validate_content_returns_false_for_email(): void {
        $llm = new llm_preparation(null, $this->repository);

        $this->assertFalse(
            $llm->validate_content('Hubungi dosen Anda di dosen@universitas.ac.id untuk bantuan.'),
            'Content with email address should fail validation'
        );
    }

    // =========================================================================
    // Additional: validate_content() returns false for phone numbers
    // =========================================================================

    /**
     * Test that validate_content() returns false for content containing phone numbers.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::validate_content
     */
    public function test_validate_content_returns_false_for_phone_number(): void {
        $llm = new llm_preparation(null, $this->repository);

        $this->assertFalse(
            $llm->validate_content('Hubungi kami di +62 812 3456 7890 untuk informasi lebih lanjut.'),
            'Content with phone number should fail validation'
        );
    }

    // =========================================================================
    // Additional: generate_encouragement() uses fallback when provider unavailable
    // =========================================================================

    /**
     * Test that generate_encouragement() uses fallback when provider is not available.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::generate_encouragement
     */
    public function test_generate_encouragement_uses_fallback_when_provider_unavailable(): void {
        $mock_provider = new mock_llm_provider('Some content', false); // is_available() = false.
        $llm           = new llm_preparation($mock_provider, $this->repository);
        $profile       = $this->make_profile(1, 30.0);

        $this->repository->seed_static_templates();

        $result = $llm->generate_encouragement($profile, 'recovery');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // =========================================================================
    // Additional: generate_encouragement() uses fallback when no provider set
    // =========================================================================

    /**
     * Test that generate_encouragement() uses fallback when no provider is configured.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::generate_encouragement
     */
    public function test_generate_encouragement_uses_fallback_when_no_provider(): void {
        $llm     = new llm_preparation(null, $this->repository);
        $profile = $this->make_profile(2, 50.0);

        $this->repository->seed_static_templates();

        $result = $llm->generate_encouragement($profile, 'reinforcement');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // =========================================================================
    // Additional: anonymize_profile() rounds engagement_score to nearest integer
    // =========================================================================

    /**
     * Test that anonymize_profile() rounds engagement_score to the nearest integer.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::anonymize_profile
     */
    public function test_anonymize_profile_rounds_engagement_score(): void {
        $llm = new llm_preparation(null, $this->repository);

        $profile = $this->make_profile(engagement_score: 72.4);
        $result  = $llm->anonymize_profile($profile);
        $this->assertSame(72.0, $result['engagement_score'], '72.4 should round to 72');

        $profile = $this->make_profile(engagement_score: 72.5);
        $result  = $llm->anonymize_profile($profile);
        $this->assertSame(73.0, $result['engagement_score'], '72.5 should round to 73');
    }

    // =========================================================================
    // Test: validate_content() returns false for content containing forbidden word 'bodoh'
    // =========================================================================

    /**
     * Test that validate_content() returns false for content containing the forbidden word 'bodoh'.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::validate_content
     */
    public function test_validate_content_returns_false_for_forbidden_word_bodoh(): void {
        $llm = new llm_preparation(null, $this->repository);

        $this->assertFalse(
            $llm->validate_content('Kamu bodoh dan tidak bisa belajar dengan baik.'),
            'Content containing "bodoh" should fail validation'
        );

        $this->assertFalse(
            $llm->validate_content('Jangan bodoh, teruslah belajar.'),
            'Content containing "bodoh" should fail validation regardless of context'
        );
    }

    // =========================================================================
    // Test: generate_encouragement() saves content to repository
    // =========================================================================

    /**
     * Test that generate_encouragement() saves the generated content to the repository.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::generate_encouragement
     */
    public function test_generate_encouragement_saves_content_to_repository(): void {
        global $DB;

        $expected_content = 'Teruslah berjuang dengan semangat akademik yang tinggi untuk meraih prestasi.';
        $mock_provider    = new mock_llm_provider($expected_content, true);
        $llm              = new llm_preparation($mock_provider, $this->repository);
        $profile          = $this->make_profile(2, 50.0);

        // Count records before generation.
        $count_before = $DB->count_records('acmls_motivation_sentence');

        $result = $llm->generate_encouragement($profile, 'reinforcement');

        // Count records after generation.
        $count_after = $DB->count_records('acmls_motivation_sentence');

        $this->assertSame($expected_content, $result, 'Should return the generated content');
        $this->assertGreaterThan($count_before, $count_after, 'A new record should be saved to the repository');

        // Verify the saved content matches.
        $saved = $DB->get_record('acmls_motivation_sentence', ['content' => $expected_content]);
        $this->assertNotFalse($saved, 'The generated content should be findable in the repository');
        $this->assertSame('reinforcement', $saved->category);
    }

    // =========================================================================
    // Test: generate_encouragement() falls back to template when provider throws RuntimeException
    // =========================================================================

    /**
     * Test that generate_encouragement() falls back to a template when provider throws a generic RuntimeException.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::generate_encouragement
     */
    public function test_generate_encouragement_falls_back_on_runtime_exception(): void {
        $mock_provider = new mock_llm_provider(new \RuntimeException('Unexpected error'), true);
        $llm           = new llm_preparation($mock_provider, $this->repository);
        $profile       = $this->make_profile(1, 30.0);

        // Seed templates so fallback has content to return.
        $this->repository->seed_static_templates();

        $result = $llm->generate_encouragement($profile, 'recovery');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertGreaterThanOrEqual(10, strlen($result));
    }

    // =========================================================================
    // Test: fallback_to_template() returns null when no template exists
    // =========================================================================

    /**
     * Test that fallback_to_template() returns a non-null fallback when no template exists in DB.
     *
     * Note: The implementation always returns a non-null string — it falls back to a hardcoded
     * default sentence when no template is found in the repository. This ensures the system
     * never returns null and always provides motivational content to the learner.
     *
     * @covers \block_attendanceleaderboard\motivation\llm_preparation::fallback_to_template
     */
    public function test_fallback_to_template_returns_default_when_no_template_exists(): void {
        $llm = new llm_preparation(null, $this->repository);

        // Do NOT seed templates — repository is empty.
        // The implementation should return the hardcoded DEFAULT_FALLBACK string.
        $result = $llm->fallback_to_template('recovery', 1);

        $this->assertIsString($result, 'Should return a string even when no template exists');
        $this->assertNotEmpty($result, 'Should return non-empty content even when no template exists');
        $this->assertGreaterThanOrEqual(10, strlen($result), 'Fallback content should be at least 10 chars');
    }
}
