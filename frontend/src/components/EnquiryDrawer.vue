<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ApiError, ConflictError, getApi } from '../api'
import { useAuthStore } from '../stores/auth'
import { ALLOWED_TRANSITIONS, STATUS_LABELS, type AuditEntry, type Enquiry, type EnquiryStatus, type User } from '../types'
import AuditTimeline from './AuditTimeline.vue'
import ConflictModal from './ConflictModal.vue'
import PriorityBadge from './PriorityBadge.vue'
import SlaBadge from './SlaBadge.vue'
import StateStepper from './StateStepper.vue'
import StatusBadge from './StatusBadge.vue'
import TransitionModal from './TransitionModal.vue'

const props = defineProps<{ enquiryId: number; users: User[] }>()
const emit = defineEmits<{ close: []; changed: [] }>()

const auth = useAuthStore()

const enquiry = ref<Enquiry | null>(null)
const etag = ref<string>('')
const audit = ref<AuditEntry[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)

const conflict = ref<{ current: Enquiry; mine: Partial<Enquiry> } | null>(null)
const pendingTransition = ref<EnquiryStatus | null>(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const [e, trail] = await Promise.all([getApi().getEnquiry(props.enquiryId), getApi().auditTrail(props.enquiryId)])
    enquiry.value = e.data
    etag.value = e.etag
    audit.value = trail
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Could not load this enquiry.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => props.enquiryId, load)

const assignee = computed(() => props.users.find((u) => u.id === enquiry.value?.assigned_to) ?? null)
const nextStatuses = computed<EnquiryStatus[]>(() => (enquiry.value ? ALLOWED_TRANSITIONS[enquiry.value.status] : []))
const criticalStatuses: EnquiryStatus[] = ['resolved', 'closed']

async function runMutation(mine: Partial<Enquiry>, apply: () => Promise<{ data: Enquiry; etag: string }>) {
  busy.value = true
  error.value = null
  try {
    const result = await apply()
    enquiry.value = result.data
    etag.value = result.etag
    audit.value = await getApi().auditTrail(props.enquiryId)
    emit('changed')
  } catch (e) {
    if (e instanceof ConflictError) {
      conflict.value = { current: e.current, mine }
    } else {
      error.value = e instanceof ApiError ? e.message : 'That change could not be saved.'
    }
  } finally {
    busy.value = false
  }
}

function requestTransition(status: EnquiryStatus) {
  if (criticalStatuses.includes(status)) {
    pendingTransition.value = status
  } else {
    void runMutation({ status }, () => getApi().transitionEnquiry(props.enquiryId, status, etag.value))
  }
}

async function confirmTransition(note: string) {
  const status = pendingTransition.value!
  pendingTransition.value = null
  await runMutation({ status }, () =>
    getApi().transitionEnquiry(props.enquiryId, status, etag.value, note || undefined),
  )
}

function assignTo(rawValue: string) {
  const userId = rawValue === '' ? null : Number(rawValue)
  void runMutation({ assigned_to: userId }, () => getApi().assignEnquiry(props.enquiryId, userId, etag.value))
}

