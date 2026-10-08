# AI Forum Assistant for Moodle

> **In development.** There is no release yet; the first version (v0.1) is being built.

AI Forum Assistant helps teachers look after their course forums with Moodle's AI subsystem:

- **Answers to students' questions.** When a student asks something in a forum, the AI drafts an answer from the course's own materials and dates, and cites where it comes from. The teacher reviews it and publishes it with one click. If the answer is not in the materials, or the student asks for the solution to a graded task, it does not answer and tells the teacher instead.
- **Weekly reminders.** Upcoming due dates and closing dates, taken from the course calendar, in a short friendly post.

The AI proposes, the teacher decides. Automatic posts are signed by **Tiko (AI assistant)**, so students always know when they are reading an AI.

From the author of [AI Grader Pro](https://github.com/HernanDiaz/moodle-local_aigrader).

## Design

The design of the first versions (in Spanish) is in [docs/diseno-v0.md](docs/diseno-v0.md).

## Requirements

- Moodle 4.5 or later (it uses the AI subsystem introduced in 4.5).
- An AI provider configured in Site administration > AI > AI providers.

## License

GPL-3.0-or-later. Free, with no paid version.
