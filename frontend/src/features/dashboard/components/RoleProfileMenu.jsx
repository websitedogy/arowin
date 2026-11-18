import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { apiRequest } from '../../../api'
import Icon from './Icon'

const EMPTY_PASSWORD_FORM = {
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
}

export default function RoleProfileMenu({
  name,
  role,
  avatar,
  actionLabel,
  actionIcon,
  actionPath,
  joiningLetterPath,
  leavePath,
  supportPath,
  isOpen,
  onOpenChange,
  onNavigate,
  onLogout,
  onOpen,
}) {
  const menuRef = useRef(null)
  const [isPasswordDialogOpen, setIsPasswordDialogOpen] = useState(false)
  const [passwordForm, setPasswordForm] = useState(EMPTY_PASSWORD_FORM)
  const [passwordError, setPasswordError] = useState('')
  const [passwordUpdated, setPasswordUpdated] = useState(false)
  const [isUpdatingPassword, setIsUpdatingPassword] = useState(false)

  useEffect(() => {
    if (!isOpen) return undefined
    const closeOnOutsideClick = (event) => {
      if (!menuRef.current?.contains(event.target)) onOpenChange(false)
    }
    const closeOnEscape = (event) => {
      if (event.key === 'Escape') onOpenChange(false)
    }
    document.addEventListener('pointerdown', closeOnOutsideClick)
    window.addEventListener('keydown', closeOnEscape)
    return () => {
      document.removeEventListener('pointerdown', closeOnOutsideClick)
      window.removeEventListener('keydown', closeOnEscape)
    }
  }, [isOpen, onOpenChange])

  useEffect(() => {
    if (!isPasswordDialogOpen) return undefined
    const previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = previousBodyOverflow
    }
  }, [isPasswordDialogOpen])

  useEffect(() => {
    if (!isPasswordDialogOpen) return undefined
    const closeOnEscape = (event) => {
      if (event.key === 'Escape' && !isUpdatingPassword) {
        setIsPasswordDialogOpen(false)
        setPasswordForm(EMPTY_PASSWORD_FORM)
        setPasswordError('')
        setPasswordUpdated(false)
      }
    }
    window.addEventListener('keydown', closeOnEscape)
    return () => window.removeEventListener('keydown', closeOnEscape)
  }, [isPasswordDialogOpen, isUpdatingPassword])

  const closePasswordDialog = () => {
    if (isUpdatingPassword) return
    setIsPasswordDialogOpen(false)
    setPasswordForm(EMPTY_PASSWORD_FORM)
    setPasswordError('')
    setPasswordUpdated(false)
  }

  const handlePasswordSubmit = async (event) => {
    event.preventDefault()
    setPasswordError('')

    if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      setPasswordError('The new passwords do not match.')
      return
    }

    setIsUpdatingPassword(true)
    try {
      await apiRequest('/auth/change-password', {
        method: 'POST',
        body: passwordForm,
      })
      setPasswordForm(EMPTY_PASSWORD_FORM)
      setPasswordUpdated(true)
    } catch (requestError) {
      setPasswordError(requestError.message)
    } finally {
      setIsUpdatingPassword(false)
    }
  }

  return (
    <div className="role-profile-menu" ref={menuRef}>
      <button
        type="button"
        className="role-profile-trigger"
        aria-label={`Profile menu for ${name}`}
        aria-haspopup="true"
        aria-expanded={isOpen}
        onClick={() => {
          onOpenChange(!isOpen)
          if (!isOpen) onOpen?.()
        }}
      >
        {avatar}
        <span className="role-profile-copy"><strong>{name}</strong><small>{role}</small></span>
        <Icon name="down" size={15} />
      </button>
      {isOpen && (
        <div className="role-profile-dropdown">
          <button
            type="button"
            onClick={() => {
              onOpenChange(false)
              onNavigate(actionPath)
            }}
          >
            <Icon name={actionIcon} size={17} />
            <span>{actionLabel}</span>
          </button>
          <button
            type="button"
            onClick={() => {
              onOpenChange(false)
              setPasswordForm(EMPTY_PASSWORD_FORM)
              setPasswordError('')
              setPasswordUpdated(false)
              setIsPasswordDialogOpen(true)
            }}
          >
            <Icon name="lock" size={17} />
            <span>Update Password</span>
          </button>
          {joiningLetterPath && (
            <button
              type="button"
              onClick={() => {
                onOpenChange(false)
                onNavigate(joiningLetterPath)
              }}
            >
              <Icon name="file" size={17} />
              <span>Joining Letter</span>
            </button>
          )}
          {leavePath && (
            <button
              type="button"
              onClick={() => {
                onOpenChange(false)
                onNavigate(leavePath)
              }}
            >
              <Icon name="calendar" size={17} />
              <span>Leave Requests</span>
            </button>
          )}
          {supportPath && (
            <button
              type="button"
              onClick={() => {
                onOpenChange(false)
                onNavigate(supportPath)
              }}
            >
              <Icon name="headset" size={17} />
              <span>Support Tickets</span>
            </button>
          )}
          <button
            type="button"
            className="role-profile-signout"
            onClick={onLogout}
          >
            <Icon name="logout" size={17} />
            <span>Sign out</span>
          </button>
        </div>
      )}
      {isPasswordDialogOpen && createPortal(
        <div
          className="password-change-backdrop"
          role="presentation"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) closePasswordDialog()
          }}
        >
          <section
            className="password-change-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="password-change-title"
          >
            <header className="password-change-heading">
              <div>
                <span>Account security</span>
                <h2 id="password-change-title">Update Password</h2>
              </div>
              <button
                type="button"
                className="password-change-close"
                onClick={closePasswordDialog}
                disabled={isUpdatingPassword}
                aria-label="Close password dialog"
              >
                <Icon name="close" size={17} />
              </button>
            </header>
            {passwordError && <p className="password-change-error" role="alert">{passwordError}</p>}
            {passwordUpdated ? (
              <div className="password-change-success">
                <p role="status">Your password has been updated.</p>
                <button type="button" className="password-change-submit" onClick={closePasswordDialog}>Done</button>
              </div>
            ) : (
              <form className="password-change-form" onSubmit={handlePasswordSubmit}>
                <label>
                  <span>Current password</span>
                  <input
                    type="password"
                    name="currentPassword"
                    autoComplete="current-password"
                    value={passwordForm.currentPassword}
                    onChange={(event) => setPasswordForm({ ...passwordForm, currentPassword: event.target.value })}
                    required
                    autoFocus
                  />
                </label>
                <label>
                  <span>New password</span>
                  <input
                    type="password"
                    name="newPassword"
                    autoComplete="new-password"
                    value={passwordForm.newPassword}
                    onChange={(event) => setPasswordForm({ ...passwordForm, newPassword: event.target.value })}
                    minLength={8}
                    maxLength={72}
                    required
                  />
                </label>
                <label>
                  <span>Confirm new password</span>
                  <input
                    type="password"
                    name="confirmPassword"
                    autoComplete="new-password"
                    value={passwordForm.confirmPassword}
                    onChange={(event) => setPasswordForm({ ...passwordForm, confirmPassword: event.target.value })}
                    minLength={8}
                    maxLength={72}
                    required
                  />
                </label>
                <small className="password-change-hint">Use at least 8 characters.</small>
                <button type="submit" className="password-change-submit" disabled={isUpdatingPassword}>
                  {isUpdatingPassword ? 'Updating…' : 'Save new password'}
                </button>
              </form>
            )}
          </section>
        </div>,
        document.body,
      )}
    </div>
  )
}
