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
 * English language strings for block_attendanceleaderboard.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name and description.
$string['pluginname']        = 'Attendance Leaderboard (ACMLS)';
$string['plugindescription'] = 'Adaptive Cognitive-Motivational Learning System — an intelligent block plugin that provides personalized learning recommendations, motivational interventions, and an engagement-based leaderboard.';

// Block title.
$string['attendanceleaderboard'] = 'Attendance Leaderboard';
$string['blocktitle']            = 'ACMLS — Attendance Leaderboard';

// Capabilities.
$string['attendanceleaderboard:viewleaderboard']  = 'View the attendance leaderboard';
$string['attendanceleaderboard:manageresources']  = 'Manage learning resources';
$string['attendanceleaderboard:viewanalytics']    = 'View learner analytics';
$string['attendanceleaderboard:addinstance']      = 'Add a new Attendance Leaderboard block';
$string['attendanceleaderboard:myaddinstance']    = 'Add a new Attendance Leaderboard block to Dashboard';

// Settings — general section.
$string['settings_general']             = 'General Settings';
$string['settings_general_desc']        = 'General configuration for the ACMLS plugin.';

// Settings — LLM configuration.
$string['settings_llm']                 = 'LLM Configuration';
$string['settings_llm_desc']            = 'Configure the Gemini provider used for generating motivational content.';
$string['settings_llm_provider']        = 'LLM Provider';
$string['settings_llm_provider_desc']   = 'Gemini is the only supported provider for generating motivational sentences.';
$string['settings_llm_provider_gemini'] = 'Gemini';
$string['settings_gemini_apikey']       = 'Gemini API Key';
$string['settings_gemini_apikey_desc']  = 'Enter your Gemini API key. Keep this value secret.';
$string['settings_gemini_model']        = 'Gemini Model';
$string['settings_gemini_model_desc']   = 'The Gemini model to use for motivational content generation (for example, gemini-1.5-flash).';
$string['settings_llm_provider_openai'] = 'OpenAI';
$string['settings_llm_provider_ollama'] = 'Ollama (Local)';
$string['settings_openai_apikey']       = 'OpenAI API Key';
$string['settings_openai_apikey_desc']  = 'Enter your OpenAI API key. Keep this value secret.';
$string['settings_openai_model']        = 'OpenAI Model';
$string['settings_openai_model_desc']   = 'The OpenAI model to use (e.g., gpt-4o-mini, gpt-4o).';
$string['settings_ollama_endpoint']     = 'Ollama Endpoint URL';
$string['settings_ollama_endpoint_desc'] = 'The base URL of your local Ollama instance (e.g., http://localhost:11434).';
$string['settings_ollama_model']        = 'Ollama Model';
$string['settings_ollama_model_desc']   = 'The Ollama model to use (e.g., llama3.2, mistral).';

// Settings — Leaderboard configuration.
$string['settings_leaderboard']              = 'Leaderboard Settings';
$string['settings_leaderboard_desc']         = 'Configure the leaderboard scoring weights and display options.';
$string['settings_weight_attendance']        = 'Attendance Weight';
$string['settings_weight_attendance_desc']   = 'Weight for attendance score in the total leaderboard score (0.0 – 1.0).';
$string['settings_weight_engagement']        = 'Engagement Weight';
$string['settings_weight_engagement_desc']   = 'Weight for engagement score in the total leaderboard score (0.0 - 1.0).';
$string['settings_weight_completion']        = 'Completion Weight';
$string['settings_weight_completion_desc']   = 'Weight for activity completion score in the total leaderboard score (0.0 – 1.0).';
$string['settings_leaderboard_privacy']      = 'Enable Leaderboard Privacy';
$string['settings_leaderboard_privacy_desc'] = 'When enabled, other learners\' names are displayed anonymously (initials or pseudonyms).';
$string['settings_leaderboard_scope']        = 'Default Leaderboard Scope';
$string['settings_leaderboard_scope_desc']   = 'The default scope for the leaderboard display.';
$string['settings_scope_course']             = 'Course';
$string['settings_scope_program']            = 'Program';
$string['settings_scope_institution']        = 'Institution';

