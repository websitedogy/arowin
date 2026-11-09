import { useEffect, useRef } from 'react'
import Icon from './Icon'

export default function RoleProfileMenu({
  name,
  role,
  avatar,
  actionLabel,
  actionIcon,
  actionPath,
  isOpen,
  onOpenChange,
  onNavigate,
  onLogout,
  onOpen,
}) {
  const menuRef = useRef(null)

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
            className="role-profile-signout"
            onClick={onLogout}
          >
            <Icon name="logout" size={17} />
            <span>Sign out</span>
          </button>
        </div>
      )}
    </div>
  )
}
