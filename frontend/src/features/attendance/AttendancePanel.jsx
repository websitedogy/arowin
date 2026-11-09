import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import AttendanceSelfieModal from './AttendanceSelfieModal'
import AttendanceHistory from './AttendanceHistory'
import formatDateTime from '../../utils/formatDateTime'
import { formatCurrency } from '../../utils/formatters'

const currency = formatCurrency

const formatTime = (dateTime) => formatDateTime(dateTime, 'en-IN', {
  hour: 'numeric',
  minute: '2-digit',
  timeZone: 'Asia/Kolkata',
}, { fallback: '', assumedOffset: '+05:30' })

const MAX_LOCATION_ACCURACY_METERS = 200
const LOCATION_MAX_AGE_MS = 15000
const LOCATION_TIMEOUT_MS = 8000

function getCurrentPosition() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Location access is not available in this browser.'))
      return
    }

    navigator.geolocation.getCurrentPosition(
      (position) => resolve({
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
      }),
      (error) => reject(new Error(error.code === error.PERMISSION_DENIED
        ? 'Allow location access in your browser to mark attendance.'
        : 'Could not get your live location quickly. Check GPS and try again.')),
      { enableHighAccuracy: true, maximumAge: LOCATION_MAX_AGE_MS, timeout: LOCATION_TIMEOUT_MS },
    )
  })
}

