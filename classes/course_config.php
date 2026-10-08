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
 * A course's settings for the assistant (table local_aiforumassist_course) and its active forums.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_config {
    /** Settings of a course that has never been configured. */
    public const DEFAULTS = [
        'enabled' => 0, 'waitminutes' => 120, 'helplevel' => 'guide', 'ainote' => 0,
        'reminders' => 0, 'reminderforumid' => 0, 'reminderday' => 1, 'reminderhour' => 8, 'reminderdays' => 7,
        'remindermode' => 'draft', 'lastreminder' => 0, 'indexedtime' => 0,
    ];

    /**
     * Settings of a course, with defaults when it was never configured.
     *
     * @param int $courseid The course.
     * @return \stdClass Row of {local_aiforumassist_course} (id 0 when not stored yet).
     */
    public static function get(int $courseid): \stdClass {
        global $DB;
        $record = $DB->get_record('local_aiforumassist_course', ['courseid' => $courseid]);
        return $record ?: (object) (['id' => 0, 'courseid' => $courseid] + self::DEFAULTS);
    }

    /**
     * Store a course's settings.
     *
     * @param int $courseid The course.
     * @param array $values Field => value, any subset of DEFAULTS.
     * @return \stdClass The stored row.
     */
    public static function save(int $courseid, array $values): \stdClass {
        global $DB, $USER;
        $record = self::get($courseid);
        foreach (array_intersect_key($values, self::DEFAULTS) as $field => $value) {
            $record->$field = $value;
        }
        $record->usermodified = (int) ($USER->id ?? 0);
        $record->timemodified = time();
        if ($record->id) {
            $DB->update_record('local_aiforumassist_course', $record);
        } else {
            unset($record->id);
            $record->timecreated = $record->timemodified;
            $record->id = $DB->insert_record('local_aiforumassist_course', $record);
        }
        return $record;
    }

    /**
     * Ids of the course forums where the assistant drafts answers.
     *
     * @param int $courseid The course.
     * @return int[]
     */
    public static function active_forums(int $courseid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select('local_aiforumassist_forum', 'forumid', 'courseid = ?', [$courseid]));
    }

    /**
     * Set the course forums where the assistant drafts answers (all others are turned off).
     *
     * @param int $courseid The course.
     * @param int[] $forumids Forums of that course.
     */
    public static function set_active_forums(int $courseid, array $forumids): void {
        global $DB;
        $DB->delete_records('local_aiforumassist_forum', ['courseid' => $courseid]);
        $now = time();
        foreach (array_unique(array_map('intval', $forumids)) as $forumid) {
            if ($DB->record_exists('forum', ['id' => $forumid, 'course' => $courseid])) {
                $DB->insert_record('local_aiforumassist_forum', (object) [
                    'courseid' => $courseid, 'forumid' => $forumid, 'mode' => 'draft', 'timemodified' => $now,
                ]);
            }
        }
    }

    /**
     * Whether the assistant answers questions in a forum: the course is enabled, available, and the forum is active.
     *
     * @param \stdClass $course The course.
     * @param int $forumid The forum.
     * @return bool
     */
    public static function answers_in_forum(\stdClass $course, int $forumid): bool {
        global $DB;
        return self::get((int) $course->id)->enabled
            && availability::is_available_in_course($course)
            && $DB->record_exists('local_aiforumassist_forum', ['courseid' => $course->id, 'forumid' => $forumid]);
    }
}
