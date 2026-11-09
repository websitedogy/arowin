import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'

const platforms = [
  'Instagram',
  'Facebook',
  'WhatsApp Status',
  'LinkedIn',
  'YouTube',
  'X (Twitter)',
  'Telegram',
  'Snapchat',
  'Pinterest',
  'Threads',
  'Reddit',
  'Other',
]
const PAGE_SIZE = 12
const statusFilters = ['all', 'pending', 'verified', 'rejected']
const statusLabels = { all: 'All', pending: 'Pending', verified: 'Accepted', rejected: 'Rejected' }
const roleLabels = { team_leader: 'Team Leader', telecaller: 'Telecaller' }

const timestampOptions = {
  dateStyle: 'medium',
  timeStyle: 'short',
  timeZone: 'Asia/Kolkata',
}

function formatTimestamp(value) {
  return formatDateTime(value, 'en-IN', timestampOptions, { assumedOffset: '+05:30' })
}

function PrivateScreenshot({ activityId, screenshot }) {
  const [imageUrl, setImageUrl] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    let objectUrl = ''
    apiRequest(`/daily-activities/${activityId}/screenshots/${screenshot.id}`, { responseType: 'blob' })
      .then((image) => {
        if (!active) return
        objectUrl = URL.createObjectURL(image)
        setImageUrl(objectUrl)
      })
      .catch((requestError) => {
        if (active) setError(requestError.message)
      })

    return () => {
      active = false
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [activityId, screenshot.id])

  if (error) return <p className="daily-activity-image-error" role="alert">{error}</p>
  if (!imageUrl) return <div className="daily-activity-image-loading" role="status">Loading screenshot…</div>

  return (
    <a className="daily-activity-screenshot" href={imageUrl} target="_blank" rel="noreferrer">
      <img src={imageUrl} alt={`Submitted screenshot: ${screenshot.name}`} loading="lazy" />
      <span>{screenshot.name}</span>
    </a>
  )
}

