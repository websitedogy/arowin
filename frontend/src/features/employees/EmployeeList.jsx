import Icon from '../dashboard/components/Icon'

export default function EmployeeList({
  employees,
  onAddEmployee,
  onResetPassword,
  emptyMessage = 'Create an employee ID to see it in this list.',
}) {
  return (
    <article className="dashboard-panel employee-list-panel">
      <div className="panel-title employee-list-title">
        <div><Icon name="users" size={22} /><h2>Employees List</h2></div>
        {onAddEmployee && (
          <button type="button" className="view-all-btn" onClick={onAddEmployee}>
            Add Employee <Icon name="plus" size={16} />
          </button>
        )}
      </div>
      {employees.length ? (
        <div className="employee-table-wrap">
          <table className="employee-table">
            <thead>
              <tr>
                <th>Employee ID</th><th>Employee</th><th>Role</th><th>Email</th><th>Status</th>
                {onResetPassword && <th>Actions</th>}
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
                  <td><span className={`employee-status${employee.status === 'Active' ? ' is-active' : ''}`}>{employee.status}</span></td>
                  {onResetPassword && (
                    <td>
                      {['team_leader', 'telecaller'].includes(employee.roleKey) && (
                        <button
                          type="button"
                          className="employee-password-reset-button"
                          onClick={() => onResetPassword(employee)}
                        >
                          <Icon name="lock" size={14} />
                          Reset Password
                        </button>
                      )}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="employee-list-empty">
          <strong>No matching employees</strong>
          <span>{emptyMessage}</span>
        </div>
      )}
    </article>
  )
}
