<script setup lang="ts">
import { computed } from 'vue'
import { SLA_LABELS, type SlaStatus } from '../types'

const props = defineProps<{ status: SlaStatus }>()

const style = computed(() => {
  switch (props.status) {
    case 'breached':
      return { backgroundColor: 'var(--color-sla-breached-bg)', color: 'var(--color-sla-breached)' }
    case 'at_risk':
      return { backgroundColor: 'transparent', color: 'var(--color-sla-at-risk)' }
    case 'on_track':
    default:
      return { backgroundColor: 'transparent', color: 'var(--color-ink-muted)' }
  }
})
</script>

<template>
  <span class="inline-flex items-center gap-1.5 rounded px-2 py-0.5 text-xs font-medium" :style="style">
    <span
      class="h-1.5 w-1.5 rounded-full"
      :style="{
        backgroundColor:
          status === 'breached'
            ? 'var(--color-sla-breached)'
            : status === 'at_risk'
              ? 'var(--color-sla-at-risk)'
              : 'var(--color-sla-on-track)',
      }"
    />
    {{ SLA_LABELS[status] }}
  </span>
</template>
