import type { PaginatedResponse } from '~/types/api'

export type RefundDecision = 'approved' | 'denied' | 'escalated'
export type RefundExecutionStatus = 'pending' | 'processing' | 'processed' | 'failed'

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

export type RefundRequestPage = PaginatedResponse<RefundRequestSummary>
