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

namespace local_aiforumassist\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider of AI Forum Assistant.
 *
 * Personal data: the drafts made from a student's question (who asked, and the drafted answer), the
 * teacher who decided on each draft, the teacher who last changed a course's settings, and the audit
 * log of teachers' decisions. Students' forum posts are sent to the site's AI provider, with their
 * names replaced, to draft the answers.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored and sent elsewhere.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_aiforumassist_draft', [
            'authorid' => 'privacy:metadata:draft:authorid',
            'message' => 'privacy:metadata:draft:message',
            'decidedby' => 'privacy:metadata:draft:decidedby',
            'timecreated' => 'privacy:metadata:draft:timecreated',
        ], 'privacy:metadata:draft');
        $collection->add_database_table('local_aiforumassist_log', [
            'userid' => 'privacy:metadata:log:userid',
            'action' => 'privacy:metadata:log:action',
            'prompt' => 'privacy:metadata:log:prompt',
            'timecreated' => 'privacy:metadata:log:timecreated',
        ], 'privacy:metadata:log');
        $collection->add_database_table('local_aiforumassist_course', [
            'usermodified' => 'privacy:metadata:course:usermodified',
        ], 'privacy:metadata:course');
        $collection->add_external_location_link('ai_provider', [
            'posttext' => 'privacy:metadata:ai_provider:posttext',
        ], 'privacy:metadata:ai_provider');
        return $collection;
    }

    /**
     * Course contexts where the user has data.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $params = ['level' => CONTEXT_COURSE, 'u1' => $userid, 'u2' => $userid];
        $contextlist->add_from_sql(
            "SELECT ctx.id FROM {context} ctx
               JOIN {local_aiforumassist_draft} d ON d.courseid = ctx.instanceid AND ctx.contextlevel = :level
              WHERE d.authorid = :u1 OR d.decidedby = :u2",
            $params
        );
        $contextlist->add_from_sql(
            "SELECT ctx.id FROM {context} ctx
               JOIN {local_aiforumassist_log} l ON l.courseid = ctx.instanceid AND ctx.contextlevel = :level
              WHERE l.userid = :u1",
            ['level' => CONTEXT_COURSE, 'u1' => $userid]
        );
        $contextlist->add_from_sql(
            "SELECT ctx.id FROM {context} ctx
               JOIN {local_aiforumassist_course} c ON c.courseid = ctx.instanceid AND ctx.contextlevel = :level
              WHERE c.usermodified = :u1",
            ['level' => CONTEXT_COURSE, 'u1' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users with data in a course context.
     *
     * @param userlist $userlist The list to fill.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $params = ['courseid' => $context->instanceid];
        $fields = ['authorid' => 'draft', 'decidedby' => 'draft', 'userid' => 'log', 'usermodified' => 'course'];
        foreach ($fields as $field => $table) {
            $userlist->add_from_sql($field, "SELECT $field FROM {local_aiforumassist_$table} WHERE courseid = :courseid", $params);
        }
    }

    /**
     * Export the user's data in the approved course contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $drafts = $DB->get_records_select(
                'local_aiforumassist_draft',
                'courseid = :courseid AND (authorid = :u1 OR decidedby = :u2)',
                ['courseid' => $context->instanceid, 'u1' => $userid, 'u2' => $userid],
                'timecreated'
            );
            $export = [];
            foreach ($drafts as $draft) {
                $export[] = (object) [
                    'kind' => $draft->kind,
                    'status' => $draft->status,
                    'subject' => $draft->subject,
                    'message' => $draft->message,
                    'youasked' => transform::yesno((int) $draft->authorid === $userid),
                    'youdecided' => transform::yesno((int) $draft->decidedby === $userid),
                    'timecreated' => transform::datetime($draft->timecreated),
                    'timedecided' => $draft->timedecided ? transform::datetime($draft->timedecided) : null,
                ];
            }
            $decisions = $DB->get_records(
                'local_aiforumassist_log',
                ['courseid' => $context->instanceid, 'userid' => $userid],
                'timecreated',
                'id, action, timecreated'
            );
            if ($export || $decisions) {
                writer::with_context($context)->export_data([get_string('pluginname', 'local_aiforumassist')], (object) [
                    'drafts' => $export,
                    'decisions' => array_values(array_map(fn($log) => (object) [
                        'action' => $log->action, 'time' => transform::datetime($log->timecreated),
                    ], $decisions)),
                ]);
            }
        }
    }

    /**
     * Delete everything the plugin stored about a course.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_course) {
            return;
        }
        $DB->delete_records('local_aiforumassist_draft', ['courseid' => $context->instanceid]);
        $DB->delete_records('local_aiforumassist_log', ['courseid' => $context->instanceid]);
        $DB->set_field('local_aiforumassist_course', 'usermodified', 0, ['courseid' => $context->instanceid]);
    }

    /**
     * Delete one user's data in the approved course contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                self::delete_users_in_course((int) $context->instanceid, [$userid]);
            }
        }
    }

    /**
     * Delete some users' data in a course context.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context instanceof \context_course) {
            self::delete_users_in_course((int) $context->instanceid, $userlist->get_userids());
        }
    }

    /**
     * Drafts made from these users' questions are deleted; their decisions and settings changes are anonymised.
     *
     * @param int $courseid Course.
     * @param int[] $userids Users.
     */
    private static function delete_users_in_course(int $courseid, array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;
        // The AI calls behind a student's drafts quote their question: keep the call (for the daily limit), not the text.
        $asked = "courseid = :courseid AND authorid $insql";
        $draftids = $DB->get_fieldset_select('local_aiforumassist_draft', 'id', $asked, $params);
        if ($draftids) {
            [$draftsql, $draftparams] = $DB->get_in_or_equal($draftids, SQL_PARAMS_NAMED, 'draft');
            $DB->set_field_select('local_aiforumassist_log', 'prompt', null, "draftid $draftsql", $draftparams);
            $DB->set_field_select('local_aiforumassist_log', 'response', null, "draftid $draftsql", $draftparams);
        }
        $DB->delete_records_select('local_aiforumassist_draft', $asked, $params);
        $DB->set_field_select('local_aiforumassist_draft', 'decidedby', 0, "courseid = :courseid AND decidedby $insql", $params);
        $DB->set_field_select('local_aiforumassist_log', 'userid', 0, "courseid = :courseid AND userid $insql", $params);
        $changed = "courseid = :courseid AND usermodified $insql";
        $DB->set_field_select('local_aiforumassist_course', 'usermodified', 0, $changed, $params);
    }
}
