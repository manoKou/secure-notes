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
| 20:08 (clock) | Claude Code | Step 1: migrations, model relationships + `$fillable`, factories (reviewed first; I had already typed the notes columns myself) | Migrated on PostgreSQL; relationships checked in tinker; 27 passed, 19 todos |
| 20:17 (clock) | Claude Code | Step 2: `NotePolicy`, `NoteRequest`, `NoteController`, note views, flash, sidebar link (reviewed first) | Views compile; Codex's tests run against it (see issues) |
| 20:53 (clock) | Claude Code | Step 3: `AttachmentRequest` (authorize first), `AttachmentService`, `AttachmentController`, attachments UI (reviewed first) | 46 passed; real round trip on RustFS: random path, `../../` stripped from the name, forced download + nosniff, file removed on delete |
| 21:12 (clock) | Claude Code | UX fixes I requested after a browser check: files on the note form, misleading kit sidebar links removed, Laravel placeholders replaced (name, icon, favicon, dashboard/welcome → notes) | 70 passed; 2 kit tests updated for the new redirects |
| 21:31 (clock) | Claude Code | Review fixes: upload rate limiter, logging of failed storage deletes (reviewed first) | 77 passed; `route:list` shows the throttle on the 3 upload routes |
| 21:36 (clock) | Claude Code | CI fix: tests.yml runs Pest on PHP 8.3 | Green on GitHub |
| 21:50 (extension) | Claude Code | Gap check against the brief: README setup steps, missing-file download (500 → flash error), branded error pages (reviewed first) | 77 passed; missing-file redirect and 404 page verified |

## Issues caught in review

| Issue | Found by | How it was resolved |
|---|---|---|
| Serving the app as `localhost/secure-notes` through Apache would expose `.env` | Claude Code (I proposed it) | Serve only `public/` via `composer run dev` |
| Composer installed Livewire 4, but the starter kit needs Livewire 3 (profile page crashed) | Kit tests + manual check | Pinned Livewire to `3.*` |
| Kit's `package-lock.json` had 13 known vulnerabilities (2 critical) | `npm audit` | `npm audit fix` (non-breaking), 0 left |
| A non-owner sending an **invalid** update got a validation redirect (302) instead of 404: FormRequest validation runs *before* the controller's policy check, which reveals that the note exists | Codex's test (written from the spec) failed against Claude's step 2 | **Fixed** (with permission): `NoteRequest::authorize()` now runs `NotePolicy` before the rules; the duplicate check in `NoteController::update()` was removed. The test passes |
| **Security review (Codex, read-only):** no high-severity issues; all categories clean (auth, 404 rule incl. the new `files[]` path, validation, paths, headers, XSS, CSRF, mass assignment, error leakage) | Codex | See the 4 findings below |
| Medium: no upload rate limit or per-user storage quota | Codex review | **Fixed (rate limit):** named `uploads` limiter, 20/min per user, on note store/update and attachment upload. Quota moved to Next steps (needs a product decision) |
| Low: private visibility is implicit (relies on bucket default) | Codex review | **Documented, not changed:** smoke test confirmed anonymous access → 403; forcing an ACL on every upload risked breaking RustFS uploads late in the clock |
| Low: if S3 delete fails after the row is deleted, the path is lost | Codex review | **Fixed (minimal):** failed deletes are logged with disk + path; a queued cleanup job stays in Next steps |
| Low: no malware scanning of allowed files | Codex review | Already in "Next steps" |
| Smoke test on the real stack: bucket and objects anonymous → 403; `/.env` → 404; guests redirected; a real Word `.docx` accepted, a hand-made zip renamed `.docx` rejected | Claude Code | Confirmed |
| Kit's CI ran PHPUnit on a Pest suite | GitHub Actions log | Fixed in tests.yml |
| Downloading an attachment whose stored file is missing crashed with a 500 | Claude Code, gap check against the brief | Checked before streaming; user gets a flash error |

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

### Codex pushed back on "files on the note form"

After my browser check I asked for files to be attachable directly on the create/edit form. Codex, working from the documented contract, advised against it for a 2-hour assessment: one upload endpoint was simpler, and a combined submit needs rollback rules. Fair point: the spec docs had not been updated yet.

**Decision (mine):** keep the feature. Uploading only after saving was a poor user experience. I updated the spec (`docs/file-structure.md`), defined the failure behavior explicitly (validate everything first; keep the note if storage fails midway, show an error), and asked Codex for tests covering exactly those rules.

