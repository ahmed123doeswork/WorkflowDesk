export type Role = 'admin' | 'counsellor' | 'viewer'

export type EnquiryStatus = 'new' | 'in_progress' | 'waiting' | 'resolved' | 'closed'

export type Priority = 'low' | 'medium' | 'high' | 'urgent'

export type SlaStatus = 'on_track' | 'at_risk' | 'breached'

export interface Tenant {
  id: number
  name: string
  slug: string
  timezone: string
}

export interface User {
  id: number
  tenant_id: number
  name: string
  email: string
  role: Role
  tenant?: Tenant
}

export interface Enquiry {
  id: number
  tenant_id: number
  student_name: string
  student_email: string
  subject: string
  description: string
  status: EnquiryStatus
  priority: Priority
  assigned_to: number | null
  created_by: number | null
  response_due_at: string | null
  resolution_due_at: string | null
  responded_at: string | null
  resolved_at: string | null
  sla_status: SlaStatus
  version: number
  created_at: string
  updated_at: string
}

export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface AuditEntry {
  id: number
  tenant_id: number
  user_id: number | null
  action: string
  auditable_type: string
  auditable_id: number
  changes: { before?: Record<string, unknown>; after?: Record<string, unknown> } | null
  previous_hash: string | null
  hash: string
  created_at: string
}

export const STATUS_LABELS: Record<EnquiryStatus, string> = {
  new: 'New',
  in_progress: 'In progress',
  waiting: 'Waiting',
  resolved: 'Resolved',
  closed: 'Closed',
}

export const STATUS_SEQUENCE: EnquiryStatus[] = ['new', 'in_progress', 'waiting', 'resolved', 'closed']

export const ALLOWED_TRANSITIONS: Record<EnquiryStatus, EnquiryStatus[]> = {
  new: ['in_progress'],
  in_progress: ['waiting', 'resolved'],
  waiting: ['in_progress'],
  resolved: ['closed', 'in_progress'],
  closed: [],
}

export const PRIORITY_LABELS: Record<Priority, string> = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
  urgent: 'Urgent',
}

export const SLA_LABELS: Record<SlaStatus, string> = {
  on_track: 'On track',
  at_risk: 'At risk',
  breached: 'Breached',
}
