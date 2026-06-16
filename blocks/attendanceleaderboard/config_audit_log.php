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
 * ACMLS Configuration Audit Log — standalone admin page.
 *
 * Displays a paginated table of all configuration changes made by
 * administrators, showing: who changed what, when, and the old vs new values.
 *
 * Requires capability: block/attendanceleaderboard:viewanalytics
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use block_attendanceleaderboard\admin\config_audit_log;
use block_attendanceleaderboard\admin\config_audit_renderer;

// -------------------------------------------------------------------------
// Authentication and capability check.
// -------------------------------------------------------------------------

require_login();

$context = context_system::instance();
require_capability('block/attendanceleaderboard:viewanalytics', $context);

// -------------------------------------------------------------------------
// Parameters.
// -------------------------------------------------------------------------

$page    = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$perpage = max(10, min(200, $perpage)); // Clamp to sensible range.

// Optional filters.
$filter_userid  = optional_param('userid', 0, PARAM_INT);
$filter_setting = optional_param('setting', '', PARAM_TEXT);

// -------------------------------------------------------------------------
// Page setup.
// -------------------------------------------------------------------------

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/attendanceleaderboard/config_audit_log.php'));
$PAGE->set_title(get_string('audit_log_title', 'block_attendanceleaderboard'));
$PAGE->set_heading(get_string('audit_log_title', 'block_attendanceleaderboard'));
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
$PAGE->navbar->add(
    get_string('analytics_dashboard', 'block_attendanceleaderboard'),
    new moodle_url('/blocks/attendanceleaderboard/analytics_dashboard.php')
);
$PAGE->navbar->add(get_string('audit_log_title', 'block_attendanceleaderboard'));

// -------------------------------------------------------------------------
// Build filters.
// -------------------------------------------------------------------------

$filters = [];
if ($filter_userid > 0) {
    $filters['userid'] = $filter_userid;
}
if ($filter_setting !== '') {
    $filters['setting_name'] = $filter_setting;
}

// -------------------------------------------------------------------------
// Data retrieval.
// -------------------------------------------------------------------------

$total   = config_audit_log::count_logs($filters);
$offset  = $page * $perpage;
$entries = config_audit_log::get_logs($filters, $perpage, $offset);

// -------------------------------------------------------------------------
// Render.
// -------------------------------------------------------------------------

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('audit_log_title', 'block_attendanceleaderboard'), 2);

// Description.
echo html_writer::tag(
    'p',
    get_string('audit_log_desc', 'block_attendanceleaderboard'),
    ['class' => 'text-muted']
);

// Back link to analytics dashboard.
echo html_writer::div(
    html_writer::link(
        new moodle_url('/blocks/attendanceleaderboard/analytics_dashboard.php'),
        '← ' . get_string('analytics_dashboard', 'block_attendanceleaderboard'),
        ['class' => 'btn btn-outline-secondary btn-sm mb-3']
    )
);

// Render the audit log table.
$base_url = new moodle_url('/blocks/attendanceleaderboard/config_audit_log.php', $filters);
echo config_audit_renderer::render_table($entries, $total, $page, $perpage, $base_url->out(false));

// Moodle paging bar.
if ($total > $perpage) {
    echo $OUTPUT->paging_bar($total, $page, $perpage, $base_url);
}

echo $OUTPUT->footer();
