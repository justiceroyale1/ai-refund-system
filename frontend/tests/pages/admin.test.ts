import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminDashboardPage from '~/pages/admin/index.vue'

const navigateToMock = vi.hoisted(() => vi.fn())
const apiMocks = vi.hoisted(() => ({
  dashboard: vi.fn(),
  listRefundRequests: vi.fn(),
}))

mockNuxtImport('navigateTo', () => navigateToMock)

vi.mock('~/composables/useAdminApi', () => ({
  useAdminApi: () => apiMocks,
}))

describe('admin dashboard page', () => {
  let pinia: ReturnType<typeof createPinia>

  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
    apiMocks.dashboard.mockResolvedValue({
      approved_request_count: 5,
      denied_request_count: 4,
      escalated_request_count: 3,
      pending_refund_count: 2,
      failed_refund_count: 1,
    })
    apiMocks.listRefundRequests.mockResolvedValue({
      data: [],
      links: { first: '', last: '', prev: '', next: '' },
      meta: { current_page: 2, from: 16, last_page: 3, path: '', per_page: 15, to: 30, total: 31 },
    })
  })

  it('renders metrics and loads URL-backed filters', async () => {
    const wrapper = await mountSuspended(AdminDashboardPage, {
      global: { plugins: [pinia] },
      route: '/admin?decision=escalated&execution_status=pending&search=Amelia&page=2',
    })

    expect(wrapper.text()).toContain('Approved requests')
    expect(wrapper.text()).toContain('Failed refunds')
    expect(wrapper.text()).toContain('31 requests found')
    expect(apiMocks.listRefundRequests).toHaveBeenCalledWith({
      decision: 'escalated',
      executionStatus: 'pending',
      search: 'Amelia',
      page: 2,
    })
    expect(wrapper.get<HTMLInputElement>('#refund-search').element.value).toBe('Amelia')
    expect(wrapper.get<HTMLSelectElement>('#decision-filter').element.value).toBe('escalated')
  })

  it('synchronizes applied search and filter values to navigation', async () => {
    const wrapper = await mountSuspended(AdminDashboardPage, {
      global: { plugins: [pinia] },
      route: '/admin',
    })

    await wrapper.get('#refund-search').setValue('  ORD-1042  ')
    await wrapper.get('#decision-filter').setValue('approved')
    await wrapper.get('#execution-filter').setValue('processing')
    await wrapper.get('form').trigger('submit')

    expect(navigateToMock).toHaveBeenCalledWith({
      path: '/admin',
      query: {
        decision: 'approved',
        execution_status: 'processing',
        search: 'ORD-1042',
      },
    })
  })

  it('preserves active filters when paging', async () => {
    const wrapper = await mountSuspended(AdminDashboardPage, {
      global: { plugins: [pinia] },
      route: '/admin?decision=denied&page=2',
    })

    const nextButton = wrapper.findAll('button').find(button => button.text() === 'Next')
    await nextButton?.trigger('click')

    expect(navigateToMock).toHaveBeenCalledWith({
      path: '/admin',
      query: { decision: 'denied', page: '3' },
    })
  })
})
