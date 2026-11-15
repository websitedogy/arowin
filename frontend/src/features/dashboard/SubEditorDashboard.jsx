import { useEffect, useMemo, useRef, useState } from 'react'
import { apiRequest } from '../../api'
import formatDateTime from '../../utils/formatDateTime'
import { formatUsdt } from '../../utils/formatters'
import CreateEmployeeModal from '../employees/CreateEmployeeModal'
import DashboardSectionHeading from './components/DashboardSectionHeading'
import Icon from './components/Icon'
import BusinessMetricCard from './components/BusinessMetricCard'
import { subEditorFeatures } from './subEditorFeatures'
import { isManagedEmployee } from '../employees/employeeData'

function SalesOverviewChart({ summary }) {
  const salesByDay = new Map((summary?.dailySales || []).map((sale) => [sale.date, Number(sale.amount)]))
  const [year, month, day] = (summary?.startDate || '').split('-').map(Number)
  const weekStart = summary?.startDate ? new Date(year, month - 1, day) : null
  const days = weekStart
    ? Array.from({ length: 7 }, (_, index) => {
      const date = new Date(weekStart)
      date.setDate(date.getDate() + index)
      const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
      return {
        date,
        label: new Intl.DateTimeFormat(undefined, { weekday: 'short' }).format(date),
        amount: salesByDay.get(dateKey) || 0,
      }
    })
    : []

  const width = 640
  const height = 190
  const padding = { top: 12, right: 12, bottom: 30, left: 36 }
  const chartWidth = width - padding.left - padding.right
  const chartHeight = height - padding.top - padding.bottom
  const maximum = Math.max(...days.map((item) => item.amount), 0)
  const tickStep = Math.max(1, Math.ceil(maximum / 4))
  const maxValue = tickStep * 4
  const slotWidth = chartWidth / Math.max(days.length, 1)
  const points = days.map((item, index) => ({
    ...item,
    x: padding.left + slotWidth * index + slotWidth / 2,
    y: padding.top + chartHeight - (item.amount / maxValue) * chartHeight,
  }))
  const linePoints = points.map((point) => `${point.x},${point.y}`).join(' ')
  const areaPoints = points.length
    ? `${points[0].x},${padding.top + chartHeight} ${linePoints} ${points[points.length - 1].x},${padding.top + chartHeight}`
    : ''
  const totalSales = days.reduce((total, item) => total + item.amount, 0)

  return (
    <div className="sales-overview-chart">
      {days.length ? (
        <svg
          viewBox={`0 0 ${width} ${height}`}
          role="img"
          aria-label={`Approved sales this week: ${formatUsdt(totalSales)}`}
        >
          <title>Approved sales by day this week</title>
          <defs>
            <linearGradient id="sales-overview-area" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor="#1682f4" stopOpacity=".3" />
              <stop offset="100%" stopColor="#1682f4" stopOpacity=".02" />
            </linearGradient>
          </defs>
          {[0, 1, 2, 3, 4].map((tick) => {
            const value = maxValue - tick * tickStep
            const y = padding.top + (tick / 4) * chartHeight
            return (
              <g key={value}>
                <line className="sales-overview-gridline" x1={padding.left} x2={width - padding.right} y1={y} y2={y} />
                <text className="sales-overview-axis-label" x={padding.left - 8} y={y + 4} textAnchor="end">{value}</text>
              </g>
            )
          })}
          {points.length > 1 && <polygon className="sales-overview-area" points={areaPoints} />}
          <polyline className="sales-overview-line" points={linePoints} />
          {points.map((point) => (
            <g key={point.date.toISOString()}>
              <circle className="sales-overview-point" cx={point.x} cy={point.y} r="4">
                <title>{`${point.label}: ${formatUsdt(point.amount)}`}</title>
              </circle>
              <text className="sales-overview-axis-label" x={point.x} y={height - 7} textAnchor="middle">
                {point.label}
              </text>
            </g>
          ))}
        </svg>
      ) : (
        <p className="sales-overview-empty">Loading weekly sales…</p>
      )}
    </div>
  )
}

