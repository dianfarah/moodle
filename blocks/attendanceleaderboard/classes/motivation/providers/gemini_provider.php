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
 * Gemini LLM provider for ACMLS.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\motivation\providers;

defined('MOODLE_INTERNAL') || die();

/**
 * Gemini provider implementation for ACMLS motivational content generation.
 */
class gemini_provider implements llm_provider_interface {

    /** @var string Gemini REST API base URL. */
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** @var string API key. */
    private string $api_key;

    /** @var string Model identifier. */
    private string $model;

    /** @var int Request timeout in seconds. */
    private int $timeout_seconds;

    /**
     * Constructor.
     *
     * @param string $api_key Gemini API key.
     * @param string $model Gemini model identifier.
     * @param int $timeout_seconds Request timeout.
     */
    public function __construct(
        string $api_key = '',
        string $model = 'gemini-3.5-flash',
        int $timeout_seconds = 10
    ) {
        $this->api_key = $api_key;
        $this->model = $model;
        $this->timeout_seconds = $timeout_seconds;
    }

    /**
     * Generate text content from the Gemini API.
     *
     * @param string $prompt Prompt text.
     * @param array $options Optional generation config.
     * @return string
     * @throws llm_auth_exception
     * @throws llm_rate_limit_exception
     * @throws llm_timeout_exception
     */
    public function generate(string $prompt, array $options = []): string {
        $maxoutputtokens = (int) ($options['max_tokens'] ?? 200);
        $temperature = (float) ($options['temperature'] ?? 0.7);

        $url = self::API_BASE . rawurlencode($this->model) .
            ':generateContent?key=' . urlencode($this->api_key);

        $body = json_encode([
            'contents' => [[
                'parts' => [[
                    'text' => $prompt,
                ]],
            ]],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxoutputtokens,
            ],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout_seconds,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $curlerrno = curl_errno($ch);
        $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlerrno === CURLE_OPERATION_TIMEDOUT) {
            throw new llm_timeout_exception(
                'Gemini request timed out after ' . $this->timeout_seconds . ' seconds.'
            );
        }

        if ($response === false) {
            throw new \RuntimeException('Gemini cURL request failed: errno ' . $curlerrno);
        }

        if ($httpcode === 401 || $httpcode === 403) {
            throw new llm_auth_exception('Gemini authentication failed (HTTP ' . $httpcode . ').');
        }

        if ($httpcode === 429) {
            throw new llm_rate_limit_exception('Gemini rate limit exceeded (HTTP 429).');
        }

        if ($httpcode < 200 || $httpcode >= 300) {
            throw new \RuntimeException('Gemini API returned HTTP ' . $httpcode . ': ' . $response);
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['candidates'][0]['content']['parts'])) {
            throw new \RuntimeException('Gemini API returned an unexpected response format.');
        }

        $parts = $data['candidates'][0]['content']['parts'];
        $texts = [];
        foreach ($parts as $part) {
            if (!empty($part['text'])) {
                $texts[] = (string) $part['text'];
            }
        }

        $content = trim(implode("\n", $texts));
        if ($content === '') {
            throw new \RuntimeException('Gemini API returned empty content.');
        }

        return $content;
    }

    /**
     * Check whether this provider is available.
     *
     * @return bool
     */
    public function is_available(): bool {
        return trim($this->api_key) !== '';
    }
}
