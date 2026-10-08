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

namespace local_aiforumassist\content;

/**
 * Due dates and closing dates of a course, read from its calendar: the facts answers and reminders rely on.
 *
 * Only student-facing deadlines of visible activities count (due, close, submissions close, expected
 * completion) plus the course's own calendar events; teacher-only ones such as "grading due" do not.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates {
    /** Activity event types that are deadlines for students. */
    public const DEADLINE_TYPES = ['due', 'close', 'submissionsclose', 'expectcompletionon'];

    /**
     * Deadlines of a course in a time window, earliest first.
     *
     * @param \stdClass $course The course.
     * @param int $from Start of the window (timestamp).
     * @param int $to End of the window (timestamp).
     * @return array[] Each: time, name (activity or event name), type (event type), cmid (0 for course events), url.
     */
    public static function between(\stdClass $course, int $from, int $to): array {
        global $DB;
        [$typesql, $params] = $DB->get_in_or_equal(self::DEADLINE_TYPES, SQL_PARAMS_NAMED);
        $params += ['courseid' => $course->id, 'from' => $from, 'to' => $to, 'from2' => $from, 'to2' => $to];
        $events = $DB->get_records_select(
            'event',
            "courseid = :courseid AND visible = 1 AND (
                (modulename IS NOT NULL AND modulename <> '' AND eventtype $typesql AND timesort >= :from AND timesort <= :to)
                OR (eventtype = 'course' AND timestart >= :from2 AND timestart <= :to2))",
            $params,
            'timestart',
            'id, name, modulename, instance, eventtype, timestart, timesort'
        );

        $modinfo = get_fast_modinfo($course, -1);
        $result = [];
        foreach ($events as $event) {
            if ($event->eventtype === 'course') {
                $params = ['view' => 'day', 'course' => $course->id, 'time' => $event->timestart];
                $url = new \moodle_url('/calendar/view.php', $params);
                $result[] = [
                    'time' => (int) $event->timestart, 'name' => format_string($event->name), 'type' => 'course', 'cmid' => 0,
                    'url' => $url,
                ];
                continue;
            }
            $cm = $modinfo->instances[$event->modulename][$event->instance] ?? null;
            if (!$cm || !$cm->visible || !$modinfo->get_section_info_by_id($cm->section)->visible) {
                continue;
            }
            $result[] = [
                'time' => (int) ($event->timesort ?: $event->timestart), 'name' => $cm->get_formatted_name(),
                'type' => $event->eventtype, 'cmid' => (int) $cm->id, 'url' => $cm->url,
            ];
        }
        usort($result, fn(array $a, array $b): int => $a['time'] <=> $b['time']);
        return $result;
    }
}
