import { flushPromises } from '@vue/test-utils'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminRefundCasePage from '~/pages/admin/refunds/[id].vue'
import { makeRefundRequestDetail } from '../fixtures/adminRefundCase'

const apiMocks = vi.hoisted(() => ({
  getRefundRequest: vi.fn(),
  reviewRefundRequest: vi.fn(),
}))
const routeMock = vi.hoisted(() => ({
  params: { id: '42' },
}))

mockNuxtImport('useRoute', () => () => routeMock)

vi.mock('~/composables/useAdminApi', () => ({
  useAdminApi: () => apiMocks,
}))

describe('admin refund case page', () => {
  let pinia: ReturnType<typeof createPinia>

  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
  })

  it('renders the complete case with human-readable semantic details', async () => {
    apiMocks.getRefundRequest.mockResolvedValue(makeRefundRequestDetail())

    const wrapper = await mountSuspended(AdminRefundCasePage, {
      global: { plugins: [pinia] },
    })

    expect(apiMocks.getRefundRequest).toHaveBeenCalledWith(42)
    expect(wrapper.text()).toContain('Amelia Carter')
    expect(wrapper.text()).toContain('Mechanical Keyboard')
    expect(wrapper.text()).toContain('$129.99')
    expect(wrapper.text()).toContain('I changed my mind about this keyboard.')
    expect(wrapper.text()).toContain('82%')
    expect(wrapper.text()).toContain('Prompt injection detected')
    expect(wrapper.text()).toContain('Changed mind')
    expect(wrapper.text()).toContain('Item is not marked as final sale.')
    expect(wrapper.text()).toContain('Approved as a one-time exception.')
    expect(wrapper.text()).toContain('Refund request reviewed')
    expect(wrapper.get('[data-testid="processor-error"]').text()).toContain('read-only')
  })

  it('renders absent optional details and review controls for an escalated case', async () => {
    apiMocks.getRefundRequest.mockResolvedValue(makeRefundRequestDetail({
      decision: 'escalated',
      reviewer: null,
      review_note: null,
      latest_ai_analysis: null,
      refund: null,
      conversation: {
        id: 11,
        state: 'resolved',
        status: 'resolved',
        messages: [],
        resolved_at: '2026-09-23T10:01:00.000Z',
        created_at: '2026-09-23T09:59:00.000Z',
        updated_at: '2026-09-23T10:01:00.000Z',
      },
      audit_timeline: [],
      decided_at: null,
    }))

    const wrapper = await mountSuspended(AdminRefundCasePage, {
      global: { plugins: [pinia] },
    })

    expect(wrapper.text()).toContain('No AI analysis is available')
    expect(wrapper.text()).toContain('No refund has been created')
    expect(wrapper.text()).toContain('No audit events are available')
    expect(wrapper.text()).toContain('No conversation messages are available')
    expect(wrapper.text()).toContain('Approve request')
    expect(wrapper.text()).toContain('Deny request')
  })

  it('submits an approved review and replaces controls with the authoritative result', async () => {
    const escalatedCase = makeRefundRequestDetail({
      decision: 'escalated',
      reviewer: null,
      review_note: null,
      refund: null,
      decided_at: null,
    })
    apiMocks.getRefundRequest.mockResolvedValue(escalatedCase)
    apiMocks.reviewRefundRequest.mockResolvedValue(makeRefundRequestDetail())
    const wrapper = await mountSuspended(AdminRefundCasePage, {
      global: { plugins: [pinia] },
    })

    const approveButton = wrapper.findAll('button').find(button => button.text().includes('Approve request'))
    await approveButton?.trigger('click')
    await wrapper.get('#review-note').setValue('Approved after reviewing the transcript.')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(apiMocks.reviewRefundRequest).toHaveBeenCalledWith(42, {
      decision: 'approved',
      review_note: 'Approved after reviewing the transcript.',
    })
    expect(wrapper.text()).toContain('The refund request was approved.')
    expect(wrapper.text()).not.toContain('Approve request')
  })

  it('hides review controls after a case has a final decision', async () => {
    apiMocks.getRefundRequest.mockResolvedValue(makeRefundRequestDetail({ decision: 'denied', refund: null }))

    const wrapper = await mountSuspended(AdminRefundCasePage, {
      global: { plugins: [pinia] },
    })

    expect(wrapper.text()).not.toContain('Approve request')
    expect(wrapper.text()).not.toContain('Deny request')
    expect(wrapper.text()).toContain('Current: Denied')
  })
})
