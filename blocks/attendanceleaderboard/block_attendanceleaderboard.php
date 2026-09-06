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
 * Main block class for block_attendanceleaderboard (ACMLS).
 *
 * This block serves as the primary entry point for the Adaptive
 * Cognitive-Motivational Learning System. It renders the leaderboard,
 * personalised resource recommendations, and motivational interventions
 * directly within the Moodle course page.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Block class for the Attendance Leaderboard (ACMLS) plugin.
 *
 * Extends block_base to integrate with the Moodle block framework.
 */
class block_attendanceleaderboard extends block_base {

    /**
     * Initialise the block.
     *
     * Sets the block title from the language string.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_attendanceleaderboard');
    }

    /**
     * Return the applicable formats for this block.
     *
     * The block is available on course pages and the site home page.
     *
     * @return array Associative array of page formats and whether the block is allowed.
     */
    public function applicable_formats(): array {
        return [
            'all'          => false,
            'site'         => true,
            'site-index'   => true,
            'course-view'  => true,
            'course'       => true,
            'my'           => true,
        ];
    }

    /**
     * Allow multiple instances of this block on a single page.
     *
     * @return bool False — only one instance per page is allowed.
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Does this block have a global configuration page?
     *
     * @return bool True — the plugin provides a settings.php page.
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Build and return the block content.
     *
     * Renders the leaderboard, resource recommendations, and motivational
     * content for the current user in the current course context.
     *
     * @return stdClass|null The block content object, or null if the user
     *                       does not have the required capability.
     */
    public function get_content(): ?stdClass {
        global $USER, $COURSE, $OUTPUT, $PAGE;

        // Return cached content if already built.
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        // Verify the user has the capability to view the leaderboard.
        $context = context_course::instance($COURSE->id);
        if (!has_capability('block/attendanceleaderboard:viewleaderboard', $context)) {
            $this->content->text = '';
            return $this->content;
        }

        // Attempt to render the block content via the Delivery System.
        try {
            $this->content->text = $this->render_block_content($USER->id, $COURSE->id, $context);
        } catch (Throwable $e) {
            // Graceful degradation: log the error and show a minimal fallback.
            error_log(
                'block_attendanceleaderboard: Failed to render block content — ' . $e->getMessage()
            );
            $this->content->text = html_writer::tag(
                'p',
                get_string('error_llm_unavailable', 'block_attendanceleaderboard'),
                ['class' => 'alert alert-warning']
            );
        }

        // Add a "Manage Learning Resources" link in the footer for instructors.
        if (has_capability('block/attendanceleaderboard:manageresources', $context)) {
            $manage_url = new moodle_url(
                '/blocks/attendanceleaderboard/manage_resources.php',
                ['courseid' => $COURSE->id]
            );
            $this->content->footer = html_writer::link(
                $manage_url,
                get_string('manage_resources_link', 'block_attendanceleaderboard'),
                ['class' => 'btn btn-sm btn-outline-secondary mt-1']
            );
        }

        return $this->content;
    }

    /**
     * Render the full block content for a given user and course.
     *
     * Delegates rendering to the DeliverySystem and the Moodle output
     * renderer. Falls back to a simple placeholder when the Delivery System
     * classes are not yet available (e.g., during initial installation).
     *
     * @param int             $userid  The ID of the current user.
     * @param int             $courseid The ID of the current course.
     * @param context_course  $context  The course context object.
     * @return string HTML content for the block body.
     */
    protected function render_block_content(int $userid, int $courseid, context_course $context): string {
        // Check whether the Delivery System class is available.
        // During the initial installation the classes/ directory may not yet
        // be fully loaded, so we guard with class_exists().
        if (class_exists('\block_attendanceleaderboard\delivery\delivery_system')) {
            $delivery = new \block_attendanceleaderboard\delivery\delivery_system();
            return $delivery->render_block($userid, $courseid);
        }

        // Fallback placeholder rendered while the system is being set up.
        return $this->render_placeholder($userid, $courseid);
    }

    /**
     * Render a lightweight placeholder when the full Delivery System is not
     * yet available.
     *
     * @param int $userid    The ID of the current user.
     * @param int $courseid  The ID of the current course.
     * @return string HTML placeholder content.
     */
    protected function render_placeholder(int $userid, int $courseid): string {
        $html  = html_writer::start_div('block-attendanceleaderboard-placeholder');
        $html .= html_writer::tag(
            'p',
            get_string('leaderboard_no_data', 'block_attendanceleaderboard'),
            ['class' => 'text-muted']
        );
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Hide the block header when there is no custom title set.
     *
     * @return bool True to hide the header when the title is empty.
     */
    public function hide_header(): bool {
        return false;
    }

    /**
     * Specifies the HTML attributes to add to the block's outer container.
     *
     * @return array Associative array of HTML attributes.
     */
    public function html_attributes(): array {
        $attributes = parent::html_attributes();
        $attributes['class'] .= ' block_attendanceleaderboard';
        return $attributes;
    }
}
