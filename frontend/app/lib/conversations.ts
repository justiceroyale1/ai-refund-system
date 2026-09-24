import type {
  ConversationMessageSubmission,
  ConversationSelection,
  RefundConversationSummary,
  RefundDecision,
} from '~/types/conversation'

const decisionLabels: Record<RefundDecision, string> = {
  approved: 'Approved',
  denied: 'Denied',
  escalated: 'Needs review',
}

export function getConversationTitle(conversation: RefundConversationSummary): string {
  return conversation.order?.reference ?? 'New refund request'
}

export function getConversationItemLabel(conversation: RefundConversationSummary): string {
  return conversation.order_item?.name ?? 'Item not selected'
}

export function getConversationStatusLabel(conversation: RefundConversationSummary): string {
  if (conversation.decision) {
    return decisionLabels[conversation.decision]
  }

  return conversation.status === 'active' ? 'In progress' : 'Resolved'
}

export function getConversationStatusTone(
  conversation: RefundConversationSummary,
): 'green' | 'orange' | 'red' | 'blue' | 'neutral' {
  if (conversation.decision === 'approved') {
    return 'green'
  }

  if (conversation.decision === 'denied') {
    return 'red'
  }

  if (conversation.decision === 'escalated') {
    return 'orange'
  }

  return conversation.status === 'active' ? 'blue' : 'neutral'
}

export function selectPreferredConversation(
  conversations: RefundConversationSummary[],
): RefundConversationSummary | null {
  return conversations.find(conversation => conversation.status === 'active')
    ?? conversations[0]
    ?? null
}

export function formatConversationDate(
  timestamp: string | null,
  locale?: string,
): string {
  if (!timestamp) {
    return 'Date unavailable'
  }

  const date = new Date(timestamp)

  if (Number.isNaN(date.getTime())) {
    return 'Date unavailable'
  }

  return new Intl.DateTimeFormat(locale, {
    day: 'numeric',
    month: 'short',
  }).format(date)
}

export function formatConversationDateTime(
  timestamp: string | null,
  locale?: string,
): string {
  if (!timestamp) {
    return 'Date unavailable'
  }

  const date = new Date(timestamp)

  if (Number.isNaN(date.getTime())) {
    return 'Date unavailable'
  }

  return new Intl.DateTimeFormat(locale, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

export function createConversationMessageSubmission(
  content: string,
  selection?: ConversationSelection,
): ConversationMessageSubmission {
  return {
    client_message_id: crypto.randomUUID(),
    content: content.trim(),
    ...(selection ? { selection } : {}),
  }
}

function isSameCalendarDay(first: Date, second: Date): boolean {
  return first.getFullYear() === second.getFullYear()
    && first.getMonth() === second.getMonth()
    && first.getDate() === second.getDate()
}

export function formatMessageTimestamp(
  timestamp: string | null,
  now = new Date(),
  locale?: string,
): string {
  if (!timestamp) {
    return 'Time unavailable'
  }

  const date = new Date(timestamp)

  if (Number.isNaN(date.getTime())) {
    return 'Time unavailable'
  }

  const yesterday = new Date(now)
  yesterday.setDate(yesterday.getDate() - 1)

  const time = new Intl.DateTimeFormat(locale, {
    hour: 'numeric',
    minute: '2-digit',
  }).format(date)

  if (isSameCalendarDay(date, now)) {
    return `Today at ${time}`
  }

  if (isSameCalendarDay(date, yesterday)) {
    return `Yesterday at ${time}`
  }

  const day = new Intl.DateTimeFormat(locale, {
    day: 'numeric',
    month: 'short',
  }).format(date)

  return `${day} at ${time}`
}

export function formatExactTimestamp(timestamp: string | null, locale?: string): string | undefined {
  if (!timestamp) {
    return undefined
  }

  const date = new Date(timestamp)

  if (Number.isNaN(date.getTime())) {
    return undefined
  }

  return new Intl.DateTimeFormat(locale, {
    dateStyle: 'full',
    timeStyle: 'long',
  }).format(date)
}
