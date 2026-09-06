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
 * External function: submit_motivation_feedback.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External API for storing popup emotion feedback.
 */
class submit_motivation_feedback extends \external_api {

    /**
     * Define input parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'userid' => new \external_value(PARAM_INT, 'Moodle user ID'),
            'courseid' => new \external_value(PARAM_INT, 'Moodle course ID'),
            'sentenceid' => new \external_value(PARAM_INT, 'Sentence repository ID', VALUE_DEFAULT, 0),
            'category' => new \external_value(PARAM_TEXT, 'Motivation category'),
            'source' => new \external_value(PARAM_TEXT, 'Message source'),
            'message_content' => new \external_value(PARAM_TEXT, 'Delivered message content'),
            'e1' => new \external_value(PARAM_INT, 'Motivation to continue'),
            'e2' => new \external_value(PARAM_INT, 'Self-confidence score'),
            'e3' => new \external_value(PARAM_INT, 'Feeling supported score'),
            'reflection_note' => new \external_value(PARAM_TEXT, 'Optional reflection note', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Moodle course ID.
     * @param int $sentenceid Sentence repository ID.
     * @param string $category Motivation category.
     * @param string $source Message source.
     * @param string $message_content Delivered message content.
     * @param int $e1 Motivation to continue score (1-5).
     * @param int $e2 Self-confidence score (1-5).
     * @param int $e3 Feeling supported score (1-5).
     * @param string $reflection_note Optional reflection note.
     * @return array<string,mixed>
     */
    public static function execute(
        int $userid,
        int $courseid,
        int $sentenceid = 0,
        string $category = '',
        string $source = '',
        string $message_content = '',
        int $e1 = 0,
        int $e2 = 0,
        int $e3 = 0,
        string $reflection_note = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'courseid' => $courseid,
            'sentenceid' => $sentenceid,
            'category' => $category,
            'source' => $source,
            'message_content' => $message_content,
            'e1' => $e1,
            'e2' => $e2,
            'e3' => $e3,
            'reflection_note' => $reflection_note,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);

        if ((int) $USER->id !== (int) $params['userid']) {
            throw new \moodle_exception('accessdenied', 'error');
        }

        require_capability('block/attendanceleaderboard:viewleaderboard', $context);

        try {
            $component = new \block_attendanceleaderboard\motivation\motivation_component();
            $feedbackid = $component->record_emotional_feedback(
                (int) $params['userid'],
                (int) $params['courseid'],
                [
                    'sentenceid' => $params['sentenceid'] > 0 ? (int) $params['sentenceid'] : null,
                    'category' => (string) $params['category'],
                    'source' => (string) $params['source'],
                    'message_content' => (string) $params['message_content'],
                    'e1' => (int) $params['e1'],
                    'e2' => (int) $params['e2'],
                    'e3' => (int) $params['e3'],
                    'reflection_note' => (string) $params['reflection_note'],
                ]
            );

            return [
                'success' => true,
                'message' => '',
                'feedbackid' => $feedbackid,
            ];
        } catch (\Throwable $e) {
            debugging('submit_motivation_feedback external error: ' . $e->getMessage(), DEBUG_DEVELOPER);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'feedbackid' => 0,
            ];
        }
    }

    /**
     * Return value structure.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether feedback was stored successfully'),
            'message' => new \external_value(PARAM_TEXT, 'Error message if any', VALUE_DEFAULT, ''),
            'feedbackid' => new \external_value(PARAM_INT, 'Inserted feedback record ID', VALUE_DEFAULT, 0),
        ]);
    }
}
