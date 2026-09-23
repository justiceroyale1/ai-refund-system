import { describe, expect, it } from 'vitest'
import {
  formatConversationDate,
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
})
