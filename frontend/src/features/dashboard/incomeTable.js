function amountOrZero(value) {
  const amount = Number(value)
  return Number.isFinite(amount) ? amount : 0
}

function addAmountForDate(amounts, dateValue, amount, month) {
  const date = String(dateValue || '').slice(0, 10)
  if (!date.startsWith(`${month}-`) || !amounts.has(date)) return
  amounts.set(date, amounts.get(date) + amountOrZero(amount))
}

function createRow(key, label, currency, dates, amounts, missing = false) {
  const values = dates.map((date) => (missing ? null : amounts.get(date) || 0))
  return {
    key,
    label,
    currency,
    amounts: values,
    total: missing ? null : values.reduce((total, amount) => total + amount, 0),
  }
}

export function buildDailyIncomeRows(month, attendance, incentives, teamGrant, teamReward) {
  if (!/^[0-9]{4}-(0[1-9]|1[0-2])$/.test(month)) {
    throw new Error('Choose a valid income month.')
  }

  const [year, monthNumber] = month.split('-').map(Number)
  const dayCount = new Date(year, monthNumber, 0).getDate()
  const days = Array.from({ length: dayCount }, (_, index) => {
    const dayNumber = index + 1
    const date = `${month}-${String(dayNumber).padStart(2, '0')}`
    return {
      date,
      dayNumber,
      weekday: new Intl.DateTimeFormat(undefined, { weekday: 'short' })
        .format(new Date(year, monthNumber - 1, dayNumber)),
    }
  })
  const dates = days.map((day) => day.date)
  const attendanceByDate = new Map(
    (Array.isArray(attendance?.days) ? attendance.days : []).map((day) => [day.date, day]),
  )
  const incentiveAmounts = new Map(dates.map((date) => [date, 0]))
  const rewardAmounts = new Map(dates.map((date) => [date, 0]))

  for (const incentive of incentives?.incentives || []) {
    addAmountForDate(incentiveAmounts, incentive.approvedAt, incentive.incentiveAmount, month)
  }

  const grants = [
    [teamGrant?.grant?.grantedAt, teamGrant?.grant?.leaderIncentiveAmount],
    [teamReward?.grant?.grantedAt, teamReward?.grant?.rewardAmount],
  ]
  for (const [grantedAt, amount] of grants) {
    addAmountForDate(rewardAmounts, grantedAt, amount, month)
  }

  const salaryMissing = attendance?.monthlySalary === null || attendance?.monthlySalary === undefined
  const salaryAmounts = new Map(dates.map((date) => [
    date,
    amountOrZero(attendanceByDate.get(date)?.earned),
  ]))
  const onTimeAmounts = new Map(dates.map((date) => [
    date,
    amountOrZero(attendanceByDate.get(date)?.onTimeBonus),
  ]))
  const activityAmounts = new Map(dates.map((date) => [
    date,
    amountOrZero(attendanceByDate.get(date)?.activityBonus),
  ]))

  const rows = [
    createRow('scale', 'My Scale', 'INR', dates, salaryAmounts, salaryMissing),
    createRow('on-time', 'On-time Bonus (Attendance)', 'INR', dates, onTimeAmounts),
    createRow('activities', 'Daily Activities', 'INR', dates, activityAmounts),
    createRow('incentives', 'Incentives', 'USDT', dates, incentiveAmounts),
    createRow('rewards', 'Rewards', 'USDT', dates, rewardAmounts),
  ]
  const currencyTotals = rows.reduce((totals, row) => {
    totals[row.currency] += row.total || 0
    return totals
  }, { INR: 0, USDT: 0 })

  return { days, rows, currencyTotals }
}
