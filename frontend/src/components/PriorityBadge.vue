<script setup lang="ts">
import { computed } from 'vue'
import { PRIORITY_LABELS, type Priority } from '../types'

const props = defineProps<{ priority: Priority }>()

// Priority is encoded by weight/contrast, not color - status already owns
// the color channel, and doubling up on it (e.g. "urgent" in rose, which
// also means "breached") would make the two signals hard to tell apart.
const style = computed(() => {
  switch (props.priority) {
    case 'urgent':
      return { backgroundColor: 'var(--color-ink)', color: 'white' }
    case 'high':
      return { backgroundColor: 'var(--color-ink-muted)', color: 'white' }
    case 'medium':
      return { backgroundColor: 'var(--color-line)', color: 'var(--color-ink)' }
    case 'low':
    default:
      return { backgroundColor: 'transparent', color: 'var(--color-ink-muted)' }
  }
})
</script>

<template>
  <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium" :style="style">
    {{ PRIORITY_LABELS[priority] }}
  </span>
</template>
