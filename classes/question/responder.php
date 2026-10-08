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

namespace local_aiforumassist\question;

use local_aiforumassist\ai\client;
use local_aiforumassist\audit;
use local_aiforumassist\content\course_facts;
use local_aiforumassist\content\indexer;
use local_aiforumassist\content\retriever;
use local_aiforumassist\course_config;
use local_aiforumassist\name_redactor;
use local_aiforumassist\notifier;
use local_aiforumassist\tiko;

/**
 * Turns a student's forum question into something for the teacher: a drafted answer, or a notice that
 * the question needs them (not in the materials, a graded task, the AI failed, the daily limit).
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class responder {
    /** Outcomes of process(), for logs and tests. */
    public const DRAFTED = 'drafted';
    /** The question needs the teacher (a draft of kind "question" was stored). */
    public const NEEDS_TEACHER = 'needs_teacher';
    /** The post is not a question. */
    public const NOT_QUESTION = 'not_question';
    /** Nothing to do: the post is gone, the forum is off, a teacher answered, or it was handled already. */
    public const SKIPPED = 'skipped';
    /** The AI provider failed for a passing reason (rate limit, server error, network): try again later. */
    public const RETRY = 'retry';

    /**
     * Handle one forum post.
     *
     * @param int $postid The student's post.
     * @return string One of the outcome constants.
     */
    public static function process(int $postid): string {
        global $DB;
        $post = $DB->get_record('forum_posts', ['id' => $postid]);
        $discussion = $post ? $DB->get_record('forum_discussions', ['id' => $post->discussion]) : null;
        if (!$post || !$discussion || !empty($post->deleted) || !empty($post->privatereplyto)) {
            return self::SKIPPED;
        }
        $course = get_course($discussion->course);
        $config = course_config::get((int) $course->id);
        if (!course_config::answers_in_forum($course, (int) $discussion->forum)) {
            return self::SKIPPED;
        }
        $context = \context_course::instance($course->id);
        $handled = $DB->record_exists('local_aiforumassist_draft', ['postid' => $post->id]);
        if ($handled || self::is_staff((int) $post->userid, $context)) {
            return self::SKIPPED;
        }
        $thread = $DB->get_records_select(
            'forum_posts',
            'discussion = ? AND deleted = 0 AND privatereplyto = 0',
            [$discussion->id],
            'created, id'
        );
        foreach ($thread as $other) {
            if ($other->created > $post->created && self::is_staff((int) $other->userid, $context)) {
                return self::SKIPPED; // A teacher already answered.
            }
        }
        if (!detector::is_question((string) $post->subject, (string) $post->message)) {
            return self::NOT_QUESTION;
        }

        $limit = (int) get_config('local_aiforumassist', 'dailylimit');
        if ($limit > 0 && audit::answer_calls_since((int) $course->id, time() - DAYSECS) >= $limit) {
            self::store_question($course, $discussion, $post, 'daily_limit');
            return self::NEEDS_TEACHER;
        }

        if (!$config->indexedtime) {
            indexer::index_course((int) $course->id);
        }
        $earlier = array_filter($thread, fn($other) => $other->created < $post->created || $other->id == $post->id);
        [$promptthread, $query] = self::thread_for_prompt($earlier, $context);
        $chunks = retriever::search((int) $course->id, $query);
        $facts = course_facts::build($course, time());
        $language = $course->lang ?: get_config('core', 'lang') ?: 'en';
        $prompt = prompt::build(
            $facts['text'],
            $chunks,
            $promptthread,
            $language,
            (string) $config->helplevel,
            (string) get_config('local_aiforumassist', 'instruction')
        );

        $result = client::generate($context->id, tiko::user_id(), $prompt);
        $logid = audit::ai_call((int) $course->id, 'answer', $prompt, $result);
        if (!$result->success && self::is_transient((string) $result->error)) {
            return self::RETRY;
        }
        $answer = $result->success ? answer::parse($result->text) : null;
        if (!$answer) {
            $draftid = self::store_question($course, $discussion, $post, 'ai_error');
            audit::set_draft($logid, $draftid);
            return self::NEEDS_TEACHER;
        }
        if (!$answer->isquestion) {
            return self::NOT_QUESTION;
        }
        if ($answer->gradedtask || !$answer->answerable) {
            $draftid = self::store_question($course, $discussion, $post, $answer->gradedtask ? 'graded_task' : 'not_in_materials');
            audit::set_draft($logid, $draftid);
            return self::NEEDS_TEACHER;
        }

        $sources = self::sources($answer->sources, array_values($chunks), $facts['activities']);
        $draft = self::store(
            $course,
            $discussion,
            $post,
            'answer',
            null,
            self::html($answer->text, $sources, $language),
            $sources
        );
        audit::set_draft($logid, $draft->id);
        notifier::pending($draft);
        return self::DRAFTED;
    }

    /**
     * Whether an AI error is likely to pass if the call is repeated later: a rate limit (429), a server
     * error (5xx), a timeout or a network failure. Other errors (a wrong API key, a removed model) need a person.
     *
     * @param string $error Error message from the AI subsystem.
     * @return bool
     */
    public static function is_transient(string $error): bool {
        $pattern = '/\b(?:429|5\d\d)\b|rate.?limit|too many requests|timed? ?out|connection|could not resolve|curl/i';
        return preg_match($pattern, $error) === 1;
    }

    /**
     * Whether a user is course staff (can manage the assistant) or Tiko: their posts are not questions to answer.
     *
     * @param int $userid User.
     * @param \context_course $context Course context.
     * @return bool
     */
    private static function is_staff(int $userid, \context_course $context): bool {
        return $userid === tiko::user_id() || has_capability('local/aiforumassist:manage', $context, $userid);
    }

    /**
     * Posts for the prompt, with students' names replaced, and the search query (the question itself).
     *
     * @param \stdClass[] $posts Posts up to the question, oldest first.
     * @param \context_course $context Course context.
     * @return array{0: array[], 1: string}
     */
    private static function thread_for_prompt(array $posts, \context_course $context): array {
        $authors = array_unique(array_map(fn($p) => (int) $p->userid, $posts));
        $students = array_filter($authors, fn($id) => !self::is_staff($id, $context));
        [$names, $identifiers] = name_redactor::is_enabled() ? name_redactor::terms_for_users($students) : [[], []];
        $clean = fn(string $text): string => name_redactor::redact($text, $names, $identifiers);

        $thread = [];
        $query = '';
        foreach ($posts as $post) {
            $subject = $clean(format_string($post->subject));
            $text = $clean(trim(content_to_text((string) $post->message, $post->messageformat)));
            $thread[] = [
                'role' => self::is_staff((int) $post->userid, $context) ? 'teacher' : 'student',
                'time' => userdate($post->created, '%Y-%m-%d %H:%M'),
                'subject' => $subject,
                'text' => $text,
            ];
            $query = $subject . ' ' . $text;
        }
        return [$thread, $query];
    }

    /**
     * The sources the AI cited, as course modules: fragment labels map to their activity, activity labels to themselves.
     *
     * @param string[] $labels Labels from the AI ("S2", "A42").
     * @param \stdClass[] $chunks Fragments in prompt order (S1 = index 0).
     * @param array $activities Activities from the fact sheet, by cmid: [title, url].
     * @return array[] Unique list of ['cmid' => int, 'title' => string].
     */
    private static function sources(array $labels, array $chunks, array $activities): array {
        $sources = [];
        foreach ($labels as $label) {
            $number = (int) substr($label, 1);
            $cmid = $label[0] === 'S' ? (int) ($chunks[$number - 1]->cmid ?? 0) : $number;
            if ($cmid && isset($activities[$cmid])) {
                $sources[$cmid] = ['cmid' => $cmid, 'title' => $activities[$cmid]['title']];
            }
        }
        return array_values($sources);
    }

    /**
     * The drafted reply as HTML: the answer's paragraphs, then links to its sources.
     *
     * @param string $text Answer text, paragraphs separated by blank lines.
     * @param array[] $sources ['cmid', 'title'] list.
     * @param string $language Course language, for the "More in" line.
     * @return string
     */
    public static function html(string $text, array $sources, string $language): string {
        $html = '';
        foreach (preg_split('/\R\s*\R/u', trim($text)) as $paragraph) {
            $html .= '<p>' . nl2br(s(trim($paragraph)), false) . '</p>';
        }
        if ($sources) {
            $links = [];
            foreach ($sources as $source) {
                $url = new \moodle_url('/mod/' . self::modname($source['cmid']) . '/view.php', ['id' => $source['cmid']]);
                $links[] = '<li>' . \html_writer::link($url, s($source['title'])) . '</li>';
            }
            $label = get_string_manager()->get_string('answer_sources', 'local_aiforumassist', null, $language);
            $html .= '<p>' . s($label) . '</p><ul>' . implode('', $links) . '</ul>';
        }
        return $html;
    }

    /**
     * Module type name of a course module.
     *
     * @param int $cmid Course module.
     * @return string
     */
    private static function modname(int $cmid): string {
        global $DB;
        return (string) $DB->get_field_sql(
            'SELECT m.name FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module WHERE cm.id = ?',
            [$cmid]
        );
    }

    /**
     * Store a question the assistant cannot answer, and tell the teachers.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $discussion Discussion.
     * @param \stdClass $post The student's post.
     * @param string $reason not_in_materials | graded_task | ai_error | daily_limit.
     * @return int Draft id.
     */
    private static function store_question(\stdClass $course, \stdClass $discussion, \stdClass $post, string $reason): int {
        $draft = self::store($course, $discussion, $post, 'question', $reason, null, []);
        notifier::pending($draft);
        return (int) $draft->id;
    }

    /**
     * Insert a draft row.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $discussion Discussion.
     * @param \stdClass $post The student's post.
     * @param string $kind answer | question.
     * @param string|null $reason Why it needs the teacher (kind question).
     * @param string|null $message Drafted HTML (kind answer).
     * @param array[] $sources Sources of the answer.
     * @return \stdClass The stored row.
     */
    private static function store(
        \stdClass $course,
        \stdClass $discussion,
        \stdClass $post,
        string $kind,
        ?string $reason,
        ?string $message,
        array $sources
    ): \stdClass {
        global $DB;
        $re = get_string('re', 'forum');
        $subject = str_starts_with((string) $post->subject, $re) ? $post->subject : $re . ' ' . $post->subject;
        $now = time();
        $draft = (object) [
            'courseid' => $course->id, 'kind' => $kind, 'status' => 'pending', 'forumid' => $discussion->forum,
            'discussionid' => $discussion->id, 'postid' => $post->id, 'authorid' => $post->userid, 'reason' => $reason,
            'subject' => \core_text::substr($subject, 0, 255), 'message' => $message,
            'sources' => $sources ? json_encode($sources) : null, 'publishedpostid' => 0, 'decidedby' => 0,
            'timedecided' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ];
        $draft->id = $DB->insert_record('local_aiforumassist_draft', $draft);
        return $draft;
    }
}
