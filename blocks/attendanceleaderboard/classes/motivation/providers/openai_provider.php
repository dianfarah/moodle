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
 * OpenAI LLM Provider for ACMLS.
 *
 * Implements the llm_provider_interface using the OpenAI Chat Completions API.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\motivation\providers;

defined('MOODLE_INTERNAL') || die();

/**
 * OpenAI provider implementation for ACMLS LLM content generation.
 *
 * Sends prompts to the OpenAI Chat Completions API and returns the generated
 * text. Throws typed exceptions for authentication errors, rate limits, and
 * timeouts so that LLMPreparation can apply the correct fallback strategy.
 *
 * Requirements addressed:
 * - Req 7.1: Generate personalised Encouragement_Content via LLM.
 * - Req 7.6: Fallback on timeout (>10 s), auth error, and rate limit.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openai_provider implements llm_provider_interface {

    /** @var string OpenAI Chat Completions API endpoint. */
    const API_URL = 'https://api.openai.com/v1/chat/completions';

    /** @var string OpenAI API key. */
    private string $api_key;

    /** @var string OpenAI model identifier. */
    private string $model;

    /** @var int Request timeout in seconds. */
    private int $timeout_seconds;

    /**
     * Constructor.
     *
     * @param string $api_key         OpenAI API key (empty string = not configured).
     * @param string $model           OpenAI model to use (default: gpt-4o-mini).
     * @param int    $timeout_seconds Request timeout in seconds (default: 10).
     */
    public function __construct(
        string $api_key = '',
        string $model = 'gpt-4o-mini',
        int $timeout_seconds = 10
    ) {
        $this->api_key         = $api_key;
        $this->model           = $model;
        $this->timeout_seconds = $timeout_seconds;
    }

    /**
     * Generate text content from the OpenAI API.
     *
     * POSTs the prompt to the Chat Completions endpoint and returns the
     * content from choices[0].message.content.
     *
     * @param  string $prompt  The prompt to send.
     * @param  array  $options Optional overrides: 'max_tokens' (int), 'temperature' (float).
     * @return string          Generated text content.
     * @throws llm_auth_exception       On HTTP 401 (invalid API key).
     * @throws llm_rate_limit_exception On HTTP 429 (rate limit exceeded).
     * @throws llm_timeout_exception    On cURL timeout (CURLE_OPERATION_TIMEDOUT).
     * @throws \RuntimeException        On other HTTP errors or malformed responses.
     */
    public function generate(string $prompt, array $options = []): string {
        $max_tokens  = (int) ($options['max_tokens'] ?? 200);
        $temperature = (float) ($options['temperature'] ?? 0.7);

        $body = json_encode([
            'model'       => $this->model,
            'messages'    => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens'  => $max_tokens,
            'temperature' => $temperature,
        ]);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout_seconds,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
            ],
        ]);

        $response  = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $http_code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle cURL-level timeout.
        if ($curl_errno === CURLE_OPERATION_TIMEDOUT) {
            throw new llm_timeout_exception(
                'OpenAI request timed out after ' . $this->timeout_seconds . ' seconds.'
            );
        }

        if ($response === false) {
            throw new \RuntimeException('OpenAI cURL request failed: errno ' . $curl_errno);
        }

        // Handle HTTP-level errors.
        if ($http_code === 401) {
            throw new llm_auth_exception('OpenAI authentication failed: invalid API key (HTTP 401).');
        }

        if ($http_code === 429) {
            throw new llm_rate_limit_exception('OpenAI rate limit exceeded (HTTP 429).');
        }

        if ($http_code < 200 || $http_code >= 300) {
            throw new \RuntimeException('OpenAI API returned HTTP ' . $http_code . ': ' . $response);
        }

        // Parse the JSON response.
        $data = json_decode($response, true);

        if (!is_array($data)
            || !isset($data['choices'][0]['message']['content'])
        ) {
            throw new \RuntimeException('OpenAI API returned an unexpected response format.');
        }

        return (string) $data['choices'][0]['message']['content'];
    }

    /**
     * Check whether this provider is available (i.e., an API key is configured).
     *
     * @return bool True if the API key is non-empty.
     */
    public function is_available(): bool {
        return $this->api_key !== '';
    }
}
