import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'

const steps = [
  { title: 'Profile details', description: 'Confirm your contact information.', icon: 'user' },
  { title: 'Bank details', description: 'Add the account for your payments.', icon: 'coins' },
  { title: 'Identity documents', description: 'Provide Aadhaar and PAN details.', icon: 'file' },
  { title: 'Office details', description: 'Review your assigned work location.', icon: 'home' },
]

const emptyBank = { bankName: '', accountHolderName: '', accountNumber: '', ifscCode: '', branchName: '' }
const emptyIdentity = { aadhaarNumber: '', panNumber: '' }
const allowedDocumentTypes = ['application/pdf', 'image/jpeg', 'image/png']

function documentMessage(file) {
  if (!file) return ''
  if (!allowedDocumentTypes.includes(file.type)) return 'Choose a JPEG, PNG, or PDF file.'
  if (file.size < 1 || file.size > 5_000_000) return 'Each document must be smaller than 5 MB.'
  return ''
}

function formatJoiningDate(value) {
  if (!value) return 'Not configured'
  return new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Kolkata',
  }).format(new Date(`${value}T00:00:00+05:30`))
}

function formatRole(role) {
  if (role === 'team_leader') return 'Team Leader'
  if (role === 'sub_editor') return 'Sub Editor'
  return 'Telecaller'
}

