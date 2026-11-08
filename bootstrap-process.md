# Secure Notes — Bootstrap Process & Architectural Decisions

A log of the decisions made before writing code, and why. Source brief: `../Senior Full Stack Developer.md`.

**App:** a small, secure web app where users manage private notes with file attachments.

---

## 1. Environment

| Item | Decision | Why |
|---|---|---|
| Web server / PHP | WampServer 3.4.0, Apache 2.4.65, **PHP 8.3.28** | Already in daily use; no Docker needed |
| Local URL | `composer run dev` → **http://127.0.0.1:8000** (Laravel's built-in dev server), *not* WAMP's Apache | Only `public/` is served, so `.env` (secrets), `vendor/` and `storage/` are never web-accessible. Serving it as `localhost/secure-notes` via Apache would expose `http://localhost/secure-notes/.env`. No VirtualHost, hosts-file edit or admin rights needed; reviewers use the same command |
| Database | **PostgreSQL 16** on port 5432 | Preferred by the brief; runs alongside existing MySQL (3306) / MariaDB (3307) without conflict |
| Object storage | **RustFS** 1.0.4 (S3-compatible) on ports 9000 (S3 API) / 9001 (web console) | Preferred by the brief; free and local; the code is the same as for real AWS S3. *Originally MinIO, but MinIO stopped publishing free binaries in 2025 (download returns `410 Gone`); swapped with config-only impact* |
| Package manager | Composer, pointed at WAMP's PHP 8.3 | Required for Laravel |
| Node.js | **Node 22 LTS on Windows** (nvm-windows) | Builds CSS/JS (Vite, Tailwind). Laravel 12 needs Node 20.19+; Windows had 18 (end of life). Kept in Windows, same as PHP: Node packages built in WSL don't run on Windows, and WSL misses file changes made by Windows editors |
| Mail | `MAIL_MAILER=log` | Password-reset mails are written to `storage/logs` instead of sent; no mail server needed |

## 2. Framework & Frontend

| Decision | Why |
|---|---|
| **Laravel 12** | Preferred by the brief |
| **Livewire starter kit** for authentication only | Gives tested register / login / logout / password reset / rate limiting / CSRF out of the box. We don't hand-roll security-critical auth code |
| **Blade + controllers** for notes and attachments | Blade is Laravel's server-side HTML templating: the server renders the page and sends finished HTML, with no separate JS app. Classic controllers (`NoteController`, `AttachmentController`) keep the request → validation → authorization → response flow explicit and easy to explain |
| Tailwind CSS (bundled with the kit) | Clean, consistent UI without writing a design system |
| Flash messages for feedback | Every action (created, updated, deleted, upload failed) tells the user what happened |

## 3. Data Model

```
users        id, name, email, password, timestamps          (from starter kit)

notes        id (ULID), user_id → users (cascade delete),
             title, body (text), timestamps
             index(user_id)

attachments  id (ULID), note_id → notes (cascade delete),
             disk, path, original_name, mime_type, size, timestamps
             index(note_id)
```

- **ULIDs instead of auto-increment IDs** (`HasUlids` trait). URLs look like `/notes/01JBX7K3M9...` and cannot be guessed (no ID enumeration). Authorization is the real protection; ULIDs are defense in depth.
- **Attachments have no `user_id`.** Ownership flows through the note, which gives a single source of truth.
- **The original filename is stored only as data**, never used as the storage path.

## 4. Security

