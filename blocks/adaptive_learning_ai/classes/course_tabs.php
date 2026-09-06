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

defined('MOODLE_INTERNAL') || die();

/**
 * Class untuk manage custom course tabs
 */
class adaptive_learning_ai_course_tabs {

    /**
     * Inject tab menu ke course header
     * 
     * @param stdClass $course Course object
     * @param context_course $context Course context
     * @return string HTML untuk tab menu
     */
    public static function get_course_tabs($course, $context) {
        global $PAGE, $CFG;

        $html = '';
        
        // Cek apakah user adalah guru
        if (has_capability('moodle/course:manageactivities', $context)) {
            $report_url = new moodle_url('/blocks/adaptive_learning_ai/reports/teacher_report.php', ['courseid' => $course->id]);
            
            // Check if current page is the report page
            $is_active = $PAGE->pagetype === 'blocks_adaptive_learning_ai_teacher_report';
            $active_class = $is_active ? 'active' : '';
            
            $html .= html_writer::link(
                $report_url,
                get_string('report_teacher', 'block_adaptive_learning_ai'),
                [
                    'class' => 'nav-link ' . $active_class,
                    'data-toggle' => 'tab',
                    'role' => 'tab'
                ]
            );
        }

        return $html;
    }

    /**
     * Get CSS untuk tab styling
     */
    public static function get_tab_styles() {
        return <<<CSS
        <style>
            .course-tabs-extended {
                display: inline-flex;
                gap: 0;
                border-bottom: 1px solid #dee2e6;
                margin-bottom: 20px;
            }
            
            .course-tabs-extended .nav-link {
                padding: 10px 20px;
                color: #0066cc;
                text-decoration: none;
                border-bottom: 3px solid transparent;
                margin-bottom: -1px;
                transition: all 0.2s;
                font-size: 14px;
                font-weight: 500;
            }
            
            .course-tabs-extended .nav-link:hover {
                color: #0052a3;
                border-bottom-color: #0052a3;
            }
            
            .course-tabs-extended .nav-link.active {
                color: #0052a3;
                border-bottom-color: #0052a3;
            }
        </style>
        CSS;
    }
}
