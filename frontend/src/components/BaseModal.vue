<script setup lang="ts">
defineProps<{ title: string }>()
const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="emit('close')">
      <div
        class="w-full max-w-md rounded-sm border bg-white shadow-xl"
        style="border-color: var(--color-line)"
        role="dialog"
        aria-modal="true"
      >
        <div class="flex items-center justify-between border-b px-5 py-3" style="border-color: var(--color-line)">
          <h2 class="text-sm font-semibold" style="color: var(--color-ink)">{{ title }}</h2>
          <button class="text-sm" style="color: var(--color-ink-muted)" @click="emit('close')">Close</button>
        </div>
        <div class="px-5 py-4">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>
