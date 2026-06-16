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
 * Learner Record Analytics Repository for ACMLS.
 *
 * Stores all historical learning data comprehensively to support longitudinal
 * analysis, system auditing, and data-driven research.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository for acmls_learner_record table.
 *
 * Provides save, longitudinal query, CSV/JSON export, integrity checking,
 * and role-based access control for the Learner Record analytics store.
 *
 * Requirements addressed:
 * - Req 10.1: Stores historical data for each Learner.
 * - Req 10.2: Supports longitudinal analytics queries.
 * - Req 10.3: Stores data with accurate timestamps and source metadata.
 * - Req 10.4: Exports data in CSV and JSON formats.
 * - Req 10.5: Enforces role-based access control via viewanalytics capability.
 * - Req 10.6: Detects data integrity issues and notifies administrator.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_record {

    /** @var string Database table name (without Moodle prefix). */
    const TABLE = 'acmls_learner_record';

    // -------------------------------------------------------------------------
    // Valid record types.
    // -------------------------------------------------------------------------

    /** Record type: snapshot of the learner profile at a point in time. */
    const TYPE_PROFILE_SNAPSHOT = 'profile_snapshot';

    /** Record type: academic performance data from Evaluation System. */
    const TYPE_PERFORMANCE = 'performance';

    /** Record type: adaptive intervention delivered to the learner. */
    const TYPE_INTERVENTION = 'intervention';

    /** Record type: Coach recommendation decision. */
    const TYPE_DECISION = 'decision';

    /** Record type: motivational content delivered to the learner. */
    const TYPE_MOTIVATION_DELIVERY = 'motivation_delivery';

    /** Record type: learner's response to a motivational intervention. */
    const TYPE_MOTIVATION_RESPONSE = 'motivation_response';

    /** Record type: performance alert triggered by Evaluation System. */
    const TYPE_PERFORMANCE_ALERT = 'performance_alert';

    /** Record type: data access log entry (for audit purposes). */
    const TYPE_DATA_ACCESS_LOG = 'data_access_log';

    // -------------------------------------------------------------------------
    // Valid source components.
    // -------------------------------------------------------------------------

    /** Source component: Profiling System. */
    const SOURCE_PROFILING = 'profiling';

    /** Source component: Evaluation System. */
    const SOURCE_EVALUATION = 'evaluation';

    /** Source component: Tracking System. */
    const SOURCE_TRACKING = 'tracking';

    /** Source component: Coach rule-based engine. */
    const SOURCE_COACH = 'coach';

    /** Source component: Motivation Component. */
    const SOURCE_MOTIVATION = 'motivation';

    // -------------------------------------------------------------------------
    // Public API — save()
    // -------------------------------------------------------------------------

    /**
     * Save a learning record for a learner.
     *
     * Inserts a new record into acmls_learner_record with:
     * - userid, courseid, record_type, source_component
     * - data_payload = json_encode($data)
     * - profile_version = $profile_version (nullable)
     * - timecreated = time()
     *
     * @param  int         $userid           Moodle user ID.
     * @param  int         $courseid         Moodle course ID.
     * @param  string      $record_type      One of the TYPE_* constants.
     * @param  string      $source_component One of the SOURCE_* constants.
     * @param  array       $data             Payload data to store as JSON.
     * @param  int|null    $profile_version  Optional profile version number.
     * @return int                           Inserted record ID.
     */
    public function save(
        int $userid,
        int $courseid,
        string $record_type,
        string $source_component,
        array $data,
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
        $record->timecreated      = time();

        return (int) $DB->insert_record(self::TABLE, $record);
    }

    // -------------------------------------------------------------------------
    // Public API — query_longitudinal()
    // -------------------------------------------------------------------------

    /**
     * Query historical learning records within a time range.
     *
     * Retrieves records for a specific learner and course where timecreated
     * falls between $from and $to (inclusive).
     *
     * Optional filters:
     * - 'record_type'      (string) — filter by exact record_type
     * - 'source_component' (string) — filter by exact source_component
     *
     * Returns records ordered by timecreated ASC with data_payload decoded from JSON.
     *
     * @param  int   $userid   Moodle user ID.
     * @param  int   $courseid Moodle course ID.
     * @param  int   $from     Unix timestamp — start of range (inclusive).
     * @param  int   $to       Unix timestamp — end of range (inclusive).
     * @param  array $filters  Optional associative array of additional filters.
     * @return array           Array of record objects with decoded data_payload.
     */
    public function query_longitudinal(
        int $userid,
        int $courseid,
        int $from,
        int $to,
        array $filters = []
    ): array {
        global $DB, $USER;

        $params = [
            'userid'    => $userid,
            'courseid'  => $courseid,
            'from_time' => $from,
            'to_time'   => $to,
        ];

        $where_clauses = [
            'userid = :userid',
            'courseid = :courseid',
            'timecreated >= :from_time',
            'timecreated <= :to_time',
        ];

        // Apply optional filters.
        if (!empty($filters['record_type'])) {
            $where_clauses[]       = 'record_type = :record_type';
            $params['record_type'] = (string) $filters['record_type'];
        }

        if (!empty($filters['source_component'])) {
            $where_clauses[]            = 'source_component = :source_component';
            $params['source_component'] = (string) $filters['source_component'];
        }

        $sql = "SELECT *
                  FROM {" . self::TABLE . "}
                 WHERE " . implode(' AND ', $where_clauses) . "
                 ORDER BY timecreated ASC";

        $rows = $DB->get_records_sql($sql, $params);

        // Decode data_payload for each record.
        $results = [];
        foreach ($rows as $row) {
            $row->data_payload = json_decode($row->data_payload, true) ?? [];
            $results[]         = $row;
        }

        // Log this access for audit purposes (Req 15.4).
        $accessor_userid = isset($USER->id) ? (int) $USER->id : 0;
        $this->log_access(
            $accessor_userid,
            $userid,
            $courseid,
            'query_longitudinal',
            count($results),
            $filters['record_type'] ?? null,
            $filters['source_component'] ?? null
        );

        return $results;
    }

    // -------------------------------------------------------------------------
    // Public API — export_csv()
    // -------------------------------------------------------------------------

    /**
     * Export all records for a learner in CSV format.
     *
     * Builds a CSV string with headers:
     * id, userid, courseid, record_type, source_component, profile_version,
     * timecreated, data_payload
     *
     * Uses fputcsv() with a php://memory stream for RFC 4180-compliant output.
     * The data_payload column contains the raw JSON string from the database.
     *
     * @param  int $userid   Moodle user ID.
     * @param  int $courseid Moodle course ID.
     * @return string        CSV string including header row.
     */
    public function export_csv(int $userid, int $courseid): string {
        global $USER;

        $records = $this->get_all_records($userid, $courseid);

        $handle = fopen('php://memory', 'w');

        // Write header row.
        fputcsv($handle, ['id', 'userid', 'courseid', 'record_type', 'source_component',
                          'profile_version', 'timecreated', 'data_payload']);

        // Write data rows.
        foreach ($records as $record) {
            fputcsv($handle, [
                $record->id,
                $record->userid,
                $record->courseid,
                $record->record_type,
                $record->source_component,
                $record->profile_version ?? '',
                $record->timecreated,
                // data_payload is stored as JSON string in DB; use it directly.
                $record->data_payload,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        // Log this access for audit purposes (Req 15.4).
        $accessor_userid = isset($USER->id) ? (int) $USER->id : 0;
        $this->log_access(
            $accessor_userid,
            $userid,
            $courseid,
            'export_csv',
            count($records)
        );

        return $csv;
    }

    // -------------------------------------------------------------------------
    // Public API — export_json()
    // -------------------------------------------------------------------------

    /**
     * Export all records for a learner in JSON format.
     *
     * Returns a JSON-encoded array of records. Each record has data_payload
     * decoded from JSON (not double-encoded).
     *
     * @param  int $userid   Moodle user ID.
     * @param  int $courseid Moodle course ID.
     * @return string        JSON-encoded string.
     */
    public function export_json(int $userid, int $courseid): string {
        global $USER;

        $records = $this->get_all_records($userid, $courseid);

        $decoded_records = [];
        foreach ($records as $record) {
            $decoded_record               = clone $record;
            $decoded_record->data_payload = json_decode($record->data_payload, true) ?? [];
            $decoded_records[]            = $decoded_record;
        }

        // Log this access for audit purposes (Req 15.4).
        $accessor_userid = isset($USER->id) ? (int) $USER->id : 0;
        $this->log_access(
            $accessor_userid,
            $userid,
            $courseid,
            'export_json',
            count($records)
        );

        return json_encode($decoded_records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    // -------------------------------------------------------------------------
    // Public API — check_integrity()
    // -------------------------------------------------------------------------

    /**
     * Check the integrity of all learner records in the table.
     *
     * Integrity checks performed:
     * 1. Records with NULL or empty data_payload → log warning via debugging().
     * 2. Records with timecreated = 0 or NULL → log warning via debugging().
     *
     * @return bool True if no integrity issues found, false if issues detected.
     */
    public function check_integrity(): bool {
        global $DB;

        $issues_found = false;

        // Check 1: Records with NULL or empty data_payload.
        $sql_empty_payload = "SELECT COUNT(*) FROM {" . self::TABLE . "}
                               WHERE data_payload IS NULL OR data_payload = ''";
        $count_empty = (int) $DB->count_records_sql($sql_empty_payload);

        if ($count_empty > 0) {
            debugging(
                "ACMLS LearnerRecord integrity check: {$count_empty} record(s) with NULL or empty data_payload detected.",
                DEBUG_DEVELOPER
            );
            $issues_found = true;
        }

        // Check 2: Records with timecreated = 0 or NULL.
        $sql_zero_time = "SELECT COUNT(*) FROM {" . self::TABLE . "}
                           WHERE timecreated = 0 OR timecreated IS NULL";
        $count_zero_time = (int) $DB->count_records_sql($sql_zero_time);

        if ($count_zero_time > 0) {
            debugging(
                "ACMLS LearnerRecord integrity check: {$count_zero_time} record(s) with timecreated = 0 or NULL detected.",
                DEBUG_DEVELOPER
            );
            $issues_found = true;
        }

        return !$issues_found;
    }

    // -------------------------------------------------------------------------
    // Public API — apply_access_control()
    // -------------------------------------------------------------------------

    /**
     * Check if a user has access to Learner Record analytics.
     *
     * Uses Moodle's has_capability() to verify the requester has the
     * 'block/attendanceleaderboard:viewanalytics' capability in the course context.
     *
     * @param  int  $requester_userid Moodle user ID of the person requesting access.
     * @param  int  $courseid         Moodle course ID.
     * @return bool                   True if access is granted, false otherwise.
     */
    public function apply_access_control(int $requester_userid, int $courseid): bool {
        $context = \context_course::instance($courseid);
        return has_capability(
            'block/attendanceleaderboard:viewanalytics',
            $context,
            $requester_userid
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Log an access event to the acmls_data_access_log table.
     *
     * Records who accessed Learner_Record data, when, and what was accessed.
     * This satisfies Requirement 15.4: audit log for Learner_Record access.
     *
     * This method is non-blocking: any database exception is silently caught
     * so that a logging failure never prevents the actual data access from
     * succeeding.
     *
     * @param  int         $accessor_userid         Moodle user ID of the person accessing the data.
     * @param  int         $target_userid           Moodle user ID of the learner whose data is accessed.
     * @param  int         $courseid                Moodle course ID.
     * @param  string      $access_type             Type of access: 'query_longitudinal', 'export_csv', 'export_json'.
     * @param  int         $records_returned        Number of records returned by the access operation.
     * @param  string|null $record_type_filter      Optional record_type filter applied during the access.
     * @param  string|null $source_component_filter Optional source_component filter applied during the access.
     * @return void
     */
    private function log_access(
        int $accessor_userid,
        int $target_userid,
        int $courseid,
        string $access_type,
        int $records_returned = 0,
        ?string $record_type_filter = null,
        ?string $source_component_filter = null
    ): void {
        global $DB;

        try {
            $log = new \stdClass();
            $log->accessor_userid          = $accessor_userid;
            $log->target_userid            = $target_userid;
            $log->courseid                 = $courseid;
            $log->access_type              = $access_type;
            $log->record_type_filter       = $record_type_filter;
            $log->source_component_filter  = $source_component_filter;
            $log->records_returned         = $records_returned;
            $log->timecreated              = time();

            $DB->insert_record('acmls_data_access_log', $log);
        } catch (\Throwable $e) {
            // Non-blocking: log the error for developers but do not propagate.
            debugging(
                'ACMLS LearnerRecord: failed to write data access log — ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Retrieve all records for a learner/course ordered by timecreated ASC.
     *
     * Used internally by export_csv() and export_json().
     * Returns raw DB records (data_payload as JSON string).
     *
     * @param  int $userid   Moodle user ID.
     * @param  int $courseid Moodle course ID.
     * @return array         Array of raw stdClass records.
     */
    private function get_all_records(int $userid, int $courseid): array {
        global $DB;

        $sql = "SELECT *
                  FROM {" . self::TABLE . "}
                 WHERE userid = :userid
                   AND courseid = :courseid
                 ORDER BY timecreated ASC";

        return array_values($DB->get_records_sql($sql, [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]));
    }
}
