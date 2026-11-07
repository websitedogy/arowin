import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from './components/Icon'
import formatDashboardDate from './formatDashboardDate'
import AttendancePanel from '../attendance/AttendancePanel'

const teamLeaderPages = [
  { label: 'Dashboard', path: '/team-leader', icon: 'home', title: 'Team Leader Dashboard', description: 'Your team workspace overview.' },
  { label: 'Profile', path: '/team-leader/profile', icon: 'user', title: 'Profile', description: 'Your account details.' },
  { label: 'Attendance', path: '/team-leader/attendance', icon: 'calendar', title: 'Attendance', description: 'View your salary, attendance, and calendar history.' },
  { label: 'Support', path: '/team-leader/support', icon: 'bell', title: 'Support', description: 'Support requests and responses will appear here when support is configured.', status: 'No support requests yet.' },
  { label: 'Daily Activities', path: '/team-leader/daily-activities', icon: 'file', title: 'Daily Activities', description: 'Your daily work updates will appear here when activity tracking is configured.', status: 'Daily activity tracking is not configured yet.' },
  { label: 'My team', path: '/team-leader/my-team', icon: 'users', title: 'My Team', description: 'Employees assigned to your team will appear here.', status: 'No team members are assigned yet.' },
  { label: 'Team sales', path: '/team-leader/team-sales', icon: 'chart', title: 'Team Sales', description: 'Sales recorded for your team will appear here.', status: 'Team sales are not available yet.' },
  { label: 'Team Incentives', path: '/team-leader/team-incentives', icon: 'gift', title: 'Team Incentives', description: 'Team incentive records will appear here.', status: 'Team incentives are not available yet.' },
  { label: 'Team Grant Incentives', path: '/team-leader/team-grant-incentives', icon: 'coins', title: 'Team Grant Incentives', description: 'Team incentive grants and their status will appear here.', status: 'Incentive grants are not configured yet.' },
  { label: 'Team Rewards', path: '/team-leader/team-rewards', icon: 'trophy', title: 'Team Rewards', description: 'Rewards earned by your team will appear here.', status: 'Team rewards are not available yet.' },
  { label: 'Grant Team Rewards', path: '/team-leader/grant-team-rewards', icon: 'medal', title: 'Grant Team Rewards', description: 'Reward grants will be managed here when reward permissions and rules are configured.', status: 'Reward granting is not configured yet.' },
]

const telecallerSections = [
  { label: 'My Unlock Sales', value: '—', icon: 'trend', tone: 'blue', note: 'Sales tracking is not configured' },
  { label: 'My Incentives', value: '—', icon: 'gift', tone: 'green', note: 'Incentive tracking is not configured' },
  { label: 'Our Total Team Sales', value: '—', icon: 'users', tone: 'orange', note: 'Team sales are not available yet' },
  { label: 'Our Team Achievers', value: '—', icon: 'trophy', tone: 'purple', note: 'Rankings will appear here' },
]

const telecallerNavItems = [
  { label: 'Dashboard', path: '/telecaller', icon: 'home', available: true },
  { label: 'My Attendance', path: '/telecaller/attendance', icon: 'calendar', available: true },
  { label: 'My Unlock Sales', icon: 'trend' },
  { label: 'My Incentives', icon: 'gift' },
  { label: 'Leads Entry', icon: 'userPlus' },
  { label: 'Daily Activities', icon: 'file' },
  { label: 'Our Total Team Sales', icon: 'chart' },
  { label: 'Our Team Achievers', icon: 'trophy' },
  { label: 'Rankers Supporting', icon: 'users' },
  { label: 'Support', icon: 'headset' },
  { label: 'Profile', icon: 'user' },
]

