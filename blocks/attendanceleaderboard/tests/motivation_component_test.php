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
 * Unit tests for ACMLS MotivationComponent and MotivationSentenceRepository.
 *
 * Tests cover:
 *  1.  find_relevant() returns content matching category + performance_target
 *  2.  find_relevant() returns null when no matching content
 *  3.  check_duplicate() returns true when same hash sent in last 7 days
 *  4.  check_duplicate() returns false when hash not in history
 *  5.  check_duplicate() returns false when content sent >7 days ago
 *  6.  check_motivation_threshold() returns false when motivation above threshold
 *  7.  check_motivation_threshold() returns false when no historical data
 *  8.  record_learner_response() inserts record into acmls_learner_record
 *  9.  seed_static_templates() inserts 12 template records
 * 10.  seed_static_templates() is idempotent
 * 11.  crud_create() inserts record
 * 12.  crud_read() returns correct data
 * 13.  crud_update() updates fields
 * 14.  crud_delete() soft-deletes (is_active=0)
 * 15.  get_intervention_category() for all mapping combinations
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tests;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\motivation\motivation_sentence_repository;
use block_attendanceleaderboard\motivation\motivation_component;
use block_attendanceleaderboard\profiling\learner_profile;

/**
 * Unit test class for MotivationComponent and MotivationSentenceRepository.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_attendanceleaderboard\motivation\motivation_sentence_repository
 * @covers     \block_attendanceleaderboard\motivation\motivation_component
 */
class motivation_component_test extends \advanced_testcase {

    /** @var motivation_sentence_repository Repository under test. */
    private motivation_sentence_repository $repository;

    /** @var motivation_component Component under test. */
    private motivation_component $component;

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
        $this->component  = new motivation_component($this->repository);
        $this->user       = $this->getDataGenerator()->create_user();
        $this->course     = $this->getDataGenerator()->create_course();
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Build a LearnerProfile with the given performance_category and motivation_level.
     *
     * @param  int   $performance_category 1=Low, 2=Middle, 3=High.
     * @param  float $motivation_level     0.0-100.0.
     * @return learner_profile
     */
    private function make_profile(int $performance_category, float $motivation_level): learner_profile {
        $profile = new learner_profile((int) $this->user->id, (int) $this->course->id);
        $profile->performance_category = $performance_category;
        $profile->motivation_level     = $motivation_level;
        $profile->profile_version      = 1;
        return $profile;
    }

    /**
     * Insert a motivation delivery record into acmls_learner_record.
     *
     * @param  int    $userid       User ID.
     * @param  int    $courseid     Course ID.
     * @param  string $content_hash MD5 hash of the content.
     * @param  int    $timecreated  Unix timestamp.
     * @return int                  Inserted record ID.
     */
    private function insert_delivery_record(
        int $userid,
        int $courseid,
        string $content_hash,
        int $timecreated
    ): int {
        global $DB;

        $payload = json_encode(['content_hash' => $content_hash, 'category' => 'recovery']);

        $record                   = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->record_type      = 'motivation_delivery';
        $record->source_component = 'motivation';
        $record->data_payload     = $payload;
        $record->profile_version  = 1;
        $record->timecreated      = $timecreated;

        return (int) $DB->insert_record('acmls_learner_record', $record);
    }

    /**
     * Insert a profile snapshot record into acmls_learner_record.
     *
     * @param  int   $userid           User ID.
     * @param  int   $courseid         Course ID.
     * @param  float $motivation_level Motivation level value.
     * @param  int   $timecreated      Unix timestamp.
     * @return int                     Inserted record ID.
     */
    private function insert_snapshot_record(
        int $userid,
        int $courseid,
        float $motivation_level,
        int $timecreated
    ): int {
        global $DB;

        $payload = json_encode([
            'motivation_level'     => $motivation_level,
            'performance_category' => 1,
            'cognitive_level'      => 1,
        ]);

        $record                   = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->record_type      = 'profile_snapshot';
        $record->source_component = 'profiling';
        $record->data_payload     = $payload;
        $record->profile_version  = 1;
        $record->timecreated      = $timecreated;

        return (int) $DB->insert_record('acmls_learner_record', $record);
    }

    /**
     * Insert a motivation sentence into acmls_motivation_sentence.
     *
     * @param  string $category           Content category.
     * @param  int    $performance_target Performance target (1-3).
     * @param  int    $motivation_target  Motivation target (1-3).
     * @param  string $content            Content text.
     * @return int                        Inserted record ID.
     */
    private function insert_sentence(
        string $category,
        int $performance_target,
        int $motivation_target,
        string $content
    ): int {
        return $this->repository->crud_create([
            'category'           => $category,
            'performance_target' => $performance_target,
            'motivation_target'  => $motivation_target,
            'content'            => $content,
            'language'           => 'id',
            'source'             => 'template',
        ]);
    }

