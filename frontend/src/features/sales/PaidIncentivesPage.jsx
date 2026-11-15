import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import { formatUsdt } from '../../utils/formatters'

function currentMonth() {
  const today = new Date()
  return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`
}

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

export default function PaidIncentivesPage() {
  const [month, setMonth] = useState(currentMonth)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [payingEmployeeId, setPayingEmployeeId] = useState(null)

  const loadPayouts = async () => {
    setIsLoading(true)
    setError('')
    try {
      const response = await apiRequest(`/sales/incentives/paid?month=${encodeURIComponent(month)}`)
      setResult(response.result)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    let active = true
    apiRequest(`/sales/incentives/paid?month=${encodeURIComponent(month)}`)
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
  }, [month])

  const markPaid = async (employee) => {
    if (!window.confirm(`Mark ${employee.name}'s ${month} incentive of ${formatUsdt(employee.earnedAmount)} as paid?`)) return

    setPayingEmployeeId(employee.id)
    setError('')
    try {
      await apiRequest('/sales/incentives/paid', {
        method: 'POST',
        body: { employeeId: employee.id, month },
      })
      await loadPayouts()
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setPayingEmployeeId(null)
    }
  }

  return (
    <section className="sub-editor-feature-page paid-incentives-page">
      <span className="mini-label">Sub Editor Workspace</span>
      <h1>Incentive Paid</h1>
      <p className="sub-editor-feature-description">
        Review monthly incentives earned from approved sales and granted Team Leader rewards. Marking paid records one monthly payout snapshot.
      </p>

      <label className="sales-month-filter attendance-month-filter">
        Payout month
        <input
          type="month"
          value={month}
          onChange={(event) => {
            setIsLoading(true)
            setError('')
            setMonth(event.target.value)
          }}
        />
      </label>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <article className="dashboard-panel sub-editor-feature-table-panel">
        <div className="panel-title">
          <div>
            <h2>Employee Monthly Incentives</h2>
            <small>{isLoading ? 'Loading…' : `${result?.employees.length || 0} ${result?.employees.length === 1 ? 'employee' : 'employees'}`}</small>
          </div>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table sales-review-table">
            <thead>
              <tr><th>Employee</th><th>Role</th><th>Approved sales</th><th>Earned</th><th>Paid amount</th><th>Payment status</th><th>Action</th></tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="7" className="sub-editor-feature-empty">Loading monthly incentives…</td></tr>
              ) : error && !result ? (
                <tr><td colSpan="7" className="sub-editor-feature-empty">Monthly incentives could not be loaded.</td></tr>
              ) : result?.employees.length ? result.employees.map((employee) => (
                <tr key={employee.id}>
                  <td>{employee.name}<small className="sales-review-employee-id">{employee.employeeId}</small></td>
                  <td>{employee.role === 'team_leader' ? 'Team Leader' : 'Telecaller'}</td>
                  <td>{employee.approvedSales}</td>
                  <td>{formatUsdt(employee.earnedAmount)}</td>
                  <td>{employee.isPaid ? formatUsdt(employee.paidAmount) : '—'}</td>
                  <td>{employee.isPaid ? `Paid ${displayDate(employee.paidAt)}` : 'Unpaid'}</td>
                  <td>
                    <button
                      type="button"
                      className="secondary-btn"
                      disabled={employee.isPaid || payingEmployeeId !== null}
                      onClick={() => markPaid(employee)}
                    >
                      {payingEmployeeId === employee.id ? 'Saving…' : employee.isPaid ? 'Paid' : 'Mark paid'}
                    </button>
                  </td>
                </tr>
              )) : (
                <tr><td colSpan="7" className="sub-editor-feature-empty">No earned incentives for this month.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>
    </section>
  )
}
