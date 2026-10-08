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
 * Event observers: queue a question for later, or refresh a course's index when its materials change.
 *
 * Nothing slow happens here: the AI call runs in a background task, after the course's waiting time.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A new discussion: its first post may be a question.
     *
     * @param \mod_forum\event\discussion_created $event The event.
     */
    public static function discussion_created(\mod_forum\event\discussion_created $event): void {
        global $DB;
        $discussion = $DB->get_record('forum_discussions', ['id' => $event->objectid]);
        if ($discussion) {
            self::queue((int) $discussion->firstpost, $discussion);
        }
    }

    /**
     * A reply: it may be a question too.
     *
     * @param \mod_forum\event\post_created $event The event.
     */
    public static function post_created(\mod_forum\event\post_created $event): void {
        global $DB;
        $discussion = $DB->get_record('forum_discussions', ['id' => $event->other['discussionid'] ?? 0]);
        if ($discussion) {
            self::queue((int) $event->objectid, $discussion);
        }
    }

    /**
     * An activity was added, changed or deleted: refresh the course's index in the background.
     *
     * @param \core\event\base $event course_module_created, course_module_updated or course_module_deleted.
     */
    public static function course_module_changed(\core\event\base $event): void {
        if (!$event->courseid || !course_config::get((int) $event->courseid)->enabled) {
            return;
        }
        $task = new task\reindex_course();
        $task->set_custom_data(['courseid' => (int) $event->courseid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Queue a post for an answer draft after the course's waiting time, if the assistant works in its forum.
     *
     * @param int $postid The post.
     * @param \stdClass $discussion Its discussion.
     */
    private static function queue(int $postid, \stdClass $discussion): void {
        global $DB;
        $course = $DB->get_record('course', ['id' => $discussion->course]);
        if (!$course || !course_config::answers_in_forum($course, (int) $discussion->forum)) {
            return;
        }
        $userid = (int) $DB->get_field('forum_posts', 'userid', ['id' => $postid]);
        $context = \context_course::instance($course->id);
        if (!$userid || $userid === tiko::user_id() || has_capability('local/aiforumassist:manage', $context, $userid)) {
            return;
        }
        $task = new task\answer_question();
        $task->set_custom_data(['postid' => $postid]);
        $task->set_next_run_time(time() + 60 * (int) course_config::get((int) $course->id)->waitminutes);
        \core\task\manager::queue_adhoc_task($task);
    }
}
