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
 * RuleEngine — IF-THEN rule evaluator for the ACMLS Coach.
 *
 * Loads configurable IF-THEN rules and evaluates them against a LearnerProfile
 * to produce resource query parameters and motivation intervention categories.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\coach;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\profiling\learner_profile;

/**
 * Rule-based engine for Coach recommendations.
 *
 * Each rule is an associative array with:
 *   - id          : string  — unique rule identifier
 *   - conditions  : array   — list of condition arrays (field, op, value)
 *   - actions     : array   — key-value pairs describing what to do when matched
 *   - priority    : int     — higher = evaluated first (default 0)
 *
 * Supported condition operators: '=', '>=', '<=', '>', '<', 'contains'
 *
 * Requirements addressed:
 * - Req 4.1: Coach SHALL use rule-based reasoning to evaluate Learner_Profile.
 * - Req 4.3: Low performance → difficulty_level=1, increase motivation frequency.
 * - Req 4.4: High performance → difficulty_level=3, enrichment resources.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_engine {

    // -------------------------------------------------------------------------
    // Motivation intervention categories.
    // -------------------------------------------------------------------------

    /** @var string Intervention: recovery encouragement (Low perf + Low motivation). */
    const MOTIVATION_RECOVERY = 'recovery';

    /** @var string Intervention: persistence motivation (Low perf + Middle motivation). */
    const MOTIVATION_PERSISTENCE = 'persistence';

    /** @var string Intervention: reinforcement (Middle perf + Middle motivation). */
    const MOTIVATION_REINFORCEMENT = 'reinforcement';

    /** @var string Intervention: achievement prompts (High perf or High motivation). */
    const MOTIVATION_ACHIEVEMENT = 'achievement';

    // -------------------------------------------------------------------------
    // Properties.
    // -------------------------------------------------------------------------

    /**
     * Loaded rules, keyed by rule ID.
     *
     * @var array<string, array>
     */
    private array $rules = [];

    // -------------------------------------------------------------------------
    // Public API.
    // -------------------------------------------------------------------------

    /**
     * Load the default IF-THEN recommendation rules.
     *
     * Rules are defined inline and can be overridden via plugin configuration
     * in future iterations. Each rule has:
     *   - id         : unique string identifier
     *   - conditions : array of condition arrays
     *   - actions    : array of action key-value pairs
     *   - priority   : evaluation order (higher = first)
     *
     * Default rules (from design.md):
     *   Rule 1: Low + visual  → difficulty=1, style=visual, LIMIT 3
     *   Rule 2: High + cog≥3  → difficulty=3, LIMIT 5
     *   Rule 3: motivation<50 + rank_change<0 → increase_motivation_frequency
     *   Rule 4: Low           → difficulty=1, increase_motivation_frequency
     *   Rule 5: High          → difficulty=3, enrichment
     *   Rule 6: Middle        → difficulty=1-2
     *
     * @return void
     */
    public function load_default_rules(): void {
        $this->rules = [];

        // Rule 1: Low performance + visual learning style.
        $this->add_rule([
            'id'         => 'rule_1',
            'priority'   => 10,
            'conditions' => [
                ['field' => 'performance_category', 'op' => '=',  'value' => learner_profile::PERFORMANCE_LOW],
                ['field' => 'learning_style',        'op' => '=',  'value' => learner_profile::STYLE_VISUAL],
            ],
            'actions' => [
                'difficulty_level'              => 1,
                'filter_learning_style'         => learner_profile::STYLE_VISUAL,
                'limit'                         => 3,
                'order_by'                      => 'effectiveness_score DESC',
            ],
        ]);

        // Rule 2: High performance + cognitive_level >= 3.
        $this->add_rule([
            'id'         => 'rule_2',
            'priority'   => 10,
            'conditions' => [
                ['field' => 'performance_category', 'op' => '=',  'value' => learner_profile::PERFORMANCE_HIGH],
                ['field' => 'cognitive_level',       'op' => '>=', 'value' => learner_profile::COGNITIVE_HIGH],
            ],
            'actions' => [
                'difficulty_level' => 3,
                'limit'            => 5,
                'order_by'         => 'effectiveness_score DESC',
            ],
        ]);

        // Rule 3: Low motivation + rank declining.
        $this->add_rule([
            'id'         => 'rule_3',
            'priority'   => 8,
            'conditions' => [
                ['field' => 'motivation_level', 'op' => '<', 'value' => 50],
                ['field' => 'rank_change',       'op' => '<', 'value' => 0],
            ],
            'actions' => [
                'increase_motivation_frequency' => true,
            ],
        ]);

        // Rule 4: Low performance (catch-all).
        $this->add_rule([
            'id'         => 'rule_4',
            'priority'   => 5,
            'conditions' => [
                ['field' => 'performance_category', 'op' => '=', 'value' => learner_profile::PERFORMANCE_LOW],
            ],
            'actions' => [
                'difficulty_level'              => 1,
                'increase_motivation_frequency' => true,
            ],
        ]);

        // Rule 5: High performance (catch-all).
        $this->add_rule([
            'id'         => 'rule_5',
            'priority'   => 5,
            'conditions' => [
                ['field' => 'performance_category', 'op' => '=', 'value' => learner_profile::PERFORMANCE_HIGH],
            ],
            'actions' => [
                'difficulty_level' => 3,
                'enrichment'       => true,
            ],
        ]);

        // Rule 6: Middle performance (catch-all).
        $this->add_rule([
            'id'         => 'rule_6',
            'priority'   => 5,
            'conditions' => [
                ['field' => 'performance_category', 'op' => '=', 'value' => learner_profile::PERFORMANCE_MIDDLE],
            ],
            'actions' => [
                'difficulty_level_min' => 1,
                'difficulty_level_max' => 2,
            ],
        ]);
    }

    /**
     * Add a single rule to the engine.
     *
     * @param  array $rule Rule definition array.
     * @return void
     */
    public function add_rule(array $rule): void {
        $id = $rule['id'] ?? ('rule_' . count($this->rules));
        $this->rules[$id] = $rule;
    }

    /**
     * Evaluate all loaded rules against a LearnerProfile.
     *
     * Returns all rules whose conditions are satisfied, sorted by priority
     * (highest first).
     *
     * The profile is augmented with an optional 'rank_change' field that can
     * be injected via $extra_context for Rule 3 evaluation.
     *
     * @param  learner_profile $profile       Learner's current profile.
     * @param  array           $extra_context Optional extra fields (e.g., rank_change).
     * @return array                          Array of matched rule arrays.
     */
    public function evaluate(learner_profile $profile, array $extra_context = []): array {
        if (empty($this->rules)) {
            $this->load_default_rules();
        }

        // Build a flat context map from the profile + extra context.
        $context = array_merge([
            'performance_category' => $profile->performance_category,
            'learning_style'       => $profile->learning_style,
            'motivation_level'     => $profile->motivation_level,
            'cognitive_level'      => $profile->cognitive_level,
            'behavioral_score'     => $profile->behavioral_score,
            'engagement_score'     => $profile->engagement_score,
        ], $extra_context);

        $matched = [];

        foreach ($this->rules as $rule) {
            if ($this->evaluate_conditions($rule['conditions'] ?? [], $context)) {
                $matched[] = $rule;
            }
        }

        // Sort by priority descending.
        usort($matched, function (array $a, array $b): int {
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });

        return $matched;
    }

    /**
     * Derive resource query parameters from matched rules.
     *
     * Merges actions from all matched rules (higher-priority rules win on
     * conflicting keys). Returns an array suitable for passing to
     * LearningResourceRepository::find_by_profile() or a custom query.
     *
     * @param  learner_profile $profile       Learner's current profile.
     * @param  array           $extra_context Optional extra fields (e.g., rank_change).
     * @return array {
     *     difficulty_level?: int,
     *     difficulty_level_min?: int,
     *     difficulty_level_max?: int,
     *     filter_learning_style?: string,
     *     limit?: int,
     *     order_by?: string,
     *     enrichment?: bool,
     *     increase_motivation_frequency?: bool,
     * }
     */
    public function get_resource_query_params(learner_profile $profile, array $extra_context = []): array {
        $matched = $this->evaluate($profile, $extra_context);

        $params = [];

        // Merge actions from matched rules (first match wins for each key,
        // since rules are already sorted by priority descending).
        foreach ($matched as $rule) {
            foreach ($rule['actions'] ?? [] as $key => $value) {
                if (!array_key_exists($key, $params)) {
                    $params[$key] = $value;
                }
            }
        }

        // Fallback defaults if no rules matched.
        if (empty($params)) {
            $params = $this->get_fallback_params($profile->performance_category);
        }

        return $params;
    }

    /**
     * Determine the motivation intervention category for a LearnerProfile.
     *
     * Uses the mapping table from design.md:
     *
     * | Performance_Category | Motivation_Level     | Category              |
     * |----------------------|----------------------|-----------------------|
     * | Low                  | Low (<threshold)     | recovery              |
     * | Low                  | Middle (thr–70)      | persistence           |
     * | Middle               | Low (<threshold)     | recovery              |
     * | Middle               | Middle (thr–70)      | reinforcement         |
     * | High                 | Any                  | achievement           |
     * | Any                  | High (>70)           | achievement           |
     *
     * The Low motivation threshold is configurable via plugin settings
     * (default: 40).
     *
     * @param  learner_profile $profile Learner's current profile.
     * @return string                   One of: 'recovery', 'persistence',
     *                                  'reinforcement', 'achievement'.
     */
    public function get_motivation_category(learner_profile $profile): string {
        $threshold = (int) get_config('block_attendanceleaderboard', 'motivation_threshold');
        if ($threshold <= 0) {
            $threshold = 40;
        }

        $perf  = $profile->performance_category;
        $motiv = $profile->motivation_level;

        // High motivation (>70) → achievement regardless of performance.
        if ($motiv > 70) {
            return self::MOTIVATION_ACHIEVEMENT;
        }

        // High performance → achievement regardless of motivation.
        if ($perf === learner_profile::PERFORMANCE_HIGH) {
            return self::MOTIVATION_ACHIEVEMENT;
        }

        // Low motivation (< threshold).
        $is_low_motivation = ($motiv < $threshold);

        if ($perf === learner_profile::PERFORMANCE_LOW) {
            return $is_low_motivation
                ? self::MOTIVATION_RECOVERY
                : self::MOTIVATION_PERSISTENCE;
        }

        // Middle performance.
        if ($perf === learner_profile::PERFORMANCE_MIDDLE) {
            return $is_low_motivation
                ? self::MOTIVATION_RECOVERY
                : self::MOTIVATION_REINFORCEMENT;
        }

        // Default fallback.
        return self::MOTIVATION_REINFORCEMENT;
    }

    /**
     * Return all currently loaded rules.
     *
     * @return array<string, array>
     */
    public function get_rules(): array {
        return $this->rules;
    }

    // -------------------------------------------------------------------------
    // Private helpers.
    // -------------------------------------------------------------------------

    /**
     * Evaluate a list of conditions against a context map.
     *
     * All conditions must be satisfied (AND logic).
     *
     * @param  array $conditions List of condition arrays.
     * @param  array $context    Flat key-value map of profile fields.
     * @return bool              True if all conditions are satisfied.
     */
    private function evaluate_conditions(array $conditions, array $context): bool {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $op    = $condition['op']    ?? '=';
            $value = $condition['value'] ?? null;

            // If the field is not in context, condition fails.
            if (!array_key_exists($field, $context)) {
                return false;
            }

            $actual = $context[$field];

            if (!$this->compare($actual, $op, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare two values using the given operator.
     *
     * @param  mixed  $actual Actual value from context.
     * @param  string $op     Operator: '=', '!=', '>=', '<=', '>', '<', 'contains'.
     * @param  mixed  $value  Expected value.
     * @return bool
     */
    private function compare($actual, string $op, $value): bool {
        switch ($op) {
            case '=':
                return $actual == $value;
            case '!=':
                return $actual != $value;
            case '>=':
                return $actual >= $value;
            case '<=':
                return $actual <= $value;
            case '>':
                return $actual > $value;
            case '<':
                return $actual < $value;
            case 'contains':
                if (is_array($actual)) {
                    return in_array($value, $actual, true);
                }
                return strpos((string) $actual, (string) $value) !== false;
            default:
                return false;
        }
    }

    /**
     * Return fallback resource query parameters based on performance_category alone.
     *
     * Used when no rules match or as a timeout fallback.
     *
     * @param  int   $performance_category 1=Low, 2=Middle, 3=High.
     * @return array Resource query parameters.
     */
    private function get_fallback_params(int $performance_category): array {
        switch ($performance_category) {
            case learner_profile::PERFORMANCE_HIGH:
                return ['difficulty_level' => 3, 'limit' => 5];
            case learner_profile::PERFORMANCE_MIDDLE:
                return ['difficulty_level_min' => 1, 'difficulty_level_max' => 2, 'limit' => 5];
            case learner_profile::PERFORMANCE_LOW:
            default:
                return ['difficulty_level' => 1, 'limit' => 5];
        }
    }
}
