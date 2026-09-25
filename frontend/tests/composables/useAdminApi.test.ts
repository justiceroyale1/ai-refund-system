import { ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import { createAdminApiClient } from '~/composables/useAdminApi'

describe('admin API client', () => {
  it('establishes CSRF protection before credentialed login', async () => {
    const token = ref<string | null>(null)
    const transport = vi.fn()
      .mockImplementationOnce(async () => {
        token.value = 'csrf%20token'
      })
      .mockResolvedValueOnce({ data: { id: 1, name: 'Talia Mercer', email: 'talia@example.test' } })
    const refreshXsrfToken = vi.fn()
    const api = createAdminApiClient(transport, 'http://backend.test/', token, refreshXsrfToken)

    const admin = await api.login({ email: 'talia@example.test', password: 'password' })

    expect(transport).toHaveBeenNthCalledWith(
      1,
      'http://backend.test/sanctum/csrf-cookie',
      { credentials: 'include' },
    )
    expect(refreshXsrfToken).toHaveBeenCalledOnce()
    expect(transport).toHaveBeenNthCalledWith(
      2,
      'http://backend.test/api/admin/login',
      {
        body: { email: 'talia@example.test', password: 'password' },
        credentials: 'include',
        headers: { 'X-XSRF-TOKEN': 'csrf token' },
        method: 'POST',
      },
    )
    expect(admin.name).toBe('Talia Mercer')
  })

  it('sends filters and pagination on credentialed list requests', async () => {
    const transport = vi.fn().mockResolvedValue({ data: [], links: {}, meta: {} })
    const api = createAdminApiClient(transport, '', null)

    await api.listRefundRequests({
      decision: 'escalated',
      executionStatus: 'pending',
      search: 'ORD-1042',
      page: 2,
    })

    expect(transport).toHaveBeenCalledWith('/api/admin/refund-requests', {
      credentials: 'include',
      query: {
        decision: 'escalated',
        execution_status: 'pending',
        page: 2,
        search: 'ORD-1042',
      },
    })
  })

  it('loads a refund case through the protected detail endpoint', async () => {
    const transport = vi.fn().mockResolvedValue({ data: { id: 42 } })
    const api = createAdminApiClient(transport, 'http://backend.test', null)

    const refundRequest = await api.getRefundRequest(42)

    expect(transport).toHaveBeenCalledWith(
      'http://backend.test/api/admin/refund-requests/42',
      { credentials: 'include' },
    )
    expect(refundRequest.id).toBe(42)
  })

  it('sends the CSRF token and review payload for a human decision', async () => {
    const transport = vi.fn().mockResolvedValue({ data: { id: 42, decision: 'denied' } })
    const api = createAdminApiClient(transport, '', 'review%20token')

    await api.reviewRefundRequest(42, {
      decision: 'denied',
      review_note: 'Customer history checked.',
    })

    expect(transport).toHaveBeenCalledWith('/api/admin/refund-requests/42/review', {
      body: {
        decision: 'denied',
        review_note: 'Customer history checked.',
      },
      credentials: 'include',
      headers: { 'X-XSRF-TOKEN': 'review token' },
      method: 'POST',
    })
  })

  it('sends the CSRF token when signing out', async () => {
    const transport = vi.fn().mockResolvedValue(undefined)
    const api = createAdminApiClient(transport, '', 'logout-token')

    await api.logout()

    expect(transport).toHaveBeenCalledWith('/api/admin/logout', {
      credentials: 'include',
      headers: { 'X-XSRF-TOKEN': 'logout-token' },
      method: 'POST',
    })
  })

  it('uses credentialed notification endpoints and CSRF-protected read mutations', async () => {
    const transport = vi.fn()
      .mockResolvedValueOnce({ data: [], links: {}, meta: { unread_count: 0 } })
      .mockResolvedValueOnce({ data: { id: 'notification-1' }, meta: { unread_count: 0 } })
      .mockResolvedValueOnce({ data: { unread_count: 0 } })
    const api = createAdminApiClient(transport, '', 'notification-token')

    await api.listNotifications(2)
    await api.markNotificationRead('notification-1')
    await api.markAllNotificationsRead()

    expect(transport).toHaveBeenNthCalledWith(1, '/api/admin/notifications', {
      credentials: 'include',
      query: { page: 2 },
    })
    expect(transport).toHaveBeenNthCalledWith(2, '/api/admin/notifications/notification-1/read', {
      credentials: 'include',
      headers: { 'X-XSRF-TOKEN': 'notification-token' },
      method: 'PATCH',
    })
    expect(transport).toHaveBeenNthCalledWith(3, '/api/admin/notifications/read-all', {
      credentials: 'include',
      headers: { 'X-XSRF-TOKEN': 'notification-token' },
      method: 'PATCH',
    })
  })
})
