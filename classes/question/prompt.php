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

namespace local_aiforumassist\question;

/**
 * The prompt that asks the AI to draft an answer to a student's forum question.
 *
 * The instructions are in English (models follow them best); the answer is written in the course's language.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt {
    /**
     * Build the prompt.
     *
     * @param string $facts Course fact sheet (course_facts::build()).
     * @param \stdClass[] $chunks Material fragments, in the order they are labelled S1, S2...
     * @param array[] $thread Posts of the discussion up to the question: role, time, subject, text (names replaced).
     * @param string $language Language code the answer must be written in.
     * @param string $helplevel guide | explain.
     * @param string $instruction Institution-wide instruction from the site settings ('' for none).
     * @return string
     */
    public static function build(
        string $facts,
        array $chunks,
        array $thread,
        string $language,
        string $helplevel,
        string $instruction = ''
    ): string {
        $help = $helplevel === 'explain'
            ? 'You may explain concepts in detail, with examples, as long as the materials support the explanation.'
            : 'Keep explanations short and point the student to the exact part of the materials where the answer is, '
                . 'so they read it themselves.';
        $placeholder = \local_aiforumassist\name_redactor::PLACEHOLDER;

        $out = "You are Tiko, the teaching assistant of a Moodle course. A student asked something in a course forum.\n"
            . "Draft a reply for the teacher, who will review it before anything is published.\n\n"
            . "Rules:\n"
            . "1. Use ONLY the course information below: the course facts and the excerpts of the course materials.\n"
            . "   If they do not contain what is needed to answer, set \"answerable\" to false and leave \"answer\" empty.\n"
            . "   Never fill gaps with outside knowledge, and never invent dates, rules or requirements.\n"
            . "2. If the student asks for the solution, answers or code of a graded activity (assignment, quiz, exam,\n"
            . "   final project), set \"graded_task_request\" to true and leave \"answer\" empty.\n"
            . "3. If the post is not a question or a request for help (a greeting, thanks, an opinion, an announcement),\n"
            . "   set \"is_question\" to false.\n"
            . "4. " . $help . "\n"
            . "5. Write the answer in the language with code \"" . $language . "\", in a friendly, clear tone, addressing the\n"
            . "   student directly, in at most 200 words. Do not greet with a name: names were replaced with "
            . $placeholder . ".\n"
            . "   Do not sign it and do not mention that you are an AI: the teacher publishes it.\n"
            . "6. List in \"sources\" the labels of the excerpts (S1, S2...) and activities (A followed by a number) you used.\n"
            . "   Never write those labels in the answer itself: the system adds links to the sources below it.\n";
        if (trim($instruction) !== '') {
            $out .= "\nInstitution's instruction:\n" . trim($instruction) . "\n";
        }

        $out .= "\n=== COURSE FACTS ===\n" . $facts . "\n";
        $out .= "\n=== EXCERPTS OF THE COURSE MATERIALS ===\n";
        if (!$chunks) {
            $out .= "(no excerpt matched the question)\n";
        }
        foreach (array_values($chunks) as $i => $chunk) {
            $out .= "[S" . ($i + 1) . "] " . $chunk->title . "\n" . $chunk->content . "\n\n";
        }

        $out .= "=== FORUM DISCUSSION (the last post is the one to answer) ===\n";
        foreach ($thread as $i => $post) {
            $out .= "Post " . ($i + 1) . " by a " . $post['role'] . ", " . $post['time'] . "\n"
                . "Subject: " . $post['subject'] . "\n" . $post['text'] . "\n\n";
        }

        $out .= "=== OUTPUT ===\n"
            . "Return only a JSON object, with no text before or after it and no code fences:\n"
            . '{"is_question": true, "answerable": true, "graded_task_request": false, '
            . '"answer": "...", "sources": ["S1", "A42"]}' . "\n"
            . "In \"answer\", separate paragraphs with a blank line; plain text, no Markdown.\n";
        return $out;
    }
}
