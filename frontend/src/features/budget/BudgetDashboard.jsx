import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import DashboardSectionHeading from '../dashboard/components/DashboardSectionHeading'
import Icon from '../dashboard/components/Icon'
import { formatCurrency } from '../../utils/formatters'

const budgetCategories = [
  { label: 'Total Budget', icon: 'coins', tone: 'blue' },
  { label: 'Office Budget', icon: 'home', tone: 'purple' },
  { label: 'Salaries', icon: 'users', tone: 'green', path: '/budget/salaries' },
  { label: 'Assets (Purchased)', icon: 'gift', tone: 'orange' },
  { label: 'Expenses', icon: 'file', tone: 'red' },
  { label: 'Advantages', icon: 'trend', tone: 'teal' },
  { label: 'Ads Budget', icon: 'chart', tone: 'indigo' },
]

export default function BudgetDashboard({ onNavigate }) {
  const [salaryTotal, setSalaryTotal] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')

  useEffect(() => {
    let active = true
    apiRequest('/budget/salaries')
      .then((result) => {
        if (active) {
          setSalaryTotal(result.salaries.reduce((total, employee) => total + Number(employee.salaryAmount || 0), 0))
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

  return (
    <section className="budget-page">
      <header className="page-heading budget-heading">
        <div className="page-heading-copy">
          <span className="mini-label">Finance</span>
          <h1>Budget</h1>
          <p>Review your budget categories and manage employee salaries.</p>
        </div>
      </header>

      {loadError && <p className="budget-error" role="alert">{loadError}</p>}

      <section className="budget-grid" aria-label="Budget categories">
        {budgetCategories.map((category) => {
          const value = category.label === 'Salaries'
            ? (isLoading ? 'Loading…' : formatCurrency(salaryTotal))
            : 'Not set'
          const content = (
            <>
              <span className="budget-card-icon"><Icon name={category.icon} size={22} /></span>
              <span className="budget-card-label">{category.label}</span>
              <strong>{value}</strong>
              {category.path && <span className="budget-card-action">View employee salaries <Icon name="arrow" size={16} /></span>}
            </>
          )

          return category.path ? (
            <button
              type="button"
              key={category.label}
              className={`budget-card tone-${category.tone}`}
              onClick={() => onNavigate(category.path)}
            >
              {content}
            </button>
          ) : (
            <article key={category.label} className={`budget-card tone-${category.tone}`}>
              {content}
            </article>
          )
        })}
      </section>

      <section className="budget-note" aria-labelledby="budget-note-title">
        <DashboardSectionHeading
          eyebrow="Budget tracking"
          title="More budget categories"
          description="Office, assets, expenses, advantages and ads budgets can be filled in when budget setup is available."
          id="budget-note-title"
        />
      </section>
    </section>
  )
}
