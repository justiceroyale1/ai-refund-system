import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiClientError } from '~/composables/useRefundApi'
import { useAdminRefundCaseStore } from '~/stores/adminRefundCase'
import { makeRefundRequestDetail } from '../fixtures/adminRefundCase'

const apiMocks = vi.hoisted(() => ({
  getRefundRequest: vi.fn(),
  reviewRefundRequest: vi.fn(),
}))

vi.mock('~/composables/useAdminApi', () => ({
  useAdminApi: () => apiMocks,
}))

describe('admin refund case store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads a refund case', async () => {
    apiMocks.getRefundRequest.mockResolvedValue(makeRefundRequestDetail())
    const store = useAdminRefundCaseStore()

    await store.loadRefundRequest(42)

    expect(apiMocks.getRefundRequest).toHaveBeenCalledWith(42)
    expect(store.refundRequest?.order?.reference).toBe('ORD-2042')
    expect(store.loadError).toBeNull()
  })

  it('submits a trimmed approval note and stores the authoritative response', async () => {
    const pendingCase = makeRefundRequestDetail({ decision: 'escalated', reviewer: null, review_note: null, refund: null })
    const approvedCase = makeRefundRequestDetail()
    apiMocks.reviewRefundRequest.mockResolvedValue(approvedCase)
    const store = useAdminRefundCaseStore()
    store.refundRequest = pendingCase

    const succeeded = await store.submitReview('approved', '  Approved after review.  ')

    expect(apiMocks.reviewRefundRequest).toHaveBeenCalledWith(42, {
      decision: 'approved',
      review_note: 'Approved after review.',
    })
    expect(succeeded).toBe(true)
    expect(store.refundRequest).toEqual(approvedCase)
    expect(store.reviewNotice).toBe('The refund request was approved.')
  })

  it('surfaces review validation errors', async () => {
    apiMocks.reviewRefundRequest.mockRejectedValue(new ApiClientError(
      'The given data was invalid.',
      422,
      'VALIDATION_FAILED',
      { errors: { review_note: ['The review note field must not be greater than 4000 characters.'] } },
    ))
    const store = useAdminRefundCaseStore()
    store.refundRequest = makeRefundRequestDetail({ decision: 'escalated', reviewer: null })

    const succeeded = await store.submitReview('denied', 'N'.repeat(4001))

    expect(succeeded).toBe(false)
    expect(store.reviewValidationErrors.review_note).toContain('4000 characters')
    expect(store.reviewError).toContain('correct the review details')
  })

  it('refetches the authoritative case after a stale second review conflict', async () => {
    const pendingCase = makeRefundRequestDetail({ decision: 'escalated', reviewer: null, review_note: null, refund: null })
    const deniedCase = makeRefundRequestDetail({
      decision: 'denied',
      reviewer: { id: 10, name: 'Another Admin', email: 'admin@example.test' },
      review_note: null,
      refund: null,
    })
    apiMocks.reviewRefundRequest.mockRejectedValue(new ApiClientError(
      'This refund request is no longer awaiting review.',
      409,
      'CONFLICT',
    ))
    apiMocks.getRefundRequest.mockResolvedValue(deniedCase)
    const store = useAdminRefundCaseStore()
    store.refundRequest = pendingCase

    const succeeded = await store.submitReview('approved', '')

    expect(succeeded).toBe(false)
    expect(apiMocks.getRefundRequest).toHaveBeenCalledWith(42)
    expect(store.refundRequest?.decision).toBe('denied')
    expect(store.reviewError).toContain('latest case details')
  })
})