function PrivateVideo({ activityId, video }) {
  const [videoUrl, setVideoUrl] = useState('')
  const [error, setError] = useState('')
  const [shouldLoad, setShouldLoad] = useState(false)

  useEffect(() => {
    if (!shouldLoad) return undefined

    let active = true
    let objectUrl = ''
    apiRequest(`/daily-activities/${activityId}/videos/${video.id}`, { responseType: 'blob' })
      .then((file) => {
        if (!active) return
        objectUrl = URL.createObjectURL(file)
        setVideoUrl(objectUrl)
      })
      .catch((requestError) => {
        if (active) setError(requestError.message)
      })

    return () => {
      active = false
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [activityId, shouldLoad, video.id])

  if (error) return <p className="daily-activity-image-error" role="alert">{error}</p>
  if (!videoUrl && !shouldLoad) {
    return (
      <button type="button" className="daily-activity-video-load" onClick={() => setShouldLoad(true)}>
        Load video · {video.name}
      </button>
    )
  }
  if (!videoUrl) return <div className="daily-activity-image-loading" role="status">Loading video…</div>

  return (
    <div className="daily-activity-video">
      <video src={videoUrl} controls preload="metadata" aria-label={`Submitted YouTube Short: ${video.name}`} />
      <span>{video.name}</span>
    </div>
  )
}

export default function DailyActivitiesPage({ user }) {
  const isSubEditor = user.role === 'sub_editor'
  const [activities, setActivities] = useState([])
  const [platform, setPlatform] = useState(platforms[0])
  const [files, setFiles] = useState([])
  const [fileInputKey, setFileInputKey] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [busyActivityId, setBusyActivityId] = useState(null)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [activeFilter, setActiveFilter] = useState(isSubEditor ? 'pending' : 'all')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [showTaskModal, setShowTaskModal] = useState(false)
  const [rejectionTarget, setRejectionTarget] = useState(null)
  const [rejectionReason, setRejectionReason] = useState('')

  useEffect(() => {
    let active = true
    apiRequest('/daily-activities')
      .then((result) => {
        if (active) setActivities(result.activities)
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

  useEffect(() => {
    if (!showTaskModal && !rejectionTarget) return undefined

    const handleKeyDown = (event) => {
      if (event.key === 'Escape' && busyActivityId === null) {
        setShowTaskModal(false)
        setRejectionTarget(null)
      }
    }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [busyActivityId, rejectionTarget, showTaskModal])

  const counts = useMemo(() => activities.reduce((result, activity) => {
    result[activity.status] = (result[activity.status] || 0) + 1
    return result
  }, { all: activities.length, pending: 0, verified: 0, rejected: 0 }), [activities])

  const filteredActivities = useMemo(() => {
    const query = search.trim().toLowerCase()
    return activities.filter((activity) => {
      if (activeFilter !== 'all' && activity.status !== activeFilter) return false
      if (!query) return true
      return [
        activity.employeeName,
        activity.employeeId,
        activity.role,
        activity.platform,
        activity.submittedAt,
      ].filter(Boolean).join(' ').toLowerCase().includes(query)
    })
  }, [activeFilter, activities, search])

  const pageCount = Math.max(1, Math.ceil(filteredActivities.length / PAGE_SIZE))
  const visibleActivities = filteredActivities.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

  const selectFiles = (selectedFiles) => {
    const nextFiles = Array.from(selectedFiles)
    if (platform === 'YouTube') {
      if (nextFiles.length > 2) {
        setError('Upload up to 2 YouTube Short videos per submission.')
        setFiles([])
        setFileInputKey((key) => key + 1)
        return
      }
      if (nextFiles.some((file) => !['video/mp4', 'video/webm'].includes(file.type))) {
        setError('YouTube Shorts must be MP4 or WebM videos.')
        setFiles([])
        setFileInputKey((key) => key + 1)
        return
      }
      if (nextFiles.some((file) => file.size > 50_000_000)) {
        setError('Each YouTube Short video must be smaller than 50 MB.')
        setFiles([])
        setFileInputKey((key) => key + 1)
        return
      }
      setError('')
      setFiles(nextFiles)
      return
    }

    if (nextFiles.length > 5) {
      setError('Upload up to 5 screenshots per activity.')
      setFiles([])
      setFileInputKey((key) => key + 1)
      return
    }
    if (nextFiles.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) {
      setError('Screenshots must be JPEG, PNG, or WebP images.')
      setFiles([])
      setFileInputKey((key) => key + 1)
      return
    }
    if (nextFiles.some((file) => file.size > 4_000_000)) {
      setError('Each screenshot must be smaller than 4 MB.')
      setFiles([])
      setFileInputKey((key) => key + 1)
      return
    }
    setError('')
    setFiles(nextFiles)
  }

  const changePlatform = (value) => {
    setPlatform(value)
    setFiles([])
    setFileInputKey((key) => key + 1)
    setError('')
  }

  const submitActivity = async (event) => {
    event.preventDefault()
    if (files.length === 0) {
      setError('Upload at least one screenshot; screenshots are required.')
      return
    }

    setError('')
    setMessage('')
    setIsSubmitting(true)
    const body = new FormData()
    body.append('platform', platform)
    files.forEach((file) => body.append(platform === 'YouTube' ? 'videos[]' : 'screenshots[]', file))

    try {
      await apiRequest('/daily-activities', { method: 'POST', body })
      setFiles([])
      setFileInputKey((key) => key + 1)
      setMessage('Daily activity submitted successfully.')
      const result = await apiRequest('/daily-activities')
      setActivities(result.activities)
      setShowTaskModal(false)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const reviewActivity = async (activityId, decision, reason = '') => {
    setError('')
    setMessage('')
    setBusyActivityId(activityId)
    try {
      await apiRequest(`/daily-activities/${activityId}/decision`, {
        method: 'POST',
        body: { decision, reason },
      })
      setMessage(decision === 'approve' ? 'Activity accepted.' : 'Activity rejected.')
      const result = await apiRequest('/daily-activities')
      setActivities(result.activities)
      setPage(1)
      setRejectionTarget(null)
      setRejectionReason('')
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setBusyActivityId(null)
    }
  }

  const rejectActivity = async (event) => {
    event.preventDefault()
    if (!rejectionTarget || !rejectionReason.trim()) {
      setError('Enter a reason so the employee knows what to fix.')
      return
    }
    await reviewActivity(rejectionTarget.id, 'reject', rejectionReason)
  }

  const changeFilter = (value) => {
    setActiveFilter(value)
    setPage(1)
  }

  const changeSearch = (value) => {
    setSearch(value)
    setPage(1)
  }

  const submissionsTable = (
    <div className="daily-activity-table-wrap">
      <table className="daily-activity-table">
        <thead>
          <tr>
            <th>Date & time</th>
            <th>Platform</th>
            <th>Proof</th>
            <th>Status</th>
            <th>Review</th>
          </tr>
        </thead>
        <tbody>
          {activities.map((activity) => (
            <tr key={activity.id}>
              <td><time dateTime={`${activity.submittedAt.replace(' ', 'T')}+05:30`}>{formatTimestamp(activity.submittedAt)}</time></td>
              <td><strong>{activity.platform}</strong></td>
              <td>
                <div className="daily-activity-table-screenshots">
                  {activity.videos.map((video) => (
                    <PrivateVideo key={video.id} activityId={activity.id} video={video} />
                  ))}
                  {activity.screenshots.map((screenshot) => (
                    <PrivateScreenshot key={screenshot.id} activityId={activity.id} screenshot={screenshot} />
                  ))}
                </div>
              </td>
              <td>
                <span className={`daily-activity-status is-${activity.status}`}>
                  {statusLabels[activity.status]}
                </span>
              </td>
              <td>{activity.status === 'verified'
                ? `${activity.reviewerName || 'Sub Editor'}${activity.verifiedAt ? ` · ${formatTimestamp(activity.verifiedAt)}` : ''}`
                : activity.status === 'rejected'
                  ? <>{activity.reviewerName || 'Sub Editor'}{activity.rejectionReason ? ` · ${activity.rejectionReason}` : ''}</>
                  : 'Awaiting review'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )

  return (
    <section className="daily-activities-page">
      {isSubEditor ? (
        <header className="daily-activities-heading">
          <div>
            <span className="mini-label">Sub Editor Workspace</span>
            <h1>Daily Activity Review</h1>
            <p>Check the proof, then accept or reject.</p>
          </div>
          <span className="daily-activity-total">{activities.length} submission{activities.length === 1 ? '' : 's'}</span>
        </header>
      ) : (
        <div className="daily-activity-top-actions">
          <button
            type="button"
            className="daily-activity-today-button"
            onClick={() => setShowTaskModal(true)}
          >
            Today task
          </button>
        </div>
      )}

      {(isSubEditor || !showTaskModal) && error && <p className="daily-activity-feedback is-error" role="alert">{error}</p>}
      {(isSubEditor || !showTaskModal) && message && <p className="daily-activity-feedback is-success" role="status">{message}</p>}

      {!isSubEditor && (
        <>
          <section className="daily-activity-list-section" aria-labelledby="my-activity-history-title">
            <div className="daily-activity-list-heading">
              <div>
                <h2 id="my-activity-history-title">My activity history</h2>
                <p>Review your submitted platforms, screenshots, and video verification status.</p>
              </div>
              <span>{activities.length} submission{activities.length === 1 ? '' : 's'}</span>
            </div>
            {isLoading ? (
              <p className="daily-activity-empty" role="status">Loading activity history…</p>
            ) : error && activities.length === 0 ? (
              <p className="daily-activity-feedback is-error" role="alert">{error}</p>
            ) : activities.length === 0 ? (
              <p className="daily-activity-empty">You have not submitted any activities yet.</p>
            ) : submissionsTable}
          </section>
        </>
      )}

      {isSubEditor && <section className="daily-activity-list-section" aria-labelledby="daily-activity-list-title">
        <div className="daily-activity-filters daily-activity-review-tabs" role="group" aria-label="Filter by review status">
          {statusFilters.map((filter) => (
            <button
              type="button"
              key={filter}
              className={activeFilter === filter ? 'is-active' : ''}
              aria-pressed={activeFilter === filter}
              onClick={() => changeFilter(filter)}
            >
              {statusLabels[filter]}
              <span>{counts[filter]}</span>
            </button>
          ))}
        </div>
        <div className="daily-activity-list-heading">
          <div>
            <h2 id="daily-activity-list-title">{isSubEditor ? 'Employee submissions' : 'My submissions'}</h2>
            <p>{isSubEditor ? 'Review proof and leave a reason when rejecting.' : 'Review your submitted platforms, screenshots, and video verification status.'}</p>
          </div>
          {!isSubEditor && <span>{activities.length} submission{activities.length === 1 ? '' : 's'}</span>}
        </div>

        {isSubEditor && activities.length > 0 && (
          <div className="daily-activity-toolbar">
            <input
              type="search"
              aria-label="Search daily activities"
              placeholder="Search employee, ID, or platform..."
              value={search}
              onChange={(event) => changeSearch(event.target.value)}
            />
          </div>
        )}

        {isLoading ? (
          <p className="daily-activity-empty" role="status">Loading activity submissions…</p>
        ) : filteredActivities.length === 0 ? (
          <p className="daily-activity-empty">
            {activities.length === 0
              ? (isSubEditor ? 'No activity submissions yet.' : 'You have not submitted any activities yet.')
              : 'No activity submissions match this filter.'}
          </p>
        ) : (
          <>
            <div className="daily-activity-review-table-wrap">
              <table className="daily-activity-table daily-activity-review-table">
                <thead>
                  <tr>
                    <th>Employee</th>
                    <th>Platform</th>
                    <th>Submitted</th>
                    <th>Proof</th>
                    <th>Status</th>
                    <th>Review details</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {visibleActivities.map((activity) => (
                    <tr key={activity.id}>
                      <td className="daily-activity-review-employee">
                        <strong>{activity.employeeName}</strong>
                        <span>{roleLabels[activity.role] || activity.role} · ID {activity.employeeId}</span>
                      </td>
                      <td><strong>{activity.platform === 'YouTube' ? 'YouTube Shorts' : activity.platform}</strong></td>
                      <td>
                        <time dateTime={`${activity.submittedAt.replace(' ', 'T')}+05:30`}>
                          {formatTimestamp(activity.submittedAt)}
                        </time>
                      </td>
                      <td>
                        <div className="daily-activity-review-proof">
                          {activity.videos.map((video) => (
                            <PrivateVideo key={video.id} activityId={activity.id} video={video} />
                          ))}
                          {activity.screenshots.map((screenshot) => (
                            <PrivateScreenshot key={screenshot.id} activityId={activity.id} screenshot={screenshot} />
                          ))}
                        </div>
                      </td>
                      <td>
                        <span className={`daily-activity-status is-${activity.status}`}>
                          {statusLabels[activity.status]}
                        </span>
                      </td>
                      <td className="daily-activity-review-details">
                        {activity.status === 'pending' ? (
                          <span className="daily-activity-review-awaiting">Awaiting review</span>
                        ) : (
                          <>
                            <strong>{activity.status === 'verified' ? 'Accepted' : 'Rejected'}</strong>
                            <span>{activity.reviewerName || 'Sub Editor'}</span>
                            {activity.rejectionReason && <p>{activity.rejectionReason}</p>}
                            {(activity.reviewedAt || activity.verifiedAt) && (
                              <time dateTime={`${(activity.reviewedAt || activity.verifiedAt).replace(' ', 'T')}+05:30`}>
                                {formatTimestamp(activity.reviewedAt || activity.verifiedAt)}
                              </time>
                            )}
                          </>
                        )}
                      </td>
                      <td>
                        {activity.status === 'pending' ? (
                          <div className="daily-activity-review-actions">
                            <button
                              type="button"
                              className="daily-activity-approve"
                              disabled={busyActivityId !== null}
                              onClick={() => reviewActivity(activity.id, 'approve')}
                            >
                              {busyActivityId === activity.id ? 'Saving…' : 'Accept'}
                            </button>
                            <button
                              type="button"
                              className="daily-activity-reject"
                              disabled={busyActivityId !== null}
                              onClick={() => {
                                setRejectionTarget(activity)
                                setRejectionReason('')
                                setError('')
                              }}
                            >
                              Reject
                            </button>
                          </div>
                        ) : <span className="daily-activity-action-complete">Reviewed</span>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {isSubEditor && pageCount > 1 && (
              <nav className="daily-activity-pagination" aria-label="Activity submissions pages">
                <span>Showing {(page - 1) * PAGE_SIZE + 1}–{Math.min(page * PAGE_SIZE, filteredActivities.length)} of {filteredActivities.length}</span>
                <div>
                  <button type="button" disabled={page === 1} onClick={() => setPage((value) => Math.max(1, value - 1))}>Previous</button>
                  <span>Page {page} of {pageCount}</span>
                  <button type="button" disabled={page === pageCount} onClick={() => setPage((value) => Math.min(pageCount, value + 1))}>Next</button>
                </div>
              </nav>
            )}
          </>
        )}
      </section>}

      {isSubEditor && rejectionTarget && (
        <div
          className="daily-activity-modal-backdrop"
          role="presentation"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget && busyActivityId === null) setRejectionTarget(null)
          }}
        >
          <section
            className="daily-activity-modal is-reject-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reject-activity-title"
          >
            <header className="daily-activity-modal-heading">
              <div>
                <span className="mini-label">Review submission</span>
                <h2 id="reject-activity-title">Reject activity?</h2>
                <p>{rejectionTarget.employeeName} · {rejectionTarget.platform}</p>
              </div>
              <button
                type="button"
                className="daily-activity-modal-close"
                aria-label="Close rejection form"
                disabled={busyActivityId !== null}
                onClick={() => setRejectionTarget(null)}
              >
                ×
              </button>
            </header>
            <form className="daily-activity-reject-form" onSubmit={rejectActivity}>
              <label htmlFor="daily-activity-rejection-reason">Reason for rejection</label>
              <textarea
                id="daily-activity-rejection-reason"
                value={rejectionReason}
                onChange={(event) => setRejectionReason(event.target.value)}
                maxLength={500}
                placeholder="Briefly explain what needs to be corrected"
                required
                autoFocus
              />
              <div className="daily-activity-reject-form-footer">
                <span>{rejectionReason.trim().length}/500</span>
                <div>
                  <button
                    type="button"
                    className="daily-activity-cancel"
                    disabled={busyActivityId !== null}
                    onClick={() => setRejectionTarget(null)}
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="daily-activity-reject-confirm"
                    disabled={busyActivityId !== null || !rejectionReason.trim()}
                  >
                    {busyActivityId === rejectionTarget.id ? 'Rejecting…' : 'Confirm rejection'}
                  </button>
                </div>
              </div>
            </form>
            {error && <p className="daily-activity-feedback is-error" role="alert">{error}</p>}
          </section>
        </div>
      )}

      {!isSubEditor && showTaskModal && (
        <div
          className="daily-activity-modal-backdrop"
          role="presentation"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) setShowTaskModal(false)
          }}
        >
          <section
            className="daily-activity-modal is-task-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="today-task-title"
          >
            <header className="daily-activity-modal-heading">
              <div>
                <span className="mini-label">Daily Activities</span>
                <h2 id="today-task-title">Today task</h2>
                <p>Choose a platform and upload screenshots of your activity.</p>
              </div>
              <button
                type="button"
                className="daily-activity-modal-close"
                aria-label="Close today task"
                onClick={() => setShowTaskModal(false)}
              >
                ×
              </button>
            </header>
            {error && <p className="daily-activity-feedback is-error" role="alert">{error}</p>}
            {message && <p className="daily-activity-feedback is-success" role="status">{message}</p>}
            <form className="daily-activity-form" onSubmit={submitActivity}>
              <div className="daily-activity-form-heading">
                <div>
                  <h2>Post activity</h2>
                  <p>{platform === 'YouTube'
                    ? 'Upload one or two Shorts videos for Sub Editor verification.'
                    : 'Attach at least one screenshot. Up to five are allowed.'}</p>
                </div>
              </div>
              <label>
                <span>Platform</span>
                <select value={platform} onChange={(event) => changePlatform(event.target.value)} required>
                  {platforms.map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
              </label>
              <label className="daily-activity-upload">
                <span>{platform === 'YouTube' ? 'Short video(s)' : 'Screenshot(s)'}
                  <strong>Required · up to {platform === 'YouTube' ? '2' : '5'}</strong>
                </span>
                <input
                  key={fileInputKey}
                  type="file"
                  accept={platform === 'YouTube' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp'}
                  multiple
                  required
                  onChange={(event) => selectFiles(event.target.files)}
                />
                <small>{platform === 'YouTube'
                  ? 'MP4 or WebM · up to 50 MB each'
                  : 'JPEG, PNG, or WebP · up to 4 MB each'}</small>
              </label>
              {files.length > 0 && (
                <ul className="daily-activity-selected-files" aria-label="Selected screenshots">
                  {files.map((file, index) => (
                    <li key={`${file.name}-${file.lastModified}-${index}`}>
                      <span>{file.name}</span>
                      <small>{(file.size / 1024 / 1024).toFixed(2)} MB</small>
                    </li>
                  ))}
                </ul>
              )}
              <p className="daily-activity-time-note">Date and time will be recorded automatically when submitted (IST).</p>
              <button type="submit" className="daily-activity-submit" disabled={isSubmitting || files.length === 0}>
                {isSubmitting ? 'Uploading screenshots…' : 'Submit activity'}
              </button>
            </form>
          </section>
        </div>
      )}
    </section>
  )
}
