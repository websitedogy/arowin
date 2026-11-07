import Icon from './Icon'

export default function BusinessMetricCard({ label, icon, tone, value = '—', status = 'Not tracked yet', onClick }) {
  const content = (
    <>
      <span className="business-metric-icon"><Icon name={icon} size={20} /></span>
      <div>
        <strong aria-label={value === '—' ? `${label}: not available` : undefined}>{value}</strong>
        <span>{label}</span>
      </div>
      <small>{status}</small>
    </>
  )

  if (onClick) {
    return (
      <button
        type="button"
        className={`business-metric-card ${tone} workspace-feature-card`}
        onClick={onClick}
        aria-label={`Open ${label}`}
      >
        {content}
      </button>
    )
  }

  return <article className={`business-metric-card ${tone}`}>{content}</article>
}
