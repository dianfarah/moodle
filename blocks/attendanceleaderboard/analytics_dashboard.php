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
 * ACMLS Analytics Dashboard — standalone admin page.
 *
 * Displays:
 *  - Performance_Category distribution (Low / Middle / High counts and percentages)
 *  - Average Motivation_Level per course
 *  - Intervention effectiveness (percentage of positive learner responses)
 *
 * Requires capability: block/attendanceleaderboard:viewanalytics
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use block_attendanceleaderboard\output\analytics_dashboard_page;
use block_attendanceleaderboard\admin\config_audit_log;
use block_attendanceleaderboard\admin\config_audit_renderer;

// -------------------------------------------------------------------------
// Authentication and capability check.
// -------------------------------------------------------------------------

require_login();

$context = context_system::instance();
require_capability('block/attendanceleaderboard:viewanalytics', $context);

// -------------------------------------------------------------------------
// Page setup.
// -------------------------------------------------------------------------

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/attendanceleaderboard/analytics_dashboard.php'));
$PAGE->set_title(get_string('analytics_dashboard_title', 'block_attendanceleaderboard'));
$PAGE->set_heading(get_string('analytics_dashboard_title', 'block_attendanceleaderboard'));
$PAGE->set_pagelayout('admin');

// Breadcrumb navigation.
$PAGE->navbar->add(
    get_string('administrationsite'),
    new moodle_url('/admin/index.php')
);
$PAGE->navbar->add(
    get_string('pluginname', 'block_attendanceleaderboard'),
    new moodle_url('/admin/settings.php', ['section' => 'blocksettingattendanceleaderboard'])
);
$PAGE->navbar->add(get_string('analytics_dashboard', 'block_attendanceleaderboard'));

// -------------------------------------------------------------------------
// Data queries.
// -------------------------------------------------------------------------

// --- 1. Performance_Category distribution ---
// Performance_Category: 1=Low, 2=Middle, 3=High (TINYINT in acmls_learner_profile).

$performance_sql = "
    SELECT performance_category, COUNT(*) AS cnt
      FROM {acmls_learner_profile}
     GROUP BY performance_category
";

$performance_rows = $DB->get_records_sql($performance_sql);

$counts = [1 => 0, 2 => 0, 3 => 0];
foreach ($performance_rows as $row) {
    $cat = (int) $row->performance_category;
    if (isset($counts[$cat])) {
        $counts[$cat] = (int) $row->cnt;
    }
}

$total_learners = array_sum($counts);

// Build distribution array for the renderable.
$category_map = [
    1 => ['key' => 'low',    'string' => 'category_low',    'css_class' => 'danger'],
    2 => ['key' => 'middle', 'string' => 'category_middle', 'css_class' => 'warning'],
    3 => ['key' => 'high',   'string' => 'category_high',   'css_class' => 'success'],
];

$performance_distribution = [];
foreach ($category_map as $cat_id => $meta) {
    $count = $counts[$cat_id];
    $percentage = ($total_learners > 0) ? round(($count / $total_learners) * 100, 1) : 0.0;
    $performance_distribution[] = [
        'category_name' => get_string($meta['string'], 'block_attendanceleaderboard'),
        'count'         => $count,
        'percentage'    => $percentage,
        'css_class'     => $meta['css_class'],
    ];
}

// --- 2. Average Motivation_Level per course ---
// JOIN with mdl_course to get the course fullname.

$motivation_sql = "
    SELECT lp.courseid,
           c.fullname AS coursename,
           AVG(lp.motivation_level) AS avg_motivation
      FROM {acmls_learner_profile} lp
      JOIN {course} c ON c.id = lp.courseid
     GROUP BY lp.courseid, c.fullname
     ORDER BY c.fullname ASC
";

$motivation_rows = $DB->get_records_sql($motivation_sql);

