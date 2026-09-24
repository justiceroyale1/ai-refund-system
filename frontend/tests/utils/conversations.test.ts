import { describe, expect, it, vi } from 'vitest'
import {
  createConversationMessageSubmission,
  formatConversationDate,
  formatMessageTimestamp,
  getConversationStatusLabel,
} from '~/lib/conversations'
import type { RefundConversationSummary } from '~/types/conversation'

const conversation: RefundConversationSummary = {
  id: 1,
  order: null,
  order_item: null,
  state: 'started',
  status: 'active',
  reason: null,
  reason_details: null,
  decision: null,
  available_actions: [],
  resolved_at: null,
  created_at: null,
  updated_at: null,
}

describe('conversation presentation helpers', () => {
  it('uses human-readable labels for workflow values', () => {
    expect(getConversationStatusLabel(conversation)).toBe('In progress')
    expect(getConversationStatusLabel({ ...conversation, decision: 'escalated' })).toBe('Needs review')
  })

  it('formats valid timestamps and protects the UI from invalid ones', () => {
    expect(formatConversationDate('2026-09-19T12:00:00.000Z', 'en-US')).toBe('Sep 19')
    expect(formatConversationDate('not-a-date', 'en-US')).toBe('Date unavailable')
    expect(formatConversationDate(null, 'en-US')).toBe('Date unavailable')
  })

  it('formats chat timestamps relative to the current calendar day', () => {
    const now = new Date(2026, 8, 23, 16, 0)

    expect(formatMessageTimestamp(
      new Date(2026, 8, 23, 14, 14).toISOString(),
      now,
      'en-US',
    )).toBe('Today at 2:14 PM')
    expect(formatMessageTimestamp(
      new Date(2026, 8, 22, 16, 32).toISOString(),
      now,
      'en-US',
    )).toBe('Yesterday at 4:32 PM')
    expect(formatMessageTimestamp(
      new Date(2026, 8, 18, 11, 8).toISOString(),
      now,
      'en-US',
    )).toBe('Sep 18 at 11:08 AM')
    expect(formatMessageTimestamp('not-a-date', now, 'en-US')).toBe('Time unavailable')
  })

  it('generates a fresh cryptographic UUID for every new submission', () => {
    const randomUuid = vi.spyOn(crypto, 'randomUUID')
      .mockReturnValueOnce('6f92fcbb-b660-4fba-b07f-8329381da397')
      .mockReturnValueOnce('76f06743-b329-49a6-b060-a2aa77d34492')

    const manual = createConversationMessageSubmission('  Please help me.  ')
    const quickAction = createConversationMessageSubmission('Damaged item', {
      type: 'refund_reason',
      value: 'damaged_item',
    })

    expect(manual).toEqual({
      client_message_id: '6f92fcbb-b660-4fba-b07f-8329381da397',
      content: 'Please help me.',
    })
    expect(quickAction).toEqual({
      client_message_id: '76f06743-b329-49a6-b060-a2aa77d34492',
      content: 'Damaged item',
      selection: { type: 'refund_reason', value: 'damaged_item' },
    })

    randomUuid.mockRestore()
  })
})
