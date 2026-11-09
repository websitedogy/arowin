import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import { formatCurrency } from '../../utils/formatters'

const budgetCategories = [
  { key: 'office', label: 'Office Budget', icon: 'home', tone: 'purple' },
  { key: 'salaries', label: 'Salaries', icon: 'users', tone: 'green', path: '/budget/salaries' },
  { key: 'assets', label: 'Assets', icon: 'gift', tone: 'orange' },
  { key: 'expenses', label: 'Expenses', icon: 'file', tone: 'red' },
  { key: 'advances', label: 'Advances', icon: 'trend', tone: 'teal' },
  { key: 'ads_promotions', label: 'Ads & Promotions', icon: 'chart', tone: 'indigo' },
]

const emptyAllocations = () => Object.fromEntries(budgetCategories.map(({ key }) => [key, '0.00']))
const emptyBankDetails = () => ({ bankName: '', accountHolderName: '', accountNumber: '', ifscCode: '' })
const currency = (value) => formatCurrency(Number(value || 0))

function amountInCents(value) {
  if (!/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/.test(value)) return 0
  const [rupees, paise = ''] = value.split('.')
  return Number(rupees) * 100 + Number(paise.padEnd(2, '0'))
}

function BudgetModal({
  title,
  description,
  children,
  onClose,
  onSubmit,
  submitLabel,
  cancelLabel = 'Cancel',
  submitDisabled = false,
  isSaving,
}) {
  return (
    <div className="budget-modal-backdrop" role="presentation" onMouseDown={(event) => {
      if (event.target === event.currentTarget && !isSaving) onClose()
    }}>
      <section className="budget-modal" role="dialog" aria-modal="true" aria-labelledby="budget-modal-title">
        <header className="budget-modal-heading">
          <div>
            <span className="mini-label">Budget setup</span>
            <h2 id="budget-modal-title">{title}</h2>
            <p>{description}</p>
          </div>
          <button type="button" className="budget-modal-close" onClick={onClose} disabled={isSaving} aria-label="Close">×</button>
        </header>
        <form onSubmit={onSubmit}>
          {children}
          <footer className="budget-modal-actions">
            <button type="button" className="budget-secondary-button" onClick={onClose} disabled={isSaving}>{cancelLabel}</button>
            {submitLabel && (
              <button type="submit" className="budget-primary-button" disabled={isSaving || submitDisabled}>
                {isSaving ? 'Saving…' : submitLabel}
              </button>
            )}
          </footer>
        </form>
      </section>
    </div>
  )
}

