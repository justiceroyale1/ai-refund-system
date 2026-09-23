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
})
