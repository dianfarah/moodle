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
 * Learning Resource Repository for ACMLS.
 *
 * Stores metadata for learning resources available in Moodle and provides
 * profile-based query mechanisms to support Coach recommendations.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\repository;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\profiling\learner_profile;

/**
 * Repository for acmls_learning_resource table.
 *
 * Provides CRUD operations and profile-based querying for learning resources.
 * Supports auto-indexing of new Moodle course modules via event observer.
 *
 * Requirements addressed:
 * - Req 6.1: Learning Resource Repository with CRUD and profile-based query.
 * - Req 6.2: Auto-indexing of new resources on course_module_created event.
 * - Req 6.3: find_by_profile() with response time ≤3 seconds.
 * - Req 6.4: get_effectiveness_data() correlating resource access with performance.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_resource_repository {

    /** @var string Database table name (without Moodle prefix). */
    const TABLE = 'acmls_learning_resource';

    // -------------------------------------------------------------------------
    // Resource type → learning style mapping.
    // -------------------------------------------------------------------------

    /**
     * Map resource type to default learning styles.
     *
     * @var array<string, string[]>
     */
    private static array $type_to_styles = [
        'video'     => ['auditory'],
        'audio'     => ['auditory'],
        'document'  => ['reading'],
        'pdf'       => ['reading'],
        'resource'  => ['reading'],
        'page'      => ['reading'],
        'book'      => ['reading'],
        'quiz'      => ['kinesthetic'],
        'assign'    => ['kinesthetic'],
        'workshop'  => ['kinesthetic'],
        'forum'     => ['kinesthetic', 'reading'],
        'chat'      => ['kinesthetic', 'reading'],
    ];

    // -------------------------------------------------------------------------
    // Public API — CRUD
    // -------------------------------------------------------------------------

    /**
     * Add a new learning resource record.
     *
     * Inserts a record into acmls_learning_resource with the provided metadata.
     * Fields: cmid, courseid, title, resource_type, difficulty_level, topic_tags,
     * learning_styles, access_count=0, is_active=1, timecreated, timemodified.
     *
     * @param  int   $cmid     FK to mdl_course_modules.id.
     * @param  array $metadata Associative array with resource metadata.
     * @return int             Inserted record ID.
     */
    public function add_resource(int $cmid, array $metadata): int {
        global $DB;

        $now = time();

        $record = new \stdClass();
        $record->cmid             = $cmid;
        $record->courseid         = (int) ($metadata['courseid'] ?? 0);
        $record->title            = (string) ($metadata['title'] ?? '');
        $record->resource_type    = (string) ($metadata['resource_type'] ?? 'resource');
        $record->difficulty_level = (int) ($metadata['difficulty_level'] ?? 1);
        $record->topic_tags       = json_encode($metadata['topic_tags'] ?? []);
        $record->learning_styles  = json_encode($metadata['learning_styles'] ?? ['reading']);
        $record->access_count     = 0;
        $record->avg_rating       = null;
        $record->effectiveness_score = null;
        $record->is_active        = 1;
        $record->timecreated      = $now;
        $record->timemodified     = $now;

        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update fields of an existing learning resource.
     *
     * @param  int   $id   Record ID in acmls_learning_resource.
     * @param  array $data Associative array of fields to update.
     * @return bool        True on success, false if record not found.
     */
    public function update_resource(int $id, array $data): bool {
        global $DB;

        if (!$DB->record_exists(self::TABLE, ['id' => $id])) {
            return false;
        }

        $record = new \stdClass();
        $record->id = $id;
        $record->timemodified = time();

        $allowed = [
            'title', 'resource_type', 'difficulty_level',
            'topic_tags', 'learning_styles', 'is_active',
            'avg_rating', 'effectiveness_score',
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                if (in_array($field, ['topic_tags', 'learning_styles']) && is_array($data[$field])) {
                    $record->$field = json_encode($data[$field]);
                } else {
                    $record->$field = $data[$field];
                }
            }
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * Soft-delete a learning resource by marking it inactive.
     *
     * Sets is_active = 0 rather than physically deleting the record,
     * preserving historical data for analytics.
     *
     * @param  int  $id Record ID in acmls_learning_resource.
     * @return bool     True on success, false if record not found.
     */
    public function delete_resource(int $id): bool {
        global $DB;

        if (!$DB->record_exists(self::TABLE, ['id' => $id])) {
            return false;
        }

        $record = new \stdClass();
        $record->id           = $id;
        $record->is_active    = 0;
        $record->timemodified = time();

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * Record a Learner's access to a resource.
     *
     * Increments access_count and updates timemodified.
     *
     * @param  int $resource_id ID of the learning resource.
     * @param  int $userid      Moodle user ID (reserved for future per-user tracking).
     * @return void
     */
    public function record_access(int $resource_id, int $userid): void {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $resource_id]);
        if (!$record) {
            return;
        }

        $update = new \stdClass();
        $update->id           = $resource_id;
        $update->access_count = (int) $record->access_count + 1;
        $update->timemodified = time();

        $DB->update_record(self::TABLE, $update);
    }

    // -------------------------------------------------------------------------
    // Public API — Profile-based query (Task 6.3)
    // -------------------------------------------------------------------------

    /**
     * Find learning resources matching a Learner's profile.
     *
     * Logic:
     * 1. Map performance_category to difficulty_level range:
     *    - Low (1)    → difficulty_level IN (1)
     *    - Middle (2) → difficulty_level IN (1, 2)
     *    - High (3)   → difficulty_level IN (2, 3)
     * 2. Filter active resources within the difficulty range.
     * 3. Among those, prefer resources whose learning_styles JSON contains
     *    the Learner's learning_style.
     * 4. Order by effectiveness_score DESC, access_count DESC.
     * 5. If no style-matched resources found, fall back to difficulty-only.
     * 6. Return up to $limit resources.
     *
     * @param  learner_profile $profile Learner's current profile.
     * @param  int             $limit   Maximum number of resources to return.
     * @return array                    Array of resource records (stdClass).
     */
    public function find_by_profile(learner_profile $profile, int $limit = 5): array {
        global $DB;

        $difficulty_levels = $this->map_performance_to_difficulty($profile->performance_category);

        if (empty($difficulty_levels)) {
            return [];
        }

        list($in_sql, $params) = $DB->get_in_or_equal($difficulty_levels, SQL_PARAMS_NAMED, 'diff');
        $params['is_active'] = 1;

        // Fetch all active resources matching difficulty range.
        $sql = "SELECT *
                  FROM {" . self::TABLE . "}
                 WHERE difficulty_level {$in_sql}
                   AND is_active = :is_active
                 ORDER BY effectiveness_score DESC, access_count DESC";

        $all_resources = array_values($DB->get_records_sql($sql, $params));

        if (empty($all_resources)) {
            return [];
        }

        // Try to filter by learning_style.
        $style = $profile->learning_style;
        if ($style !== learner_profile::STYLE_UNKNOWN) {
            $style_matched = array_filter($all_resources, function ($r) use ($style) {
                $styles = json_decode($r->learning_styles ?? '[]', true);
                return is_array($styles) && in_array($style, $styles, true);
            });

            if (!empty($style_matched)) {
                return array_slice(array_values($style_matched), 0, $limit);
            }
        }

        // Fallback: return difficulty-only matches.
        return array_slice($all_resources, 0, $limit);
    }

    // -------------------------------------------------------------------------
    // Public API — Auto-indexing (Task 6.2)
    // -------------------------------------------------------------------------

    /**
     * Auto-index a new course module as a learning resource.
     *
     * Called by the course_module_created event observer. Loads the course_modules
     * record, determines the module type and title, then inserts (or updates if
     * already indexed) a record in acmls_learning_resource.
     *
     * UPSERT pattern: if cmid already exists, update; otherwise insert.
     *
     * @param  int $cmid Course module ID (mdl_course_modules.id).
     * @return int       ID of the inserted or updated acmls_learning_resource record.
     */
    public function auto_index_new_resource(int $cmid): int {
        global $DB;

        // Load the course_modules record.
        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', IGNORE_MISSING);
        if (!$cm) {
            debugging("auto_index_new_resource: course_module {$cmid} not found.", DEBUG_DEVELOPER);
            return 0;
        }

        // Get module name from mdl_modules.
        $module = $DB->get_record('modules', ['id' => $cm->module], 'name', IGNORE_MISSING);
        $module_name = $module ? (string) $module->name : 'resource';

        // Get the instance title from the module's own table.
        $title = $this->get_module_instance_title($module_name, (int) $cm->instance);

        // Determine resource_type from module name.
        $resource_type = $this->determine_resource_type($module_name);

        // Determine default learning_styles from resource_type.
        $learning_styles = $this->get_default_learning_styles($resource_type);

        $metadata = [
            'courseid'         => (int) $cm->course,
            'title'            => $title,
            'resource_type'    => $resource_type,
            'difficulty_level' => 1,
            'topic_tags'       => [],
            'learning_styles'  => $learning_styles,
        ];

        // UPSERT: check if cmid already exists.
        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid], 'id', IGNORE_MISSING);
        if ($existing) {
            $this->update_resource((int) $existing->id, $metadata);
            return (int) $existing->id;
        }

        return $this->add_resource($cmid, $metadata);
    }

    // -------------------------------------------------------------------------
    // Public API — Effectiveness data (Task 6.4)
    // -------------------------------------------------------------------------

    /**
     * Calculate effectiveness data for a learning resource.
     *
     * Queries acmls_learner_record for performance records of users who accessed
     * this resource, compares performance before and after access, and returns
     * an effectiveness score.
     *
     * Algorithm:
     * 1. Get the resource record (access_count, timecreated).
     * 2. For each user who accessed the resource (via activity_log), find their
     *    performance records before and after the first access.
     * 3. Calculate average improvement percentage.
     * 4. effectiveness_score = avg improvement clamped to [0, 100].
     * 5. If no data, return effectiveness_score = null.
     *
     * @param  int   $resource_id ID of the learning resource.
     * @return array {
     *     resource_id: int,
     *     access_count: int,
     *     avg_improvement: float|null,
     *     effectiveness_score: float|null
     * }
     */
    public function get_effectiveness_data(int $resource_id): array {
        global $DB;

        $resource = $DB->get_record(self::TABLE, ['id' => $resource_id], '*', IGNORE_MISSING);

        $result = [
            'resource_id'        => $resource_id,
            'access_count'       => $resource ? (int) $resource->access_count : 0,
            'avg_improvement'    => null,
            'effectiveness_score' => null,
        ];

        if (!$resource) {
            return $result;
        }

        // Find users who accessed this resource via acmls_activity_log.
        $sql_users = "SELECT DISTINCT userid
                        FROM {acmls_activity_log}
                       WHERE objectid = :cmid
                         AND event_type = 'course_module_viewed'";

        $user_records = $DB->get_records_sql($sql_users, ['cmid' => (int) $resource->cmid]);

        if (empty($user_records)) {
            return $result;
        }

        $improvements = [];

        foreach ($user_records as $user_row) {
            $userid = (int) $user_row->userid;

            // Find the first access time for this user and resource.
            $first_access = $DB->get_field_sql(
                "SELECT MIN(timecreated)
                   FROM {acmls_activity_log}
                  WHERE userid = :userid
                    AND objectid = :cmid
                    AND event_type = 'course_module_viewed'",
                ['userid' => $userid, 'cmid' => (int) $resource->cmid]
            );

            if (!$first_access) {
                continue;
            }

            // Get performance score before first access.
            $before_score = $DB->get_field_sql(
                "SELECT AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(data_payload, '$.score')) AS DECIMAL(10,2)))
                   FROM {acmls_learner_record}
                  WHERE userid = :userid
                    AND record_type = 'performance'
                    AND timecreated < :access_time",
                ['userid' => $userid, 'access_time' => $first_access]
            );

            // Get performance score after first access.
            $after_score = $DB->get_field_sql(
                "SELECT AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(data_payload, '$.score')) AS DECIMAL(10,2)))
                   FROM {acmls_learner_record}
                  WHERE userid = :userid
                    AND record_type = 'performance'
                    AND timecreated >= :access_time",
                ['userid' => $userid, 'access_time' => $first_access]
            );

            if ($before_score !== false && $after_score !== false
                && $before_score !== null && $after_score !== null
                && (float) $before_score > 0) {
                $improvement = (((float) $after_score - (float) $before_score) / (float) $before_score) * 100.0;
                $improvements[] = $improvement;
            }
        }

        if (empty($improvements)) {
            return $result;
        }

        $avg_improvement = array_sum($improvements) / count($improvements);
        $effectiveness_score = max(0.0, min(100.0, $avg_improvement));

        $result['avg_improvement']    = round($avg_improvement, 2);
        $result['effectiveness_score'] = round($effectiveness_score, 2);

        // Persist the effectiveness_score back to the resource record.
        $DB->set_field(self::TABLE, 'effectiveness_score', $effectiveness_score, ['id' => $resource_id]);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Map performance_category to allowed difficulty_level values.
     *
     * - Low (1)    → [1]
     * - Middle (2) → [1, 2]
     * - High (3)   → [2, 3]
     *
     * @param  int   $performance_category 1=Low, 2=Middle, 3=High.
     * @return int[]                       Array of allowed difficulty levels.
     */
    private function map_performance_to_difficulty(int $performance_category): array {
        switch ($performance_category) {
            case learner_profile::PERFORMANCE_LOW:
                return [1];
            case learner_profile::PERFORMANCE_MIDDLE:
                return [1, 2];
            case learner_profile::PERFORMANCE_HIGH:
                return [2, 3];
            default:
                return [1];
        }
    }

    /**
     * Determine resource_type from Moodle module name.
     *
     * @param  string $module_name Moodle module name (e.g., 'quiz', 'resource').
     * @return string              Resource type string.
     */
    private function determine_resource_type(string $module_name): string {
        $known_types = [
            'quiz', 'assign', 'workshop', 'forum', 'chat',
            'video', 'audio', 'document', 'pdf', 'page', 'book', 'resource',
        ];

        if (in_array($module_name, $known_types, true)) {
            return $module_name;
        }

        return 'resource';
    }

    /**
     * Get default learning styles for a given resource type.
     *
     * @param  string   $resource_type Resource type string.
     * @return string[]                Array of learning style strings.
     */
    private function get_default_learning_styles(string $resource_type): array {
        return self::$type_to_styles[$resource_type] ?? ['reading'];
    }

    /**
     * Retrieve the title of a module instance from its own table.
     *
     * Attempts to read the 'name' field from the module's table (e.g., mdl_quiz,
     * mdl_resource, mdl_page). Falls back to 'Untitled Resource' on failure.
     *
     * @param  string $module_name Module name (table name without prefix).
     * @param  int    $instance    Module instance ID.
     * @return string              Title of the module instance.
     */
    private function get_module_instance_title(string $module_name, int $instance): string {
        global $DB;

        // Validate module_name to prevent SQL injection (only allow alphanumeric + underscore).
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $module_name)) {
            return 'Untitled Resource';
        }

        try {
            $name = $DB->get_field($module_name, 'name', ['id' => $instance]);
            if ($name !== false && $name !== null) {
                return (string) $name;
            }
        } catch (\dml_exception $e) {
            debugging("auto_index_new_resource: could not get title from {$module_name}: " . $e->getMessage(),
                DEBUG_DEVELOPER);
        }

        return 'Untitled Resource';
    }
}
