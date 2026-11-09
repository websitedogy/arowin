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

export default function TeamIncentivesPage() {
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let active = true
    apiRequest('/sales/team-incentives')
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
  }, [])

  return (
    <section className="sub-editor-feature-page earned-incentives-page">
      <span className="mini-label">Team Leader Workspace</span>
      <h1>Team Incentives</h1>
      <p className="sub-editor-feature-description">
        Approved package-sale incentives earned by the Telecallers assigned to your team.
      </p>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <div className="sales-summary-stats">
        <article className="dashboard-panel sales-summary-stat">
          <span>Total Team Incentives</span>
          <strong>{isLoading ? 'Loading…' : formatUsdt(result?.totalIncentive || '0')}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Approved Team Sales</span>
          <strong>{isLoading ? '—' : result?.approvedSales ?? 0}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Assigned Telecallers</span>
          <strong>{isLoading ? '—' : result?.teamMemberCount ?? 0}</strong>
        </article>
      </div>

      <article className="dashboard-panel sub-editor-feature-table-panel">
        <div className="panel-title"><h2>Incentives by Team Member</h2></div>
        <div className="employee-table-wrap">
          <table className="employee-table sales-review-table">
            <thead>
              <tr><th>Employee ID</th><th>Telecaller</th><th>Approved Sales</th><th>Incentive Earned</th></tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="4" className="sub-editor-feature-empty">Loading team incentives…</td></tr>
              ) : error ? (
                <tr><td colSpan="4" className="sub-editor-feature-empty">Team incentives could not be loaded.</td></tr>
              ) : result?.employees.length ? result.employees.map((employee) => (
                <tr key={employee.employeeId}>
                  <td>{employee.employeeId}</td>
                  <td>{employee.name}</td>
                  <td>{employee.approvedSales}</td>
                  <td>{formatUsdt(employee.totalIncentive)}</td>
                </tr>
              )) : (
                <tr><td colSpan="4" className="sub-editor-feature-empty">No Telecallers are assigned to your team yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>

      <article className="dashboard-panel sub-editor-feature-table-panel earned-incentive-history">
        <div className="panel-title"><h2>Approved Team Incentive History</h2></div>
        <div className="employee-table-wrap">
          <table className="employee-table sales-review-table">
            <thead>
              <tr><th>Approved</th><th>Telecaller</th><th>Package</th><th>Incentive Earned</th></tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="4" className="sub-editor-feature-empty">Loading incentive history…</td></tr>
              ) : error ? (
                <tr><td colSpan="4" className="sub-editor-feature-empty">Incentive history could not be loaded.</td></tr>
              ) : result?.incentives.length ? result.incentives.map((item) => (
                <tr key={item.saleId}>
                  <td>{displayDate(item.approvedAt)}</td>
                  <td>{item.employeeName}<small className="sales-review-employee-id">{item.employeeId}</small></td>
                  <td>{formatUsdt(item.packageAmount)}</td>
                  <td>{formatUsdt(item.incentiveAmount)}</td>
                </tr>
              )) : (
                <tr><td colSpan="4" className="sub-editor-feature-empty">No approved team incentives yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>
    </section>
  )
}