import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiClientError } from '~/composables/useRefundApi'
import { useConversationStore } from '~/stores/conversations'
import { useCustomerStore } from '~/stores/customer'
import type { RefundConversation, RefundConversationSummary } from '~/types/conversation'

const apiMocks = vi.hoisted(() => ({
  listDemoCustomers: vi.fn(),
  listConversations: vi.fn(),
  getConversation: vi.fn(),
  createConversation: vi.fn(),
  submitMessage: vi.fn(),
}))

vi.mock('~/composables/useRefundApi', async (importOriginal) => {
  const original = await importOriginal<typeof import('~/composables/useRefundApi')>()

  return {
    ...original,
    useRefundApi: () => apiMocks,
  }
})

function conversation(
  id: number,
  status: 'active' | 'resolved' = 'active',
): RefundConversationSummary {
  return {
    id,
    order: { id, reference: `ORD-${id}` },
    order_item: { id, name: `Item ${id}` },
    state: status === 'active' ? 'collecting_reason' : 'resolved',
    status,
    reason: null,
    reason_details: null,
    decision: status === 'resolved' ? 'approved' : null,
    available_actions: [],
    resolved_at: status === 'resolved' ? '2026-09-20T12:00:00.000Z' : null,
    created_at: '2026-09-20T10:00:00.000Z',
    updated_at: `2026-09-${id.toString().padStart(2, '0')}T12:00:00.000Z`,
  }
}

function paginated(data: RefundConversationSummary[]) {
  return {
    data,
    links: { first: '', last: '', prev: null, next: null },
    meta: {
      current_page: 1,
      from: data.length ? 1 : null,
      last_page: 1,
      path: '',
      per_page: 15,
      to: data.length || null,
      total: data.length,
    },
  }
}

function detailedConversation(
  id: number,
  status: 'active' | 'resolved' = 'active',
): RefundConversation {
  return {
    ...conversation(id, status),
    messages: [],
  }
}

