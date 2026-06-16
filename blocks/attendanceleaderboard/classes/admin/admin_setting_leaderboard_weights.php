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
 * Custom admin setting for a single leaderboard weight with cross-field sum validation.
 *
 * When any one of the three weight fields is saved, this class reads the
 * submitted values of the other two fields when available and validates that
 * the three weights together are each in [0.0, 1.0] and sum to 1.0.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin setting for a leaderboard weight field that validates the cross-field sum.
 *
 * Each of the three weight settings (attendance, engagement, completion) is an
 * instance of this class. When write_setting() is called for one field, it
 * resolves the sibling values from the submitted form if possible and validates
 * the combined sum.
 */
class admin_setting_leaderboard_weights extends \admin_setting_configtext {

    /** @var string Config name of the second weight (sibling). */
    protected $sibling1name;

    /** @var string Config name of the third weight (sibling). */
    protected $sibling2name;

    /**
     * Constructor.
     *
     * @param string $name Setting name for this weight field.
     * @param string $visiblename Human-readable label.
     * @param string $description Help text.
     * @param mixed $defaultsetting Default value.
     * @param string $sibling1name Config name of the second weight.
     * @param string $sibling2name Config name of the third weight.
     */
    public function __construct(
        $name,
        $visiblename,
        $description,
        $defaultsetting,
        $sibling1name,
        $sibling2name
    ) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_FLOAT, 10);
        $this->sibling1name = $sibling1name;
        $this->sibling2name = $sibling2name;
    }

    /**
     * Validate the individual weight range and the cross-field sum, then save.
     *
     * @param mixed $data The submitted value for this weight field.
     * @return string Empty string on success; descriptive error string on failure.
     */
    public function write_setting($data) {
        if (!is_numeric($data)) {
            return get_string('error_validation_weight_range', 'block_attendanceleaderboard');
        }

        $thisweight = (float) $data;
        if ($thisweight < 0.0 || $thisweight > 1.0) {
            return get_string('error_validation_weight_range', 'block_attendanceleaderboard');
        }

        $plugin = 'block_attendanceleaderboard';
        $sibling1 = $this->resolve_weight_value(
            $this->get_form_field_name($this->sibling1name),
            get_config($plugin, $this->sibling1name)
        );
        $sibling2 = $this->resolve_weight_value(
            $this->get_form_field_name($this->sibling2name),
            get_config($plugin, $this->sibling2name)
        );

        $validation = settings_validator::validate_leaderboard_weights(
            $thisweight,
            $sibling1,
            $sibling2
        );
        if ($validation !== true) {
            return $validation;
        }

        return parent::write_setting($data);
    }

    /**
     * Build the admin form field name for a sibling setting.
     *
     * @param string $shortname Plugin config name without the plugin prefix.
     * @return string
     */
    private function get_form_field_name(string $shortname): string {
        return 's_block_attendanceleaderboard_' . $shortname;
    }

    /**
     * Resolve a weight from submitted form data or stored config.
     *
     * @param string $formfield Submitted admin form field name.
     * @param mixed $fallback Stored config fallback.
     * @return float
     */
    private function resolve_weight_value(string $formfield, $fallback): float {
        $submitted = optional_param($formfield, null, PARAM_RAW_TRIMMED);
        $value = ($submitted !== null && $submitted !== '') ? $submitted : $fallback;

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
