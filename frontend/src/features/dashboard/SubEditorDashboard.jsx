import { useEffect, useMemo, useRef, useState } from 'react'
import { apiRequest } from '../../api'
import CreateEmployeeModal from '../employees/CreateEmployeeModal'
import DashboardSectionHeading from './components/DashboardSectionHeading'
import Icon from './components/Icon'
import BusinessMetricCard from './components/BusinessMetricCard'
import { subEditorFeatures } from './subEditorFeatures'
import { isManagedEmployee } from '../employees/employeeData'

function TrendChart({ range, employees }) {
  const [today, setToday] = useState(() => {
    const date = new Date()
    date.setHours(0, 0, 0, 0)
    return date
  })
  const dayCount = Number(range)
  const start = new Date(today)
  start.setDate(start.getDate() - dayCount + 1)

  useEffect(() => {
    const now = new Date()
    const nextDay = new Date(now)
    nextDay.setHours(24, 0, 0, 0)
    const timer = setTimeout(() => {
      const date = new Date()
      date.setHours(0, 0, 0, 0)
      setToday(date)
    }, nextDay.getTime() - now.getTime())

    return () => clearTimeout(timer)
  }, [today])

  const startDay = Date.UTC(start.getFullYear(), start.getMonth(), start.getDate()) / 86400000
  const counts = Array(dayCount).fill(0)
  employees.forEach((employee) => {
    if (employee.roleKey === 'sub_editor' || employee.role === 'Sub Editor' || !employee.createdAt) return

    const datePart = String(employee.createdAt).slice(0, 10)
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(datePart)
    if (!match) return

    const createdDay = Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])) / 86400000
    const index = createdDay - startDay
    if (index >= 0 && index < dayCount) counts[index] += 1
  })

  const width = 640
  const height = 190
  const padding = { top: 12, right: 12, bottom: 30, left: 36 }
  const chartWidth = width - padding.left - padding.right
  const chartHeight = height - padding.top - padding.bottom
  const maxCount = Math.max(...counts)
  const tickStep = Math.max(1, Math.ceil(maxCount / 4))
  const maxValue = tickStep * 4
  const points = counts.map((count, index) => ({
    count,
    x: padding.left + (dayCount === 1 ? chartWidth / 2 : (index / (dayCount - 1)) * chartWidth),
    y: padding.top + chartHeight - (count / maxValue) * chartHeight,
    date: new Date(start.getFullYear(), start.getMonth(), start.getDate() + index),
  }))
  const tickIndexes = [...new Set([0, 1, 2, 3, 4].map((tick) => Math.round((tick * (dayCount - 1)) / 4)))]
  const dateFormatter = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' })
  const plottedPoints = points.map((point) => `${point.x},${point.y}`).join(' ')
  const totalCreated = counts.reduce((total, count) => total + count, 0)

  return (
    <div className="trend-chart">
      <svg
        viewBox={`0 0 ${width} ${height}`}
        role="img"
        aria-label={`Employee creation trend for the last ${dayCount} days: ${totalCreated} employees created`}
      >
        <title>Employee accounts created per day</title>
        {[0, 1, 2, 3, 4].map((tick) => {
          const value = maxValue - tick * tickStep
          const y = padding.top + (tick / 4) * chartHeight
          return (
            <g key={value}>
            <text className="trend-axis-label" x={padding.left - 8} y={y + 4} textAnchor="end">{value}</text>
            </g>
          )
        })}
        <polyline className="trend-line" points={plottedPoints} />
        {points.map((point) => (
          <circle className="trend-point" key={point.date.toISOString()} cx={point.x} cy={point.y} r={dayCount === 7 ? 3 : 1.8}>
            <title>{`${dateFormatter.format(point.date)}: ${point.count} employee${point.count === 1 ? '' : 's'}`}</title>
          </circle>
        ))}
        {tickIndexes.map((index) => {
          const point = points[index]
          return (
            <text className="trend-axis-label" key={point.date.toISOString()} x={point.x} y={height - 7} textAnchor="middle">
              {dateFormatter.format(point.date)}
            </text>
          )
        })}
      </svg>
    </div>
  )
}

