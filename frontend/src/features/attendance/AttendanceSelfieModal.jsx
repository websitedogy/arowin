import { useEffect, useRef, useState } from 'react'

export default function AttendanceSelfieModal({ action, onClose, onSubmit }) {
  const videoRef = useRef(null)
  const streamRef = useRef(null)
  const [selfie, setSelfie] = useState('')
  const [cameraError, setCameraError] = useState('')
  const [cameraReady, setCameraReady] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState('')

  useEffect(() => {
    let active = true
    const openCamera = async () => {
      if (!navigator.mediaDevices?.getUserMedia) {
        setCameraError('Camera access requires a supported browser on HTTPS or localhost.')
        return
      }

      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 720 } },
          audio: false,
        })
        if (!active) {
          stream.getTracks().forEach((track) => track.stop())
          return
        }
        streamRef.current = stream
        if (videoRef.current) videoRef.current.srcObject = stream
        setCameraReady(true)
        setCameraError('')
      } catch {
        if (active) {
          setCameraReady(false)
          setCameraError('Camera permission is needed to take an attendance selfie.')
        }
      }
    }

    openCamera()
    return () => {
      active = false
      streamRef.current?.getTracks().forEach((track) => track.stop())
      streamRef.current = null
    }
  }, [])

  const captureSelfie = () => {
    const video = videoRef.current
    if (!video || video.readyState < 2) {
      setCameraError('Camera is still starting. Please try again in a moment.')
      return
    }

    const scale = Math.min(1, 960 / video.videoWidth)
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(video.videoWidth * scale)
    canvas.height = Math.round(video.videoHeight * scale)
    const context = canvas.getContext('2d')
    if (!context) {
      setCameraError('Could not capture the selfie. Please try again.')
      return
    }
    context.drawImage(video, 0, 0, canvas.width, canvas.height)
    setSelfie(canvas.toDataURL('image/jpeg', 0.78))
    setCameraError('')
  }

  const handleSubmit = async () => {
    if (!selfie) {
      setCameraError('Take a selfie before submitting attendance.')
      return
    }
    setIsSubmitting(true)
    setSubmitError('')
    try {
      await onSubmit(selfie)
    } catch (error) {
      setSubmitError(error.message)
      setIsSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <section
        className="employee-modal attendance-selfie-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="attendance-selfie-title"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="modal-header">
          <div>
            <h3 id="attendance-selfie-title">{action === 'check-in' ? 'Check in' : 'Check out'} selfie</h3>
            <p className="salary-modal-employee">Take a clear selfie to record your attendance.</p>
          </div>
          <button type="button" className="close-btn" onClick={onClose} disabled={isSubmitting} aria-label="Close selfie">×</button>
        </div>

        <div className="attendance-camera-frame">
          <video className={selfie ? 'is-hidden' : ''} ref={videoRef} autoPlay muted playsInline />
          {selfie && <img src={selfie} alt="Attendance selfie preview" />}
        </div>
        <p className="attendance-selfie-privacy">The selfie is stored privately with this attendance record.</p>
        {(cameraError || submitError) && <p className="budget-error" role="alert">{submitError || cameraError}</p>}
        <div className="modal-actions attendance-camera-actions">
          {selfie ? (
            <button type="button" className="secondary-btn" onClick={() => setSelfie('')} disabled={isSubmitting}>Retake selfie</button>
          ) : (
            <button type="button" className="secondary-btn" onClick={captureSelfie} disabled={!cameraReady}>Take selfie</button>
          )}
          <button type="button" className="primary-btn" onClick={handleSubmit} disabled={!selfie || isSubmitting}>
            {isSubmitting ? 'Saving…' : 'Submit attendance'}
          </button>
        </div>
      </section>
    </div>
  )
}
