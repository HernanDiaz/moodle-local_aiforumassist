# Changelog

All notable changes to `local_aiforumassist` (AI Forum Assistant) are documented
here. The format follows [Keep a Changelog](https://keepachangelog.com/),
versions follow Moodle's `YYYYMMDDXX` plugin-version convention with a
parallel semantic-style release name.

## [v0.1.0-alpha] — 2026-10-08

First working version.

### Added

- **Index of the course materials.** What students can see (section
  summaries, activity descriptions, pages, books, and PDF, Word,
  PowerPoint, OpenDocument, notebook and text files of resources and
  folders) split into fragments and searched with BM25, without
  embeddings. Refreshed in the background when activities change and
  every night; teachers can also refresh it from the panel.
- **Drafted answers to students' questions.** After a waiting time
  (two hours by default), if no teacher has replied, the AI answers
  from the best fragments and the course's activities and calendar
  deadlines, with links to the sources. The draft waits for the
  teacher, who publishes it in their own name, optionally with
  "Prepared with the help of AI", or discards it.
- **Questions handed to the teacher.** Requests for the solution of a
  graded task, questions the materials do not answer, AI failures and
  questions over the daily limit become a notice for the teacher
  instead of a draft. Rate limits and server errors are retried later.
- **Weekly reminders** of the coming deadlines, built from the course
  calendar; the AI only writes the opening line. As a draft for the
  teacher, or posted automatically by Tiko with a note saying it is
  an AI.
- **Tiko**, the assistant's user (cannot log in), with its own picture.
- Teacher panel, course settings and review page; notifications to
  the teachers when something is waiting.
- Site settings: on/off, availability by category or course, names
  kept away from the AI, daily limit per course, forced AI note, extra
  instruction.
- Audit log of every AI call and every teacher decision.
- Privacy provider (GDPR): export and deletion of students' questions
  and teachers' decisions; deleting a student also clears the text of
  the AI calls made for their questions.
- English and Spanish strings.
- 21 PHPUnit tests with a stub AI provider.
