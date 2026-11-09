import { useCallback, useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from './components/Icon'
import RoleProfileMenu from './components/RoleProfileMenu'
import { subEditorFeatures } from './subEditorFeatures'

const navigation = [
  { label: 'Dashboard', icon: 'home', path: '/' },
]

const navigationGroups = [
  {
    label: 'People',
    items: [
      { label: 'Employee KYC', icon: 'file', path: '/employee-kyc' },
      { label: 'All Employees', icon: 'users', path: '/employees/all' },
    ],
  },
  {
    label: 'Operations',
    items: [
      { label: 'Attendance Tracking', icon: 'clock', path: '/attendance' },
      { label: 'Leave Requests', icon: 'calendar', path: '/leave-requests' },
      { label: 'Daily Activities', icon: 'file', path: '/daily-activities' },
      { label: 'Support Tickets', icon: 'headset', path: '/support-tickets' },
    ],
  },
  {
    label: 'Sales & Incentives',
    items: [
      { label: 'Total Sales', icon: 'chart', path: '/sales/total' },
      { label: "This Week's Sales", icon: 'chart', path: '/sales/weekly' },
      { label: "Last Month's Sales", icon: 'chart', path: '/sales/monthly' },
      { label: 'Target Setup', icon: 'settings', path: '/targets' },
      { label: 'Incentive Earned', icon: 'plus', path: '/incentives/earned' },
      { label: 'Incentive Paid', icon: 'clock', path: '/incentives/paid' },
      { label: 'Achieved Rewards', icon: 'crown', path: '/rewards/achieved' },
      { label: 'Granted Rewards', icon: 'users', path: '/rewards/granted' },
      { label: 'All Leads', icon: 'file', path: '/leads' },
      { label: 'Notification Trigger', icon: 'bell', path: '/notifications' },
    ],
  },
  {
    label: 'Finance & Settings',
    items: [
      { label: 'Budget', icon: 'coins', path: '/budget' },
      { label: 'Settings', icon: 'settings', path: '/settings' },
    ],
  },
]

function isNavigationActive(path, currentPath) {
  if (path === '/') return currentPath === '/'
  if (path === '/employees/all' || path === '/staff') return currentPath === path
  if (path === '/reports') return currentPath === '/reports'
  if (path === '/settings') return currentPath === '/settings'
  return currentPath === path
}

function employeeRoute(employee) {
  if (employee.role === 'Team Leader') return '/employees/team-leaders'
  if (employee.role === 'Telecaller') return '/employees/telecallers'
  if (employee.role === 'Sub Editor') return '/employees/sub-editors'
  return '/employees/all'
}

export default function SubEditorLayout({ user, onLogout, onNavigate, onBack, currentPath, children }) {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => window.matchMedia('(max-width: 1024px)').matches)
  const [showProfileMenu, setShowProfileMenu] = useState(false)
  const [showNotifications, setShowNotifications] = useState(false)
  const [search, setSearch] = useState('')
  const [employees, setEmployees] = useState([])
  const [activities, setActivities] = useState([])
  const [supportTickets, setSupportTickets] = useState([])
  const [supportLoadError, setSupportLoadError] = useState('')
  const [dataLoadError, setDataLoadError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    const compactViewport = window.matchMedia('(max-width: 1024px)')
    const closeOnCompact = () => {
      if (compactViewport.matches) setSidebarCollapsed(true)
    }
    compactViewport.addEventListener('change', closeOnCompact)
    return () => compactViewport.removeEventListener('change', closeOnCompact)
  }, [])

  useEffect(() => {
    if (sidebarCollapsed || !window.matchMedia('(max-width: 1024px)').matches) return undefined
    const previousOverflow = document.body.style.overflow
    const closeOnEscape = (event) => {
      if (event.key === 'Escape') setSidebarCollapsed(true)
    }
    document.body.style.overflow = 'hidden'
    window.addEventListener('keydown', closeOnEscape)
    return () => {
      document.body.style.overflow = previousOverflow
      window.removeEventListener('keydown', closeOnEscape)
    }
  }, [sidebarCollapsed])

  const loadSupportTickets = useCallback(async () => {
    try {
      const result = await apiRequest('/support/tickets')
      setSupportTickets(result.tickets)
      setSupportLoadError('')
    } catch (error) {
      setSupportLoadError(error.message)
    }
  }, [])

  useEffect(() => {
    let active = true

    Promise.all([apiRequest('/employees'), apiRequest('/activity/recent')])
      .then(([employeeResult, activityResult]) => {
        if (!active) return
        setEmployees(employeeResult.employees)
        setActivities(activityResult.activities)
        setDataLoadError('')
      })
      .catch((error) => {
        if (active) setDataLoadError(error.message)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    const initialLoadTimer = window.setTimeout(loadSupportTickets, 0)
    const refreshTimer = window.setInterval(loadSupportTickets, 30000)
    return () => {
      window.clearTimeout(initialLoadTimer)
      window.clearInterval(refreshTimer)
    }
  }, [loadSupportTickets])

  const searchResults = useMemo(() => {
    const query = search.trim().toLowerCase()
    if (!query) return []
    return employees.filter((employee) =>
      `${employee.employeeId} ${employee.fullName} ${employee.email} ${employee.role}`.toLowerCase().includes(query),
    ).slice(0, 5)
  }, [employees, search])

  const currentUserName = user?.name || 'Sub Editor User'
  const currentPageTitle = currentPath === '/'
    ? 'Dashboard'
    : navigation.find((item) => item.path === currentPath)?.label
      || subEditorFeatures.find((item) => item.path === currentPath || (item.path === '/targets' && currentPath.startsWith('/targets/')))?.label
      || ({ '/employees/team-leaders': 'Team Leaders', '/employees/telecallers': 'Telecallers', '/employees/sub-editors': 'Sub Editors', '/employees/active': 'Active Employees', '/settings': 'Settings' })[currentPath]
      || 'Workspace'
  const ticketsNeedingAttention = supportTickets.filter((ticket) => ['open', 'in_progress'].includes(ticket.status))
  const pageContent = children({
    employees,
    setEmployees,
    activities,
    setActivities,
    dataLoadError,
    setDataLoadError,
    isLoading,
  })

  return (
    <div className={`dashboard-shell sub-editor-workspace${sidebarCollapsed ? ' sidebar-collapsed' : ''}`}>
      <button
        type="button"
        className="mobile-sidebar-overlay"
        aria-label="Close navigation menu"
        tabIndex={sidebarCollapsed ? -1 : 0}
        onClick={() => setSidebarCollapsed(true)}
      />
      <aside className="sidebar" id="sub-editor-sidebar">
        <button type="button" className="mobile-sidebar-close" aria-label="Close navigation menu" onClick={() => setSidebarCollapsed(true)}>
          <Icon name="close" size={19} />
        </button>
        <div className="brand-row">
          <div className="brand-icon">A</div>
          <div className="brand-copy">
            <span className="sidebar-label">Module Monolith</span>
            <h2>AROWIN</h2>
          </div>
        </div>

        <nav className="sidebar-nav" aria-label="Main navigation">
          {navigation.map((item) => {
            const active = isNavigationActive(item.path, currentPath)
            return (
              <button
                type="button"
                key={item.label}
                className={`nav-item${active ? ' active' : ''}`}
                aria-current={active ? 'page' : undefined}
                title={sidebarCollapsed ? item.label : undefined}
                onClick={() => {
                  onNavigate(item.path)
                  if (window.matchMedia('(max-width: 1024px)').matches) setSidebarCollapsed(true)
                }}
              >
                <Icon name={item.icon} size={20} />
                <span className="nav-label">{item.label}</span>
                {item.path === '/support-tickets' && ticketsNeedingAttention.length > 0 && (
                  <span className="support-nav-badge" aria-label={`${ticketsNeedingAttention.length} tickets need attention`}>
                    {ticketsNeedingAttention.length > 99 ? '99+' : ticketsNeedingAttention.length}
                  </span>
                )}
              </button>
            )
          })}
          {navigationGroups.map((group) => (
            <div className="sidebar-nav-section" key={group.label}>
              <span className="sidebar-nav-heading">{group.label}</span>
              {group.items.map((item) => {
                const active = currentPath === item.path
                  || (item.path === '/targets' && currentPath.startsWith('/targets/'))
                return (
                  <button
                    type="button"
                    key={item.path}
                    className={`nav-item${active ? ' active' : ''}`}
                    aria-current={active ? 'page' : undefined}
                    title={sidebarCollapsed ? item.label : undefined}
                    onClick={() => {
                      onNavigate(item.path)
                      if (window.matchMedia('(max-width: 1024px)').matches) setSidebarCollapsed(true)
                    }}
                  >
                    <Icon name={item.icon} size={20} />
                    <span className="nav-label">{item.label}</span>
                    {item.path === '/support-tickets' && ticketsNeedingAttention.length > 0 && (
                      <span className="support-nav-badge" aria-label={`${ticketsNeedingAttention.length} tickets need attention`}>
                        {ticketsNeedingAttention.length > 99 ? '99+' : ticketsNeedingAttention.length}
                      </span>
                    )}
                  </button>
                )
              })}
            </div>
          ))}
        </nav>
        <div className="sidebar-wave" aria-hidden="true" />
      </aside>

      <main className="dashboard-main">
        <header className="topbar sub-editor-topbar">
          <div className="team-header-identity">
            <button
              type="button"
              className="menu-toggle"
              onClick={() => setSidebarCollapsed((value) => !value)}
              aria-label={sidebarCollapsed ? 'Open navigation menu' : 'Close navigation menu'}
              aria-expanded={!sidebarCollapsed}
              aria-controls="sub-editor-sidebar"
            >
              <Icon name={sidebarCollapsed ? 'menu' : 'close'} size={20} />
            </button>
            <div className="team-header-brand">
              <span className="team-header-brand-mark">A</span>
              <span><strong>AROWIN</strong><small>Sub Editor</small></span>
            </div>
            <span className="team-header-divider" />
            <span className="team-header-page">{currentPageTitle}</span>
          </div>
          <div className="topbar-tools">
            <div className="search-wrap">
              <Icon name="search" size={19} />
              <input
                type="search"
                aria-label="Search employees and teams"
                placeholder="Search employees, teams..."
                value={search}
                onChange={(event) => setSearch(event.target.value)}
              />
              {search.trim() && (
                <div className="search-results">
                  {searchResults.length ? searchResults.map((employee) => (
                    <button
                      type="button"
                      className="search-result"
                      key={employee.id}
                      onClick={() => {
                        onNavigate(employeeRoute(employee))
                        setSearch('')
                      }}
                    >
                      <span className="search-result-avatar">{employee.fullName?.[0]?.toUpperCase() || 'E'}</span>
                      <span><strong>{employee.fullName}</strong><small>{employee.role}</small></span>
                    </button>
                  )) : <p>No matching employees</p>}
                </div>
              )}
            </div>
            <div className="notification-wrap">
              <button
                type="button"
                className={`notification-button${activities.length || ticketsNeedingAttention.length ? ' has-notifications' : ''}`}
                aria-label={`Notifications${ticketsNeedingAttention.length ? `, ${ticketsNeedingAttention.length} support tickets need attention` : ''}`}
                aria-expanded={showNotifications}
                onClick={() => {
                  setShowNotifications((value) => !value)
                  setShowProfileMenu(false)
                }}
              >
                <Icon name="bell" size={21} />
                {(activities.length > 0 || ticketsNeedingAttention.length > 0) && <span className="notification-dot" />}
              </button>
              {showNotifications && (
                <div className="notification-popover">
                  <strong>Notifications</strong>
                  {supportLoadError && <p className="support-notification-error" role="alert">{supportLoadError}</p>}
                  {ticketsNeedingAttention.length > 0 && (
                    <button
                      type="button"
                      className="support-notification-link"
                      onClick={() => {
                        setShowNotifications(false)
                        onNavigate('/support-tickets')
                      }}
                    >
                      <span className="support-notification-icon"><Icon name="headset" size={17} /></span>
                      <span>
                        <b>{ticketsNeedingAttention.length} support ticket{ticketsNeedingAttention.length === 1 ? '' : 's'} need attention</b>
                        <small>Open the support inbox to respond</small>
                      </span>
                      <Icon name="chevron" size={16} />
                    </button>
                  )}
                  {activities.length > 0 && <p>{activities.length} employee update{activities.length === 1 ? '' : 's'} recorded.</p>}
                  {!ticketsNeedingAttention.length && !activities.length && !supportLoadError && <p>You’re all caught up.</p>}
                </div>
              )}
            </div>
            <RoleProfileMenu
              name={currentUserName}
              role="Sub Editor"
              avatar={<span className="profile-avatar">{currentUserName[0]?.toUpperCase() || 'A'}</span>}
              actionLabel="Settings"
              actionIcon="settings"
              actionPath="/settings"
              isOpen={showProfileMenu}
              onOpenChange={setShowProfileMenu}
              onOpen={() => setShowNotifications(false)}
              onNavigate={onNavigate}
              onLogout={onLogout}
            />
          </div>
        </header>

        <button type="button" className="team-content-back sub-editor-content-back" onClick={onBack}>
          <Icon name="back" size={17} />
          <span>Back</span>
        </button>
        {dataLoadError && <p className="dashboard-data-error workspace-data-error" role="alert">{dataLoadError}</p>}
        {pageContent}
      </main>
    </div>
  )
}
