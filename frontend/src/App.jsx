import { useCallback, useEffect, useRef, useState } from 'react'
import './App.css'
import { apiRequest } from './api'
import LoginPage from './features/auth/LoginPage'
import CredentialsDownloadPage from './features/auth/CredentialsDownloadPage'
import SubEditorDashboard from './features/dashboard/SubEditorDashboard'
import FeatureDetailPage from './features/dashboard/FeatureDetailPage'
import StateAssignmentPage from './features/dashboard/StateAssignmentPage'
import { subEditorFeatureByPath } from './features/dashboard/subEditorFeatures'
import SubEditorLayout from './features/dashboard/SubEditorLayout'
import RoleDashboard from './features/dashboard/RoleDashboard'
import TargetSetupPage from './features/dashboard/TargetSetupPage'
import EmployeeDirectoryPage from './features/employees/EmployeeDirectoryPage'
import EmployeeKycPage from './features/employees/EmployeeKycPage'
import BudgetDashboard from './features/budget/BudgetDashboard'
import SalaryManagementPage from './features/budget/SalaryManagementPage'
import AttendanceTrackingPage from './features/attendance/AttendanceTrackingPage'
import AllLeadsPage from './features/sales/AllLeadsPage'
import LeaveManagementPage from './features/leave/LeaveManagementPage'
import DailyActivitiesPage from './features/activities/DailyActivitiesPage'
import RewardsReportPage from './features/sales/RewardsReportPage'
import PaidIncentivesPage from './features/sales/PaidIncentivesPage'
import SalesReviewPage from './features/sales/SalesReviewPage'
import EmergencySalesAssignPage from './features/sales/EmergencySalesAssignPage'
import EarnedIncentivesPage from './features/sales/EarnedIncentivesPage'
import SalesSummaryPage from './features/sales/SalesSummaryPage'
import NotificationTriggersPage from './features/notifications/NotificationTriggersPage'
import SettingsPage from './features/settings/SettingsPage'
import SupportTicketsPage from './features/support/SupportTicketsPage'

