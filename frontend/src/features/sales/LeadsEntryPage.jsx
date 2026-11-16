import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import Icon from '../dashboard/components/Icon'

const initialForm = {
  customerName: '',
  mobileNumber: '',
  stateCode: '',
  district: '',
  email: '',
  address: '',
  dateOfBirth: '',
  gender: '',
  note: '',
}

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

export default function LeadsEntryPage() {
  const [leads, setLeads] = useState([])
  const [states, setStates] = useState([])
  const [form, setForm] = useState(initialForm)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [territoryError, setTerritoryError] = useState('')
  const [notice, setNotice] = useState('')

  useEffect(() => {
    let active = true
    apiRequest('/leads')
      .then((response) => {
        if (active) setLeads(response.leads)
      })
      .catch((requestError) => {
        if (active) setError(requestError.message)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })

    apiRequest('/territories')
      .then((response) => {
        if (active) setStates(response.states)
      })
      .catch((requestError) => {
        if (active) setTerritoryError(requestError.message)
      })

    return () => {
      active = false
    }
  }, [])

  const closeModal = () => {
    if (isSubmitting) return
    setIsModalOpen(false)
    setForm(initialForm)
    setError('')
  }

  const submitLead = async (event) => {
    event.preventDefault()
    setIsSubmitting(true)
    setError('')
    setNotice('')
    try {
      const response = await apiRequest('/leads', {
        method: 'POST',
        body: form,
      })
      setLeads((currentLeads) => [response.lead, ...currentLeads])
      setIsModalOpen(false)
      setForm(initialForm)
      setNotice('Lead entry added successfully.')
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const selectedState = states.find((state) => state.code === form.stateCode)

  return (
    <section className="leads-entry-page">
      <header className="leads-entry-heading">
        <div>
          <span className="mini-label">Telecaller Workspace</span>
          <h1>Leads Entry</h1>
          <p>Save and review your customer leads.</p>
        </div>
        <button type="button" className="leads-add-button" onClick={() => {
          setNotice('')
          setError('')
          setIsModalOpen(true)
        }}>
          <Icon name="plus" size={18} />
          Add Entry
        </button>
      </header>

      {notice && <p className="leads-entry-notice" role="status">{notice}</p>}
      {error && !isModalOpen && <p className="dashboard-data-error" role="alert">{error}</p>}

      <article className="dashboard-panel leads-table-panel">
        <div className="leads-table-heading">
          <div>
            <h2>Customer Leads</h2>
            <p>{leads.length} entr{leads.length === 1 ? 'y' : 'ies'}</p>
          </div>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table leads-table">
            <thead>
              <tr>
                <th>Customer Name</th>
                <th>Mobile Number</th>
                <th>Lead ID</th>
                <th>State</th>
                <th>District</th>
                <th>Email</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Note</th>
                <th>Added</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="10" className="sub-editor-feature-empty">Loading leads…</td></tr>
              ) : error && !leads.length ? (
                <tr><td colSpan="10" className="sub-editor-feature-empty">Leads could not be loaded.</td></tr>
              ) : leads.length ? leads.map((lead) => (
                <tr key={lead.id}>
                  <td>{lead.customerName}</td>
                  <td>{lead.mobileNumber}</td>
                  <td>{lead.leadId}</td>
                  <td>{lead.stateName || '—'}</td>
                  <td>{lead.district}</td>
                  <td>{lead.email || '—'}</td>
                  <td>{lead.age ?? '—'}</td>
                  <td>{lead.gender ? `${lead.gender[0].toUpperCase()}${lead.gender.slice(1)}` : '—'}</td>
                  <td>{lead.note || '—'}</td>
                  <td>{displayDate(lead.createdAt)}</td>
                </tr>
              )) : (
                <tr><td colSpan="10" className="sub-editor-feature-empty">No leads yet. Select <strong>Add Entry</strong> to add your first lead.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>

      {isModalOpen && (
        <div className="modal-backdrop leads-modal-backdrop" role="presentation" onMouseDown={(event) => {
          if (event.target === event.currentTarget) closeModal()
        }}>
          <section
            className="assignment-modal leads-entry-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="leads-entry-modal-title"
          >
            <header className="assignment-modal-heading">
              <div>
                <span className="mini-label">New lead</span>
                <h2 id="leads-entry-modal-title">Add Lead Entry</h2>
                <p>Enter the customer and location details below.</p>
              </div>
              <button type="button" className="close-btn" aria-label="Close" onClick={closeModal}>×</button>
            </header>

            <form className="leads-entry-form" onSubmit={submitLead}>
              <label>
                <span>Customer Name</span>
                <input
                  type="text"
                  value={form.customerName}
                  onChange={(event) => setForm({ ...form, customerName: event.target.value })}
                  maxLength={120}
                  autoComplete="name"
                  required
                />
              </label>
              <label>
                <span>Mobile Number</span>
                <input
                  type="tel"
                  value={form.mobileNumber}
                  onChange={(event) => setForm({ ...form, mobileNumber: event.target.value })}
                  maxLength={25}
                  autoComplete="tel"
                  required
                />
              </label>
              <label>
                <span>State / Union Territory</span>
                <select
                  value={form.stateCode}
                  onChange={(event) => setForm({ ...form, stateCode: event.target.value, district: '' })}
                  required
                >
                  <option value="">Select a state</option>
                  {states.map((state) => <option key={state.code} value={state.code}>{state.name}</option>)}
                </select>
              </label>
              <label>
                <span>District</span>
                <select
                  value={form.district}
                  onChange={(event) => setForm({ ...form, district: event.target.value })}
                  disabled={!selectedState}
                  required
                >
                  <option value="">Select a district</option>
                  {(selectedState?.districts || []).map((district) => <option key={district} value={district}>{district}</option>)}
                </select>
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
                <span>Date of Birth</span>
                <input
                  type="date"
                  value={form.dateOfBirth}
                  onChange={(event) => setForm({ ...form, dateOfBirth: event.target.value })}
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
              <label>
                <span>Note <small>(Optional)</small></span>
                <textarea
                  value={form.note}
                  onChange={(event) => setForm({ ...form, note: event.target.value })}
                  maxLength={500}
                  rows={3}
                />
              </label>

              <p className="territory-attribution">
                District data: <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">© OpenStreetMap contributors</a>
              </p>
              {territoryError && <p className="dashboard-data-error" role="alert">{territoryError}</p>}
              {error && <p className="dashboard-data-error" role="alert">{error}</p>}
              <footer className="leads-form-actions">
                <button type="button" className="leads-cancel-button" onClick={closeModal} disabled={isSubmitting}>Cancel</button>
                <button type="submit" className="leads-add-button" disabled={isSubmitting || !states.length}>
                  {isSubmitting ? 'Saving…' : 'Submit'}
                </button>
              </footer>
            </form>
          </section>
        </div>
      )}
    </section>
  )
}