// Settings — Motivational thresholds.
$string['settings_motivation']                    = 'Motivational Thresholds';
$string['settings_motivation_desc']               = 'Configure thresholds that trigger motivational interventions.';
$string['settings_motivation_threshold']          = 'Motivation Level Threshold';
$string['settings_motivation_threshold_desc']     = 'Motivation_Level below this value (0–100) triggers an intervention request.';
$string['settings_motivation_threshold_days']     = 'Consecutive Days Below Threshold';
$string['settings_motivation_threshold_days_desc'] = 'Number of consecutive days Motivation_Level must remain below the threshold before an intervention is triggered.';
$string['settings_motivation_capacity_warning']      = 'Repository Capacity Warning (%)';
$string['settings_motivation_capacity_warning_desc'] = 'Percentage of Motivation Sentence Repository capacity at which an administrator warning notification is sent. Default: 80.';

// Settings — Coach / Profiling parameters.
$string['settings_coach']                         = 'Coach & Profiling Parameters';
$string['settings_coach_desc']                    = 'Configure the adaptive engine and profiling algorithm parameters.';
$string['settings_learning_rate']                 = 'Learning Rate (α)';
$string['settings_learning_rate_desc']            = 'The α value used in the weighted moving average for profile updates: new_value = (recent_data × α) + (historical_average × (1 − α)). Default: 0.7.';
$string['settings_performance_decline_threshold'] = 'Performance Decline Threshold (%)';
$string['settings_performance_decline_threshold_desc'] = 'Percentage decline from the previous average that triggers a performance alert to the Coach. Default: 20.';

// Settings — Leaderboard update interval.
$string['settings_leaderboard_update_interval']      = 'Leaderboard Update Interval (minutes)';
$string['settings_leaderboard_update_interval_desc'] = 'How often (in minutes) the leaderboard rankings are recalculated. Default: 15.';

// Settings — Evaluation configuration.
$string['settings_evaluation']                    = 'Evaluation Configuration';
$string['settings_evaluation_desc']               = 'Configure performance evaluation thresholds and Coach response parameters.';
$string['settings_coach_response_timeout']        = 'Coach Response Timeout (seconds)';
$string['settings_coach_response_timeout_desc']   = 'Maximum time in seconds the Coach rule-based engine is allowed to compute an Adaptive_Intervention before timing out. Default: 10.';

// Leaderboard display strings.
$string['leaderboard_title']         = 'Leaderboard';
$string['leaderboard_rank']          = 'Rank';
$string['leaderboard_name']          = 'Name';
$string['leaderboard_score']         = 'Score';
$string['leaderboard_change']        = 'Change';
$string['leaderboard_rank_change']   = 'Change';
$string['leaderboard_points_to_next'] = 'Points to Next';
$string['leaderboard_your_rank']     = 'Your current rank: {$a}';
$string['leaderboard_no_data']       = 'No leaderboard data available yet.';

// Performance categories.
$string['performance_low']    = 'Low';
$string['performance_middle'] = 'Middle';
$string['performance_high']   = 'High';

// Encouragement content categories.
$string['encouragement_reinforcement'] = 'Penguatan Positif';
$string['encouragement_achievement']   = 'Pencapaian Tinggi';
$string['encouragement_recovery']      = 'Pemulihan Semangat';
$string['encouragement_persistence']   = 'Ketekunan Belajar';

// Error messages.
$string['error_llm_unavailable']    = 'The LLM service is currently unavailable. Using a static template instead.';
$string['error_invalid_config']     = 'Invalid configuration value: {$a}. The previous valid configuration has been retained.';
$string['error_db_write']           = 'Failed to write to the database. The data has been buffered and will be retried.';
$string['error_gradebook_unavailable'] = 'Moodle Gradebook data is currently unavailable. Retaining the last valid performance value.';