    // =========================================================================
    // Test 1: find_relevant() returns content matching category + performance_target
    // =========================================================================

    /**
     * Test that find_relevant() returns content matching category and performance_target.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::find_relevant
     */
    public function test_find_relevant_returns_matching_content(): void {
        $content = 'Teruslah berjuang, kamu pasti bisa!';
        $this->insert_sentence('recovery', 1, 1, $content);

        $result = $this->repository->find_relevant(
            (int) $this->user->id,
            'recovery',
            1,
            1,
            'id'
        );

        $this->assertSame($content, $result);
    }

    // =========================================================================
    // Test 2: find_relevant() returns null when no matching content exists
    // =========================================================================

    /**
     * Test that find_relevant() returns null when no matching sentence exists.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::find_relevant
     */
    public function test_find_relevant_returns_null_when_no_match(): void {
        // Insert a sentence for a different category.
        $this->insert_sentence('achievement', 3, 3, 'Luar biasa!');

        $result = $this->repository->find_relevant(
            (int) $this->user->id,
            'recovery',   // different category — no match.
            1,
            1,
            'id'
        );

        $this->assertNull($result);
    }

    // =========================================================================
    // Test 3: check_duplicate() returns true when same hash sent in last 7 days
    // =========================================================================

    /**
     * Test that check_duplicate() returns true when the same hash was sent within 7 days.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::check_duplicate
     */
    public function test_check_duplicate_returns_true_for_recent_delivery(): void {
        $content_hash = md5('Some motivational content');

        // Insert a delivery record from 1 day ago (within 7 days).
        $this->insert_delivery_record(
            (int) $this->user->id,
            (int) $this->course->id,
            $content_hash,
            time() - DAYSECS
        );

        $result = $this->repository->check_duplicate(
            (int) $this->user->id,
            $content_hash,
            7
        );

        $this->assertTrue($result);
    }

    /**
     * Test that duplicate delivery payload rows do not break history retrieval.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::get_delivery_history
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::check_duplicate
     */
    public function test_get_delivery_history_handles_duplicate_payload_rows(): void {
        $content_hash = md5('Repeated motivational content');

        $this->insert_delivery_record(
            (int) $this->user->id,
            (int) $this->course->id,
            $content_hash,
            time() - DAYSECS
        );
        $this->insert_delivery_record(
            (int) $this->user->id,
            (int) $this->course->id,
            $content_hash,
            time() - (DAYSECS - 60)
        );

        $history = $this->repository->get_delivery_history((int) $this->user->id, 7);

        $this->assertCount(2, $history);
        $this->assertSame($content_hash, $history[0]);
        $this->assertSame($content_hash, $history[1]);
        $this->assertTrue($this->repository->check_duplicate((int) $this->user->id, $content_hash, 7));
    }

    // =========================================================================
    // Test 4: check_duplicate() returns false when hash not in history
    // =========================================================================

    /**
     * Test that check_duplicate() returns false when the hash has never been sent.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::check_duplicate
     */
    public function test_check_duplicate_returns_false_when_not_in_history(): void {
        $content_hash = md5('Content that was never sent');

        $result = $this->repository->check_duplicate(
            (int) $this->user->id,
            $content_hash,
            7
        );

        $this->assertFalse($result);
    }

    // =========================================================================
    // Test 5: check_duplicate() returns false when content sent >7 days ago (boundary)
    // =========================================================================

    /**
     * Test that check_duplicate() returns false when the delivery was more than 7 days ago.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::check_duplicate
     */
    public function test_check_duplicate_returns_false_for_old_delivery(): void {
        $content_hash = md5('Old motivational content');

        // Insert a delivery record from 8 days ago (outside the 7-day window).
        $this->insert_delivery_record(
            (int) $this->user->id,
            (int) $this->course->id,
            $content_hash,
            time() - (8 * DAYSECS)
        );

        $result = $this->repository->check_duplicate(
            (int) $this->user->id,
            $content_hash,
            7
        );

        $this->assertFalse($result);
    }

    // =========================================================================
    // Test 6: check_motivation_threshold() returns false when motivation above threshold
    // =========================================================================

