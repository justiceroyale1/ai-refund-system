import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import AdminRefundRequestList from '~/components/admin/AdminRefundRequestList.vue'
import type { RefundRequestSummary } from '~/types/admin'

const request: RefundRequestSummary = {
  id: 42,
  customer: { id: 1, name: 'Amelia Carter', email: 'amelia@example.test' },
  order: { id: 3, reference: 'ORD-2042' },
  order_item: { id: 5, name: 'Mechanical Keyboard' },
  reason: 'damaged_item',
  amount_cents: 12999,
  initial_decision: 'approved',
  decision: 'approved',
  decision_source: 'policy',
  decision_code: 'damaged_item_eligible',
  execution_status: 'pending',
  decided_at: '2026-09-20T10:00:00.000Z',
  created_at: '2026-09-20T10:00:00.000Z',
}

describe('admin refund request list', () => {
  it('renders a loading state', async () => {
    const wrapper = await mountSuspended(AdminRefundRequestList, {
      props: { requests: [], loading: true, error: null },
    })

    expect(wrapper.get('[data-testid="refund-requests-loading"]').text()).toContain('Loading')
  })

  it('renders an actionable error state', async () => {
    const wrapper = await mountSuspended(AdminRefundRequestList, {
      props: { requests: [], loading: false, error: 'Unable to load.' },
    })

    await wrapper.get('button').trigger('click')

    expect(wrapper.get('[role="alert"]').text()).toBe('Unable to load.')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })

  it('renders an empty state', async () => {
    const wrapper = await mountSuspended(AdminRefundRequestList, {
      props: { requests: [], loading: false, error: null },
    })

    expect(wrapper.get('[data-testid="refund-requests-empty"]').text()).toContain('No refund requests found')
  })

  it('renders human-readable request data and case navigation', async () => {
    const wrapper = await mountSuspended(AdminRefundRequestList, {
      props: { requests: [request], loading: false, error: null },
    })

    expect(wrapper.text()).toContain('Amelia Carter')
    expect(wrapper.text()).toContain('$129.99')
    expect(wrapper.text()).toContain('Approved')
    expect(wrapper.text()).toContain('Pending')
    expect(wrapper.findAll('a[href="/admin/refunds/42"]')).toHaveLength(2)
  })
})
