# 3. Optimistic locking via a version counter exposed as an ETag

## Status

Accepted

## Context

Two counsellors can open the same enquiry at once. If both save, the second write should not silently clobber the first — the spec requires a stale write to fail with `412` and leave the record untouched, using standard HTTP concurrency semantics (`ETag` / `If-Match`) rather than a bespoke `version` field in the request body.

## Decision

`Enquiry` carries a plain `version` integer, bumped by one in a `updating` model event whenever any attribute other than `version` itself is dirty. `$enquiry->etag()` returns `"{version}"` (quoted, per the HTTP spec). `GET` responses set the `ETag` header; every mutating endpoint (`update`, `assign`, `transition`) requires a matching `If-Match` header via a small `ChecksIfMatch` concern:

- missing `If-Match` → `428 Precondition Required`
- `If-Match` present but stale → `412 Precondition Failed`, no write performed
- matches → the write proceeds and the version advances

The front end treats a `412` as a distinct, typed error (`ConflictError`, carrying the server's current version of the record) rather than a generic failure, and shows a comparison dialog instead of retrying silently or failing opaquely.

## Consequences

- This is optimistic, not pessimistic, locking — there's no "someone else is editing this" lock held server-side, only a check at write time. Two people can both be looking at the same stale version; the first save wins, the second gets `412`. A real-time "X is editing this" indicator (mentioned as a nice-to-have in the UX brief) would need a presence layer (WebSockets or polling) on top of this, which is out of scope here.
- The version counter — not `updated_at` — is the concurrency token, because clock-based tokens have resolution and clock-skew problems across requests that land in the same millisecond or across replicas; an integer that only moves forward under a DB lock for the row doesn't.
- `428` vs `412` is a real distinction worth keeping separate in client code: the first means "you forgot to send the precondition," the second means "you sent it and it's wrong." Collapsing both into "it didn't work" would lose information the front end's conflict-resolution UI depends on.
- The front end hit a real-world version of this early: the browser demo adapter (no server involved) returns the *same mutated object reference* after a write, which defeats Vue's reference-equality change detection for the drawer's "next allowed transitions" — a reminder that optimistic-concurrency bugs aren't only a server-side concern; the client's own state management has to treat "the record changed" as a distinct event too, not infer it from object identity.
