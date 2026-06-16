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
 * Renderable / templatable class for the ACMLS Analytics Dashboard page.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;

/**
 * Renderable data class for the analytics dashboard page.
 *
 * Implements both renderable and templatable so it can be passed directly
 * to $renderer->render() and exported to a Mustache template via
 * export_for_template().
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_dashboard_page implements renderable, templatable {

    /**
     * Performance_Category distribution data.
     *
     * Each element is an associative array with keys:
     *   - category_name (string)  Human-readable category label.
     *   - count         (int)     Number of learners in this category.
     *   - percentage    (float)   Percentage of total learners.
     *   - css_class     (string)  Bootstrap contextual class (danger/warning/success).
     *
     * @var array
     */
    protected array $performance_distribution;

    /**
     * Average Motivation_Level per course.
     *
     * Each element is an associative array with keys:
     *   - courseid                (int)    Moodle course ID.
     *   - coursename              (string) Course full name.
     *   - avg_motivation          (float)  Raw average value.
     *   - avg_motivation_formatted (string) Formatted to 1 decimal place.
     *
     * @var array
     */
    protected array $motivation_per_course;

    /**
     * Intervention effectiveness data.
     *
     * Associative array with keys:
     *   - total_decisions          (int)    Total decided interventions (response IS NOT NULL).
     *   - positive_responses       (int)    Count of learner_response = 1.
     *   - effectiveness_percentage (float)  Raw percentage value.
     *   - effectiveness_formatted  (string) Formatted percentage string (e.g. "72.5%").
     *
     * @var array
     */
    protected array $intervention_effectiveness;

    /**
     * Total number of learner profiles across all courses.
     *
     * @var int
     */
    protected int $total_learners;

    /**
     * Constructor.
     *
     * @param array $performance_distribution  Performance_Category distribution data.
     * @param array $motivation_per_course     Average Motivation_Level per course.
     * @param array $intervention_effectiveness Intervention effectiveness data.
     * @param int   $total_learners            Total learner count.
     */
    public function __construct(
        array $performance_distribution,
        array $motivation_per_course,
        array $intervention_effectiveness,
        int $total_learners = 0
    ) {
        $this->performance_distribution  = $performance_distribution;
        $this->motivation_per_course     = $motivation_per_course;
        $this->intervention_effectiveness = $intervention_effectiveness;
        $this->total_learners            = $total_learners;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param  renderer_base $output Moodle renderer base (unused but required by interface).
     * @return array                 Template context array.
     */
    public function export_for_template(renderer_base $output): array {
        $has_data = ($this->total_learners > 0)
            || !empty($this->motivation_per_course)
            || ($this->intervention_effectiveness['total_decisions'] ?? 0) > 0;

        return [
            'performance_distribution'  => array_values($this->performance_distribution),
            'motivation_per_course'     => array_values($this->motivation_per_course),
            'intervention_effectiveness' => $this->intervention_effectiveness,
            'has_data'                  => $has_data,
            'total_learners'            => $this->total_learners,
        ];
    }
}
