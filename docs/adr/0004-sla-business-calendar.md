# 4. SLA due dates computed in local business time, converted to UTC last

## Status

Accepted

## Context

"Respond within 4 business hours" means something different in Karachi than in New York, and means something different again across a daylight-saving transition. Due dates have to be (a) computed against the *tenant's* working hours and holidays, (b) correct across DST, and (c) stored in UTC so comparisons and sorting are unambiguous. Also, the spec specifically calls for an injectable clock, so SLA math is testable at exact, repeatable instants rather than depending on `now()` at test-run time — doubly important for DST tests, which only mean something at specific calendar dates.

## Decision

- **`BusinessCalendar`** walks forward in the tenant's own timezone: convert the starting instant to local wall-clock time, step through business minutes (09:00–17:00, Mon–Fri, minus per-tenant holidays), and convert back to UTC only at the very end. This ordering is what makes DST handling correct *by construction* rather than by special-casing: "9:00 local" stays "9:00 local" across a DST boundary because Carbon's timezone-aware arithmetic (`addDay()`, `setTime()`) already encodes the tenant's offset rules — the code never does timezone-unaware minute arithmetic that would need separate DST correction.
- **`Clock` interface** (`SystemClock` in production, `FrozenClock` in tests) is injected into `SlaCalculator`, the enquiry controller, and the scheduled command — nothing calls `now()` directly. Tests bind a `FrozenClock` at an exact instant (including the literal weekend spanning the 2026 US spring-forward and fall-back transitions) and assert the exact resulting UTC instant.
- **`enquiries:check-sla`** is a scheduled command, not computed lazily on read, because "at risk" and "breached" are time-dependent facts that need to become true even if nobody requests the enquiry at that moment — a breach that happened overnight should show as breached the next morning, not only once someone happens to GET it.

## Consequences

- Business hours (09:00–17:00) are currently a fixed global constant, not configurable per tenant — only timezone and holidays vary by tenant. Making hours themselves tenant-configurable would be a config schema change, not an architecture change, if a future tenant needed it.
- SLA status is a single enum (`on_track` / `at_risk` / `breached`) covering both the response and resolution targets, rather than two independent fields. This was a scope call: a combined status is simpler to test and display, at the cost of not being able to show "response breached, resolution still on track" as a distinct state in the UI. The underlying due dates (`response_due_at`, `resolution_due_at`) are both still stored, so a future UI could split the display without a data model change.
- `enquiries:check-sla` is registered to run every five minutes via the scheduler (`routes/console.php`), which in turn needs exactly one cron entry (`php artisan schedule:run` every minute) in any real deployment — a detail easy to forget since nothing fails loudly if it's missing, enquiries just silently stop updating their SLA status.
