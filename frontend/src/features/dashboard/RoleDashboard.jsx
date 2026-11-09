import { useCallback, useEffect, useRef, useState } from 'react'
import { apiRequest } from '../../api'
import { formatMonth, formatUsdt } from '../../utils/formatters'
import Icon from './components/Icon'
import RoleProfileMenu from './components/RoleProfileMenu'
import formatDashboardDate from './formatDashboardDate'
import AttendancePanel from '../attendance/AttendancePanel'
import SalesTrendPanel from '../sales/SalesTrendPanel'
import UnlockSalesPage from '../sales/UnlockSalesPage'
import MyIncentivesPage from '../sales/MyIncentivesPage'
import TeamIncentivesPage from '../sales/TeamIncentivesPage'
import TeamGrantIncentivesPage from '../sales/TeamGrantIncentivesPage'
import TeamRewardsPage from '../sales/TeamRewardsPage'
import LeadsEntryPage from '../sales/LeadsEntryPage'
import LeaveManagementPage from '../leave/LeaveManagementPage'
import DailyActivitiesPage from '../activities/DailyActivitiesPage'
import SupportTicketsPage from '../support/SupportTicketsPage'
import EmployeeKycPage from '../employees/EmployeeKycPage'

const teamLeaderNavigationGroups = [
  {
    items: [
      { label: 'Dashboard', path: '/team-leader', icon: 'home', title: 'Team Leader Dashboard', description: 'Your team workspace overview.' },
    ],
  },
  {
    label: 'My Work',
    items: [
      { label: 'Profile', path: '/team-leader/profile', icon: 'user', title: 'Profile', description: 'Your account details.' },
      { label: 'Employee KYC', path: '/team-leader/kyc', icon: 'file', title: 'Employee KYC', description: 'Submit your profile, bank, and identity details securely.' },
      { label: 'Attendance', path: '/team-leader/attendance', icon: 'calendar', title: 'Attendance', description: 'View your salary, attendance, and calendar history.' },
      { label: 'Leave', path: '/team-leader/leave', icon: 'calendar', title: 'Leave', description: 'Apply for leave and review your request status.' },
      { label: 'Daily Activities', path: '/team-leader/daily-activities', icon: 'file', title: 'Daily Activities', description: 'Submit platform activity screenshots and check their verification status.' },
      { label: 'Support', path: '/team-leader/support', icon: 'headset', title: 'Support Center' },
    ],
  },
  {
    label: 'Team Management',
    items: [
      { label: 'My team', path: '/team-leader/my-team', icon: 'users', title: 'My Team', description: 'Employees assigned to your team will appear here.', status: 'No team members are assigned yet.' },
      { label: 'Team sales', path: '/team-leader/team-sales', icon: 'chart', title: 'Team Sales', description: 'Sales recorded for your team will appear here.', status: 'Team sales are not available yet.' },
      { label: 'Team Achievers', path: '/team-leader/team-achievers', icon: 'trophy', title: 'Team Achievers', description: 'Approved sales achievements by your assigned Telecallers.' },
    ],
  },
  {
    label: 'Sales & Rewards',
    items: [
      { label: 'My Unlock Sales', path: '/team-leader/sales', icon: 'trend', title: 'My Unlock Sales', description: 'Submit your own package sales for Sub Editor review and track their status.' },
      { label: 'My Incentives', path: '/team-leader/incentives', icon: 'gift', title: 'My Incentives', description: 'View incentives earned from your approved package sales.' },
      { label: 'Team Incentives', path: '/team-leader/team-incentives', icon: 'gift', title: 'Team Incentives', description: 'View package-sale incentives earned by your assigned Telecallers.' },
      { label: 'Team Grant Incentives', path: '/team-leader/team-grant-incentives', icon: 'coins', title: 'Team Grant Incentives', description: 'See each Telecaller’s equal monthly target and your incentive share when everyone reaches their target.' },
      { label: 'Team Rewards', path: '/team-leader/team-rewards', icon: 'trophy', title: 'Team Rewards', description: 'Track each assigned Telecaller’s contribution to the shared monthly sales target.' },
      { label: 'Grant Team Rewards', path: '/team-leader/grant-team-rewards', icon: 'medal', title: 'Grant Team Rewards', description: 'View monthly target rewards automatically recorded for you when your team meets its target.' },
    ],
  },
]

const teamLeaderPages = teamLeaderNavigationGroups.flatMap((group) => group.items)

