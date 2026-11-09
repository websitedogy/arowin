import { describe, expect, it } from 'vitest'
import formatDateTime from './formatDateTime.js'

describe('formatDateTime', () => {
  it('returns the configured fallback when a value is missing', () => {
    expect(formatDateTime('', 'en-GB', { dateStyle: 'short' })).toBe('—')
    expect(formatDateTime(null, 'en-GB', { dateStyle: 'short' })).toBe('—')
  })

  it('formats ISO timestamps with the supplied locale and options', () => {
    const formatted = formatDateTime('2025-01-15T10:30:00Z', 'en-GB', {
      timeZone: 'UTC',
      dateStyle: 'medium',
      timeStyle: 'short',
    })

    expect(formatted).toContain('2025')
    expect(formatted).toContain('10:30')
  })

  it('falls back to the placeholder for invalid date strings', () => {
    expect(formatDateTime('not-a-date', 'en-GB', { dateStyle: 'short' })).toBe('—')
  })
})
