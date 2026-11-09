import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import { formatUsdt } from '../../utils/formatters'

function currentMonth() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

function monthOptions() {
  const current = new Date()
  current.setDate(1)
  return Array.from({ length: 12 }, (_, index) => {
    const month = new Date(current.getFullYear(), current.getMonth() - index, 1)
    const value = `${month.getFullYear()}-${String(month.getMonth() + 1).padStart(2, '0')}`
    return {
      value,
      label: new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(month),
    }
  })
}

function displayMonth(value) {
  return new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' })
    .format(new Date(`${value}-01T00:00:00`))
}

function displayDate(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Kolkata',
  }).format(new Date(value.replace(' ', 'T') + (value.includes('T') ? '' : '+05:30')))
}

export default function TeamRewardsPage({ grantsOnly = false }) {
  const [month, setMonth] = useState(currentMonth)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let active = true
    apiRequest(`/sales/team-rewards?month=${encodeURIComponent(month)}`)
      .then((response) => {
        if (!active) return
        setResult(response.result)
        setError('')
      })
      .catch((requestError) => {
        if (active) setError(requestError.message)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })

    return () => { active = false }
  }, [month])

  const progress = Math.min(100, result?.progressPercent || 0)
  const targetIsSet = result?.targetAmount !== null && result?.targetAmount !== undefined

  return (
    <section className="sub-editor-feature-page earned-incentives-page">
      <header className="unlock-sales-page-heading team-rewards-page-heading">
        <div>
          <span className="mini-label">Team Leader Workspace</span>
          <h1>{grantsOnly ? 'Grant Team Rewards' : 'Team Rewards'}</h1>
          <p>
            {grantsOnly
              ? 'Monthly reward grants automatically recorded when your assigned team meets its full sales target.'
              : 'Track each assigned Telecaller’s contribution to the shared monthly sales target.'}
          </p>
        </div>
        <label className="sales-month-filter">
          <span>Target month</span>
          <select value={month} onChange={(event) => {
            setIsLoading(true)
            setMonth(event.target.value)
          }}>
            {monthOptions().map((option) => (
              <option value={option.value} key={option.value}>{option.label}</option>
            ))}
          </select>
        </label>
      </header>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <div className="sales-summary-stats">
        <article className="dashboard-panel sales-summary-stat">
          <span>Monthly Team Target</span>
          <strong>{isLoading ? 'Loading…' : formatUsdt(result?.targetAmount || '0')}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Approved Team Sales</span>
          <strong>{isLoading ? 'Loading…' : formatUsdt(result?.teamSalesAmount || '0')}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Target Progress</span>
          <strong>{isLoading ? '—' : `${result?.progressPercent || 0}%`}</strong>
        </article>
      </div>

      <article className="dashboard-panel team-reward-progress-card">
        <div className="team-reward-progress-heading">
          <div>
            <span className="settings-card-eyebrow">{displayMonth(month).toUpperCase()}</span>
            <h2>{grantsOnly ? 'Monthly grant status' : 'Team target progress'}</h2>
          </div>
          <strong className={`team-reward-status${result?.grant ? ' is-granted' : result?.targetAchieved ? ' is-achieved' : ''}`}>
            {isLoading
              ? 'Loading…'
              : !targetIsSet
                ? 'Target not set'
                : result?.grant
                  ? 'Reward granted'
                  : result?.targetAchieved
                    ? 'Target achieved'
                    : 'In progress'}
          </strong>
        </div>

        {targetIsSet && (
          <>
            <div className="team-reward-progress-track" role="progressbar" aria-label="Monthly team target progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow={progress}>
              <span style={{ width: `${progress}%` }} />
            </div>
            <div className="team-reward-progress-caption">
              <span>{formatUsdt(result?.teamSalesAmount || '0')} achieved</span>
              <span>{formatUsdt(result?.remainingAmount || '0')} remaining</span>
            </div>
          </>
        )}

        {result?.grant ? (
          <p className="team-reward-grant-message" role="status">
            {formatUsdt(result.grant.rewardAmount)} was automatically recorded for this month on {displayDate(result.grant.grantedAt)}.
          </p>
        ) : !isLoading && targetIsSet && result?.targetAchieved && Number(result.configuredRewardAmount) <= 0 ? (
          <p className="team-reward-grant-message">The sales target is met, but the Sub Editor has not configured a reward amount yet.</p>
        ) : !isLoading && targetIsSet && !result?.targetAchieved ? (
          <p className="team-reward-grant-message">
            The Team Leader reward will be recorded automatically when assigned Telecallers’ combined approved sales reach {formatUsdt(result.targetAmount)}.
          </p>
        ) : null}
      </article>

      {grantsOnly ? (
        <article className="dashboard-panel sub-editor-feature-table-panel earned-incentive-history">
          <div className="panel-title"><h2>Automatic Reward Grant History</h2></div>
          <div className="employee-table-wrap">
            <table className="employee-table sales-review-table">
              <thead>
                <tr><th>Month</th><th>Team Target</th><th>Approved Sales</th><th>Reward Granted</th><th>Granted On</th></tr>
              </thead>
              <tbody>
                {isLoading ? (
                  <tr><td colSpan="5" className="sub-editor-feature-empty">Loading reward grants…</td></tr>
                ) : error ? (
                  <tr><td colSpan="5" className="sub-editor-feature-empty">Reward history could not be loaded.</td></tr>
                ) : result?.grantHistory.length ? result.grantHistory.map((grant) => (
                  <tr key={grant.month}>
                    <td>{displayMonth(grant.month)}</td>
                    <td>{formatUsdt(grant.targetAmount)}</td>
                    <td>{formatUsdt(grant.achievedSalesAmount)}</td>
                    <td>{formatUsdt(grant.rewardAmount)}</td>
                    <td>{displayDate(grant.grantedAt)}</td>
                  </tr>
                )) : (
                  <tr><td colSpan="5" className="sub-editor-feature-empty">No monthly rewards have been granted yet.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </article>
      ) : (
        <article className="dashboard-panel sub-editor-feature-table-panel">
          <div className="panel-title"><h2>Team Member Contributions</h2></div>
          <div className="employee-table-wrap">
            <table className="employee-table sales-review-table">
              <thead>
                <tr><th>Employee ID</th><th>Telecaller</th><th>Approved Sales</th><th>Sales Volume</th><th>Share of Team Target</th></tr>
              </thead>
              <tbody>
                {isLoading ? (
                  <tr><td colSpan="5" className="sub-editor-feature-empty">Loading team progress…</td></tr>
                ) : error ? (
                  <tr><td colSpan="5" className="sub-editor-feature-empty">Team progress could not be loaded.</td></tr>
                ) : result?.members.length ? result.members.map((member) => (
                  <tr key={member.employeeId}>
                    <td>{member.employeeId}</td>
                    <td>{member.name}</td>
                    <td>{member.approvedSales}</td>
                    <td>{formatUsdt(member.salesAmount)}</td>
                    <td>{targetIsSet ? `${member.targetContributionPercent}%` : '—'}</td>
                  </tr>
                )) : (
                  <tr><td colSpan="5" className="sub-editor-feature-empty">No Telecallers are assigned to your team yet.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </article>
      )}
    </section>
  )
}
