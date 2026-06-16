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
 * Custom admin setting class with inline validation for block_attendanceleaderboard.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\admin;

defined('MOODLE_INTERNAL') || die();

// admin_setting_configtext is defined in lib/adminlib.php which is always
// loaded before settings.php is executed.

/**
 * An admin_setting_configtext subclass that validates the submitted value
 * before persisting it.
 *
 * Usage:
 *   new admin_setting_with_validation(
 *       $name, $visiblename, $description, $defaultsetting, $paramtype,
 *       callable $validator
 *   );
 *
 * The $validator callable receives the raw submitted value and must return
 * true on success or a descriptive error string on failure.
 *
 * Moodle's write_setting() contract:
 *   - Return '' (empty string) on success → value is saved.
 *   - Return a non-empty error string on failure → value is NOT saved and the
 *     error is displayed in the admin UI, preserving the previous valid value.
 */
class admin_setting_with_validation extends \admin_setting_configtext {

    /** @var callable Validation callback. */
    protected $validator;

    /**
     * Constructor.
     *
     * @param string   $name           Unique setting name (e.g. 'block_attendanceleaderboard/weight_attendance').
     * @param string   $visiblename    Human-readable label.
     * @param string   $description    Help text shown below the field.
     * @param mixed    $defaultsetting Default value.
     * @param mixed    $paramtype      PARAM_* constant used for cleaning (default PARAM_RAW).
     * @param callable $validator      Callable that returns true or an error string.
     * @param int      $size           Input field size (default 30).
     */
    public function __construct(
        $name,
        $visiblename,
        $description,
        $defaultsetting,
        $paramtype = PARAM_RAW,
        callable $validator = null,
        $size = 30
    ) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, $paramtype, $size);
        $this->validator = $validator;
    }

    /**
     * Validates and saves the submitted value.
     *
     * Overrides admin_setting_configtext::write_setting().
     *
     * @param  mixed  $data The raw value submitted by the administrator.
     * @return string       Empty string on success; descriptive error string on failure.
     */
    public function write_setting($data) {
        if ($this->validator !== null) {
            $result = call_user_func($this->validator, $data);
            if ($result !== true) {
                // Return the error string — Moodle will display it and will NOT
                // persist the value, preserving the previous valid configuration.
                return (string) $result;
            }
        }

        // Validation passed — delegate to the parent to persist the value.
        return parent::write_setting($data);
    }
}
