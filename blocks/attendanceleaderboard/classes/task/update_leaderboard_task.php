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
 * Scheduled task: update Leaderboard rankings every 15 minutes.
 *
 * Queries all distinct (courseid, scope) pairs from acmls_leaderboard and
 * calls Leaderboard::update_rankings() for each pair.
 *
 * Requirements addressed:
 * - Req 12.2: Rankings updated with interval ≤15 minutes.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\task;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\leaderboard\leaderboard;

/**
 * Scheduled task for updating Leaderboard rankings.
 *
 * Runs every 15 minutes (configured in db/tasks.php).
 * Iterates over all distinct (courseid, scope) pairs in acmls_leaderboard
 * and triggers a full ranking update for each.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_leaderboard_task extends \core\task\scheduled_task {

    /**
     * Return the human-readable name of this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_update_leaderboard', 'block_attendanceleaderboard');
    }

    /**
     * Execute the task.
     *
     * Queries all distinct (courseid, scope) pairs from acmls_leaderboard
     * and calls Leaderboard::update_rankings() for each pair.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        mtrace('ACMLS update_leaderboard_task: Starting leaderboard ranking update...');

        // Query all distinct (courseid, scope) pairs.
        $sql = "SELECT DISTINCT courseid, scope
                  FROM {acmls_leaderboard}
                 ORDER BY courseid ASC, scope ASC";

        $pairs = $DB->get_records_sql($sql);

        if (empty($pairs)) {
            mtrace('ACMLS update_leaderboard_task: No leaderboard records found. Nothing to update.');
            return;
        }

        $leaderboard = new leaderboard();
        $count       = 0;

        foreach ($pairs as $pair) {
            $courseid = (int) $pair->courseid;
            $scope    = (string) $pair->scope;

            try {
                $leaderboard->update_rankings($courseid, $scope);
                $count++;
                mtrace("ACMLS update_leaderboard_task: Updated rankings for course {$courseid}, scope '{$scope}'.");
            } catch (\Throwable $e) {
                // Log error but continue processing other pairs.
                mtrace("ACMLS update_leaderboard_task: ERROR updating course {$courseid}, scope '{$scope}': "
                    . $e->getMessage());
            }
        }

        mtrace("ACMLS update_leaderboard_task: Completed. Updated {$count} course/scope pair(s).");
    }
}
