import formatDateTime from '../../utils/formatDateTime'

export default function formatDashboardDate(value) {
  return formatDateTime(value, undefined, { year: 'numeric', month: 'short', day: '2-digit' }, { fallback: 'Not recorded' })
}
