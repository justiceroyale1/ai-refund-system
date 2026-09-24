import { formatMachineValue } from '~/lib/formatters'
import type {
  RefundDecision,
  RefundExecutionStatus,
  RefundRequestFilters,
} from '~/types/admin'

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

export function semanticTone(value: string | null): string {
  switch (value) {
    case 'approved':
    case 'processed':
      return 'green'
    case 'denied':
    case 'failed':
      return 'red'
    case 'escalated':
    case 'pending':
      return 'orange'
    case 'processing':
      return 'blue'
    default:
      return 'neutral'
  }
}