// Configuration validation error messages.
$string['error_validation_llm_provider']       = 'Invalid LLM provider. The value must be "gemini". The previous valid configuration has been retained.';
$string['error_validation_api_key']            = 'The Gemini API key must not be empty when Gemini generation is enabled. The previous valid configuration has been retained.';
$string['error_validation_ollama_endpoint']    = 'The Ollama endpoint must be a valid URL starting with http:// or https:// (e.g., http://localhost:11434). The previous valid configuration has been retained.';
$string['error_validation_weight_range']       = 'Each leaderboard weight must be a number between 0.0 and 1.0. The previous valid configuration has been retained.';
$string['error_validation_weight_sum']         = 'The three leaderboard weights must sum to 1.0 (current sum: {$a}). Please adjust the weights so that attendance + engagement + completion = 1.0. The previous valid configuration has been retained.';
$string['error_validation_motivation_threshold'] = 'The motivation threshold must be an integer between 1 and 100. The previous valid configuration has been retained.';
$string['error_validation_learning_rate']      = 'The learning rate (α) must be a number between 0.01 and 1.0. The previous valid configuration has been retained.';
$string['error_validation_decline_threshold']  = 'The performance decline threshold must be an integer between 1 and 100. The previous valid configuration has been retained.';

// Privacy.
$string['privacy:metadata:acmls_learner_profile']          = 'Stores the multidimensional learner profile including cognitive level, motivation level, and performance category.';
$string['privacy:metadata:acmls_activity_log']             = 'Stores a log of all learner activities within the LMS.';
$string['privacy:metadata:acmls_motivation_sentence']      = 'Stores motivational content generated for learners.';
$string['privacy:metadata:acmls_learning_resource']        = 'Stores metadata about learning resources available in the course.';
$string['privacy:metadata:acmls_learner_record']           = 'Stores longitudinal learning analytics data for each learner.';
$string['privacy:metadata:acmls_coach_decision']           = 'Stores Coach recommendation decisions and their reasoning.';
$string['privacy:metadata:acmls_leaderboard']              = 'Stores leaderboard ranking data for learners.';
$string['privacy:metadata:gemini_api']                     = 'Anonymised learner context data may be sent to the Gemini API to generate motivational content.';
$string['privacy:metadata:openai_api']                     = 'Anonymised learner context data may be sent to the OpenAI API to generate motivational content.';

// Scheduled task names.
$string['task_flush_activity_logs']  = 'Flush ACMLS activity logs to Profiling System';
$string['task_update_profiles']      = 'Update ACMLS learner profiles';
$string['task_update_leaderboard']   = 'Update ACMLS leaderboard rankings';

// Analytics dashboard strings.
$string['analytics_dashboard']       = 'Analytics Dashboard';
$string['analytics_dashboard_title'] = 'ACMLS Analytics Dashboard';
$string['performance_distribution']  = 'Performance Category Distribution';
$string['motivation_per_course']     = 'Average Motivation Level per Course';
$string['intervention_effectiveness'] = 'Intervention Effectiveness';
$string['category_low']              = 'Low';
$string['category_middle']           = 'Middle';
$string['category_high']             = 'High';
$string['total_learners']            = 'Total Learners';
$string['avg_motivation']            = 'Average Motivation Level';
$string['total_decisions']           = 'Total Decisions';
$string['positive_responses']        = 'Positive Responses';
$string['effectiveness_percentage']  = 'Effectiveness';
$string['no_data_available']         = 'No data available yet.';
$string['course_name']               = 'Course';
$string['count']                     = 'Count';
$string['percentage']                = 'Percentage';
$string['viewdashboard']             = 'View Analytics Dashboard';

// Block instance configuration strings (used by edit_form.php).
$string['configtitle']          = 'Block title';
$string['blocksettings']        = 'Block settings';
$string['configdisplaytype']    = 'Display type';
$string['displaytype_top']      = 'Top N students';
$string['displaytype_all']      = 'All students';
$string['numbertodisplay']      = 'Number of students to display';
$string['confignameformat']     = 'Name format';
$string['nameformat_full']      = 'Full name (Firstname Lastname)';
$string['nameformat_initial']   = 'Initial (Firstname L.)';

