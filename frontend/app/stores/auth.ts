import { defineStore } from 'pinia'
import type { ApiEnvelope, User } from '~/types/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    loading: false,
    initialized: false,
    // initialize() is called from several places; without sharing the
    // in-flight request the second caller returns before user is loaded.
    initPromise: null as Promise<void> | null,
  }),

  getters: {
    // The token lives in an httpOnly cookie and cannot be read from JS; the
    // proof of a session is /auth/me returning a user.
    isAuthenticated: state => !!state.user,
  },

  actions: {
    initialize(): Promise<void> {
      if (this.initialized) return Promise.resolve()
      if (this.initPromise) return this.initPromise

      this.initPromise = (async () => {
        try {
          await this.fetchMe()
        } catch {
          this.user = null
        }
      })().finally(() => {
        this.initialized = true
        this.initPromise = null
      })

      return this.initPromise
    },

    async login(email: string, password: string) {
      this.loading = true
      try {
        // Goes to the Nuxt server: the token is written to an httpOnly
        // cookie there and never returned to the browser.
        const res = await $fetch<ApiEnvelope<{ user: User }>>('/api/session/login', {
          method: 'POST',
          body: { email, password },
        })
        this.setUser(res.data.user)
      } finally {
        this.loading = false
      }
    },

    async register(name: string, email: string, password: string) {
      this.loading = true
      try {
        const res = await $fetch<ApiEnvelope<{ user: User }>>('/api/session/register', {
          method: 'POST',
          body: { name, email, password },
        })
        this.setUser(res.data.user)
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        await $fetch('/api/session/logout', { method: 'POST' })
      } catch { /* cookie is cleared regardless */ }
      this.clear()
      await navigateTo('/login')
    },

    async fetchMe() {
      const { api } = useApi()
      // redirectOn401: false — this request runs from inside route
      // middleware (via initialize). A 401 here is not an error, it means
      // "no session"; the middleware decides where to navigate.
      const res = await api<ApiEnvelope<{ user: User }>>('/auth/me', { redirectOn401: false })
      this.user = res.data.user
    },

    setUser(user: User) {
      this.user = user
      this.initialized = true
    },

    clear() {
      this.user = null
    },
  },
})