function TeamSalesChart() {
  return (
    <article className="team-dashboard-panel team-sales-panel">
      <div className="team-panel-heading">
        <div className="team-panel-title"><Icon name="chart" size={22} /><h2>Team Sales Trend</h2></div>
        <span className="team-data-status">Not configured</span>
      </div>
      <div className="team-sales-empty">
        <Icon name="chart" size={28} />
        <p>Sales data will appear here after sales tracking is configured.</p>
      </div>
    </article>
  )
}

function TeamOverviewPanel({ members }) {
  const activeCount = members.filter((member) => member.status === 'Active').length
  const inactiveCount = members.length - activeCount
  const activeDegrees = members.length ? (activeCount / members.length) * 360 : 0
  const chartBackground = members.length
    ? `conic-gradient(#09b965 0deg ${activeDegrees}deg, #aab8ca ${activeDegrees}deg 360deg)`
    : '#e5eaf2'

  return (
    <article className="team-dashboard-panel team-overview-panel">
      <div className="team-panel-heading">
        <div className="team-panel-title"><Icon name="users" size={23} /><h2>Team Overview</h2></div>
      </div>
      <div className="team-overview-content">
        <div className="team-overview-chart" style={{ background: chartBackground }} role="img" aria-label={`${members.length} assigned team members; ${activeCount} active and ${inactiveCount} inactive`}>
          <div className="team-overview-chart-center"><strong>{members.length}</strong><span>Members</span></div>
        </div>
        <ul className="team-overview-legend">
          <li><span className="team-legend-dot active" /><span>Active</span><strong>{activeCount}</strong></li>
          <li><span className="team-legend-dot inactive" /><span>Inactive</span><strong>{inactiveCount}</strong></li>
        </ul>
      </div>
    </article>
  )
}

function TeamPerformancePanel({ members, isLoading, search }) {
  const filteredMembers = members.filter((member) => (
    `${member.employeeId} ${member.fullName} ${member.mobile} ${member.email}`.toLowerCase().includes(search.trim().toLowerCase())
  ))

  return (
    <article className="team-dashboard-panel team-performance-panel">
      <div className="team-panel-heading">
        <div className="team-panel-title"><Icon name="users" size={23} /><h2>My Team</h2></div>
        <span className="team-member-total">{members.length} member{members.length === 1 ? '' : 's'}</span>
      </div>
      <div className="team-table-wrap">
        <table className="team-performance-table">
          <thead><tr><th>Employee ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Joining Date</th><th>Status</th></tr></thead>
          <tbody>
            {isLoading ? (
              <tr className="team-table-message-row"><td colSpan="6">Loading assigned Telecallers…</td></tr>
            ) : filteredMembers.length ? filteredMembers.map((member) => (
              <tr className="team-member-row" key={member.id}>
                <td data-label="Employee ID"><strong className="assignment-employee-id">{member.employeeId}</strong></td>
                <td data-label="Name">{member.fullName}</td>
                <td data-label="Phone">{member.mobile || '—'}</td>
                <td data-label="Email">{member.email}</td>
                <td data-label="Joining Date">{formatDashboardDate(member.joiningDate)}</td>
                <td data-label="Status"><span className={`team-member-status${member.status === 'Active' ? ' is-active' : ''}`}>{member.status}</span></td>
              </tr>
            )) : <tr className="team-table-message-row"><td colSpan="6">{search ? 'No assigned Telecallers match your search.' : 'No Telecallers have been assigned to your team yet.'}</td></tr>}
          </tbody>
        </table>
      </div>
    </article>
  )
}

function TeamActivitiesPanel({ activities }) {
  return (
    <article className="team-dashboard-panel team-activities-panel">
      <div className="team-panel-heading">
        <div className="team-panel-title"><Icon name="clock" size={23} /><h2>Recent Team Activities</h2></div>
      </div>
      {activities.length ? (
        <ul className="team-activities-list">
          {activities.map((activity, index) => (
            <li key={`${activity.time}-${index}`}>
              <span className="team-activity-icon green"><Icon name="users" size={22} /></span>
              <span className="team-activity-copy"><strong>{activity.title}</strong><small>{formatDashboardDate(activity.time)}</small></span>
            </li>
          ))}
        </ul>
      ) : <p className="team-activity-empty">Team activity will appear here after Telecallers are assigned.</p>}
    </article>
  )
}

