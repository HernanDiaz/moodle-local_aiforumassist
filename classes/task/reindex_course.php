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

namespace local_aiforumassist\task;

/**
 * Background task: rebuild a course's material index (custom data: courseid).
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reindex_course extends \core\task\adhoc_task {
    /**
     * Run the task.
     */
    public function execute() {
        global $DB;
        $courseid = (int) ($this->get_custom_data()->courseid ?? 0);
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return;
        }
        $stats = \local_aiforumassist\content\indexer::index_course($courseid);
        mtrace("local_aiforumassist: course $courseid: {$stats['fragments']} fragments from {$stats['sources']} sources");
    }
}
