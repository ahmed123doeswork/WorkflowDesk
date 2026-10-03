<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiMode, ApiError } from '../api'
import { DEMO_PASSWORD, DEMO_USERS } from '../api/mockData'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref(apiMode.value === 'browser' ? DEMO_PASSWORD : '')
const submitting = ref(false)
const error = ref<string | null>(null)

async function submit() {
  submitting.value = true
  error.value = null
  try {
    await auth.login(email.value, password.value)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/enquiries'
    router.push(redirect)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Could not sign in.'
  } finally {
    submitting.value = false
  }
}

function useDemoAccount(demoEmail: string) {
  email.value = demoEmail
  password.value = DEMO_PASSWORD
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4" style="background-color: var(--color-surface)">
    <div class="w-full max-w-sm">
      <div class="mb-8 text-center">
        <p class="text-lg font-semibold" style="color: var(--color-ink)">WorkflowDesk</p>
        <p class="mt-1 text-sm" style="color: var(--color-ink-muted)">Enquiry triage for admissions teams</p>
      </div>

      <form class="rounded-sm border bg-white p-6" style="border-color: var(--color-line); background-color: var(--color-surface-raised)" @submit.prevent="submit">
        <label class="text-xs font-medium" style="color: var(--color-ink)">Email</label>
        <input v-model="email" type="email" required autofocus class="mt-1 w-full rounded-sm border px-3 py-2 text-sm" style="border-color: var(--color-line)" />

        <label class="mt-3 block text-xs font-medium" style="color: var(--color-ink)">Password</label>
        <input v-model="password" type="password" required class="mt-1 w-full rounded-sm border px-3 py-2 text-sm" style="border-color: var(--color-line)" />

        <p v-if="error" class="mt-3 text-xs" style="color: var(--color-sla-breached)">{{ error }}</p>

        <button type="submit" :disabled="submitting" class="mt-4 w-full rounded-sm px-3 py-2 text-sm font-medium text-white disabled:opacity-60" style="background-color: var(--color-brand-500)">
          Sign in
        </button>
      </form>

      <div v-if="apiMode === 'browser'" class="mt-5 rounded-sm border p-4 text-xs" style="border-color: var(--color-line); color: var(--color-ink-muted)">
        <p class="font-medium" style="color: var(--color-ink)">Demo accounts (any tenant, any role)</p>
        <ul class="mt-2 space-y-1">
          <li v-for="user in DEMO_USERS" :key="user.id">
            <button class="underline" style="color: var(--color-brand-500)" @click="useDemoAccount(user.email)">
              {{ user.name }}
            </button>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
