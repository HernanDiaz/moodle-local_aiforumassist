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
 * Event observers of AI Forum Assistant.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // A student's question: a new discussion or a reply.
    ['eventname' => '\mod_forum\event\discussion_created', 'callback' => '\local_aiforumassist\observer::discussion_created'],
    ['eventname' => '\mod_forum\event\post_created', 'callback' => '\local_aiforumassist\observer::post_created'],
    // The course materials changed: refresh the index.
    ['eventname' => '\core\event\course_module_created', 'callback' => '\local_aiforumassist\observer::course_module_changed'],
    ['eventname' => '\core\event\course_module_updated', 'callback' => '\local_aiforumassist\observer::course_module_changed'],
    ['eventname' => '\core\event\course_module_deleted', 'callback' => '\local_aiforumassist\observer::course_module_changed'],
];
