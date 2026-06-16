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
 * Web service function definitions for block_attendanceleaderboard.
 *
 * Registers the external functions used by the AMD frontend modules:
 * - block_attendanceleaderboard_record_interaction  (called by amd/src/block.js)
 * - block_attendanceleaderboard_get_leaderboard_data (called by amd/src/leaderboard.js)
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    /**
     * Record a Learner interaction (resource accessed or encouragement dismissed).
     *
     * Called by amd/src/block.js when the Learner clicks a resource link or
     * dismisses an encouragement message. Persists the event to
     * acmls_learner_record and forwards it to TrackingSystem.
     *
     * Requirements: Req 5.4, Req 5.6, Req 12.7
     */
    'block_attendanceleaderboard_record_interaction' => [
        'classname'     => 'block_attendanceleaderboard\external\record_interaction',
        'methodname'    => 'execute',
        'description'   => 'Record a Learner interaction (resource accessed or encouragement dismissed).',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/attendanceleaderboard:viewleaderboard',
    ],

    /**
     * Get leaderboard data for a specific user and course.
     *
     * Called by amd/src/leaderboard.js every 15 minutes to refresh the
     * leaderboard display without a full page reload.
     *
     * Requirements: Req 12.2, Req 12.3
     */
    'block_attendanceleaderboard_get_leaderboard_data' => [
        'classname'     => 'block_attendanceleaderboard\external\get_leaderboard_data',
        'methodname'    => 'execute',
        'description'   => 'Get leaderboard rank data for a specific user and course.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/attendanceleaderboard:viewleaderboard',
    ],

    /**
     * Save a Learner's consent decision for external LLM data transmission.
     *
     * Called by amd/src/consent_dialog.js when the Learner clicks "Setuju"
     * (Agree) or "Tolak" (Decline) in the consent modal. Persists the
     * decision to acmls_learner_consent via ConsentManager::save_consent().
     *
     * Requirements: Req 15.5 / Task 14.6
     */
    'block_attendanceleaderboard_save_consent' => [
        'classname'     => 'block_attendanceleaderboard\external\save_consent',
        'methodname'    => 'execute',
        'description'   => 'Save a Learner\'s explicit consent decision for sending anonymised data to an external LLM service.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/attendanceleaderboard:viewleaderboard',
    ],

    /**
     * Save a learner's emotional feedback after a motivational popup.
     */
    'block_attendanceleaderboard_submit_motivation_feedback' => [
        'classname'     => 'block_attendanceleaderboard\external\submit_motivation_feedback',
        'methodname'    => 'execute',
        'description'   => 'Store the learner emotional feedback submitted after a motivational popup.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'block/attendanceleaderboard:viewleaderboard',
    ],
];
