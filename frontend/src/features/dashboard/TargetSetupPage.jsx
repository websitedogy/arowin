import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from './components/Icon'
import formatDashboardDate from './formatDashboardDate'

const setupOptions = [
  {
    title: 'Work Assigning to TL',
    description: 'Assign active Telecallers to a Team Leader and review each leader’s current team.',
    icon: 'users',
    path: '/targets/work-assignment',
  },
  {
    title: 'Sales Target Setup',
    description: 'Set up sales targets once the target rules and workflow are defined.',
    icon: 'chart',
    path: '/targets/sales',
  },
]

function EmployeeDetailsTable({
  employees,
  emptyMessage,
  action,
  showJoiningDate = false,
  phoneLabel = 'Mobile',
  actionLabel = 'Action',
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
            </tr>
          )) : (
            <tr><td className="assignment-empty-cell" colSpan={showJoiningDate ? 7 : 6}>{emptyMessage}</td></tr>
          )}
        </tbody>
      </table>
    </div>
  )
}

export default function TargetSetupPage({ currentPath, onNavigate }) {
  const [teamLeaders, setTeamLeaders] = useState([])
  const [selectedLeader, setSelectedLeader] = useState(null)
  const [availableTelecallers, setAvailableTelecallers] = useState([])
  const [assignmentHistory, setAssignmentHistory] = useState([])
  const [historyDetailLeader, setHistoryDetailLeader] = useState(null)
  const [historyDetailTelecallers, setHistoryDetailTelecallers] = useState([])
  const [historyLoaded, setHistoryLoaded] = useState(false)
  const [isLoadingHistoryDetails, setIsLoadingHistoryDetails] = useState(false)
  const [historyError, setHistoryError] = useState('')
  const [loadedPath, setLoadedPath] = useState('')
  const [isLoadingTelecallers, setIsLoadingTelecallers] = useState(false)
  const [assigningTelecallerId, setAssigningTelecallerId] = useState(null)
  const [error, setError] = useState('')
  const [modalError, setModalError] = useState('')
  const [notice, setNotice] = useState('')

  useEffect(() => {
    if (currentPath === '/targets/work-assignment/history') {
      let active = true
      apiRequest('/team-assignments/history')
        .then((result) => {
          if (active) {
            setAssignmentHistory(result.history)
            setHistoryError('')
          }
        })
        .catch((requestError) => {
          if (active) setHistoryError(requestError.message)
        })
        .finally(() => {
          if (active) setHistoryLoaded(true)
        })

      return () => {
        active = false
      }
    }

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

  const viewHistoryTeam = async (historyItem) => {
    setHistoryDetailLeader(historyItem)
    setHistoryDetailTelecallers([])
    setHistoryError('')
    setIsLoadingHistoryDetails(true)
    try {
      const result = await apiRequest(`/team-assignments/${historyItem.teamLeaderId}/telecallers`)
      setHistoryDetailTelecallers(result.telecallers)
    } catch (requestError) {
      setHistoryError(requestError.message)
    } finally {
      setIsLoadingHistoryDetails(false)
    }
  }

  const closeHistoryDetails = () => {
    setHistoryDetailLeader(null)
    setHistoryDetailTelecallers([])
    setHistoryError('')
  }

  const isLoadingHistory = !historyLoaded
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
        leader.id === selectedLeader.id
          ? { ...leader, assignedCount: leader.assignedCount + 1 }
          : leader
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
    return (
      <section className="target-setup-page">
        <button type="button" className="target-back-button" onClick={() => onNavigate('/targets')}>
          <Icon name="arrow" size={17} /> Target Setup
        </button>
        <header className="target-setup-heading">
          <span className="mini-label">Target Setup</span>
          <h1>Sales Target Setup</h1>
          <p>Sales target setup is ready for your workflow and target rules.</p>
        </header>
        <article className="dashboard-panel target-setup-placeholder">
          <Icon name="chart" size={28} />
          <strong>Sales targets are not configured yet.</strong>
          <p>Share the target rules and workflow to connect this page to saved sales targets.</p>
        </article>
      </section>
    )
  }

  if (currentPath === '/targets/work-assignment/history') {
    return (
      <section className="target-setup-page assignment-page assignment-history-page">
        <button type="button" className="target-back-button" onClick={() => onNavigate('/targets/work-assignment')}>
          <Icon name="arrow" size={17} /> Work Assigning to TL
        </button>
        <header className="target-setup-heading assignment-history-page-heading">
          <h1>Assignment History</h1>
          <p>Team Leader details and assignment dates. Select View to see that Team Leader’s Telecallers.</p>
        </header>

        {historyError && <p className="dashboard-data-error" role="alert">{historyError}</p>}
        {isLoadingHistory ? (
          <p className="employee-directory-loading" role="status">Loading assignment history…</p>
        ) : (
          <article className="dashboard-panel assignment-leaders-panel">
            <div className="assignment-table-wrap">
              <table className="assignment-table">
                <thead>
                  <tr>
                    <th>Employee ID</th>
                    <th>Team Leader</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>ID Creation Date</th>
                    <th>Joining Date</th>
                    <th>Assigned Date</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {assignmentHistory.length ? assignmentHistory.map((item) => (
                    <tr key={item.id}>
                      <td><strong className="assignment-employee-id">{item.teamLeaderEmployeeId || '—'}</strong></td>
                      <td>{item.teamLeaderName}</td>
                      <td>{item.teamLeaderMobile || '—'}</td>
                      <td>{item.teamLeaderEmail || '—'}</td>
                      <td>{formatDashboardDate(item.teamLeaderCreatedAt)}</td>
                      <td>{formatDashboardDate(item.teamLeaderJoiningDate)}</td>
                      <td>{formatDashboardDate(item.assignedAt)}</td>
                      <td>
                        <button type="button" className="assignment-action-button" onClick={() => viewHistoryTeam(item)}>
                          View
                        </button>
                      </td>
                    </tr>
                  )) : (
                    <tr><td className="assignment-empty-cell" colSpan="8">No Team Leader assignments have been recorded.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </article>
        )}

        {historyDetailLeader && (
          <div className="modal-backdrop assignment-modal-backdrop" onClick={closeHistoryDetails}>
            <section
              className="assignment-modal assignment-history-modal"
              role="dialog"
              aria-modal="true"
              aria-labelledby="history-team-title"
              onClick={(event) => event.stopPropagation()}
            >
              <header className="assignment-modal-heading">
                <div>
                  <span className="mini-label">Assigned Telecallers</span>
                  <h2 id="history-team-title">{historyDetailLeader.teamLeaderName}</h2>
                  <p>Team Leader ID: {historyDetailLeader.teamLeaderEmployeeId || 'Not available'}</p>
                </div>
                <button type="button" className="close-btn" aria-label="Close" onClick={closeHistoryDetails}>×</button>
              </header>
              {historyError && <p className="feedback error" role="alert">{historyError}</p>}
              {isLoadingHistoryDetails ? (
                <p className="employee-directory-loading" role="status">Loading assigned Telecallers…</p>
              ) : (
                <EmployeeDetailsTable
                  employees={historyDetailTelecallers}
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
      </section>
    )
  }

  return (
    <section className="target-setup-page assignment-page">
      <header className="target-setup-heading assignment-page-heading">
        <h1>Work Assigning to TL</h1>
        <button type="button" className="assignment-history-button" onClick={() => {
          setHistoryLoaded(false)
          setHistoryError('')
          onNavigate('/targets/work-assignment/history')
        }}>
          <Icon name="clock" size={17} /> History
        </button>
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
            action={(teamLeader) => (
              <button type="button" className="assignment-action-button" onClick={() => openAssignModal(teamLeader)}>
                Assign
              </button>
            )}
          />
        </article>
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
