import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'

export default function TeamLeaderStatesPage({ onNavigate }) {
  const [districts, setDistricts] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    apiRequest('/team-leader/state-districts')
      .then((result) => {
        if (active) setDistricts(result.districts)
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

  const openDistrictLeads = (district) => {
    onNavigate(`/team-leader/state/${district.stateCode}/${encodeURIComponent(district.district)}`)
  }

  return (
    <section className="team-leader-district-directory">
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}
      <div className="team-leader-district-table-wrap">
        <table className="employee-table team-leader-district-table">
          <thead>
            <tr>
              <th>District</th>
              <th>Employee ID</th>
              <th>Employee Name</th>
              <th>Employee Email</th>
              <th>Mobile</th>
              <th>Address</th>
              <th>Age</th>
              <th>Gender</th>
              <th>Lead Entry</th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <tr><td colSpan="9" className="sub-editor-feature-empty">Loading districts…</td></tr>
            ) : error ? (
              <tr><td colSpan="9" className="sub-editor-feature-empty">Districts could not be loaded.</td></tr>
            ) : districts.length ? districts.map((district) => {
              const lead = district.latestLead
              return (
                <tr key={`${district.stateCode}-${district.district}`}>
                  <td>
                    <span className="team-leader-district-name">{district.district}</span>
                    <small className="team-leader-district-state">{district.stateName}</small>
                  </td>
                  <td>{lead?.employeeId || '—'}</td>
                  <td>{lead?.employeeName || '—'}</td>
                  <td>{lead?.email || '—'}</td>
                  <td>{lead?.mobile || '—'}</td>
                  <td>{lead?.address || '—'}</td>
                  <td>{lead?.age ?? '—'}</td>
                  <td>{lead?.gender ? `${lead.gender[0].toUpperCase()}${lead.gender.slice(1)}` : '—'}</td>
                  <td>
                    <button
                      type="button"
                      className="team-leader-open-leads"
                      onClick={() => openDistrictLeads(district)}
                    >
                      Lead Entry
                    </button>
                  </td>
                </tr>
              )
            }) : (
              <tr><td colSpan="9" className="sub-editor-feature-empty">No assigned districts yet.</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </section>
  )
}