export default function SubEditorDashboard({
  onNavigate,
  currentPath,
  employees,
  setEmployees,
  activities,
  setActivities,
  setDataLoadError,
  isLoading,
}) {
  const [showEmployeeModal, setShowEmployeeModal] = useState(false)
  const [deliveryNotice, setDeliveryNotice] = useState(null)
  const [isResendingCredentials, setIsResendingCredentials] = useState(false)
  const [employeeRole, setEmployeeRole] = useState('Team Leader')
  const [featureCounts, setFeatureCounts] = useState(null)
  const [featureCountsError, setFeatureCountsError] = useState('')
  const [salesSummary, setSalesSummary] = useState(null)
  const [salesSummaryError, setSalesSummaryError] = useState('')
  const [isLoadingSalesSummary, setIsLoadingSalesSummary] = useState(true)
  const chartRef = useRef(null)

  useEffect(() => {
    let active = true
    apiRequest('/dashboard/summary')
      .then((result) => {
        if (!active) return
        if (!result.dashboard.featureCounts) {
          throw new Error('Dashboard feature counts were not returned.')
        }
        setFeatureCounts(result.dashboard.featureCounts)
      })
      .catch((error) => {
        if (active) setFeatureCountsError(error.message)
      })

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    let active = true
    apiRequest('/sales/summary?period=this_week')
      .then((result) => {
        if (active) setSalesSummary(result.summary)
      })
      .catch((error) => {
        if (active) setSalesSummaryError(error.message)
      })
      .finally(() => {
        if (active) setIsLoadingSalesSummary(false)
      })

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    if (currentPath === '/reports') {
      chartRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }, [currentPath])

  const employeeSummary = useMemo(() => {
    const employeeRecords = employees.filter(isManagedEmployee)
    const countRole = (role) => employeeRecords.filter((item) => item.role === role).length
    return {
      summary: [
        { label: 'Emergency Sales', value: 'Open', tone: 'emergency', icon: 'zap', path: '/sales/emergency', highlightLabel: true },
        { label: 'Budget', value: 'Open', tone: 'budget', icon: 'coins', path: '/budget', highlightLabel: true },
        { label: 'All Employees', value: employeeRecords.length, tone: 'employees', icon: 'users', filter: 'all' },
        { label: 'Team Leaders', value: countRole('Team Leader'), tone: 'leaders', icon: 'crown', filter: 'team-leaders' },
        { label: 'Telecallers', value: countRole('Telecaller'), tone: 'telecallers', icon: 'headset', filter: 'telecallers' },
        { label: 'Active Staff', value: employeeRecords.filter((item) => item.status === 'Active').length, tone: 'active', icon: 'users', filter: 'active' },
      ],
    }
  }, [employees])

  const { summary } = employeeSummary
  const [greeting] = useState(() => {
    const hour = new Date().getHours()
    return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'
  })

  const openEmployeeModal = (role = 'Team Leader') => {
    setEmployeeRole(role)
    setShowEmployeeModal(true)
  }

  const handleAddEmployee = async (employee) => {
    const result = await apiRequest('/employees', {
      method: 'POST',
      body: {
        fullName: employee.fullName,
        email: employee.email,
        mobile: employee.mobile,
        dob: employee.dob,
        address: employee.address,
        password: employee.password,
        role: employee.role.toLowerCase().replace(' ', '_'),
      },
    })
    setEmployees((current) => [result.employee, ...current])
    setActivities((current) => [result.activity, ...current])
    setDataLoadError('')
    setDeliveryNotice({
      employeeId: result.employee.id,
      employeeCode: result.employee.employeeId,
      email: result.employee.email,
      sent: result.emailDelivery.sent,
      message: result.emailDelivery.message,
    })
    return result
  }

  const resendCredentials = async () => {
    if (!deliveryNotice) return

    setIsResendingCredentials(true)
    try {
      const result = await apiRequest(`/employees/${deliveryNotice.employeeId}/credentials/resend`, { method: 'POST' })
      setDeliveryNotice((current) => current && ({
        ...current,
        sent: result.emailDelivery.sent,
        message: result.emailDelivery.message,
      }))
    } catch (error) {
      setDeliveryNotice((current) => current && ({
        ...current,
        sent: false,
        message: error.message,
      }))
    } finally {
      setIsResendingCredentials(false)
    }
  }

  return (
    <>
      <section className="dashboard-content">
        <header className="page-heading sub-editor-hero">
          <div className="page-heading-copy">
            <span className="mini-label">{greeting} <span aria-hidden="true">👋</span></span>
            <h1>Sub Editor <span>Dashboard</span></h1>
            <p>Manage employees, teams and telecaller activities efficiently.</p>
          </div>
          <div className="page-heading-actions">
            <button type="button" className="create-btn" onClick={() => openEmployeeModal()}>
              <Icon name="plus" size={19} />
              Create Employee ID
            </button>
          </div>
        </header>

            {deliveryNotice && (
              <div className={`dashboard-delivery-notice${deliveryNotice.sent ? ' is-sent' : ' is-failed'}`} role={deliveryNotice.sent ? 'status' : 'alert'}>
                <span>{deliveryNotice.sent
                  ? `Employee ID ${deliveryNotice.employeeCode} created. Credentials download link sent to ${deliveryNotice.email}.`
                  : `Employee ID ${deliveryNotice.employeeCode} created. Account email to ${deliveryNotice.email} was not sent. ${deliveryNotice.message}`}</span>
                {!deliveryNotice.sent && (
                  <button type="button" onClick={resendCredentials} disabled={isResendingCredentials}>
                    {isResendingCredentials ? 'Sending…' : 'Retry email'}
                  </button>
                )}
              </div>
            )}

            <section className="stats-grid dashboard-stats" aria-label="Employee summary">
              {summary.map((item) => (
                <button
                  type="button"
                  className={`stat-card tone-${item.tone}`}
                  key={item.label}
                  onClick={() => onNavigate(item.path || `/employees/${item.filter}`)}
                  aria-label={item.highlightLabel ? item.label : item.path ? `Open ${item.label}` : `View ${item.value} ${item.label.toLowerCase()}`}
                >
                  <div className="stat-icon"><Icon name={item.icon} size={27} /></div>
                  <strong>{item.highlightLabel ? item.label : item.value}</strong>
                  {!item.highlightLabel && <span>{item.label}</span>}
                  <Icon name={item.icon} size={54} className="stat-watermark" />
                </button>
              ))}
            </section>

            <section className="business-overview" aria-labelledby="business-overview-title">
              <DashboardSectionHeading
                eyebrow="Workspace"
                title="Sub Editor Features"
                description="Track key activity and open a feature for details."
                id="business-overview-title"
              />
              {featureCountsError && <p className="dashboard-data-error" role="alert">Feature counts could not be loaded: {featureCountsError}</p>}
              <div className="business-metrics-grid">
                {subEditorFeatures.map((feature) => (
                  <BusinessMetricCard
                    key={feature.path}
                    label={feature.label}
                    icon={feature.icon}
                    tone={feature.tone}
                    value={featureCounts?.[feature.countKey] ?? '—'}
                    status={feature.status}
                    onClick={() => onNavigate(feature.path)}
                  />
                ))}
              </div>
            </section>

        <section className="analytics-grid sub-editor-dashboard-bottom">
          <article className="dashboard-panel sales-overview-panel" ref={chartRef}>
            <div className="sub-editor-panel-heading">
              <div className="panel-title">
                <Icon name="chart" size={22} />
                <h2>Sales Overview</h2>
              </div>
              <span className="sub-editor-period-label">This Week</span>
            </div>
            {salesSummaryError && <p className="dashboard-data-error" role="alert">Sales overview could not be loaded: {salesSummaryError}</p>}
            {isLoadingSalesSummary ? (
              <p className="sales-overview-empty" role="status">Loading weekly sales…</p>
            ) : salesSummaryError ? (
              <p className="sales-overview-empty">Sales data is unavailable.</p>
            ) : (
              <>
                <p className="sales-overview-total">
                  {formatUsdt(salesSummary?.totalSales || 0)} <span>approved this week</span>
                </p>
                <SalesOverviewChart summary={salesSummary} />
              </>
            )}
          </article>

          <article className="dashboard-panel recent-activities-panel">
            <div className="sub-editor-panel-heading">
              <div className="panel-title">
                <Icon name="clock" size={21} />
                <h2>Recent Activities</h2>
              </div>
              <button type="button" className="recent-activities-link" onClick={() => onNavigate('/employees/all')}>
                View employees <Icon name="arrow" size={14} />
              </button>
            </div>
            {isLoading ? (
              <p className="recent-activities-empty" role="status">Loading recent activity…</p>
            ) : activities?.length ? (
              <ul className="recent-activities-list">
                {activities.slice(0, 5).map((activity) => (
                  <li key={activity.id}>
                    <span className="recent-activity-icon"><Icon name="users" size={17} /></span>
                    <span className="recent-activity-copy">
                      <strong>{activity.name} added</strong>
                      <small>{activity.role} · New employee account</small>
                    </span>
                    <time dateTime={`${String(activity.time).replace(' ', 'T')}+05:30`}>{formatDateTime(activity.time, undefined, {
                      day: 'numeric',
                      month: 'short',
                      hour: 'numeric',
                      minute: '2-digit',
                      timeZone: 'Asia/Kolkata',
                    }, { assumedOffset: '+05:30' })}</time>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="recent-activities-empty">No recent employee activity yet.</p>
            )}
          </article>
        </section>

      </section>
      <CreateEmployeeModal
        isOpen={showEmployeeModal}
        initialRole={employeeRole}
        onClose={() => setShowEmployeeModal(false)}
        onSubmit={handleAddEmployee}
      />
    </>
  )
}
