import { useState } from 'react'

export default function SalarySetupModal({ employee, onClose, onSubmit }) {
  const [isSaving, setIsSaving] = useState(false)
  const [formError, setFormError] = useState('')

  const handleSubmit = async (event) => {
    event.preventDefault()
    const formData = new FormData(event.currentTarget)
    setIsSaving(true)
    setFormError('')

    try {
      await onSubmit({
        employee,
        joiningDate: formData.get('joiningDate'),
        salaryAmount: formData.get('salaryAmount'),
      })
    } catch (error) {
      setFormError(error.message)
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <section
        className="employee-modal salary-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="salary-modal-title"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="modal-header">
          <div>
            <h3 id="salary-modal-title">Salary Setup</h3>
            <p className="salary-modal-employee">{employee.fullName} · {employee.employeeId}</p>
          </div>
          <button type="button" className="close-btn" onClick={onClose} aria-label="Close salary setup">×</button>
        </div>

        <form className="employee-form" onSubmit={handleSubmit}>
          <div className="salary-form-fields">
            <label>
              <span>Joining Date</span>
              <input
                name="joiningDate"
                type="date"
                defaultValue={employee.joiningDate || ''}
                required
              />
            </label>
            <label>
              <span>Salary Amount (₹ per month)</span>
              <input
                name="salaryAmount"
                type="number"
                min="0.01"
                max="9999999999.99"
                step="0.01"
                defaultValue={employee.salaryAmount ?? ''}
                placeholder="Enter monthly salary"
                required
              />
            </label>
          </div>
          {formError && <p className="feedback error" role="alert">{formError}</p>}
          <div className="modal-actions">
            <button type="button" className="secondary-btn" onClick={onClose} disabled={isSaving}>Cancel</button>
            <button type="submit" className="primary-btn" disabled={isSaving}>
              {isSaving ? 'Saving…' : 'Save Salary'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}
