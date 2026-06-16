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
 * Event observers registration for block_attendanceleaderboard (ACMLS).
 *
 * Registers all Moodle event observers required by the ACMLS Tracking System.
 * Each observer delegates to the TrackingSystem via the event\observer class.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [

    // -------------------------------------------------------------------------
    // ACMLS Configuration Audit Log observer (Task 13.6)
    // Listens to Moodle's built-in config_log_created event and filters
    // for block_attendanceleaderboard plugin settings.
    // -------------------------------------------------------------------------

    [
        'eventname' => '\core\event\config_log_created',
        'callback'  => '\block_attendanceleaderboard\event\observer::config_log_created',
    ],

    // -------------------------------------------------------------------------
    // ACMLS Tracking System observers (Task 3)
    // -------------------------------------------------------------------------

    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\block_attendanceleaderboard\event\observer::user_loggedin',
    ],
    [
        'eventname' => '\core\event\user_loggedout',
        'callback'  => '\block_attendanceleaderboard\event\observer::user_loggedout',
    ],
    [
        'eventname' => '\core\event\course_module_viewed',
        'callback'  => '\block_attendanceleaderboard\event\observer::course_module_viewed',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => '\block_attendanceleaderboard\event\observer::quiz_attempt_submitted',
    ],
    [
        'eventname' => '\core\event\grade_item_updated',
        'callback'  => '\block_attendanceleaderboard\event\observer::grade_updated',
    ],
    [
        'eventname' => '\mod_forum\event\post_created',
        'callback'  => '\block_attendanceleaderboard\event\observer::forum_post_created',
    ],
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback'  => '\block_attendanceleaderboard\event\observer::activity_completed',
    ],

    // -------------------------------------------------------------------------
    // ACMLS Learning Resource Repository observer (Task 6)
    // -------------------------------------------------------------------------

    [
        'eventname' => '\core\event\course_module_created',
        'callback'  => '\block_attendanceleaderboard\event\observer::course_module_created',
    ],

    // -------------------------------------------------------------------------
    // Legacy attendance observers (preserved from original plugin)
    // -------------------------------------------------------------------------

    [
        'eventname' => '\mod_attendance\event\attendance_taken',
        'callback'  => '\block_attendanceleaderboard\observer::attendance_taken',
    ],
    [
        'eventname' => '\mod_attendance\event\attendance_taken_by_student',
        'callback'  => '\block_attendanceleaderboard\observer::attendance_taken_by_student',
    ],
    [
        'eventname' => '\mod_attendance\event\session_updated',
        'callback'  => '\block_attendanceleaderboard\observer::session_updated',
    ],
    [
        'eventname' => '\mod_attendance\event\session_deleted',
        'callback'  => '\block_attendanceleaderboard\observer::session_deleted',
    ],
    [
        'eventname'   => '\core\event\course_module_deleted',
        'callback'    => '\block_attendanceleaderboard\observer::course_module_deleted',
        'includefile' => null,
        'internal'    => true,
    ],
];
