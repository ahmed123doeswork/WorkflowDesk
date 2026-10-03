import type { AuditEntry, Enquiry, Paginated, Priority, User } from '../types'
import { ApiError, ConflictError, type EnquiryFilters, type LoginResult, type WithEtag, type WorkflowDeskApi } from './types'

export class LiveAdapter implements WorkflowDeskApi {
  readonly mode = 'live' as const

  private baseUrl: string
  private getToken: () => string | null

  constructor(baseUrl: string, getToken: () => string | null) {
    this.baseUrl = baseUrl
    this.getToken = getToken
  }

  private async request<T>(
    path: string,
    init: RequestInit = {},
  ): Promise<{ body: T; etag: string | null }> {
    const token = this.getToken()

    const response = await fetch(`${this.baseUrl}${path}`, {
      ...init,
      headers: {
        Accept: 'application/json',
        ...(init.body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...init.headers,
      },
    })

    if (response.status === 412) {
      const current = (await response.json()) as Enquiry
      throw new ConflictError(current)
    }

    if (!response.ok) {
      const message = await response.text()
      throw new ApiError(message || response.statusText, response.status)
    }

    const etag = response.headers.get('ETag')

    if (response.status === 204) {
      return { body: undefined as T, etag }
    }

    return { body: (await response.json()) as T, etag }
  }

  async login(email: string, password: string): Promise<LoginResult> {
    const { body } = await this.request<LoginResult>('/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })
    return body
  }

  async logout(): Promise<void> {
    await this.request('/logout', { method: 'POST' })
  }

  async me(): Promise<User> {
    const { body } = await this.request<User>('/me')
    return body
  }

  async listEnquiries(filters: EnquiryFilters): Promise<Paginated<Enquiry>> {
    const query = new URLSearchParams()
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== '') query.set(key, String(value))
    }
    const { body } = await this.request<Paginated<Enquiry>>(`/enquiries?${query.toString()}`)
    return body
  }

  async getEnquiry(id: number): Promise<WithEtag<Enquiry>> {
    const { body, etag } = await this.request<Enquiry>(`/enquiries/${id}`)
    return { data: body, etag: etag! }
  }

  async updateEnquiry(id: number, patch: Partial<Enquiry>, etag: string): Promise<WithEtag<Enquiry>> {
    const { body, etag: newEtag } = await this.request<Enquiry>(`/enquiries/${id}`, {
      method: 'PATCH',
      headers: { 'If-Match': etag },
      body: JSON.stringify(patch),
    })
    return { data: body, etag: newEtag! }
  }

  async assignEnquiry(id: number, userId: number | null, etag: string): Promise<WithEtag<Enquiry>> {
    const { body, etag: newEtag } = await this.request<Enquiry>(`/enquiries/${id}/assign`, {
      method: 'PATCH',
      headers: { 'If-Match': etag },
      body: JSON.stringify({ assigned_to: userId }),
    })
    return { data: body, etag: newEtag! }
  }

  async transitionEnquiry(id: number, status: string, etag: string, note?: string): Promise<WithEtag<Enquiry>> {
    const { body, etag: newEtag } = await this.request<Enquiry>(`/enquiries/${id}/transition`, {
      method: 'PATCH',
      headers: { 'If-Match': etag },
      body: JSON.stringify({ status, note }),
    })
    return { data: body, etag: newEtag! }
  }

  async createEnquiry(input: {
    student_name: string
    student_email: string
    subject: string
    description: string
    priority: Priority
  }): Promise<Enquiry> {
    const { body } = await this.request<Enquiry>('/enquiries', {
      method: 'POST',
      body: JSON.stringify(input),
    })
    return body
  }

  async listUsers(): Promise<User[]> {
    const { body } = await this.request<Paginated<User>>('/users?per_page=100')
    return body.data
  }

  async auditTrail(enquiryId: number): Promise<AuditEntry[]> {
    const { body } = await this.request<AuditEntry[]>(`/enquiries/${enquiryId}/audit`)
    return body
  }

  async verifyAuditChain() {
    const { body } = await this.request<{ valid: boolean; checked: number; broken_at: number | null }>(
      '/audit/verify',
    )
    return body
  }
}
