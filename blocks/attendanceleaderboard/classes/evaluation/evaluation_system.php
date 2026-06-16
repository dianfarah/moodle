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
 * EvaluationSystem — integrates with Moodle Gradebook to assess Learner performance.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * Evaluation System for ACMLS.
 *
 * Responsible for:
 * - Subscribing to Moodle grade events and fetching scores from Gradebook API.
 * - Calculating aggregate performance metrics for a Learner in a course.
 * - Detecting performance decline > threshold (default 20%) vs historical average.
 * - Sending performance decline alerts to the Coach component.
 * - Persisting score data to acmls_learner_record with full metadata.
 *
 * Requirements addressed:
 * - Req 5.3: Update Performance_Category based on latest score with historical context.
 * - Req 9 (Evaluation step): Fetch score from Gradebook, compute aggregate metrics,
 *   send to Profiling System within ≤60 seconds, save to Learner Record.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluation_system {

    /** @var string Database table for learner records. */
    const TABLE_LEARNER_RECORD = 'acmls_learner_record';

    /** @var int Default number of historical scores to use for decline detection. */
    const DEFAULT_HISTORY_SIZE = 5;

    /**
     * Percentage decline threshold that triggers a performance alert.
     * Read from plugin config; falls back to 20.0.
     *
     * @var float
     */
    private float $decline_threshold_percent;

    /**
     * Constructor — loads the decline threshold from plugin config.
     */
    public function __construct() {
        $configured = get_config('block_attendanceleaderboard', 'performance_decline_threshold');
        $this->decline_threshold_percent = ($configured !== false && is_numeric($configured))
            ? (float) $configured
            : 20.0;
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Handle a Moodle grade-related event.
     *
     * Extracts the user, course, and course-module from the event, fetches the
     * current Gradebook score, saves it to the Learner Record, and checks for
     * a performance decline.
     *
     * @param  \core\event\base $event  The Moodle grade event.
     * @return void
     */
    public function handle_grade_event(\core\event\base $event): void {
        $userid   = (int) $event->userid;
        $courseid = (int) $event->courseid;
        $cmid     = (int) ($event->objectid ?? 0);

        if ($userid <= 0 || $courseid <= 0) {
            debugging(
                'ACMLS EvaluationSystem: handle_grade_event received invalid userid or courseid.',
                DEBUG_DEVELOPER
            );
            return;
        }

        // Fetch the score from Gradebook.
        $score = $this->fetch_gradebook_score($userid, $cmid);

        if ($score === null) {
            // Score unavailable — already logged inside fetch_gradebook_score().
            return;
        }

        // Persist the score to the Learner Record.
        $this->save_to_learner_record($userid, $courseid, [
            'score'          => $score,
            'cmid'           => $cmid,
            'event_type'     => get_class($event),
            'source'         => 'grade_event',
        ]);

        // Check for performance decline and alert Coach if needed.
        $metrics = $this->calculate_aggregate_metrics($userid, $courseid);
        if ($this->detect_performance_decline($userid, $courseid)) {
            $this->send_alert_to_coach($userid, $courseid, $metrics);
        }

        // Forward performance data to Profiling System if available.
        $this->notify_profiling_system($userid, $courseid, $metrics);
    }

    /**
     * Fetch the current Gradebook score for a user and course-module.
     *
     * Uses Moodle's gradelib functions. Returns null and logs an error if the
     * score cannot be retrieved.
     *
     * @param  int       $userid  Moodle user ID.
     * @param  int       $cmid    Course-module ID (used to look up the grade item).
     * @return float|null         The final grade (0–100 scale), or null if unavailable.
     */
    public function fetch_gradebook_score(int $userid, int $cmid): ?float {
        global $CFG, $DB;

        if ($userid <= 0 || $cmid <= 0) {
            return null;
        }

        // Load Moodle grade library.
        require_once($CFG->libdir . '/gradelib.php');

        try {
            // Resolve the grade_item for this course-module.
            $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, course, module, instance', IGNORE_MISSING);
            if (!$cm) {
                debugging(
                    "ACMLS EvaluationSystem: course_module record not found for cmid={$cmid}.",
                    DEBUG_DEVELOPER
                );
                return null;
            }

            // Fetch the grade_grades record directly via DB for reliability.
            // First, find the grade_item for this course-module.
            $grade_item = $DB->get_record('grade_items', [
                'courseid'   => $cm->course,
                'itemtype'   => 'mod',
                'itemmodule' => $DB->get_field('modules', 'name', ['id' => $cm->module]),
                'iteminstance' => $cm->instance,
            ], '*', IGNORE_MISSING);

            if (!$grade_item) {
                debugging(
                    "ACMLS EvaluationSystem: grade_item not found for cmid={$cmid}.",
                    DEBUG_DEVELOPER
                );
                return null;
            }

            // Fetch the grade_grades record for this user and grade_item.
            $grade_record = $DB->get_record('grade_grades', [
                'itemid' => $grade_item->id,
                'userid' => $userid,
            ], '*', IGNORE_MISSING);

            if (!$grade_record || $grade_record->finalgrade === null) {
                debugging(
                    "ACMLS EvaluationSystem: No grade found for userid={$userid}, itemid={$grade_item->id}.",
                    DEBUG_DEVELOPER
                );
                return null;
            }

            // Normalise to 0–100 scale.
            $raw_grade  = (float) $grade_record->finalgrade;
            $grade_max  = (float) ($grade_item->grademax ?: 100);
            $grade_min  = (float) ($grade_item->grademin ?: 0);
            $range      = $grade_max - $grade_min;

            if ($range <= 0) {
                return $raw_grade;
            }

            return (($raw_grade - $grade_min) / $range) * 100.0;

        } catch (\Exception $e) {
            debugging(
                'ACMLS EvaluationSystem: fetch_gradebook_score exception — ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * Calculate aggregate performance metrics for a Learner in a course.
     *
     * Queries acmls_learner_record for all 'performance' records belonging to
     * this user/course and computes:
     *   - average_score   : mean of all recorded scores
     *   - latest_score    : most recent score
     *   - score_count     : number of score records
     *   - trend           : 'improving' | 'declining' | 'stable' | 'insufficient_data'
     *   - class_average   : mean score across all learners in the course
     *   - vs_class        : difference between learner average and class average
     *
     * @param  int   $userid    Moodle user ID.
     * @param  int   $courseid  Course ID.
     * @return array            Associative array of metric keys → values.
     */
    public function calculate_aggregate_metrics(int $userid, int $courseid): array {
        global $DB;

        $metrics = [
            'userid'            => $userid,
            'courseid'          => $courseid,
            'average_score'     => 0.0,
            'latest_score'      => null,
            'score_count'       => 0,
            'trend'             => 'insufficient_data',
            'class_average'     => 0.0,
            'vs_class'          => 0.0,
        ];

        // Fetch all performance records for this learner in this course.
        $records = $DB->get_records(
            self::TABLE_LEARNER_RECORD,
            ['userid' => $userid, 'courseid' => $courseid, 'record_type' => 'performance'],
            'timecreated ASC'
        );

        if (empty($records)) {
            return $metrics;
        }

        $scores = [];
        foreach ($records as $record) {
            $payload = json_decode($record->data_payload, true);
            if (isset($payload['score']) && is_numeric($payload['score'])) {
                $scores[] = (float) $payload['score'];
            }
        }

        if (empty($scores)) {
            return $metrics;
        }

        $count   = count($scores);
        $average = array_sum($scores) / $count;
        $latest  = end($scores);

        $metrics['score_count']   = $count;
        $metrics['average_score'] = round($average, 2);
        $metrics['latest_score']  = round($latest, 2);

        // Determine trend using last two scores.
        if ($count >= 2) {
            $prev = $scores[$count - 2];
            if ($latest > $prev + 0.5) {
                $metrics['trend'] = 'improving';
            } elseif ($latest < $prev - 0.5) {
                $metrics['trend'] = 'declining';
            } else {
                $metrics['trend'] = 'stable';
            }
        }

        // Calculate class average across all learners in this course.
        $class_records = $DB->get_records(
            self::TABLE_LEARNER_RECORD,
            ['courseid' => $courseid, 'record_type' => 'performance'],
            'timecreated ASC'
        );

        $class_scores = [];
        foreach ($class_records as $record) {
            $payload = json_decode($record->data_payload, true);
            if (isset($payload['score']) && is_numeric($payload['score'])) {
                $class_scores[] = (float) $payload['score'];
            }
        }

        if (!empty($class_scores)) {
            $class_avg = array_sum($class_scores) / count($class_scores);
            $metrics['class_average'] = round($class_avg, 2);
            $metrics['vs_class']      = round($average - $class_avg, 2);
        }

        return $metrics;
    }

    /**
     * Detect whether a Learner's performance has declined by more than the threshold.
     *
     * Algorithm:
     *   historical_scores = last N scores before the most recent score (N = DEFAULT_HISTORY_SIZE)
     *   historical_average = mean(historical_scores)
     *   current_score      = latest score
     *   decline_percent    = ((historical_average - current_score) / historical_average) * 100
     *   return decline_percent > threshold (strictly greater than, not >=)
     *
     * Returns false if there is no historical data (cannot determine decline).
     *
     * @param  int  $userid    Moodle user ID.
     * @param  int  $courseid  Course ID.
     * @return bool            True if decline exceeds threshold; false otherwise.
     */
    public function detect_performance_decline(int $userid, int $courseid): bool {
        global $DB;

        // Fetch all performance records ordered by time ascending.
        $records = $DB->get_records(
            self::TABLE_LEARNER_RECORD,
            ['userid' => $userid, 'courseid' => $courseid, 'record_type' => 'performance'],
            'timecreated ASC'
        );

        if (empty($records)) {
            return false;
        }

        $scores = [];
        foreach ($records as $record) {
            $payload = json_decode($record->data_payload, true);
            if (isset($payload['score']) && is_numeric($payload['score'])) {
                $scores[] = (float) $payload['score'];
            }
        }

        // Need at least 2 scores: at least 1 historical + 1 current.
        if (count($scores) < 2) {
            return false;
        }

        // Current score is the last element.
        $current_score = array_pop($scores);

        // Historical scores: up to DEFAULT_HISTORY_SIZE scores before current.
        $historical_scores = array_slice($scores, -self::DEFAULT_HISTORY_SIZE);

        if (empty($historical_scores)) {
            return false;
        }

        $historical_average = array_sum($historical_scores) / count($historical_scores);

        // Avoid division by zero.
        if ($historical_average == 0) {
            return false;
        }

        $decline_percent = (($historical_average - $current_score) / $historical_average) * 100.0;

        // Strictly greater than threshold (not >=).
        return $decline_percent > $this->decline_threshold_percent;
    }

    /**
     * Send a performance decline alert to the Coach component.
     *
     * If the Coach class exists, calls Coach::receive_performance_alert().
     * Otherwise, logs the alert and stores it in acmls_learner_record with
     * record_type='performance_alert'.
     *
     * @param  int   $userid    Moodle user ID.
     * @param  int   $courseid  Course ID.
     * @param  array $metrics   Aggregate metrics from calculate_aggregate_metrics().
     * @return void
     */
    public function send_alert_to_coach(int $userid, int $courseid, array $metrics): void {
        $alert_data = [
            'alert_type'    => 'performance_decline',
            'userid'        => $userid,
            'courseid'      => $courseid,
            'metrics'       => $metrics,
            'threshold'     => $this->decline_threshold_percent,
            'timecreated'   => time(),
        ];

        // Attempt to notify the Coach component.
        if (class_exists('\block_attendanceleaderboard\coach\coach')) {
            try {
                \block_attendanceleaderboard\coach\coach::receive_performance_alert(
                    $userid,
                    $courseid,
                    $metrics
                );
            } catch (\Exception $e) {
                debugging(
                    'ACMLS EvaluationSystem: Coach::receive_performance_alert failed — ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        } else {
            // Coach not yet available — log the alert.
            debugging(
                'ACMLS EvaluationSystem: Performance decline alert for userid=' . $userid .
                ', courseid=' . $courseid .
                ', average_score=' . ($metrics['average_score'] ?? 'N/A') .
                ', latest_score=' . ($metrics['latest_score'] ?? 'N/A'),
                DEBUG_DEVELOPER
            );
        }

        // Always persist the alert to the Learner Record.
        $this->save_to_learner_record($userid, $courseid, $alert_data, 'performance_alert');
    }

    /**
     * Save score data to acmls_learner_record.
     *
     * Inserts a new record with record_type='performance' (or a custom type),
     * source_component='evaluation', and the provided data serialised as JSON.
     *
     * @param  int    $userid    Moodle user ID.
     * @param  int    $courseid  Course ID.
     * @param  array  $data      Data payload to store (will be JSON-encoded).
     * @param  string $type      Record type (default: 'performance').
     * @return int               ID of the inserted record.
     */
    public function save_to_learner_record(
        int $userid,
        int $courseid,
        array $data,
        string $type = 'performance'
    ): int {
        global $DB;

        $record = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->record_type      = $type;
        $record->source_component = 'evaluation';
        $record->data_payload     = json_encode($data);
        $record->profile_version  = null;
        $record->timecreated      = time();

        return (int) $DB->insert_record(self::TABLE_LEARNER_RECORD, $record);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Forward performance metrics to the Profiling System if available.
     *
     * @param  int   $userid    Moodle user ID.
     * @param  int   $courseid  Course ID.
     * @param  array $metrics   Aggregate metrics.
     * @return void
     */
    private function notify_profiling_system(int $userid, int $courseid, array $metrics): void {
        if (!class_exists('\block_attendanceleaderboard\profiling\profiling_system')) {
            return;
        }

        try {
            $profiler = new \block_attendanceleaderboard\profiling\profiling_system();
            if (method_exists($profiler, 'update_profile')) {
                $profiler->update_profile($userid, $courseid, $metrics);
            }
        } catch (\Exception $e) {
            debugging(
                'ACMLS EvaluationSystem: notify_profiling_system failed — ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
