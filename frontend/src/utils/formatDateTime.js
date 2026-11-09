export default function formatDateTime(value, locale, options, { fallback = '—', assumedOffset } = {}) {
  if (!value) return fallback

  const normalizedValue = String(value).replace(' ', 'T')
  const hasTimezone = /(?:Z|[+-]\d{2}:\d{2})$/i.test(normalizedValue)
  const hasTime = /^\d{4}-\d{2}-\d{2}T/.test(normalizedValue)
  const dateValue = assumedOffset && hasTime && !hasTimezone
    ? `${normalizedValue}${assumedOffset}`
    : normalizedValue
  const date = new Date(dateValue)

  if (Number.isNaN(date.getTime())) return fallback
  return new Intl.DateTimeFormat(locale, options).format(date)
}
