import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import { formatUsdt } from '../../utils/formatters'

export default function VerifiedSaleSubmissionModal({ user, mode = 'unlock', onClose, onSubmitted }) {
  const isEmergencySale = mode === 'emergency'
  const [customerId, setCustomerId] = useState('')
  const [customer, setCustomer] = useState(null)
  const [lookupError, setLookupError] = useState('')
  const [isLookingUp, setIsLookingUp] = useState(false)
  const [otp, setOtp] = useState('')
  const [isOtpSent, setIsOtpSent] = useState(false)
  const [isOtpVerified, setIsOtpVerified] = useState(false)
  const [isSendingOtp, setIsSendingOtp] = useState(false)
  const [isVerifyingOtp, setIsVerifyingOtp] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [feedback, setFeedback] = useState({ type: '', message: '' })

  useEffect(() => {
    const lookupId = customerId.trim()
    if (!lookupId) {
      setCustomer(null)
      setLookupError('')
      setIsLookingUp(false)
      return undefined
    }

    let active = true
    const timeoutId = window.setTimeout(() => {
      setIsLookingUp(true)
      setLookupError('')
      apiRequest(isEmergencySale ? '/sales/emergency/me/customer' : '/sales/unlocks/customer', {
        method: 'POST',
        body: { customerId: lookupId },
      })
        .then((result) => {
          if (active) setCustomer(result.customer)
        })
        .catch((error) => {
          if (active) {
            setCustomer(null)
            setLookupError(error.message)
          }
        })
        .finally(() => {
          if (active) setIsLookingUp(false)
        })
    }, 350)

    return () => {
      active = false
      window.clearTimeout(timeoutId)
    }
  }, [customerId, isEmergencySale])

  const close = () => {
    setCustomerId('')
    setCustomer(null)
    setLookupError('')
    setOtp('')
    setIsOtpSent(false)
    setIsOtpVerified(false)
    setFeedback({ type: '', message: '' })
    onClose()
  }

  const sendOtp = async () => {
    setIsSendingOtp(true)
    setOtp('')
    setFeedback({ type: '', message: '' })
    try {
      const result = await apiRequest('/sales/unlock-otp/send', { method: 'POST', body: {} })
      setIsOtpSent(true)
      setIsOtpVerified(false)
      setFeedback({ type: 'success', message: result.message })
    } catch (error) {
      setFeedback({ type: 'error', message: error.message })
    } finally {
      setIsSendingOtp(false)
    }
  }

  const verifyOtp = async () => {
    setIsVerifyingOtp(true)
    setFeedback({ type: '', message: '' })
    try {
      const result = await apiRequest('/sales/unlock-otp/verify', {
        method: 'POST',
        body: { otp },
      })
      setIsOtpVerified(true)
      setFeedback({ type: 'success', message: result.message })
    } catch (error) {
      setFeedback({ type: 'error', message: error.message })
    } finally {
      setIsVerifyingOtp(false)
    }
  }

  const submitSale = async (event) => {
    event.preventDefault()
    setFeedback({ type: '', message: '' })
    setIsSubmitting(true)
    try {
      const endpoint = isEmergencySale ? '/sales/emergency/me/submissions' : '/sales/unlocks'
      const result = await apiRequest(endpoint, {
        method: 'POST',
        body: { customerId },
      })
      setIsOtpVerified(false)
      setFeedback({
        type: 'success',
        message: isEmergencySale
          ? 'Emergency sale submitted for Sub Editor review. Approval counts toward completing your assigned amount.'
          : customer.isEmployeeCredited
            ? 'Sale submitted for Sub Editor review.'
            : 'Request submitted for review. It will not count toward your sales or incentives.',
      })
      onSubmitted?.(result.sale)
    } catch (error) {
      setFeedback({ type: 'error', message: error.message })
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop assignment-modal-backdrop" onClick={close}>
      <section
        className="assignment-modal unlock-token-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="unlock-token-modal-title"
        onClick={(event) => event.stopPropagation()}
      >
        <header className="assignment-modal-heading">
          <div>
            <span className="mini-label">{isEmergencySale ? 'Emergency Sales' : 'Arowin Global lookup'}</span>
            <h2 id="unlock-token-modal-title">{isEmergencySale ? 'Submit Emergency Sale' : 'Customer Purchase'}</h2>
            <p>
              {isEmergencySale
                ? 'Look up the customer and submit their activated package toward your assigned emergency amount.'
                : 'Search the customer in Arowin Global to load their name and activated package.'}
            </p>
          </div>
          <button type="button" className="close-btn" aria-label="Close" onClick={close}>×</button>
        </header>

        <form className="unlock-token-form" onSubmit={submitSale}>
          <label>
            <span>Customer ID</span>
            <input
              type="text"
              value={customerId}
              onChange={(event) => {
                setCustomerId(event.target.value)
                setCustomer(null)
                setLookupError('')
                setOtp('')
                setIsOtpSent(false)
                setIsOtpVerified(false)
                setFeedback({ type: '', message: '' })
              }}
              placeholder="Enter Arowin Global customer ID"
              maxLength={60}
              required
            />
          </label>

          <label>
            <span>Customer Name</span>
            <input
              type="text"
              value={customer?.customerName || ''}
              placeholder={isLookingUp ? 'Looking up customer…' : 'Available after customer lookup'}
              readOnly
            />
          </label>

          <label>
            <span>Activated Package</span>
            <input
              type="text"
              value={customer?.packageAmount ? formatUsdt(customer.packageAmount) : ''}
              placeholder={customer ? 'No package selected' : 'Loaded from Arowin Global'}
              readOnly
            />
          </label>

          <p className={`unlock-integration-notice${customer && (customer.activationStatus === 'pending' || !customer.canSubmit || (isEmergencySale && !customer.isEmployeeCredited)) ? ' is-pending' : customer ? ' is-connected' : ''}`} role={lookupError ? 'alert' : 'status'}>
            {lookupError || (customer
              ? isEmergencySale && !customer.isEmployeeCredited
                ? 'This purchase is not credited to your employee account and cannot complete emergency sales.'
                : customer.message
              : isLookingUp
                ? 'Looking up active customer and package…'
                : 'Enter a customer ID to automatically load the active customer and purchased package.')}
          </p>

          <label>
            <span>Employee email</span>
            <input type="email" value={user?.email || ''} readOnly autoComplete="email" />
          </label>

          <div className="unlock-token-otp-row">
            <label>
              <span>Email OTP</span>
              <input
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                placeholder="Enter 6-digit OTP"
                value={otp}
                onChange={(event) => setOtp(event.target.value.replace(/\D/g, '').slice(0, 6))}
                disabled={!isOtpSent || isOtpVerified}
              />
            </label>
            <button
              type="button"
              className="unlock-send-otp-button"
              onClick={verifyOtp}
              disabled={!isOtpSent || otp.length !== 6 || isVerifyingOtp || isOtpVerified}
            >
              {isVerifyingOtp ? 'Verifying…' : isOtpVerified ? 'Verified' : 'Verify OTP'}
            </button>
          </div>
          <button
            type="button"
            className="unlock-send-otp-button"
            onClick={sendOtp}
            disabled={!user?.email || !customer || isLookingUp || isSendingOtp || isOtpVerified}
          >
            {isSendingOtp ? 'Sending…' : isOtpSent ? 'Resend OTP' : 'Send OTP'}
          </button>

          {feedback.message && (
            <p className={`unlock-otp-feedback ${feedback.type}`} role={feedback.type === 'error' ? 'alert' : 'status'}>
              {feedback.message}
            </p>
          )}
          <button
            type="submit"
            className="unlock-confirm-token-button"
            disabled={!isOtpVerified || !customer?.canSubmit || customer.customerId !== customerId.trim() || (isEmergencySale && !customer.isEmployeeCredited) || isSubmitting}
          >
            <Icon name="plus" size={17} /> {isSubmitting ? 'Submitting…' : 'Submit for Approval'}
          </button>
        </form>
      </section>
    </div>
  )
}
