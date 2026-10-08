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

use local_aiforumassist\extractor\docx_extractor;
use local_aiforumassist\extractor\ipynb_extractor;
use local_aiforumassist\extractor\odf_extractor;
use local_aiforumassist\extractor\pdf_extractor;
use local_aiforumassist\extractor\pptx_extractor;

/**
 * Builds the index of a course's materials: the text students can see, split into fragments.
 *
 * Indexed: the course summary, section summaries, every visible activity's name and description,
 * page content, book chapters, and the files of resources and folders (PDF, Word, PowerPoint,
 * OpenDocument, notebooks, text). Hidden activities and sections are left out, so an answer never
 * reveals something students cannot see.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class indexer {
    /** Most characters read from a single file. */
    private const MAX_FILE_CHARS = 300000;

    /**
     * Rebuild the index of a course.
     *
     * @param int $courseid Course to index.
     * @return array{fragments: int, sources: int, skipped: string[]} What was indexed, and files that could not be read.
     */
    public static function index_course(int $courseid): array {
        global $DB;
        // Reading a course's PDFs takes a few hundred MB.
        \core_php_time_limit::raise(600);
        raise_memory_limit(MEMORY_EXTRA);
        $course = get_course($courseid);
        $sources = [];
        $skipped = [];

        $sources[] = [0, format_string($course->fullname), content_to_text((string) $course->summary, $course->summaryformat)];

        $modinfo = get_fast_modinfo($course, -1);
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->visible) {
                continue;
            }
            $text = content_to_text((string) $section->summary, $section->summaryformat);
            if (trim($text) !== '') {
                $sources[] = [0, get_section_name($course, $section), $text];
            }
        }

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->visible || $cm->deletioninprogress || !$modinfo->get_section_info_by_id($cm->section)->visible) {
                continue;
            }
            // Activities often share a name across sections ("Theory and activities"): the section tells them apart.
            $sectionname = get_section_name($course, $modinfo->get_section_info_by_id($cm->section));
            foreach (self::module_sources($cm, $skipped) as [$title, $text]) {
                $sources[] = [(int) $cm->id, $sectionname . ' · ' . $title, $text];
            }
        }

        $now = time();
        $rows = [];
        $sourcecount = 0;
        foreach ($sources as [$cmid, $title, $text]) {
            $fragments = chunker::split($text);
            if ($fragments) {
                $sourcecount++;
            }
            foreach ($fragments as $i => $fragment) {
                $rows[] = (object) [
                    'courseid' => $courseid, 'cmid' => $cmid, 'title' => \core_text::substr($title, 0, 255),
                    'chunkno' => $i, 'content' => $fragment, 'timecreated' => $now,
                ];
            }
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_aiforumassist_chunk', ['courseid' => $courseid]);
        $DB->insert_records('local_aiforumassist_chunk', $rows);
        $DB->set_field('local_aiforumassist_course', 'indexedtime', $now, ['courseid' => $courseid]);
        $transaction->allow_commit();

        return ['fragments' => count($rows), 'sources' => $sourcecount, 'skipped' => $skipped];
    }

    /**
     * The texts of one activity: its description plus whatever content its type holds.
     *
     * @param \cm_info $cm The activity.
     * @param string[] $skipped Receives the names of files that could not be read.
     * @return array[] [title, text] pairs.
     */
    private static function module_sources(\cm_info $cm, array &$skipped): array {
        global $DB;
        $name = $cm->get_formatted_name();
        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance]);
        if (!$instance) {
            return [];
        }
        $sources = [];
        $intro = isset($instance->intro) ? content_to_text((string) $instance->intro, $instance->introformat ?? FORMAT_HTML) : '';
        // The activity name is part of the text, so a question naming it finds it even without a description.
        $sources[] = [$name, trim($name . "\n\n" . $intro)];

        $context = \context_module::instance($cm->id);
        switch ($cm->modname) {
            case 'page':
                $sources[] = [$name, content_to_text((string) $instance->content, $instance->contentformat)];
                break;
            case 'book':
                $chapters = $DB->get_records('book_chapters', ['bookid' => $instance->id, 'hidden' => 0], 'pagenum');
                foreach ($chapters as $chapter) {
                    $sources[] = [
                        $name . ' · ' . format_string($chapter->title),
                        content_to_text((string) $chapter->content, $chapter->contentformat),
                    ];
                }
                break;
            case 'resource':
            case 'folder':
                $files = get_file_storage()->get_area_files(
                    $context->id,
                    'mod_' . $cm->modname,
                    'content',
                    0,
                    'sortorder DESC, filepath, filename',
                    false
                );
                foreach ($files as $file) {
                    $text = self::file_text($file);
                    if ($text === null) {
                        $skipped[] = $file->get_filename();
                        continue;
                    }
                    $title = $cm->modname === 'folder' || count($files) > 1 ? $name . ' · ' . $file->get_filename() : $name;
                    $sources[] = [$title, $text];
                }
                break;
        }
        return $sources;
    }

    /**
     * Plain text of a course file, or null when its format is not supported or it cannot be read.
     *
     * @param \stored_file $file A file of a resource or folder.
     * @return string|null
     */
    public static function file_text(\stored_file $file): ?string {
        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $text = match ($extension) {
            'pdf' => pdf_extractor::extract_file($file),
            'docx' => docx_extractor::extract_file($file),
            'pptx' => pptx_extractor::extract_file($file),
            'odt', 'odp' => odf_extractor::extract_file($file),
            'ipynb' => ipynb_extractor::extract_file($file),
            'txt', 'md' => (string) $file->get_content(),
            'html', 'htm' => content_to_text((string) $file->get_content(), FORMAT_HTML),
            default => null,
        };
        if ($text === null || trim($text) === '') {
            return null;
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        return \core_text::substr($text, 0, self::MAX_FILE_CHARS);
    }
}
