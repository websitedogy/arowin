export default function DashboardSectionHeading({ eyebrow, title, description, id }) {
  return (
    <div className="dashboard-section-heading">
      <div>
        <span className="mini-label">{eyebrow}</span>
        <h2 id={id}>{title}</h2>
      </div>
      {description && <p>{description}</p>}
    </div>
  )
}
