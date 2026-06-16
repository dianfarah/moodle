<?php
define('CLI_SCRIPT', true);
require_once('config.php');

echo "=== Debug Time-Based Points System ===\n";

try {
    $courseid = 3; // Course "Kelas 2"
    
    // Get attendance sessions and logs
    $attendances = $DB->get_records('attendance', ['course' => $courseid]);
    if (empty($attendances)) {
        echo "No attendance instances found!\n";
        exit;
    }
    
    $attendanceids = array_keys($attendances);
    list($insql, $params) = $DB->get_in_or_equal($attendanceids);
    
    $sessions = $DB->get_records_select('attendance_sessions', "attendanceid $insql", $params);
    $statuses = $DB->get_records_select('attendance_statuses', "attendanceid $insql", $params);
    
    echo "Sessions found: " . count($sessions) . "\n";
    echo "Statuses found: " . count($statuses) . "\n";
    
    // Show session details
    foreach ($sessions as $session) {
        echo "\nSession ID: {$session->id}\n";
        echo "Session date/time: " . date('Y-m-d H:i:s', $session->sessdate) . "\n";
        
        // Get logs for this session
        $logs = $DB->get_records('attendance_log', ['sessionid' => $session->id]);
        echo "Attendance logs: " . count($logs) . "\n";
        
        foreach ($logs as $log) {
            $user = $DB->get_record('user', ['id' => $log->studentid], 'firstname, lastname');
            $status = $statuses[$log->statusid] ?? null;
            
            if ($user && $status) {
                $sessionStart = $session->sessdate;
                $checkinTime = $log->timetaken;
                
                echo "  Student: {$user->firstname} {$user->lastname}\n";
                echo "    Status: {$status->acronym} ({$status->description})\n";
                echo "    Session start: " . date('H:i:s', $sessionStart) . "\n";
                echo "    Check-in time: " . date('H:i:s', $checkinTime) . "\n";
                
                if ($checkinTime && $sessionStart) {
                    $timeDiffMinutes = ($checkinTime - $sessionStart) / 60;
                    echo "    Time difference: " . round($timeDiffMinutes, 2) . " minutes\n";
                    
                    // Calculate points based on new system
                    $points = 0;
                    $acronym = strtoupper(trim($status->acronym));
                    
                    switch ($acronym) {
                        case 'A':
                            $points = 0;
                            break;
                        case 'E':
                            $points = 1;
                            break;
                        case 'P':
                        case 'L':
                            if ($timeDiffMinutes <= 5) {
                                $points = 5;
                            } elseif ($timeDiffMinutes <= 10) {
                                $points = 3;
                            } else {
                                $points = 2;
                            }
                            break;
                    }
                    
                    echo "    Points awarded: {$points}\n";
                } else {
                    echo "    No timing data available\n";
                }
                echo "\n";
            }
        }
    }
    
    // Test new manager
    echo "\n=== Testing New Manager ===\n";
    require_once($CFG->dirroot . '/blocks/attendanceleaderboard/classes/leaderboard_manager.php');
    
    $manager = new \block_attendanceleaderboard\leaderboard_manager($courseid);
    $manager->refresh_data(); // Force recalculation
    
    $scores = $manager->get_leaderboard(5);
    echo "New scores calculated:\n";
    
    foreach ($scores as $score) {
        if (is_array($score)) {
            echo "  {$score['firstname']} {$score['lastname']}: {$score['points']} points\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?> 