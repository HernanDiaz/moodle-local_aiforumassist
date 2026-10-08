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

use local_aiforumassist\content\chunker;
use local_aiforumassist\content\dates;
use local_aiforumassist\content\indexer;
use local_aiforumassist\content\retriever;

/**
 * Tests for the course material index: splitting, indexing, searching, and the calendar deadlines.
 *
 * @package    local_aiforumassist
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aiforumassist\content\chunker
 * @covers     \local_aiforumassist\content\indexer
 * @covers     \local_aiforumassist\content\retriever
 * @covers     \local_aiforumassist\content\dates
 */
final class content_test extends \advanced_testcase {
    /**
     * Paragraphs that fit stay together; a long paragraph is cut into fragment-sized pieces.
     */
    public function test_chunker_keeps_paragraphs_and_cuts_long_ones(): void {
        $this->assertSame([], chunker::split("  \n\n "));
        $this->assertSame(['one two three four'], chunker::split("one two\n\nthree four", 10));
        $this->assertSame(['one two', 'three four'], chunker::split("one two\n\nthree four", 3));
        $this->assertSame(['a b c', 'd e'], chunker::split('a b c d e', 3));
    }

    /**
     * Words are compared without case or accents, and stop words are ignored.
     */
    public function test_tokens_ignore_case_accents_and_stopwords(): void {
        $this->assertSame(['perceptron', 'multicapa'], retriever::tokens('¿Qué es el PERCEPTRÓN multicapa?'));
        $this->assertSame(['dropout', 'reduce', 'overfitting'], retriever::tokens('How does dropout reduce the overfitting?'));
    }

    /**
     * The index holds visible material only, with the section in each title; the search finds the right fragment.
     */
    public function test_index_and_search(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        course_config::save((int) $course->id, ['enabled' => 1]);
        $generator->create_module('page', [
            'course' => $course->id, 'name' => 'Perceptron', 'section' => 1,
            'content' => '<p>The perceptron cannot learn XOR because XOR is not linearly separable.</p>',
        ]);
        $generator->create_module('page', [
            'course' => $course->id, 'name' => 'Regularisation', 'section' => 1,
            'content' => '<p>Dropout randomly switches off neurons and reduces overfitting.</p>',
        ]);
        $generator->create_module('page', [
            'course' => $course->id, 'name' => 'Exam answers', 'visible' => 0,
            'content' => '<p>Secret: the answer to question 3 is dropout.</p>',
        ]);
        $generator->create_module('book', ['course' => $course->id, 'name' => 'Handbook']);
        $book = $DB->get_record('book', ['course' => $course->id], '*', MUST_EXIST);
        $generator->get_plugin_generator('mod_book')->create_chapter([
            'bookid' => $book->id, 'title' => 'Attention', 'content' => '<p>Attention weighs every token of the sequence.</p>',
        ]);

        $stats = indexer::index_course((int) $course->id);

        $this->assertGreaterThan(0, $stats['fragments']);
        $this->assertNotEmpty(course_config::get((int) $course->id)->indexedtime);
        $contents = implode(' ', $DB->get_fieldset_select('local_aiforumassist_chunk', 'content', 'courseid = ?', [$course->id]));
        $this->assertStringContainsString('linearly separable', $contents);
        $this->assertStringContainsString('weighs every token', $contents);
        $this->assertStringNotContainsString('Secret', $contents);

        $hits = retriever::search((int) $course->id, 'Why does dropout help against overfitting?');
        $this->assertSame(get_section_name($course, 1) . ' · Regularisation', $hits[0]->title);
        $this->assertSame([], retriever::search((int) $course->id, 'the and of'));
    }

    /**
     * Student deadlines of visible activities and course events count; teacher-only and hidden ones do not.
     */
    public function test_deadlines_from_the_calendar(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $now = time();
        $generator->create_module('assign', [
            'course' => $course->id, 'name' => 'Lab 2', 'duedate' => $now + 2 * DAYSECS, 'gradingduedate' => $now + 3 * DAYSECS,
        ]);
        $generator->create_module('assign', [
            'course' => $course->id, 'name' => 'Hidden lab', 'duedate' => $now + DAYSECS, 'visible' => 0,
        ]);
        $generator->create_event([
            'courseid' => $course->id, 'eventtype' => 'course', 'name' => 'Live session', 'timestart' => $now + 4 * DAYSECS,
        ]);
        $generator->create_module('assign', ['course' => $course->id, 'name' => 'Lab 9', 'duedate' => $now + 30 * DAYSECS]);

        $deadlines = dates::between($course, $now, $now + 7 * DAYSECS);

        $this->assertSame(['Lab 2', 'Live session'], array_column($deadlines, 'name'));
        $this->assertSame(['due', 'course'], array_column($deadlines, 'type'));
    }
}
