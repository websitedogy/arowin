import { describe, expect, it } from 'vitest'
import { formatCurrency, formatMonth, formatUsdt } from './formatters.js'

describe('frontend formatters', () => {
  it('formats Indian Rupee values for display', () => {
    const formatted = formatCurrency(1234.5)

    expect(formatted).toContain('₹')
    expect(formatted).toContain('1,234')
  })

  it('formats USDT values without extra rounding noise', () => {
    expect(formatUsdt(42.5)).toBe('42.5 USDT')
    expect(formatUsdt(0)).toBe('0 USDT')
  })

  it('returns the original value when the month is invalid', () => {
    expect(formatMonth('2025-99')).toBe('2025-99')
  })

  it('formats valid month strings as human-readable dates', () => {
    expect(formatMonth('2025-01')).toContain('2025')
  })
})
