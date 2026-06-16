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
 * LLM preparation class for ACMLS.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\motivation;

defined('MOODLE_INTERNAL') || die();

use block_attendanceleaderboard\motivation\providers\gemini_provider;
use block_attendanceleaderboard\motivation\providers\llm_auth_exception;
use block_attendanceleaderboard\motivation\providers\llm_provider_interface;
use block_attendanceleaderboard\motivation\providers\llm_rate_limit_exception;
use block_attendanceleaderboard\motivation\providers\llm_timeout_exception;
use block_attendanceleaderboard\privacy\consent_manager;
use block_attendanceleaderboard\profiling\learner_profile;

/**
 * Orchestrates motivational content generation and fallback handling.
 */
class llm_preparation {

    /** @var int Maximum content length in characters. */
    public const MAX_CONTENT_LENGTH = 500;

    /** @var int Minimum content length in characters. */
    public const MIN_CONTENT_LENGTH = 10;

    /** @var int Duplicate-detection window in days. */
    public const DUPLICATE_WINDOW_DAYS = 7;

    /** @var int Maximum retry attempts for rate-limit errors. */
    public const MAX_RETRIES = 3;

    /** @var string Default hardcoded fallback content. */
    public const DEFAULT_FALLBACK =
        'Teruslah belajar dengan penuh semangat dan ketekunan untuk meraih prestasi terbaik Anda.';

    /** @var llm_provider_interface|null The LLM provider instance. */
    private ?llm_provider_interface $provider;

    /** @var motivation_sentence_repository Sentence repository instance. */
    private motivation_sentence_repository $repository;

    /** @var consent_manager Learner consent manager. */
    private consent_manager $consent_manager;

    /**
     * Constructor.
     *
     * @param llm_provider_interface|null $provider Optional provider.
     * @param motivation_sentence_repository|null $repository Optional repository.
     * @param consent_manager|null $consent_manager Optional consent manager.
     */
    public function __construct(
        ?llm_provider_interface $provider = null,
        ?motivation_sentence_repository $repository = null,
        ?consent_manager $consent_manager = null
    ) {
        $this->provider = $provider;
        $this->repository = $repository ?? new motivation_sentence_repository();
        $this->consent_manager = $consent_manager ?? new consent_manager();
    }

    /**
     * Build an llm_preparation instance from plugin configuration.
     *
     * @param motivation_sentence_repository|null $repository Optional repository.
     * @param consent_manager|null $consent_manager Optional consent manager.
     * @return self
     */
    public static function build_from_config(
        ?motivation_sentence_repository $repository = null,
        ?consent_manager $consent_manager = null
    ): self {
        return new self(
            self::build_provider_from_config(),
            $repository,
            $consent_manager
        );
    }

    /**
     * Build the configured provider instance.
     *
     * @return llm_provider_interface|null
     */
    public static function build_provider_from_config(): ?llm_provider_interface {
        $apikey = (string) (get_config('block_attendanceleaderboard', 'gemini_apikey') ?? '');
        $model = (string) (get_config('block_attendanceleaderboard', 'gemini_model') ?? 'gemini-3.5-flash');
        $timeout = (int) (get_config('block_attendanceleaderboard', 'coach_response_timeout') ?? 10);

        return new gemini_provider($apikey, $model !== '' ? $model : 'gemini-3.5-flash', max(1, $timeout));
    }

    /**
     * Generate a personalised encouragement sentence.
     *
     * @param learner_profile $profile Learner profile.
     * @param string $category Intervention category.
     * @return string
     */
    public function generate_encouragement(learner_profile $profile, string $category): string {
        $record = $this->generate_encouragement_record($profile, $category);
        return (string) $record['content'];
    }