async function resolveConflict(action: 'reload' | 'overwrite') {
  const c = conflict.value!
  conflict.value = null

  if (action === 'reload') {
    await load()
    return
  }

  // Overwrite: retry the same intent against the now-current ETag.
  etag.value = `"${c.current.version}"`
  if ('status' in c.mine) {
    await runMutation(c.mine, () => getApi().transitionEnquiry(props.enquiryId, c.mine.status as EnquiryStatus, etag.value))
  } else if ('assigned_to' in c.mine) {
    await runMutation(c.mine, () => getApi().assignEnquiry(props.enquiryId, c.mine.assigned_to ?? null, etag.value))
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-40 flex justify-end bg-black/20" @click.self="emit('close')">
      <aside class="flex h-full w-full max-w-lg flex-col bg-white shadow-2xl" style="background-color: var(--color-surface-raised)">
        <div v-if="loading" class="flex flex-1 items-center justify-center text-sm" style="color: var(--color-ink-muted)">
          Loading…
        </div>

        <div v-else-if="error && !enquiry" class="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center">
          <p class="text-sm" style="color: var(--color-ink)">{{ error }}</p>
          <button class="text-sm underline" style="color: var(--color-brand-500)" @click="load">Try again</button>
        </div>

        <template v-else-if="enquiry">
          <header class="shrink-0 border-b px-6 py-4" style="border-color: var(--color-line)">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="tabular text-xs" style="color: var(--color-ink-muted)">Enquiry #{{ enquiry.id }}</p>
                <h2 class="mt-0.5 text-base font-semibold" style="color: var(--color-ink)">{{ enquiry.subject }}</h2>
              </div>
              <button class="shrink-0 text-sm" style="color: var(--color-ink-muted)" @click="emit('close')">Close</button>
            </div>
            <div class="mt-3 flex items-center gap-2">
              <StatusBadge :status="enquiry.status" />
              <PriorityBadge :priority="enquiry.priority" />
              <SlaBadge :status="enquiry.sla_status" />
            </div>
          </header>

          <div class="flex-1 overflow-y-auto px-6 py-5">
            <StateStepper :status="enquiry.status" />

            <p v-if="error" class="mt-4 rounded-sm px-3 py-2 text-xs" style="background-color: var(--color-sla-breached-bg); color: var(--color-sla-breached)">
              {{ error }}
            </p>

            <section class="mt-6">
              <h3 class="text-xs font-semibold uppercase" style="color: var(--color-ink-muted); letter-spacing: 0.04em">Student</h3>
              <p class="mt-1 text-sm" style="color: var(--color-ink)">{{ enquiry.student_name }}</p>
              <p class="text-sm" style="color: var(--color-ink-muted)">{{ enquiry.student_email }}</p>
            </section>

            <section class="mt-5">
              <h3 class="text-xs font-semibold uppercase" style="color: var(--color-ink-muted); letter-spacing: 0.04em">Description</h3>
              <p class="mt-1 text-sm leading-relaxed" style="color: var(--color-ink)">{{ enquiry.description }}</p>
            </section>

            <section class="mt-5">
              <h3 class="text-xs font-semibold uppercase" style="color: var(--color-ink-muted); letter-spacing: 0.04em">Assigned to</h3>
              <select
                v-if="auth.canManage"
                class="mt-1.5 w-full rounded-sm border px-2 py-1.5 text-sm"
                style="border-color: var(--color-line)"
                :value="enquiry.assigned_to ?? ''"
                :disabled="busy"
                @change="assignTo(($event.target as HTMLSelectElement).value)"
              >
                <option value="">Unassigned</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-else class="mt-1 text-sm" style="color: var(--color-ink)">{{ assignee?.name ?? 'Unassigned' }}</p>
            </section>

            <section v-if="auth.canManage && nextStatuses.length" class="mt-6">
              <h3 class="text-xs font-semibold uppercase" style="color: var(--color-ink-muted); letter-spacing: 0.04em">
                Move to
              </h3>
              <div class="mt-2 flex flex-wrap gap-2">
                <button
                  v-for="status in nextStatuses"
                  :key="status"
                  :disabled="busy"
                  class="rounded-sm border px-3 py-1.5 text-sm font-medium disabled:opacity-60"
                  style="border-color: var(--color-line); color: var(--color-ink)"
                  @click="requestTransition(status)"
                >
                  {{ STATUS_LABELS[status] }}
                </button>
              </div>
            </section>

            <section class="mt-8">
              <h3 class="text-xs font-semibold uppercase" style="color: var(--color-ink-muted); letter-spacing: 0.04em">
                Activity
              </h3>
              <div class="mt-3">
                <AuditTimeline :entries="audit" :users="users" />
              </div>
            </section>
          </div>
        </template>
      </aside>
    </div>
  </Teleport>

  <TransitionModal
    v-if="pendingTransition && enquiry"
    :from="enquiry.status"
    :to="pendingTransition"
    @close="pendingTransition = null"
    @confirm="confirmTransition"
  />

  <ConflictModal
    v-if="conflict"
    :current="conflict.current"
    :mine="conflict.mine"
    :users="users"
    @reload="resolveConflict('reload')"
    @overwrite="resolveConflict('overwrite')"
  />
</template>
