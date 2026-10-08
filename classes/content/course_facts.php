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
 * The course "fact sheet" for the prompt, built without AI: its sections and visible activities, each
 * with a label the AI can cite ("A" + course module id), and its deadlines from the calendar. Questions
 * about logistics ("when is it due?") are answered from these exact data.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_facts {
    /** Most activities listed, so a huge course does not flood the prompt. */
    private const MAX_ACTIVITIES = 200;

    /**
     * Build the fact sheet.
     *
     * @param \stdClass $course The course.
     * @param int $now Current time.
     * @return array{text: string, activities: array} Text for the prompt; activities by cmid: [title, url].
     */
    public static function build(\stdClass $course, int $now): array {
        $modinfo = get_fast_modinfo($course, -1);
        $lines = ['Course: ' . format_string($course->fullname)];
        if ($course->startdate) {
            $lines[] = 'Starts: ' . self::date((int) $course->startdate);
        }
        if ($course->enddate) {
            $lines[] = 'Ends: ' . self::date((int) $course->enddate);
        }

        $activities = [];
        $lines[] = '';
        $lines[] = 'Sections and activities:';
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->visible) {
                continue;
            }
            $sectionlines = [];
            $sectionname = get_section_name($course, $section);
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->visible || $cm->deletioninprogress || $cm->modname === 'label') {
                    continue;
                }
                if (count($activities) >= self::MAX_ACTIVITIES) {
                    continue;
                }
                $name = $cm->get_formatted_name();
                // The section tells apart activities that share a name, in the links under an answer.
                $activities[$cm->id] = ['title' => $name . ' (' . $sectionname . ')', 'url' => $cm->url];
                $sectionlines[] = '  - [A' . $cm->id . '] ' . $name . ' (' . get_string('modulename', $cm->modname) . ')';
            }
            if ($sectionlines) {
                $lines[] = '- ' . $sectionname;
                array_push($lines, ...$sectionlines);
            }
        }

        $deadlines = dates::between($course, $now - 14 * DAYSECS, $now + 90 * DAYSECS);
        $lines[] = '';
        $lines[] = 'Deadlines from the course calendar (today is ' . self::date($now) . '):';
        if (!$deadlines) {
            $lines[] = '- none in the calendar';
        }
        foreach ($deadlines as $deadline) {
            $label = $deadline['cmid'] ? '[A' . $deadline['cmid'] . '] ' : '';
            $lines[] = '- ' . self::date($deadline['time']) . ': ' . $label . $deadline['name'] . ' (' . $deadline['type'] . ')';
        }
        return ['text' => implode("\n", $lines), 'activities' => $activities];
    }

    /**
     * A date and time the AI cannot misread, in the site's time zone.
     *
     * @param int $time Timestamp.
     * @return string E.g. "2026-10-14 23:59 (Wednesday)".
     */
    private static function date(int $time): string {
        $date = new \DateTime('@' . $time);
        $date->setTimezone(\core_date::get_server_timezone_object());
        return $date->format('Y-m-d H:i (l)');
    }
}
