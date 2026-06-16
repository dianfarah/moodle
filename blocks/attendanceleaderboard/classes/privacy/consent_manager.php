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
 * Consent Manager for ACMLS — manages Learner consent for external LLM data transmission.
 *
 * Provides methods to check, save, revoke, and retrieve consent records stored
 * in the acmls_learner_consent table.
 *
 * Requirements addressed:
 * - Req 15.5: WHERE fitur pengiriman data ke layanan eksternal diaktifkan,
 *             THE ACMLS SHALL meminta persetujuan eksplisit dari Learner
 *             sebelum data mereka dikirimkan ke layanan tersebut.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Consent Manager — stores and retrieves Learner consent for external LLM data transmission.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consent_manager {

    /** @var string DB table name for learner consent. */
    const TABLE = 'acmls_learner_consent';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Check whether a Learner has given consent for external LLM data transmission.
     *
     * Returns true only if a consent record exists AND consent_given = 1.
     * Returns false if no record exists or consent_given = 0.
     *
     * @param  int  $userid   Moodle user ID.
     * @param  int  $courseid Moodle course ID.
     * @return bool           True if consent has been given, false otherwise.
     */
    public function has_consent(int $userid, int $courseid): bool {
        $record = $this->get_consent_record($userid, $courseid);

        if ($record === null) {
            return false;
        }

        return (bool) $record->consent_given;
    }

    /**
     * Save a Learner's consent decision for external LLM data transmission.
     *
     * If a record already exists for the user/course pair, it is updated.
     * Otherwise a new record is inserted.
     *
     * @param  int  $userid   Moodle user ID.
     * @param  int  $courseid Moodle course ID.
     * @param  bool $consent  True = consent given, false = consent declined.
     * @return void
     */
    public function save_consent(int $userid, int $courseid, bool $consent): void {
        global $DB;

        $now = time();
        $existing = $this->get_consent_record($userid, $courseid);

        if ($existing !== null) {
            // Update existing record.
            $record                   = new \stdClass();
            $record->id               = $existing->id;
            $record->consent_given    = $consent ? 1 : 0;
            $record->consent_timestamp = $consent ? $now : null;
            $record->timemodified     = $now;

            $DB->update_record(self::TABLE, $record);
        } else {
            // Insert new record.
            $record                   = new \stdClass();
            $record->userid           = $userid;
            $record->courseid         = $courseid;
            $record->consent_given    = $consent ? 1 : 0;
            $record->consent_timestamp = $consent ? $now : null;
            $record->timecreated      = $now;
            $record->timemodified     = $now;

            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Revoke a Learner's previously given consent.
     *
     * Sets consent_given = 0 and clears consent_timestamp.
     * If no record exists, this is a no-op.
     *
     * @param  int  $userid   Moodle user ID.
     * @param  int  $courseid Moodle course ID.
     * @return void
     */
    public function revoke_consent(int $userid, int $courseid): void {
        global $DB;

        $existing = $this->get_consent_record($userid, $courseid);

        if ($existing === null) {
            // Nothing to revoke.
            return;
        }

        $record                   = new \stdClass();
        $record->id               = $existing->id;
        $record->consent_given    = 0;
        $record->consent_timestamp = null;
        $record->timemodified     = time();

        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Get the full consent record for a Learner in a specific course.
     *
     * Returns null if no record exists.
     *
     * @param  int          $userid   Moodle user ID.
     * @param  int          $courseid Moodle course ID.
     * @return \stdClass|null         The consent record, or null if not found.
     */
    public function get_consent_record(int $userid, int $courseid): ?\stdClass {
        global $DB;

        $record = $DB->get_record(
            self::TABLE,
            ['userid' => $userid, 'courseid' => $courseid],
            '*',
            IGNORE_MISSING
        );

        return $record ?: null;
    }

    /**
     * Check whether the Learner has made any consent decision (given or declined).
     *
     * Returns true if a consent record exists (regardless of the decision).
     * Returns false if no record exists (consent dialog has not been shown yet).
     *
     * @param  int  $userid   Moodle user ID.
     * @param  int  $courseid Moodle course ID.
     * @return bool           True if a consent decision has been recorded.
     */
    public function has_made_decision(int $userid, int $courseid): bool {
        return $this->get_consent_record($userid, $courseid) !== null;
    }

    /**
     * Delete all consent records for a specific user (used by Privacy API).
     *
     * @param  int  $userid Moodle user ID.
     * @return void
     */
    public function delete_for_user(int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['userid' => $userid]);
    }

    /**
     * Delete all consent records for a specific user in a specific course.
     *
     * @param  int  $userid   Moodle user ID.
     * @param  int  $courseid Moodle course ID.
     * @return void
     */
    public function delete_for_user_in_course(int $userid, int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['userid' => $userid, 'courseid' => $courseid]);
    }
}
