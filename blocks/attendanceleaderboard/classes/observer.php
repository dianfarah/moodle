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
 * Event observers.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Observer for attendance_taken event.
     *
     * @param \mod_attendance\event\attendance_taken $event
     * @return bool
     */
    public static function attendance_taken(\mod_attendance\event\attendance_taken $event) {
        global $DB;
        
        $courseid = $event->courseid;
        
        if (!$courseid) {
            return false;
        }
        
        // Update all students in the course since attendance was taken for a session
        $manager = new \block_attendanceleaderboard\leaderboard_manager($courseid);
        return $manager->refresh_data();
    }
    
    /**
     * Observer for attendance_taken_by_student event.
     *
     * @param \mod_attendance\event\attendance_taken_by_student $event
     * @return bool
     */
    public static function attendance_taken_by_student(\mod_attendance\event\attendance_taken_by_student $event) {
        global $DB;
        
        $studentid = $event->relateduserid;
        $courseid = $event->courseid;
        
        if (!$studentid || !$courseid) {
            return false;
        }
        
        // Update only the affected student
        $manager = new \block_attendanceleaderboard\leaderboard_manager($courseid);
        return $manager->refresh_data($studentid);
    }
    
    /**
     * Observer for session_updated event.
     *
     * @param \mod_attendance\event\session_updated $event
     * @return bool
     */
    public static function session_updated(\mod_attendance\event\session_updated $event) {
        global $DB;
        
        $courseid = $event->courseid;
        
        if (!$courseid) {
            return false;
        }
        
        // Update all students in the course since session was modified
        $manager = new \block_attendanceleaderboard\leaderboard_manager($courseid);
        return $manager->refresh_data();
    }
    
    /**
     * Observer for session_deleted event.
     *
     * @param \mod_attendance\event\session_deleted $event
     * @return bool
     */
    public static function session_deleted(\mod_attendance\event\session_deleted $event) {
        global $DB;
        
        $courseid = $event->courseid;
        
        if (!$courseid) {
            return false;
        }
        
        // Update all students in the course since session was deleted
        $manager = new \block_attendanceleaderboard\leaderboard_manager($courseid);
        return $manager->refresh_data();
    }
    
    /**
     * Observer for course_module_deleted event.
     *
     * @param \core\event\course_module_deleted $event
     * @return bool
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $DB;
        
        // Check if the deleted module was an attendance module
        if ($event->other['modulename'] !== 'attendance') {
            return true;
        }
        
        $courseid = $event->courseid;
        
        if (!$courseid) {
            return false;
        }
        
        // Update all students in the course
        $manager = new \block_attendanceleaderboard\leaderboard_manager($courseid);
        return $manager->refresh_data();
    }
} 