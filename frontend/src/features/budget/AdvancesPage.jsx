import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import { formatCurrency, formatDateTime } from '../../utils/formatters'

const paymentModes = [
  { value: 'cash', label: 'Cash' },
  { value: 'online', label: 'Online' },
  { value: 'cod', label: 'Cash on Delivery (COD)' },
  { value: 'net_banking', label: 'Net Banking' },
  { value: 'upi', label: 'UPI' },
  { value: 'card', label: 'Debit or Credit Card' },
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'other', label: 'Other' },
]
const currency = (value) => formatCurrency(Number(value || 0))

function AdvanceModal({ entry, error, isSaving, onClose, onSubmit }) {
  const [paidTo, setPaidTo] = useState(entry?.paidTo || '')
  const [reason, setReason] = useState(entry?.reason || '')
  const [amount, setAmount] = useState(entry?.amount || '')
  const [paymentMode, setPaymentMode] = useState(entry?.paymentMode || '')
  const [isReturnable, setIsReturnable] = useState(entry?.isReturnable ? 'yes' : 'no')
  const [hasExpiryDate, setHasExpiryDate] = useState(entry?.hasExpiryDate ? 'yes' : 'no')
  const [expiryDate, setExpiryDate] = useState(entry?.expiryDate || '')

  const submit = (event) => {
    event.preventDefault()
    onSubmit({ paidTo, reason, amount, paymentMode, isReturnable, hasExpiryDate, expiryDate })
  }

  return (
    <div className="budget-modal-backdrop accounting-modal-backdrop" role="presentation" onMouseDown={(event) => {
      if (event.target === event.currentTarget && !isSaving) onClose()
    }}>
      <section className="budget-modal accounting-modal" role="dialog" aria-modal="true" aria-labelledby="advance-modal-title">
        <header className="budget-modal-heading">
          <div>
            <span className="mini-label">Budget / Advances</span>
            <h2 id="advance-modal-title">{entry ? 'Edit Advance' : 'Add Advance'}</h2>
            <p>Enter the payment details and return terms.</p>
          </div>
          <button type="button" className="budget-modal-close" onClick={onClose} disabled={isSaving} aria-label="Close">×</button>
        </header>
        <form onSubmit={submit}>
          {error && <p className="budget-error" role="alert">{error}</p>}
          <label className="budget-form-field">
            <span>Paid to</span>
            <input
              className="budget-text-input"
              type="text"
              maxLength={160}
              value={paidTo}
              onChange={(event) => setPaidTo(event.target.value)}
              placeholder="Enter the recipient's name"
              required
            />
          </label>
          <label className="budget-form-field">
            <span>Reason</span>
            <textarea
              className="budget-text-input accounting-note-input"
              rows="3"
              maxLength={500}
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              placeholder="Enter the reason for this advance"
              required
            />
          </label>
          <label className="budget-form-field">
            <span>Amount paid</span>
            <div className="budget-amount-input">
              <span>₹</span>
              <input
                type="number"
                min="0.01"
                max="999999999999.99"
                step="0.01"
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
                placeholder="0.00"
                required
              />
            </div>
          </label>
          <label className="budget-form-field">
            <span>Payment method</span>
            <select
              className="budget-text-input"
              value={paymentMode}
              onChange={(event) => setPaymentMode(event.target.value)}
              required
            >
              <option value="" disabled>Select payment method</option>
              {paymentModes.map((mode) => <option value={mode.value} key={mode.value}>{mode.label}</option>)}
            </select>
          </label>
          <label className="budget-form-field">
            <span>Will this amount be returned?</span>
            <select
              className="budget-text-input"
              value={isReturnable}
              onChange={(event) => setIsReturnable(event.target.value)}
              required
            >
              <option value="yes">Yes</option>
              <option value="no">No</option>
            </select>
          </label>
          <label className="budget-form-field">
            <span>Does this advance have an expiry date?</span>
            <select
              className="budget-text-input"
              value={hasExpiryDate}
              onChange={(event) => {
                setHasExpiryDate(event.target.value)
                if (event.target.value === 'no') setExpiryDate('')
              }}
              required
            >
              <option value="no">No expiry date</option>
              <option value="yes">Yes</option>
            </select>
          </label>
          {hasExpiryDate === 'yes' && (
            <label className="budget-form-field">
              <span>Expiry date</span>
              <input
                className="budget-text-input"
                type="date"
                value={expiryDate}
                onChange={(event) => setExpiryDate(event.target.value)}
                required
              />
            </label>
          )}
          <footer className="budget-modal-actions">
            <button type="button" className="budget-secondary-button" onClick={onClose} disabled={isSaving}>Cancel</button>
            <button type="submit" className="budget-primary-button" disabled={isSaving}>
              {isSaving ? 'Saving…' : entry ? 'Save changes' : 'Submit'}
            </button>
          </footer>
        </form>
      </section>
    </div>
  )
}

