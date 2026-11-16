import { useCallback, useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import { formatCurrency, formatUsdt } from '../../utils/formatters'
import { getExactPackageIdCount } from './packageTargetMath'
import VerifiedSaleSubmissionModal from './VerifiedSaleSubmissionModal'

const INR_PER_USDT = 100

function OverviewCard({ label, value, detail, status, highlight = false }) {
  return (
    <article className={`dashboard-panel emergency-sales-overview-card${status ? ` is-${status}` : ''}${highlight ? ' is-highlighted' : ''}`}>
      <span>{label}</span>
      <strong>{value}</strong>
      {detail && <small>{detail}</small>}
    </article>
  )
}

function displayDate(value) {
  return formatDateTime(value, undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Kolkata',
  }, { assumedOffset: '+05:30' })
}

export default function EmergencySalesPage({ user, onOverviewChange }) {
  const [assignments, setAssignments] = useState([])
  const [packages, setPackages] = useState([])
  const [packageError, setPackageError] = useState('')
  const [isLoadingPackages, setIsLoadingPackages] = useState(true)
  const [overview, setOverview] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmissionOpen, setIsSubmissionOpen] = useState(false)

  const loadEmergencySales = useCallback(async () => {
    try {
      const result = await apiRequest('/sales/emergency/me')
      setAssignments(result.assignments)
      setOverview(result.overview)
      onOverviewChange?.(result.overview)
      setError('')
      return result
    } catch (requestError) {
      setError(requestError.message)
      throw requestError
    }
  }, [onOverviewChange])

  useEffect(() => {
    let active = true
    apiRequest('/sales/packages')
      .then((result) => {
        if (active) setPackages(result.packages)
      })
      .catch((requestError) => {
        if (active) setPackageError(requestError.message)
      })
      .finally(() => {
        if (active) setIsLoadingPackages(false)
      })

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    let active = true
    loadEmergencySales()
      .catch(() => {})
      .finally(() => {
        if (active) setIsLoading(false)
      })
    const refreshTimer = window.setInterval(() => {
      loadEmergencySales().catch(() => {})
    }, 30000)

    return () => {
      active = false
      window.clearInterval(refreshTimer)
    }
  }, [loadEmergencySales])

  const currentAssignment = assignments.find((assignment) => assignment.status === 'active')
    || assignments.reduce((mostRecent, assignment) => (
      !mostRecent || assignment.assignedAt > mostRecent.assignedAt ? assignment : mostRecent
    ), null)
  const annualSalaryInr = overview?.monthlySalaryInr == null
    ? null
    : Number(overview.monthlySalaryInr) * 12
  const unavailableValue = isLoading ? 'Loading…' : error ? '—' : 'Unavailable'
  const emergencyAssignment = overview?.emergencyAssignment
  const approvedEmergencyAmount = Number(overview?.approvedEmergencySalesAmount || 0)
  const emergencyTargetAmount = Number(emergencyAssignment?.packageAmount || 0)
  const emergencyProgressPercent = emergencyTargetAmount > 0
    ? Math.min(100, approvedEmergencyAmount / emergencyTargetAmount * 100)
    : 0

  return (
    <section className="sub-editor-feature-page emergency-sales-employee-page">
      <header className="emergency-sales-page-heading">
        <div>
          <span className="mini-label">{user?.role === 'team_leader' ? 'Team Leader' : 'Telecaller'} Workspace</span>
          <h1>Emergency Sales</h1>
          <p className="sub-editor-feature-description">Review your annual scale, emergency sales assignment, and pending or approved sales.</p>
        </div>
        {overview?.canSubmitEmergencySale && (
          <button
            type="button"
            className="emergency-sales-done-button"
            onClick={() => setIsSubmissionOpen(true)}
          >
            <span aria-hidden="true">$</span>
            Emergency Sales
          </button>
        )}
      </header>
      {error && <p className="dashboard-data-error" role="alert">Emergency sales could not be loaded: {error}</p>}

      <div className="emergency-sales-overview-grid" role="group" aria-label="Emergency sales overview">
        <OverviewCard
          label="Annual Scale (INR)"
          value={overview
            ? annualSalaryInr === null ? 'Salary not set' : formatCurrency(annualSalaryInr)
            : unavailableValue}
        />
        <OverviewCard
          label="Annual Scale (USDT)"
          value={overview
            ? annualSalaryInr === null ? 'Salary not set' : formatUsdt(annualSalaryInr / INR_PER_USDT)
            : unavailableValue}
          detail={overview && annualSalaryInr !== null ? `₹${INR_PER_USDT} per USDT` : undefined}
        />
        <OverviewCard
          label="Emergency Sales Expiry"
          value={currentAssignment ? displayDate(currentAssignment.expiresAt) : isLoading || error ? unavailableValue : 'Not assigned'}
          detail={currentAssignment?.status === 'active' ? 'Current assignment' : currentAssignment ? 'Most recent assignment' : undefined}
        />
        <OverviewCard
          label="Emergency Sales Amount"
          value={currentAssignment
            ? formatUsdt(Number(currentAssignment.packageAmount))
            : isLoading || error ? unavailableValue : 'Not assigned'}
          detail={currentAssignment?.status === 'active' ? 'Current assignment' : currentAssignment ? 'Most recent assignment' : undefined}
          highlight
        />
        <OverviewCard
          label="Pending Sales"
          value={overview ? `${overview.pendingSales.count} sales` : unavailableValue}
          detail={overview ? `Total ${formatUsdt(overview.pendingSales.amount)}` : undefined}
          status="pending"
        />
        <OverviewCard
          label="Approved Sales"
          value={overview ? `${overview.approvedSales.count} sales` : unavailableValue}
          detail={overview ? `Total ${formatUsdt(overview.approvedSales.amount)}` : undefined}
          status="approved"
        />
        <OverviewCard
          label="Rejected Sales"
          value={overview ? `${overview.rejectedSales.count} sales` : unavailableValue}
          detail={overview ? `Total ${formatUsdt(overview.rejectedSales.amount)}` : undefined}
          status="rejected"
        />
      </div>

      <section className="dashboard-panel emergency-package-count-panel" aria-labelledby="emergency-package-count-title">
        <div className="emergency-package-count-heading">
          <div>
            <h2 id="emergency-package-count-title">Customer IDs by Package</h2>
            <p>
              {emergencyTargetAmount > 0
                ? `Target: ${formatUsdt(emergencyTargetAmount)} · Counts are shown only when one package size matches the target exactly.`
                : 'An emergency sales target is required to calculate package ID counts.'}
            </p>
          </div>
        </div>
        {packageError && <p className="dashboard-data-error" role="alert">Package options could not be loaded: {packageError}</p>}
        {isLoadingPackages ? (
          <p className="sub-editor-feature-empty" role="status">Loading package options…</p>
        ) : packageError ? null : (
          packages.length ? (
            <div className="emergency-package-count-grid">
              {packages.map((item) => {
                const idCount = emergencyTargetAmount > 0
                  ? getExactPackageIdCount(emergencyTargetAmount, item.packageAmount)
                  : null

                return (
                  <article className={`emergency-package-count-card${idCount === null ? ' is-unavailable' : ''}`} key={item.packageAmount}>
                    <span>{formatUsdt(item.packageAmount)} package</span>
                    <strong>
                      {emergencyTargetAmount <= 0
                        ? 'Target not set'
                        : idCount === null
                          ? 'No exact match'
                          : `${idCount} ${idCount === 1 ? 'ID' : 'IDs'}`}
                    </strong>
                    {idCount !== null && <small>Total: {formatUsdt(idCount * Number(item.packageAmount))}</small>}
                    {emergencyTargetAmount > 0 && idCount === null && <small>Package amount does not divide the target evenly.</small>}
                  </article>
                )
              })}
            </div>
          ) : (
            <p className="sub-editor-feature-empty" role="status">No package sizes are configured.</p>
          )
        )}
      </section>

      {emergencyAssignment ? (
        <article className="dashboard-panel emergency-sales-progress-panel">
          <div className="panel-title">
            <div>
              <h2>Emergency Sales Completion</h2>
              <p>Pending sales do not count. The target completes after Sub Editor approval.</p>
            </div>
            <span className={`sales-status-badge is-${overview.unlockSalesUnlocked ? 'approved' : emergencyAssignment.status}`}>
              {overview.unlockSalesUnlocked ? 'complete' : emergencyAssignment.status}
            </span>
          </div>
          <div className="emergency-sales-progress-copy">
            <strong>{formatUsdt(approvedEmergencyAmount)} <span>of {formatUsdt(emergencyTargetAmount)}</span></strong>
            <span>{overview.unlockSalesUnlocked
              ? 'Emergency sales complete. My Unlock Sales is now available.'
              : `${formatUsdt(overview.remainingEmergencySalesAmount)} remaining`}</span>
          </div>
          <div
            className="emergency-sales-progress-track"
            role="progressbar"
            aria-label="Approved emergency sales progress"
            aria-valuemin={0}
            aria-valuemax={emergencyTargetAmount}
            aria-valuenow={Math.min(approvedEmergencyAmount, emergencyTargetAmount)}
          >
            <span style={{ width: `${emergencyProgressPercent}%` }} />
          </div>
          {!overview.unlockSalesUnlocked && emergencyAssignment.status === 'expired' && (
            <div className="emergency-sales-progress-actions">
              <p>The expiry date has passed, but the amount must still be completed and approved to unlock My Unlock Sales.</p>
            </div>
          )}
        </article>
      ) : (
        <article className="dashboard-panel emergency-sales-progress-panel">
          <h2>No Emergency Sales Assigned</h2>
          <p>My Unlock Sales stays locked until a Sub Editor assigns and approves your emergency sales target.</p>
        </article>
      )}

      <article className="dashboard-panel emergency-assignment-panel">
        <div className="panel-title">
          <h2>Emergency Assignments</h2>
          <span className="unlock-sales-record-count">{isLoading ? 'Loading…' : `${assignments.length} records`}</span>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table emergency-assignment-table">
            <thead>
              <tr><th>Sales Amount</th><th>Assigned Date</th><th>Expiry Date</th><th>Days Left</th><th>Note</th><th>Status</th></tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="6" className="sub-editor-feature-empty">Loading emergency sales…</td></tr>
              ) : error ? (
                <tr><td colSpan="6" className="sub-editor-feature-empty">Emergency sales are unavailable.</td></tr>
              ) : assignments.length ? assignments.map((assignment) => (
                <tr key={assignment.id}>
                  <td>{formatUsdt(assignment.packageAmount)}</td>
                  <td>{displayDate(assignment.assignedAt)}</td>
                  <td>{displayDate(assignment.expiresAt)}</td>
                  <td>{assignment.daysLeft}</td>
                  <td>{assignment.note || '—'}</td>
                  <td><span className={`sales-status-badge is-${assignment.status}`}>{assignment.status}</span></td>
                </tr>
              )) : (
                <tr><td colSpan="6" className="sub-editor-feature-empty">No emergency sales have been assigned to you.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>

      <article className="dashboard-panel emergency-assignment-panel emergency-submission-history">
        <div className="panel-title">
          <h2>Emergency Sale Submissions</h2>
          <span className="unlock-sales-record-count">{overview?.emergencySales?.length ?? 0} records</span>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table emergency-assignment-table">
            <thead>
              <tr><th>Submitted</th><th>Customer Reference</th><th>Package</th><th>Status</th><th>Review Note</th></tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="5" className="sub-editor-feature-empty">Loading emergency sale submissions…</td></tr>
              ) : error ? (
                <tr><td colSpan="5" className="sub-editor-feature-empty">Emergency sale submissions are unavailable.</td></tr>
              ) : overview?.emergencySales?.length ? overview.emergencySales.map((sale) => (
                <tr key={sale.id}>
                  <td>{displayDate(sale.createdAt)}</td>
                  <td>{sale.customerReference}</td>
                  <td>{formatUsdt(sale.packageAmount)}</td>
                  <td><span className={`sales-status-badge is-${sale.status}`}>{sale.status}</span></td>
                  <td>{sale.rejectionReason || (sale.status === 'pending' ? 'Awaiting Sub Editor review' : '—')}</td>
                </tr>
              )) : (
                <tr><td colSpan="5" className="sub-editor-feature-empty">No emergency sales submitted yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>

      {isSubmissionOpen && (
        <VerifiedSaleSubmissionModal
          user={user}
          mode="emergency"
          onClose={() => setIsSubmissionOpen(false)}
          onSubmitted={() => loadEmergencySales().catch(() => {})}
        />
      )}
    </section>
  )
}
