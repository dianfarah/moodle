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
 * ACMLS Event Observer — handles all Moodle events for the Tracking System.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\event;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\tracking\tracking_system;
use block_attendanceleaderboard\repository\learning_resource_repository;
use block_attendanceleaderboard\admin\config_audit_log;

/**
 * Event observer for ACMLS Tracking System.
 *
 * Each static method is registered as a callback in db/events.php and
 * delegates to TrackingSystem::handle_event() for unified processing.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle user_loggedin event.
     *
     * Records login time, identity, and session context (Req 1.2).
     *
     * @param  \core\event\user_loggedin $event
     * @return void
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        try {
            $ts = new tracking_system();
            $ts->handle_event($event);
        } catch (\Exception $e) {
            debugging('ACMLS observer::user_loggedin error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handle user_loggedout event.
     *
     * Records session end and duration (Req 1.5).
     *
     * @param  \core\event\user_loggedout $event
     * @return void
     */
    public static function user_loggedout(\core\event\user_loggedout $event): void {
        try {
            $ts = new tracking_system();
            $ts->handle_event($event);
        } catch (\Exception $e) {
            debugging('ACMLS observer::user_loggedout error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handle course_module_viewed event.
     *
     * Records Learner access to a Learning Resource (Req 2.1).
     *
     * @param  \core\event\course_module_viewed $event
     * @return void
     */
    public static function course_module_viewed(\core\event\course_module_viewed $event): void {
        try {
            $ts = new tracking_system();
            $ts->handle_event($event);
        } catch (\Exception $e) {
            debugging('ACMLS observer::course_module_viewed error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handle quiz attempt_submitted event.
     *
     * Records quiz completion and result (Req 2.2).
     *
     * @param  \mod_quiz\event\attempt_submitted $event
     * @return void
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        try {
            $ts = new tracking_system();
            $ts->handle_event($event);
            self::trigger_quiz_motivation($event);
        } catch (\Exception $e) {
            debugging('ACMLS observer::quiz_attempt_submitted error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Generate and queue a post-quiz motivational encouragement message.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     * @return void
     */
    private static function trigger_quiz_motivation(\mod_quiz\event\attempt_submitted $event): void {
        global $DB;

        try {
            // Fetch attempt score and student userid.
            $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid]);
            $userid = (int) ($event->relateduserid ?: $event->userid);
            if ($attempt && !empty($attempt->userid)) {
                $userid = (int) $attempt->userid;
            }
            $courseid = (int) $event->courseid;

            $quizgrade = null;
            if ($attempt && isset($attempt->sumgrades)) {
                $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
                if ($quiz && (float)$quiz->sumgrades > 0) {
                    $quizgrade = ((float)$attempt->sumgrades / (float)$quiz->sumgrades) * 100.0;
                }
            }

            // Determine motivation category based on quiz performance.
            if ($quizgrade !== null) {
                if ($quizgrade >= 70.0) {
                    $category = 'achievement';
                } else if ($quizgrade >= 40.0) {
                    $category = 'reinforcement';
                } else {
                    $category = 'recovery';
                }
            } else {
                $category = 'reinforcement';
            }

            // Get or update learner profile.
            $profile = null;
            if (class_exists('\block_attendanceleaderboard\profiling\profiling_system')) {
                $profiler = new \block_attendanceleaderboard\profiling\profiling_system();
                $metrics = [];
                if ($quizgrade !== null) {
                    $metrics['score'] = $quizgrade;
                }
                $profile = $profiler->update_profile($userid, $courseid, $metrics);
            }

            $message = [];
            $repository = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();

            // Try LLM / Gemini if configured.
            $apikey = (string) (get_config('block_attendanceleaderboard', 'gemini_apikey') ?? '');
            if (!empty($apikey) && $profile && class_exists('\block_attendanceleaderboard\motivation\llm_preparation')) {
                try {
                    $generator = \block_attendanceleaderboard\motivation\llm_preparation::build_from_config($repository);
                    $message = $generator->generate_encouragement_record($profile, $category);
                } catch (\Throwable $ex) {
                    debugging('quiz_attempt_submitted LLM error: ' . $ex->getMessage(), DEBUG_DEVELOPER);
                }
            }

            // Fallback to static template.
            if (empty($message['content'])) {
                $perf_target = $profile ? (int) $profile->performance_category : 2;
                $mot_target = $profile ? ($profile->motivation_level >= 70 ? 3 : ($profile->motivation_level >= 40 ? 2 : 1)) : 2;
                $record = $repository->find_relevant_record($userid, $category, $perf_target, $mot_target);
                if ($record) {
                    $message = [
                        'messageid' => (int) $record->id,
                        'content' => (string) $record->content,
                        'category' => $category,
                        'source' => (string) $record->source,
                    ];
                } else {
                    $message = [
                        'content' => 'Kerja luar biasa! Anda telah menyelesaikan kuis ini. Terus tingkatkan kemampuan dan pertahankan semangat belajar Anda!',
                        'category' => $category,
                        'source' => 'system',
                    ];
                }
            }

            // Queue as pending_quiz_motivation in acmls_learner_record.
            $pending = new \stdClass();
            $pending->userid = $userid;
            $pending->courseid = $courseid;
            $pending->record_type = 'pending_quiz_motivation';
            $pending->source_component = 'motivation';
            $pending->data_payload = json_encode([
                'content' => $message['content'],
                'category' => $message['category'] ?? $category,
                'source' => $message['source'] ?? 'system',
                'quizgrade' => $quizgrade,
                'timecreated' => time(),
            ]);
            $pending->profile_version = $profile ? (int) $profile->profile_version : 0;
            $pending->timecreated = time();

            $DB->insert_record('acmls_learner_record', $pending);

        } catch (\Throwable $e) {
            debugging('ACMLS observer::trigger_quiz_motivation error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handle grade_item_updated event.
     *
     * Records grade updates for Learner performance tracking (Req 2.2).
     *
     * @param  \core\event\grade_item_updated $event
     * @return void
     */
    public static function grade_updated(\core\event\grade_item_updated $event): void {
        try {
            $ts = new tracking_system();
            $ts->handle_event($event);
        } catch (\Exception $e) {
            debugging('ACMLS observer::grade_updated error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handle forum post_created event.
     *
     * Records social interaction / forum participation (Req 2.6).
     *
     * @param  \mod_forum\event\post_created $event
     * @return void
     */
    public static function forum_post_created(\mod_forum\event\post_created $event): void {
        try {
            $ts = new tracking_system();
            $ts->handle_event($event);
        } catch (\Exception $e) {
            debugging('ACMLS observer::forum_post_created error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handle course_module_completion_updated event.
     *
     * Records activity completion (Req 2.2).
     *
     * @param  \core\event\course_module_completion_updated $event
     * @return void
     */
    public static function activity_completed(\core\event\course_module_completion_updated $event): void {
        try {
            $ts = new tracking_system();
            $ts->handle_event($event);
        } catch (\Exception $e) {
            debugging('ACMLS observer::activity_completed error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handle course_module_created event.
     *
     * Auto-indexes the new course module as a learning resource in
     * acmls_learning_resource (Req 6.2).
     *
     * @param  \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        try {
            $cmid = (int) $event->objectid;
            $repo = new learning_resource_repository();
            $repo->auto_index_new_resource($cmid);
        } catch (\Exception $e) {
            debugging('ACMLS observer::course_module_created error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handle config_log_created event (Task 13.6).
     *
     * Moodle fires \core\event\config_log_created whenever a plugin config
     * value is saved via the admin settings UI. This handler filters for
     * block_attendanceleaderboard settings and writes an entry to the
     * acmls_config_audit_log table.
     *
     * The event's other data contains:
     *   - plugin   : the plugin name (e.g. 'block_attendanceleaderboard')
     *   - name     : the setting name (e.g. 'llm_provider')
     *   - value    : the new value
     *   - oldvalue : the previous value
     *
     * @param  \core\event\config_log_created $event
     * @return void
     */
    public static function config_log_created(\core\event\config_log_created $event): void {
        try {
            $data = $event->get_data();

            // Only process settings that belong to this plugin.
            $plugin = $data['other']['plugin'] ?? '';
            if ($plugin !== 'block_attendanceleaderboard') {
                return;
            }

            $setting_name = 'block_attendanceleaderboard/' . ($data['other']['name'] ?? '');
            $old_value    = (string) ($data['other']['oldvalue'] ?? '');
            $new_value    = (string) ($data['other']['value'] ?? '');
            $userid       = (int) $event->userid;

            config_audit_log::log_change(
                $userid,
                $setting_name,
                $old_value,
                $new_value,
                'admin_settings_page'
            );
        } catch (\Exception $e) {
            debugging('ACMLS observer::config_log_created error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
