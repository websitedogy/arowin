import BusinessMetricCard from '../dashboard/components/BusinessMetricCard'

const metrics = [
  { label: 'Achieved Rewards', icon: 'crown', tone: 'sales-green' },
  { label: 'Granted Rewards', icon: 'users', tone: 'sales-purple' },
]

export default function RewardMetrics() {
  return metrics.map((metric) => <BusinessMetricCard key={metric.label} {...metric} />)
}
