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
 * Unit tests for ACMLS DeliverySystem.
 *
 * Tests cover:
 *  1.  display_resource_recommendations() returns non-empty string for valid resources
 *  2.  display_resource_recommendations() returns non-empty string for empty resources
 *  3.  display_encouragement() returns non-empty string with content and category
 *  4.  display_encouragement() handles empty category gracefully
 *  5.  display_leaderboard() returns non-empty string (no leaderboard data)
 *  6.  record_interaction() inserts a record into acmls_learner_record
 *  7.  record_interaction() stores correct interaction_type in data_payload
 *  8.  record_interaction() stores correct source_component = 'delivery'
 *  9.  record_interaction() stores correct record_type = 'interaction'
 * 10.  record_interaction() stores additional data payload correctly
 * 11.  record_interaction() sets a valid timecreated timestamp
 * 12.  render_block() returns non-empty string (no profile data)
 * 13.  render_block() returns non-empty string (with leaderboard data)
 * 14.  Multiple interactions can be recorded independently
 * 15.  record_interaction() for resource_accessed stores resourceid in payload
 * 16.  record_interaction() for encouragement_dismissed stores category in payload
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tests;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\delivery\delivery_system;

/**
 * Unit test class for DeliverySystem.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_attendanceleaderboard\delivery\delivery_system
 */
class delivery_system_test extends \advanced_testcase {

    /** @var delivery_system System under test. */
    private delivery_system $ds;

    /** @var \stdClass Test user. */
    private \stdClass $user;

    /** @var \stdClass Test course. */
    private \stdClass $course;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest(true);

        $this->ds     = new delivery_system();
        $this->user   = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();

        // Set up a minimal PAGE context so render_from_template() works.
        $this->setUser($this->user);
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/');
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Build a minimal resource record for testing.
     *
     * @param  int    $id             Resource ID.
     * @param  string $title          Resource title.
     * @param  string $resource_type  Resource type string.
     * @param  int    $difficulty     Difficulty level (1–3).
     * @return array                  Resource data array.
     */
    private function make_resource(
        int $id = 1,
        string $title = 'Test Resource',
        string $resource_type = 'video',
        int $difficulty = 1
    ): array {
        return [
            'id'              => $id,
            'title'           => $title,
            'resource_type'   => $resource_type,
            'difficulty_level' => $difficulty,
        ];
    }

    /**
     * Insert a leaderboard record for a user/course.
     *
     * @param  int   $userid   Moodle user ID.
     * @param  int   $courseid Moodle course ID.
     * @param  float $score    Total score.
     * @param  int   $rank     Current rank.
     * @return void
     */
    private function insert_leaderboard_record(int $userid, int $courseid, float $score = 75.0, int $rank = 1): void {
        global $DB;

        $record                   = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->scope            = 'course';
        $record->attendance_score = $score;
        $record->engagement_score = $score;
        $record->completion_score = $score;
        $record->total_score      = $score;
        $record->current_rank     = $rank;
        $record->previous_rank    = $rank + 1;
        $record->rank_change      = 1;
        $record->points_to_next   = null;
        $record->display_name     = fullname($this->user);
        $record->last_updated     = time();

        $DB->insert_record('acmls_leaderboard', $record);
    }

    // =========================================================================
    // Test 1: display_resource_recommendations() with valid resources
    // =========================================================================

    /**
     * Test that display_resource_recommendations() returns a non-empty HTML string
     * when resources are provided.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::display_resource_recommendations
     */
    public function test_display_resource_recommendations_with_resources(): void {
        $userid    = (int) $this->user->id;
        $resources = [
            $this->make_resource(1, 'Pengantar Algoritma', 'video', 1),
            $this->make_resource(2, 'Struktur Data Lanjutan', 'document', 3),
        ];

        $html = $this->ds->display_resource_recommendations($userid, $resources);

        $this->assertIsString($html, 'display_resource_recommendations() should return a string');
        $this->assertNotEmpty($html, 'HTML output should not be empty when resources are provided');
    }

    // =========================================================================
    // Test 2: display_resource_recommendations() with empty resources
    // =========================================================================

