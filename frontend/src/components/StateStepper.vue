<script setup lang="ts">
import { computed } from 'vue'
import { STATUS_LABELS, type EnquiryStatus } from '../types'

const props = defineProps<{ status: EnquiryStatus }>()

// The happy path is linear; "waiting" is a detour off in_progress, so it
// only appears in the pipeline while the enquiry is actually there.
const sequence = computed<EnquiryStatus[]>(() =>
  props.status === 'waiting'
    ? ['new', 'in_progress', 'waiting', 'resolved', 'closed']
    : ['new', 'in_progress', 'resolved', 'closed'],
)

const currentIndex = computed(() => sequence.value.indexOf(props.status))
</script>

<template>
  <ol class="flex items-center">
    <li v-for="(step, index) in sequence" :key="step" class="flex items-center">
      <div class="flex items-center gap-2">
        <span
          class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
          :style="
            index < currentIndex
              ? { backgroundColor: 'var(--color-ink)', color: 'white' }
              : index === currentIndex
                ? { backgroundColor: 'var(--color-brand-500)', color: 'white' }
                : { backgroundColor: 'var(--color-line)', color: 'var(--color-ink-muted)' }
          "
        >
          {{ index < currentIndex ? '✓' : index + 1 }}
        </span>
        <span
          class="text-sm"
          :style="{
            color: index === currentIndex ? 'var(--color-ink)' : 'var(--color-ink-muted)',
            fontWeight: index === currentIndex ? 600 : 400,
          }"
        >
          {{ STATUS_LABELS[step] }}
        </span>
      </div>
      <span
        v-if="index < sequence.length - 1"
        class="mx-3 h-px w-8 shrink-0"
        :style="{ backgroundColor: index < currentIndex ? 'var(--color-ink)' : 'var(--color-line)' }"
      />
    </li>
  </ol>
</template>
