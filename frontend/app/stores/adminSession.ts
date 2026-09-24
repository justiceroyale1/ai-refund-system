import { defineStore } from 'pinia'
import { ApiClientError } from '~/composables/useRefundApi'
import { useAdminApi } from '~/composables/useAdminApi'
import type { AdminUser } from '~/types/admin'

type AdminSessionStatus = 'unknown' | 'authenticated' | 'guest'

interface AdminSessionState {
  admin: AdminUser | null
  status: AdminSessionStatus
  isChecking: boolean
  isLoggingIn: boolean
  isLoggingOut: boolean
  loginError: string | null
}

function firstFieldError(error: ApiClientError): string | null {
  if (typeof error.details !== 'object' || error.details === null || Array.isArray(error.details)) {
    return null
  }

  const errors = error.details.errors

  if (typeof errors !== 'object' || errors === null || Array.isArray(errors)) {
    return null
  }

  for (const messages of Object.values(errors)) {
    if (Array.isArray(messages) && typeof messages[0] === 'string') {
      return messages[0]
    }
  }

  return null
}

export const useAdminSessionStore = defineStore('admin-session', {
  state: (): AdminSessionState => ({
    admin: null,
    status: 'unknown',
    isChecking: false,
    isLoggingIn: false,
    isLoggingOut: false,
    loginError: null,
  }),

  getters: {
    isAuthenticated: state => state.status === 'authenticated' && state.admin !== null,
  },

  actions: {
    clearSession(): void {
      this.admin = null
      this.status = 'guest'
    },

    async ensureSession(force = false): Promise<boolean> {
      if (!force && this.status !== 'unknown') {
        return this.isAuthenticated
      }

      if (this.isChecking) {
        return this.isAuthenticated
      }

      this.isChecking = true

      try {
        this.admin = await useAdminApi().currentAdmin()
        this.status = 'authenticated'

        return true
      }
      catch {
        this.clearSession()

        return false
      }
      finally {
        this.isChecking = false
      }
    },

    async login(email: string, password: string): Promise<boolean> {
      if (this.isLoggingIn) {
        return false
      }

      this.isLoggingIn = true
      this.loginError = null

      try {
        this.admin = await useAdminApi().login({ email, password })
        this.status = 'authenticated'

        return true
      }
      catch (error) {
        this.clearSession()
        this.loginError = error instanceof ApiClientError
          ? firstFieldError(error) ?? error.message
          : 'We could not sign you in. Please try again.'

        return false
      }
      finally {
        this.isLoggingIn = false
      }
    },

    async logout(): Promise<void> {
      if (this.isLoggingOut) {
        return
      }

      this.isLoggingOut = true

      try {
        await useAdminApi().logout()
      }
      catch {
        return
      }
      finally {
        this.clearSession()
        this.isLoggingOut = false
      }
    },
  },
})
