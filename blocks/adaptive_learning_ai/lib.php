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
 * Hook untuk extend navigation di course
 */
function block_adaptive_learning_ai_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    global $USER;

    if (!is_enrolled($context, $USER, '', true)) {
        return;
    }

    $url = new moodle_url('/blocks/adaptive_learning_ai/reports/teacher_report.php', ['courseid' => $course->id]);
    $node = $navigation->add(get_string('report_quiz', 'block_adaptive_learning_ai'), $url,
            navigation_node::TYPE_SETTING, null, 'adaptive_learning_ai_report', new pix_icon('i/report', ''));
    $node->set_force_into_more_menu(true);
}