export default function BudgetDashboard({ onNavigate }) {
  const [budget, setBudget] = useState(null)
  const [salaryTotal, setSalaryTotal] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [saveError, setSaveError] = useState('')
  const [showCompanyModal, setShowCompanyModal] = useState(false)
  const [showAllocationModal, setShowAllocationModal] = useState(false)
  const [companyAmount, setCompanyAmount] = useState('')
  const [bankDetails, setBankDetails] = useState(emptyBankDetails)
  const [allocations, setAllocations] = useState(emptyAllocations)
  const [allocationCategoryKey, setAllocationCategoryKey] = useState(null)
  const [allocationAmount, setAllocationAmount] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  const refresh = async () => {
    const [budgetResult, salaryResult] = await Promise.all([
      apiRequest('/budget'),
      apiRequest('/budget/salaries'),
    ])
    setBudget(budgetResult.budget)
    setCompanyAmount(budgetResult.budget.companyBudget || '')
    setBankDetails({ ...emptyBankDetails(), ...budgetResult.budget.bankDetails })
    setAllocations({ ...emptyAllocations(), ...budgetResult.budget.allocations })
    setSalaryTotal(salaryResult.salaries.reduce((total, employee) => total + Number(employee.salaryAmount || 0), 0))
  }

  useEffect(() => {
    let active = true
    Promise.all([apiRequest('/budget'), apiRequest('/budget/salaries')])
      .then(([budgetResult, salaryResult]) => {
        if (!active) return
        setBudget(budgetResult.budget)
        setCompanyAmount(budgetResult.budget.companyBudget || '')
        setBankDetails({ ...emptyBankDetails(), ...budgetResult.budget.bankDetails })
        setAllocations({ ...emptyAllocations(), ...budgetResult.budget.allocations })
        setSalaryTotal(salaryResult.salaries.reduce((total, employee) => total + Number(employee.salaryAmount || 0), 0))
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

  const allocatedCents = useMemo(
    () => budgetCategories.reduce((total, { key }) => total + amountInCents(allocations[key] || ''), 0),
    [allocations],
  )
  const companyCents = budget?.companyBudget ? amountInCents(budget.companyBudget) : 0
  const remainingBudgetCents = companyCents - allocatedCents
  const hasSavedAllocations = Object.values(budget?.assignedCategories || {}).some(Boolean)
  const selectedCategory = budgetCategories.find(({ key }) => key === allocationCategoryKey)
  const selectedCategoryIsAssigned = allocationCategoryKey !== null
    && budget?.assignedCategories?.[allocationCategoryKey] === true
  const selectedCategoryCents = selectedCategoryIsAssigned
    ? amountInCents(allocations[allocationCategoryKey] || '0')
    : 0
  const allocationLimitCents = Math.max(0, companyCents - (allocatedCents - selectedCategoryCents))
  const isAllocationAmountValid = /^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/.test(allocationAmount)
    && amountInCents(allocationAmount) <= allocationLimitCents

  const openAllocationModal = () => {
    setSaveError('')
    setAllocationCategoryKey(null)
    setAllocationAmount('')
    setShowAllocationModal(true)
  }

  const selectAllocationCategory = (category) => {
    setSaveError('')
    setAllocationCategoryKey(category.key)
    setAllocationAmount(budget?.assignedCategories?.[category.key] ? allocations[category.key] : '')
  }

  const saveCompanyBudget = async (event) => {
    event.preventDefault()
    setSaveError('')
    setIsSaving(true)
    try {
      await apiRequest('/budget/company', {
        method: 'POST',
        body: { totalBudget: companyAmount, ...bankDetails },
      })
      await refresh()
      setShowCompanyModal(false)
    } catch (error) {
      setSaveError(error.message)
    } finally {
      setIsSaving(false)
    }
  }

  const saveAllocation = async (event) => {
    event.preventDefault()
    setSaveError('')
    if (!selectedCategory || !isAllocationAmountValid) {
      setSaveError('Enter an amount within the remaining Company Budget.')
      return
    }
    setIsSaving(true)
    try {
      await apiRequest('/budget/allocations', {
        method: 'POST',
        body: { allocations: { [selectedCategory.key]: allocationAmount } },
      })
      await refresh()
      setShowAllocationModal(false)
    } catch (error) {
      setSaveError(error.message)
    } finally {
      setIsSaving(false)
    }
  }

  const summaryValue = (cents) => formatCurrency(cents / 100)

  return (
    <section className="budget-page">
      <header className="page-heading budget-heading">
        <div className="page-heading-copy">
          <span className="mini-label">Finance</span>
          <h1>Budget</h1>
          <p>Add the company budget, then assign it across all six categories.</p>
        </div>
      </header>

      {loadError && <p className="budget-error" role="alert">{loadError}</p>}
      {saveError && !showCompanyModal && !showAllocationModal && <p className="budget-error" role="alert">{saveError}</p>}

      <section className="budget-grid" aria-label="Company and category budgets">
        <article className="budget-card tone-blue budget-company-card">
          <span className="budget-card-icon"><Icon name="coins" size={22} /></span>
          <span className="budget-card-label">Total Budget</span>
          <strong>{isLoading ? 'Loading…' : budget?.companyBudget ? currency(budget.companyBudget) : 'Not set'}</strong>
          {budget?.bankDetails && (
            <div className="budget-bank-summary">
              <span>{budget.bankDetails.bankName}</span>
              <span>{budget.bankDetails.accountHolderName}</span>
              <span>A/C {budget.bankDetails.maskedAccountNumber} · IFSC {budget.bankDetails.ifscCode}</span>
            </div>
          )}
          <button
            type="button"
            className="budget-card-action-button"
            onClick={() => {
              setSaveError('')
              setShowCompanyModal(true)
            }}
          >
            {budget?.companyBudget ? 'Edit Company Budget' : 'Add Company Budget'}
          </button>
          <button
            type="button"
            className="budget-primary-button budget-card-assign-button"
            disabled={isLoading || !budget?.companyBudget}
            onClick={openAllocationModal}
          >
            Assign Budget
          </button>
        </article>

        {budgetCategories.map((category) => {
          const isAssigned = budget?.assignedCategories?.[category.key] === true
          const allocationCents = amountInCents(budget?.allocations?.[category.key] || '0')
          const allocationShare = companyCents > 0
            ? Math.round((allocationCents / companyCents) * 100)
            : 0
          const value = isLoading
            ? 'Loading…'
            : isAssigned
              ? currency(allocationCents / 100)
              : category.key === 'salaries' && salaryTotal > 0
                ? currency(salaryTotal)
              : 'Not assigned'
          const content = (
            <>
              <span className="budget-card-icon"><Icon name={category.icon} size={22} /></span>
              <span className="budget-card-label">{category.label}</span>
              <strong>{value}</strong>
              {isAssigned && !isLoading && (
                <span className="budget-card-supporting">{allocationShare}% of Company Budget</span>
              )}
              {category.key === 'salaries' && !isLoading && (
                <span className={`budget-card-supporting${isAssigned ? '' : ' is-warning'}`}>
                  {isAssigned
                    ? `Current employee payroll: ${currency(salaryTotal)}`
                    : salaryTotal > 0
                      ? 'Current employee payroll · Category budget not assigned'
                      : 'Employee payroll not set'}
                </span>
              )}
              {category.path && <span className="budget-card-action">Manage employee salaries <Icon name="arrow" size={16} /></span>}
            </>
          )

          return category.path ? (
            <button
              type="button"
              key={category.key}
              className={`budget-card tone-${category.tone}`}
              onClick={() => onNavigate(category.path)}
            >
              {content}
            </button>
          ) : (
            <article key={category.key} className={`budget-card tone-${category.tone}`}>
              {content}
            </article>
          )
        })}
      </section>

      {budget?.companyBudget && (
        <p className={`budget-allocation-summary${remainingBudgetCents < 0 ? ' is-warning' : ''}`}>
          {hasSavedAllocations
            ? `Assigned ${summaryValue(allocatedCents)} of ${currency(budget.companyBudget)} · ${remainingBudgetCents >= 0 ? `Remaining ${summaryValue(remainingBudgetCents)}` : `Over by ${summaryValue(Math.abs(remainingBudgetCents))}`}`
            : `Choose a category to assign part of the Company Budget of ${currency(budget.companyBudget)}.`}
        </p>
      )}

      {showCompanyModal && (
        <BudgetModal
          title={budget?.companyBudget ? 'Edit Company Budget' : 'Add Company Budget'}
          description="Set the total funds available before assigning category budgets."
          onClose={() => setShowCompanyModal(false)}
          onSubmit={saveCompanyBudget}
          submitLabel={budget?.companyBudget ? 'Save Company Budget' : 'Add Company Budget'}
          isSaving={isSaving}
        >
          {saveError && <p className="budget-error" role="alert">{saveError}</p>}
          <label className="budget-form-field">
            <span>Company Budget</span>
            <div className="budget-amount-input">
              <span>₹</span>
              <input
                type="number"
                min="0.01"
                step="0.01"
                value={companyAmount}
                onChange={(event) => setCompanyAmount(event.target.value)}
                placeholder="Enter total amount"
                required
              />
            </div>
          </label>
          <div className="budget-bank-fields-heading">
            <strong>Add Bank Details</strong>
            <span>Required for budget setup</span>
          </div>
          <label className="budget-form-field">
            <span>Bank Name</span>
            <input
              className="budget-text-input"
              type="text"
              maxLength={120}
              value={bankDetails.bankName}
              onChange={(event) => setBankDetails((current) => ({ ...current, bankName: event.target.value }))}
              placeholder="Bank Name"
              autoComplete="organization"
              required
            />
          </label>
          <label className="budget-form-field">
            <span>Account Holder Name</span>
            <input
              className="budget-text-input"
              type="text"
              maxLength={120}
              value={bankDetails.accountHolderName}
              onChange={(event) => setBankDetails((current) => ({ ...current, accountHolderName: event.target.value }))}
              placeholder="Account Holder Name"
              autoComplete="name"
              required
            />
          </label>
          <label className="budget-form-field">
            <span>Account Number</span>
            <input
              className="budget-text-input"
              type="text"
              inputMode="numeric"
              minLength={6}
              maxLength={34}
              pattern="[0-9]{6,34}"
              value={bankDetails.accountNumber}
              onChange={(event) => setBankDetails((current) => ({ ...current, accountNumber: event.target.value }))}
              placeholder={budget?.bankDetails?.hasAccountNumber
                ? `Saved ${budget.bankDetails.maskedAccountNumber} · leave blank to keep`
                : 'Account Number'}
              autoComplete="off"
              required={!budget?.bankDetails?.hasAccountNumber}
            />
            {budget?.bankDetails?.hasAccountNumber && <small className="budget-field-hint">Leave blank to keep the saved account number.</small>}
          </label>
          <label className="budget-form-field">
            <span>IFSC Code</span>
            <input
              className="budget-text-input"
              type="text"
              minLength={11}
              maxLength={11}
              pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}"
              value={bankDetails.ifscCode}
              onChange={(event) => setBankDetails((current) => ({ ...current, ifscCode: event.target.value.toUpperCase() }))}
              placeholder="IFSC Code"
              autoComplete="off"
              required
            />
          </label>
        </BudgetModal>
      )}

      {showAllocationModal && (
        <BudgetModal
          title={selectedCategory ? selectedCategory.label : 'Assign Budget'}
          description={selectedCategory
            ? 'Enter the amount to assign to this category.'
            : 'Choose one category to assign or update its budget.'}
          onClose={() => setShowAllocationModal(false)}
          onSubmit={saveAllocation}
          submitLabel={selectedCategory ? `Save ${selectedCategory.label}` : ''}
          cancelLabel="Close"
          submitDisabled={selectedCategory !== null && !isAllocationAmountValid}
          isSaving={isSaving}
        >
          {saveError && <p className="budget-error" role="alert">{saveError}</p>}
          {selectedCategory ? (
            <>
              <button
                type="button"
                className="budget-allocation-back"
                onClick={() => {
                  setAllocationCategoryKey(null)
                  setAllocationAmount('')
                  setSaveError('')
                }}
                disabled={isSaving}
              >
                ← All categories
              </button>
              <div className="budget-allocation-limit">
                Available for this category: <strong>{summaryValue(allocationLimitCents)}</strong>
              </div>
              <label className="budget-form-field">
                <span>{selectedCategory.label} amount</span>
                <div className="budget-amount-input">
                  <span>₹</span>
                  <input
                    type="number"
                    min="0"
                    max={allocationLimitCents / 100}
                    step="0.01"
                    value={allocationAmount}
                    onChange={(event) => setAllocationAmount(event.target.value)}
                    placeholder="Enter amount"
                    required
                  />
                </div>
                <small className="budget-field-hint">Enter 0 to remove this category assignment.</small>
              </label>
              {allocationAmount && !isAllocationAmountValid && (
                <p className="budget-error" role="alert">Amount cannot exceed the available Company Budget.</p>
              )}
            </>
          ) : (
            <div className="budget-category-options">
              {budgetCategories.map((category) => {
                const isAssigned = budget?.assignedCategories?.[category.key] === true
                return (
                  <button
                    type="button"
                    className="budget-category-option"
                    key={category.key}
                    onClick={() => selectAllocationCategory(category)}
                  >
                    <span>
                      <strong>{category.label}</strong>
                      <small>{isAssigned ? currency(allocations[category.key]) : 'Not assigned'}</small>
                    </span>
                    <span className="budget-category-option-action">{isAssigned ? 'Edit amount' : 'Assign'} →</span>
                  </button>
                )
              })}
              <div className="budget-allocation-limit">
                Remaining Company Budget: <strong>{summaryValue(Math.max(0, remainingBudgetCents))}</strong>
              </div>
            </div>
          )}
        </BudgetModal>
      )}
    </section>
  )
}
