import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import { formatCurrency, formatMonth, formatUsdt } from '../../utils/formatters'
import Icon from './components/Icon'
import { buildDailyIncomeRows } from './incomeTable'

const monthOptions = Array.from({ length: 12 }, (_, offset) => {
  const date = new Date()
  date.setDate(1)
  date.setMonth(date.getMonth() - offset)
  return {
    value: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`,
    label: new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(date),
  }
})

const dailyAmount = new Intl.NumberFormat('en-IN', {
  maximumFractionDigits: 2,
})

export default function EmployeeIncomePage({ user }) {
  const isTeamLeader = user?.role === 'team_leader'
  const roleTitle = isTeamLeader ? 'Team Leader' : 'Telecaller'
  const [selectedMonth, setSelectedMonth] = useState(monthOptions[0].value)
  const [refreshIndex, setRefreshIndex] = useState(0)
  const [incomeResult, setIncomeResult] = useState({
    month: null,
    refreshIndex: -1,
    data: null,
    error: '',
  })
  const isLoading = incomeResult.month !== selectedMonth || incomeResult.refreshIndex !== refreshIndex
  const incomeData = isLoading ? null : incomeResult.data
  const error = isLoading ? '' : incomeResult.error

  useEffect(() => {
    let active = true

    const monthQuery = encodeURIComponent(selectedMonth)
    const requests = [
      apiRequest(`/attendance/history?month=${monthQuery}`),
      apiRequest(`/sales/incentives/me?month=${monthQuery}`),
    ]

    if (isTeamLeader) {
      requests.push(
        apiRequest(`/sales/team-grant-incentives?month=${monthQuery}`),
        apiRequest(`/sales/team-rewards?month=${monthQuery}`),
      )
    }

    Promise.all(requests)
      .then(([attendanceResponse, incentivesResponse, teamGrantResponse, teamRewardResponse]) => {
        if (!active) return
        setIncomeResult({
          month: selectedMonth,
          refreshIndex,
          data: {
            attendance: attendanceResponse.history,
            incentives: incentivesResponse.result,
            teamGrant: teamGrantResponse?.result || null,
            teamReward: teamRewardResponse?.result || null,
          },
          error: '',
        })
      })
      .catch((requestError) => {
        if (!active) return
        setIncomeResult({
          month: selectedMonth,
          refreshIndex,
          data: null,
          error: requestError.message || 'Unable to load income details.',
        })
      })

    return () => {
      active = false
    }
  }, [isTeamLeader, refreshIndex, selectedMonth])

  const { days, rows, currencyTotals } = incomeData
    ? buildDailyIncomeRows(
      selectedMonth,
      incomeData.attendance,
      incomeData.incentives,
      incomeData.teamGrant,
      incomeData.teamReward,
    )
    : { days: [], rows: [], currencyTotals: { INR: 0, USDT: 0 } }

  return (
    <div className="employee-income-view">
      <header className="income-page-header">
        <div className="income-header-info">
          <span className="income-role-badge">
            <Icon name="sparkles" size={14} /> {roleTitle} · Income
          </span>
          <h1>My Income</h1>
          <p>Daily salary, attendance bonuses, activities, incentives, and rewards.</p>
        </div>
        <div className="income-header-actions">
          <div className="income-month-selector">
            <Icon name="calendar" size={16} />
            <select
              id="income-month-filter"
              value={selectedMonth}
              onChange={(event) => setSelectedMonth(event.target.value)}
              aria-label="Select income month"
            >
              {monthOptions.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
          </div>
          <button
            type="button"
            className="income-refresh-btn"
            onClick={() => setRefreshIndex((index) => index + 1)}
            title="Refresh income details"
            disabled={isLoading}
          >
            <Icon name="refresh" size={16} className={isLoading ? 'spin-icon' : ''} />
            <span>Refresh</span>
          </button>
        </div>
      </header>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <section className="income-daily-panel" aria-label={`${formatMonth(selectedMonth)} income`}>
        <div className="income-daily-heading">
          <div>
            <span className="income-daily-eyebrow">{roleTitle} · MONTHLY BREAKDOWN</span>
            <h2>{formatMonth(selectedMonth)}</h2>
          </div>
          <p>— means no income for that day. Salary and attendance are in INR; incentives and rewards are in USDT.</p>
        </div>

        {isLoading ? (
          <p className="income-daily-message" role="status">Loading income details…</p>
        ) : error ? (
          <p className="income-daily-message">Income details could not be loaded.</p>
        ) : (
          <>
            <p className="income-daily-scroll-hint">Swipe horizontally to view all days</p>
            <div className="income-daily-table-wrap" role="region" aria-label="Daily income table" tabIndex="0">
              <table className="income-daily-table">
                <thead>
                  <tr>
                    <th className="income-daily-category">Income Type</th>
                    {days.map((day) => (
                      <th className="income-daily-day" key={day.date}>
                        <span>{day.dayNumber}</span>
                        <small>{day.weekday}</small>
                      </th>
                    ))}
                    <th className="income-daily-total">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.key}>
                      <th scope="row" className="income-daily-category">
                        <span>{row.label}</span>
                        <small>{row.currency}</small>
                      </th>
                      {row.amounts.map((amount, index) => (
                        <td
                          key={days[index].date}
                          title={amount === null
                            ? 'Salary scale not configured'
                            : row.currency === 'INR'
                              ? formatCurrency(amount)
                              : formatUsdt(amount)}
                        >
                          <span className="income-daily-amount">
                              {amount === null || amount === 0 ? '—' : dailyAmount.format(amount)}
                          </span>
                        </td>
                      ))}
                      <td className="income-daily-total">
                        {row.total === null
                          ? '—'
                          : row.currency === 'INR'
                            ? formatCurrency(row.total)
                            : formatUsdt(row.total)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="income-currency-totals" aria-label="Monthly totals by currency">
              <article>
                <span>Monthly Total · INR</span>
                <strong>{formatCurrency(currencyTotals.INR)}</strong>
              </article>
              <article>
                <span>Monthly Total · USDT</span>
                <strong>{formatUsdt(currencyTotals.USDT)}</strong>
              </article>
            </div>
          </>
        )}
      </section>
    </div>
  )
}
