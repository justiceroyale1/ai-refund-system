import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SupportPage from '~/pages/support/index.vue'
import { useConversationStore } from '~/stores/conversations'
import { useCustomerStore } from '~/stores/customer'
import type { RefundConversationSummary } from '~/types/conversation'

const navigateToMock = vi.hoisted(() => vi.fn())

mockNuxtImport('navigateTo', () => navigateToMock)

function summary(id: number, status: 'active' | 'resolved'): RefundConversationSummary {
  return {
    id,
    order: null,
    order_item: null,
    state: status === 'active' ? 'started' : 'resolved',
    status,
    reason: null,
    reason_details: null,
    decision: null,
    available_actions: [],
    resolved_at: null,
    created_at: '2026-09-20T10:00:00.000Z',
    updated_at: '2026-09-20T10:00:00.000Z',
  }
}

describe('/support route selection', () => {
  let pinia: ReturnType<typeof createPinia>

  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
    navigateToMock.mockReset()

    const customerStore = useCustomerStore()
    customerStore.customers = [{ id: 1, name: 'Demo Customer', email: 'demo@example.test' }]
    customerStore.selectedCustomerId = 1
    customerStore.hasLoaded = true
  })

  it('opens the newest active conversation before a newer resolved one', async () => {
    const conversationStore = useConversationStore()
    conversationStore.history = [summary(12, 'resolved'), summary(8, 'active')]
    conversationStore.hasLoadedHistory = true

    await mountSuspended(SupportPage, {
      global: { plugins: [pinia] },
    })

    expect(navigateToMock).toHaveBeenCalledWith('/support/conversations/8', { replace: true })
  })

  it('opens the newest resolved conversation when there is no active one', async () => {
    const conversationStore = useConversationStore()
    conversationStore.history = [summary(12, 'resolved'), summary(8, 'resolved')]
    conversationStore.hasLoadedHistory = true

    await mountSuspended(SupportPage, {
      global: { plugins: [pinia] },
    })

    expect(navigateToMock).toHaveBeenCalledWith('/support/conversations/12', { replace: true })
  })

  it('renders the new-request state when the customer has no history', async () => {
    const conversationStore = useConversationStore()
    conversationStore.hasLoadedHistory = true

    const wrapper = await mountSuspended(SupportPage, {
      global: { plugins: [pinia] },
    })

    expect(navigateToMock).not.toHaveBeenCalled()
    expect(wrapper.get('h1').text()).toBe('How can we help?')
    expect(wrapper.text()).toContain('Start a refund request')
  })
})
