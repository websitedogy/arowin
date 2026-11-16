import { useCallback, useEffect, useRef, useState } from 'react'
import { apiRequest } from '../../api'
import { formatMonth, formatUsdt } from '../../utils/formatters'
import Icon from './components/Icon'
import RoleProfileMenu from './components/RoleProfileMenu'
import formatDashboardDate from './formatDashboardDate'
import formatDateTime from '../../utils/formatDateTime'
import AttendancePanel from '../attendance/AttendancePanel'

const formatTime = (dateTime) => {
  if (!dateTime) return '—'
  return formatDateTime(dateTime, 'en-IN', {
    hour: 'numeric',
    minute: '2-digit',
    timeZone: 'Asia/Kolkata',
  }, { fallback: '—', assumedOffset: '+05:30' })
}
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
import EmployeeIncomePage from './EmployeeIncomePage'
import EmergencySalesPage from '../sales/EmergencySalesPage'
import TeamLeaderStatesPage from './TeamLeaderStatesPage'
import TeamLeaderDistrictLeadsPage from './TeamLeaderDistrictLeadsPage'
import './dashboard-2027.css'

const teamLeaderNavigationGroups = [
  {
    items: [
      { label: 'Dashboard', path: '/team-leader', icon: 'home', title: 'Team Leader Dashboard', description: 'Your team workspace overview.' },
    ],
  },
  {
    label: 'My Work',
    items: [
      { label: 'Employee KYC', path: '/team-leader/kyc', icon: 'file', title: 'Employee KYC', description: 'Submit your profile, bank, and identity details securely.' },
      { label: 'Attendance', path: '/team-leader/attendance', icon: 'calendar', title: 'Attendance', description: 'View your salary, attendance, and calendar history.' },
      { label: 'My Income', path: '/team-leader/income', icon: 'wallet', title: 'My Income', description: 'Review your complete compensation, salary, incentives, and payroll details.' },
      { label: 'Daily Activities', path: '/team-leader/daily-activities', icon: 'file', title: 'Daily Activities', description: 'Submit platform activity screenshots and check their verification status.' },
    ],
  },
  {
    label: 'Team Management',
    items: [
      { label: 'State', path: '/team-leader/state', icon: 'mapPin', title: 'Assigned State', description: 'Browse lead entries from your assigned states.' },
      { label: 'Team sales', path: '/team-leader/team-sales', icon: 'chart', title: 'Team Sales', description: 'Sales recorded for your team will appear here.', status: 'Team sales are not available yet.' },
      { label: 'Team Achievers', path: '/team-leader/team-achievers', icon: 'trophy', title: 'Team Achievers', description: 'Approved sales achievements by your assigned Telecallers.' },
    ],
  },
  {
    label: 'Sales & Rewards',
    items: [
      { label: 'My Unlock Sales', path: '/team-leader/sales', icon: 'trend', title: 'My Unlock Sales', description: 'Submit your own package sales for Sub Editor review and track their status.' },
      { label: 'Emergency Sales', path: '/team-leader/emergency-sales', icon: 'zap', title: 'Emergency Sales', description: 'View emergency sales amounts assigned to you.' },
      { label: 'My Incentives', path: '/team-leader/incentives', icon: 'gift', title: 'My Incentives', description: 'View incentives earned from your approved package sales.' },
      { label: 'Team Incentives', path: '/team-leader/team-incentives', icon: 'gift', title: 'Team Incentives', description: 'View package-sale incentives earned by your assigned Telecallers.' },
      { label: 'Team Grant Incentives', path: '/team-leader/team-grant-incentives', icon: 'coins', title: 'Team Grant Incentives', description: 'See each Telecaller’s equal monthly target and your incentive share when everyone reaches their target.' },
      { label: 'Team Rewards', path: '/team-leader/team-rewards', icon: 'trophy', title: 'Team Rewards', description: 'Track each assigned Telecaller’s contribution to the shared monthly sales target.' },
      { label: 'Grant Team Rewards', path: '/team-leader/grant-team-rewards', icon: 'medal', title: 'Grant Team Rewards', description: 'View monthly target rewards automatically recorded for you when your team meets its target.' },
    ],
  },
]

const teamLeaderPages = [
  ...teamLeaderNavigationGroups.flatMap((group) => group.items),
  { label: 'State', path: '/team-leader/my-team', icon: 'mapPin', title: 'Assigned State', description: 'Browse lead entries from your assigned states.', hidden: true },
  { label: 'Support', path: '/team-leader/support', icon: 'headset', title: 'Support Helpdesk', description: 'Submit and track support tickets with the management team.' },
  { label: 'Profile', path: '/team-leader/profile', icon: 'user', title: 'My Profile', description: 'View and update your personal details and credentials.' },
  { label: 'Leave', path: '/team-leader/leave', icon: 'calendar', title: 'Leave Management', description: 'Apply for leave and review your leave requests.' },
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
      { label: 'Employee KYC', path: '/telecaller/kyc', icon: 'file', available: true },
      { label: 'My Attendance', path: '/telecaller/attendance', icon: 'calendar', available: true },
      { label: 'My Income', path: '/telecaller/income', icon: 'wallet', available: true },
      { label: 'Daily Activities', path: '/telecaller/daily-activities', icon: 'file', available: true },
    ],
  },
  {
    label: 'Sales & Performance',
    items: [
      { label: 'My Unlock Sales', path: '/telecaller/sales', icon: 'trend', available: true },
      { label: 'Emergency Sales', path: '/telecaller/emergency-sales', icon: 'zap', available: true },
      { label: 'My Incentives', path: '/telecaller/incentives', icon: 'gift', available: true },
      { label: 'Our Team Achievers', path: '/telecaller/team-achievers', icon: 'trophy', available: true },
      { label: 'Leads Entry', path: '/telecaller/leads', icon: 'userPlus', available: true },
    ],
  },
]

const telecallerNavItems = [
  ...telecallerNavigationGroups.flatMap((group) => group.items),
  { label: 'Support', path: '/telecaller/support', icon: 'headset', available: true },
  { label: 'Profile', path: '/telecaller/profile', icon: 'user', available: true },
  { label: 'Leave', path: '/telecaller/leave', icon: 'calendar', available: true },
]

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
const teamTargetSlides = [
  { title: 'One team. One goal.', detail: 'Your monthly team target, at a glance.', image: '/images/team-target-slide-1.svg', alt: 'Abstract blue target and progress illustration' },
  { title: 'Progress that adds up.', detail: 'Approved sales are building toward the goal.', image: '/images/team-target-slide-2.svg', alt: 'Abstract blue sales chart illustration' },
  { title: 'Built by your team.', detail: 'Your assigned team is working toward the goal.', image: '/images/team-target-slide-3.svg', alt: 'Abstract blue team collaboration illustration' },
]

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