describe('conversation store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    useCookie<string | null>('demo-customer-id').value = null

    const customerStore = useCustomerStore()
    customerStore.customers = [
      { id: 1, name: 'First Customer', email: 'first@example.test' },
      { id: 2, name: 'Second Customer', email: 'second@example.test' },
    ]
    customerStore.selectedCustomerId = 1
    customerStore.hasLoaded = true
  })

  it('clears all customer-scoped state before refetching after a switch', async () => {
    let resolveRequest: ((value: ReturnType<typeof paginated>) => void) | undefined
    apiMocks.listConversations.mockReturnValue(new Promise((resolve) => {
      resolveRequest = resolve
    }))
    const store = useConversationStore()
    store.history = [conversation(1)]
    store.currentConversation = { ...conversation(1), messages: [] } as RefundConversation
    store.hasLoadedHistory = true

    const switching = store.switchCustomer(2)

    expect(useCustomerStore().selectedCustomerId).toBe(2)
    expect(store.history).toEqual([])
    expect(store.currentConversation).toBeNull()
    expect(store.hasLoadedHistory).toBe(false)

    resolveRequest?.(paginated([conversation(2)]))
    await switching

    expect(store.history.map(item => item.id)).toEqual([2])
    expect(store.hasLoadedHistory).toBe(true)
  })

  it('prefers the newest active conversation over newer resolved history', () => {
    const store = useConversationStore()
    store.history = [conversation(9, 'resolved'), conversation(7), conversation(5)]

    expect(store.preferredConversation?.id).toBe(7)
  })

  it('falls back to the newest resolved conversation when none is active', () => {
    const store = useConversationStore()
    store.history = [conversation(9, 'resolved'), conversation(4, 'resolved')]

    expect(store.preferredConversation?.id).toBe(9)
  })

  it('maps missing and unowned conversations to the same generic not-found state', async () => {
    apiMocks.getConversation.mockRejectedValue(
      new ApiClientError('The requested resource was not found.', 404, 'RESOURCE_NOT_FOUND'),
    )
    const store = useConversationStore()

    await store.loadConversation('999')

    expect(store.conversationNotFound).toBe(true)
    expect(store.conversationError).toBe('This conversation could not be found.')
    expect(store.currentConversation).toBeNull()
  })

  it('appends the next page without replacing recent history', async () => {
    apiMocks.listConversations.mockResolvedValue({
      ...paginated([conversation(2, 'resolved')]),
      meta: {
        ...paginated([]).meta,
        current_page: 2,
        last_page: 2,
        total: 2,
      },
    })
    const store = useConversationStore()
    store.history = [conversation(1)]
    store.currentPage = 1
    store.lastPage = 2

    await store.loadMoreHistory()

    expect(apiMocks.listConversations).toHaveBeenCalledWith(2)
    expect(store.history.map(item => item.id)).toEqual([1, 2])
  })

  it('shows an optimistic customer message while a submission is in flight', async () => {
    let resolveRequest: ((value: RefundConversation) => void) | undefined
    apiMocks.submitMessage.mockReturnValue(new Promise((resolve) => {
      resolveRequest = resolve
    }))
    const store = useConversationStore()
    store.currentConversation = detailedConversation(7)
    const submission = {
      client_message_id: '6f92fcbb-b660-4fba-b07f-8329381da397',
      content: 'The keyboard arrived damaged.',
    }

    const submitting = store.submitMessage(7, submission)

    expect(store.isSubmittingMessage).toBe(true)
    expect(store.optimisticMessage).toEqual(expect.objectContaining({
      clientMessageId: submission.client_message_id,
      content: submission.content,
      status: 'sending',
    }))

    resolveRequest?.(detailedConversation(7))
    await submitting

    expect(store.isSubmittingMessage).toBe(false)
    expect(store.optimisticMessage).toBeNull()
  })

  it('retries a recoverable failure with the original UUID and payload', async () => {
    const updatedConversation = detailedConversation(7)
    apiMocks.submitMessage
      .mockRejectedValueOnce(new ApiClientError(
        'The AI analysis provider is temporarily unavailable.',
        503,
        'SERVICE_UNAVAILABLE',
      ))
      .mockResolvedValueOnce(updatedConversation)
    const store = useConversationStore()
    store.currentConversation = detailedConversation(7)
    const submission = {
      client_message_id: '99999999-9999-4999-8999-999999999999',
      content: 'I need help with a refund.',
    }

    await store.submitMessage(7, submission)

    expect(store.messageErrorKind).toBe('retryable')
    expect(store.retrySubmission).toEqual(submission)
    expect(store.optimisticMessage?.status).toBe('failed')

    await store.retryMessage(7)

    expect(apiMocks.submitMessage).toHaveBeenNthCalledWith(1, 7, submission)
    expect(apiMocks.submitMessage).toHaveBeenNthCalledWith(2, 7, submission)
    expect(store.currentConversation).toEqual(updatedConversation)
    expect(store.retrySubmission).toBeNull()
    expect(store.optimisticMessage).toBeNull()
  })

  it('shows the first server validation message without offering a retry', async () => {
    apiMocks.submitMessage.mockRejectedValue(new ApiClientError(
      'The given data was invalid.',
      422,
      'VALIDATION_FAILED',
      { errors: { content: ['The content field must not be greater than 5000 characters.'] } },
    ))
    const store = useConversationStore()
    store.currentConversation = detailedConversation(7)

    await store.submitMessage(7, {
      client_message_id: '99999999-9999-4999-8999-999999999999',
      content: 'Message',
    })

    expect(store.messageError).toBe('The content field must not be greater than 5000 characters.')
    expect(store.messageErrorKind).toBe('validation')
    expect(store.retrySubmission).toBeNull()
    expect(store.optimisticMessage).toBeNull()
  })

  it('refreshes the transcript after a resolved-conversation conflict', async () => {
    const resolvedConversation = detailedConversation(7, 'resolved')
    apiMocks.submitMessage.mockRejectedValue(new ApiClientError(
      'This refund conversation has already been resolved.',
      409,
      'CONVERSATION_ALREADY_RESOLVED',
    ))
    apiMocks.getConversation.mockResolvedValue(resolvedConversation)
    const store = useConversationStore()
    store.currentConversation = detailedConversation(7)

    await store.submitMessage(7, {
      client_message_id: '99999999-9999-4999-8999-999999999999',
      content: 'One more message.',
    })

    expect(apiMocks.getConversation).toHaveBeenCalledWith(7)
    expect(store.currentConversation?.status).toBe('resolved')
    expect(store.messageErrorKind).toBe('resolved')
    expect(store.retrySubmission).toBeNull()
  })

  it('returns the accepted existing-conversation destination for duplicate navigation', async () => {
    apiMocks.submitMessage.mockResolvedValue(detailedConversation(7, 'resolved'))
    const store = useConversationStore()
    store.currentConversation = detailedConversation(7)
    const submission = {
      client_message_id: 'b2d1c9ea-6ee3-4c36-954f-59981db965ee',
      content: 'Open existing conversation',
      selection: {
        type: 'open_existing_conversation' as const,
        value: 11,
      },
    }

    const result = await store.submitMessage(7, submission)

    expect(result?.redirectConversationId).toBe(11)
    expect(apiMocks.submitMessage).toHaveBeenCalledWith(7, submission)
  })

  it('keeps the current conversation open when the customer chooses another item', async () => {
    const updatedConversation = {
      ...detailedConversation(7),
      available_actions: [{
        type: 'order_item' as const,
        value: 25,
        label: 'Wireless Mouse',
      }],
    }
    apiMocks.submitMessage.mockResolvedValue(updatedConversation)
    const store = useConversationStore()
    store.currentConversation = detailedConversation(7)

    const result = await store.submitMessage(7, {
      client_message_id: 'b2d1c9ea-6ee3-4c36-954f-59981db965ee',
      content: 'Choose another item',
      selection: {
        type: 'choose_another_item',
        value: 24,
      },
    })

    expect(result?.redirectConversationId).toBeNull()
    expect(store.currentConversation?.available_actions).toEqual(updatedConversation.available_actions)
  })
})
