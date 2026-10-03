# Architecture decision records

Short records of the decisions that weren't obvious from reading the code — not a changelog, not a design doc for every file.

- [0001 — Tenant isolation via a global Eloquent scope](0001-tenancy-via-global-scope.md)
- [0002 — Tamper-evident audit log: hash chain + DB-level immutability](0002-audit-hash-chain.md)
- [0003 — Optimistic locking via a version counter exposed as an ETag](0003-optimistic-locking-etag.md)
- [0004 — SLA due dates computed in local business time, converted to UTC last](0004-sla-business-calendar.md)
- [0005 — One typed API interface, three implementations](0005-frontend-adapter-pattern.md)