    /**
     * Test that check_motivation_threshold() returns false when motivation is above threshold.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_component::check_motivation_threshold
     */
    public function test_check_motivation_threshold_returns_false_when_above_threshold(): void {
        // motivation_level = 60 is above the default threshold of 40.
        $profile = $this->make_profile(1, 60.0);

        $result = $this->component->check_motivation_threshold($profile);

        $this->assertFalse($result);
    }

    // =========================================================================
    // Test 7: check_motivation_threshold() returns false when no historical snapshots
    // =========================================================================

    /**
     * Test that check_motivation_threshold() returns false when no historical snapshots exist.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_component::check_motivation_threshold
     */
    public function test_check_motivation_threshold_returns_false_when_no_snapshots(): void {
        // motivation_level = 20 is below threshold, but no historical snapshots exist.
        $profile = $this->make_profile(1, 20.0);

        $result = $this->component->check_motivation_threshold($profile);

        $this->assertFalse($result);
    }

    // =========================================================================
    // Test 8: record_learner_response() inserts correct record into acmls_learner_record
    // =========================================================================

    /**
     * Test that record_learner_response() inserts a record with correct fields.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_component::record_learner_response
     */
    public function test_record_learner_response_inserts_record(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $this->component->record_learner_response($userid, $courseid, 1);

        $records = $DB->get_records('acmls_learner_record', [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'record_type' => 'motivation_response',
        ]);

        $this->assertCount(1, $records);

        $record  = reset($records);
        $payload = json_decode($record->data_payload, true);

        $this->assertSame(1, $payload['response']);
        $this->assertSame('motivation', $record->source_component);
    }

    /**
     * Test that record_emotional_feedback() stores popup feedback in both tables.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_component::record_emotional_feedback
     */
    public function test_record_emotional_feedback_persists_feedback(): void {
        global $DB;

        $userid = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $sentenceid = $this->insert_sentence('recovery', 1, 1, 'Tetap semangat, kamu tidak sendirian.');

        $feedbackid = $this->component->record_emotional_feedback($userid, $courseid, [
            'sentenceid' => $sentenceid,
            'category' => 'recovery',
            'source' => 'gemini',
            'feeling_key' => 'motivated',
            'reflection_note' => 'Pesannya membantu saya kembali fokus.',
            'message_content' => 'Tetap semangat, kamu tidak sendirian.',
        ]);

        $feedback = $DB->get_record('acmls_motivation_feedback', ['id' => $feedbackid], '*', MUST_EXIST);
        $this->assertSame($userid, (int) $feedback->userid);
        $this->assertSame($courseid, (int) $feedback->courseid);
        $this->assertSame($sentenceid, (int) $feedback->sentenceid);
        $this->assertSame('gemini', $feedback->source);
        $this->assertSame('motivated', $feedback->feeling_key);
        $this->assertSame(4, (int) $feedback->feeling_score);
        $this->assertSame('Pesannya membantu saya kembali fokus.', $feedback->reflection_note);

        $historycount = $DB->count_records('acmls_learner_record', [
            'userid' => $userid,
            'courseid' => $courseid,
            'record_type' => 'motivation_feedback',
        ]);
        $responsecount = $DB->count_records('acmls_learner_record', [
            'userid' => $userid,
            'courseid' => $courseid,
            'record_type' => 'motivation_response',
        ]);

        $this->assertSame(1, $historycount);
        $this->assertSame(1, $responsecount);
    }

    // =========================================================================
    // Test 9: seed_static_templates() inserts exactly 12 template records
    // =========================================================================

    /**
     * Test that seed_static_templates() inserts exactly 12 template records.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::seed_static_templates
     */
    public function test_seed_static_templates_inserts_12_records(): void {
        global $DB;

        $this->repository->seed_static_templates();

        $count = $DB->count_records('acmls_motivation_sentence', ['source' => 'template']);

        $this->assertSame(12, $count);
    }

    // =========================================================================
    // Test 10: seed_static_templates() is idempotent (calling twice doesn't duplicate)
    // =========================================================================

    /**
     * Test that seed_static_templates() is idempotent — calling it twice doesn't duplicate records.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::seed_static_templates
     */
    public function test_seed_static_templates_is_idempotent(): void {
        global $DB;

        $this->repository->seed_static_templates();
        $this->repository->seed_static_templates();

        $count = $DB->count_records('acmls_motivation_sentence', ['source' => 'template']);

        $this->assertSame(12, $count);
    }

    // =========================================================================
    // Test 11: crud_create() inserts record
    // =========================================================================

