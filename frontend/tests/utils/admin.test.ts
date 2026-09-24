import { describe, expect, it } from 'vitest'
import {
  confidenceTone,
  formatBoolean,
  formatExtractedValue,
  refundRequestFiltersFromQuery,
  refundRequestQuery,
  semanticLabel,
  semanticTone,
  signalTone,
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

  it('maps AI confidence, risk signals, booleans, and extracted values', () => {
    expect(confidenceTone(49)).toBe('red')
    expect(confidenceTone(50)).toBe('orange')
    expect(confidenceTone(74)).toBe('orange')
    expect(confidenceTone(75)).toBe('green')
    expect(signalTone(true)).toBe('red')
    expect(signalTone(false)).toBe('green')
    expect(formatBoolean(true)).toBe('Yes')
    expect(formatBoolean(false)).toBe('No')
    expect(formatExtractedValue('changed_mind')).toBe('Changed mind')
    expect(formatExtractedValue(['damaged_item', 'incorrect_item'])).toBe('Damaged item, Incorrect item')
  })

  it('maps policy and workflow statuses to consistent semantic colors', () => {
    expect(semanticTone('passed')).toBe('green')
    expect(semanticTone('review_required')).toBe('orange')
    expect(semanticTone('failed')).toBe('red')
    expect(semanticTone('processing')).toBe('blue')
  })
})
