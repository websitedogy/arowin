export const subEditorFeatures = [
  { label: 'Attendance Tracking', path: '/attendance', countKey: 'attendance_tracking', icon: 'clock', tone: 'sales-orange', status: 'Recorded attendance entries' },
  { label: 'Total Sales', path: '/sales/total', countKey: 'total_sales', icon: 'chart', tone: 'sales-blue', status: 'Approved sales entries' },
  { label: "This Week's Sales", path: '/sales/weekly', countKey: 'this_week_sales', icon: 'chart', tone: 'sales-green', status: 'Approved sales entries this week' },
  { label: 'Incentive Earned', path: '/incentives/earned', countKey: 'incentive_earned', icon: 'plus', tone: 'sales-purple', status: 'Approved sales earning incentives' },
  { label: 'Incentive Paid', path: '/incentives/paid', countKey: 'incentive_paid', icon: 'clock', tone: 'sales-orange', status: 'Recorded employee payouts' },
  { label: "Last Month's Sales", path: '/sales/monthly', countKey: 'last_month_sales', icon: 'chart', tone: 'sales-blue', status: 'Approved sales entries last month' },
  { label: 'Target Setup', path: '/targets', countKey: 'target_setup', icon: 'settings', tone: 'sales-purple', status: 'Targets set for this month' },
  { label: 'Achieved Rewards', path: '/rewards/achieved', countKey: 'achieved_rewards', icon: 'crown', tone: 'sales-green', status: 'Rewards earned by Team Leaders' },
  { label: 'Granted Rewards', path: '/rewards/granted', countKey: 'granted_rewards', icon: 'users', tone: 'sales-purple', status: 'Team incentives granted' },
  { label: 'Notification Trigger', path: '/notifications', countKey: 'notification_trigger', icon: 'bell', tone: 'sales-orange', status: 'Enabled notification events' },
  { label: 'All Leads', path: '/leads', countKey: 'all_leads', icon: 'file', tone: 'sales-blue', status: 'Saved telecaller leads' },
  { label: 'States Assign', path: '/state-assignments', icon: 'mapPin', tone: 'sales-green', status: 'Assign states to Team Leaders' },
]

export const subEditorFeatureByPath = Object.fromEntries(
  subEditorFeatures.map((feature) => [feature.path, feature]),
)