    /**
     * Test that crud_create() inserts a record and returns a valid ID.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::crud_create
     */
    public function test_crud_create_inserts_record(): void {
        global $DB;

        $id = $this->repository->crud_create([
            'category'           => 'persistence',
            'performance_target' => 2,
            'motivation_target'  => 2,
            'content'            => 'Teruslah belajar dengan tekun.',
            'language'           => 'id',
            'source'             => 'template',
        ]);

        $this->assertGreaterThan(0, $id);
        $this->assertTrue($DB->record_exists('acmls_motivation_sentence', ['id' => $id]));
    }

    // =========================================================================
    // Test 12: crud_read() returns correct data
    // =========================================================================

    /**
     * Test that crud_read() returns the correct record data.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::crud_read
     */
    public function test_crud_read_returns_correct_data(): void {
        $content = 'Konsistensi adalah kunci sukses.';
        $id = $this->repository->crud_create([
            'category'           => 'reinforcement',
            'performance_target' => 2,
            'motivation_target'  => 2,
            'content'            => $content,
            'language'           => 'id',
            'source'             => 'template',
        ]);

        $record = $this->repository->crud_read($id);

        $this->assertIsArray($record);
        $this->assertSame($id, (int) $record['id']);
        $this->assertSame($content, $record['content']);
        $this->assertSame('reinforcement', $record['category']);
        $this->assertSame(2, (int) $record['performance_target']);
    }

    /**
     * Test that crud_read() returns null for a non-existent ID.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::crud_read
     */
    public function test_crud_read_returns_null_for_nonexistent_id(): void {
        $result = $this->repository->crud_read(999999);
        $this->assertNull($result);
    }

    // =========================================================================
    // Test 13: crud_update() updates fields
    // =========================================================================

    /**
     * Test that crud_update() updates the specified fields.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::crud_update
     */
    public function test_crud_update_updates_fields(): void {
        $id = $this->repository->crud_create([
            'category'           => 'recovery',
            'performance_target' => 1,
            'motivation_target'  => 1,
            'content'            => 'Original content.',
            'language'           => 'id',
            'source'             => 'template',
        ]);

        $updated_content = 'Updated motivational content.';
        $result = $this->repository->crud_update($id, ['content' => $updated_content]);

        $this->assertTrue($result);

        $record = $this->repository->crud_read($id);
        $this->assertSame($updated_content, $record['content']);
    }

    /**
     * Test that crud_update() returns false for a non-existent ID.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::crud_update
     */
    public function test_crud_update_returns_false_for_nonexistent_id(): void {
        $result = $this->repository->crud_update(999999, ['content' => 'New content']);
        $this->assertFalse($result);
    }

    // =========================================================================
    // Test 14: crud_delete() soft-deletes (is_active=0)
    // =========================================================================

    /**
     * Test that crud_delete() performs a soft delete by setting is_active=0.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::crud_delete
     */
    public function test_crud_delete_soft_deletes_record(): void {
        $id = $this->repository->crud_create([
            'category'           => 'achievement',
            'performance_target' => 3,
            'motivation_target'  => 3,
            'content'            => 'Luar biasa! Anda telah mencapai puncak.',
            'language'           => 'id',
            'source'             => 'template',
        ]);

        $result = $this->repository->crud_delete($id);

        $this->assertTrue($result);

        $record = $this->repository->crud_read($id);
        $this->assertNotNull($record, 'Record should still exist after soft delete');
        $this->assertSame(0, (int) $record['is_active'], 'is_active should be 0 after soft delete');
    }

    /**
     * Test that crud_delete() returns false for a non-existent ID.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::crud_delete
     */
    public function test_crud_delete_returns_false_for_nonexistent_id(): void {
        $result = $this->repository->crud_delete(999999);
        $this->assertFalse($result);
    }

    // =========================================================================
    // Test 15: get_intervention_category() for all mapping combinations
    // =========================================================================

