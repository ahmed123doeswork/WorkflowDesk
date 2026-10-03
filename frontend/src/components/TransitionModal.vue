<script setup lang="ts">
import { ref } from 'vue'
import { STATUS_LABELS, type EnquiryStatus } from '../types'
import BaseModal from './BaseModal.vue'

defineProps<{ from: EnquiryStatus; to: EnquiryStatus }>()
const emit = defineEmits<{ close: []; confirm: [note: string] }>()

const note = ref('')
const submitting = ref(false)

function confirm() {
  submitting.value = true
  emit('confirm', note.value.trim())
}
</script>

<template>
  <BaseModal :title="`Move to ${STATUS_LABELS[to]}`" @close="emit('close')">
    <p class="text-sm" style="color: var(--color-ink-muted)">
      This moves the enquiry from <strong style="color: var(--color-ink)">{{ STATUS_LABELS[from] }}</strong> to
      <strong style="color: var(--color-ink)">{{ STATUS_LABELS[to] }}</strong>. The note below is optional and is
      recorded on the audit trail.
    </p>
    <label class="mt-4 block text-xs font-medium" style="color: var(--color-ink)">Transition note (optional)</label>
    <textarea
      v-model="note"
      rows="3"
      class="mt-1 w-full rounded-sm border px-3 py-2 text-sm"
      style="border-color: var(--color-line)"
      placeholder="e.g. Confirmed with the registrar's office."
    />
    <div class="mt-4 flex justify-end gap-2">
      <button
        class="rounded-sm px-3 py-1.5 text-sm font-medium"
        style="color: var(--color-ink-muted)"
        @click="emit('close')"
      >
        Cancel
      </button>
      <button
        class="rounded-sm px-3 py-1.5 text-sm font-medium text-white disabled:opacity-60"
        style="background-color: var(--color-brand-500)"
        :disabled="submitting"
        @click="confirm"
      >
        Confirm
      </button>
    </div>
  </BaseModal>
</template>
