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
 * External function: record_interaction
 *
 * Called by the AMD block.js module when a Learner accesses a recommended
 * resource or dismisses an encouragement message. Delegates to
 * DeliverySystem::record_interaction() which persists the event to
 * acmls_learner_record and forwards it to TrackingSystem.
 *
 * Requirements addressed:
 * - Req 5.4: Record resource access interactions.
 * - Req 5.6: Record dismissal/ignore interactions.
 * - Req 12.7: Record whether Learner accessed or ignored recommended resource.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External function class for recording Learner interactions.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class record_interaction extends \external_api {

    /**
     * Define the input parameters for this external function.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'userid'           => new \external_value(PARAM_INT,  'Moodle user ID'),
            'courseid'         => new \external_value(PARAM_INT,  'Moodle course ID'),
            'interaction_type' => new \external_value(PARAM_ALPHANUMEXT, 'Interaction type (e.g. resource_accessed, encouragement_dismissed)'),
            'data'             => new \external_value(PARAM_RAW,  'JSON-encoded additional data payload', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Execute the external function.
     *
     * Validates the caller's context, then delegates to DeliverySystem::record_interaction().
     *
     * @param  int    $userid           Moodle user ID.
     * @param  int    $courseid         Moodle course ID.
     * @param  string $interaction_type Interaction type string.
     * @param  string $data             JSON-encoded additional data.
     * @return array                    ['success' => bool, 'message' => string]
     */
    public static function execute(
        int $userid,
        int $courseid,
        string $interaction_type,
        string $data = '{}'
    ): array {
        global $USER;

        // Validate and clean parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid'           => $userid,
            'courseid'         => $courseid,
            'interaction_type' => $interaction_type,
            'data'             => $data,
        ]);

        // Validate context.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);

        // Security: users may only record interactions for themselves.
        if ((int) $USER->id !== (int) $params['userid']) {
            throw new \moodle_exception('accessdenied', 'error');
        }

        // Require the viewleaderboard capability.
        require_capability('block/attendanceleaderboard:viewleaderboard', $context);

        // Decode the data payload.
        $decoded_data = [];
        if (!empty($params['data'])) {
            $decoded = json_decode($params['data'], true);
            if (is_array($decoded)) {
                $decoded_data = $decoded;
            }
        }

        // Delegate to DeliverySystem.
        try {
            $delivery = new \block_attendanceleaderboard\delivery\delivery_system();
            $delivery->record_interaction(
                (int) $params['userid'],
                (int) $params['courseid'],
                (string) $params['interaction_type'],
                $decoded_data
            );

            return ['success' => true, 'message' => ''];
        } catch (\Throwable $e) {
            debugging(
                'record_interaction external function error: ' . $e->getMessage(),
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
            'success' => new \external_value(PARAM_BOOL, 'Whether the interaction was recorded successfully'),
            'message' => new \external_value(PARAM_TEXT, 'Error message if not successful', VALUE_DEFAULT, ''),
        ]);
    }
}