export default function AttendancePanel({ compact = false }) {
  const [attendance, setAttendance] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [action, setAction] = useState('')
  const [position, setPosition] = useState(null)
  const [isLocating, setIsLocating] = useState(false)
  const [locationDiagnostics, setLocationDiagnostics] = useState(null)
  const [historyRefresh, setHistoryRefresh] = useState(0)
  const [serverClock, setServerClock] = useState(null)
  const [clockTick, setClockTick] = useState(() => Date.now())

  useEffect(() => {
    const timer = window.setInterval(() => setClockTick(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [])

  useEffect(() => {
    let active = true
    const loadAttendance = () => {
      apiRequest('/attendance/me')
        .then((result) => {
          if (active) {
            setAttendance(result.attendance)
            setServerClock({ serverTime: result.attendance.serverTime, syncedAt: Date.now() })
            setError('')
          }
        })
        .catch((requestError) => {
          if (active) setError(requestError.message)
        })
        .finally(() => {
          if (active) setIsLoading(false)
        })
    }

    loadAttendance()
    const timer = window.setInterval(loadAttendance, 60000)
    return () => {
      active = false
      window.clearInterval(timer)
    }
  }, [])

  const beginAttendanceAction = async (nextAction) => {
    setError('')
    setLocationDiagnostics(null)
    setIsLocating(true)
    let livePosition = null
    try {
      livePosition = await getCurrentPosition()
      setLocationDiagnostics(livePosition)
      if (livePosition.accuracy > MAX_LOCATION_ACCURACY_METERS) {
        throw new Error(
          `GPS accuracy is too low (±${Math.round(livePosition.accuracy)} m) to verify the 200 m office boundary. Turn on Precise location and try again near a window or outdoors.`,
        )
      }
      await apiRequest('/attendance/location-check', {
        method: 'POST',
        body: livePosition,
      })
      setPosition(livePosition)
      setAction(nextAction)
    } catch (locationError) {
      if (livePosition) setLocationDiagnostics(livePosition)
      const accuracyMessage = livePosition && !locationError.message.includes('GPS accuracy is too low')
        ? ` Device GPS accuracy was about ±${Math.round(livePosition.accuracy)} m.`
        : ''
      setError(`${locationError.message}${accuracyMessage}`)
    } finally {
      setIsLocating(false)
    }
  }

  const submitAttendance = async (selfie) => {
    const result = await apiRequest(`/attendance/${action}`, {
      method: 'POST',
      body: { ...position, selfie },
    })
    setAttendance(result.attendance)
    setServerClock({ serverTime: result.attendance.serverTime, syncedAt: Date.now() })
    setHistoryRefresh((value) => value + 1)
    setAction('')
    setPosition(null)
  }

  const today = attendance?.today
  const checkedIn = Boolean(today?.checkedInAt)
  const checkedOut = Boolean(today?.checkedOutAt)
  const nowInOfficeTime = serverClock?.serverTime
    ? new Date(Date.parse(serverClock.serverTime) + clockTick - serverClock.syncedAt).toLocaleTimeString('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23',
      timeZone: 'Asia/Kolkata',
    })
    : ''
  const checkInFrom = attendance?.checkInFrom || '00:00:00'
  const checkInUntil = attendance?.checkInUntil || '10:30:00'
  const checkInOpen = nowInOfficeTime >= checkInFrom && nowInOfficeTime < checkInUntil
  const isPaidHolidayToday = Boolean(attendance?.todayIsHoliday || attendance?.todayIsSunday)
  const isOnApprovedLeaveToday = Boolean(attendance?.todayIsApprovedLeave)
  const checkoutOpen = nowInOfficeTime >= (attendance?.checkoutFrom || '17:30')
  const progress = attendance?.monthlySalary > 0
    ? Math.min(100, (attendance.earned / attendance.monthlySalary) * 100)
    : 0

  return (
    <article className={`attendance-panel${compact ? ' is-compact' : ''}`}>
      <div className="attendance-panel-heading">
        <div className="attendance-panel-title">
          <span className="attendance-panel-icon"><Icon name="calendar" size={21} /></span>
          <div><h2>Attendance & Salary</h2><p>Check-in: 12:00 AM–10:30 AM · Check-out: 5:30 PM · IST</p></div>
        </div>
        {attendance?.today?.checkedInAt && (
          <span className={`attendance-state${checkedOut ? ' is-complete' : ''}`}>
            {checkedOut ? 'Shift complete' : 'Checked in'}
          </span>
        )}
      </div>

      {error && (
        <div className="attendance-error-details">
          <p className="budget-error attendance-error" role="alert">{error}</p>
          {locationDiagnostics && (
            <p className="attendance-location-diagnostic">
              <a
                href={`https://www.google.com/maps?q=${locationDiagnostics.latitude},${locationDiagnostics.longitude}`}
                target="_blank"
                rel="noreferrer"
              >
                View device location on Google Maps
              </a>
              {' · '}If the pin is at the office but the reported distance is still high, ask your Sub Editor to verify the fixed office pin.
            </p>
          )}
        </div>
      )}
      {isLoading ? (
        <p className="attendance-loading" role="status">Loading salary and attendance…</p>
      ) : !attendance ? (
        <p className="attendance-loading" role="status">Attendance information could not be loaded.</p>
      ) : (
        <>
          {!attendance.salaryConfigured && (
            <p className="attendance-setup-warning" role="status">Salary is not set yet. Ask your Sub Editor to set your joining date and salary.</p>
          )}
          {!attendance.officeConfigured && (
            <p className="attendance-setup-warning" role="status">Office location is not set yet. Ask your Sub Editor to configure the attendance geofence.</p>
          )}

          <div className="attendance-salary-scales">
            <div><span>Monthly salary</span><strong>{attendance.monthlySalary === null ? 'Not set' : currency(attendance.monthlySalary)}</strong></div>
            <div><span>Earned this month</span><strong>{attendance.earned === null ? 'Not set' : currency(attendance.earned)}</strong></div>
            <div><span>Daily activity bonus</span><strong>{currency(attendance.activityBonus)}</strong></div>
            <div><span>On-time bonus</span><strong>{currency(attendance.onTimeBonus ?? 0)}</strong></div>
            <div><span>Approved leave cut-off</span><strong>{attendance.leaveDeductions === null ? 'Not set' : currency(attendance.leaveDeductions)}</strong></div>
            <div><span>Cut-off · after late deductions</span><strong>{attendance.remainingPayable === null ? 'Not set' : currency(attendance.remainingPayable)}</strong></div>
          </div>
          <div
            className="attendance-progress-track"
            role="progressbar"
            aria-label="Salary earned this month"
            aria-valuemin="0"
            aria-valuemax={attendance.monthlySalary || 0}
            aria-valuenow={attendance.earned || 0}
          >
            <span className="attendance-progress-fill" style={{ width: `${progress}%` }} />
          </div>
          <div className="attendance-pay-summary">
            <small>
              {attendance.dailySalary === null
                ? 'Daily salary not available'
                : `${currency(attendance.dailySalary)} earned after a completed check-in and check-out; approved leave is unpaid, while Sundays and configured holidays are paid.`}
            </small>
          </div>

          <div className="attendance-policy-note">
            <Icon name="clock" size={17} />
            <span>Check in by 9:30 AM to earn the configured on-time bonus. Check in by 9:45 AM to avoid a deduction. Check-ins from 9:46 AM to 10:29 AM are marked late; the ₹100 deduction is included in the cut-off. Approved leave and unattended workdays are unpaid. Sundays and Sub Editor holidays are paid and need no attendance. Attendance requires live location within {attendance.officeRadiusMeters} meters and a selfie.</span>
          </div>

          {isPaidHolidayToday ? (
            <div className="attendance-today-status">
              <span>Today: H · {attendance.todayHolidayName || 'Sunday · Paid holiday'}</span>
            </div>
          ) : isOnApprovedLeaveToday ? (
            <div className="attendance-today-status">
              <span>Today: Approved leave · Unpaid</span>
            </div>
          ) : today && (
            <div className="attendance-today-status">
              <span>Today: {formatTime(today.checkedInAt)}{today.checkedOutAt ? ` – ${formatTime(today.checkedOutAt)}` : ''}</span>
              {today.lateDeduction > 0 && <strong>Late check-in</strong>}
            </div>
          )}

          <div className="attendance-actions">
            {!checkedIn ? (
              <button
                type="button"
                className="attendance-action-button"
                onClick={() => beginAttendanceAction('check-in')}
                disabled={!attendance.salaryConfigured || !attendance.officeConfigured || !checkInOpen || isPaidHolidayToday || isOnApprovedLeaveToday || isLocating}
              >
                {isLocating
                  ? 'Getting live location…'
                  : isPaidHolidayToday
                    ? 'Paid holiday · No check-in needed'
                  : isOnApprovedLeaveToday
                    ? 'Approved leave · No check-in'
                  : nowInOfficeTime < checkInFrom
                    ? 'Check-in opens at 12:00 AM'
                    : nowInOfficeTime >= checkInUntil
                      ? 'Check-in closed at 10:30 AM'
                      : 'Check in'}
              </button>
            ) : !checkedOut ? (
              <button
                type="button"
                className="attendance-action-button secondary"
                onClick={() => beginAttendanceAction('check-out')}
                disabled={!checkoutOpen || isLocating}
              >
                {isLocating ? 'Getting live location…' : checkoutOpen ? 'Check out' : 'Check-out opens at 5:30 PM'}
              </button>
            ) : <p className="attendance-complete-message">Attendance is locked for today. See you tomorrow.</p>}
          </div>
        </>
      )}

      {action && (
        <AttendanceSelfieModal
          action={action}
          onClose={() => {
            setAction('')
            setPosition(null)
          }}
          onSubmit={submitAttendance}
        />
      )}
      <AttendanceHistory initialMonth={attendance?.month} refreshToken={historyRefresh} />
    </article>
  )
}