// Manage resources page strings.
$string['manage_resources_title']       = 'Manage Learning Resources';
$string['manage_resources_link']        = 'Manage Learning Resources';
$string['addresource']                  = 'Add Resource';
$string['editresource']                 = 'Edit Resource';
$string['deleteresource']               = 'Delete Resource';
$string['saveresource']                 = 'Save Resource';
$string['resource_title']               = 'Title';
$string['resource_title_help']          = 'Enter the title of the learning resource.';
$string['resource_type']                = 'Resource Type';
$string['resource_type_help']           = 'Select the type of learning resource.';
$string['resource_type_resource']       = 'File / Resource';
$string['resource_type_page']           = 'Page';
$string['resource_type_book']           = 'Book';
$string['resource_type_video']          = 'Video';
$string['resource_type_audio']          = 'Audio';
$string['resource_type_document']       = 'Document';
$string['resource_type_pdf']            = 'PDF';
$string['resource_type_quiz']           = 'Quiz';
$string['resource_type_assign']         = 'Assignment';
$string['resource_type_workshop']       = 'Workshop';
$string['resource_type_forum']          = 'Forum';
$string['resource_type_chat']           = 'Chat';
$string['difficulty_level']             = 'Difficulty Level';
$string['difficulty_level_help']        = 'Select the difficulty level of this resource.';
$string['difficulty_basic']             = 'Basic';
$string['difficulty_intermediate']      = 'Intermediate';
$string['difficulty_advanced']          = 'Advanced';
$string['topic_tags']                   = 'Topic Tags';
$string['topic_tags_help']              = 'Enter comma-separated topic tags (e.g. algebra, calculus, statistics).';
$string['learning_styles']              = 'Learning Styles';
$string['learning_styles_help']         = 'Select one or more learning styles this resource supports. Hold Ctrl/Cmd to select multiple.';
$string['style_visual']                 = 'Visual';
$string['style_auditory']               = 'Auditory';
$string['style_reading']                = 'Reading / Writing';
$string['style_kinesthetic']            = 'Kinesthetic';
$string['resource_is_active']           = 'Active';
$string['resource_is_active_desc']      = 'Inactive resources are hidden from recommendations but their data is preserved.';
$string['resource_active']              = 'Active';
$string['resource_inactive']            = 'Inactive';
$string['resource_status']              = 'Status';
$string['access_count']                 = 'Access Count';
$string['avg_rating']                   = 'Avg. Rating';
$string['effectiveness_score']          = 'Effectiveness';
$string['actions']                      = 'Actions';
$string['no_resources']                 = 'No learning resources have been added yet. Click "Add Resource" to get started.';
$string['resource_added']               = 'Learning resource added successfully.';
$string['resource_updated']             = 'Learning resource updated successfully.';
$string['resource_deleted']             = 'Learning resource deactivated successfully.';
$string['resource_not_found']           = 'The requested learning resource was not found.';
$string['resource_delete_confirm']      = 'Are you sure you want to deactivate the resource "{$a}"? The resource data will be preserved for analytics.';
$string['manage_resources_link_desc']   = 'Add, edit, and manage learning resources for this course.';

// Learner Profile Monitoring strings.
$string['learnerprofiles']           = 'Learner Profiles';
$string['learnerprofilemonitoring']  = 'Learner Profile Monitoring';
$string['learnerprofiledetail']      = 'Learner Profile Detail';
$string['performancecategory']       = 'Performance Category';
$string['cognitivelevel']            = 'Cognitive Level';
$string['motivationlevel']           = 'Motivation Level';
$string['learningstyle']             = 'Learning Style';
$string['behavioralscore']           = 'Behavioral Score';
$string['engagementscore']           = 'Engagement Score';
$string['lastupdated']               = 'Last Updated';
$string['profileversion']            = 'Profile Version';
$string['profilehistory']            = 'Profile History';
$string['noprofilefound']            = 'No profile found for this learner.';
$string['nolearnersprofile']         = 'No learner profiles found for this course.';
$string['filterbyperformance']       = 'Filter by Performance Category';
$string['allcategories']             = 'All Categories';
$string['viewdetail']                = 'View Detail';
$string['low']                       = 'Low';
$string['middle']                    = 'Middle';
$string['high']                      = 'High';
$string['backtolist']                = 'Back to Learner List';
$string['viewlearnerprofiles']       = 'View Learner Profiles';
$string['viewlearnerprofiles_desc']  = 'Monitor learner profiles for this course (requires viewanalytics capability).';

