import { defineStore } from 'pinia'
import { useAdminApi } from '~/composables/useAdminApi'
import { mergeBroadcastNotification, mergeNotificationPage } from '~/lib/notifications'
import type {
  AdminNotification,
  AdminNotificationBroadcast,
} from '~/types/notification'

interface AdminNotificationState {
  notifications: AdminNotification[]
  unreadCount: number
  currentPage: number
  lastPage: number
  scopeVersion: number
  isLoading: boolean
  error: string | null
}

export const useAdminNotificationStore = defineStore('admin-notifications', {
  state: (): AdminNotificationState => ({
    notifications: [],
    unreadCount: 0,
    currentPage: 0,
    lastPage: 1,
    scopeVersion: 0,
    isLoading: false,
    error: null,
  }),

  getters: {
    hasMore(state): boolean {
      return state.currentPage < state.lastPage
    },
  },

  actions: {
    resetScope(): void {
      this.notifications = []
      this.unreadCount = 0
      this.currentPage = 0
      this.lastPage = 1
      this.scopeVersion += 1
      this.isLoading = false
      this.error = null
    },

    async load(page = 1): Promise<void> {
      if (this.isLoading) {
        return
      }

      const requestScope = this.scopeVersion
      this.isLoading = true
      this.error = null

      try {
        const response = await useAdminApi().listNotifications(page)

        if (requestScope !== this.scopeVersion) {
          return
        }

        this.notifications = mergeNotificationPage(
          this.notifications,
          response.data,
          page === 1,
        )
        this.unreadCount = response.meta.unread_count
        this.currentPage = response.meta.current_page
        this.lastPage = response.meta.last_page
      }
      catch {
        if (requestScope === this.scopeVersion) {
          this.error = 'We could not load administrator notifications. Please try again.'
        }
      }
      finally {
        if (requestScope === this.scopeVersion) {
          this.isLoading = false
        }
      }
    },

    async loadMore(): Promise<void> {
      if (this.hasMore) {
        await this.load(this.currentPage + 1)
      }
    },

    receiveBroadcast(payload: AdminNotificationBroadcast): boolean {
      const result = mergeBroadcastNotification(this.notifications, {
        ...payload,
        read_at: null,
        created_at: new Date().toISOString(),
      })

      this.notifications = result.notifications

      if (result.inserted) {
        this.unreadCount += 1
      }

      return result.inserted
    },

    async markRead(notificationId: string): Promise<void> {
      const notification = this.notifications.find(item => item.id === notificationId)

      if (!notification || notification.read_at !== null) {
        return
      }

      const requestScope = this.scopeVersion
      let response

      try {
        response = await useAdminApi().markNotificationRead(notificationId)
      }
      catch {
        if (requestScope === this.scopeVersion) {
          this.error = 'We could not update this notification.'
        }

        return
      }

      if (requestScope !== this.scopeVersion) {
        return
      }

      this.notifications = this.notifications.map(item => (
        item.id === notificationId ? response.data : item
      ))
      this.unreadCount = response.meta.unread_count
    },

    async markAllRead(): Promise<void> {
      if (this.unreadCount === 0) {
        return
      }

      const requestScope = this.scopeVersion
      let response

      try {
        response = await useAdminApi().markAllNotificationsRead()
      }
      catch {
        if (requestScope === this.scopeVersion) {
          this.error = 'We could not update administrator notifications.'
        }

        return
      }

      if (requestScope !== this.scopeVersion) {
        return
      }

      const readAt = new Date().toISOString()
      this.notifications = this.notifications.map(notification => ({
        ...notification,
        read_at: notification.read_at ?? readAt,
      }))
      this.unreadCount = response.data.unread_count
    },
  },
})
