# WorkflowDesk front end

Vue 3 + TypeScript + Tailwind, talking to the Laravel API in [`../`](../).

## Three ways it can run

Everything the UI needs from the backend goes through one typed interface, [`WorkflowDeskApi`](src/api/types.ts). Three implementations satisfy it:

- **`live`** — real `fetch()` calls to the Laravel API, with bearer-token auth and ETag/If-Match concurrency handling.
- **`browser`** — a static in-memory demo dataset (two tenants, seeded enquiries, a working hash-chain-shaped audit log), persisted to `localStorage`. Enforces the same rules as the backend (tenant scoping, role checks, the transition graph) so it's not just a prettier mock. Used for demo hosting with no backend at all.
- **`unavailable`** — returned when `live` was requested but the API's health check didn't respond. Every call fails the same, named way, so the UI shows one clear "can't reach the backend" state instead of scattered per-call error handling.

`src/api/index.ts` resolves which one to use at startup, based on `VITE_API_MODE`:

```
VITE_API_MODE=auto    # (default) probe the API, fall back to "unavailable" if unreachable - never to a silent mock
VITE_API_MODE=browser # always use the demo dataset, no backend needed
```

## Running it

```bash
cp .env.example .env   # point VITE_API_BASE_URL at your Laravel instance if not localhost:8000
npm install
npm run dev
```

With no Laravel server running, set `VITE_API_MODE=browser` in `.env` and sign in with any of the demo accounts shown on the login screen (password `password`).

## Notable decisions

- **Drawer, not a page.** Clicking an enquiry opens it in a slide-over rather than navigating away, so the list underneath stays put.
- **Filters and the open enquiry are URL query params** (`?status=&priority=&search=&page=&enquiry=`), so the back button returns to exactly where you were.
- **Priority is encoded by weight/contrast, not color** — status already owns the color channel (new/in-progress/waiting/resolved/closed), and reusing it for priority (e.g. "urgent" in the same rose used for "breached") would make the two signals hard to tell apart at a glance.
- **Stale writes are a dialog, not a toast.** A 412 opens a comparison of "their change" vs "your change" with an explicit choice (discard-and-reload or overwrite), rather than silently retrying or failing.
