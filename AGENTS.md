# AGENTS.md

Guidance for AI agents working in this repo. `README.md` is comprehensive and authoritative on features — read it before doing feature work.

## Stack

Laravel 13 / PHP 8.3, Inertia 2 + React 18 + TypeScript, Tailwind CSS 3, SQLite (WAL) as the only database. Single app, no monorepo.

## The codebase is in Spanish

Identifiers, comments, UI text, and command names are Spanish (e.g. `usuarioConRol()`, `citas:recordatorios`, `OptimizadorDeFotos`, `CLINIC_TIMEZONE` → `zona_horaria` in `config/clinic.php`). Match this when adding commands, tests, config keys, or comments.

## Commands

```bash
composer test                     # suite (Pest, runs config:clear first — use this over plain php artisan test)
vendor/bin/pest tests/Feature/Pagos/CobroTest.php   # single file
vendor/bin/pint --dirty           # PHP formatter (only touched files)
pnpm run build                    # build + typecheck (tsc is part of build)
pnpm run lint                     # ESLint over resources/js (auto-fixes)
```

- `composer test` calls `config:clear` before running for a reason: cached production config makes Pest's tests hit the real database instead of `:memory:`. If tests fail with weird DB errors, clear config first.
- Tests run against in-memory SQLite (`phpunit.xml`), use `RefreshDatabase` (applied to all `tests/Feature` via `tests/Pest.php`). This never touches the real DB — but staging/production cached config breaks this.
- Frontend needs `pnpm run build` (or running `pnpm run dev`) before `php artisan serve` serves pages on a fresh install.
- pnpm-specific config lives in `pnpm-workspace.yaml`: `shamefullyHoist: true` is required (vite-plugin-pwa resolves `workbox-window` transitively at build time) and `allowBuilds: esbuild: true` permits esbuild's scripts — note the setting was renamed from `onlyBuiltDependencies` (removed in pnpm 12; using the old key fails the install). Use `pnpm`, not npm.

## Setup gotchas

- The dev database lives at `storage/app/umamed.sqlite` — not `database/database.sqlite`. Touch it once, then `php artisan migrate --seed`. Seeder users are listed in `README.md`; password default `password123` (`SEEDER_PASSWORD`).
- Environment is documented in `.env.example` and `.env.production.example`. For production, always use the production example — deploying with the dev `.env` leaks debug info.
- The Docker image never contains `.env` (see `.dockerignore`). Deploy via `./deploy.sh` / `./deploy.ps1` (compose passes the host-side `.env` from the compose directory via `env_file`). For Raspberry builds use `.env.raspberry.example` as the template — it pins `DB_DATABASE=storage/app/umamed.sqlite` (so the SQLite file lives on the compose volume) and `SESSION_SECURE_COOKIE=false` until https exists. `docker-compose.yml` deliberately does NOT set up MySQL — the database is a SQLite file on the volume.
- Container build quirks (`Dockerfile`, built with podman on arm64):
  - Image is `php:8.5-fpm-alpine`; `pdo_sqlite` needs the `sqlite-dev` apk package. The frontend is built inside the image with **pnpm** (`npm i -g pnpm@12.16.0 && pnpm install --frozen-lockfile`) — `npm ci` fails since there is no `package-lock.json`.
  - `.dockerignore` used to exclude `tsconfig.json`, which made in-image `tsc` fail with bare help output ( glitches-looking, not a TS error) — don't exclude files the build itself consumes.
  - `supervisord.conf` commands must be single-line: supervisor's INI parser treats `\` line continuations as literal arguments, and the queue worker dies with "Too many arguments to queue:work".
  - Fresh volumes need `php artisan migrate --seed --force` + a container restart before the supervisors' workers come up (they give up quickly on a missing DB).

## Domain rules that break silently if ignored