// Configuration Audit Log strings (Task 13.6).
$string['audit_log_title']           = 'Configuration Audit Log';
$string['audit_log_desc']            = 'Records every configuration change made by an administrator, showing who changed what and when.';
$string['audit_log_settings_desc']   = 'View a full history of all administrator configuration changes.';
$string['audit_view_full_log']       = 'View Full Audit Log';
$string['audit_no_entries']          = 'No configuration changes have been recorded yet.';
$string['audit_col_time']            = 'Date / Time';
$string['audit_col_user']            = 'Administrator';
$string['audit_col_setting']         = 'Setting';
$string['audit_col_old_value']       = 'Previous Value';
$string['audit_col_new_value']       = 'New Value';
$string['audit_recent_changes']      = 'configuration changes in the last 30 days';
$string['audit_showing']             = 'Showing {$a->from}–{$a->to} of {$a->total} entries';
$string['audit_total_entries']       = '{$a} entries total';
$string['privacy:metadata:acmls_config_audit_log'] = 'Stores a log of all administrator configuration changes, including who made the change, when, and what was changed.';

// Privacy metadata — field-level strings for acmls_learner_profile.
$string['privacy:metadata:acmls_learner_profile:userid']               = 'The ID of the learner whose profile is stored.';
$string['privacy:metadata:acmls_learner_profile:courseid']             = 'The course this learner profile belongs to.';
$string['privacy:metadata:acmls_learner_profile:cognitive_level']      = 'The learner\'s cognitive level (1=Low, 2=Middle, 3=High).';
$string['privacy:metadata:acmls_learner_profile:motivation_level']     = 'The learner\'s motivation level score (0.00–100.00).';
$string['privacy:metadata:acmls_learner_profile:performance_category'] = 'The learner\'s performance category (1=Low, 2=Middle, 3=High).';
$string['privacy:metadata:acmls_learner_profile:learning_style']       = 'The learner\'s classified learning style (e.g. visual, auditory).';
$string['privacy:metadata:acmls_learner_profile:behavioral_score']     = 'The learner\'s behavioural engagement score.';
$string['privacy:metadata:acmls_learner_profile:engagement_score']     = 'The learner\'s overall engagement score.';
$string['privacy:metadata:acmls_learner_profile:profile_version']      = 'The version number of this learner profile snapshot.';
$string['privacy:metadata:acmls_learner_profile:last_updated']         = 'The timestamp when this profile was last updated.';

// Privacy metadata — field-level strings for acmls_activity_log.
$string['privacy:metadata:acmls_activity_log:userid']           = 'The ID of the learner who performed the activity.';
$string['privacy:metadata:acmls_activity_log:courseid']         = 'The course in which the activity occurred.';
$string['privacy:metadata:acmls_activity_log:event_type']       = 'The type of event recorded (e.g. course_module_viewed, quiz_submitted).';
$string['privacy:metadata:acmls_activity_log:component']        = 'The Moodle component that generated the event (e.g. mod_quiz).';
$string['privacy:metadata:acmls_activity_log:objectid']         = 'The ID of the object involved in the event.';
$string['privacy:metadata:acmls_activity_log:action']           = 'The action performed by the learner.';
$string['privacy:metadata:acmls_activity_log:duration_seconds'] = 'The duration of the activity in seconds.';
$string['privacy:metadata:acmls_activity_log:result_value']     = 'The score or result value associated with the activity, if applicable.';
$string['privacy:metadata:acmls_activity_log:context_data']     = 'Additional JSON metadata about the activity context.';
$string['privacy:metadata:acmls_activity_log:timecreated']      = 'The timestamp when this activity log entry was created.';

// Privacy metadata — field-level strings for acmls_motivation_sentence.
$string['privacy:metadata:acmls_motivation_sentence:learner_context'] = 'Anonymised learner context data used to generate this motivational sentence. No personally identifiable information is stored.';
$string['privacy:metadata:acmls_motivation_sentence:content']         = 'The motivational sentence content generated for learners.';
$string['privacy:metadata:acmls_motivation_sentence:category']        = 'The motivational category (reinforcement, achievement, recovery, persistence).';
$string['privacy:metadata:acmls_motivation_sentence:timecreated']     = 'The timestamp when this motivational sentence was generated.';

