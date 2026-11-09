import { useCallback, useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import Icon from '../dashboard/components/Icon'

const categories = [
  { value: 'account', label: 'Account & access' },
  { value: 'attendance', label: 'Attendance' },
  { value: 'salary', label: 'Salary & payroll' },
  { value: 'sales', label: 'Sales & incentives' },
  { value: 'technical', label: 'Technical issue' },
  { value: 'other', label: 'Other' },
]
const priorities = [
  { value: 'low', label: 'Low' },
  { value: 'normal', label: 'Normal' },
  { value: 'high', label: 'High' },
  { value: 'urgent', label: 'Urgent' },
]
const statuses = [
  { value: 'open', label: 'Open' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'waiting_on_employee', label: 'Waiting on employee' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'closed', label: 'Closed' },
]
const dateTimeOptions = {
  timeZone: 'Asia/Kolkata',
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
}

function formatDate(value) {
  return formatDateTime(value, 'en-IN', dateTimeOptions, { assumedOffset: '+05:30' })
}

function labelFor(options, value) {
  return options.find((option) => option.value === value)?.label || value
}

function ticketCode(id) {
  return `SUP-${String(id).padStart(5, '0')}`
}

export default function SupportTicketsPage({ user }) {
  const isSubEditor = user.role === 'sub_editor'
  const [tickets, setTickets] = useState([])
  const [selectedId, setSelectedId] = useState(null)
  const [selectedTicket, setSelectedTicket] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [showNewTicket, setShowNewTicket] = useState(false)
  const [subject, setSubject] = useState('')
  const [category, setCategory] = useState('technical')
  const [priority, setPriority] = useState('normal')
  const [description, setDescription] = useState('')
  const [reply, setReply] = useState('')
  const [replyStatus, setReplyStatus] = useState('automatic')
  const [ticketSearch, setTicketSearch] = useState('')
  const [ticketFilter, setTicketFilter] = useState('all')
  const activeTicket = selectedTicket?.id === selectedId ? selectedTicket : null
  const isLoadingTicket = Boolean(selectedId && !activeTicket)

  useEffect(() => {
    if (!showNewTicket) return undefined
    const closeOnEscape = (event) => {
      if (event.key === 'Escape' && !isSubmitting) setShowNewTicket(false)
    }
    window.addEventListener('keydown', closeOnEscape)
    return () => window.removeEventListener('keydown', closeOnEscape)
  }, [isSubmitting, showNewTicket])

  const loadTickets = useCallback(async () => {
    try {
      const result = await apiRequest('/support/tickets')
      setTickets(result.tickets)
      setError('')
      setSelectedId((current) => current && result.tickets.some((ticket) => ticket.id === current)
        ? current
        : result.tickets[0]?.id || null)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    let active = true
    apiRequest('/support/tickets')
      .then((result) => {
        if (!active) return
        setTickets(result.tickets)
        setSelectedId(result.tickets[0]?.id || null)
        setError('')
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
    if (!selectedId) return undefined
    let active = true
    apiRequest(`/support/tickets/${selectedId}`)
      .then((result) => {
        if (active) {
          setSelectedTicket(result.ticket)
          setError('')
        }
      })
      .catch((requestError) => {
        if (active) setError(requestError.message)
      })
    return () => {
      active = false
    }
  }, [selectedId])

  const counts = useMemo(() => tickets.reduce((summary, ticket) => {
    summary.total += 1
    if (['open', 'in_progress'].includes(ticket.status)) summary.active += 1
    if (ticket.status === 'waiting_on_employee') summary.waiting += 1
    if (ticket.status === 'resolved' || ticket.status === 'closed') summary.resolved += 1
    return summary
  }, { total: 0, active: 0, waiting: 0, resolved: 0 }), [tickets])

  const filteredTickets = useMemo(() => {
    const query = ticketSearch.trim().toLowerCase()
    return tickets.filter((ticket) => {
      const matchesFilter = ticketFilter === 'all'
        || (ticketFilter === 'active' && ['open', 'in_progress'].includes(ticket.status))
        || (ticketFilter === 'waiting' && ticket.status === 'waiting_on_employee')
        || (ticketFilter === 'resolved' && ['resolved', 'closed'].includes(ticket.status))
      const matchesSearch = !query
        || `${ticket.subject} ${ticket.employeeName} ${ticket.employeeId} ${ticket.category}`.toLowerCase().includes(query)
      return matchesFilter && matchesSearch
    })
  }, [ticketFilter, ticketSearch, tickets])

  const createTicket = async (event) => {
    event.preventDefault()
    setIsSubmitting(true)
    setError('')
    setSuccess('')
    try {
      const result = await apiRequest('/support/tickets', {
        method: 'POST',
        body: { subject, category, priority, message: description },
      })
      setSubject('')
      setCategory('technical')
      setPriority('normal')
      setDescription('')
      setShowNewTicket(false)
      setSelectedId(result.ticketId)
      await loadTickets()
      setSelectedId(result.ticketId)
      setSuccess(`Ticket ${ticketCode(result.ticketId)} has been submitted to the support team.`)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const sendReply = async (event) => {
    event.preventDefault()
    if (!activeTicket) return
    setIsSubmitting(true)
    setError('')
    setSuccess('')
    try {
      await apiRequest(`/support/tickets/${activeTicket.id}/messages`, {
        method: 'POST',
        body: {
          message: reply,
          ...(isSubEditor && replyStatus !== 'automatic' ? { status: replyStatus } : {}),
        },
      })
      setReply('')
      setReplyStatus('automatic')
      const [ticketResult] = await Promise.all([
        apiRequest(`/support/tickets/${activeTicket.id}`),
        loadTickets(),
      ])
      setSelectedTicket(ticketResult.ticket)
      setSuccess('Your update has been added to the ticket.')
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <section className={`support-page${isSubEditor ? ' is-sub-editor' : ''}`}>
      <header className="support-page-heading">
        <div>
          <span className="mini-label">{isSubEditor ? 'Service Desk' : `${user.role === 'team_leader' ? 'Team Leader' : 'Telecaller'} Workspace`}</span>
          <h1>{isSubEditor ? 'Support Inbox' : 'Support Center'}</h1>
          <p>{isSubEditor
            ? 'Manage employee requests, respond to conversations, and keep every issue moving.'
            : 'Create a ticket for an issue and keep all updates in one place.'}</p>
        </div>
        {!isSubEditor && (
          <button type="button" className="support-primary-button" onClick={() => setShowNewTicket((current) => !current)}>
            <Icon name="plus" size={18} />
            New ticket
          </button>
        )}
      </header>

      <div className="support-stat-grid" aria-label="Ticket overview">
        <article className="is-total">
          <span className="support-stat-icon"><Icon name="headset" size={18} /></span>
          <div><span>Total tickets</span><strong>{counts.total}</strong><small>All requests</small></div>
        </article>
        <article className="is-active">
          <span className="support-stat-icon"><Icon name="trend" size={18} /></span>
          <div><span>Active</span><strong>{counts.active}</strong><small>Open or in progress</small></div>
        </article>
        <article>
          <span className="support-stat-icon"><Icon name="clock" size={18} /></span>
          <div>
            <span>{isSubEditor ? 'Awaiting employee' : 'Waiting on you'}</span>
            <strong>{counts.waiting}</strong>
            <small>{isSubEditor ? 'Employee reply pending' : 'Needs your response'}</small>
          </div>
        </article>
        <article className="is-resolved">
          <span className="support-stat-icon"><Icon name="gift" size={18} /></span>
          <div><span>Resolved</span><strong>{counts.resolved}</strong><small>Completed tickets</small></div>
        </article>
      </div>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}
      {success && <p className="support-success" role="status">{success}</p>}

      {showNewTicket && !isSubEditor && (
        <div
          className="support-modal-backdrop"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget && !isSubmitting) setShowNewTicket(false)
          }}
        >
          <form className="support-new-ticket" onSubmit={createTicket} role="dialog" aria-modal="true" aria-labelledby="support-form-title">
            <div className="support-new-ticket-heading">
              <span className="support-form-icon"><Icon name="headset" size={21} /></span>
              <div><h2 id="support-form-title">How can we help?</h2><p>Tell us a little about the issue. Our support team will follow up here.</p></div>
              <button type="button" className="support-modal-close" aria-label="Close new ticket form" onClick={() => setShowNewTicket(false)} disabled={isSubmitting}>×</button>
            </div>
            <div className="support-form-grid">
              <label>
                <span>Subject</span>
                <input autoFocus value={subject} onChange={(event) => setSubject(event.target.value)} minLength={5} maxLength={140} required placeholder="Summarize your issue" />
              </label>
              <label>
                <span>Category</span>
                <select value={category} onChange={(event) => setCategory(event.target.value)}>
                  {categories.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
              </label>
              <label>
                <span>Priority</span>
                <select value={priority} onChange={(event) => setPriority(event.target.value)}>
                  {priorities.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
              </label>
              <label className="support-form-description">
                <span>What happened?</span>
                <textarea value={description} onChange={(event) => setDescription(event.target.value)} minLength={10} maxLength={4000} required placeholder="What were you trying to do? What happened? Add any details that can help us investigate." />
                <small>{description.length}/4,000 characters</small>
              </label>
            </div>
            <div className="support-form-actions">
              <button type="button" className="support-secondary-button" onClick={() => setShowNewTicket(false)} disabled={isSubmitting}>Cancel</button>
              <button type="submit" className="support-primary-button" disabled={isSubmitting}>
                {isSubmitting ? 'Submitting…' : 'Submit ticket'}
              </button>
            </div>
          </form>
        </div>
      )}

      <div className={`support-workspace${isSubEditor ? ' is-sub-editor' : ''}`}>
        <div className="support-ticket-toolbar">
          <div className="support-ticket-toolbar-copy">
            <span className="support-list-eyebrow">{isSubEditor ? 'SERVICE DESK' : 'YOUR REQUESTS'}</span>
            <strong>{isSubEditor ? 'Support tickets' : 'My support tickets'}</strong>
            <span>{isLoading ? 'Loading tickets…' : `${tickets.length} ticket${tickets.length === 1 ? '' : 's'}`}</span>
          </div>
          <div className="support-ticket-toolbar-actions">
            {!isSubEditor && tickets.length > 0 && (
              <label className="support-ticket-picker">
                <span className="visually-hidden">Choose a ticket</span>
                <select
                  aria-label="Choose a support ticket"
                  value={selectedId || ''}
                  onChange={(event) => {
                    setSelectedId(Number(event.target.value))
                    setSuccess('')
                  }}
                >
                  {tickets.map((ticket) => (
                    <option key={ticket.id} value={ticket.id}>
                      {ticketCode(ticket.id)} · {ticket.subject} · {labelFor(statuses, ticket.status)}
                      {isSubEditor ? ` · ${ticket.employeeName}` : ''}
                    </option>
                  ))}
                </select>
              </label>
            )}
            <button type="button" className="support-refresh-button" aria-label="Refresh tickets" onClick={loadTickets} disabled={isLoading}>
              <Icon name="clock" size={17} />
            </button>
          </div>
        </div>

        <div className="support-inbox-body">
          {isSubEditor && (
            <aside className="support-ticket-queue" aria-label="Employee support ticket queue">
              <div className="support-queue-heading">
                <div>
                  <strong>Ticket queue</strong>
                  <span>{filteredTickets.length} of {tickets.length} requests</span>
                </div>
                <span className="support-queue-total">{tickets.length}</span>
              </div>
              <label className="support-queue-search">
                <Icon name="search" size={17} />
                <input
                  type="search"
                  aria-label="Search tickets by employee or subject"
                  placeholder="Search name or issue"
                  value={ticketSearch}
                  onChange={(event) => setTicketSearch(event.target.value)}
                />
              </label>
              <div className="support-queue-filters" aria-label="Filter support tickets">
                {[
                  { value: 'all', label: 'All', count: counts.total },
                  { value: 'active', label: 'Active', count: counts.active },
                  { value: 'waiting', label: 'Waiting', count: counts.waiting },
                  { value: 'resolved', label: 'Resolved', count: counts.resolved },
                ].map((filter) => (
                  <button
                    type="button"
                    key={filter.value}
                    className={ticketFilter === filter.value ? 'active' : ''}
                    aria-pressed={ticketFilter === filter.value}
                    onClick={() => setTicketFilter(filter.value)}
                  >
                    {filter.label}<span>{filter.count}</span>
                  </button>
                ))}
              </div>
              <div className="support-queue-list">
                {isLoading ? (
                  <p className="support-queue-empty">Loading support requests…</p>
                ) : filteredTickets.length ? filteredTickets.map((ticket) => (
                  <button
                    type="button"
                    key={ticket.id}
                    className={`support-queue-ticket${selectedId === ticket.id ? ' is-selected' : ''}`}
                    aria-current={selectedId === ticket.id ? 'true' : undefined}
                    onClick={() => {
                      setSelectedId(ticket.id)
                      setSuccess('')
                    }}
                  >
                    <span className="support-queue-ticket-top">
                      <strong>{ticket.employeeName}</strong>
                      <span className={`support-status-badge status-${ticket.status}`}>{labelFor(statuses, ticket.status)}</span>
                    </span>
                    <span className="support-queue-ticket-role">{ticket.role === 'team_leader' ? 'Team Leader' : 'Telecaller'} · {ticket.employeeId}</span>
                    <span className="support-queue-ticket-subject">{ticket.subject}</span>
                    <span className="support-queue-ticket-preview">{ticket.latestMessage}</span>
                    <span className="support-queue-ticket-bottom">
                      <span>{ticketCode(ticket.id)} · {labelFor(categories, ticket.category)}</span>
                      <span className={`support-priority priority-${ticket.priority}`}>{ticket.priority}</span>
                    </span>
                  </button>
                )) : (
                  <div className="support-queue-empty">
                    <Icon name="search" size={20} />
                    <strong>{tickets.length ? 'No matching tickets' : 'No support requests yet'}</strong>
                    <span>{tickets.length ? 'Try another search or filter.' : 'Employee requests will appear here.'}</span>
                  </div>
                )}
              </div>
            </aside>
          )}

        <article className="support-conversation-panel">
          {activeTicket ? (
            <>
              <header className="support-conversation-heading">
                <div>
                  <span>{ticketCode(activeTicket.id)} <i /> {labelFor(categories, activeTicket.category)}</span>
                  <h2>{activeTicket.subject}</h2>
                  {isSubEditor && <p>Raised by {activeTicket.employeeName} · {activeTicket.role === 'team_leader' ? 'Team Leader' : 'Telecaller'} · {activeTicket.employeeId}</p>}
                </div>
                <span className={`support-status-badge status-${activeTicket.status}`}>{labelFor(statuses, activeTicket.status)}</span>
              </header>
              <div className="support-conversation-meta">
                <span>Priority <strong className={`support-priority priority-${activeTicket.priority}`}>{activeTicket.priority}</strong></span>
                <span>Created {formatDate(activeTicket.createdAt)}</span>
                <span>Updated {formatDate(activeTicket.updatedAt)}</span>
              </div>
              <div className="support-message-thread" aria-live="polite">
                {isLoadingTicket ? <p className="support-list-empty">Loading conversation…</p> : activeTicket.messages.map((item) => {
                  const isOwnMessage = Number(item.senderId) === Number(user.id)
                  return (
                    <div className={`support-message${isOwnMessage ? ' is-own' : ''}`} key={item.id}>
                      <div className="support-message-meta">
                        <strong>{isOwnMessage ? 'You' : item.senderRole === 'sub_editor' ? 'AROWIN Support' : item.senderName}</strong>
                        <time>{formatDate(item.createdAt)}</time>
                      </div>
                      <p>{item.message}</p>
                    </div>
                  )
                })}
              </div>
              <form className="support-reply-form" onSubmit={sendReply}>
                {isSubEditor && (
                  <label>
                    <span>Update status</span>
                    <select value={replyStatus} onChange={(event) => setReplyStatus(event.target.value)}>
                      <option value="automatic">Automatic update</option>
                      {statuses.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                  </label>
                )}
                <label className="support-reply-input">
                  <span>{isSubEditor ? 'Reply to employee' : 'Add a reply'}</span>
                  <textarea value={reply} onChange={(event) => setReply(event.target.value)} maxLength={4000} required placeholder={isSubEditor ? 'Share progress, ask a follow-up question, or explain the resolution…' : 'Add more information or answer the support team…'} />
                </label>
                <div className="support-reply-footer">
                  <small>{isSubEditor
                    ? 'Your reply and status update are visible to the employee.'
                    : ['resolved', 'closed'].includes(activeTicket.status)
                      ? 'Replying reopens this ticket for the support team.'
                      : 'The support team will see your update in this conversation.'}</small>
                  <button type="submit" className="support-primary-button" disabled={isSubmitting || !reply.trim()}>
                    {isSubmitting ? 'Sending…' : 'Send reply'}
                    <Icon name="arrow" size={17} />
                  </button>
                </div>
              </form>
            </>
          ) : isLoadingTicket ? (
            <div className="support-conversation-loading">
              <span className="support-loading-mark"><Icon name="clock" size={22} /></span>
              <strong>Opening conversation…</strong>
              <span>Loading the latest ticket updates.</span>
            </div>
          ) : (
            <div className="support-conversation-empty">
              <span className="support-conversation-art"><Icon name="headset" size={28} /></span>
              <span className="support-conversation-kicker">AROWIN SUPPORT</span>
              <h2>{isLoading ? 'Loading support…' : tickets.length ? 'Your support, all in one place' : isSubEditor ? 'No support requests yet' : 'We’re here to help'}</h2>
              <p>{isLoading
                ? 'Getting your latest ticket updates.'
                : tickets.length
                  ? 'Choose a ticket above to see the conversation, check its status, or send an update.'
                  : isSubEditor
                    ? 'Employee support requests will appear here when they are submitted.'
                    : 'Something not working as expected? Send our team a support ticket and we’ll take a look.'}</p>
              {!isSubEditor && !isLoading && (
                <button type="button" className="support-primary-button" onClick={() => setShowNewTicket(true)}>
                  <Icon name="plus" size={17} /> Create a support ticket
                </button>
              )}
              <div className="support-conversation-footnote"><Icon name="clock" size={15} /> Replies and updates stay attached to your ticket.</div>
            </div>
          )}
        </article>
        </div>
      </div>
    </section>
  )
}
