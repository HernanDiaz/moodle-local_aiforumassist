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

use local_aiforumassist\question\answer;
use local_aiforumassist\question\detector;
use local_aiforumassist\question\responder;

/**
 * Tests for answering questions: detecting them, reading the AI's answer, and the whole pipeline with a stub AI.
 *
 * @package    local_aiforumassist
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aiforumassist\question\detector
 * @covers     \local_aiforumassist\question\answer
 * @covers     \local_aiforumassist\question\responder
 * @covers     \local_aiforumassist\question\prompt
 */
final class question_test extends \advanced_testcase {
    /**
     * Questions are recognised with or without a question mark; a thank-you note is not.
     */
    public function test_detector(): void {
        $this->assertTrue(detector::is_question('Lab', '<p>When is the lab due?</p>'));
        $this->assertTrue(detector::is_question('Duda', '<p>No entiendo el dropout</p>'));
        $this->assertTrue(detector::is_question('XOR', '<p>Como se resuelve el XOR con un perceptron</p>'));
        $this->assertFalse(detector::is_question('Gracias', '<p>Muchas gracias por los materiales, están genial.</p>'));
    }

    /**
     * The JSON answer is read even with code fences around it; labels in the text are removed.
     */
    public function test_answer_parsing(): void {
        $this->assertNull(answer::parse('Sorry, I cannot help.'));
        $fence = str_repeat(chr(96), 3);
        $answer = answer::parse($fence . "json\n" . json_encode([
            'is_question' => true, 'answerable' => true, 'graded_task_request' => false,
            'answer' => 'The perceptron is linear (see S3). An MLP adds hidden layers [S1].',
            'sources' => ['s3', 'A42', 'nonsense'],
        ]) . "\n" . $fence);
        $this->assertTrue($answer->answerable);
        $this->assertSame('The perceptron is linear. An MLP adds hidden layers.', $answer->text);
        $this->assertSame(['S3', 'A42'], $answer->sources);

        // An "answerable" verdict with no text is not answerable.
        $empty = answer::parse('{"is_question": true, "answerable": true, "answer": ""}');
        $this->assertFalse($empty->answerable);
    }

    /**
     * A student's question becomes a draft for the teacher, built from the course material, with the
     * student's name kept away from the AI and the source linked.
     */
    public function test_question_is_drafted(): void {
        global $DB;
        $this->resetAfterTest();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_aiforumassist');
        $setup = $plugin->create_course_with_forum();
        set_config('redactnames', 1, 'local_aiforumassist');
        $message = '<p>I am Irene Soler. What is dropout for?</p>';
        $discussion = $plugin->create_question($setup['forum'], $setup['student'], 'Dropout', $message);
        $ai = $plugin->stub_ai([$plugin->ai_answer()]);
        $messages = $this->redirectMessages();

        $this->assertSame(responder::DRAFTED, responder::process((int) $discussion->firstpost));

        $draft = $DB->get_record('local_aiforumassist_draft', ['postid' => $discussion->firstpost], '*', MUST_EXIST);
        $this->assertSame('answer', $draft->kind);
        $this->assertSame('pending', $draft->status);
        $this->assertSame((int) $setup['student']->id, (int) $draft->authorid);
        $this->assertStringContainsString('<p>Dropout switches off random neurons while training.</p>', $draft->message);
        $this->assertStringContainsString('Regularisation', $draft->message);
        $this->assertStringContainsString('/mod/page/view.php', $draft->message);
        $this->assertStringStartsWith('Regularisation (', json_decode($draft->sources)[0]->title);

        $this->assertCount(1, $ai->prompts);
        $this->assertStringContainsString('Dropout randomly switches off neurons', $ai->prompts[0]);
        $this->assertStringContainsString('I am [STUDENT]. What is dropout for?', $ai->prompts[0]);
        $this->assertStringNotContainsString('Soler', $ai->prompts[0]);
        $this->assertSame(1, $DB->count_records('local_aiforumassist_log', ['draftid' => $draft->id]));

        // The teacher is told there is a draft to review.
        $this->assertSame([(int) $setup['teacher']->id], array_map(fn($m) => (int) $m->useridto, $messages->get_messages()));
    }

