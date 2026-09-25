import { flushPromises } from '@vue/test-utils'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import { createPinia, getActivePinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminLayout from '~/layouts/admin.vue'
import CustomerLayout from '~/layouts/customer.vue'
import { useAdminNotificationStore } from '~/stores/adminNotifications'
import { useAdminSessionStore } from '~/stores/adminSession'
import { useCustomerNotificationStore } from '~/stores/customerNotifications'

const navigateToMock = vi.hoisted(() => vi.fn())
const toastMocks = vi.hoisted(() => ({
  info: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
}))
const realtimeMocks = vi.hoisted(() => ({
  create: vi.fn(),
  disconnect: vi.fn(),
}))
const customerApiMocks = vi.hoisted(() => ({
  listDemoCustomers: vi.fn(),
  listConversations: vi.fn(),
  listNotifications: vi.fn(),
  markNotificationRead: vi.fn(),
  markAllNotificationsRead: vi.fn(),
}))
const adminApiMocks = vi.hoisted(() => ({
  listNotifications: vi.fn(),
  markNotificationRead: vi.fn(),
  markAllNotificationsRead: vi.fn(),
  logout: vi.fn(),
}))

mockNuxtImport('navigateTo', () => navigateToMock)

vi.mock('vue-sonner', async (importOriginal) => ({
  ...await importOriginal<typeof import('vue-sonner')>(),
  toast: toastMocks,
}))

vi.mock('~/lib/realtime', () => ({
  createNotificationRealtimeConnection: realtimeMocks.create,
}))

vi.mock('~/composables/useRefundApi', async (importOriginal) => ({
  ...await importOriginal<typeof import('~/composables/useRefundApi')>(),
  useRefundApi: () => customerApiMocks,
}))

vi.mock('~/composables/useAdminApi', () => ({
  useAdminApi: () => adminApiMocks,
}))

function emptyNotificationPage() {
  return {
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
  }
}

describe('notification realtime lifecycle', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    realtimeMocks.create.mockReturnValue({ disconnect: realtimeMocks.disconnect })
    customerApiMocks.listDemoCustomers.mockResolvedValue([
      { id: 1, name: 'First Customer', email: 'first@example.test' },
      { id: 2, name: 'Second Customer', email: 'second@example.test' },
    ])
    customerApiMocks.listConversations.mockResolvedValue({
      data: [],
      links: { first: '', last: '', prev: null, next: null },
      meta: { current_page: 1, from: null, last_page: 1, path: '', per_page: 15, to: null, total: 0 },
    })
    customerApiMocks.listNotifications.mockResolvedValue(emptyNotificationPage())
    customerApiMocks.markNotificationRead.mockImplementation(async (notificationId: string) => ({
      data: {
        id: notificationId,
        type: 'refund_processed',
        title: 'Refund processed',
        message: 'Your refund has been processed successfully.',
        conversation_id: 42,
        read_at: '2026-09-25T10:00:00.000Z',
        created_at: '2026-09-25T09:00:00.000Z',
      },
      meta: { unread_count: 0 },
    }))
    adminApiMocks.listNotifications.mockResolvedValue(emptyNotificationPage())
    adminApiMocks.markNotificationRead.mockImplementation(async (notificationId: string) => ({
      data: {
        id: notificationId,
        type: 'refund_review_required',
        title: 'Refund request needs review',
        message: 'A refund request requires a human decision. Review the case details.',
        refund_request_id: 88,
        error_summary: null,
        read_at: '2026-09-25T10:00:00.000Z',
        created_at: '2026-09-25T09:00:00.000Z',
      },
      meta: { unread_count: 0 },
    }))
    adminApiMocks.logout.mockResolvedValue(undefined)
  })

  it('unsubscribes before changing customer scope and gives toast and history the same destination', async () => {
    const wrapper = await mountSuspended(CustomerLayout, {
      global: { plugins: [getActivePinia()!] },
      slots: { default: '<p>Customer content</p>' },
    })

    expect(realtimeMocks.create).toHaveBeenCalledWith(expect.objectContaining({
      channelName: 'customers.1',
      authHeaders: { 'X-Demo-Customer-Id': '1' },
    }))

    const firstConnection = realtimeMocks.create.mock.calls[0]?.[0]
    firstConnection.onNotification({
      id: 'notification-42',
      type: 'refund_processed',
      title: 'Refund processed',
      message: 'Your refund has been processed successfully.',
      conversation_id: 42,
    })
    await flushPromises()

    const toastAction = toastMocks.info.mock.calls[0]?.[1]?.action
    toastAction.onClick()
    await flushPromises()
    const toastDestination = navigateToMock.mock.calls.at(-1)?.[0]

    navigateToMock.mockClear()
    const notificationButton = wrapper.findAll('section button')
      .find(button => button.text().includes('Refund processed'))
    await notificationButton?.trigger('click')
    await flushPromises()

    expect(navigateToMock).toHaveBeenLastCalledWith(toastDestination)

    await wrapper.get('select[aria-label="Demo customer"]').setValue('2')
    await flushPromises()

    expect(realtimeMocks.disconnect).toHaveBeenCalled()
    expect(realtimeMocks.create).toHaveBeenLastCalledWith(expect.objectContaining({
      channelName: 'customers.2',
      authHeaders: { 'X-Demo-Customer-Id': '2' },
    }))
    expect(useCustomerNotificationStore().scopeCustomerId).toBe(2)
  })

  it('shows one warning toast for a review-required broadcast and opens the same case as history', async () => {
    const pinia = getActivePinia()!
    const sessionStore = useAdminSessionStore()
    sessionStore.admin = { id: 7, name: 'Talia Mercer', email: 'talia@example.test' }
    sessionStore.status = 'authenticated'
    const wrapper = await mountSuspended(AdminLayout, {
      global: { plugins: [pinia] },
      slots: { default: '<p>Admin content</p>' },
    })
    const connection = realtimeMocks.create.mock.calls.at(-1)?.[0]
    const payload = {
      id: 'notification-review-88',
      type: 'refund_review_required',
      title: 'Refund request needs review',
      message: 'A refund request requires a human decision. Review the case details.',
      refund_request_id: 88,
      error_summary: null,
    }

    connection.onNotification(payload)
    connection.onNotification(payload)
    await flushPromises()

    expect(toastMocks.warning).toHaveBeenCalledOnce()
    expect(toastMocks.error).not.toHaveBeenCalled()
    expect(useAdminNotificationStore().unreadCount).toBe(1)

    const toastAction = toastMocks.warning.mock.calls[0]?.[1]?.action
    toastAction.onClick()
    await flushPromises()
    const toastDestination = navigateToMock.mock.calls.at(-1)?.[0]

    navigateToMock.mockClear()
    const notificationButton = wrapper.findAll('section button')
      .find(button => button.text().includes('Refund request needs review'))
    await notificationButton?.trigger('click')
    await flushPromises()

    expect(toastDestination).toBe('/admin/refunds/88')
    expect(navigateToMock).toHaveBeenLastCalledWith(toastDestination)
  })

  it('disconnects and clears administrator notifications before logout navigation', async () => {
    const pinia = getActivePinia()!
    const sessionStore = useAdminSessionStore()
    sessionStore.admin = { id: 7, name: 'Talia Mercer', email: 'talia@example.test' }
    sessionStore.status = 'authenticated'
    const notificationStore = useAdminNotificationStore()
    notificationStore.receiveBroadcast({
      id: 'notification-7',
      type: 'processor_error',
      title: 'Refund processing error',
      message: 'Review the case for details.',
      refund_request_id: 77,
      error_summary: 'The processor could not complete the refund.',
    })
    const wrapper = await mountSuspended(AdminLayout, {
      global: { plugins: [pinia] },
      slots: { default: '<p>Admin content</p>' },
    })

    expect(realtimeMocks.create).toHaveBeenCalledWith(expect.objectContaining({
      channelName: 'admins.7',
    }))

    const signOut = wrapper.findAll('button').find(button => button.text().includes('Sign out'))
    await signOut?.trigger('click')
    await flushPromises()

    expect(realtimeMocks.disconnect).toHaveBeenCalled()
    expect(notificationStore.notifications).toEqual([])
    expect(notificationStore.unreadCount).toBe(0)
    expect(adminApiMocks.logout).toHaveBeenCalledOnce()
    expect(navigateToMock).toHaveBeenCalledWith('/admin/login')
  })
})
