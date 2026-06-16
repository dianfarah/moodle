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
 * ACMLS Learner Profile Monitoring — instructor interface.
 *
 * Lists all enrolled learners in a course with their ACMLS profile data.
 * Supports filtering by Performance Category.
 *
 * URL parameters:
 *   - courseid           (int, required) — Moodle course ID.
 *   - filter_performance (int, optional) — 0=All, 1=Low, 2=Middle, 3=High.
 *
 * Requires capability: block/attendanceleaderboard:viewanalytics
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_attendanceleaderboard\output\learner_profile_table;

// -------------------------------------------------------------------------
// Parameters.
// -------------------------------------------------------------------------

$courseid           = required_param('courseid', PARAM_INT);
$filter_performance = optional_param('filter_performance', 0, PARAM_INT);

// -------------------------------------------------------------------------
// Authentication and capability check.
// -------------------------------------------------------------------------

require_login($courseid);

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_capability('block/attendanceleaderboard:viewanalytics', $context);

// -------------------------------------------------------------------------
// Page setup.
// -------------------------------------------------------------------------

$baseurl = new moodle_url('/blocks/attendanceleaderboard/learner_profiles.php', [
    'courseid'           => $courseid,
    'filter_performance' => $filter_performance,
]);

$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_course($course);
$PAGE->set_title(get_string('learnerprofilemonitoring', 'block_attendanceleaderboard'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Breadcrumb navigation.
$PAGE->navbar->add(get_string('pluginname', 'block_attendanceleaderboard'));
$PAGE->navbar->add(
    get_string('learnerprofilemonitoring', 'block_attendanceleaderboard'),
    $baseurl
);

// -------------------------------------------------------------------------
// Filter form (GET).
// -------------------------------------------------------------------------

$filter_options = [
    0 => get_string('allcategories',  'block_attendanceleaderboard'),
    1 => get_string('low',            'block_attendanceleaderboard'),
    2 => get_string('middle',         'block_attendanceleaderboard'),
    3 => get_string('high',           'block_attendanceleaderboard'),
];

// Validate filter value.
if (!array_key_exists($filter_performance, $filter_options)) {
    $filter_performance = 0;
}

$filter_param = ($filter_performance > 0) ? $filter_performance : null;

// -------------------------------------------------------------------------
// Build the table.
// -------------------------------------------------------------------------

$table = new learner_profile_table(
    'acmls_learner_profiles_' . $courseid,
    $courseid,
    $filter_param,
    $context
);

$table->define_baseurl($baseurl);

// -------------------------------------------------------------------------
// Render.
// -------------------------------------------------------------------------

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('learnerprofilemonitoring', 'block_attendanceleaderboard'));

// --- Filter form ---
$filter_url = new moodle_url('/blocks/attendanceleaderboard/learner_profiles.php', [
    'courseid' => $courseid,
]);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $filter_url->out(false),
    'class'  => 'mb-3 form-inline',
]);
echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'courseid',
    'value' => $courseid,
]);

echo html_writer::tag('label',
    get_string('filterbyperformance', 'block_attendanceleaderboard') . ':',
    ['for' => 'filter_performance', 'class' => 'mr-2']
);

$select_attrs = [
    'id'    => 'filter_performance',
    'name'  => 'filter_performance',
    'class' => 'custom-select mr-2',
];
$select_html = html_writer::start_tag('select', $select_attrs);
foreach ($filter_options as $val => $label) {
    $opt_attrs = ['value' => $val];
    if ($val == $filter_performance) {
        $opt_attrs['selected'] = 'selected';
    }
    $select_html .= html_writer::tag('option', $label, $opt_attrs);
}
$select_html .= html_writer::end_tag('select');
echo $select_html;

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('filter'),
    'class' => 'btn btn-secondary',
]);

echo html_writer::end_tag('form');

// --- Table ---
$table->out(30, true);

echo $OUTPUT->footer();
