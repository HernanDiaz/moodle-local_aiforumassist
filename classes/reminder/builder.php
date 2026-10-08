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

namespace local_aiforumassist\reminder;

use local_aiforumassist\ai\client;
use local_aiforumassist\audit;
use local_aiforumassist\content\dates;
use local_aiforumassist\tiko;

/**
 * Writes the weekly reminder of a course: the deadlines of the coming days, taken from the calendar.
 *
 * The plugin writes the dates, names and links itself, so they cannot be wrong; the AI only writes a
 * short friendly opening line. If the AI fails, a fixed opening is used and the reminder still goes out.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class builder {
    /**
     * Build the reminder.
     *
     * @param \stdClass $course The course.
     * @param int $days How many days ahead.
     * @param int $now Current time.
     * @return array|null ['subject' => string, 'message' => string (HTML)], or null when nothing is due.
     */
    public static function build(\stdClass $course, int $days, int $now): ?array {
        $items = dates::between($course, $now, $now + $days * DAYSECS);
        if (!$items) {
            return null;
        }
        $lang = $course->lang ?: get_config('core', 'lang') ?: 'en';
        $strings = get_string_manager();

        $previous = force_current_language($lang);
        $list = '';
        foreach ($items as $item) {
            $when = userdate($item['time'], get_string('strftimedaydatetime', 'langconfig'), \core_date::get_server_timezone());
            $type = $strings->string_exists('remindertype_' . $item['type'], 'local_aiforumassist')
                ? ' (' . get_string('remindertype_' . $item['type'], 'local_aiforumassist') . ')' : '';
            $link = \html_writer::link($item['url'], s($item['name']));
            $list .= '<li><strong>' . s($when) . '</strong>: ' . $link . s($type) . '</li>';
        }
        force_current_language($previous);

        $intro = self::intro($course, $items, $lang);
        return [
            'subject' => $strings->get_string('reminder_subject', 'local_aiforumassist', null, $lang),
            'message' => '<p>' . s($intro) . '</p><ul>' . $list . '</ul>',
        ];
    }

    /**
     * The opening line, written by the AI, or a fixed one if the call fails.
     *
     * @param \stdClass $course The course.
     * @param array[] $items Deadlines (only their names are sent).
     * @param string $lang Course language code.
     * @return string Plain text.
     */
    private static function intro(\stdClass $course, array $items, string $lang): string {
        $names = implode("\n", array_map(fn(array $item) => '- ' . $item['name'], $items));
        $prompt = "You write the opening of a weekly reminder post in the forum of a Moodle course.\n"
            . "Write one or two short, friendly sentences (at most 40 words) in the language with code \"" . $lang . "\",\n"
            . "encouraging the students to plan their week. The list of deadlines is added below your text by the system:\n"
            . "do not repeat it, do not mention dates, do not greet anyone by name and do not sign.\n"
            . "Course: " . format_string($course->fullname) . "\nDeadlines this week:\n" . $names . "\n\n"
            . "Return only the sentences, with no quotes and no Markdown.";
        $result = client::generate(\context_course::instance($course->id)->id, tiko::user_id(), $prompt);
        audit::ai_call((int) $course->id, 'reminder', $prompt, $result);
        $text = trim(strip_tags($result->text), " \t\n\r\"'");
        if (!$result->success || $text === '' || \core_text::strlen($text) > 400) {
            return get_string_manager()->get_string('reminder_intro_fallback', 'local_aiforumassist', null, $lang);
        }
        return $text;
    }
}
