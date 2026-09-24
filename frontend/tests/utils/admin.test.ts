import { describe, expect, it } from 'vitest'
import {
  refundRequestFiltersFromQuery,
  refundRequestQuery,
  semanticLabel,
  semanticTone,
} from '~/lib/admin'
import { formatCurrencyCents, formatMachineValue } from '~/lib/formatters'

describe('admin presentation utilities', () => {
  it('normalizes route query filters and pagination', () => {
    expect(refundRequestFiltersFromQuery({
      decision: 'escalated',
      execution_status: 'processing',
      search: '  ORD-1042  ',
      page: '2',
    })).toEqual({
      decision: 'escalated',
      executionStatus: 'processing',
      search: 'ORD-1042',
      page: 2,
    })
  })

  it('drops unsupported machine values and invalid pages', () => {
    expect(refundRequestFiltersFromQuery({
      decision: 'waiting',
      execution_status: 'queued',
      page: '-1',
    })).toEqual({
      decision: null,
      executionStatus: null,
      search: '',
      page: 1,
    })
  })

  it('omits default values from navigation queries', () => {
    expect(refundRequestQuery({
      decision: 'approved',
      executionStatus: null,
      search: '',
      page: 1,
    })).toEqual({ decision: 'approved' })
  })

  it('formats machine values, semantic tones, and cents for people', () => {
    expect(formatMachineValue('changed_mind')).toBe('Changed mind')
    expect(semanticLabel('processed')).toBe('Processed')
    expect(semanticTone('escalated')).toBe('orange')
    expect(formatCurrencyCents(12999)).toBe('$129.99')
  })
})
