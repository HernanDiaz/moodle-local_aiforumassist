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

namespace local_aiforumassist\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The teacher's settings of the assistant in a course.
 *
 * Custom data: courseid, forums (id => name of the course's forums), forcenote (bool).
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_settings extends \moodleform {
    /** Weekday string ids of the calendar component, Monday first (ISO-8601 day 1). */
    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $forums = $this->_customdata['forums'];

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('header', 'answersheader', get_string('settings_answers', 'local_aiforumassist'));
        $mform->addElement('advcheckbox', 'enabled', get_string('settings_enabled', 'local_aiforumassist'));
        $mform->addHelpButton('enabled', 'settings_enabled', 'local_aiforumassist');

        if ($forums) {
            $checkboxes = [];
            foreach ($forums as $id => $name) {
                $checkboxes[] = $mform->createElement('advcheckbox', 'forum_' . $id, '', $name);
            }
            $mform->addGroup($checkboxes, 'forumsgroup', get_string('settings_forums', 'local_aiforumassist'), '<br>', false);
            $mform->addHelpButton('forumsgroup', 'settings_forums', 'local_aiforumassist');
        } else {
            $mform->addElement(
                'static',
                'noforums',
                get_string('settings_forums', 'local_aiforumassist'),
                get_string('settings_noforums', 'local_aiforumassist')
            );
        }

        $waits = [];
        foreach ([0, 30, 60, 120, 360, 720, 1440] as $minutes) {
            $waits[$minutes] = $minutes ? format_time($minutes * MINSECS) : get_string('settings_wait_none', 'local_aiforumassist');
        }
        $mform->addElement('select', 'waitminutes', get_string('settings_wait', 'local_aiforumassist'), $waits);
        $mform->addHelpButton('waitminutes', 'settings_wait', 'local_aiforumassist');

        $mform->addElement('select', 'helplevel', get_string('settings_helplevel', 'local_aiforumassist'), [
            'guide' => get_string('settings_helplevel_guide', 'local_aiforumassist'),
            'explain' => get_string('settings_helplevel_explain', 'local_aiforumassist'),
        ]);
        $mform->addHelpButton('helplevel', 'settings_helplevel', 'local_aiforumassist');

        $mform->addElement('advcheckbox', 'ainote', get_string('settings_ainote', 'local_aiforumassist'));
        $mform->addHelpButton('ainote', 'settings_ainote', 'local_aiforumassist');
        if (!empty($this->_customdata['forcenote'])) {
            $mform->setDefault('ainote', 1);
            $mform->freeze('ainote');
        }

        $mform->addElement('header', 'remindersheader', get_string('settings_reminders', 'local_aiforumassist'));
        $mform->addElement('advcheckbox', 'reminders', get_string('settings_reminders_enabled', 'local_aiforumassist'));
        $mform->addHelpButton('reminders', 'settings_reminders_enabled', 'local_aiforumassist');

        $reminderforums = [0 => get_string('settings_reminderforum_news', 'local_aiforumassist')] + $forums;
        $label = get_string('settings_reminderforum', 'local_aiforumassist');
        $mform->addElement('select', 'reminderforumid', $label, $reminderforums);
        $days = [];
        for ($day = 1; $day <= 7; $day++) {
            $days[$day] = get_string(self::WEEKDAYS[$day - 1], 'calendar');
        }
        $mform->addElement('select', 'reminderday', get_string('settings_reminderday', 'local_aiforumassist'), $days);
        $hours = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hours[$hour] = sprintf('%02d:00', $hour);
        }
        $mform->addElement('select', 'reminderhour', get_string('settings_reminderhour', 'local_aiforumassist'), $hours);
        $mform->addElement('select', 'reminderdays', get_string('settings_reminderdays', 'local_aiforumassist'), [
            3 => get_string('numdays', 'core', 3), 7 => get_string('numdays', 'core', 7), 14 => get_string('numdays', 'core', 14),
        ]);
        $mform->addElement('select', 'remindermode', get_string('settings_remindermode', 'local_aiforumassist'), [
            'draft' => get_string('settings_remindermode_draft', 'local_aiforumassist'),
            'auto' => get_string('settings_remindermode_auto', 'local_aiforumassist'),
        ]);
        $mform->addHelpButton('remindermode', 'settings_remindermode', 'local_aiforumassist');
        foreach (['reminderforumid', 'reminderday', 'reminderhour', 'reminderdays', 'remindermode'] as $field) {
            $mform->hideIf($field, 'reminders', 'notchecked');
        }

        $this->add_action_buttons();
    }
}
