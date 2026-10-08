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

namespace local_aiforumassist\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_aiforumassist\publisher;
use local_aiforumassist\question\responder;

/**
 * Privacy provider tests: a student's questions and a teacher's decisions can be found, exported and deleted.
 *
 * @package    local_aiforumassist
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aiforumassist\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var array Course, forum, teacher and student from the generator. */
    private array $setup;

    /** @var \stdClass The student's question, drafted and published by the teacher. */
    private \stdClass $draft;

    /**
     * A question drafted by the assistant and published by the teacher.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_aiforumassist');
        $this->setup = $plugin->create_course_with_forum();
        $plugin->stub_ai([$plugin->ai_answer()]);
        $question = $plugin->create_question($this->setup['forum'], $this->setup['student'], 'Dropout', '<p>What is dropout?</p>');
        $this->redirectMessages();
        responder::process((int) $question->firstpost);
        $this->draft = $DB->get_record('local_aiforumassist_draft', ['postid' => $question->firstpost], '*', MUST_EXIST);
        $this->setUser($this->setup['teacher']);
        publisher::publish_answer($this->draft, '<p>Dropout switches off neurons.</p>', false);
        $this->setUser(null);
    }

    /**
     * The course context holds data of the student who asked and the teacher who decided, nobody else.
     */
    public function test_contexts_and_users(): void {
        $context = \context_course::instance($this->setup['course']->id);
        $contexts = fn(\stdClass $user): array => array_map(
            'intval',
            provider::get_contexts_for_userid((int) $user->id)->get_contextids()
        );
        $this->assertSame([(int) $context->id], $contexts($this->setup['student']));
        $this->assertSame([(int) $context->id], $contexts($this->setup['teacher']));
        $this->assertSame([], $contexts($this->getDataGenerator()->create_user()));

        $userlist = new userlist($context, 'local_aiforumassist');
        provider::get_users_in_context($userlist);
        $expected = [(int) $this->setup['student']->id, (int) $this->setup['teacher']->id];
        sort($expected);
        $actual = array_map('intval', $userlist->get_userids());
        sort($actual);
        $this->assertSame($expected, array_values(array_filter($actual)));
    }

    /**
     * The student gets the drafts made from their questions.
     */
    public function test_export(): void {
        $context = \context_course::instance($this->setup['course']->id);
        $this->export_context_data_for_user((int) $this->setup['student']->id, $context, 'local_aiforumassist');
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'local_aiforumassist')]);
        $this->assertCount(1, $data->drafts);
        $this->assertSame('published', $data->drafts[0]->status);
        $this->assertSame(get_string('yes'), $data->drafts[0]->youasked);
        $this->assertSame([], $data->decisions);
    }

    /**
     * Deleting the student removes their drafts and the text of the AI calls behind them; deleting the
     * teacher anonymises their decisions.
     */
    public function test_delete_for_users(): void {
        global $DB;
        $context = \context_course::instance($this->setup['course']->id);
        $student = (int) $this->setup['student']->id;
        $teacher = (int) $this->setup['teacher']->id;

        provider::delete_data_for_user(new approved_contextlist($this->setup['student'], 'local_aiforumassist', [$context->id]));
        $this->assertSame(0, $DB->count_records('local_aiforumassist_draft', ['authorid' => $student]));
        $call = $DB->get_record('local_aiforumassist_log', ['draftid' => $this->draft->id, 'action' => 'answer'], '*', MUST_EXIST);
        $this->assertNull($call->prompt);
        $this->assertNull($call->response);

        provider::delete_data_for_users(new approved_userlist($context, 'local_aiforumassist', [$teacher]));
        $this->assertSame(0, $DB->count_records('local_aiforumassist_log', ['userid' => $teacher]));
        $this->assertSame(0, $DB->count_records('local_aiforumassist_course', ['usermodified' => $teacher]));
    }

    /**
     * Deleting a course's data removes its drafts and log.
     */
    public function test_delete_all_in_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context(\context_course::instance($this->setup['course']->id));
        $this->assertSame(0, $DB->count_records('local_aiforumassist_draft'));
        $this->assertSame(0, $DB->count_records('local_aiforumassist_log'));
    }
}