- **Roles are enforced in routes**: `role:admin`, `role:admin|bariatra|recepcionista`, `role:paciente` etc. in `routes/web.php` (spatie/laravel-permission). A route guarded with `auth` alone is effectively a security bug — patients must only reach their own data.
- **Registration is intentionally disabled** (`/register` does not exist). Account creation happens from `/admin/users/create` by staff — don't "restore" registration.
- **Timezone**: every stored datetime is UTC; agenda forms display/capture in `config('clinic.zona_horaria')`. Never store or display agenda times in naive local time.
- **Audit**: every create/update/delete of clinical models is logged. New clinical models should use `#[ObservedBy(AuditObserver::class)]` — see `app/Observers/AuditObserver.php`.
- **Photos are sanitized on upload**: `App\Support\OptimizadorDeFotos` strips EXIF (privacy, health data) and shrinks to `config('clinic.foto_*')` limits. If bypassing it for a new upload path, you're reintroducing an EXIF leak.
- **No overlapping appointments**: enforced server-side and again inside a transaction that locks the doctor's schedule (see `AppointmentController`). If you touch appointment creation/updates, keep both checks.
- **WhatsApp defaults to driver `log`** — recordatorios are written to log in dev; cloud sending requires `WHATSAPP_DRIVER=cloud` + credentials in `.env`. Don't "fix" missing WhatsApp sends in dev without first checking the driver.
- **Web Push (VAPID)**: patient push subscriptions live in `push_subscriptions` (`laravel-notification-channels/webpush`); the recuerdo job sends WhatsApp AND/OR push, gated by `whatsapp.recordatorios.push_activos` (independent of Meta credentials). Profile-update pushes (medición/notas/recibo/documento de seguimiento) go through `App\Support\NotificadorDePerfil` and are gated by the same `push_activos` + patient's `recibir_recordatorios` consent + an existing subscription. VAPID keys are generated once with `php artisan webpush:vapid` and must never rotate. The package's published migration forces `Schema::connection(config('webpush.database_connection'))` — keep that config `null` (default connection) or plain `migrate` against other connections (e.g. `origen` in `HerramientasTest`) breaks.
- **Seguimiento documents** (PDFs the staff uploads: menús, pedimentos, notas) live in table `seguimiento` / model `DocumentoSeguimiento`, disk `local/seguimiento/`. Upload/delete restricted to `role:admin|bariatra`; download (`seguimiento.descargar`) is staff OR the owning patient — check that controller's dual gating before touching it. Deleting the record also deletes the file (`booted()` hook, same as `ProgressPhoto`).

## Frontend conventions

- Alias: `@` maps to `resources/js/` (see `vite.config.js`).
- Routes come from Ziggy (`route('name')`, not hardcoded `/path` — keeps URLs consistent with server-side middleware).
- Design tokens live in `tailwind.config.js` (`paper`, `pine`, `sand`, `arcilla`, `ink`). `emerald`, `blue`, `indigo`, and `sky` are **remapped to pine** and `purple` to `arcilla`, so existing Tailwind color names still yield the clinic palette. New screens should use the shared classes in `resources/css/app.css` (`.card`, `.field`, `.label`, `.btn-primary`, `.eyebrow`, `.page-header`).
- Page titles use `h2` and pick up `font-display` (Lora serif) automatically. **No emojis in the UI.** Dark mode is intentionally off (clinic UI is always light).
- This is a PWA (`vite-plugin-pwa`): service worker updates automatically; icons live in `public/icons/`.

<!-- graphicle:start (added by graphicle setup; graphicle disconnect removes it) -->
## Graphicle
Use the `graphicle` MCP server for where or how something works, for questions across files or history, and before changing unfamiliar code: `recall_project` or `query_graph` with the question, then `get_node` and `get_neighbors` for one symbol; answers cite file and lines. For an exact identifier you already know, grep is enough.
Without the server, run `graphicle context "<question>"` in a terminal.
<!-- graphicle:end -->
