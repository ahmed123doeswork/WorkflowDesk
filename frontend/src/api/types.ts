import type { AuditEntry, Enquiry, Paginated, Priority, User } from '../types'

export class ApiError extends Error {
  status: number

  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

/** Thrown on a 412: the caller's If-Match no longer matches the current ETag. */
export class ConflictError extends ApiError {
  current: Enquiry

  constructor(current: Enquiry) {
    super('This enquiry changed since you loaded it.', 412)
    this.current = current
  }
}

export interface EnquiryFilters {
  status?: string
  priority?: string
  assigned_to?: number
  search?: string
  page?: number
  per_page?: number
}

export interface LoginResult {
  token: string
  user: User
}

export interface WithEtag<T> {
  data: T
  etag: string
}

/**
 * Everything a view needs from the backend, in one typed surface. Swapping
 * the implementation (live HTTP, static in-browser demo, or a stub that
 * reports the backend is unreachable) never touches a component.
 */
export interface WorkflowDeskApi {
  readonly mode: 'live' | 'browser' | 'unavailable'

  login(email: string, password: string): Promise<LoginResult>
  logout(): Promise<void>
  me(): Promise<User>

  listEnquiries(filters: EnquiryFilters): Promise<Paginated<Enquiry>>
  getEnquiry(id: number): Promise<WithEtag<Enquiry>>
  updateEnquiry(id: number, patch: Partial<Enquiry>, etag: string): Promise<WithEtag<Enquiry>>
  assignEnquiry(id: number, userId: number | null, etag: string): Promise<WithEtag<Enquiry>>
  transitionEnquiry(id: number, status: string, etag: string, note?: string): Promise<WithEtag<Enquiry>>
  createEnquiry(input: {
    student_name: string
    student_email: string
    subject: string
    description: string
    priority: Priority
  }): Promise<Enquiry>

  listUsers(): Promise<User[]>
  auditTrail(enquiryId: number): Promise<AuditEntry[]>
  verifyAuditChain(): Promise<{ valid: boolean; checked: number; broken_at: number | null }>
}
