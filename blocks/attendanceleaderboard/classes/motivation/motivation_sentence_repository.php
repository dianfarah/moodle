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
 * Motivation Sentence Repository for ACMLS.
 *
 * Stores and retrieves motivational encouragement content, tracks delivery
 * history to prevent duplicate delivery, and manages capacity notifications.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\motivation;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository for acmls_motivation_sentence table.
 *
 * Provides CRUD operations, profile-based content retrieval, duplicate
 * detection, and capacity monitoring for motivational content.
 *
 * Requirements addressed:
 * - Req 8.1: Store Encouragement_Content indexed by category, performance_target,
 *            motivation_target, and learning context.
 * - Req 8.2: Return most relevant content within ≤2 seconds.
 * - Req 8.3: Maintain delivery history to prevent excessive repetition.
 * - Req 8.4: Support CRUD operations for administrator management.
 * - Req 8.5: Send admin notification when capacity reaches 80%.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class motivation_sentence_repository {

    /** @var string Database table name (without Moodle prefix). */
    const TABLE = 'acmls_motivation_sentence';

    /** @var string Learner record table for delivery history. */
    const TABLE_LEARNER_RECORD = 'acmls_learner_record';

    /** @var string Record type for motivation delivery in acmls_learner_record. */
    const RECORD_TYPE_DELIVERY = 'motivation_delivery';

    /** @var string Source type for LLM-generated content. */
    const SOURCE_LLM = 'llm';

    /** @var string Source type for static template content. */
    const SOURCE_TEMPLATE = 'template';

    /** @var int Default capacity threshold for admin notification (records). */
    const DEFAULT_MAX_RECORDS = 10000;

    /** @var float Capacity percentage threshold for admin notification. */
    const CAPACITY_ALERT_THRESHOLD = 80.0;

    // =========================================================================
    // Public API — Core methods
    // =========================================================================

    /**
     * Save a new motivation sentence to the repository.
     *
     * Inserts a new record into acmls_motivation_sentence with the provided
     * content and metadata.
     *
     * @param  string $content  The motivational content text.
     * @param  array  $metadata Associative array with fields:
     *                          - category (string): reinforcement|achievement|recovery|persistence
     *                          - performance_target (int): 1=Low, 2=Middle, 3=High
     *                          - motivation_target (int): 1=Low, 2=Middle, 3=High
     *                          - language (string, optional): default 'id'
     *                          - source (string, optional): 'llm' or 'template'
     *                          - llm_model (string, optional)
     *                          - learner_context (array, optional): anonymised context
     * @return int              Inserted record ID.
     */
    public function save(string $content, array $metadata): int {
        global $DB;

        $record = new \stdClass();
        $record->category           = (string) ($metadata['category'] ?? 'reinforcement');
        $record->performance_target = (int) ($metadata['performance_target'] ?? 1);
        $record->motivation_target  = (int) ($metadata['motivation_target'] ?? 1);
        $record->content            = $content;
        $record->language           = (string) ($metadata['language'] ?? 'id');
        $record->source             = (string) ($metadata['source'] ?? self::SOURCE_LLM);
        $record->llm_model          = isset($metadata['llm_model']) ? (string) $metadata['llm_model'] : null;
        $record->learner_context    = isset($metadata['learner_context'])
            ? json_encode($metadata['learner_context'])
            : null;
        $record->is_active          = 1;
        $record->usage_count        = 0;
        $record->timecreated        = time();

        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Find the most relevant active motivation sentence for a Learner.
     *
     * Logic:
     * 1. Get delivery history (content hashes sent in last 7 days).
     * 2. Query active sentences matching category + performance_target +
     *    motivation_target + language.
     * 3. Exclude sentences whose content hash is in delivery history.
     * 4. Order by usage_count ASC (prefer less-used content).
     * 5. Return content of first result, or null if none found.
     *
     * @param  int    $userid             Moodle user ID.
     * @param  string $category           Content category (recovery|persistence|reinforcement|achievement).
     * @param  int    $performance_target Target performance category (1=Low, 2=Middle, 3=High).
     * @param  int    $motivation_target  Target motivation level (1=Low, 2=Middle, 3=High).
     * @param  string $language           Language code (default: 'id').
     * @return string|null                Content string, or null if no suitable content found.
     */
    public function find_relevant(
        int $userid,
        string $category,
        int $performance_target,
        int $motivation_target,
        string $language = 'id'
    ): ?string {
        $record = $this->find_relevant_record(
            $userid,
            $category,
            $performance_target,
            $motivation_target,
            $language
        );

        return $record ? (string) $record->content : null;
    }

    /**
     * Find the most relevant active motivation sentence record for a learner.
     *
     * @param  int    $userid Moodle user ID.
     * @param  string $category Content category.
     * @param  int    $performance_target Target performance category.
     * @param  int    $motivation_target Target motivation level.
     * @param  string $language Language code.
     * @return \stdClass|null
     */
    public function find_relevant_record(
        int $userid,
        string $category,
        int $performance_target,
        int $motivation_target,
        string $language = 'id'
    ): ?\stdClass {
        global $DB;

        $recent_hashes = $this->get_delivery_history($userid, 7);

        $sql = "SELECT id, category, performance_target, motivation_target, content,
                       language, source, llm_model, usage_count
                  FROM {" . self::TABLE . "}
                 WHERE category = :category
                   AND performance_target = :performance_target
                   AND motivation_target = :motivation_target
                   AND language = :language
                   AND is_active = 1
                 ORDER BY usage_count ASC, timecreated ASC";

        $params = [
            'category' => $category,
            'performance_target' => $performance_target,
            'motivation_target' => $motivation_target,
            'language' => $language,
        ];

        $sentences = $DB->get_records_sql($sql, $params);
        if (empty($sentences)) {
            return null;
        }

        foreach ($sentences as $sentence) {
            if (in_array(md5($sentence->content), $recent_hashes, true)) {
                continue;
            }

            return $sentence;
        }

        return null;
    }

    /**
     * Get content hashes of motivation sentences delivered to a user in the last N days.
     *
     * Queries acmls_learner_record for records with record_type='motivation_delivery'
     * for this user in the last N days, and extracts content_hash from data_payload.
     *
     * @param  int $userid Moodle user ID.
     * @param  int $days   Number of days to look back (default: 7).
     * @return string[]    Array of md5 content hashes.
     */
    public function get_delivery_history(int $userid, int $days = 7): array {
        global $DB;

        $since = time() - ($days * DAYSECS);

        $sql = "SELECT id, data_payload
                  FROM {" . self::TABLE_LEARNER_RECORD . "}
                 WHERE userid = :userid
                   AND record_type = :record_type
                   AND timecreated >= :since";

        $params = [
            'userid'      => $userid,
            'record_type' => self::RECORD_TYPE_DELIVERY,
            'since'       => $since,
        ];

        $records = $DB->get_records_sql($sql, $params);

        $hashes = [];
        foreach ($records as $record) {
            $payload = json_decode($record->data_payload, true);
            if (is_array($payload) && isset($payload['content_hash'])) {
                $hashes[] = (string) $payload['content_hash'];
            }
        }

        return $hashes;
    }

    /**
     * Check whether the same content has been delivered to a Learner in the last N days.
     *
     * Uses md5() to hash the content and checks against delivery history.
     *
     * @param  int    $userid       Moodle user ID.
     * @param  string $content_hash MD5 hash of the content to check.
     * @param  int    $days         Number of days to look back (default: 7).
     * @return bool                 True if the same content was sent within the period.
     */
    public function check_duplicate(int $userid, string $content_hash, int $days = 7): bool {
        $recent_hashes = $this->get_delivery_history($userid, $days);
        return in_array($content_hash, $recent_hashes, true);
    }

    // =========================================================================
    // Public API — CRUD operations
    // =========================================================================

    /**
     * Create a new motivation sentence record.
     *
     * @param  array $data Associative array with fields matching acmls_motivation_sentence.
     * @return int         Inserted record ID.
     */
    public function crud_create(array $data): int {
        global $DB;

        $record = new \stdClass();
        $record->category           = (string) ($data['category'] ?? 'reinforcement');
        $record->performance_target = (int) ($data['performance_target'] ?? 1);
        $record->motivation_target  = (int) ($data['motivation_target'] ?? 1);
        $record->content            = (string) ($data['content'] ?? '');
        $record->language           = (string) ($data['language'] ?? 'id');
        $record->source             = (string) ($data['source'] ?? self::SOURCE_LLM);
        $record->llm_model          = isset($data['llm_model']) ? (string) $data['llm_model'] : null;
        $record->learner_context    = isset($data['learner_context'])
            ? (is_array($data['learner_context']) ? json_encode($data['learner_context']) : (string) $data['learner_context'])
            : null;
        $record->is_active          = (int) ($data['is_active'] ?? 1);
        $record->usage_count        = (int) ($data['usage_count'] ?? 0);
        $record->timecreated        = (int) ($data['timecreated'] ?? time());

        return (int) $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Read a motivation sentence record by ID.
     *
     * @param  int        $id Record ID.
     * @return array|null     Associative array of record fields, or null if not found.
     */
    public function crud_read(int $id): ?array {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $id], '*', IGNORE_MISSING);

        if (!$record) {
            return null;
        }

        return (array) $record;
    }

    /**
     * Update fields of an existing motivation sentence record.
     *
     * @param  int   $id   Record ID.
     * @param  array $data Associative array of fields to update.
     * @return bool        True on success, false if record not found.
     */
    public function crud_update(int $id, array $data): bool {
        global $DB;

        if (!$DB->record_exists(self::TABLE, ['id' => $id])) {
            return false;
        }

        $record     = new \stdClass();
        $record->id = $id;

        $allowed = [
            'category', 'performance_target', 'motivation_target',
            'content', 'language', 'source', 'llm_model',
            'learner_context', 'is_active', 'usage_count',
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'learner_context' && is_array($data[$field])) {
                    $record->$field = json_encode($data[$field]);
                } else {
                    $record->$field = $data[$field];
                }
            }
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * Soft-delete a motivation sentence by marking it inactive.
     *
     * Sets is_active = 0 rather than physically deleting the record,
     * preserving historical data for analytics.
     *
     * @param  int  $id Record ID.
     * @return bool     True on success, false if record not found.
     */
    public function crud_delete(int $id): bool {
        global $DB;

        if (!$DB->record_exists(self::TABLE, ['id' => $id])) {
            return false;
        }

        $record           = new \stdClass();
        $record->id       = $id;
        $record->is_active = 0;

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    // =========================================================================
    // Public API — Capacity monitoring
    // =========================================================================

    /**
     * Check the current capacity usage of the motivation sentence table.
     *
     * Returns the percentage of max_records currently used. If the percentage
     * is >= CAPACITY_ALERT_THRESHOLD (80%), sends an admin notification.
     *
     * @param  int   $max_records Maximum allowed records (default: 10000).
     * @return float              Percentage of capacity used (0.0–100.0+).
     */
    public function check_capacity(int $max_records = self::DEFAULT_MAX_RECORDS): float {
        global $DB;

        $count = (int) $DB->count_records(self::TABLE);

        if ($max_records <= 0) {
            return 0.0;
        }

        $percentage = ($count / $max_records) * 100.0;

        if ($percentage >= self::CAPACITY_ALERT_THRESHOLD) {
            $this->send_capacity_notification($count, $max_records, $percentage);
        }

        return $percentage;
    }

    /**
     * Increment the usage_count for a motivation sentence record.
     *
     * @param  int  $id Record ID.
     * @return void
     */
    public function increment_usage(int $id): void {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $id], 'id, usage_count', IGNORE_MISSING);
        if (!$record) {
            return;
        }

        $update               = new \stdClass();
        $update->id           = $id;
        $update->usage_count  = (int) $record->usage_count + 1;

        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Record that a motivation message was delivered to a learner.
     *
     * @param int $userid Moodle user ID.
     * @param int $courseid Moodle course ID.
     * @param string $content Delivered content.
     * @param array $metadata Delivery metadata.
     * @return void
     */
    public function record_delivery(int $userid, int $courseid, string $content, array $metadata = []): void {
        global $DB;

        $payload = [
            'content_hash' => md5($content),
            'sentenceid' => isset($metadata['sentenceid']) ? (int) $metadata['sentenceid'] : null,
            'category' => (string) ($metadata['category'] ?? ''),
            'source' => (string) ($metadata['source'] ?? ''),
            'performance_target' => isset($metadata['performance_target']) ? (int) $metadata['performance_target'] : null,
            'motivation_target' => isset($metadata['motivation_target']) ? (int) $metadata['motivation_target'] : null,
        ];

        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->record_type = self::RECORD_TYPE_DELIVERY;
        $record->source_component = 'motivation';
        $record->data_payload = json_encode($payload);
        $record->profile_version = isset($metadata['profile_version']) ? (int) $metadata['profile_version'] : null;
        $record->timecreated = time();

        $DB->insert_record(self::TABLE_LEARNER_RECORD, $record);

        if (!empty($metadata['sentenceid'])) {
            $this->increment_usage((int) $metadata['sentenceid']);
        }
    }

    // =========================================================================
    // Public API — Static seed data (Task 8.8)
    // =========================================================================

    /**
     * Seed static motivational template sentences for all category × performance combinations.
     *
     * Inserts template sentences for all 4 categories × 3 performance levels if they
     * don't already exist (checked by category + performance_target + source='template').
     *
     * Categories: recovery, persistence, reinforcement, achievement
     * Performance levels: 1=Low, 2=Middle, 3=High
     *
     * @return void
     */
    public function seed_static_templates(): void {
        global $DB;

        $templates = $this->get_static_templates();

        foreach ($templates as $template) {
            // Check if a template already exists for this category + performance_target.
            $exists = $DB->record_exists(self::TABLE, [
                'category'           => $template['category'],
                'performance_target' => $template['performance_target'],
                'source'             => self::SOURCE_TEMPLATE,
            ]);

            if (!$exists) {
                $this->crud_create($template);
            }
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Send an admin notification about capacity threshold being reached.
     *
     * Uses Moodle's messaging API to notify site administrators.
     *
     * @param  int   $count       Current record count.
     * @param  int   $max_records Maximum allowed records.
     * @param  float $percentage  Current usage percentage.
     * @return void
     */
    private function send_capacity_notification(int $count, int $max_records, float $percentage): void {
        global $DB;

        // Get all site administrators.
        $admins = get_admins();

        if (empty($admins)) {
            return;
        }

        $subject = get_string('motivation_capacity_subject', 'block_attendanceleaderboard',
            round($percentage, 1));
        $message = get_string('motivation_capacity_message', 'block_attendanceleaderboard', [
            'count'      => $count,
            'max'        => $max_records,
            'percentage' => round($percentage, 1),
        ]);

        // Fallback to plain text if language strings are not available.
        if (strpos($subject, '[[') !== false || $subject === 'motivation_capacity_subject') {
            $subject = "ACMLS: Motivation Sentence Repository capacity at " . round($percentage, 1) . "%";
            $message = "The motivation sentence repository currently contains {$count} records " .
                       "({$max_records} max), which is " . round($percentage, 1) . "% of capacity. " .
                       "Consider archiving or removing old entries.";
        }

        $noreply_user = \core_user::get_noreply_user();

        foreach ($admins as $admin) {
            try {
                $eventdata                    = new \core\message\message();
                $eventdata->component         = 'block_attendanceleaderboard';
                $eventdata->name              = 'motivation_capacity_alert';
                $eventdata->userfrom          = $noreply_user;
                $eventdata->userto            = $admin;
                $eventdata->subject           = $subject;
                $eventdata->fullmessage       = $message;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml   = '<p>' . htmlspecialchars($message) . '</p>';
                $eventdata->smallmessage      = $subject;
                $eventdata->notification      = 1;

                message_send($eventdata);
            } catch (\Throwable $e) {
                debugging(
                    'motivation_sentence_repository: failed to send capacity notification: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
    }

    /**
     * Return the static template data for all category × performance combinations.
     *
     * @return array[] Array of template data arrays.
     */
    private function get_static_templates(): array {
        return [
            // recovery × Low
            [
                'category'           => 'recovery',
                'performance_target' => 1,
                'motivation_target'  => 1,
                'content'            => 'Jangan menyerah! Setiap langkah kecil membawa Anda lebih dekat ke tujuan akademik Anda.',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // recovery × Middle
            [
                'category'           => 'recovery',
                'performance_target' => 2,
                'motivation_target'  => 1,
                'content'            => 'Anda memiliki potensi yang besar. Teruslah berusaha dan hasil yang lebih baik akan datang.',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // recovery × High
            [
                'category'           => 'recovery',
                'performance_target' => 3,
                'motivation_target'  => 1,
                'content'            => 'Bahkan yang terbaik pun mengalami masa sulit. Gunakan pengalaman ini untuk tumbuh lebih kuat.',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // persistence × Low
            [
                'category'           => 'persistence',
                'performance_target' => 1,
                'motivation_target'  => 2,
                'content'            => 'Ketekunan adalah kunci keberhasilan. Teruslah belajar meskipun terasa sulit.',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // persistence × Middle
            [
                'category'           => 'persistence',
                'performance_target' => 2,
                'motivation_target'  => 2,
                'content'            => 'Konsistensi dalam belajar akan membawa Anda ke tingkat yang lebih tinggi.',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // persistence × High
            [
                'category'           => 'persistence',
                'performance_target' => 3,
                'motivation_target'  => 2,
                'content'            => 'Pertahankan semangat belajar Anda. Kesuksesan adalah hasil dari ketekunan yang berkelanjutan.',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // reinforcement × Low
            [
                'category'           => 'reinforcement',
                'performance_target' => 1,
                'motivation_target'  => 2,
                'content'            => 'Anda telah menunjukkan kemajuan yang berarti. Teruslah berlatih untuk mencapai hasil yang lebih baik.',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // reinforcement × Middle
            [
                'category'           => 'reinforcement',
                'performance_target' => 2,
                'motivation_target'  => 2,
                'content'            => 'Kerja keras Anda mulai membuahkan hasil. Pertahankan momentum ini!',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // reinforcement × High
            [
                'category'           => 'reinforcement',
                'performance_target' => 3,
                'motivation_target'  => 2,
                'content'            => 'Prestasi Anda sangat membanggakan. Teruslah tingkatkan standar Anda.',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // achievement × Low
            [
                'category'           => 'achievement',
                'performance_target' => 1,
                'motivation_target'  => 3,
                'content'            => 'Selamat atas pencapaian Anda! Ini adalah bukti bahwa kerja keras selalu terbayar.',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // achievement × Middle
            [
                'category'           => 'achievement',
                'performance_target' => 2,
                'motivation_target'  => 3,
                'content'            => 'Pencapaian Anda hari ini adalah fondasi kesuksesan masa depan. Teruslah berprestasi!',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
            // achievement × High
            [
                'category'           => 'achievement',
                'performance_target' => 3,
                'motivation_target'  => 3,
                'content'            => 'Luar biasa! Anda telah mencapai standar tertinggi. Jadilah inspirasi bagi rekan-rekan Anda.',
                'language'           => 'id',
                'source'             => self::SOURCE_TEMPLATE,
            ],
        ];
    }
}
