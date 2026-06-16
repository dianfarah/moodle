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
 * ACMLS Learning Resource Management — instructor interface.
 *
 * Supports actions: list (default), add, edit, delete (with confirmation).
 * Requires capability: block/attendanceleaderboard:manageresources
 *
 * URL parameters:
 *   - courseid (int, required) — Moodle course ID.
 *   - action   (string)       — 'list' (default), 'add', 'edit', 'delete'.
 *   - id       (int)          — Resource ID for edit/delete actions.
 *   - confirm  (int)          — 1 to confirm a delete action.
 *   - sesskey  (string)       — Moodle session key (required for write actions).
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use block_attendanceleaderboard\repository\learning_resource_repository;
use block_attendanceleaderboard\form\resource_form;

// -------------------------------------------------------------------------
// Parameters.
// -------------------------------------------------------------------------

$courseid = required_param('courseid', PARAM_INT);
$action   = optional_param('action', 'list', PARAM_ALPHA);
$id       = optional_param('id', 0, PARAM_INT);
$confirm  = optional_param('confirm', 0, PARAM_INT);

// -------------------------------------------------------------------------
// Authentication and capability check.
// -------------------------------------------------------------------------

require_login($courseid);

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_capability('block/attendanceleaderboard:manageresources', $context);

// -------------------------------------------------------------------------
// Page setup.
// -------------------------------------------------------------------------

$base_url = new moodle_url('/blocks/attendanceleaderboard/manage_resources.php', ['courseid' => $courseid]);

