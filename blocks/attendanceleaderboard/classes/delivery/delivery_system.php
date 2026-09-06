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
 * Delivery system for ACMLS block output.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\delivery;

defined('MOODLE_INTERNAL') || die();

/**
 * Rendering and interaction delivery for the ACMLS block.
 */
class delivery_system {

    /** @var string DB table for learner records. */
    public const TABLE_LEARNER_RECORD = 'acmls_learner_record';

    /** @var string DB table for learner profiles. */
    public const TABLE_LEARNER_PROFILE = 'acmls_learner_profile';

    /** @var string DB table for leaderboard. */
    public const TABLE_LEADERBOARD = 'acmls_leaderboard';

    /** @var string Record type for interaction entries. */
    public const RECORD_TYPE_INTERACTION = 'interaction';

    /** @var string Source component identifier. */
    public const SOURCE_COMPONENT = 'delivery';

    /** @var array<int,string> Difficulty level labels. */
    public const DIFFICULTY_LABELS = [
        1 => 'Dasar',
        2 => 'Menengah',
        3 => 'Lanjutan',
    ];

    /**
     * Render the resource recommendations template.
     *
     * @param int $userid Moodle user ID.
     * @param array $resources Resource rows.
     * @return string
     */
    public function display_resource_recommendations(int $userid, array $resources): string {
        global $OUTPUT;

        $resource_data = [];
        foreach ($resources as $resource) {
            $r = (object) $resource;
            $difficulty = isset($r->difficulty_level) ? (int) $r->difficulty_level : 1;
            $resource_data[] = [
                'id' => isset($r->id) ? (int) $r->id : 0,
                'title' => isset($r->title) ? (string) $r->title : '',
                'resource_type' => isset($r->resource_type) ? (string) $r->resource_type : '',
                'difficulty_level' => $difficulty,
                'difficulty_label' => self::DIFFICULTY_LABELS[$difficulty] ?? 'Dasar',
            ];
        }

        return $OUTPUT->render_from_template(
            'block_attendanceleaderboard/resource_recommendations',
            [
                'userid' => $userid,
                'resources' => $resource_data,
                'has_resources' => !empty($resource_data),
            ]
        );
    }