    /**
     * Test get_intervention_category() for all intervention mapping combinations.
     *
     * Mapping table:
     * | Performance_Category | Motivation_Level  | Category      |
     * |----------------------|-------------------|---------------|
     * | Low (1)              | Low (<40)         | recovery      |
     * | Low (1)              | Middle (40-69)    | persistence   |
     * | Middle (2)           | Low (<40)         | recovery      |
     * | Middle (2)           | Middle (40-69)    | reinforcement |
     * | High (3)             | Any               | achievement   |
     * | Any                  | High (>=70)       | achievement   |
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_component::get_intervention_category
     */
    public function test_get_intervention_category_all_combinations(): void {
        // Low performance + Low motivation → recovery.
        $this->assertSame(
            'recovery',
            motivation_component::get_intervention_category(1, 20.0),
            'Low performance + Low motivation should be recovery'
        );

        // Low performance + Middle motivation → persistence.
        $this->assertSame(
            'persistence',
            motivation_component::get_intervention_category(1, 50.0),
            'Low performance + Middle motivation should be persistence'
        );

        // Middle performance + Low motivation → recovery.
        $this->assertSame(
            'recovery',
            motivation_component::get_intervention_category(2, 30.0),
            'Middle performance + Low motivation should be recovery'
        );

        // Middle performance + Middle motivation → reinforcement.
        $this->assertSame(
            'reinforcement',
            motivation_component::get_intervention_category(2, 55.0),
            'Middle performance + Middle motivation should be reinforcement'
        );

        // High performance + Low motivation → achievement.
        $this->assertSame(
            'achievement',
            motivation_component::get_intervention_category(3, 20.0),
            'High performance + Low motivation should be achievement'
        );

        // High performance + Middle motivation → achievement.
        $this->assertSame(
            'achievement',
            motivation_component::get_intervention_category(3, 55.0),
            'High performance + Middle motivation should be achievement'
        );

        // Any performance + High motivation (>=70) → achievement.
        $this->assertSame(
            'achievement',
            motivation_component::get_intervention_category(1, 75.0),
            'Low performance + High motivation should be achievement'
        );

        $this->assertSame(
            'achievement',
            motivation_component::get_intervention_category(2, 80.0),
            'Middle performance + High motivation should be achievement'
        );

        // Boundary: motivation exactly at 40 (Middle threshold) → not low.
        $this->assertSame(
            'persistence',
            motivation_component::get_intervention_category(1, 40.0),
            'Low performance + motivation exactly at 40 should be persistence'
        );

        // Boundary: motivation exactly at 70 (High threshold) → achievement.
        $this->assertSame(
            'achievement',
            motivation_component::get_intervention_category(1, 70.0),
            'Any performance + motivation exactly at 70 should be achievement'
        );
    }

    // =========================================================================
    // Additional: find_relevant() excludes recently delivered content
    // =========================================================================

    /**
     * Test that find_relevant() excludes content that was recently delivered to the user.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_sentence_repository::find_relevant
     */
    public function test_find_relevant_excludes_recently_delivered_content(): void {
        $content1 = 'Jangan menyerah, teruslah berjuang!';
        $content2 = 'Kamu pasti bisa mencapai tujuanmu!';

        $this->insert_sentence('recovery', 1, 1, $content1);
        $this->insert_sentence('recovery', 1, 1, $content2);

        // Mark content1 as recently delivered.
        $this->insert_delivery_record(
            (int) $this->user->id,
            (int) $this->course->id,
            md5($content1),
            time() - DAYSECS
        );

        $result = $this->repository->find_relevant(
            (int) $this->user->id,
            'recovery',
            1,
            1,
            'id'
        );

        // Should return content2 since content1 was recently delivered.
        $this->assertSame($content2, $result);
    }

    // =========================================================================
    // Additional: check_motivation_threshold() returns true when consistently low
    // =========================================================================

    /**
     * Test that check_motivation_threshold() returns true when motivation is consistently
     * below threshold for the required number of consecutive days.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_component::check_motivation_threshold
     */
    public function test_check_motivation_threshold_returns_true_when_consistently_low(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Insert 3 snapshots within the last 3 days, all with low motivation.
        $this->insert_snapshot_record($userid, $courseid, 20.0, time() - DAYSECS);
        $this->insert_snapshot_record($userid, $courseid, 25.0, time() - (2 * DAYSECS));
        $this->insert_snapshot_record($userid, $courseid, 15.0, time() - (3 * DAYSECS));

        // Profile also has low motivation.
        $profile = $this->make_profile(1, 20.0);

        $result = $this->component->check_motivation_threshold($profile);

        $this->assertTrue($result);
    }

    /**
     * Test that identical snapshot payload rows do not break threshold evaluation.
     *
     * @covers \block_attendanceleaderboard\motivation\motivation_component::check_motivation_threshold
     */
    public function test_check_motivation_threshold_handles_duplicate_snapshot_payloads(): void {
        $userid = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $this->insert_snapshot_record($userid, $courseid, 20.0, time() - DAYSECS);
        $this->insert_snapshot_record($userid, $courseid, 20.0, time() - (2 * DAYSECS));
        $this->insert_snapshot_record($userid, $courseid, 20.0, time() - (3 * DAYSECS));

        $profile = $this->make_profile(1, 20.0);

        $this->assertTrue($this->component->check_motivation_threshold($profile));
    }
}
