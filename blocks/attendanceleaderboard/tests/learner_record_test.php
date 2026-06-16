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
 * Unit tests for ACMLS LearnerRecord repository.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tests;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\repository\learner_record;

/**
 * Unit test class for LearnerRecord.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_attendanceleaderboard\repository\learner_record
 */
class learner_record_test extends \advanced_testcase {

    /** @var learner_record System under test. */
    private learner_record $repo;

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

        $this->repo   = new learner_record();
        $this->user   = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
    }

    // =========================================================================
    // Test 1: save() inserts record with correct fields
    // =========================================================================

    /**
     * Test 1: save() inserts a record with all correct fields into the DB.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::save
     */
    public function test_save_inserts_record_with_correct_fields(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $data     = ['score' => 85.5, 'activity' => 'quiz'];

        $before = time();
        $id = $this->repo->save(
            $userid,
            $courseid,
            learner_record::TYPE_PERFORMANCE,
            learner_record::SOURCE_EVALUATION,
            $data,
            3
        );
        $after = time();

        $record = $DB->get_record('acmls_learner_record', ['id' => $id]);
        $this->assertNotFalse($record, 'Record should exist in acmls_learner_record.');

        $this->assertEquals($userid, (int) $record->userid);
        $this->assertEquals($courseid, (int) $record->courseid);
        $this->assertEquals(learner_record::TYPE_PERFORMANCE, $record->record_type);
        $this->assertEquals(learner_record::SOURCE_EVALUATION, $record->source_component);
        $this->assertEquals(3, (int) $record->profile_version);
        $this->assertGreaterThanOrEqual($before, (int) $record->timecreated);
        $this->assertLessThanOrEqual($after, (int) $record->timecreated);

        $decoded = json_decode($record->data_payload, true);
        $this->assertEquals($data, $decoded, 'data_payload should match original data.');
    }

    // =========================================================================
    // Test 2: save() returns positive ID
    // =========================================================================

    /**
     * Test 2: save() returns a positive integer ID.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::save
     */
    public function test_save_returns_positive_id(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;

        $id = $this->repo->save(
            $userid,
            $courseid,
            learner_record::TYPE_PROFILE_SNAPSHOT,
            learner_record::SOURCE_PROFILING,
            ['level' => 2]
        );

        $this->assertIsInt($id, 'save() should return an integer.');
        $this->assertGreaterThan(0, $id, 'save() should return a positive ID.');
    }

    // =========================================================================
    // Test 3: query_longitudinal() returns records within time range
    // =========================================================================

    /**
     * Test 3: query_longitudinal() returns records within the specified time range.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::query_longitudinal
     */
    public function test_query_longitudinal_returns_records_within_time_range(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        // Insert records at different times.
        $id1 = $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                                    learner_record::SOURCE_EVALUATION, ['score' => 70], $base - 100);
        $id2 = $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                                    learner_record::SOURCE_EVALUATION, ['score' => 80], $base);
        $id3 = $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                                    learner_record::SOURCE_EVALUATION, ['score' => 90], $base + 100);

        // Query only the middle record.
        $results = $this->repo->query_longitudinal($userid, $courseid, $base - 50, $base + 50);

        $returned_ids = array_map(fn($r) => (int) $r->id, $results);

        $this->assertContains($id2, $returned_ids, 'Record within range should be returned.');
        $this->assertNotContains($id1, $returned_ids, 'Record before range should not be returned.');
        $this->assertNotContains($id3, $returned_ids, 'Record after range should not be returned.');
    }

    // =========================================================================
    // Test 4: query_longitudinal() excludes records outside time range
    // =========================================================================

    /**
     * Test 4: query_longitudinal() excludes records outside the time range.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::query_longitudinal
     */
    public function test_query_longitudinal_excludes_records_outside_time_range(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        $id_before = $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                                          learner_record::SOURCE_EVALUATION, ['score' => 50], $base - 200);
        $id_after  = $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                                          learner_record::SOURCE_EVALUATION, ['score' => 60], $base + 200);

        $results = $this->repo->query_longitudinal($userid, $courseid, $base - 100, $base + 100);

        $returned_ids = array_map(fn($r) => (int) $r->id, $results);

        $this->assertNotContains($id_before, $returned_ids, 'Record before range should be excluded.');
        $this->assertNotContains($id_after, $returned_ids, 'Record after range should be excluded.');
    }

    // =========================================================================
    // Test 5: query_longitudinal() filters by record_type
    // =========================================================================

    /**
     * Test 5: query_longitudinal() correctly filters by record_type.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::query_longitudinal
     */
    public function test_query_longitudinal_filters_by_record_type(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        $id_perf     = $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                                            learner_record::SOURCE_EVALUATION, ['score' => 75], $base);
        $id_snapshot = $this->insert_record($userid, $courseid, learner_record::TYPE_PROFILE_SNAPSHOT,
                                            learner_record::SOURCE_PROFILING, ['level' => 2], $base);

        $results = $this->repo->query_longitudinal(
            $userid, $courseid, $base - 10, $base + 10,
            ['record_type' => learner_record::TYPE_PERFORMANCE]
        );

        $returned_ids = array_map(fn($r) => (int) $r->id, $results);

        $this->assertContains($id_perf, $returned_ids, 'Performance record should be returned.');
        $this->assertNotContains($id_snapshot, $returned_ids, 'Snapshot record should be filtered out.');
    }

    // =========================================================================
    // Test 6: query_longitudinal() filters by source_component
    // =========================================================================

    /**
     * Test 6: query_longitudinal() correctly filters by source_component.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::query_longitudinal
     */
    public function test_query_longitudinal_filters_by_source_component(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        $id_eval  = $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                                         learner_record::SOURCE_EVALUATION, ['score' => 80], $base);
        $id_coach = $this->insert_record($userid, $courseid, learner_record::TYPE_DECISION,
                                         learner_record::SOURCE_COACH, ['rule' => 'R1'], $base);

        $results = $this->repo->query_longitudinal(
            $userid, $courseid, $base - 10, $base + 10,
            ['source_component' => learner_record::SOURCE_EVALUATION]
        );

        $returned_ids = array_map(fn($r) => (int) $r->id, $results);

        $this->assertContains($id_eval, $returned_ids, 'Evaluation record should be returned.');
        $this->assertNotContains($id_coach, $returned_ids, 'Coach record should be filtered out.');
    }

    // =========================================================================
    // Test 7: export_csv() returns valid CSV with correct headers
    // =========================================================================

    /**
     * Test 7: export_csv() returns a valid CSV string with correct headers.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::export_csv
     */
    public function test_export_csv_returns_valid_csv_with_correct_headers(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, ['score' => 88], $base);

        $csv = $this->repo->export_csv($userid, $courseid);

        $this->assertIsString($csv, 'export_csv() should return a string.');
        $this->assertNotEmpty($csv, 'export_csv() should not return an empty string.');

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV should have at least a header and one data row.');

        // Verify headers are present in the first line.
        $header_line = $lines[0];
        $expected_headers = ['id', 'userid', 'courseid', 'record_type', 'source_component',
                             'profile_version', 'timecreated', 'data_payload'];

        foreach ($expected_headers as $header) {
            $this->assertStringContainsString($header, $header_line,
                "CSV header should contain '{$header}'.");
        }
    }

    // =========================================================================
    // Test 8: export_csv() includes all records for the user
    // =========================================================================

    /**
     * Test 8: export_csv() includes all records for the user/course.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::export_csv
     */
    public function test_export_csv_includes_all_records_for_the_user(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        // Insert 3 records for this user.
        $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, ['score' => 70], $base - 100);
        $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, ['score' => 80], $base);
        $this->insert_record($userid, $courseid, learner_record::TYPE_PROFILE_SNAPSHOT,
                             learner_record::SOURCE_PROFILING, ['level' => 2], $base + 100);

        // Insert a record for a different user — should NOT appear.
        $other_user = $this->getDataGenerator()->create_user();
        $this->insert_record((int) $other_user->id, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, ['score' => 60], $base);

        $csv = $this->repo->export_csv($userid, $courseid);

        // Count data rows (total lines minus header line, minus possible trailing newline).
        $lines = array_filter(explode("\n", trim($csv)));
        $data_rows = count($lines) - 1; // Subtract header.

        $this->assertEquals(3, $data_rows, 'CSV should contain exactly 3 data rows for this user.');

        // Verify the other user's data is not included by parsing each data row
        // and checking the userid column (index 1).
        $all_lines = array_values(array_filter(explode("\n", trim($csv))));
        for ($i = 1; $i < count($all_lines); $i++) {
            $fields = str_getcsv($all_lines[$i]);
            $this->assertNotEquals(
                (string) $other_user->id,
                $fields[1],
                "Row {$i}: other user's record should not appear in CSV."
            );
        }
    }

    // =========================================================================
    // Test 9: export_json() returns valid JSON
    // =========================================================================

    /**
     * Test 9: export_json() returns valid JSON.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::export_json
     */
    public function test_export_json_returns_valid_json(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, ['score' => 75], $base);

        $json = $this->repo->export_json($userid, $courseid);

        $this->assertIsString($json, 'export_json() should return a string.');
        $this->assertNotEmpty($json, 'export_json() should not return an empty string.');

        $decoded = json_decode($json, true);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error(),
            'export_json() should return valid JSON. Error: ' . json_last_error_msg());
        $this->assertIsArray($decoded, 'Decoded JSON should be an array of records.');
    }

    // =========================================================================
    // Test 10: export_json() includes decoded data_payload
    // =========================================================================

    /**
     * Test 10: export_json() includes decoded data_payload (not double-encoded).
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::export_json
     */
    public function test_export_json_includes_decoded_data_payload(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        $data = ['score' => 92, 'grade' => 'A', 'details' => ['passed' => true]];
        $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, $data, $base);

        $json = $this->repo->export_json($userid, $courseid);
        $decoded = json_decode($json, true);

        $this->assertCount(1, $decoded, 'Should have exactly 1 record.');

        $record = $decoded[0];
        $this->assertArrayHasKey('data_payload', $record, 'Record should have data_payload key.');
        $this->assertIsArray($record['data_payload'],
            'data_payload in JSON export should be a decoded array, not a JSON string.');
        $this->assertEquals(92, $record['data_payload']['score'],
            'data_payload score should match original value.');
        $this->assertEquals('A', $record['data_payload']['grade'],
            'data_payload grade should match original value.');
    }

    // =========================================================================
    // Test 11: check_integrity() returns true when all records are valid
    // =========================================================================

    /**
     * Test 11: check_integrity() returns true when all records are valid.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::check_integrity
     */
    public function test_check_integrity_returns_true_when_all_records_are_valid(): void {
        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        // Insert valid records with proper data_payload and timecreated.
        $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, ['score' => 80], $base - 100);
        $this->insert_record($userid, $courseid, learner_record::TYPE_PROFILE_SNAPSHOT,
                             learner_record::SOURCE_PROFILING, ['level' => 2], $base - 50);
        $this->insert_record($userid, $courseid, learner_record::TYPE_DECISION,
                             learner_record::SOURCE_COACH, ['rule' => 'R1'], $base);

        $result = $this->repo->check_integrity();

        $this->assertTrue($result, 'check_integrity() should return true when all records are valid.');
    }

    // =========================================================================
    // Test 12: check_integrity() returns false when records have empty data_payload
    // =========================================================================

    /**
     * Test 12: check_integrity() returns false when records have empty data_payload.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::check_integrity
     */
    public function test_check_integrity_returns_false_when_records_have_empty_data_payload(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        // Insert a record with empty data_payload directly.
        $bad_record = new \stdClass();
        $bad_record->userid           = $userid;
        $bad_record->courseid         = $courseid;
        $bad_record->record_type      = learner_record::TYPE_PERFORMANCE;
        $bad_record->source_component = learner_record::SOURCE_EVALUATION;
        $bad_record->data_payload     = '';
        $bad_record->profile_version  = null;
        $bad_record->timecreated      = $base;
        $DB->insert_record('acmls_learner_record', $bad_record);

        $result = $this->repo->check_integrity();

        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);
        $this->assertFalse($result, 'check_integrity() should return false when records have empty data_payload.');
    }

    // =========================================================================
    // Test 13: apply_access_control() returns false for student (no viewanalytics)
    // =========================================================================

    /**
     * Test 13: apply_access_control() returns false for a student without viewanalytics capability.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::apply_access_control
     */
    public function test_apply_access_control_returns_false_for_student(): void {
        $courseid = (int) $this->course->id;

        // Create a regular student user enrolled without viewanalytics.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $courseid, 'student');

        $result = $this->repo->apply_access_control((int) $student->id, $courseid);

        $this->assertFalse($result, 'Student without viewanalytics capability should not have access.');
    }

    // =========================================================================
    // Test 14: apply_access_control() returns true for teacher with viewanalytics
    // =========================================================================

    /**
     * Test 14: apply_access_control() returns true for a teacher with viewanalytics capability.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::apply_access_control
     */
    public function test_apply_access_control_returns_true_for_teacher_with_viewanalytics(): void {
        $courseid = (int) $this->course->id;

        // Create a user and assign a role with viewanalytics capability.
        $teacher = $this->getDataGenerator()->create_user();
        $role_id = $this->getDataGenerator()->create_role();

        // Assign viewanalytics capability to the role.
        $context = \context_course::instance($courseid);
        assign_capability('block/attendanceleaderboard:viewanalytics', CAP_ALLOW, $role_id, $context->id);

        // Enrol the teacher with this role.
        $this->getDataGenerator()->enrol_user($teacher->id, $courseid, $role_id);

        $result = $this->repo->apply_access_control((int) $teacher->id, $courseid);

        $this->assertTrue($result, 'Teacher with viewanalytics capability should have access.');
    }

    // =========================================================================
    // Test 15: query_longitudinal() writes an access log entry
    // =========================================================================

    /**
     * Test 15: query_longitudinal() writes an entry to acmls_data_access_log.
     *
     * Verifies that every call to query_longitudinal() creates an audit log
     * record capturing the accessor, target, course, access type, and filters.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::query_longitudinal
     */
    public function test_query_longitudinal_writes_access_log(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        // Set the current user so log_access() can capture the accessor.
        $accessor = $this->getDataGenerator()->create_user();
        $this->setUser($accessor);

        // Insert a record to query.
        $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, ['score' => 80], $base);

        $before = time();
        $this->repo->query_longitudinal(
            $userid, $courseid, $base - 10, $base + 10,
            ['record_type' => learner_record::TYPE_PERFORMANCE]
        );
        $after = time();

        // Verify an access log entry was created.
        $logs = $DB->get_records('acmls_data_access_log', [
            'accessor_userid' => (int) $accessor->id,
            'target_userid'   => $userid,
            'courseid'        => $courseid,
            'access_type'     => 'query_longitudinal',
        ]);

        $this->assertCount(1, $logs, 'Exactly one access log entry should be created.');

        $log = reset($logs);
        $this->assertEquals(learner_record::TYPE_PERFORMANCE, $log->record_type_filter,
            'record_type_filter should match the filter passed to query_longitudinal().');
        $this->assertNull($log->source_component_filter,
            'source_component_filter should be null when not passed.');
        $this->assertEquals(1, (int) $log->records_returned,
            'records_returned should reflect the number of records returned.');
        $this->assertGreaterThanOrEqual($before, (int) $log->timecreated);
        $this->assertLessThanOrEqual($after, (int) $log->timecreated);
    }

    // =========================================================================
    // Test 16: export_csv() writes an access log entry
    // =========================================================================

    /**
     * Test 16: export_csv() writes an entry to acmls_data_access_log.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::export_csv
     */
    public function test_export_csv_writes_access_log(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        $accessor = $this->getDataGenerator()->create_user();
        $this->setUser($accessor);

        $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, ['score' => 75], $base);
        $this->insert_record($userid, $courseid, learner_record::TYPE_PROFILE_SNAPSHOT,
                             learner_record::SOURCE_PROFILING, ['level' => 2], $base + 1);

        $this->repo->export_csv($userid, $courseid);

        $logs = $DB->get_records('acmls_data_access_log', [
            'accessor_userid' => (int) $accessor->id,
            'target_userid'   => $userid,
            'courseid'        => $courseid,
            'access_type'     => 'export_csv',
        ]);

        $this->assertCount(1, $logs, 'Exactly one access log entry should be created for export_csv().');

        $log = reset($logs);
        $this->assertEquals(2, (int) $log->records_returned,
            'records_returned should equal the number of records exported.');
        $this->assertNull($log->record_type_filter,
            'record_type_filter should be null for export_csv().');
        $this->assertNull($log->source_component_filter,
            'source_component_filter should be null for export_csv().');
    }

    // =========================================================================
    // Test 17: export_json() writes an access log entry
    // =========================================================================

    /**
     * Test 17: export_json() writes an entry to acmls_data_access_log.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::export_json
     */
    public function test_export_json_writes_access_log(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        $accessor = $this->getDataGenerator()->create_user();
        $this->setUser($accessor);

        $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, ['score' => 90], $base);

        $this->repo->export_json($userid, $courseid);

        $logs = $DB->get_records('acmls_data_access_log', [
            'accessor_userid' => (int) $accessor->id,
            'target_userid'   => $userid,
            'courseid'        => $courseid,
            'access_type'     => 'export_json',
        ]);

        $this->assertCount(1, $logs, 'Exactly one access log entry should be created for export_json().');

        $log = reset($logs);
        $this->assertEquals(1, (int) $log->records_returned,
            'records_returned should equal the number of records exported.');
    }

    // =========================================================================
    // Test 18: access log failure does not break data access
    // =========================================================================

    /**
     * Test 18: A failure in log_access() does not prevent data from being returned.
     *
     * This test verifies the non-blocking contract: even if the access log
     * table does not exist (simulated by dropping it), the read methods still
     * return data successfully.
     *
     * @covers \block_attendanceleaderboard\repository\learner_record::query_longitudinal
     */
    public function test_access_log_failure_does_not_break_data_access(): void {
        global $DB;

        $userid   = (int) $this->user->id;
        $courseid = (int) $this->course->id;
        $base     = time();

        $this->insert_record($userid, $courseid, learner_record::TYPE_PERFORMANCE,
                             learner_record::SOURCE_EVALUATION, ['score' => 65], $base);

        // Drop the access log table to force a DB error in log_access().
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('acmls_data_access_log');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // query_longitudinal() should still succeed and return data.
        $results = $this->repo->query_longitudinal($userid, $courseid, $base - 10, $base + 10);

        $this->assertCount(1, $results,
            'query_longitudinal() should return data even when access log write fails.');
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER,
            'A debugging message should be emitted when log_access() fails.');
    }

    // =========================================================================
    // Helper: insert a record directly into the DB
    // =========================================================================

    /**
     * Insert a learner record directly into the DB for testing.
     *
     * @param  int      $userid
     * @param  int      $courseid
     * @param  string   $record_type
     * @param  string   $source_component
     * @param  array    $data
     * @param  int      $timecreated
     * @param  int|null $profile_version
     * @return int      Inserted record ID.
     */
    private function insert_record(
        int $userid,
        int $courseid,
        string $record_type,
        string $source_component,
        array $data,
        int $timecreated,
        ?int $profile_version = null
    ): int {
        global $DB;

        $record = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->record_type      = $record_type;
        $record->source_component = $source_component;
        $record->data_payload     = json_encode($data);
        $record->profile_version  = $profile_version;
        $record->timecreated      = $timecreated;

        return (int) $DB->insert_record('acmls_learner_record', $record);
    }
}
