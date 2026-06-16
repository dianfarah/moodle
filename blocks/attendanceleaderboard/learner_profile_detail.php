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
 * ACMLS Learner Profile Detail — individual learner view for instructors.
 *
 * Displays the full ACMLS profile for a single learner in a course, plus
 * a history of profile snapshots from acmls_learner_record.
 *
 * URL parameters:
 *   - userid   (int, required) — Moodle user ID.
 *   - courseid (int, required) — Moodle course ID.
 *
 * Requires capability: block/attendanceleaderboard:viewanalytics
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// -------------------------------------------------------------------------
// Parameters.
// -------------------------------------------------------------------------

$userid   = required_param('userid',   PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

// -------------------------------------------------------------------------
// Authentication and capability check.
// -------------------------------------------------------------------------

require_login($courseid);

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_capability('block/attendanceleaderboard:viewanalytics', $context);

// Verify the target user exists.
$targetuser = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

// -------------------------------------------------------------------------
// Page setup.
// -------------------------------------------------------------------------

$pageurl = new moodle_url('/blocks/attendanceleaderboard/learner_profile_detail.php', [
    'userid'   => $userid,
    'courseid' => $courseid,
]);

$listurl = new moodle_url('/blocks/attendanceleaderboard/learner_profiles.php', [
    'courseid' => $courseid,
]);

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_course($course);
$PAGE->set_title(get_string('learnerprofiledetail', 'block_attendanceleaderboard'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Breadcrumb navigation.
$PAGE->navbar->add(get_string('pluginname', 'block_attendanceleaderboard'));
$PAGE->navbar->add(
    get_string('learnerprofilemonitoring', 'block_attendanceleaderboard'),
    $listurl
);
$PAGE->navbar->add(
    get_string('learnerprofiledetail', 'block_attendanceleaderboard'),
    $pageurl
);

// -------------------------------------------------------------------------
// Fetch profile data.
// -------------------------------------------------------------------------

$profile = $DB->get_record('acmls_learner_profile', [
    'userid'   => $userid,
    'courseid' => $courseid,
]);

// -------------------------------------------------------------------------
// Fetch profile history (last 10 profile_snapshot records).
// -------------------------------------------------------------------------

$history_records = [];
if ($profile) {
    $history_sql = "
        SELECT id, data_payload, profile_version, timecreated
          FROM {acmls_learner_record}
         WHERE userid   = :userid
           AND courseid = :courseid
           AND record_type = 'profile_snapshot'
         ORDER BY timecreated DESC
         LIMIT 10
    ";
    $history_raw = $DB->get_records_sql($history_sql, [
        'userid'   => $userid,
        'courseid' => $courseid,
    ]);

    foreach ($history_raw as $rec) {
        $payload = @json_decode($rec->data_payload, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        // Extract performance_category and motivation_level from the snapshot payload.
        $perf_cat = isset($payload['performance_category']) ? (int) $payload['performance_category'] : null;
        $mot_lvl  = isset($payload['motivation_level'])     ? (float) $payload['motivation_level']   : null;

        $history_records[] = [
            'timecreated'          => userdate((int) $rec->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            'profile_version'      => (int) $rec->profile_version,
            'performance_category' => $perf_cat !== null ? get_level_label($perf_cat) : '—',
            'motivation_level'     => $mot_lvl  !== null ? number_format($mot_lvl, 1) . '%' : '—',
        ];
    }
}

// -------------------------------------------------------------------------
// Helper: map 1/2/3 to Low/Middle/High label.
// -------------------------------------------------------------------------

/**
 * Return the localised label for a 1/2/3 level value.
 *
 * @param  int    $level  1=Low, 2=Middle, 3=High.
 * @return string         Localised label.
 */
function get_level_label(int $level): string {
    switch ($level) {
        case 1: return get_string('low',    'block_attendanceleaderboard');
        case 2: return get_string('middle', 'block_attendanceleaderboard');
        case 3: return get_string('high',   'block_attendanceleaderboard');
        default: return '—';
    }
}

// -------------------------------------------------------------------------
// Build template data.
// -------------------------------------------------------------------------

$learner_fullname = fullname($targetuser);

if ($profile) {
    $perf_cat = (int) $profile->performance_category;
    $cog_lvl  = (int) $profile->cognitive_level;

    // Bootstrap badge class for performance category.
    $perf_class_map = [1 => 'danger', 2 => 'warning', 3 => 'success'];
    $perf_class = $perf_class_map[$perf_cat] ?? 'secondary';

    $motivation_pct = min(100, max(0, (float) $profile->motivation_level));
    if ($motivation_pct >= 70) {
        $mot_bar_class = 'bg-success';
    } else if ($motivation_pct >= 40) {
        $mot_bar_class = 'bg-warning';
    } else {
        $mot_bar_class = 'bg-danger';
    }

    $template_data = [
        'has_profile'          => true,
        'learner_fullname'     => $learner_fullname,
        'userid'               => $userid,
        'courseid'             => $courseid,
        'profile_version'      => (int) $profile->profile_version,
        'performance_category' => get_level_label($perf_cat),
        'performance_class'    => $perf_class,
        'cognitive_level'      => get_level_label($cog_lvl),
        'motivation_level'     => number_format((float) $profile->motivation_level, 1),
        'motivation_pct'       => $motivation_pct,
        'mot_bar_class'        => $mot_bar_class,
        'learning_style'       => format_string($profile->learning_style ?? '—'),
        'behavioral_score'     => number_format((float) $profile->behavioral_score, 2),
        'engagement_score'     => number_format((float) $profile->engagement_score, 2),
        'last_updated'         => !empty($profile->last_updated)
            ? userdate((int) $profile->last_updated, get_string('strftimedatetimeshort', 'langconfig'))
            : '—',
        'has_history'          => !empty($history_records),
        'history'              => $history_records,
        'back_url'             => $listurl->out(false),
        'profile_url'          => (new moodle_url('/user/view.php', [
            'id'     => $userid,
            'course' => $courseid,
        ]))->out(false),
    ];
} else {
    $template_data = [
        'has_profile'      => false,
        'learner_fullname' => $learner_fullname,
        'userid'           => $userid,
        'courseid'         => $courseid,
        'back_url'         => $listurl->out(false),
        'profile_url'      => (new moodle_url('/user/view.php', [
            'id'     => $userid,
            'course' => $courseid,
        ]))->out(false),
        'noprofilefound'   => get_string('noprofilefound', 'block_attendanceleaderboard'),
    ];
}

// -------------------------------------------------------------------------
// Render.
// -------------------------------------------------------------------------

echo $OUTPUT->header();
echo $OUTPUT->heading(
    get_string('learnerprofiledetail', 'block_attendanceleaderboard') . ': ' . $learner_fullname
);

echo $OUTPUT->render_from_template(
    'block_attendanceleaderboard/learner_profile_detail',
    $template_data
);

echo $OUTPUT->footer();
