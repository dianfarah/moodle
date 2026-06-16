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
 * Leaderboard — engagement-based ranking system for ACMLS.
 *
 * Manages learner rankings based on a weighted composite score of attendance,
 * engagement, and completion. Supports course, program, and institution scopes.
 * Integrates with the Motivation Component to trigger achievement prompts when
 * a learner's rank improves.
 *
 * Requirements addressed:
 * - Req 12.1: Calculate composite score using configurable weights.
 * - Req 12.2: Update rankings periodically (≤15 min interval via scheduled task).
 * - Req 12.3: Track rank changes and points to next rank.
 * - Req 12.4: Trigger achievement prompt when rank improves.
 * - Req 12.5: Support privacy mode (anonymize display names).
 * - Req 12.6: Support course, program, and institution scopes.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Leaderboard — engagement-based ranking system.
 *
 * Responsibilities:
 * - Calculate composite scores from attendance, engagement, and completion.
 * - Update and persist rankings for all learners in a course/scope.
 * - Detect rank improvements and trigger achievement prompts.
 * - Anonymize display names when privacy mode is enabled.
 * - Support multiple ranking scopes: course, program, institution.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class leaderboard {

    // =========================================================================
    // Constants
    // =========================================================================

    /** @var string Database table for leaderboard data. */
    const TABLE = 'acmls_leaderboard';

    /** @var string Scope: course-level ranking. */
    const SCOPE_COURSE = 'course';

    /** @var string Scope: program-level ranking. */
    const SCOPE_PROGRAM = 'program';

    /** @var string Scope: institution-level ranking. */
    const SCOPE_INSTITUTION = 'institution';

    /** @var float Default weight for attendance score. */
    const DEFAULT_WEIGHT_ATTENDANCE = 0.4;

    /** @var float Default weight for engagement score. */
    const DEFAULT_WEIGHT_ENGAGEMENT = 0.4;

    /** @var float Default weight for completion score. */
    const DEFAULT_WEIGHT_COMPLETION = 0.2;

    // =========================================================================
    // Properties
    // =========================================================================

    /** @var float Weight for attendance score (from config, default 0.4). */
    private float $weight_attendance;

    /** @var float Weight for engagement score (from config, default 0.4). */
    private float $weight_engagement;

    /** @var float Weight for completion score (from config, default 0.2). */
    private float $weight_completion;

    /** @var bool Whether privacy mode is enabled (from config, default false). */
    private bool $privacy_enabled;

    // =========================================================================
    // Constructor
    // =========================================================================

    /**
     * Constructor.
     *
     * Loads configurable weights and privacy setting from plugin configuration.
     */
    public function __construct() {
        $weights = $this->load_weights_from_config();
        $this->weight_attendance = $weights['attendance'];
        $this->weight_engagement = $weights['engagement'];
        $this->weight_completion = $weights['completion'];

        $privacy = get_config('block_attendanceleaderboard', 'leaderboard_privacy');
        $this->privacy_enabled = !empty($privacy);
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Calculate the composite leaderboard score for a learner.
     *
     * Formula:
     *   total_score = (attendance_score × w1) + (engagement_score × w2) + (completion_score × w3)
     *
     * Weights are loaded from plugin configuration (defaults: 0.4, 0.4, 0.2).
     *
     * @param  float $attendance_score Attendance score (0–100).
     * @param  float $engagement_score Engagement score (0–100).
     * @param  float $completion_score Completion score (0–100).
     * @return float                   Composite total score.
     */
    public function calculate_score(
        float $attendance_score,
        float $engagement_score,
        float $completion_score
    ): float {
        return ($attendance_score * $this->weight_attendance)
             + ($engagement_score * $this->weight_engagement)
             + ($completion_score * $this->weight_completion);
    }

    /**
     * Update rankings for all learners in a given course and scope.
     *
     * Logic:
     * 1. Fetch all learner records for this courseid + scope from acmls_leaderboard.
     * 2. Sort by total_score DESC.
     * 3. Assign new rank (1 = highest score).
     * 4. Calculate rank_change = previous_rank - current_rank (positive = improved).
     * 5. Calculate points_to_next = score of rank above - this learner's score (null for rank 1).
     * 6. Update display_name if privacy enabled.
     * 7. Set last_updated = time().
     * 8. Persist all updates to DB.
     * 9. For each learner where rank improved (rank_change > 0), call trigger_achievement_prompt().
     *
     * @param  int    $courseid Moodle course ID.
     * @param  string $scope    Ranking scope: 'course', 'program', or 'institution'.
     * @return void
     */
    public function update_rankings(int $courseid, string $scope = self::SCOPE_COURSE): void {
        global $DB;

        // 1. Fetch all learner records for this courseid + scope.
        $records = $DB->get_records(
            self::TABLE,
            ['courseid' => $courseid, 'scope' => $scope],
            '',
            '*'
        );

        if (empty($records)) {
            return;
        }

        // 2. Sort by total_score DESC.
        usort($records, function ($a, $b) {
            return $b->total_score <=> $a->total_score;
        });

        $now = time();
        $records_to_update = [];
        $improved_userids  = [];

        // 3–7. Assign ranks and calculate derived fields.
        foreach ($records as $new_rank => $record) {
            $new_rank_number = $new_rank + 1; // 1-indexed.

            // 4. Calculate rank_change (positive = improved = moved up).
            $previous_rank = isset($record->current_rank) && $record->current_rank > 0
                ? (int) $record->current_rank
                : null;

            $rank_change = ($previous_rank !== null)
                ? ($previous_rank - $new_rank_number)
                : null;

            // 5. Calculate points_to_next.
            $points_to_next = null;
            if ($new_rank > 0) {
                // The record at index $new_rank - 1 has the rank above.
                $above_score    = (float) $records[$new_rank - 1]->total_score;
                $points_to_next = $above_score - (float) $record->total_score;
                // Ensure non-negative (floating point safety).
                if ($points_to_next < 0) {
                    $points_to_next = 0.0;
                }
            }

            // 6. Update display_name if privacy enabled.
            $display_name = $this->privacy_enabled
                ? $this->anonymize_display((int) $record->userid)
                : null;

            // Build update object.
            $update                 = new \stdClass();
            $update->id             = $record->id;
            $update->previous_rank  = $previous_rank;
            $update->current_rank   = $new_rank_number;
            $update->rank_change    = $rank_change;
            $update->points_to_next = $points_to_next;
            $update->last_updated   = $now;

            if ($display_name !== null) {
                $update->display_name = $display_name;
            }

            $records_to_update[] = $update;

            // 9. Track learners whose rank improved.
            if ($rank_change !== null && $rank_change > 0) {
                $improved_userids[] = (int) $record->userid;
            }
        }

        // 8. Persist all updates to DB.
        foreach ($records_to_update as $update) {
            $DB->update_record(self::TABLE, $update);
        }

        // 9. Trigger achievement prompts for improved learners.
        foreach ($improved_userids as $userid) {
            $this->trigger_achievement_prompt($userid, $courseid);
        }
    }

    /**
     * Get the leaderboard rank data for a specific learner.
     *
     * @param  int    $userid   Moodle user ID.
     * @param  int    $courseid Moodle course ID.
     * @param  string $scope    Ranking scope: 'course', 'program', or 'institution'.
     * @return array            Associative array with rank data, or empty array if not found.
     */
    public function get_learner_rank(
        int $userid,
        int $courseid,
        string $scope = self::SCOPE_COURSE
    ): array {
        global $DB;

        $record = $DB->get_record(
            self::TABLE,
            ['userid' => $userid, 'courseid' => $courseid, 'scope' => $scope],
            '*',
            IGNORE_MISSING
        );

        if (!$record) {
            return [];
        }

        return [
            'userid'           => (int) $record->userid,
            'courseid'         => (int) $record->courseid,
            'scope'            => (string) $record->scope,
            'current_rank'     => (int) $record->current_rank,
            'previous_rank'    => isset($record->previous_rank) ? (int) $record->previous_rank : null,
            'rank_change'      => isset($record->rank_change) ? (int) $record->rank_change : null,
            'total_score'      => (float) $record->total_score,
            'attendance_score' => (float) $record->attendance_score,
            'engagement_score' => (float) $record->engagement_score,
            'completion_score' => (float) $record->completion_score,
            'points_to_next'   => isset($record->points_to_next) ? (float) $record->points_to_next : null,
            'display_name'     => (string) ($record->display_name ?? ''),
            'last_updated'     => (int) $record->last_updated,
        ];
    }

    /**
     * Trigger an achievement prompt for a learner whose rank has improved.
     *
     * Logic:
     * - Verify that the learner's rank has actually improved (current_rank < previous_rank).
     * - If MotivationComponent class exists, call get_intervention_category() with
     *   High performance + High motivation → 'achievement'.
     * - Otherwise, log a debug message.
     *
     * @param  int $userid   Moodle user ID.
     * @param  int $courseid Moodle course ID.
     * @return void
     */
    public function trigger_achievement_prompt(int $userid, int $courseid): void {
        global $DB;

        // Verify rank has improved.
        $record = $DB->get_record(
            self::TABLE,
            ['userid' => $userid, 'courseid' => $courseid],
            'current_rank, previous_rank, rank_change',
            IGNORE_MISSING
        );

        if (!$record) {
            return;
        }

        $rank_change = isset($record->rank_change) ? (int) $record->rank_change : 0;

        // Only trigger if rank improved (positive rank_change means moved up).
        if ($rank_change <= 0) {
            return;
        }

        // Attempt to call MotivationComponent if available.
        $motivation_class = '\block_attendanceleaderboard\motivation\motivation_component';

        if (class_exists($motivation_class)) {
            // High performance (3) + High motivation (>=70) → 'achievement'.
            $category = $motivation_class::get_intervention_category(3, 75.0);
            debugging(
                "ACMLS Leaderboard: Achievement prompt triggered for user {$userid} in course {$courseid}."
                . " Category: {$category}",
                DEBUG_DEVELOPER
            );
        } else {
            debugging(
                "ACMLS Leaderboard: Achievement prompt triggered for user {$userid} in course {$courseid}."
                . " MotivationComponent not available.",
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Return the display name for a learner, respecting the privacy setting.
     *
     * - Privacy enabled:  "J. D." (first letter of firstname + ". " + first letter of lastname + ".")
     * - Privacy disabled: full name via fullname($user)
     *
     * @param  int    $userid Moodle user ID.
     * @return string         Display name (anonymized or full).
     */
    public function anonymize_display(int $userid): string {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname', IGNORE_MISSING);

        if (!$user) {
            return 'Unknown';
        }

        if ($this->privacy_enabled) {
            $first_initial = !empty($user->firstname) ? mb_strtoupper(mb_substr($user->firstname, 0, 1)) : '?';
            $last_initial  = !empty($user->lastname)  ? mb_strtoupper(mb_substr($user->lastname,  0, 1)) : '?';
            return $first_initial . '. ' . $last_initial . '.';
        }

        return fullname($user);
    }

    /**
     * Get the top-N ranked learners for a given course and scope.
     *
     * @param  int    $courseid Moodle course ID.
     * @param  string $scope    Ranking scope: 'course', 'program', or 'institution'.
     * @param  int    $limit    Maximum number of records to return (default 50).
     * @return array            Array of leaderboard records ordered by current_rank ASC.
     */
    public function get_scope_rankings(
        int $courseid,
        string $scope = self::SCOPE_COURSE,
        int $limit = 50
    ): array {
        global $DB;

        $sql = "SELECT *
                  FROM {" . self::TABLE . "}
                 WHERE courseid = :courseid
                   AND scope    = :scope
                   AND current_rank > 0
                 ORDER BY current_rank ASC";

        $params = [
            'courseid' => $courseid,
            'scope'    => $scope,
        ];

        $records = $DB->get_records_sql($sql, $params, 0, $limit);

        $result = [];
        foreach ($records as $record) {
            $result[] = [
                'userid'           => (int) $record->userid,
                'courseid'         => (int) $record->courseid,
                'scope'            => (string) $record->scope,
                'current_rank'     => (int) $record->current_rank,
                'previous_rank'    => isset($record->previous_rank) ? (int) $record->previous_rank : null,
                'rank_change'      => isset($record->rank_change) ? (int) $record->rank_change : null,
                'total_score'      => (float) $record->total_score,
                'attendance_score' => (float) $record->attendance_score,
                'engagement_score' => (float) $record->engagement_score,
                'completion_score' => (float) $record->completion_score,
                'points_to_next'   => isset($record->points_to_next) ? (float) $record->points_to_next : null,
                'display_name'     => (string) ($record->display_name ?? ''),
                'last_updated'     => (int) $record->last_updated,
            ];
        }

        return $result;
    }

    /**
     * Insert or update a learner's score record in the leaderboard table.
     *
     * - If a record exists for userid + courseid + scope: update scores and last_updated.
     * - If no record exists: insert a new record with current_rank=0, previous_rank=null.
     *
     * The total_score is recalculated using the current weight configuration.
     *
     * @param  int    $userid     Moodle user ID.
     * @param  int    $courseid   Moodle course ID.
     * @param  string $scope      Ranking scope: 'course', 'program', or 'institution'.
     * @param  float  $attendance Attendance score (0–100).
     * @param  float  $engagement Engagement score (0–100).
     * @param  float  $completion Completion score (0–100).
     * @return void
     */
    public function upsert_learner_score(
        int $userid,
        int $courseid,
        string $scope,
        float $attendance,
        float $engagement,
        float $completion
    ): void {
        global $DB;

        $total_score = $this->calculate_score($attendance, $engagement, $completion);

        $existing = $DB->get_record(
            self::TABLE,
            ['userid' => $userid, 'courseid' => $courseid, 'scope' => $scope],
            'id',
            IGNORE_MISSING
        );

        if ($existing) {
            // Update existing record.
            $update                   = new \stdClass();
            $update->id               = $existing->id;
            $update->attendance_score = $attendance;
            $update->engagement_score = $engagement;
            $update->completion_score = $completion;
            $update->total_score      = $total_score;
            $update->last_updated     = time();

            $DB->update_record(self::TABLE, $update);
        } else {
            // Insert new record.
            $record                   = new \stdClass();
            $record->userid           = $userid;
            $record->courseid         = $courseid;
            $record->scope            = $scope;
            $record->attendance_score = $attendance;
            $record->engagement_score = $engagement;
            $record->completion_score = $completion;
            $record->total_score      = $total_score;
            $record->current_rank     = 0;
            $record->previous_rank    = null;
            $record->rank_change      = null;
            $record->points_to_next   = null;
            $record->display_name     = null;
            $record->last_updated     = time();

            $DB->insert_record(self::TABLE, $record);
        }
    }

    // =========================================================================
    // Accessors (for testing)
    // =========================================================================

    /**
     * Get the configured weight for attendance score.
     *
     * @return float
     */
    public function get_weight_attendance(): float {
        return $this->weight_attendance;
    }

    /**
     * Get the configured weight for engagement score.
     *
     * @return float
     */
    public function get_weight_engagement(): float {
        return $this->weight_engagement;
    }

    /**
     * Get the configured weight for completion score.
     *
     * @return float
     */
    public function get_weight_completion(): float {
        return $this->weight_completion;
    }

    /**
     * Check whether privacy mode is enabled.
     *
     * @return bool
     */
    public function is_privacy_enabled(): bool {
        return $this->privacy_enabled;
    }

    /**
     * Load weights from config while preserving valid zero values.
     *
     * If the stored weights are present but do not sum to 1.0, they are
     * normalised so score calculations stay mathematically consistent.
     *
     * @return array<string,float>
     */
    private function load_weights_from_config(): array {
        $attendance = $this->read_weight('weight_attendance', self::DEFAULT_WEIGHT_ATTENDANCE);
        $engagement = $this->read_weight('weight_engagement', self::DEFAULT_WEIGHT_ENGAGEMENT);
        $completion = $this->read_weight('weight_completion', self::DEFAULT_WEIGHT_COMPLETION);

        $sum = $attendance + $engagement + $completion;
        if ($sum <= 0.0) {
            return [
                'attendance' => self::DEFAULT_WEIGHT_ATTENDANCE,
                'engagement' => self::DEFAULT_WEIGHT_ENGAGEMENT,
                'completion' => self::DEFAULT_WEIGHT_COMPLETION,
            ];
        }

        if (abs($sum - 1.0) <= 0.01) {
            return [
                'attendance' => $attendance,
                'engagement' => $engagement,
                'completion' => $completion,
            ];
        }

        return [
            'attendance' => $attendance / $sum,
            'engagement' => $engagement / $sum,
            'completion' => $completion / $sum,
        ];
    }

    /**
     * Read a single configured weight, preserving zero as a valid value.
     *
     * @param string $name Config key.
     * @param float $default Default weight.
     * @return float
     */
    private function read_weight(string $name, float $default): float {
        $raw = get_config('block_attendanceleaderboard', $name);
        if ($raw === false || $raw === null || $raw === '') {
            return $default;
        }

        if (!is_numeric($raw)) {
            return $default;
        }

        $weight = (float) $raw;
        if ($weight < 0.0 || $weight > 1.0) {
            return $default;
        }

        return $weight;
    }
}
