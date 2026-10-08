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

namespace local_aiforumassist;

/**
 * Tells the course's teachers that something is waiting for them, through Moodle's notifications.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /**
     * Notify the teachers of a new draft (answer, question that needs them, or reminder).
     *
     * @param \stdClass $draft Row of {local_aiforumassist_draft}.
     */
    public static function pending(\stdClass $draft): void {
        global $DB;
        $course = get_course($draft->courseid);
        $context = \context_course::instance($course->id);
        $teachers = get_enrolled_users($context, 'local/aiforumassist:manage', 0, 'u.*', null, 0, 0, true);
        if (!$teachers) {
            return;
        }
        $from = $DB->get_record('user', ['id' => tiko::user_id()]);
        $url = new \moodle_url('/local/aiforumassist/draft.php', ['id' => $draft->id]);
        $strings = get_string_manager();
        foreach ($teachers as $teacher) {
            $lang = $teacher->lang ?: get_config('core', 'lang');
            $a = (object) ['course' => format_string($course->fullname), 'url' => $url->out(false)];
            $subject = $strings->get_string('notify_' . $draft->kind . '_subject', 'local_aiforumassist', $a, $lang);
            $body = $strings->get_string('notify_' . $draft->kind . '_body', 'local_aiforumassist', $a, $lang);

            $message = new \core\message\message();
            $message->component = 'local_aiforumassist';
            $message->name = 'pending';
            $message->userfrom = $from ?: \core_user::get_noreply_user();
            $message->userto = $teacher;
            $message->courseid = $course->id;
            $message->subject = $subject;
            $message->fullmessage = $body . "\n\n" . $url->out(false);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '<p>' . s($body) . '</p><p>' . \html_writer::link($url, s($url->out(false))) . '</p>';
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = $strings->get_string('panel', 'local_aiforumassist', null, $lang);
            message_send($message);
        }
    }
}
