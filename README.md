# AI Forum Assistant for Moodle

> **Alpha (v0.1).** It works on a test site; try it in a test course before using it with students.

AI Forum Assistant helps teachers look after their course forums with Moodle's AI subsystem:

- **Answers to students' questions.** When a student asks something in a forum, the AI drafts an answer from the course's own materials and dates, and links to where it comes from. The teacher reviews it and publishes it with one click, in their own name. If the answer is not in the materials, or the student asks for the solution to a graded task, it does not answer and tells the teacher instead.
- **Weekly reminders.** Upcoming due dates and closing dates, taken from the course calendar, in a short friendly post. The dates and links are written by the plugin, not by the AI, so they cannot be wrong.

The AI proposes, the teacher decides. Posts the assistant publishes on its own (automatic reminders) are signed by **Tiko (AI assistant)**, so students always know when they are reading an AI.

From the author of [AI Grader Pro](https://github.com/HernanDiaz/moodle-local_aigrader).

## How it works

1. A teacher turns the assistant on in a course (*More > AI Forum Assistant > Settings*) and picks the forums where it should help.
2. The plugin indexes what students can see in the course: section summaries, activity descriptions, pages, books, and the files of resources and folders (PDF, Word, PowerPoint, OpenDocument, notebooks, text). Hidden activities are left out. The index is refreshed when activities change and every night.
3. When a student posts a question, the assistant waits (two hours by default, so classmates or the teacher can answer first). If nobody from the teaching staff has replied by then, it searches the index, sends the question with the best fragments and the course dates to the AI, and stores the draft.
4. The teacher gets a notification, opens the draft, edits it if needed, and publishes it or discards it. Everything waits in the assistant's panel in the course.

Student names, email addresses, usernames and ID numbers are replaced with `[STUDENT]` before anything is sent to the AI (site setting, on by default). Every AI call is logged.

## Settings

Site administration > Plugins > Local plugins > AI Forum Assistant:

- On/off switch for the site, and the courses or categories where it is available.
- Keep students' names away from the AI.
- Most AI calls per course and day (50 by default).
- Always add "Prepared with the help of AI" to published answers.
- An extra instruction for every answer (for example, your institution's tone).

In each course: the forums, the waiting time, how much to explain ("guide" points to the materials, "explain" gives longer explanations; graded work is never solved), and the weekly reminder (day, hour, forum, how many days ahead, draft for the teacher or posted by Tiko).

## Requirements

- Moodle 4.5 or later (it uses the AI subsystem introduced in 4.5).
- An AI provider configured in Site administration > AI > AI providers, with the "Generate text" action enabled.

## Design

The design of the first versions (in Spanish) is in [docs/diseno-v0.md](docs/diseno-v0.md).

## Tests

21 PHPUnit tests: indexing and search, deadlines, question detection, the whole answer pipeline with a stub AI provider (drafts, graded tasks, questions outside the materials, rate limits, the daily limit), publishing and discarding, reminders, Tiko, and the privacy provider.

## License

GPL-3.0-or-later. Free, with no paid version.
