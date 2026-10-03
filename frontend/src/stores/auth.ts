import { defineStore } from 'pinia'
import { getApi, setToken, tokenRef } from '../api'
import type { User } from '../types'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    restoring: true,
  }),

  getters: {
    isAuthenticated: (state) => state.user !== null,
    role: (state) => state.user?.role ?? null,
    canManage: (state) => state.user?.role === 'admin' || state.user?.role === 'counsellor',
    isAdmin: (state) => state.user?.role === 'admin',
  },

  actions: {
    /** Called once at startup to resolve an existing session, if any. */
    async restore() {
      this.restoring = true
      try {
        if (getApi().mode === 'browser' || tokenRef.value) {
          this.user = await getApi().me()
        } else {
          this.user = null
        }
      } catch {
        this.user = null
      } finally {
        this.restoring = false
      }
    },

    async login(email: string, password: string) {
      const result = await getApi().login(email, password)
      setToken(result.token)
      this.user = result.user
    },

    async logout() {
      try {
        await getApi().logout()
      } catch {
        // A failed logout call shouldn't block clearing the local session.
      }
      setToken(null)
      this.user = null
    },
  },
})