| Concern | Decision |
|---|---|
| Authentication | Starter-kit auth with hashed passwords (bcrypt), session regeneration on login, login rate limiting, CSRF on every form |
| Authorization | One `NotePolicy` ("is this my note?") also covers attachments, because an attachment belongs to exactly one note; queries always go through the owner (`$request->user()->notes()`) |
| Another user's resource | Returns **404, not 403**, so the app doesn't reveal that the resource exists |
| Nested resources | Scoped route bindings: `notes/{note}/attachments/{attachment}`, so an attachment must belong to that note |
| Input validation | FormRequest classes: one `NoteRequest` (create and edit share the same rules) and `AttachmentRequest`. Notes: `title` required, max 255 characters; `body` optional, max 10,000 characters (a note can be just a title + attachments) |
| Upload validation | Allowlist: pdf, png, jpg, jpeg, webp, txt, docx. Max 10 MB. Both live in `config/attachments.php`, read by validation and by the upload form hint. Checked by real MIME type, not by extension. SVG and HTML are blocked because they can carry XSS |
| Storage path | Random name under `notes/{note_id}/`, never the client filename (prevents path traversal and overwrites) |
| Bucket | Private, so no public URLs exist |
| Downloads | Streamed through an authorized controller with `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`. The brief allows "viewed or downloaded"; forced download is the safer choice (a file can never run in the browser). Inline viewing of images/PDF is a next step |
| Output | Note bodies are plain text rendered with escaped `{{ }}`; no HTML/Markdown rendering |
| Errors | Friendly error pages and flash messages; no stack traces outside `APP_DEBUG=true` |

## 5. Architecture

- **Thin controllers.** Each controller method does: validate (FormRequest) → authorize (Policy) → act → respond.
- **`AttachmentService`**, injected through the constructor, owns storing and deleting files. Controllers don't touch `Storage` directly. This keeps file handling in one place and makes it easy to test.
- **No repository layer.** Eloquent already is the data-access abstraction; another layer would be complexity without benefit at this scale.
- **Deleting a note:** database rows are removed in a transaction, and the S3 objects are deleted after commit. If an S3 delete fails, the worst case is an orphaned file, never a broken database record. (Next step: a cleanup job.)
- **Downloads go through the app, not presigned URLs.** Every download passes the policy. Presigned short-lived URLs are the scaling improvement for large files and high traffic.

## 6. Testing

| Decision | Why |
|---|---|
| **Pest** | Laravel's default; concise syntax |
| Feature tests on **SQLite in-memory** | Fast, zero setup, anyone can run `php artisan test` right after cloning |
| `Storage::fake('s3')` | Upload and delete tests never hit real storage |
| Trade-off | SQLite is not Postgres (`LIKE` case sensitivity, loose types, JSON). In a real project the suite would run on PostgreSQL in CI |

**Key tests:** guests are redirected; user A gets 404 on user B's notes and attachments; note CRUD; rejected uploads (oversized file, disallowed type); deleting a note or attachment removes the stored file.

## 7. AI Agent Collaboration

| Agent | Responsibility |
|---|---|
| **Claude Code** | Planning, environment setup (with explicit permission), file structure, proposing implementation in small chunks |
| **Codex** | Proposes tests independently from the spec; smoke/canary testing; adversarial security review of the implementation |
| **Me (developer)** | Delegate tasks, review every chunk, resolve disagreements between agents, own the final quality |

### Code ownership policy

- **Agents do not write code into the project.** No agent creates or edits source files directly.
- Agents **present code to me in bite-sized chunks**: one class, method, migration or test at a time.
- I review each chunk, ask questions or request changes, and only then add it to the codebase myself.
- Every line in the repository has therefore been read and understood by me before it exists.
- Agents may read the code and run it (tests, artisan commands) to review and debug. 

### Explicit write permission (exception)

- When it saves time, I grant an agent **explicit, specific permission** to write code directly.
- The permission is scoped: it names the task and the files (e.g. "write the `notes` migration and the `Note` model").
- It applies only to that task. It does not carry over to the next task or to other agents.
- Changes made under permission still arrive in small chunks, and I review every diff before committing.
- Each granted permission is noted in `AI_COLLABORATION.md`, so the history shows what each agent wrote with permission and what I changed afterwards.

The process is logged in `AI_COLLABORATION.md`: who did what, what was reviewed, and what issues were caught and fixed.

## 8. Out of Scope / Next Steps

- Email verification (mail is logged, not sent)
- Presigned S3 URLs for direct downloads
- Inline viewing of images and PDFs (currently every file is downloaded)
- Background job to clean up orphaned files
- Virus scanning of uploads
- CI pipeline running tests against PostgreSQL

---

## Installation Checklist

