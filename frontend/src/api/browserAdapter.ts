import { ALLOWED_TRANSITIONS, type AuditEntry, type Enquiry, type EnquiryStatus, type Paginated, type Priority, type User } from '../types'
import { DEMO_PASSWORD, DEMO_TENANTS, DEMO_USERS, auditEntryFor, seedAuditTrail, seedEnquiries } from './mockData'
import { ApiError, ConflictError, type EnquiryFilters, type LoginResult, type WithEtag, type WorkflowDeskApi } from './types'

const STORAGE_KEY = 'workflowdesk.browserAdapter.v1'

interface BrowserState {
  enquiries: Enquiry[]
  auditTrail: Record<number, AuditEntry[]>
  currentUserId: number | null
}

function loadState(): BrowserState {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) return JSON.parse(raw) as BrowserState
  } catch {
    // Ignore corrupt/blocked storage and fall through to a fresh seed.
  }

  const enquiries = seedEnquiries()
  return { enquiries, auditTrail: seedAuditTrail(enquiries), currentUserId: null }
}

/**
 * A fully working copy of the product that never talks to a server - used
 * for demo hosting (e.g. a static portfolio build) where there is no
 * Laravel API behind it. Mirrors the live API's rules (tenant scoping,
 * role checks, the transition graph, ETag/If-Match) against an in-memory
 * dataset persisted to localStorage, so the UI exercises the exact same
 * code paths either way.
 */
export class BrowserAdapter implements WorkflowDeskApi {
  readonly mode = 'browser' as const

  private state: BrowserState = loadState()

