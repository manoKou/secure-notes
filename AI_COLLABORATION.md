# AI Collaboration

How I used AI agents on this project: who did what, how I reviewed it, and what I caught.
The rules the agents follow are in [`AGENTS.md`](AGENTS.md).

## Agents and responsibilities

| Agent | Responsibility |
|---|---|
| **Claude Code** | Planning, environment setup (with permission), file structure, proposes implementation in small chunks |
| **Codex** | Writes the feature tests from the spec, not from my code; smoke tests; security review |
| **Me** | Delegate, review every chunk before it is written, resolve disagreements, own the final quality |

## How work was integrated

- **Core logic** (models, policy, requests, service, controllers, routes): the agent proposes small chunks, I review them and type them in myself.
- **Review before writing:** every agent presents its code first; I review it, then either type it in myself or grant permission to write exactly that code. Nothing is reviewed after the fact.
- **Tests are independent:** Codex writes them from the requirements in parallel with my implementation, so they check what the app should do, not what I happened to write.

## Permissions granted

| When | Agent | Permission (exact task) | Reviewed / changed afterwards |
|---|---|---|---|
| Before the clock | Claude Code | Install Composer | Verified versions |
| Before the clock | Claude Code | Install PostgreSQL; create the app database and user | Verified non-superuser role |
| Before the clock | Claude Code | Install the Laravel kit and storage driver | 27/27 kit tests pass; fixed Livewire version mismatch |
| Before the clock | Claude Code | Skeleton Batch 2: `config/attachments.php`, `NotePolicy`, `NoteRequest`, `AttachmentRequest` (code reviewed first) | Written exactly as reviewed; syntax-checked |
| Before the clock | Claude Code | Skeleton Batch 3: `AttachmentService`, `NoteController`, `AttachmentController` (code reviewed first) | Written exactly as reviewed; syntax-checked |
| Before the clock | Claude Code | Skeleton Batch 4: notes + attachment routes, 5 note views, flash component (code reviewed first) | Written exactly as reviewed; `route:list` shows all 10 routes |
| Before the clock | Claude Code | Skeleton Batch 5: 4 feature test files with `->todo()` tests from the spec (code reviewed first) | Written exactly as reviewed; Codex replaces the todos |
| _TODO_ | | | |

## Issues caught in review

| Issue | Found by | How it was resolved |
|---|---|---|
| Serving the app as `localhost/secure-notes` through Apache would expose `.env` | Claude Code (I proposed it) | Serve only `public/` via `composer run dev` |
| Composer installed Livewire 4, but the starter kit needs Livewire 3 (profile page crashed) | Kit tests + manual check | Pinned Livewire to `3.*` |
| Kit's `package-lock.json` had 13 known vulnerabilities (2 critical) | `npm audit` | `npm audit fix` (non-breaking), 0 left |
| _TODO: issues from implementation, tests and Codex's security review_ | | |

## Disagreements between agents

### Codex questioned the spec before writing tests (before the clock)

Asked to plan its tests from the docs, Codex flagged 8 points. Claude Code reviewed them; I decided:

| Codex flagged | Decision |
|---|---|
| Note validation rules were undefined | **Gap closed:** title required (max 255), body optional (max 10,000) |
| `jpg` vs `jpeg`, `txt`/`docx` MIME detection unclear | **Added `jpeg`**; a real `.docx` upload goes in the smoke test (some systems detect it as `application/zip`) |
| The brief says "viewed or downloaded", the docs force download | **Kept forced download** (safer); documented as a trade-off, inline viewing listed as next step |
| A fake disk cannot prove the bucket is private | Moved to the smoke test (anonymous request → 403) |
| Don't re-test the starter kit's auth | Agreed (27 kit tests already cover it) |
| Test redirects and that feedback appears, not exact wording | Agreed |
| SQLite cannot catch every PostgreSQL difference | Agreed; smoke test runs on PostgreSQL + RustFS |
| Test successful file cleanup; review failure handling separately | Agreed; matches the "orphan file at worst" decision |

Also adopted: tests read size and types from `config/attachments.php` instead of hard-coding 10 MB.

_TODO: disagreements during implementation._
