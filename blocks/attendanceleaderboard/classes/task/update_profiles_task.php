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
 * Scheduled task: update Learner Profiles daily at 02:00.
 *
 * Queries all active learners (users with records in acmls_learner_profile)
 * and calls ProfilingSystem::update_profile() for each to refresh all
 * profile dimensions.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\task;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\profiling\profiling_system;

/**
 * Scheduled task for updating all Learner Profiles daily.
 *
 * Runs at 02:00 every day (configured in db/tasks.php).
 * Iterates over all user/course pairs in acmls_learner_profile and
 * triggers a profile update for each active learner.
 *
 * Requirements addressed:
 * - Req 3.1: Profiling_System SHALL update Learner_Profile when Activity_Log data arrives.
 * - Req 3.4: Profiling_System SHALL update Motivation_Level based on engagement patterns.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_profiles_task extends \core\task\scheduled_task {

    /**
     * Return the human-readable name of this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_update_profiles', 'block_attendanceleaderboard');
    }

    /**
     * Execute the task: update all active Learner Profiles.
     *
     * Queries all distinct (userid, courseid) pairs from acmls_learner_profile
     * and calls ProfilingSystem::update_profile() for each.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        mtrace('ACMLS update_profiles_task: Starting daily profile update...');

        // Fetch all active learner/course pairs.
        $sql = 'SELECT DISTINCT userid, courseid FROM {acmls_learner_profile} ORDER BY userid ASC, courseid ASC';
        $learners = $DB->get_records_sql($sql);

        if (empty($learners)) {
            mtrace('ACMLS update_profiles_task: No active learners found. Nothing to update.');
            return;
        }

        $profiler   = new profiling_system();
        $total      = count($learners);
        $updated    = 0;
        $failed     = 0;

        mtrace("ACMLS update_profiles_task: Found {$total} active learner/course pair(s) to update.");

        foreach ($learners as $row) {
            $userid   = (int) $row->userid;
            $courseid = (int) $row->courseid;

            try {
                $profiler->update_profile($userid, $courseid);
                $updated++;
            } catch (\Exception $e) {
                $failed++;
                debugging(
                    "ACMLS update_profiles_task: Failed to update profile for userid={$userid}, " .
                    "courseid={$courseid}: " . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        mtrace("ACMLS update_profiles_task: Completed. Updated={$updated}, Failed={$failed}.");
    }
}
