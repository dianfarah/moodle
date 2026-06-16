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
 * Leaderboard manager class.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Class leaderboard_manager
 *
 * @package    block_attendanceleaderboard
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class leaderboard_manager {

    /** @var int Course ID */
    protected $courseid;
    
    /** @var bool Flag to track if scores have been updated in current request */
    protected $scoresuptodate = false;

    /**
     * Constructor.
     *
     * @param int $courseid The course ID
     */
    public function __construct($courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Update the scores for all students in the course.
     *
     * @param int $userid Optional user ID to update just one user
     * @return bool True if successful
     */
    public function update_scores($userid = 0) {
        global $DB;
        
        // If scores are already up to date in this request and not updating a single user, skip.
        if ($this->scoresuptodate && $userid == 0) {
            return true;
        }

        // Check if mod_attendance is installed.
        if (!\core_component::get_component_directory('mod_attendance')) {
            return false;
        }
        
        // Get all attendance instances in this course
        $attendanceinstances = $DB->get_records('attendance', ['course' => $this->courseid]);
        if (empty($attendanceinstances)) {
            return false;
        }
        
        // If updating a specific user, we still need to recalculate all scores
        // because the ranking depends on all users' scores
        $this->calculate_scores();
        
        // Mark scores as up to date
        $this->scoresuptodate = true;
        
        return true;
    }

    /**
     * Get the leaderboard data.
     *
     * @param int $limit The number of students to return (0 for all)
     * @return array The leaderboard data
     */
    public function get_leaderboard($limit = 0) {
        global $DB;
        
        // Try to get from cache first
        $limitstr = ($limit > 0) ? (string)$limit : 'all';
        $cachekeyleaderboard = "attendance_leaderboard_{$this->courseid}_{$limitstr}";
        $cache = \cache::make('block_attendanceleaderboard', 'leaderboarddata');
        $scores = $cache->get($cachekeyleaderboard);
        
        if ($scores === false) {
            // Only update scores if needed, not on every display
            $this->update_scores();

            // Get the leaderboard data
            $sql = "SELECT s.*, u.firstname, u.lastname, u.picture, u.email
                    FROM {block_attendanceleaderboard_scores} s
                    JOIN {user} u ON u.id = s.userid
                    WHERE s.courseid = :courseid
                    ORDER BY s.points DESC";
            
            $params = ['courseid' => $this->courseid];
            
            if ($limit > 0) {
                $scores = $DB->get_records_sql($sql, $params, 0, $limit);
            } else {
                $scores = $DB->get_records_sql($sql, $params);
            }
            
            // Convert to simple array to avoid cache issues
            $scoresarray = [];
            foreach ($scores as $score) {
                $scoresarray[] = [
                    'userid' => (int)$score->userid,
                    'points' => (int)$score->points,
                    'present' => (int)$score->present,
                    'late' => (int)$score->late,
                    'excused' => (int)$score->excused,
                    'absent' => (int)$score->absent,
                    'activitypoints' => isset($score->activitypoints) ? (int)$score->activitypoints : 0,
                    'firstname' => (string)$score->firstname,
                    'lastname' => (string)$score->lastname,
                    'email' => (string)$score->email,
                    'picture' => (string)$score->picture
                ];
            }
            
            // Store in cache for 15 minutes (900 seconds)
            $cache->set($cachekeyleaderboard, $scoresarray, 900);
            $scores = $scoresarray;
        }

        return $scores;
    }
    
    /**
     * Force refresh of leaderboard data for a specific user or all users.
     *
     * @param int $userid Optional user ID to refresh just one user
     * @return bool True if successful
     */
    public function refresh_data($userid = 0) {
        // Clear the cache
        $cache = \cache::make('block_attendanceleaderboard', 'leaderboarddata');
        $cache->delete("attendance_leaderboard_{$this->courseid}_all");
        
        // Also clear limited cache entries (common limits)
        $commonlimits = [5, 10, 15, 20, 25, 30];
        foreach ($commonlimits as $limit) {
            $cache->delete("attendance_leaderboard_{$this->courseid}_{$limit}");
        }
        
        // Update scores forcing recalculation
        $this->scoresuptodate = false;
        return $this->update_scores($userid);
    }

    /**
     * Calculate and update scores for all users in the course.
     *
     * @return void
     */
    private function calculate_scores() {
        global $DB;

        // Clear existing scores for this course.
        $DB->delete_records('block_attendanceleaderboard_scores', ['courseid' => $this->courseid]);

        // Get all attendance instances for this course.
        $attendances = $DB->get_records('attendance', ['course' => $this->courseid]);
        if (empty($attendances)) {
            return;
        }

        $attendanceids = array_keys($attendances);
        list($insql, $params) = $DB->get_in_or_equal($attendanceids);

        // Get all sessions for these attendance instances.
        $sessions = $DB->get_records_select('attendance_sessions', "attendanceid $insql", $params);
        if (empty($sessions)) {
            return;
        }

        // Get all statuses for these attendance instances.
        $statuses = $DB->get_records_select('attendance_statuses', "attendanceid $insql", $params);
        $statusmap = [];
        foreach ($statuses as $status) {
            $statusmap[$status->id] = $status;
        }

        // Get all logs for these sessions.
        $sessionids = array_keys($sessions);
        list($sessioninsql, $sessionparams) = $DB->get_in_or_equal($sessionids);
        $logs = $DB->get_records_select('attendance_log', "sessionid $sessioninsql", $sessionparams);

        // Get course context to find enrolled users.
        $context = \context_course::instance($this->courseid);
        $enrolledusers = get_enrolled_users($context, 'mod/attendance:canbelisted');

        // Calculate scores for each user.
        $userscores = [];
        foreach ($enrolledusers as $user) {
            $userscores[$user->id] = [
                'userid' => $user->id,
                'courseid' => $this->courseid,
                'points' => 0,
                'present' => 0,
                'late' => 0,
                'excused' => 0,
                'absent' => 0,
                'activitypoints' => 0,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'picture' => $user->picture,
                'imagealt' => $user->imagealt ?? ''
            ];
        }

        // Process each attendance log to calculate time-based points
        foreach ($logs as $log) {
            if (!isset($userscores[$log->studentid])) {
                continue; // User not enrolled or doesn't have capability
            }

            $session = $sessions[$log->sessionid] ?? null;
            $status = $statusmap[$log->statusid] ?? null;

            if (!$session || !$status) {
                continue;
            }

            // Get status acronym (P, L, A, E)
            $acronym = strtoupper(trim($status->acronym));
            
            // Calculate points based on attendance status and timing
            $points = 0;
            
            switch ($acronym) {
                case 'A': // Absent
                    $points = 0;
                    $userscores[$log->studentid]['absent']++;
                    break;
                    
                case 'E': // Excused
                    $points = 1;
                    $userscores[$log->studentid]['excused']++;
                    break;
                    
                case 'P': // Present
                case 'L': // Late
                    // Calculate time difference for Present and Late
                    $sessionstart = $session->sessdate; // Session start time
                    $checkintime = $log->timetaken; // When student checked in
                    
                    if ($checkintime && $sessionstart) {
                        $timeDiffMinutes = ($checkintime - $sessionstart) / 60; // Convert to minutes
                        
                        if ($timeDiffMinutes <= 5) {
                            $points = 5; // 0-5 minutes: 5 points
                        } elseif ($timeDiffMinutes <= 10) {
                            $points = 3; // 5-10 minutes: 3 points
                        } else {
                            $points = 2; // >10 minutes: 2 points
                        }
                    } else {
                        // Fallback if timing data is not available
                        $points = ($acronym === 'P') ? 5 : 2;
                    }
                    
                    if ($acronym === 'P') {
                        $userscores[$log->studentid]['present']++;
                    } else {
                        $userscores[$log->studentid]['late']++;
                    }
                    break;
                    
                default:
                    // Handle other status types - check description for multilingual support
                    $description = strtolower(trim($status->description));
                    if (strpos($description, 'present') !== false || strpos($description, 'hadir') !== false) {
                        $sessionstart = $session->sessdate;
                        $checkintime = $log->timetaken;
                        
                        if ($checkintime && $sessionstart) {
                            $timeDiffMinutes = ($checkintime - $sessionstart) / 60;
                            
                            if ($timeDiffMinutes <= 5) {
                                $points = 5;
                            } elseif ($timeDiffMinutes <= 10) {
                                $points = 3;
                            } else {
                                $points = 2;
                            }
                        } else {
                            $points = 5;
                        }
                        $userscores[$log->studentid]['present']++;
                    } elseif (strpos($description, 'late') !== false || strpos($description, 'terlambat') !== false) {
                        $sessionstart = $session->sessdate;
                        $checkintime = $log->timetaken;
                        
                        if ($checkintime && $sessionstart) {
                            $timeDiffMinutes = ($checkintime - $sessionstart) / 60;
                            
                            if ($timeDiffMinutes <= 5) {
                                $points = 5;
                            } elseif ($timeDiffMinutes <= 10) {
                                $points = 3;
                            } else {
                                $points = 2;
                            }
                        } else {
                            $points = 2;
                        }
                        $userscores[$log->studentid]['late']++;
                    } elseif (strpos($description, 'excused') !== false || strpos($description, 'ijin') !== false) {
                        $points = 1;
                        $userscores[$log->studentid]['excused']++;
                    } else {
                        // Assume absent for unknown status
                        $points = 0;
                        $userscores[$log->studentid]['absent']++;
                    }
                    break;
            }

            // Add points to user's total
            $userscores[$log->studentid]['points'] += $points;
        }

        // Calculate activity points for each user
        $this->calculate_activity_points($userscores);

        // Save scores to database.
        foreach ($userscores as $score) {
            if ($score['points'] > 0 || $score['present'] > 0 || $score['late'] > 0 || $score['excused'] > 0 || $score['absent'] > 0) {
                $record = (object)[
                    'courseid' => $score['courseid'],
                    'userid' => $score['userid'],
                    'points' => $score['points'],
                    'present' => $score['present'],
                    'late' => $score['late'],
                    'excused' => $score['excused'],
                    'absent' => $score['absent'],
                    'activitypoints' => $score['activitypoints'],
                    'timecreated' => time(),
                    'timemodified' => time()
                ];
                $DB->insert_record('block_attendanceleaderboard_scores', $record);
            }
        }

        // Clear any existing cache entries for this course
        $cache = \cache::make('block_attendanceleaderboard', 'leaderboarddata');
        $cache->delete("attendance_leaderboard_{$this->courseid}_all");
        
        // Also clear limited cache entries (common limits)
        $commonlimits = [5, 10, 15, 20, 25, 30];
        foreach ($commonlimits as $limit) {
            $cache->delete("attendance_leaderboard_{$this->courseid}_{$limit}");
        }
    }

    /**
     * Calculate activity points for users based on their course activities.
     *
     * @param array $userscores Reference to user scores array
     * @return void
     */
    private function calculate_activity_points(&$userscores) {
        global $DB;

        foreach ($userscores as $userid => &$score) {
            $activitypoints = 0;

            // 1. Membuka activity/resource (viewed) - 1 poin
            $viewlogs = $DB->get_records_select('logstore_standard_log',
                "courseid = ? AND userid = ? AND action = 'viewed' AND target = 'course_module'",
                [$this->courseid, $userid]);
            
            // Count unique course modules viewed
            $viewedmodules = [];
            foreach ($viewlogs as $log) {
                if (!in_array($log->contextinstanceid, $viewedmodules)) {
                    $viewedmodules[] = $log->contextinstanceid;
                    $activitypoints += 1;
                }
            }

            // 2. Mengumpulkan tugas (assignment) - 2 poin + bonus 1 poin jika di hari yang sama
            $assignmentlogs = $DB->get_records_select('logstore_standard_log',
                "courseid = ? AND userid = ? AND component = 'mod_assign' AND action = 'submitted'",
                [$this->courseid, $userid]);

            foreach ($assignmentlogs as $log) {
                $activitypoints += 2; // Base points for submission

                // Check if submitted on the same day as assignment creation/deadline
                $assignmentcm = $DB->get_record('course_modules', ['id' => $log->contextinstanceid]);
                if ($assignmentcm) {
                    $assignment = $DB->get_record('assign', ['id' => $assignmentcm->instance]);
                    if ($assignment) {
                        $submissiondate = date('Y-m-d', $log->timecreated);
                        $duedate = date('Y-m-d', $assignment->duedate);
                        $allowsubmissionsfromdate = date('Y-m-d', $assignment->allowsubmissionsfromdate);
                        
                        // Bonus 1 poin jika dikumpulkan di hari yang sama dengan deadline atau hari buka submission
                        if ($submissiondate == $duedate || $submissiondate == $allowsubmissionsfromdate) {
                            $activitypoints += 1;
                        }
                    }
                }
            }

            // 3. Mengerjakan quiz - 2 poin
            $quizlogs = $DB->get_records_select('logstore_standard_log',
                "courseid = ? AND userid = ? AND component = 'mod_quiz' AND action = 'submitted'",
                [$this->courseid, $userid]);
            $activitypoints += count($quizlogs) * 2;

            // 4. Forum post/reply - 2 poin
            $forumlogs = $DB->get_records_select('logstore_standard_log',
                "courseid = ? AND userid = ? AND component = 'mod_forum' AND (action = 'created' OR action = 'posted')",
                [$this->courseid, $userid]);
            $activitypoints += count($forumlogs) * 2;

            // 5. Zoom meeting join - 2 poin
            $zoomlogs = $DB->get_records_select('logstore_standard_log',
                "courseid = ? AND userid = ? AND component = 'mod_zoom' AND action = 'joined'",
                [$this->courseid, $userid]);
            $activitypoints += count($zoomlogs) * 2;

            // Add activity points to user score
            $score['activitypoints'] = $activitypoints;
            $score['points'] += $activitypoints;
            
            // Debug logging (dapat dihapus setelah testing)
            if (debugging() && $activitypoints > 0) {
                error_log("User ID {$userid}: Activity Points = {$activitypoints}, Total Points = {$score['points']}");
            }
        }
    }
}