    /**
     * Generate a personalised encouragement record with metadata.
     *
     * @param learner_profile $profile Learner profile.
     * @param string $category Intervention category.
     * @return array<string,mixed>
     */
    public function generate_encouragement_record(learner_profile $profile, string $category): array {
        if ($this->provider === null || !$this->provider->is_available()) {
            return $this->fallback_to_template_record($category, $profile->performance_category);
        }

        if ($this->requires_consent_check()
            && !$this->consent_manager->has_consent($profile->userid, $profile->courseid)) {
            return $this->fallback_to_template_record($category, $profile->performance_category);
        }

        try {
            $anonprofile = $this->anonymize_profile($profile);
            $prompt = $this->build_prompt($anonprofile, $category);
            $content = $this->call_with_retry($prompt);

            if (!$this->validate_content($content)) {
                debugging('llm_preparation: generated content failed validation, using fallback.', DEBUG_DEVELOPER);
                return $this->fallback_to_template_record($category, $profile->performance_category);
            }

            $contenthash = md5($content);
            if ($this->repository->check_duplicate($profile->userid, $contenthash, self::DUPLICATE_WINDOW_DAYS)) {
                debugging('llm_preparation: duplicate content detected, using fallback.', DEBUG_DEVELOPER);
                return $this->fallback_to_template_record($category, $profile->performance_category);
            }

            $messageid = $this->repository->save($content, [
                'category' => $category,
                'performance_target' => $profile->performance_category,
                'motivation_target' => $this->motivation_level_to_category($profile->motivation_level),
                'source' => motivation_sentence_repository::SOURCE_LLM,
                'llm_model' => $this->get_current_model_name(),
                'learner_context' => $anonprofile,
            ]);

            return [
                'messageid' => $messageid,
                'content' => $content,
                'source' => motivation_sentence_repository::SOURCE_LLM,
                'category' => $category,
                'llm_model' => $this->get_current_model_name(),
            ];
        } catch (llm_timeout_exception $e) {
            debugging('llm_preparation: Gemini request timed out - ' . $e->getMessage(), DEBUG_DEVELOPER);
        } catch (llm_auth_exception $e) {
            debugging('llm_preparation: Gemini authentication error - ' . $e->getMessage(), DEBUG_NORMAL);
            $this->notify_admin_auth_error($e->getMessage());
        } catch (\Throwable $e) {
            debugging('llm_preparation: unexpected generation error - ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $this->fallback_to_template_record($category, $profile->performance_category);
    }

    /**
     * Determine whether the current provider requires consent.
     *
     * @return bool
     */
    public function requires_consent_check(): bool {
        return self::get_configured_provider_type() === 'gemini';
    }

    /**
     * Anonymise a learner profile before external transmission.
     *
     * @param learner_profile $profile Learner profile.
     * @return array<string,mixed>
     */
    public function anonymize_profile(learner_profile $profile): array {
        $roundedmotivation = (float) (round($profile->motivation_level / 10) * 10);
        $roundedengagement = (float) round($profile->engagement_score);

        return [
            'performance_category' => $profile->performance_category,
            'motivation_level' => $roundedmotivation,
            'learning_style' => $profile->learning_style,
            'cognitive_level' => $profile->cognitive_level,
            'engagement_score' => $roundedengagement,
        ];
    }

    /**
     * Validate generated content for academic delivery.
     *
     * @param string $content Generated content.
     * @return bool
     */
    public function validate_content(string $content): bool {
        if (strlen($content) < self::MIN_CONTENT_LENGTH) {
            return false;
        }

        if (strlen($content) > self::MAX_CONTENT_LENGTH) {
            return false;
        }

        if (preg_match('/https?:\/\/|www\./i', $content)) {
            return false;
        }

        if (preg_match('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $content)) {
            return false;
        }

        if (preg_match('/[\+\(]?\d[\d\s\-\(\)]{7,}\d/', $content)) {
            return false;
        }

        foreach ($this->get_profanity_patterns() as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return fallback content from the template repository.
     *
     * @param string $category Intervention category.
     * @param int $performance_category Performance category.
     * @return string
     */
    public function fallback_to_template(string $category, int $performance_category): string {
        $record = $this->fallback_to_template_record($category, $performance_category);
        return (string) $record['content'];
    }

    /**
     * Return fallback content with metadata from the template repository.
     *
     * @param string $category Intervention category.
     * @param int $performance_category Performance category.
     * @return array<string,mixed>
     */
    public function fallback_to_template_record(string $category, int $performance_category): array {
        $motivationtarget = $this->performance_to_motivation_target($performance_category);

        $record = $this->repository->find_relevant_record(
            0,
            $category,
            $performance_category,
            $motivationtarget,
            'id'
        );
        if ($record !== null) {
            return [
                'messageid' => (int) $record->id,
                'content' => (string) $record->content,
                'source' => (string) $record->source,
                'category' => $category,
                'llm_model' => (string) ($record->llm_model ?? ''),
            ];
        }

        $this->repository->seed_static_templates();
        $record = $this->repository->find_relevant_record(
            0,
            $category,
            $performance_category,
            $motivationtarget,
            'id'
        );
        if ($record !== null) {
            return [
                'messageid' => (int) $record->id,
                'content' => (string) $record->content,
                'source' => (string) $record->source,
                'category' => $category,
                'llm_model' => (string) ($record->llm_model ?? ''),
            ];
        }

        return [
            'messageid' => null,
            'content' => self::DEFAULT_FALLBACK,
            'source' => motivation_sentence_repository::SOURCE_TEMPLATE,
            'category' => $category,
            'llm_model' => '',
        ];
    }

    /**
     * Build an Indonesian formal-academic prompt for the LLM.
     *
     * @param array<string,mixed> $anon_profile Anonymised profile.
     * @param string $category Intervention category.
     * @return string
     */
    private function build_prompt(array $anon_profile, string $category): string {
        $performancelabel = $this->performance_category_label((int) ($anon_profile['performance_category'] ?? 1));
        $motivationlabel = $this->motivation_level_label((float) ($anon_profile['motivation_level'] ?? 50.0));
        $context = $this->build_context_description($anon_profile, $category);

        return "Anda adalah asisten motivasional akademik. Hasilkan satu kalimat motivasional\n" .
            "dalam bahasa Indonesia formal untuk mahasiswa dengan kondisi berikut:\n" .
            "- Kategori performa: {$performancelabel}\n" .
            "- Tingkat motivasi: {$motivationlabel}\n" .
            "- Kategori intervensi: {$category}\n" .
            "- Konteks: {$context}\n\n" .
            "Kalimat harus singkat (1-2 kalimat), positif, akademik, mendukung semangat belajar,\n" .
            "dan tidak mengandung informasi yang menyesatkan.";
    }

    /**
     * Call the LLM provider with exponential backoff on rate limits.
     *
     * @param string $prompt Prompt text.
     * @param int $max_retries Maximum attempts.
     * @return string
     * @throws llm_auth_exception
     * @throws llm_rate_limit_exception
     * @throws llm_timeout_exception
     */
    private function call_with_retry(string $prompt, int $max_retries = self::MAX_RETRIES): string {
        for ($attempt = 0; $attempt < $max_retries; $attempt++) {
            try {
                return $this->provider->generate($prompt);
            } catch (llm_rate_limit_exception $e) {
                if ($attempt >= $max_retries - 1) {
                    throw $e;
                }

                sleep((int) pow(2, $attempt));
            }
        }

        throw new llm_rate_limit_exception('LLM rate limit: all retries exhausted.');
    }

    /**
     * Send an admin notification about an LLM authentication error.
     *
     * @param string $error_message Error message.
     * @return void
     */
    private function notify_admin_auth_error(string $error_message): void {
        $admins = get_admins();
        if (empty($admins)) {
            return;
        }

        $subject = 'ACMLS: Gemini Authentication Error';
        $message = "The ACMLS Gemini provider encountered an authentication error and has fallen back " .
            "to static templates.\n\nError: {$error_message}\n\n" .
            "Please check the Gemini API key configuration in the ACMLS plugin settings.";

        $noreplyuser = \core_user::get_noreply_user();
        foreach ($admins as $admin) {
            try {
                $eventdata = new \core\message\message();
                $eventdata->component = 'block_attendanceleaderboard';
                $eventdata->name = 'llm_auth_error';
                $eventdata->userfrom = $noreplyuser;
                $eventdata->userto = $admin;
                $eventdata->subject = $subject;
                $eventdata->fullmessage = $message;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml = '<p>' . nl2br(s($message)) . '</p>';
                $eventdata->smallmessage = $subject;
                $eventdata->notification = 1;

                message_send($eventdata);
            } catch (\Throwable $e) {
                debugging(
                    'llm_preparation: failed to send admin auth notification: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
    }

    /**
     * Convert performance_category to a label.
     *
     * @param int $performance_category Performance category.
     * @return string
     */
    private function performance_category_label(int $performance_category): string {
        $labels = [
            learner_profile::PERFORMANCE_LOW => 'Low',
            learner_profile::PERFORMANCE_MIDDLE => 'Middle',
            learner_profile::PERFORMANCE_HIGH => 'High',
        ];

        return $labels[$performance_category] ?? 'Low';
    }

    /**
     * Convert motivation level to a label.
     *
     * @param float $motivation_level Motivation level.
     * @return string
     */
    private function motivation_level_label(float $motivation_level): string {
        if ($motivation_level >= 70.0) {
            return 'High';
        }

        if ($motivation_level >= 40.0) {
            return 'Middle';
        }

        return 'Low';
    }

    /**
     * Convert motivation level to a target category.
     *
     * @param float $motivation_level Motivation level.
     * @return int
     */
    private function motivation_level_to_category(float $motivation_level): int {
        if ($motivation_level >= 70.0) {
            return 3;
        }

        if ($motivation_level >= 40.0) {
            return 2;
        }

        return 1;
    }

    /**
     * Map performance category to a template motivation target.
     *
     * @param int $performance_category Performance category.
     * @return int
     */
    private function performance_to_motivation_target(int $performance_category): int {
        $map = [
            learner_profile::PERFORMANCE_LOW => 1,
            learner_profile::PERFORMANCE_MIDDLE => 2,
            learner_profile::PERFORMANCE_HIGH => 3,
        ];

        return $map[$performance_category] ?? 1;
    }

    /**
     * Build a human-readable context description.
     *
     * @param array<string,mixed> $anon_profile Anonymised profile.
     * @param string $category Intervention category.
     * @return string
     */
    private function build_context_description(array $anon_profile, string $category): string {
        $learningstyle = (string) ($anon_profile['learning_style'] ?? 'unknown');
        $cognitive = (int) ($anon_profile['cognitive_level'] ?? 1);
        $engagement = (float) ($anon_profile['engagement_score'] ?? 0.0);

        $cognitivelabel = ['', 'rendah', 'menengah', 'tinggi'][$cognitive] ?? 'rendah';

        return implode(', ', [
            "mahasiswa dengan gaya belajar {$learningstyle}",
            "tingkat kognitif {$cognitivelabel}",
            "skor keterlibatan {$engagement}",
            "memerlukan intervensi kategori {$category}",
        ]) . '.';
    }

    /**
     * Return profanity detection patterns.
     *
     * @return string[]
     */
    private function get_profanity_patterns(): array {
        return [
            '/\bf[u\*]ck/i',
            '/\bsh[i\*]t/i',
            '/\bb[i\*]tch/i',
            '/\bass[h\s]?hole/i',
            '/\bdamn\b/i',
            '/\bcrap\b/i',
            '/\bbodoh\b/i',
            '/\bidiot\b/i',
            '/\bkontol\b/i',
            '/\bmemek\b/i',
            '/\bbajingan\b/i',
            '/\bbrengsek\b/i',
            '/\bkeparat\b/i',
            '/\bsial\b/i',
            '/\bbangsat\b/i',
            '/\banjing\b/i',
            '/\bbabi\b/i',
        ];
    }

    /**
     * Resolve the current provider type.
     *
     * @return string
     */
    private static function get_configured_provider_type(): string {
        $provider = (string) (get_config('block_attendanceleaderboard', 'llm_provider') ?? '');
        return $provider === 'gemini' ? 'gemini' : 'gemini';
    }

    /**
     * Return the configured model name for metadata storage.
     *
     * @return string|null
     */
    private function get_current_model_name(): ?string {
        if (self::get_configured_provider_type() !== 'gemini') {
            return null;
        }

        $model = (string) (get_config('block_attendanceleaderboard', 'gemini_model') ?? '');
        return $model !== '' ? $model : 'gemini-3.5-flash';
    }
}
