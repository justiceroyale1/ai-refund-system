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
      .mockResolvedValueOnce({ data: { id: 20 } })
    const api = createRefundApiClient(transport, 'http://backend.test/', selectedCustomerId)

    await api.listConversations()
    selectedCustomerId.value = 8
    await api.getConversation(19)
    await api.createConversation()
    await api.submitMessage(20, {
      client_message_id: '6f92fcbb-b660-4fba-b07f-8329381da397',
      content: 'The keyboard arrived damaged.',
    })

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
    expect(transport).toHaveBeenNthCalledWith(
      4,
      'http://backend.test/api/customer/conversations/20/messages',
      expect.objectContaining({
        body: {
          client_message_id: '6f92fcbb-b660-4fba-b07f-8329381da397',
          content: 'The keyboard arrived damaged.',
        },
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

  it('uses the selected customer for notification history and read state', async () => {
    const transport = vi.fn()
      .mockResolvedValueOnce({ data: [], links: {}, meta: { unread_count: 0 } })
      .mockResolvedValueOnce({ data: { id: 'notification-1' }, meta: { unread_count: 0 } })
      .mockResolvedValueOnce({ data: { unread_count: 0 } })
    const api = createRefundApiClient(transport, '', 7)

    await api.listNotifications(2)
    await api.markNotificationRead('notification-1')
    await api.markAllNotificationsRead()

    expect(transport).toHaveBeenNthCalledWith(1, '/api/customer/notifications', {
      headers: { 'X-Demo-Customer-Id': '7' },
      query: { page: 2 },
    })
    expect(transport).toHaveBeenNthCalledWith(2, '/api/customer/notifications/notification-1/read', {
      headers: { 'X-Demo-Customer-Id': '7' },
      method: 'PATCH',
    })
    expect(transport).toHaveBeenNthCalledWith(3, '/api/customer/notifications/read-all', {
      headers: { 'X-Demo-Customer-Id': '7' },
      method: 'PATCH',
    })
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

  it('preserves structured validation details for the message form', async () => {
    const details = {
      errors: {
        content: ['The content field is required.'],
      },
    }
    const transport = vi.fn().mockRejectedValue({
      status: 422,
      data: {
        error: {
          code: 'VALIDATION_FAILED',
          message: 'The given data was invalid.',
          details,
        },
      },
    })
    const api = createRefundApiClient(transport, '', 4)

    await expect(api.submitMessage(12, {
      client_message_id: '6f92fcbb-b660-4fba-b07f-8329381da397',
      content: '',
    })).rejects.toEqual(expect.objectContaining({ details }))
  })
})
