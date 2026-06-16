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
 * CLI helper to seed or update an ACMLS learner profile for manual testing.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$help = <<<EOF
Seed or update an ACMLS learner profile for manual testing.

Options:
--userid=INT        Required. Moodle user id.
--courseid=INT      Required. Moodle course id.
--score=FLOAT       Optional. Default: 25
--duration=INT      Optional. Duration in seconds. Default: 300
--resource=STRING   Optional. Resource type. Default: video
--help              Show this help

Example:
php blocks/attendanceleaderboard/cli/seed_profile.php --userid=3 --courseid=3
php blocks/attendanceleaderboard/cli/seed_profile.php --userid=3 --courseid=3 --score=20 --duration=600 --resource=page
EOF;

[$options] = cli_get_params(
    [
        'help' => false,
        'userid' => null,
        'courseid' => null,
        'score' => 25,
        'duration' => 300,
        'resource' => 'video',
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($options['help']) || empty($options['userid']) || empty($options['courseid'])) {
    echo $help . PHP_EOL;
    exit(empty($options['help']) ? 1 : 0);
}

$userid = (int) $options['userid'];
$courseid = (int) $options['courseid'];
$score = (float) $options['score'];
$duration = (int) $options['duration'];
$resource = trim((string) $options['resource']);

if ($userid <= 0 || $courseid <= 0) {
    cli_error('userid and courseid must be positive integers.');
}

if (!class_exists('\block_attendanceleaderboard\profiling\profiling_system')) {
    cli_error('The ACMLS profiling system class is not available.');
}

try {
    $profiler = new \block_attendanceleaderboard\profiling\profiling_system();
    $profile = $profiler->update_profile($userid, $courseid, [
        'score' => $score,
        'duration_seconds' => $duration,
        'resource_type' => $resource !== '' ? $resource : 'video',
    ]);

    mtrace('ACMLS profile seeded successfully.');
    mtrace('userid=' . $userid . ', courseid=' . $courseid);
    mtrace('performance_category=' . $profile->performance_category .
        ', motivation_level=' . $profile->motivation_level .
        ', engagement_score=' . $profile->engagement_score);
} catch (\Throwable $e) {
    cli_error('Failed to seed ACMLS profile: ' . $e->getMessage());
}
