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

use local_aiforumassist\question\responder;
use local_aiforumassist\reminder\builder;
use local_aiforumassist\reminder\scheduler;

/**
 * Tests for what happens around a draft: queuing questions, publishing or discarding drafts,
 * the weekly reminders, and the Tiko user.
 *
 * @package    local_aiforumassist
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aiforumassist\observer
 * @covers     \local_aiforumassist\publisher
 * @covers     \local_aiforumassist\reminder\builder
 * @covers     \local_aiforumassist\reminder\scheduler
 * @covers     \local_aiforumassist\tiko
 */
final class workflow_test extends \advanced_testcase {
    /**
     * Answer tasks waiting in the queue.
     *
     * @return \core\task\adhoc_task[]
     */
    private function queued_answers(): array {
        return \core\task\manager::get_adhoc_tasks('\\local_aiforumassist\\task\\answer_question');
    }

    /**
     * A student's new discussion is queued for after the waiting time; posts by teachers and Tiko,
     * and posts in forums where the assistant is off, are not.
     */
    public function test_questions_are_queued_after_the_waiting_time(): void {
        global $DB;
        $this->resetAfterTest();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_aiforumassist');
        $setup = $plugin->create_course_with_forum(['waitminutes' => 120]);
        $other = $this->getDataGenerator()->create_module('forum', ['course' => $setup['course']->id]);
        $student = (int) $setup['student']->id;

        $before = time();
        $discussionid = publisher::post_discussion($setup['forum'], 'Dropout', '<p>What is dropout?</p>', $student);
        publisher::post_discussion($setup['forum'], 'Welcome', '<p>Ask here.</p>', (int) $setup['teacher']->id);
        publisher::post_discussion($setup['forum'], 'Reminder', '<p>Lab 2 is due.</p>', tiko::user_id());
        publisher::post_discussion($other, 'Off topic', '<p>Anyone for football?</p>', $student);

        $tasks = $this->queued_answers();
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $firstpost = $DB->get_field('forum_discussions', 'firstpost', ['id' => $discussionid]);
        $this->assertEquals($firstpost, $task->get_custom_data()->postid);
        $this->assertGreaterThanOrEqual($before + 120 * MINSECS, $task->get_next_run_time());
    }

    /**
     * Adding or changing activities queues one index refresh, only in courses that use the assistant.
     */
    public function test_material_changes_queue_one_reindex(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $unused = $generator->create_course();
        course_config::save((int) $course->id, ['enabled' => 1]);

        $generator->create_module('page', ['course' => $course->id]);
        $generator->create_module('page', ['course' => $course->id]);
        $generator->create_module('page', ['course' => $unused->id]);

        $tasks = \core\task\manager::get_adhoc_tasks('\\local_aiforumassist\\task\\reindex_course');
        $this->assertCount(1, $tasks);
        $this->assertEquals($course->id, reset($tasks)->get_custom_data()->courseid);
    }

    /**
     * The teacher publishes a draft as their own reply, with the optional AI note; nothing is queued for it.
     */
    public function test_publish_and_discard(): void {
        global $DB;
        $this->resetAfterTest();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_aiforumassist');
        $setup = $plugin->create_course_with_forum();
        $plugin->stub_ai([$plugin->ai_answer(), $plugin->ai_answer()]);
        $first = $plugin->create_question($setup['forum'], $setup['student'], 'Dropout', '<p>What is dropout?</p>');
        $second = $plugin->create_question($setup['forum'], $setup['student'], 'L2', '<p>What is L2?</p>');
        $this->redirectMessages();
        responder::process((int) $first->firstpost);
        responder::process((int) $second->firstpost);
        $draft = $DB->get_record('local_aiforumassist_draft', ['postid' => $first->firstpost], '*', MUST_EXIST);
        $other = $DB->get_record('local_aiforumassist_draft', ['postid' => $second->firstpost], '*', MUST_EXIST);

        $this->setUser($setup['teacher']);
        $postid = publisher::publish_answer($draft, '<p>Dropout switches off neurons.</p>', true);
        publisher::discard($other);

        $post = $DB->get_record('forum_posts', ['id' => $postid], '*', MUST_EXIST);
        $this->assertEquals($setup['teacher']->id, $post->userid);
        $this->assertEquals($first->firstpost, $post->parent);
        $this->assertSame('Re: Dropout', $post->subject);
        $this->assertSame('<p>Dropout switches off neurons.</p><p><em>Prepared with the help of AI.</em></p>', $post->message);

        $draft = $DB->get_record('local_aiforumassist_draft', ['id' => $draft->id]);
        $this->assertSame('published', $draft->status);
        $this->assertEquals($postid, $draft->publishedpostid);
        $this->assertEquals($setup['teacher']->id, $draft->decidedby);
        $this->assertSame('discarded', $DB->get_field('local_aiforumassist_draft', 'status', ['id' => $other->id]));
        $teacher = $setup['teacher']->id;
        $decisions = $DB->get_fieldset_select('local_aiforumassist_log', 'action', 'userid = ? ORDER BY id', [$teacher]);
        $this->assertSame(['publish', 'discard'], $decisions);
        $this->assertSame([], $this->queued_answers());

        // Without the note, the reply is exactly what the teacher approved.
        $third = $plugin->create_question($setup['forum'], $setup['student'], 'More', '<p>And batch norm?</p>');
        $draft = (object) [
            'id' => $other->id, 'courseid' => $setup['course']->id, 'postid' => $third->firstpost, 'subject' => 'Re: More',
        ];
        $postid = publisher::publish_answer($draft, '<p>See the page on normalisation.</p>', false);
        $this->assertSame('<p>See the page on normalisation.</p>', $DB->get_field('forum_posts', 'message', ['id' => $postid]));
    }

