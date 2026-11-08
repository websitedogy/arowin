import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import { formatMonth } from '../../utils/formatters'
import Icon from './components/Icon'
import formatDashboardDate from './formatDashboardDate'

const setupOptions = [
  {
    title: 'Team Assignment',
    description: 'Assign active Telecallers to Team Leaders and view each leader’s team.',
    icon: 'users',
    path: '/targets/work-assignment',
  },
  {
    title: 'Sales Target Setup',
    description: 'Set monthly USDT sales targets for Team Leaders and review target history.',
    icon: 'chart',
    path: '/targets/sales',
  },
]

function currentMonthValue() {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

function createMonthOptions() {
  const current = new Date()
  current.setDate(1)
  return Array.from({ length: 12 }, (_, index) => {
    const date = new Date(current)
    date.setMonth(date.getMonth() + index - 5)
    const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
    return {
      value,
      label: new Intl.DateTimeFormat(undefined, { month: 'short', year: 'numeric' }).format(date),
    }
  })
}

const targetMonthOptions = createMonthOptions()

function formatTargetUpdatedAt(value) {
  if (!value) return '—'
  const timestamp = String(value).trim().replace(' ', 'T').replace(/\.(\d{3})\d+$/, '.$1')
  const date = new Date(timestamp)
  return Number.isNaN(date.getTime())
    ? String(value)
    : new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(date)
}

function SalesTargetSetup({ onBack }) {
  const [selectedMonth, setSelectedMonth] = useState(currentMonthValue)
  const [teamLeaders, setTeamLeaders] = useState([])
  const [targetDrafts, setTargetDrafts] = useState({})
  const [history, setHistory] = useState([])
  const [loadedMonth, setLoadedMonth] = useState('')
  const [savingLeaderId, setSavingLeaderId] = useState(null)
  const [editingLeader, setEditingLeader] = useState(null)
  const [historyLeader, setHistoryLeader] = useState(null)
  const [confirmationAmount, setConfirmationAmount] = useState('')
  const [confirmationPassword, setConfirmationPassword] = useState('')
  const [confirmationError, setConfirmationError] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const isLoading = loadedMonth !== selectedMonth
  const selectedLeaderHistory = historyLeader
    ? history.filter((item) => item.employeeId === historyLeader.employeeId)
    : []

  const selectMonth = (month) => {
    setSelectedMonth(month)
    setNotice('')
    setError('')
  }

  useEffect(() => {
    let active = true

    apiRequest(`/sales-targets?month=${encodeURIComponent(selectedMonth)}`)
      .then((result) => {
        if (!active) return
        setTeamLeaders(result.targets.teamLeaders)
        setTargetDrafts(Object.fromEntries(
          result.targets.teamLeaders.map((leader) => [leader.id, leader.targetAmount || '']),
        ))
        setHistory(result.targets.history)
      })
      .catch((requestError) => {
        if (active) setError(requestError.message)
      })
      .finally(() => {
        if (active) setLoadedMonth(selectedMonth)
      })

    return () => {
      active = false
    }
  }, [selectedMonth])

  const openTargetConfirmation = (teamLeader) => {
    setEditingLeader(teamLeader)
    setConfirmationAmount(targetDrafts[teamLeader.id] ?? '')
    setConfirmationPassword('')
    setConfirmationError('')
  }

  const closeTargetConfirmation = () => {
    if (savingLeaderId !== null) return
    setEditingLeader(null)
    setConfirmationPassword('')
    setConfirmationError('')
  }

  const saveTarget = async (event) => {
    event.preventDefault()
    if (!editingLeader) return

    const teamLeader = editingLeader
    const targetAmount = confirmationAmount.trim()
    if (!targetAmount || !Number.isFinite(Number(targetAmount)) || Number(targetAmount) <= 0) {
      setConfirmationError('Enter a target amount greater than zero.')
      return
    }
    if (confirmationPassword === '') {
      setConfirmationError('Enter your Sub Editor password to confirm this change.')
      return
    }

    setSavingLeaderId(teamLeader.id)
    setConfirmationError('')
    setConfirmationPassword('')
    setNotice('')
    try {
      const result = await apiRequest(`/sales-targets/${teamLeader.id}`, {
        method: 'POST',
        body: { month: selectedMonth, targetAmount, password: confirmationPassword },
      })
      const savedTarget = result.target
      setTargetDrafts((drafts) => ({ ...drafts, [teamLeader.id]: savedTarget.targetAmount }))
      setTeamLeaders((leaders) => leaders.map((leader) => (
        leader.id === teamLeader.id
          ? { ...leader, targetAmount: savedTarget.targetAmount }
          : leader
      )))
      setHistory((items) => [
        {
          month: savedTarget.month,
          employeeId: teamLeader.employeeId,
          fullName: teamLeader.fullName,
          targetAmount: savedTarget.targetAmount,
          changedAt: savedTarget.changedAt,
          historyId: savedTarget.historyId,
        },
        ...items,
      ].sort((a, b) => b.month.localeCompare(a.month)
        || a.fullName.localeCompare(b.fullName)
        || String(b.changedAt).localeCompare(String(a.changedAt))
        || Number(b.historyId) - Number(a.historyId)))
      setNotice(`${formatMonth(selectedMonth)} target saved for ${teamLeader.fullName}.`)
      setEditingLeader(null)
    } catch (requestError) {
      setConfirmationError(requestError.message)
    } finally {
      setSavingLeaderId(null)
    }
  }

  const changeMonth = (offset) => {
    const [year, month] = selectedMonth.split('-').map(Number)
    const next = new Date(year, month - 1 + offset, 1)
    selectMonth(`${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`)
  }

  return (
    <section className="target-setup-page sales-target-page">
      <button type="button" className="target-back-button" onClick={onBack}>
        <Icon name="arrow" size={17} /> Target Setup
      </button>
      <header className="target-setup-heading sales-target-heading">
        <div>
          <span className="mini-label">Monthly target planning</span>
          <h1>Sales Target Setup</h1>
          <p>Assign monthly USDT sales targets to Team Leaders and review previous months.</p>
        </div>
        <span className="sales-target-active-month"><Icon name="calendar" size={17} /> Active month · {formatMonth(selectedMonth)}</span>
      </header>

      <article className="dashboard-panel sales-target-calendar">
        <div className="sales-target-calendar-heading">
          <div>
            <strong>Choose target month</strong>
            <span>Targets are saved separately for each Team Leader and month.</span>
          </div>
          <label className="sales-target-month-input">
            <Icon name="calendar" size={17} />
            <span className="visually-hidden">Target month</span>
            <input
              type="month"
              value={selectedMonth}
              onChange={(event) => {
                if (event.target.value) {
                  selectMonth(event.target.value)
                }
              }}
            />
          </label>
        </div>
        <div className="sales-target-month-strip">
          <button type="button" className="sales-target-month-arrow" onClick={() => changeMonth(-1)} aria-label="Previous month">‹</button>
          {targetMonthOptions.map((month) => (
            <button
              type="button"
              key={month.value}
              className={`sales-target-month-option${month.value === selectedMonth ? ' is-selected' : ''}`}
              aria-pressed={month.value === selectedMonth}
              onClick={() => {
                selectMonth(month.value)
              }}
            >
              {month.label}
            </button>
          ))}
          <button type="button" className="sales-target-month-arrow" onClick={() => changeMonth(1)} aria-label="Next month">›</button>
        </div>
      </article>

      {notice && <p className="assignment-notice" role="status">{notice}</p>}
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <article className="dashboard-panel sales-target-panel">
        <div className="assignment-section-heading">
          <div><Icon name="chart" size={22} /><h2>Team Leader Targets</h2></div>
          <span>{teamLeaders.length} Team Leader{teamLeaders.length === 1 ? '' : 's'}</span>
        </div>
        {isLoading ? (
          <p className="employee-directory-loading" role="status">Loading Team Leaders and targets…</p>
        ) : teamLeaders.length ? (
          <div className="assignment-table-wrap">
            <table className="assignment-table sales-target-table">
              <thead>
                <tr><th>Employee ID</th><th>Team Leader</th><th>Email</th><th>Monthly Target (USDT)</th><th>Action</th><th>History</th></tr>
              </thead>
              <tbody>
                {teamLeaders.map((leader) => (
                  <tr key={leader.id}>
                    <td><strong className="assignment-employee-id">{leader.employeeId}</strong></td>
                    <td>{leader.fullName}</td>
                    <td>{leader.email}</td>
                    <td>
                      <label className="sales-target-amount">
                        <span>USDT</span>
                        <input
                          type="number"
                          min="0.01"
                          max="999999999999.99"
                          step="0.01"
                          inputMode="decimal"
                          aria-label={`Monthly USDT target for ${leader.fullName}`}
                          placeholder="Enter amount"
                          value={targetDrafts[leader.id] ?? ''}
                          onChange={(event) => setTargetDrafts((drafts) => ({
                            ...drafts,
                            [leader.id]: event.target.value,
                          }))}
                        />
                      </label>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="assignment-action-button"
                        onClick={() => openTargetConfirmation(leader)}
                        disabled={savingLeaderId !== null}
                      >
                        {leader.targetAmount ? 'Update Target' : 'Save Target'}
                      </button>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="sales-target-history-view"
                        onClick={() => setHistoryLeader(leader)}
                      >
                        View
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="team-activity-empty">Create an active Team Leader account before assigning monthly targets.</p>
        )}
      </article>

      {editingLeader && (
        <div className="modal-backdrop assignment-modal-backdrop" onClick={closeTargetConfirmation}>
          <section
            className="assignment-modal target-confirmation-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="target-confirmation-title"
            onClick={(event) => event.stopPropagation()}
          >
            <header className="assignment-modal-heading">
              <div>
                <span className="mini-label">Confirm target change</span>
                <h2 id="target-confirmation-title">{editingLeader.fullName}</h2>
                <p>{formatMonth(selectedMonth)} · Enter the new USDT target and confirm with your Sub Editor password.</p>
              </div>
              <button type="button" className="close-btn" aria-label="Close" onClick={closeTargetConfirmation} disabled={savingLeaderId !== null}>×</button>
            </header>
            <form className="target-confirmation-form" onSubmit={saveTarget}>
              <label>
                <span>Monthly target (USDT)</span>
                <input
                  type="number"
                  min="0.01"
                  max="999999999999.99"
                  step="0.01"
                  inputMode="decimal"
                  value={confirmationAmount}
                  onChange={(event) => setConfirmationAmount(event.target.value)}
                  autoFocus
                  required
                />
              </label>
              <label>
                <span>Sub Editor password</span>
                <input
                  type="password"
                  value={confirmationPassword}
                  onChange={(event) => setConfirmationPassword(event.target.value)}
                  autoComplete="current-password"
                  required
                />
              </label>
              {confirmationError && <p className="feedback error" role="alert">{confirmationError}</p>}
              <div className="target-confirmation-actions">
                <button type="button" className="target-confirmation-cancel" onClick={closeTargetConfirmation} disabled={savingLeaderId !== null}>Cancel</button>
                <button type="submit" className="assignment-action-button" disabled={savingLeaderId !== null}>
                  {savingLeaderId === editingLeader.id ? 'Verifying and saving…' : 'Verify & update target'}
                </button>
              </div>
            </form>
          </section>
        </div>
      )}

      {historyLeader && (
        <div className="modal-backdrop assignment-modal-backdrop" onClick={() => setHistoryLeader(null)}>
          <section
            className="assignment-modal target-history-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="target-history-title"
            onClick={(event) => event.stopPropagation()}
          >
            <header className="assignment-modal-heading">
              <div>
                <span className="mini-label">Monthly target history</span>
                <h2 id="target-history-title">{historyLeader.fullName}</h2>
                <p>Saved targets for the last 12 months.</p>
              </div>
              <button type="button" className="close-btn" aria-label="Close history" onClick={() => setHistoryLeader(null)}>×</button>
            </header>
            {selectedLeaderHistory.length ? (
              <div className="assignment-table-wrap target-history-table-wrap">
                <table className="assignment-table sales-target-table">
                  <thead>
                        <tr><th>Month</th><th>Sales Target (USDT)</th><th>Changed Date &amp; Time</th></tr>
                  </thead>
                  <tbody>
                    {selectedLeaderHistory.map((item) => (
                      <tr key={item.historyId}>
                          <td>{formatMonth(item.month)}</td>
                          <td><strong>{item.targetAmount} USDT</strong></td>
                        <td>{formatTargetUpdatedAt(item.changedAt)}</td>
                        </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="team-activity-empty">No saved target history for this Team Leader in the last 12 months.</p>
            )}
          </section>
        </div>
      )}

    </section>
  )
}

function EmployeeDetailsTable({
  employees,
  emptyMessage,
  action,
  showJoiningDate = false,
  showTeamCount = false,
  phoneLabel = 'Mobile',
  actionLabel = 'Action',
  teamAction,
}) {
  return (
    <div className="assignment-table-wrap">
      <table className="assignment-table">
        <thead>
          <tr>
            <th>Employee ID</th>
            <th>Name</th>
            <th>{phoneLabel}</th>
            <th>Email</th>
            <th>ID Creation Date</th>
            {showJoiningDate && <th>Joining Date</th>}
            <th>{actionLabel}</th>
            {showTeamCount && <th>My Team</th>}
          </tr>
        </thead>
        <tbody>
          {employees.length ? employees.map((employee) => (
            <tr key={employee.id}>
              <td><strong className="assignment-employee-id">{employee.employeeId}</strong></td>
              <td>{employee.fullName}</td>
              <td>{employee.mobile || '—'}</td>
              <td>{employee.email}</td>
              <td>{formatDashboardDate(employee.createdAt)}</td>
              {showJoiningDate && <td>{formatDashboardDate(employee.joiningDate)}</td>}
              <td>{action(employee)}</td>
              {showTeamCount && (
                <td>
                  <button type="button" className="assignment-team-button" onClick={() => teamAction(employee)}>
                    My Team <span>{employee.assignedCount}</span>
                  </button>
                </td>
              )}
            </tr>
          )) : (
            <tr><td className="assignment-empty-cell" colSpan={6 + Number(showJoiningDate) + Number(showTeamCount)}>{emptyMessage}</td></tr>
          )}
        </tbody>
      </table>
    </div>
  )
}

export default function TargetSetupPage({ currentPath, onNavigate }) {
  const [teamLeaders, setTeamLeaders] = useState([])
  const [selectedLeader, setSelectedLeader] = useState(null)
  const [teamLeaderToView, setTeamLeaderToView] = useState(null)
  const [teamTelecallers, setTeamTelecallers] = useState([])
  const [isLoadingTeam, setIsLoadingTeam] = useState(false)
  const [teamError, setTeamError] = useState('')
  const [availableTelecallers, setAvailableTelecallers] = useState([])
  const [loadedPath, setLoadedPath] = useState('')
  const [isLoadingTelecallers, setIsLoadingTelecallers] = useState(false)
  const [assigningTelecallerId, setAssigningTelecallerId] = useState(null)
  const [error, setError] = useState('')
  const [modalError, setModalError] = useState('')
  const [notice, setNotice] = useState('')

  useEffect(() => {
    if (currentPath !== '/targets/work-assignment') return undefined

    let active = true
    apiRequest('/team-assignments')
      .then((result) => {
        if (active) setTeamLeaders(result.teamLeaders)
      })
      .catch((requestError) => {
        if (active) setError(requestError.message)
      })
      .finally(() => {
        if (active) setLoadedPath(currentPath)
      })

    return () => {
      active = false
    }
  }, [currentPath])

  const openAssignModal = async (teamLeader) => {
    setSelectedLeader(teamLeader)
    setAvailableTelecallers([])
    setModalError('')
    setIsLoadingTelecallers(true)

    try {
      const result = await apiRequest(`/team-assignments/${teamLeader.id}/available-telecallers`)
      setAvailableTelecallers(result.telecallers)
    } catch (requestError) {
      setModalError(requestError.message)
    } finally {
      setIsLoadingTelecallers(false)
    }
  }

  const viewTeam = async (teamLeader) => {
    setTeamLeaderToView(teamLeader)
    setTeamTelecallers([])
    setTeamError('')
    setIsLoadingTeam(true)

    try {
      const result = await apiRequest(`/team-assignments/${teamLeader.id}/telecallers`)
      setTeamTelecallers(result.telecallers)
    } catch (requestError) {
      setTeamError(requestError.message)
    } finally {
      setIsLoadingTeam(false)
    }
  }

  const isLoading = currentPath === '/targets/work-assignment' && loadedPath !== currentPath

  const assignTelecaller = async (telecaller) => {
    if (!selectedLeader) return

    setAssigningTelecallerId(telecaller.id)
    setModalError('')
    try {
      const result = await apiRequest('/team-assignments', {
        method: 'POST',
        body: { teamLeaderId: selectedLeader.id, telecallerId: telecaller.id },
      })
      setTeamLeaders((leaders) => leaders.map((leader) => (
        {
          ...leader,
          assignedCount: leader.id === selectedLeader.id ? leader.assignedCount + 1 : leader.assignedCount,
          availableCount: Math.max(0, leader.availableCount - 1),
        }
      )))
      setAvailableTelecallers((telecallers) => telecallers.filter((item) => item.id !== telecaller.id))
      setNotice(`${telecaller.fullName} assigned to ${selectedLeader.fullName}.`)
      setModalError('')
      return result
    } catch (requestError) {
      setModalError(requestError.message)
    } finally {
      setAssigningTelecallerId(null)
    }
  }

  if (currentPath === '/targets') {
    return (
      <section className="target-setup-page">
        <header className="target-setup-heading">
          <span className="mini-label">Sub Editor Workspace</span>
          <h1>Target Setup</h1>
          <p>Choose a setup task to manage team assignments or sales targets.</p>
        </header>
        <div className="target-setup-options">
          {setupOptions.map((option) => (
            <button type="button" className="target-setup-option" key={option.path} onClick={() => onNavigate(option.path)}>
              <span className="target-setup-option-icon"><Icon name={option.icon} size={25} /></span>
              <span className="target-setup-option-copy">
                <strong>{option.title}</strong>
                <small>{option.description}</small>
              </span>
              <Icon name="arrow" size={19} />
            </button>
          ))}
        </div>
      </section>
    )
  }

  if (currentPath === '/targets/sales') {
    return <SalesTargetSetup onBack={() => onNavigate('/targets')} />
  }

  return (
    <section className="target-setup-page assignment-page">
      <header className="target-setup-heading assignment-page-heading">
        <h1>Team Assignment</h1>
      </header>

      {notice && <p className="assignment-notice" role="status">{notice}</p>}
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}
      {isLoading ? (
        <p className="employee-directory-loading" role="status">Loading Team Leaders…</p>
      ) : (
        <article className="dashboard-panel assignment-leaders-panel">
          <div className="assignment-section-heading">
            <div><Icon name="users" size={22} /><h2>Team Leaders</h2></div>
            <span>{teamLeaders.length} Team Leader{teamLeaders.length === 1 ? '' : 's'}</span>
          </div>
          <EmployeeDetailsTable
            employees={teamLeaders}
            emptyMessage="No Team Leaders are available. Create a Team Leader account first."
            showJoiningDate
            showTeamCount
            action={(teamLeader) => (
              <span className="assignment-action-group">
                <button type="button" className="assignment-action-button" onClick={() => openAssignModal(teamLeader)}>
                  Assign
                </button>
                <span className="assignment-available-count" aria-label={`${teamLeader.availableCount} unassigned Telecallers`}>
                  <span>Unassigned</span>
                  <strong>{teamLeader.availableCount}</strong>
                </span>
              </span>
            )}
            teamAction={viewTeam}
          />
        </article>
      )}

      {teamLeaderToView && (
        <div className="modal-backdrop assignment-modal-backdrop" onClick={() => setTeamLeaderToView(null)}>
          <section
            className="assignment-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="team-modal-title"
            onClick={(event) => event.stopPropagation()}
          >
            <header className="assignment-modal-heading">
              <div>
                <span className="mini-label">My Team</span>
                <h2 id="team-modal-title">{teamLeaderToView.fullName}</h2>
                <p>{teamLeaderToView.assignedCount} assigned Telecaller{teamLeaderToView.assignedCount === 1 ? '' : 's'}</p>
              </div>
              <button type="button" className="close-btn" aria-label="Close" onClick={() => setTeamLeaderToView(null)}>×</button>
            </header>
            {teamError && <p className="feedback error" role="alert">{teamError}</p>}
            {isLoadingTeam ? (
              <p className="employee-directory-loading" role="status">Loading assigned Telecallers…</p>
            ) : (
              <EmployeeDetailsTable
                employees={teamTelecallers}
                emptyMessage="No Telecallers are currently assigned to this Team Leader."
                phoneLabel="Phone No."
                actionLabel="Status"
                action={(telecaller) => (
                  <span className={`team-member-status${telecaller.status === 'Active' ? ' is-active' : ''}`}>
                    {telecaller.status}
                  </span>
                )}
              />
            )}
          </section>
        </div>
      )}

      {selectedLeader && (
        <div className="modal-backdrop assignment-modal-backdrop" onClick={() => setSelectedLeader(null)}>
          <section
            className="assignment-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="assignment-modal-title"
            onClick={(event) => event.stopPropagation()}
          >
            <header className="assignment-modal-heading">
              <div>
                <span className="mini-label">Assign Telecallers</span>
                <h2 id="assignment-modal-title">{selectedLeader.fullName}</h2>
                <p>Choose an unassigned Telecaller for this Team Leader.</p>
              </div>
              <button type="button" className="close-btn" aria-label="Close" onClick={() => setSelectedLeader(null)}>×</button>
            </header>
            {modalError && <p className="feedback error" role="alert">{modalError}</p>}
            {isLoadingTelecallers ? (
              <p className="employee-directory-loading" role="status">Loading available Telecallers…</p>
            ) : (
              <EmployeeDetailsTable
                employees={availableTelecallers}
                emptyMessage="No unassigned active Telecallers are available."
                phoneLabel="Phone No."
                action={(telecaller) => (
                  <button
                    type="button"
                    className="assignment-action-button"
                    onClick={() => assignTelecaller(telecaller)}
                    disabled={assigningTelecallerId !== null}
                  >
                    {assigningTelecallerId === telecaller.id ? 'Assigning…' : 'Assign'}
                  </button>
                )}
              />
            )}
          </section>
        </div>
      )}
    </section>
  )
}
