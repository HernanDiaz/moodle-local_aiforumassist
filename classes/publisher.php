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
 * Publishes in the forum what the teacher approved, and posts Tiko's automatic reminders.
 *
 * Posts are created through mod_forum's own functions and trigger its events, so subscriptions,
 * notifications, completion and logs behave as for any post.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publisher {
    /**
     * Publish an answer draft as the current user's (the teacher's) reply to the student's post.
     *
     * @param \stdClass $draft Draft of kind "answer" or "question" (the teacher wrote the answer).
     * @param string $message The text the teacher approved (HTML).
     * @param bool $note Add "Prepared with the help of AI".
     * @return int Id of the new post.
     */
    public static function publish_answer(\stdClass $draft, string $message, bool $note): int {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $parent = $DB->get_record('forum_posts', ['id' => $draft->postid], '*', MUST_EXIST);
        $discussion = $DB->get_record('forum_discussions', ['id' => $parent->discussion], '*', MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        require_capability('mod/forum:replypost', $context);

        $post = (object) [
            'discussion' => $discussion->id,
            'parent' => $parent->id,
            'subject' => $draft->subject,
            'message' => $message . ($note ? self::note($forum->course, 'ainote') : ''),
            'messageformat' => FORMAT_HTML,
            'messagetrust' => trusttext_trusted($context),
            'itemid' => file_get_unused_draft_itemid(),
            'mailnow' => 0,
            'deleted' => 0,
        ];
        $post->id = forum_add_new_post($post, null);

        $event = \mod_forum\event\post_created::create([
            'context' => $context,
            'objectid' => $post->id,
            'other' => ['discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => $forum->type],
        ]);
        $event->add_record_snapshot('forum_posts', $post);
        $event->add_record_snapshot('forum_discussions', $discussion);
        $event->trigger();
        self::update_completion($forum, $cm);

        self::decided($draft, 'published', (int) $post->id, (int) $USER->id);
        return (int) $post->id;
    }

    /**
     * Publish a reminder draft as a new discussion by the current user (the teacher).
     *
     * @param \stdClass $draft Draft of kind "reminder".
     * @param string $message The text the teacher approved (HTML).
     * @param bool $note Add "Prepared with the help of AI".
     * @return int Id of the new discussion.
     */
    public static function publish_reminder(\stdClass $draft, string $message, bool $note): int {
        global $DB, $USER;
        $forum = $DB->get_record('forum', ['id' => $draft->forumid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course, false, MUST_EXIST);
        require_capability('mod/forum:startdiscussion', \context_module::instance($cm->id));
        $message .= $note ? self::note($forum->course, 'ainote') : '';
        $discussionid = self::post_discussion($forum, $draft->subject, $message, (int) $USER->id);
        $firstpost = (int) $DB->get_field('forum_discussions', 'firstpost', ['id' => $discussionid]);
        self::decided($draft, 'published', $firstpost, (int) $USER->id);
        return $discussionid;
    }

    /**
     * Start a discussion in a forum on behalf of a user (the teacher, or Tiko for automatic reminders).
     *
     * @param \stdClass $forum Row of {forum}.
     * @param string $subject Subject.
     * @param string $message Message (HTML).
     * @param int $userid Author.
     * @return int Id of the new discussion.
     */
    public static function post_discussion(\stdClass $forum, string $subject, string $message, int $userid): int {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $discussion = (object) [
            'course' => $forum->course,
            'forum' => $forum->id,
            'name' => \core_text::substr($subject, 0, 255),
            'message' => $message,
            'messageformat' => FORMAT_HTML,
            'messagetrust' => 0,
            'mailnow' => 0,
            'groupid' => -1,
            'timestart' => 0,
            'timeend' => 0,
            'pinned' => 0,
        ];
        $discussionid = (int) forum_add_discussion($discussion, null, null, $userid);

        $event = \mod_forum\event\discussion_created::create([
            'context' => $context,
            'objectid' => $discussionid,
            'relateduserid' => $userid,
            'other' => ['forumid' => $forum->id],
        ]);
        $event->trigger();
        return $discussionid;
    }

    /**
     * Mark a draft as discarded by the current user (the teacher).
     *
     * @param \stdClass $draft The draft.
     */
    public static function discard(\stdClass $draft): void {
        global $USER;
        self::decided($draft, 'discarded', 0, (int) $USER->id);
    }

    /**
     * A small italic line for the end of a post, in the course's language.
     *
     * @param int $courseid Course.
     * @param string $identifier String id (ainote, tikonote_reminder).
     * @return string HTML.
     */
    public static function note(int $courseid, string $identifier): string {
        $lang = get_course($courseid)->lang ?: get_config('core', 'lang');
        $text = get_string_manager()->get_string($identifier, 'local_aiforumassist', null, $lang);
        return '<p><em>' . s($text) . '</em></p>';
    }

    /**
     * Record the teacher's decision on a draft.
     *
     * @param \stdClass $draft The draft.
     * @param string $status published | discarded.
     * @param int $postid Published post, or 0.
     * @param int $userid Teacher.
     */
    private static function decided(\stdClass $draft, string $status, int $postid, int $userid): void {
        global $DB;
        $now = time();
        $DB->update_record('local_aiforumassist_draft', (object) [
            'id' => $draft->id, 'status' => $status, 'publishedpostid' => $postid, 'decidedby' => $userid,
            'timedecided' => $now, 'timemodified' => $now,
        ]);
        audit::decision($draft, $status === 'published' ? 'publish' : 'discard', $userid);
    }

    /**
     * Update the forum's completion state for the current user, as mod_forum does after a post.
     *
     * @param \stdClass $forum Row of {forum}.
     * @param \stdClass $cm Course module.
     */
    private static function update_completion(\stdClass $forum, \stdClass $cm): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new \completion_info(get_course($forum->course));
        if ($completion->is_enabled($cm) && ($forum->completionreplies || $forum->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }
    }
}
