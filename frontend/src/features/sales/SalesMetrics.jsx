import BusinessMetricCard from '../dashboard/components/BusinessMetricCard'

const metrics = [
  { label: 'Total Sales', icon: 'chart', tone: 'sales-blue' },
  { label: "This Week's Sales", icon: 'chart', tone: 'sales-green' },
  { label: "Last Month's Sales", icon: 'chart', tone: 'sales-blue' },
]

export default function SalesMetrics() {
  return metrics.map((metric) => <BusinessMetricCard key={metric.label} {...metric} />)
}