function TeamLeaderHome({ teamData, isLoading, error, search }) {
  const members = teamData?.members || []
  const metrics = [
    { label: 'Team Members', value: isLoading ? '…' : String(members.length), icon: 'users', tone: 'blue' },
    { label: 'Team Sales', value: '—', status: 'Not configured', icon: 'trend', tone: 'green' },
    { label: 'Team Incentives', value: '—', status: 'Not configured', icon: 'gift', tone: 'orange' },
    { label: 'Team Rewards', value: '—', status: 'Not configured', icon: 'trophy', tone: 'purple' },
  ]

  return (
    <>
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}
      <div className="team-metrics-grid">
        {metrics.map((metric) => (
          <article className={`team-metric-card ${metric.tone}`} key={metric.label}>
            <span className="team-metric-icon"><Icon name={metric.icon} size={27} /></span>
            <div><strong>{metric.value}</strong><span>{metric.label}</span>{metric.status && <small>{metric.status}</small>}</div>
            {metric.label === 'Team Members' && <Icon className="team-metric-decoration" name="users" size={48} />}
          </article>
        ))}
      </div>
      <div className="team-dashboard-grid team-dashboard-grid-top">
        <TeamSalesChart />
        <TeamOverviewPanel members={members} />
      </div>
      <div className="team-dashboard-grid team-dashboard-grid-bottom">
        <TeamPerformancePanel members={members} isLoading={isLoading} search={search} />
        <TeamActivitiesPanel activities={teamData?.activities || []} />
      </div>
    </>
  )
}

function TeamLeaderContent({ user, page, teamData, isLoadingTeam, teamError, teamSearch }) {
  if (page.path === '/team-leader/attendance') {
    return <AttendancePanel />
  }

  if (page.path === '/team-leader/my-team') {
    return (
      <>
        {teamError && <p className="dashboard-data-error" role="alert">{teamError}</p>}
        <TeamPerformancePanel
          members={teamData?.members || []}
          isLoading={isLoadingTeam}
          search={teamSearch}
        />
      </>
    )
  }

  if (page.path === '/team-leader/profile') {
    return (
      <article className="dashboard-panel role-detail-panel">
        <h2>Account Details</h2>
        <dl className="role-profile-details">
          <div><dt>Name</dt><dd>{user.name}</dd></div>
          <div><dt>Employee ID</dt><dd>{user.employeeId || 'Not available'}</dd></div>
          <div><dt>Email</dt><dd>{user.email}</dd></div>
          <div><dt>Role</dt><dd>Team Leader</dd></div>
        </dl>
      </article>
    )
  }

  if (page.path === '/team-leader') {
    return <TeamLeaderHome teamData={teamData} isLoading={isLoadingTeam} error={teamError} search={teamSearch} />
  }

  return (
    <article className="dashboard-panel role-detail-panel">
      <span className="role-work-icon"><Icon name={page.icon} size={20} /></span>
      <p className="role-dashboard-description">{page.description}</p>
      <strong className="role-detail-status">{page.status}</strong>
    </article>
  )
}