const telecallerSections = [
  { label: 'My Unlock Sales', value: '—', icon: 'trend', tone: 'blue', note: 'Sales tracking is not configured' },
  { label: 'My Incentives', value: '—', icon: 'gift', tone: 'green', note: 'Approved sale incentives' },
  { label: 'Our Team Achievers', value: '—', icon: 'trophy', tone: 'purple', note: 'Approved teammate sale achievements' },
]

const telecallerNavigationGroups = [
  {
    items: [
      { label: 'Dashboard', path: '/telecaller', icon: 'home', available: true },
    ],
  },
  {
    label: 'My Work',
    items: [
      { label: 'Profile', path: '/telecaller/profile', icon: 'user', available: true },
      { label: 'Employee KYC', path: '/telecaller/kyc', icon: 'file', available: true },
      { label: 'My Attendance', path: '/telecaller/attendance', icon: 'calendar', available: true },
      { label: 'Leave', path: '/telecaller/leave', icon: 'calendar', available: true },
      { label: 'Daily Activities', path: '/telecaller/daily-activities', icon: 'file', available: true },
      { label: 'Support', path: '/telecaller/support', icon: 'headset', available: true },
    ],
  },
  {
    label: 'Sales & Performance',
    items: [
      { label: 'My Unlock Sales', path: '/telecaller/sales', icon: 'trend', available: true },
      { label: 'My Incentives', path: '/telecaller/incentives', icon: 'gift', available: true },
      { label: 'Our Team Achievers', path: '/telecaller/team-achievers', icon: 'trophy', available: true },
      { label: 'Leads Entry', path: '/telecaller/leads', icon: 'userPlus', available: true },
    ],
  },
]

const telecallerNavItems = telecallerNavigationGroups.flatMap((group) => group.items)