$motivation_per_course = [];
foreach ($motivation_rows as $row) {
    $avg = (float) $row->avg_motivation;
    $motivation_per_course[] = [
        'courseid'               => (int) $row->courseid,
        'coursename'             => $row->coursename,
        'avg_motivation'         => $avg,
        'avg_motivation_formatted' => number_format($avg, 1),
    ];
}

// --- 3. Intervention effectiveness ---
// From acmls_coach_decision.learner_response:
//   0 = ignored, 1 = accessed (positive), NULL = pending.
// Only rows where learner_response IS NOT NULL are "decided".

$effectiveness_sql = "
    SELECT COUNT(*) AS total_decisions,
           SUM(CASE WHEN learner_response = 1 THEN 1 ELSE 0 END) AS positive_responses
      FROM {acmls_coach_decision}
     WHERE learner_response IS NOT NULL
";

$effectiveness_row = $DB->get_record_sql($effectiveness_sql);

$total_decisions    = (int) ($effectiveness_row->total_decisions ?? 0);
$positive_responses = (int) ($effectiveness_row->positive_responses ?? 0);
$effectiveness_pct  = ($total_decisions > 0)
    ? round(($positive_responses / $total_decisions) * 100, 1)
    : 0.0;

$intervention_effectiveness = [
    'total_decisions'          => $total_decisions,
    'positive_responses'       => $positive_responses,
    'effectiveness_percentage' => $effectiveness_pct,
    'effectiveness_formatted'  => number_format($effectiveness_pct, 1) . '%',
];

// -------------------------------------------------------------------------
// Render.
// -------------------------------------------------------------------------

$renderable = new analytics_dashboard_page(
    $performance_distribution,
    $motivation_per_course,
    $intervention_effectiveness,
    $total_learners
);

/** @var \block_attendanceleaderboard\output\renderer $renderer */
$renderer = $PAGE->get_renderer('block_attendanceleaderboard');

echo $OUTPUT->header();
echo $renderer->render($renderable);

// -------------------------------------------------------------------------
// Quick links section for instructors.
// -------------------------------------------------------------------------

// Show a "Manage Learning Resources" note directing instructors to access
// the manage_resources page from within a course context.
echo html_writer::div(
    html_writer::tag('p',
        get_string('manage_resources_link_desc', 'block_attendanceleaderboard'),
        ['class' => 'text-muted mb-0']
    ),
    'mt-3 p-3 border rounded bg-light'
);

// Show a "Learner Profile Monitoring" note directing instructors to access
// the learner_profiles page from within a course context.
echo html_writer::div(
    html_writer::tag('p',
        get_string('viewlearnerprofiles_desc', 'block_attendanceleaderboard'),
        ['class' => 'text-muted mb-0']
    ),
    'mt-3 p-3 border rounded bg-light'
);

// -------------------------------------------------------------------------
// Configuration Audit Log section (Task 13.6).
// -------------------------------------------------------------------------

// Count recent config changes (last 30 days) for the summary card.
$since_30_days = time() - (30 * DAYSECS);
$recent_audit_count = config_audit_log::count_logs(['since' => $since_30_days]);

echo html_writer::tag('h3',
    get_string('audit_log_title', 'block_attendanceleaderboard'),
    ['class' => 'mt-4']
);

echo html_writer::tag('p',
    get_string('audit_log_desc', 'block_attendanceleaderboard'),
    ['class' => 'text-muted']
);

// Show the last 10 audit entries as a preview.
$recent_entries = config_audit_log::get_logs([], 10, 0);
echo config_audit_renderer::render_table($recent_entries, $recent_audit_count, 0, 10);

// Link to the full audit log page.
echo html_writer::div(
    html_writer::link(
        new moodle_url('/blocks/attendanceleaderboard/config_audit_log.php'),
        get_string('audit_view_full_log', 'block_attendanceleaderboard'),
        ['class' => 'btn btn-outline-secondary btn-sm']
    ),
    'mt-2 mb-4'
);

echo $OUTPUT->footer();
