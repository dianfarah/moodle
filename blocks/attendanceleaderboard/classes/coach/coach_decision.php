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
 * CoachDecision data class for ACMLS Coach.
 *
 * Represents a single decision made by the Coach, including the input profile
 * snapshot, recommended resources, motivation category, reasoning, and the
 * rules that were triggered.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\coach;

defined('MOODLE_INTERNAL') || die();

/**
 * Data class representing a Coach decision record.
 *
 * Maps to the acmls_coach_decision database table.
 *
 * Properties:
 * - id                   : DB record ID (null if not yet persisted)
 * - userid               : Moodle user ID
 * - courseid             : Moodle course ID
 * - decision_type        : 'resource_recommendation' or 'motivation_intervention'
 * - input_profile        : JSON snapshot of LearnerProfile at decision time
 * - recommended_resources: array of resource IDs
 * - motivation_category  : one of 'recovery', 'persistence', 'reinforcement', 'achievement'
 * - reasoning            : human-readable explanation of rules triggered
 * - rules_triggered      : array of rule IDs that matched
 * - learner_response     : 0=ignored, 1=accessed, null=pending
 * - response_time        : Unix timestamp of learner response (null if pending)
 * - timecreated          : Unix timestamp when decision was created
 *
 * Requirements addressed:
 * - Req 4.6: Coach SHALL save decision with reasoning to Learner_Record.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coach_decision {

    // -------------------------------------------------------------------------
    // Decision type constants.
    // -------------------------------------------------------------------------

    /** @var string Decision type: resource recommendation. */
    const TYPE_RESOURCE = 'resource_recommendation';

    /** @var string Decision type: motivation intervention. */
    const TYPE_MOTIVATION = 'motivation_intervention';

    // -------------------------------------------------------------------------
    // Learner response constants.
    // -------------------------------------------------------------------------

    /** @var int Learner response: ignored the recommendation. */
    const RESPONSE_IGNORED = 0;

    /** @var int Learner response: accessed the recommended resource. */
    const RESPONSE_ACCESSED = 1;

    // -------------------------------------------------------------------------
    // Properties.
    // -------------------------------------------------------------------------

    /** @var int|null Database record ID (null if not yet persisted). */
    public ?int $id = null;

    /** @var int Moodle user ID. */
    public int $userid;

    /** @var int Moodle course ID. */
    public int $courseid;

    /**
     * @var string Decision type.
     * One of: 'resource_recommendation', 'motivation_intervention'.
     */
    public string $decision_type;

    /**
     * @var array JSON snapshot of LearnerProfile at the time of the decision.
     * Stored as JSON in the database.
     */
    public array $input_profile = [];

    /**
     * @var array Array of recommended resource IDs (integers).
     * Stored as JSON in the database.
     */
    public array $recommended_resources = [];

    /**
     * @var string|null Motivation intervention category.
     * One of: 'recovery', 'persistence', 'reinforcement', 'achievement', or null.
     */
    public ?string $motivation_category = null;

    /**
     * @var string Human-readable explanation of the rules that were triggered
     * and why this decision was made.
     */
    public string $reasoning = '';

    /**
     * @var array Array of rule IDs (strings) that matched during evaluation.
     * Stored as JSON in the database.
     */
    public array $rules_triggered = [];

    /**
     * @var int|null Learner response to the recommendation.
     * 0 = ignored, 1 = accessed, null = pending (no response yet).
     */
    public ?int $learner_response = null;

    /**
     * @var int|null Unix timestamp when the learner responded.
     * null if no response yet.
     */
    public ?int $response_time = null;

    /** @var int Unix timestamp when this decision was created. */
    public int $timecreated;

    // -------------------------------------------------------------------------
    // Constructor.
    // -------------------------------------------------------------------------

    /**
     * Constructor.
     *
     * @param int    $userid        Moodle user ID.
     * @param int    $courseid      Moodle course ID.
     * @param string $decision_type Decision type constant.
     */
    public function __construct(int $userid, int $courseid, string $decision_type = self::TYPE_RESOURCE) {
        $this->userid        = $userid;
        $this->courseid      = $courseid;
        $this->decision_type = $decision_type;
        $this->timecreated   = time();
    }

    // -------------------------------------------------------------------------
    // Serialisation.
    // -------------------------------------------------------------------------

    /**
     * Convert this CoachDecision to a database record object.
     *
     * Suitable for use with $DB->insert_record() or $DB->update_record()
     * against the acmls_coach_decision table.
     *
     * @return \stdClass Database record object.
     */
    public function to_db_record(): \stdClass {
        $record = new \stdClass();

        if ($this->id !== null) {
            $record->id = $this->id;
        }

        $record->userid                = $this->userid;
        $record->courseid              = $this->courseid;
        $record->decision_type         = $this->decision_type;
        $record->input_profile         = json_encode($this->input_profile);
        $record->recommended_resources = json_encode($this->recommended_resources);
        $record->motivation_category   = $this->motivation_category;
        $record->reasoning             = $this->reasoning;
        $record->rules_triggered       = json_encode($this->rules_triggered);
        $record->learner_response      = $this->learner_response;
        $record->response_time         = $this->response_time;
        $record->timecreated           = $this->timecreated;

        return $record;
    }

    /**
     * Reconstruct a CoachDecision from a database record object.
     *
     * @param  \stdClass $record Database record from acmls_coach_decision table.
     * @return self              Populated CoachDecision instance.
     */
    public static function from_db_record(\stdClass $record): self {
        $decision = new self(
            (int) $record->userid,
            (int) $record->courseid,
            (string) ($record->decision_type ?? self::TYPE_RESOURCE)
        );

        $decision->id                    = (int) $record->id;
        $decision->input_profile         = json_decode($record->input_profile ?? '[]', true) ?? [];
        $decision->recommended_resources = json_decode($record->recommended_resources ?? '[]', true) ?? [];
        $decision->motivation_category   = isset($record->motivation_category)
            ? (string) $record->motivation_category
            : null;
        $decision->reasoning             = (string) ($record->reasoning ?? '');
        $decision->rules_triggered       = json_decode($record->rules_triggered ?? '[]', true) ?? [];
        $decision->learner_response      = isset($record->learner_response)
            ? (int) $record->learner_response
            : null;
        $decision->response_time         = isset($record->response_time)
            ? (int) $record->response_time
            : null;
        $decision->timecreated           = (int) ($record->timecreated ?? time());

        return $decision;
    }

    /**
     * Return all decision properties as an associative array.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'id'                    => $this->id,
            'userid'                => $this->userid,
            'courseid'              => $this->courseid,
            'decision_type'         => $this->decision_type,
            'input_profile'         => $this->input_profile,
            'recommended_resources' => $this->recommended_resources,
            'motivation_category'   => $this->motivation_category,
            'reasoning'             => $this->reasoning,
            'rules_triggered'       => $this->rules_triggered,
            'learner_response'      => $this->learner_response,
            'response_time'         => $this->response_time,
            'timecreated'           => $this->timecreated,
        ];
    }
}
