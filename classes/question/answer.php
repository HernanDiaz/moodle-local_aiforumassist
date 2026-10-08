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
 * The AI's verdict on a forum post, read from its JSON answer.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer {
    /** @var bool The post asks something or asks for help. */
    public bool $isquestion = false;

    /** @var bool The course information is enough to answer it. */
    public bool $answerable = false;

    /** @var bool The student asks for the solution of a graded activity. */
    public bool $gradedtask = false;

    /** @var string The drafted reply (plain text, paragraphs separated by blank lines). */
    public string $text = '';

    /** @var string[] Labels of the sources used: "S1" (material fragment) or "A123" (activity, by cmid). */
    public array $sources = [];

    /**
     * Read the AI's JSON answer. Tolerates code fences and text around the JSON object.
     *
     * @param string $raw The generated text.
     * @return self|null Null when no valid JSON object can be found.
     */
    public static function parse(string $raw): ?self {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $data = json_decode(substr($raw, $start, $end - $start + 1), true);
        if (!is_array($data)) {
            return null;
        }
        $answer = new self();
        $answer->isquestion = (bool) ($data['is_question'] ?? false);
        $answer->answerable = (bool) ($data['answerable'] ?? false);
        $answer->gradedtask = (bool) ($data['graded_task_request'] ?? false);
        $answer->text = self::without_labels(trim((string) ($data['answer'] ?? '')));
        $answer->sources = array_values(array_filter(
            array_map(fn($label) => strtoupper(trim((string) $label)), (array) ($data['sources'] ?? [])),
            fn(string $label): bool => preg_match('/^[SA]\d+$/', $label) === 1
        ));
        if ($answer->answerable && $answer->text === '') {
            $answer->answerable = false;
        }
        return $answer;
    }

    /**
     * Remove the source labels the AI sometimes writes into the answer despite being told not to:
     * parentheses that cite them ("(see S3)", "(ver S1 y S4)") and bare labels ("[S2]", "A42").
     * The links to the sources are added under the answer instead.
     *
     * @param string $text Answer text.
     * @return string
     */
    public static function without_labels(string $text): string {
        $label = '\[?\b[SA]\d+\b\]?';
        $text = preg_replace('/\s*\((?=[^()]*' . $label . ')[^()]*\)/u', '', $text);
        $text = preg_replace('/\s*' . $label . '/u', '', $text);
        // Tidy the punctuation the removals leave behind.
        return trim(preg_replace(['/ +([.,;:])/u', '/[ \t]{2,}/u'], ['$1', ' '], $text));
    }
}