- [x] Composer 2.11.1 installed in `C:\Users\onoma\AppData\Local\Programs\Composer` (on user PATH; wrapper pinned to `C:\wamp64\bin\php\php8.3.28\php.exe`)
- [x] PostgreSQL 16.15 installed (winget, unattended; service `postgresql-x64-16`, port 5432, no Stack Builder)
- [x] Database `secure_notes` owned by login role `secure_notes` (not a superuser)
- [x] `pdo_pgsql` + `pgsql` enabled in `phpForApache.ini` (WAMP menu) **and** `php.ini` (CLI)
- [x] RustFS 1.0.4 in `C:\rustfs` (start: `start-rustfs.bat`, localhost only); private bucket `secure-notes` created (anonymous access → 403)
- [x] ~~Apache VirtualHost~~ Not needed: app runs with `composer run dev` on http://127.0.0.1:8000
- [x] Node 22.23.4 / npm 10.9.9 active on Windows (`nvm use 22`)
- [x] Laravel 12.71 Livewire starter kit created and merged; Pest 4 (27/27 kit tests pass); Livewire pinned to 3.x; `npm audit` 0 vulnerabilities; Postgres migrated; S3 round-trip OK
- [x] WAMP PHP 8.3 on the Windows user PATH; `composer run dev` works on http://127.0.0.1:8000

---

## Setup Commands (do it yourself)

Every command needed to reproduce this setup by hand. Run them in **PowerShell** on Windows unless stated otherwise.

> **Never write real passwords into this file.** Use the placeholders below and keep real values in a password manager and in `.env` (which git ignores).
>
> | Placeholder | Meaning |
> |---|---|
> | `<SUPERUSER_PASSWORD>` | Password of the PostgreSQL admin user `postgres` |
> | `<APP_DB_PASSWORD>` | Password of the app's own database user `secure_notes` |
> | `<S3_ACCESS_KEY>` / `<S3_SECRET_KEY>` | RustFS keys (S3 username / password) |

### 1. Composer

The graphical way: run `Composer-Setup.exe` from https://getcomposer.org/download/ and pick `C:\wamp64\bin\php\php8.3.28\php.exe` when asked.

The command-line way (what was actually done):

```powershell
# Folder for Composer
$dir = "$env:LOCALAPPDATA\Programs\Composer"
New-Item -ItemType Directory -Force $dir | Out-Null
Set-Location $dir
$php = "C:\wamp64\bin\php\php8.3.28\php.exe"

# Download the installer and check it has not been tampered with
Invoke-WebRequest https://getcomposer.org/installer -OutFile composer-setup.php
$expected = (Invoke-WebRequest https://composer.github.io/installer.sig).Content.Trim()
$actual   = & $php -r "echo hash_file('sha384', 'composer-setup.php');"
if ($expected -ne $actual) { throw "Installer corrupt, do not run it" }

# Install, then remove the installer
& $php composer-setup.php --quiet
Remove-Item composer-setup.php

# Small wrapper so "composer" always uses WAMP's PHP 8.3
Set-Content composer.bat "@echo off`r`n`"$php`" `"%~dp0composer.phar`" %*"

# Add the folder to YOUR user PATH (no admin needed)
$userPath = [Environment]::GetEnvironmentVariable("Path", "User")
[Environment]::SetEnvironmentVariable("Path", "$userPath;$dir", "User")
```

Open a **new** terminal (close Windows Terminal / VS Code completely), then check: `composer --version`, which should say PHP 8.3.28.

### 2. PostgreSQL 16

```powershell
# Installs the server as a Windows service on port 5432 (a UAC/admin prompt appears; click Yes)
winget install --id PostgreSQL.PostgreSQL.16 --exact --source winget `
  --override "--mode unattended --unattendedmodeui minimal --superpassword <SUPERUSER_PASSWORD> --serverport 5432 --disable-components stackbuilder"
```

Check that it runs:

```powershell
Get-Service postgresql-x64-16          # Status should be "Running"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -h 127.0.0.1 -c "select version();"
# psql asks for the superuser password
```

