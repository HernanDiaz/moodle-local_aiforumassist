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
 * Review one draft: read the student's question and the proposed answer (or reminder), edit it, then
 * publish it in the teacher's name or discard it.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_aiforumassist\course_config;
use local_aiforumassist\publisher;

$id = required_param('id', PARAM_INT);
$draft = $DB->get_record('local_aiforumassist_draft', ['id' => $id], '*', MUST_EXIST);
$course = get_course($draft->courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/aiforumassist:manage', $context);

$url = new moodle_url('/local/aiforumassist/draft.php', ['id' => $draft->id]);
$panelurl = new moodle_url('/local/aiforumassist/panel.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('draft_title', 'local_aiforumassist'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('pluginname', 'local_aiforumassist'), $panelurl);

if ($draft->status !== 'pending') {
    redirect($panelurl, get_string('draft_alreadydecided', 'local_aiforumassist'), null, \core\output\notification::NOTIFY_WARNING);
}

$forcenote = (bool) get_config('local_aiforumassist', 'forcenote');
$form = new \local_aiforumassist\form\draft_review($url, [
    'draft' => $draft, 'forcenote' => $forcenote, 'defaultnote' => course_config::get($course->id)->ainote,
]);
$form->set_data([
    'id' => $draft->id,
    'subject' => $draft->subject,
    'message' => ['text' => (string) $draft->message, 'format' => FORMAT_HTML],
]);

if ($form->is_cancelled()) {
    redirect($panelurl);
}
// Discarding needs no text, so it is handled before validation.
if (optional_param('discard', '', PARAM_RAW) !== '' && confirm_sesskey()) {
    publisher::discard($draft);
    redirect($panelurl, get_string('draft_discarded', 'local_aiforumassist'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($data = $form->get_data()) {
    $draft->subject = $data->subject;
    $note = $forcenote || !empty($data->ainote);
    if ($draft->kind === 'reminder') {
        publisher::publish_reminder($draft, $data->message['text'], $note);
    } else {
        publisher::publish_answer($draft, $data->message['text'], $note);
    }
    redirect($panelurl, get_string('draft_published', 'local_aiforumassist'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('draft_title', 'local_aiforumassist'));

if ($draft->postid) {
    // The student's question, as posted, with a link to the discussion.
    $post = $DB->get_record('forum_posts', ['id' => $draft->postid]);
    $author = $post ? $DB->get_record('user', ['id' => $post->userid]) : null;
    $discussionurl = new moodle_url('/mod/forum/discuss.php', ['d' => $draft->discussionid], 'p' . $draft->postid);
    $question = $post
        ? html_writer::tag('h4', format_string($post->subject))
            . html_writer::div(get_string('draft_askedby', 'local_aiforumassist', (object) [
                'name' => $author ? fullname($author) : '-', 'time' => userdate($post->created),
            ]), 'small text-muted mb-2')
            . format_text($post->message, $post->messageformat, ['context' => $context])
            . html_writer::div(html_writer::link($discussionurl, get_string('draft_opendiscussion', 'local_aiforumassist')), 'mt-2')
        : get_string('draft_postgone', 'local_aiforumassist');
    echo $OUTPUT->box($question, 'generalbox mb-3', 'local-aiforumassist-question');
}

if ($draft->kind === 'question') {
    echo $OUTPUT->notification(
        get_string('reason_' . $draft->reason, 'local_aiforumassist') . ' '
            . get_string('draft_writeyourself', 'local_aiforumassist'),
        \core\output\notification::NOTIFY_WARNING,
        false
    );
} else {
    $intro = $draft->kind === 'reminder' ? 'draft_intro_reminder' : 'draft_intro_answer';
    echo $OUTPUT->notification(get_string($intro, 'local_aiforumassist'), \core\output\notification::NOTIFY_INFO, false);
}

$form->display();
echo $OUTPUT->footer();
