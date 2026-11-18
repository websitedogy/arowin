import { useMemo, useState } from 'react'
import { apiRequest } from '../../api'
import EmployeeList from './EmployeeList'
import { isManagedEmployee } from './employeeData'
import Icon from '../dashboard/components/Icon'

const EMPTY_PASSWORD_FORM = { newPassword: '', confirmPassword: '' }

const filters = {
  all: {
    title: 'All Employees',
    matches: isManagedEmployee,
  },
  'team-leaders': {
    title: 'Team Leaders',
    matches: (employee) => employee.role === 'Team Leader',
  },
  telecallers: {
    title: 'Telecallers',
    matches: (employee) => employee.role === 'Telecaller',
  },
  'sub-editors': {
    title: 'Sub Editors',
    matches: (employee) => employee.role === 'Sub Editor',
  },
  active: {
    title: 'Active Staff',
    matches: (employee) => isManagedEmployee(employee) && employee.status === 'Active',
  },
}

export default function EmployeeDirectoryPage({ filter, employees, isLoading, onNavigate }) {
  const selectedFilter = filters[filter] || filters.all
  const [selectedEmployee, setSelectedEmployee] = useState(null)
  const [passwordForm, setPasswordForm] = useState(EMPTY_PASSWORD_FORM)
  const [passwordError, setPasswordError] = useState('')
  const [passwordReset, setPasswordReset] = useState(false)
  const [isResettingPassword, setIsResettingPassword] = useState(false)

  const filteredEmployees = useMemo(
    () => employees.filter(selectedFilter.matches),
    [employees, selectedFilter],
  )

  const openPasswordReset = (employee) => {
    setSelectedEmployee(employee)
    setPasswordForm(EMPTY_PASSWORD_FORM)
    setPasswordError('')
    setPasswordReset(false)
  }

  const closePasswordReset = () => {
    if (isResettingPassword) return
    setSelectedEmployee(null)
    setPasswordForm(EMPTY_PASSWORD_FORM)
    setPasswordError('')
    setPasswordReset(false)
  }

  const handlePasswordReset = async (event) => {
    event.preventDefault()
    setPasswordError('')
    if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      setPasswordError('The new passwords do not match.')
      return
    }

    setIsResettingPassword(true)
    try {
      await apiRequest(`/employees/${selectedEmployee.id}/password/reset`, {
        method: 'POST',
        body: passwordForm,
      })
      setPasswordForm(EMPTY_PASSWORD_FORM)
      setPasswordReset(true)
    } catch (requestError) {
      setPasswordError(requestError.message)
    } finally {
      setIsResettingPassword(false)
    }
  }

  return (
      <>
        <section className="employee-directory-content">
        <nav className="employee-directory-filters" aria-label="Employee category">
          {Object.entries(filters).map(([key, item]) => (
            <button
              type="button"
              key={key}
              className={`employee-filter-button${filter === key ? ' is-selected' : ''}`}
              aria-current={filter === key ? 'page' : undefined}
              onClick={() => onNavigate(key === 'all' ? '/employees/all' : `/employees/${key}`)}
            >
              {item.title}
            </button>
          ))}
        </nav>

        {isLoading ? (
          <p className="employee-directory-loading" role="status">Loading employees…</p>
        ) : (
          <div className="employee-directory-results">
            <p className="employee-directory-count">{filteredEmployees.length} employee{filteredEmployees.length === 1 ? '' : 's'}</p>
            <EmployeeList
              employees={filteredEmployees}
              onResetPassword={['team-leaders', 'telecallers'].includes(filter) ? openPasswordReset : undefined}
              emptyMessage={`There are no ${selectedFilter.title.toLowerCase()} to display.`}
            />
          </div>
        )}
        </section>
        {selectedEmployee && (
          <div
            className="password-change-backdrop"
            role="presentation"
            onMouseDown={(event) => {
              if (event.target === event.currentTarget) closePasswordReset()
            }}
          >
            <section
              className="password-change-dialog"
              role="dialog"
              aria-modal="true"
              aria-labelledby="employee-password-reset-title"
            >
              <header className="password-change-heading">
                <div>
                  <span>Sub Editor action</span>
                  <h2 id="employee-password-reset-title">Reset {selectedEmployee.role} Password</h2>
                </div>
                <button
                  type="button"
                  className="password-change-close"
                  onClick={closePasswordReset}
                  disabled={isResettingPassword}
                  aria-label="Close password reset dialog"
                >
                  <Icon name="close" size={17} />
                </button>
              </header>
              <p className="employee-password-reset-target">
                {selectedEmployee.fullName} · #{selectedEmployee.employeeId}
              </p>
              {passwordError && <p className="password-change-error" role="alert">{passwordError}</p>}
              {passwordReset ? (
                <div className="password-change-success">
                  <p role="status">
                    Password reset successfully. Share the new password with the {selectedEmployee.role} securely.
                  </p>
                  <button type="button" className="password-change-submit" onClick={closePasswordReset}>Done</button>
                </div>
              ) : (
                <form className="password-change-form" onSubmit={handlePasswordReset}>
                  <label>
                    <span>New password</span>
                    <input
                      type="password"
                      autoComplete="new-password"
                      value={passwordForm.newPassword}
                      onChange={(event) => setPasswordForm({ ...passwordForm, newPassword: event.target.value })}
                      minLength={8}
                      maxLength={72}
                      required
                      autoFocus
                    />
                  </label>
                  <label>
                    <span>Confirm new password</span>
                    <input
                      type="password"
                      autoComplete="new-password"
                      value={passwordForm.confirmPassword}
                      onChange={(event) => setPasswordForm({ ...passwordForm, confirmPassword: event.target.value })}
                      minLength={8}
                      maxLength={72}
                      required
                    />
                  </label>
                  <small className="password-change-hint">At least 8 characters. The current password is not required.</small>
                  <button type="submit" className="password-change-submit" disabled={isResettingPassword}>
                    {isResettingPassword ? 'Resetting…' : 'Set new password'}
                  </button>
                </form>
              )}
            </section>
          </div>
        )}
      </>
  )
}
