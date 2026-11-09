import { useEffect, useRef, useState } from 'react'
import { apiRequest } from '../../api'

export default function LoginPage({ onLogin, backendError }) {
  const [formData, setFormData] = useState({
    username: '',
    password: '',
  })
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [installPrompt, setInstallPrompt] = useState(null)
  const [isInstalled, setIsInstalled] = useState(() => (
    window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true
  ))
  const [showInstallHelp, setShowInstallHelp] = useState(false)
  const submissionInProgress = useRef(false)
  const installationRequested = new URLSearchParams(window.location.search).get('install') === '1'
  const [feedback, setFeedback] = useState({
    type: backendError ? 'error' : '',
    message: backendError || '',
  })

  useEffect(() => {
    const handleInstallAvailable = (event) => {
      event.preventDefault()
      setInstallPrompt(event)
    }
    const handleInstalled = () => {
      setIsInstalled(true)
      setInstallPrompt(null)
      setShowInstallHelp(false)
    }

    window.addEventListener('beforeinstallprompt', handleInstallAvailable)
    window.addEventListener('appinstalled', handleInstalled)
    return () => {
      window.removeEventListener('beforeinstallprompt', handleInstallAvailable)
      window.removeEventListener('appinstalled', handleInstalled)
    }
  }, [])

  const installApp = async () => {
    if (!installPrompt) {
      setShowInstallHelp(true)
      return
    }

    try {
      await installPrompt.prompt()
      const choice = await installPrompt.userChoice
      if (choice.outcome !== 'accepted') setShowInstallHelp(true)
    } catch (error) {
      console.error('AROWIN installation prompt failed.', error)
      setShowInstallHelp(true)
    } finally {
      setInstallPrompt(null)
    }
  }

  const handleChange = (event) => {
    const { name, value } = event.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (submissionInProgress.current) return

    if (!formData.username || !formData.password) {
      setFeedback({
        type: 'error',
        message: 'Email or Employee ID and password are required before sign in.',
      })
      return
    }

    submissionInProgress.current = true
    setIsSubmitting(true)

    try {
      const result = await apiRequest('/auth/login', {
        method: 'POST',
        body: formData,
      })

      onLogin(result.user)
      setFeedback({
        type: 'success',
        message: `Welcome back, ${result.user.name}. Redirecting to dashboard.`,
      })
    } catch (error) {
      setFeedback({
        type: 'error',
        message:
          error.message ||
          'Connection to the PHP backend failed. Start the API server on localhost:8000 first.',
      })
    } finally {
      submissionInProgress.current = false
      setIsSubmitting(false)
    }
  }

  return (
    <div className="app-shell">
      <div className="login-layout">
        <main className="login-panel">
          <div className="login-card">
            <div className="login-brand" aria-label="AROWIN">
              <span className="login-brand-mark">A</span>
              <span>AROWIN</span>
            </div>
            <div className="card-header">
              <h1>Sign in to your account</h1>
            </div>

            {installationRequested && (
              <section className="login-install" aria-label="Install AROWIN">
                <div>
                  <strong>{isInstalled ? 'AROWIN is installed' : 'Get the AROWIN app'}</strong>
                  <p>{isInstalled
                    ? 'Open AROWIN from your home screen or apps list.'
                    : 'Install AROWIN on this device for quick access to your dashboard.'}</p>
                </div>
                {!isInstalled && (
                  <button type="button" className="login-install-button" onClick={installApp}>
                    Install app
                  </button>
                )}
                {showInstallHelp && !isInstalled && (
                  <p className="login-install-help" role="status">
                    Open your browser menu and choose “Install app” or “Add to Home Screen”. On iPhone or iPad, use Share, then “Add to Home Screen”.
                  </p>
                )}
              </section>
            )}

            <form className="login-form" onSubmit={handleSubmit}>
              <label>
                <span>Email or Employee ID</span>
                <input
                  type="text"
                  name="username"
                  value={formData.username}
                  onChange={handleChange}
                  placeholder="Enter your email or Employee ID"
                  autoComplete="username"
                  required
                />
              </label>

              <label>
                <span>Password</span>
                <span className="password-input-wrap">
                  <input
                    type={showPassword ? 'text' : 'password'}
                    name="password"
                    value={formData.password}
                    onChange={handleChange}
                    placeholder="Enter your password"
                    autoComplete="current-password"
                    required
                  />
                  <button
                    type="button"
                    className="password-visibility-toggle"
                    onClick={() => setShowPassword((visible) => !visible)}
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                    aria-pressed={showPassword}
                  >
                    {showPassword ? (
                      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M3 3l18 18" />
                        <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                        <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c5.5 0 9 7 9 7a15.7 15.7 0 0 1-3.1 3.8" />
                        <path d="M6.2 6.2C3.9 7.7 3 12 3 12s3.5 7 9 7a10.5 10.5 0 0 0 4-.8" />
                      </svg>
                    ) : (
                      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7Z" />
                        <circle cx="12" cy="12" r="3" />
                      </svg>
                    )}
                  </button>
                </span>
              </label>

              <button type="submit" disabled={isSubmitting}>
                {isSubmitting ? 'Signing in...' : 'Sign in'}
              </button>
            </form>

            {feedback.message && (
              <div className={`feedback ${feedback.type}`} role={feedback.type === 'error' ? 'alert' : 'status'}>
                {feedback.message}
              </div>
            )}
          </div>
        </main>
      </div>
    </div>
  )
}