function TelecallerContent({ onNavigate }) {
  return (
    <div className="telecaller-dashboard">
      <header className="telecaller-dashboard-heading">
        <div>
          <span className="mini-label">Telecaller</span>
          <h1>Telecaller Dashboard</h1>
          <p>Track your sales, incentives, activities and team performance.</p>
        </div>
        <span className="telecaller-period"><Icon name="calendar" size={17} /> Overview</span>
      </header>

      <div className="telecaller-metrics-grid">
        {telecallerSections.map((metric) => (
          <article className={`telecaller-metric-card ${metric.tone}`} key={metric.label}>
            <span className="telecaller-metric-icon"><Icon name={metric.icon} size={25} /></span>
            <div className="telecaller-metric-copy">
              <strong>{metric.value}</strong>
              <span>{metric.label}</span>
              <small>{metric.note}</small>
            </div>
          </article>
        ))}
      </div>

      <div className="telecaller-panels-grid">
        <article className="telecaller-panel telecaller-sales-panel">
          <div className="telecaller-panel-heading">
            <span className="telecaller-panel-icon"><Icon name="chart" size={21} /></span>
            <div><h2>My Sales Trend</h2><p>Performance overview</p></div>
          </div>
          <div className="telecaller-empty-chart">
            <span className="telecaller-empty-illustration"><Icon name="trend" size={27} /></span>
            <strong>Sales data will appear here</strong>
            <p>Sales tracking is not configured for your workspace yet.</p>
          </div>
        </article>

        <article className="telecaller-panel telecaller-attendance-card">
          <div className="telecaller-panel-heading">
            <span className="telecaller-panel-icon"><Icon name="calendar" size={21} /></span>
            <div><h2>My Attendance</h2><p>Your attendance and salary details</p></div>
          </div>
          <div className="telecaller-attendance-summary">
            <span className="telecaller-attendance-mark"><Icon name="calendar" size={27} /></span>
            <div><strong>Stay on top of your day</strong><p>Check in, view your salary progress and attendance history.</p></div>
          </div>
          <button type="button" className="telecaller-primary-button" onClick={() => onNavigate('/telecaller/attendance')}>
            View attendance <Icon name="arrow" size={17} />
          </button>
        </article>
      </div>

      <div className="telecaller-panels-grid telecaller-lower-grid">
        <article className="telecaller-panel telecaller-activity-panel">
          <div className="telecaller-panel-heading">
            <span className="telecaller-panel-icon"><Icon name="clock" size={21} /></span>
            <div><h2>Daily Activities</h2><p>Your progress at a glance</p></div>
          </div>
          <div className="telecaller-activity-stats">
            {[
              { label: 'Calls done', icon: 'headset', tone: 'green' },
              { label: 'Leads added', icon: 'userPlus', tone: 'blue' },
              { label: 'Follow-ups', icon: 'calendar', tone: 'purple' },
              { label: 'Conversions', icon: 'trend', tone: 'orange' },
            ].map((item) => (
              <div className={`telecaller-activity-stat ${item.tone}`} key={item.label}>
                <Icon name={item.icon} size={19} /><strong>—</strong><span>{item.label}</span>
              </div>
            ))}
          </div>
          <p className="telecaller-panel-footnote">Activity tracking is not configured yet.</p>
        </article>

        <article className="telecaller-panel telecaller-leads-panel">
          <span className="telecaller-leads-icon"><Icon name="userPlus" size={24} /></span>
          <div><h2>Leads Entry</h2><p>Add new leads and track your follow-ups.</p></div>
          <span className="telecaller-coming-soon">Coming soon</span>
        </article>
      </div>
    </div>
  )
}

