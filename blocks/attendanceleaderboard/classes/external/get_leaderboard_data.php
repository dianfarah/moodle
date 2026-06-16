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
 * External function: get_leaderboard_data
 *
 * Called by the AMD leaderboard.js module to refresh leaderboard data
 * every 15 minutes without a full page reload.
 *
 * Requirements addressed:
 * - Req 12.2: Update leaderboard position within ≤15 minutes.
 * - Req 12.3: Display current rank, total score, rank change, and points to next.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External function class for fetching leaderboard data.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_leaderboard_data extends \external_api {

    /**
     * Define the input parameters for this external function.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'userid'   => new \external_value(PARAM_INT, 'Moodle user ID'),
            'courseid' => new \external_value(PARAM_INT, 'Moodle course ID'),
            'scope'    => new \external_value(PARAM_ALPHA, 'Leaderboard scope: course, program, or institution', VALUE_DEFAULT, 'course'),
        ]);
    }

    /**
     * Execute the external function.
     *
     * Returns the current learner rank data and top-10 leaderboard entries
     * for use in the leaderboard.mustache template re-render.
     *
     * @param  int    $userid   Moodle user ID.
     * @param  int    $courseid Moodle course ID.
     * @param  string $scope    Leaderboard scope.
     * @return array            Leaderboard data array.
     */
    public static function execute(int $userid, int $courseid, string $scope = 'course'): array {
        // Validate and clean parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid'   => $userid,
            'courseid' => $courseid,
            'scope'    => $scope,
        ]);

        // Validate context.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);

        // Require the viewleaderboard capability.
        require_capability('block/attendanceleaderboard:viewleaderboard', $context);

        $rank_data = [];
        $entries   = [];

        if (class_exists('\block_attendanceleaderboard\leaderboard\leaderboard')) {
            try {
                $lb = new \block_attendanceleaderboard\leaderboard\leaderboard();

                $raw_rank = $lb->get_learner_rank(
                    (int) $params['userid'],
                    (int) $params['courseid'],
                    (string) $params['scope']
                );

                if (!empty($raw_rank)) {
                    $rank_change = isset($raw_rank['rank_change']) ? (int) $raw_rank['rank_change'] : 0;
                    $rank_data = [
                        'userid'               => (int) $raw_rank['userid'],
                        'current_rank'         => (int) $raw_rank['current_rank'],
                        'total_score'          => number_format((float) $raw_rank['total_score'], 2),
                        'rank_change'          => $rank_change,
                        'rank_change_positive' => $rank_change > 0,
                        'rank_change_negative' => $rank_change < 0,
                        'rank_change_abs'      => abs($rank_change),
                        'points_to_next'       => isset($raw_rank['points_to_next']) ? (float) $raw_rank['points_to_next'] : 0.0,
                        'display_name'         => (string) ($raw_rank['display_name'] ?? ''),
                    ];
                }

                $top_rankings = $lb->get_scope_rankings(
                    (int) $params['courseid'],
                    (string) $params['scope'],
                    10
                );

                foreach ($top_rankings as $entry) {
                    $entry_rank_change = isset($entry['rank_change']) ? (int) $entry['rank_change'] : 0;
                    $entries[] = [
                        'rank'            => (int) $entry['current_rank'],
                        'display_name'    => (string) ($entry['display_name'] ?? ''),
                        'total_score'     => number_format((float) ($entry['total_score'] ?? 0), 2),
                        'rank_change'     => $entry_rank_change,
                        'is_current_user' => ((int) ($entry['userid'] ?? 0)) === (int) $params['userid'],
                    ];
                }
            } catch (\Throwable $e) {
                debugging(
                    'get_leaderboard_data external function error: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        return [
            'rank_data' => $rank_data,
            'entries'   => $entries,
        ];
    }

    /**
     * Define the return value structure for this external function.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'rank_data' => new \external_single_structure([
                'userid'               => new \external_value(PARAM_INT,   'User ID'),
                'current_rank'         => new \external_value(PARAM_INT,   'Current rank'),
                'total_score'          => new \external_value(PARAM_TEXT,  'Total score (formatted)'),
                'rank_change'          => new \external_value(PARAM_INT,   'Rank change (positive = improved)'),
                'rank_change_positive' => new \external_value(PARAM_BOOL,  'True if rank improved'),
                'rank_change_negative' => new \external_value(PARAM_BOOL,  'True if rank declined'),
                'rank_change_abs'      => new \external_value(PARAM_INT,   'Absolute value of rank change'),
                'points_to_next'       => new \external_value(PARAM_FLOAT, 'Points needed to reach next rank'),
                'display_name'         => new \external_value(PARAM_TEXT,  'Display name'),
            ], 'Learner rank data', VALUE_DEFAULT, []),
            'entries' => new \external_multiple_structure(
                new \external_single_structure([
                    'rank'            => new \external_value(PARAM_INT,  'Rank position'),
                    'display_name'    => new \external_value(PARAM_TEXT, 'Display name'),
                    'total_score'     => new \external_value(PARAM_TEXT, 'Total score (formatted)'),
                    'rank_change'     => new \external_value(PARAM_INT,  'Rank change'),
                    'is_current_user' => new \external_value(PARAM_BOOL, 'True if this entry is the current user'),
                ]),
                'Top leaderboard entries',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }
}
