export default function formatDashboardDate(value) {
  if (!value) return 'Not recorded'

  const date = new Date(String(value).replace(' ', 'T'))
  return Number.isNaN(date.getTime())
    ? 'Not recorded'
    : new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: '2-digit' }).format(date)
}