    /**
     * Test that display_resource_recommendations() returns a non-empty HTML string
     * even when the resources array is empty (shows "no resources" state).
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::display_resource_recommendations
     */
    public function test_display_resource_recommendations_with_empty_resources(): void {
        $html = $this->ds->display_resource_recommendations((int) $this->user->id, []);

        $this->assertIsString($html, 'display_resource_recommendations() should return a string');
        $this->assertNotEmpty($html, 'HTML output should not be empty even with no resources');
    }

    // =========================================================================
    // Test 3: display_encouragement() with content and category
    // =========================================================================

    /**
     * Test that display_encouragement() returns a non-empty HTML string
     * when content and category are provided.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::display_encouragement
     */
    public function test_display_encouragement_with_content_and_category(): void {
        $userid   = (int) $this->user->id;
        $content  = 'Anda telah menunjukkan kemajuan yang luar biasa. Terus pertahankan semangat belajar Anda!';
        $category = 'reinforcement';

        $html = $this->ds->display_encouragement($userid, $content, $category);

        $this->assertIsString($html, 'display_encouragement() should return a string');
        $this->assertNotEmpty($html, 'HTML output should not be empty');
    }

    // =========================================================================
    // Test 4: display_encouragement() with empty category
    // =========================================================================

    /**
     * Test that display_encouragement() handles an empty category gracefully.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::display_encouragement
     */
    public function test_display_encouragement_with_empty_category(): void {
        $html = $this->ds->display_encouragement(
            (int) $this->user->id,
            'Tetap semangat dalam belajar!',
            ''
        );

        $this->assertIsString($html, 'display_encouragement() should return a string');
        $this->assertNotEmpty($html, 'HTML output should not be empty with empty category');
    }

    // =========================================================================
    // Test 5: display_leaderboard() returns non-empty string (no data)
    // =========================================================================

    /**
     * Test that display_leaderboard() returns a non-empty HTML string
     * even when no leaderboard data exists for the user.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::display_leaderboard
     */
    public function test_display_leaderboard_returns_string_when_no_data(): void {
        $html = $this->ds->display_leaderboard(
            (int) $this->user->id,
            (int) $this->course->id,
            'course'
        );

        $this->assertIsString($html, 'display_leaderboard() should return a string');
        $this->assertNotEmpty($html, 'HTML output should not be empty');
    }

    // =========================================================================
    // Test 6: record_interaction() inserts a record into acmls_learner_record
    // =========================================================================