// Privacy metadata — field-level strings for acmls_learner_record.
$string['privacy:metadata:acmls_learner_record:userid']           = 'The ID of the learner this record belongs to.';
$string['privacy:metadata:acmls_learner_record:courseid']         = 'The course this learning record is associated with.';
$string['privacy:metadata:acmls_learner_record:record_type']      = 'The type of learning record (e.g. profile_snapshot, performance, intervention).';
$string['privacy:metadata:acmls_learner_record:source_component'] = 'The ACMLS component that created this record (e.g. profiling, evaluation, coach).';
$string['privacy:metadata:acmls_learner_record:data_payload']     = 'The JSON-encoded data payload containing the learner\'s learning analytics data.';
$string['privacy:metadata:acmls_learner_record:profile_version']  = 'The profile version number at the time this record was created.';
$string['privacy:metadata:acmls_learner_record:timecreated']      = 'The timestamp when this learning record was created.';

// Privacy metadata — field-level strings for acmls_coach_decision.
$string['privacy:metadata:acmls_coach_decision:userid']                = 'The ID of the learner this Coach decision was made for.';
$string['privacy:metadata:acmls_coach_decision:courseid']              = 'The course context in which this decision was made.';
$string['privacy:metadata:acmls_coach_decision:decision_type']         = 'The type of Coach decision (resource_recommendation or motivation_intervention).';
$string['privacy:metadata:acmls_coach_decision:input_profile']         = 'A JSON snapshot of the learner\'s profile at the time the decision was made.';
$string['privacy:metadata:acmls_coach_decision:recommended_resources'] = 'A JSON array of learning resource IDs recommended to the learner.';
$string['privacy:metadata:acmls_coach_decision:motivation_category']   = 'The motivational category selected for this intervention.';
$string['privacy:metadata:acmls_coach_decision:reasoning']             = 'The reasoning and rules that led to this Coach decision.';
$string['privacy:metadata:acmls_coach_decision:rules_triggered']       = 'A JSON array of rule IDs that were triggered to produce this decision.';
$string['privacy:metadata:acmls_coach_decision:learner_response']      = 'The learner\'s response to this decision (0=ignored, 1=accessed, null=pending).';
$string['privacy:metadata:acmls_coach_decision:response_time']         = 'The timestamp when the learner responded to this decision.';
$string['privacy:metadata:acmls_coach_decision:timecreated']           = 'The timestamp when this Coach decision was recorded.';

// Privacy metadata — field-level strings for acmls_leaderboard.
$string['privacy:metadata:acmls_leaderboard:userid']          = 'The ID of the learner whose leaderboard entry this is.';
$string['privacy:metadata:acmls_leaderboard:courseid']        = 'The course this leaderboard entry belongs to.';
$string['privacy:metadata:acmls_leaderboard:scope']           = 'The scope of this leaderboard entry (course, program, or institution).';
$string['privacy:metadata:acmls_leaderboard:attendance_score'] = 'The learner\'s attendance score component.';
$string['privacy:metadata:acmls_leaderboard:engagement_score'] = 'The learner\'s engagement score component.';
$string['privacy:metadata:acmls_leaderboard:completion_score'] = 'The learner\'s activity completion score component.';
$string['privacy:metadata:acmls_leaderboard:total_score']     = 'The learner\'s total weighted leaderboard score.';
$string['privacy:metadata:acmls_leaderboard:current_rank']    = 'The learner\'s current rank on the leaderboard.';
$string['privacy:metadata:acmls_leaderboard:previous_rank']   = 'The learner\'s previous rank on the leaderboard.';
$string['privacy:metadata:acmls_leaderboard:rank_change']     = 'The change in rank since the last update (positive = improved, negative = declined).';
$string['privacy:metadata:acmls_leaderboard:points_to_next']  = 'The number of points needed to reach the next rank.';
$string['privacy:metadata:acmls_leaderboard:display_name']    = 'The display name shown on the leaderboard (may be anonymised).';
$string['privacy:metadata:acmls_leaderboard:last_updated']    = 'The timestamp when this leaderboard entry was last updated.';

