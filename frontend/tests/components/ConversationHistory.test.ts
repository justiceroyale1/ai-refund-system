import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import ConversationHistory from '~/components/customer/ConversationHistory.vue'
import type { RefundConversationSummary } from '~/types/conversation'

const history: RefundConversationSummary[] = [{
  id: 42,
  order: { id: 10, reference: 'ORD-1042' },
  order_item: { id: 20, name: 'Mechanical Keyboard' },
  state: 'resolved',
  status: 'resolved',
  reason: 'damaged_item',
  reason_details: 'Two broken keys.',
  decision: 'approved',
  available_actions: [],
  resolved_at: '2026-09-19T12:00:00.000Z',
  created_at: '2026-09-19T11:00:00.000Z',
  updated_at: '2026-09-19T12:00:00.000Z',
}]

describe('conversation history', () => {
  it('shows the empty state when the customer has no history', async () => {
    const wrapper = await mountSuspended(ConversationHistory, {
      props: {
        conversations: [],
        activeConversationId: null,
        loading: false,
        error: null,
        hasMore: false,
      },
    })

    expect(wrapper.text()).toContain('No conversations yet')
  })

  it('shows useful summaries and marks the active route', async () => {
    const wrapper = await mountSuspended(ConversationHistory, {
      props: {
        conversations: history,
        activeConversationId: '42',
        loading: false,
        error: null,
        hasMore: false,
      },
    })

    expect(wrapper.text()).toContain('ORD-1042')
    expect(wrapper.text()).toContain('Mechanical Keyboard')
    expect(wrapper.text()).toContain('Approved')
    expect(wrapper.get('[aria-current="page"]').attributes('href')).toBe('/support/conversations/42')
  })

  it('renders a recoverable error state', async () => {
    const wrapper = await mountSuspended(ConversationHistory, {
      props: {
        conversations: [],
        activeConversationId: null,
        loading: false,
        error: 'History unavailable.',
        hasMore: false,
      },
    })

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('retry')).toHaveLength(1)
  })
})
