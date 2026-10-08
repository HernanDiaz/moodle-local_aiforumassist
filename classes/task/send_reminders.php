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
 * Scheduled task (hourly): prepare the weekly reminders of the courses whose day and hour have come.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_reminders extends \core\task\scheduled_task {
    /**
     * Name shown in the scheduled tasks list.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_send_reminders', 'local_aiforumassist');
    }

    /**
     * Run the task.
     */
    public function execute() {
        foreach (\local_aiforumassist\reminder\scheduler::run(time()) as $courseid => $outcome) {
            mtrace("local_aiforumassist: reminder for course $courseid: $outcome");
        }
    }
}
