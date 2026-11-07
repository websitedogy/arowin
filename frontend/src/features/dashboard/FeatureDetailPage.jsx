import Icon from './components/Icon'

export default function FeatureDetailPage({ feature }) {
  return (
    <section className="sub-editor-feature-page">
      <span className="mini-label">Sub Editor Workspace</span>
      <h1>{feature.label}</h1>
      <p className="sub-editor-feature-description">{feature.status}. This page will show records here once the feature is connected.</p>
      <article className="dashboard-panel sub-editor-feature-table-panel">
        <div className="panel-title">
          <Icon name={feature.icon} size={22} />
          <h2>{feature.label}</h2>
        </div>
        <div className="employee-table-wrap">
          <table className="employee-table">
            <thead>
              <tr><th>Record</th><th>Status</th><th>Details</th></tr>
            </thead>
            <tbody>
              <tr>
                <td colSpan="3" className="sub-editor-feature-empty">No records available. Feature data is not connected yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </section>
  )
}