  private persist() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state))
    } catch {
      // Best-effort only; demo mode still works without persistence.
    }
  }

  private currentUser(): User {
    const user = DEMO_USERS.find((u) => u.id === this.state.currentUserId)
    if (!user) throw new ApiError('Not authenticated.', 401)
    return user
  }

  private requireRole(roles: User['role'][]) {
    const user = this.currentUser()
    if (!roles.includes(user.role)) {
      throw new ApiError('You do not have permission to do that.', 403)
    }
    return user
  }

  private findOwnEnquiry(id: number): Enquiry {
    const user = this.currentUser()
    const enquiry = this.state.enquiries.find((e) => e.id === id && e.tenant_id === user.tenant_id)
    if (!enquiry) throw new ApiError('Enquiry not found.', 404)
    return enquiry
  }

  private etagFor(enquiry: Enquiry) {
    return `"${enquiry.version}"`
  }

  private assertIfMatch(enquiry: Enquiry, etag: string) {
    if (!etag) throw new ApiError('If-Match header is required.', 428)
    if (etag !== this.etagFor(enquiry)) throw new ConflictError(enquiry)
  }

  async login(email: string): Promise<LoginResult> {
    // Demo mode accepts the fixed demo password for any seeded account.
    const user = DEMO_USERS.find((u) => u.email === email)
    if (!user) throw new ApiError('No demo account with that email.', 422)

    this.state.currentUserId = user.id
    this.persist()

    const tenant = DEMO_TENANTS.find((t) => t.id === user.tenant_id)
    return { token: `demo-token-${user.id}`, user: { ...user, tenant } }
  }

  async logout(): Promise<void> {
    this.state.currentUserId = null
    this.persist()
  }

  async me(): Promise<User> {
    const user = this.currentUser()
    const tenant = DEMO_TENANTS.find((t) => t.id === user.tenant_id)
    return { ...user, tenant }
  }

  async listEnquiries(filters: EnquiryFilters): Promise<Paginated<Enquiry>> {
    const user = this.currentUser()
    let rows = this.state.enquiries.filter((e) => e.tenant_id === user.tenant_id)

    if (filters.status) rows = rows.filter((e) => e.status === filters.status)
    if (filters.priority) rows = rows.filter((e) => e.priority === filters.priority)
    if (filters.assigned_to) rows = rows.filter((e) => e.assigned_to === filters.assigned_to)
    if (filters.search) {
      const q = filters.search.toLowerCase()
      rows = rows.filter(
        (e) => e.subject.toLowerCase().includes(q) || e.student_name.toLowerCase().includes(q),
      )
    }

    rows = [...rows].sort((a, b) => b.id - a.id)

    const perPage = filters.per_page ?? 15
    const page = filters.page ?? 1
    const start = (page - 1) * perPage

    return {
      data: rows.slice(start, start + perPage),
      current_page: page,
      last_page: Math.max(1, Math.ceil(rows.length / perPage)),
      per_page: perPage,
      total: rows.length,
    }
  }

  async getEnquiry(id: number): Promise<WithEtag<Enquiry>> {
    const enquiry = this.findOwnEnquiry(id)
    // A fresh object, not the one living in this.state.enquiries - callers
    // (Vue refs in particular) compare by reference, and handing back the
    // same mutable object every time would make in-place edits invisible.
    return { data: { ...enquiry }, etag: this.etagFor(enquiry) }
  }

  private mutate(id: number, etag: string, mutator: (e: Enquiry) => void): WithEtag<Enquiry> {
    const enquiry = this.findOwnEnquiry(id)
    this.assertIfMatch(enquiry, etag)

    mutator(enquiry)
    enquiry.version += 1
    enquiry.updated_at = new Date().toISOString()
    this.persist()

    return { data: { ...enquiry }, etag: this.etagFor(enquiry) }
  }

  async updateEnquiry(id: number, patch: Partial<Enquiry>, etag: string): Promise<WithEtag<Enquiry>> {
    this.requireRole(['admin', 'counsellor'])
    const before = { ...this.findOwnEnquiry(id) }

    const result = this.mutate(id, etag, (e) => Object.assign(e, patch))

    this.appendAudit(result.data, 'enquiry.updated', before, patch)
    return result
  }

  async assignEnquiry(id: number, userId: number | null, etag: string): Promise<WithEtag<Enquiry>> {
    const actor = this.requireRole(['admin', 'counsellor'])
    const before = this.findOwnEnquiry(id).assigned_to

    const result = this.mutate(id, etag, (e) => {
      e.assigned_to = userId
    })

    this.appendAudit(result.data, 'enquiry.assigned', { assigned_to: before }, { assigned_to: userId }, actor.id)
    return result
  }

  async transitionEnquiry(id: number, status: string, etag: string, note?: string): Promise<WithEtag<Enquiry>> {
    const actor = this.requireRole(['admin', 'counsellor'])
    const enquiry = this.findOwnEnquiry(id)
    const to = status as EnquiryStatus

    if (!ALLOWED_TRANSITIONS[enquiry.status].includes(to)) {
      throw new ApiError(`Cannot transition from ${enquiry.status} to ${to}.`, 422)
    }

    const before = enquiry.status

    const result = this.mutate(id, etag, (e) => {
      e.status = to
      if (to === 'in_progress' && !e.responded_at) e.responded_at = new Date().toISOString()
      if (to === 'resolved') e.resolved_at = new Date().toISOString()
    })

    const after: Record<string, unknown> = { status: to }
    if (note) after.note = note

    this.appendAudit(result.data, 'enquiry.status_changed', { status: before }, after, actor.id)
    return result
  }

  async createEnquiry(input: {
    student_name: string
    student_email: string
    subject: string
    description: string
    priority: Priority
  }): Promise<Enquiry> {
    const actor = this.requireRole(['admin', 'counsellor'])

    const id = Math.max(0, ...this.state.enquiries.map((e) => e.id)) + 1
    const now = new Date().toISOString()

    const enquiry: Enquiry = {
      id,
      tenant_id: actor.tenant_id,
      student_name: input.student_name,
      student_email: input.student_email,
      subject: input.subject,
      description: input.description,
      status: 'new',
      priority: input.priority,
      assigned_to: null,
      created_by: actor.id,
      response_due_at: new Date(Date.now() + 4 * 3_600_000).toISOString(),
      resolution_due_at: new Date(Date.now() + 48 * 3_600_000).toISOString(),
      responded_at: null,
      resolved_at: null,
      sla_status: 'on_track',
      version: 1,
      created_at: now,
      updated_at: now,
    }

    this.state.enquiries.push(enquiry)
    this.appendAudit(enquiry, 'enquiry.created', {}, { subject: enquiry.subject }, actor.id)
    this.persist()

    return enquiry
  }

  async listUsers(): Promise<User[]> {
    const user = this.currentUser()
    return DEMO_USERS.filter((u) => u.tenant_id === user.tenant_id)
  }

  async auditTrail(enquiryId: number): Promise<AuditEntry[]> {
    this.findOwnEnquiry(enquiryId)
    return this.state.auditTrail[enquiryId] ?? []
  }

  async verifyAuditChain() {
    this.requireRole(['admin'])
    const user = this.currentUser()
    const checked = Object.values(this.state.auditTrail)
      .flat()
      .filter((e) => e.tenant_id === user.tenant_id).length

    return { valid: true, checked, broken_at: null }
  }

  private appendAudit(
    enquiry: Enquiry,
    action: string,
    before: Record<string, unknown>,
    after: Record<string, unknown>,
    userId: number | null = null,
  ) {
    const entry = auditEntryFor(enquiry, action, { before, after }, userId)
    this.state.auditTrail[enquiry.id] = [...(this.state.auditTrail[enquiry.id] ?? []), entry]
    this.persist()
  }
}

export { DEMO_TENANTS, DEMO_USERS, DEMO_PASSWORD }
