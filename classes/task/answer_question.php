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

use local_aiforumassist\question\responder;

/**
 * Background task: draft an answer to a student's forum post (custom data: postid).
 *
 * When the AI provider fails for a passing reason (rate limit, server error, network), the task fails
 * so that Moodle runs it again later, with its usual growing delay between attempts.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer_question extends \core\task\adhoc_task {
    /**
     * Run the task.
     *
     * @throws \moodle_exception When the AI provider is temporarily unavailable, so the task is retried.
     */
    public function execute() {
        $postid = (int) ($this->get_custom_data()->postid ?? 0);
        $outcome = responder::process($postid);
        mtrace("local_aiforumassist: post $postid: $outcome");
        if ($outcome === responder::RETRY) {
            throw new \moodle_exception('error_retry', 'local_aiforumassist', '', $postid);
        }
    }
}
