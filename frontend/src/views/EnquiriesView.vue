<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, getApi } from '../api'
import CreateEnquiryModal from '../components/CreateEnquiryModal.vue'
import EnquiryDrawer from '../components/EnquiryDrawer.vue'
import PriorityBadge from '../components/PriorityBadge.vue'
import SlaBadge from '../components/SlaBadge.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { useAuthStore } from '../stores/auth'
import type { Enquiry, Paginated, User } from '../types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const result = ref<Paginated<Enquiry> | null>(null)
const users = ref<User[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const showCreate = ref(false)

const status = computed(() => (route.query.status as string) ?? '')
const priority = computed(() => (route.query.priority as string) ?? '')
const search = computed(() => (route.query.search as string) ?? '')
const page = computed(() => Number(route.query.page ?? 1))
const openEnquiryId = computed(() => (route.query.enquiry ? Number(route.query.enquiry) : null))

function updateQuery(patch: Record<string, string | number | undefined>) {
  const query = { ...route.query, ...patch }
  for (const key of Object.keys(query)) {
    if (query[key] === undefined || query[key] === '') delete query[key]
  }
  router.replace({ query })
}

async function load() {
  loading.value = true
  error.value = null
  try {
    result.value = await getApi().listEnquiries({
      status: status.value || undefined,
      priority: priority.value || undefined,
      search: search.value || undefined,
      page: page.value,
      per_page: 15,
    })
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Could not load enquiries.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  users.value = await getApi().listUsers()
  await load()
})

watch([status, priority, search, page], load)

function assigneeName(id: number | null): string {
  if (!id) return '—'
  return users.value.find((u) => u.id === id)?.name ?? `#${id}`
}

function openDrawer(id: number) {
  updateQuery({ enquiry: id })
}

function closeDrawer() {
  updateQuery({ enquiry: undefined })
}
</script>

<template>
  <div class="mx-auto max-w-6xl px-6 py-6">
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-semibold" style="color: var(--color-ink)">Enquiries</h1>
      <button
        v-if="auth.canManage"
        class="rounded-sm px-3 py-1.5 text-sm font-medium text-white"
        style="background-color: var(--color-brand-500)"
        @click="showCreate = true"
      >
        New enquiry
      </button>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
      <input
        :value="search"
        placeholder="Search subject or student…"
        class="w-56 rounded-sm border px-3 py-1.5 text-sm"
        style="border-color: var(--color-line)"
        @input="updateQuery({ search: ($event.target as HTMLInputElement).value, page: 1 })"
      />
      <select
        :value="status"
        class="rounded-sm border px-2 py-1.5 text-sm"
        style="border-color: var(--color-line)"
        @change="updateQuery({ status: ($event.target as HTMLSelectElement).value, page: 1 })"
      >
        <option value="">All statuses</option>
        <option value="new">New</option>
        <option value="in_progress">In progress</option>
        <option value="waiting">Waiting</option>
        <option value="resolved">Resolved</option>
        <option value="closed">Closed</option>
      </select>
      <select
        :value="priority"
        class="rounded-sm border px-2 py-1.5 text-sm"
        style="border-color: var(--color-line)"
        @change="updateQuery({ priority: ($event.target as HTMLSelectElement).value, page: 1 })"
      >
        <option value="">All priorities</option>
        <option value="urgent">Urgent</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
      </select>
    </div>

    <p v-if="error" class="mt-4 rounded-sm px-3 py-2 text-sm" style="background-color: var(--color-sla-breached-bg); color: var(--color-sla-breached)">
      {{ error }}
    </p>

    <div v-else class="mt-4 overflow-auto rounded-sm border" style="border-color: var(--color-line); max-height: 70vh">
      <table class="w-full border-collapse text-sm">
        <thead class="sticky top-0 z-10" style="background-color: var(--color-surface)">
          <tr class="text-left text-xs" style="color: var(--color-ink-muted)">
            <th class="sticky left-0 z-20 border-b px-3 py-2" style="border-color: var(--color-line); background-color: var(--color-surface)">ID</th>
            <th class="border-b px-3 py-2" style="border-color: var(--color-line)">Enquiry</th>
            <th class="border-b px-3 py-2" style="border-color: var(--color-line)">Status</th>
            <th class="border-b px-3 py-2" style="border-color: var(--color-line)">Priority</th>
            <th class="border-b px-3 py-2" style="border-color: var(--color-line)">SLA</th>
            <th class="border-b px-3 py-2" style="border-color: var(--color-line)">Assigned to</th>
            <th class="border-b px-3 py-2" style="border-color: var(--color-line)">Updated</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="px-3 py-6 text-center text-sm" style="color: var(--color-ink-muted)">Loading…</td>
          </tr>
          <tr v-else-if="!result?.data.length">
            <td colspan="7" class="px-3 py-6 text-center text-sm" style="color: var(--color-ink-muted)">No enquiries match these filters.</td>
          </tr>
          <tr
            v-for="enquiry in result?.data"
            :key="enquiry.id"
            class="cursor-pointer hover:bg-black/[0.02]"
            @click="openDrawer(enquiry.id)"
          >
            <td class="tabular sticky left-0 border-b px-3 py-2" style="border-color: var(--color-line); background-color: var(--color-surface-raised); color: var(--color-ink-muted)">
              #{{ enquiry.id }}
            </td>
            <td class="border-b px-3 py-2" style="border-color: var(--color-line)">
              <p style="color: var(--color-ink)">{{ enquiry.subject }}</p>
              <p class="text-xs" style="color: var(--color-ink-muted)">{{ enquiry.student_name }}</p>
            </td>
            <td class="border-b px-3 py-2" style="border-color: var(--color-line)"><StatusBadge :status="enquiry.status" /></td>
            <td class="border-b px-3 py-2" style="border-color: var(--color-line)"><PriorityBadge :priority="enquiry.priority" /></td>
            <td class="border-b px-3 py-2" style="border-color: var(--color-line)"><SlaBadge :status="enquiry.sla_status" /></td>
            <td class="border-b px-3 py-2" style="border-color: var(--color-line); color: var(--color-ink)">{{ assigneeName(enquiry.assigned_to) }}</td>
            <td class="tabular border-b px-3 py-2 text-xs" style="border-color: var(--color-line); color: var(--color-ink-muted)">
              {{ new Date(enquiry.updated_at).toLocaleDateString() }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="result && result.last_page > 1" class="mt-3 flex items-center justify-between text-xs" style="color: var(--color-ink-muted)">
      <span>{{ result.total }} enquiries · page {{ result.current_page }} of {{ result.last_page }}</span>
      <div class="flex gap-2">
        <button :disabled="page <= 1" class="rounded-sm border px-2 py-1 disabled:opacity-40" style="border-color: var(--color-line)" @click="updateQuery({ page: page - 1 })">
          Previous
        </button>
        <button :disabled="page >= result.last_page" class="rounded-sm border px-2 py-1 disabled:opacity-40" style="border-color: var(--color-line)" @click="updateQuery({ page: page + 1 })">
          Next
        </button>
      </div>
    </div>
  </div>

  <EnquiryDrawer v-if="openEnquiryId" :enquiry-id="openEnquiryId" :users="users" @close="closeDrawer" @changed="load" />

  <CreateEnquiryModal v-if="showCreate" @close="showCreate = false" @created="showCreate = false; load()" />
</template>
