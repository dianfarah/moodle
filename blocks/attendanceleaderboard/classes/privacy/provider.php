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
 * Privacy Subsystem implementation for block_attendanceleaderboard (ACMLS).
 *
 * Implements the Moodle Privacy API to declare all data stored by the plugin,
 * and to support export and deletion of user data on request.
 *
 * Requirements addressed:
 * - Req 15.1: Moodle Privacy API compliance.
 * - Req 15.2: Data export for learner data requests.
 * - Req 15.3: Data deletion for learner data requests.
 * - Req 15.4: External service (Gemini API) declaration.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for block_attendanceleaderboard.
 *
 * Declares all personal data stored across the ACMLS database tables
 * and the external Gemini API, and implements export and deletion of that data.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    // -------------------------------------------------------------------------
    // Metadata declaration — Req 15.1, Req 15.4
    // -------------------------------------------------------------------------

    /**
     * Returns metadata about all personal data stored by this plugin.
     *
     * Declares:
     * - Six database tables that store user-keyed data.
     * - acmls_motivation_sentence as a database table (learner_context field
     *   may contain anonymised learner data used to generate motivational content).
     * - The Gemini external API as an external location.
     *
     * @param  collection $collection The metadata collection to populate.
     * @return collection             The populated collection.
     */
    public static function get_metadata(collection $collection): collection {

        // --- acmls_learner_profile -------------------------------------------
        $collection->add_database_table(
            'acmls_learner_profile',
            [
                'userid'               => 'privacy:metadata:acmls_learner_profile:userid',
                'courseid'             => 'privacy:metadata:acmls_learner_profile:courseid',
                'cognitive_level'      => 'privacy:metadata:acmls_learner_profile:cognitive_level',
                'motivation_level'     => 'privacy:metadata:acmls_learner_profile:motivation_level',
                'performance_category' => 'privacy:metadata:acmls_learner_profile:performance_category',
                'learning_style'       => 'privacy:metadata:acmls_learner_profile:learning_style',
                'behavioral_score'     => 'privacy:metadata:acmls_learner_profile:behavioral_score',
                'engagement_score'     => 'privacy:metadata:acmls_learner_profile:engagement_score',
                'profile_version'      => 'privacy:metadata:acmls_learner_profile:profile_version',
                'last_updated'         => 'privacy:metadata:acmls_learner_profile:last_updated',
            ],
            'privacy:metadata:acmls_learner_profile'
        );

        // --- acmls_activity_log ----------------------------------------------
        $collection->add_database_table(
            'acmls_activity_log',
            [
                'userid'           => 'privacy:metadata:acmls_activity_log:userid',
                'courseid'         => 'privacy:metadata:acmls_activity_log:courseid',
                'event_type'       => 'privacy:metadata:acmls_activity_log:event_type',
                'component'        => 'privacy:metadata:acmls_activity_log:component',
                'objectid'         => 'privacy:metadata:acmls_activity_log:objectid',
                'action'           => 'privacy:metadata:acmls_activity_log:action',
                'duration_seconds' => 'privacy:metadata:acmls_activity_log:duration_seconds',
                'result_value'     => 'privacy:metadata:acmls_activity_log:result_value',
                'context_data'     => 'privacy:metadata:acmls_activity_log:context_data',
                'timecreated'      => 'privacy:metadata:acmls_activity_log:timecreated',
            ],
            'privacy:metadata:acmls_activity_log'
        );

        // --- acmls_motivation_sentence ---------------------------------------
        // Does not store userid directly, but the learner_context field may
        // contain anonymised learner data used to generate motivational content.
        $collection->add_database_table(
            'acmls_motivation_sentence',
            [
                'learner_context' => 'privacy:metadata:acmls_motivation_sentence:learner_context',
                'content'         => 'privacy:metadata:acmls_motivation_sentence:content',
                'category'        => 'privacy:metadata:acmls_motivation_sentence:category',
                'timecreated'     => 'privacy:metadata:acmls_motivation_sentence:timecreated',
            ],
            'privacy:metadata:acmls_motivation_sentence'
        );

        // --- acmls_learner_record --------------------------------------------
        $collection->add_database_table(
            'acmls_learner_record',
            [
                'userid'           => 'privacy:metadata:acmls_learner_record:userid',
                'courseid'         => 'privacy:metadata:acmls_learner_record:courseid',
                'record_type'      => 'privacy:metadata:acmls_learner_record:record_type',
                'source_component' => 'privacy:metadata:acmls_learner_record:source_component',
                'data_payload'     => 'privacy:metadata:acmls_learner_record:data_payload',
                'profile_version'  => 'privacy:metadata:acmls_learner_record:profile_version',
                'timecreated'      => 'privacy:metadata:acmls_learner_record:timecreated',
            ],
            'privacy:metadata:acmls_learner_record'
        );

        // --- acmls_coach_decision --------------------------------------------
        $collection->add_database_table(
            'acmls_coach_decision',
            [
                'userid'                 => 'privacy:metadata:acmls_coach_decision:userid',
                'courseid'               => 'privacy:metadata:acmls_coach_decision:courseid',
                'decision_type'          => 'privacy:metadata:acmls_coach_decision:decision_type',
                'input_profile'          => 'privacy:metadata:acmls_coach_decision:input_profile',
                'recommended_resources'  => 'privacy:metadata:acmls_coach_decision:recommended_resources',
                'motivation_category'    => 'privacy:metadata:acmls_coach_decision:motivation_category',
                'reasoning'              => 'privacy:metadata:acmls_coach_decision:reasoning',
                'rules_triggered'        => 'privacy:metadata:acmls_coach_decision:rules_triggered',
                'learner_response'       => 'privacy:metadata:acmls_coach_decision:learner_response',
                'response_time'          => 'privacy:metadata:acmls_coach_decision:response_time',
                'timecreated'            => 'privacy:metadata:acmls_coach_decision:timecreated',
            ],
            'privacy:metadata:acmls_coach_decision'
        );

        // --- acmls_leaderboard -----------------------------------------------
        $collection->add_database_table(
            'acmls_leaderboard',
            [
                'userid'         => 'privacy:metadata:acmls_leaderboard:userid',
                'courseid'       => 'privacy:metadata:acmls_leaderboard:courseid',
                'scope'          => 'privacy:metadata:acmls_leaderboard:scope',
                'attendance_score'  => 'privacy:metadata:acmls_leaderboard:attendance_score',
                'engagement_score'  => 'privacy:metadata:acmls_leaderboard:engagement_score',
                'completion_score'  => 'privacy:metadata:acmls_leaderboard:completion_score',
                'total_score'    => 'privacy:metadata:acmls_leaderboard:total_score',
                'current_rank'   => 'privacy:metadata:acmls_leaderboard:current_rank',
                'previous_rank'  => 'privacy:metadata:acmls_leaderboard:previous_rank',
                'rank_change'    => 'privacy:metadata:acmls_leaderboard:rank_change',
                'points_to_next' => 'privacy:metadata:acmls_leaderboard:points_to_next',
                'display_name'   => 'privacy:metadata:acmls_leaderboard:display_name',
                'last_updated'   => 'privacy:metadata:acmls_leaderboard:last_updated',
            ],
            'privacy:metadata:acmls_leaderboard'
        );

        // --- acmls_config_audit_log ------------------------------------------
        $collection->add_database_table(
            'acmls_config_audit_log',
            [
                'userid'       => 'privacy:metadata:acmls_config_audit_log:userid',
                'setting_name' => 'privacy:metadata:acmls_config_audit_log:setting_name',
                'old_value'    => 'privacy:metadata:acmls_config_audit_log:old_value',
                'new_value'    => 'privacy:metadata:acmls_config_audit_log:new_value',
                'context'      => 'privacy:metadata:acmls_config_audit_log:context',
                'timecreated'  => 'privacy:metadata:acmls_config_audit_log:timecreated',
            ],
            'privacy:metadata:acmls_config_audit_log'
        );

        // --- acmls_data_access_log -------------------------------------------
        // Req 15.4: Records every access to Learner_Record data, including
        // the accessor's identity, timestamp, and what data was accessed.
        $collection->add_database_table(
            'acmls_data_access_log',
            [
                'accessor_userid'          => 'privacy:metadata:acmls_data_access_log:accessor_userid',
                'target_userid'            => 'privacy:metadata:acmls_data_access_log:target_userid',
                'courseid'                 => 'privacy:metadata:acmls_data_access_log:courseid',
                'access_type'              => 'privacy:metadata:acmls_data_access_log:access_type',
                'record_type_filter'       => 'privacy:metadata:acmls_data_access_log:record_type_filter',
                'source_component_filter'  => 'privacy:metadata:acmls_data_access_log:source_component_filter',
                'records_returned'         => 'privacy:metadata:acmls_data_access_log:records_returned',
                'timecreated'              => 'privacy:metadata:acmls_data_access_log:timecreated',
            ],
            'privacy:metadata:acmls_data_access_log'
        );

        // --- acmls_motivation_feedback ---------------------------------------
        $collection->add_database_table(
            'acmls_motivation_feedback',
            [
                'userid'          => 'privacy:metadata:acmls_motivation_feedback:userid',
                'courseid'        => 'privacy:metadata:acmls_motivation_feedback:courseid',
                'sentenceid'      => 'privacy:metadata:acmls_motivation_feedback:sentenceid',
                'category'        => 'privacy:metadata:acmls_motivation_feedback:category',
                'source'          => 'privacy:metadata:acmls_motivation_feedback:source',
                'feeling_key'     => 'privacy:metadata:acmls_motivation_feedback:feeling_key',
                'feeling_score'   => 'privacy:metadata:acmls_motivation_feedback:feeling_score',
                'reflection_note' => 'privacy:metadata:acmls_motivation_feedback:reflection_note',
                'message_content' => 'privacy:metadata:acmls_motivation_feedback:message_content',
                'timecreated'     => 'privacy:metadata:acmls_motivation_feedback:timecreated',
            ],
            'privacy:metadata:acmls_motivation_feedback'
        );

        // --- External service: Gemini API ------------------------------------
        $collection->add_external_location_link(
            'gemini_api',
            [
                'learner_context' => 'privacy:metadata:gemini_api:learner_context',
            ],
            'privacy:metadata:gemini_api'
        );

        // --- acmls_learner_consent -------------------------------------------
        // Req 15.5: Stores each learner's explicit consent decision for sending
        // anonymised data to external LLM services (Task 14.6).
        $collection->add_database_table(
            'acmls_learner_consent',
            [
                'userid'            => 'privacy:metadata:acmls_learner_consent:userid',
                'courseid'          => 'privacy:metadata:acmls_learner_consent:courseid',
                'consent_given'     => 'privacy:metadata:acmls_learner_consent:consent_given',
                'consent_timestamp' => 'privacy:metadata:acmls_learner_consent:consent_timestamp',
                'timecreated'       => 'privacy:metadata:acmls_learner_consent:timecreated',
                'timemodified'      => 'privacy:metadata:acmls_learner_consent:timemodified',
            ],
            'privacy:metadata:acmls_learner_consent'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for the specified user.
     *
     * Queries all ACMLS tables that store a userid column to find the course
     * contexts where the user has data. For acmls_config_audit_log (admin-level
     * data) the system context is added if any records exist for the user.
     *
     * @param  int         $userid The Moodle user ID to search for.
     * @return contextlist         The list of contexts containing user data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        // Tables that store courseid alongside userid — map to course contexts.
        $course_tables = [
            'acmls_learner_profile',
            'acmls_activity_log',
            'acmls_learner_record',
            'acmls_coach_decision',
            'acmls_leaderboard',
            'acmls_motivation_feedback',
            'acmls_learner_consent',
        ];

        foreach ($course_tables as $table) {
            $sql = "SELECT ctx.id
                      FROM {context} ctx
                      JOIN {{$table}} t ON t.courseid = ctx.instanceid
                     WHERE t.userid = :userid
                       AND ctx.contextlevel = :contextlevel";
            $contextlist->add_from_sql($sql, [
                'userid'       => $userid,
                'contextlevel' => CONTEXT_COURSE,
            ]);
        }

        // acmls_config_audit_log — admin-level, use system context.
        if ($DB->record_exists('acmls_config_audit_log', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }

        // acmls_data_access_log — user appears as accessor_userid or target_userid.
        // Map to course contexts via courseid.
        $sql_access_log = "SELECT ctx.id
                             FROM {context} ctx
                             JOIN {acmls_data_access_log} dal ON dal.courseid = ctx.instanceid
                            WHERE (dal.accessor_userid = :accessor_userid OR dal.target_userid = :target_userid)
                              AND ctx.contextlevel = :contextlevel";
        $contextlist->add_from_sql($sql_access_log, [
            'accessor_userid' => $userid,
            'target_userid'   => $userid,
            'contextlevel'    => CONTEXT_COURSE,
        ]);

        return $contextlist;
    }

    // -------------------------------------------------------------------------
    // Userlist discovery — core_userlist_provider
    // -------------------------------------------------------------------------

    /**
     * Get the list of users who have data within a specific context.
     *
     * For course contexts, queries all course-scoped ACMLS tables to collect
     * the distinct set of userids that have records in that course.
     * For the system context, queries acmls_config_audit_log.
     *
     * @param  userlist $userlist The userlist to populate with users who have data.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = (int) $context->instanceid;
            $params   = ['courseid' => $courseid];

            // Tables that store (userid, courseid) pairs.
            $course_tables = [
                'acmls_learner_profile',
                'acmls_activity_log',
                'acmls_learner_record',
                'acmls_coach_decision',
                'acmls_leaderboard',
                'acmls_motivation_feedback',
                'acmls_learner_consent',
            ];

            foreach ($course_tables as $table) {
                $sql = "SELECT userid FROM {{$table}} WHERE courseid = :courseid";
                $userlist->add_from_sql('userid', $sql, $params);
            }

            // acmls_data_access_log — collect both accessor and target users.
            $sql_accessor = "SELECT accessor_userid AS userid FROM {acmls_data_access_log} WHERE courseid = :courseid";
            $userlist->add_from_sql('userid', $sql_accessor, $params);
            $sql_target = "SELECT target_userid AS userid FROM {acmls_data_access_log} WHERE courseid = :courseid";
            $userlist->add_from_sql('userid', $sql_target, $params);

        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            $sql = "SELECT userid FROM {acmls_config_audit_log}";
            $userlist->add_from_sql('userid', $sql, []);
        }
    }

    // -------------------------------------------------------------------------
    // Bulk deletion — core_userlist_provider
    // -------------------------------------------------------------------------

    /**
     * Delete all personal data for multiple users within a single context.
     *
     * Accepts an approved_userlist scoped to one context and deletes every
     * ACMLS record that belongs to any of the approved users in that context.
     *
     * - Course context: deletes from all course-scoped ACMLS tables.
     * - System context: deletes from acmls_config_audit_log.
     *
     * @param  approved_userlist $userlist The approved context and user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$in_sql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

        if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid           = (int) $context->instanceid;
            $params['courseid'] = $courseid;

            $course_tables = [
                'acmls_learner_profile',
                'acmls_activity_log',
                'acmls_learner_record',
                'acmls_coach_decision',
                'acmls_leaderboard',
                'acmls_motivation_feedback',
                'acmls_learner_consent',
            ];

            foreach ($course_tables as $table) {
                $DB->delete_records_select(
                    $table,
                    "userid $in_sql AND courseid = :courseid",
                    $params
                );
            }

            // acmls_data_access_log — delete rows where user is accessor or target.
            // Use separate param sets to avoid duplicate named parameter conflicts.
            [$in_sql_acc, $params_acc] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'acc');
            [$in_sql_tgt, $params_tgt] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'tgt');
            $dal_params = array_merge($params_acc, $params_tgt, ['courseid_dal' => $courseid]);
            $DB->delete_records_select(
                'acmls_data_access_log',
                "(accessor_userid $in_sql_acc OR target_userid $in_sql_tgt) AND courseid = :courseid_dal",
                $dal_params
            );

        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records_select(
                'acmls_config_audit_log',
                "userid $in_sql",
                $params
            );
        }
    }

    // -------------------------------------------------------------------------
    // Data export — Req 15.2
    // -------------------------------------------------------------------------

    /**
     * Export all personal data for the specified user in the approved contexts.
     *
     * For each approved context:
     * - Course context: exports data from all course-scoped ACMLS tables.
     * - System context: exports data from acmls_config_audit_log.
     *
     * Data is written via the Moodle Privacy writer under the plugin name path.
     *
     * @param  approved_contextlist $contextlist The approved contexts to export.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        $pluginpath = [get_string('pluginname', 'block_attendanceleaderboard')];

        foreach ($contextlist->get_contexts() as $context) {

            if ($context->contextlevel == CONTEXT_COURSE) {
                $courseid = (int) $context->instanceid;

                // --- acmls_learner_profile -----------------------------------
                $records = $DB->get_records('acmls_learner_profile', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                if (!empty($records)) {
                    $data = array_map(function($r) {
                        return [
                            'cognitive_level'      => $r->cognitive_level,
                            'motivation_level'     => $r->motivation_level,
                            'performance_category' => $r->performance_category,
                            'learning_style'       => $r->learning_style,
                            'behavioral_score'     => $r->behavioral_score,
                            'engagement_score'     => $r->engagement_score,
                            'profile_version'      => $r->profile_version,
                            'last_updated'         => transform::datetime($r->last_updated),
                        ];
                    }, $records);
                    writer::with_context($context)->export_data(
                        array_merge($pluginpath, ['acmls_learner_profile']),
                        (object) ['records' => array_values($data)]
                    );
                }

                // --- acmls_activity_log --------------------------------------
                $records = $DB->get_records('acmls_activity_log', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                if (!empty($records)) {
                    $data = array_map(function($r) {
                        return [
                            'event_type'       => $r->event_type,
                            'component'        => $r->component,
                            'objectid'         => $r->objectid,
                            'action'           => $r->action,
                            'duration_seconds' => $r->duration_seconds,
                            'result_value'     => $r->result_value,
                            'context_data'     => $r->context_data,
                            'timecreated'      => transform::datetime($r->timecreated),
                        ];
                    }, $records);
                    writer::with_context($context)->export_data(
                        array_merge($pluginpath, ['acmls_activity_log']),
                        (object) ['records' => array_values($data)]
                    );
                }

                // --- acmls_learner_record ------------------------------------
                $records = $DB->get_records('acmls_learner_record', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                if (!empty($records)) {
                    $data = array_map(function($r) {
                        return [
                            'record_type'      => $r->record_type,
                            'source_component' => $r->source_component,
                            'data_payload'     => $r->data_payload,
                            'profile_version'  => $r->profile_version,
                            'timecreated'      => transform::datetime($r->timecreated),
                        ];
                    }, $records);
                    writer::with_context($context)->export_data(
                        array_merge($pluginpath, ['acmls_learner_record']),
                        (object) ['records' => array_values($data)]
                    );
                }

                // --- acmls_coach_decision ------------------------------------
                $records = $DB->get_records('acmls_coach_decision', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                if (!empty($records)) {
                    $data = array_map(function($r) {
                        return [
                            'decision_type'         => $r->decision_type,
                            'input_profile'         => $r->input_profile,
                            'recommended_resources' => $r->recommended_resources,
                            'motivation_category'   => $r->motivation_category,
                            'reasoning'             => $r->reasoning,
                            'rules_triggered'       => $r->rules_triggered,
                            'learner_response'      => $r->learner_response,
                            'response_time'         => $r->response_time
                                ? transform::datetime($r->response_time) : null,
                            'timecreated'           => transform::datetime($r->timecreated),
                        ];
                    }, $records);
                    writer::with_context($context)->export_data(
                        array_merge($pluginpath, ['acmls_coach_decision']),
                        (object) ['records' => array_values($data)]
                    );
                }

                // --- acmls_leaderboard ---------------------------------------
                $records = $DB->get_records('acmls_leaderboard', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                if (!empty($records)) {
                    $data = array_map(function($r) {
                        return [
                            'scope'            => $r->scope,
                            'attendance_score' => $r->attendance_score,
                            'engagement_score' => $r->engagement_score,
                            'completion_score' => $r->completion_score,
                            'total_score'      => $r->total_score,
                            'current_rank'     => $r->current_rank,
                            'previous_rank'    => $r->previous_rank,
                            'rank_change'      => $r->rank_change,
                            'points_to_next'   => $r->points_to_next,
                            'display_name'     => $r->display_name,
                            'last_updated'     => transform::datetime($r->last_updated),
                        ];
                    }, $records);
                    writer::with_context($context)->export_data(
                        array_merge($pluginpath, ['acmls_leaderboard']),
                        (object) ['records' => array_values($data)]
                    );
                }

                // --- acmls_motivation_feedback ------------------------------
                $records = $DB->get_records('acmls_motivation_feedback', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                if (!empty($records)) {
                    $data = array_map(function($r) {
                        return [
                            'sentenceid'      => $r->sentenceid,
                            'category'        => $r->category,
                            'source'          => $r->source,
                            'feeling_key'     => $r->feeling_key,
                            'feeling_score'   => $r->feeling_score,
                            'reflection_note' => $r->reflection_note,
                            'message_content' => $r->message_content,
                            'timecreated'     => transform::datetime($r->timecreated),
                        ];
                    }, $records);
                    writer::with_context($context)->export_data(
                        array_merge($pluginpath, ['acmls_motivation_feedback']),
                        (object) ['records' => array_values($data)]
                    );
                }

                // --- acmls_data_access_log -----------------------------------
                // Export rows where the user is the accessor or the target.
                $sql_access_log = "SELECT *
                                     FROM {acmls_data_access_log}
                                    WHERE (accessor_userid = :accessor_userid OR target_userid = :target_userid)
                                      AND courseid = :courseid
                                    ORDER BY timecreated ASC";
                $access_log_records = $DB->get_records_sql($sql_access_log, [
                    'accessor_userid' => $userid,
                    'target_userid'   => $userid,
                    'courseid'        => $courseid,
                ]);
                if (!empty($access_log_records)) {
                    $data = array_map(function($r) {
                        return [
                            'accessor_userid'         => $r->accessor_userid,
                            'target_userid'           => $r->target_userid,
                            'access_type'             => $r->access_type,
                            'record_type_filter'      => $r->record_type_filter,
                            'source_component_filter' => $r->source_component_filter,
                            'records_returned'        => $r->records_returned,
                            'timecreated'             => transform::datetime($r->timecreated),
                        ];
                    }, $access_log_records);
                    writer::with_context($context)->export_data(
                        array_merge($pluginpath, ['acmls_data_access_log']),
                        (object) ['records' => array_values($data)]
                    );
                }

                // --- acmls_learner_consent -----------------------------------
                // Req 15.5: Export the learner's consent decision record.
                $consent_records = $DB->get_records('acmls_learner_consent', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                if (!empty($consent_records)) {
                    $data = array_map(function($r) {
                        return [
                            'consent_given'     => (bool) $r->consent_given,
                            'consent_timestamp' => $r->consent_timestamp
                                ? transform::datetime($r->consent_timestamp) : null,
                            'timecreated'       => transform::datetime($r->timecreated),
                            'timemodified'      => transform::datetime($r->timemodified),
                        ];
                    }, $consent_records);
                    writer::with_context($context)->export_data(
                        array_merge($pluginpath, ['acmls_learner_consent']),
                        (object) ['records' => array_values($data)]
                    );
                }

            } else if ($context->contextlevel == CONTEXT_SYSTEM) {

                // --- acmls_config_audit_log (admin-level) --------------------
                $records = $DB->get_records('acmls_config_audit_log', ['userid' => $userid]);
                if (!empty($records)) {
                    $data = array_map(function($r) {
                        return [
                            'setting_name' => $r->setting_name,
                            'old_value'    => $r->old_value,
                            'new_value'    => $r->new_value,
                            'context'      => $r->context,
                            'timecreated'  => transform::datetime($r->timecreated),
                        ];
                    }, $records);
                    writer::with_context($context)->export_data(
                        array_merge($pluginpath, ['acmls_config_audit_log']),
                        (object) ['records' => array_values($data)]
                    );
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Data deletion — Req 15.3
    // -------------------------------------------------------------------------

    /**
     * Delete all personal data for all users in the specified context.
     *
     * - Course context: deletes all records from course-scoped ACMLS tables
     *   where courseid matches the context instance.
     * - System context: deletes all records from acmls_config_audit_log.
     *
     * @param  \context $context The context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = (int) $context->instanceid;

            $DB->delete_records('acmls_learner_profile',  ['courseid' => $courseid]);
            $DB->delete_records('acmls_activity_log',     ['courseid' => $courseid]);
            $DB->delete_records('acmls_learner_record',   ['courseid' => $courseid]);
            $DB->delete_records('acmls_coach_decision',   ['courseid' => $courseid]);
            $DB->delete_records('acmls_leaderboard',      ['courseid' => $courseid]);
            $DB->delete_records('acmls_motivation_feedback', ['courseid' => $courseid]);
            $DB->delete_records('acmls_data_access_log',  ['courseid' => $courseid]);
            $DB->delete_records('acmls_learner_consent',  ['courseid' => $courseid]);

        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records('acmls_config_audit_log');
        }
    }

    /**
     * Delete all personal data for the specified user in the approved contexts.
     *
     * Iterates over each approved context and deletes all ACMLS records that
     * belong to the user in that context:
     *
     * - Course context: removes the user's rows from acmls_learner_profile,
     *   acmls_activity_log, acmls_learner_record, acmls_coach_decision, and
     *   acmls_leaderboard for the matching courseid.
     * - System context: removes the user's rows from acmls_config_audit_log.
     *
     * Tables without a userid column (acmls_motivation_sentence,
     * acmls_learning_resource) store no directly user-identifiable data and
     * are therefore not touched.
     *
     * @param  approved_contextlist $contextlist The approved contexts to delete data for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {

            if ($context->contextlevel == CONTEXT_COURSE) {
                $courseid = (int) $context->instanceid;

                $DB->delete_records('acmls_learner_profile', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                $DB->delete_records('acmls_activity_log', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                $DB->delete_records('acmls_learner_record', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                $DB->delete_records('acmls_coach_decision', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                $DB->delete_records('acmls_leaderboard', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                $DB->delete_records('acmls_motivation_feedback', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);

                // acmls_data_access_log — delete rows where user is accessor or target.
                $DB->delete_records_select(
                    'acmls_data_access_log',
                    '(accessor_userid = :accessor_userid OR target_userid = :target_userid) AND courseid = :courseid',
                    [
                        'accessor_userid' => $userid,
                        'target_userid'   => $userid,
                        'courseid'        => $courseid,
                    ]
                );

                // acmls_learner_consent — delete the user's consent decision.
                $DB->delete_records('acmls_learner_consent', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);

            } else if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->delete_records('acmls_config_audit_log', ['userid' => $userid]);
            }
        }
    }
}
