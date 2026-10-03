# WorkflowDesk — Plan

A multi-tenant enquiry queue: Laravel 13 API + Vue/TypeScript front end. Portfolio-grade demo of enterprise patterns — tenancy, state machines, optimistic concurrency, tamper-evident audit logging, and SLA clocks with business-hours math.

## Scope

**In scope**
- Two synthetic tenants; roles: admin, counsellor, viewer.
- Every query scoped to tenant; cross-tenant access returns 404.
- Enquiry list (filters, pagination), detail view, assignment, allowed-transition state machine.
- Optimistic locking over HTTP: ETag / If-Match. Stale write → 412, no overwrite.
- Tamper-evident audit log: hash-chained per tenant, written in the same DB transaction as the change, immutable (DB trigger blocks UPDATE/DELETE). `GET /api/audit/verify` recomputes the chain and reports the first broken link. Test proves detection via raw-SQL tampering.
- SLA engine: priority → response/resolution targets; due times computed in tenant timezone against a business-hours + holiday calendar, stored in UTC. Scheduled command flags at-risk/breached and writes an audit entry. Clock is injectable; tests cover DST boundaries.
- Permission matrix test (role × tenant × action), request-ID logging, health endpoint, OpenAPI spec.
- Vue front end reusing portfolio demo UI behind a typed adapter (browser / live / unavailable modes).
- CI (GitHub Actions) against real MySQL, README, ADRs, Docker Compose (written, verified only in CI).

**Out of scope**
- Real email, real student data, SSO, billing, webhooks (deferred to WebhookLab).

## Machine setup

| Need | Status | Action |
|---|---|---|
| PHP 8.3+ | Missing (XAMPP has 7.3) | `winget install PHP.PHP.8.4`, installed alongside XAMPP, untouched |
| Composer | Missing | Official Composer-Setup.exe |
| MySQL | XAMPP has MariaDB 10.4 (EOL) | `winget install Oracle.MySQL` (8.4 LTS) |
| Node / npm / git | Installed | — |
| Docker | Missing, no WSL | Deferred to CI/docs phase; needs reboot |

## Phases

1. **Setup** (~1h) — Install PHP, Composer, MySQL. Scaffold Laravel 13. First passing test.
2. **Tenancy & auth** (~1 day) — Sanctum tokens, tenant scoping, policies, permission matrix test.
3. **Enquiries & workflow** (~1–2 days) — State machine, assignment, ETag/If-Match, transactions.
4. **Audit chain** (~1 day) — Hash chain, DB-trigger immutability, verify endpoint, tamper test.
5. **SLA engine** (~1–2 days) — Business calendar, scheduled command, DST tests, injectable clock.
6. **Vue front end** (~2 days) — Typed adapter (browser / live / unavailable), reuse portfolio UI.
7. **CI & docs** (~1 day) — GitHub Actions + real MySQL, README, ADRs, Docker Compose.

Each phase ends with passing tests.

## Status

- [x] Phase 1 — Setup
- [x] Phase 2 — Tenancy & auth
- [ ] Phase 3 — Enquiries & workflow
- [ ] Phase 4 — Audit chain
- [ ] Phase 5 — SLA engine
- [ ] Phase 6 — Vue front end
- [ ] Phase 7 — CI & docs
