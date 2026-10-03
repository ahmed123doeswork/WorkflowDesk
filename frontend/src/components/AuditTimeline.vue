<script setup lang="ts">
import { computed } from 'vue'
import type { AuditEntry, User } from '../types'

const props = defineProps<{ entries: AuditEntry[]; users: User[] }>()

const actionLabels: Record<string, string> = {
  'enquiry.created': 'Enquiry created',
  'enquiry.updated': 'Details updated',
  'enquiry.assigned': 'Reassigned',
  'enquiry.status_changed': 'Status changed',
  'enquiry.sla_status_changed': 'SLA status recalculated',
}

const fieldLabels: Record<string, string> = {
  status: 'Status',
  assigned_to: 'Assigned to',
  subject: 'Subject',
  description: 'Description',
  priority: 'Priority',
  sla_status: 'SLA status',
}

function actorName(entry: AuditEntry): string | null {
  if (!entry.user_id) return null
  return props.users.find((u) => u.id === entry.user_id)?.name ?? `User #${entry.user_id}`
}

function displayValue(field: string, value: unknown): unknown {
  if (field === 'assigned_to' || field === 'created_by') {
    if (value === null || value === undefined) return null
    return props.users.find((u) => u.id === value)?.name ?? `User #${value}`
  }
  return value
}

function fieldDiffs(entry: AuditEntry): { field: string; before: unknown; after: unknown }[] {
  const before = entry.changes?.before ?? {}
  const after = entry.changes?.after ?? {}
  const fields = new Set([...Object.keys(before), ...Object.keys(after)])
  fields.delete('note')
  return [...fields].map((field) => ({
    field,
    before: displayValue(field, (before as any)[field]),
    after: displayValue(field, (after as any)[field]),
  }))
}

function note(entry: AuditEntry): string | undefined {
  return (entry.changes?.after as any)?.note
}

const rows = computed(() =>
  [...props.entries].sort((a, b) => b.id - a.id).map((entry) => ({
    entry,
    actor: actorName(entry),
    diffs: fieldDiffs(entry),
    note: note(entry),
  })),
)
</script>

<template>
  <ol class="space-y-0">
    <li v-for="(row, index) in rows" :key="row.entry.id" class="relative flex gap-3 pb-5 pl-1">
      <div class="flex flex-col items-center">
        <span
          class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold"
          :style="
            row.actor
              ? { backgroundColor: 'var(--color-brand-100)', color: 'var(--color-brand-600)' }
              : { backgroundColor: 'var(--color-line)', color: 'var(--color-ink-muted)' }
          "
          :title="row.actor ? 'User action' : 'System action'"
        >
          {{ row.actor ? row.actor.charAt(0).toUpperCase() : '⚙' }}
        </span>
        <span v-if="index < rows.length - 1" class="mt-1 w-px flex-1" style="background-color: var(--color-line)" />
      </div>

      <div class="min-w-0 flex-1">
        <p class="text-sm" style="color: var(--color-ink)">
          <span class="font-medium">{{ actionLabels[row.entry.action] ?? row.entry.action }}</span>
          <span style="color: var(--color-ink-muted)"> — {{ row.actor ?? 'System' }}</span>
        </p>
        <p class="tabular text-xs" style="color: var(--color-ink-muted)">
          {{ new Date(row.entry.created_at).toLocaleString() }}
        </p>

        <ul v-if="row.diffs.length" class="mt-1.5 space-y-0.5">
          <li v-for="diff in row.diffs" :key="diff.field" class="text-xs" style="color: var(--color-ink-muted)">
            <span class="font-medium" style="color: var(--color-ink)">{{ fieldLabels[diff.field] ?? diff.field }}</span>:
            <span>{{ diff.before ?? '—' }}</span>
            <span aria-hidden="true"> → </span>
            <span style="color: var(--color-ink)">{{ diff.after ?? '—' }}</span>
          </li>
        </ul>

        <p v-if="row.note" class="mt-1.5 rounded-sm px-2 py-1 text-xs" style="background-color: var(--color-surface); color: var(--color-ink)">
          “{{ row.note }}”
        </p>
      </div>
    </li>
  </ol>
</template>