export default function SubEditorDashboard({
  onNavigate,
  currentPath,
  employees,
  setEmployees,
  setActivities,
  setDataLoadError,
}) {
  const [showEmployeeModal, setShowEmployeeModal] = useState(false)
  const [deliveryNotice, setDeliveryNotice] = useState(null)
  const [isResendingCredentials, setIsResendingCredentials] = useState(false)
  const [chartRange, setChartRange] = useState('30')
  const [employeeRole, setEmployeeRole] = useState('Team Leader')
  const chartRef = useRef(null)

  useEffect(() => {
    if (currentPath === '/reports') {
      chartRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }, [currentPath])

  const employeeSummary = useMemo(() => {
    const employeeRecords = employees.filter(isManagedEmployee)
    const countRole = (role) => employeeRecords.filter((item) => item.role === role).length
    const leaders = employeeRecords.filter((item) => item.role === 'Team Leader').length
    const telecallers = employeeRecords.filter((item) => item.role === 'Telecaller').length
    return {
      summary: [
        { label: 'Budget', value: 'Open', tone: 'budget', icon: 'coins', path: '/budget', highlightLabel: true },
        { label: 'All Employees', value: employeeRecords.length, tone: 'employees', icon: 'users', filter: 'all' },
        { label: 'Team Leaders', value: countRole('Team Leader'), tone: 'leaders', icon: 'crown', filter: 'team-leaders' },
        { label: 'Telecallers', value: countRole('Telecaller'), tone: 'telecallers', icon: 'headset', filter: 'telecallers' },
        { label: 'Active Staff', value: employeeRecords.filter((item) => item.status === 'Active').length, tone: 'active', icon: 'users', filter: 'active' },
      ],
      breakdown: [
        { label: 'Team Leaders', value: leaders, tone: 'blue' },
        { label: 'Telecallers', value: telecallers, tone: 'orange' },
        { label: 'Others', value: employeeRecords.length - leaders - telecallers, tone: 'slate' },
      ],
      employeeCount: employeeRecords.length,
    }
  }, [employees])

  const { summary, breakdown: employeeBreakdown, employeeCount } = employeeSummary
  const breakdownTotal = employeeBreakdown.reduce((total, item) => total + item.value, 0)
  let donutStart = 0
  const donutBackground = breakdownTotal
    ? `conic-gradient(${employeeBreakdown.map((item, index) => {
      const colors = ['#0878f9', '#ff8a00', '#8052ff', '#94a3b8']
      const end = donutStart + (item.value / breakdownTotal) * 100
      const slice = `${colors[index]} ${donutStart}% ${end}%`
      donutStart = end
      return slice
    }).join(', ')})`
    : undefined

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
            <header className="page-heading">
              <div className="page-heading-copy">
                <span className="mini-label">Overview</span>
                <h1>Sub Editor Dashboard</h1>
                <p>Manage employees, teams and telecaller activities</p>
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
                description="Open a feature to view its records."
                id="business-overview-title"
              />
              <div className="business-metrics-grid">
                {subEditorFeatures.map((feature) => (
                  <BusinessMetricCard
                    key={feature.path}
                    label={feature.label}
                    icon={feature.icon}
                    tone={feature.tone}
                    value="—"
                    status={feature.status}
                    onClick={() => onNavigate(feature.path)}
                  />
                ))}
              </div>
            </section>

            <section className="analytics-grid">
              <article className="dashboard-panel overview-panel">
                <div className="panel-title">
                  <Icon name="users" size={23} />
                  <h2>Employee Overview</h2>
                </div>
                <div className="overview-content">
                  <div className="donut-chart" style={{ background: donutBackground }}>
                    <div className="donut-center">
                      <strong>{employeeCount}</strong>
                      <span>Total</span>
                    </div>
                  </div>
                  <ul className="overview-legend">
                    {employeeBreakdown.map((item) => (
                      <li key={item.label}>
                        <span className={`legend-dot ${item.tone}`} />
                        <span className="legend-name">{item.label}</span>
                        <strong>{item.value}</strong>
                      </li>
                    ))}
                  </ul>
                </div>
              </article>

              <article className="dashboard-panel trend-panel" ref={chartRef}>
                <div className="panel-title trend-title">
                  <div><Icon name="chart" size={22} /><h2>Employee Trend</h2></div>
                  <select aria-label="Employee trend period" value={chartRange} onChange={(event) => setChartRange(event.target.value)}>
                    <option value="7">Last 7 Days</option>
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                  </select>
                </div>
                <TrendChart range={chartRange} employees={employees} />
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
