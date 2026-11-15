import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'

const usdCurrency = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })
const PAGE_SIZE = 10

function PaginationControls({ currentPage, pageCount, totalItems, label, onPageChange }) {
  const firstItem = totalItems === 0 ? 0 : (currentPage - 1) * PAGE_SIZE + 1
  const lastItem = Math.min(currentPage * PAGE_SIZE, totalItems)

  return (
    <nav className="emergency-pagination" aria-label={`${label} pagination`}>
      <span>Showing {firstItem}–{lastItem} of {totalItems}</span>
      <div>
        <button type="button" disabled={currentPage === 1} onClick={() => onPageChange(currentPage - 1)}>
          Previous
        </button>
        <span>Page {currentPage} of {pageCount}</span>
        <button type="button" disabled={currentPage === pageCount} onClick={() => onPageChange(currentPage + 1)}>
          Next
        </button>
      </div>
    </nav>
  )
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

function todayDate() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Kolkata',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date())
  const date = Object.fromEntries(parts.map((part) => [part.type, part.value]))
  return `${date.year}-${date.month}-${date.day}`
}

function daysUntil(expiryDate, currentDate) {
  const expiry = Date.parse(`${expiryDate}T00:00:00Z`)
  const today = Date.parse(`${currentDate}T00:00:00Z`)
  return Number.isNaN(expiry) || Number.isNaN(today)
    ? 0
    : Math.max(0, Math.round((expiry - today) / 86400000))
}

