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
 * Configuration settings validator for block_attendanceleaderboard (ACMLS).
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Validates ACMLS plugin configuration values.
 */
class settings_validator {

    /**
     * Validate the configured LLM provider.
     *
     * @param mixed $value Submitted value.
     * @return true|string
     */
    public static function validate_llm_provider($value) {
        $allowed = ['gemini'];
        if (!in_array($value, $allowed, true)) {
            return get_string('error_validation_llm_provider', 'block_attendanceleaderboard');
        }

        return true;
    }

    /**
     * Validate the Gemini API key.
     *
     * @param mixed $value Submitted value.
     * @return true|string
     */
    public static function validate_api_key($value) {
        if (!is_string($value) || trim($value) === '') {
            return get_string('error_validation_api_key', 'block_attendanceleaderboard');
        }

        return true;
    }

    /**
     * Validate the Ollama endpoint URL.
     *
     * Retained for backward compatibility with older stored configs.
     *
     * @param mixed $value Submitted value.
     * @return true|string
     */
    public static function validate_ollama_endpoint($value) {
        if (!is_string($value) || trim($value) === '') {
            return get_string('error_validation_ollama_endpoint', 'block_attendanceleaderboard');
        }

        $cleaned = clean_param($value, PARAM_URL);
        if ($cleaned === '' || $cleaned === null) {
            return get_string('error_validation_ollama_endpoint', 'block_attendanceleaderboard');
        }

        if (!preg_match('#^https?://#i', $cleaned)) {
            return get_string('error_validation_ollama_endpoint', 'block_attendanceleaderboard');
        }

        return true;
    }

    /**
     * Validate the three leaderboard weights.
     *
     * @param mixed $w1 Attendance weight.
     * @param mixed $w2 Engagement weight.
     * @param mixed $w3 Completion weight.
     * @return true|string
     */
    public static function validate_leaderboard_weights($w1, $w2, $w3) {
        $weights = [$w1, $w2, $w3];

        foreach ($weights as $weight) {
            if (!is_numeric($weight)) {
                return get_string('error_validation_weight_range', 'block_attendanceleaderboard');
            }

            $value = (float) $weight;
            if ($value < 0.0 || $value > 1.0) {
                return get_string('error_validation_weight_range', 'block_attendanceleaderboard');
            }
        }

        $sum = (float) $w1 + (float) $w2 + (float) $w3;
        if (abs($sum - 1.0) > 0.01) {
            return get_string(
                'error_validation_weight_sum',
                'block_attendanceleaderboard',
                number_format($sum, 2)
            );
        }

        return true;
    }

    /**
     * Validate the motivation threshold value.
     *
     * @param mixed $value Submitted value.
     * @return true|string
     */
    public static function validate_motivation_threshold($value) {
        if (!is_numeric($value)) {
            return get_string('error_validation_motivation_threshold', 'block_attendanceleaderboard');
        }

        $int = (int) $value;
        if ($int < 1 || $int > 100) {
            return get_string('error_validation_motivation_threshold', 'block_attendanceleaderboard');
        }

        return true;
    }

    /**
     * Validate the learning rate value.
     *
     * @param mixed $value Submitted value.
     * @return true|string
     */
    public static function validate_learning_rate($value) {
        if (!is_numeric($value)) {
            return get_string('error_validation_learning_rate', 'block_attendanceleaderboard');
        }

        $float = (float) $value;
        if ($float < 0.01 || $float > 1.0) {
            return get_string('error_validation_learning_rate', 'block_attendanceleaderboard');
        }

        return true;
    }

    /**
     * Validate the performance decline threshold value.
     *
     * @param mixed $value Submitted value.
     * @return true|string
     */
    public static function validate_decline_threshold($value) {
        if (!is_numeric($value)) {
            return get_string('error_validation_decline_threshold', 'block_attendanceleaderboard');
        }

        $int = (int) $value;
        if ($int < 1 || $int > 100) {
            return get_string('error_validation_decline_threshold', 'block_attendanceleaderboard');
        }

        return true;
    }
}
