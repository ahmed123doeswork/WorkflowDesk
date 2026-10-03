# 5. One typed API interface, three implementations (live / browser / unavailable)

## Status

Accepted

## Context

The front end needs to work in at least two very different situations: talking to a real Laravel API, and running as a standalone demo with no backend at all (for static hosting, e.g. a portfolio deployment with no server to pay for). It also needs to behave sensibly when a real backend *should* be there but isn't reachable — that's a different situation from "there is deliberately no backend," and collapsing the two would either make the demo mode feel like an error state, or make a genuine outage look like intended behavior.

## Decision

Every view talks to one TypeScript interface, `WorkflowDeskApi` (`frontend/src/api/types.ts`). Three classes implement it:

- **`LiveAdapter`** — real `fetch()` calls, bearer-token auth, ETag/If-Match concurrency handling, maps `412` responses to a typed `ConflictError`.
- **`BrowserAdapter`** — an in-memory dataset (two tenants, seeded enquiries, a hash-chain-shaped audit log) persisted to `localStorage`, enforcing the *same* rules as the backend: tenant scoping, role checks, the transition graph, ETag/If-Match. It's a full reimplementation of the business rules, not a dumb fixture — the point is that the UI exercises the same code paths and the same failure modes either way.
- **`UnavailableAdapter`** — returned when a live backend was expected but a health-check probe (`GET /api/health`) didn't respond. Every method fails the same, named way (`ApiError` with status `0`), so the UI shows one clear "can't reach the backend" banner with a retry action, instead of each view inventing its own error handling for a fetch failure.

`src/api/index.ts` resolves which one to use once, at startup, based on `VITE_API_MODE`. The `auto` default always probes the real API and falls back to `unavailable` — never silently to `browser` — because degrading a real outage into "look, a demo!" would hide the actual problem from whoever's looking at it.

## Consequences

- Any new API capability has to be added to the interface and implemented three times. For a project this size that's a reasonable cost for the guarantee that no view can accidentally depend on a live-only behavior that silently breaks demo mode (or vice versa).
- `BrowserAdapter` mutates its in-memory records in place for simplicity, then explicitly returns a shallow copy (`{ ...enquiry }`) rather than the mutated reference — this was a real bug caught by hand-testing in a browser: Vue's `ref` change detection compares by reference, and handing back the same mutated object silently froze dependent `computed()` values (the drawer's "next allowed transitions" stayed stale after a status change) even though some unrelated template reads happened to look current. `LiveAdapter` never has this problem because every response is freshly parsed JSON.
- The browser demo's "any" user can authenticate with a fixed demo password — there's no real auth to bypass, so this is a convenience, not a security shortcut; it would be a bug in `LiveAdapter`, where it doesn't exist.
- `/api/health` exists specifically because Laravel's default health route (`/up`) isn't under the `api/*` path prefix, so it isn't covered by the default CORS policy — a cross-origin dev frontend couldn't read it. The same class of bug (a response header silently unreadable cross-origin) also hit the `ETag` header itself before `exposed_headers` was set explicitly in `config/cors.php`; both are notes-to-self for anyone adding a new cross-origin-visible response header later.
