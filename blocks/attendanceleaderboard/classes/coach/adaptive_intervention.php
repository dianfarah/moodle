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
 * AdaptiveIntervention data class for ACMLS Coach.
 *
 * Represents the output of a Coach evaluation — the adaptive intervention
 * determined for a specific Learner, including recommended resources,
 * motivation category, reasoning, and triggered rules.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\coach;

defined('MOODLE_INTERNAL') || die();

/**
 * Data class representing an adaptive intervention produced by the Coach.
 *
 * This is the return type of Coach::evaluate_profile(). It encapsulates all
 * information about the intervention decision: which resources to recommend,
 * what motivation category applies, the reasoning behind the decision, and
 * which rules were triggered.
 *
 * Properties:
 * - userid              : Moodle user ID
 * - courseid            : Moodle course ID
 * - intervention_type   : 'resource_recommendation' or 'motivation_intervention'
 * - resources           : array of resource records (stdClass) recommended
 * - motivation_category : one of 'recovery', 'persistence', 'reinforcement', 'achievement'
 * - reasoning           : human-readable explanation of the decision
 * - rules_triggered     : array of rule IDs that matched
 * - timecreated         : Unix timestamp when the intervention was created
 * - decision_id         : ID of the persisted CoachDecision record (null if not saved)
 * - is_fallback         : true if this is a fallback response (timeout or error)
 *
 * Requirements addressed:
 * - Req 4.1: Coach SHALL evaluate profile and return Adaptive_Intervention within 10 seconds.
 * - Req 4.6: Coach SHALL save decision with reasoning to Learner_Record.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adaptive_intervention {

    // -------------------------------------------------------------------------
    // Intervention type constants.
    // -------------------------------------------------------------------------

    /** @var string Intervention type: resource recommendation. */
    const TYPE_RESOURCE = 'resource_recommendation';

    /** @var string Intervention type: motivation intervention. */
    const TYPE_MOTIVATION = 'motivation_intervention';

    // -------------------------------------------------------------------------
    // Properties.
    // -------------------------------------------------------------------------

    /** @var int Moodle user ID. */
    public int $userid;

    /** @var int Moodle course ID. */
    public int $courseid;

    /**
     * @var string Intervention type.
     * One of: 'resource_recommendation', 'motivation_intervention'.
     */
    public string $intervention_type;

    /**
     * @var array Array of recommended resource records (stdClass objects).
     * Each record corresponds to a row from acmls_learning_resource.
     */
    public array $resources = [];

    /**
     * @var string Motivation intervention category.
     * One of: 'recovery', 'persistence', 'reinforcement', 'achievement'.
     */
    public string $motivation_category = '';

    /**
     * @var string Human-readable explanation of the rules that were triggered
     * and why this intervention was determined.
     */
    public string $reasoning = '';

    /**
     * @var array Array of rule IDs (strings) that matched during evaluation.
     */
    public array $rules_triggered = [];

    /** @var int Unix timestamp when this intervention was created. */
    public int $timecreated;

    /**
     * @var int|null ID of the persisted CoachDecision record.
     * null if the decision was not saved to the database.
     */
    public ?int $decision_id = null;

    /**
     * @var bool Whether this is a fallback response.
     * true if the full evaluation timed out or encountered an error.
     */
    public bool $is_fallback = false;

    // -------------------------------------------------------------------------
    // Constructor.
    // -------------------------------------------------------------------------

    /**
     * Constructor.
     *
     * @param int    $userid            Moodle user ID.
     * @param int    $courseid          Moodle course ID.
     * @param string $intervention_type Intervention type constant.
     */
    public function __construct(
        int $userid,
        int $courseid,
        string $intervention_type = self::TYPE_RESOURCE
    ) {
        $this->userid            = $userid;
        $this->courseid          = $courseid;
        $this->intervention_type = $intervention_type;
        $this->timecreated       = time();
    }

    // -------------------------------------------------------------------------
    // Serialisation.
    // -------------------------------------------------------------------------

    /**
     * Convert this AdaptiveIntervention to an associative array.
     *
     * Useful for JSON serialisation, API responses, or passing to templates.
     *
     * @return array<string, mixed> Associative array of all properties.
     */
    public function to_array(): array {
        return [
            'userid'              => $this->userid,
            'courseid'            => $this->courseid,
            'intervention_type'   => $this->intervention_type,
            'resources'           => array_map(function ($r) {
                return is_object($r) ? (array) $r : $r;
            }, $this->resources),
            'motivation_category' => $this->motivation_category,
            'reasoning'           => $this->reasoning,
            'rules_triggered'     => $this->rules_triggered,
            'timecreated'         => $this->timecreated,
            'decision_id'         => $this->decision_id,
            'is_fallback'         => $this->is_fallback,
        ];
    }

    /**
     * Reconstruct an AdaptiveIntervention from an associative array.
     *
     * @param  array $data Associative array (e.g., from to_array()).
     * @return self        Populated AdaptiveIntervention instance.
     */
    public static function from_array(array $data): self {
        $intervention = new self(
            (int) ($data['userid'] ?? 0),
            (int) ($data['courseid'] ?? 0),
            (string) ($data['intervention_type'] ?? self::TYPE_RESOURCE)
        );

        $intervention->resources           = $data['resources'] ?? [];
        $intervention->motivation_category = (string) ($data['motivation_category'] ?? '');
        $intervention->reasoning           = (string) ($data['reasoning'] ?? '');
        $intervention->rules_triggered     = $data['rules_triggered'] ?? [];
        $intervention->timecreated         = (int) ($data['timecreated'] ?? time());
        $intervention->decision_id         = isset($data['decision_id']) ? (int) $data['decision_id'] : null;
        $intervention->is_fallback         = (bool) ($data['is_fallback'] ?? false);

        return $intervention;
    }
}
