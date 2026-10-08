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
 * Review of a draft: the teacher edits the text and publishes it, or discards the draft.
 *
 * Custom data: draft (row), forcenote (bool), defaultnote (bool).
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class draft_review extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $draft = $this->_customdata['draft'];

        $mform->addElement('hidden', 'id', $draft->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'subject', get_string('subject', 'forum'), ['size' => 60]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');

        $mform->addElement('editor', 'message', get_string('draft_message', 'local_aiforumassist'), ['rows' => 14]);
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'ainote', get_string('settings_ainote', 'local_aiforumassist'));
        $mform->setDefault('ainote', !empty($this->_customdata['defaultnote']) ? 1 : 0);
        if (!empty($this->_customdata['forcenote'])) {
            $mform->setDefault('ainote', 1);
            $mform->freeze('ainote');
        }

        $buttons = [
            $mform->createElement('submit', 'publish', get_string('draft_publish', 'local_aiforumassist')),
            $mform->createElement('submit', 'discard', get_string('draft_discard', 'local_aiforumassist')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
        $mform->closeHeaderBefore('buttonar');
    }
}
