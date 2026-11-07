export default function SettingsPage({ user }) {
  return (
    <section className="settings-page-content">
      <header className="page-heading settings-page-heading">
        <div className="page-heading-copy">
          <span className="mini-label">Workspace</span>
          <h1>Settings</h1>
          <p>Review your Sub Editor account details.</p>
        </div>
      </header>

      <article className="dashboard-panel settings-account-card">
        <h2>Account</h2>
        <dl>
          <div><dt>Name</dt><dd>{user.name}</dd></div>
          <div><dt>Employee ID</dt><dd>{user.employeeId}</dd></div>
          <div><dt>Email</dt><dd>{user.email}</dd></div>
          <div><dt>Role</dt><dd>Sub Editor</dd></div>
        </dl>
      </article>
    </section>
  )
}
