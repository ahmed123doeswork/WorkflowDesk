<script setup lang="ts">
import { reactive, ref } from 'vue'
import { ApiError, getApi } from '../api'
import type { Priority } from '../types'
import BaseModal from './BaseModal.vue'

const emit = defineEmits<{ close: []; created: [] }>()

const form = reactive({
  student_name: '',
  student_email: '',
  subject: '',
  description: '',
  priority: 'medium' as Priority,
})

const submitting = ref(false)
const error = ref<string | null>(null)

async function submit() {
  submitting.value = true
  error.value = null
  try {
    await getApi().createEnquiry({ ...form })
    emit('created')
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Could not create the enquiry.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <BaseModal title="New enquiry" @close="emit('close')">
    <form class="space-y-3" @submit.prevent="submit">
      <div>
        <label class="text-xs font-medium" style="color: var(--color-ink)">Student name</label>
        <input v-model="form.student_name" required class="mt-1 w-full rounded-sm border px-2 py-1.5 text-sm" style="border-color: var(--color-line)" />
      </div>
      <div>
        <label class="text-xs font-medium" style="color: var(--color-ink)">Student email</label>
        <input v-model="form.student_email" type="email" required class="mt-1 w-full rounded-sm border px-2 py-1.5 text-sm" style="border-color: var(--color-line)" />
      </div>
      <div>
        <label class="text-xs font-medium" style="color: var(--color-ink)">Subject</label>
        <input v-model="form.subject" required class="mt-1 w-full rounded-sm border px-2 py-1.5 text-sm" style="border-color: var(--color-line)" />
      </div>
      <div>
        <label class="text-xs font-medium" style="color: var(--color-ink)">Description</label>
        <textarea v-model="form.description" rows="3" required class="mt-1 w-full rounded-sm border px-2 py-1.5 text-sm" style="border-color: var(--color-line)" />
      </div>
      <div>
        <label class="text-xs font-medium" style="color: var(--color-ink)">Priority</label>
        <select v-model="form.priority" class="mt-1 w-full rounded-sm border px-2 py-1.5 text-sm" style="border-color: var(--color-line)">
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>

      <p v-if="error" class="text-xs" style="color: var(--color-sla-breached)">{{ error }}</p>

      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="rounded-sm px-3 py-1.5 text-sm font-medium" style="color: var(--color-ink-muted)" @click="emit('close')">
          Cancel
        </button>
        <button type="submit" :disabled="submitting" class="rounded-sm px-3 py-1.5 text-sm font-medium text-white disabled:opacity-60" style="background-color: var(--color-brand-500)">
          Create enquiry
        </button>
      </div>
    </form>
  </BaseModal>
</template>
