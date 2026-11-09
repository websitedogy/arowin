import { useMemo } from 'react'
import EmployeeList from './EmployeeList'
import { isManagedEmployee } from './employeeData'

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

  const filteredEmployees = useMemo(
    () => employees.filter(selectedFilter.matches),
    [employees, selectedFilter],
  )

  return (
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
              emptyMessage={`There are no ${selectedFilter.title.toLowerCase()} to display.`}
            />
          </div>
        )}
      </section>
  )
}
