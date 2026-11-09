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

export default function EarnedIncentivesPage() {
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let active = true
    apiRequest('/sales/incentives/earned')
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
    <section className="sub-editor-feature-page earned-incentives-page">
      <span className="mini-label">Sub Editor Workspace</span>
      <h1>Incentive Earned</h1>
      <p className="sub-editor-feature-description">Incentives are earned when package sale requests are approved.</p>
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <div className="sales-summary-stats">
        <article className="dashboard-panel sales-summary-stat">
          <span>Total Incentives Earned</span>
          <strong>{isLoading ? 'Loading…' : formatUsdt(result?.totalEarned || '0')}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Approved Sales</span>
          <strong>{isLoading ? '—' : result?.approvedSalesCount ?? 0}</strong>
        </article>
      </div>

      <article className="dashboard-panel sub-editor-feature-table-panel">
        <div className="panel-title"><h2>Incentives by Employee</h2></div>
        <div className="employee-table-wrap">
          <table className="employee-table sales-review-table">
            <thead><tr><th>Employee ID</th><th>Employee</th><th>Role</th><th>Approved Sales</th><th>Incentive Earned</th></tr></thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="5" className="sub-editor-feature-empty">Loading incentives…</td></tr>
              ) : error ? (
                <tr><td colSpan="5" className="sub-editor-feature-empty">Incentives could not be loaded.</td></tr>
              ) : result?.employees.length ? result.employees.map((item) => (
                <tr key={item.employeeId}>
                  <td>{item.employeeId}</td><td>{item.name}</td><td>{item.role === 'team_leader' ? 'Team Leader' : 'Telecaller'}</td><td>{item.approvedSales}</td><td>{formatUsdt(item.totalIncentive)}</td>
                </tr>
              )) : (
                <tr><td colSpan="5" className="sub-editor-feature-empty">No approved incentives yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>

      <article className="dashboard-panel sub-editor-feature-table-panel earned-incentive-history">
        <div className="panel-title"><h2>Approved Incentive History</h2></div>
        <div className="employee-table-wrap">
          <table className="employee-table sales-review-table">
            <thead><tr><th>Approved</th><th>Employee</th><th>Customer Reference</th><th>Package</th><th>Incentive</th></tr></thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="5" className="sub-editor-feature-empty">Loading history…</td></tr>
              ) : result?.incentives.length ? result.incentives.map((item) => (
                <tr key={item.saleId}>
                  <td>{displayDate(item.approvedAt)}</td><td>{item.employeeName}<small className="sales-review-employee-id">{item.role === 'team_leader' ? 'Team Leader' : 'Telecaller'} · {item.employeeId}</small></td>
                  <td>{item.customerReference}</td><td>{formatUsdt(item.packageAmount)}</td><td>{formatUsdt(item.incentiveAmount)}</td>
                </tr>
              )) : (
                <tr><td colSpan="5" className="sub-editor-feature-empty">No approved incentive records yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>
    </section>
  )
}
