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
 * Configuration Audit Log for block_attendanceleaderboard (ACMLS).
 *
 * Records every configuration change made by an administrator, capturing:
 *  - who made the change (userid)
 *  - when it was made (timecreated)
 *  - what was changed (setting_name, old_value, new_value)
 *  - optional context information
 *
 * Satisfies Requirement 13.3: "WHEN administrator mengubah konfigurasi sistem,
 * THE ACMLS SHALL mencatat perubahan konfigurasi di log audit."
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages the configuration audit log for ACMLS admin settings.
 *
 * Writes audit entries to the `acmls_config_audit_log` table and provides
 * retrieval methods for display in the admin dashboard.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config_audit_log {

    /** @var string The database table name (without Moodle prefix). */
    const TABLE = 'acmls_config_audit_log';

    /**
     * Log a configuration change to the audit table.
     *
     * @param  int    $userid       The ID of the administrator who made the change.
     * @param  string $setting_name The full config key (e.g. 'block_attendanceleaderboard/llm_provider').
     * @param  string $old_value    The previous value of the setting (empty string if unknown/new).
     * @param  string $new_value    The new value of the setting.
     * @param  string $context      Optional context string (e.g. 'admin_settings_page').
     * @return int|false            The new record ID on success, false on failure.
     */
    public static function log_change(
        int $userid,
        string $setting_name,
        string $old_value,
        string $new_value,
        string $context = ''
    ) {
        global $DB;

        // Skip logging if old and new values are identical — no real change occurred.
        if ($old_value === $new_value) {
            return false;
        }

        $record = new \stdClass();
        $record->userid       = $userid;
        $record->setting_name = $setting_name;
        $record->old_value    = $old_value;
        $record->new_value    = $new_value;
        $record->context      = $context;
        $record->timecreated  = time();

        try {
            return $DB->insert_record(self::TABLE, $record);
        } catch (\Exception $e) {
            debugging(
                'ACMLS config_audit_log::log_change failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return false;
        }
    }

    /**
     * Retrieve audit log entries with optional filtering.
     *
     * @param  array $filters  Associative array of filter conditions. Supported keys:
     *                         - 'userid'       (int)    Filter by administrator user ID.
     *                         - 'setting_name' (string) Filter by exact setting name.
     *                         - 'since'        (int)    Unix timestamp — only entries after this time.
     *                         - 'until'        (int)    Unix timestamp — only entries before this time.
     * @param  int   $limit    Maximum number of records to return. Default: 100.
     * @param  int   $offset   Number of records to skip (for pagination). Default: 0.
     * @return array           Array of stdClass objects representing audit log entries.
     */
    public static function get_logs(array $filters = [], int $limit = 100, int $offset = 0): array {
        global $DB;

        $where  = [];
        $params = [];

        if (!empty($filters['userid'])) {
            $where[]          = 'l.userid = :userid';
            $params['userid'] = (int) $filters['userid'];
        }

        if (!empty($filters['setting_name'])) {
            $where[]               = 'l.setting_name = :setting_name';
            $params['setting_name'] = $filters['setting_name'];
        }

        if (!empty($filters['since'])) {
            $where[]         = 'l.timecreated >= :since';
            $params['since'] = (int) $filters['since'];
        }

        if (!empty($filters['until'])) {
            $where[]         = 'l.timecreated <= :until';
            $params['until'] = (int) $filters['until'];
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT l.id,
                   l.userid,
                   l.setting_name,
                   l.old_value,
                   l.new_value,
                   l.context,
                   l.timecreated,
                   " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS user_fullname,
                   u.username
              FROM {" . self::TABLE . "} l
              JOIN {user} u ON u.id = l.userid
            $where_sql
             ORDER BY l.timecreated DESC
        ";

        return array_values($DB->get_records_sql($sql, $params, $offset, $limit));
    }

    /**
     * Retrieve the change history for a specific setting.
     *
     * @param  string $setting_name The full config key to retrieve history for.
     * @param  int    $limit        Maximum number of records to return. Default: 50.
     * @return array                Array of stdClass objects, newest first.
     */
    public static function get_logs_for_setting(string $setting_name, int $limit = 50): array {
        return self::get_logs(['setting_name' => $setting_name], $limit);
    }

    /**
     * Count the total number of audit log entries matching the given filters.
     *
     * Useful for building paginated views.
     *
     * @param  array $filters Same filter keys as get_logs().
     * @return int            Total count of matching records.
     */
    public static function count_logs(array $filters = []): int {
        global $DB;

        $where  = [];
        $params = [];

        if (!empty($filters['userid'])) {
            $where[]          = 'userid = :userid';
            $params['userid'] = (int) $filters['userid'];
        }

        if (!empty($filters['setting_name'])) {
            $where[]               = 'setting_name = :setting_name';
            $params['setting_name'] = $filters['setting_name'];
        }

        if (!empty($filters['since'])) {
            $where[]         = 'timecreated >= :since';
            $params['since'] = (int) $filters['since'];
        }

        if (!empty($filters['until'])) {
            $where[]         = 'timecreated <= :until';
            $params['until'] = (int) $filters['until'];
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {" . self::TABLE . "} $where_sql",
            $params
        );
    }
}
