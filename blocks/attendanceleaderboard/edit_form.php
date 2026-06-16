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
 * Form for editing attendance leaderboard block instances.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Form for editing attendance leaderboard block instances.
 *
 * @package    block_attendanceleaderboard
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_attendanceleaderboard_edit_form extends block_edit_form {

    /**
     * Extends the configuration form for block_attendanceleaderboard.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    protected function specific_definition($mform) {
        // Section header title.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Block title.
        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_attendanceleaderboard'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('blocktitle', 'block_attendanceleaderboard'));

        // Display type.
        $displaytypeoptions = [
            'top' => get_string('displaytype_top', 'block_attendanceleaderboard'),
            'all' => get_string('displaytype_all', 'block_attendanceleaderboard')
        ];
        $mform->addElement('select', 'config_displaytype', get_string('configdisplaytype', 'block_attendanceleaderboard'), $displaytypeoptions);
        $mform->setDefault('config_displaytype', 'top');

        // Number to display.
        $mform->addElement('text', 'config_numbertodisplay', get_string('numbertodisplay', 'block_attendanceleaderboard'));
        $mform->setType('config_numbertodisplay', PARAM_INT);
        $mform->setDefault('config_numbertodisplay', 10);
        $mform->disabledIf('config_numbertodisplay', 'config_displaytype', 'eq', 'all');

        // Name format.
        $nameformatoptions = [
            'full' => get_string('nameformat_full', 'block_attendanceleaderboard'), // "Firstname Lastname"
            'initial' => get_string('nameformat_initial', 'block_attendanceleaderboard') // "Firstname L."
        ];
        $mform->addElement('select', 'config_nameformat', get_string('confignameformat', 'block_attendanceleaderboard'), $nameformatoptions);
        $mform->setDefault('config_nameformat', 'initial');
    }
}