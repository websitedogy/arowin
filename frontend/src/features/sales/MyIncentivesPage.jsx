import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import { formatUsdt } from '../../utils/formatters'

function displayDate(value) {
  return formatDateTime(value, undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Kolkata',
  }, { assumedOffset: '+05:30' })
}

export default function MyIncentivesPage({ user }) {
  const employeeRole = user?.role === 'team_leader' ? 'Team Leader' : 'Telecaller'
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let active = true
    apiRequest('/sales/incentives/me')
      .then((response) => {
        if (active) setResult(response.result)
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
  }, [])

  return (
    <section className="unlock-sales-page my-incentives-page">
      <header className="unlock-sales-page-heading">
        <div>
          <span className="mini-label">{employeeRole} · Incentives</span>
          <h1>My Incentives</h1>
          <p>Your package-sale incentives are added here after Sub Editor approval.</p>
        </div>
      </header>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <article className="dashboard-panel my-incentives-total">
        <span>Total Incentive Earned</span>
        <strong>{isLoading ? 'Loading…' : formatUsdt(result?.totalEarned || '0')}</strong>
      </article>

      <section className="unlock-sales-panel" aria-label="Approved incentives">
        <div className="unlock-sales-panel-heading">
          <div className="unlock-sales-panel-title">
            <span className="unlock-sales-panel-icon">+</span>
            <span><h2>Approved Incentives</h2><p>Only approved sales are included in this total.</p></span>
          </div>
          <span className="unlock-sales-record-count">{result?.incentives.length || 0} records</span>
        </div>
        <div className="unlock-sales-table-wrap">
          <table className="unlock-sales-table">
            <thead>
              <tr><th>Approved Date</th><th>Customer Reference</th><th>Package</th><th>Incentive Earned</th></tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="4"><div className="unlock-sales-empty"><strong>Loading incentives…</strong></div></td></tr>
              ) : error ? (
                <tr><td colSpan="4"><div className="unlock-sales-empty"><strong>Incentives could not be loaded.</strong></div></td></tr>
              ) : result?.incentives.length ? (
                result.incentives.map((item) => (
                  <tr key={item.saleId}>
                    <td>{displayDate(item.approvedAt?.slice(0, 10))}</td>
                    <td>{item.customerReference}</td>
                    <td>{formatUsdt(item.packageAmount)}</td>
                    <td>{formatUsdt(item.incentiveAmount)}</td>
                  </tr>
                ))
              ) : (
                <tr><td colSpan="4"><div className="unlock-sales-empty"><strong>No approved incentives yet</strong><p>Approved package sales will appear here.</p></div></td></tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
    </section>
  )
}
