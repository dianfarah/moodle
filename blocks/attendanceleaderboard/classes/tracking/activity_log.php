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
 * ActivityLog data class for ACMLS Tracking System.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\tracking;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a single activity log entry for a Learner.
 *
 * Stores all relevant data about a Learner's interaction with the LMS,
 * including login events, resource access, quiz submissions, forum posts,
 * and activity completions.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_log {

    /** @var int|null Database record ID (null if not yet persisted). */
    public ?int $id = null;

    /** @var int Moodle user ID of the Learner. */
    public int $userid;

    /** @var int Moodle course ID associated with this activity. */
    public int $courseid;

    /**
     * @var string Event type identifier.
     * Examples: 'user_loggedin', 'course_module_viewed', 'quiz_attempt_submitted',
     *           'grade_item_updated', 'forum_post_created', 'course_module_completion_updated',
     *           'user_loggedout'.
     */
    public string $event_type;

    /**
     * @var string Moodle component that generated the event.
     * Examples: 'core', 'mod_quiz', 'mod_resource', 'mod_forum'.
     */
    public string $component;

    /** @var int|null Object ID related to the event (e.g., course module ID, quiz attempt ID). */
    public ?int $objectid = null;

    /** @var string Action performed (e.g., 'viewed', 'submitted', 'created', 'loggedin'). */
    public string $action;

    /** @var int Duration of the activity/session in seconds. Defaults to 0. */
    public int $duration_seconds = 0;

    /** @var float|null Score/result value if applicable (e.g., quiz score as percentage). */
    public ?float $result_value = null;

    /** @var array JSON-serializable metadata about the event context. */
    public array $context_data = [];

    /** @var int Unix timestamp when this log entry was created. */
    public int $timecreated;

    /** @var bool Whether this log has been sent to the Profiling System. */
    public bool $sent_to_profiler = false;

    /**
     * Constructor.
     *
     * @param int    $userid      Moodle user ID.
     * @param int    $courseid    Moodle course ID.
     * @param string $event_type  Event type string.
     * @param string $component   Moodle component name.
     * @param string $action      Action performed.
     */
    public function __construct(
        int $userid,
        int $courseid,
        string $event_type,
        string $component,
        string $action
    ) {
        $this->userid      = $userid;
        $this->courseid    = $courseid;
        $this->event_type  = $event_type;
        $this->component   = $component;
        $this->action      = $action;
        $this->timecreated = time();
    }

    /**
     * Convert this ActivityLog to a database record object suitable for $DB->insert_record().
     *
     * The context_data array is JSON-encoded for storage.
     *
     * @return \stdClass Database record object.
     */
    public function to_db_record(): \stdClass {
        $record = new \stdClass();

        if ($this->id !== null) {
            $record->id = $this->id;
        }

        $record->userid           = $this->userid;
        $record->courseid         = $this->courseid;
        $record->event_type       = $this->event_type;
        $record->component        = $this->component;
        $record->objectid         = $this->objectid;
        $record->action           = $this->action;
        $record->duration_seconds = $this->duration_seconds;
        $record->result_value     = $this->result_value;
        $record->context_data     = json_encode($this->context_data);
        $record->timecreated      = $this->timecreated;
        $record->sent_to_profiler = (int) $this->sent_to_profiler;

        return $record;
    }

    /**
     * Create an ActivityLog instance from a database record object.
     *
     * @param  \stdClass    $record Database record from acmls_activity_log table.
     * @return activity_log         Populated ActivityLog instance.
     */
    public static function from_db_record(\stdClass $record): self {
        $log = new self(
            (int) $record->userid,
            (int) $record->courseid,
            (string) $record->event_type,
            (string) $record->component,
            (string) $record->action
        );

        $log->id               = (int) $record->id;
        $log->objectid         = isset($record->objectid) ? (int) $record->objectid : null;
        $log->duration_seconds = (int) ($record->duration_seconds ?? 0);
        $log->result_value     = isset($record->result_value) ? (float) $record->result_value : null;
        $log->context_data     = !empty($record->context_data)
            ? (array) json_decode($record->context_data, true)
            : [];
        $log->timecreated      = (int) $record->timecreated;
        $log->sent_to_profiler = (bool) ($record->sent_to_profiler ?? false);

        return $log;
    }
}