function TeamTargetDisplay({ performance, isLoading, month, teamCount = 1 }) {
  const [activeSlide, setActiveSlide] = useState(0)
  const target = Number(performance?.monthlyTarget || 0)
  const sales = (performance?.dailySales || []).reduce((total, sale) => total + Number(sale.amount), 0)
  const salesCount = (performance?.dailySales || []).length
  const progress = target > 0 ? Math.min(100, Math.round((sales / target) * 100)) : 0

  useEffect(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return undefined
    const timer = window.setInterval(() => {
      setActiveSlide((current) => (current + 1) % teamTargetSlides.length)
    }, 5000)
    return () => window.clearInterval(timer)
  }, [])

  const goToSlide = (index) => {
    setActiveSlide((index + teamTargetSlides.length) % teamTargetSlides.length)
  }

  return (
    <article className="team-target-carousel" aria-label="Team target highlights">
      {teamTargetSlides.map((slide, index) => (
        <section
          className={`team-target-slide${activeSlide === index ? ' is-active' : ''}`}
          key={slide.title}
          aria-hidden={activeSlide !== index}
        >
          <div className="team-target-slide-copy">
            <span className="team-target-slide-eyebrow"><Icon name="target" size={15} /> {formatMonth(month)} TEAM TARGET</span>
            <h2>{slide.title}</h2>
            <p>
              {index === 1
                ? `${salesCount} approved ${salesCount === 1 ? 'sale' : 'sales'} this month.`
                : index === 2
                  ? `${teamCount} assigned ${teamCount === 1 ? 'telecaller' : 'telecallers'} working toward the goal.`
                  : slide.detail}
            </p>
            <div className="team-target-slide-metrics">
              <div>
                <span>Monthly team target</span>
                <strong>{isLoading ? 'Loading…' : target > 0 ? formatUsdt(target) : 'Not set'}</strong>
              </div>
              <div className="team-target-slide-progress">
                <span>{isLoading ? '—' : `${progress}%`} achieved</span>
                <span className="team-target-progress-track"><i style={{ width: `${isLoading ? 0 : progress}%` }} /></span>
              </div>
            </div>
          </div>
          <img className="team-target-slide-image" src={slide.image} alt={slide.alt} />
        </section>
      ))}
      <div className="team-target-carousel-controls" aria-label="Carousel controls">
        <button type="button" aria-label="Previous slide" onClick={() => goToSlide(activeSlide - 1)}>
          <Icon name="back" size={16} />
        </button>
        {teamTargetSlides.map((slide, index) => (
          <button
            type="button"
            className={`team-target-carousel-dot${activeSlide === index ? ' is-active' : ''}`}
            key={slide.title}
            aria-label={`Show slide ${index + 1}`}
            aria-pressed={activeSlide === index}
            onClick={() => goToSlide(index)}
          />
        ))}
        <button type="button" aria-label="Next slide" onClick={() => goToSlide(activeSlide + 1)}>
          <Icon name="arrow" size={16} />
        </button>
      </div>
    </article>
  )
}

const teamLeaderActivityCards = [
  { label: 'My Sales Unlock', detail: 'Submit and follow your direct sales', icon: 'trend', path: '/team-leader/sales', tone: 'blue', countLabel: 'sale', countLabelPlural: 'sales' },
  { label: 'Incentives', detail: 'Review your earned package incentives', icon: 'gift', path: '/team-leader/incentives', tone: 'green', countLabel: 'incentive', countLabelPlural: 'incentives' },
  { label: 'Incentive Grant', detail: 'Track team equal monthly incentive grants', icon: 'coins', path: '/team-leader/team-grant-incentives', tone: 'amber', countLabel: 'grant', countLabelPlural: 'grants' },
  { label: 'Reward', detail: 'View your team reward grants and milestones', icon: 'medal', path: '/team-leader/grant-team-rewards', tone: 'violet', countLabel: 'grant', countLabelPlural: 'grants' },
  { label: 'My Income', detail: 'Complete salary, incentives & bonus breakdown', icon: 'wallet', path: '/team-leader/income', tone: 'emerald', countLabel: 'income entry', countLabelPlural: 'income entries' },
  { label: 'Attendance', detail: 'Check in and view shift attendance logs', icon: 'calendar', path: '/team-leader/attendance', tone: 'cyan', countLabel: 'day', countLabelPlural: 'days' },
  { label: 'Employee KYC', detail: 'Complete or update your identity and bank details', icon: 'shield', path: '/team-leader/kyc', tone: 'blue', countLabel: 'KYC record', countLabelPlural: 'KYC records' },
  { label: 'Daily Activities', detail: 'Submit activity proof and review verification status', icon: 'file', path: '/team-leader/daily-activities', tone: 'green', countLabel: 'activity', countLabelPlural: 'activities' },
]

const telecallerActivityCards = [
  { label: 'Team Sales Tracker', detail: 'Review daily sales trends and pacing', icon: 'chart', path: '/team-leader/team-sales', tone: 'blue', countLabel: 'sales day', countLabelPlural: 'sales days' },
  { label: 'Team Target', detail: 'Track team monthly sales vs target', icon: 'target', path: '/team-leader/team-sales', tone: 'green', countLabel: 'target', countLabelPlural: 'targets' },
  { label: 'Team Incentives', detail: 'Track team incentive earnings by member', icon: 'gift', path: '/team-leader/team-incentives', tone: 'amber', countLabel: 'member with incentives', countLabelPlural: 'members with incentives' },
  { label: 'Team Rewards', detail: 'Review team monthly reward progress', icon: 'trophy', path: '/team-leader/team-rewards', tone: 'violet', countLabel: 'grant', countLabelPlural: 'grants' },
  { label: 'Team Achievers', detail: 'Celebrate approved team sales achievements', icon: 'medal', path: '/team-leader/team-achievers', tone: 'rose', countLabel: 'achievement', countLabelPlural: 'achievements' },
  { label: 'State', detail: 'View assigned states, districts, and lead entries', icon: 'mapPin', path: '/team-leader/state', tone: 'cyan' },
]

