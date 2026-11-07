import BusinessMetricCard from '../dashboard/components/BusinessMetricCard'

const metrics = [
  { label: 'Incentive Earned', icon: 'plus', tone: 'sales-purple' },
  { label: 'Incentive Paid', icon: 'clock', tone: 'sales-orange' },
]

export default function IncentiveMetrics() {
  return metrics.map((metric) => <BusinessMetricCard key={metric.label} {...metric} />)
}