const activityMonthOptions = Array.from({ length: 12 }, (_, offset) => {
  const date = new Date()
  date.setDate(1)
  date.setMonth(date.getMonth() - offset)
  return {
    value: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`,
    label: new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(date),
  }
})

const currentActivityMonth = activityMonthOptions[0].value

function TeamSalesChart({ performance, isLoading, error, month, onMonthChange }) {
  return (
    <SalesTrendPanel
      team
      compact
      performance={performance}
      isLoading={isLoading}
      error={error}
      month={month}
      onMonthChange={onMonthChange}
    />
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

function TeamAchieversPanel() {
  const [achievements, setAchievements] = useState([])
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let active = true
    const loadAchievements = () => {
      apiRequest('/sales/team-achievements')
        .then((response) => {
          if (!active) return
          setAchievements(response.result.achievements)
          setError('')
        })
        .catch((requestError) => {
          if (active) setError(requestError.message)
        })
        .finally(() => {
          if (active) setIsLoading(false)
        })
    }

    loadAchievements()
    const refreshTimer = window.setInterval(loadAchievements, 30000)
    return () => {
      active = false
      window.clearInterval(refreshTimer)
    }
  }, [])

  return (
    <article className="telecaller-panel team-achievers-panel">
      <div className="telecaller-panel-heading">
        <span className="telecaller-panel-icon"><Icon name="trophy" size={21} /></span>
        <div><h2>Our Team Achievers</h2><p>Approved sale achievements from your team</p></div>
      </div>
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}
      {isLoading ? (
        <p className="team-achievers-message" role="status">Loading team achievements…</p>
      ) : error ? (
        <p className="team-achievers-message">Team achievements could not be loaded.</p>
      ) : achievements.length ? (
        <ul className="team-achievers-list">
          {achievements.map((achievement) => (
            <li key={achievement.id}>
              <span className="team-achievers-avatar">{achievement.employeeName.slice(0, 1).toUpperCase()}</span>
              <strong>{achievement.employeeName}</strong>
              <span>Achieved a sale</span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="team-achievers-message">No approved team sales yet.</p>
      )}
    </article>
  )
}

function ProfilePhoto({ profileImage, name, className, onError }) {
  const [loadedImage, setLoadedImage] = useState(null)

  useEffect(() => {
    let active = true
    let nextImageUrl = ''
    if (!profileImage) return undefined

    apiRequest(`/auth/profile-image?v=${encodeURIComponent(profileImage)}`, { responseType: 'blob' })
      .then((image) => {
        if (!active) return
        nextImageUrl = URL.createObjectURL(image)
        setLoadedImage({ fileName: profileImage, url: nextImageUrl })
      })
      .catch((error) => {
        if (active) onError?.(error.message)
      })

    return () => {
      active = false
      if (nextImageUrl) URL.revokeObjectURL(nextImageUrl)
    }
  }, [onError, profileImage])

  if (loadedImage?.fileName === profileImage) {
    return <img className={`${className} has-image`} src={loadedImage.url} alt={`${name || 'Employee'} profile`} />
  }
  return <span className={className}>{name?.trim()?.[0]?.toUpperCase() || 'E'}</span>
}

function EmployeeProfile({ user, onUserUpdate }) {
  const [profile, setProfile] = useState(user)
  const [isLoadingProfile, setIsLoadingProfile] = useState(true)
  const [profileError, setProfileError] = useState('')
  const [photoError, setPhotoError] = useState('')
  const [photoMessage, setPhotoMessage] = useState('')
  const [isUploadingPhoto, setIsUploadingPhoto] = useState(false)
  const profilePhotoInput = useRef(null)
  const showPhotoError = useCallback((message) => setPhotoError(message), [])

  useEffect(() => {
    let active = true
    apiRequest('/auth/me')
      .then((response) => {
        if (active) {
          setProfile(response.user)
          onUserUpdate(response.user)
        }
      })
      .catch((error) => {
        if (active) setProfileError(error.message)
      })
      .finally(() => {
        if (active) setIsLoadingProfile(false)
      })
    return () => {
      active = false
    }
  }, [onUserUpdate])

  const uploadProfilePhoto = async (event) => {
    const file = event.target.files?.[0]
    if (!file) return
    event.target.value = ''
    setPhotoError('')
    setPhotoMessage('')
    if (file.size > 5_000_000) {
      setPhotoError('Profile photos must be smaller than 5 MB.')
      return
    }

    const body = new FormData()
    body.append('profile_image', file)
    setIsUploadingPhoto(true)
    try {
      const response = await apiRequest('/auth/profile-image', { method: 'POST', body })
      setProfile(response.user)
      onUserUpdate(response.user)
      setPhotoMessage(response.message)
    } catch (error) {
      setPhotoError(error.message)
    } finally {
      setIsUploadingPhoto(false)
    }
  }

  const dateOfBirth = profile.dob && profile.dob !== '1970-01-01'
    ? new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
      .format(new Date(`${profile.dob}T00:00:00Z`))
    : 'Not recorded'
  const joiningDate = profile.joiningDate
    ? new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
      .format(new Date(`${profile.joiningDate}T00:00:00Z`))
    : 'Not recorded'

  return (
    <section className="employee-profile-page">
      <header className="employee-profile-heading">
        <div>
          <span className="mini-label">{profile.role === 'team_leader' ? 'Team Leader' : 'Telecaller'} Workspace</span>
          <h1>My Profile</h1>
          <p>Your employee information from the AROWIN records.</p>
        </div>
        {profile.employeeId && <span className="employee-profile-id">ID · {profile.employeeId}</span>}
      </header>

      {profileError && <p className="dashboard-data-error" role="alert">{profileError}</p>}
      {photoError && <p className="dashboard-data-error" role="alert">{photoError}</p>}
      {photoMessage && <p className="employee-profile-photo-message" role="status">{photoMessage}</p>}

      <article className="employee-profile-card">
        <div className="employee-profile-identity">
          <div className="employee-profile-photo-wrap">
            <ProfilePhoto
              profileImage={profile.profileImage}
              name={profile.name}
              className="employee-profile-avatar"
              onError={showPhotoError}
            />
            <button
              type="button"
              className="employee-profile-photo-action"
              onClick={() => profilePhotoInput.current?.click()}
              disabled={isUploadingPhoto}
            >
              {isUploadingPhoto ? 'Uploading…' : profile.profileImage ? 'Change photo' : 'Add photo'}
            </button>
            <span className="employee-profile-photo-hint">JPG, PNG or WebP · Max 5 MB</span>
            <input
              ref={profilePhotoInput}
              className="visually-hidden"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              aria-label="Choose a profile photo"
              onChange={uploadProfilePhoto}
            />
          </div>
          <div>
            <h2>{profile.name || 'Employee'}</h2>
            <p>{profile.role === 'team_leader' ? 'Team Leader' : 'Telecaller'}</p>
          </div>
          <span className="employee-profile-status"><i /> Active</span>
        </div>

        <div className="employee-profile-section-heading">
          <h3>Personal Information</h3>
          <span>{isLoadingProfile ? 'Refreshing saved details…' : 'From your employee record'}</span>
        </div>
        <dl className="employee-profile-grid">
          <div><dt>Email Address</dt><dd>{profile.email || 'Not recorded'}</dd></div>
          <div><dt>Mobile Number</dt><dd>{profile.mobile || 'Not recorded'}</dd></div>
          <div><dt>Date of Birth</dt><dd>{dateOfBirth}</dd></div>
          <div><dt>Joining Date</dt><dd>{joiningDate}</dd></div>
          <div className="employee-profile-address"><dt>Address</dt><dd>{profile.address || 'Not recorded'}</dd></div>
        </dl>
      </article>
    </section>
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

function TeamActivitiesPanel({ history, selectedMonth }) {
  const activities = history.filter((activity) => String(activity.time).slice(0, 7) === selectedMonth)

  return (
    <article className="team-dashboard-panel team-activities-panel">
      <div className="team-panel-heading">
        <div className="team-panel-title"><Icon name="clock" size={23} /><h2>Team Activity History</h2></div>
      </div>
      {activities.length ? (
        <div className="team-table-wrap team-activity-table-wrap">
          <table className="team-performance-table team-activity-table">
            <thead>
              <tr><th>Employee ID</th><th>Employee Name</th><th>Activity</th><th>Sales Target</th><th>Date</th></tr>
            </thead>
            <tbody>
              {activities.map((activity, index) => (
                <tr key={`${activity.employeeId}-${activity.time}-${index}`}>
                  <td>{activity.employeeId || '—'}</td>
                  <td>{activity.employeeName}</td>
                  <td>{activity.activity}</td>
                  <td><span className="team-target-unconfigured">Not configured</span></td>
                  <td>{formatDashboardDate(activity.time)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : <p className="team-activity-empty">No team activities were recorded for this month.</p>}
    </article>
  )
}

function TeamLeaderHome({
  teamData,
  isLoading,
  error,
  search,
  selectedActivityMonth,
  salesData,
  isLoadingSales,
  salesError,
  earnedIncentives,
  isLoadingIncentives,
  incentiveError,
  onMonthChange,
}) {
  const members = teamData?.members || []
  const actualSales = (salesData?.dailySales || []).reduce((total, sale) => total + Number(sale.amount), 0)
  const monthlyTarget = Number(salesData?.monthlyTarget || 0)
  const salesValue = isLoadingSales ? '…' : formatUsdt(actualSales)
  const metrics = [
    { label: 'Team Members', value: isLoading ? '…' : String(members.length), icon: 'users', tone: 'blue' },
    {
      label: monthlyTarget > 0 ? 'Monthly Target' : 'Team Sales',
      value: monthlyTarget > 0
        ? (isLoadingSales ? '…' : formatUsdt(monthlyTarget))
        : salesValue,
      status: monthlyTarget > 0
        ? `Team sales ${salesValue} · ${formatMonth(selectedActivityMonth)}`
        : 'Monthly target not set',
      icon: 'trend',
      tone: 'green',
    },
    { label: 'My Incentives', value: isLoadingIncentives ? '…' : formatUsdt(earnedIncentives), status: incentiveError ? 'Incentive data unavailable' : 'Approved personal package sales', icon: 'gift', tone: 'orange' },
    {
      label: 'Team Rewards',
      value: isLoadingSales ? '…' : monthlyTarget > 0 ? `${Math.round(actualSales / monthlyTarget * 100)}%` : '—',
      status: monthlyTarget <= 0
        ? 'Monthly target not set'
        : actualSales >= monthlyTarget
          ? 'Team target achieved'
          : `${formatUsdt(Math.max(0, monthlyTarget - actualSales))} left to target`,
      icon: 'trophy',
      tone: 'purple',
    },
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
        <TeamSalesChart
          performance={salesData}
          isLoading={isLoadingSales}
          error={salesError}
          month={selectedActivityMonth}
          onMonthChange={onMonthChange}
        />
        <TeamOverviewPanel members={members} />
      </div>
      <div className="team-dashboard-grid team-dashboard-grid-bottom">
        <TeamPerformancePanel members={members} isLoading={isLoading} search={search} />
        <TeamActivitiesPanel history={teamData?.history || []} selectedMonth={selectedActivityMonth} />
        <TeamAchieversPanel />
      </div>
    </>
  )
}

function TeamLeaderContent({
  user,
  onUserUpdate,
  page,
  teamData,
  isLoadingTeam,
  teamError,
  teamSearch,
  selectedActivityMonth,
  salesData,
  isLoadingSales,
  salesError,
  earnedIncentives,
  isLoadingIncentives,
  incentiveError,
  onMonthChange,
}) {
  if (page.path === '/team-leader/attendance') {
    return <AttendancePanel />
  }

  if (page.path === '/team-leader/leave') {
    return <LeaveManagementPage user={user} />
  }

  if (page.path === '/team-leader/support') {
    return <SupportTicketsPage user={user} />
  }

  if (page.path === '/team-leader/daily-activities') {
    return <DailyActivitiesPage user={user} />
  }

  if (page.path === '/team-leader/sales') {
    return <UnlockSalesPage user={user} />
  }

  if (page.path === '/team-leader/incentives') {
    return <MyIncentivesPage user={user} />
  }

  if (page.path === '/team-leader/team-incentives') {
    return <TeamIncentivesPage />
  }

  if (page.path === '/team-leader/team-grant-incentives') {
    return <TeamGrantIncentivesPage />
  }

  if (page.path === '/team-leader/team-rewards') {
    return <TeamRewardsPage />
  }

  if (page.path === '/team-leader/grant-team-rewards') {
    return <TeamRewardsPage grantsOnly />
  }

  if (page.path === '/team-leader/team-achievers') {
    return <TeamAchieversPanel />
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

  if (page.path === '/team-leader/team-sales') {
    return (
      <SalesTrendPanel
        team
        performance={salesData}
        isLoading={isLoadingSales}
        error={salesError}
        month={selectedActivityMonth}
        onMonthChange={onMonthChange}
      />
    )
  }

  if (page.path === '/team-leader/profile') {
    return <EmployeeProfile user={user} onUserUpdate={onUserUpdate} />
  }

  if (page.path === '/team-leader/kyc') {
    return <EmployeeKycPage user={user} />
  }

  if (page.path === '/team-leader') {
    return (
      <TeamLeaderHome
        teamData={teamData}
        isLoading={isLoadingTeam}
        error={teamError}
        search={teamSearch}
        selectedActivityMonth={selectedActivityMonth}
        salesData={salesData}
        isLoadingSales={isLoadingSales}
        salesError={salesError}
        earnedIncentives={earnedIncentives}
        isLoadingIncentives={isLoadingIncentives}
        incentiveError={incentiveError}
        onMonthChange={onMonthChange}
      />
    )
  }

  return (
    <article className="dashboard-panel role-detail-panel">
      <span className="role-work-icon"><Icon name={page.icon} size={20} /></span>
      <p className="role-dashboard-description">{page.description}</p>
      <strong className="role-detail-status">{page.status}</strong>
    </article>
  )
}

function TelecallerContent({
  user,
  onUserUpdate,
  onNavigate,
  currentPath,
  salesData,
  isLoadingSales,
  salesError,
  earnedIncentives,
  isLoadingIncentives,
  incentiveError,
  onMonthChange,
}) {
  const actualSales = (salesData?.dailySales || []).reduce((total, sale) => total + Number(sale.amount), 0)

  if (currentPath === '/telecaller/sales') {
    return <UnlockSalesPage user={user} />
  }

  if (currentPath === '/telecaller/incentives') {
    return <MyIncentivesPage user={user} />
  }

  if (currentPath === '/telecaller/team-achievers') {
    return <TeamAchieversPanel />
  }

  if (currentPath === '/telecaller/leads') {
    return <LeadsEntryPage />
  }
  if (currentPath === '/telecaller/profile') {
    return <EmployeeProfile user={user} onUserUpdate={onUserUpdate} />
  }
  if (currentPath === '/telecaller/kyc') {
    return <EmployeeKycPage user={user} />
  }
  if (currentPath === '/telecaller/support') {
    return <SupportTicketsPage user={user} />
  }
  if (currentPath === '/telecaller/leave') {
    return <LeaveManagementPage user={user} />
  }

  if (currentPath === '/telecaller/daily-activities') {
    return <DailyActivitiesPage user={user} />
  }

  const metrics = telecallerSections.map((metric) => {
    if (metric.label === 'My Unlock Sales') {
      return {
        ...metric,
        value: isLoadingSales ? '…' : formatUsdt(actualSales),
        note: salesError ? 'Sales data unavailable' : `Recorded sales · ${formatMonth(salesData?.month || currentActivityMonth)}`,
      }
    }
    if (metric.label === 'My Incentives') {
      return {
        ...metric,
        value: isLoadingIncentives ? '…' : formatUsdt(earnedIncentives),
        note: incentiveError ? 'Incentive data unavailable' : 'From approved package sales',
      }
    }
    return metric
  })

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
        {metrics.map((metric) => (
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
        <SalesTrendPanel
          compact
          performance={salesData}
          isLoading={isLoadingSales}
          error={salesError}
          month={salesData?.month || currentActivityMonth}
          onMonthChange={onMonthChange}
          onViewSales={() => onNavigate('/telecaller/sales')}
        />

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

        <TeamAchieversPanel />
      </div>
    </div>
  )
}

export default function RoleDashboard({ user, onUserUpdate, onLogout, onBack, currentPath, onNavigate }) {
  const isTeamLeader = user.role === 'team_leader'
  const dashboard = {
    roleLabel: isTeamLeader ? 'Team Leader' : 'Telecaller',
    title: isTeamLeader ? 'Team Leader Dashboard' : 'Telecaller Dashboard',
    accent: isTeamLeader ? 'leader' : 'telecaller',
  }
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => window.matchMedia('(max-width: 1024px)').matches)
  const [teamData, setTeamData] = useState(null)
  const [isLoadingTeam, setIsLoadingTeam] = useState(isTeamLeader)
  const [teamError, setTeamError] = useState('')
  const [teamSearch, setTeamSearch] = useState('')
  const [selectedActivityMonth, setSelectedActivityMonth] = useState(currentActivityMonth)
  const [salesData, setSalesData] = useState(null)
  const [isLoadingSales, setIsLoadingSales] = useState(true)
  const [salesError, setSalesError] = useState('')
  const [earnedIncentives, setEarnedIncentives] = useState('0.00')
  const [isLoadingIncentives, setIsLoadingIncentives] = useState(true)
  const [incentiveError, setIncentiveError] = useState('')
  const [showTeamNotifications, setShowTeamNotifications] = useState(false)
  const [showProfileMenu, setShowProfileMenu] = useState(false)
  const salesRequestId = useRef(0)
  const page = teamLeaderPages.find((item) => item.path === currentPath) || teamLeaderPages[0]
  const currentPageTitle = isTeamLeader
    ? page.title
    : telecallerNavItems.find((item) => item.path === currentPath)?.label || dashboard.title

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

  const loadSales = useCallback(async () => {
    const requestId = ++salesRequestId.current
    try {
      const result = await apiRequest(`/sales/performance?month=${selectedActivityMonth}`)
      if (requestId !== salesRequestId.current) return
      setSalesData(result.performance)
      setSalesError('')
    } catch (error) {
      if (requestId !== salesRequestId.current) return
      setSalesError(error.message)
    } finally {
      if (requestId === salesRequestId.current) setIsLoadingSales(false)
    }
  }, [selectedActivityMonth])
  const handleSalesMonthChange = (month) => {
    setIsLoadingSales(true)
    setSalesData(null)
    setSelectedActivityMonth(month)
  }

  useEffect(() => {
    if (!isTeamLeader || !['/', '/team-leader', '/team-leader/my-team', '/team-leader/team-sales'].includes(currentPath)) return undefined

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

  useEffect(() => {
    const salesPage = isTeamLeader
      ? ['/', '/team-leader', '/team-leader/team-sales'].includes(currentPath)
      : ['/', '/telecaller', '/telecaller/sales'].includes(currentPath)
    if (!salesPage) return undefined

    const initialLoad = window.setTimeout(loadSales, 0)
    const refreshTimer = window.setInterval(loadSales, 30000)
    return () => {
      window.clearTimeout(initialLoad)
      window.clearInterval(refreshTimer)
    }
  }, [currentPath, isTeamLeader, loadSales, user.id])

  useEffect(() => {
    const dashboardPath = isTeamLeader
      ? ['/', '/team-leader'].includes(currentPath)
      : ['/', '/telecaller'].includes(currentPath)
    if (!dashboardPath) return undefined

    let active = true
    const loadIncentives = () => {
      apiRequest('/sales/incentives/me')
        .then((result) => {
          if (!active) return
          setEarnedIncentives(result.result.totalEarned)
          setIncentiveError('')
        })
        .catch((error) => {
          if (active) setIncentiveError(error.message)
        })
        .finally(() => {
          if (active) setIsLoadingIncentives(false)
        })
    }

    loadIncentives()
    const refreshTimer = window.setInterval(loadIncentives, 30000)
    return () => {
      active = false
      window.clearInterval(refreshTimer)
    }
  }, [currentPath, isTeamLeader, user.id])

  return (
    <div className={`dashboard-shell role-workspace-shell${isTeamLeader ? '' : ' telecaller-workspace-shell'}${sidebarCollapsed ? ' sidebar-collapsed' : ''}`}>
      <button
        type="button"
        className="mobile-sidebar-overlay"
        aria-label="Close navigation menu"
        tabIndex={sidebarCollapsed ? -1 : 0}
        onClick={() => setSidebarCollapsed(true)}
      />
      {isTeamLeader && (
        <aside className="sidebar" id="team-leader-sidebar">
          <button type="button" className="mobile-sidebar-close" aria-label="Close navigation menu" onClick={() => setSidebarCollapsed(true)}>
            <Icon name="close" size={19} />
          </button>
          <div className="brand-row">
            <div className="brand-icon">A</div>
            <div className="brand-copy">
              <span className="sidebar-label">Team Workspace</span>
              <h2>AROWIN</h2>
            </div>
          </div>
          <nav className="sidebar-nav" aria-label="Team Leader navigation">
            {teamLeaderNavigationGroups.map((group) => (
              <div className="sidebar-nav-section" key={group.label || 'workspace'}>
                {group.label && <span className="sidebar-nav-heading">{group.label}</span>}
                {group.items.map((item) => {
                  const active = item.path === currentPath || (item.path === '/team-leader' && currentPath === '/')
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
                    </button>
                  )
                })}
              </div>
            ))}
          </nav>
          <div className="sidebar-wave" aria-hidden="true" />
        </aside>
      )}
      {!isTeamLeader && (
        <aside className="telecaller-sidebar" id="telecaller-sidebar">
          <button type="button" className="mobile-sidebar-close" aria-label="Close navigation menu" onClick={() => setSidebarCollapsed(true)}>
            <Icon name="close" size={19} />
          </button>
          <div className="telecaller-brand">
            <span className="telecaller-brand-mark">A</span>
            <span><small>Module Monolith</small><strong>AROWIN</strong></span>
          </div>
          <nav className="telecaller-nav" aria-label="Telecaller navigation">
            {telecallerNavigationGroups.map((group) => (
              <div className="sidebar-nav-section" key={group.label || 'workspace'}>
                {group.label && <span className="sidebar-nav-heading">{group.label}</span>}
                {group.items.map((item) => {
                  const active = item.path === currentPath || (item.path === '/telecaller' && currentPath === '/')
                  return (
                    <button
                      type="button"
                      key={item.label}
                      className={`telecaller-nav-item${active ? ' active' : ''}`}
                      aria-current={active ? 'page' : undefined}
                      disabled={!item.available}
                      title={item.available ? undefined : 'This feature is not configured yet'}
                      onClick={() => {
                        onNavigate(item.path)
                        if (window.matchMedia('(max-width: 1024px)').matches) setSidebarCollapsed(true)
                      }}
                    >
                      <Icon name={item.icon} size={19} />
                      <span>{item.label}</span>
                      {!item.available && <small>Soon</small>}
                    </button>
                  )
                })}
              </div>
            ))}
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
            <>
              <div className="team-header-identity">
                <button
                  type="button"
                  className="menu-toggle"
                  onClick={() => setSidebarCollapsed((value) => !value)}
                  aria-label={sidebarCollapsed ? 'Open navigation menu' : 'Close navigation menu'}
                  aria-expanded={!sidebarCollapsed}
                  aria-controls="team-leader-sidebar"
                >
                  <Icon name={sidebarCollapsed ? 'menu' : 'close'} size={20} />
                </button>
                <div className="team-header-brand">
                  <span className="team-header-brand-mark">A</span>
                  <span><strong>AROWIN</strong><small>{dashboard.roleLabel}</small></span>
                </div>
                <span className="team-header-divider" />
                <span className="team-header-page">{currentPageTitle}</span>
              </div>
            </>
          ) : (
            <div className="team-header-identity">
              <button
                type="button"
                className="menu-toggle"
                onClick={() => setSidebarCollapsed((value) => !value)}
                aria-label={sidebarCollapsed ? 'Open navigation menu' : 'Close navigation menu'}
                aria-expanded={!sidebarCollapsed}
                aria-controls="telecaller-sidebar"
              >
                <Icon name={sidebarCollapsed ? 'menu' : 'close'} size={20} />
              </button>
              <div className="team-header-brand">
                <span className="team-header-brand-mark">A</span>
                <span><strong>AROWIN</strong><small>{dashboard.roleLabel}</small></span>
              </div>
              <span className="team-header-divider" />
              <span className="team-header-page">{currentPageTitle}</span>
            </div>
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
            {isTeamLeader && ['/team-leader', '/team-leader/my-team'].includes(currentPath) && (
              <div className="team-notification-wrap">
                <button
                  className="team-notification-button"
                  type="button"
                  aria-label="Notifications"
                  aria-expanded={showTeamNotifications}
                  onClick={() => setShowTeamNotifications((open) => !open)}
                >
                  <Icon name="bell" size={20} />
                  {teamData?.activities?.length > 0 && <span>{teamData.activities.length}</span>}
                </button>
                {showTeamNotifications && (
                  <section className="team-notification-popover" aria-label="Notifications">
                    <h2>Notifications</h2>
                    {teamData?.activities?.length ? (
                      <ul className="team-notification-list">
                        {teamData.activities.map((activity, index) => (
                          <li key={`${activity.time}-${index}`}>
                            <span className="team-activity-icon green"><Icon name="users" size={20} /></span>
                            <span className="team-activity-copy">
                              <strong>{activity.title}</strong>
                              <small>{formatDashboardDate(activity.time)}</small>
                            </span>
                          </li>
                        ))}
                      </ul>
                    ) : (
                      <p className="team-notification-empty">No team notifications yet.</p>
                    )}
                  </section>
                )}
              </div>
            )}
            <RoleProfileMenu
              name={user.name}
              role={dashboard.roleLabel}
              avatar={<ProfilePhoto profileImage={user.profileImage} name={user.name} className="profile-avatar" />}
              actionLabel="My profile"
              actionIcon="user"
              actionPath={isTeamLeader ? '/team-leader/profile' : '/telecaller/profile'}
              isOpen={showProfileMenu}
              onOpenChange={setShowProfileMenu}
              onNavigate={onNavigate}
              onLogout={onLogout}
            />
          </div>
        </header>

        {isTeamLeader ? (
          <section className={`role-dashboard-content${page.path === '/team-leader' ? ' team-leader-dashboard-content' : ''}`}>
            <button type="button" className="team-content-back" onClick={onBack}>
              <Icon name="back" size={17} />
              <span>Back</span>
            </button>
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
                    <label className="visually-hidden" htmlFor="team-activity-month">Activity history month</label>
                    <select
                      id="team-activity-month"
                      value={selectedActivityMonth}
                      onChange={(event) => handleSalesMonthChange(event.target.value)}
                    >
                      {activityMonthOptions.map((month) => (
                        <option key={month.value} value={month.value}>{month.label}</option>
                      ))}
                    </select>
                  </div>
                </div>
              </div>
            ) : page.path === '/team-leader/kyc' ? null : (
              <header className={`team-subpage-heading${page.path === '/team-leader/daily-activities' ? ' is-daily-activities' : ''}`}>
                <span className="mini-label">Team Workspace</span>
                <h1>{page.title}</h1>
                <p className="role-dashboard-description">{page.description}</p>
              </header>
            )}
            <TeamLeaderContent
              user={user}
              onUserUpdate={onUserUpdate}
              page={page}
              teamData={teamData}
              isLoadingTeam={isLoadingTeam}
              teamError={teamError}
              teamSearch={teamSearch}
              selectedActivityMonth={selectedActivityMonth}
              salesData={salesData}
              isLoadingSales={isLoadingSales}
              salesError={salesError}
              earnedIncentives={earnedIncentives}
              isLoadingIncentives={isLoadingIncentives}
              incentiveError={incentiveError}
              onMonthChange={handleSalesMonthChange}
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
            <TelecallerContent
              user={user}
              onUserUpdate={onUserUpdate}
              onNavigate={onNavigate}
              currentPath={currentPath === '/' ? '/telecaller' : currentPath}
              salesData={salesData}
              isLoadingSales={isLoadingSales}
              salesError={salesError}
              onMonthChange={handleSalesMonthChange}
            />
          )}
        </section>
        )}
      </main>
    </div>
  )
}
