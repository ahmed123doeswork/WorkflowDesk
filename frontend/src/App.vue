<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import ModeBanner from './components/ModeBanner.vue'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()

const roleLabel: Record<string, string> = { admin: 'Admin', counsellor: 'Counsellor', viewer: 'Viewer' }

const tenantName = computed(() => auth.user?.tenant?.name ?? '—')

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <ModeBanner />

    <header
      v-if="auth.isAuthenticated"
      class="flex shrink-0 items-center justify-between border-b px-6 py-3"
      style="border-color: var(--color-line); background-color: var(--color-surface-raised)"
    >
      <div class="flex items-center gap-3">
        <span class="text-sm font-semibold" style="color: var(--color-ink)">WorkflowDesk</span>
        <span class="h-4 w-px" style="background-color: var(--color-line)" />
        <span class="flex items-center gap-1.5 text-sm" style="color: var(--color-tenant)">
          <span class="h-1.5 w-1.5 rounded-full" style="background-color: var(--color-tenant)" />
          {{ tenantName }}
        </span>
      </div>

      <div class="flex items-center gap-3">
        <span class="text-sm" style="color: var(--color-ink-muted)">{{ auth.user?.name }}</span>
        <span
          class="rounded-sm px-2 py-0.5 text-xs font-medium"
          style="background-color: var(--color-brand-100); color: var(--color-brand-600)"
        >
          {{ roleLabel[auth.role ?? ''] }}
        </span>
        <button class="text-sm" style="color: var(--color-ink-muted)" @click="logout">Sign out</button>
      </div>
    </header>

    <main class="flex-1" style="background-color: var(--color-surface)">
      <router-view />
    </main>
  </div>
</template>
