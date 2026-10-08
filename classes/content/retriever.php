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
 * Finds the fragments of a course's materials most related to a question, with BM25.
 *
 * Moodle's AI subsystem offers no embeddings, so the search is lexical and runs in PHP: words are
 * compared without case or accents, and common Spanish and English words are ignored. BM25 favours
 * fragments that contain the question's rarer words, without letting long fragments win by size.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retriever {
    /** BM25 term-frequency saturation. */
    private const K1 = 1.2;

    /** BM25 length normalisation. */
    private const B = 0.75;

    /** Common Spanish and English words that say nothing about the topic (without accents). */
    private const STOPWORDS = [
        'para', 'por', 'con', 'sin', 'los', 'las', 'del', 'que', 'una', 'uno', 'unos', 'unas', 'como', 'pero', 'mas',
        'este', 'esta', 'esto', 'estos', 'estas', 'ese', 'esa', 'eso', 'esos', 'esas', 'hay', 'han', 'has', 'hemos',
        'muy', 'tan', 'sus', 'mis', 'tus', 'nos', 'les', 'ser', 'son', 'era', 'fue', 'sea', 'estar', 'estan', 'tiene',
        'tengo', 'tienen', 'puedo', 'puede', 'pueden', 'hacer', 'hago', 'hace', 'sobre', 'entre', 'cuando', 'donde',
        'cual', 'cuales', 'quien', 'porque', 'pregunta', 'duda', 'dudas', 'hola', 'gracias', 'saludos', 'alguien',
        'tambien', 'todo', 'todos', 'toda', 'todas', 'algo', 'nada', 'bien', 'solo', 'ahora', 'aqui', 'cada', 'otro',
        'otra', 'otros', 'otras', 'mismo', 'misma', 'sabe', 'saber', 'favor', 'profe', 'profesor', 'profesora',
        'the', 'and', 'for', 'with', 'without', 'that', 'this', 'these', 'those', 'are', 'was', 'were', 'have',
        'has', 'had', 'can', 'could', 'should', 'would', 'will', 'how', 'what', 'when', 'where', 'which', 'who',
        'why', 'does', 'did', 'not', 'but', 'from', 'about', 'into', 'there', 'their', 'they', 'you', 'your', 'our',
        'any', 'some', 'all', 'just', 'also', 'thanks', 'hello', 'question', 'anyone', 'please',
    ];

    /**
     * Normalised words of a text: lower case, no accents, at least 3 characters, no stop words.
     *
     * @param string $text Any text.
     * @return string[] Words in order, with repetitions.
     */
    public static function tokens(string $text): array {
        $plain = \core_text::specialtoascii(\core_text::strtolower($text));
        $words = preg_split('/[^a-z0-9]+/', $plain, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(
            $words,
            fn(string $word): bool => strlen($word) >= 3 && !in_array($word, self::STOPWORDS, true)
        ));
    }

    /**
     * The course fragments that best match a question.
     *
     * @param int $courseid Course whose index is searched.
     * @param string $query The question (subject and message).
     * @param int $limit Most fragments returned.
     * @param int $maxchars Most characters returned in total, so the prompt stays bounded.
     * @return \stdClass[] Rows of {local_aiforumassist_chunk} with a "score" field, best first.
     */
    public static function search(int $courseid, string $query, int $limit = 6, int $maxchars = 24000): array {
        global $DB;
        $querywords = array_unique(self::tokens($query));
        if (!$querywords) {
            return [];
        }
        $fields = 'id, cmid, title, chunkno, content';
        $chunks = $DB->get_records('local_aiforumassist_chunk', ['courseid' => $courseid], 'id', $fields);
        if (!$chunks) {
            return [];
        }

        // Term frequencies of the query words in each fragment (the title counts too) and document frequencies.
        $tf = [];
        $lengths = [];
        $df = array_fill_keys($querywords, 0);
        foreach ($chunks as $id => $chunk) {
            $words = self::tokens($chunk->title . ' ' . $chunk->content);
            $lengths[$id] = max(1, count($words));
            $counts = array_count_values($words);
            foreach ($querywords as $word) {
                if (!empty($counts[$word])) {
                    $tf[$id][$word] = $counts[$word];
                    $df[$word]++;
                }
            }
        }
        $n = count($chunks);
        $avglength = array_sum($lengths) / $n;

        $scores = [];
        foreach ($tf as $id => $words) {
            $score = 0.0;
            foreach ($words as $word => $count) {
                $idf = log(1 + ($n - $df[$word] + 0.5) / ($df[$word] + 0.5));
                $norm = $count + self::K1 * (1 - self::B + self::B * $lengths[$id] / $avglength);
                $score += $idf * $count * (self::K1 + 1) / $norm;
            }
            $scores[$id] = $score;
        }
        arsort($scores);

        $result = [];
        $chars = 0;
        foreach ($scores as $id => $score) {
            $length = \core_text::strlen($chunks[$id]->content);
            if (count($result) >= $limit || ($result && $chars + $length > $maxchars)) {
                break;
            }
            $chunks[$id]->score = $score;
            $result[] = $chunks[$id];
            $chars += $length;
        }
        return $result;
    }
}
