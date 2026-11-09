const inrCurrency = new Intl.NumberFormat('en-IN', {
  style: 'currency',
  currency: 'INR',
  maximumFractionDigits: 2,
})

export function formatCurrency(amount) {
  return inrCurrency.format(amount)
}

export function formatUsdt(amount) {
  return `${Number(amount || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })} USDT`
}

export function formatMonth(value, month = 'long') {
  const date = new Date(`${value}-01T00:00:00`)
  return Number.isNaN(date.getTime())
    ? value
    : new Intl.DateTimeFormat(undefined, { month, year: 'numeric' }).format(date)
}
