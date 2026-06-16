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
 * Moodle output renderer for block_attendanceleaderboard (ACMLS).
 *
 * Extends plugin_renderer_base to provide template-based rendering methods
 * for all ACMLS block content sections.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Output renderer for block_attendanceleaderboard.
 *
 * Each method delegates to render_from_template() with the appropriate
 * Mustache template name and data array.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2024 ACMLS Project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the main block content template.
     *
     * @param  array $data Template context data. Expected keys:
     *                     - has_leaderboard (bool)
     *                     - leaderboard (string, pre-rendered HTML)
     *                     - has_recommendations (bool)
     *                     - recommendations (string, pre-rendered HTML)
     *                     - has_encouragement (bool)
     *                     - encouragement (string, pre-rendered HTML)
     * @return string      Rendered HTML string.
     */
    public function render_block_content(array $data): string {
        return $this->render_from_template(
            'block_attendanceleaderboard/block_content',
            $data
        );
    }

    /**
     * Render the leaderboard template.
     *
     * @param  array $data Template context data. Expected keys:
     *                     - userid (int)
     *                     - current_rank (int)
     *                     - total_score (string, formatted to 2 decimals)
     *                     - rank_change (int)
     *                     - rank_change_positive (bool)
     *                     - rank_change_negative (bool)
     *                     - rank_change_abs (int)
     *                     - points_to_next (float)
     *                     - display_name (string)
     * @return string      Rendered HTML string.
     */
    public function render_leaderboard(array $data): string {
        return $this->render_from_template(
            'block_attendanceleaderboard/leaderboard',
            $data
        );
    }

    /**
     * Render the resource recommendations template.
     *
     * @param  array $data Template context data. Expected keys:
     *                     - userid (int)
     *                     - resources (array of resource objects with title,
     *                       resource_type, difficulty_level, difficulty_label)
     *                     - has_resources (bool)
     * @return string      Rendered HTML string.
     */
    public function render_resource_recommendations(array $data): string {
        return $this->render_from_template(
            'block_attendanceleaderboard/resource_recommendations',
            $data
        );
    }

    /**
     * Render the encouragement message template.
     *
     * @param  array $data Template context data. Expected keys:
     *                     - userid (int)
     *                     - content (string)
     *                     - category (string)
     *                     - category_label (string)
     * @return string      Rendered HTML string.
     */
    public function render_encouragement_message(array $data): string {
        return $this->render_from_template(
            'block_attendanceleaderboard/encouragement_message',
            $data
        );
    }

    /**
     * Alias for render_encouragement_message() for backward compatibility.
     *
     * @param  array $data Template context data.
     * @return string      Rendered HTML string.
     */
    public function render_encouragement(array $data): string {
        return $this->render_encouragement_message($data);
    }

    /**
     * Render the analytics dashboard page.
     *
     * Accepts an analytics_dashboard_page renderable and delegates to the
     * analytics_dashboard Mustache template.
     *
     * @param  analytics_dashboard_page $page Renderable analytics dashboard data.
     * @return string                         Rendered HTML string.
     */
    public function render_analytics_dashboard_page(analytics_dashboard_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template(
            'block_attendanceleaderboard/analytics_dashboard',
            $data
        );
    }

    /**
     * Render the manage resources page.
     *
     * Displays a table of all learning resources with usage statistics
     * (access_count, avg_rating, effectiveness_score) and action buttons
     * (edit, delete) for instructors.
     *
     * @param  array $data Template context data. Expected keys:
     *                     - courseid          (int)    Current course ID.
     *                     - manage_url        (string) Base URL for manage_resources.php.
     *                     - has_resources     (bool)   Whether resources exist.
     *                     - resources         (array)  Array of resource data arrays.
     *                     - add_url           (string) URL to add a new resource.
     *                     - sesskey           (string) Moodle session key.
     *                     - no_resources_msg  (string) Message when no resources exist.
     * @return string      Rendered HTML string.
     */
    public function render_manage_resources(array $data): string {
        return $this->render_from_template(
            'block_attendanceleaderboard/manage_resources',
            $data
        );
    }
}
