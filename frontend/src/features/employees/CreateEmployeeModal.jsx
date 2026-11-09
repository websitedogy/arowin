import { useState } from 'react'

export default function CreateEmployeeModal({ isOpen, initialRole = 'Team Leader', onClose, onSubmit }) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState('')

  if (!isOpen) return null

  const handleSubmit = async (event) => {
    event.preventDefault()
    const form = event.currentTarget
    const formData = new FormData(form)
    setIsSubmitting(true)
    setFormError('')

    const employee = {
      role: formData.get('role'),
      fullName: formData.get('fullName'),
      email: formData.get('email'),
      mobile: formData.get('mobile'),
      dob: formData.get('dob'),
      address: formData.get('address'),
      password: formData.get('password'),
    }

    try {
      await onSubmit(employee)
      form.reset()
      onClose()
    } catch (error) {
      setFormError(error.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className="employee-modal" onClick={(event) => event.stopPropagation()}>
        <div className="modal-header">
          <h3>Create Employee ID</h3>
          <button type="button" className="close-btn" onClick={onClose}>×</button>
        </div>

        <form className="employee-form" onSubmit={handleSubmit}>
          <p className="feedback info employee-id-notice">A unique 6-digit Employee ID will be generated automatically after this account is created.</p>
          <div className="form-grid">
            <label>
              <span>Role</span>
              {initialRole === 'Sub Editor' ? (
                <>
                  <input type="hidden" name="role" value="Sub Editor" />
                  <span className="employee-role-value">Sub Editor</span>
                </>
              ) : (
                <select key={initialRole} name="role" defaultValue={initialRole}>
                  <option value="Team Leader">Team Leader</option>
                  <option value="Telecaller">Telecaller</option>
                </select>
              )}
            </label>

            <label>
              <span>Full Name</span>
              <input name="fullName" type="text" placeholder="Enter full name" required />
            </label>

            <label>
              <span>Gmail / Email</span>
              <input name="email" type="email" placeholder="name@arowin.com" required />
            </label>

            <label>
              <span>Mobile Number</span>
              <input name="mobile" type="tel" placeholder="+91 98765 43210" required />
            </label>

            <label>
              <span>Date of Birth</span>
              <input name="dob" type="date" required />
            </label>

            <label>
              <span>Address</span>
              <input name="address" type="text" placeholder="Enter address" required />
            </label>

            <label>
              <span>Create Password</span>
              <input name="password" type="password" placeholder="Create password" minLength={8} autoComplete="new-password" required />
            </label>
          </div>

          {formError && <p className="feedback error" role="alert">{formError}</p>}

          <div className="modal-actions">
            <button type="button" className="secondary-btn" onClick={onClose} disabled={isSubmitting}>
              Cancel
            </button>
            <button type="submit" className="primary-btn" disabled={isSubmitting}>
              {isSubmitting ? 'Creating…' : 'Submit'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
