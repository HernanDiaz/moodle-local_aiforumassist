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

namespace local_aiforumassist;

use local_aiforumassist\ai\result;

/**
 * Audit log (table local_aiforumassist_log): every AI call and every teacher decision.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit {
    /**
     * Record an AI call.
     *
     * @param int $courseid Course.
     * @param string $action answer | reminder.
     * @param string $prompt Prompt sent (student names already replaced).
     * @param result $result Outcome of the call.
     * @param int $draftid Draft it produced, if any.
     * @return int Log row id.
     */
    public static function ai_call(int $courseid, string $action, string $prompt, result $result, int $draftid = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_aiforumassist_log', (object) [
            'courseid' => $courseid, 'draftid' => $draftid, 'userid' => 0, 'action' => $action,
            'provider' => $result->provider, 'model' => $result->model, 'prompthash' => hash('sha256', $prompt),
            'prompt' => $prompt, 'response' => $result->success ? $result->text : null,
            'tokensin' => $result->tokensin, 'tokensout' => $result->tokensout, 'durationms' => $result->durationms,
            'error' => $result->error, 'timecreated' => time(),
        ]);
    }

    /**
     * Link a logged call to the draft it produced.
     *
     * @param int $logid Log row.
     * @param int $draftid Draft.
     */
    public static function set_draft(int $logid, int $draftid): void {
        global $DB;
        $DB->set_field('local_aiforumassist_log', 'draftid', $draftid, ['id' => $logid]);
    }

    /**
     * Record a teacher's decision on a draft.
     *
     * @param \stdClass $draft The draft.
     * @param string $action publish | discard.
     * @param int $userid Teacher.
     */
    public static function decision(\stdClass $draft, string $action, int $userid): void {
        global $DB;
        $DB->insert_record('local_aiforumassist_log', (object) [
            'courseid' => $draft->courseid, 'draftid' => $draft->id, 'userid' => $userid, 'action' => $action,
            'durationms' => 0, 'timecreated' => time(),
        ]);
    }

    /**
     * AI calls made to answer questions in a course since a moment, for the daily limit.
     *
     * @param int $courseid Course.
     * @param int $since Timestamp.
     * @return int
     */
    public static function answer_calls_since(int $courseid, int $since): int {
        global $DB;
        return $DB->count_records_select(
            'local_aiforumassist_log',
            "courseid = :courseid AND action = 'answer' AND timecreated >= :since",
            ['courseid' => $courseid, 'since' => $since]
        );
    }
}