$PAGE->set_context($context);
$PAGE->set_url($base_url);
$PAGE->set_course($course);
$PAGE->set_title(get_string('manage_resources_title', 'block_attendanceleaderboard'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Breadcrumb navigation.
$PAGE->navbar->add(
    get_string('pluginname', 'block_attendanceleaderboard')
);
$PAGE->navbar->add(
    get_string('manage_resources_title', 'block_attendanceleaderboard'),
    $base_url
);

// -------------------------------------------------------------------------
// Repository instance.
// -------------------------------------------------------------------------

$repo = new learning_resource_repository();

// -------------------------------------------------------------------------
// Action: DELETE
// -------------------------------------------------------------------------

if ($action === 'delete' && $id > 0) {
    require_sesskey();

    $resource = $DB->get_record('acmls_learning_resource', ['id' => $id, 'courseid' => $courseid]);

    if (!$resource) {
        redirect($base_url, get_string('resource_not_found', 'block_attendanceleaderboard'), null, \core\output\notification::NOTIFY_ERROR);
    }

    if ($confirm) {
        // Perform soft-delete (sets is_active = 0).
        $repo->delete_resource($id);
        redirect($base_url, get_string('resource_deleted', 'block_attendanceleaderboard'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Show confirmation page.
    $PAGE->navbar->add(get_string('deleteresource', 'block_attendanceleaderboard'));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deleteresource', 'block_attendanceleaderboard'));

    $confirm_url = new moodle_url($base_url, [
        'action'  => 'delete',
        'id'      => $id,
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]);

    echo $OUTPUT->confirm(
        get_string('resource_delete_confirm', 'block_attendanceleaderboard', format_string($resource->title)),
        $confirm_url,
        $base_url
    );

    echo $OUTPUT->footer();
    exit;
}

// -------------------------------------------------------------------------
// Action: ADD or EDIT (form handling)
// -------------------------------------------------------------------------

if ($action === 'add' || $action === 'edit') {

    // For edit, load existing record.
    $existing = null;
    if ($action === 'edit' && $id > 0) {
        $existing = $DB->get_record('acmls_learning_resource', ['id' => $id, 'courseid' => $courseid]);
        if (!$existing) {
            redirect($base_url, get_string('resource_not_found', 'block_attendanceleaderboard'), null, \core\output\notification::NOTIFY_ERROR);
        }
    }

    $form_url = new moodle_url($base_url, ['action' => $action, 'id' => $id]);
    $mform    = new resource_form($form_url->out(false));

    // Populate form with existing data for edit.
    if ($existing) {
        $form_data = [
            'id'               => $existing->id,
            'courseid'         => $existing->courseid,
            'title'            => $existing->title,
            'resource_type'    => $existing->resource_type,
            'difficulty_level' => (int) $existing->difficulty_level,
            'topic_tags'       => implode(', ', json_decode($existing->topic_tags ?? '[]', true) ?: []),
            'learning_styles'  => json_decode($existing->learning_styles ?? '[]', true) ?: ['reading'],
            'is_active'        => (int) $existing->is_active,
        ];
        $mform->set_data($form_data);
    } else {
        $mform->set_data(['courseid' => $courseid]);
    }

    if ($mform->is_cancelled()) {
        redirect($base_url);
    }

    if ($form_data_submitted = $mform->get_data()) {
        // Parse topic_tags from comma-separated string to JSON array.
        $tags_raw = trim($form_data_submitted->topic_tags ?? '');
        $tags = [];
        if ($tags_raw !== '') {
            $tags = array_values(array_filter(array_map('trim', explode(',', $tags_raw))));
        }

        // learning_styles comes as array from multi-select.
        $styles = (array) ($form_data_submitted->learning_styles ?? ['reading']);

        $metadata = [
            'title'            => trim($form_data_submitted->title),
            'resource_type'    => $form_data_submitted->resource_type,
            'difficulty_level' => (int) $form_data_submitted->difficulty_level,
            'topic_tags'       => $tags,
            'learning_styles'  => $styles,
            'is_active'        => (int) $form_data_submitted->is_active,
        ];

        if ($action === 'edit' && $id > 0) {
            $repo->update_resource($id, $metadata);
            redirect($base_url, get_string('resource_updated', 'block_attendanceleaderboard'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            // For add, we need a cmid. Use 0 as placeholder (manual entry without a Moodle cm).
            $metadata['courseid'] = $courseid;
            $cmid_placeholder = 0;

            // Check if a cmid was provided (optional advanced use).
            $cmid_placeholder = optional_param('cmid', 0, PARAM_INT);

            $repo->add_resource($cmid_placeholder, $metadata);
            redirect($base_url, get_string('resource_added', 'block_attendanceleaderboard'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }

    // Render form.
    $heading_key = ($action === 'edit') ? 'editresource' : 'addresource';
    $PAGE->navbar->add(get_string($heading_key, 'block_attendanceleaderboard'));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($heading_key, 'block_attendanceleaderboard'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// -------------------------------------------------------------------------
// Action: LIST (default)
// -------------------------------------------------------------------------

// Fetch all resources for this course (active and inactive).
$resources_raw = $DB->get_records(
    'acmls_learning_resource',
    ['courseid' => $courseid],
    'is_active DESC, title ASC'
);

// Difficulty labels and Bootstrap classes.
$difficulty_labels = [
    1 => get_string('difficulty_basic', 'block_attendanceleaderboard'),
    2 => get_string('difficulty_intermediate', 'block_attendanceleaderboard'),
    3 => get_string('difficulty_advanced', 'block_attendanceleaderboard'),
];
$difficulty_classes = [
    1 => 'success',
    2 => 'warning',
    3 => 'danger',
];

// Resource type labels.
$type_label_keys = [
    'resource'  => 'resource_type_resource',
    'page'      => 'resource_type_page',
    'book'      => 'resource_type_book',
    'video'     => 'resource_type_video',
    'audio'     => 'resource_type_audio',
    'document'  => 'resource_type_document',
    'pdf'       => 'resource_type_pdf',
    'quiz'      => 'resource_type_quiz',
    'assign'    => 'resource_type_assign',
    'workshop'  => 'resource_type_workshop',
    'forum'     => 'resource_type_forum',
    'chat'      => 'resource_type_chat',
];

// Build template data for each resource.
$resources_data = [];
foreach ($resources_raw as $r) {
    $diff_level = (int) $r->difficulty_level;
    $type_key   = $r->resource_type;

    $type_label = isset($type_label_keys[$type_key])
        ? get_string($type_label_keys[$type_key], 'block_attendanceleaderboard')
        : ucfirst($type_key);

    $edit_url = new moodle_url($base_url, [
        'action'  => 'edit',
        'id'      => $r->id,
        'sesskey' => sesskey(),
    ]);

    $delete_url = new moodle_url($base_url, [
        'action'  => 'delete',
        'id'      => $r->id,
        'sesskey' => sesskey(),
    ]);

    $resources_data[] = [
        'id'                  => (int) $r->id,
        'title'               => format_string($r->title),
        'resource_type'       => $r->resource_type,
        'resource_type_label' => $type_label,
        'difficulty_level'    => $diff_level,
        'difficulty_label'    => $difficulty_labels[$diff_level] ?? (string) $diff_level,
        'difficulty_class'    => $difficulty_classes[$diff_level] ?? 'secondary',
        'access_count'        => (int) $r->access_count,
        'avg_rating'          => ($r->avg_rating !== null) ? number_format((float) $r->avg_rating, 2) : '—',
        'effectiveness_score' => ($r->effectiveness_score !== null) ? number_format((float) $r->effectiveness_score, 2) : '—',
        'is_active'           => (bool) $r->is_active,
        'is_active_label'     => $r->is_active
            ? get_string('resource_active', 'block_attendanceleaderboard')
            : get_string('resource_inactive', 'block_attendanceleaderboard'),
        'is_active_class'     => $r->is_active ? 'success' : 'secondary',
        'edit_url'            => $edit_url->out(false),
        'delete_url'          => $delete_url->out(false),
        'delete_confirm'      => get_string('resource_delete_confirm', 'block_attendanceleaderboard', format_string($r->title)),
    ];
}

$add_url = new moodle_url($base_url, ['action' => 'add']);

$template_data = [
    'courseid'         => $courseid,
    'manage_url'       => $base_url->out(false),
    'has_resources'    => !empty($resources_data),
    'resources'        => array_values($resources_data),
    'add_url'          => $add_url->out(false),
    'sesskey'          => sesskey(),
    'no_resources_msg' => get_string('no_resources', 'block_attendanceleaderboard'),
];

// -------------------------------------------------------------------------
// Render.
// -------------------------------------------------------------------------

/** @var \block_attendanceleaderboard\output\renderer $renderer */
$renderer = $PAGE->get_renderer('block_attendanceleaderboard');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_resources_title', 'block_attendanceleaderboard'));
echo $renderer->render_manage_resources($template_data);
echo $OUTPUT->footer();
