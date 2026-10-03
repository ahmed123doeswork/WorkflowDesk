<script setup lang="ts">
import type { Enquiry } from '../types'
import BaseModal from './BaseModal.vue'

const props = defineProps<{
  current: Enquiry
  mine: Partial<Enquiry>
}>()

const emit = defineEmits<{ reload: []; overwrite: [] }>()

const fieldLabels: Partial<Record<keyof Enquiry, string>> = {
  subject: 'Subject',
  description: 'Description',
  priority: 'Priority',
  status: 'Status',
  assigned_to: 'Assigned to',
}

const changedFields = (Object.keys(props.mine) as (keyof Enquiry)[]).filter((key) => key in fieldLabels)
</script>

<template>
  <BaseModal title="Someone else updated this enquiry" @close="emit('reload')">
    <p class="text-sm" style="color: var(--color-ink-muted)">
      Another user saved a change while you were editing. Review both versions before choosing what happens to your
      edit.
    </p>

    <div class="mt-4 space-y-3">
      <div v-for="field in changedFields" :key="field" class="rounded-sm border p-3" style="border-color: var(--color-line)">
        <p class="text-xs font-semibold" style="color: var(--color-ink)">{{ fieldLabels[field] }}</p>
        <div class="mt-2 grid grid-cols-2 gap-3 text-sm">
          <div>
            <p class="text-xs" style="color: var(--color-ink-muted)">Their change</p>
            <p style="color: var(--color-ink)">{{ String((current as any)[field] ?? '—') }}</p>
          </div>
          <div>
            <p class="text-xs" style="color: var(--color-ink-muted)">Your change</p>
            <p style="color: var(--color-ink)">{{ String((mine as any)[field] ?? '—') }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-5 flex justify-end gap-2">
      <button
        class="rounded-sm px-3 py-1.5 text-sm font-medium"
        style="color: var(--color-ink-muted)"
        @click="emit('reload')"
      >
        Discard my changes and reload
      </button>
      <button
        class="rounded-sm px-3 py-1.5 text-sm font-medium text-white"
        style="background-color: var(--color-brand-500)"
        @click="emit('overwrite')"
      >
        Overwrite with my changes
      </button>
    </div>
  </BaseModal>
</template>
