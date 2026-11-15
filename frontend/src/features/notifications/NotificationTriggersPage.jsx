import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'

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

export default function NotificationTriggersPage() {
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [savingEventKey, setSavingEventKey] = useState('')

  useEffect(() => {
    let active = true
    apiRequest('/notifications')
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
  }, [])

  const updateTrigger = async (eventKey, enabled) => {
    setSavingEventKey(eventKey)
    setError('')
    try {
      const response = await apiRequest('/notifications/triggers', {
        method: 'POST',
        body: { eventKey, enabled },
      })
      setResult((current) => ({
        ...current,
        triggers: current.triggers.map((trigger) =>
          trigger.eventKey === eventKey ? response.trigger : trigger,
        ),
      }))
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setSavingEventKey('')
    }
  }

  return (
    <section className="sub-editor-feature-page notification-triggers-page">
      <span className="mini-label">Sub Editor Workspace</span>
      <h1>Notification Triggers</h1>
      <p className="sub-editor-feature-description">Choose which employee events create in-app alerts for Sub Editors.</p>
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <article className="dashboard-panel notification-trigger-panel">
        <div className="panel-title"><h2>Active event triggers</h2></div>
        {isLoading ? (
          <p className="sub-editor-feature-empty" role="status">Loading notification triggers…</p>
        ) : result?.triggers.map((trigger) => (
          <label className="notification-trigger-row" key={trigger.eventKey}>
            <span>
              <strong>{trigger.label}</strong>
              <small>{trigger.enabled ? 'In-app notifications are enabled' : 'No in-app notifications will be created'}</small>
            </span>
            <input
              type="checkbox"
              checked={trigger.enabled}
              disabled={savingEventKey !== ''}
              onChange={(event) => updateTrigger(trigger.eventKey, event.target.checked)}
            />
          </label>
        ))}
      </article>

      <article className="dashboard-panel sub-editor-feature-table-panel notification-history-panel">
        <div className="panel-title">
          <div>
            <h2>Recent notifications</h2>
            <small>{isLoading ? 'Loading…' : `${result?.unreadCount || 0} unread`}</small>
          </div>
        </div>
        {isLoading ? (
          <p className="sub-editor-feature-empty" role="status">Loading notifications…</p>
        ) : result?.notifications.length ? (
          <div className="notification-trigger-list">
            {result.notifications.map((notification) => (
              <article className={`notification-trigger-item${notification.isRead ? '' : ' is-unread'}`} key={`${notification.eventKey}-${notification.entityId}`}>
                <div>
                  <strong>{notification.title}</strong>
                  <p>{notification.message}</p>
                </div>
                <time dateTime={notification.occurredAt}>{displayDate(notification.occurredAt)}</time>
              </article>
            ))}
          </div>
        ) : (
          <p className="sub-editor-feature-empty">No notifications match the enabled triggers.</p>
        )}
      </article>
    </section>
  )
}
