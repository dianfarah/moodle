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
 * Coach — Rule-based adaptive recommendation engine for ACMLS.
 *
 * The Coach is the central intelligence component of ACMLS. It evaluates a
 * Learner's profile, recommends appropriate learning resources, determines
 * the motivation intervention category, and persists its decisions for audit
 * and research purposes.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\coach;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\profiling\learner_profile;
use block_attendanceleaderboard\repository\learning_resource_repository;
use block_attendanceleaderboard\coach\adaptive_intervention;

/**
 * Coach — adaptive recommendation engine.
 *
 * Entry points:
 * - receive_profile_update()    : called by ProfilingSystem after profile update
 * - receive_performance_alert() : called by EvaluationSystem on performance decline
 *
 * Core methods:
 * - evaluate_profile()                  : full evaluation pipeline with 10s timeout
 * - recommend_resources()               : query resources matching profile
 * - determine_motivation_intervention() : map profile to motivation category
 * - save_decision()                     : persist CoachDecision to DB
 * - get_leaderboard_factor()            : compute leaderboard influence factor
 * - update_rules_from_history()         : analyse historical decision effectiveness
 *
 * Requirements addressed:
 * - Req 4.1–4.8: Coach rule-based engine, resource recommendations, motivation
 *                intervention, decision logging, leaderboard factor, rule updates.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coach {

    /** @var string Database table for Coach decisions. */
    const TABLE = 'acmls_coach_decision';

    /** @var string Database table for Leaderboard data. */
    const TABLE_LEADERBOARD = 'acmls_leaderboard';

    /** @var int Maximum seconds allowed for a full evaluation before fallback. */
    private int $max_response_seconds = 10;

    /** @var rule_engine Rule engine instance. */
    private rule_engine $rule_engine;

    /** @var learning_resource_repository Resource repository instance. */
    private learning_resource_repository $resource_repo;

    // -------------------------------------------------------------------------
    // Constructor.
    // -------------------------------------------------------------------------

    /**
     * Constructor.
     *
     * @param rule_engine|null                  $rule_engine   Optional rule engine (for testing).
     * @param learning_resource_repository|null $resource_repo Optional resource repo (for testing).
     */
    public function __construct(
        ?rule_engine $rule_engine = null,
        ?learning_resource_repository $resource_repo = null
    ) {
        $this->rule_engine   = $rule_engine   ?? new rule_engine();
        $this->resource_repo = $resource_repo ?? new learning_resource_repository();

        $this->rule_engine->load_default_rules();
    }

    // -------------------------------------------------------------------------
    // Static entry points.
    // -------------------------------------------------------------------------

    /**
     * Static entry point called by ProfilingSystem after a profile update.
     *
     * Instantiates a Coach and runs a full evaluation for the given profile.
     *
     * @param  learner_profile $profile Updated LearnerProfile.
     * @return void
     */
    public static function receive_profile_update(learner_profile $profile): void {
        try {
            $coach = new self();
            $coach->evaluate_profile($profile);
        } catch (\Throwable $e) {
            debugging(
                'Coach::receive_profile_update failed for user ' . $profile->userid .
                ' course ' . $profile->courseid . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Static entry point called by EvaluationSystem on performance decline.
     *
     * Loads the current profile from DB and runs a full evaluation.
     *
     * @param  int   $userid   Moodle user ID.
     * @param  int   $courseid Moodle course ID.
     * @param  array $metrics  Performance metrics from EvaluationSystem.
     * @return void
     */
    public static function receive_performance_alert(int $userid, int $courseid, array $metrics): void {
        global $DB;

        try {
            $record = $DB->get_record('acmls_learner_profile', ['userid' => $userid, 'courseid' => $courseid]);
            if (!$record) {
                debugging(
                    'Coach::receive_performance_alert: no profile found for user ' . $userid,
                    DEBUG_DEVELOPER
                );
                return;
            }

            $profile = learner_profile::from_db_record($record);
            $coach   = new self();
            $coach->evaluate_profile($profile);
        } catch (\Throwable $e) {
            debugging(
                'Coach::receive_performance_alert failed for user ' . $userid . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    // -------------------------------------------------------------------------
    // Core evaluation pipeline.
    // -------------------------------------------------------------------------

    /**
     * Full evaluation pipeline for a LearnerProfile.
     *
     * Steps:
     * 1. Start timer.
     * 2. Use RuleEngine to evaluate profile.
     * 3. Get leaderboard factor.
     * 4. Call recommend_resources() to get resource recommendations.
     * 5. Call determine_motivation_intervention() to get motivation category.
     * 6. Build CoachDecision with reasoning.
     * 7. Save decision to DB.
     * 8. If time > max_response_seconds, use fallback (default resources by
     *    performance_category only).
     * 9. Return AdaptiveIntervention.
     *
     * @param  learner_profile $profile Learner's current profile.
     * @return adaptive_intervention    The adaptive intervention for this Learner.
     */
    public function evaluate_profile(learner_profile $profile): adaptive_intervention {
        $start_time = microtime(true);

        try {
            // Get leaderboard factor for extra context.
            $leaderboard_factor = $this->get_leaderboard_factor($profile->userid, $profile->courseid);
            $rank_change        = $this->get_rank_change($profile->userid, $profile->courseid);

            $extra_context = ['rank_change' => $rank_change];

            // Evaluate rules.
            $matched_rules = $this->rule_engine->evaluate($profile, $extra_context);
            $rule_ids      = array_column($matched_rules, 'id');

            // Check timeout before expensive DB operations.
            $elapsed = microtime(true) - $start_time;
            if ($elapsed > $this->max_response_seconds) {
                return $this->build_fallback_intervention($profile, $start_time);
            }

            // Get resource recommendations.
            $resources = $this->recommend_resources($profile, $extra_context);

            // Check timeout again.
            $elapsed = microtime(true) - $start_time;
            if ($elapsed > $this->max_response_seconds) {
                return $this->build_fallback_intervention($profile, $start_time);
            }

            // Determine motivation category.
            $motivation_category = $this->determine_motivation_intervention($profile);

            // Build reasoning string.
            $reasoning = $this->build_reasoning($matched_rules, $leaderboard_factor, $motivation_category);

            // Build and save decision.
            $decision                        = new coach_decision($profile->userid, $profile->courseid);
            $decision->decision_type         = coach_decision::TYPE_RESOURCE;
            $decision->input_profile         = $profile->get_snapshot();
            $decision->recommended_resources = array_map(fn($r) => (int) $r->id, $resources);
            $decision->motivation_category   = $motivation_category;
            $decision->reasoning             = $reasoning;
            $decision->rules_triggered       = $rule_ids;

            $decision_id = $this->save_decision($decision);

            // Build and return AdaptiveIntervention.
            $intervention                      = new adaptive_intervention(
                $profile->userid,
                $profile->courseid,
                adaptive_intervention::TYPE_RESOURCE
            );
            $intervention->resources           = $resources;
            $intervention->motivation_category = $motivation_category;
            $intervention->reasoning           = $reasoning;
            $intervention->rules_triggered     = $rule_ids;
            $intervention->decision_id         = $decision_id;

            return $intervention;

        } catch (\Throwable $e) {
            debugging('Coach::evaluate_profile error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $this->build_fallback_intervention($profile, $start_time);
        }
    }

    // -------------------------------------------------------------------------
    // Resource recommendations.
    // -------------------------------------------------------------------------

    /**
     * Recommend learning resources for a LearnerProfile.
     *
     * Uses the RuleEngine to determine query parameters, then queries the
     * LearningResourceRepository. Falls back to difficulty-only query if
     * no style-specific resources are found.
     *
     * @param  learner_profile $profile       Learner's current profile.
     * @param  array           $extra_context Optional extra context (e.g., rank_change).
     * @return array                          Array of resource records (stdClass).
     */
    public function recommend_resources(learner_profile $profile, array $extra_context = []): array {
        $params = $this->rule_engine->get_resource_query_params($profile, $extra_context);

        // Determine difficulty levels from params.
        if (isset($params['difficulty_level'])) {
            $difficulty_levels = [$params['difficulty_level']];
        } elseif (isset($params['difficulty_level_min'], $params['difficulty_level_max'])) {
            $difficulty_levels = range(
                (int) $params['difficulty_level_min'],
                (int) $params['difficulty_level_max']
            );
        } else {
            // Fallback: use performance_category mapping.
            $difficulty_levels = $this->map_performance_to_difficulty($profile->performance_category);
        }

        $limit = (int) ($params['limit'] ?? 5);

        return $this->query_resources_by_difficulty(
            $profile,
            $difficulty_levels,
            $params['filter_learning_style'] ?? null,
            $limit
        );
    }

    // -------------------------------------------------------------------------
    // Motivation intervention.
    // -------------------------------------------------------------------------

    /**
     * Determine the motivation intervention category for a LearnerProfile.
     *
     * Delegates to RuleEngine::get_motivation_category().
     *
     * @param  learner_profile $profile Learner's current profile.
     * @return string                   One of: 'recovery', 'persistence',
     *                                  'reinforcement', 'achievement'.
     */
    public function determine_motivation_intervention(learner_profile $profile): string {
        return $this->rule_engine->get_motivation_category($profile);
    }

    // -------------------------------------------------------------------------
    // Decision persistence.
    // -------------------------------------------------------------------------

    /**
     * Save a CoachDecision to the acmls_coach_decision table.
     *
     * @param  coach_decision $decision Decision to persist.
     * @return int                      Inserted record ID.
     */
    public function save_decision(coach_decision $decision): int {
        global $DB;

        $record = $decision->to_db_record();
        $id     = (int) $DB->insert_record(self::TABLE, $record);
        $decision->id = $id;

        return $id;
    }

    // -------------------------------------------------------------------------
    // Leaderboard factor (Task 7.5).
    // -------------------------------------------------------------------------

    /**
     * Calculate the leaderboard influence factor for a Learner.
     *
     * Queries acmls_leaderboard for the user's rank_change:
     * - rank_change > 0 (rank improved) → return 1.0  (positive factor)
     * - rank_change < 0 (rank declined) → return -0.5 (negative factor)
     * - no data or rank_change = 0      → return 0.0
     *
     * @param  int   $userid   Moodle user ID.
     * @param  int   $courseid Moodle course ID.
     * @return float           Factor in range [-0.5, 1.0].
     */
    public function get_leaderboard_factor(int $userid, int $courseid): float {
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

    // -------------------------------------------------------------------------
    // Rule update from history (Task 7.6).
    // -------------------------------------------------------------------------

    /**
     * Analyse historical decision effectiveness and log acceptance rates.
     *
     * Queries acmls_coach_decision for decisions with learner_response = 1
     * (accessed), calculates acceptance rate per decision_type, and logs the
     * analysis. Actual rule weight adjustment is a future enhancement.
     *
     * @return void
     */
    public function update_rules_from_history(): void {
        global $DB;

        // Get total decisions per type.
        $sql_total = "SELECT decision_type, COUNT(*) AS total
                        FROM {" . self::TABLE . "}
                    GROUP BY decision_type";

        $totals = $DB->get_records_sql($sql_total);

        // Get accepted decisions per type.
        $sql_accepted = "SELECT decision_type, COUNT(*) AS accepted
                           FROM {" . self::TABLE . "}
                          WHERE learner_response = :response
                       GROUP BY decision_type";

        $accepted_records = $DB->get_records_sql($sql_accepted, [
            'response' => coach_decision::RESPONSE_ACCESSED,
        ]);

        // Build acceptance rate map.
        $acceptance_rates = [];

        foreach ($totals as $row) {
            $type  = $row->decision_type;
            $total = (int) $row->total;

            $accepted = isset($accepted_records[$type])
                ? (int) $accepted_records[$type]->accepted
                : 0;

            $rate = $total > 0 ? round(($accepted / $total) * 100, 2) : 0.0;
            $acceptance_rates[$type] = [
                'total'           => $total,
                'accepted'        => $accepted,
                'acceptance_rate' => $rate,
            ];

            debugging(
                "Coach rule history: decision_type={$type}, total={$total}, " .
                "accepted={$accepted}, acceptance_rate={$rate}%",
                DEBUG_DEVELOPER
            );
        }

        // Future enhancement: adjust rule weights based on acceptance_rates.
        // For now, we log the analysis only.
    }

    // -------------------------------------------------------------------------
    // Private helpers.
    // -------------------------------------------------------------------------

    /**
     * Get the raw rank_change value for a user from the leaderboard.
     *
     * @param  int $userid   Moodle user ID.
     * @param  int $courseid Moodle course ID.
     * @return int           rank_change value, or 0 if no data.
     */
    private function get_rank_change(int $userid, int $courseid): int {
        global $DB;

        $record = $DB->get_record(
            self::TABLE_LEADERBOARD,
            ['userid' => $userid, 'courseid' => $courseid],
            'rank_change',
            IGNORE_MISSING
        );

        return $record ? (int) $record->rank_change : 0;
    }

    /**
     * Query resources from the repository filtered by difficulty levels.
     *
     * @param  learner_profile $profile         Learner profile (for style matching).
     * @param  int[]           $difficulty_levels Allowed difficulty levels.
     * @param  string|null     $filter_style     Optional learning style filter.
     * @param  int             $limit            Maximum number of results.
     * @return array                             Array of resource records (stdClass).
     */
    private function query_resources_by_difficulty(
        learner_profile $profile,
        array $difficulty_levels,
        ?string $filter_style,
        int $limit
    ): array {
        global $DB;

        if (empty($difficulty_levels)) {
            return [];
        }

        list($in_sql, $params) = $DB->get_in_or_equal($difficulty_levels, SQL_PARAMS_NAMED, 'diff');
        $params['is_active'] = 1;

        $sql = "SELECT *
                  FROM {acmls_learning_resource}
                 WHERE difficulty_level {$in_sql}
                   AND is_active = :is_active
                 ORDER BY effectiveness_score DESC, access_count DESC";

        $all_resources = array_values($DB->get_records_sql($sql, $params));

        if (empty($all_resources)) {
            return [];
        }

        // Apply learning style filter if specified.
        $style = $filter_style ?? $profile->learning_style;
        if ($style && $style !== learner_profile::STYLE_UNKNOWN) {
            $style_matched = array_filter($all_resources, function ($r) use ($style) {
                $styles = json_decode($r->learning_styles ?? '[]', true);
                return is_array($styles) && in_array($style, $styles, true);
            });

            if (!empty($style_matched)) {
                return array_slice(array_values($style_matched), 0, $limit);
            }
        }

        return array_slice($all_resources, 0, $limit);
    }

    /**
     * Map performance_category to allowed difficulty_level values.
     *
     * @param  int   $performance_category 1=Low, 2=Middle, 3=High.
     * @return int[]                       Array of allowed difficulty levels.
     */
    private function map_performance_to_difficulty(int $performance_category): array {
        switch ($performance_category) {
            case learner_profile::PERFORMANCE_HIGH:
                return [2, 3];
            case learner_profile::PERFORMANCE_MIDDLE:
                return [1, 2];
            case learner_profile::PERFORMANCE_LOW:
            default:
                return [1];
        }
    }

    /**
     * Build a human-readable reasoning string from matched rules.
     *
     * @param  array  $matched_rules      Array of matched rule arrays.
     * @param  float  $leaderboard_factor Leaderboard influence factor.
     * @param  string $motivation_category Determined motivation category.
     * @return string                     Reasoning explanation.
     */
    private function build_reasoning(
        array $matched_rules,
        float $leaderboard_factor,
        string $motivation_category
    ): string {
        if (empty($matched_rules)) {
            return 'No specific rules matched; using default recommendations based on performance category. ' .
                   "Motivation category: {$motivation_category}. " .
                   "Leaderboard factor: {$leaderboard_factor}.";
        }

        $rule_descriptions = [];
        foreach ($matched_rules as $rule) {
            $rule_id = $rule['id'] ?? 'unknown';
            $actions = $rule['actions'] ?? [];
            $action_str = implode(', ', array_map(
                fn($k, $v) => "{$k}=" . (is_bool($v) ? ($v ? 'true' : 'false') : $v),
                array_keys($actions),
                $actions
            ));
            $rule_descriptions[] = "Rule {$rule_id} triggered (actions: {$action_str})";
        }

        $rules_str = implode('; ', $rule_descriptions);

        return "Rules triggered: {$rules_str}. " .
               "Motivation category: {$motivation_category}. " .
               "Leaderboard factor: {$leaderboard_factor}.";
    }

    /**
     * Build a fallback intervention response based on performance_category only.
     *
     * Used when the full evaluation exceeds max_response_seconds or encounters
     * an unrecoverable error.
     *
     * @param  learner_profile $profile    Learner's profile.
     * @param  float           $start_time microtime(true) at evaluation start.
     * @return adaptive_intervention       Fallback AdaptiveIntervention.
     */
    private function build_fallback_intervention(learner_profile $profile, float $start_time): adaptive_intervention {
        $difficulty_levels = $this->map_performance_to_difficulty($profile->performance_category);
        $resources         = $this->query_resources_by_difficulty($profile, $difficulty_levels, null, 5);
        $motivation        = $this->determine_motivation_intervention($profile);

        $elapsed  = round(microtime(true) - $start_time, 2);
        $reasoning = "Fallback response (elapsed: {$elapsed}s > {$this->max_response_seconds}s). " .
                     "Using default resources for performance_category={$profile->performance_category}. " .
                     "Motivation category: {$motivation}.";

        // Save fallback decision.
        $decision                        = new coach_decision($profile->userid, $profile->courseid);
        $decision->decision_type         = coach_decision::TYPE_RESOURCE;
        $decision->input_profile         = $profile->get_snapshot();
        $decision->recommended_resources = array_map(fn($r) => (int) $r->id, $resources);
        $decision->motivation_category   = $motivation;
        $decision->reasoning             = $reasoning;
        $decision->rules_triggered       = [];

        $decision_id = null;
        try {
            $decision_id = $this->save_decision($decision);
        } catch (\Throwable $e) {
            debugging('Coach fallback: could not save decision: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $intervention                      = new adaptive_intervention(
            $profile->userid,
            $profile->courseid,
            adaptive_intervention::TYPE_RESOURCE
        );
        $intervention->resources           = $resources;
        $intervention->motivation_category = $motivation;
        $intervention->reasoning           = $reasoning;
        $intervention->rules_triggered     = [];
        $intervention->decision_id         = $decision_id;
        $intervention->is_fallback         = true;

        return $intervention;
    }
}
