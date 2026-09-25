import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useCustomerNotificationStore } from '~/stores/customerNotifications'
import { useCustomerStore } from '~/stores/customer'

const apiMocks = vi.hoisted(() => ({
  listNotifications: vi.fn(),
  markNotificationRead: vi.fn(),
  markAllNotificationsRead: vi.fn(),
}))

vi.mock('~/composables/useRefundApi', () => ({
  useRefundApi: () => apiMocks,
}))

function page() {
  return {
    data: [{
      id: 'notification-1',
      type: 'refund_approved',
      title: 'Refund approved',
      message: 'Your refund request was approved.',
      conversation_id: 12,
      read_at: null,
      created_at: '2026-09-25T09:00:00.000Z',
    }],
    links: { first: '', last: '', prev: null, next: null },
    meta: {
      current_page: 1,
      from: 1,
      last_page: 1,
      path: '',
      per_page: 15,
      to: 1,
      total: 1,
      unread_count: 1,
    },
  }
}

describe('customer notification store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    const customerStore = useCustomerStore()
    customerStore.customers = [{ id: 4, name: 'Demo Customer', email: 'demo@example.test' }]
    customerStore.selectedCustomerId = 4
  })

  it('loads durable history and merges duplicate broadcasts only once', async () => {
    apiMocks.listNotifications.mockResolvedValue(page())
    const store = useCustomerNotificationStore()

    await store.load()

    const broadcast = {
      id: 'notification-2',
      type: 'refund_processed',
      title: 'Refund processed',
      message: 'Your refund has been processed successfully.',
      conversation_id: 12,
    }

    expect(store.receiveBroadcast(broadcast)).toBe(true)
    expect(store.receiveBroadcast(broadcast)).toBe(false)
    expect(store.notifications.map(notification => notification.id)).toEqual([
      'notification-2',
      'notification-1',
    ])
    expect(store.unreadCount).toBe(2)
  })

  it('uses the server unread count when marking one or all notifications read', async () => {
    apiMocks.listNotifications.mockResolvedValue(page())
    apiMocks.markNotificationRead.mockResolvedValue({
      data: { ...page().data[0], read_at: '2026-09-25T10:00:00.000Z' },
      meta: { unread_count: 0 },
    })
    apiMocks.markAllNotificationsRead.mockResolvedValue({ data: { unread_count: 0 } })
    const store = useCustomerNotificationStore()

    await store.load()
    await store.markRead('notification-1')

    expect(store.notifications[0]?.read_at).toBe('2026-09-25T10:00:00.000Z')
    expect(store.unreadCount).toBe(0)

    store.receiveBroadcast({
      id: 'notification-2',
      type: 'refund_processed',
      title: 'Refund processed',
      message: 'Processed.',
      conversation_id: 12,
    })
    await store.markAllRead()

    expect(store.notifications.every(notification => notification.read_at !== null)).toBe(true)
    expect(store.unreadCount).toBe(0)
  })

  it('discards a history response from a previous customer scope', async () => {
    let resolveRequest: ((value: ReturnType<typeof page>) => void) | undefined
    apiMocks.listNotifications.mockReturnValue(new Promise((resolve) => {
      resolveRequest = resolve
    }))
    const store = useCustomerNotificationStore()
    const request = store.load()

    store.resetScope(9)
    resolveRequest?.(page())
    await request

    expect(store.scopeCustomerId).toBe(9)
    expect(store.notifications).toEqual([])
    expect(store.unreadCount).toBe(0)
  })
})
