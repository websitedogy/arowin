import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import Icon from '../dashboard/components/Icon'

const initialForm = {
  customerName: '',
  mobileNumber: '',
  district: '',
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
  const [form, setForm] = useState(initialForm)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
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
                <th>District</th>
                <th>Note</th>
                <th>Added</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="5" className="sub-editor-feature-empty">Loading leads…</td></tr>
              ) : error && !leads.length ? (
                <tr><td colSpan="5" className="sub-editor-feature-empty">Leads could not be loaded.</td></tr>
              ) : leads.length ? leads.map((lead) => (
                <tr key={lead.id}>
                  <td>{lead.customerName}</td>
                  <td>{lead.mobileNumber}</td>
                  <td>{lead.district}</td>
                  <td>{lead.note || '—'}</td>
                  <td>{displayDate(lead.createdAt)}</td>
                </tr>
              )) : (
                <tr><td colSpan="5" className="sub-editor-feature-empty">No leads yet. Select <strong>Add Entry</strong> to add your first lead.</td></tr>
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
                <p>Enter the customer details below.</p>
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
                <span>District</span>
                <input
                  type="text"
                  value={form.district}
                  onChange={(event) => setForm({ ...form, district: event.target.value })}
                  maxLength={100}
                  required
                />
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

              {error && <p className="dashboard-data-error" role="alert">{error}</p>}
              <footer className="leads-form-actions">
                <button type="button" className="leads-cancel-button" onClick={closeModal} disabled={isSubmitting}>Cancel</button>
                <button type="submit" className="leads-add-button" disabled={isSubmitting}>
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