    /**
     * Test that record_interaction() inserts a record into acmls_learner_record.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_record_interaction_inserts_db_record(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $count_before = $DB->count_records('acmls_learner_record', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', ['resourceid' => 42]);

        $count_after = $DB->count_records('acmls_learner_record', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        $this->assertEquals(
            $count_before + 1,
            $count_after,
            'record_interaction() should insert exactly one record into acmls_learner_record'
        );
    }

    // =========================================================================
    // Test 7: record_interaction() stores correct interaction_type in data_payload
    // =========================================================================

    /**
     * Test that record_interaction() stores the interaction_type in the data_payload JSON.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_record_interaction_stores_interaction_type_in_payload(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', ['resourceid' => 10]);

        $record = $DB->get_record('acmls_learner_record', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        $this->assertNotFalse($record, 'A record should exist in acmls_learner_record');

        $payload = json_decode($record->data_payload, true);
        $this->assertIsArray($payload, 'data_payload should be valid JSON');
        $this->assertArrayHasKey('interaction_type', $payload, 'data_payload should contain interaction_type');
        $this->assertEquals('resource_accessed', $payload['interaction_type']);
    }

    // =========================================================================
    // Test 8: record_interaction() stores correct source_component = 'delivery'
    // =========================================================================

    /**
     * Test that record_interaction() sets source_component to 'delivery'.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_record_interaction_sets_source_component_delivery(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $this->ds->record_interaction($userid, $courseid, 'encouragement_dismissed', []);

        $record = $DB->get_record('acmls_learner_record', [
            'userid'           => $userid,
            'courseid'         => $courseid,
            'source_component' => 'delivery',
        ]);

        $this->assertNotFalse($record, 'Record should have source_component = "delivery"');
        $this->assertEquals('delivery', $record->source_component);
    }

    // =========================================================================
    // Test 9: record_interaction() stores correct record_type = 'interaction'
    // =========================================================================

    /**
     * Test that record_interaction() sets record_type to 'interaction'.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_record_interaction_sets_record_type_interaction(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', []);

        $record = $DB->get_record('acmls_learner_record', [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'record_type' => 'interaction',
        ]);

        $this->assertNotFalse($record, 'Record should have record_type = "interaction"');
        $this->assertEquals('interaction', $record->record_type);
    }

    // =========================================================================
    // Test 10: record_interaction() stores additional data payload correctly
    // =========================================================================

    /**
     * Test that record_interaction() stores the additional data array in the payload.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_record_interaction_stores_additional_data_in_payload(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $extra    = ['resourceid' => 99, 'cmid' => 55];

        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', $extra);

        $record = $DB->get_record('acmls_learner_record', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        $payload = json_decode($record->data_payload, true);

        $this->assertArrayHasKey('data', $payload, 'data_payload should contain a "data" key');
        $this->assertEquals(99, $payload['data']['resourceid'], 'resourceid should be stored in data');
        $this->assertEquals(55, $payload['data']['cmid'], 'cmid should be stored in data');
    }

    // =========================================================================
    // Test 11: record_interaction() sets a valid timecreated timestamp
    // =========================================================================

    /**
     * Test that record_interaction() sets a valid timecreated timestamp.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_record_interaction_sets_valid_timecreated(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $before = time();
        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', []);
        $after  = time();

        $record = $DB->get_record('acmls_learner_record', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        $this->assertGreaterThanOrEqual($before, (int) $record->timecreated,
            'timecreated should be >= time before the call');
        $this->assertLessThanOrEqual($after, (int) $record->timecreated,
            'timecreated should be <= time after the call');
    }

    // =========================================================================
    // Test 12: render_block() returns non-empty string (no profile data)
    // =========================================================================

    /**
     * Test that render_block() returns a non-empty HTML string even when
     * no learner profile exists for the user/course.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::render_block
     */
    public function test_render_block_returns_string_without_profile(): void {
        $html = $this->ds->render_block(
            (int) $this->user->id,
            (int) $this->course->id
        );

        $this->assertIsString($html, 'render_block() should return a string');
        $this->assertNotEmpty($html, 'render_block() should return non-empty HTML');
    }

    // =========================================================================
    // Test 13: render_block() returns non-empty string (with leaderboard data)
    // =========================================================================

    /**
     * Test that render_block() returns a non-empty HTML string when leaderboard
     * data exists for the user/course.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::render_block
     */
    public function test_render_block_returns_string_with_leaderboard_data(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Insert a leaderboard record so the block has data to display.
        $this->insert_leaderboard_record($userid, $courseid, 80.0, 1);

        $html = $this->ds->render_block($userid, $courseid);

        $this->assertIsString($html, 'render_block() should return a string');
        $this->assertNotEmpty($html, 'render_block() should return non-empty HTML with leaderboard data');
    }

    // =========================================================================
    // Test 14: Multiple interactions can be recorded independently
    // =========================================================================