// Privacy metadata — field-level strings for acmls_config_audit_log.
$string['privacy:metadata:acmls_config_audit_log:userid']       = 'The ID of the administrator who made the configuration change.';
$string['privacy:metadata:acmls_config_audit_log:setting_name'] = 'The full name of the configuration setting that was changed.';
$string['privacy:metadata:acmls_config_audit_log:old_value']    = 'The previous value of the configuration setting before the change.';
$string['privacy:metadata:acmls_config_audit_log:new_value']    = 'The new value of the configuration setting after the change.';
$string['privacy:metadata:acmls_config_audit_log:context']      = 'The context in which the configuration change was made (e.g. admin_settings_page).';
$string['privacy:metadata:acmls_config_audit_log:timecreated']  = 'The timestamp when this configuration change was recorded.';

// Privacy metadata — field-level strings for OpenAI external API.
$string['privacy:metadata:gemini_api:learner_context'] = 'Anonymised learner context data (performance category, motivation level, and learning patterns) sent to the Gemini API to generate personalised motivational content. No personally identifiable information is included.';

// Privacy metadata — acmls_data_access_log table (Task 14.5 / Req 15.4).
$string['privacy:metadata:acmls_data_access_log']                          = 'Stores an audit log of every access to Learner_Record data, recording the accessor\'s identity, the target learner, the timestamp, and what data was accessed.';
$string['privacy:metadata:acmls_data_access_log:accessor_userid']          = 'The ID of the user who accessed the Learner_Record data.';
$string['privacy:metadata:acmls_data_access_log:target_userid']            = 'The ID of the learner whose data was accessed.';
$string['privacy:metadata:acmls_data_access_log:courseid']                 = 'The course context in which the data access occurred.';
$string['privacy:metadata:acmls_data_access_log:access_type']              = 'The type of access operation performed (e.g. query_longitudinal, export_csv, export_json).';
$string['privacy:metadata:acmls_data_access_log:record_type_filter']       = 'The record_type filter applied during the access, if any.';
$string['privacy:metadata:acmls_data_access_log:source_component_filter']  = 'The source_component filter applied during the access, if any.';
$string['privacy:metadata:acmls_data_access_log:records_returned']         = 'The number of Learner_Record entries returned by this access operation.';
$string['privacy:metadata:acmls_data_access_log:timecreated']              = 'The timestamp when this data access event was recorded.';

// Consent dialog strings (Task 14.6 / Req 15.5).
$string['consent_dialog_title']   = 'Data Privacy Consent';
$string['consent_dialog_body']    = 'To provide you with personalised motivational content, ACMLS can send anonymised learning data to an external AI service (Gemini). The following anonymised data may be transmitted:';
$string['consent_agree']          = 'Agree';
$string['consent_decline']        = 'Decline';
$string['consent_required_notice'] = 'You can change your preference at any time from your profile settings. Declining will use pre-written motivational templates instead.';
$string['consent_data_item_performance']  = 'Performance category (Low / Middle / High)';
$string['consent_data_item_motivation']   = 'Motivation level (rounded to nearest 10)';
$string['consent_data_item_style']        = 'Learning style (e.g. visual, auditory)';
$string['consent_data_item_engagement']   = 'Engagement score (rounded to nearest integer)';
$string['consent_anonymization_notice']   = 'All personal identifiers (name, email, student ID) are removed before any data is transmitted.';

// Privacy metadata — acmls_learner_consent table (Task 14.6 / Req 15.5).
$string['privacy:metadata:acmls_learner_consent']                    = 'Stores each learner\'s explicit consent decision for sending anonymised data to external LLM services.';
$string['privacy:metadata:acmls_learner_consent:userid']             = 'The ID of the learner whose consent decision is stored.';
$string['privacy:metadata:acmls_learner_consent:courseid']           = 'The course context for which this consent decision applies.';
$string['privacy:metadata:acmls_learner_consent:consent_given']      = 'Whether the learner has given consent (1 = agreed, 0 = declined).';
$string['privacy:metadata:acmls_learner_consent:consent_timestamp']  = 'The timestamp when the learner gave consent, if applicable.';
$string['privacy:metadata:acmls_learner_consent:timecreated']        = 'The timestamp when this consent record was first created.';
$string['privacy:metadata:acmls_learner_consent:timemodified']       = 'The timestamp when this consent record was last modified.';