    /**
     * A request for the solution of graded work, or a question the materials do not answer, goes to the teacher without a draft.
     */
    public function test_questions_the_assistant_hands_to_the_teacher(): void {
        global $DB;
        $this->resetAfterTest();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_aiforumassist');
        $setup = $plugin->create_course_with_forum();
        $graded = $plugin->create_question($setup['forum'], $setup['student'], 'Lab', '<p>Can someone share the solved lab?</p>');
        $unknown = $plugin->create_question($setup['forum'], $setup['student'], 'Exam', '<p>Is there an exam in June?</p>');
        $plugin->stub_ai([
            $plugin->ai_answer(['answerable' => false, 'graded_task_request' => true, 'answer' => '']),
            $plugin->ai_answer(['answerable' => false, 'answer' => '']),
        ]);

        $this->assertSame(responder::NEEDS_TEACHER, responder::process((int) $graded->firstpost));
        $this->assertSame(responder::NEEDS_TEACHER, responder::process((int) $unknown->firstpost));

        $reason = fn($discussion) => $DB->get_field('local_aiforumassist_draft', 'reason', ['postid' => $discussion->firstpost]);
        $this->assertSame('graded_task', $reason($graded));
        $this->assertSame('not_in_materials', $reason($unknown));
        $this->assertNull($DB->get_field('local_aiforumassist_draft', 'message', ['postid' => $unknown->firstpost]));
    }

    /**
     * No AI call when there is nothing to answer: a thank-you note, a teacher already replied, a teacher's own post,
     * or a forum where the assistant is off.
     */
    public function test_nothing_to_answer(): void {
        global $DB;
        $this->resetAfterTest();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_aiforumassist');
        $setup = $plugin->create_course_with_forum();
        $ai = $plugin->stub_ai([$plugin->ai_answer()]);

        $thanks = $plugin->create_question($setup['forum'], $setup['student'], 'Thanks', '<p>Thank you for the slides!</p>');
        $this->assertSame(responder::NOT_QUESTION, responder::process((int) $thanks->firstpost));

        $answered = $plugin->create_question($setup['forum'], $setup['student'], 'Lab', '<p>When is lab 2 due?</p>');
        $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_post([
            'discussion' => $answered->id, 'parent' => $answered->firstpost, 'userid' => $setup['teacher']->id,
            'message' => 'On Friday.', 'created' => time() + 1,
        ]);
        $this->assertSame(responder::SKIPPED, responder::process((int) $answered->firstpost));

        $byteacher = $plugin->create_question($setup['forum'], $setup['teacher'], 'Welcome', '<p>Any questions so far?</p>');
        $this->assertSame(responder::SKIPPED, responder::process((int) $byteacher->firstpost));

        course_config::set_active_forums((int) $setup['course']->id, []);
        $off = $plugin->create_question($setup['forum'], $setup['student'], 'Lab', '<p>When is lab 3 due?</p>');
        $this->assertSame(responder::SKIPPED, responder::process((int) $off->firstpost));

        $this->assertSame([], $ai->prompts);
        $this->assertSame(0, $DB->count_records('local_aiforumassist_draft'));
    }

    /**
     * A passing AI failure (rate limit) leaves no draft, so the task is retried; other failures go to the teacher.
     */
    public function test_ai_failures(): void {
        global $DB;
        $this->resetAfterTest();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_aiforumassist');
        $setup = $plugin->create_course_with_forum();
        $question = $plugin->create_question($setup['forum'], $setup['student'], 'Dropout', '<p>What is dropout?</p>');
        $this->expectOutputRegex('/post \d+: retry/');

        $plugin->stub_ai([null], '429: Rate limit reached for model on tokens per minute');
        $this->assertSame(responder::RETRY, responder::process((int) $question->firstpost));
        $this->assertSame(0, $DB->count_records('local_aiforumassist_draft'));

        $task = new task\answer_question();
        $task->set_custom_data(['postid' => (int) $question->firstpost]);
        $plugin->stub_ai([null], '429: Rate limit reached');
        try {
            $task->execute();
            $this->fail('The task should fail so that Moodle retries it.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_retry', $e->errorcode);
        }

        $plugin->stub_ai([null], '401: Incorrect API key provided');
        $this->assertSame(responder::NEEDS_TEACHER, responder::process((int) $question->firstpost));
        $this->assertSame('ai_error', $DB->get_field('local_aiforumassist_draft', 'reason', ['postid' => $question->firstpost]));
    }

    /**
     * Over the daily limit, questions go to the teacher without calling the AI.
     */
    public function test_daily_limit(): void {
        global $DB;
        $this->resetAfterTest();
        $plugin = $this->getDataGenerator()->get_plugin_generator('local_aiforumassist');
        $setup = $plugin->create_course_with_forum();
        set_config('dailylimit', 1, 'local_aiforumassist');
        $ai = $plugin->stub_ai([$plugin->ai_answer(), $plugin->ai_answer()]);
        $first = $plugin->create_question($setup['forum'], $setup['student'], 'One', '<p>What is dropout?</p>');
        $second = $plugin->create_question($setup['forum'], $setup['student'], 'Two', '<p>What is L2?</p>');

        $this->assertSame(responder::DRAFTED, responder::process((int) $first->firstpost));
        $this->assertSame(responder::NEEDS_TEACHER, responder::process((int) $second->firstpost));
        $this->assertCount(1, $ai->prompts);
        $this->assertSame('daily_limit', $DB->get_field('local_aiforumassist_draft', 'reason', ['postid' => $second->firstpost]));
    }
}
