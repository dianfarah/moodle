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
 * Renderer for the ACMLS Configuration Audit Log admin view.
 *
 * Generates an HTML table of audit log entries for display in the admin
 * dashboard, showing who changed what and when.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attendanceleaderboard\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders the configuration audit log as an HTML table.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config_audit_renderer {

    /**
     * Render an HTML table of audit log entries.
     *
     * Each row shows: date/time, administrator name, setting name,
     * old value, and new value.
     *
     * @param  array  $entries   Array of stdClass objects from config_audit_log::get_logs().
     * @param  int    $total     Total number of matching entries (for pagination display).
     * @param  int    $page      Current page number (0-indexed).
     * @param  int    $perpage   Entries per page.
     * @param  string $base_url  Base URL for pagination links (moodle_url as string).
     * @return string            Rendered HTML.
     */
    public static function render_table(
        array $entries,
        int $total = 0,
        int $page = 0,
        int $perpage = 50,
        string $base_url = ''
    ): string {

        if (empty($entries)) {
            return \html_writer::div(
                \html_writer::tag(
                    'p',
                    get_string('audit_no_entries', 'block_attendanceleaderboard'),
                    ['class' => 'text-muted']
                ),
                'alert alert-info'
            );
        }

        // Build table header.
        $header_cells = [
            \html_writer::tag('th', get_string('audit_col_time', 'block_attendanceleaderboard'),
                ['scope' => 'col']),
            \html_writer::tag('th', get_string('audit_col_user', 'block_attendanceleaderboard'),
                ['scope' => 'col']),
            \html_writer::tag('th', get_string('audit_col_setting', 'block_attendanceleaderboard'),
                ['scope' => 'col']),
            \html_writer::tag('th', get_string('audit_col_old_value', 'block_attendanceleaderboard'),
                ['scope' => 'col']),
            \html_writer::tag('th', get_string('audit_col_new_value', 'block_attendanceleaderboard'),
                ['scope' => 'col']),
        ];

        $thead = \html_writer::tag(
            'thead',
            \html_writer::tag('tr', implode('', $header_cells))
        );

        // Build table rows.
        $rows = '';
        foreach ($entries as $entry) {
            $time_str = userdate($entry->timecreated, get_string('strftimedatetimeshort', 'langconfig'));

            // Truncate long values for display; show full value in title attribute.
            $old_display = self::truncate_value((string) $entry->old_value);
            $new_display = self::truncate_value((string) $entry->new_value);

            // Mask API key values for security.
            if (strpos($entry->setting_name, 'apikey') !== false
                || strpos($entry->setting_name, 'api_key') !== false) {
                $old_display = self::mask_secret((string) $entry->old_value);
                $new_display = self::mask_secret((string) $entry->new_value);
            }

            $user_display = \html_writer::tag(
                'span',
                s($entry->user_fullname ?? $entry->username ?? ''),
                ['title' => 'userid: ' . (int) $entry->userid]
            );

            $cells = [
                \html_writer::tag('td', s($time_str)),
                \html_writer::tag('td', $user_display),
                \html_writer::tag('td',
                    \html_writer::tag('code', s($entry->setting_name)),
                    ['class' => 'text-break']
                ),
                \html_writer::tag('td',
                    \html_writer::tag('span', s($old_display),
                        ['title' => s((string) $entry->old_value), 'class' => 'text-danger']
                    )
                ),
                \html_writer::tag('td',
                    \html_writer::tag('span', s($new_display),
                        ['title' => s((string) $entry->new_value), 'class' => 'text-success']
                    )
                ),
            ];

            $rows .= \html_writer::tag('tr', implode('', $cells));
        }

        $tbody = \html_writer::tag('tbody', $rows);

        $table_html = \html_writer::tag(
            'table',
            $thead . $tbody,
            ['class' => 'table table-sm table-striped table-hover generaltable']
        );

        // Wrap in responsive container.
        $output = \html_writer::div($table_html, 'table-responsive');

        // Pagination info.
        if ($total > $perpage) {
            $showing_from = ($page * $perpage) + 1;
            $showing_to   = min(($page + 1) * $perpage, $total);
            $output .= \html_writer::tag(
                'p',
                get_string('audit_showing', 'block_attendanceleaderboard',
                    (object) ['from' => $showing_from, 'to' => $showing_to, 'total' => $total]
                ),
                ['class' => 'text-muted small']
            );
        } else {
            $output .= \html_writer::tag(
                'p',
                get_string('audit_total_entries', 'block_attendanceleaderboard', $total),
                ['class' => 'text-muted small']
            );
        }

        return $output;
    }

    /**
     * Render a summary card showing the count of recent config changes.
     *
     * @param  int $count  Number of changes in the last 30 days.
     * @return string      Rendered HTML card.
     */
    public static function render_summary_card(int $count): string {
        $title   = get_string('audit_log_title', 'block_attendanceleaderboard');
        $label   = get_string('audit_recent_changes', 'block_attendanceleaderboard');
        $viewurl = new \moodle_url('/blocks/attendanceleaderboard/config_audit_log.php');

        $card_body = \html_writer::div(
            \html_writer::tag('h5', $title, ['class' => 'card-title'])
            . \html_writer::tag('p',
                \html_writer::tag('strong', $count) . ' ' . $label,
                ['class' => 'card-text']
            )
            . \html_writer::link(
                $viewurl,
                get_string('audit_view_full_log', 'block_attendanceleaderboard'),
                ['class' => 'btn btn-outline-secondary btn-sm']
            ),
            'card-body'
        );

        return \html_writer::div($card_body, 'card mb-3');
    }

    /**
     * Truncate a long value string for table display.
     *
     * @param  string $value  The value to truncate.
     * @param  int    $maxlen Maximum display length. Default: 60.
     * @return string         Truncated string with ellipsis if needed.
     */
    protected static function truncate_value(string $value, int $maxlen = 60): string {
        if (core_text::strlen($value) <= $maxlen) {
            return $value;
        }
        return core_text::substr($value, 0, $maxlen) . '…';
    }

    /**
     * Mask a secret value (e.g. API key) for safe display.
     *
     * Shows only the first 4 characters followed by asterisks.
     *
     * @param  string $value  The secret value to mask.
     * @return string         Masked string.
     */
    protected static function mask_secret(string $value): string {
        if (empty($value)) {
            return '';
        }
        $visible = core_text::substr($value, 0, 4);
        return $visible . str_repeat('*', min(8, max(0, core_text::strlen($value) - 4)));
    }
}