    /**
     * The reminder lists the deadlines itself; the AI only writes the opening, and a fixed one replaces it when the AI fails.
     */
    public function test_reminder_text(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $plugin = $generator->get_plugin_generator('local_aiforumassist');
        $course = $generator->create_course(['lang' => 'en', 'fullname' => 'Deep Learning']);
        $now = time();
        $ai = $plugin->stub_ai([]);
        $this->assertNull(builder::build($course, 7, $now));
        $this->assertSame([], $ai->prompts);

        $due = $now + 2 * DAYSECS;
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Lab 2', 'duedate' => $due]);
        $ai = $plugin->stub_ai(['"A busy week: plan your time!"', null]);

        $reminder = builder::build($course, 7, $now);

        $this->assertSame("Reminder: this week's deadlines", $reminder['subject']);
        $this->assertStringStartsWith('<p>A busy week: plan your time!</p><ul><li><strong>', $reminder['message']);
        $when = userdate($due, get_string('strftimedaydatetime', 'langconfig'), \core_date::get_server_timezone());
        $this->assertStringContainsString(s($when), $reminder['message']);
        $this->assertStringContainsString('>Lab 2</a> (due)</li>', $reminder['message']);
        $this->assertStringContainsString('- Lab 2', $ai->prompts[0]);
        $this->assertStringNotContainsString(userdate($due), $ai->prompts[0]);

        $reminder = builder::build($course, 7, $now);
        $this->assertStringStartsWith('<p>This is what is due in the coming days. Plan your week!</p>', $reminder['message']);
    }

    /**
     * At the chosen weekday and hour the reminder becomes a draft for the teacher, once; in automatic mode Tiko posts it.
     */
    public function test_reminder_schedule(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $plugin = $generator->get_plugin_generator('local_aiforumassist');
        $setup = $plugin->create_course_with_forum();
        $course = $setup['course'];
        $now = time();
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Lab 2', 'duedate' => $now + DAYSECS]);
        $plugin->stub_ai(['Plan your week.', 'Plan your week.']);
        $local = (new \DateTime('@' . $now))->setTimezone(\core_date::get_server_timezone_object());
        $config = course_config::save((int) $course->id, [
            'reminders' => 1, 'reminderday' => (int) $local->format('N'), 'reminderhour' => (int) $local->format('G'),
            'reminderforumid' => $setup['forum']->id,
        ]);
        $messages = $this->redirectMessages();

        $this->assertSame([$course->id => 'drafted'], scheduler::run($now));
        $this->assertSame([], scheduler::run($now + MINSECS));
        $this->assertSame([], scheduler::run($now + DAYSECS));

        $draft = $DB->get_record('local_aiforumassist_draft', ['courseid' => $course->id, 'kind' => 'reminder'], '*', MUST_EXIST);
        $this->assertEquals($setup['forum']->id, $draft->forumid);
        $this->assertCount(1, $messages->get_messages());

        $config->remindermode = 'auto';
        $this->assertSame('posted', scheduler::prepare($course, $config, $now));
        $discussion = $DB->get_record('forum_discussions', ['forum' => $setup['forum']->id], '*', MUST_EXIST);
        $this->assertEquals(tiko::user_id(), $discussion->userid);
        $message = $DB->get_field('forum_posts', 'message', ['id' => $discussion->firstpost]);
        $note = '<p><em>' . get_string('tikonote_reminder', 'local_aiforumassist') . '</em></p>';
        $this->assertStringEndsWith($note, $message);
        $this->assertSame([], $this->queued_answers());
    }

    /**
     * Tiko is a user who cannot log in, with the fox picture; it is created again if someone deletes it.
     */
    public function test_tiko_user(): void {
        global $DB;
        $this->resetAfterTest();
        $id = tiko::user_id();
        $tiko = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(tiko::USERNAME, $tiko->username);
        $this->assertSame('nologin', $tiko->auth);
        $this->assertSame('Tiko', $tiko->firstname);
        $this->assertNotEmpty($tiko->picture);
        $this->assertSame($id, tiko::user_id());

        delete_user($tiko);
        $this->assertNotEquals($id, tiko::user_id());
    }
}
