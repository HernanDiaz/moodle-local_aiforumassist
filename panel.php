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
 * The teacher's panel: what the assistant prepared in a course, waiting for review.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_aiforumassist\course_config;

$courseid = required_param('courseid', PARAM_INT);
$show = optional_param('show', 'pending', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/aiforumassist:manage', $context);
if (!\local_aiforumassist\availability::is_available_in_course($course)) {
    throw new moodle_exception('notavailable', 'local_aiforumassist');
}

$url = new moodle_url('/local/aiforumassist/panel.php', ['courseid' => $course->id, 'show' => $show]);
if ($action === 'reindex' && confirm_sesskey()) {
    $stats = \local_aiforumassist\content\indexer::index_course($course->id);
    $message = get_string('panel_reindexed', 'local_aiforumassist', (object) $stats);
    redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_aiforumassist'));
$PAGE->set_heading(format_string($course->fullname));

$config = course_config::get($course->id);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_aiforumassist'));

// State of the assistant in this course.
$settingsurl = new moodle_url('/local/aiforumassist/coursesettings.php', ['courseid' => $course->id]);
$fragments = $DB->count_records('local_aiforumassist_chunk', ['courseid' => $course->id]);
$state = $config->enabled
    ? get_string('panel_state_on', 'local_aiforumassist', count(course_config::active_forums($course->id)))
    : get_string('panel_state_off', 'local_aiforumassist');
$index = $config->indexedtime
    ? get_string('panel_index', 'local_aiforumassist', (object) [
        'fragments' => $fragments, 'time' => userdate($config->indexedtime),
    ])
    : get_string('panel_index_never', 'local_aiforumassist');
echo html_writer::div(
    html_writer::tag('p', s($state) . ' ' . html_writer::link($settingsurl, get_string('panel_settings', 'local_aiforumassist')))
    . html_writer::tag('p', s($index) . ' ' . html_writer::link(
        new moodle_url($url, ['action' => 'reindex', 'sesskey' => sesskey()]),
        get_string('panel_reindex', 'local_aiforumassist')
    )),
    'alert alert-info'
);

// Pending first; "all" shows the history too.
$tabs = [];
foreach (['pending', 'all'] as $tab) {
    $tabs[] = new tabobject($tab, new moodle_url($url, ['show' => $tab]), get_string('panel_show_' . $tab, 'local_aiforumassist'));
}
echo $OUTPUT->tabtree($tabs, $show === 'all' ? 'all' : 'pending');

$conditions = ['courseid' => $course->id] + ($show === 'all' ? [] : ['status' => 'pending']);
$drafts = $DB->get_records('local_aiforumassist_draft', $conditions, 'timecreated DESC', '*', 0, 200);
if (!$drafts) {
    echo $OUTPUT->notification(get_string('panel_empty', 'local_aiforumassist'), \core\output\notification::NOTIFY_INFO, false);
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('panel_when', 'local_aiforumassist'),
    get_string('panel_what', 'local_aiforumassist'),
    get_string('panel_from', 'local_aiforumassist'),
    get_string('subject', 'forum'),
    get_string('status'),
    '',
];
$table->attributes['class'] = 'generaltable local-aiforumassist-panel';
foreach ($drafts as $draft) {
    $author = $draft->authorid ? $DB->get_record('user', ['id' => $draft->authorid]) : null;
    $what = get_string('kind_' . $draft->kind, 'local_aiforumassist');
    if ($draft->kind === 'question' && $draft->reason) {
        $what .= html_writer::div(get_string('reason_' . $draft->reason, 'local_aiforumassist'), 'small text-muted');
    }
    $review = $draft->status === 'pending'
        ? html_writer::link(
            new moodle_url('/local/aiforumassist/draft.php', ['id' => $draft->id]),
            get_string('panel_review', 'local_aiforumassist'),
            ['class' => 'btn btn-primary btn-sm']
        )
        : '';
    $table->data[] = [
        userdate($draft->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        $what,
        $author ? fullname($author) : '-',
        format_string($draft->subject),
        get_string('status_' . $draft->status, 'local_aiforumassist'),
        $review,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
