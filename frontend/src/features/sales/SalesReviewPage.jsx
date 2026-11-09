import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import { formatUsdt } from '../../utils/formatters'

const filters = [
  { key: 'all', label: 'All Sales' },
  { key: 'pending', label: 'Pending' },
  { key: 'approved', label: 'Approved' },
  { key: 'rejected', label: 'Rejected' },
]

function displayDate(value) {
  return formatDateTime(value, undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone: 'Asia/Kolkata',
  }, { assumedOffset: '+05:30' })
}

export default function SalesReviewPage() {
  const [filter, setFilter] = useState('pending')
  const [result, setResult] = useState(null)
  const [rejectionSale, setRejectionSale] = useState(null)
  const [rejectionReason, setRejectionReason] = useState('')
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [reviewingId, setReviewingId] = useState(null)

  const loadSales = async (activeFilter = filter) => {
    setError('')
    setIsLoading(true)
    try {
      const response = await apiRequest(`/sales/unlocks?status=${activeFilter}`)
      setResult(response.result)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    let active = true
    apiRequest(`/sales/unlocks?status=${filter}`)
      .then((response) => {
        if (active) {
          setResult(response.result)
          setError('')
        }
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
  }, [filter])

  const review = async (sale, decision, reason = '') => {
    setReviewingId(sale.id)
    setError('')
    try {
      await apiRequest(`/sales/unlocks/${sale.id}/review`, {
        method: 'POST',
        body: { decision, reason },
      })
      setRejectionSale(null)
      setRejectionReason('')
      await loadSales(filter)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setReviewingId(null)
    }
  }

  const reject = async (event) => {
    event.preventDefault()
    if (!rejectionSale || !rejectionReason.trim()) return
    await review(rejectionSale, 'rejected', rejectionReason.trim())
  }

  const summary = result?.summary

  return (
    <section className="sub-editor-feature-page sales-review-page">
      <span className="mini-label">Sub Editor Workspace</span>
      <h1>Total Sales</h1>
      <p className="sub-editor-feature-description">
        Review Telecaller and Team Leader package sale requests. Only approved sales count toward Total Sales and earn incentives.
      </p>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <div className="sales-summary-stats">
        <article className="dashboard-panel sales-summary-stat">
          <span>Approved Sales</span>
          <strong>{isLoading && !summary ? 'Loading…' : formatUsdt(summary?.approvedAmount || '0')}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Pending Sales</span>
          <strong>{isLoading && !summary ? 'Loading…' : formatUsdt(summary?.pendingAmount || '0')}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Pending Reviews</span>
          <strong>{isLoading && !summary ? '—' : summary?.pendingCount ?? 0}</strong>
        </article>
      </div>

      <article className="dashboard-panel sales-review-panel">
        <div className="daily-activity-filters daily-activity-review-tabs" role="tablist" aria-label="Filter sales by status">
          {filters.map((item) => {
            const count = item.key === 'pending'
              ? summary?.pendingCount
              : item.key === 'approved'
                ? summary?.approvedCount
                : item.key === 'rejected'
                  ? summary?.rejectedCount
                  : summary?.totalCount
            return (
              <button
                type="button"
                role="tab"
                aria-selected={filter === item.key}
                className={filter === item.key ? 'is-active' : ''}
                key={item.key}
                onClick={() => {
                  setIsLoading(true)
                  setFilter(item.key)
                }}
              >
                {item.label}{count === undefined ? '' : ` (${count})`}
              </button>
            )
          })}
        </div>

        <div className="employee-table-wrap">
          <table className="employee-table sales-review-table">
            <thead>
              <tr>
                <th>Submitted</th>
                <th>Submitted By</th>
                <th>Customer Reference</th>
                <th>Package</th>
                <th>Status</th>
                <th>Incentive</th>
                {filter !== 'approved' && filter !== 'rejected' && <th>Review</th>}
                {filter === 'rejected' && <th>Reason</th>}
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan={filter === 'approved' ? 6 : 7} className="sub-editor-feature-empty">Loading sales requests…</td></tr>
              ) : result?.sales.length ? (
                result.sales.map((sale) => (
                  <tr key={sale.id}>
                    <td>{displayDate(sale.createdAt)}</td>
                    <td>{sale.employeeName}<small className="sales-review-employee-id">{sale.employeeRole === 'team_leader' ? 'Team Leader' : 'Telecaller'} · {sale.employeeId}</small></td>
                    <td>{sale.customerReference}</td>
                    <td>{formatUsdt(sale.packageAmount)}</td>
                    <td><span className={`sales-status-badge is-${sale.status}`}>{sale.status}</span></td>
                    <td>{sale.incentiveAmount === null ? '—' : formatUsdt(sale.incentiveAmount)}</td>
                    {filter !== 'approved' && filter !== 'rejected' && (
                      <td>
                        {sale.status === 'pending' ? (
                          <span className="sales-review-actions">
                            <button
                              type="button"
                              className="sales-approve-button"
                              disabled={reviewingId === sale.id}
                              onClick={() => review(sale, 'approved')}
                            >
                              Approve
                            </button>
                            <button
                              type="button"
                              className="sales-reject-button"
                              disabled={reviewingId === sale.id}
                              onClick={() => {
                                setRejectionSale(sale)
                                setRejectionReason('')
                                setError('')
                              }}
                            >
                              Reject
                            </button>
                          </span>
                        ) : sale.rejectionReason || '—'}
                      </td>
                    )}
                    {filter === 'rejected' && <td>{sale.rejectionReason}</td>}
                  </tr>
                ))
              ) : (
                <tr><td colSpan={filter === 'approved' ? 6 : 7} className="sub-editor-feature-empty">No {filter === 'all' ? '' : `${filter} `}sales requests.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>

      {rejectionSale && (
        <div className="budget-modal-backdrop" role="presentation" onMouseDown={(event) => {
          if (event.target === event.currentTarget && reviewingId === null) setRejectionSale(null)
        }}>
          <section className="budget-modal sales-rejection-modal" role="dialog" aria-modal="true" aria-labelledby="sales-rejection-title">
            <header className="budget-modal-heading">
              <div>
                <span className="mini-label">Reject sale</span>
                <h2 id="sales-rejection-title">Add a rejection reason</h2>
                <p>{rejectionSale.telecallerName} · {formatUsdt(rejectionSale.packageAmount)} package</p>
              </div>
              <button type="button" className="budget-modal-close" onClick={() => setRejectionSale(null)} disabled={reviewingId !== null} aria-label="Close">×</button>
            </header>
            <form onSubmit={reject}>
              <label className="budget-form-field">
                <span>Reason (required)</span>
                <textarea
                  className="sales-rejection-reason"
                  value={rejectionReason}
                  onChange={(event) => setRejectionReason(event.target.value)}
                  maxLength={500}
                  rows={4}
                  required
                />
              </label>
              {error && <p className="budget-error" role="alert">{error}</p>}
              <footer className="budget-modal-actions">
                <button type="button" className="budget-secondary-button" onClick={() => setRejectionSale(null)} disabled={reviewingId !== null}>Cancel</button>
                <button type="submit" className="sales-reject-confirm-button" disabled={reviewingId !== null || !rejectionReason.trim()}>
                  {reviewingId !== null ? 'Rejecting…' : 'Reject Sale'}
                </button>
              </footer>
            </form>
          </section>
        </div>
      )}
    </section>
  )
}
