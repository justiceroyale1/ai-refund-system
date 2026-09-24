import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAdminDashboardStore } from '~/stores/adminDashboard'

const apiMocks = vi.hoisted(() => ({
  dashboard: vi.fn(),
  listRefundRequests: vi.fn(),
}))

vi.mock('~/composables/useAdminApi', () => ({
  useAdminApi: () => apiMocks,
}))

describe('admin dashboard store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads all dashboard metrics', async () => {
    apiMocks.dashboard.mockResolvedValue({
      approved_request_count: 5,
      denied_request_count: 4,
      escalated_request_count: 3,
      pending_refund_count: 2,
      failed_refund_count: 1,
    })
    const store = useAdminDashboardStore()

    await store.loadMetrics()

    expect(store.metrics?.approved_request_count).toBe(5)
    expect(store.metrics?.failed_refund_count).toBe(1)
    expect(store.metricsError).toBeNull()
  })

  it('passes filters and stores pagination metadata', async () => {
    apiMocks.listRefundRequests.mockResolvedValue({
      data: [{ id: 17 }],
      links: { first: '', last: '', prev: null, next: null },
      meta: { current_page: 2, from: 16, last_page: 3, path: '', per_page: 15, to: 17, total: 32 },
    })
    const store = useAdminDashboardStore()
    const filters = {
      decision: 'approved' as const,
      executionStatus: 'pending' as const,
      search: 'Amelia',
      page: 2,
    }

    await store.loadRefundRequests(filters)

    expect(apiMocks.listRefundRequests).toHaveBeenCalledWith(filters)
    expect(store.refundRequests).toEqual([{ id: 17 }])
    expect(store.currentPage).toBe(2)
    expect(store.lastPage).toBe(3)
    expect(store.total).toBe(32)
  })

  it('renders a stable error state when list loading fails', async () => {
    apiMocks.listRefundRequests.mockRejectedValue(new Error('offline'))
    const store = useAdminDashboardStore()

    await store.loadRefundRequests({ decision: null, executionStatus: null, search: '', page: 1 })

    expect(store.refundRequests).toEqual([])
    expect(store.requestsError).toBe('We could not load refund requests. Please try again.')
  })
})
