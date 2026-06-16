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
 * LLM Provider Interface for ACMLS.
 *
 * Defines the contract that all LLM provider implementations must fulfil.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\motivation\providers;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface for LLM provider implementations.
 *
 * All providers (OpenAI, Ollama, etc.) must implement this interface so that
 * LLMPreparation can use them interchangeably.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface llm_provider_interface {

    /**
     * Generate a text response from the LLM given a prompt.
     *
     * @param  string $prompt  The prompt to send to the LLM.
     * @param  array  $options Optional provider-specific options.
     * @return string          The generated text content.
     * @throws \block_attendanceleaderboard\motivation\providers\llm_auth_exception       On authentication failure.
     * @throws \block_attendanceleaderboard\motivation\providers\llm_rate_limit_exception On rate limit exceeded.
     * @throws \block_attendanceleaderboard\motivation\providers\llm_timeout_exception    On request timeout.
     */
    public function generate(string $prompt, array $options = []): string;

    /**
     * Check whether this provider is currently available and configured.
     *
     * @return bool True if the provider is available and ready to use.
     */
    public function is_available(): bool;
}
