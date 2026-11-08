import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../../api'

const todayParts = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'Asia/Kolkata',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
}).formatToParts(new Date())
const todayInIndia = `${todayParts.find((part) => part.type === 'year').value}-${todayParts.find((part) => part.type === 'month').value}-${todayParts.find((part) => part.type === 'day').value}`

const dateFormatter = new Intl.DateTimeFormat('en-IN', {
  timeZone: 'Asia/Kolkata',
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})

const leaveTypes = [
  { value: 'casual', label: 'Casual Leave' },
  { value: 'sick', label: 'Sick Leave' },
  { value: 'emergency', label: 'Emergency Leave' },
  { value: 'personal', label: 'Personal Leave' },
]
const REQUESTS_PER_PAGE = 8
const statusFilters = ['all', 'pending', 'approved', 'rejected']

function formatLeaveDate(value) {
  return dateFormatter.format(new Date(`${value}T12:00:00+05:30`))
}

function formatLeaveType(value) {
  return leaveTypes.find((type) => type.value === value)?.label || value
}

export default function LeaveManagementPage({ user }) {
  const isSubEditor = user.role === 'sub_editor'
  const [requests, setRequests] = useState([])
  const [leaveType, setLeaveType] = useState('casual')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [reason, setReason] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [busyRequestId, setBusyRequestId] = useState(null)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [activeFilter, setActiveFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [currentPage, setCurrentPage] = useState(1)

  const statusCounts = useMemo(() => requests.reduce((counts, request) => {
    counts[request.status] = (counts[request.status] || 0) + 1
    return counts
  }, { all: requests.length, pending: 0, approved: 0, rejected: 0 }), [requests])

  const filteredRequests = useMemo(() => {
    const query = search.trim().toLowerCase()
    return requests.filter((request) => {
      if (activeFilter !== 'all' && request.status !== activeFilter) return false
      if (!query) return true
      return [
        request.employeeName,
        request.employeeId,
        request.role,
        request.leaveType,
        request.reason,
        request.startDate,
        request.endDate,
      ].filter(Boolean).join(' ').toLowerCase().includes(query)
    })
  }, [activeFilter, requests, search])

  const pageCount = Math.max(1, Math.ceil(filteredRequests.length / REQUESTS_PER_PAGE))
  const visibleRequests = filteredRequests.slice(
    (currentPage - 1) * REQUESTS_PER_PAGE,
    currentPage * REQUESTS_PER_PAGE,
  )

  const changeFilter = (filter) => {
    setActiveFilter(filter)
    setCurrentPage(1)
  }

  const changeSearch = (value) => {
    setSearch(value)
    setCurrentPage(1)
  }

  const loadRequests = async () => {
    try {
      const result = await apiRequest('/leave-requests')
      setRequests(result.requests)
      setError('')
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    let active = true
    apiRequest('/leave-requests')
      .then((result) => {
        if (active) {
          setRequests(result.requests)
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
  }, [])

  const submitRequest = async (event) => {
    event.preventDefault()
    setError('')
    setMessage('')
    setIsSubmitting(true)
    try {
      await apiRequest('/leave-requests', {
        method: 'POST',
        body: { leaveType, startDate, endDate, reason },
      })
      setLeaveType('casual')
      setStartDate('')
      setEndDate('')
      setReason('')
      setMessage('Leave request submitted. The Sub Editor will review it.')
      await loadRequests()
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const decideRequest = async (requestId, decision) => {
    setError('')
    setMessage('')
    setBusyRequestId(requestId)
    try {
      await apiRequest(`/leave-requests/${requestId}/decision`, {
        method: 'POST',
        body: { decision },
      })
      setMessage(`Leave request ${decision}.`)
      setCurrentPage(1)
      await loadRequests()
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setBusyRequestId(null)
    }
  }

  return (
    <div className="leave-page">
      <header className="leave-page-heading">
        <span className="mini-label">{isSubEditor ? 'Sub Editor Workspace' : `${user.role === 'team_leader' ? 'Team Leader' : 'Telecaller'} Workspace`}</span>
        <h1>{isSubEditor ? 'Leave Requests' : 'Apply for Leave'}</h1>
        <p>{isSubEditor
          ? 'Review and approve or reject employee leave requests.'
          : 'Submit your requested dates and check the approval status here.'}</p>
      </header>

      {error && <p className="leave-feedback is-error" role="alert">{error}</p>}
      {message && <p className="leave-feedback is-success" role="status">{message}</p>}

      {!isSubEditor && (
        <form className="leave-form" onSubmit={submitRequest}>
          <div className="leave-form-heading">
            <div>
              <h2>Leave Application</h2>
              <p>Submit your leave request in a few steps.</p>
            </div>
            <span>Apply Leave</span>
          </div>
          <label className="leave-type-field">
            <span>Leave Type</span>
            <select
              value={leaveType}
              required
              onChange={(event) => setLeaveType(event.target.value)}
            >
              {leaveTypes.map((type) => (
                <option key={type.value} value={type.value}>{type.label}</option>
              ))}
            </select>
          </label>
          <label>
            <span>From Date</span>
            <input
              type="date"
              value={startDate}
              min={todayInIndia}
              aria-label="Select start date"
              required
              onChange={(event) => {
                setStartDate(event.target.value)
                if (endDate && event.target.value > endDate) setEndDate('')
              }}
            />
          </label>
          <label>
            <span>To Date</span>
            <input
              type="date"
              value={endDate}
              min={startDate || todayInIndia}
              aria-label="Select end date"
              required
              onChange={(event) => setEndDate(event.target.value)}
            />
          </label>
          <label className="leave-reason-field">
            <span>Reason</span>
            <textarea
              value={reason}
              maxLength={500}
              rows={3}
              required
              placeholder="Enter leave reason..."
              onChange={(event) => setReason(event.target.value)}
            />
          </label>
          <button type="submit" className="leave-submit-button" disabled={isSubmitting}>
            {isSubmitting ? 'Submitting…' : 'Submit Leave Request'}
          </button>
        </form>
      )}

      <section className="leave-requests-section" aria-labelledby="leave-requests-title">
        <div className="leave-list-heading">
          <div>
            <h2 id="leave-requests-title">{isSubEditor ? 'Employee requests' : 'My leave requests'}</h2>
            <p>{isSubEditor ? 'Review requests from your team in one place.' : 'Pending requests can be reviewed by the Sub Editor.'}</p>
          </div>
          <span>{requests.length} request{requests.length === 1 ? '' : 's'}</span>
        </div>

        {isSubEditor && !isLoading && requests.length > 0 && (
          <>
            <div className="leave-summary-grid" aria-label="Leave request summary">
              {statusFilters.map((filter) => (
                <article className={`leave-summary-card is-${filter}`} key={filter}>
                  <span>{filter === 'all' ? 'Total requests' : `${filter[0].toUpperCase()}${filter.slice(1)}`}</span>
                  <strong>{statusCounts[filter]}</strong>
                </article>
              ))}
            </div>
            <div className="leave-review-toolbar">
              <label className="leave-search">
                <span className="visually-hidden">Search leave requests</span>
                <input
                  type="search"
                  placeholder="Search name, employee ID, reason..."
                  value={search}
                  onChange={(event) => changeSearch(event.target.value)}
                />
              </label>
              <div className="leave-filter-tabs" role="group" aria-label="Filter leave requests by status">
                {statusFilters.map((filter) => (
                  <button
                    type="button"
                    key={filter}
                    className={activeFilter === filter ? 'is-active' : ''}
                    aria-pressed={activeFilter === filter}
                    onClick={() => changeFilter(filter)}
                  >
                    {filter[0].toUpperCase()}{filter.slice(1)}
                    <span>{statusCounts[filter]}</span>
                  </button>
                ))}
              </div>
            </div>
          </>
        )}

        {isLoading ? (
          <p className="leave-empty-state" role="status">Loading leave requests…</p>
        ) : requests.length === 0 ? (
          <p className="leave-empty-state">{isSubEditor ? 'No leave requests have been submitted.' : 'You have not applied for leave yet.'}</p>
        ) : filteredRequests.length === 0 ? (
          <p className="leave-empty-state">No requests match this search or status filter.</p>
        ) : (
          <>
            <div className={`leave-request-list${isSubEditor ? ' is-review-list' : ''}`}>
            {visibleRequests.map((request) => (
              <article className="leave-request-card" key={request.id}>
                <div className="leave-request-topline">
                  <div>
                    <strong className="leave-request-type">{formatLeaveType(request.leaveType)}</strong>
                    {isSubEditor && (
                      <strong className="leave-employee-name">
                        {request.employeeName} <span>· {request.role.replace('_', ' ')}</span>
                      </strong>
                    )}
                    <p className="leave-request-dates">
                      {formatLeaveDate(request.startDate)}
                      {request.startDate !== request.endDate && <> – {formatLeaveDate(request.endDate)}</>}
                    </p>
                  </div>
                  <span className={`leave-status is-${request.status}`}>{request.status}</span>
                </div>
                {isSubEditor && <p className="leave-employee-id">Employee ID: {request.employeeId}</p>}
                <p className="leave-request-reason">{request.reason}</p>
                {request.reviewerName && (
                  <p className="leave-reviewed-by">Reviewed by {request.reviewerName}</p>
                )}
                {isSubEditor && request.status === 'pending' && (
                  <div className="leave-decision-actions">
                    <button
                      type="button"
                      className="leave-approve-button"
                      disabled={busyRequestId !== null}
                      onClick={() => decideRequest(request.id, 'approved')}
                    >
                      {busyRequestId === request.id ? 'Saving…' : 'Approve'}
                    </button>
                    <button
                      type="button"
                      className="leave-reject-button"
                      disabled={busyRequestId !== null}
                      onClick={() => decideRequest(request.id, 'rejected')}
                    >
                      Reject
                    </button>
                  </div>
                )}
              </article>
            ))}
            </div>
            {isSubEditor && (
              <div className="leave-pagination">
                <span>
                  Showing {(currentPage - 1) * REQUESTS_PER_PAGE + 1}–{Math.min(currentPage * REQUESTS_PER_PAGE, filteredRequests.length)} of {filteredRequests.length}
                </span>
                <div>
                  <button
                    type="button"
                    disabled={currentPage === 1}
                    onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                  >
                    Previous
                  </button>
                  <span>Page {currentPage} of {pageCount}</span>
                  <button
                    type="button"
                    disabled={currentPage >= pageCount}
                    onClick={() => setCurrentPage((page) => Math.min(pageCount, page + 1))}
                  >
                    Next
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </section>
    </div>
  )
}
