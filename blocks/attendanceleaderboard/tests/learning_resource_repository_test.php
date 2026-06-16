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
 * Unit tests for ACMLS LearningResourceRepository.
 *
 * Tests cover:
 *  1.  find_by_profile() returns difficulty=1 resources for Low performance learner.
 *  2.  find_by_profile() returns difficulty=2,3 resources for High performance learner.
 *  3.  find_by_profile() filters by learning_style when matching resources exist.
 *  4.  find_by_profile() falls back to difficulty-only when no style match.
 *  5.  add_resource() inserts correct record into DB.
 *  6.  update_resource() updates fields correctly.
 *  7.  delete_resource() marks resource as inactive (soft delete).
 *  8.  record_access() increments access_count.
 *  9.  get_effectiveness_data() returns correct structure.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tests;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\repository\learning_resource_repository;
use block_attendanceleaderboard\profiling\learner_profile;

/**
 * Unit test class for LearningResourceRepository.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_attendanceleaderboard\repository\learning_resource_repository
 */
class learning_resource_repository_test extends \advanced_testcase {

    /** @var learning_resource_repository System under test. */
    private learning_resource_repository $repo;

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

        $this->repo   = new learning_resource_repository();
        $this->user   = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
    }

    // =========================================================================
    // Helper: insert a resource directly
    // =========================================================================

    /**
     * Insert a learning resource record directly into the DB for testing.
     *
     * @param  int    $courseid
     * @param  int    $cmid
     * @param  string $title
     * @param  int    $difficulty_level
     * @param  array  $learning_styles
     * @param  float  $effectiveness_score
     * @param  int    $access_count
     * @param  int    $is_active
     * @return int    Inserted record ID.
     */
    private function insert_resource(
        int $courseid,
        int $cmid,
        string $title,
        int $difficulty_level,
        array $learning_styles = ['reading'],
        float $effectiveness_score = 0.0,
        int $access_count = 0,
        int $is_active = 1
    ): int {
        global $DB;

        $record = new \stdClass();
        $record->courseid           = $courseid;
        $record->cmid               = $cmid;
        $record->title              = $title;
        $record->resource_type      = 'resource';
        $record->difficulty_level   = $difficulty_level;
        $record->topic_tags         = json_encode([]);
        $record->learning_styles    = json_encode($learning_styles);
        $record->access_count       = $access_count;
        $record->avg_rating         = null;
        $record->effectiveness_score = $effectiveness_score;
        $record->is_active          = $is_active;
        $record->timecreated        = time();
        $record->timemodified       = time();

        return (int) $DB->insert_record('acmls_learning_resource', $record);
    }

    /**
     * Build a LearnerProfile with given performance_category and learning_style.
     *
     * @param  int    $performance_category
     * @param  string $learning_style
     * @return learner_profile
     */
    private function make_profile(int $performance_category, string $learning_style): learner_profile {
        $profile = new learner_profile((int) $this->user->id, (int) $this->course->id);
        $profile->performance_category = $performance_category;
        $profile->learning_style       = $learning_style;
        return $profile;
    }

    // =========================================================================
    // Test 1: find_by_profile() — Low performance → difficulty=1 only
    // =========================================================================

    /**
     * Test 1: find_by_profile() returns only difficulty=1 resources for Low learner.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::find_by_profile
     */
    public function test_find_by_profile_low_performance_returns_difficulty_1(): void {
        $courseid = (int) $this->course->id;

        // Insert resources at different difficulty levels.
        $id1 = $this->insert_resource($courseid, 101, 'Basic Doc', 1, ['reading']);
        $id2 = $this->insert_resource($courseid, 102, 'Intermediate Doc', 2, ['reading']);
        $id3 = $this->insert_resource($courseid, 103, 'Advanced Doc', 3, ['reading']);

        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, learner_profile::STYLE_READING);
        $results = $this->repo->find_by_profile($profile, 10);

        $returned_ids = array_map('intval', array_column($results, 'id'));

        $this->assertContains($id1, $returned_ids,
            'Low performance should return difficulty=1 resource.');
        $this->assertNotContains($id2, $returned_ids,
            'Low performance should NOT return difficulty=2 resource.');
        $this->assertNotContains($id3, $returned_ids,
            'Low performance should NOT return difficulty=3 resource.');
    }

    // =========================================================================
    // Test 2: find_by_profile() — High performance → difficulty=2,3
    // =========================================================================

    /**
     * Test 2: find_by_profile() returns difficulty=2 and 3 resources for High learner.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::find_by_profile
     */
    public function test_find_by_profile_high_performance_returns_difficulty_2_and_3(): void {
        $courseid = (int) $this->course->id;

        $id1 = $this->insert_resource($courseid, 201, 'Basic Doc', 1, ['reading']);
        $id2 = $this->insert_resource($courseid, 202, 'Intermediate Doc', 2, ['reading']);
        $id3 = $this->insert_resource($courseid, 203, 'Advanced Doc', 3, ['reading']);

        $profile = $this->make_profile(learner_profile::PERFORMANCE_HIGH, learner_profile::STYLE_READING);
        $results = $this->repo->find_by_profile($profile, 10);

        $returned_ids = array_map('intval', array_column($results, 'id'));

        $this->assertNotContains($id1, $returned_ids,
            'High performance should NOT return difficulty=1 resource.');
        $this->assertContains($id2, $returned_ids,
            'High performance should return difficulty=2 resource.');
        $this->assertContains($id3, $returned_ids,
            'High performance should return difficulty=3 resource.');
    }

    // =========================================================================
    // Test 3: find_by_profile() — filters by learning_style when match exists
    // =========================================================================

    /**
     * Test 3: find_by_profile() prefers resources matching the learner's learning_style.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::find_by_profile
     */
    public function test_find_by_profile_filters_by_learning_style(): void {
        $courseid = (int) $this->course->id;

        // Two difficulty=1 resources: one auditory, one reading.
        $id_auditory = $this->insert_resource($courseid, 301, 'Video Lecture', 1, ['auditory']);
        $id_reading  = $this->insert_resource($courseid, 302, 'Text Document', 1, ['reading']);

        // Profile: Low performance, auditory style.
        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, learner_profile::STYLE_AUDITORY);
        $results = $this->repo->find_by_profile($profile, 10);

        $returned_ids = array_map('intval', array_column($results, 'id'));

        $this->assertContains($id_auditory, $returned_ids,
            'Should return auditory resource when learner style is auditory.');
        $this->assertNotContains($id_reading, $returned_ids,
            'Should NOT return reading resource when auditory match exists.');
    }

    // =========================================================================
    // Test 4: find_by_profile() — falls back to difficulty-only when no style match
    // =========================================================================

    /**
     * Test 4: find_by_profile() falls back to all difficulty-matched resources
     * when no resource matches the learner's learning_style.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::find_by_profile
     */
    public function test_find_by_profile_fallback_when_no_style_match(): void {
        $courseid = (int) $this->course->id;

        // Only reading resources at difficulty=1, but learner wants kinesthetic.
        $id1 = $this->insert_resource($courseid, 401, 'Text A', 1, ['reading']);
        $id2 = $this->insert_resource($courseid, 402, 'Text B', 1, ['reading']);

        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, learner_profile::STYLE_KINESTHETIC);
        $results = $this->repo->find_by_profile($profile, 10);

        $returned_ids = array_map('intval', array_column($results, 'id'));

        // Should fall back and return both reading resources.
        $this->assertContains($id1, $returned_ids,
            'Fallback should return difficulty-matched resource even without style match.');
        $this->assertContains($id2, $returned_ids,
            'Fallback should return all difficulty-matched resources.');
    }

    // =========================================================================
    // Test 5: add_resource() — inserts correct record into DB
    // =========================================================================

    /**
     * Test 5: add_resource() inserts a record with all correct fields.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::add_resource
     */
    public function test_add_resource_inserts_correct_record(): void {
        global $DB;

        $courseid = (int) $this->course->id;
        $cmid     = 999;

        $metadata = [
            'courseid'         => $courseid,
            'title'            => 'Test Resource',
            'resource_type'    => 'video',
            'difficulty_level' => 2,
            'topic_tags'       => ['math', 'algebra'],
            'learning_styles'  => ['auditory', 'visual'],
        ];

        $before = time();
        $id = $this->repo->add_resource($cmid, $metadata);
        $after = time();

        $this->assertGreaterThan(0, $id, 'add_resource() should return a positive ID.');

        $record = $DB->get_record('acmls_learning_resource', ['id' => $id]);
        $this->assertNotFalse($record, 'Record should exist in acmls_learning_resource.');

        $this->assertEquals($cmid, (int) $record->cmid);
        $this->assertEquals($courseid, (int) $record->courseid);
        $this->assertEquals('Test Resource', $record->title);
        $this->assertEquals('video', $record->resource_type);
        $this->assertEquals(2, (int) $record->difficulty_level);
        $this->assertEquals(0, (int) $record->access_count);
        $this->assertEquals(1, (int) $record->is_active);

        $tags = json_decode($record->topic_tags, true);
        $this->assertEquals(['math', 'algebra'], $tags);

        $styles = json_decode($record->learning_styles, true);
        $this->assertEquals(['auditory', 'visual'], $styles);

        $this->assertGreaterThanOrEqual($before, (int) $record->timecreated);
        $this->assertLessThanOrEqual($after, (int) $record->timecreated);
        $this->assertGreaterThanOrEqual($before, (int) $record->timemodified);
    }

    // =========================================================================
    // Test 6: update_resource() — updates fields correctly
    // =========================================================================

    /**
     * Test 6: update_resource() updates the specified fields and timemodified.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::update_resource
     */
    public function test_update_resource_updates_fields_correctly(): void {
        global $DB;

        $courseid = (int) $this->course->id;
        $id = $this->insert_resource($courseid, 501, 'Original Title', 1, ['reading']);

        $before_update = time();
        $result = $this->repo->update_resource($id, [
            'title'            => 'Updated Title',
            'difficulty_level' => 2,
            'learning_styles'  => ['kinesthetic'],
        ]);
        $after_update = time();

        $this->assertTrue($result, 'update_resource() should return true on success.');

        $record = $DB->get_record('acmls_learning_resource', ['id' => $id]);
        $this->assertEquals('Updated Title', $record->title);
        $this->assertEquals(2, (int) $record->difficulty_level);

        $styles = json_decode($record->learning_styles, true);
        $this->assertEquals(['kinesthetic'], $styles);

        $this->assertGreaterThanOrEqual($before_update, (int) $record->timemodified);
        $this->assertLessThanOrEqual($after_update, (int) $record->timemodified);
    }

    /**
     * Test that update_resource() returns false for non-existent ID.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::update_resource
     */
    public function test_update_resource_returns_false_for_nonexistent_id(): void {
        $result = $this->repo->update_resource(99999, ['title' => 'Ghost']);
        $this->assertFalse($result, 'update_resource() should return false for non-existent ID.');
    }

    // =========================================================================
    // Test 7: delete_resource() — soft delete (marks is_active = 0)
    // =========================================================================

    /**
     * Test 7: delete_resource() marks the resource as inactive (soft delete).
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::delete_resource
     */
    public function test_delete_resource_marks_as_inactive(): void {
        global $DB;

        $courseid = (int) $this->course->id;
        $id = $this->insert_resource($courseid, 601, 'To Be Deleted', 1);

        // Verify it's active before deletion.
        $before = $DB->get_record('acmls_learning_resource', ['id' => $id]);
        $this->assertEquals(1, (int) $before->is_active, 'Resource should be active before deletion.');

        $result = $this->repo->delete_resource($id);

        $this->assertTrue($result, 'delete_resource() should return true on success.');

        // Verify it's now inactive (soft delete).
        $after = $DB->get_record('acmls_learning_resource', ['id' => $id]);
        $this->assertNotFalse($after, 'Record should still exist after soft delete.');
        $this->assertEquals(0, (int) $after->is_active, 'Resource should be inactive after delete_resource().');
    }

    /**
     * Test that delete_resource() returns false for non-existent ID.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::delete_resource
     */
    public function test_delete_resource_returns_false_for_nonexistent_id(): void {
        $result = $this->repo->delete_resource(99999);
        $this->assertFalse($result, 'delete_resource() should return false for non-existent ID.');
    }

    /**
     * Test that deleted (inactive) resources are excluded from find_by_profile().
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::find_by_profile
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::delete_resource
     */
    public function test_deleted_resource_excluded_from_find_by_profile(): void {
        $courseid = (int) $this->course->id;

        $id = $this->insert_resource($courseid, 701, 'Deleted Resource', 1, ['reading']);
        $this->repo->delete_resource($id);

        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, learner_profile::STYLE_READING);
        $results = $this->repo->find_by_profile($profile, 10);

        $returned_ids = array_map('intval', array_column($results, 'id'));
        $this->assertNotContains($id, $returned_ids,
            'Soft-deleted resource should not appear in find_by_profile() results.');
    }

    // =========================================================================
    // Test 8: record_access() — increments access_count
    // =========================================================================

    /**
     * Test 8: record_access() increments access_count by 1 and updates timemodified.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::record_access
     */
    public function test_record_access_increments_access_count(): void {
        global $DB;

        $courseid = (int) $this->course->id;
        $userid   = (int) $this->user->id;
        $id = $this->insert_resource($courseid, 801, 'Accessed Resource', 1, ['reading'], 0.0, 0);

        $before_time = time();
        $this->repo->record_access($id, $userid);
        $after_time = time();

        $record = $DB->get_record('acmls_learning_resource', ['id' => $id]);
        $this->assertEquals(1, (int) $record->access_count,
            'access_count should be 1 after first record_access().');
        $this->assertGreaterThanOrEqual($before_time, (int) $record->timemodified);
        $this->assertLessThanOrEqual($after_time, (int) $record->timemodified);

        // Call again — should be 2.
        $this->repo->record_access($id, $userid);
        $record2 = $DB->get_record('acmls_learning_resource', ['id' => $id]);
        $this->assertEquals(2, (int) $record2->access_count,
            'access_count should be 2 after second record_access().');
    }

    /**
     * Test that record_access() on non-existent resource does not throw.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::record_access
     */
    public function test_record_access_nonexistent_resource_does_not_throw(): void {
        // Should silently do nothing.
        $this->repo->record_access(99999, (int) $this->user->id);
        $this->assertTrue(true, 'record_access() on non-existent resource should not throw.');
    }

    // =========================================================================
    // Test 9: get_effectiveness_data() — returns correct structure
    // =========================================================================

    /**
     * Test 9a: get_effectiveness_data() returns correct structure with null scores
     * when no access data exists.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::get_effectiveness_data
     */
    public function test_get_effectiveness_data_returns_correct_structure_no_data(): void {
        $courseid = (int) $this->course->id;
        $id = $this->insert_resource($courseid, 901, 'Effectiveness Test', 1, ['reading'], 0.0, 5);

        $data = $this->repo->get_effectiveness_data($id);

        $this->assertIsArray($data, 'get_effectiveness_data() should return an array.');
        $this->assertArrayHasKey('resource_id', $data);
        $this->assertArrayHasKey('access_count', $data);
        $this->assertArrayHasKey('avg_improvement', $data);
        $this->assertArrayHasKey('effectiveness_score', $data);

        $this->assertEquals($id, $data['resource_id']);
        $this->assertEquals(5, $data['access_count']);
        $this->assertNull($data['avg_improvement'],
            'avg_improvement should be null when no access log data exists.');
        $this->assertNull($data['effectiveness_score'],
            'effectiveness_score should be null when no access log data exists.');
    }

    /**
     * Test 9b: get_effectiveness_data() returns correct structure for non-existent resource.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::get_effectiveness_data
     */
    public function test_get_effectiveness_data_nonexistent_resource(): void {
        $data = $this->repo->get_effectiveness_data(99999);

        $this->assertIsArray($data);
        $this->assertEquals(99999, $data['resource_id']);
        $this->assertEquals(0, $data['access_count']);
        $this->assertNull($data['avg_improvement']);
        $this->assertNull($data['effectiveness_score']);
    }

    // =========================================================================
    // Additional tests: auto_index_new_resource()
    // =========================================================================

    /**
     * Test that auto_index_new_resource() returns 0 for non-existent cmid.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::auto_index_new_resource
     */
    public function test_auto_index_nonexistent_cmid_returns_zero(): void {
        $result = $this->repo->auto_index_new_resource(99999);
        $this->assertDebuggingCalled();
        $this->assertEquals(0, $result,
            'auto_index_new_resource() should return 0 for non-existent cmid.');
    }

    /**
     * Test that auto_index_new_resource() creates a resource for a real course module.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::auto_index_new_resource
     */
    public function test_auto_index_creates_resource_for_real_module(): void {
        global $DB;

        // Create a real page module in the test course.
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name'   => 'Test Page Resource',
        ]);

        $cmid = (int) $page->cmid;

        $id = $this->repo->auto_index_new_resource($cmid);

        $this->assertGreaterThan(0, $id,
            'auto_index_new_resource() should return a positive ID for a real module.');

        $record = $DB->get_record('acmls_learning_resource', ['id' => $id]);
        $this->assertNotFalse($record, 'Resource record should exist in DB.');
        $this->assertEquals($cmid, (int) $record->cmid);
        $this->assertEquals((int) $this->course->id, (int) $record->courseid);
        $this->assertEquals('Test Page Resource', $record->title);
        $this->assertEquals('page', $record->resource_type);
        $this->assertEquals(1, (int) $record->difficulty_level);
        $this->assertEquals(1, (int) $record->is_active);

        $styles = json_decode($record->learning_styles, true);
        $this->assertContains('reading', $styles,
            'Page module should default to reading learning style.');
    }

    /**
     * Test that auto_index_new_resource() uses UPSERT: calling twice for same cmid updates, not duplicates.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::auto_index_new_resource
     */
    public function test_auto_index_upsert_does_not_duplicate(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name'   => 'Upsert Test Page',
        ]);

        $cmid = (int) $page->cmid;

        $id1 = $this->repo->auto_index_new_resource($cmid);
        $id2 = $this->repo->auto_index_new_resource($cmid);

        $this->assertEquals($id1, $id2,
            'UPSERT: calling auto_index twice for same cmid should return same record ID.');

        $count = $DB->count_records('acmls_learning_resource', ['cmid' => $cmid]);
        $this->assertEquals(1, $count,
            'UPSERT: only one record should exist for a given cmid.');
    }

    // =========================================================================
    // Additional tests: find_by_profile() ordering and limit
    // =========================================================================

    /**
     * Test that find_by_profile() respects the $limit parameter.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::find_by_profile
     */
    public function test_find_by_profile_respects_limit(): void {
        $courseid = (int) $this->course->id;

        // Insert 10 difficulty=1 resources.
        for ($i = 1; $i <= 10; $i++) {
            $this->insert_resource($courseid, 1000 + $i, "Resource {$i}", 1, ['reading']);
        }

        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, learner_profile::STYLE_READING);
        $results = $this->repo->find_by_profile($profile, 3);

        $this->assertCount(3, $results,
            'find_by_profile() should return at most $limit resources.');
    }

    /**
     * Test that find_by_profile() orders by effectiveness_score DESC.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::find_by_profile
     */
    public function test_find_by_profile_orders_by_effectiveness_score_desc(): void {
        $courseid = (int) $this->course->id;

        $id_low  = $this->insert_resource($courseid, 1101, 'Low Effectiveness', 1, ['reading'], 20.0);
        $id_high = $this->insert_resource($courseid, 1102, 'High Effectiveness', 1, ['reading'], 80.0);
        $id_mid  = $this->insert_resource($courseid, 1103, 'Mid Effectiveness', 1, ['reading'], 50.0);

        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, learner_profile::STYLE_READING);
        $results = $this->repo->find_by_profile($profile, 10);

        $this->assertEquals($id_high, (int) $results[0]->id,
            'First result should have highest effectiveness_score.');
        $this->assertEquals($id_mid, (int) $results[1]->id,
            'Second result should have mid effectiveness_score.');
        $this->assertEquals($id_low, (int) $results[2]->id,
            'Third result should have lowest effectiveness_score.');
    }

    /**
     * Test that find_by_profile() returns empty array when no resources exist.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::find_by_profile
     */
    public function test_find_by_profile_returns_empty_when_no_resources(): void {
        $profile = $this->make_profile(learner_profile::PERFORMANCE_LOW, learner_profile::STYLE_READING);
        $results = $this->repo->find_by_profile($profile, 5);

        $this->assertIsArray($results);
        $this->assertEmpty($results, 'find_by_profile() should return empty array when no resources exist.');
    }

    /**
     * Test that find_by_profile() for Middle performance returns difficulty 1 and 2.
     *
     * @covers \block_attendanceleaderboard\repository\learning_resource_repository::find_by_profile
     */
    public function test_find_by_profile_middle_performance_returns_difficulty_1_and_2(): void {
        $courseid = (int) $this->course->id;

        $id1 = $this->insert_resource($courseid, 1201, 'Basic', 1, ['reading']);
        $id2 = $this->insert_resource($courseid, 1202, 'Intermediate', 2, ['reading']);
        $id3 = $this->insert_resource($courseid, 1203, 'Advanced', 3, ['reading']);

        $profile = $this->make_profile(learner_profile::PERFORMANCE_MIDDLE, learner_profile::STYLE_READING);
        $results = $this->repo->find_by_profile($profile, 10);

        $returned_ids = array_map('intval', array_column($results, 'id'));

        $this->assertContains($id1, $returned_ids, 'Middle performance should include difficulty=1.');
        $this->assertContains($id2, $returned_ids, 'Middle performance should include difficulty=2.');
        $this->assertNotContains($id3, $returned_ids, 'Middle performance should NOT include difficulty=3.');
    }
}