export default function RoleDashboard({ user, onLogout, onBack, currentPath, onNavigate }) {
  const isTeamLeader = user.role === 'team_leader'
  const dashboard = {
    roleLabel: isTeamLeader ? 'Team Leader' : 'Telecaller',
    title: isTeamLeader ? 'Team Leader Dashboard' : 'Telecaller Dashboard',
    accent: isTeamLeader ? 'leader' : 'telecaller',
  }
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => window.matchMedia('(max-width: 850px)').matches)
  const [teamData, setTeamData] = useState(null)
  const [isLoadingTeam, setIsLoadingTeam] = useState(isTeamLeader)
  const [teamError, setTeamError] = useState('')
  const [teamSearch, setTeamSearch] = useState('')
  const page = teamLeaderPages.find((item) => item.path === currentPath) || teamLeaderPages[0]
  const navItems = isTeamLeader ? teamLeaderPages : []

  useEffect(() => {
    if (!isTeamLeader || !['/team-leader', '/team-leader/my-team'].includes(currentPath)) return undefined

    let active = true
    const loadTeam = () => {
      apiRequest('/team-leader/team')
        .then((result) => {
          if (active) {
            setTeamData(result.team)
            setTeamError('')
          }
        })
        .catch((error) => {
          if (active) setTeamError(error.message)
        })
        .finally(() => {
          if (active) setIsLoadingTeam(false)
        })
    }

    loadTeam()
    const refreshTimer = window.setInterval(loadTeam, 30000)

    return () => {
      active = false
      window.clearInterval(refreshTimer)
    }
  }, [currentPath, isTeamLeader, user.id])

  return (
    <div className={`dashboard-shell role-workspace-shell${isTeamLeader ? '' : ' telecaller-workspace-shell'}${sidebarCollapsed ? ' sidebar-collapsed' : ''}`}>
      {isTeamLeader && (
        <aside className="sidebar">
          <div className="brand-row">
            <div className="brand-icon">A</div>
            <div className="brand-copy">
              <span className="sidebar-label">Team Workspace</span>
              <h2>AROWIN</h2>
            </div>
          </div>
          <nav className="sidebar-nav" aria-label="Team Leader navigation">
            {navItems.map((item) => {
              const active = item.path === currentPath || (item.path === '/team-leader' && currentPath === '/')
              return (
                <button
                  type="button"
                  key={item.path}
                  className={`nav-item${active ? ' active' : ''}`}
                  aria-current={active ? 'page' : undefined}
                  title={sidebarCollapsed ? item.label : undefined}
                  onClick={() => onNavigate(item.path)}
                >
                  <Icon name={item.icon} size={20} />
                  <span className="nav-label">{item.label}</span>
                </button>
              )
            })}
          </nav>
          <div className="sidebar-wave" aria-hidden="true" />
        </aside>
      )}
      {!isTeamLeader && (
        <aside className="telecaller-sidebar">
          <div className="telecaller-brand">
            <span className="telecaller-brand-mark">A</span>
            <span><small>Module Monolith</small><strong>AROWIN</strong></span>
          </div>
          <nav className="telecaller-nav" aria-label="Telecaller navigation">
            {telecallerNavItems.map((item) => {
              const active = item.path === currentPath || (item.path === '/telecaller' && currentPath === '/')
              return (
                <button
                  type="button"
                  key={item.label}
                  className={`telecaller-nav-item${active ? ' active' : ''}`}
                  aria-current={active ? 'page' : undefined}
                  disabled={!item.available}
                  title={item.available ? undefined : 'This feature is not configured yet'}
                  onClick={() => onNavigate(item.path)}
                >
                  <Icon name={item.icon} size={19} />
                  <span>{item.label}</span>
                  {!item.available && <small>Soon</small>}
                </button>
              )
            })}
          </nav>
          <div className="telecaller-sidebar-footer">
            <span className="telecaller-sidebar-user">{user.name?.[0]?.toUpperCase() || 'T'}</span>
            <span className="telecaller-sidebar-user-copy"><strong>{user.name}</strong><small>Telecaller</small></span>
            <button type="button" onClick={onLogout} aria-label="Sign out"><Icon name="logout" size={18} /></button>
          </div>
        </aside>
      )}

      <main className={`dashboard-main role-dashboard role-dashboard-${dashboard.accent}`}>
        <header className="role-dashboard-topbar">
          {isTeamLeader ? (
            <button type="button" className="back-page-button" onClick={onBack} aria-label="Go back">
              <Icon name="back" size={18} />
              <span>Back</span>
            </button>
          ) : (
            <div className="telecaller-topbar-brand">
              <button type="button" className="telecaller-menu-toggle" onClick={() => setSidebarCollapsed((value) => !value)} aria-label="Toggle navigation">
                <Icon name="menu" size={20} />
              </button>
              <span className="telecaller-topbar-dot" /> Workspace
            </div>
          )}
          {isTeamLeader ? (
            <>
              <div className="team-header-identity">
                <button type="button" className="menu-toggle" onClick={() => setSidebarCollapsed((value) => !value)} aria-label="Toggle sidebar">
                  <Icon name="menu" size={20} />
                </button>
                <div className="team-header-brand">
                  <span className="team-header-brand-mark">A</span>
                  <span><strong>AROWIN</strong><small>Team Leader</small></span>
                </div>
                <span className="team-header-divider" />
                <span className="team-header-page">{page.title}</span>
              </div>
            </>
          ) : (
            <>
              <button
                type="button"
                className="role-attendance-nav"
                aria-current={currentPath === '/telecaller/attendance' ? 'page' : undefined}
                onClick={() => onNavigate(currentPath === '/telecaller/attendance' ? '/telecaller' : '/telecaller/attendance')}
              >
                <Icon name="calendar" size={18} />
                {currentPath === '/telecaller/attendance' ? 'Dashboard' : 'Attendance'}
              </button>
            </>
          )}
          {isTeamLeader && ['/team-leader', '/team-leader/my-team'].includes(currentPath) && (
            <div className="team-topbar-search">
              <Icon name="search" size={19} />
              <input
                aria-label="Search assigned Telecallers"
                placeholder="Search team members..."
                value={teamSearch}
                onChange={(event) => setTeamSearch(event.target.value)}
              />
            </div>
          )}
          <div className="role-user">
            {isTeamLeader && ['/team-leader', '/team-leader/my-team'].includes(currentPath) && <button className="team-notification-button" type="button" aria-label="Notifications"><Icon name="bell" size={20} />{teamData?.activities?.length > 0 && <span>{teamData.activities.length}</span>}</button>}
            <span className="profile-avatar">{isTeamLeader ? 'TL' : user.name?.[0]?.toUpperCase() || 'A'}</span>
            <div><strong>{user.name}</strong><small>{dashboard.roleLabel}</small></div>
            {isTeamLeader && <button type="button" className="role-logout" onClick={onLogout}>Sign out</button>}
          </div>
        </header>

        {isTeamLeader ? (
          <section className={`role-dashboard-content${page.path === '/team-leader' ? ' team-leader-dashboard-content' : ''}`}>
            {page.path === '/team-leader' ? (
              <div className="team-dashboard-heading">
                <div>
                  <span className="mini-label">Team Leader</span>
                  <h1>Welcome, {user.name}</h1>
                  <p>Team Leader Dashboard · Your team activities, sales, incentives and rewards</p>
                </div>
                <div className="team-dashboard-heading-actions">
                  <div className="team-date-select">
                    <Icon name="calendar" size={18} />
                    <span>All time</span>
                  </div>
                </div>
              </div>
            ) : (
              <header className="team-subpage-heading">
                <span className="mini-label">Team Workspace</span>
                <h1>{page.title}</h1>
                <p className="role-dashboard-description">{page.description}</p>
              </header>
            )}
            <TeamLeaderContent
              user={user}
              page={page}
              teamData={teamData}
              isLoadingTeam={isLoadingTeam}
              teamError={teamError}
              teamSearch={teamSearch}
            />
          </section>
        ) : (
          <section className={`role-dashboard-content${currentPath === '/telecaller/attendance' ? '' : ' telecaller-home-content'}`}>
          {currentPath === '/telecaller/attendance' ? (
            <>
              <span className="mini-label">Telecaller Workspace</span>
              <h1>Attendance</h1>
              <p className="role-dashboard-description">View your salary progress and manage today’s attendance.</p>
              <AttendancePanel />
            </>
          ) : (
            <TelecallerContent onNavigate={onNavigate} />
          )}
        </section>
        )}
      </main>
    </div>
  )
}
