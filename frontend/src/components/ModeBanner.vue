<script setup lang="ts">
import { apiMode, retryConnection } from '../api'

async function retry() {
  await retryConnection()
}
</script>

<template>
  <div
    v-if="apiMode === 'browser'"
    class="flex items-center justify-center gap-2 px-4 py-1.5 text-xs font-medium"
    style="background-color: var(--color-tenant-100); color: var(--color-tenant)"
  >
    Demo mode — this workspace runs entirely in your browser; nothing you do here reaches a server.
  </div>
  <div
    v-else-if="apiMode === 'unavailable'"
    class="flex items-center justify-center gap-3 px-4 py-1.5 text-xs font-medium"
    style="background-color: var(--color-sla-breached-bg); color: var(--color-sla-breached)"
  >
    <span>Can't reach the WorkflowDesk API right now.</span>
    <button class="underline underline-offset-2" @click="retry">Retry</button>
  </div>
</template>
