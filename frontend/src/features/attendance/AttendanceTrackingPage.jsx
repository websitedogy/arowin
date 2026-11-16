import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import { formatUsdt } from '../../utils/formatters'
import OfficeLocationSetup from './OfficeLocationSetup'

function currentMonth() {
  const today = new Date()
  return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`
}

function displayTime(value) {
  return value
    ? formatDateTime(value, undefined, {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      timeZone: 'Asia/Kolkata',
    }, { assumedOffset: '+05:30' })
    : 'Not checked out'
}

const attendanceTabs = [
  { id: 'attendance', label: 'Attendance Tracking' },
  { id: 'holidays', label: 'Paid Festivals' },
]

export default function AttendanceTrackingPage() {
  const [month, setMonth] = useState(currentMonth)
  const [report, setReport] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [activeTab, setActiveTab] = useState('attendance')

  useEffect(() => {
    let active = true
    apiRequest(`/attendance/report?month=${encodeURIComponent(month)}`)
      .then((response) => {
        if (active) setReport(response.report)
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

  return (
    <div className="attendance-management-page">
      <div className="attendance-management-tabs" role="group" aria-label="Attendance sections">
        {attendanceTabs.map((tab) => (
          <button
            type="button"
            key={tab.id}
            className={`attendance-management-tab${activeTab === tab.id ? ' active' : ''}`}
            aria-pressed={activeTab === tab.id}
            onClick={() => setActiveTab(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {activeTab === 'attendance' && (
        <section className="attendance-report-page">
          <h1>Employee Attendance</h1>
          <p className="sub-editor-feature-description">Review employee check-in, check-out, and attendance adjustments by month.</p>

          <label className="sales-month-filter attendance-month-filter">
            Report month
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
                <h2>Attendance Records</h2>
                <small>{isLoading ? 'Loading…' : `${report?.records.length || 0} ${report?.records.length === 1 ? 'record' : 'records'}`}</small>
              </div>
            </div>
            <div className="employee-table-wrap">
              <table className="employee-table sales-review-table">
                <thead>
                  <tr><th>Date</th><th>Employee</th><th>Role</th><th>Check-in</th><th>Check-out</th><th>Late deduction</th><th>On-time bonus</th></tr>
                </thead>
                <tbody>
                  {isLoading ? (
                    <tr><td colSpan="7" className="sub-editor-feature-empty">Loading attendance…</td></tr>
                  ) : error ? (
                    <tr><td colSpan="7" className="sub-editor-feature-empty">Attendance records could not be loaded.</td></tr>
                  ) : report?.records.length ? report.records.map((record) => (
                    <tr key={`${record.employeeId}-${record.date}`}>
                      <td>{record.date}</td>
                      <td>{record.employeeName}<small className="sales-review-employee-id">{record.employeeId}</small></td>
                      <td>{record.role === 'team_leader' ? 'Team Leader' : 'Telecaller'}</td>
                      <td>{displayTime(record.checkedInAt)}</td>
                      <td>{displayTime(record.checkedOutAt)}</td>
                      <td>{formatUsdt(record.lateDeduction)}</td>
                      <td>{formatUsdt(record.onTimeBonus)}</td>
                    </tr>
                  )) : (
                    <tr><td colSpan="7" className="sub-editor-feature-empty">No attendance records for this month.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </article>
        </section>
      )}

      {activeTab === 'holidays' && <OfficeLocationSetup section="holidays" />}
    </div>
  )
}
