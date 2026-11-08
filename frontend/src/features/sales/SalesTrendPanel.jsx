import { useMemo, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'

function localToday() {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function monthDays(month) {
  const [year, value] = month.split('-').map(Number)
  return new Date(year, value, 0).getDate()
}

function amountByDay(performance) {
  return new Map((performance?.dailySales || []).map((sale) => [
    sale.date,
    Number(sale.amount),
  ]))
}

function achievementTone(actual, target) {
  if (!target || target <= 0) return 'untracked'
  const percentage = (actual / target) * 100
  if (percentage < 33) return 'red'
  if (percentage <= 66) return 'yellow'
  return 'green'
}

function formatAmount(value) {
  return formatUsdt(value)
}

function DateAxis({ days, x, y }) {
  const interval = Math.max(1, Math.ceil(days / 7))
  const labels = []
  for (let day = 1; day <= days; day += interval) labels.push(day)
  if (labels[labels.length - 1] !== days) labels.push(days)

  return labels.map((day) => (
    <text key={day} x={x(day)} y={y} className="sales-chart-axis-label" textAnchor="middle">{day}</text>
  ))
}

function DailySalesChart({ days, month, dailyTotals, dailyTarget }) {
  const width = 720
  const height = 260
  const left = 48
  const right = 18
  const top = 18
  const bottom = 38
  const chartWidth = width - left - right
  const chartHeight = height - top - bottom
  const maximum = Math.max(dailyTarget * 1.18, ...dailyTotals, 1)
  const x = (day) => left + ((day - 0.5) / days) * chartWidth
  const y = (value) => top + chartHeight - (value / maximum) * chartHeight
  const barWidth = Math.max(2, Math.min(15, chartWidth / days * 0.62))

  return (
    <svg className="sales-trend-svg" viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Daily sales compared with daily target and break-even">
      {[0, 0.25, 0.5, 0.75, 1].map((fraction) => {
        const value = maximum * fraction
        return (
          <g key={fraction}>
            <line x1={left} x2={width - right} y1={y(value)} y2={y(value)} className="sales-chart-gridline" />
            <text x={left - 7} y={y(value) + 4} className="sales-chart-axis-label" textAnchor="end">
              {Math.round(value)}
            </text>
          </g>
        )
      })}
      {dailyTarget > 0 && (
        <>
          <line x1={left} x2={width - right} y1={y(dailyTarget * 0.5)} y2={y(dailyTarget * 0.5)} className="sales-chart-breakeven-line" />
          <line x1={left} x2={width - right} y1={y(dailyTarget)} y2={y(dailyTarget)} className="sales-chart-target-line" />
        </>
      )}
      {dailyTotals.map((amount, index) => {
        const day = index + 1
        const tone = achievementTone(amount, dailyTarget)
        const barHeight = (amount / maximum) * chartHeight
        return (
          <rect
            key={`${month}-${day}`}
            x={x(day) - barWidth / 2}
            y={top + chartHeight - barHeight}
            width={barWidth}
            height={Math.max(amount > 0 ? 2 : 0, barHeight)}
            rx="3"
            className={`sales-chart-bar tone-${tone}`}
          >
            <title>{`${day}: ${formatAmount(amount)}`}</title>
          </rect>
        )
      })}
      <DateAxis days={days} x={x} y={height - 10} />
    </svg>
  )
}

function WeeklySalesChart({ days, dailyTotals, weeklyTarget }) {
  const width = 720
  const left = 118
  const right = 112
  const top = 24
  const rowHeight = 48
  const chartWidth = width - left - right
  const weeks = Array.from({ length: Math.ceil(days / 7) }, (_, index) => {
    const start = index * 7
    const end = Math.min(start + 7, days)
    const total = dailyTotals.slice(start, end).reduce((sum, amount) => sum + amount, 0)
    const target = weeklyTarget * ((end - start) / 7)
    return { number: index + 1, start: start + 1, end, total, target }
  })
  const maximum = Math.max(...weeks.map((week) => week.target * 1.12), ...weeks.map((week) => week.total), 1)

  return (
    <svg className="sales-trend-svg sales-weekly-svg" viewBox={`0 0 ${width} ${top + rowHeight * weeks.length + 10}`} role="img" aria-label="Weekly sales totals compared with weekly target and break-even">
      {weeks.map((week, index) => {
        const y = top + index * rowHeight
        const fillWidth = Math.min(chartWidth, (week.total / maximum) * chartWidth)
        const tone = achievementTone(week.total, week.target)
        return (
          <g key={week.number}>
            <text x={left - 13} y={y + 15} className="sales-week-label" textAnchor="end">{`Week ${week.number}`}</text>
            <text x={left - 13} y={y + 30} className="sales-week-date-label" textAnchor="end">{`${week.start}–${week.end}`}</text>
            <rect x={left} y={y} width={chartWidth} height="21" rx="8" className="sales-week-track" />
            <line x1={left + chartWidth * 0.5 * (week.target / maximum)} x2={left + chartWidth * 0.5 * (week.target / maximum)} y1={y - 3} y2={y + 24} className="sales-week-break-even" />
            <line x1={left + chartWidth * (week.target / maximum)} x2={left + chartWidth * (week.target / maximum)} y1={y - 3} y2={y + 24} className="sales-week-target" />
            {fillWidth > 0 && <rect x={left} y={y} width={fillWidth} height="21" rx="8" className={`sales-week-fill tone-${tone}`} />}
            <text x={width - right + 12} y={y + 14} className="sales-week-amount">{formatAmount(week.total)}</text>
            {weeklyTarget > 0 && <text x={width - right + 12} y={y + 29} className={`sales-week-percent tone-text-${tone}`}>{`${Math.round((week.total / week.target) * 100)}% of target`}</text>}
          </g>
        )
      })}
      {weeklyTarget > 0 && (
        <g className="sales-week-legend">
          <line x1={left} x2={left + 20} y1={top + rowHeight * weeks.length + 2} y2={top + rowHeight * weeks.length + 2} className="sales-week-break-even" />
          <text x={left + 25} y={top + rowHeight * weeks.length + 6}>50% break-even</text>
          <line x1={left + 145} x2={left + 165} y1={top + rowHeight * weeks.length + 2} y2={top + rowHeight * weeks.length + 2} className="sales-week-target" />
          <text x={left + 170} y={top + rowHeight * weeks.length + 6}>100% target</text>
        </g>
      )}
    </svg>
  )
}

function MonthlySalesChart({ days, dailyTotals, monthlyTarget }) {
  const width = 720
  const height = 260
  const left = 50
  const right = 20
  const top = 20
  const bottom = 36
  const chartWidth = width - left - right
  const chartHeight = height - top - bottom
  const cumulative = dailyTotals.map((_, index) => dailyTotals.slice(0, index + 1).reduce((sum, amount) => sum + amount, 0))
  const maximum = Math.max(monthlyTarget * 1.12, ...cumulative, 1)
  const x = (day) => left + ((day - 1) / Math.max(1, days - 1)) * chartWidth
  const y = (value) => top + chartHeight - (value / maximum) * chartHeight
  const actualPath = cumulative.map((amount, index) => `${index ? 'L' : 'M'} ${x(index + 1)} ${y(amount)}`).join(' ')
  const actualArea = `${actualPath} L ${x(days)} ${y(0)} L ${x(1)} ${y(0)} Z`
  const targetPath = dailyTotals.map((_, index) => `${index ? 'L' : 'M'} ${x(index + 1)} ${y(Math.min(monthlyTarget, (monthlyTarget / 28) * (index + 1)))}`).join(' ')
  const achieved = cumulative[cumulative.length - 1] || 0
  const tone = achievementTone(achieved, monthlyTarget)

  return (
    <svg className="sales-trend-svg" viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Cumulative monthly sales compared with target pace, break-even, and full target">
      {[0, 0.25, 0.5, 0.75, 1].map((fraction) => {
        const value = maximum * fraction
        return (
          <g key={fraction}>
            <line x1={left} x2={width - right} y1={y(value)} y2={y(value)} className="sales-chart-gridline" />
            <text x={left - 8} y={y(value) + 4} className="sales-chart-axis-label" textAnchor="end">{Math.round(value)}</text>
          </g>
        )
      })}
      {monthlyTarget > 0 && (
        <>
          <line x1={left} x2={width - right} y1={y(monthlyTarget * 0.5)} y2={y(monthlyTarget * 0.5)} className="sales-chart-breakeven-line" />
          <line x1={left} x2={width - right} y1={y(monthlyTarget)} y2={y(monthlyTarget)} className="sales-chart-target-line" />
          <path d={targetPath} className="sales-chart-target-pace" />
        </>
      )}
      <path d={actualArea} className={`sales-month-area tone-${tone}`} />
      {cumulative.length > 0 && <path d={actualPath} className={`sales-month-line tone-stroke-${tone}`} />}
      {cumulative.length > 0 && <circle cx={x(days)} cy={y(achieved)} r="5" className={`sales-month-point tone-${tone}`} />}
      <DateAxis days={days} x={x} y={height - 10} />
    </svg>
  )
}

export default function SalesTrendPanel({
  performance,
  isLoading,
  error,
  month,
  onMonthChange,
  onDailySaleSaved,
  canRecord = false,
  compact = false,
  team = false,
  onViewSales,
}) {
  const [chartType, setChartType] = useState('daily')
  const [saleDateValue, setSaleDateValue] = useState(() => {
    const today = localToday()
    return today.startsWith(month) ? today : `${month}-01`
  })
  const [draftAmount, setDraftAmount] = useState(null)
  const [isSaving, setIsSaving] = useState(false)
  const [saveError, setSaveError] = useState('')
  const [notice, setNotice] = useState('')
  const days = monthDays(month)
  const today = localToday()
  const saleDate = saleDateValue.startsWith(month)
    ? saleDateValue
    : today.startsWith(month) ? today : `${month}-01`
  const salesMap = useMemo(() => amountByDay(performance), [performance])
  const dailyTotals = Array.from({ length: days }, (_, index) => {
    const day = String(index + 1).padStart(2, '0')
    return salesMap.get(`${month}-${day}`) || 0
  })
  const actualTotal = dailyTotals.reduce((total, amount) => total + amount, 0)
  const monthlyTarget = Number(performance?.monthlyTarget || 0)
  const dailyTarget = monthlyTarget / 28
  const weeklyTarget = monthlyTarget / 4
  const targetPercent = monthlyTarget > 0 ? Math.round((actualTotal / monthlyTarget) * 100) : null
  const selectedSale = salesMap.get(saleDate)

  const saveDailySale = async (event) => {
    event.preventDefault()
    const amount = draftAmount ?? selectedSale ?? ''
    if (amount === '' || !Number.isFinite(Number(amount)) || Number(amount) < 0) {
      setSaveError('Enter a valid daily sales amount.')
      return
    }

    setIsSaving(true)
    setSaveError('')
    setNotice('')
    try {
      await apiRequest('/sales/daily', {
        method: 'POST',
        body: { date: saleDate, amount: String(amount) },
      })
      setNotice(`Daily sales for ${saleDate} saved.`)
      setDraftAmount(null)
      await onDailySaleSaved?.()
    } catch (requestError) {
      setSaveError(requestError.message)
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <article className={`sales-trend-panel${team ? ' team-dashboard-panel' : ''}${compact ? ' is-compact' : ''}`}>
      <div className="sales-trend-heading">
        <div className="sales-trend-title">
          <span className="sales-trend-icon"><Icon name="chart" size={21} /></span>
          <span><h2>{performance?.scope === 'team' ? 'Team Sales Trend' : 'My Sales Trend'}</h2><small>{performance?.scope === 'team' ? 'Approved package sales from your assigned Telecallers' : 'Your approved unlock package sales'}</small></span>
        </div>
        <label className="sales-trend-month">
          <Icon name="calendar" size={16} />
          <span className="visually-hidden">Sales month</span>
          <input type="month" value={month} max={today.slice(0, 7)} onChange={(event) => event.target.value && onMonthChange(event.target.value)} />
        </label>
      </div>

      {error && <p className="sales-trend-error" role="alert">{error}</p>}
      {isLoading ? (
        <p className="sales-trend-message" role="status">Loading sales performance…</p>
      ) : (
        <>
          <div className="sales-trend-summary">
            <div><span>Actual sales</span><strong>{formatAmount(actualTotal)}</strong></div>
            <div><span>{performance?.scope === 'team' ? 'Monthly team target' : 'Target status'}</span><strong>{monthlyTarget > 0 ? formatAmount(monthlyTarget) : 'Not assigned'}</strong></div>
            {monthlyTarget > 0 && <div><span>Target achieved</span><strong className={`sales-summary-percent tone-text-${achievementTone(actualTotal, monthlyTarget)}`}>{targetPercent}%</strong></div>}
          </div>

          <div className="sales-chart-toolbar">
            <div className="sales-chart-tabs" role="tablist" aria-label="Sales chart period">
              {[
                ['daily', 'Daily'],
                ['weekly', 'Weekly'],
                ['monthly', 'Monthly'],
              ].map(([value, label]) => (
                <button
                  type="button"
                  key={value}
                  role="tab"
                  aria-selected={chartType === value}
                  className={chartType === value ? 'is-selected' : ''}
                  onClick={() => setChartType(value)}
                >
                  {label}
                </button>
              ))}
            </div>
            {monthlyTarget > 0 && (
              <span className="sales-chart-period-target">
                {chartType === 'daily' ? `Daily target ${formatAmount(dailyTarget)}` : chartType === 'weekly' ? `Weekly target ${formatAmount(weeklyTarget)}` : `Monthly target ${formatAmount(monthlyTarget)}`}
              </span>
            )}
          </div>

          {monthlyTarget > 0 && (
            <div className="sales-chart-legend" aria-label="Target achievement thresholds">
              <span><i className="tone-red" /> Below 33%</span>
              <span><i className="tone-yellow" /> 33–66%</span>
              <span><i className="tone-green" /> Above 66%</span>
              <span><i className="sales-legend-break-even" /> 50% break-even</span>
            </div>
          )}

          <div className={`sales-chart-visual chart-${chartType}`}>
            {chartType === 'daily' && <DailySalesChart days={days} month={month} dailyTotals={dailyTotals} dailyTarget={dailyTarget} />}
            {chartType === 'weekly' && <WeeklySalesChart days={days} dailyTotals={dailyTotals} weeklyTarget={weeklyTarget} />}
            {chartType === 'monthly' && <MonthlySalesChart days={days} dailyTotals={dailyTotals} monthlyTarget={monthlyTarget} />}
          </div>

          {monthlyTarget <= 0 && (
            <p className="sales-target-notice">
              {performance?.scope === 'team'
                ? 'Ask your Sub Editor to assign a monthly Team Leader target to show target and break-even benchmarks.'
                : 'Your Team Leader sets the team target. This graph shows your sales without assuming an individual target.'}
            </p>
          )}

          {canRecord && (
            <form className="daily-sales-form" onSubmit={saveDailySale}>
              <div className="daily-sales-form-heading">
                <strong>Record daily unlock sales</strong>
                <span>Update today’s amount or add a previous day.</span>
              </div>
              <label>
                <span>Date</span>
                <input
                  type="date"
                  value={saleDate}
                  max={month === today.slice(0, 7) ? today : `${month}-${String(days).padStart(2, '0')}`}
                  min={`${month}-01`}
                  onChange={(event) => {
                    setSaleDateValue(event.target.value)
                    setDraftAmount(null)
                    setNotice('')
                    setSaveError('')
                  }}
                  required
                />
              </label>
              <label>
                <span>Unlock sales</span>
                <span className="daily-sales-amount"><b>USDT</b><input type="number" min="0" max="999999999999.99" step="0.01" value={draftAmount ?? selectedSale ?? ''} onChange={(event) => setDraftAmount(event.target.value)} placeholder="0.00" required /></span>
              </label>
              <button type="submit" disabled={isSaving}>{isSaving ? 'Saving…' : 'Save daily sales'}</button>
              {saveError && <p className="sales-trend-error" role="alert">{saveError}</p>}
              {notice && <p className="sales-trend-success" role="status">{notice}</p>}
            </form>
          )}

          {!canRecord && performance?.scope === 'team' && (
            <p className="sales-team-note">{performance.memberCount} assigned Telecaller{performance.memberCount === 1 ? '' : 's'} · Daily, weekly and monthly sales are combined for the selected month.</p>
          )}
          {onViewSales && (
            <button type="button" className="sales-view-entry-button" onClick={onViewSales}>
              Record or review daily sales <Icon name="arrow" size={16} />
            </button>
          )}
        </>
      )}
    </article>
  )
}
import { formatUsdt } from '../../utils/formatters'
