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

namespace local_aiforumassist\content;

/**
 * Splits a text into fragments of about the same number of words, keeping paragraphs together when they fit.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chunker {
    /** Words per fragment: small enough to send several, large enough to keep an explanation together. */
    public const WORDS = 300;

    /**
     * Split a text into fragments.
     *
     * @param string $text Plain text; blank lines separate paragraphs.
     * @param int $words Most words per fragment.
     * @return string[] Fragments with their whitespace collapsed; empty for a blank text.
     */
    public static function split(string $text, int $words = self::WORDS): array {
        $chunks = [];
        $current = [];
        foreach (preg_split('/\R\s*\R/u', $text) as $paragraph) {
            $paragraphwords = preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY);
            if (!$paragraphwords) {
                continue;
            }
            if ($current && count($current) + count($paragraphwords) > $words) {
                $chunks[] = implode(' ', $current);
                $current = [];
            }
            // A paragraph longer than a fragment is cut into fragment-sized pieces.
            while (count($paragraphwords) > $words) {
                $chunks[] = implode(' ', array_splice($paragraphwords, 0, $words));
            }
            $current = array_merge($current, $paragraphwords);
        }
        if ($current) {
            $chunks[] = implode(' ', $current);
        }
        return $chunks;
    }
}
