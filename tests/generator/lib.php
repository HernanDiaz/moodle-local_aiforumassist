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

/**
 * Test data generator for AI Forum Assistant: a course with a forum, a teacher and students, questions,
 * and an AI provider replaced by a stub that records the prompts.
 *
 * @package    local_aiforumassist
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_aiforumassist_generator extends component_generator_base {
    /**
     * A course with the assistant enabled in one forum, with a page of material, a teacher and a student.
     *
     * @param array $config Course settings overriding the defaults (enabled, wait 0).
     * @return array{course: stdClass, forum: stdClass, teacher: stdClass, student: stdClass}
     */
    public function create_course_with_forum(array $config = []): array {
        $generator = testing_util::get_data_generator();
        $course = $generator->create_course(['fullname' => 'Deep Learning', 'lang' => 'en']);
        $forum = $generator->create_module('forum', ['course' => $course->id, 'name' => 'Questions']);
        $generator->create_module('page', [
            'course' => $course->id,
            'name' => 'Regularisation',
            'content' => '<p>Dropout randomly switches off neurons during training, which reduces overfitting.</p>'
                . '<p>L2 regularisation adds the squared weights to the loss.</p>',
        ]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher', ['firstname' => 'Laura', 'lastname' => 'Teacher']);
        $student = $generator->create_and_enrol($course, 'student', ['firstname' => 'Irene', 'lastname' => 'Soler']);
        \local_aiforumassist\course_config::save((int) $course->id, $config + ['enabled' => 1, 'waitminutes' => 0]);
        \local_aiforumassist\course_config::set_active_forums((int) $course->id, [(int) $forum->id]);
        return ['course' => $course, 'forum' => $forum, 'teacher' => $teacher, 'student' => $student];
    }

    /**
     * A new discussion whose first post is the question.
     *
     * @param stdClass $forum Forum.
     * @param stdClass $user Author.
     * @param string $subject Subject.
     * @param string $message Message.
     * @return stdClass The discussion (firstpost is the question's post id).
     */
    public function create_question(stdClass $forum, stdClass $user, string $subject, string $message): stdClass {
        return testing_util::get_data_generator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $forum->course, 'forum' => $forum->id, 'userid' => $user->id, 'name' => $subject, 'message' => $message,
        ]);
    }

    /**
     * Replace the AI subsystem with a stub that answers from a list and records every prompt.
     *
     * @param array $answers Each: a string (generated text) or null (the provider fails with $error).
     * @param string $error Error message for failed calls.
     * @return stdClass Recorder: ->prompts receives each prompt sent.
     */
    public function stub_ai(array $answers, string $error = '500: Provider down'): stdClass {
        $recorder = (object) ['prompts' => [], 'answers' => $answers, 'error' => $error];
        $manager = new class ($recorder) extends \core_ai\manager {
            /** @var stdClass Shared with the test. */
            private stdClass $recorder;

            /**
             * Constructor.
             *
             * @param stdClass $recorder Shared with the test.
             */
            public function __construct(stdClass $recorder) {
                $this->recorder = $recorder;
            }

            /**
             * Answer the next stubbed text.
             *
             * @param \core_ai\aiactions\base $action The action.
             * @return \core_ai\aiactions\responses\response_base
             */
            public function process_action(\core_ai\aiactions\base $action): \core_ai\aiactions\responses\response_base {
                $this->recorder->prompts[] = $action->get_configuration('prompttext');
                $text = array_shift($this->recorder->answers);
                if ($text === null) {
                    [$code, $message] = explode(': ', $this->recorder->error, 2) + [1 => ''];
                    return new \core_ai\aiactions\responses\response_generate_text(false, (int) $code, $message);
                }
                $response = new \core_ai\aiactions\responses\response_generate_text(true);
                $response->set_response_data(['generatedcontent' => $text, 'prompttokens' => 1000, 'completiontokens' => 200]);
                return $response;
            }
        };
        \core\di::set(\core_ai\manager::class, $manager);
        return $recorder;
    }

    /**
     * JSON answer of the AI for a question.
     *
     * @param array $fields Fields overriding a successful answer.
     * @return string
     */
    public function ai_answer(array $fields = []): string {
        return json_encode($fields + [
            'is_question' => true, 'answerable' => true, 'graded_task_request' => false,
            'answer' => "Dropout switches off random neurons while training.\n\nIt is a form of regularisation.",
            'sources' => ['S1'],
        ]);
    }
}