function App() {
  const credentialToken = window.location.pathname.match(/^\/credentials\/([a-f0-9]{64})$/)?.[1]
  const [user, setUser] = useState(null)
  const [isCheckingSession, setIsCheckingSession] = useState(() => !credentialToken)
  const [backendError, setBackendError] = useState('')
  const [currentPath, setCurrentPath] = useState(() => window.location.pathname)
  const routeHistory = useRef([])
  const currentPathRef = useRef(window.location.pathname)

  useEffect(() => {
    const handlePopState = () => {
      const nextPath = window.location.pathname
      const stack = routeHistory.current
      if (stack[stack.length - 1] === nextPath) {
        stack.pop()
      } else if (currentPathRef.current !== nextPath) {
        stack.push(currentPathRef.current)
      }
      currentPathRef.current = nextPath
      setCurrentPath(nextPath)
    }
    window.addEventListener('popstate', handlePopState)
    return () => window.removeEventListener('popstate', handlePopState)
  }, [])

  useEffect(() => {
    if (credentialToken) {
      return undefined
    }

    let active = true

    apiRequest('/auth/me')
      .then((result) => {
        if (active) setUser(result.user)
      })
      .catch((error) => {
        if (!active) return
        if (error.status !== 401) setBackendError(error.message)
      })
      .finally(() => {
        if (active) setIsCheckingSession(false)
      })

    return () => {
      active = false
    }
  }, [credentialToken])

  const handleLogin = (userData) => {
    setBackendError('')
    if (userData.role === 'team_leader') {
      navigate('/team-leader')
    }
    setUser(userData)
  }

  const navigate = useCallback((path) => {
    if (path === currentPathRef.current) return
    routeHistory.current.push(currentPathRef.current)
    window.history.pushState({}, '', path)
    currentPathRef.current = path
    setCurrentPath(path)
  }, [])

  const handleLogout = async () => {
    try {
      await apiRequest('/auth/logout', { method: 'POST' })
      setBackendError('')
    } catch (error) {
      setBackendError(error.message)
    } finally {
      navigate('/')
      setUser(null)
    }
  }

  if (credentialToken) {
    return <CredentialsDownloadPage token={credentialToken} />
  }

  if (isCheckingSession) {
    return <main className="auth-checking" aria-label="Checking sign-in session">Loading your workspace…</main>
  }

  if (!user) {
    return <LoginPage onLogin={handleLogin} backendError={backendError} />
  }

  if (user.role === 'sub_editor') {
    const employeeFilter = currentPath.match(/^\/employees\/(all|team-leaders|telecallers|active|sub-editors)$/)?.[1]
      || (currentPath === '/staff' ? 'all' : null)

    return (
      <SubEditorLayout user={user} onLogout={handleLogout} onNavigate={navigate} currentPath={currentPath}>
        {(workspace) => {
          if (employeeFilter) {
            return (
              <EmployeeDirectoryPage
                filter={employeeFilter}
                employees={workspace.employees}
                isLoading={workspace.isLoading}
                onNavigate={navigate}
              />
            )
          }

          if (currentPath === '/settings') {
            return <SettingsPage />
          }

          if (currentPath === '/employee-kyc') {
            return <EmployeeKycPage user={user} />
          }

          if (currentPath === '/budget') {
            return <BudgetDashboard onNavigate={navigate} />
          }

          if (currentPath === '/budget/salaries') {
            return <SalaryManagementPage isLoadingEmployees={workspace.isLoading} />
          }

          if (currentPath === '/attendance') {
            return <AttendanceTrackingPage />
          }

          if (currentPath === '/leave-requests') {
            return <LeaveManagementPage user={user} />
          }

          if (currentPath === '/support-tickets') {
            return <SupportTicketsPage user={user} />
          }

          if (currentPath === '/daily-activities') {
            return <DailyActivitiesPage user={user} />
          }

          if (currentPath === '/leads') {
            return <AllLeadsPage />
          }

          if (currentPath === '/state-assignments') {
            return <StateAssignmentPage />
          }

          if (currentPath.startsWith('/targets')) {
            return <TargetSetupPage currentPath={currentPath} onNavigate={navigate} />
          }

          if (currentPath === '/sales/emergency/assign') {
            return (
              <EmergencySalesAssignPage
                employees={workspace.employees}
                isLoadingEmployees={workspace.isLoading}
                onNavigate={navigate}
              />
            )
          }

          if (currentPath === '/sales/emergency') {
            return <SalesReviewPage saleType="emergency" employees={workspace.employees} onNavigate={navigate} />
          }

          if (currentPath === '/sales/unlocks/review') {
            return <SalesReviewPage saleType="regular" employees={workspace.employees} onNavigate={navigate} />
          }

          if (currentPath === '/incentives/packages') {
            return <SettingsPage />
          }

          if (currentPath === '/incentives/earned') {
            return <EarnedIncentivesPage />
          }

          if (currentPath === '/incentives/paid') {
            return <PaidIncentivesPage />
          }

          if (currentPath === '/notifications') {
            return <NotificationTriggersPage />
          }

          if (['/sales/total', '/sales/weekly', '/sales/monthly'].includes(currentPath)) {
            return <SalesSummaryPage key={currentPath} feature={subEditorFeatureByPath[currentPath]} />
          }

          if (['/rewards/achieved', '/rewards/granted'].includes(currentPath)) {
            return <RewardsReportPage key={currentPath} feature={subEditorFeatureByPath[currentPath]} />
          }

          if (subEditorFeatureByPath[currentPath]) {
            return <FeatureDetailPage feature={subEditorFeatureByPath[currentPath]} />
          }

          return (
            <SubEditorDashboard
              user={user}
              onNavigate={navigate}
              currentPath={currentPath}
              {...workspace}
            />
          )
        }}
      </SubEditorLayout>
    )
  }

  if (user.role === 'team_leader' || user.role === 'telecaller') {
    return (
      <RoleDashboard
        user={user}
        onUserUpdate={setUser}
        onLogout={handleLogout}
        currentPath={currentPath}
        onNavigate={navigate}
      />
    )
  }

  return <LoginPage onLogin={handleLogin} backendError={`The account role "${user.role}" does not have a dashboard.`} />
}

export default App
