# 2. Tamper-evident audit log: app-level hash chain + DB-level immutability

## Status

Accepted

## Context

Compliance-minded buyers want to know an audit trail wasn't edited after the fact — not just that the app doesn't expose an edit button, but that *nothing*, including someone with direct database access, can quietly alter history without it being detectable.

Two separate guarantees are needed, and they fail independently:

1. **Detection** — if a row's content changes, something should be able to prove it changed.
2. **Prevention** — direct `UPDATE`/`DELETE` should be blocked at the lowest level possible, not just discouraged by application code that a sufficiently privileged actor can bypass.

## Decision

Both, layered:

- **Hash chain (detection).** Each `audit_logs` row stores `hash = sha256(tenant_id, user_id, action, auditable_type, auditable_id, changes, previous_hash)`, where `previous_hash` is the prior row's hash for that tenant — one chain per tenant, not one global chain, so tenants can't see or infer each other's activity volume. `AuditChain::record()` locks the chain's tail (`lockForUpdate`) before computing the next hash, so concurrent writers can't both read the same previous hash and fork the chain. `GET /api/audit/verify` recomputes every hash from scratch and reports the first entry where the stored hash doesn't match its own content, or where `previous_hash` doesn't match the prior row's actual hash.
- **DB trigger (prevention).** `BEFORE UPDATE` and `BEFORE DELETE` triggers on `audit_logs` raise a MySQL error unconditionally. This fires for *any* client — Eloquent, raw SQL, a DBA with a MySQL shell — not just the app. An Eloquent-level guard (`static::updating()` throwing) exists too, as a fast-fail for the 99% case, but the trigger is the real backstop; the Eloquent guard is bypassable by anyone with direct SQL access, which is exactly the threat model this exists for.

Each `AuditChain::record()` call happens inside the same `DB::transaction()` as the change it describes, so the audit entry and the business-data change commit or roll back together — there's no window where one exists without the other.

## Consequences

- The trigger can't stop a *forged insert* (a crafted row with a plausible-looking but wrong hash) — only `UPDATE`/`DELETE` are blocked, because `INSERT` has to stay open for legitimate writes. This is exactly what `/audit/verify`'s hash recomputation is for: it doesn't matter how a bad row got there, a hash that doesn't match its own content is detectable either way. The test suite exercises both halves separately (raw SQL `UPDATE`/`DELETE` rejected by the trigger; a crafted `INSERT` with a mismatched hash caught by `/verify`), since the trigger literally cannot be tested via `UPDATE`.
- `CREATE TRIGGER`/`DROP TRIGGER` are non-transactional DDL in MySQL (implicit commit) — this is why the test suite never drops the trigger mid-test to simulate "what if it's bypassed"; doing so inside `RefreshDatabase`'s transaction wrapping would leave the trigger gone for every test that runs afterward.
- Verifying the full chain is O(n) in the number of audit entries for that tenant. Fine at demo scale; a production system with a high-volume tenant would want to checkpoint periodically (store a verified-through hash) rather than replay from the first entry every time.
