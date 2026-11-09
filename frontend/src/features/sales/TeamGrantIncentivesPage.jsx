import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
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
    return {
      value: `${month.getFullYear()}-${String(month.getMonth() + 1).padStart(2, '0')}`,
      label: new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(month),
    }
  })
}

function displayMonth(value) {
  return new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' })
    .format(new Date(`${value}-01T00:00:00`))
}

function displayDate(value) {
  return formatDateTime(value, undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Kolkata',
  }, { assumedOffset: '+05:30' })
}

export default function TeamGrantIncentivesPage() {
  const [month, setMonth] = useState(currentMonth)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let active = true
    apiRequest(`/sales/team-grant-incentives?month=${encodeURIComponent(month)}`)
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

  const members = result?.members || []
  const progress = result?.teamMemberCount
    ? Math.round((result.achievedMemberCount / result.teamMemberCount) * 100)
    : 0
  const hasTarget = result?.teamTargetAmount !== null && result?.teamTargetAmount !== undefined

  return (
    <section className="sub-editor-feature-page earned-incentives-page">
      <header className="unlock-sales-page-heading team-rewards-page-heading">
        <div>
          <span className="mini-label">Team Leader Workspace</span>
          <h1>Team Grant Incentives</h1>
          <p>Every assigned Telecaller must reach their equal share of the monthly target before your incentive share is granted.</p>
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
          <strong>{isLoading ? 'Loading…' : formatUsdt(result?.teamTargetAmount || '0')}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Approved Team Incentives</span>
          <strong>{isLoading ? 'Loading…' : formatUsdt(result?.teamIncentiveAmount || '0')}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Your configured share</span>
          <strong>{isLoading ? '—' : `${result?.leaderSharePercent || '0'}%`}</strong>
        </article>
      </div>

      <article className="dashboard-panel team-reward-progress-card">
        <div className="team-reward-progress-heading">
          <div>
            <span className="settings-card-eyebrow">{displayMonth(month).toUpperCase()}</span>
            <h2>Individual Target Completion</h2>
          </div>
          <strong className={`team-reward-status${result?.grant ? ' is-granted' : result?.allMembersReached ? ' is-achieved' : ''}`}>
            {isLoading
              ? 'Loading…'
              : !hasTarget
                ? 'Target not set'
                : result?.grant
                  ? 'Incentive granted'
                  : result?.teamMemberCount === 0
                    ? 'No assigned Telecallers'
                    : result?.allMembersReached
                      ? Number(result.leaderSharePercent) > 0
                        ? 'All targets reached'
                        : 'Share not configured'
                      : 'Team targets in progress'}
          </strong>
        </div>

        {hasTarget && (
          <>
            <div className="team-reward-progress-track" role="progressbar" aria-label="Assigned Telecallers who reached their monthly targets" aria-valuemin="0" aria-valuemax="100" aria-valuenow={progress}>
              <span style={{ width: `${progress}%` }} />
            </div>
            <div className="team-reward-progress-caption">
              <span>{result?.achievedMemberCount || 0} of {result?.teamMemberCount || 0} Telecallers reached {formatUsdt(result?.memberTargetAmount || '0')} each</span>
              <span>{progress}% of team members reached target</span>
            </div>
          </>
        )}

        {result?.grant ? (
          <p className="team-reward-grant-message" role="status">
            {formatUsdt(result.grant.leaderIncentiveAmount)} was automatically granted ({result.grant.leaderSharePercent}% of {formatUsdt(result.grant.teamIncentiveAmount)} team incentives) on {displayDate(result.grant.grantedAt)}.
          </p>
        ) : !isLoading && hasTarget && result?.allMembersReached && Number(result.leaderSharePercent) <= 0 ? (
          <p className="team-reward-grant-message">All assigned Telecallers reached their target. The Sub Editor must configure a Team Leader share percentage before an incentive can be granted.</p>
        ) : !isLoading && hasTarget && result?.teamMemberCount > 0 && !result?.allMembersReached ? (
          <p className="team-reward-grant-message">
            Once every assigned Telecaller reaches {formatUsdt(result.memberTargetAmount)} in approved sales, your configured percentage of their combined approved sale incentives will be granted automatically.
          </p>
        ) : null}
      </article>

      <article className="dashboard-panel sub-editor-feature-table-panel">
        <div className="panel-title"><h2>Equal Target and Incentive Progress by Telecaller</h2></div>
        <div className="employee-table-wrap">
          <table className="employee-table sales-review-table">
            <thead>
              <tr><th>Employee ID</th><th>Telecaller</th><th>Approved Sales</th><th>Monthly Sales</th><th>Assigned Target</th><th>Telecaller Incentive</th><th>Target Status</th></tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="7" className="sub-editor-feature-empty">Loading individual target progress…</td></tr>
              ) : error ? (
                <tr><td colSpan="7" className="sub-editor-feature-empty">Team grant incentive progress could not be loaded.</td></tr>
              ) : members.length ? members.map((member) => (
                <tr key={member.employeeId}>
                  <td>{member.employeeId}</td>
                  <td>{member.name}</td>
                  <td>{member.approvedSales}</td>
                  <td>{formatUsdt(member.salesAmount)}</td>
                  <td>{member.targetAmount === null ? '—' : formatUsdt(member.targetAmount)}</td>
                  <td>{formatUsdt(member.incentiveAmount)}</td>
                  <td>
                    <span className={`team-reward-status${member.targetReached ? ' is-achieved' : ''}`}>
                      {!hasTarget ? 'Target not set' : member.targetReached ? 'Reached' : `${member.targetProgressPercent}%`}
                    </span>
                  </td>
                </tr>
              )) : (
                <tr><td colSpan="7" className="sub-editor-feature-empty">No Telecallers are assigned to your team yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>

      <article className="dashboard-panel sub-editor-feature-table-panel earned-incentive-history">
        <div className="panel-title"><h2>Team Grant Incentive History</h2></div>
        <div className="employee-table-wrap">
          <table className="employee-table sales-review-table">
            <thead>
              <tr><th>Month</th><th>Telecallers</th><th>Target per Telecaller</th><th>Team Incentives</th><th>Share</th><th>Leader Incentive Granted</th><th>Granted On</th></tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="7" className="sub-editor-feature-empty">Loading grant history…</td></tr>
              ) : error ? (
                <tr><td colSpan="7" className="sub-editor-feature-empty">Grant history could not be loaded.</td></tr>
              ) : result?.grantHistory.length ? result.grantHistory.map((grant) => (
                <tr key={grant.month}>
                  <td>{displayMonth(grant.month)}</td>
                  <td>{grant.teamMemberCount}</td>
                  <td>{formatUsdt(grant.memberTargetAmount)}</td>
                  <td>{formatUsdt(grant.teamIncentiveAmount)}</td>
                  <td>{grant.leaderSharePercent}%</td>
                  <td>{formatUsdt(grant.leaderIncentiveAmount)}</td>
                  <td>{displayDate(grant.grantedAt)}</td>
                </tr>
              )) : (
                <tr><td colSpan="7" className="sub-editor-feature-empty">No Team Leader grant incentives have been recorded yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>
    </section>
  )
}
