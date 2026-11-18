import { useEffect, useRef, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import { formatDateTime } from '../../utils/formatters'

export default function JoiningLetterPage() {
  const [joiningLetter, setJoiningLetter] = useState(null)
  const [selectedFile, setSelectedFile] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isUploading, setIsUploading] = useState(false)
  const [isDownloading, setIsDownloading] = useState(false)
  const [isLoadingPreview, setIsLoadingPreview] = useState(false)
  const [showViewer, setShowViewer] = useState(false)
  const [previewUrl, setPreviewUrl] = useState('')
  const [previewError, setPreviewError] = useState('')
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const fileInput = useRef(null)
  const previewRequestId = useRef(0)

  useEffect(() => {
    if (!previewUrl) return undefined
    return () => URL.revokeObjectURL(previewUrl)
  }, [previewUrl])

  useEffect(() => {
    let active = true
    apiRequest('/telecaller/joining-letter')
      .then((result) => {
        if (active) setJoiningLetter(result.joiningLetter)
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

  const uploadJoiningLetter = async (event) => {
    event.preventDefault()
    if (!selectedFile) {
      setError('Choose your joining letter to upload.')
      return
    }
    setError('')
    setMessage('')
    setIsUploading(true)
    const body = new FormData()
    body.append('joining_letter', selectedFile)
    try {
      const result = await apiRequest('/telecaller/joining-letter', { method: 'POST', body })
      setJoiningLetter(result.joiningLetter)
      setSelectedFile(null)
      if (fileInput.current) fileInput.current.value = ''
      setMessage(result.message)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsUploading(false)
    }
  }

  const downloadJoiningLetter = async () => {
    setError('')
    setIsDownloading(true)
    try {
      const file = await apiRequest('/telecaller/joining-letter/file', { responseType: 'blob' })
      const url = URL.createObjectURL(file)
      const link = document.createElement('a')
      link.href = url
      link.download = joiningLetter.name
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.setTimeout(() => URL.revokeObjectURL(url), 1000)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsDownloading(false)
    }
  }

  const viewJoiningLetter = async () => {
    const requestId = previewRequestId.current + 1
    previewRequestId.current = requestId
    setShowViewer(true)
    setPreviewError('')
    setPreviewUrl('')
    setIsLoadingPreview(true)
    try {
      const file = await apiRequest('/telecaller/joining-letter/file', { responseType: 'blob' })
      if (requestId === previewRequestId.current) setPreviewUrl(URL.createObjectURL(file))
    } catch (requestError) {
      if (requestId === previewRequestId.current) setPreviewError(requestError.message)
    } finally {
      if (requestId === previewRequestId.current) setIsLoadingPreview(false)
    }
  }

  const closeViewer = () => {
    previewRequestId.current += 1
    setShowViewer(false)
    setPreviewUrl('')
    setIsLoadingPreview(false)
  }

  return (
    <section className="employee-profile-page joining-letter-page">
      <header className="employee-profile-heading">
        <div>
          <span className="mini-label">Telecaller Workspace</span>
          <h1>Joining Letter</h1>
          <p>Upload and keep a copy of your joining letter in your profile.</p>
        </div>
      </header>

      {error && <p className="dashboard-data-error" role="alert">{error}</p>}
      {message && <p className="joining-letter-message" role="status">{message}</p>}

      <article className="employee-profile-card joining-letter-card">
        <div className="joining-letter-content">
          {isLoading ? (
            <p className="employee-directory-loading" role="status">Checking your joining letter…</p>
          ) : joiningLetter ? (
            <div className="joining-letter-current">
              <span className="joining-letter-icon"><Icon name="file" size={22} /></span>
              <div className="joining-letter-file-copy">
                <strong>Your joining letter is saved</strong>
                <span title={joiningLetter.name}>{joiningLetter.name}</span>
                <small>Uploaded {formatDateTime(joiningLetter.uploadedAt)}</small>
              </div>
              <div className="joining-letter-actions">
                <button type="button" className="budget-secondary-button" onClick={viewJoiningLetter} disabled={isLoadingPreview}>
                  {isLoadingPreview ? 'Opening…' : 'View'}
                </button>
                <button type="button" className="budget-secondary-button" onClick={downloadJoiningLetter} disabled={isDownloading}>
                  {isDownloading ? 'Preparing…' : 'Download'}
                </button>
              </div>
            </div>
          ) : (
            <div className="joining-letter-prompt">
              <span className="joining-letter-icon"><Icon name="file" size={22} /></span>
              <div>
                <strong>Please upload your joining letter</strong>
                <p>Upload a PDF, JPG, or PNG file to keep it here in your profile.</p>
              </div>
            </div>
          )}

          {!isLoading && (
            <form className="joining-letter-form" onSubmit={uploadJoiningLetter}>
              <label className="budget-form-field">
                <span>{joiningLetter ? 'Replace joining letter' : 'Choose joining letter'}</span>
                <input
                  ref={fileInput}
                  className="budget-text-input joining-letter-input"
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                  onChange={(event) => {
                    setSelectedFile(event.target.files?.[0] || null)
                    setError('')
                    setMessage('')
                  }}
                  required={!joiningLetter}
                />
              </label>
              {selectedFile && <small className="joining-letter-selected-file">{selectedFile.name}</small>}
              <button type="submit" className="budget-primary-button" disabled={isUploading || !selectedFile}>
                {isUploading ? 'Uploading…' : joiningLetter ? 'Replace Joining Letter' : 'Upload Joining Letter'}
              </button>
            </form>
          )}
        </div>
      </article>
      {showViewer && (
        <div className="joining-letter-viewer-backdrop" role="presentation" onMouseDown={(event) => {
          if (event.target === event.currentTarget) closeViewer()
        }}>
          <section className="joining-letter-viewer" role="dialog" aria-modal="true" aria-labelledby="joining-letter-viewer-title">
            <header className="joining-letter-viewer-heading">
              <div>
                <span className="mini-label">Joining Letter</span>
                <h2 id="joining-letter-viewer-title">{joiningLetter?.name}</h2>
              </div>
              <button type="button" className="budget-modal-close" onClick={closeViewer} aria-label="Close preview">×</button>
            </header>
            <div className="joining-letter-viewer-content">
              {isLoadingPreview ? (
                <p className="employee-directory-loading" role="status">Opening your joining letter…</p>
              ) : previewError ? (
                <p className="dashboard-data-error" role="alert">{previewError}</p>
              ) : joiningLetter?.mimeType === 'application/pdf' ? (
                <iframe src={previewUrl} title="Joining letter preview" />
              ) : (
                <img src={previewUrl} alt="Joining letter preview" />
              )}
            </div>
            <footer className="joining-letter-viewer-footer">
              <button type="button" className="budget-secondary-button" onClick={closeViewer}>Close</button>
              {previewUrl && (
                <button type="button" className="budget-primary-button" onClick={downloadJoiningLetter} disabled={isDownloading}>
                  {isDownloading ? 'Preparing…' : 'Download'}
                </button>
              )}
            </footer>
          </section>
        </div>
      )}
    </section>
  )
}
