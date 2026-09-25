import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAdminNotificationStore } from '~/stores/adminNotifications'

const apiMocks = vi.hoisted(() => ({
  listNotifications: vi.fn(),
  markNotificationRead: vi.fn(),
  markAllNotificationsRead: vi.fn(),
}))

vi.mock('~/composables/useAdminApi', () => ({
  useAdminApi: () => apiMocks,
}))

describe('admin notification store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    apiMocks.listNotifications.mockResolvedValue({
      data: [],
      links: { first: '', last: '', prev: null, next: null },
      meta: {
        current_page: 1,
        from: null,
        last_page: 1,
        path: '',
        per_page: 15,
        to: null,
        total: 0,
        unread_count: 0,
      },
    })
  })

  it('merges an administrator broadcast once and clears all scoped state on logout', async () => {
    const store = useAdminNotificationStore()
    await store.load()
    const payload = {
      id: 'notification-7',
      type: 'processor_error',
      title: 'Refund processing error',
      message: 'Review the case for details.',
      refund_request_id: 77,
      error_summary: 'The simulated processor could not complete the refund.',
    }

    expect(store.receiveBroadcast(payload)).toBe(true)
    expect(store.receiveBroadcast(payload)).toBe(false)
    expect(store.unreadCount).toBe(1)

    store.resetScope()

    expect(store.notifications).toEqual([])
    expect(store.unreadCount).toBe(0)
  })
})
