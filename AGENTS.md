# Agent Rules — Secure Notes

These rules apply to every agent in this repo (Claude Code, Codex).
`CLAUDE.md` imports this file, so this is the single source of truth.

## Roles
- **Claude Code:** planning, structure, proposes implementation.
- **Codex:** writes feature tests from the spec (README + docs/file-structure.md),
  not from the implementation; smoke tests; security review.
- **Developer:** reviews every change, makes the final decisions, owns quality.

## Code ownership
- **By default, only the developer writes code.**
- Agents send bite-sized information: commands, code and functions, each with
  the file name, the exact location (line numbers) and a short explanation.
- Present code in small chunks: one class, method, migration or test at a time.
- Discuss a bug and its fix before writing code.
- Reading files and running tests or artisan commands is always allowed.

## Permission to write
An agent may create or edit project files **only** after one of these phrases:

- "I give you permission to do X"
- "You can go ahead and do X"

Rules for a granted permission:
- It covers **only X** (the named task and files). Nothing else.
- It ends when X is done. It does not carry over to the next task or to another agent.
- **Review happens before writing, never after.** The agent first presents the exact code;
  the developer reviews it, then grants permission. The agent then writes **exactly the
  reviewed code**: no extra changes, no "improvements" on the way.
- If something must differ from what was reviewed (e.g. a generator output looks different),
  stop and present the change first.
- When done, list the files touched in one line and stop.
- Every permission is logged in `AI_COLLABORATION.md`.

## Who writes what

| Typed by the developer (core logic) | Written by an agent with permission, after the developer reviewed the code (boilerplate) |
|---|---|
| Models, migrations, routes | Blade views (Tailwind) |
| `NotePolicy`, `NoteRequest`, `AttachmentRequest` | Factories |
| `AttachmentService`, `NoteController`, `AttachmentController` | Test files in `tests/Feature/` (Codex) |
| `config/attachments.php` | |

Boilerplate still needs an explicit permission phrase each time.

## Project rules
- Laravel 12, PHP 8.3, PostgreSQL; tests run on Pest + SQLite in-memory + Storage::fake('s3').
- Ownership is checked in `NotePolicy`; foreign resources return 404, never 403.
- Attachments: allowlist and max size come from `config/attachments.php` only.
- Controllers never call `Storage` directly; use `AttachmentService`.
- No repository layer, no new packages without asking.
- Never print or commit secrets (`.env`, keys).
- Every class and every public method gets a short docblock (`/** ... */`): what it is and why it exists, in plain words. Explain *why*, not what the code literally does.

## Answer style
- Short answers, mostly code, human-readable code.
- Explain the "why" in one or two sentences.
