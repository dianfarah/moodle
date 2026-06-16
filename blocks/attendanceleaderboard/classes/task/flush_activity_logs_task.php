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
 * Scheduled task: flush pending ActivityLogs to the Profiling System.
 *
 * Runs every 5 minutes (configured in db/tasks.php) to satisfy Req 2.4:
 * "Activity_Log shall be sent to Profiling_System within ≤5 minutes."
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\task;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\tracking\tracking_system;

/**
 * Scheduled task that flushes pending ActivityLog entries to the Profiling System.
 *
 * If the Profiling System is unavailable, logs remain pending (sent_to_profiler=0)
 * and will be retried on the next execution (Req 2.5).
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flush_activity_logs_task extends \core\task\scheduled_task {

    /**
     * Return the human-readable name of this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_flush_activity_logs', 'block_attendanceleaderboard');
    }

    /**
     * Execute the task: flush all pending ActivityLogs to the Profiling System.
     *
     * @return void
     */
    public function execute(): void {
        $ts = new tracking_system();

        $pending_count = count($ts->get_pending_logs());

        if ($pending_count === 0) {
            mtrace('ACMLS flush_activity_logs_task: No pending logs to flush.');
            return;
        }

        mtrace("ACMLS flush_activity_logs_task: Flushing {$pending_count} pending log(s) to Profiling System...");

        $ts->flush_to_profiler();

        $remaining = count($ts->get_pending_logs());
        $sent      = $pending_count - $remaining;

        mtrace("ACMLS flush_activity_logs_task: Sent {$sent} log(s). {$remaining} log(s) remain pending (will retry).");
    }
}