function QuickLeadModal({ isOpen, onClose, onLeadCreated }) {
  const [form, setForm] = useState({
    customerName: '',
    mobileNumber: '',
    stateCode: '',
    district: '',
    email: '',
    address: '',
    dateOfBirth: '',
    gender: '',
    note: '',
  })
  const [states, setStates] = useState([])
  const [territoryError, setTerritoryError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!isOpen) return undefined
    let active = true
    apiRequest('/territories')
      .then((result) => {
        if (active) {
          setStates(result.states)
          setTerritoryError('')
        }
      })
      .catch((requestError) => {
        if (active) setTerritoryError(requestError.message)
      })

    return () => {
      active = false
    }
  }, [isOpen])

  const selectedState = states.find((state) => state.code === form.stateCode)

  if (!isOpen) return null

  const handleSubmit = async (e) => {
    e.preventDefault()
    setIsSubmitting(true)
    setError('')
    try {
      const response = await apiRequest('/leads', {
        method: 'POST',
        body: form,
      })
      onLeadCreated?.(response.lead)
      setForm({
        customerName: '',
        mobileNumber: '',
        stateCode: '',
        district: '',
        email: '',
        address: '',
        dateOfBirth: '',
        gender: '',
        note: '',
      })
      onClose()
    } catch (err) {
      setError(err.message || 'Could not save lead.')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="quick-lead-modal-backdrop" role="presentation" onMouseDown={(e) => {
      if (e.target === e.currentTarget && !isSubmitting) onClose()
    }}>
      <div className="quick-lead-modal-card" role="dialog" aria-modal="true" aria-labelledby="quick-lead-title">
        <div className="quick-lead-modal-header">
          <div>
            <span className="mini-label">Direct Customer Capture</span>
            <h2 id="quick-lead-title">Add New Customer Lead</h2>
            <p>Save customer details to your active telecalling pipeline.</p>
          </div>
          <button type="button" className="modal-close-icon-btn" onClick={onClose} disabled={isSubmitting} aria-label="Close modal">
            <Icon name="close" size={16} />
          </button>
        </div>

        {error && <p className="dashboard-data-error" role="alert">{error}</p>}

        <form className="leads-entry-form" onSubmit={handleSubmit}>
          <label>
            <span>Customer Name</span>
            <input
              type="text"
              placeholder="e.g. Ramesh Kumar"
              value={form.customerName}
              onChange={(e) => setForm({ ...form, customerName: e.target.value })}
              maxLength={120}
              autoComplete="name"
              required
            />
          </label>
          <label>
            <span>Mobile Phone</span>
            <input
              type="tel"
              placeholder="+91 98765 43210"
              value={form.mobileNumber}
              onChange={(e) => setForm({ ...form, mobileNumber: e.target.value })}
              maxLength={25}
              autoComplete="tel"
              required
            />
          </label>
          <label>
            <span>State / Union Territory</span>
            <select
              value={form.stateCode}
              onChange={(e) => setForm({ ...form, stateCode: e.target.value, district: '' })}
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
              onChange={(e) => setForm({ ...form, district: e.target.value })}
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
              onChange={(e) => setForm({ ...form, email: e.target.value })}
              maxLength={180}
              autoComplete="email"
              required
            />
          </label>
          <label>
            <span>Address</span>
            <textarea
              value={form.address}
              onChange={(e) => setForm({ ...form, address: e.target.value })}
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
              onChange={(e) => setForm({ ...form, dateOfBirth: e.target.value })}
              required
            />
          </label>
          <label>
            <span>Gender</span>
            <select value={form.gender} onChange={(e) => setForm({ ...form, gender: e.target.value })} required>
              <option value="">Select gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
            </select>
          </label>
          <label>
            <span>Customer Notes <small>(Optional)</small></span>
            <textarea
              placeholder="Customer requirements, preferred call time, interested package..."
              value={form.note}
              onChange={(e) => setForm({ ...form, note: e.target.value })}
              maxLength={500}
              rows={3}
            />
          </label>
          <p className="territory-attribution">
            District data: <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">© OpenStreetMap contributors</a>
          </p>
          {territoryError && <p className="dashboard-data-error" role="alert">{territoryError}</p>}
          <div className="leads-form-actions" style={{ marginTop: '14px', display: 'flex', gap: '10px', justifyContent: 'flex-end' }}>
            <button type="button" className="leads-cancel-button" onClick={onClose} disabled={isSubmitting}>
              Cancel
            </button>
            <button type="submit" className="leads-add-button" disabled={isSubmitting || !states.length} style={{ background: 'linear-gradient(135deg, #0284c7, #2563eb)' }}>
              {isSubmitting ? 'Saving Lead…' : 'Save Lead Entry'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

function RoleActivitySection({ title, eyebrow, icon, items, values = {}, counts = {}, className = '' }) {
  return (
    <section className={`role-activity-section ${className}`}>
      <header className="role-activity-heading">
        <span className="role-activity-heading-icon"><Icon name={icon} size={20} /></span>
        <div><span>{eyebrow}</span><h2>{title}</h2></div>
      </header>
      <div className="role-activity-grid">
        {items.map((item, index) => (
          <a
            className={`role-activity-card tone-${item.tone}`}
            key={item.label}
            href={item.path}
            aria-label={`Open ${item.label}`}
            style={{ '--activity-index': index }}
          >
            <span className="role-activity-card-icon"><Icon name={item.icon} size={21} /></span>
            <span className="role-activity-card-copy">
              <strong>{item.label}</strong>
              <small>{item.detail}</small>
              {values[item.label] && <span className="role-activity-card-value">{values[item.label]}</span>}
              {counts[item.label] !== undefined && (
                <span className="role-activity-card-count">
                  {counts[item.label] === null
                    ? 'Count unavailable'
                    : `${counts[item.label]} ${counts[item.label] === 1 ? item.countLabel : item.countLabelPlural}`}
                </span>
              )}
            </span>
            <span className="role-activity-card-arrow"><Icon name="arrow" size={17} /></span>
          </a>
        ))}
      </div>
    </section>
  )
}

function WorkspaceBottomNav({ isTeamLeader, currentPath, onNavigate, unlockSalesUnlocked }) {
  const items = isTeamLeader
    ? [
      { label: 'Home', path: '/team-leader', icon: 'home' },
      { label: 'Today Sales', path: '/team-leader/team-sales', icon: 'chart' },
      { label: 'Team Achievers', path: '/team-leader/team-achievers', icon: 'trophy' },
      { label: 'State', path: '/team-leader/state', icon: 'mapPin' },
      { label: 'My Income', path: '/team-leader/income', icon: 'wallet' },
    ]
    : [
      { label: 'Home', path: '/telecaller', icon: 'home' },
      { label: 'Today Sales', path: '/telecaller/sales', icon: 'chart', requiresEmergencyCompletion: true },
      { label: 'Today Leads Entry', path: '/telecaller/leads', icon: 'userPlus' },
      { label: 'My Team', path: '/telecaller/team-achievers', icon: 'users' },
      { label: 'My Income', path: '/telecaller/income', icon: 'wallet' },
    ]

  return (
    <nav className="workspace-bottom-nav" aria-label="Quick navigation">
      {items.map((item, index) => {
        const active = currentPath === item.path
          || (item.path === '/team-leader/state' && currentPath.startsWith('/team-leader/state/'))
          || (index === 0 && currentPath === '/')
        return (
          <button
            type="button"
            key={item.label}
            className={`workspace-bottom-nav-item tone-${item.icon}${active ? ' active' : ''}${index === 0 ? ' is-home' : ''}`}
            aria-current={active ? 'page' : undefined}
            disabled={item.requiresEmergencyCompletion && !unlockSalesUnlocked}
            title={item.requiresEmergencyCompletion && !unlockSalesUnlocked ? 'Complete emergency sales to unlock My Unlock Sales' : undefined}
            onClick={() => onNavigate(item.path)}
          >
            <span className="workspace-bottom-nav-icon"><Icon name={item.requiresEmergencyCompletion && !unlockSalesUnlocked ? 'lock' : item.icon} size={20} /></span>
            <span>{item.label}</span>
            {item.requiresEmergencyCompletion && !unlockSalesUnlocked && <small>Locked</small>}
          </button>
        )
      })}
    </nav>
  )
}

function TeamOverviewPanel({ members }) {
  const activeCount = members.filter((member) => member.status === 'Active').length
  const inactiveCount = members.length - activeCount
  const activeDegrees = members.length ? (activeCount / members.length) * 360 : 0
  const chartBackground = members.length
    ? `conic-gradient(#2563eb 0deg ${activeDegrees}deg, #dbeafe ${activeDegrees}deg 360deg)`
    : '#eff6ff'

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
        <span className="telecaller-panel-icon" style={{ background: '#fef3c7', color: '#d97706' }}>
          <Icon name="trophy" size={21} />
        </span>
        <div>
          <h2>Our Team Achievers</h2>
          <p>Approved sale achievements celebrated live across the team</p>
        </div>
      </div>
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}
      {isLoading ? (
        <p className="team-achievers-message" role="status">Loading team achievements…</p>
      ) : error ? (
        <p className="team-achievers-message">Team achievements could not be loaded.</p>
      ) : achievements.length ? (
        <ul className="team-achievers-list">
          {achievements.map((achievement, idx) => (
            <li key={achievement.id}>
              <span
                className="team-achievers-avatar"
                style={{
                  background: idx === 0 ? 'linear-gradient(135deg, #f59e0b, #d97706)' : idx === 1 ? 'linear-gradient(135deg, #94a3b8, #64748b)' : 'linear-gradient(135deg, #b45309, #78350f)',
                  color: '#fff',
                  fontWeight: 850,
                }}
              >
                {idx < 3 ? `#${idx + 1}` : achievement.employeeName.slice(0, 1).toUpperCase()}
              </span>
              <div style={{ display: 'flex', flexDirection: 'column' }}>
                <strong style={{ fontSize: '0.84rem', color: '#0f172a' }}>{achievement.employeeName}</strong>
                <small style={{ fontSize: '0.68rem', color: '#64748b' }}>Verified Deal Achiever</small>
              </div>
              <span className="status-pill status-present" style={{ marginLeft: 'auto', fontSize: '0.72rem' }}>
                <Icon name="sparkles" size={13} /> Sale Closed
              </span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="team-achievers-message">No approved team sales yet this month.</p>
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

function TeamPerformancePanel({ members, isLoading, search, compact = false }) {
  const [statusFilter, setStatusFilter] = useState('all')
  const [copiedId, setCopiedId] = useState(null)

  const handleCopy = (text, id) => {
    navigator.clipboard?.writeText(text)
    setCopiedId(id)
    setTimeout(() => setCopiedId(null), 2000)
  }

  const activeCount = members.filter((m) => m.status === 'Active').length
  const inactiveCount = members.length - activeCount

  const filteredMembers = members.filter((member) => {
    const matchesSearch = `${member.employeeId} ${member.fullName} ${member.mobile} ${member.email}`
      .toLowerCase()
      .includes(search.trim().toLowerCase())
    const matchesStatus = statusFilter === 'all' || member.status === statusFilter
    return matchesSearch && matchesStatus
  })

  return (
    <article className="team-dashboard-panel team-performance-panel">
      <div className="team-panel-heading" style={{ flexWrap: 'wrap', gap: '12px' }}>
        <div className="team-panel-title">
          <Icon name="users" size={23} />
          <h2>My Assigned Team ({members.length})</h2>
        </div>
        <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
          <div style={{ display: 'inline-flex', background: '#f1f5f9', borderRadius: '10px', padding: '3px' }}>
            <button
              type="button"
              className={`quick-dock-btn ${statusFilter === 'all' ? 'btn-glass' : ''}`}
              style={{ padding: '4px 10px', fontSize: '0.72rem', background: statusFilter === 'all' ? '#fff' : 'transparent', border: 'none' }}
              onClick={() => setStatusFilter('all')}
            >
              All ({members.length})
            </button>
            <button
              type="button"
              className={`quick-dock-btn ${statusFilter === 'Active' ? 'btn-glass' : ''}`}
              style={{ padding: '4px 10px', fontSize: '0.72rem', background: statusFilter === 'Active' ? '#fff' : 'transparent', border: 'none' }}
              onClick={() => setStatusFilter('Active')}
            >
              Active ({activeCount})
            </button>
            <button
              type="button"
              className={`quick-dock-btn ${statusFilter === 'Inactive' ? 'btn-glass' : ''}`}
              style={{ padding: '4px 10px', fontSize: '0.72rem', background: statusFilter === 'Inactive' ? '#fff' : 'transparent', border: 'none' }}
              onClick={() => setStatusFilter('Inactive')}
            >
              Inactive ({inactiveCount})
            </button>
          </div>
        </div>
      </div>
      <div className={`team-table-wrap${compact ? ' is-compact-wrap' : ''}`}>
        <table className={`team-performance-table${compact ? ' is-compact-table' : ''}`}>
          <thead>
            {compact ? (
              <tr>
                <th>Member</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            ) : (
              <tr>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Joining Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            )}
          </thead>
          <tbody>
            {isLoading ? (
              <tr className="team-table-message-row"><td colSpan={compact ? 4 : 7}>Loading assigned Telecallers…</td></tr>
            ) : filteredMembers.length ? filteredMembers.map((member) => (
              <tr className="team-member-row" key={member.id}>
                {compact ? (
                  <>
                    <td data-label="Member">
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span className="team-achievers-avatar" style={{ width: '28px', height: '28px', fontSize: '0.78rem', flex: '0 0 28px' }}>
                          {member.fullName?.slice(0, 1).toUpperCase()}
                        </span>
                        <div style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.2 }}>
                          <strong style={{ fontSize: '0.78rem', color: '#0f172a' }}>{member.fullName}</strong>
                          <span style={{ fontSize: '0.66rem', color: '#64748b', fontWeight: '700' }}>#{member.employeeId}</span>
                        </div>
                      </div>
                    </td>
                    <td data-label="Phone">
                      {member.mobile ? (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                          <span style={{ fontSize: '0.75rem' }}>{member.mobile}</span>
                          <button
                            type="button"
                            onClick={() => handleCopy(member.mobile, member.id)}
                            style={{ border: 'none', background: 'none', cursor: 'pointer', color: '#0284c7', padding: '2px' }}
                            title="Copy phone number"
                          >
                            <Icon name={copiedId === member.id ? 'check' : 'copy'} size={13} />
                          </button>
                        </div>
                      ) : '—'}
                    </td>
                    <td data-label="Status">
                      <span className={`team-member-status${member.status === 'Active' ? ' is-active' : ''}`} style={{ fontSize: '0.7rem', padding: '3px 8px' }}>
                        <span className="live-ping-dot" style={{ width: '6px', height: '6px', background: member.status === 'Active' ? '#0284c7' : '#94a3b8' }} />
                        {member.status}
                      </span>
                    </td>
                    <td data-label="Action">
                      {member.mobile && (
                        <a href={`tel:${member.mobile}`} className="lead-action-call-btn" style={{ padding: '3px 9px', fontSize: '0.7rem' }} title="Call telecaller">
                          <Icon name="phone" size={12} /> Call
                        </a>
                      )}
                    </td>
                  </>
                ) : (
                  <>
                    <td data-label="Employee ID"><strong className="assignment-employee-id">{member.employeeId}</strong></td>
                    <td data-label="Name">
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span className="team-achievers-avatar" style={{ width: '28px', height: '28px', fontSize: '0.78rem' }}>
                          {member.fullName?.slice(0, 1).toUpperCase()}
                        </span>
                        <strong>{member.fullName}</strong>
                      </div>
                    </td>
                    <td data-label="Phone">
                      {member.mobile ? (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                          <span>{member.mobile}</span>
                          <button
                            type="button"
                            onClick={() => handleCopy(member.mobile, member.id)}
                            style={{ border: 'none', background: 'none', cursor: 'pointer', color: '#64748b' }}
                            title="Copy phone number"
                          >
                            <Icon name={copiedId === member.id ? 'check' : 'copy'} size={14} />
                          </button>
                        </div>
                      ) : '—'}
                    </td>
                    <td data-label="Email">{member.email}</td>
                    <td data-label="Joining Date">{formatDashboardDate(member.joiningDate)}</td>
                    <td data-label="Status">
                      <span className={`team-member-status${member.status === 'Active' ? ' is-active' : ''}`}>
                        <span className="live-ping-dot" style={{ background: member.status === 'Active' ? '#0284c7' : '#94a3b8' }} />
                        {member.status}
                      </span>
                    </td>
                    <td data-label="Actions">
                      <div style={{ display: 'flex', gap: '6px' }}>
                        {member.mobile && (
                          <a href={`tel:${member.mobile}`} className="lead-action-call-btn" title="Call telecaller">
                            <Icon name="phone" size={13} /> Call
                          </a>
                        )}
                        {member.email && (
                          <a href={`mailto:${member.email}`} className="quick-dock-btn btn-glass" style={{ padding: '4px 8px', fontSize: '0.72rem' }} title="Send email">
                            Email
                          </a>
                        )}
                      </div>
                    </td>
                  </>
                )}
              </tr>
            )) : (
              <tr className="team-table-message-row">
                <td colSpan={compact ? 4 : 7}>
                  {search ? 'No assigned Telecallers match your search.' : 'No Telecallers currently in this filter.'}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </article>
  )
}

function TeamActivitiesPanel({ history, selectedMonth, onNavigate }) {
  const activities = history.filter((activity) => String(activity.time).slice(0, 7) === selectedMonth)

  return (
    <article className="team-dashboard-panel team-activities-panel">
      <div className="team-panel-heading">
        <div className="team-panel-title"><Icon name="clock" size={23} /><h2>Team Duty & Activity Stream</h2></div>
        <div className="team-activity-panel-actions">
          <button
            type="button"
            className="team-lead-entry-dashboard-button"
            onClick={() => onNavigate('/team-leader/state')}
          >
            <Icon name="mapPin" size={15} />
            Lead Entry
          </button>
          <span className="history-count-badge">{activities.length} recorded</span>
        </div>
      </div>
      {activities.length ? (
        <div className="team-table-wrap team-activity-table-wrap is-fixed-activity">
          <table className="team-performance-table team-activity-table is-fixed-table">
            <thead>
              <tr><th>Employee</th><th>Activity</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
              {activities.map((activity, index) => (
                <tr key={`${activity.employeeId}-${activity.time}-${index}`}>
                  <td data-label="Employee">
                    <div style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.2 }}>
                      <strong style={{ fontSize: '0.78rem', color: '#0f172a' }}>{activity.employeeName}</strong>
                      {activity.employeeId && (
                        <span style={{ fontSize: '0.66rem', color: '#0284c7', fontWeight: '700' }}>#{activity.employeeId}</span>
                      )}
                    </div>
                  </td>
                  <td data-label="Activity">{activity.activity}</td>
                  <td data-label="Status">
                    <span className="status-pill status-present" style={{ fontSize: '0.7rem', padding: '3px 8px' }}>
                      <span className="status-dot" /> Verified
                    </span>
                  </td>
                  <td data-label="Date">{formatDashboardDate(activity.time)}</td>
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
  incentiveRecords,
  activityCounts,
  onMonthChange,
  onNavigate,
}) {
  const members = teamData?.members || []
  const actualSales = (salesData?.dailySales || []).reduce((total, sale) => total + Number(sale.amount), 0)
  const monthlyTarget = Number(salesData?.monthlyTarget || 0)
  const salesValue = isLoadingSales ? '…' : formatUsdt(actualSales)
  const teamProgress = monthlyTarget > 0 ? `${Math.round(actualSales / monthlyTarget * 100)}% of target` : 'Target not set'
  const monthIncentiveCount = Array.isArray(incentiveRecords)
    ? incentiveRecords.filter((item) => item.soldOn?.startsWith(selectedActivityMonth)).length
    : null
  const incomeParts = [
    activityCounts?.Attendance,
    monthIncentiveCount,
    activityCounts?.monthlyGrantCount,
    activityCounts?.monthlyRewardCount,
  ]
  const counts = {
    ...activityCounts,
    Incentives: isLoadingIncentives ? undefined : Array.isArray(incentiveRecords) ? incentiveRecords.length : null,
    'My Income': activityCounts === null
      ? undefined
      : incomeParts.some((count) => count === null || count === undefined)
        ? null
        : incomeParts.reduce((total, count) => total + count, 0),
    'Team Sales Tracker': isLoadingSales ? undefined : salesData?.dailySales?.length ?? 0,
    'Team Target': isLoadingSales ? undefined : monthlyTarget > 0 ? 1 : 0,
  }

  return (
    <>
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}

      <div className="team-dashboard-grid team-dashboard-grid-first">
        <TeamPerformancePanel members={members} isLoading={isLoading} search={search} compact />
        <TeamActivitiesPanel history={teamData?.history || []} selectedMonth={selectedActivityMonth} onNavigate={onNavigate} />
      </div>

      <RoleActivitySection
        title="Team Leader Activities"
        eyebrow="Your personal workspace"
        icon="crown"
        items={teamLeaderActivityCards}
        values={{
          'My Sales Unlock': salesValue,
          Incentives: isLoadingIncentives ? 'Loading…' : incentiveError ? 'Unavailable' : formatUsdt(earnedIncentives),
          'Incentive Grant': isLoading ? 'Loading team…' : `${members.length} team member${members.length === 1 ? '' : 's'}`,
          Reward: isLoadingSales ? 'Loading…' : teamProgress,
          'My Income': 'View breakdown',
          Attendance: 'Duty log',
          'Employee KYC': 'Update details',
          'Daily Activities': 'Submit activity',
        }}
        counts={counts}
        className="leader-activities"
      />
      <RoleActivitySection
        title="Team Operations & Monitoring"
        eyebrow="Team performance & management"
        icon="users"
        items={telecallerActivityCards}
        values={{
          'Team Sales Tracker': salesValue,
          'Team Target': isLoadingSales ? 'Loading…' : monthlyTarget > 0 ? formatUsdt(monthlyTarget) : 'Target not set',
          'Team Incentives': 'View report',
          'Team Rewards': isLoadingSales ? 'Loading…' : teamProgress,
          'Team Achievers': 'View all',
        }}
        counts={counts}
        className="telecaller-activities"
      />

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
    </>
  )
}

function EmergencySalesLockedPage({ user, progress, error, isLoading, onNavigate }) {
  const assignment = progress?.emergencyAssignment
  const rolePath = user?.role === 'team_leader' ? '/team-leader/emergency-sales' : '/telecaller/emergency-sales'

  return (
    <section className="emergency-unlock-locked-page">
      <article className="dashboard-panel emergency-unlock-locked-card">
        <span className="emergency-unlock-lock-icon"><Icon name="lock" size={24} /></span>
        <span className="mini-label">Emergency Sales Required</span>
        <h1>My Unlock Sales is Locked</h1>
        {isLoading ? (
          <p>Checking your emergency sales completion…</p>
        ) : error ? (
          <p className="dashboard-data-error" role="alert">Emergency sales completion could not be checked: {error}</p>
        ) : assignment ? (
          <>
            <p>
              Approved emergency sales: {formatUsdt(progress.approvedEmergencySalesAmount)} of {formatUsdt(assignment.packageAmount)}.
              Pending sales do not count toward completion.
            </p>
            <strong>{formatUsdt(progress.remainingEmergencySalesAmount)} remaining</strong>
          </>
        ) : (
          <p>No emergency sales assignment exists yet. My Unlock Sales stays locked until a Sub Editor assigns an amount and it is fully completed.</p>
        )}
        {!isLoading && !error && (
          <button type="button" className="emergency-package-assign-button" onClick={() => onNavigate(rolePath)}>
            Go to Emergency Sales
          </button>
        )}
      </article>
    </section>
  )
}

function TeamLeaderContent({
  user,
  onUserUpdate,
  page,
  currentPath,
  unlockSalesUnlocked,
  emergencyProgress,
  emergencyProgressError,
  isLoadingEmergencyProgress,
  onEmergencyOverviewChange,
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
  onNavigate,
}) {
  const districtLeadRoute = currentPath.match(/^\/team-leader\/state\/([A-Z]{2})\/(.+)$/)
  if (districtLeadRoute) {
    return (
      <TeamLeaderDistrictLeadsPage
        key={`${districtLeadRoute[1]}-${districtLeadRoute[2]}`}
        stateCode={districtLeadRoute[1]}
        district={decodeURIComponent(districtLeadRoute[2])}
        onNavigate={onNavigate}
      />
    )
  }

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
    return unlockSalesUnlocked
      ? <UnlockSalesPage user={user} />
      : <EmergencySalesLockedPage
          user={user}
          progress={emergencyProgress}
          error={emergencyProgressError}
          isLoading={isLoadingEmergencyProgress}
          onNavigate={onNavigate}
        />
  }

  if (page.path === '/team-leader/emergency-sales') {
    return <EmergencySalesPage user={user} onOverviewChange={onEmergencyOverviewChange} />
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

  if (page.path === '/team-leader/state' || page.path === '/team-leader/my-team') {
    return <TeamLeaderStatesPage onNavigate={onNavigate} />
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

  if (page.path === '/team-leader/income') {
    return <EmployeeIncomePage user={user} onNavigate={onNavigate} />
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
        onNavigate={onNavigate}
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
  unlockSalesUnlocked,
  emergencyProgress,
  emergencyProgressError,
  isLoadingEmergencyProgress,
  onEmergencyOverviewChange,
  salesData,
  isLoadingSales,
  salesError,
  earnedIncentives,
  isLoadingIncentives,
  incentiveError,
  onMonthChange,
}) {
  const [leads, setLeads] = useState([])
  const [isLoadingLeads, setIsLoadingLeads] = useState(true)
  const [todayAttendance, setTodayAttendance] = useState(null)
  const [achieversCount, setAchieversCount] = useState(0)
  const [isQuickLeadOpen, setIsQuickLeadOpen] = useState(false)
  const [leadNotice, setLeadNotice] = useState('')
  const [copiedNumber, setCopiedNumber] = useState(null)

  const [todayStr] = useState(() => new Date().toISOString().slice(0, 10))
  const storageKey = `arowin_caller_stats_${user?.id}_${todayStr}`

  const [callStats, setCallStats] = useState(() => {
    try {
      const saved = localStorage.getItem(storageKey)
      return saved ? JSON.parse(saved) : { calls: 14, followUps: 5 }
    } catch {
      return { calls: 14, followUps: 5 }
    }
  })

  const incrementCalls = () => {
    setCallStats((prev) => {
      const next = { ...prev, calls: (prev.calls || 0) + 1 }
      try { localStorage.setItem(storageKey, JSON.stringify(next)) } catch {}
      return next
    })
  }

  const incrementFollowUps = () => {
    setCallStats((prev) => {
      const next = { ...prev, followUps: (prev.followUps || 0) + 1 }
      try { localStorage.setItem(storageKey, JSON.stringify(next)) } catch {}
      return next
    })
  }

  useEffect(() => {
    let active = true
    apiRequest('/leads')
      .then((res) => {
        if (active) setLeads(res.leads || [])
      })
      .catch(() => {})
      .finally(() => {
        if (active) setIsLoadingLeads(false)
      })

    apiRequest('/attendance/me')
      .then((res) => {
        if (active) setTodayAttendance(res.attendance)
      })
      .catch(() => {})

    apiRequest('/sales/team-achievements')
      .then((res) => {
        if (active) setAchieversCount(res.result?.achievements?.length || 0)
      })
      .catch(() => {})

    return () => {
      active = false
    }
  }, [])

  const handleLeadCreated = (newLead) => {
    setLeads((prev) => [newLead, ...prev])
    setLeadNotice(`Lead for "${newLead.customerName}" captured successfully!`)
    setTimeout(() => setLeadNotice(''), 4500)
  }

  const handleCopyPhone = (number, id) => {
    navigator.clipboard?.writeText(number)
    setCopiedNumber(id)
    setTimeout(() => setCopiedNumber(null), 2000)
  }

  if (currentPath === '/telecaller/sales') {
    return unlockSalesUnlocked
      ? <UnlockSalesPage user={user} />
      : <EmergencySalesLockedPage
          user={user}
          progress={emergencyProgress}
          error={emergencyProgressError}
          isLoading={isLoadingEmergencyProgress}
          onNavigate={onNavigate}
        />
  }

  if (currentPath === '/telecaller/emergency-sales') {
    return <EmergencySalesPage user={user} onOverviewChange={onEmergencyOverviewChange} />
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

  if (currentPath === '/telecaller/income') {
    return <EmployeeIncomePage user={user} onNavigate={onNavigate} />
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

  const actualSales = (salesData?.dailySales || []).reduce((total, sale) => total + Number(sale.amount), 0)
  const approvedSalesCount = (salesData?.dailySales || []).length
  const todayLeadsCount = leads.filter((l) => l.createdAt?.slice(0, 10) === todayStr).length

  const metrics = [
    {
      label: 'Emergency Sales',
      value: 'Open',
      icon: 'zap',
      tone: 'amber',
      note: 'View amounts assigned to you',
      action: () => onNavigate('/telecaller/emergency-sales'),
    },
    {
      label: 'My Unlock Sales',
      value: isLoadingSales ? '…' : formatUsdt(actualSales),
      icon: 'trend',
      tone: 'blue',
      note: salesError ? 'Sales data unavailable' : `${approvedSalesCount} sales · ${formatMonth(salesData?.month || currentActivityMonth)}`,
      action: () => onNavigate('/telecaller/sales'),
    },
    {
      label: 'My Incentives',
      value: isLoadingIncentives ? '…' : formatUsdt(earnedIncentives),
      icon: 'gift',
      tone: 'green',
      note: incentiveError ? 'Unavailable' : 'Approved package sales earnings',
      action: () => onNavigate('/telecaller/incentives'),
    },
    {
      label: 'Customer Leads',
      value: isLoadingLeads ? '…' : String(leads.length),
      icon: 'userPlus',
      tone: 'purple',
      note: `${todayLeadsCount} added today · Direct pipeline`,
      action: () => setIsQuickLeadOpen(true),
    },
    {
      label: 'Team Achievers',
      value: String(achieversCount),
      icon: 'trophy',
      tone: 'amber',
      note: 'Colleagues with approved sales',
      action: () => onNavigate('/telecaller/team-achievers'),
    },
  ]

  const checkInTimeFormatted = todayAttendance?.checkInTime
    ? formatTime(todayAttendance.checkInTime)
    : null

  const isCheckedIn = Boolean(todayAttendance?.checkInTime)

  return (
    <div className="telecaller-dashboard">
      <header className="telecaller-dashboard-heading telecaller-dashboard-greeting">
        <h1>Welcome, {user.name}</h1>
      </header>

      {leadNotice && (
        <div className="leads-entry-notice" role="status" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <Icon name="check" size={18} />
          <strong>{leadNotice}</strong>
        </div>
      )}

      {/* 4 Hero Metric Cards with Vibrant Gradients */}
      <div className="telecaller-metrics-grid">
        {metrics.map((metric) => (
          <article
            className={`telecaller-metric-card ${metric.tone}`}
            key={metric.label}
            onClick={metric.action}
          >
            <span className="telecaller-metric-icon">
              <Icon name={metric.icon} size={25} />
            </span>
            <div className="telecaller-metric-copy">
              <strong>{metric.value}</strong>
              <span>{metric.label}</span>
              <small>{metric.note}</small>
            </div>
          </article>
        ))}
      </div>

      {/* Primary Layout Grid */}
      <div className="telecaller-panels-grid">
        <div style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
          {/* 2027 Interactive Daily Call Tracker */}
          <article className="telecaller-tracker-panel">
            <div className="tracker-panel-top">
              <div className="tracker-title-wrap">
                <span className="telecaller-panel-icon" style={{ background: '#e0f2fe', color: '#0284c7' }}>
                  <Icon name="headset" size={20} />
                </span>
                <div>
                  <h2>Today's Calling Operations Tracker</h2>
                  <p>Interactive activity logs for your current shift</p>
                </div>
              </div>
              <button
                type="button"
                className="recent-leads-link"
                onClick={() => setIsQuickLeadOpen(true)}
              >
                <Icon name="plus" size={14} /> New Lead
              </button>
            </div>

            <div className="tracker-stats-grid">
              <div className="tracker-stat-box">
                <div className="tracker-stat-label">
                  <span>Connected Calls</span>
                  <Icon name="phone" size={14} />
                </div>
                <div className="tracker-stat-num">{callStats.calls || 0}</div>
                <button
                  type="button"
                  className="tracker-quick-add-btn"
                  onClick={incrementCalls}
                  title="Click after completing a phone conversation"
                >
                  <Icon name="plus" size={12} /> Log Call
                </button>
              </div>

              <div className="tracker-stat-box">
                <div className="tracker-stat-label">
                  <span>Leads Added</span>
                  <Icon name="userPlus" size={14} />
                </div>
                <div className="tracker-stat-num">{leads.length}</div>
                <button
                  type="button"
                  className="tracker-quick-add-btn"
                  onClick={() => setIsQuickLeadOpen(true)}
                >
                  <Icon name="plus" size={12} /> Add Lead
                </button>
              </div>

              <div className="tracker-stat-box">
                <div className="tracker-stat-label">
                  <span>Follow-Ups</span>
                  <Icon name="calendar" size={14} />
                </div>
                <div className="tracker-stat-num">{callStats.followUps || 0}</div>
                <button
                  type="button"
                  className="tracker-quick-add-btn"
                  onClick={incrementFollowUps}
                >
                  <Icon name="plus" size={12} /> Follow-Up
                </button>
              </div>

              <div className="tracker-stat-box">
                <div className="tracker-stat-label">
                  <span>Conversions</span>
                  <Icon name="trend" size={14} />
                </div>
                <div className="tracker-stat-num">{approvedSalesCount}</div>
                <button
                  type="button"
                  className="tracker-quick-add-btn"
                  onClick={() => onNavigate('/telecaller/sales')}
                >
                  <Icon name="arrow" size={12} /> Submit
                </button>
              </div>
            </div>

            {/* Recent Leads Inside Tracker Panel */}
            <div className="telecaller-recent-leads">
              <div className="recent-leads-header">
                <h3>Recent Leads Pipeline</h3>
                <button
                  type="button"
                  className="recent-leads-link"
                  onClick={() => onNavigate('/telecaller/leads')}
                >
                  View All ({leads.length}) <Icon name="arrow" size={14} />
                </button>
              </div>

              {isLoadingLeads ? (
                <p className="team-achievers-message">Loading recent leads…</p>
              ) : leads.length ? (
                <table className="recent-leads-table">
                  <tbody>
                    {leads.slice(0, 4).map((lead) => (
                      <tr key={lead.id}>
                        <td>{lead.customerName}</td>
                        <td>
                          <span style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
                            <span>{lead.mobileNumber}</span>
                            <button
                              type="button"
                              onClick={() => handleCopyPhone(lead.mobileNumber, lead.id)}
                              style={{ border: 'none', background: 'none', cursor: 'pointer', color: '#64748b' }}
                              title="Copy mobile number"
                            >
                              <Icon name={copiedNumber === lead.id ? 'check' : 'copy'} size={13} />
                            </button>
                          </span>
                        </td>
                        <td>
                          <span className="status-pill status-present" style={{ fontSize: '0.7rem' }}>
                            {lead.district}
                          </span>
                        </td>
                        <td>
                          <a href={`tel:${lead.mobileNumber}`} className="lead-action-call-btn">
                            <Icon name="phone" size={12} /> Call
                          </a>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <p className="team-achievers-message">
                  No leads recorded yet. Click <strong>Quick Add Lead</strong> to begin your pipeline.
                </p>
              )}
            </div>
          </article>

        </div>

        {/* Right Side Column */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
          {/* Today's Attendance & Duty Card */}
          <article className="telecaller-panel telecaller-attendance-card">
            <div className="telecaller-panel-heading">
              <span className="telecaller-panel-icon" style={{ background: '#dcfce7', color: '#16a34a' }}>
                <Icon name="calendar" size={21} />
              </span>
              <div>
                <h2>Duty & Attendance</h2>
                <p>Today's shift and salary accrual</p>
              </div>
            </div>

            <div className="telecaller-attendance-summary">
              <span
                className="telecaller-attendance-mark"
                style={{
                  background: isCheckedIn ? '#ecfdf5' : '#eff6ff',
                  color: isCheckedIn ? '#10b981' : '#2563eb',
                }}
              >
                <Icon name="clock" size={27} />
              </span>
              <div>
                <strong>
                  {isCheckedIn ? (
                    <span style={{ display: 'flex', alignItems: 'center' }}>
                      <span className="live-ping-dot" /> Check-in: {checkInTimeFormatted}
                    </span>
                  ) : (
                    'Not Checked In Yet'
                  )}
                </strong>
                <p>
                  {isCheckedIn
                    ? 'Shift active. Keep logging activities and completed customer calls.'
                    : 'Check in from the office location to log today’s salary and attendance.'}
                </p>
              </div>
            </div>

            <button
              type="button"
              className="telecaller-primary-button"
              onClick={() => onNavigate('/telecaller/attendance')}
            >
              {isCheckedIn ? 'View Attendance & Duty Logs' : 'Check In Now'}{' '}
              <Icon name="arrow" size={17} />
            </button>
          </article>

          {/* Team Achievers Panel */}
          <TeamAchieversPanel />
        </div>

        {/* Full-width Sales Trend Chart */}
        <SalesTrendPanel
          compact
          fullWidthChart
          performance={salesData}
          isLoading={isLoadingSales}
          error={salesError}
          month={salesData?.month || currentActivityMonth}
          onMonthChange={onMonthChange}
          onViewSales={() => onNavigate('/telecaller/sales')}
        />
      </div>

      {/* Quick Lead Modal */}
      <QuickLeadModal
        isOpen={isQuickLeadOpen}
        onClose={() => setIsQuickLeadOpen(false)}
        onLeadCreated={handleLeadCreated}
      />
    </div>
  )
}

export default function RoleDashboard({ user, onUserUpdate, onLogout, currentPath, onNavigate }) {
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
  const [incentiveRecords, setIncentiveRecords] = useState(null)
  const [activityCounts, setActivityCounts] = useState(null)
  const [isLoadingIncentives, setIsLoadingIncentives] = useState(true)
  const [incentiveError, setIncentiveError] = useState('')
  const [showTeamNotifications, setShowTeamNotifications] = useState(false)
  const [showProfileMenu, setShowProfileMenu] = useState(false)
  const [emergencyProgress, setEmergencyProgress] = useState(null)
  const [emergencyProgressError, setEmergencyProgressError] = useState('')
  const [isLoadingEmergencyProgress, setIsLoadingEmergencyProgress] = useState(true)
  const notificationRef = useRef(null)
  const salesRequestId = useRef(0)
  const loadEmergencyProgress = useCallback(async () => {
    const result = await apiRequest('/sales/emergency/me')
    return result.overview
  }, [])
  const handleEmergencyOverviewChange = useCallback((overview) => {
    setEmergencyProgress(overview)
    setEmergencyProgressError('')
    setIsLoadingEmergencyProgress(false)
  }, [])
  const unlockSalesUnlocked = emergencyProgress?.unlockSalesUnlocked === true
  const isDistrictLeadRoute = isTeamLeader && /^\/team-leader\/state\/[A-Z]{2}\/.+$/.test(currentPath)
  const isStateLeadsPage = isTeamLeader
    && (currentPath === '/team-leader/state' || currentPath === '/team-leader/my-team' || isDistrictLeadRoute)
  const page = teamLeaderPages.find((item) => item.path === currentPath)
    || (isDistrictLeadRoute
      ? teamLeaderPages.find((item) => item.path === '/team-leader/state')
      : teamLeaderPages[0])
  const currentPageTitle = isTeamLeader
    ? page.title
    : telecallerNavItems.find((item) => item.path === currentPath)?.label || dashboard.title

  useEffect(() => {
    let active = true
    const refreshProgress = () => {
      loadEmergencyProgress()
        .then((progress) => {
          if (active) {
            setEmergencyProgress(progress)
            setEmergencyProgressError('')
          }
        })
        .catch((error) => {
          if (active) {
            setEmergencyProgress(null)
            setEmergencyProgressError(error.message)
          }
        })
        .finally(() => {
          if (active) setIsLoadingEmergencyProgress(false)
        })
    }

    setEmergencyProgress(null)
    setEmergencyProgressError('')
    setIsLoadingEmergencyProgress(true)
    refreshProgress()
    const refreshTimer = window.setInterval(refreshProgress, 30000)

    return () => {
      active = false
      window.clearInterval(refreshTimer)
    }
  }, [currentPath, loadEmergencyProgress, user.id])

  useEffect(() => {
    if (!showTeamNotifications) return undefined
    const handleOutsideClick = (event) => {
      if (notificationRef.current && !notificationRef.current.contains(event.target)) {
        setShowTeamNotifications(false)
      }
    }
    document.addEventListener('pointerdown', handleOutsideClick)
    return () => {
      document.removeEventListener('pointerdown', handleOutsideClick)
    }
  }, [showTeamNotifications])

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
    setActivityCounts(null)
    setSelectedActivityMonth(month)
  }

  useEffect(() => {
    if (!isTeamLeader || !['/', '/team-leader', '/team-leader/team-sales'].includes(currentPath)) return undefined

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
          setIncentiveRecords(result.result.incentives)
          setIncentiveError('')
        })
        .catch((error) => {
          if (active) {
            setIncentiveRecords(null)
            setIncentiveError(error.message)
          }
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

  useEffect(() => {
    if (!isTeamLeader || !['/', '/team-leader'].includes(currentPath)) return undefined

    let active = true
    const month = encodeURIComponent(selectedActivityMonth)
    const requests = Promise.allSettled([
      apiRequest('/sales/unlocks/me'),
      apiRequest(`/sales/team-grant-incentives?month=${month}`),
      apiRequest(`/sales/team-rewards?month=${month}`),
      apiRequest(`/attendance/history?month=${month}`),
      apiRequest('/employee-kyc'),
      apiRequest('/daily-activities'),
      apiRequest('/sales/team-incentives'),
      apiRequest('/sales/team-achievements'),
    ])

    requests.then((results) => {
      if (!active) return
      const valueAt = (index) => results[index].status === 'fulfilled' ? results[index].value : null
      const ownSales = valueAt(0)?.result?.sales
      const incentiveGrants = valueAt(1)?.result
      const rewards = valueAt(2)?.result
      const attendance = valueAt(3)?.history
      const kyc = valueAt(4)?.kyc
      const activities = valueAt(5)?.activities
      const teamIncentives = valueAt(6)?.result
      const achievements = valueAt(7)?.result?.achievements
      const safeLength = (items) => Array.isArray(items) ? items.length : null

      setActivityCounts({
        'My Sales Unlock': safeLength(ownSales),
        'Incentive Grant': incentiveGrants ? safeLength(incentiveGrants.grantHistory) : null,
        Reward: rewards ? safeLength(rewards.grantHistory) : null,
        Attendance: safeLength(attendance),
        'Employee KYC': kyc?.status ? Number(kyc.status !== 'not_submitted') : null,
        'Daily Activities': safeLength(activities),
        'Team Incentives': Array.isArray(teamIncentives?.employees)
          ? teamIncentives.employees.filter((employee) => Number(employee.totalIncentive) > 0).length
          : null,
        'Team Rewards': rewards ? safeLength(rewards.grantHistory) : null,
        'Team Achievers': safeLength(achievements),
        monthlyGrantCount: incentiveGrants ? Number(Number(incentiveGrants.grant?.leaderIncentiveAmount) > 0) : null,
        monthlyRewardCount: rewards ? Number(Boolean(rewards.grant)) : null,
      })
    })

    return () => { active = false }
  }, [currentPath, isTeamLeader, selectedActivityMonth, user.id])

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
                {group.items.filter((item) => !item.hidden).map((item) => {
                  const active = item.path === currentPath
                    || (item.path === '/team-leader/state' && currentPath.startsWith('/team-leader/state/'))
                    || (item.path === '/team-leader' && currentPath === '/')
                  const unlockSalesLocked = item.path === '/team-leader/sales' && !unlockSalesUnlocked
                  return (
                    <button
                      type="button"
                      key={item.path}
                      className={`nav-item${active ? ' active' : ''}`}
                      aria-current={active ? 'page' : undefined}
                      disabled={unlockSalesLocked}
                      title={unlockSalesLocked ? 'Complete approved emergency sales to unlock My Unlock Sales' : sidebarCollapsed ? item.label : undefined}
                      onClick={() => {
                        onNavigate(item.path)
                        if (window.matchMedia('(max-width: 1024px)').matches) setSidebarCollapsed(true)
                      }}
                    >
                      <Icon name={unlockSalesLocked ? 'lock' : item.icon} size={20} />
                      <span className="nav-label">{item.label}</span>
                      {unlockSalesLocked && <small className="nav-locked-label">Locked</small>}
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
                {group.items.filter((item) => !item.hidden).map((item) => {
                  const active = item.path === currentPath || (item.path === '/telecaller' && currentPath === '/')
                  const unlockSalesLocked = item.path === '/telecaller/sales' && !unlockSalesUnlocked
                  return (
                    <button
                      type="button"
                      key={item.label}
                      className={`telecaller-nav-item${active ? ' active' : ''}`}
                      aria-current={active ? 'page' : undefined}
                      disabled={!item.available || unlockSalesLocked}
                      title={unlockSalesLocked
                        ? 'Complete approved emergency sales to unlock My Unlock Sales'
                        : item.available ? undefined : 'This feature is not configured yet'}
                      onClick={() => {
                        onNavigate(item.path)
                        if (window.matchMedia('(max-width: 1024px)').matches) setSidebarCollapsed(true)
                      }}
                    >
                      <Icon name={unlockSalesLocked ? 'lock' : item.icon} size={19} />
                      <span>{item.label}</span>
                      {unlockSalesLocked ? <small>Locked</small> : !item.available && <small>Soon</small>}
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
                  <Icon name="menu" size={20} />
                </button>
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
                <Icon name="menu" size={20} />
              </button>
            </div>
          )}
          {isTeamLeader && currentPath === '/team-leader' && (
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
            <button
              type="button"
              className={`team-support-shortcut${currentPath === (isTeamLeader ? '/team-leader/support' : '/telecaller/support') ? ' active' : ''}`}
              onClick={() => onNavigate(isTeamLeader ? '/team-leader/support' : '/telecaller/support')}
              title="Support helpdesk"
            >
              <Icon name="headset" size={18} />
              <span>Support</span>
            </button>
            {isTeamLeader && currentPath === '/team-leader' && (
              <div className="team-notification-wrap" ref={notificationRef}>
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
              leavePath={isTeamLeader ? '/team-leader/leave' : '/telecaller/leave'}
              isOpen={showProfileMenu}
              onOpenChange={setShowProfileMenu}
              onNavigate={onNavigate}
              onLogout={onLogout}
            />
          </div>
        </header>

        {isTeamLeader ? (
          <section className={`role-dashboard-content${page.path === '/team-leader' ? ' team-leader-dashboard-content' : ''}${page.path === '/team-leader/income' ? ' income-dashboard-content' : ''}${page.path === '/team-leader/state' || page.path === '/team-leader/my-team' ? ' team-leader-state-content' : ''}`}>
            {page.path === '/team-leader' ? (
              <div className="team-dashboard-heading">
                <div className="team-heading-welcome">
                  <div className="team-heading-welcome-row">
                    <h1>Welcome, {user.name}</h1>
                    <button
                      type="button"
                      className="quick-dock-btn btn-primary team-emergency-sales-btn"
                      onClick={() => onNavigate('/team-leader/emergency-sales')}
                    >
                      <Icon name="zap" size={15} /> Emergency Sales
                    </button>
                  </div>
                </div>
                <TeamTargetDisplay
                  performance={salesData}
                  isLoading={isLoadingSales}
                  month={selectedActivityMonth}
                  teamCount={teamData?.members?.length || 0}
                />
              </div>
            ) : null}
            <TeamLeaderContent
              user={user}
              onUserUpdate={onUserUpdate}
              page={page}
              currentPath={currentPath}
              onNavigate={onNavigate}
              unlockSalesUnlocked={unlockSalesUnlocked}
              emergencyProgress={emergencyProgress}
              emergencyProgressError={emergencyProgressError}
              isLoadingEmergencyProgress={isLoadingEmergencyProgress}
              onEmergencyOverviewChange={handleEmergencyOverviewChange}
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
              incentiveRecords={incentiveRecords}
              activityCounts={activityCounts}
              onMonthChange={handleSalesMonthChange}
            />
          </section>
        ) : (
          <section className={`role-dashboard-content${currentPath === '/telecaller/attendance' ? '' : ' telecaller-home-content'}${currentPath === '/telecaller/income' ? ' income-dashboard-content' : ''}`}>
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
              unlockSalesUnlocked={unlockSalesUnlocked}
              emergencyProgress={emergencyProgress}
              emergencyProgressError={emergencyProgressError}
              isLoadingEmergencyProgress={isLoadingEmergencyProgress}
              onEmergencyOverviewChange={handleEmergencyOverviewChange}
              salesData={salesData}
              isLoadingSales={isLoadingSales}
              salesError={salesError}
              onMonthChange={handleSalesMonthChange}
            />
          )}
        </section>
        )}
      </main>
      {!isStateLeadsPage && (
        <WorkspaceBottomNav
          isTeamLeader={isTeamLeader}
          currentPath={currentPath}
          onNavigate={onNavigate}
          unlockSalesUnlocked={unlockSalesUnlocked}
        />
      )}
    </div>
  )
}
