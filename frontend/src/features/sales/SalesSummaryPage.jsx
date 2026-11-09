import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import formatDateTime from '../../utils/formatDateTime'
import { formatUsdt } from '../../utils/formatters'

const periodByPath = {
  '/sales/total': 'total',
  '/sales/weekly': 'this_week',
  '/sales/monthly': 'last_month',
}

function displayDate(date) {
  return formatDateTime(date, undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Kolkata',
  }, { assumedOffset: '+05:30' })
}

export default function SalesSummaryPage({ feature }) {
  const period = periodByPath[feature.path]
  const [summary, setSummary] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let active = true
    apiRequest(`/sales/summary?period=${encodeURIComponent(period)}`)
      .then((result) => {
        if (active) setSummary(result.summary)
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
  }, [period])

  return (
    <section className="sub-editor-feature-page sales-summary-page">
      <span className="mini-label">Sub Editor Workspace</span>
      <h1>{feature.label}</h1>
      <p className="sub-editor-feature-description">
        {summary
          ? summary.startDate
            ? `${summary.periodLabel}: ${displayDate(summary.startDate)} – ${displayDate(summary.throughDate)}. Approved unlock package sales only.`
            : 'All approved unlock package sales through today.'
          : `Approved package sales reported by employees${period === 'this_week' ? ' this week' : period === 'last_month' ? ' last month' : ''}.`}
      </p>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <div className="sales-summary-stats">
        <article className="dashboard-panel sales-summary-stat">
          <span>Total Sales</span>
          <strong>{isLoading ? 'Loading…' : error ? '—' : formatUsdt(summary.totalSales)}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Employees with sales</span>
          <strong>{isLoading || error ? '—' : summary.salespersonCount}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Sales entries</span>
          <strong>{isLoading || error ? '—' : summary.salesEntries}</strong>
        </article>
      </div>

      <article className="dashboard-panel sub-editor-feature-table-panel">
        <div className="panel-title">
          <Icon name={feature.icon} size={22} />
          <h2>Sales by Employee</h2>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table sales-summary-table">
            <thead>
              <tr>
                <th>Employee ID</th>
                <th>Employee</th>
                <th>Role</th>
                <th>Sales Entries</th>
                <th>Total Sales (USDT)</th>
                <th>Last Sale Date</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="6" className="sub-editor-feature-empty">Loading sales…</td></tr>
              ) : error ? (
                <tr><td colSpan="6" className="sub-editor-feature-empty">Sales could not be loaded.</td></tr>
              ) : summary.salespeople.length ? (
                summary.salespeople.map((salesperson) => (
                  <tr key={salesperson.employeeId}>
                    <td>{salesperson.employeeId}</td>
                    <td>{salesperson.name}</td>
                    <td>{salesperson.role === 'team_leader' ? 'Team Leader' : 'Telecaller'}</td>
                    <td>{salesperson.salesEntries}</td>
                    <td>{formatUsdt(salesperson.totalSales)}</td>
                    <td>{displayDate(salesperson.lastSaleDate)}</td>
                  </tr>
                ))
              ) : (
                <tr><td colSpan="6" className="sub-editor-feature-empty">No sales recorded for this period.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>
    </section>
  )
}
