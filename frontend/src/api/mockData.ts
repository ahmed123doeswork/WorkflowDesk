import type { AuditEntry, Enquiry, Priority, Tenant, User } from '../types'

export const DEMO_TENANTS: Tenant[] = [
  { id: 1, name: 'Acme Education', slug: 'acme-education', timezone: 'America/New_York' },
  { id: 2, name: 'Globex Learning', slug: 'globex-learning', timezone: 'Asia/Karachi' },
]

export const DEMO_USERS: User[] = [
  { id: 1, tenant_id: 1, name: 'Admin (Acme Education)', email: 'admin@acme-education.test', role: 'admin' },
  { id: 2, tenant_id: 1, name: 'Counsellor (Acme Education)', email: 'counsellor@acme-education.test', role: 'counsellor' },
  { id: 3, tenant_id: 1, name: 'Viewer (Acme Education)', email: 'viewer@acme-education.test', role: 'viewer' },
  { id: 4, tenant_id: 2, name: 'Admin (Globex Learning)', email: 'admin@globex-learning.test', role: 'admin' },
  { id: 5, tenant_id: 2, name: 'Counsellor (Globex Learning)', email: 'counsellor@globex-learning.test', role: 'counsellor' },
  { id: 6, tenant_id: 2, name: 'Viewer (Globex Learning)', email: 'viewer@globex-learning.test', role: 'viewer' },
]

const hoursFromNow = (hours: number) => new Date(Date.now() + hours * 3_600_000).toISOString()
const hoursAgo = (hours: number) => new Date(Date.now() - hours * 3_600_000).toISOString()

function makeEnquiry(partial: Partial<Enquiry> & { id: number; tenant_id: number }): Enquiry {
  return {
    student_name: 'Jordan Rivera',
    student_email: 'jordan.rivera@example.com',
    subject: 'Question about enrolment',
    description: 'I need help understanding the next steps for my application.',
    status: 'new',
    priority: 'medium',
    assigned_to: null,
    created_by: null,
    response_due_at: hoursFromNow(4),
    resolution_due_at: hoursFromNow(48),
    responded_at: null,
    resolved_at: null,
    sla_status: 'on_track',
    version: 1,
    created_at: hoursAgo(1),
    updated_at: hoursAgo(1),
    ...partial,
  }
}

export function seedEnquiries(): Enquiry[] {
  return [
    makeEnquiry({
      id: 1,
      tenant_id: 1,
      student_name: 'Priya Natarajan',
      subject: 'Visa document checklist',
      description: 'Could you confirm which documents are required for the student visa interview?',
      status: 'in_progress',
      priority: 'high',
      assigned_to: 2,
      created_by: 2,
      responded_at: hoursAgo(20),
      response_due_at: hoursAgo(22),
      resolution_due_at: hoursFromNow(6),
      sla_status: 'at_risk',
      created_at: hoursAgo(24),
      updated_at: hoursAgo(1),
    }),
    makeEnquiry({
      id: 2,
      tenant_id: 1,
      student_name: 'Marcus Webb',
      subject: 'Scholarship renewal deadline',
      description: "My scholarship renewal form is due soon and I'm not sure which supporting documents to attach.",
      status: 'new',
      priority: 'urgent',
      response_due_at: hoursAgo(1),
      resolution_due_at: hoursFromNow(3),
      sla_status: 'breached',
      created_at: hoursAgo(3),
      updated_at: hoursAgo(3),
    }),
    makeEnquiry({
      id: 3,
      tenant_id: 1,
      student_name: 'Lena Ostrowski',
      subject: 'Transfer credit evaluation',
      description: 'I transferred from another institution and want to know how many credits will carry over.',
      status: 'resolved',
      priority: 'medium',
      assigned_to: 2,
      created_by: 2,
      responded_at: hoursAgo(70),
      resolved_at: hoursAgo(2),
      response_due_at: hoursAgo(68),
      resolution_due_at: hoursAgo(1),
      sla_status: 'on_track',
      created_at: hoursAgo(72),
      updated_at: hoursAgo(2),
    }),
    makeEnquiry({
      id: 4,
      tenant_id: 1,
      student_name: 'Tomás Herrera',
      subject: 'Housing deposit refund',
      description: "I cancelled my dorm reservation and I'm waiting on the deposit refund status.",
      status: 'waiting',
      priority: 'low',
      assigned_to: 2,
      created_by: 2,
      responded_at: hoursAgo(10),
      response_due_at: hoursAgo(10),
      resolution_due_at: hoursFromNow(96),
      sla_status: 'on_track',
      created_at: hoursAgo(12),
      updated_at: hoursAgo(4),
    }),
    makeEnquiry({
      id: 5,
      tenant_id: 2,
      student_name: 'Amara Chukwu',
      subject: 'Timetable clash',
      description: 'Two of my required courses overlap this term. Can I get help adjusting my schedule?',
      status: 'in_progress',
      priority: 'high',
      assigned_to: 5,
      created_by: 5,
      responded_at: hoursAgo(5),
      response_due_at: hoursAgo(5),
      resolution_due_at: hoursFromNow(8),
      sla_status: 'on_track',
      created_at: hoursAgo(6),
      updated_at: hoursAgo(5),
    }),
    makeEnquiry({
      id: 6,
      tenant_id: 2,
      student_name: 'Felix Granger',
      subject: 'Graduation ceremony tickets',
      description: 'How many guest tickets am I allotted for the graduation ceremony?',
      status: 'closed',
      priority: 'low',
      assigned_to: 5,
      created_by: 5,
      responded_at: hoursAgo(100),
      resolved_at: hoursAgo(50),
      response_due_at: hoursAgo(98),
      resolution_due_at: hoursAgo(49),
      sla_status: 'on_track',
      created_at: hoursAgo(101),
      updated_at: hoursAgo(50),
    }),
  ]
}

let hashCounter = 0
const nextHash = () => `demo-hash-${++hashCounter}`

export function auditEntryFor(enquiry: Enquiry, action: string, changes: AuditEntry['changes'], userId: number | null): AuditEntry {
  return {
    id: Date.now() + Math.random(),
    tenant_id: enquiry.tenant_id,
    user_id: userId,
    action,
    auditable_type: 'App\\Models\\Enquiry',
    auditable_id: enquiry.id,
    changes,
    previous_hash: nextHash(),
    hash: nextHash(),
    created_at: new Date().toISOString(),
  }
}

export function seedAuditTrail(enquiries: Enquiry[]): Record<number, AuditEntry[]> {
  const trail: Record<number, AuditEntry[]> = {}

  for (const enquiry of enquiries) {
    trail[enquiry.id] = [
      auditEntryFor(enquiry, 'enquiry.created', { after: { subject: enquiry.subject } }, enquiry.created_by),
    ]

    if (enquiry.assigned_to) {
      trail[enquiry.id].push(
        auditEntryFor(
          enquiry,
          'enquiry.assigned',
          { before: { assigned_to: null }, after: { assigned_to: enquiry.assigned_to } },
          enquiry.created_by,
        ),
      )
    }

    if (enquiry.status !== 'new') {
      trail[enquiry.id].push(
        auditEntryFor(
          enquiry,
          'enquiry.status_changed',
          { before: { status: 'new' }, after: { status: enquiry.status } },
          enquiry.assigned_to,
        ),
      )
    }
  }

  return trail
}

export const DEMO_PASSWORD = 'password'

export function defaultPriority(): Priority {
  return 'medium'
}
