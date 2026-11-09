import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import formatDateTime from '../../utils/formatDateTime'
import { formatUsdt } from '../../utils/formatters'

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
  const [customerId, setCustomerId] = useState('')
  const [sales, setSales] = useState([])
  const [earnedIncentives, setEarnedIncentives] = useState('0.00')
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [otp, setOtp] = useState('')
  const [isOtpSent, setIsOtpSent] = useState(false)
  const [isOtpVerified, setIsOtpVerified] = useState(false)
  const [isSendingOtp, setIsSendingOtp] = useState(false)
  const [isVerifyingOtp, setIsVerifyingOtp] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [feedback, setFeedback] = useState({ type: '', message: '' })

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

  const closeGenerateModal = () => {
    setIsGenerateOpen(false)
    setCustomerId('')
    setOtp('')
    setIsOtpSent(false)
    setIsOtpVerified(false)
    setFeedback({ type: '', message: '' })
  }

  const openGenerateModal = () => {
    setFeedback({ type: '', message: '' })
    setIsGenerateOpen(true)
  }

  const sendOtp = async () => {
    setIsSendingOtp(true)
    setOtp('')
    setFeedback({ type: '', message: '' })
    try {
      const result = await apiRequest('/sales/unlock-otp/send', { method: 'POST', body: {} })
      setIsOtpSent(true)
      setIsOtpVerified(false)
      setFeedback({ type: 'success', message: result.message })
    } catch (error) {
      setFeedback({ type: 'error', message: error.message })
    } finally {
      setIsSendingOtp(false)
    }
  }

  const verifyOtp = async () => {
    setIsVerifyingOtp(true)
    setFeedback({ type: '', message: '' })
    try {
      const result = await apiRequest('/sales/unlock-otp/verify', {
        method: 'POST',
        body: { otp },
      })
      setIsOtpVerified(true)
      setFeedback({ type: 'success', message: result.message })
    } catch (error) {
      setFeedback({ type: 'error', message: error.message })
    } finally {
      setIsVerifyingOtp(false)
    }
  }

  const submitSale = async (event) => {
    event.preventDefault()
    setFeedback({ type: '', message: '' })
    setIsSubmitting(true)
    try {
      await apiRequest('/sales/unlocks', {
        method: 'POST',
        body: { customerId },
      })
      setIsOtpVerified(false)
      setFeedback({ type: 'success', message: 'Sale submitted for Sub Editor review.' })
    } catch (error) {
      setFeedback({ type: 'error', message: error.message })
    } finally {
      setIsSubmitting(false)
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
                <th scope="col">Status</th>
                <th scope="col">Incentive</th>
                <th scope="col">Review Note</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="6"><div className="unlock-sales-empty"><strong>Loading sales…</strong></div></td></tr>
              ) : loadError ? (
                <tr><td colSpan="6"><div className="unlock-sales-empty"><strong>Sales could not be loaded.</strong></div></td></tr>
              ) : sales.length ? (
                sales.map((sale) => (
                  <tr key={sale.id}>
                    <td>{displayDate(sale.createdAt)}</td>
                    <td>{sale.customerReference}</td>
                    <td>{formatUsdt(sale.packageAmount)}</td>
                    <td><span className={`sales-status-badge is-${sale.status}`}>{sale.status}</span></td>
                    <td>{sale.incentiveAmount === null ? '—' : formatUsdt(sale.incentiveAmount)}</td>
                    <td>{sale.rejectionReason || (sale.status === 'pending' ? 'Awaiting Sub Editor review' : '—')}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="6">
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
        <div className="modal-backdrop assignment-modal-backdrop" onClick={closeGenerateModal}>
          <section
            className="assignment-modal unlock-token-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="unlock-token-modal-title"
            onClick={(event) => event.stopPropagation()}
          >
            <header className="assignment-modal-heading">
              <div>
                <span className="mini-label">Arowin Global lookup</span>
                <h2 id="unlock-token-modal-title">Customer Purchase</h2>
                <p>Search the customer in Arowin Global to load their name and activated package.</p>
              </div>
              <button type="button" className="close-btn" aria-label="Close" onClick={closeGenerateModal}>×</button>
            </header>

            <form className="unlock-token-form" onSubmit={submitSale}>
              <label>
                <span>Customer ID</span>
                <input
                  type="text"
                  value={customerId}
                  onChange={(event) => setCustomerId(event.target.value)}
                  placeholder="Enter Arowin Global customer ID"
                  maxLength={80}
                  required
                />
              </label>

              <label>
                <span>Customer Name</span>
                <input type="text" value="" placeholder="Available after Arowin Global lookup" readOnly />
              </label>

              <label>
                <span>Activated Package</span>
                <input type="text" value="" placeholder="Loaded from Arowin Global" readOnly />
              </label>

              <p className="unlock-integration-notice" role="status">
                Arowin Global customer lookup is not connected yet. The customer name and activated package will load from Global after its API is live.
              </p>

              <label>
                <span>Employee email</span>
                <input type="email" value={user?.email || ''} readOnly autoComplete="email" />
              </label>

              <div className="unlock-token-otp-row">
                <label>
                  <span>Email OTP</span>
                  <input
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    placeholder="Enter 6-digit OTP"
                    value={otp}
                    onChange={(event) => setOtp(event.target.value.replace(/\D/g, '').slice(0, 6))}
                    disabled={!isOtpSent || isOtpVerified}
                  />
                </label>
                <button
                  type="button"
                  className="unlock-send-otp-button"
                  onClick={verifyOtp}
                  disabled={!isOtpSent || otp.length !== 6 || isVerifyingOtp || isOtpVerified}
                >
                  {isVerifyingOtp ? 'Verifying…' : isOtpVerified ? 'Verified' : 'Verify OTP'}
                </button>
              </div>
              <button
                type="button"
                className="unlock-send-otp-button"
                onClick={sendOtp}
                disabled={!user?.email || isSendingOtp || isOtpVerified}
              >
                {isSendingOtp ? 'Sending…' : isOtpSent ? 'Resend OTP' : 'Send OTP'}
              </button>

              {feedback.message && (
                <p className={`unlock-otp-feedback ${feedback.type}`} role={feedback.type === 'error' ? 'alert' : 'status'}>
                  {feedback.message}
                </p>
              )}
              <button
                type="submit"
                className="unlock-confirm-token-button"
                disabled={!isOtpVerified || !customerId.trim() || isSubmitting}
              >
                <Icon name="plus" size={17} /> {isSubmitting ? 'Submitting…' : 'Submit for Approval'}
              </button>
            </form>
          </section>
        </div>
      )}
    </div>
  )
}
