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
 * Cheap filter before calling the AI: does a forum post look like a question or a request for help?
 *
 * Generous on purpose: a false positive costs one AI call (which then says it is not a question);
 * a false negative leaves a student without an answer.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class detector {
    /** Words and phrases that usually start or signal a question (without accents, lower case). */
    private const SIGNALS = [
        'que', 'como', 'cuando', 'donde', 'cual', 'cuales', 'cuanto', 'cuanta', 'quien', 'por que', 'para que',
        'alguien sabe', 'duda', 'dudas', 'no entiendo', 'no me queda claro', 'no se', 'ayuda', 'se puede', 'hay que',
        'tengo que', 'debo', 'podria', 'podrias', 'me podeis', 'me puedes', 'error', 'no funciona', 'no consigo',
        'what', 'how', 'when', 'where', 'which', 'who', 'why', 'is it', 'are we', 'do we', 'does', 'can i', 'can we',
        'should', 'could', 'anyone know', 'not sure', 'confused', 'help', "doesn't work", 'does not work', 'stuck',
    ];

    /**
     * Whether a post looks like a question.
     *
     * @param string $subject Post subject.
     * @param string $message Post message (HTML or text).
     * @return bool
     */
    public static function is_question(string $subject, string $message): bool {
        $text = $subject . "\n" . content_to_text($message, FORMAT_HTML);
        if (str_contains($text, '?')) {
            return true;
        }
        $plain = ' ' . preg_replace('/[^a-z0-9\']+/', ' ', \core_text::specialtoascii(\core_text::strtolower($text))) . ' ';
        foreach (self::SIGNALS as $signal) {
            if (str_contains($plain, ' ' . $signal . ' ')) {
                return true;
            }
        }
        return false;
    }
}
