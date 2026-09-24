import { formatMachineValue } from '~/lib/formatters'
import type {
  RefundDecision,
  RefundExecutionStatus,
  RefundRequestFilters,
} from '~/types/admin'

export type SemanticTone = 'blue' | 'green' | 'neutral' | 'orange' | 'red'

export const refundDecisionOptions: ReadonlyArray<{ value: RefundDecision, label: string }> = [
  { value: 'approved', label: 'Approved' },
  { value: 'denied', label: 'Denied' },
  { value: 'escalated', label: 'Escalated' },
]

export const refundExecutionStatusOptions: ReadonlyArray<{
  value: RefundExecutionStatus
  label: string
}> = [
  { value: 'pending', label: 'Pending' },
  { value: 'processing', label: 'Processing' },
  { value: 'processed', label: 'Processed' },
  { value: 'failed', label: 'Failed' },
]

const decisionValues = new Set(refundDecisionOptions.map(option => option.value))
const executionStatusValues = new Set(refundExecutionStatusOptions.map(option => option.value))

function singleQueryValue(value: unknown): string | null {
  return typeof value === 'string' ? value : null
}

export function refundRequestFiltersFromQuery(query: Record<string, unknown>): RefundRequestFilters {
  const decision = singleQueryValue(query.decision)
  const executionStatus = singleQueryValue(query.execution_status)
  const search = singleQueryValue(query.search)?.trim() ?? ''
  const parsedPage = Number(singleQueryValue(query.page) ?? '1')

  return {
    decision: decisionValues.has(decision as RefundDecision)
      ? decision as RefundDecision
      : null,
    executionStatus: executionStatusValues.has(executionStatus as RefundExecutionStatus)
      ? executionStatus as RefundExecutionStatus
      : null,
    search: search.slice(0, 255),
    page: Number.isInteger(parsedPage) && parsedPage > 0 ? parsedPage : 1,
  }
}

export function refundRequestQuery(filters: RefundRequestFilters): Record<string, string> {
  return {
    ...(filters.decision ? { decision: filters.decision } : {}),
    ...(filters.executionStatus ? { execution_status: filters.executionStatus } : {}),
    ...(filters.search ? { search: filters.search } : {}),
    ...(filters.page > 1 ? { page: String(filters.page) } : {}),
  }
}

export function semanticLabel(value: string | null): string {
  return value === null ? 'Not available' : formatMachineValue(value)
}

export function semanticTone(value: string | null): SemanticTone {
  switch (value) {
    case 'approved':
    case 'delivered':
    case 'passed':
    case 'processed':
    case 'resolved':
      return 'green'
    case 'denied':
    case 'failed':
      return 'red'
    case 'escalated':
    case 'manual_review_required':
    case 'pending':
    case 'review_required':
      return 'orange'
    case 'active':
    case 'evaluating':
    case 'missing':
    case 'processing':
      return 'blue'
    default:
      return 'neutral'
  }
}

export function confidenceTone(confidence: number): SemanticTone {
  if (confidence < 50) {
    return 'red'
  }

  if (confidence < 75) {
    return 'orange'
  }

  return 'green'
}

export function signalTone(detected: boolean): SemanticTone {
  return detected ? 'red' : 'green'
}

export function formatBoolean(value: boolean): string {
  return value ? 'Yes' : 'No'
}

export function formatExtractedValue(value: unknown): string {
  if (typeof value === 'boolean') {
    return formatBoolean(value)
  }

  if (typeof value === 'string') {
    return formatMachineValue(value)
  }

  if (value === null || value === undefined || value === '') {
    return 'Not available'
  }

  if (Array.isArray(value)) {
    return value.map(item => formatExtractedValue(item)).join(', ')
  }

  if (typeof value === 'object') {
    return JSON.stringify(value)
  }

  return String(value)
}
