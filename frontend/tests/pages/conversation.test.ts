import { mountSuspended } from '@nuxt/test-utils/runtime'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ConversationPage from '~/pages/support/conversations/[id].vue'
import { useConversationStore } from '~/stores/conversations'
import { useCustomerStore } from '~/stores/customer'
import type { RefundConversation } from '~/types/conversation'

function activeConversation(): RefundConversation {
  return {
    id: 7,
    order: { id: 12, reference: 'ORD-1105' },
    order_item: null,
    state: 'identifying_item',
    status: 'active',
    reason: null,
    reason_details: null,
    decision: null,
    available_actions: [{
      type: 'order_item',
      value: 24,
      label: 'Adjustable Desk Lamp',
    }],
    resolved_at: null,
    created_at: '2026-09-20T10:00:00.000Z',
    updated_at: '2026-09-20T10:02:00.000Z',
    messages: [
      {
        id: 1,
        client_message_id: '6f92fcbb-b660-4fba-b07f-8329381da397',
        sender: 'customer',
        content: 'I need help with a recent delivery.',
        metadata: null,
        created_at: '2026-09-20T10:00:00.000Z',
      },
      {
        id: 2,
        client_message_id: null,
        sender: 'assistant',
        content: 'Which item from this order would you like refunded?',
        metadata: null,
        created_at: '2026-09-20T10:02:00.000Z',
      },
    ],
  }
}

describe('/support/conversations/:id', () => {
  let pinia: ReturnType<typeof createPinia>

  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)

    const customerStore = useCustomerStore()
    customerStore.customers = [{
      id: 1,
      name: 'James Munroe',
      email: 'james.munroe@example.test',
    }]
    customerStore.selectedCustomerId = 1
    customerStore.hasLoaded = true

    const conversationStore = useConversationStore()
    conversationStore.hasLoadedHistory = true
    vi.spyOn(conversationStore, 'loadConversation').mockImplementation(async () => {
      conversationStore.currentConversation = activeConversation()
    })
  })

  it('renders the transcript, quick actions, and composer for an active conversation', async () => {
    const wrapper = await mountSuspended(ConversationPage, {
      route: '/support/conversations/7',
      global: { plugins: [pinia] },
    })

    expect(wrapper.findAll('article')).toHaveLength(2)
    expect(wrapper.text()).toContain('I need help with a recent delivery.')
    expect(wrapper.text()).toContain('Which item from this order would you like refunded?')
    expect(wrapper.get('[aria-label="Suggested replies"]').text()).toContain('Adjustable Desk Lamp')

    const composer = wrapper.get<HTMLTextAreaElement>('#conversation-message')
    expect(composer.attributes('disabled')).toBeUndefined()

    await composer.setValue('The lamp shade is cracked.')

    expect(wrapper.get('button[aria-label="Send message"]').attributes('disabled')).toBeUndefined()
  })
})
