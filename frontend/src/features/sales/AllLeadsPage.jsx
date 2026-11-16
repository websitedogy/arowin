import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import Icon from '../dashboard/components/Icon'

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

export default function AllLeadsPage() {
  const [leads, setLeads] = useState([])
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let active = true
    apiRequest('/leads/all')
      .then((result) => {
        if (active) setLeads(result.leads)
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

  return (
    <section className="sub-editor-feature-page">
      <span className="mini-label">Sub Editor Workspace</span>
      <h1>All Leads</h1>
      <p className="sub-editor-feature-description">Review customer leads submitted by Telecallers.</p>
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <article className="dashboard-panel sub-editor-feature-table-panel">
        <div className="panel-title">
          <Icon name="file" size={22} />
          <div>
            <h2>Customer Leads</h2>
            <small>{isLoading ? 'Loading…' : `${leads.length} ${leads.length === 1 ? 'entry' : 'entries'}`}</small>
          </div>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table leads-table">
            <thead>
              <tr>
                <th>Telecaller</th>
                <th>Employee ID</th>
                <th>Customer Name</th>
                <th>Mobile Number</th>
                <th>State</th>
                <th>District</th>
                <th>Email</th>
                <th>Address</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Note</th>
                <th>Added</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan="12" className="sub-editor-feature-empty">Loading leads…</td></tr>
              ) : error ? (
                <tr><td colSpan="12" className="sub-editor-feature-empty">Leads could not be loaded.</td></tr>
              ) : leads.length ? leads.map((lead) => (
                <tr key={lead.id}>
                  <td>{lead.employeeName}</td>
                  <td>{lead.employeeId}</td>
                  <td>{lead.customerName}</td>
                  <td>{lead.mobileNumber}</td>
                  <td>{lead.stateName || '—'}</td>
                  <td>{lead.district}</td>
                  <td>{lead.email || '—'}</td>
                  <td>{lead.address || '—'}</td>
                  <td>{lead.age ?? '—'}</td>
                  <td>{lead.gender ? `${lead.gender[0].toUpperCase()}${lead.gender.slice(1)}` : '—'}</td>
                  <td>{lead.note || '—'}</td>
                  <td>{displayDate(lead.createdAt)}</td>
                </tr>
              )) : (
                <tr><td colSpan="12" className="sub-editor-feature-empty">No Telecaller leads have been entered yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </article>
    </section>
  )
}
