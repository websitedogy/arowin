import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import formatDateTime from '../../utils/formatDateTime'
import { formatUsdt } from '../../utils/formatters'
import VerifiedSaleSubmissionModal from './VerifiedSaleSubmissionModal'

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

export default function UnlockSalesPage({ user }) {
  const employeeRole = user?.role === 'team_leader' ? 'Team Leader' : 'Telecaller'
  const [isGenerateOpen, setIsGenerateOpen] = useState(false)
  const [sales, setSales] = useState([])
  const [earnedIncentives, setEarnedIncentives] = useState('0.00')
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')

  useEffect(() => {
    let active = true
    apiRequest('/sales/unlocks/me')
      .then((salesResult) => {
        if (!active) return
        setSales(salesResult.result.sales)
        setEarnedIncentives(salesResult.result.earnedIncentives)
      })
      .catch((error) => {
        if (active) setLoadError(error.message)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })
    return () => {
      active = false
    }
  }, [])

  const openGenerateModal = () => setIsGenerateOpen(true)
  const closeGenerateModal = () => setIsGenerateOpen(false)

  const refreshSales = async () => {
    try {
      const salesResult = await apiRequest('/sales/unlocks/me')
      setSales(salesResult.result.sales)
      setEarnedIncentives(salesResult.result.earnedIncentives)
      setLoadError('')
    } catch (error) {
      setLoadError(error.message)
    }
  }

  const pendingCount = sales.filter((sale) => sale.status === 'pending').length

  return (
    <div className="unlock-sales-page">
      <header className="unlock-sales-page-heading">
        <div>
          <span className="mini-label">{employeeRole} · Sales</span>
          <h1>My Unlock Sales</h1>
          <p>Look up a customer purchase in Arowin Global and track its review status.</p>
        </div>
        <button type="button" className="unlock-generate-button" onClick={openGenerateModal}>
          <Icon name="plus" size={18} />
          Lookup Customer
        </button>
      </header>

      {loadError && <p className="dashboard-data-error" role="alert">{loadError}</p>}
      <div className="sales-summary-stats telecaller-unlock-stats">
        <article className="dashboard-panel sales-summary-stat">
          <span>Total Submitted</span>
          <strong>{isLoading ? 'Loading…' : sales.length}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Pending Review</span>
          <strong>{isLoading ? '—' : pendingCount}</strong>
        </article>
        <article className="dashboard-panel sales-summary-stat">
          <span>Incentive Earned</span>
          <strong>{isLoading ? 'Loading…' : formatUsdt(earnedIncentives)}</strong>
        </article>
      </div>

      <section className="unlock-sales-panel" aria-labelledby="unlock-sales-history-title">
        <div className="unlock-sales-panel-heading">
          <div className="unlock-sales-panel-title">
            <span className="unlock-sales-panel-icon"><Icon name="chart" size={21} /></span>
            <span>
              <h2 id="unlock-sales-history-title">Unlock Sales History</h2>
              <p>Only approved sales count toward sales totals and incentives.</p>
            </span>
          </div>
          <span className="unlock-sales-record-count">{sales.length} records</span>
        </div>

        <div className="unlock-sales-table-wrap">
          <table className="unlock-sales-table">
            <thead>
              <tr>
                <th scope="col">Submitted</th>
                <th scope="col">Customer Reference</th>
                <th scope="col">Package</th>
                <th scope="col">Type</th>
                <th scope="col">Status</th>
                <th scope="col">Incentive</th>
                <th scope="col">Review Note</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="7"><div className="unlock-sales-empty"><strong>Loading sales…</strong></div></td></tr>
              ) : loadError ? (
                <tr><td colSpan="7"><div className="unlock-sales-empty"><strong>Sales could not be loaded.</strong></div></td></tr>
              ) : sales.length ? (
                sales.map((sale) => (
                  <tr key={sale.id}>
                    <td>{displayDate(sale.createdAt)}</td>
                    <td>{sale.customerReference}</td>
                    <td>{formatUsdt(sale.packageAmount)}</td>
                    <td>{sale.emergencyAssignmentId ? 'Emergency' : 'Regular'}</td>
                    <td>
                      <span className={`sales-status-badge is-${sale.status}`}>{sale.status}</span>
                      {!sale.isEmployeeCredited && (
                        <small className="sales-review-employee-id">Not counted toward your sales or incentives</small>
                      )}
                    </td>
                    <td>{sale.incentiveAmount === null ? '—' : formatUsdt(sale.incentiveAmount)}</td>
                    <td>{sale.rejectionReason || (sale.status === 'pending' ? 'Awaiting Sub Editor review' : '—')}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="7">
                    <div className="unlock-sales-empty">
                      <span><Icon name="trend" size={23} /></span>
                      <strong>No package sales submitted yet</strong>
                      <p>Your requests and their review status will appear here.</p>
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      {isGenerateOpen && (
        <VerifiedSaleSubmissionModal
          user={user}
          mode="unlock"
          onClose={closeGenerateModal}
          onSubmitted={refreshSales}
        />
      )}
    </div>
  )
}