Optional: stop it from starting with Windows (admin PowerShell):

```powershell
Set-Service postgresql-x64-16 -StartupType Manual
Start-Service postgresql-x64-16        # start it when you need it
```

### 3. Change the `postgres` superuser password

```powershell
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -h 127.0.0.1
```

Then, inside psql (the prompt looks like `postgres=#`):

```sql
\password postgres
-- type the new password twice (it is not shown, and is not saved in history)
\q
```

### 4. Create the app's database and user

Why a separate user: the app should never connect as the admin (`postgres`). If the app were compromised, a normal user can only touch its own database. This is called **least privilege**.

Open psql as the admin (step 3), then:

```sql
-- A login user that is NOT an admin and cannot create databases or users
CREATE ROLE secure_notes WITH LOGIN PASSWORD '<APP_DB_PASSWORD>' NOSUPERUSER NOCREATEDB NOCREATEROLE;

-- A database owned by that user (owner = full rights inside it, nothing outside)
CREATE DATABASE secure_notes OWNER secure_notes ENCODING 'UTF8';

\q
```

Check that the app user can log in:

```powershell
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U secure_notes -h 127.0.0.1 -d secure_notes -c "select current_user;"
```

Change the app user's password later (as `postgres` in psql): `\password secure_notes`

Undo everything (as `postgres` in psql): `DROP DATABASE secure_notes;` then `DROP ROLE secure_notes;`

### 5. Enable PostgreSQL drivers in WAMP's PHP

WAMP ships the drivers with PHP; they are only switched off (a `;` at the start of a line means "comment").
WAMP's PHP has **two** config files and both need the change:

| File | Used by |
|---|---|
| `C:\wamp64\bin\php\php8.3.28\phpForApache.ini` | Websites served by Apache |
| `C:\wamp64\bin\php\php8.3.28\php.ini` | Command line (Composer, `artisan`, tests) |

In both, change `;extension=pdo_pgsql` and `;extension=pgsql` to (no semicolon):

```ini
extension=pdo_pgsql   ; required: Laravel/Eloquent use PDO
extension=pgsql       ; optional: older pg_* functions, used by some tools
```

Apache side can also be done from the WAMP tray icon: **PHP → PHP extensions → pdo_pgsql / pgsql**.
Then restart WAMP: tray icon → **Restart All Services**.

Check:

```powershell
C:\wamp64\bin\php\php8.3.28\php.exe -m | findstr pgsql   # CLI: prints pdo_pgsql and pgsql
# Apache: open http://localhost/?phpinfo=-1 and search for "pdo_pgsql"
```

### 6. RustFS (local S3-compatible storage)

**What it is:** a free program that behaves like Amazon S3. Laravel talks to it exactly as it would talk to Amazon, but files are stored in `C:\rustfs\data`.
**Why not MinIO:** MinIO stopped publishing free downloadable binaries in 2025 (its download URL returns `410 Gone`).
**Not a service:** it only runs while its window is open.

Install (PowerShell):

```powershell
# 1. Download the official release and verify it (hash from the GitHub release page)
New-Item -ItemType Directory -Force C:\rustfs\data, C:\rustfs\logs | Out-Null
$zip = "$env:TEMP\rustfs.zip"
Invoke-WebRequest https://github.com/rustfs/rustfs/releases/download/1.0.4/rustfs-windows-x86_64-v1.0.4.zip -OutFile $zip
(Get-FileHash $zip -Algorithm SHA256).Hash   # must equal 2E3AF0B187DD70435DFDEFE5F61AAE7EEDE5A21FC9A2A2F07E289F727ADBCFA6
Expand-Archive $zip -DestinationPath C:\rustfs -Force
C:\rustfs\rustfs.exe --version

# 2. Create the keys (= S3 username and password). Use your own random values; no trailing newline.
Set-Content C:\rustfs\access-key.txt "<S3_ACCESS_KEY>" -NoNewline
Set-Content C:\rustfs\secret-key.txt "<S3_SECRET_KEY>" -NoNewline
```

3. Start script `C:\rustfs\start-rustfs.ps1` (keys are read from the files, so the script holds no secrets):

