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
 * English strings for AI Forum Assistant.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aiforumassist:manage'] = 'Configure AI Forum Assistant in a course and review what it prepares';
$string['ainote'] = 'Prepared with the help of AI.';
$string['answer_sources'] = 'More in:';
$string['draft_alreadydecided'] = 'This was already published or discarded.';
$string['draft_askedby'] = 'Asked by {$a->name}, {$a->time}';
$string['draft_discard'] = 'Discard';
$string['draft_discarded'] = 'Discarded.';
$string['draft_intro_answer'] = 'Check the answer and its sources before publishing: it will be published in your name.';
$string['draft_intro_reminder'] = 'The dates and links come from the course calendar. It will be published in your name.';
$string['draft_message'] = 'Message';
$string['draft_opendiscussion'] = 'Open the discussion';
$string['draft_postgone'] = 'The student\'s post no longer exists.';
$string['draft_publish'] = 'Publish in my name';
$string['draft_published'] = 'Published in the forum.';
$string['draft_title'] = 'Review';
$string['draft_writeyourself'] = 'You can write your answer here, or reply in the forum and discard this.';
$string['error_retry'] = 'The AI provider is temporarily unavailable; the question about post {$a} will be tried again later.';
$string['kind_answer'] = 'Drafted answer';
$string['kind_question'] = 'Question for you';
$string['kind_reminder'] = 'Weekly reminder';
$string['messageprovider:pending'] = 'Drafts and questions waiting for you in AI Forum Assistant';
$string['notavailable'] = 'AI Forum Assistant is not available in this course. Ask the site administrator.';
$string['notify_answer_body'] = 'A student asked a question in {$a->course} and the AI drafted an answer. Review it before publishing.';
$string['notify_answer_subject'] = 'Tiko drafted an answer in {$a->course}';
$string['notify_question_body'] = 'A student asked a question in {$a->course} that the assistant could not answer.';
$string['notify_question_subject'] = 'A question for you in {$a->course}';
$string['notify_reminder_body'] = 'The weekly reminder of {$a->course} is waiting for your approval.';
$string['notify_reminder_subject'] = 'The weekly reminder of {$a->course} is ready';
$string['panel'] = 'AI Forum Assistant panel';
$string['panel_empty'] = 'Nothing here.';
$string['panel_from'] = 'Student';
$string['panel_index'] = 'Course materials indexed: {$a->fragments} fragments ({$a->time}).';
$string['panel_index_never'] = 'The course materials have not been indexed yet.';
$string['panel_reindex'] = 'Index now';
$string['panel_reindexed'] = 'Course materials indexed: {$a->fragments} fragments from {$a->sources} sources.';
$string['panel_review'] = 'Review';
$string['panel_settings'] = 'Settings';
$string['panel_show_all'] = 'All';
$string['panel_show_pending'] = 'Waiting for you';
$string['panel_state_off'] = 'The assistant does not draft answers in this course.';
$string['panel_state_on'] = 'The assistant drafts answers in {$a} forum(s) of this course.';
$string['panel_what'] = 'What';
$string['panel_when'] = 'When';
$string['pluginname'] = 'AI Forum Assistant';
$string['privacy:metadata:ai_provider'] = 'To draft an answer, the forum discussion and excerpts of the course materials are sent to the AI provider configured in Moodle\'s AI subsystem.';
$string['privacy:metadata:ai_provider:posttext'] = 'The text of the forum posts of the discussion, with students\' names replaced when the site setting is on.';
$string['privacy:metadata:course'] = 'Settings of the assistant in each course.';
$string['privacy:metadata:course:usermodified'] = 'The teacher who last changed the settings.';
$string['privacy:metadata:draft'] = 'What the assistant prepared for teachers from students\' questions.';
$string['privacy:metadata:draft:authorid'] = 'The student who asked the question.';
$string['privacy:metadata:draft:decidedby'] = 'The teacher who published or discarded the draft.';
$string['privacy:metadata:draft:message'] = 'The drafted answer.';
$string['privacy:metadata:draft:timecreated'] = 'When the draft was made.';
$string['privacy:metadata:log'] = 'Audit log of the assistant\'s AI calls and of teachers\' decisions.';
$string['privacy:metadata:log:action'] = 'What happened (publish, discard...).';
$string['privacy:metadata:log:prompt'] = 'The text sent to the AI, which quotes the student\'s question (with names replaced when the site setting is on).';
$string['privacy:metadata:log:timecreated'] = 'When it happened.';
$string['privacy:metadata:log:userid'] = 'The teacher who decided.';
$string['reason_ai_error'] = 'The AI provider did not answer.';
$string['reason_daily_limit'] = 'The daily limit of AI calls was reached.';
$string['reason_graded_task'] = 'It asks for the solution of graded work.';
$string['reason_not_in_materials'] = 'The course materials do not answer it.';
$string['reminder_intro_fallback'] = 'This is what is due in the coming days. Plan your week!';
$string['reminder_subject'] = 'Reminder: this week\'s deadlines';
$string['remindertype_close'] = 'closes';
$string['remindertype_due'] = 'due';
$string['remindertype_expectcompletionon'] = 'expected completion';
$string['remindertype_submissionsclose'] = 'submissions close';
$string['setting_allowedcategories'] = 'Categories';
$string['setting_allowedcategories_desc'] = 'Courses in these categories (and their subcategories) can use it.';
$string['setting_allowedcourses'] = 'Courses';
$string['setting_allowedcourses_desc'] = 'Short names of courses that can use it, one per line or separated by commas.';
$string['setting_availability_heading'] = 'Where it is available';
$string['setting_availability_heading_desc'] = 'Limit AI Forum Assistant to some categories and/or courses. Leave both empty to make it available everywhere.';
$string['setting_dailylimit'] = 'AI calls per course and day';
$string['setting_dailylimit_desc'] = 'Most questions drafted per course in 24 hours, to keep costs under control. Beyond it, questions go to the teacher without a draft. 0 = no limit.';
$string['setting_enabled'] = 'Enable AI Forum Assistant';
$string['setting_enabled_desc'] = 'Site-wide switch. When off, no course drafts answers or reminders.';
$string['setting_forcenote'] = 'Always mark replies prepared with AI';
$string['setting_forcenote_desc'] = 'Adds "Prepared with the help of AI" to every reply a teacher publishes from a draft, and teachers cannot turn it off. Use it if your institution asks staff to declare the use of AI.';
$string['setting_instruction'] = 'Institution-wide instruction';
$string['setting_instruction_desc'] = 'Optional text added to every request, e.g. a tone or a policy all answers must follow.';
$string['setting_redactnames'] = 'Keep students\' names away from the AI';
$string['setting_redactnames_desc'] = 'Before a forum discussion is sent to the AI provider, students\' names, email addresses, usernames and ID numbers are replaced with [STUDENT].';
$string['settings_ainote'] = 'Add "Prepared with the help of AI"';
$string['settings_ainote_help'] = 'Adds that line to the replies you publish from a draft. Not required when you review the reply yourself, but some institutions ask for it.';
$string['settings_answers'] = 'Answers to questions';
$string['settings_enabled'] = 'Draft answers to students\' questions';
$string['settings_enabled_help'] = 'When a student asks something in one of the forums below, the AI drafts an answer from the course materials and dates. You review it and publish it in your name; nothing is published without you.';
$string['settings_forums'] = 'Forums';
$string['settings_forums_help'] = 'The forums where the assistant drafts answers.';
$string['settings_helplevel'] = 'Help on graded work';
$string['settings_helplevel_explain'] = 'Explain';
$string['settings_helplevel_guide'] = 'Guide';
$string['settings_helplevel_help'] = 'Graded activities are never solved. "Guide" keeps answers short and points to the exact part of the materials; "Explain" allows longer explanations with examples, when the materials support them.';
$string['settings_noforums'] = 'This course has no forum where students can post yet.';
$string['settings_reminderday'] = 'Day';
$string['settings_reminderdays'] = 'Look ahead';
$string['settings_reminderforum'] = 'Forum';
$string['settings_reminderforum_news'] = 'Announcements';
$string['settings_reminderhour'] = 'Time';
$string['settings_remindermode'] = 'Publishing';
$string['settings_remindermode_auto'] = 'Automatic (posted by Tiko)';
$string['settings_remindermode_draft'] = 'I approve it';
$string['settings_remindermode_help'] = '"I approve it": the reminder waits in your panel and is published in your name. "Automatic": Tiko, the AI assistant, posts it, with a note saying it is an AI.';
$string['settings_reminders'] = 'Weekly reminders';
$string['settings_reminders_enabled'] = 'Weekly reminder of upcoming deadlines';
$string['settings_reminders_enabled_help'] = 'Once a week, a post listing the due dates and closing dates of the coming days, taken from the course calendar. The dates and links are written by the plugin, not by the AI, so they are always right; the AI only writes a short opening line.';
$string['settings_saved'] = 'Settings saved.';
$string['settings_title'] = 'AI Forum Assistant settings';
$string['settings_wait'] = 'Wait before drafting';
$string['settings_wait_help'] = 'Time to wait after a question, so you or a classmate can answer first. If someone from the teaching staff replies in the meantime, no draft is made.';
$string['settings_wait_none'] = 'Draft straight away';
$string['status_discarded'] = 'Discarded';
$string['status_pending'] = 'Waiting';
$string['status_published'] = 'Published';
$string['task_refresh_indexes'] = 'Refresh the indexes of the course materials';
$string['task_send_reminders'] = 'Prepare the weekly reminders';
$string['tiko_description'] = '<p>I am Tiko, an AI assistant. I post automatic reminders in some course forums. What I write can contain mistakes, and your teacher sees all of it.</p>';
$string['tiko_lastname'] = '(AI assistant)';
$string['tikonote_reminder'] = 'I am Tiko, an AI assistant. The dates come from the course calendar.';
