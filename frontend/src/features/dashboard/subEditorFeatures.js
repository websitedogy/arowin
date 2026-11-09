export const subEditorFeatures = [
  { label: 'Attendance Tracking', path: '/attendance', icon: 'clock', tone: 'sales-orange', status: 'Configure the office location' },
  { label: 'Total Sales', path: '/sales/total', icon: 'chart', tone: 'sales-blue', status: 'Review pending and approved package sales' },
  { label: "This Week's Sales", path: '/sales/weekly', icon: 'chart', tone: 'sales-green', status: 'Approved package sales recorded this week' },
  { label: 'Incentive Earned', path: '/incentives/earned', icon: 'plus', tone: 'sales-purple', status: 'Incentives from approved package sales' },
  { label: 'Incentive Paid', path: '/incentives/paid', icon: 'clock', tone: 'sales-orange', status: 'Not tracked yet' },
  { label: "Last Month's Sales", path: '/sales/monthly', icon: 'chart', tone: 'sales-blue', status: 'Approved package sales recorded last month' },
  { label: 'Target Setup', path: '/targets', icon: 'settings', tone: 'sales-purple', status: 'Target management not configured' },
  { label: 'Achieved Rewards', path: '/rewards/achieved', icon: 'crown', tone: 'sales-green', status: 'Not tracked yet' },
  { label: 'Granted Rewards', path: '/rewards/granted', icon: 'users', tone: 'sales-purple', status: 'Not tracked yet' },
  { label: 'Notification Trigger', path: '/notifications', icon: 'bell', tone: 'sales-orange', status: 'Notification rules not configured' },
  { label: 'All Leads', path: '/leads', icon: 'file', tone: 'sales-blue', status: 'Lead tracking not configured' },
]

export const subEditorFeatureByPath = Object.fromEntries(
  subEditorFeatures.map((feature) => [feature.path, feature]),
)
