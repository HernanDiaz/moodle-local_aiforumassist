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
 * Scheduled task: rebuild every enabled course's material index once a night, catching changes that
 * fire no course module event (a replaced file, a renamed section).
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_indexes extends \core\task\scheduled_task {
    /**
     * Name shown in the scheduled tasks list.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_refresh_indexes', 'local_aiforumassist');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;
        foreach ($DB->get_fieldset_select('local_aiforumassist_course', 'courseid', 'enabled = 1') as $courseid) {
            if (!$DB->record_exists('course', ['id' => $courseid])) {
                continue;
            }
            try {
                $stats = \local_aiforumassist\content\indexer::index_course((int) $courseid);
                mtrace("local_aiforumassist: course $courseid: {$stats['fragments']} fragments");
            } catch (\Throwable $e) {
                mtrace("local_aiforumassist: course $courseid: index failed: " . $e->getMessage());
            }
        }
    }
}
