<?php
define('CLI_SCRIPT', true);
require_once('config.php');

echo "=== Debug Course Data ===\n";

try {
    // Get all courses
    $courses = $DB->get_records('course', [], '', 'id, fullname, shortname');
    echo "Available courses:\n";
    foreach ($courses as $course) {
        if ($course->id > 1) { // Skip site course
            echo "  Course ID: {$course->id}, Name: {$course->fullname}\n";
        }
    }
    
    // Check specific course (assuming "Kelas 2" has ID 3 based on URL)
    $courseid = 3; // Change this based on URL you're viewing
    $course = $DB->get_record('course', ['id' => $courseid]);
    
    if ($course) {
        echo "\n=== Checking Course ID: $courseid ({$course->fullname}) ===\n";
        
        // Check if there are attendance instances
        $attendance_instances = $DB->get_records('attendance', ['course' => $courseid]);
        echo "Attendance instances: " . count($attendance_instances) . "\n";
        
        if (!empty($attendance_instances)) {
            foreach ($attendance_instances as $att) {
                echo "  - Attendance ID: {$att->id}, Name: {$att->name}\n";
                
                // Check sessions
                $sessions = $DB->get_records('attendance_sessions', ['attendanceid' => $att->id]);
                echo "    Sessions: " . count($sessions) . "\n";
                
                // Check statuses
                $statuses = $DB->get_records('attendance_statuses', ['attendanceid' => $att->id]);
                echo "    Statuses: " . count($statuses) . "\n";
                
                // Check logs
                if (!empty($sessions)) {
                    $sessionids = array_keys($sessions);
                    list($insql, $params) = $DB->get_in_or_equal($sessionids);
                    $logs = $DB->get_records_select('attendance_log', "sessionid $insql", $params);
                    echo "    Logs: " . count($logs) . "\n";
                }
            }
        }
        
        // Check enrolled users
        $context = context_course::instance($courseid);
        $enrolled = get_enrolled_users($context);
        echo "Enrolled users: " . count($enrolled) . "\n";
        
        // Check users with attendance capability
        $attendance_users = get_enrolled_users($context, 'mod/attendance:canbelisted');
        echo "Users with attendance capability: " . count($attendance_users) . "\n";
        
        // Test manager with this course
        echo "\n=== Testing Manager with Course ID: $courseid ===\n";
        require_once($CFG->dirroot . '/blocks/attendanceleaderboard/classes/leaderboard_manager.php');
        
        $manager = new \block_attendanceleaderboard\leaderboard_manager($courseid);
        $scores = $manager->get_leaderboard(5);
        echo "Manager returned " . count($scores) . " entries\n";
        
        if (!empty($scores)) {
            foreach ($scores as $score) {
                if (is_array($score)) {
                    echo "  - User {$score['userid']}: {$score['firstname']} {$score['lastname']}, Points: {$score['points']}\n";
                }
            }
        }
        
        // Check leaderboard scores table
        $saved_scores = $DB->get_records('block_attendanceleaderboard_scores', ['courseid' => $courseid]);
        echo "Saved scores in DB: " . count($saved_scores) . "\n";
        
    } else {
        echo "Course ID $courseid not found!\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?> 