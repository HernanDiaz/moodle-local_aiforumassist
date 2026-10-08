<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Site settings of AI Forum Assistant: Site administration > Plugins > Local plugins > AI Forum Assistant.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aiforumassist_settings', get_string('pluginname', 'local_aiforumassist'));

    $settings->add(new admin_setting_configcheckbox(
        'local_aiforumassist/enabled',
        get_string('setting_enabled', 'local_aiforumassist'),
        get_string('setting_enabled_desc', 'local_aiforumassist'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_aiforumassist/redactnames',
        get_string('setting_redactnames', 'local_aiforumassist'),
        get_string('setting_redactnames_desc', 'local_aiforumassist'),
        1
    ));
    $settings->add(new admin_setting_configtext(
        'local_aiforumassist/dailylimit',
        get_string('setting_dailylimit', 'local_aiforumassist'),
        get_string('setting_dailylimit_desc', 'local_aiforumassist'),
        50,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_aiforumassist/forcenote',
        get_string('setting_forcenote', 'local_aiforumassist'),
        get_string('setting_forcenote_desc', 'local_aiforumassist'),
        0
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_aiforumassist/instruction',
        get_string('setting_instruction', 'local_aiforumassist'),
        get_string('setting_instruction_desc', 'local_aiforumassist'),
        '',
        PARAM_RAW,
        60,
        6
    ));

    // Where the assistant is available. Both empty = every course.
    $settings->add(new admin_setting_heading(
        'local_aiforumassist/availabilityheading',
        get_string('setting_availability_heading', 'local_aiforumassist'),
        get_string('setting_availability_heading_desc', 'local_aiforumassist')
    ));
    // A Closure, so the category list is only loaded when the page is shown.
    $settings->add(new admin_setting_configmultiselect(
        'local_aiforumassist/allowedcategories',
        get_string('setting_allowedcategories', 'local_aiforumassist'),
        get_string('setting_allowedcategories_desc', 'local_aiforumassist'),
        [],
        fn(): array => \local_aiforumassist\availability::get_category_choices()
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_aiforumassist/allowedcourses',
        get_string('setting_allowedcourses', 'local_aiforumassist'),
        get_string('setting_allowedcourses_desc', 'local_aiforumassist'),
        '',
        PARAM_RAW_TRIMMED,
        40,
        5
    ));

    $ADMIN->add('localplugins', $settings);
}
