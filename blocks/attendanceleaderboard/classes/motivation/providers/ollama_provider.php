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
 * Ollama LLM Provider for ACMLS.
 *
 * Implements the llm_provider_interface using a locally-hosted Ollama instance.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\motivation\providers;

defined('MOODLE_INTERNAL') || die();

/**
 * Ollama provider implementation for ACMLS LLM content generation.
 *
 * Sends prompts to a locally-hosted Ollama instance via its REST API and
 * returns the generated text. Throws typed exceptions for authentication
 * errors, rate limits, and timeouts.
 *
 * Requirements addressed:
 * - Req 7.1: Generate personalised Encouragement_Content via LLM.
 * - Req 7.6: Fallback on timeout (>10 s), auth error, and rate limit.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ollama_provider implements llm_provider_interface {

    /** @var string Ollama API endpoint base URL. */
    private string $endpoint;

    /** @var string Ollama model identifier. */
    private string $model;

    /** @var int Request timeout in seconds. */
    private int $timeout_seconds;

    /**
     * Constructor.
     *
     * @param string $endpoint        Ollama base URL (default: http://localhost:11434).
     * @param string $model           Ollama model to use (default: llama3.2).
     * @param int    $timeout_seconds Request timeout in seconds (default: 10).
     */
    public function __construct(
        string $endpoint = 'http://localhost:11434',
        string $model = 'llama3.2',
        int $timeout_seconds = 10
    ) {
        $this->endpoint        = rtrim($endpoint, '/');
        $this->model           = $model;
        $this->timeout_seconds = $timeout_seconds;
    }

    /**
     * Generate text content from the Ollama API.
     *
     * POSTs the prompt to {endpoint}/api/generate and returns the 'response'
     * field from the JSON response.
     *
     * @param  string $prompt  The prompt to send.
     * @param  array  $options Optional overrides (currently unused for Ollama).
     * @return string          Generated text content.
     * @throws llm_auth_exception       On HTTP 401.
     * @throws llm_rate_limit_exception On HTTP 429.
     * @throws llm_timeout_exception    On cURL timeout (CURLE_OPERATION_TIMEDOUT).
     * @throws \RuntimeException        On other HTTP errors or malformed responses.
     */
    public function generate(string $prompt, array $options = []): string {
        $url  = $this->endpoint . '/api/generate';
        $body = json_encode([
            'model'  => $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout_seconds,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
        ]);

        $response   = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $http_code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Handle cURL-level timeout.
        if ($curl_errno === CURLE_OPERATION_TIMEDOUT) {
            throw new llm_timeout_exception(
                'Ollama request timed out after ' . $this->timeout_seconds . ' seconds.'
            );
        }

        if ($response === false) {
            throw new \RuntimeException('Ollama cURL request failed: errno ' . $curl_errno);
        }

        // Handle HTTP-level errors.
        if ($http_code === 401) {
            throw new llm_auth_exception('Ollama authentication failed (HTTP 401).');
        }

        if ($http_code === 429) {
            throw new llm_rate_limit_exception('Ollama rate limit exceeded (HTTP 429).');
        }

        if ($http_code < 200 || $http_code >= 300) {
            throw new \RuntimeException('Ollama API returned HTTP ' . $http_code . ': ' . $response);
        }

        // Parse the JSON response.
        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['response'])) {
            throw new \RuntimeException('Ollama API returned an unexpected response format.');
        }

        return (string) $data['response'];
    }

    /**
     * Check whether the Ollama instance is reachable.
     *
     * Performs a GET request to {endpoint}/api/tags and returns true if the
     * response is HTTP 200.
     *
     * @return bool True if the Ollama instance responds with HTTP 200.
     */
    public function is_available(): bool {
        $url = $this->endpoint . '/api/tags';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_NOBODY         => false,
        ]);

        curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $http_code === 200;
    }
}
