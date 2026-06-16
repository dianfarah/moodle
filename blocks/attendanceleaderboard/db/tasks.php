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
 * Scheduled tasks definition for block_attendanceleaderboard (ACMLS).
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [

    // -------------------------------------------------------------------------
    // Task 3: Flush pending ActivityLogs to Profiling System every 5 minutes.
    // Satisfies Req 2.4 (≤5 min interval) and Req 2.5 (retry on failure).
    // -------------------------------------------------------------------------
    [
        'classname' => '\block_attendanceleaderboard\task\flush_activity_logs_task',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],

    // -------------------------------------------------------------------------
    // Task 5: Update Learner Profiles daily at 02:00.
    // Implemented in Task 5 (Profiling System). Enabled.
    // -------------------------------------------------------------------------
    [
        'classname' => '\block_attendanceleaderboard\task\update_profiles_task',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '2',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],

    // -------------------------------------------------------------------------
    // Task 10: Update Leaderboard every 15 minutes.
    // Implemented in Task 10 (Leaderboard). Enabled.
    // Satisfies Req 12.2 (ranking interval ≤15 minutes).
    // -------------------------------------------------------------------------
    [
        'classname' => '\block_attendanceleaderboard\task\update_leaderboard_task',
        'blocking'  => 0,
        'minute'    => '*/15',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
