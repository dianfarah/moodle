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
 * LearnerProfile data class for ACMLS Profiling System.
 *
 * Represents the multidimensional model of a Learner, covering cognitive,
 * motivational, performance, and behavioural dimensions.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\profiling;

defined('MOODLE_INTERNAL') || die();

/**
 * Multidimensional Learner Profile for ACMLS.
 *
 * Properties:
 * - id               : DB record ID (null if not yet persisted)
 * - userid           : Moodle user ID
 * - courseid         : Moodle course ID
 * - cognitive_level  : 1=Low, 2=Middle, 3=High
 * - motivation_level : 0.00–100.00
 * - performance_category : 1=Low, 2=Middle, 3=High
 * - learning_style   : 'visual', 'auditory', 'reading', 'kinesthetic', 'unknown'
 * - behavioral_score : 0.00–100.00
 * - engagement_score : 0.00–100.00
 * - profile_version  : starts at 1, increments on each update
 * - last_updated     : Unix timestamp
 * - created_at       : Unix timestamp
 *
 * Requirements addressed:
 * - Req 3.1: Learner_Profile dimensions: Cognitive_Level, Academic_Performance,
 *            Motivation_Level, Learning_Style, Behavioral_History.
 * - Req 3.5: Store all historical profile versions in Learner_Record.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_profile {

    // -------------------------------------------------------------------------
    // Performance category constants.
    // -------------------------------------------------------------------------

    /** @var int Performance category: Low (score < 60%). */
    const PERFORMANCE_LOW = 1;

    /** @var int Performance category: Middle (60% ≤ score < 80%). */
    const PERFORMANCE_MIDDLE = 2;

    /** @var int Performance category: High (score ≥ 80%). */
    const PERFORMANCE_HIGH = 3;

    // -------------------------------------------------------------------------
    // Cognitive level constants.
    // -------------------------------------------------------------------------

    /** @var int Cognitive level: Low. */
    const COGNITIVE_LOW = 1;

    /** @var int Cognitive level: Middle. */
    const COGNITIVE_MIDDLE = 2;

    /** @var int Cognitive level: High. */
    const COGNITIVE_HIGH = 3;

    // -------------------------------------------------------------------------
    // Learning style constants.
    // -------------------------------------------------------------------------

    /** @var string Learning style: visual. */
    const STYLE_VISUAL = 'visual';

    /** @var string Learning style: auditory. */
    const STYLE_AUDITORY = 'auditory';

    /** @var string Learning style: reading. */
    const STYLE_READING = 'reading';

    /** @var string Learning style: kinesthetic. */
    const STYLE_KINESTHETIC = 'kinesthetic';

    /** @var string Learning style: unknown (insufficient data). */
    const STYLE_UNKNOWN = 'unknown';

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
     * @var int Cognitive level.
     * 1 = Low, 2 = Middle, 3 = High.
     */
    public int $cognitive_level = self::COGNITIVE_LOW;

    /**
     * @var float Motivation level (0.00–100.00).
     * Calculated using weighted moving average.
     */
    public float $motivation_level = 50.0;

    /**
     * @var int Performance category.
     * 1 = Low (<60%), 2 = Middle (60–79%), 3 = High (≥80%).
     */
    public int $performance_category = self::PERFORMANCE_LOW;

    /**
     * @var string Learning style.
     * One of: 'visual', 'auditory', 'reading', 'kinesthetic', 'unknown'.
     */
    public string $learning_style = self::STYLE_UNKNOWN;

    /** @var float Behavioural score (0.00–100.00). */
    public float $behavioral_score = 0.0;

    /** @var float Engagement score (0.00–100.00). */
    public float $engagement_score = 0.0;

    /**
     * @var int Profile version.
     * Starts at 1 and increments on each update.
     */
    public int $profile_version = 1;

    /** @var int Unix timestamp of the last profile update. */
    public int $last_updated;

    /** @var int Unix timestamp when the profile was first created. */
    public int $created_at;

    // -------------------------------------------------------------------------
    // Constructor.
    // -------------------------------------------------------------------------

    /**
     * Constructor.
     *
     * @param int $userid   Moodle user ID.
     * @param int $courseid Moodle course ID.
     */
    public function __construct(int $userid, int $courseid) {
        $this->userid      = $userid;
        $this->courseid    = $courseid;
        $this->last_updated = time();
        $this->created_at   = time();
    }

    // -------------------------------------------------------------------------
    // Public API.
    // -------------------------------------------------------------------------

    /**
     * Return all profile properties as an associative array.
     *
     * Used for storage/serialisation (e.g., saving a snapshot to acmls_learner_record).
     *
     * @return array<string, mixed> Associative array of all profile properties.
     */
    public function get_snapshot(): array {
        return [
            'id'                   => $this->id,
            'userid'               => $this->userid,
            'courseid'             => $this->courseid,
            'cognitive_level'      => $this->cognitive_level,
            'motivation_level'     => $this->motivation_level,
            'performance_category' => $this->performance_category,
            'learning_style'       => $this->learning_style,
            'behavioral_score'     => $this->behavioral_score,
            'engagement_score'     => $this->engagement_score,
            'profile_version'      => $this->profile_version,
            'last_updated'         => $this->last_updated,
            'created_at'           => $this->created_at,
        ];
    }

    /**
     * Convert this LearnerProfile to a database record object.
     *
     * Suitable for use with $DB->insert_record() or $DB->update_record()
     * against the acmls_learner_profile table.
     *
     * @return \stdClass Database record object.
     */
    public function to_db_record(): \stdClass {
        $record = new \stdClass();

        if ($this->id !== null) {
            $record->id = $this->id;
        }

        $record->userid               = $this->userid;
        $record->courseid             = $this->courseid;
        $record->cognitive_level      = $this->cognitive_level;
        $record->motivation_level     = $this->motivation_level;
        $record->performance_category = $this->performance_category;
        $record->learning_style       = $this->learning_style;
        $record->behavioral_score     = $this->behavioral_score;
        $record->engagement_score     = $this->engagement_score;
        $record->profile_version      = $this->profile_version;
        $record->last_updated         = $this->last_updated;
        $record->created_at           = $this->created_at;

        return $record;
    }

    /**
     * Reconstruct a LearnerProfile from a database record object.
     *
     * @param  \stdClass     $record Database record from acmls_learner_profile table.
     * @return self                  Populated LearnerProfile instance.
     */
    public static function from_db_record(\stdClass $record): self {
        $profile = new self(
            (int) $record->userid,
            (int) $record->courseid
        );

        $profile->id                   = (int) $record->id;
        $profile->cognitive_level      = (int) ($record->cognitive_level ?? self::COGNITIVE_LOW);
        $profile->motivation_level     = (float) ($record->motivation_level ?? 50.0);
        $profile->performance_category = (int) ($record->performance_category ?? self::PERFORMANCE_LOW);
        $profile->learning_style       = (string) ($record->learning_style ?? self::STYLE_UNKNOWN);
        $profile->behavioral_score     = (float) ($record->behavioral_score ?? 0.0);
        $profile->engagement_score     = (float) ($record->engagement_score ?? 0.0);
        $profile->profile_version      = (int) ($record->profile_version ?? 1);
        $profile->last_updated         = (int) ($record->last_updated ?? time());
        $profile->created_at           = (int) ($record->created_at ?? time());

        return $profile;
    }
}
