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
 * TrackingSystem — collects and records Learner activity logs.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tracking;

defined('MOODLE_INTERNAL') || die();

/**
 * Tracking System for ACMLS.
 *
 * Responsible for:
 * - Receiving Moodle events and dispatching them to the appropriate record methods.
 * - Persisting ActivityLog entries to the acmls_activity_log database table.
 * - Flushing pending logs to the Profiling System with retry support.
 *
 * Requirements addressed:
 * - Req 1.2: Record login time, identity, and session context.
 * - Req 1.4: Monitor and record all Learner interactions continuously.
 * - Req 1.5: Record session end and send Activity_Log summary to Profiling System.
 * - Req 2.1: Record every Learner access to Learning_Resource.
 * - Req 2.2: Record activity type, completion time, and result.
 * - Req 2.4: Send Activity_Log to Profiling System within ≤5 minutes.
 * - Req 2.5: If Profiling System unavailable, store locally and retry when connection recovers.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tracking_system {

    /** @var string Database table name for activity logs. */
    const TABLE = 'acmls_activity_log';

    /**
     * Map of Moodle event class names to handler method names.
     *
     * @var array<string, string>
     */
    private static array $event_map = [
        '\core\event\user_loggedin'                      => 'record_login_event',
        '\core\event\user_loggedout'                     => 'record_logout_event',
        '\core\event\course_module_viewed'               => 'record_module_viewed',
        '\mod_quiz\event\attempt_submitted'              => 'record_quiz_submitted',
        '\core\event\grade_item_updated'                 => 'record_grade_updated',
        '\mod_forum\event\post_created'                  => 'record_forum_post',
        '\core\event\course_module_completion_updated'   => 'record_completion_updated',
    ];

    /**
     * Dispatch a Moodle event to the appropriate record method.
     *
     * @param  \core\event\base $event The Moodle event instance.
     * @return void
     */
    public function handle_event(\core\event\base $event): void {
        $classname = '\\' . ltrim(get_class($event), '\\');

        if (!isset(self::$event_map[$classname])) {
            // Unknown event — record as generic activity.
            $this->record_activity(
                (int) $event->userid,
                (int) $event->courseid,
                $classname,
                $event->component,
                [
                    'objectid' => $event->objectid,
                    'action'   => $event->action,
                ]
            );
            return;
        }

        $method = self::$event_map[$classname];
        $this->$method($event);
    }

    /**
     * Record a login event for a Learner.
     *
     * @param  int   $userid   Moodle user ID.
     * @param  int   $courseid Course ID (0 if not course-specific).
     * @param  array $context  Additional context data.
     * @return int             ID of the inserted database record.
     */
    public function record_login(int $userid, int $courseid, array $context = []): int {
        $log = new activity_log($userid, $courseid, 'user_loggedin', 'core', 'loggedin');
        $log->context_data = $context;
        return $this->save_log($log);
    }

    /**
     * Record any generic activity for a Learner.
     *
     * @param  int    $userid     Moodle user ID.
     * @param  int    $courseid   Course ID.
     * @param  string $event_type Event type string.
     * @param  string $component  Moodle component name.
     * @param  array  $data       Additional data (objectid, action, result_value, duration_seconds, etc.).
     * @return int                ID of the inserted database record.
     */
    public function record_activity(
        int $userid,
        int $courseid,
        string $event_type,
        string $component,
        array $data = []
    ): int {
        $action = $data['action'] ?? 'activity';

        $log = new activity_log($userid, $courseid, $event_type, $component, $action);

        if (isset($data['objectid'])) {
            $log->objectid = (int) $data['objectid'];
        }
        if (isset($data['duration_seconds'])) {
            $log->duration_seconds = (int) $data['duration_seconds'];
        }
        if (isset($data['result_value'])) {
            $log->result_value = (float) $data['result_value'];
        }

        // Store remaining data as context_data (exclude known top-level keys).
        $context_keys = ['action', 'objectid', 'duration_seconds', 'result_value'];
        $log->context_data = array_diff_key($data, array_flip($context_keys));

        return $this->save_log($log);
    }

    /**
     * Retrieve all ActivityLog entries not yet sent to the Profiling System.
     *
     * @return activity_log[] Array of pending ActivityLog instances.
     */
    public function get_pending_logs(): array {
        global $DB;

        $records = $DB->get_records(self::TABLE, ['sent_to_profiler' => 0]);

        $logs = [];
        foreach ($records as $record) {
            $logs[] = activity_log::from_db_record($record);
        }

        return $logs;
    }

    /**
     * Flush pending ActivityLog entries to the Profiling System.
     *
     * Sends each pending log to ProfilingSystem::process_activity_log().
     * If the Profiling System is unavailable (class not found or exception thrown),
     * the logs remain with sent_to_profiler=0 so they will be retried on the next run.
     *
     * Implements Req 2.4 (≤5 min interval via scheduled task) and
     * Req 2.5 (local storage + retry on connection recovery).
     *
     * @return void
     */
    public function flush_to_profiler(): void {
        global $DB;

        // Guard: ProfilingSystem may not be implemented yet.
        if (!class_exists('\block_attendanceleaderboard\profiling\profiling_system')) {
            // Nothing to flush to — logs remain pending for retry.
            return;
        }

        $pending = $this->get_pending_logs();

        if (empty($pending)) {
            return;
        }

        $profiler = new \block_attendanceleaderboard\profiling\profiling_system();

        foreach ($pending as $log) {
            try {
                $profiler->process_activity_log($log);

                // Mark as sent only after successful processing.
                $DB->set_field(self::TABLE, 'sent_to_profiler', 1, ['id' => $log->id]);
                $log->sent_to_profiler = true;

            } catch (\Exception $e) {
                // Profiling System unavailable or threw an error.
                // Leave sent_to_profiler=0 so this log will be retried next time.
                debugging(
                    'ACMLS TrackingSystem: Failed to flush log ID ' . $log->id .
                    ' to ProfilingSystem: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
                // Stop processing further logs in this batch to avoid cascading failures.
                break;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private event handler methods
    // -------------------------------------------------------------------------

    /**
     * Handle user_loggedin event.
     *
     * @param  \core\event\base $event
     * @return void
     */
    private function record_login_event(\core\event\base $event): void {
        $this->record_login(
            (int) $event->userid,
            (int) $event->courseid,
            [
                'ip'        => $event->get_data()['other']['username'] ?? '',
                'sessionid' => session_id(),
            ]
        );
    }

    /**
     * Handle user_loggedout event and calculate session duration.
     *
     * Looks up the most recent login log for this user to compute duration_seconds.
     *
     * @param  \core\event\base $event
     * @return void
     */
    private function record_logout_event(\core\event\base $event): void {
        global $DB;

        $userid   = (int) $event->userid;
        $courseid = (int) $event->courseid;

        // Calculate session duration from the last login record.
        $duration = 0;
        $last_login = $DB->get_record_sql(
            'SELECT timecreated FROM {' . self::TABLE . '}
              WHERE userid = :userid
                AND event_type = :event_type
           ORDER BY timecreated DESC',
            ['userid' => $userid, 'event_type' => 'user_loggedin'],
            IGNORE_MULTIPLE
        );

        if ($last_login) {
            $duration = max(0, time() - (int) $last_login->timecreated);
        }

        $log = new activity_log($userid, $courseid, 'user_loggedout', 'core', 'loggedout');
        $log->duration_seconds = $duration;
        $log->context_data     = ['session_duration' => $duration];

        $this->save_log($log);
    }

    /**
     * Handle course_module_viewed event.
     *
     * @param  \core\event\base $event
     * @return void
     */
    private function record_module_viewed(\core\event\base $event): void {
        $this->record_activity(
            (int) $event->userid,
            (int) $event->courseid,
            'course_module_viewed',
            $event->component,
            [
                'objectid'  => $event->objectid,
                'action'    => 'viewed',
                'contextid' => $event->contextid,
            ]
        );
    }

    /**
     * Handle quiz attempt_submitted event.
     *
     * @param  \core\event\base $event
     * @return void
     */
    private function record_quiz_submitted(\core\event\base $event): void {
        $data = $event->get_data();
        $this->record_activity(
            (int) $event->userid,
            (int) $event->courseid,
            'quiz_attempt_submitted',
            'mod_quiz',
            [
                'objectid' => $event->objectid,
                'action'   => 'submitted',
                'other'    => $data['other'] ?? [],
            ]
        );
    }

    /**
     * Handle grade_item_updated event.
     *
     * @param  \core\event\base $event
     * @return void
     */
    private function record_grade_updated(\core\event\base $event): void {
        $data = $event->get_data();
        $this->record_activity(
            (int) $event->userid,
            (int) $event->courseid,
            'grade_item_updated',
            'core',
            [
                'objectid' => $event->objectid,
                'action'   => 'updated',
                'other'    => $data['other'] ?? [],
            ]
        );
    }

    /**
     * Handle forum post_created event.
     *
     * @param  \core\event\base $event
     * @return void
     */
    private function record_forum_post(\core\event\base $event): void {
        $this->record_activity(
            (int) $event->userid,
            (int) $event->courseid,
            'forum_post_created',
            'mod_forum',
            [
                'objectid' => $event->objectid,
                'action'   => 'created',
            ]
        );
    }

    /**
     * Handle course_module_completion_updated event.
     *
     * @param  \core\event\base $event
     * @return void
     */
    private function record_completion_updated(\core\event\base $event): void {
        $data = $event->get_data();
        $this->record_activity(
            (int) $event->userid,
            (int) $event->courseid,
            'course_module_completion_updated',
            'core',
            [
                'objectid'          => $event->objectid,
                'action'            => 'completed',
                'completion_state'  => $data['other']['completionstate'] ?? 0,
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Persist an ActivityLog to the database.
     *
     * @param  activity_log $log The log entry to save.
     * @return int               The new record ID.
     */
    private function save_log(activity_log $log): int {
        global $DB;

        $record = $log->to_db_record();
        $id = $DB->insert_record(self::TABLE, $record);
        $log->id = (int) $id;

        return (int) $id;
    }
}