```powershell
$root = "C:\rustfs"
& "$root\rustfs.exe" server "$root\data" `
    --address 127.0.0.1:9000 `
    --console-enable `
    --console-address 127.0.0.1:9001 `
    --access-key-file "$root\access-key.txt" `
    --secret-key-file "$root\secret-key.txt"
```

`127.0.0.1` means only this computer can reach it, not other devices on the network.
For double-click starting, `C:\rustfs\start-rustfs.bat`:

```bat
@echo off
powershell -NoProfile -ExecutionPolicy Bypass -NoExit -File "%~dp0start-rustfs.ps1"
```

4. Create the private bucket `secure-notes`, either:
   - **Browser:** open http://127.0.0.1:9001, log in with the two keys, **Create Bucket** → `secure-notes` (leave it private), or
   - **Command line:**

```powershell
$u = "$(Get-Content C:\rustfs\access-key.txt -Raw):$(Get-Content C:\rustfs\secret-key.txt -Raw)"
curl.exe --aws-sigv4 "aws:amz:us-east-1:s3" --user $u -X PUT http://127.0.0.1:9000/secure-notes   # create
curl.exe -o NUL -w "%{http_code}" http://127.0.0.1:9000/secure-notes/                               # 403 = private, good
```

Daily use: double-click `C:\rustfs\start-rustfs.bat` before working on the project; close the window when done.

### 7. Node.js 22 on Windows (nvm-windows)

nvm installs several Node versions side by side; `nvm use` switches the **whole computer** to one.
Run in PowerShell (`nvm use` may show an admin/UAC prompt):

```powershell
nvm install 22        # download Node 22 LTS next to the existing 18
nvm use 22            # make it the active version
node --version        # v22.x
npm --version
nvm list              # shows all installed versions; * marks the active one
```

Back to the old version for another project: `nvm use 18.19.1`.

### 8. Create the Laravel project

The folder already contained my own files (README, docs), and `create-project` needs an empty folder, so the kit was created next to it and moved in. Run in PowerShell from `C:\wamp64\www\PHP-interview`:

```powershell
composer create-project laravel/livewire-starter-kit secure-notes-tmp
# move everything from secure-notes-tmp into secure-notes, except .gitignore (merge it by hand), then delete secure-notes-tmp
cd secure-notes
```

Switch the kit's PHPUnit tests to **Pest** (what the Laravel installer does when you pick Pest):

```powershell
composer remove phpunit/phpunit --dev --no-update
composer require pestphp/pest pestphp/pest-plugin-laravel pestphp/pest-plugin-drift --dev -W
php vendor\bin\pest --drift                  # converts the existing tests to Pest syntax
composer remove pestphp/pest-plugin-drift --dev
```

Fixes that were needed:

```powershell
# The kit's screens are built for Livewire 3, but Composer resolved Livewire 4
# (symptom: "No hint path defined for [layouts]" on the profile page)
composer require "livewire/livewire:3.*" -W

# The kit's package-lock.json pins old build tools with known vulnerabilities (2 critical)
npm install
npm audit fix            # non-breaking updates only → 0 vulnerabilities
npm run build
```

S3 driver, then configure `.env` (see README "How to run") and migrate:

```powershell
composer require "league/flysystem-aws-s3-v3:3.*"
php artisan migrate
php artisan test         # 27 passed
```

> In `cmd`/`.bat`, the `^` in a version constraint like `^3.7` is an escape character and gets swallowed; use `3.*` or quote carefully.

### 9. Put PHP on the Windows PATH

`composer run dev` starts its processes with a plain `php` command, so WAMP's PHP must be on PATH (the official Composer installer would do this). PowerShell, no admin needed:

```powershell
$d = "C:\wamp64\bin\php\php8.3.28"
$p = [Environment]::GetEnvironmentVariable("Path", "User")
[Environment]::SetEnvironmentVariable("Path", "$p;$d", "User")
```

Close the terminal **completely** (Windows Terminal window or VS Code), reopen it, then `php -v` should print 8.3.28.
