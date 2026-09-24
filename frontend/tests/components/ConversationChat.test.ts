import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import ConversationComposer from '~/components/customer/ConversationComposer.vue'
import ConversationMessageBubble from '~/components/customer/ConversationMessageBubble.vue'
import ConversationQuickActions from '~/components/customer/ConversationQuickActions.vue'
import ConversationTranscript from '~/components/customer/ConversationTranscript.vue'
import type { ConversationMessage } from '~/types/conversation'

describe('customer conversation chat', () => {
  it('presents customer, assistant, and system messages with friendly metadata', async () => {
    const customer = await mountSuspended(ConversationMessageBubble, {
      props: {
        sender: 'customer',
        content: 'The keyboard arrived with two broken keys.',
        createdAt: '2026-09-18T10:08:00.000Z',
        customerName: 'James Munroe',
      },
    })
    const assistant = await mountSuspended(ConversationMessageBubble, {
      props: {
        sender: 'assistant',
        content: 'Which item was damaged?',
        createdAt: '2026-09-18T10:09:00.000Z',
        customerName: 'James Munroe',
      },
    })
    const system = await mountSuspended(ConversationMessageBubble, {
      props: {
        sender: 'system',
        content: 'This request is complete.',
        createdAt: '2026-09-18T10:10:00.000Z',
        customerName: 'James Munroe',
      },
    })

    expect(customer.text()).toContain('James Munroe')
    expect(customer.text()).toContain('The keyboard arrived with two broken keys.')
    expect(customer.text()).not.toContain('2026-09-18T10:08:00.000Z')
    expect(customer.get('time').attributes('datetime')).toBe('2026-09-18T10:08:00.000Z')
    expect(assistant.text()).toContain('Refund Assistant')
    expect(system.text()).toContain('Status update')
  })

  it('emits structured quick actions using human-readable labels', async () => {
    const action = {
      type: 'open_existing_conversation' as const,
      value: 18,
      label: 'Open existing conversation',
    }
    const wrapper = await mountSuspended(ConversationQuickActions, {
      props: {
        actions: [
          action,
          {
            type: 'choose_another_item',
            value: 24,
            label: 'Choose another item',
          },
        ],
      },
    })

    await wrapper.get('button').trigger('click')

    expect(wrapper.text()).toContain('Open existing conversation')
    expect(wrapper.text()).toContain('Choose another item')
    expect(wrapper.text()).not.toContain('open_existing_conversation')
    expect(wrapper.emitted('select')?.[0]).toEqual([action])
  })

  it('submits manual text with Enter and disables duplicate submission while sending', async () => {
    const wrapper = await mountSuspended(ConversationComposer, {
      props: {
        modelValue: 'The keyboard arrived damaged.',
      },
    })

    await wrapper.get('textarea').trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('submit')?.[0]).toEqual(['The keyboard arrived damaged.'])

    await wrapper.setProps({ submitting: true })
    expect(wrapper.get('textarea').attributes()).toHaveProperty('disabled')
    expect(wrapper.get('button').attributes()).toHaveProperty('disabled')

    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('submit')).toHaveLength(1)
  })

  it('keeps Shift+Enter available for multi-line messages', async () => {
    const wrapper = await mountSuspended(ConversationComposer, {
      props: {
        modelValue: 'First line',
      },
    })

    await wrapper.get('textarea').trigger('keydown', { key: 'Enter', shiftKey: true })

    expect(wrapper.emitted('submit')).toBeUndefined()
  })

  it('renders a complete seeded refund transcript in chronological order', async () => {
    const contents = [
      'I would like to report a problem with a delivered item.',
      'Which delivered order would you like help with?',
      'ORD-1105',
      'Which item from this order would you like refunded?',
      'Adjustable Desk Lamp',
      'What is the reason for your refund request?',
      'Damaged item',
      'Please describe the damage.',
      'The desk lamp shade was cracked when the package was opened.',
      'Your refund request has been approved.',
      'Refund processing completed successfully.',
    ]
    const messages: ConversationMessage[] = contents.map((content, index) => ({
      id: index + 1,
      client_message_id: index % 2 === 0 ? crypto.randomUUID() : null,
      sender: index === contents.length - 1
        ? 'system'
        : index % 2 === 0 ? 'customer' : 'assistant',
      content,
      metadata: null,
      created_at: new Date(Date.UTC(2026, 8, 18, 10, index * 2)).toISOString(),
    }))
    const wrapper = await mountSuspended(ConversationTranscript, {
      props: {
        messages,
        optimisticMessage: null,
        customerName: 'James Munroe',
      },
    })

    expect(wrapper.findAll('article')).toHaveLength(11)

    const transcriptText = wrapper.text()
    let previousPosition = -1

    for (const content of contents) {
      const position = transcriptText.indexOf(content)
      expect(position).toBeGreaterThan(previousPosition)
      previousPosition = position
    }

    expect(wrapper.text()).toContain('James Munroe')
    expect(wrapper.text()).toContain('Refund Assistant')
    expect(wrapper.text()).toContain('Status update')
    expect(wrapper.findAll('time')).toHaveLength(11)
  })
})
