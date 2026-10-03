# WorkflowDesk

A multi-tenant enquiry queue for admissions/support teams — Laravel 13 API + Vue 3 front end. Built as a portfolio piece demonstrating patterns enterprise buyers actually ask about: tenant isolation, optimistic concurrency, a tamper-evident audit log, and SLA clocks that respect business hours and time zones.

See [plan.md](plan.md) for the phase-by-phase build log, and [docs/adr](docs/adr) for the reasoning behind the non-obvious decisions.

## What's here

- **Two synthetic tenants**, three roles each (admin, counsellor, viewer). Every query is scoped to the caller's tenant; another tenant's record 404s rather than 403s, because the row is never in the query result set to begin with — see [ADR 0001](docs/adr/0001-tenancy-via-global-scope.md).
- **An enquiry workflow** with a fixed transition graph (not "any status to any status"), assignment, and optimistic locking over HTTP: every mutation requires an `If-Match` header, and a stale write gets a `412` with nothing overwritten — see [ADR 0003](docs/adr/0003-optimistic-locking-etag.md).
- **A tamper-evident audit log**: each entry hashes the previous entry's hash (one chain per tenant), written in the same DB transaction as the change it describes, and a MySQL trigger blocks `UPDATE`/`DELETE` on the table outright. `GET /api/audit/verify` recomputes the chain and reports the first broken link — see [ADR 0002](docs/adr/0002-audit-hash-chain.md).
- **An SLA engine**: priority sets response/resolution targets, due dates are computed against the tenant's business-hours calendar (correct across DST, not just correct in UTC), and a scheduled command flags at-risk/breached enquiries — see [ADR 0004](docs/adr/0004-sla-business-calendar.md).
- **A Vue front end** behind a typed adapter with three implementations — live, a self-contained browser demo, and an honest "unavailable" state — see [ADR 0005](docs/adr/0005-frontend-adapter-pattern.md) and [frontend/README.md](frontend/README.md).

## Project layout

```
app/                  Laravel application code
database/migrations/  Schema, including the audit_logs triggers
frontend/             Vue 3 + TypeScript front end (separate README)
tests/                PHPUnit feature/unit tests (66, all against real MySQL)
docs/adr/             Architecture decision records
.github/workflows/    CI
```

## Running it locally

### Backend

Requires PHP 8.3+, Composer, and MySQL.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Seeded accounts (all tenants, all roles share the password `password`):

| Email | Tenant | Role |
|---|---|---|
| `admin@acme-education.test` | Acme Education | admin |
| `counsellor@acme-education.test` | Acme Education | counsellor |
| `viewer@acme-education.test` | Acme Education | viewer |
| `admin@globex-learning.test` | Globex Learning | admin |
| `counsellor@globex-learning.test` | Globex Learning | counsellor |
| `viewer@globex-learning.test` | Globex Learning | viewer |

### Frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

With no backend running, set `VITE_API_MODE=browser` in `frontend/.env` first — the UI then runs entirely on a built-in demo dataset. Details in [frontend/README.md](frontend/README.md).

### Tests

```bash
php artisan test            # backend - 66 tests against real MySQL, not SQLite
cd frontend && npx vue-tsc -b && npm run build   # frontend type-check + build
```

### SLA scheduler

`php artisan enquiries:check-sla` recomputes SLA status for open enquiries and writes an audit entry when it changes. It's registered in `routes/console.php` to run every 5 minutes; running the scheduler in production needs a single cron entry calling `php artisan schedule:run` every minute, same as any Laravel app.

## CI

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs the backend suite against a real MySQL 8.4 service container (not SQLite — the audit-log triggers and DST-sensitive SLA math are both things SQLite would either fake or silently get subtly wrong), and type-checks + builds the frontend, on every push and pull request.
