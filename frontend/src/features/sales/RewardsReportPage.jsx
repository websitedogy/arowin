import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import { formatUsdt } from '../../utils/formatters'
import Icon from '../dashboard/components/Icon'

function displayDate(value) {
  return formatDateTime(value, undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Kolkata',
  }, { assumedOffset: '+05:30' })
}

export default function RewardsReportPage({ feature }) {
  const isAchieved = feature.path === '/rewards/achieved'
  const endpoint = isAchieved ? '/sales/rewards/achieved' : '/sales/rewards/granted'
  const [rewards, setRewards] = useState([])
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let active = true
    apiRequest(endpoint)
      .then((result) => {
        if (active) setRewards(result.rewards)
      })
      .catch((requestError) => {
        if (active) setError(requestError.message)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })

    return () => {
      active = false
    }
  }, [endpoint])

  return (
    <section className="sub-editor-feature-page">
      <span className="mini-label">Sub Editor Workspace</span>
      <h1>{feature.label}</h1>
      <p className="sub-editor-feature-description">
        {isAchieved
          ? 'Review monthly Team Leader rewards recorded after reaching sales targets.'
          : 'Review monthly Team Leader incentives granted from assigned team sales.'}
      </p>
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <article className="dashboard-panel sub-editor-feature-table-panel">
        <div className="panel-title">
          <Icon name={feature.icon} size={22} />
          <div>
            <h2>{feature.label}</h2>
            <small>{isLoading ? 'Loading…' : `${rewards.length} ${rewards.length === 1 ? 'record' : 'records'}`}</small>
          </div>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table sales-review-table">
            {isAchieved ? (
              <>
                <thead>
                  <tr><th>Team Leader</th><th>Employee ID</th><th>Month</th><th>Target</th><th>Sales Achieved</th><th>Reward</th><th>Granted</th></tr>
                </thead>
                <tbody>
                  {isLoading ? (
                    <tr><td colSpan="7" className="sub-editor-feature-empty">Loading rewards…</td></tr>
                  ) : error ? (
                    <tr><td colSpan="7" className="sub-editor-feature-empty">Rewards could not be loaded.</td></tr>
                  ) : rewards.length ? rewards.map((reward) => (
                    <tr key={`${reward.employeeId}-${reward.month}`}>
                      <td>{reward.leaderName}</td>
                      <td>{reward.employeeId}</td>
                      <td>{reward.month}</td>
                      <td>{formatUsdt(reward.targetAmount)}</td>
                      <td>{formatUsdt(reward.achievedSalesAmount)}</td>
                      <td>{formatUsdt(reward.rewardAmount)}</td>
                      <td>{displayDate(reward.grantedAt)}</td>
                    </tr>
                  )) : (
                    <tr><td colSpan="7" className="sub-editor-feature-empty">No achieved rewards have been recorded.</td></tr>
                  )}
                </tbody>
              </>
            ) : (
              <>
                <thead>
                  <tr><th>Team Leader</th><th>Employee ID</th><th>Month</th><th>Team Target</th><th>Team Incentive</th><th>Leader Share</th><th>Granted Incentive</th><th>Granted</th></tr>
                </thead>
                <tbody>
                  {isLoading ? (
                    <tr><td colSpan="8" className="sub-editor-feature-empty">Loading grants…</td></tr>
                  ) : error ? (
                    <tr><td colSpan="8" className="sub-editor-feature-empty">Grants could not be loaded.</td></tr>
                  ) : rewards.length ? rewards.map((reward) => (
                    <tr key={`${reward.employeeId}-${reward.month}`}>
                      <td>{reward.leaderName}</td>
                      <td>{reward.employeeId}</td>
                      <td>{reward.month}</td>
                      <td>{formatUsdt(reward.teamTargetAmount)}</td>
                      <td>{formatUsdt(reward.teamIncentiveAmount)}</td>
                      <td>{reward.leaderSharePercent}%</td>
                      <td>{formatUsdt(reward.leaderIncentiveAmount)}</td>
                      <td>{displayDate(reward.grantedAt)}</td>
                    </tr>
                  )) : (
                    <tr><td colSpan="8" className="sub-editor-feature-empty">No Team Leader incentives have been granted.</td></tr>
                  )}
                </tbody>
              </>
            )}
          </table>
        </div>
      </article>
    </section>
  )
}
