import { useCallback, useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import { formatCurrency, formatMonth, formatUsdt } from '../../utils/formatters'
import formatDateTime from '../../utils/formatDateTime'
import Icon from './components/Icon'

const formatTime = (dateTime) => {
  if (!dateTime) return '—'
  return formatDateTime(dateTime, 'en-IN', {
    hour: 'numeric',
    minute: '2-digit',
    timeZone: 'Asia/Kolkata',
  }, { fallback: '—', assumedOffset: '+05:30' })
}

const formatDateLabel = (dateStr) => {
  if (!dateStr) return '—'
  const date = new Date(dateStr)
  return new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    weekday: 'short',
  }).format(date)
}

const activityMonthOptions = Array.from({ length: 12 }, (_, offset) => {
  const date = new Date()
  date.setDate(1)
  date.setMonth(date.getMonth() - offset)
  return {
    value: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`,
    label: new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(date),
  }
})

export default function EmployeeIncomePage({ user, onNavigate }) {
  const isTeamLeader = user?.role === 'team_leader'
  const roleTitle = isTeamLeader ? 'Team Leader' : 'Telecaller'

  const [selectedMonth, setSelectedMonth] = useState(activityMonthOptions[0].value)
  const [attendance, setAttendance] = useState(null)
  const [history, setHistory] = useState([])
  const [incentives, setIncentives] = useState(null)
  const [teamGrant, setTeamGrant] = useState(null)
  const [teamReward, setTeamReward] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const loadIncomeData = useCallback(async (isRefresh = false) => {
    if (isRefresh) setIsLoading(true)
    try {
      const [attRes, incRes, histRes] = await Promise.all([
        apiRequest('/attendance/me').catch(() => ({ attendance: null })),
        apiRequest('/sales/incentives/me').catch(() => ({ result: { totalEarned: '0.00', incentives: [] } })),
        apiRequest(`/attendance/history?month=${selectedMonth}`).catch(() => ({ history: [] })),
      ])

      setAttendance(attRes.attendance)
      setIncentives(incRes.result)
      setHistory(histRes.history || [])

      if (isTeamLeader) {
        const [grantRes, rewardRes] = await Promise.all([
          apiRequest(`/sales/team-grant-incentives?month=${selectedMonth}`).catch(() => ({ result: null })),
          apiRequest(`/sales/team-rewards?month=${selectedMonth}`).catch(() => ({ result: null })),
        ])
        setTeamGrant(grantRes.result)
        setTeamReward(rewardRes.result)
      }
    } catch (err) {
      setError(err.message || 'Unable to load compensation details.')
    } finally {
      setIsLoading(false)
    }
  }, [isTeamLeader, selectedMonth])

  useEffect(() => {
    loadIncomeData(false)
  }, [loadIncomeData])

  // Computations
  const monthlySalary = Number(attendance?.monthlySalary || 0)
  const estimatedBaseEarnings = Number(attendance?.estimatedEarnings || 0)
  const deductions = Number(attendance?.totalDeductions || 0)
  const onTimeBonus = Number(attendance?.onTimeBonus || 0)
  const activityBonus = Number(attendance?.activityBonus || 0)
  const salesIncentiveEarned = Number(incentives?.totalEarned || 0)
  const tlGrantShare = Number(teamGrant?.leaderIncentiveShareAmount || 0)
  const tlRewardAmount = Number(teamReward?.grantedRewardAmount || 0)

  // Total projected payout in INR and USDT equivalence
  const totalBonuses = onTimeBonus + activityBonus
  const totalPayout = Math.max(0, estimatedBaseEarnings + totalBonuses - deductions)

  return (
    <div className="employee-income-view">
      <header className="income-page-header">
        <div className="income-header-info">
          <span className="income-role-badge">
            <Icon name="sparkles" size={14} /> {roleTitle} Compensation Hub
          </span>
          <h1>My Income & Compensation</h1>
          <p>Real-time salary estimation, sales incentives, bonus rewards, and payroll history.</p>
        </div>
        <div className="income-header-actions">
          <div className="income-month-selector">
            <Icon name="calendar" size={16} />
            <select
              id="income-month-filter"
              value={selectedMonth}
              onChange={(e) => setSelectedMonth(e.target.value)}
              aria-label="Filter compensation by month"
            >
              {activityMonthOptions.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
          </div>
          <button
            type="button"
            className="income-refresh-btn"
            onClick={loadIncomeData}
            title="Refresh income metrics"
            disabled={isLoading}
          >
            <Icon name="refresh" size={16} className={isLoading ? 'spin-icon' : ''} />
            <span>Refresh</span>
          </button>
        </div>
      </header>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      {/* Hero 2027 Payout Projection Card */}
      <section className="income-hero-card">
        <div className="income-hero-glass-mesh" />
        <div className="income-hero-content">
          <div className="income-hero-main">
            <span className="income-hero-label">
              <span className="live-ping-dot" /> ESTIMATED TAKE-HOME PAYROLL · {formatMonth(selectedMonth).toUpperCase()}
            </span>
            <div className="income-hero-amount">
              {isLoading ? (
                <span className="income-loading-skeleton">Loading…</span>
              ) : (
                <strong>{formatCurrency(totalPayout)}</strong>
              )}
              <span className="income-currency-tag">INR Net</span>
            </div>
            <p className="income-hero-note">
              Based on monthly attendance days, earned bonuses, and applied deductions.
            </p>
          </div>

          <div className="income-hero-pills">
            <div className="income-pill">
              <span className="pill-title">Monthly Base</span>
              <span className="pill-value">{formatCurrency(monthlySalary)}</span>
            </div>
            <div className="income-pill accent-sky">
              <span className="pill-title">Unlock Sales Incentives</span>
              <span className="pill-value">{formatUsdt(salesIncentiveEarned)}</span>
            </div>
            {isTeamLeader && (
              <div className="income-pill accent-blue">
                <span className="pill-title">Team Grant & Rewards</span>
                <span className="pill-value">{formatUsdt(tlGrantShare + tlRewardAmount)}</span>
              </div>
            )}
            <div className="income-pill accent-ice">
              <span className="pill-title">Total Deductions</span>
              <span className="pill-value">-{formatCurrency(deductions)}</span>
            </div>
          </div>
        </div>
      </section>

      {/* Grid of Compensation Breakdown */}
      <div className="income-breakdown-grid">
        <article className="income-card">
          <div className="income-card-header">
            <div className="income-card-icon tone-blue">
              <Icon name="wallet" size={20} />
            </div>
            <div>
              <h3>Base Salary Progress</h3>
              <p>Fixed monthly compensation tier</p>
            </div>
          </div>
          <div className="income-card-body">
            <div className="income-metric-row">
              <span>Full Monthly Target:</span>
              <strong>{formatCurrency(monthlySalary)}</strong>
            </div>
            <div className="income-metric-row">
              <span>Earned to Date:</span>
              <strong className="text-blue">{formatCurrency(estimatedBaseEarnings)}</strong>
            </div>
            <div className="income-progress-track">
              <div
                className="income-progress-bar tone-blue"
                style={{
                  width: `${monthlySalary > 0 ? Math.min(100, Math.round((estimatedBaseEarnings / monthlySalary) * 100)) : 0}%`,
                }}
              />
            </div>
            <span className="income-card-footnote">
              Calculated on active office days and approved paid leaves.
            </span>
          </div>
        </article>

        <article className="income-card">
          <div className="income-card-header">
            <div className="income-card-icon tone-sky">
              <Icon name="gift" size={20} />
            </div>
            <div>
              <h3>Package Sales Incentives</h3>
              <p>Approved sales commissions</p>
            </div>
          </div>
          <div className="income-card-body">
            <div className="income-metric-row">
              <span>Approved Incentives:</span>
              <strong className="text-sky">{formatUsdt(salesIncentiveEarned)}</strong>
            </div>
            <div className="income-metric-row">
              <span>Approved Deals:</span>
              <strong>{incentives?.incentives?.length || 0} packages</strong>
            </div>
            <button
              type="button"
              className="income-card-action-btn"
              onClick={() => onNavigate(isTeamLeader ? '/team-leader/incentives' : '/telecaller/incentives')}
            >
              <span>View Approved Incentives</span>
              <Icon name="arrow" size={15} />
            </button>
          </div>
        </article>

        <article className="income-card">
          <div className="income-card-header">
            <div className="income-card-icon tone-navy">
              <Icon name="award" size={20} />
            </div>
            <div>
              <h3>Performance Bonuses</h3>
              <p>On-time attendance & activities</p>
            </div>
          </div>
          <div className="income-card-body">
            <div className="income-metric-row">
              <span>On-Time Arrival Bonus:</span>
              <strong className="text-navy">{formatCurrency(onTimeBonus)}</strong>
            </div>
            <div className="income-metric-row">
              <span>Social Activity Bonus:</span>
              <strong className="text-navy">{formatCurrency(activityBonus)}</strong>
            </div>
            <div className="income-metric-row total-row">
              <span>Total Extra Rewards:</span>
              <strong>+{formatCurrency(totalBonuses)}</strong>
            </div>
          </div>
        </article>

        <article className="income-card">
          <div className="income-card-header">
            <div className="income-card-icon tone-ice">
              <Icon name="clock" size={20} />
            </div>
            <div>
              <h3>Deductions & Adjustments</h3>
              <p>Penalties and unexcused leaves</p>
            </div>
          </div>
          <div className="income-card-body">
            <div className="income-metric-row">
              <span>Late Check-in Penalties:</span>
              <strong className="text-ice">-{formatCurrency(deductions)}</strong>
            </div>
            <div className="income-metric-row">
              <span>Unpaid Leaves:</span>
              <strong>{attendance?.unpaidLeaves || 0} days</strong>
            </div>
            <div className="income-metric-row">
              <span>Approved Paid Leaves:</span>
              <strong className="text-sky">{attendance?.approvedLeaves || 0} days</strong>
            </div>
          </div>
        </article>
      </div>

      {/* Attendance & Shift Breakdown */}
      <section className="income-history-section">
        <div className="income-history-header">
          <div className="history-title-wrap">
            <Icon name="calendar" size={20} />
            <div>
              <h2>Attendance & Duty Record</h2>
              <p>Daily check-in logs and salary accrual for {formatMonth(selectedMonth)}</p>
            </div>
          </div>
          <span className="history-count-badge">{history.length} logged days</span>
        </div>

        <div className="income-table-wrapper">
          <table className="income-history-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Check-In</th>
                <th>Check-Out</th>
                <th>Status</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr>
                  <td colSpan="5" className="table-status-message">Loading attendance logs…</td>
                </tr>
              ) : history.length ? (
                history.map((record) => {
                  const isLate = Boolean(record.isLate)
                  const status = record.status || (record.checkInTime ? 'Present' : 'Not marked')
                  return (
                    <tr key={record.date || record.id}>
                      <td>
                        <strong>{formatDateLabel(record.date)}</strong>
                      </td>
                      <td>
                        <span className="time-code">{formatTime(record.checkInTime)}</span>
                      </td>
                      <td>
                        <span className="time-code">{formatTime(record.checkOutTime)}</span>
                      </td>
                      <td>
                        <span className={`status-pill ${isLate ? 'status-late' : 'status-present'}`}>
                          <span className="status-dot" />
                          {isLate ? 'Late Arrival' : status}
                        </span>
                      </td>
                      <td>
                        <span className="remark-text">
                          {record.notes || (isLate ? 'Penalty applied' : 'Regular shift')}
                        </span>
                      </td>
                    </tr>
                  )
                })
              ) : (
                <tr>
                  <td colSpan="5" className="table-status-message">
                    No attendance records found for this period.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  )
}
