import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from './components/Icon'

export default function StateAssignmentPage() {
  const [teamLeaders, setTeamLeaders] = useState([])
  const [states, setStates] = useState([])
  const [selectedLeader, setSelectedLeader] = useState(null)
  const [selectedStateCode, setSelectedStateCode] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState('')
  const [territoryError, setTerritoryError] = useState('')
  const [notice, setNotice] = useState('')

  const loadAssignments = () => apiRequest('/state-assignments')
    .then((result) => setTeamLeaders(result.teamLeaders))

  useEffect(() => {
    let active = true
    Promise.allSettled([apiRequest('/state-assignments'), apiRequest('/territories')])
      .then(([assignmentResult, territoryResult]) => {
        if (!active) return
        if (assignmentResult.status === 'fulfilled') {
          setTeamLeaders(assignmentResult.value.teamLeaders)
        } else {
          setError(assignmentResult.reason.message)
        }
        if (territoryResult.status === 'fulfilled') {
          setStates(territoryResult.value.states)
        } else {
          setTerritoryError(territoryResult.reason.message)
        }
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  const openAssignment = (leader) => {
    setSelectedLeader(leader)
    setSelectedStateCode('')
    setError('')
    setNotice('')
  }

  const assignState = async (event) => {
    event.preventDefault()
    if (!selectedLeader || !selectedStateCode) return

    setIsSaving(true)
    setError('')
    setNotice('')
    try {
      await apiRequest('/state-assignments', {
        method: 'POST',
        body: { teamLeaderId: selectedLeader.id, stateCode: selectedStateCode },
      })
      await loadAssignments()
      setSelectedLeader(null)
      setNotice(`State assigned to ${selectedLeader.fullName}.`)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsSaving(false)
    }
  }

  const selectedState = states.find((state) => state.code === selectedStateCode)

  return (
    <section className="sub-editor-feature-page state-assignment-page">
      <span className="mini-label">Sub Editor Workspace</span>
      <h1>States Assign</h1>
      <p className="sub-editor-feature-description">Assign one or more Indian states to active Team Leaders.</p>
      <p className="territory-attribution">
        District data: <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">© OpenStreetMap contributors</a>
      </p>
      {notice && <p className="leads-entry-notice" role="status">{notice}</p>}
      {error && !selectedLeader && <p className="dashboard-data-error" role="alert">{error}</p>}
      {territoryError && !selectedLeader && <p className="dashboard-data-error" role="alert">{territoryError}</p>}

      <article className="dashboard-panel sub-editor-feature-table-panel state-assignment-panel">
        <div className="panel-title">
          <Icon name="mapPin" size={22} />
          <div>
            <h2>Team Leaders</h2>
            <small>{isLoading ? 'Loading…' : `${teamLeaders.length} active ${teamLeaders.length === 1 ? 'Team Leader' : 'Team Leaders'}`}</small>
          </div>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table state-assignment-table">
            <thead>
              <tr>
                <th>Employee ID</th>
                <th>Team Leader</th>
                <th>Phone Number</th>
                <th>Email</th>
                <th>Assigned States</th>
                <th>Assign</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="6" className="sub-editor-feature-empty">Loading Team Leaders…</td></tr>
              ) : error ? (
                <tr><td colSpan="6" className="sub-editor-feature-empty">Team Leaders could not be loaded.</td></tr>
              ) : teamLeaders.length ? teamLeaders.map((leader) => (
                <tr key={leader.id}>
                  <td>{leader.employeeId}</td>
                  <td>{leader.fullName}</td>
                  <td>{leader.mobile || '—'}</td>
                  <td>{leader.email}</td>
                  <td>
                    <div className="assigned-state-badges">
                      {leader.assignedStates.length
                        ? leader.assignedStates.map((state) => (
                          <span className="assigned-state-badge" key={state.stateCode}>{state.stateName}</span>
                        ))
                        : <span className="assigned-state-empty">No states assigned</span>}
                    </div>
                  </td>
                  <td>
                    <button type="button" className="state-assign-action" onClick={() => openAssignment(leader)}>
                      Assign
                    </button>
                  </td>
                </tr>
              )) : (
                <tr><td colSpan="6" className="sub-editor-feature-empty">No active Team Leaders found.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>
      {selectedLeader && (
        <div className="modal-backdrop" role="presentation" onMouseDown={(event) => {
          if (event.target === event.currentTarget && !isSaving) setSelectedLeader(null)
        }}>
          <section className="assignment-modal state-assignment-modal" role="dialog" aria-modal="true" aria-labelledby="state-assignment-title">
            <header className="assignment-modal-heading">
              <div>
                <span className="mini-label">Assign a state</span>
                <h2 id="state-assignment-title">{selectedLeader.fullName}</h2>
                <p>Choose an Indian state to add to this Team Leader.</p>
              </div>
              <button type="button" className="close-btn" aria-label="Close" onClick={() => setSelectedLeader(null)} disabled={isSaving}>×</button>
            </header>
            <form className="state-assignment-form" onSubmit={assignState}>
              <label>
                <span>State or Union Territory</span>
                <select value={selectedStateCode} onChange={(event) => setSelectedStateCode(event.target.value)} required>
                  <option value="">Select a state</option>
                  {states.map((state) => {
                    const assigned = selectedLeader.assignedStates.some((item) => item.stateCode === state.code)
                    return (
                      <option key={state.code} value={state.code} disabled={assigned}>
                        {state.name}{assigned ? ' (Already assigned)' : ''}
                      </option>
                    )
                  })}
                </select>
                {selectedState && <small>{selectedState.districts.length} districts will be available to the Team Leader.</small>}
              </label>
              {error && <p className="dashboard-data-error" role="alert">{error}</p>}
              <footer className="leads-form-actions">
                <button type="button" className="leads-cancel-button" onClick={() => setSelectedLeader(null)} disabled={isSaving}>Cancel</button>
                <button type="submit" className="leads-add-button" disabled={isSaving || !selectedStateCode}>
                  {isSaving ? 'Assigning…' : 'Assign State'}
                </button>
              </footer>
            </form>
          </section>
        </div>
      )}
    </section>
  )
}