export default function EmergencySalesAssignPage({ employees = [], isLoadingEmployees = false, onNavigate }) {
  const [assignments, setAssignments] = useState([])
  const [employeePage, setEmployeePage] = useState(1)
  const [historyPage, setHistoryPage] = useState(1)
  const [today, setToday] = useState(todayDate)
  const [isLoadingAssignments, setIsLoadingAssignments] = useState(true)
  const [assignmentError, setAssignmentError] = useState('')
  const [assignmentTarget, setAssignmentTarget] = useState(null)
  const [assignmentAmount, setAssignmentAmount] = useState('')
  const [expiryDate, setExpiryDate] = useState('')
  const [assignmentNote, setAssignmentNote] = useState('')
  const [isAssigning, setIsAssigning] = useState(false)
  const [assignmentNotice, setAssignmentNotice] = useState('')

  const loadAssignments = async (showLoading = false) => {
    if (showLoading) setIsLoadingAssignments(true)
    try {
      const result = await apiRequest('/sales/emergency')
      setAssignments(result.result.assignments)
      setAssignmentError('')
    } catch (error) {
      setAssignmentError(error.message)
    } finally {
      setIsLoadingAssignments(false)
    }
  }

  useEffect(() => {
    let active = true
    apiRequest('/sales/emergency')
      .then((result) => {
        if (active) {
          setAssignments(result.result.assignments)
          setAssignmentError('')
        }
      })
      .catch((error) => {
        if (active) setAssignmentError(error.message)
      })
      .finally(() => {
        if (active) setIsLoadingAssignments(false)
      })

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    const refreshToday = () => setToday(todayDate())
    const timer = window.setInterval(refreshToday, 60000)
    window.addEventListener('focus', refreshToday)
    return () => {
      window.clearInterval(timer)
      window.removeEventListener('focus', refreshToday)
    }
  }, [])

  const hasActiveAssignment = (employee) => assignments.some(
    (assignment) => assignment.employeeId === String(employee.employeeId) && assignment.expiresAt >= today,
  )
  const assignableEmployees = employees.filter((employee) =>
    employee.status === 'Active'
    && ['team_leader', 'telecaller'].includes(employee.roleKey)
    && !hasActiveAssignment(employee),
  )
  const employeePageCount = Math.max(1, Math.ceil(assignableEmployees.length / PAGE_SIZE))
  const currentEmployeePage = Math.min(employeePage, employeePageCount)
  const visibleEmployees = assignableEmployees.slice(
    (currentEmployeePage - 1) * PAGE_SIZE,
    currentEmployeePage * PAGE_SIZE,
  )
  const historyPageCount = Math.max(1, Math.ceil(assignments.length / PAGE_SIZE))
  const currentHistoryPage = Math.min(historyPage, historyPageCount)
  const visibleAssignments = assignments.slice(
    (currentHistoryPage - 1) * PAGE_SIZE,
    currentHistoryPage * PAGE_SIZE,
  )

  const openAssignment = (employee) => {
    setAssignmentTarget(employee)
    setAssignmentAmount('')
    setExpiryDate('')
    setAssignmentNote('')
    setAssignmentNotice('')
    setAssignmentError('')
  }

  const closeAssignment = () => {
    setAssignmentTarget(null)
    setAssignmentAmount('')
    setExpiryDate('')
    setAssignmentNote('')
  }

  const submitAssignment = async (event) => {
    event.preventDefault()
    if (!assignmentTarget) return

    setIsAssigning(true)
    setAssignmentError('')
    setAssignmentNotice('')
    try {
      const result = await apiRequest('/sales/emergency', {
        method: 'POST',
        body: {
          employeeId: Number(assignmentTarget.id),
          packageAmount: assignmentAmount,
          expiryDate,
          note: assignmentNote,
        },
      })
      setAssignments((current) => [{
        ...result.assignment,
        employeeId: String(assignmentTarget.employeeId),
        employeeName: assignmentTarget.fullName,
        employeeRole: assignmentTarget.roleKey,
      }, ...current])
      closeAssignment()
      setAssignmentNotice('Emergency sales assigned successfully.')
      await loadAssignments(true)
    } catch (error) {
      setAssignmentError(error.message)
    } finally {
      setIsAssigning(false)
    }
  }

  return (
    <section className="sub-editor-feature-page sales-review-page">
      <header className="emergency-assign-page-heading">
        <div>
          <span className="mini-label">Sub Editor Workspace</span>
          <h1>Emergency Sales Assign</h1>
          <p className="sub-editor-feature-description">Select a Team Leader or Telecaller, then enter an amount, expiry date, and optional note.</p>
        </div>
        <button type="button" className="budget-secondary-button" onClick={() => onNavigate('/sales/emergency')}>
          Back to Emergency Sales
        </button>
      </header>

      {assignmentError && <p className="dashboard-data-error" role="alert">{assignmentError}</p>}
      {assignmentNotice && <p className="sales-success-message" role="status">{assignmentNotice}</p>}

      <article className="dashboard-panel sub-editor-feature-table-panel emergency-assignment-panel">
        <div className="panel-title">
          <div>
            <h2>Team Leaders and Telecallers</h2>
            <p>Click Assign on an employee row to create an emergency sales assignment.</p>
          </div>
          <span className="unlock-sales-record-count">{assignableEmployees.length} available</span>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table emergency-assignment-table">
            <thead>
              <tr><th>Employee ID</th><th>Employee</th><th>Role</th><th>Status</th><th>Assign</th></tr>
            </thead>
            <tbody>
              {isLoadingEmployees || isLoadingAssignments ? (
                <tr><td colSpan="5" className="sub-editor-feature-empty">Loading employees…</td></tr>
              ) : assignableEmployees.length ? visibleEmployees.map((employee) => (
                <tr key={employee.id}>
                  <td>{employee.employeeId}</td>
                  <td>{employee.fullName}</td>
                  <td>{employee.role}</td>
                  <td>{employee.status}</td>
                  <td>
                    <button
                      type="button"
                      className="emergency-package-assign-button"
                      disabled={isLoadingAssignments || hasActiveAssignment(employee)}
                      title={hasActiveAssignment(employee) ? 'Available after the current assignment expires.' : undefined}
                      onClick={() => openAssignment(employee)}
                    >
                      {hasActiveAssignment(employee) ? 'Assigned' : 'Assign'}
                    </button>
                  </td>
                </tr>
              )) : (
                <tr><td colSpan="5" className="sub-editor-feature-empty">No active Team Leaders or Telecallers found.</td></tr>
              )}
            </tbody>
          </table>
        </div>
        <PaginationControls
          currentPage={currentEmployeePage}
          pageCount={employeePageCount}
          totalItems={assignableEmployees.length}
          label="Available employees"
          onPageChange={setEmployeePage}
        />
      </article>

      <article className="dashboard-panel sub-editor-feature-table-panel emergency-assignment-panel emergency-assigned-list">
        <div className="panel-title">
          <h2>Emergency Sales History</h2>
          <span className="unlock-sales-record-count">{assignments.length} records</span>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table emergency-assignment-table">
            <thead>
              <tr><th>Employee</th><th>Role</th><th>Sales ($)</th><th>Assigned Date</th><th>Expiry Date</th><th>Days Left</th><th>Note</th><th>Status</th></tr>
            </thead>
            <tbody>
              {isLoadingAssignments ? (
                <tr><td colSpan="8" className="sub-editor-feature-empty">Loading emergency sales history…</td></tr>
              ) : assignments.length ? visibleAssignments.map((assignment) => {
                const status = assignment.expiresAt >= today ? 'active' : 'expired'
                return (
                  <tr key={assignment.id}>
                    <td>{assignment.employeeName}<small className="sales-review-employee-id">{assignment.employeeId}</small></td>
                    <td>{assignment.employeeRole === 'team_leader' ? 'Team Leader' : 'Telecaller'}</td>
                    <td>{usdCurrency.format(Number(assignment.packageAmount))}</td>
                    <td>{displayDate(assignment.assignedAt)}</td>
                    <td>{displayDate(assignment.expiresAt)}</td>
                    <td>{daysUntil(assignment.expiresAt, today)}</td>
                    <td>{assignment.note || '—'}</td>
                    <td><span className={`sales-status-badge is-${status}`}>{status}</span></td>
                  </tr>
                )
              }) : (
                <tr><td colSpan="8" className="sub-editor-feature-empty">No emergency sales have been assigned yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
        <PaginationControls
          currentPage={currentHistoryPage}
          pageCount={historyPageCount}
          totalItems={assignments.length}
          label="Emergency sales history"
          onPageChange={setHistoryPage}
        />
      </article>

      {assignmentTarget && (
        <div className="budget-modal-backdrop" role="presentation" onMouseDown={(event) => {
          if (event.target === event.currentTarget && !isAssigning) closeAssignment()
        }}>
          <section className="budget-modal emergency-assignment-modal" role="dialog" aria-modal="true" aria-labelledby="emergency-assignment-title">
            <header className="budget-modal-heading">
              <div>
                <span className="mini-label">Emergency Sales</span>
                <h2 id="emergency-assignment-title">Assign Emergency Sales</h2>
                <p>{assignmentTarget.fullName} · {assignmentTarget.role} · {assignmentTarget.employeeId}</p>
              </div>
              <button type="button" className="budget-modal-close" onClick={closeAssignment} disabled={isAssigning} aria-label="Close">×</button>
            </header>
            <form onSubmit={submitAssignment}>
              <label className="budget-form-field">
                <span>Sales amount ($)</span>
                <div className="budget-amount-input">
                  <span>$</span>
                  <input
                    type="number"
                    min="0.01"
                    max="99999999.99"
                    step="0.01"
                    value={assignmentAmount}
                    onChange={(event) => setAssignmentAmount(event.target.value)}
                    placeholder="Enter sales amount"
                    required
                  />
                </div>
              </label>
              <label className="budget-form-field">
                <span>Expiry date</span>
                <input
                  type="date"
                  min={todayDate()}
                  value={expiryDate}
                  onChange={(event) => setExpiryDate(event.target.value)}
                  required
                />
              </label>
              <label className="budget-form-field">
                <span>Note (optional)</span>
                <textarea
                  maxLength="500"
                  value={assignmentNote}
                  onChange={(event) => setAssignmentNote(event.target.value)}
                  placeholder="Add a note for the employee"
                />
              </label>
              <footer className="budget-modal-actions">
                <button type="button" className="budget-secondary-button" onClick={closeAssignment} disabled={isAssigning}>Cancel</button>
                <button type="submit" className="sales-approve-button" disabled={isAssigning || !assignmentAmount || !expiryDate}>
                  {isAssigning ? 'Assigning…' : 'Submit assignment'}
                </button>
              </footer>
            </form>
          </section>
        </div>
      )}
    </section>
  )
}