export default function AdvancesPage() {
  const [entries, setEntries] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [saveError, setSaveError] = useState('')
  const [showModal, setShowModal] = useState(false)
  const [editingEntry, setEditingEntry] = useState(null)
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleteError, setDeleteError] = useState('')
  const [isSaving, setIsSaving] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    let active = true
    apiRequest('/budget/advances')
      .then((result) => {
        if (active) {
          setEntries(result.entries)
          setLoadError('')
        }
      })
      .catch((error) => {
        if (active) setLoadError(error.message)
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })
    return () => {
      active = false
    }
  }, [])

  const saveEntry = async (details) => {
    setSaveError('')
    setIsSaving(true)
    try {
      const path = editingEntry ? `/budget/advances/${editingEntry.id}` : '/budget/advances'
      const result = await apiRequest(path, { method: 'POST', body: details })
      setEntries((current) => editingEntry
        ? current.map((entry) => entry.id === result.entry.id ? result.entry : entry)
        : [result.entry, ...current])
      setShowModal(false)
      setEditingEntry(null)
    } catch (error) {
      setSaveError(error.message)
    } finally {
      setIsSaving(false)
    }
  }

  const deleteEntry = async () => {
    if (!deleteTarget) return
    setDeleteError('')
    setIsDeleting(true)
    try {
      await apiRequest(`/budget/advances/${deleteTarget.id}`, { method: 'DELETE' })
      setEntries((current) => current.filter((entry) => entry.id !== deleteTarget.id))
      setDeleteTarget(null)
    } catch (error) {
      setDeleteError(error.message)
    } finally {
      setIsDeleting(false)
    }
  }

  const paymentModeLabel = (value) => paymentModes.find((mode) => mode.value === value)?.label || value

  return (
    <section className="budget-page accounting-page">
      <header className="page-heading budget-heading accounting-page-heading">
        <div className="page-heading-copy">
          <span className="mini-label">Budget / Advances</span>
          <h1>Advances</h1>
          <p>Track advances, payment methods, and return dates.</p>
        </div>
        <div className="page-heading-actions">
          <button type="button" className="budget-primary-button accounting-add-button" onClick={() => {
            setSaveError('')
            setEditingEntry(null)
            setShowModal(true)
          }}>
            <Icon name="plus" size={17} />
            Add Advance
          </button>
        </div>
      </header>

      {loadError && <p className="budget-error" role="alert">{loadError}</p>}
      <article className="dashboard-panel accounting-ledger-panel">
        <div className="panel-title accounting-ledger-title">
          <h2>Advance records</h2>
          {!isLoading && !loadError && <span>{entries.length}</span>}
        </div>
        {isLoading ? (
          <p className="employee-directory-loading" role="status">Loading advances…</p>
        ) : loadError ? null : entries.length ? (
          <div className="accounting-table-wrap">
            <table className="accounting-table">
              <thead>
                <tr>
                  <th>Paid to / reason</th>
                  <th>Amount</th>
                  <th>Payment method</th>
                  <th>Returned?</th>
                  <th>Expiry date</th>
                  <th>Created date &amp; time</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry) => (
                  <tr key={entry.id}>
                    <td data-label="Paid to / reason">
                      <strong>{entry.paidTo}</strong>
                      <small className="accounting-entry-note">{entry.reason}</small>
                    </td>
                    <td data-label="Amount"><strong>{currency(entry.amount)}</strong></td>
                    <td data-label="Payment method">{paymentModeLabel(entry.paymentMode)}</td>
                    <td data-label="Returned?">{entry.isReturnable ? 'Yes' : 'No'}</td>
                    <td data-label="Expiry date">{entry.hasExpiryDate ? entry.expiryDate : 'No expiry date'}</td>
                    <td data-label="Created date & time">
                      <time dateTime={entry.createdAt}>{formatDateTime(entry.createdAt)}</time>
                    </td>
                    <td data-label="Actions">
                      <div className="accounting-row-actions">
                        <button
                          type="button"
                          onClick={() => {
                            setSaveError('')
                            setEditingEntry(entry)
                            setShowModal(true)
                          }}
                        >
                          Edit
                        </button>
                        <button
                          type="button"
                          className="is-delete"
                          onClick={() => {
                            setDeleteError('')
                            setDeleteTarget(entry)
                          }}
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="employee-list-empty accounting-empty">
            <strong>No advances recorded yet</strong>
            <span>Add the first advance record.</span>
          </div>
        )}
      </article>

      {showModal && (
        <AdvanceModal
          key={editingEntry?.id || 'new-advance'}
          entry={editingEntry}
          error={saveError}
          isSaving={isSaving}
          onClose={() => {
            if (!isSaving) setShowModal(false)
          }}
          onSubmit={saveEntry}
        />
      )}
      {deleteTarget && (
        <div className="budget-modal-backdrop" role="presentation" onMouseDown={(event) => {
          if (event.target === event.currentTarget && !isDeleting) setDeleteTarget(null)
        }}>
          <section className="budget-modal accounting-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="advance-delete-title">
            <header className="budget-modal-heading">
              <div>
                <span className="mini-label">Budget / Advances</span>
                <h2 id="advance-delete-title">Delete advance?</h2>
                <p>Delete the advance record for <strong>{deleteTarget.paidTo}</strong>?</p>
              </div>
              <button type="button" className="budget-modal-close" onClick={() => setDeleteTarget(null)} disabled={isDeleting} aria-label="Close">×</button>
            </header>
            {deleteError && <p className="budget-error" role="alert">{deleteError}</p>}
            <footer className="budget-modal-actions">
              <button type="button" className="budget-secondary-button" onClick={() => setDeleteTarget(null)} disabled={isDeleting}>Cancel</button>
              <button type="button" className="accounting-delete-confirm" onClick={deleteEntry} disabled={isDeleting}>
                {isDeleting ? 'Deleting…' : 'Delete advance'}
              </button>
            </footer>
          </section>
        </div>
      )}
    </section>
  )
}
