import { ref } from 'vue'
import { BrowserAdapter } from './browserAdapter'
import { LiveAdapter } from './liveAdapter'
import type { WorkflowDeskApi } from './types'
import { UnavailableAdapter } from './unavailableAdapter'

const BASE_URL = (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? 'http://localhost:8000/api'
const REQUESTED_MODE = (import.meta.env.VITE_API_MODE as string | undefined) ?? 'auto'
const TOKEN_KEY = 'workflowdesk.token'

export const apiMode = ref<WorkflowDeskApi['mode']>('unavailable')
export const tokenRef = ref<string | null>(localStorage.getItem(TOKEN_KEY))

export function setToken(token: string | null) {
  tokenRef.value = token
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

let adapter: WorkflowDeskApi = new UnavailableAdapter()

async function pingLive(): Promise<boolean> {
  try {
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), 2500)
    const response = await fetch(`${BASE_URL}/health`, { signal: controller.signal })
    clearTimeout(timeout)
    return response.ok
  } catch {
    return false
  }
}

/**
 * Picks the adapter once at startup. VITE_API_MODE=browser forces the
 * static demo dataset (used for demo hosting with no backend); otherwise
 * we probe the real API's health endpoint and fall back to the honest
 * "unavailable" state - never to a silently-faked mock - if it can't be
 * reached.
 */
export async function initializeApi(): Promise<void> {
  if (REQUESTED_MODE === 'browser') {
    adapter = new BrowserAdapter()
    apiMode.value = 'browser'
    return
  }

  if (await pingLive()) {
    adapter = new LiveAdapter(BASE_URL, () => tokenRef.value)
    apiMode.value = 'live'
  } else {
    adapter = new UnavailableAdapter()
    apiMode.value = 'unavailable'
  }
}

export function getApi(): WorkflowDeskApi {
  return adapter
}

export async function retryConnection(): Promise<void> {
  await initializeApi()
}

export * from './types'
