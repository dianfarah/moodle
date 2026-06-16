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
 * Learner Profile table — table_sql subclass for the profiles listing page.
 *
 * Joins acmls_learner_profile with mdl_user for a given courseid and
 * supports optional filtering by performance_category.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * SQL-backed table for displaying Learner Profiles per course.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_profile_table extends \table_sql {

    /** @var int The course ID being displayed. */
    protected int $courseid;

    /** @var int|null Optional performance_category filter (1=Low, 2=Middle, 3=High). */
    protected ?int $filter_performance;

    /** @var \context_course Course context for building URLs. */
    protected \context_course $coursecontext;

    /**
     * Constructor.
     *
     * @param string          $uniqueid          Unique ID for this table instance.
     * @param int             $courseid          Course ID.
     * @param int|null        $filter_performance Optional performance_category filter.
     * @param \context_course $coursecontext     Course context.
     */
    public function __construct(
        string $uniqueid,
        int $courseid,
        ?int $filter_performance,
        \context_course $coursecontext
    ) {
        parent::__construct($uniqueid);

        $this->courseid          = $courseid;
        $this->filter_performance = $filter_performance;
        $this->coursecontext     = $coursecontext;

        // Define columns.
        $this->define_columns([
            'fullname',
            'performance_category',
            'cognitive_level',
            'motivation_level',
            'learning_style',
            'behavioral_score',
            'engagement_score',
            'last_updated',
            'actions',
        ]);

        // Define column headers.
        $this->define_headers([
            get_string('fullname'),
            get_string('performancecategory', 'block_attendanceleaderboard'),
            get_string('cognitivelevel',      'block_attendanceleaderboard'),
            get_string('motivationlevel',     'block_attendanceleaderboard'),
            get_string('learningstyle',       'block_attendanceleaderboard'),
            get_string('behavioralscore',     'block_attendanceleaderboard'),
            get_string('engagementscore',     'block_attendanceleaderboard'),
            get_string('lastupdated',         'block_attendanceleaderboard'),
            get_string('actions',             'block_attendanceleaderboard'),
        ]);

        // Sortable columns.
        $this->sortable(true, 'fullname', SORT_ASC);
        $this->no_sorting('actions');

        // Collapsible columns.
        $this->collapsible(false);

        // Set up the SQL.
        $this->setup_sql();
    }

    /**
     * Configure the SQL query for this table.
     */
    protected function setup_sql(): void {
        global $DB;

        $params = ['courseid' => $this->courseid];

        $fields = "lp.id,
                   lp.userid,
                   lp.performance_category,
                   lp.cognitive_level,
                   lp.motivation_level,
                   lp.learning_style,
                   lp.behavioral_score,
                   lp.engagement_score,
                   lp.last_updated,
                   " . get_all_user_name_fields(true, 'u');

        $from = "{acmls_learner_profile} lp
                 JOIN {user} u ON u.id = lp.userid";

        $where = "lp.courseid = :courseid AND u.deleted = 0";

        if ($this->filter_performance !== null && $this->filter_performance > 0) {
            $where .= " AND lp.performance_category = :filter_performance";
            $params['filter_performance'] = $this->filter_performance;
        }

        $this->set_sql($fields, $from, $where, $params);
        $this->set_count_sql(
            "SELECT COUNT(lp.id) FROM {acmls_learner_profile} lp
              JOIN {user} u ON u.id = lp.userid
             WHERE {$where}",
            $params
        );
    }

    /**
     * Format the fullname column as a link to the user's Moodle profile.
     *
     * @param  \stdClass $row Table row data.
     * @return string         Formatted HTML.
     */
    public function col_fullname(\stdClass $row): string {
        $fullname = fullname($row);
        $profileurl = new \moodle_url('/user/view.php', [
            'id'     => $row->userid,
            'course' => $this->courseid,
        ]);
        return \html_writer::link($profileurl, format_string($fullname));
    }

    /**
     * Format the performance_category column as a Bootstrap badge.
     *
     * @param  \stdClass $row Table row data.
     * @return string         Formatted HTML.
     */
    public function col_performance_category(\stdClass $row): string {
        $cat = (int) $row->performance_category;
        [$label, $class] = $this->get_level_label_class($cat);
        return \html_writer::tag('span', $label, ['class' => "badge badge-{$class}"]);
    }

    /**
     * Format the cognitive_level column as a text label.
     *
     * @param  \stdClass $row Table row data.
     * @return string         Formatted text.
     */
    public function col_cognitive_level(\stdClass $row): string {
        $level = (int) $row->cognitive_level;
        [$label] = $this->get_level_label_class($level);
        return $label;
    }

    /**
     * Format the motivation_level column as a percentage value with a progress bar.
     *
     * @param  \stdClass $row Table row data.
     * @return string         Formatted HTML.
     */
    public function col_motivation_level(\stdClass $row): string {
        $value = (float) $row->motivation_level;
        $pct   = min(100, max(0, $value));

        // Choose bar colour based on value.
        if ($pct >= 70) {
            $barclass = 'bg-success';
        } else if ($pct >= 40) {
            $barclass = 'bg-warning';
        } else {
            $barclass = 'bg-danger';
        }

        $bar = \html_writer::div(
            \html_writer::div('', "progress-bar {$barclass}", [
                'role'          => 'progressbar',
                'style'         => "width:{$pct}%",
                'aria-valuenow' => (string) $pct,
                'aria-valuemin' => '0',
                'aria-valuemax' => '100',
            ]),
            'progress',
            ['style' => 'min-width:80px;height:10px;']
        );

        return \html_writer::div(
            number_format($value, 1) . '%' . \html_writer::empty_tag('br') . $bar,
            'text-nowrap'
        );
    }

    /**
     * Format the learning_style column.
     *
     * @param  \stdClass $row Table row data.
     * @return string         Formatted text.
     */
    public function col_learning_style(\stdClass $row): string {
        return format_string($row->learning_style ?? '—');
    }

    /**
     * Format the behavioral_score column.
     *
     * @param  \stdClass $row Table row data.
     * @return string         Formatted text.
     */
    public function col_behavioral_score(\stdClass $row): string {
        return number_format((float) $row->behavioral_score, 2);
    }

    /**
     * Format the engagement_score column.
     *
     * @param  \stdClass $row Table row data.
     * @return string         Formatted text.
     */
    public function col_engagement_score(\stdClass $row): string {
        return number_format((float) $row->engagement_score, 2);
    }

    /**
     * Format the last_updated column as a human-readable date.
     *
     * @param  \stdClass $row Table row data.
     * @return string         Formatted date string.
     */
    public function col_last_updated(\stdClass $row): string {
        if (empty($row->last_updated)) {
            return '—';
        }
        return userdate((int) $row->last_updated, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Format the actions column with a "View Detail" link.
     *
     * @param  \stdClass $row Table row data.
     * @return string         Formatted HTML.
     */
    public function col_actions(\stdClass $row): string {
        $detailurl = new \moodle_url('/blocks/attendanceleaderboard/learner_profile_detail.php', [
            'userid'   => $row->userid,
            'courseid' => $this->courseid,
        ]);
        return \html_writer::link(
            $detailurl,
            get_string('viewdetail', 'block_attendanceleaderboard'),
            ['class' => 'btn btn-sm btn-outline-primary']
        );
    }

    /**
     * Return a [label, Bootstrap colour class] pair for a 1/2/3 level value.
     *
     * @param  int   $level  1=Low, 2=Middle, 3=High.
     * @return array         [string $label, string $class].
     */
    protected function get_level_label_class(int $level): array {
        switch ($level) {
            case 1:
                return [get_string('low',    'block_attendanceleaderboard'), 'danger'];
            case 2:
                return [get_string('middle', 'block_attendanceleaderboard'), 'warning'];
            case 3:
                return [get_string('high',   'block_attendanceleaderboard'), 'success'];
            default:
                return ['—', 'secondary'];
        }
    }
}
