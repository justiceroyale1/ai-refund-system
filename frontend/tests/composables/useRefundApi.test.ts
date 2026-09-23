import { ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import {
  ApiClientError,
  createRefundApiClient,
} from '~/composables/useRefundApi'

describe('refund API client', () => {
  it('adds the selected customer header to every customer-scoped request', async () => {
    const selectedCustomerId = ref<number | null>(7)
    const transport = vi.fn()
      .mockResolvedValueOnce({ data: [], links: {}, meta: {} })
      .mockResolvedValueOnce({ data: { id: 19 } })
      .mockResolvedValueOnce({ data: { id: 20 } })
    const api = createRefundApiClient(transport, 'http://backend.test/', selectedCustomerId)

    await api.listConversations()
    selectedCustomerId.value = 8
    await api.getConversation(19)
    await api.createConversation()

    expect(transport).toHaveBeenNthCalledWith(
      1,
      'http://backend.test/api/customer/conversations',
      expect.objectContaining({
        headers: { 'X-Demo-Customer-Id': '7' },
        query: { page: 1 },
      }),
    )
    expect(transport).toHaveBeenNthCalledWith(
      2,
      'http://backend.test/api/customer/conversations/19',
      expect.objectContaining({ headers: { 'X-Demo-Customer-Id': '8' } }),
    )
    expect(transport).toHaveBeenNthCalledWith(
      3,
      'http://backend.test/api/customer/conversations',
      expect.objectContaining({
        headers: { 'X-Demo-Customer-Id': '8' },
        method: 'POST',
      }),
    )
  })

  it('keeps the public demo-customer request unscoped', async () => {
    const transport = vi.fn().mockResolvedValue({ data: [] })
    const api = createRefundApiClient(transport, '', null)

    await api.listDemoCustomers()

    expect(transport).toHaveBeenCalledWith('/api/demo/customers', undefined)
  })

  it('refuses customer requests until an identity is selected', async () => {
    const transport = vi.fn()
    const api = createRefundApiClient(transport, '', null)

    await expect(api.listConversations()).rejects.toEqual(
      expect.objectContaining({
        message: 'Select a demo customer before continuing.',
      }),
    )
    expect(transport).not.toHaveBeenCalled()
  })

  it('normalizes the structured backend error contract', async () => {
    const transport = vi.fn().mockRejectedValue({
      statusCode: 404,
      data: {
        error: {
          code: 'RESOURCE_NOT_FOUND',
          message: 'The requested resource was not found.',
          details: [],
        },
      },
    })
    const api = createRefundApiClient(transport, '', 4)

    await expect(api.getConversation(999)).rejects.toEqual(
      new ApiClientError(
        'The requested resource was not found.',
        404,
        'RESOURCE_NOT_FOUND',
      ),
    )
  })
})
