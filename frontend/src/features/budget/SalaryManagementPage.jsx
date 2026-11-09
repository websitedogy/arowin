import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import SalarySetupModal from './SalarySetupModal'
import { formatCurrency } from '../../utils/formatters'

export default function SalaryManagementPage({ isLoadingEmployees }) {
  const [employees, setEmployees] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [selectedEmployee, setSelectedEmployee] = useState(null)

  useEffect(() => {
    let active = true
    apiRequest('/budget/salaries')
      .then((result) => {
        if (active) {
          setEmployees(result.salaries)
          setLoadError('')
        }
      })
      .catch((error) => {
        if (active) setLoadError(error.message)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  const saveSalary = async ({ employee, joiningDate, salaryAmount }) => {
    const result = await apiRequest(`/budget/salaries/${employee.id}`, {
      method: 'POST',
      body: { joiningDate, salaryAmount },
    })
    setEmployees((current) => current.map((item) => item.id === employee.id
      ? { ...item, joiningDate: result.salary.joiningDate, salaryAmount: result.salary.salaryAmount }
      : item))
    setSelectedEmployee(null)
  }

  const loading = isLoading || isLoadingEmployees

  return (
    <section className="salary-page">
      <header className="page-heading budget-heading">
        <div className="page-heading-copy">
          <span className="mini-label">Budget / Salaries</span>
          <h1>Employee Salaries</h1>
          <p>View employee details and set or update salary information.</p>
        </div>
      </header>

      {loadError && <p className="budget-error" role="alert">{loadError}</p>}

      <article className="dashboard-panel employee-list-panel salary-list-panel">
        <div className="panel-title employee-list-title">
          <div><Icon name="users" size={22} /><h2>Employees</h2></div>
          <span className="salary-employee-count">{employees.length} employees</span>
        </div>
        {loading ? (
          <p className="employee-directory-loading" role="status">Loading employees…</p>
        ) : loadError ? null : employees.length ? (
          <div className="employee-table-wrap">
            <table className="employee-table salary-table">
              <thead>
                <tr>
                  <th>Employee ID</th>
                  <th>Employee</th>
                  <th>Role</th>
                  <th>Email</th>
                  <th>Joining Date</th>
                  <th>Salary</th>
                  <th>Salary Setup</th>
                </tr>
              </thead>
              <tbody>
                {employees.map((employee) => (
                  <tr key={employee.id}>
                    <td><strong className="employee-code">{employee.employeeId}</strong></td>
                    <td>
                      <span className="employee-table-person">
                        <span className="employee-table-avatar">{employee.fullName?.[0]?.toUpperCase() || 'E'}</span>
                        <strong>{employee.fullName}</strong>
                      </span>
                    </td>
                    <td>{employee.role}</td>
                    <td>{employee.email}</td>
                    <td>{employee.joiningDate || 'Not set'}</td>
                    <td>{employee.salaryAmount === null ? 'Not set' : formatCurrency(Number(employee.salaryAmount))}</td>
                    <td>
                      <button
                        type="button"
                        className="salary-setup-button"
                        onClick={() => setSelectedEmployee(employee)}
                      >
                        {employee.salaryAmount === null ? 'Set Salary' : 'Edit Salary'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="employee-list-empty">
            <strong>No employees to display</strong>
            <span>Create employee accounts to set up salaries.</span>
          </div>
        )}
      </article>

      {selectedEmployee && (
        <SalarySetupModal
          key={selectedEmployee.id}
          employee={selectedEmployee}
          onClose={() => setSelectedEmployee(null)}
          onSubmit={saveSalary}
        />
      )}
    </section>
  )
}
