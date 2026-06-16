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
 * Admin settings page for block_attendanceleaderboard (ACMLS).
 *
 * Provides configuration for:
 *  - Gemini LLM provider, API key, and model name
 *  - Leaderboard scoring weights (attendance, engagement, completion)
 *  - Motivational thresholds
 *  - Coach / Profiling parameters (learning rate alpha, performance decline threshold)
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/attendanceleaderboard/classes/admin/settings_validator.php');
require_once($CFG->dirroot . '/blocks/attendanceleaderboard/classes/admin/admin_setting_with_validation.php');
require_once($CFG->dirroot . '/blocks/attendanceleaderboard/classes/admin/admin_setting_leaderboard_weights.php');

use block_attendanceleaderboard\admin\admin_setting_leaderboard_weights;
use block_attendanceleaderboard\admin\admin_setting_with_validation;
use block_attendanceleaderboard\admin\settings_validator;

if ($ADMIN->fulltree) {
    $dashboardurl = new moodle_url('/blocks/attendanceleaderboard/analytics_dashboard.php');
    $dashboardlink = html_writer::link(
        $dashboardurl,
        get_string('viewdashboard', 'block_attendanceleaderboard'),
        ['class' => 'btn btn-primary btn-sm']
    );

    $settings->add(new admin_setting_heading(
        'block_attendanceleaderboard/heading_analytics',
        get_string('analytics_dashboard', 'block_attendanceleaderboard'),
        $dashboardlink
    ));

    $auditlogurl = new moodle_url('/blocks/attendanceleaderboard/config_audit_log.php');
    $auditloglink = html_writer::link(
        $auditlogurl,
        get_string('audit_view_full_log', 'block_attendanceleaderboard'),
        ['class' => 'btn btn-outline-secondary btn-sm']
    );

    $settings->add(new admin_setting_heading(
        'block_attendanceleaderboard/heading_audit_log',
        get_string('audit_log_title', 'block_attendanceleaderboard'),
        get_string('audit_log_settings_desc', 'block_attendanceleaderboard') . ' ' . $auditloglink
    ));

    $settings->add(new admin_setting_heading(
        'block_attendanceleaderboard/heading_manage_resources',
        get_string('manage_resources_link', 'block_attendanceleaderboard'),
        get_string('manage_resources_link_desc', 'block_attendanceleaderboard')
    ));

    $settings->add(new admin_setting_heading(
        'block_attendanceleaderboard/heading_learner_profiles',
        get_string('learnerprofilemonitoring', 'block_attendanceleaderboard'),
        get_string('viewlearnerprofiles_desc', 'block_attendanceleaderboard')
    ));

    $settings->add(new admin_setting_heading(
        'block_attendanceleaderboard/heading_llm',
        get_string('settings_llm', 'block_attendanceleaderboard'),
        get_string('settings_llm_desc', 'block_attendanceleaderboard')
    ));

    $settings->add(new admin_setting_configselect(
        'block_attendanceleaderboard/llm_provider',
        get_string('settings_llm_provider', 'block_attendanceleaderboard'),
        get_string('settings_llm_provider_desc', 'block_attendanceleaderboard'),
        'gemini',
        [
            'gemini' => get_string('settings_llm_provider_gemini', 'block_attendanceleaderboard'),
        ]
    ));

    $settings->add(new class(
        'block_attendanceleaderboard/gemini_apikey',
        get_string('settings_gemini_apikey', 'block_attendanceleaderboard'),
        get_string('settings_gemini_apikey_desc', 'block_attendanceleaderboard'),
        ''
    ) extends \admin_setting_configpasswordunmask {
        public function write_setting($data) {
            $provider = get_config('block_attendanceleaderboard', 'llm_provider');
            if ($provider === 'gemini') {
                $result = \block_attendanceleaderboard\admin\settings_validator::validate_api_key($data);
                if ($result !== true) {
                    return (string) $result;
                }
            }
            return parent::write_setting($data);
        }
    });

    $settings->add(new admin_setting_with_validation(
        'block_attendanceleaderboard/gemini_model',
        get_string('settings_gemini_model', 'block_attendanceleaderboard'),
        get_string('settings_gemini_model_desc', 'block_attendanceleaderboard'),
        'gemini-3.5-flash',
        PARAM_TEXT,
        null
    ));

    $settings->add(new admin_setting_heading(
        'block_attendanceleaderboard/heading_leaderboard',
        get_string('settings_leaderboard', 'block_attendanceleaderboard'),
        get_string('settings_leaderboard_desc', 'block_attendanceleaderboard')
    ));

    $settings->add(new admin_setting_leaderboard_weights(
        'block_attendanceleaderboard/weight_attendance',
        get_string('settings_weight_attendance', 'block_attendanceleaderboard'),
        get_string('settings_weight_attendance_desc', 'block_attendanceleaderboard'),
        '0.4',
        'weight_engagement',
        'weight_completion'
    ));

    $settings->add(new admin_setting_leaderboard_weights(
        'block_attendanceleaderboard/weight_engagement',
        get_string('settings_weight_engagement', 'block_attendanceleaderboard'),
        get_string('settings_weight_engagement_desc', 'block_attendanceleaderboard'),
        '0.4',
        'weight_attendance',
        'weight_completion'
    ));

    $settings->add(new admin_setting_leaderboard_weights(
        'block_attendanceleaderboard/weight_completion',
        get_string('settings_weight_completion', 'block_attendanceleaderboard'),
        get_string('settings_weight_completion_desc', 'block_attendanceleaderboard'),
        '0.2',
        'weight_attendance',
        'weight_engagement'
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_attendanceleaderboard/leaderboard_privacy',
        get_string('settings_leaderboard_privacy', 'block_attendanceleaderboard'),
        get_string('settings_leaderboard_privacy_desc', 'block_attendanceleaderboard'),
        0
    ));

    $settings->add(new admin_setting_configselect(
        'block_attendanceleaderboard/leaderboard_scope',
        get_string('settings_leaderboard_scope', 'block_attendanceleaderboard'),
        get_string('settings_leaderboard_scope_desc', 'block_attendanceleaderboard'),
        'course',
        [
            'course' => get_string('settings_scope_course', 'block_attendanceleaderboard'),
            'program' => get_string('settings_scope_program', 'block_attendanceleaderboard'),
            'institution' => get_string('settings_scope_institution', 'block_attendanceleaderboard'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'block_attendanceleaderboard/leaderboard_update_interval',
        get_string('settings_leaderboard_update_interval', 'block_attendanceleaderboard'),
        get_string('settings_leaderboard_update_interval_desc', 'block_attendanceleaderboard'),
        '15',
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'block_attendanceleaderboard/heading_motivation',
        get_string('settings_motivation', 'block_attendanceleaderboard'),
        get_string('settings_motivation_desc', 'block_attendanceleaderboard')
    ));

    $settings->add(new admin_setting_with_validation(
        'block_attendanceleaderboard/motivation_threshold',
        get_string('settings_motivation_threshold', 'block_attendanceleaderboard'),
        get_string('settings_motivation_threshold_desc', 'block_attendanceleaderboard'),
        '30',
        PARAM_INT,
        [settings_validator::class, 'validate_motivation_threshold']
    ));

    $settings->add(new admin_setting_configtext(
        'block_attendanceleaderboard/motivation_threshold_days',
        get_string('settings_motivation_threshold_days', 'block_attendanceleaderboard'),
        get_string('settings_motivation_threshold_days_desc', 'block_attendanceleaderboard'),
        '3',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_attendanceleaderboard/motivation_capacity_warning',
        get_string('settings_motivation_capacity_warning', 'block_attendanceleaderboard'),
        get_string('settings_motivation_capacity_warning_desc', 'block_attendanceleaderboard'),
        '80',
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'block_attendanceleaderboard/heading_coach',
        get_string('settings_coach', 'block_attendanceleaderboard'),
        get_string('settings_coach_desc', 'block_attendanceleaderboard')
    ));

    $settings->add(new admin_setting_with_validation(
        'block_attendanceleaderboard/profiling_alpha',
        get_string('settings_learning_rate', 'block_attendanceleaderboard'),
        get_string('settings_learning_rate_desc', 'block_attendanceleaderboard'),
        '0.7',
        PARAM_FLOAT,
        [settings_validator::class, 'validate_learning_rate']
    ));

    $settings->add(new admin_setting_heading(
        'block_attendanceleaderboard/heading_evaluation',
        get_string('settings_evaluation', 'block_attendanceleaderboard'),
        get_string('settings_evaluation_desc', 'block_attendanceleaderboard')
    ));

    $settings->add(new admin_setting_with_validation(
        'block_attendanceleaderboard/performance_decline_threshold',
        get_string('settings_performance_decline_threshold', 'block_attendanceleaderboard'),
        get_string('settings_performance_decline_threshold_desc', 'block_attendanceleaderboard'),
        '20',
        PARAM_INT,
        [settings_validator::class, 'validate_decline_threshold']
    ));

    $settings->add(new admin_setting_configtext(
        'block_attendanceleaderboard/coach_response_timeout',
        get_string('settings_coach_response_timeout', 'block_attendanceleaderboard'),
        get_string('settings_coach_response_timeout_desc', 'block_attendanceleaderboard'),
        '10',
        PARAM_INT
    ));
}
