import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from './components/Icon'

const emptyCounts = {
  all: 0,
  new: 0,
  taken_employee: 0,
  direct_seller: 0,
  not_interested: 0,
}

const leadCategories = [
  { key: 'all', label: 'All Leads' },
  { key: 'taken_employee', label: 'Taken Employees' },
  { key: 'direct_seller', label: 'Direct Sellers' },
  { key: 'not_interested', label: 'Not Interested' },
]

const statusLabels = {
  new: 'New Lead',
  taken_employee: 'Taken Employee',
  direct_seller: 'Direct Seller',
  not_interested: 'Not Interested',
}

const emptyForm = {
  name: '',
  mobile: '',
  email: '',
  address: '',
  age: '',
  gender: '',
}

export default function TeamLeaderDistrictLeadsPage({ stateCode, district, onNavigate }) {
  const [stateName, setStateName] = useState('')
  const [leads, setLeads] = useState([])
  const [counts, setCounts] = useState(emptyCounts)
  const [selectedCategory, setSelectedCategory] = useState('all')
  const [isLoading, setIsLoading] = useState(true)
  const [isAddModalOpen, setIsAddModalOpen] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [updatingLeadId, setUpdatingLeadId] = useState(null)
  const [statusDrafts, setStatusDrafts] = useState({})
  const [form, setForm] = useState(emptyForm)
  const [error, setError] = useState('')
  const [formError, setFormError] = useState('')
  const [refreshIndex, setRefreshIndex] = useState(0)

  const endpoint = `/team-leader/states/${encodeURIComponent(stateCode)}/leads`

  useEffect(() => {
    let active = true
    const districtQuery = `?district=${encodeURIComponent(district)}`

    Promise.all([
      apiRequest('/team-leader/states'),
      apiRequest(`${endpoint}${districtQuery}`),
    ])
      .then(([stateResult, leadResult]) => {
        if (!active) return
        const selectedState = stateResult.states.find((state) => state.stateCode === stateCode)
        setStateName(selectedState?.stateName || '')
        setLeads(leadResult.leads)
        setCounts(leadResult.counts)
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
  }, [district, endpoint, refreshIndex, stateCode])

  const visibleLeads = useMemo(
    () => selectedCategory === 'all'
      ? leads.filter((lead) => lead.status === 'new')
      : leads.filter((lead) => lead.status === selectedCategory),
    [leads, selectedCategory],
  )

  const openAddModal = () => {
    setForm(emptyForm)
    setFormError('')
    setIsAddModalOpen(true)
  }

  const closeAddModal = () => {
    if (isSubmitting) return
    setIsAddModalOpen(false)
    setFormError('')
  }

  const submitLead = async (event) => {
    event.preventDefault()
    setIsSubmitting(true)
    setFormError('')
    try {
      await apiRequest(endpoint, {
        method: 'POST',
        body: { ...form, district },
      })
      setIsAddModalOpen(false)
      setForm(emptyForm)
      setSelectedCategory('all')
      setRefreshIndex((index) => index + 1)
    } catch (requestError) {
      setFormError(requestError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const saveStatus = async (lead) => {
    const status = statusDrafts[lead.id] ?? lead.status
    if (lead.status === status) return
    setUpdatingLeadId(lead.id)
    setError('')
    try {
      const result = await apiRequest(`/team-leader/leads/${lead.id}/status`, {
        method: 'PATCH',
        body: { status },
      })
      setLeads((currentLeads) => currentLeads.map((item) => item.id === lead.id ? result.lead : item))
      setCounts((currentCounts) => ({
        ...currentCounts,
        [lead.status]: Math.max(0, currentCounts[lead.status] - 1),
        [result.lead.status]: currentCounts[result.lead.status] + 1,
      }))
      setStatusDrafts((currentDrafts) => {
        const nextDrafts = { ...currentDrafts }
        delete nextDrafts[lead.id]
        return nextDrafts
      })
      setSelectedCategory(result.lead.status === 'new' ? 'all' : result.lead.status)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setUpdatingLeadId(null)
    }
  }

  return (
    <section className="team-leader-district-leads">
      <header className="team-leader-leads-header">
        <button
          type="button"
          className="team-leader-back-button"
          onClick={() => onNavigate('/team-leader/state')}
        >
          <Icon name="back" size={16} />
          Districts
        </button>
        <div className="team-leader-leads-title">
          <h1>{district}</h1>
          {stateName && <p>{stateName}</p>}
        </div>
        <button type="button" className="team-leader-add-lead" onClick={openAddModal}>
          <Icon name="plus" size={17} />
          Add Lead
        </button>
      </header>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <div className="team-leader-lead-categories" aria-label="Lead categories">
        {leadCategories.map((category) => (
          <button
            type="button"
            key={category.key}
            className={`team-leader-lead-category${selectedCategory === category.key ? ' active' : ''}`}
            aria-pressed={selectedCategory === category.key}
            onClick={() => setSelectedCategory(category.key)}
          >
            <span>{category.label}</span>
            <strong>{counts[category.key === 'all' ? 'new' : category.key]}</strong>
          </button>
        ))}
      </div>

      <div className="team-leader-lead-table-wrap">
        <table className="employee-table team-leader-lead-table">
          <thead>
            <tr>
              <th>Employee ID</th>
              <th>Employee Name</th>
              <th>Employee Email</th>
              <th>Mobile</th>
              <th>Address</th>
              <th>Age</th>
              <th>Gender</th>
              <th>Lead Status</th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <tr><td colSpan="8" className="sub-editor-feature-empty">Loading leads…</td></tr>
            ) : visibleLeads.length ? visibleLeads.map((lead) => (
              <tr key={lead.id}>
                <td>{lead.employeeId}</td>
                <td>{lead.employeeName}</td>
                <td>{lead.email}</td>
                <td>{lead.mobile}</td>
                <td>{lead.address}</td>
                <td>{lead.age}</td>
                <td>{lead.gender === 'male' ? 'Male' : 'Female'}</td>
                <td>
                  <div className="team-leader-lead-status-actions">
                    <select
                      className="team-leader-lead-status"
                      value={statusDrafts[lead.id] ?? lead.status}
                      disabled={updatingLeadId === lead.id}
                      aria-label={`Update status for ${lead.employeeName}`}
                      onChange={(event) => setStatusDrafts((currentDrafts) => ({
                        ...currentDrafts,
                        [lead.id]: event.target.value,
                      }))}
                    >
                      {Object.entries(statusLabels).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                      ))}
                    </select>
                    {(statusDrafts[lead.id] ?? lead.status) !== lead.status && (
                      <button
                        type="button"
                        className="team-leader-lead-save"
                        disabled={updatingLeadId === lead.id}
                        onClick={() => saveStatus(lead)}
                      >
                        {updatingLeadId === lead.id ? 'Saving…' : 'Save'}
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            )) : (
              <tr>
                <td colSpan="8" className="sub-editor-feature-empty">
                  {error ? 'Leads could not be loaded.' : 'No leads in this category yet. Use Add Lead to create one.'}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {isAddModalOpen && (
        <div
          className="modal-backdrop leads-modal-backdrop"
          role="presentation"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) closeAddModal()
          }}
        >
          <section
            className="assignment-modal leads-entry-modal team-leader-add-lead-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="team-leader-add-lead-title"
          >
            <header className="assignment-modal-heading">
              <div>
                <span className="mini-label">District lead</span>
                <h2 id="team-leader-add-lead-title">Add Lead</h2>
                <p>The state and district are set from this page.</p>
              </div>
              <button type="button" className="close-btn" aria-label="Close" onClick={closeAddModal}>×</button>
            </header>

            <form className="leads-entry-form" onSubmit={submitLead}>
              <label>
                <span>State</span>
                <input type="text" value={stateName} readOnly />
              </label>
              <label>
                <span>District</span>
                <input type="text" value={district} readOnly />
              </label>
              <label>
                <span>Name</span>
                <input
                  type="text"
                  value={form.name}
                  onChange={(event) => setForm({ ...form, name: event.target.value })}
                  maxLength={120}
                  autoComplete="name"
                  required
                />
              </label>
              <label>
                <span>Mobile</span>
                <input
                  type="tel"
                  value={form.mobile}
                  onChange={(event) => setForm({ ...form, mobile: event.target.value })}
                  maxLength={25}
                  autoComplete="tel"
                  required
                />
              </label>
              <label>
                <span>Email</span>
                <input
                  type="email"
                  value={form.email}
                  onChange={(event) => setForm({ ...form, email: event.target.value })}
                  maxLength={180}
                  autoComplete="email"
                  required
                />
              </label>
              <label>
                <span>Address</span>
                <textarea
                  value={form.address}
                  onChange={(event) => setForm({ ...form, address: event.target.value })}
                  maxLength={500}
                  rows={3}
                  autoComplete="street-address"
                  required
                />
              </label>
              <label>
                <span>Age</span>
                <input
                  type="number"
                  value={form.age}
                  onChange={(event) => setForm({ ...form, age: event.target.value })}
                  min="1"
                  max="120"
                  required
                />
              </label>
              <label>
                <span>Gender</span>
                <select value={form.gender} onChange={(event) => setForm({ ...form, gender: event.target.value })} required>
                  <option value="">Select gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </label>

              {formError && <p className="dashboard-data-error" role="alert">{formError}</p>}
              <div className="leads-form-actions">
                <button type="button" className="leads-cancel-button" onClick={closeAddModal} disabled={isSubmitting}>
                  Cancel
                </button>
                <button type="submit" className="team-leader-add-lead" disabled={isSubmitting}>
                  {isSubmitting ? 'Saving…' : 'Submit'}
                </button>
              </div>
            </form>
          </section>
        </div>
      )}
    </section>
  )
}
