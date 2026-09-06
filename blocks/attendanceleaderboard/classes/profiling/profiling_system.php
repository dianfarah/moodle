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
 * ProfilingSystem — builds and updates multidimensional Learner Profiles.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\profiling;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\tracking\activity_log;

/**
 * Profiling System for ACMLS.
 *
 * Responsible for:
 * - Processing incoming ActivityLog entries and updating Learner Profiles.
 * - Classifying Performance_Category based on score thresholds.
 * - Classifying Learning_Style based on resource interaction patterns.
 * - Calculating Motivation_Level using a weighted moving average.
 * - Persisting profile snapshots to acmls_learner_record for longitudinal analysis.
 * - Notifying the Coach component after each profile update.
 *
 * Requirements addressed:
 * - Req 3.1: Update Learner_Profile dimensions from Activity_Log data.
 * - Req 3.2: Create initial profile with Performance_Category classification.
 * - Req 3.3: Update Performance_Category based on latest score.
 * - Req 3.4: Update Motivation_Level based on engagement patterns.
 * - Req 3.5: Store all historical profile versions in Learner_Record.
 * - Req 3.6: Notify Coach after each profile update.
 * - Req 3.7: Classify Learning_Style from resource interaction patterns.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profiling_system {

    /** @var string Database table for learner profiles. */
    const TABLE_PROFILE = 'acmls_learner_profile';

    /** @var string Database table for learner records (analytics repository). */
    const TABLE_RECORD = 'acmls_learner_record';

    /**
     * Map of resource types to learning styles.
     *
     * Used by classify_learning_style() to count interactions per style.
     *
     * @var array<string, string>
     */
    private static array $resource_style_map = [
        'video'        => learner_profile::STYLE_AUDITORY,
        'audio'        => learner_profile::STYLE_AUDITORY,
        'document'     => learner_profile::STYLE_READING,
        'pdf'          => learner_profile::STYLE_READING,
        'book'         => learner_profile::STYLE_READING,
        'page'         => learner_profile::STYLE_READING,
        'image'        => learner_profile::STYLE_VISUAL,
        'diagram'      => learner_profile::STYLE_VISUAL,
        'presentation' => learner_profile::STYLE_VISUAL,
        'quiz'         => learner_profile::STYLE_KINESTHETIC,
        'assignment'   => learner_profile::STYLE_KINESTHETIC,
        'workshop'     => learner_profile::STYLE_KINESTHETIC,
    ];

    /**
     * Weighted moving average alpha (learning rate).
     * Read from plugin config; falls back to 0.7.
     *
     * @var float
     */
    private float $alpha;

    /**
     * Constructor — loads the learning rate (α) from plugin config.
     */
    public function __construct() {
        $configured = get_config('block_attendanceleaderboard', 'learning_rate');
        $this->alpha = ($configured !== false && is_numeric($configured))
            ? (float) $configured
            : 0.7;
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Process a single ActivityLog entry and update the Learner Profile.
     *
     * Extracts relevant metrics from the log (score, resource type, engagement
     * indicators) and delegates to update_profile().
     *
     * @param  activity_log $log The activity log entry to process.
     * @return void
     */
    public function process_activity_log(activity_log $log): void {
        $metrics = [];

        // Extract score if present (e.g., from quiz submission or grade event).
        if ($log->result_value !== null) {
            $metrics['score'] = $log->result_value;
        }

        // Extract resource type from context_data for learning style classification.
        if (!empty($log->context_data['resource_type'])) {
            $metrics['resource_type'] = (string) $log->context_data['resource_type'];
        }

        // Extract engagement indicators.
        if ($log->duration_seconds > 0) {
            $metrics['duration_seconds'] = $log->duration_seconds;
        }

        $metrics['event_type'] = $log->event_type;
        $metrics['component']  = $log->component;

        $this->update_profile($log->userid, $log->courseid, $metrics);
    }

    /**
     * Load or create a Learner Profile, update all dimensions, and save to DB.
     *
     * Algorithm:
     * 1. Load existing profile from acmls_learner_profile, or create a new one.
     * 2. Update performance_category if a score is provided.
     * 3. Update motivation_level using weighted moving average.
     * 4. Update learning_style if resource interaction data is provided.
     * 5. Update engagement_score and behavioral_score from engagement metrics.
     * 6. Increment profile_version and update last_updated.
     * 7. Persist the updated profile to acmls_learner_profile.
     * 8. Save a snapshot to acmls_learner_record.
     * 9. Notify the Coach.
     *
     * @param  int   $userid    Moodle user ID.
     * @param  int   $courseid  Moodle course ID.
     * @param  array $metrics   Optional metrics to apply:
     *                          - 'score'          (float)  : latest performance score (0–100)
     *                          - 'resource_type'  (string) : type of resource accessed
     *                          - 'duration_seconds' (int)  : engagement duration
     *                          - 'interactions'   (array)  : array of resource_type strings
     * @return learner_profile  The updated (or newly created) profile.
     */
    public function update_profile(int $userid, int $courseid, array $metrics = []): learner_profile {
        global $DB;

        // Step 1: Load or create profile.
        $record = $DB->get_record(self::TABLE_PROFILE, [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        if ($record) {
            $profile = learner_profile::from_db_record($record);
        } else {
            $profile = new learner_profile($userid, $courseid);
        }

        // --- 1. Behavioral Engagement (b) ---
        $b1_count = 0;
        $b1_score = $this->compute_b1_score($userid, $courseid, $b1_count);
        $profile->b1_access_count = $b1_count;

        $b2_count = 0;
        $b2_score = $this->compute_b2_score($userid, $courseid, $b2_count);
        $profile->b2_completion_count = $b2_count;

        $b3_count = 0;
        $b3_score = $this->compute_b3_score($userid, $courseid, $b3_count);
        $profile->b3_punctual_count = $b3_count;

        $behavioral_score = ($b1_score * 0.3) + ($b2_score * 0.4) + ($b3_score * 0.3);
        $profile->behavioral_score = round($behavioral_score, 2);

        // --- 2. Cognitive Engagement (c) ---
        $c1_avg = 0.0;
        $c1_score = $this->compute_c1_score($userid, $courseid, $c1_avg);
        $profile->c1_quiz_avg = $c1_avg;

        $c2_count = 0;
        $c2_score = $this->compute_c2_score($userid, $courseid, $c2_count);
        $profile->c2_quiz_attempts = $c2_count;

        $cognitive_score = ($c1_score * 0.7) + ($c2_score * 0.3);
        $profile->cognitive_score = round($cognitive_score, 2);
        $profile->cognitive_level = $this->classify_performance_category($cognitive_score);

        // --- 3. Emotional Engagement (e) ---
        if (isset($metrics['e1']) && isset($metrics['e2']) && isset($metrics['e3'])) {
            $e1_val = (float) $metrics['e1'];
            $e2_val = (float) $metrics['e2'];
            $e3_val = (float) $metrics['e3'];

            // Map 1-5 scale to 0-100 scale (value * 20.0).
            $profile->e1_score = $this->calculate_motivation_level($e1_val * 20.0, $profile->e1_score, $this->alpha);
            $profile->e2_score = $this->calculate_motivation_level($e2_val * 20.0, $profile->e2_score, $this->alpha);
            $profile->e3_score = $this->calculate_motivation_level($e3_val * 20.0, $profile->e3_score, $this->alpha);
            $profile->emotional_score = round(($profile->e1_score + $profile->e2_score + $profile->e3_score) / 3.0, 2);
        } else if ($profile->emotional_score == 0.0) {
            // Default emotional metrics if never set before.
            $profile->e1_score = 50.0;
            $profile->e2_score = 50.0;
            $profile->e3_score = 50.0;
            $profile->emotional_score = 50.0;
        }

        // --- 4. Engagement Score (overall composite of behavioral + cognitive) ---
        // Let's set the composite engagement_score as 50% behavioral + 50% cognitive.
        $profile->engagement_score = round(($profile->behavioral_score * 0.5) + ($profile->cognitive_score * 0.5), 2);

        // --- 5. Motivation Level ---
        // Formula: Motivation_Level = (Behavioral_Score * 0.4) + (Cognitive_Score * 0.4) + (Emotional_Score * 0.2)
        $motivation_level = ($profile->behavioral_score * 0.4) + ($profile->cognitive_score * 0.4) + ($profile->emotional_score * 0.2);
        $profile->motivation_level = round($motivation_level, 2);
        $profile->performance_category = $this->classify_performance_category($profile->motivation_level);

        // Step 4: Update learning style (optional but kept).
        if (!empty($metrics['interactions']) && is_array($metrics['interactions'])) {
            $profile->learning_style = $this->classify_learning_style($metrics['interactions']);
        } elseif (!empty($metrics['resource_type'])) {
            $profile->learning_style = $this->classify_learning_style([$metrics['resource_type']]);
        }

        // Step 6: Increment version and update timestamp.
        if ($record) {
            $profile->profile_version++;
        }
        $profile->last_updated = time();

        // Step 7: Persist to acmls_learner_profile.
        $db_record = $profile->to_db_record();
        if ($profile->id !== null) {
            $DB->update_record(self::TABLE_PROFILE, $db_record);
        } else {
            $new_id = (int) $DB->insert_record(self::TABLE_PROFILE, $db_record);
            $profile->id = $new_id;
        }

        // Step 8: Save a snapshot to acmls_learner_record.
        $this->save_profile_snapshot($profile);

        // Step 9: Notify the Coach.
        $this->notify_coach($profile);

        return $profile;
    }

    /**
     * Classify a performance score into a category.
     *
     * Rules:
     *   score < 60  → 1 (Low)
     *   60 ≤ score < 80 → 2 (Middle)
     *   score ≥ 80  → 3 (High)
     *
     * @param  float $score Performance score (0–100).
     * @return int          1 = Low, 2 = Middle, 3 = High.
     */
    public function classify_performance_category(float $score): int {
        if ($score < 60.0) {
            return learner_profile::PERFORMANCE_LOW;
        }
        if ($score < 80.0) {
            return learner_profile::PERFORMANCE_MIDDLE;
        }
        return learner_profile::PERFORMANCE_HIGH;
    }

    /**
     * Classify a Learner's learning style from resource interaction patterns.
     *
     * Counts interactions per style using the resource-to-style mapping:
     *   'video', 'audio'                    → 'auditory'
     *   'document', 'pdf', 'book', 'page'   → 'reading'
     *   'image', 'diagram', 'presentation'  → 'visual'
     *   'quiz', 'assignment', 'workshop'    → 'kinesthetic'
     *
     * Returns the style with the highest count.
     * Returns 'unknown' if the interactions array is empty or no known types found.
     *
     * @param  array  $interactions Array of resource type strings (e.g., ['video', 'quiz', 'video']).
     * @return string               One of: 'visual', 'auditory', 'reading', 'kinesthetic', 'unknown'.
     */
    public function classify_learning_style(array $interactions): string {
        if (empty($interactions)) {
            return learner_profile::STYLE_UNKNOWN;
        }

        $counts = [
            learner_profile::STYLE_VISUAL      => 0,
            learner_profile::STYLE_AUDITORY    => 0,
            learner_profile::STYLE_READING     => 0,
            learner_profile::STYLE_KINESTHETIC => 0,
        ];

        foreach ($interactions as $resource_type) {
            $type = strtolower(trim((string) $resource_type));
            if (isset(self::$resource_style_map[$type])) {
                $style = self::$resource_style_map[$type];
                $counts[$style]++;
            }
        }

        $total = array_sum($counts);
        if ($total === 0) {
            return learner_profile::STYLE_UNKNOWN;
        }

        // Return the style with the highest count.
        return (string) array_search(max($counts), $counts);
    }

    /**
     * Calculate the new Motivation_Level using a weighted moving average.
     *
     * Formula:
     *   new_value = (recent_data × α) + (historical_average × (1 − α))
     *
     * The result is clamped to [0.0, 100.0].
     *
     * @param  float $recent_data         The most recent data point (0–100).
     * @param  float $historical_average  The historical average (0–100).
     * @param  float $alpha               Weight for recent data (default: from config or 0.7).
     * @return float                      New motivation level, clamped to [0.0, 100.0].
     */
    public function calculate_motivation_level(
        float $recent_data,
        float $historical_average,
        float $alpha = 0.7
    ): float {
        $new_value = ($recent_data * $alpha) + ($historical_average * (1.0 - $alpha));

        // Clamp to [0.0, 100.0].
        return (float) max(0.0, min(100.0, $new_value));
    }

    /**
     * Save a profile snapshot to acmls_learner_record.
     *
     * Inserts a record with record_type='profile_snapshot' and
     * source_component='profiling', containing the full profile snapshot as JSON.
     *
     * @param  learner_profile $profile The profile to snapshot.
     * @return int                      ID of the inserted record.
     */
    public function save_profile_snapshot(learner_profile $profile): int {
        global $DB;

        $record = new \stdClass();
        $record->userid           = $profile->userid;
        $record->courseid         = $profile->courseid;
        $record->record_type      = 'profile_snapshot';
        $record->source_component = 'profiling';
        $record->data_payload     = json_encode($profile->get_snapshot());
        $record->profile_version  = $profile->profile_version;
        $record->timecreated      = time();

        return (int) $DB->insert_record(self::TABLE_RECORD, $record);
    }

    /**
     * Notify the Coach component that a Learner Profile has been updated.
     *
     * If the Coach class is available, calls Coach::receive_profile_update().
     * Otherwise, logs a debug message so the notification is not silently lost.
     *
     * @param  learner_profile $profile The updated profile.
     * @return void
     */
    public function notify_coach(learner_profile $profile): void {
        if (class_exists('\block_attendanceleaderboard\coach\coach')) {
            try {
                \block_attendanceleaderboard\coach\coach::receive_profile_update($profile);
            } catch (\Exception $e) {
                debugging(
                    'ACMLS ProfilingSystem: Coach::receive_profile_update failed — ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        } else {
            // Coach not yet available — log the notification.
            debugging(
                'ACMLS ProfilingSystem: Profile updated for userid=' . $profile->userid .
                ', courseid=' . $profile->courseid .
                ', version=' . $profile->profile_version .
                ', performance_category=' . $profile->performance_category .
                ', motivation_level=' . $profile->motivation_level .
                ', learning_style=' . $profile->learning_style,
                DEBUG_DEVELOPER
            );
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Retrieve all profile snapshots for a user/course in chronological order.
     *
     * Used internally and by tests to verify historical version storage.
     *
     * @param  int   $userid    Moodle user ID.
     * @param  int   $courseid  Moodle course ID.
     * @return array            Array of decoded snapshot arrays, ordered by timecreated ASC.
     */
    public function get_profile_history(int $userid, int $courseid): array {
        global $DB;

        $records = $DB->get_records(
            self::TABLE_RECORD,
            [
                'userid'           => $userid,
                'courseid'         => $courseid,
                'record_type'      => 'profile_snapshot',
                'source_component' => 'profiling',
            ],
            'timecreated ASC, id ASC'
        );

        $history = [];
        foreach ($records as $record) {
            $snapshot = json_decode($record->data_payload, true);
            if (is_array($snapshot)) {
                $snapshot['_record_id']   = (int) $record->id;
                $snapshot['_timecreated'] = (int) $record->timecreated;
                $history[] = $snapshot;
            }
        }

        return $history;
    }

    /**
     * Compute b1 access count and return b1 score.
     */
    private function compute_b1_score(int $userid, int $courseid, &$access_count): float {
        global $DB;
        $access_count = $DB->count_records_select(
            self::TABLE_RECORD,
            'userid = ? AND courseid = ? AND record_type = ?',
            [$userid, $courseid, 'interaction']
        );
        // Add logins from activity log
        $access_count += $DB->count_records('acmls_activity_log', [
            'userid' => $userid,
            'courseid' => $courseid,
            'event_type' => 'user_loggedin',
        ]);
        return min(100.0, ($access_count / 20.0) * 100.0);
    }

    /**
     * Compute b2 completion count and return b2 score.
     */
    private function compute_b2_score(int $userid, int $courseid, &$completion_count): float {
        global $DB;

        $total_trackable = $DB->count_records_sql("
            SELECT COUNT(*) 
              FROM {course_modules} cm
             WHERE cm.course = :courseid 
               AND cm.completion > 0
        ", ['courseid' => $courseid]);

        $completion_count = $DB->count_records_sql("
            SELECT COUNT(*)
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cmc.coursemoduleid = cm.id
             WHERE cmc.userid = :userid
               AND cm.course = :courseid
               AND cmc.completionstate = 1
        ", ['userid' => $userid, 'courseid' => $courseid]);

        return ($total_trackable > 0) ? min(100.0, ($completion_count / $total_trackable) * 100.0) : 100.0;
    }

    /**
     * Compute b3 punctuality and return b3 score.
     */
    private function compute_b3_score(int $userid, int $courseid, &$punctual_count): float {
        global $DB;

        // Fetch all assignments in the course with a valid due date.
        $assignments = $DB->get_records('assign', ['course' => $courseid], '', 'id, duedate');
        if (empty($assignments)) {
            $punctual_count = 0;
            return 100.0;
        }

        $punctual_count = 0;
        $active_assigns = 0;

        foreach ($assignments as $assign) {
            $duedate = (int) $assign->duedate;
            if ($duedate <= 0) {
                continue;
            }
            $active_assigns++;

            // Check if student has submitted.
            $submission = $DB->get_record('assign_submission', [
                'assignment' => $assign->id,
                'userid' => $userid,
                'status' => 'submitted',
            ], 'timemodified');

            if ($submission) {
                if ((int) $submission->timemodified <= $duedate) {
                    $punctual_count++;
                }
            }
        }

        if ($active_assigns === 0) {
            return 100.0;
        }

        return min(100.0, ($punctual_count / $active_assigns) * 100.0);
    }

    /**
     * Compute c1 quiz average and return c1 score.
     */
    private function compute_c1_score(int $userid, int $courseid, &$quiz_avg): float {
        global $DB;

        $grades = $DB->get_records_sql("
            SELECT qg.id, qg.grade, q.grade as maxgrade
              FROM {quiz_grades} qg
              JOIN {quiz} q ON qg.quiz = q.id
             WHERE q.course = :courseid
               AND qg.userid = :userid
        ", ['courseid' => $courseid, 'userid' => $userid]);

        if (empty($grades)) {
            $quiz_avg = 0.0;
            return 100.0; // Default if no quizzes
        }

        $total_pct = 0.0;
        foreach ($grades as $g) {
            $max = (float) $g->maxgrade;
            $grade = (float) $g->grade;
            if ($max > 0) {
                $total_pct += ($grade / $max) * 100.0;
            }
        }

        $quiz_avg = round($total_pct / count($grades), 2);
        return $quiz_avg;
    }

    /**
     * Compute c2 quiz attempts and return c2 score.
     */
    private function compute_c2_score(int $userid, int $courseid, &$total_attempts): float {
        global $DB;

        // Get total attempts count across all quizzes in the course for this user.
        $attempts = $DB->get_records_sql("
            SELECT quiz, COUNT(*) as attempts_count
              FROM {quiz_attempts}
             WHERE userid = :userid
               AND state = 'finished'
             GROUP BY quiz
        ", ['userid' => $userid]);

        if (empty($attempts)) {
            $total_attempts = 0;
            return 100.0;
        }

        $total_attempts = 0;
        $scores = [];
        foreach ($attempts as $att) {
            $count = (int) $att->attempts_count;
            $total_attempts += $count;
            if ($count === 1) {
                $scores[] = 100.0;
            } else if ($count === 2) {
                $scores[] = 75.0;
            } else if ($count === 3) {
                $scores[] = 50.0;
            } else {
                $scores[] = 25.0;
            }
        }

        return round(array_sum($scores) / count($scores), 2);
    }
}
