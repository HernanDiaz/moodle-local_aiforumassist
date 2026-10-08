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
 * The teacher's settings of the assistant in a course.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_aiforumassist\course_config;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/aiforumassist:manage', $context);
if (!\local_aiforumassist\availability::is_available_in_course($course)) {
    throw new moodle_exception('notavailable', 'local_aiforumassist');
}

$url = new moodle_url('/local/aiforumassist/coursesettings.php', ['courseid' => $course->id]);
$panelurl = new moodle_url('/local/aiforumassist/panel.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('settings_title', 'local_aiforumassist'));
$PAGE->set_heading(format_string($course->fullname));

// Forums where students can post: every forum of the course except the announcements one.
$forums = [];
foreach (get_fast_modinfo($course)->get_instances_of('forum') as $cm) {
    $forum = $DB->get_record('forum', ['id' => $cm->instance], 'id, type');
    if ($forum && $forum->type !== 'news') {
        $forums[$forum->id] = $cm->get_formatted_name();
    }
}

$forcenote = (bool) get_config('local_aiforumassist', 'forcenote');
$form = new \local_aiforumassist\form\course_settings($url, [
    'courseid' => $course->id, 'forums' => $forums, 'forcenote' => $forcenote,
]);
$config = course_config::get($course->id);
$active = course_config::active_forums($course->id);
$defaults = (array) $config;
foreach (array_keys($forums) as $forumid) {
    $defaults['forum_' . $forumid] = in_array($forumid, $active, true) ? 1 : 0;
}
$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect($panelurl);
}
if ($data = $form->get_data()) {
    $wasenabled = (bool) $config->enabled;
    course_config::save($course->id, [
        'enabled' => (int) $data->enabled,
        'waitminutes' => (int) $data->waitminutes,
        'helplevel' => $data->helplevel === 'explain' ? 'explain' : 'guide',
        'ainote' => $forcenote ? 1 : (int) $data->ainote,
        'reminders' => (int) $data->reminders,
        'reminderforumid' => isset($forums[(int) $data->reminderforumid]) ? (int) $data->reminderforumid : 0,
        'reminderday' => max(1, min(7, (int) $data->reminderday)),
        'reminderhour' => max(0, min(23, (int) $data->reminderhour)),
        'reminderdays' => (int) $data->reminderdays,
        'remindermode' => $data->remindermode === 'auto' ? 'auto' : 'draft',
    ]);
    $selected = [];
    foreach (array_keys($forums) as $forumid) {
        if (!empty($data->{'forum_' . $forumid})) {
            $selected[] = $forumid;
        }
    }
    course_config::set_active_forums($course->id, $selected);
    if ($data->enabled && !$wasenabled) {
        // Index the materials in the background, so the first question is answered with them.
        $task = new \local_aiforumassist\task\reindex_course();
        $task->set_custom_data(['courseid' => (int) $course->id]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
    redirect($panelurl, get_string('settings_saved', 'local_aiforumassist'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('settings_title', 'local_aiforumassist'));
$form->display();
echo $OUTPUT->footer();
