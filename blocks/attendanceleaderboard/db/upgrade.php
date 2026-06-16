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
 * Upgrade script for block_attendanceleaderboard (ACMLS).
 *
 * This file handles database schema migrations when upgrading from
 * older versions of the plugin. New installations use install.xml
 * directly and do not execute this file.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for block_attendanceleaderboard.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_block_attendanceleaderboard_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    // ----------------------------------------------------------------
    // Upgrade to 2024010100 — ACMLS initial schema migration.
    //
    // Installs all 7 ACMLS tables for sites that were running the
    // legacy block_attendanceleaderboard_scores table and are now
    // upgrading to the full ACMLS feature set.
    // ----------------------------------------------------------------
    if ($oldversion < 2024010100) {

        // --- acmls_learner_profile -----------------------------------
        $table = new xmldb_table('acmls_learner_profile');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('cognitive_level', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('motivation_level', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('performance_category', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('learning_style', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'unknown');
            $table->add_field('behavioral_score', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('engagement_score', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('profile_version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('last_updated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('uq_user_course', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
            $table->add_index('idx_performance', XMLDB_INDEX_NOTUNIQUE, ['performance_category']);
            $table->add_index('idx_motivation', XMLDB_INDEX_NOTUNIQUE, ['motivation_level']);
            $dbman->create_table($table);
        }

        // --- acmls_activity_log --------------------------------------
        $table = new xmldb_table('acmls_activity_log');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('event_type', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('objectid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('action', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('duration_seconds', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('result_value', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
            $table->add_field('context_data', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sent_to_profiler', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_user_course', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('idx_event_type', XMLDB_INDEX_NOTUNIQUE, ['event_type']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('idx_sent', XMLDB_INDEX_NOTUNIQUE, ['sent_to_profiler']);
            $dbman->create_table($table);
        }

        // --- acmls_motivation_sentence -------------------------------
        $table = new xmldb_table('acmls_motivation_sentence');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('category', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('performance_target', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
            $table->add_field('motivation_target', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
            $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('language', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'id');
            $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'llm');
            $table->add_field('llm_model', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('learner_context', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('is_active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('usage_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_category', XMLDB_INDEX_NOTUNIQUE, ['category']);
            $table->add_index('idx_target', XMLDB_INDEX_NOTUNIQUE, ['performance_target', 'motivation_target']);
            $table->add_index('idx_active', XMLDB_INDEX_NOTUNIQUE, ['is_active']);
            $dbman->create_table($table);
        }

        // --- acmls_learning_resource ---------------------------------
        $table = new xmldb_table('acmls_learning_resource');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('resource_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('difficulty_level', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('topic_tags', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('learning_styles', XMLDB_TYPE_CHAR, '200', null, null, null, null);
            $table->add_field('access_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('avg_rating', XMLDB_TYPE_NUMBER, '3, 2', null, null, null, null);
            $table->add_field('effectiveness_score', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null);
            $table->add_field('is_active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('uq_cmid', XMLDB_INDEX_UNIQUE, ['cmid']);
            $table->add_index('idx_difficulty', XMLDB_INDEX_NOTUNIQUE, ['difficulty_level']);
            $table->add_index('idx_course', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('idx_active', XMLDB_INDEX_NOTUNIQUE, ['is_active']);
            $dbman->create_table($table);
        }

        // --- acmls_learner_record ------------------------------------
        $table = new xmldb_table('acmls_learner_record');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('record_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('source_component', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('data_payload', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('profile_version', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_user_course', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('idx_record_type', XMLDB_INDEX_NOTUNIQUE, ['record_type']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('idx_source', XMLDB_INDEX_NOTUNIQUE, ['source_component']);
            $dbman->create_table($table);
        }

        // --- acmls_coach_decision ------------------------------------
        $table = new xmldb_table('acmls_coach_decision');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('decision_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('input_profile', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('recommended_resources', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('motivation_category', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('reasoning', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('rules_triggered', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('learner_response', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('response_time', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_user_course', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('idx_decision_type', XMLDB_INDEX_NOTUNIQUE, ['decision_type']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $dbman->create_table($table);
        }

        // --- acmls_leaderboard ---------------------------------------
        $table = new xmldb_table('acmls_leaderboard');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('scope', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'course');
            $table->add_field('attendance_score', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('engagement_score', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completion_score', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('total_score', XMLDB_TYPE_NUMBER, '7, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('current_rank', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('previous_rank', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('rank_change', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('points_to_next', XMLDB_TYPE_NUMBER, '7, 2', null, null, null, null);
            $table->add_field('display_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('last_updated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('uq_user_course_scope', XMLDB_INDEX_UNIQUE, ['userid', 'courseid', 'scope']);
            $table->add_index('idx_rank', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'scope', 'current_rank']);
            $table->add_index('idx_score', XMLDB_INDEX_NOTUNIQUE, ['total_score']);
            $table->add_index('idx_last_updated', XMLDB_INDEX_NOTUNIQUE, ['last_updated']);
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2024010100, 'attendanceleaderboard');
    }

    // ----------------------------------------------------------------
    // Upgrade to 2025050100 — Add acmls_config_audit_log table.
    //
    // Adds the configuration audit log table that records every
    // admin configuration change (who, when, what changed).
    // Satisfies Requirement 13.3 / Task 13.6.
    // ----------------------------------------------------------------
    if ($oldversion < 2025050100) {

        $table = new xmldb_table('acmls_config_audit_log');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('setting_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('old_value', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('new_value', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('context', XMLDB_TYPE_CHAR, '255', null, null, null, '');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('idx_setting_name', XMLDB_INDEX_NOTUNIQUE, ['setting_name']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2025050100, 'attendanceleaderboard');
    }

    // ----------------------------------------------------------------
    // Upgrade to 2025060100 — Add acmls_data_access_log table.
    //
    // Adds the data access log table that records every access to
    // Learner_Record data: accessor identity, timestamp, and what
    // data was accessed.
    // Satisfies Requirement 15.4 / Task 14.5.
    // ----------------------------------------------------------------
    if ($oldversion < 2025060100) {

        $table = new xmldb_table('acmls_data_access_log');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('accessor_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('target_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('access_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('record_type_filter', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('source_component_filter', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('records_returned', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_accessor', XMLDB_INDEX_NOTUNIQUE, ['accessor_userid']);
            $table->add_index('idx_target', XMLDB_INDEX_NOTUNIQUE, ['target_userid']);
            $table->add_index('idx_courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('idx_access_type', XMLDB_INDEX_NOTUNIQUE, ['access_type']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2025060100, 'attendanceleaderboard');
    }

    // ----------------------------------------------------------------
    // Upgrade to 2025070100 — Add acmls_learner_consent table.
    //
    // Adds the learner consent table that stores each learner's
    // explicit consent decision for sending data to external LLM
    // services. Satisfies Requirement 15.5 / Task 14.6.
    // ----------------------------------------------------------------
    if ($oldversion < 2025070100) {

        $table = new xmldb_table('acmls_learner_consent');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('consent_given', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('consent_timestamp', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('uq_user_course', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
            $table->add_index('idx_consent_given', XMLDB_INDEX_NOTUNIQUE, ['consent_given']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2025070100, 'attendanceleaderboard');
    }

    // ----------------------------------------------------------------
    // Upgrade to 2026060800 - add structured motivation feedback and
    // migrate the active provider to Gemini.
    // ----------------------------------------------------------------
    if ($oldversion < 2026060800) {

        $table = new xmldb_table('acmls_motivation_feedback');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sentenceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('category', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, '');
            $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
            $table->add_field('feeling_key', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('feeling_score', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('reflection_note', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('message_content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_user_course', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('idx_sentenceid', XMLDB_INDEX_NOTUNIQUE, ['sentenceid']);
            $table->add_index('idx_feeling_key', XMLDB_INDEX_NOTUNIQUE, ['feeling_key']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $dbman->create_table($table);
        }

        set_config('llm_provider', 'gemini', 'block_attendanceleaderboard');

        upgrade_block_savepoint(true, 2026060800, 'attendanceleaderboard');
    }

    // ----------------------------------------------------------------
    // Upgrade to 2026060801 - recover missing core tables for sites
    // that already store a current plugin version but never completed
    // the original ACMLS schema installation.
    // ----------------------------------------------------------------
    if ($oldversion < 2026060801) {

        $installxml = $CFG->dirroot . '/blocks/attendanceleaderboard/db/install.xml';
        $requiredtables = [
            'acmls_learner_profile',
            'acmls_activity_log',
            'acmls_motivation_sentence',
            'acmls_learning_resource',
            'acmls_learner_record',
            'acmls_coach_decision',
            'acmls_leaderboard',
            'acmls_config_audit_log',
            'acmls_data_access_log',
            'acmls_learner_consent',
            'acmls_motivation_feedback',
        ];

        foreach ($requiredtables as $tablename) {
            $table = new xmldb_table($tablename);
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file($installxml, $tablename);
            }
        }

        set_config('llm_provider', 'gemini', 'block_attendanceleaderboard');

        upgrade_block_savepoint(true, 2026060801, 'attendanceleaderboard');
    }

    return true;
}
