import type { PaginatedResponse } from '~/types/api'
import type {
  ConversationState,
  ConversationStatus,
  MessageSender,
} from '~/types/conversation'

export type RefundDecision = 'approved' | 'denied' | 'escalated'
export type RefundExecutionStatus = 'pending' | 'processing' | 'processed' | 'failed'
export type RefundReviewDecision = Exclude<RefundDecision, 'escalated'>

export interface AdminUser {
  id: number
  name: string
  email: string
}

export interface AdminDashboardMetrics {
  approved_request_count: number
  denied_request_count: number
  escalated_request_count: number
  pending_refund_count: number
  failed_refund_count: number
}

export interface RefundRequestSummary {
  id: number
  customer: {
    id: number
    name: string
    email: string
  } | null
  order: {
    id: number
    reference: string
  } | null
  order_item: {
    id: number
    name: string
  } | null
  reason: string
  amount_cents: number
  initial_decision: RefundDecision
  decision: RefundDecision | null
  decision_source: string
  decision_code: string
  execution_status: RefundExecutionStatus | null
  decided_at: string | null
  created_at: string
}

export interface RefundRequestFilters {
  decision: RefundDecision | null
  executionStatus: RefundExecutionStatus | null
  search: string
  page: number
}

export interface RefundPolicyCheck {
  code: string
  result: string
  message: string
}

export interface AdminConversationMessage {
  id: number
  sender: MessageSender
  content: string
  metadata: Record<string, unknown> | null
  created_at: string | null
}

export interface RefundRequestDetail {
  id: number
  customer: {
    id: number
    name: string
    email: string
  } | null
  order: {
    id: number
    reference: string
    status: string
    ordered_at: string | null
    delivered_at: string | null
  } | null
  order_item: {
    id: number
    sku: string
    name: string
    quantity: number
    unit_price_cents: number
    final_sale: boolean
  } | null
  reason: string
  reason_details: string | null
  amount_cents: number
  policy_checks: RefundPolicyCheck[]
  initial_decision: RefundDecision
  decision: RefundDecision | null
  decision_source: string
  decision_code: string
  reviewer: AdminUser | null
  review_note: string | null
  conversation: {
    id: number
    state: ConversationState
    status: ConversationStatus
    messages: AdminConversationMessage[]
    resolved_at: string | null
    created_at: string | null
    updated_at: string | null
  } | null
  latest_ai_analysis: {
    id: number
    conversation_message_id: number
    confidence: number
    prompt_injection_detected: boolean
    conflicting_information: boolean
    extracted_data: Record<string, unknown>
    created_at: string
  } | null
  refund: {
    id: number
    amount_cents: number
    status: RefundExecutionStatus
    processor: string
    processor_reference: string | null
    attempts: number
    last_error: string | null
    next_retry_at: string | null
    processed_at: string | null
    created_at: string
    updated_at: string
  } | null
  audit_timeline: Array<{
    id: number
    actor_type: string
    actor_id: number | null
    subject_type: string
    subject_id: number
    event: string
    created_at: string
  }>
  decided_at: string | null
  created_at: string
  updated_at: string
}

export interface RefundReviewSubmission {
  decision: RefundReviewDecision
  review_note: string | null
}

export type RefundRequestPage = PaginatedResponse<RefundRequestSummary>
