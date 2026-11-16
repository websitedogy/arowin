import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'

const localToday = () => {
  const today = new Date()
  return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
}

export default function OfficeLocationSetup() {
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [holidays, setHolidays] = useState([])
  const [holidayDate, setHolidayDate] = useState(localToday)
  const [holidayName, setHolidayName] = useState('')
  const [holidayError, setHolidayError] = useState('')
  const [holidayNotice, setHolidayNotice] = useState('')
  const [isSavingHoliday, setIsSavingHoliday] = useState(false)

  useEffect(() => {
    let active = true
    apiRequest('/attendance/holidays')
      .then((result) => {
        if (!active) return
        setHolidays(result.holidays)
      })
      .catch((requestError) => {
        if (active) setLoadError(requestError.message)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  const saveHoliday = async (event) => {
    event.preventDefault()
    setHolidayError('')
    setHolidayNotice('')
    setIsSavingHoliday(true)
    try {
      const result = await apiRequest('/attendance/holidays', {
        method: 'POST',
        body: { date: holidayDate, name: holidayName },
      })
      setHolidays((current) => [...current.filter((holiday) => holiday.date !== result.holiday.date), result.holiday]
        .sort((first, second) => first.date.localeCompare(second.date)))
      setHolidayName('')
      setHolidayNotice('Paid holiday saved.')
    } catch (requestError) {
      setHolidayError(requestError.message)
    } finally {
      setIsSavingHoliday(false)
    }
  }

  const removeHoliday = async (date) => {
    setHolidayError('')
    setHolidayNotice('')
    try {
      await apiRequest(`/attendance/holidays/${date}`, { method: 'DELETE' })
      setHolidays((current) => current.filter((holiday) => holiday.date !== date))
      setHolidayNotice('Holiday removed.')
    } catch (requestError) {
      setHolidayError(requestError.message)
    }
  }

  return (
    <section className="office-location-page">
      <article className="dashboard-panel attendance-holiday-manager">
        <div className="panel-title">
          <Icon name="calendar" size={22} />
          <h2>Paid festival holidays</h2>
        </div>
        <p className="office-location-instructions">
          Sundays are automatically paid holidays. Add festival or other paid holidays here; employees do not need to check in or out on these dates.
        </p>
        {loadError && <p className="budget-error" role="alert">{loadError}</p>}
        {holidayError && <p className="budget-error" role="alert">{holidayError}</p>}
        {holidayNotice && <p className="office-location-success" role="status">{holidayNotice}</p>}
        <form className="attendance-holiday-form" onSubmit={saveHoliday}>
          <label>
            <span>Holiday date</span>
            <input type="date" value={holidayDate} onChange={(event) => setHolidayDate(event.target.value)} required />
          </label>
          <label>
            <span>Holiday name</span>
            <input
              type="text"
              maxLength="120"
              value={holidayName}
              onChange={(event) => setHolidayName(event.target.value)}
              placeholder="e.g. Diwali"
              required
            />
          </label>
          <button type="submit" className="primary-btn" disabled={isSavingHoliday}>
            {isSavingHoliday ? 'Saving…' : 'Save paid holiday'}
          </button>
        </form>
        <div className="attendance-holiday-list">
          {isLoading ? (
            <p className="attendance-loading" role="status">Loading paid holidays…</p>
          ) : loadError ? null : holidays.length ? holidays.map((holiday) => (
            <div className="attendance-holiday-row" key={holiday.date}>
              <span><strong>{holiday.name}</strong><small>{holiday.date}</small></span>
              <button type="button" className="secondary-btn" onClick={() => removeHoliday(holiday.date)}>Remove</button>
            </div>
          )) : <p className="attendance-loading">No festival holidays added yet.</p>}
        </div>
      </article>
    </section>
  )
}
