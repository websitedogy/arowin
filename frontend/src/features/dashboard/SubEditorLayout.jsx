import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from './components/Icon'
import { subEditorFeatures } from './subEditorFeatures'

const navigation = [
  { label: 'Dashboard', icon: 'home', path: '/' },
  { label: 'All Employees', icon: 'users', path: '/employees/all' },
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
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => window.matchMedia('(max-width: 850px)').matches)
  const [showProfileMenu, setShowProfileMenu] = useState(false)
  const [showNotifications, setShowNotifications] = useState(false)
  const [search, setSearch] = useState('')
  const [employees, setEmployees] = useState([])
  const [activities, setActivities] = useState([])
  const [dataLoadError, setDataLoadError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

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

  const searchResults = useMemo(() => {
    const query = search.trim().toLowerCase()
    if (!query) return []
    return employees.filter((employee) =>
      `${employee.employeeId} ${employee.fullName} ${employee.email} ${employee.role}`.toLowerCase().includes(query),
    ).slice(0, 5)
  }, [employees, search])

  const currentUserName = user?.name || 'Sub Editor User'
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
    <div className={`dashboard-shell${sidebarCollapsed ? ' sidebar-collapsed' : ''}`}>
      <aside className="sidebar">
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
                onClick={() => onNavigate(item.path)}
              >
                <Icon name={item.icon} size={20} />
                <span className="nav-label">{item.label}</span>
              </button>
            )
          })}
          <div className="sidebar-nav-section">
            <span className="sidebar-nav-heading">Sub Editor Features</span>
            {subEditorFeatures.map((item) => {
              const active = currentPath === item.path || (item.path === '/targets' && currentPath.startsWith('/targets/'))
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
          </div>
        </nav>
        <div className="sidebar-wave" aria-hidden="true" />
      </aside>

      <main className="dashboard-main">
        <div className="topbar">
          <button type="button" className="back-page-button" onClick={onBack} aria-label="Go back">
            <Icon name="back" size={18} />
            <span>Back</span>
          </button>
          <button type="button" className="menu-toggle" onClick={() => setSidebarCollapsed((value) => !value)} aria-label="Toggle sidebar">
            <Icon name="menu" size={21} />
          </button>
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
                className={`notification-button${activities.length ? ' has-notifications' : ''}`}
                aria-label="Notifications"
                aria-expanded={showNotifications}
                onClick={() => {
                  setShowNotifications((value) => !value)
                  setShowProfileMenu(false)
                }}
              >
                <Icon name="bell" size={21} />
                {activities.length > 0 && <span className="notification-dot" />}
              </button>
              {showNotifications && (
                <div className="notification-popover">
                  <strong>Notifications</strong>
                  {activities.length ? <p>{activities.length} employee update{activities.length === 1 ? '' : 's'} recorded.</p> : <p>You’re all caught up.</p>}
                </div>
              )}
            </div>
            <div className="profile-menu">
              <button
                type="button"
                className="profile-trigger"
                onClick={() => {
                  setShowProfileMenu((value) => !value)
                  setShowNotifications(false)
                }}
                aria-expanded={showProfileMenu}
              >
                <span className="profile-avatar">{currentUserName[0]?.toUpperCase() || 'A'}</span>
                <span className="profile-name">{currentUserName}</span>
                <Icon name="down" size={15} />
              </button>
              {showProfileMenu && (
                <div className="profile-dropdown">
                  <button type="button" onClick={onLogout}>Sign out</button>
                </div>
              )}
            </div>
          </div>
        </div>

        {dataLoadError && <p className="dashboard-data-error workspace-data-error" role="alert">{dataLoadError}</p>}
        {pageContent}
      </main>
    </div>
  )
}
