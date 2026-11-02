# HT-CGRF

Internal portal for an **HT (High Tension) Consumer Grievance Redressal Forum**: electricity
consumers register grievances against their distribution company (DISCOM), cases are escalated
through a Relationship Manager to a Competent Authority, and every step is recorded in an
audit trail. Built on [Laravel](https://laravel.com) 13 (PHP 8.3+) with Blade + Tailwind CSS 4.

## Features

- **Complaint lifecycle** — `submitted → forwarded → resolved`, with auto-generated
  `ELEC-XXXXXXXX` ticket numbers, an append-only `complaint_actions` audit trail, and
  row locking (`lockForUpdate`) so concurrent forwards/resolves fail safely with `409`.
- **Role-aware dashboards** — consumers see their own queue, officials see region/assignment queues.
- **Complaint tracker** — horizontal stepper pipeline plus a vertical timeline of actions
  (`/complaints/{complaint}/track`).
- **Attachments** — consumers may attach files when registering (up to 5 files, 20 MB each);
  Competent Authorities can attach files on resolution.
- **Consumer OTP login** — consumers log in with an OTP sent to their registered mobile/email
  (no password); officials log in with email + password. Both are rate-limited.
- **Admin panel** — Site Admins manage the portal sidebar menu, complaint categories, user
  role mapping, and the DISCOM hierarchy used to scope officials to areas.
- **In-app notifications** — officials receive notifications on case activity.

## Roles

| Role | Capabilities |
| --- | --- |
| Consumer | OTP self-service login, register complaints with attachments, track own cases |
| Site Admin (`site_admin`) | Portal menu, complaint categories, user/role mapping |
| Relationship Manager (`relationship_manager`) | Region-scoped queue, forward `submitted` complaints to a Competent Authority |
| Competent Authority (`competent_authority`) | Assigned queue, resolve `forwarded` complaints with remarks |

Officials are scoped by region and, where mapped, to the DISCOM hierarchy
(Discom → Circle → Division → Subdivision → Section). Role checks are enforced by the
`role:` middleware (`App\Http\Middleware\EnsureUserHasRole`) **and** duplicated as
in-controller checks, deliberately.

## Workflow

```
Consumer                    Relationship Manager                 Competent Authority
--------                    --------------------                 -------------------
register() ─ 201
  status: submitted
  level:  regional_manager
      │
      └────► forwardToAuthority() ─ 200
                 status: submitted → forwarded
                 level:  regional_manager → competent_authority
                 (target authority must share the complaint's region)
                     │
                     └────► resolveGrievance() ─ 200
                                status: forwarded → resolved
                                resolved_at set, only by the assignee
```

Every transition runs in a `DB::transaction()` and appends an audit row
(`action`, `from/to_status`, `from/to_level`, actor, optional comment ≤ 5000 chars).
Violations return `401` (unauthenticated), `403` (wrong role/region/assignee), `409`
(already processed) or `422` (invalid input).

## Requirements

- PHP 8.3+ (developed against 8.5)
- Composer 2.x
- Node.js + npm (Vite + Tailwind asset build)
- MySQL (local dev uses WAMP at `127.0.0.1:3306`)

## Getting started

1. Install dependencies and set everything up in one step:

```sh
composer setup
```

This runs `composer install`, creates `.env` from `.env.example`, generates the app key,
runs migrations, and installs/builds the npm assets.

2. Configure the database in `.env` (dev default in use: the `ht_cgrf` MySQL database on WAMP):

```sh
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ht_cgrf
DB_USERNAME=root
```

`.env.example` itself ships with `DB_CONNECTION=sqlite` as a zero-config fallback.

3. Seed the demo data (accounts plus one complaint per lifecycle stage for the tracker):

```sh
php artisan db:seed
```

4. Start the dev server:

```sh
composer dev
```

The app will be available at [http://localhost:8000](http://localhost:8000).

### Demo accounts (password: `password`)

| Email | Role | Region |
| --- | --- | --- |
| `admin@ht-cgrf.test` | Site Admin | — |
| `consumer@ht-cgrf.test` | Consumer (North) | North |
| `consumer-south@ht-cgrf.test` | Consumer (South) | South |
| `rm-north@ht-cgrf.test` | Relationship Manager | North |
| `rm-south@ht-cgrf.test` | Relationship Manager | South |
| `ca-north@ht-cgrf.test` | Competent Authority | North |
| `ca-south@ht-cgrf.test` | Competent Authority | South |

Consumers also have an OTP login path (`POST /login/otp/request` → `POST /login/otp/verify`);
with `MAIL_MAILER=log` the code appears in `storage/logs/laravel.log`.

## Testing

```sh
composer test
```

Tests run on SQLite `:memory:` per `phpunit.xml`; the feature suite covers the routes,
role guards, workflow transitions and the concurrency/error matrix.

## Deployment (InfinityFree)

`build-infinityfree.sh` assembles an upload-ready package in `dist/infinityfree/htdocs/`
(production vendor/, pruned storage, pre-compiled views, `.env.production` and `htdocs/.htaccess`).

```sh
./build-infinityfree.sh         # build + assemble
./build-infinityfree.sh --zip   # also produce dist/infinityfree/htdocs.zip
./build-infinityfree.sh --skip-build   # reuse an existing public/build
```

Upload the contents of `dist/infinityfree/htdocs/` via FTP, chmod `775`
`storage/` and `bootstrap/cache/`, import the DB dump through phpMyAdmin, then confirm
the real `APP_URL`/`DB_*` values in `htdocs/.env`. See the script's final output for the
full checklist.

## Structure

| Path | Purpose |
| --- | --- |
| `app/Models` | Eloquent models (`Complaint`, `Consumer`, `ComplaintAction`, hierarchy models…) |
| `app/Enums` | `UserRole`, `ComplaintStatus`, `ComplaintLevel`, `ComplaintCategory` |
| `app/Http/Controllers` | `GrievanceController`, `DashboardController`, `Auth/`, `Admin/` controllers |
| `app/Http/Middleware` | `EnsureUserHasRole` (`role:` alias) |
| `app/Http/Resources` | JSON representations for the API endpoints |
| `app/Notifications`, `app/Rules` | DB notifications, custom validation rules |
| `app/View/Components` | Blade components incl. `Portal/` chrome |
| `resources/views` | Blade templates (auth, complaints, admin) |
| `routes/web.php` | All routes — session-based, JSON-ready, CSRF-protected |
| `database/migrations` | Users/consumers/roles, complaints, audit trail, hierarchy, menus |
| `tests/Feature` | Feature tests incl. the full denial matrix |
| `bootstrap/app.php` | Middleware alias, JSON exception rendering, rate limiter (`login` 5/min) |

## More documentation

- [`PROJECT_OUTLINE.md`](PROJECT_OUTLINE.md) — full architecture/behaviour outline and roadmap
- [`docs/controllers.md`](docs/controllers.md) — controller reference
- [`AGENTS.md`](AGENTS.md) / `CLAUDE.md` — agent coding guidelines (Laravel Boost)
