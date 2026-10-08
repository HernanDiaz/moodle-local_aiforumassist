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

/**
 * Callbacks of AI Forum Assistant.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "AI Forum Assistant" to the course's navigation (the "More" menu) for its teachers.
 *
 * @param navigation_node $navigation The course node.
 * @param stdClass $course The course.
 * @param context $context The course context.
 */
function local_aiforumassist_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context): void {
    if (
        !has_capability('local/aiforumassist:manage', $context)
        || !\local_aiforumassist\availability::is_available_in_course($course)
    ) {
        return;
    }
    $navigation->add(
        get_string('pluginname', 'local_aiforumassist'),
        new moodle_url('/local/aiforumassist/panel.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_aiforumassist',
        new pix_icon('t/message', '')
    );
}
