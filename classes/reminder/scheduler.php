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

use local_aiforumassist\availability;
use local_aiforumassist\notifier;
use local_aiforumassist\publisher;
use local_aiforumassist\tiko;

/**
 * Decides which courses are due their weekly reminder and prepares it: posted by Tiko in automatic
 * mode, or left as a draft for the teacher.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scheduler {
    /**
     * Prepare the reminders due at this hour.
     *
     * @param int $now Current time.
     * @return string[] Course id => what happened, for the task log.
     */
    public static function run(int $now): array {
        global $DB;
        $local = (new \DateTime('@' . $now))->setTimezone(\core_date::get_server_timezone_object());
        $weekday = (int) $local->format('N');
        $hour = (int) $local->format('G');

        $done = [];
        $configs = $DB->get_records(
            'local_aiforumassist_course',
            ['reminders' => 1, 'reminderday' => $weekday, 'reminderhour' => $hour]
        );
        foreach ($configs as $config) {
            // Once per slot, even if the task runs twice in the same hour.
            if ($config->lastreminder > $now - 2 * HOURSECS) {
                continue;
            }
            $course = $DB->get_record('course', ['id' => $config->courseid]);
            if (!$course || !$course->visible || !availability::is_available_in_course($course)) {
                continue;
            }
            $DB->set_field('local_aiforumassist_course', 'lastreminder', $now, ['id' => $config->id]);
            $done[$course->id] = self::prepare($course, $config, $now);
        }
        return $done;
    }

    /**
     * Build one course's reminder and post it or leave it as a draft.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $config Its settings.
     * @param int $now Current time.
     * @return string posted | drafted | nothing_due | no_forum
     */
    public static function prepare(\stdClass $course, \stdClass $config, int $now): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $forum = $config->reminderforumid
            ? $DB->get_record('forum', ['id' => $config->reminderforumid, 'course' => $course->id])
            : forum_get_course_forum((int) $course->id, 'news');
        if (!$forum) {
            return 'no_forum';
        }
        $reminder = builder::build($course, (int) $config->reminderdays, $now);
        if (!$reminder) {
            return 'nothing_due';
        }
        if ($config->remindermode === 'auto') {
            $message = $reminder['message'] . publisher::note((int) $course->id, 'tikonote_reminder');
            publisher::post_discussion($forum, $reminder['subject'], $message, tiko::user_id());
            return 'posted';
        }
        $draft = (object) [
            'courseid' => $course->id, 'kind' => 'reminder', 'status' => 'pending', 'forumid' => $forum->id,
            'discussionid' => 0, 'postid' => 0, 'authorid' => 0, 'reason' => null, 'subject' => $reminder['subject'],
            'message' => $reminder['message'], 'sources' => null, 'publishedpostid' => 0, 'decidedby' => 0,
            'timedecided' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ];
        $draft->id = $DB->insert_record('local_aiforumassist_draft', $draft);
        notifier::pending($draft);
        return 'drafted';
    }
}