    /**
     * Render the motivation popup template.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Moodle course ID.
     * @param string $content Motivation content.
     * @param string $category Message category.
     * @param int|null $sentenceid Sentence repository ID.
     * @param string $source Message source.
     * @return string
     */
    public function display_encouragement(
        int $userid,
        int $courseid,
        string $content,
        string $category = '',
        ?int $sentenceid = null,
        string $source = ''
    ): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template(
            'block_attendanceleaderboard/encouragement_message',
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'content' => $content,
                'category' => $category,
                'category_label' => $this->get_category_label($category),
                'sentenceid' => $sentenceid ?? 0,
                'source' => $source,
                'likert_options' => $this->get_likert_options(),
                'str_popup_title' => get_string('motivation_popup_title', 'block_attendanceleaderboard'),
                'str_popup_body' => get_string('motivation_popup_body', 'block_attendanceleaderboard'),
                'str_e1_prompt' => get_string('motivation_e1_prompt', 'block_attendanceleaderboard'),
                'str_e2_prompt' => get_string('motivation_e2_prompt', 'block_attendanceleaderboard'),
                'str_e3_prompt' => get_string('motivation_e3_prompt', 'block_attendanceleaderboard'),
                'str_reflection_label' => get_string('motivation_reflection_label', 'block_attendanceleaderboard'),
                'str_reflection_placeholder' => get_string('motivation_reflection_placeholder', 'block_attendanceleaderboard'),
                'str_submit' => get_string('motivation_submit', 'block_attendanceleaderboard'),
                'str_required' => get_string('motivation_feedback_required', 'block_attendanceleaderboard'),
                'str_research_notice' => get_string('motivation_research_notice', 'block_attendanceleaderboard'),
            ]
        );
    }

    /**
     * Render the leaderboard section for a learner.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Moodle course ID.
     * @param string $scope Ranking scope.
     * @return string
     */
    public function display_leaderboard(int $userid, int $courseid, string $scope = 'course'): string {
        global $OUTPUT;

        $rank_data = [];
        $leaderboard_entries = [];

        if (class_exists('\block_attendanceleaderboard\leaderboard\leaderboard')) {
            try {
                $lb = new \block_attendanceleaderboard\leaderboard\leaderboard();
                $rank_data = $lb->get_learner_rank($userid, $courseid, $scope);
                $top_rankings = $lb->get_scope_rankings($courseid, $scope, 10);

                foreach ($top_rankings as $entry) {
                    $entry_rank_change = isset($entry['rank_change']) ? (int) $entry['rank_change'] : 0;
                    $leaderboard_entries[] = [
                        'rank' => (int) $entry['current_rank'],
                        'display_name' => (string) ($entry['display_name'] ?? ''),
                        'total_score' => number_format((float) ($entry['total_score'] ?? 0), 2),
                        'rank_change' => $entry_rank_change,
                        'is_current_user' => ((int) ($entry['userid'] ?? 0)) === $userid,
                    ];
                }
            } catch (\Throwable $e) {
                debugging('DeliverySystem::display_leaderboard error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $rank_change = isset($rank_data['rank_change']) ? (int) $rank_data['rank_change'] : 0;
        $total_score = isset($rank_data['total_score']) ? (float) $rank_data['total_score'] : 0.0;
        $points_to_next = isset($rank_data['points_to_next']) ? (float) $rank_data['points_to_next'] : 0.0;

        return $OUTPUT->render_from_template(
            'block_attendanceleaderboard/leaderboard',
            [
                'userid' => $userid,
                'current_rank' => isset($rank_data['current_rank']) ? (int) $rank_data['current_rank'] : 0,
                'total_score' => number_format($total_score, 2),
                'rank_change' => $rank_change,
                'rank_change_positive' => $rank_change > 0,
                'rank_change_negative' => $rank_change < 0,
                'rank_change_abs' => abs($rank_change),
                'points_to_next' => $points_to_next,
                'display_name' => isset($rank_data['display_name']) ? (string) $rank_data['display_name'] : '',
                'leaderboard_entries' => $leaderboard_entries,
                'str_leaderboard_title' => get_string('leaderboard_title', 'block_attendanceleaderboard'),
                'str_your_rank' => get_string('leaderboard_your_rank', 'block_attendanceleaderboard'),
                'str_rank' => get_string('leaderboard_rank', 'block_attendanceleaderboard'),
                'str_name' => get_string('leaderboard_name', 'block_attendanceleaderboard'),
                'str_score' => get_string('leaderboard_score', 'block_attendanceleaderboard'),
                'str_change' => get_string('leaderboard_change', 'block_attendanceleaderboard'),
                'str_points_to_next' => get_string('leaderboard_points_to_next', 'block_attendanceleaderboard'),
                'str_no_data' => get_string('leaderboard_no_data', 'block_attendanceleaderboard'),
            ]
        );
    }

    /**
     * Record a learner interaction and forward it to tracking when available.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Moodle course ID.
     * @param string $interaction_type Interaction type.
     * @param array $data Additional payload.
     * @return void
     */
    public function record_interaction(
        int $userid,
        int $courseid,
        string $interaction_type,
        array $data = []
    ): void {
        global $DB;

        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->record_type = self::RECORD_TYPE_INTERACTION;
        $record->source_component = self::SOURCE_COMPONENT;
        $record->data_payload = json_encode([
            'interaction_type' => $interaction_type,
            'data' => $data,
        ]);
        $record->profile_version = 0;
        $record->timecreated = time();

        $DB->insert_record(self::TABLE_LEARNER_RECORD, $record);

        if (class_exists('\block_attendanceleaderboard\tracking\tracking_system')) {
            try {
                $tracking = new \block_attendanceleaderboard\tracking\tracking_system();
                if (method_exists($tracking, 'record_activity')) {
                    $tracking->record_activity($userid, $courseid, $interaction_type, $data);
                }
            } catch (\Throwable $e) {
                debugging('DeliverySystem::record_interaction tracking error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Render the complete block content.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Moodle course ID.
     * @return string
     */
    public function render_block(int $userid, int $courseid): string {
        global $DB, $OUTPUT, $PAGE;

        $profile_record = $DB->get_record(
            self::TABLE_LEARNER_PROFILE,
            ['userid' => $userid, 'courseid' => $courseid],
            '*',
            IGNORE_MISSING
        );
        $profile_record = $profile_record ?: null;

        $profile = null;
        if ($profile_record) {
            try {
                $profile = \block_attendanceleaderboard\profiling\learner_profile::from_db_record($profile_record);
            } catch (\Throwable $e) {
                debugging('DeliverySystem::render_block profile error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $resources = [];
        $motivation_category = 'reinforcement';
        if ($profile && class_exists('\block_attendanceleaderboard\coach\coach')) {
            try {
                $coach = new \block_attendanceleaderboard\coach\coach();
                $intervention = $coach->evaluate_profile($profile);
                $resources = $intervention->resources ?? [];
                $motivation_category = $intervention->motivation_category ?? 'reinforcement';
            } catch (\Throwable $e) {
                debugging('DeliverySystem::render_block coach error: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $resources = $this->get_default_resources($courseid);
            }
        } else {
            $resources = $this->get_default_resources($courseid);
        }

        $has_leaderboard_data = false;
        if (class_exists('\block_attendanceleaderboard\leaderboard\leaderboard')) {
            try {
                $lb = new \block_attendanceleaderboard\leaderboard\leaderboard();
                $has_leaderboard_data = !empty($lb->get_learner_rank($userid, $courseid));
            } catch (\Throwable $e) {
                debugging('DeliverySystem::render_block leaderboard error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        } else {
            $leaderboard_record = $DB->get_record(
                self::TABLE_LEADERBOARD,
                ['userid' => $userid, 'courseid' => $courseid],
                'id',
                IGNORE_MISSING
            );
            $leaderboard_record = $leaderboard_record ?: null;
            $has_leaderboard_data = !empty($leaderboard_record);
        }

        $encouragement = $this->resolve_encouragement_message(
            $userid,
            $courseid,
            $profile,
            $profile_record,
            $motivation_category
        );

        $leaderboard_html = $has_leaderboard_data ? $this->display_leaderboard($userid, $courseid) : '';
        $recommendations_html = !empty($resources)
            ? $this->display_resource_recommendations($userid, $resources)
            : '';
        $encouragement_html = !empty($encouragement['content'])
            ? $this->display_encouragement(
                $userid,
                $courseid,
                (string) $encouragement['content'],
                (string) $encouragement['category'],
                isset($encouragement['messageid']) ? (int) $encouragement['messageid'] : null,
                (string) ($encouragement['source'] ?? '')
            )
            : '';

        $template_data = [
            'userid' => $userid,
            'courseid' => $courseid,
            'has_leaderboard' => $leaderboard_html !== '',
            'leaderboard' => $leaderboard_html,
            'has_recommendations' => $recommendations_html !== '',
            'recommendations' => $recommendations_html,
            'has_encouragement' => $encouragement_html !== '',
            'encouragement' => $encouragement_html,
            'show_consent_dialog' => false,
            'consent_dialog' => '',
        ];

        if ($this->should_show_consent_dialog($userid, $courseid)) {
            $template_data['show_consent_dialog'] = true;
            $template_data['consent_dialog'] = $this->render_consent_dialog($userid, $courseid);
        }

        $PAGE->requires->js_call_amd(
            'block_attendanceleaderboard/block',
            'init',
            [$userid, $courseid]
        );

        return $OUTPUT->render_from_template(
            'block_attendanceleaderboard/block_content',
            $template_data
        );
    }

    /**
     * Render the consent dialog template.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Moodle course ID.
     * @return string
     */
    public function render_consent_dialog(int $userid, int $courseid): string {
        global $OUTPUT, $PAGE;

        $PAGE->requires->js_call_amd(
            'block_attendanceleaderboard/consent_dialog',
            'init',
            [$userid, $courseid, false]
        );

        return $OUTPUT->render_from_template(
            'block_attendanceleaderboard/consent_dialog',
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'str_title' => get_string('consent_dialog_title', 'block_attendanceleaderboard'),
                'str_body' => get_string('consent_dialog_body', 'block_attendanceleaderboard'),
                'str_agree' => get_string('consent_agree', 'block_attendanceleaderboard'),
                'str_decline' => get_string('consent_decline', 'block_attendanceleaderboard'),
                'str_notice' => get_string('consent_required_notice', 'block_attendanceleaderboard'),
            ]
        );
    }

    /**
     * Resolve the encouragement message to show.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Moodle course ID.
     * @param \block_attendanceleaderboard\profiling\learner_profile|null $profile Learner profile object.
     * @param \stdClass|null $profile_record Learner profile DB row.
     * @param string $motivation_category Category determined by the coach.
     * @return array<string,mixed>
     */
    private function resolve_encouragement_message(
        int $userid,
        int $courseid,
        ?\block_attendanceleaderboard\profiling\learner_profile $profile,
        ?\stdClass $profile_record,
        string $motivation_category
    ): array {
        if (!class_exists('\block_attendanceleaderboard\motivation\motivation_sentence_repository')) {
            return [];
        }

        try {
            $repository = new \block_attendanceleaderboard\motivation\motivation_sentence_repository();
            $performance_target = $profile_record ? (int) $profile_record->performance_category : 1;
            $motivation_target = $profile_record
                ? $this->map_motivation_level((float) $profile_record->motivation_level)
                : 1;

            $message = [];

            // Check if Gemini is configured.
            $apikey = (string) (get_config('block_attendanceleaderboard', 'gemini_apikey') ?? '');
            if (!empty($apikey) && $profile) {
                $generator = \block_attendanceleaderboard\motivation\llm_preparation::build_from_config($repository);
                $message = $generator->generate_encouragement_record($profile, $motivation_category);
            }

            // Fallback to static template if Gemini failed or is not configured
            if (empty($message['content'])) {
                $record = $repository->find_relevant_record(
                    $userid,
                    $motivation_category,
                    $performance_target,
                    $motivation_target
                );

                if ($record) {
                    $message = [
                        'messageid' => (int) $record->id,
                        'content' => (string) $record->content,
                        'category' => $motivation_category,
                        'source' => (string) $record->source,
                    ];
                } else if ($profile) {
                    $generator = \block_attendanceleaderboard\motivation\llm_preparation::build_from_config($repository);
                    $message = $generator->generate_encouragement_record($profile, $motivation_category);
                }
            }

            if (!empty($message['content'])) {
                $repository->record_delivery($userid, $courseid, (string) $message['content'], [
                    'sentenceid' => $message['messageid'] ?? null,
                    'category' => $message['category'] ?? $motivation_category,
                    'source' => $message['source'] ?? '',
                    'performance_target' => $performance_target,
                    'motivation_target' => $motivation_target,
                    'profile_version' => $profile_record ? (int) ($profile_record->profile_version ?? 0) : null,
                ]);
            }

            return $message;
        } catch (\Throwable $e) {
            debugging('DeliverySystem::resolve_encouragement_message error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Determine whether the Gemini consent dialog should be shown.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Moodle course ID.
     * @return bool
     */
    private function should_show_consent_dialog(int $userid, int $courseid): bool {
        if ($this->get_provider_type() !== 'gemini') {
            return false;
        }

        try {
            $consent_manager = new \block_attendanceleaderboard\privacy\consent_manager();
            return !$consent_manager->has_made_decision($userid, $courseid);
        } catch (\Throwable $e) {
            debugging('DeliverySystem::should_show_consent_dialog error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Get the translated category label.
     *
     * @param string $category Category identifier.
     * @return string
     */
    private function get_category_label(string $category): string {
        $map = [
            'reinforcement' => get_string('encouragement_reinforcement', 'block_attendanceleaderboard'),
            'achievement' => get_string('encouragement_achievement', 'block_attendanceleaderboard'),
            'recovery' => get_string('encouragement_recovery', 'block_attendanceleaderboard'),
            'persistence' => get_string('encouragement_persistence', 'block_attendanceleaderboard'),
        ];

        return $map[$category] ?? ucfirst($category);
    }

    /**
     * Map a motivation level score to the repository target scale.
     *
     * @param float $motivation_level Motivation level.
     * @return int
     */
    private function map_motivation_level(float $motivation_level): int {
        if ($motivation_level >= 70.0) {
            return 3;
        }

        if ($motivation_level >= 40.0) {
            return 2;
        }

        return 1;
    }

    /**
     * Return the Likert scale feedback options (1 to 5).
     *
     * @return array<int,array<string,mixed>>
     */
    private function get_likert_options(): array {
        return [
            [
                'score' => 5,
                'label' => get_string('motivation_likert_5', 'block_attendanceleaderboard'),
            ],
            [
                'score' => 4,
                'label' => get_string('motivation_likert_4', 'block_attendanceleaderboard'),
            ],
            [
                'score' => 3,
                'label' => get_string('motivation_likert_3', 'block_attendanceleaderboard'),
            ],
            [
                'score' => 2,
                'label' => get_string('motivation_likert_2', 'block_attendanceleaderboard'),
            ],
            [
                'score' => 1,
                'label' => get_string('motivation_likert_1', 'block_attendanceleaderboard'),
            ],
        ];
    }

    /**
     * Get default resources for a course when the coach is unavailable.
     *
     * @param int $courseid Moodle course ID.
     * @return array
     */
    private function get_default_resources(int $courseid): array {
        global $DB;

        try {
            $sql = "SELECT *
                      FROM {acmls_learning_resource}
                     WHERE is_active = 1
                     ORDER BY effectiveness_score DESC, access_count DESC";

            return array_values($DB->get_records_sql($sql, [], 0, 3));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Resolve the currently configured provider type.
     *
     * @return string
     */
    private function get_provider_type(): string {
        $provider = (string) (get_config('block_attendanceleaderboard', 'llm_provider') ?? '');
        return $provider === 'gemini' ? 'gemini' : 'gemini';
    }
}
