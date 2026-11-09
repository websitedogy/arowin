import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'

export default function CredentialsDownloadPage({ token }) {
  const [credentialInfo, setCredentialInfo] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [isDownloading, setIsDownloading] = useState(false)
  const [downloaded, setDownloaded] = useState(false)

  useEffect(() => {
    let active = true

    apiRequest(`/credentials/download/${encodeURIComponent(token)}`)
      .then((result) => {
        if (active) setCredentialInfo(result.credential)
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
  }, [token])

  const downloadCredentials = async () => {
    setIsDownloading(true)
    setError('')

    try {
      const result = await apiRequest(`/credentials/download/${encodeURIComponent(token)}`, { method: 'POST' })
      const credentials = result.credentials
      const content = [
        'AROWIN LOGIN CREDENTIALS',
        '=======================',
        '',
        `Name: ${credentials.name}`,
        `Employee ID: ${credentials.employeeId}`,
        `Role: ${credentials.role.replaceAll('_', ' ')}`,
        `Email / Username: ${credentials.email}`,
        `Password: ${credentials.password}`,
        `Login: ${window.location.origin}`,
        '',
        'Keep this file private. Delete it after signing in and change your password if prompted.',
      ].join('\r\n')
      const file = new Blob([content], { type: 'text/plain;charset=utf-8' })
      const fileUrl = URL.createObjectURL(file)
      const link = document.createElement('a')
      link.href = fileUrl
      link.download = `arowin-credentials-${credentials.email.replace(/[^a-z0-9._-]/gi, '-')}.txt`
      document.body.append(link)
      link.click()
      link.remove()
      window.setTimeout(() => URL.revokeObjectURL(fileUrl), 1000)
      setDownloaded(true)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsDownloading(false)
    }
  }

  return (
    <main className="credential-download-page">
      <section className="credential-download-card">
        <div className="credential-brand-mark">A</div>
        <span className="credential-brand-name">AROWIN</span>
        {isLoading ? (
          <p className="credential-page-message">Checking your secure link…</p>
        ) : error && !credentialInfo ? (
          <>
            <h1>This link is unavailable</h1>
            <p className="credential-page-message">{error}</p>
            <p className="credential-page-footnote">Links expire after 24 hours and can only be used once. Ask your Sub Editor to send a new one.</p>
          </>
        ) : (
          <>
            <h1>Your account is ready</h1>
            <p className="credential-page-message">
              Hello {credentialInfo.name}. Download your {credentialInfo.role.replaceAll('_', ' ')} login credentials.
            </p>
            <p className="credential-page-footnote">This link expires at {credentialInfo.expiresAt} UTC. Downloading consumes the link, so keep the file private.</p>
            {error && <p className="credential-page-error" role="alert">{error}</p>}
            {downloaded ? (
              <p className="credential-page-success" role="status">Credentials downloaded. Keep them private and sign in to your AROWIN workspace.</p>
            ) : (
              <button type="button" className="credential-download-button" onClick={downloadCredentials} disabled={isDownloading}>
                {isDownloading ? 'Preparing download…' : 'Download credentials'}
              </button>
            )}
          </>
        )}
      </section>
    </main>
  )
}