    /**
     * Test that multiple calls to record_interaction() create independent records.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_multiple_interactions_recorded_independently(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', ['resourceid' => 1]);
        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', ['resourceid' => 2]);
        $this->ds->record_interaction($userid, $courseid, 'encouragement_dismissed', ['category' => 'reinforcement']);

        $count = $DB->count_records('acmls_learner_record', [
            'userid'           => $userid,
            'courseid'         => $courseid,
            'record_type'      => 'interaction',
            'source_component' => 'delivery',
        ]);

        $this->assertEquals(3, $count, 'Three independent interaction records should be created');
    }

    // =========================================================================
    // Test 15: record_interaction() for resource_accessed stores resourceid
    // =========================================================================

    /**
     * Test that a resource_accessed interaction stores the resourceid in the payload.
     *
     * This verifies the Learner response recording for resource access (Req 5.4).
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_resource_accessed_interaction_stores_resourceid(): void {
        global $DB;

        $userid     = (int) $this->user->id;
        $courseid   = (int) $this->course->id;
        $resourceid = 42;

        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', [
            'resourceid' => $resourceid,
        ]);

        $record = $DB->get_record('acmls_learner_record', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        $payload = json_decode($record->data_payload, true);

        $this->assertEquals('resource_accessed', $payload['interaction_type'],
            'interaction_type should be resource_accessed');
        $this->assertEquals($resourceid, $payload['data']['resourceid'],
            'resourceid should be stored in the payload data');
    }

    // =========================================================================
    // Test 16: record_interaction() for encouragement_dismissed stores category
    // =========================================================================

    /**
     * Test that an encouragement_dismissed interaction stores the category in the payload.
     *
     * This verifies the Learner response recording for dismissal (Req 5.6).
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_encouragement_dismissed_interaction_stores_category(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $category = 'achievement';

        $this->ds->record_interaction($userid, $courseid, 'encouragement_dismissed', [
            'category' => $category,
        ]);

        $record = $DB->get_record('acmls_learner_record', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        $payload = json_decode($record->data_payload, true);

        $this->assertEquals('encouragement_dismissed', $payload['interaction_type'],
            'interaction_type should be encouragement_dismissed');
        $this->assertEquals($category, $payload['data']['category'],
            'category should be stored in the payload data');
    }

    // =========================================================================
    // Test: DIFFICULTY_LABELS constant covers all valid levels
    // =========================================================================

    /**
     * Test that the DIFFICULTY_LABELS constant covers all valid difficulty levels (1–3).
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system
     */
    public function test_difficulty_labels_cover_all_levels(): void {
        $labels = delivery_system::DIFFICULTY_LABELS;

        $this->assertArrayHasKey(1, $labels, 'DIFFICULTY_LABELS should have key 1 (Dasar)');
        $this->assertArrayHasKey(2, $labels, 'DIFFICULTY_LABELS should have key 2 (Menengah)');
        $this->assertArrayHasKey(3, $labels, 'DIFFICULTY_LABELS should have key 3 (Lanjutan)');

        $this->assertEquals('Dasar',    $labels[1]);
        $this->assertEquals('Menengah', $labels[2]);
        $this->assertEquals('Lanjutan', $labels[3]);
    }

    // =========================================================================
    // Test: display_resource_recommendations() includes difficulty_label
    // =========================================================================

    /**
     * Test that display_resource_recommendations() maps difficulty_level to the
     * correct difficulty_label for each level.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::display_resource_recommendations
     */
    public function test_display_resource_recommendations_maps_difficulty_labels(): void {
        $userid = (int) $this->user->id;

        // Test all three difficulty levels.
        foreach ([1 => 'Dasar', 2 => 'Menengah', 3 => 'Lanjutan'] as $level => $expected_label) {
            $resources = [$this->make_resource(1, 'Test', 'video', $level)];
            $html = $this->ds->display_resource_recommendations($userid, $resources);

            $this->assertStringContainsString(
                $expected_label,
                $html,
                "Difficulty level {$level} should render label '{$expected_label}'"
            );
        }
    }

    // =========================================================================
    // Test: record_interaction() with empty data array
    // =========================================================================

    /**
     * Test that record_interaction() works correctly with an empty data array.
     *
     * @covers \block_attendanceleaderboard\delivery\delivery_system::record_interaction
     */
    public function test_record_interaction_with_empty_data(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        // Should not throw any exception.
        $this->ds->record_interaction($userid, $courseid, 'resource_accessed', []);

        $record = $DB->get_record('acmls_learner_record', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        $this->assertNotFalse($record, 'Record should be inserted even with empty data array');

        $payload = json_decode($record->data_payload, true);
        $this->assertIsArray($payload, 'data_payload should be valid JSON');
        $this->assertArrayHasKey('data', $payload, 'data_payload should contain "data" key');
        $this->assertIsArray($payload['data'], '"data" should be an array');
        $this->assertEmpty($payload['data'], '"data" should be empty when no extra data provided');
    }
}
