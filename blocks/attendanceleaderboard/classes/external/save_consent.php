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
 * External function: save_consent
 *
 * Called by the AMD consent_dialog.js module when a Learner makes a consent
 * decision (agree or decline) for sending anonymised data to an external LLM
 * service. Delegates to ConsentManager::save_consent().
 *
 * Requirements addressed:
 * - Req 15.5: Explicit Learner consent before data is sent to external services.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use block_attendanceleaderboard\privacy\consent_manager;

/**
 * External function class for saving Learner consent decisions.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_consent extends \external_api {

    /**
     * Define the input parameters for this external function.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'userid'   => new \external_value(PARAM_INT,  'Moodle user ID'),
            'courseid' => new \external_value(PARAM_INT,  'Moodle course ID'),
            'consent'  => new \external_value(PARAM_BOOL, 'True = consent given, false = consent declined'),
        ]);
    }

    /**
     * Execute the external function.
     *
     * Validates the caller's context and capability, then delegates to
     * ConsentManager::save_consent() to persist the decision.
     *
     * @param  int  $userid   Moodle user ID.
     * @param  int  $courseid Moodle course ID.
     * @param  bool $consent  True = consent given, false = consent declined.
     * @return array          ['success' => bool, 'message' => string]
     */
    public static function execute(int $userid, int $courseid, bool $consent): array {
        global $USER;

        // Validate and clean parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid'   => $userid,
            'courseid' => $courseid,
            'consent'  => $consent,
        ]);

        // Validate context.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);

        // Security: users may only save consent for themselves.
        if ((int) $USER->id !== (int) $params['userid']) {
            throw new \moodle_exception('accessdenied', 'error');
        }

        // Require the viewleaderboard capability (all enrolled learners have this).
        require_capability('block/attendanceleaderboard:viewleaderboard', $context);

        try {
            $manager = new consent_manager();
            $manager->save_consent(
                (int) $params['userid'],
                (int) $params['courseid'],
                (bool) $params['consent']
            );

            return ['success' => true, 'message' => ''];
        } catch (\Throwable $e) {
            debugging(
                'save_consent external function error: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Define the return value structure for this external function.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the consent was saved successfully'),
            'message' => new \external_value(PARAM_TEXT, 'Error message if not successful', VALUE_DEFAULT, ''),
        ]);
    }
}
