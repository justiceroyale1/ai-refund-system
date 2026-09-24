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
})
