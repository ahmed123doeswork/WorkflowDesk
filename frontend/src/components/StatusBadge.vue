<script setup lang="ts">
import { computed } from 'vue'
import { STATUS_LABELS, type EnquiryStatus } from '../types'

const props = defineProps<{ status: EnquiryStatus }>()

const palette: Record<EnquiryStatus, { fg: string; bg: string }> = {
  new: { fg: 'var(--color-status-new)', bg: 'var(--color-status-new-bg)' },
  in_progress: { fg: 'var(--color-status-progress)', bg: 'var(--color-status-progress-bg)' },
  waiting: { fg: 'var(--color-status-waiting)', bg: 'var(--color-status-waiting-bg)' },
  resolved: { fg: 'var(--color-status-resolved)', bg: 'var(--color-status-resolved-bg)' },
  closed: { fg: 'var(--color-status-closed)', bg: 'var(--color-status-closed-bg)' },
}

const style = computed(() => ({
  color: palette[props.status].fg,
  backgroundColor: palette[props.status].bg,
}))
</script>

<template>
  <span class="inline-flex items-center gap-1.5 rounded px-2 py-0.5 text-xs font-medium" :style="style">
    <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: palette[status].fg }" />
    {{ STATUS_LABELS[status] }}
  </span>
</template>
