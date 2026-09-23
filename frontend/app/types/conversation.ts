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

export interface ConversationOrder {
  id: number
  reference: string
}

export interface ConversationOrderItem {
  id: number
  name: string
}

export interface ConversationAction extends Record<string, unknown> {
  type: string
  label: string
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
