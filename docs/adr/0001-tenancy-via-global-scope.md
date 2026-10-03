# 1. Tenant isolation via a global Eloquent scope, not per-controller checks

## Status

Accepted

## Context

Every tenant-owned resource (users, enquiries, audit log entries) must be invisible to other tenants. The spec also requires that reaching across tenants returns `404`, not `403` — a `403` confirms the record exists; a `404` doesn't.

The obvious alternative is a `TenantPolicy`-style check in each controller action: load the record, then compare `$record->tenant_id` to the caller's. That works, but it has to be remembered on every new endpoint and every new model, and it's easy to get the status code wrong (returning 403 after successfully loading a cross-tenant record).

## Decision

A `BelongsToTenant` trait applies a global `TenantScope` to any tenant-owned model. The scope filters every query — `index`, `show`, route-model binding, everything — by `Auth::user()->tenant_id` automatically. The trait also auto-fills `tenant_id` on create.

Because the scope operates at the query layer, a cross-tenant ID passed to `Route::model` binding simply isn't found: Eloquent's `ModelNotFoundException` becomes Laravel's standard `404`, with no special-casing required in any controller. The 404-not-403 behavior falls out of the architecture rather than being hand-coded per endpoint.

## Consequences

- New tenant-owned models get correct isolation by adding one trait, not by remembering a check.
- Any query that needs to see across tenants (the audit chain verifier, the SLA scheduler, admin tooling) must explicitly call `withoutGlobalScopes()` and filter by `tenant_id` itself — this is a deliberate "opt out loudly" posture rather than "opt in silently."
- Authenticated-but-wrong-tenant and not-authenticated both ultimately produce different status codes (`401` vs `404`) for the same underlying reason — the middleware pipeline sees different request states before the controller runs — which is consistent with the no-exists-for-you framing throughout.
