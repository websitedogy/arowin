import { useEffect, useRef, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import { formatCurrency, formatDateTime } from '../../utils/formatters'

const categories = {
  expenses: {
    label: 'Expenses',
    entryLabel: 'Expense',
    nameLabel: 'Expense name',
    addLabel: 'Add Expenses',
  },
  ads_promotions: {
    label: 'Ads & Promotions',
    entryLabel: 'Promotion',
    nameLabel: 'Campaign name',
    addLabel: 'Add Ads & Promotions',
  },
}
const supportedImageTypes = ['image/jpeg', 'image/png', 'image/webp']
const currency = (value) => formatCurrency(Number(value || 0))

function PrivateProof({ proof, categoryKey }) {
  const [imageUrl, setImageUrl] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    let objectUrl = ''
    apiRequest(`/budget/costs/proofs/${proof.id}?category=${categoryKey}`, { responseType: 'blob' })
      .then((image) => {
        if (!active) return
        objectUrl = URL.createObjectURL(image)
        setImageUrl(objectUrl)
      })
      .catch((requestError) => {
        if (active) setError(requestError.message)
      })

    return () => {
      active = false
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [categoryKey, proof.id])

  if (error) return <span className="accounting-proof-error" title={error}>Proof unavailable</span>
  if (!imageUrl) return <span className="accounting-proof-loading" role="status">Loading…</span>

  return (
    <a className="accounting-proof-thumb" href={imageUrl} target="_blank" rel="noreferrer" title={proof.name}>
      <img src={imageUrl} alt={proof.name} loading="lazy" />
    </a>
  )
}

function CostEntryModal({ category, categoryKey, entry, error, isSaving, onClose, onSubmit }) {
  const [entryName, setEntryName] = useState(entry?.entryName || '')
  const [amount, setAmount] = useState(entry?.amount || '')
  const [note, setNote] = useState(entry?.note || '')
  const [files, setFiles] = useState([])
  const [fileError, setFileError] = useState('')
  const [previews, setPreviews] = useState([])
  const [removedProofIds, setRemovedProofIds] = useState([])
  const previewUrls = useRef(new Set())

  useEffect(() => () => {
    previewUrls.current.forEach((url) => URL.revokeObjectURL(url))
  }, [])

  const selectFiles = (selectedFiles) => {
    const addedFiles = Array.from(selectedFiles)
    if (addedFiles.some((file) => !supportedImageTypes.includes(file.type))) {
      setFileError('Proofs must be JPEG, PNG, or WebP images.')
      return
    }
    setFileError('')
    const addedPreviews = addedFiles.map((file) => {
      const url = URL.createObjectURL(file)
      previewUrls.current.add(url)
      return { file, url }
    })
    setFiles((current) => [...current, ...addedFiles])
    setPreviews((current) => [...current, ...addedPreviews])
  }

  const submit = (event) => {
    event.preventDefault()
    onSubmit({ entryName, amount, note, files, removeProofIds: removedProofIds })
  }

  return (
    <div className="budget-modal-backdrop accounting-modal-backdrop" role="presentation" onMouseDown={(event) => {
      if (event.target === event.currentTarget && !isSaving) onClose()
    }}>
      <section className="budget-modal accounting-modal" role="dialog" aria-modal="true" aria-labelledby="cost-entry-modal-title">
        <header className="budget-modal-heading">
          <div>
            <span className="mini-label">{category.label}</span>
            <h2 id="cost-entry-modal-title">{entry ? `Edit ${category.entryLabel}` : `Add ${category.entryLabel}`}</h2>
            <p>Add the amount and attach proof images if available.</p>
          </div>
          <button type="button" className="budget-modal-close" onClick={onClose} disabled={isSaving} aria-label="Close">×</button>
        </header>
        <form onSubmit={submit}>
          {(error || fileError) && <p className="budget-error" role="alert">{error || fileError}</p>}
          <label className="budget-form-field">
            <span>{category.nameLabel}</span>
            <input
              className="budget-text-input"
              type="text"
              maxLength={160}
              value={entryName}
              onChange={(event) => setEntryName(event.target.value)}
              placeholder={`Enter ${category.nameLabel.toLowerCase()}`}
              required
            />
          </label>
          <label className="budget-form-field">
            <span>Amount</span>
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
          <div className="budget-form-field">
            <span>Proof <small>(optional · select multiple images)</small></span>
            {entry?.proofs.length > 0 && (
              <div className="accounting-existing-proofs">
                {entry.proofs.map((proof) => {
                  const isRemoved = removedProofIds.includes(proof.id)
                  return (
                    <article className={`accounting-existing-proof${isRemoved ? ' is-removed' : ''}`} key={proof.id}>
                      <PrivateProof proof={proof} categoryKey={categoryKey} />
                      <span title={proof.name}>{proof.name}</span>
                      <button
                        type="button"
                        onClick={() => setRemovedProofIds((current) => isRemoved
                          ? current.filter((id) => id !== proof.id)
                          : [...current, proof.id])}
                        disabled={isSaving}
                      >
                        {isRemoved ? 'Keep' : 'Remove'}
                      </button>
                    </article>
                  )
                })}
              </div>
            )}
            <label className="accounting-upload-button">
              <Icon name="plus" size={17} />
              Choose images
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                onChange={(event) => {
                  selectFiles(event.target.files)
                  event.target.value = ''
                }}
              />
            </label>
            {previews.length > 0 && (
              <div className="accounting-preview-grid">
                {previews.map(({ file, url }, index) => (
                  <article className="accounting-preview-card" key={url}>
                    <img src={url} alt={`Proof preview: ${file.name}`} />
                    <span title={file.name}>{file.name}</span>
                    <button
                      type="button"
                      onClick={() => {
                        URL.revokeObjectURL(url)
                        previewUrls.current.delete(url)
                        setFiles((current) => current.filter((_, fileIndex) => fileIndex !== index))
                        setPreviews((current) => current.filter((_, previewIndex) => previewIndex !== index))
                      }}
                      aria-label={`Remove ${file.name}`}
                      disabled={isSaving}
                    >
                      ×
                    </button>
                  </article>
                ))}
              </div>
            )}
          </div>
          <label className="budget-form-field">
            <span>Note <small>(optional)</small></span>
            <textarea
              className="budget-text-input accounting-note-input"
              rows="3"
              maxLength={500}
              value={note}
              onChange={(event) => setNote(event.target.value)}
              placeholder="Add a note"
            />
          </label>
          <footer className="budget-modal-actions">
            <button type="button" className="budget-secondary-button" onClick={onClose} disabled={isSaving}>Cancel</button>
            <button type="submit" className="budget-primary-button" disabled={isSaving || Boolean(fileError) || !Number(amount)}>
              {isSaving ? 'Saving…' : entry ? 'Save changes' : 'Submit'}
            </button>
          </footer>
        </form>
      </section>
    </div>
  )
}

export default function BudgetCostLedgerPage({ categoryKey }) {
  const category = categories[categoryKey]
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
    apiRequest(`/budget/costs?category=${categoryKey}`)
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
  }, [categoryKey])

  const saveEntry = async ({ entryName, amount, note, files, removeProofIds }) => {
    setSaveError('')
    setIsSaving(true)
    const body = new FormData()
    body.set('category', categoryKey)
    body.set('entryName', entryName)
    body.set('amount', amount)
    body.set('note', note)
    files.forEach((file) => body.append('proofs[]', file))
    removeProofIds.forEach((id) => body.append('removeProofIds[]', String(id)))

    try {
      const path = editingEntry
        ? `/budget/costs/${categoryKey}/${editingEntry.id}`
        : '/budget/costs'
      const result = await apiRequest(path, { method: 'POST', body })
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
      await apiRequest(`/budget/costs/${categoryKey}/${deleteTarget.id}`, { method: 'DELETE' })
      setEntries((current) => current.filter((entry) => entry.id !== deleteTarget.id))
      setDeleteTarget(null)
    } catch (error) {
      setDeleteError(error.message)
    } finally {
      setIsDeleting(false)
    }
  }

  if (!category) return null

  return (
    <section className="budget-page accounting-page">
      <header className="page-heading budget-heading accounting-page-heading">
        <div className="page-heading-copy">
          <span className="mini-label">Budget / {category.label}</span>
          <h1>{category.label}</h1>
          <p>Record and review {category.label.toLowerCase()} with proof images and notes.</p>
        </div>
        <div className="page-heading-actions">
          <button type="button" className="budget-primary-button accounting-add-button" onClick={() => {
            setSaveError('')
            setEditingEntry(null)
            setShowModal(true)
          }}>
            <Icon name="plus" size={17} />
            {category.addLabel}
          </button>
        </div>
      </header>

      {loadError && <p className="budget-error" role="alert">{loadError}</p>}
      <article className="dashboard-panel accounting-ledger-panel">
        <div className="panel-title accounting-ledger-title">
          <h2>{category.label} entries</h2>
          {!isLoading && !loadError && <span>{entries.length}</span>}
        </div>
        {isLoading ? (
          <p className="employee-directory-loading" role="status">Loading entries…</p>
        ) : loadError ? null : entries.length ? (
          <div className="accounting-table-wrap">
            <table className="accounting-table">
              <thead>
                <tr>
                  <th>{category.nameLabel}</th>
                  <th>Amount</th>
                  <th>Proof</th>
                  <th>Created date &amp; time</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry) => (
                  <tr key={entry.id}>
                    <td data-label={category.nameLabel}>
                      <strong>{entry.entryName}</strong>
                      {entry.note && <small className="accounting-entry-note">{entry.note}</small>}
                    </td>
                    <td data-label="Amount"><strong>{currency(entry.amount)}</strong></td>
                    <td data-label="Proof">
                      {entry.proofs.length
                        ? <div className="accounting-proof-list">{entry.proofs.map((proof) => <PrivateProof key={`${categoryKey}-${proof.id}`} proof={proof} categoryKey={categoryKey} />)}</div>
                        : <span className="accounting-no-proof">—</span>}
                    </td>
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
            <strong>No {category.label.toLowerCase()} entries yet</strong>
            <span>Add the first {category.entryLabel.toLowerCase()} entry.</span>
          </div>
        )}
      </article>

      {showModal && (
        <CostEntryModal
          key={editingEntry?.id || 'new-entry'}
          category={category}
          categoryKey={categoryKey}
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
          <section className="budget-modal accounting-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="cost-delete-title">
            <header className="budget-modal-heading">
              <div>
                <span className="mini-label">{category.label}</span>
                <h2 id="cost-delete-title">Delete entry?</h2>
                <p><strong>{deleteTarget.entryName}</strong> and its proof images will be permanently deleted.</p>
              </div>
              <button type="button" className="budget-modal-close" onClick={() => setDeleteTarget(null)} disabled={isDeleting} aria-label="Close">×</button>
            </header>
            {deleteError && <p className="budget-error" role="alert">{deleteError}</p>}
            <footer className="budget-modal-actions">
              <button type="button" className="budget-secondary-button" onClick={() => setDeleteTarget(null)} disabled={isDeleting}>Cancel</button>
              <button type="button" className="accounting-delete-confirm" onClick={deleteEntry} disabled={isDeleting}>
                {isDeleting ? 'Deleting…' : 'Delete entry'}
              </button>
            </footer>
          </section>
        </div>
      )}
    </section>
  )
}