// Motivation popup and research feedback.
$string['motivation_popup_title'] = 'Check-In Motivasi';
$string['motivation_popup_body'] = 'Silakan baca pesan di bawah ini, lalu beri tahu kami tanggapanmu setelah menerimanya.';
$string['motivation_feeling_prompt'] = 'Bagaimana perasaanmu setelah membaca pesan ini?';
$string['motivation_reflection_label'] = 'Refleksi singkat (opsional)';
$string['motivation_reflection_placeholder'] = 'Ceritakan reaksimu dalam satu atau dua kalimat.';
$string['motivation_submit'] = 'Kirim respons';
$string['motivation_feeling_required'] = 'Silakan pilih respons sebelum mengirim.';
$string['motivation_research_notice'] = 'Responsmu akan disimpan untuk evaluasi intervensi dan analisis motivasi.';
$string['motivation_feeling_very_motivated'] = '😍 Sangat Menerima';
$string['motivation_feeling_motivated'] = '😊 Menerima';
$string['motivation_feeling_neutral'] = '😐 Netral';
$string['motivation_feeling_confused'] = '🙁 Tidak Menerima';
$string['motivation_feeling_discouraged'] = '😠 Sangat Tidak Menerima';

$string['motivation_e1_prompt'] = 'Saya termotivasi untuk melanjutkan pembelajaran ini.';
$string['motivation_e2_prompt'] = 'Saya merasa percaya diri dengan kemampuan saya.';
$string['motivation_e3_prompt'] = 'Saya merasa didukung dalam proses belajar ini.';
$string['motivation_likert_1'] = '😠 Sangat Tidak Menerima';
$string['motivation_likert_2'] = '🙁 Tidak Menerima';
$string['motivation_likert_3'] = '😐 Netral';
$string['motivation_likert_4'] = '😊 Menerima';
$string['motivation_likert_5'] = '😍 Sangat Menerima';
$string['motivation_feedback_required'] = 'Silakan jawab ketiga pertanyaan sebelum mengirim respons.';

$string['emotion_checkin_title'] = 'Check-in Kesiapan Emosi';
$string['emotion_checkin_subtitle'] = 'Sebelum memulai sesi pembelajaran dan latihan, sampaikan kesiapan emosimu hari ini.';
$string['emotion_checkin_submit'] = 'Mulai Belajar';
$string['quiz_motivation_title'] = 'Umpan Balik Motivasi Belajar';
$string['quiz_motivation_subtitle'] = 'Apresiasi dan Evaluasi Pasca-Kuis';
$string['quiz_motivation_continue'] = 'Lanjutkan Belajar';
$string['quiz_motivation_score'] = 'Nilai Kuis Kamu: {$a}%';
$string['quiz_motivation_suggestion_title'] = 'Saran Perbaikan & Langkah Selanjutnya';
$string['motivation_suggestion_title'] = 'Saran Perbaikan';

// Privacy metadata - acmls_motivation_feedback.
$string['privacy:metadata:acmls_motivation_feedback'] = 'Stores each learner response to a motivational popup, including feeling selection and optional reflection text.';
$string['privacy:metadata:acmls_motivation_feedback:userid'] = 'The ID of the learner who submitted the motivational feedback.';
$string['privacy:metadata:acmls_motivation_feedback:courseid'] = 'The course context where the motivational feedback was submitted.';
$string['privacy:metadata:acmls_motivation_feedback:sentenceid'] = 'The motivation sentence record shown to the learner, when available.';
$string['privacy:metadata:acmls_motivation_feedback:category'] = 'The motivational category associated with the popup message.';
$string['privacy:metadata:acmls_motivation_feedback:source'] = 'The source of the message shown to the learner (template or Gemini).';
$string['privacy:metadata:acmls_motivation_feedback:feeling_key'] = 'The learner-selected feeling label key.';
$string['privacy:metadata:acmls_motivation_feedback:feeling_score'] = 'The numeric score mapped from the learner-selected feeling.';
$string['privacy:metadata:acmls_motivation_feedback:reflection_note'] = 'The learner optional written reflection after receiving the motivational message.';
$string['privacy:metadata:acmls_motivation_feedback:message_content'] = 'The motivational message content that was displayed to the learner.';
$string['privacy:metadata:acmls_motivation_feedback:timecreated'] = 'The timestamp when the learner submitted the motivational feedback.';
