import { describe, expect, it } from 'vitest'
import { buildDailyIncomeRows } from './incomeTable'

describe('buildDailyIncomeRows', () => {
  it.each([
    ['2025-02', 28],
    ['2024-02', 29],
    ['2025-04', 30],
    ['2025-01', 31],
  ])('creates the correct number of daily columns for %s', (month, dayCount) => {
    const result = buildDailyIncomeRows(month, { monthlySalary: '3000', days: [] }, null, null, null)

    expect(result.days).toHaveLength(dayCount)
  })

  it('groups salary, bonuses, incentives, and team grants by date with separate currency totals', () => {
    const result = buildDailyIncomeRows(
      '2025-01',
      {
        monthlySalary: '3100',
        days: [
          { date: '2025-01-01', earned: 100, onTimeBonus: 10, activityBonus: 4 },
          { date: '2025-01-02', earned: 100, onTimeBonus: 0, activityBonus: 0 },
        ],
      },
      {
        incentives: [
          { approvedAt: '2025-01-01 10:00:00', incentiveAmount: '5.50' },
          { approvedAt: '2025-02-01 10:00:00', incentiveAmount: '99.00' },
        ],
      },
      { grant: { grantedAt: '2025-01-02 12:00:00', leaderIncentiveAmount: '2.25' } },
      { grant: { grantedAt: '2025-01-02 12:00:00', rewardAmount: '7.00' } },
    )

    expect(result.rows.map((row) => row.total)).toEqual([200, 10, 4, 5.5, 9.25])
    expect(result.rows[0].amounts.slice(0, 2)).toEqual([100, 100])
    expect(result.rows[3].amounts[0]).toBe(5.5)
    expect(result.rows[4].amounts[1]).toBe(9.25)
    expect(result.currencyTotals).toEqual({ INR: 214, USDT: 14.75 })
  })

  it('rejects invalid month values', () => {
    expect(() => buildDailyIncomeRows('2025-13', {}, null, null, null))
      .toThrow('Choose a valid income month.')
  })
})
