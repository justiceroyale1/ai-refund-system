export type ConversationState =
  | 'started'
  | 'identifying_order'
  | 'identifying_item'
  | 'collecting_reason'
  | 'collecting_details'
  | 'evaluating'
  | 'resolved'

export type ConversationStatus = 'active' | 'resolved'
export type RefundDecision = 'approved' | 'denied' | 'escalated'
export type MessageSender = 'customer' | 'assistant' | 'system'
export type ConversationSelectionType =
  | 'order'
  | 'order_item'
  | 'refund_reason'
  | 'open_existing_conversation'
  | 'choose_another_item'

export interface ConversationOrder {
  id: number
  reference: string
}

export interface ConversationOrderItem {
  id: number
  name: string
}

export interface ConversationAction {
  type: ConversationSelectionType
  value: number | string
  label: string
}

export interface ConversationSelection {
  type: ConversationSelectionType
  value: number | string
}

export interface ConversationMessageSubmission {
  client_message_id: string
  content: string
  selection?: ConversationSelection
}

export interface OptimisticConversationMessage {
  clientMessageId: string
  content: string
  createdAt: string
  status: 'sending' | 'failed'
}

export interface ConversationMessage {
  id: number
  client_message_id: string | null
  sender: MessageSender
  content: string
  metadata: Record<string, unknown> | null
  created_at: string | null
}

export interface RefundConversationSummary {
  id: number
  order: ConversationOrder | null
  order_item: ConversationOrderItem | null
  state: ConversationState
  status: ConversationStatus
  reason: string | null
  reason_details: string | null
  decision: RefundDecision | null
  available_actions: ConversationAction[]
  resolved_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface RefundConversation extends RefundConversationSummary {
  messages: ConversationMessage[]
}
