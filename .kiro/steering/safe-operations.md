---
inclusion: always
---

# Safe Operations

## Files you must NEVER edit

1. **`.env`** — contains production credentials and secrets. Never read it into output, never modify it.
2. **`.env.backup`**, **`.env.local`** — same as above.
3. **`vendor/`** — managed by Composer. Never edit files in this directory.
4. **`node_modules/`** — managed by npm. Never edit files in this directory.
5. **`*.sql` backup files** — the SQL dumps in the project root are production database backups. Never modify, delete, or reference their contents.
6. **`storage/logs/`** — read for debugging only. Never write to or delete log files manually (use artisan commands).
7. **`composer.lock`** — only modified by `composer install` or `composer update`. Never edit manually.

## Files you should not edit without explicit approval

1. **`composer.json`** — adding dependencies changes the project's supply chain. Ask first.
2. **`bootstrap/app.php`** — core application bootstrapping. Changes here affect everything.
3. **`config/*.php`** — shared configuration. Small additions (like a new log channel) are fine; structural changes need approval.
4. **Existing migrations** — never modify a migration that has already been run in production. Create a new migration instead.
5. **Existing tests** — do not delete or significantly alter existing tests without approval. Add new tests freely.

## Commands you must NEVER run

- **`vendor/bin/pint`** — never run Pint (the code formatter) in any form. No `pint`, `pint --dirty`, `pint --test`, or any variation.

## Bash safety

- **Never use heredocs or `echo` with multi-line content** to write files. Use file-writing tools instead.
- **Never use `sed -i` on production config files** (.env, config/*.php). Use the proper tools.
- **Always use `--no-interaction`** when running artisan commands to prevent hanging on prompts.
- **Never run destructive database commands** (`migrate:fresh`, `migrate:reset`, `db:wipe`) without explicit approval. Use `migrate` (forward-only) instead.

## Artisan safety

- `php artisan migrate` — safe (runs pending migrations forward)
- `php artisan migrate:fresh` — DANGEROUS (drops all tables). Never run without approval.
- `php artisan migrate:rollback` — ask first (reverts the last batch)
- `php artisan db:seed` — ask first (may duplicate data)
- `php artisan tinker` — safe for read queries, ask before writes