export default function EmployeeKycPage({ user }) {
  const [step, setStep] = useState(0)
  const [profile, setProfile] = useState({ fullName: user.name || '', mobile: user.mobile || '', email: user.email || '' })
  const [bank, setBank] = useState({ ...emptyBank, accountHolderName: user.name || '' })
  const [identity, setIdentity] = useState(emptyIdentity)
  const [documents, setDocuments] = useState({ aadhaar: null, pan: null })
  const [savedDocumentNames, setSavedDocumentNames] = useState({ aadhaar: '', pan: '' })
  const [office, setOffice] = useState(null)
  const [status, setStatus] = useState('not_submitted')
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  useEffect(() => {
    let active = true
    apiRequest('/employee-kyc')
      .then(({ kyc }) => {
        if (!active) return
        setProfile(kyc.profile)
        if (kyc.bank) setBank(kyc.bank)
        if (kyc.identity) {
          setIdentity({ aadhaarNumber: kyc.identity.aadhaarNumber, panNumber: kyc.identity.panNumber })
          setSavedDocumentNames({
            aadhaar: kyc.identity.aadhaarDocumentName || '',
            pan: kyc.identity.panDocumentName || '',
          })
        }
        setOffice(kyc.office)
        setStatus(kyc.status)
      })
      .catch((requestError) => {
        if (active) setError(requestError.message)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })
    return () => {
      active = false
    }
  }, [])

  const progress = useMemo(() => Math.round(((step + 1) / steps.length) * 100), [step])

  const validateStep = (targetStep = step) => {
    if (targetStep === 0) {
      if (!profile.fullName.trim()) return 'Enter your full name.'
      if (!/^[0-9+().\-\s]{7,40}$/.test(profile.mobile.trim())) return 'Enter a valid mobile number.'
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(profile.email.trim())) return 'Enter a valid email address.'
    }
    if (targetStep === 1) {
      if (Object.values(bank).some((value) => !value.trim())) return 'Complete all bank details to continue.'
      if (!/^[0-9]{8,34}$/.test(bank.accountNumber.replace(/\s+/g, ''))) return 'Enter a valid bank account number.'
      if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(bank.ifscCode.trim().toUpperCase())) return 'Enter a valid IFSC code.'
    }
    if (targetStep === 2) {
      if (!/^[2-9][0-9]{11}$/.test(identity.aadhaarNumber.replace(/\s+/g, ''))) return 'Enter a valid 12-digit Aadhaar number.'
      if (!/^[A-Z]{5}[0-9]{4}[A-Z]$/.test(identity.panNumber.trim().toUpperCase())) return 'Enter a valid PAN number.'
      for (const type of ['aadhaar', 'pan']) {
        const message = documentMessage(documents[type])
        if (message) return `${type === 'aadhaar' ? 'Aadhaar' : 'PAN'} document: ${message}`
        if (!documents[type] && !savedDocumentNames[type]) return `Upload your ${type === 'aadhaar' ? 'Aadhaar' : 'PAN'} document.`
      }
    }
    return ''
  }

  const goToStep = (nextStep) => {
    setError('')
    setNotice('')
    if (nextStep > step) {
      const validationMessage = validateStep()
      if (validationMessage) {
        setError(validationMessage)
        return
      }
    }
    setStep(nextStep)
  }

  const setDocument = (type, file) => {
    setError('')
    setDocuments((current) => ({ ...current, [type]: file }))
  }

  const submitKyc = async (event) => {
    event.preventDefault()
    setError('')
    setNotice('')
    const validationMessage = [0, 1, 2].map((targetStep) => validateStep(targetStep)).find(Boolean) || ''
    if (validationMessage) {
      const invalidStep = [0, 1, 2].find((targetStep) => validateStep(targetStep))
      setStep(invalidStep ?? 0)
      setError(validationMessage)
      return
    }

    setIsSubmitting(true)
    const payload = new FormData()
    Object.entries(profile).forEach(([key, value]) => payload.append(key, value.trim()))
    Object.entries(bank).forEach(([key, value]) => payload.append(key, key === 'ifscCode' ? value.trim().toUpperCase() : value.trim()))
    Object.entries(identity).forEach(([key, value]) => payload.append(key, key === 'panNumber' ? value.trim().toUpperCase() : value.replace(/\s+/g, '')))
    if (documents.aadhaar) payload.append('aadhaar_document', documents.aadhaar)
    if (documents.pan) payload.append('pan_document', documents.pan)

    try {
      const result = await apiRequest('/employee-kyc', { method: 'POST', body: payload })
      setStatus(result.kyc.status)
      setSavedDocumentNames({
        aadhaar: result.kyc.identity.aadhaarDocumentName || '',
        pan: result.kyc.identity.panDocumentName || '',
      })
      setDocuments({ aadhaar: null, pan: null })
      setNotice(result.message)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const activeStep = steps[step]

  return (
    <section className="employee-kyc-page">
      <header className="employee-kyc-heading">
        <div>
          <span className="mini-label">Employee Services</span>
          <h1>Employee KYC</h1>
          <p>Complete your profile, payment, and identity details securely.</p>
        </div>
        {status === 'submitted' && (
          <span className="employee-kyc-status"><i /> Submitted</span>
        )}
      </header>

      {error && <p className="employee-kyc-feedback is-error" role="alert">{error}</p>}
      {notice && <p className="employee-kyc-feedback is-success" role="status">{notice}</p>}

      {isLoading ? (
        <div className="employee-kyc-loading" role="status"><span className="employee-kyc-spinner" /> Loading your saved KYC details…</div>
      ) : (
        <div className="employee-kyc-layout">
          <aside className="employee-kyc-stepper" aria-label="KYC steps">
            <div className="employee-kyc-stepper-heading">
              <span>YOUR KYC</span>
              <strong>Step {step + 1} of {steps.length}</strong>
            </div>
            <div className="employee-kyc-progress"><span style={{ width: `${progress}%` }} /></div>
            <nav>
              {steps.map((item, index) => (
                <button
                  type="button"
                  key={item.title}
                  className={`employee-kyc-step${index === step ? ' is-active' : ''}${index < step ? ' is-complete' : ''}`}
                  aria-current={index === step ? 'step' : undefined}
                  onClick={() => index < step && goToStep(index)}
                  disabled={index > step}
                >
                  <span className="employee-kyc-step-icon">{index < step ? <Icon name="check" size={17} /> : <Icon name={item.icon} size={17} />}</span>
                  <span><strong>{item.title}</strong><small>{item.description}</small></span>
                </button>
              ))}
            </nav>
            <div className="employee-kyc-security">
              <Icon name="settings" size={17} />
              <span><strong>Private & secure</strong><small>Identity and bank numbers are encrypted. Documents are stored privately.</small></span>
            </div>
          </aside>

          <form className="employee-kyc-card" onSubmit={submitKyc}>
            <header className="employee-kyc-card-heading">
              <span className="employee-kyc-card-icon"><Icon name={activeStep.icon} size={21} /></span>
              <div><span>STEP {String(step + 1).padStart(2, '0')}</span><h2>{activeStep.title}</h2><p>{activeStep.description}</p></div>
            </header>

            {step === 0 && (
              <div className="employee-kyc-fields">
                <label><span>Full name</span><input autoComplete="name" value={profile.fullName} onChange={(event) => setProfile({ ...profile, fullName: event.target.value })} maxLength={120} required /></label>
                <label><span>Mobile number</span><input type="tel" autoComplete="tel" value={profile.mobile} onChange={(event) => setProfile({ ...profile, mobile: event.target.value })} maxLength={40} required /></label>
                <label className="is-full"><span>Email address</span><input type="email" autoComplete="email" value={profile.email} onChange={(event) => setProfile({ ...profile, email: event.target.value })} maxLength={180} required /></label>
                <p className="employee-kyc-hint is-full">These details were filled from your employee account. Changes here apply to your KYC submission.</p>
              </div>
            )}

            {step === 1 && (
              <div className="employee-kyc-fields">
                <label><span>Account holder name</span><input autoComplete="name" value={bank.accountHolderName} onChange={(event) => setBank({ ...bank, accountHolderName: event.target.value })} maxLength={120} required /></label>
                <label><span>Bank name</span><input autoComplete="organization" value={bank.bankName} onChange={(event) => setBank({ ...bank, bankName: event.target.value })} maxLength={120} required placeholder="e.g. State Bank of India" /></label>
                <label><span>Account number</span><input inputMode="numeric" autoComplete="off" value={bank.accountNumber} onChange={(event) => setBank({ ...bank, accountNumber: event.target.value.replace(/[^0-9\s]/g, '') })} maxLength={40} required /></label>
                <label><span>IFSC code</span><input autoComplete="off" value={bank.ifscCode} onChange={(event) => setBank({ ...bank, ifscCode: event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '') })} maxLength={11} required placeholder="e.g. SBIN0001234" /></label>
                <label className="is-full"><span>Branch name</span><input autoComplete="off" value={bank.branchName} onChange={(event) => setBank({ ...bank, branchName: event.target.value })} maxLength={120} required /></label>
              </div>
            )}

            {step === 2 && (
              <div className="employee-kyc-fields">
                <label><span>Aadhaar card number</span><input inputMode="numeric" autoComplete="off" value={identity.aadhaarNumber} onChange={(event) => setIdentity({ ...identity, aadhaarNumber: event.target.value.replace(/[^0-9\s]/g, '').slice(0, 14) })} maxLength={14} required placeholder="12-digit Aadhaar number" /></label>
                <label><span>PAN card number</span><input autoComplete="off" value={identity.panNumber} onChange={(event) => setIdentity({ ...identity, panNumber: event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10) })} maxLength={10} required placeholder="ABCDE1234F" /></label>
                {[
                  { type: 'aadhaar', title: 'Aadhaar document', name: savedDocumentNames.aadhaar },
                  { type: 'pan', title: 'PAN document', name: savedDocumentNames.pan },
                ].map((document) => (
                  <label className="employee-kyc-upload is-full" key={document.type}>
                    <span>{document.title}</span>
                    <span className={`employee-kyc-file${documents[document.type] || document.name ? ' has-file' : ''}`}>
                      <Icon name="file" size={19} />
                      <span><strong>{documents[document.type]?.name || document.name || 'Upload a clear photo or PDF'}</strong><small>JPEG, PNG, or PDF · Up to 5 MB</small></span>
                      <span className="employee-kyc-choose">Choose file</span>
                      <input type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" onChange={(event) => setDocument(document.type, event.target.files?.[0] || null)} />
                    </span>
                  </label>
                ))}
                <p className="employee-kyc-hint is-full">Enter the number exactly as printed. For your privacy, KYC details are visible only to you in this flow.</p>
              </div>
            )}

            {step === 3 && (
              <div className="employee-kyc-office">
                <div className="employee-kyc-office-intro"><Icon name="home" size={20} /><span><strong>Assigned office details</strong><small>Read-only information from your employee and office records.</small></span></div>
                <div className="employee-kyc-office-grid">
                  <div><span>Employee ID</span><strong>{office?.employeeId || user.employeeId || 'Not available'}</strong></div>
                  <div><span>Role</span><strong>{office?.role || formatRole(user.role)}</strong></div>
                  <div><span>Joining date</span><strong>{formatJoiningDate(office?.joiningDate)}</strong></div>
                  <div className="is-full"><span>Office address</span><strong>{office?.officeAddress || 'Office address has not been configured yet.'}</strong></div>
                </div>
                <div className="employee-kyc-review">
                  <strong>Before submitting</strong>
                  <span>Check your bank details, identity numbers, and both uploaded documents. You can reopen this form later to update your KYC.</span>
                </div>
              </div>
            )}

            <footer className="employee-kyc-actions">
              <button type="button" className="employee-kyc-back" onClick={() => goToStep(Math.max(0, step - 1))} disabled={step === 0 || isSubmitting}>Back</button>
              <span>{progress}% completed</span>
              {step < steps.length - 1 ? (
                <button type="button" className="employee-kyc-next" onClick={() => goToStep(step + 1)}>Continue <Icon name="arrow" size={17} /></button>
              ) : (
                <button type="submit" className="employee-kyc-next" disabled={isSubmitting}>
                  {isSubmitting ? 'Submitting…' : status === 'submitted' ? 'Update KYC' : 'Submit KYC'} <Icon name="arrow" size={17} />
                </button>
              )}
            </footer>
          </form>
        </div>
      )}
    </section>
  )
}
