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
 * Motivation Component for ACMLS.
 *
 * Manages motivational logic: threshold monitoring, intervention requests,
 * learner response recording, and leaderboard factor integration.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\motivation;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\profiling\learner_profile;

/**
 * Motivation Component — motivational logic and intervention management.
 *
 * Responsibilities:
 * - Monitor motivation threshold (detect sustained low motivation > 3 days).
 * - Request motivational interventions via the sentence repository.
 * - Record learner responses to interventions.
 * - Integrate leaderboard data as a motivational factor.
 *
 * Intervention mapping table (from design.md):
 * | Performance_Category | Motivation_Level  | Category      |
 * |----------------------|-------------------|---------------|
 * | Low                  | Low (<40)         | recovery      |
 * | Low                  | Middle (40-69)    | persistence   |
 * | Middle               | Low (<40)         | recovery      |
 * | Middle               | Middle (40-69)    | reinforcement |
 * | High                 | Any               | achievement   |
 * | Any                  | High (>=70)       | achievement   |
 *
 * Requirements addressed:
 * - Req 11.1: Define and store motivational rules.
 * - Req 11.2: Trigger intervention when motivation below threshold for >3 days.
 * - Req 11.3: Support configurable motivational thresholds.
 * - Req 11.4: Record learner response and use for rule adjustment.
 * - Req 11.5: Integrate leaderboard data as motivational factor.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class motivation_component {

    /** @var string Learner profile table. */
    const TABLE_PROFILE = 'acmls_learner_profile';

    /** @var string Learner record table. */
    const TABLE_LEARNER_RECORD = 'acmls_learner_record';

    /** @var string Leaderboard table. */
    const TABLE_LEADERBOARD = 'acmls_leaderboard';

    /** @var string Structured motivation feedback table. */
    const TABLE_FEEDBACK = 'acmls_motivation_feedback';

    /** @var string Record type for motivation delivery. */
    const RECORD_TYPE_DELIVERY = 'motivation_delivery';

    /** @var string Record type for learner response. */
    const RECORD_TYPE_RESPONSE = 'motivation_response';

    /** @var string Record type for emotional feedback. */
    const RECORD_TYPE_FEEDBACK = 'motivation_feedback';

    /** @var string Record type for profile snapshot. */
    const RECORD_TYPE_SNAPSHOT = 'profile_snapshot';

    /** @var int Default motivation threshold (below this = low motivation). */
    const DEFAULT_THRESHOLD = 40;

    /** @var int Number of consecutive days below threshold to trigger intervention. */
    const CONSECUTIVE_DAYS_THRESHOLD = 3;

    /** @var int Number of days to look back for snapshot history (>3 days requires 4-day window). */
    const SNAPSHOT_LOOKBACK_DAYS = 4;

    /** @var int Minimum number of snapshots required to confirm sustained low motivation. */
    const MIN_SNAPSHOTS_REQUIRED = 3;

    /** @var int Learner response: ignored. */
    const RESPONSE_IGNORED = 0;

    /** @var int Learner response: engaged. */
    const RESPONSE_ENGAGED = 1;

    /** @var array<string,int> Allowed emotional feedback options and their scores. */
    const FEELING_SCORES = [
        'very_motivated' => 5,
        'motivated' => 4,
        'neutral' => 3,
        'confused' => 2,
        'discouraged' => 1,
    ];

    // =========================================================================
    // Intervention mapping constants
    // =========================================================================

    /** @var string Intervention category: recovery. */
    const CATEGORY_RECOVERY = 'recovery';

    /** @var string Intervention category: persistence. */
    const CATEGORY_PERSISTENCE = 'persistence';

    /** @var string Intervention category: reinforcement. */
    const CATEGORY_REINFORCEMENT = 'reinforcement';

    /** @var string Intervention category: achievement. */
    const CATEGORY_ACHIEVEMENT = 'achievement';

    /** @var int Motivation level boundary: Low/Middle threshold. */
    const MOTIVATION_LOW_BOUNDARY = 40;

    /** @var int Motivation level boundary: Middle/High threshold. */
    const MOTIVATION_HIGH_BOUNDARY = 70;

    // =========================================================================
    // Properties
    // =========================================================================

    /** @var motivation_sentence_repository Sentence repository instance. */
    private motivation_sentence_repository $repository;

    /** @var int Motivation threshold (configurable). */
    private int $threshold;

    // =========================================================================
    // Constructor
    // =========================================================================

    /**
     * Constructor.
     *
     * @param motivation_sentence_repository|null $repository Optional repository (for testing).
     */
    public function __construct(?motivation_sentence_repository $repository = null) {
        $this->repository = $repository ?? new motivation_sentence_repository();

        // Load configurable threshold from plugin settings.
        $configured = (int) get_config('block_attendanceleaderboard', 'motivation_threshold');
        $this->threshold = ($configured > 0) ? $configured : self::DEFAULT_THRESHOLD;
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Check whether a Learner's motivation has been below threshold for >3 consecutive days.
     *
     * Logic:
     * 1. Check current motivation level — if at or above threshold, return false immediately.
     * 2. Query acmls_learner_record for profile_snapshot records in last 4 days
     *    (to check >3 consecutive days).
     * 3. If fewer than 3 snapshots exist → return false (insufficient data).
     * 4. Check if motivation_level was below threshold in ALL snapshots.
     * 5. Return true only if consistently below threshold across all snapshots.
     *
     * @param  learner_profile $profile Learner's current profile.
     * @return bool                     True if motivation has been below threshold for >3 days.
     */
    public function check_motivation_threshold(learner_profile $profile): bool {
        global $DB;

        // Check current motivation level.
        if ($profile->motivation_level >= $this->threshold) {
            return false;
        }

        // Query profile snapshots from the last SNAPSHOT_LOOKBACK_DAYS days (to check >3 consecutive days).
        $since = time() - (self::SNAPSHOT_LOOKBACK_DAYS * DAYSECS);

        $sql = "SELECT id, data_payload
                  FROM {" . self::TABLE_LEARNER_RECORD . "}
                 WHERE userid = :userid
                   AND courseid = :courseid
                   AND record_type = :record_type
                   AND timecreated >= :since
                 ORDER BY timecreated ASC";

        $params = [
            'userid'      => $profile->userid,
            'courseid'    => $profile->courseid,
            'record_type' => self::RECORD_TYPE_SNAPSHOT,
            'since'       => $since,
        ];

        $snapshots = $DB->get_records_sql($sql, $params);

        // If no historical data, we cannot confirm sustained low motivation.
        if (empty($snapshots)) {
            return false;
        }

        // If fewer than MIN_SNAPSHOTS_REQUIRED snapshots exist, insufficient data.
        if (count($snapshots) < self::MIN_SNAPSHOTS_REQUIRED) {
            return false;
        }

        // Check if motivation was below threshold in ALL snapshots.
        foreach ($snapshots as $snapshot) {
            $payload = json_decode($snapshot->data_payload, true);
            if (!is_array($payload)) {
                continue;
            }

            $snapshot_motivation = isset($payload['motivation_level'])
                ? (float) $payload['motivation_level']
                : null;

            // If any snapshot shows motivation at or above threshold, return false.
            if ($snapshot_motivation === null || $snapshot_motivation >= $this->threshold) {
                return false;
            }
        }

        // All snapshots show motivation below threshold — sustained low motivation confirmed.
        return true;
    }

    /**
     * Request a motivational intervention for a Learner.
     *
     * Determines the appropriate content category based on the intervention
     * mapping table, fetches relevant content from the repository (or uses
     * a fallback template), records the delivery, and returns the content.
     *
     * @param  learner_profile $profile  Learner's current profile.
     * @param  string          $category Intervention category (recovery|persistence|reinforcement|achievement).
     * @return string|null               The motivational content delivered, or null if none available.
     */
    public function request_intervention(learner_profile $profile, string $category): ?string {
        global $DB;

        // Determine motivation_target based on current motivation level.
        $motivation_target = $this->classify_motivation_target($profile->motivation_level);

        // Fetch relevant content from repository.
        $content = $this->repository->find_relevant(
            $profile->userid,
            $category,
            $profile->performance_category,
            $motivation_target
        );

        if ($content === null) {
            // No content found — try seeding templates and retry.
            $this->repository->seed_static_templates();
            $content = $this->repository->find_relevant(
                $profile->userid,
                $category,
                $profile->performance_category,
                $motivation_target
            );
        }

        if ($content === null) {
            return null;
        }

        // Record the delivery in acmls_learner_record.
        $content_hash = md5($content);
        $payload = [
            'content_hash'       => $content_hash,
            'category'           => $category,
            'performance_target' => $profile->performance_category,
            'motivation_target'  => $motivation_target,
        ];

        $record                   = new \stdClass();
        $record->userid           = $profile->userid;
        $record->courseid         = $profile->courseid;
        $record->record_type      = self::RECORD_TYPE_DELIVERY;
        $record->source_component = 'motivation';
        $record->data_payload     = json_encode($payload);
        $record->profile_version  = $profile->profile_version;
        $record->timecreated      = time();

        $DB->insert_record(self::TABLE_LEARNER_RECORD, $record);

        return $content;
    }

    /**
     * Record a Learner's response to a motivational intervention.
     *
     * Saves the response (0=ignored, 1=engaged) to acmls_learner_record
     * for use in future rule adjustments.
     *
     * @param  int $userid   Moodle user ID.
     * @param  int $courseid Moodle course ID.
     * @param  int $response Response value: 0=ignored, 1=engaged.
     * @return void
     */
    public function record_learner_response(int $userid, int $courseid, int $response): void {
        global $DB;

        $payload = [
            'response'   => $response,
            'response_label' => ($response === self::RESPONSE_ENGAGED) ? 'engaged' : 'ignored',
        ];

        $record                   = new \stdClass();
        $record->userid           = $userid;
        $record->courseid         = $courseid;
        $record->record_type      = self::RECORD_TYPE_RESPONSE;
        $record->source_component = 'motivation';
        $record->data_payload     = json_encode($payload);
        $record->profile_version  = null;
        $record->timecreated      = time();

        $DB->insert_record(self::TABLE_LEARNER_RECORD, $record);
    }

    /**
     * Record structured emotional feedback after a motivational popup is shown.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Moodle course ID.
     * @param array $feedback Feedback payload.
     * @return int Inserted feedback record ID.
     */
    public function record_emotional_feedback(int $userid, int $courseid, array $feedback): int {
        global $DB;

        $feelingkey = (string) ($feedback['feeling_key'] ?? '');
        if (!array_key_exists($feelingkey, self::FEELING_SCORES)) {
            throw new \invalid_parameter_exception('Invalid feeling_key provided.');
        }

        $feelingscore = isset($feedback['feeling_score']) && is_numeric($feedback['feeling_score'])
            ? (int) $feedback['feeling_score']
            : self::FEELING_SCORES[$feelingkey];

        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->sentenceid = !empty($feedback['sentenceid']) ? (int) $feedback['sentenceid'] : null;
        $record->category = (string) ($feedback['category'] ?? '');
        $record->source = (string) ($feedback['source'] ?? '');
        $record->feeling_key = $feelingkey;
        $record->feeling_score = $feelingscore;
        $record->reflection_note = isset($feedback['reflection_note']) ? trim((string) $feedback['reflection_note']) : null;
        $record->message_content = (string) ($feedback['message_content'] ?? '');
        $record->timecreated = time();

        $feedbackid = (int) $DB->insert_record(self::TABLE_FEEDBACK, $record);

        $payload = [
            'feedbackid' => $feedbackid,
            'sentenceid' => $record->sentenceid,
            'category' => $record->category,
            'source' => $record->source,
            'feeling_key' => $record->feeling_key,
            'feeling_score' => $record->feeling_score,
            'reflection_note' => $record->reflection_note,
        ];

        $history = new \stdClass();
        $history->userid = $userid;
        $history->courseid = $courseid;
        $history->record_type = self::RECORD_TYPE_FEEDBACK;
        $history->source_component = 'motivation';
        $history->data_payload = json_encode($payload);
        $history->profile_version = null;
        $history->timecreated = time();

        $DB->insert_record(self::TABLE_LEARNER_RECORD, $history);

        $this->record_learner_response($userid, $courseid, self::RESPONSE_ENGAGED);

        return $feedbackid;
    }

    /**
     * Integrate leaderboard data as a motivational factor.
     *
     * Queries acmls_leaderboard for the user's rank_change and returns a
     * factor that can be used to adjust motivation calculations:
     * - rank_change > 0 (rank improved) → return 1.0  (positive factor)
     * - rank_change < 0 (rank declined) → return -0.5 (negative factor)
     * - no data or rank_change = 0      → return 0.0
     *
     * @param  int   $userid   Moodle user ID.
     * @param  int   $courseid Moodle course ID.
     * @return float           Factor in range [-0.5, 1.0].
     */
    public function integrate_leaderboard_factor(int $userid, int $courseid): float {
        global $DB;

        $record = $DB->get_record(
            self::TABLE_LEADERBOARD,
            ['userid' => $userid, 'courseid' => $courseid],
            'rank_change',
            IGNORE_MISSING
        );

        if (!$record) {
            return 0.0;
        }

        $rank_change = (int) $record->rank_change;

        if ($rank_change > 0) {
            return 1.0;
        }

        if ($rank_change < 0) {
            return -0.5;
        }

        return 0.0;
    }

    // =========================================================================
    // Public API — Intervention mapping (Task 8.7)
    // =========================================================================

    /**
     * Determine the intervention category based on Performance_Category × Motivation_Level.
     *
     * Mapping table (from design.md):
     * | Performance_Category | Motivation_Level  | Category      |
     * |----------------------|-------------------|---------------|
     * | Low                  | Low (<40)         | recovery      |
     * | Low                  | Middle (40-69)    | persistence   |
     * | Middle               | Low (<40)         | recovery      |
     * | Middle               | Middle (40-69)    | reinforcement |
     * | High                 | Any               | achievement   |
     * | Any                  | High (>=70)       | achievement   |
     *
     * @param  int   $performance_category 1=Low, 2=Middle, 3=High.
     * @param  float $motivation_level     0.0–100.0.
     * @return string                      One of: recovery|persistence|reinforcement|achievement.
     */
    public static function get_intervention_category(int $performance_category, float $motivation_level): string {
        // High motivation (>=70) → achievement regardless of performance.
        if ($motivation_level >= self::MOTIVATION_HIGH_BOUNDARY) {
            return self::CATEGORY_ACHIEVEMENT;
        }

        // High performance → achievement regardless of motivation.
        if ($performance_category === learner_profile::PERFORMANCE_HIGH) {
            return self::CATEGORY_ACHIEVEMENT;
        }

        $is_low_motivation = ($motivation_level < self::MOTIVATION_LOW_BOUNDARY);

        if ($performance_category === learner_profile::PERFORMANCE_LOW) {
            return $is_low_motivation
                ? self::CATEGORY_RECOVERY
                : self::CATEGORY_PERSISTENCE;
        }

        if ($performance_category === learner_profile::PERFORMANCE_MIDDLE) {
            return $is_low_motivation
                ? self::CATEGORY_RECOVERY
                : self::CATEGORY_REINFORCEMENT;
        }

        // Default fallback.
        return self::CATEGORY_REINFORCEMENT;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Classify motivation level into a target integer (1=Low, 2=Middle, 3=High).
     *
     * @param  float $motivation_level 0.0–100.0.
     * @return int                     1=Low (<40), 2=Middle (40-69), 3=High (>=70).
     */
    private function classify_motivation_target(float $motivation_level): int {
        if ($motivation_level >= self::MOTIVATION_HIGH_BOUNDARY) {
            return 3; // High
        }

        if ($motivation_level >= self::MOTIVATION_LOW_BOUNDARY) {
            return 2; // Middle
        }

        return 1; // Low
    }
}
