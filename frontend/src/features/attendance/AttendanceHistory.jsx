import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import { formatCurrency } from '../../utils/formatters'

const currency = (amount) => amount === null ? '—' : formatCurrency(amount)

const formatTime = (dateTime) => dateTime
  ? new Intl.DateTimeFormat('en-IN', {
    hour: 'numeric',
    minute: '2-digit',
    timeZone: 'Asia/Kolkata',
  }).format(new Date(`${dateTime.replace(' ', 'T')}+05:30`))
  : '—'

function monthLabel(month) {
  const [year, value] = month.split('-').map(Number)
  return new Intl.DateTimeFormat('en-IN', { month: 'long', year: 'numeric' }).format(new Date(year, value - 1, 1))
}

function shiftMonth(month, offset) {
  const [year, value] = month.split('-').map(Number)
  const shifted = new Date(year, value - 1 + offset, 1)
  return `${shifted.getFullYear()}-${String(shifted.getMonth() + 1).padStart(2, '0')}`
}

const statusLabels = {
  present: 'Present',
  H: 'Holiday',
  incomplete: 'Incomplete',
  absent: 'Absent',
  pending: 'Pending',
  future: '—',
  'not-joined': '—',
}

export default function AttendanceHistory({ initialMonth, refreshToken }) {
  const [month, setMonth] = useState(initialMonth || '')
  const [history, setHistory] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(false)

  useEffect(() => {
    if (initialMonth) setMonth(initialMonth)
  }, [initialMonth])

  useEffect(() => {
    if (!month) return undefined

    let active = true
    setIsLoading(true)
    setError('')
    apiRequest(`/attendance/history?month=${encodeURIComponent(month)}`)
      .then((result) => {
        if (active) setHistory(result.history)
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
  }, [month, refreshToken])

  if (!month) return null

  const firstDay = new Date(Number(month.slice(0, 4)), Number(month.slice(5, 7)) - 1, 1)
  const leadingCells = (firstDay.getDay() + 6) % 7
  const currentMonth = initialMonth || month
  const canGoForward = month < currentMonth

  return (
    <section className="attendance-history">
      <div className="attendance-history-heading">
        <div>
          <span className="mini-label">Attendance record</span>
          <h2>History & Calendar</h2>
        </div>
        <div className="attendance-history-month-nav">
          <button type="button" onClick={() => setMonth((value) => shiftMonth(value, -1))} aria-label="Previous month">‹</button>
          <label className="attendance-history-month-picker">
            <span className="visually-hidden">Select attendance month and year</span>
            <input
              type="month"
              value={month}
              max={currentMonth}
              onChange={(event) => {
                if (event.target.value) setMonth(event.target.value)
              }}
            />
          </label>
          <button type="button" onClick={() => setMonth((value) => shiftMonth(value, 1))} disabled={!canGoForward} aria-label="Next month">›</button>
        </div>
      </div>

      {error && <p className="budget-error" role="alert">{error}</p>}
      {isLoading ? (
        <p className="attendance-loading" role="status">Loading attendance history…</p>
      ) : history ? (
        <>
          <div className="attendance-history-summary">
            <span>Completed work days <strong>{history.attendedDays}</strong></span>
            <span>Earned <strong>{currency(history.earned)}</strong></span>
            <span>Cut-off <strong>{currency(history.remainingPayable)}</strong></span>
          </div>

          <div className="attendance-calendar" aria-label={`${monthLabel(month)} attendance calendar`}>
            {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day) => (
              <span className="attendance-calendar-weekday" key={day}>{day}</span>
            ))}
            {Array.from({ length: leadingCells }, (_, index) => (
              <span className="attendance-calendar-blank" key={`blank-${index}`} />
            ))}
            {history.days.map((day) => (
              <div
                className={`attendance-calendar-day is-${day.status}`}
                key={day.date}
                title={`${day.date} · ${day.holidayName || statusLabels[day.status]}`}
                aria-label={`${day.date}: ${day.holidayName || statusLabels[day.status]}`}
              >
                <strong>{Number(day.date.slice(-2))}</strong>
                <span>{day.status === 'H' ? 'H' : statusLabels[day.status]}</span>
              </div>
            ))}
          </div>
          <div className="attendance-calendar-legend">
            <span><i className="is-present" /> Present</span>
            <span><i className="is-holiday" /> H · Paid holiday</span>
            <span><i className="is-incomplete" /> Incomplete</span>
            <span><i className="is-absent" /> Absent</span>
          </div>

          <div className="attendance-history-table-wrap">
            <table className="attendance-history-table">
              <thead>
                <tr><th>Date</th><th>Status</th><th>Check-in</th><th>Check-out</th><th>Late deduction</th><th>Earned</th></tr>
              </thead>
              <tbody>
                {history.days.map((day) => (
                  <tr key={day.date}>
                    <td>{new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short' }).format(new Date(`${day.date}T00:00:00`))}</td>
                    <td>
                      <span className={`attendance-history-status is-${day.status}`}>
                        {day.status === 'H' ? `H · ${day.holidayName || 'Sunday'}` : statusLabels[day.status]}
                      </span>
                    </td>
                    <td>{formatTime(day.checkInAt)}</td>
                    <td>{formatTime(day.checkOutAt)}</td>
                    <td>{day.lateDeduction ? currency(day.lateDeduction) : '—'}</td>
                    <td>{currency(day.earned)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      ) : null}
    </section>
  )
}
