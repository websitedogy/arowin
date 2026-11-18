import { useEffect, useMemo, useRef, useState } from 'react'
import { apiRequest } from '../../api'
import Icon from '../dashboard/components/Icon'
import { formatCurrency, formatDateTime } from '../../utils/formatters'

const categories = {
  office: { label: 'Office', description: 'Office purchases and related accounting.' },
  assets: { label: 'Assets', description: 'Assets purchased and their supporting proofs.' },
}
const supportedImageTypes = ['image/jpeg', 'image/png', 'image/webp']
const currency = (value) => formatCurrency(Number(value || 0))

function PrivateProof({ proof, categoryKey, className = 'accounting-proof-thumb' }) {
  const [imageUrl, setImageUrl] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    let objectUrl = ''
    apiRequest(`/budget/accounting/proofs/${proof.id}?category=${categoryKey}`, { responseType: 'blob' })
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
    <a className={className} href={imageUrl} target="_blank" rel="noreferrer" title={proof.name}>
      <img src={imageUrl} alt={proof.name} loading="lazy" />
    </a>
  )
}

function AccountingModal({ category, categoryKey, entry, onClose, onSubmit, isSaving, error }) {
  const [itemName, setItemName] = useState(entry?.itemName || '')
  const [quantity, setQuantity] = useState(entry?.quantity || '1')
  const [pricePerItem, setPricePerItem] = useState(entry?.pricePerItem || '')
  const [note, setNote] = useState(entry?.note || '')
  const [files, setFiles] = useState([])
  const [fileError, setFileError] = useState('')
  const [previews, setPreviews] = useState([])
  const [removedProofIds, setRemovedProofIds] = useState([])
  const previewUrls = useRef(new Set())
  const total = useMemo(
    () => Number(quantity || 0) * Number(pricePerItem || 0),
    [pricePerItem, quantity],
  )

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
    onSubmit({ itemName, quantity, pricePerItem, note, files, removeProofIds: removedProofIds })
  }

  return (
    <div className="budget-modal-backdrop accounting-modal-backdrop" role="presentation" onMouseDown={(event) => {
      if (event.target === event.currentTarget && !isSaving) onClose()
    }}>
      <section className="budget-modal accounting-modal" role="dialog" aria-modal="true" aria-labelledby="accounting-modal-title">
        <header className="budget-modal-heading">
          <div>
            <span className="mini-label">{category.label} accounting</span>
            <h2 id="accounting-modal-title">{entry ? 'Edit accounting' : 'Add accounting'}</h2>
            <p>Enter the purchase details and attach proof if available.</p>
          </div>
          <button type="button" className="budget-modal-close" onClick={onClose} disabled={isSaving} aria-label="Close">×</button>
        </header>
        <form onSubmit={submit}>
          {(error || fileError) && <p className="budget-error" role="alert">{error || fileError}</p>}
          <label className="budget-form-field">
            <span>Item name</span>
            <input
              className="budget-text-input"
              type="text"
              maxLength={160}
              value={itemName}
              onChange={(event) => setItemName(event.target.value)}
              placeholder="Enter item name"
              required
            />
          </label>
          <div className="accounting-form-grid">
            <label className="budget-form-field">
              <span>Quantity</span>
              <input
                className="budget-text-input"
                type="number"
                min="0.001"
                max="999999999.999"
                step="0.001"
                value={quantity}
                onChange={(event) => setQuantity(event.target.value)}
                required
              />
            </label>
            <label className="budget-form-field">
              <span>Price per item</span>
              <div className="budget-amount-input">
                <span>₹</span>
                <input
                  type="number"
                  min="0.01"
                  max="999999999999.99"
                  step="0.01"
                  value={pricePerItem}
                  onChange={(event) => setPricePerItem(event.target.value)}
                  placeholder="0.00"
                  required
                />
              </div>
            </label>
          </div>
          <div className="accounting-total" aria-live="polite">
            <span>Total price</span>
            <strong>{currency(total)}</strong>
          </div>
          <div className="budget-form-field">
            <span>Proof <small>(optional · select multiple images)</small></span>
            {entry?.proofs.length > 0 && (
              <div className="accounting-existing-proofs">
                {entry.proofs.map((proof) => {
                  const isRemoved = removedProofIds.includes(proof.id)
                  return (
                    <article className={`accounting-existing-proof${isRemoved ? ' is-removed' : ''}`} key={proof.id}>
                      <PrivateProof proof={proof} categoryKey={categoryKey} className="accounting-edit-proof-thumb" />
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
            <button type="submit" className="budget-primary-button" disabled={isSaving || Boolean(fileError) || !Number(quantity) || !Number(pricePerItem)}>
              {isSaving ? 'Saving…' : entry ? 'Save changes' : 'Submit'}
            </button>
          </footer>
        </form>
      </section>
    </div>
  )
}

export default function AccountingLedgerPage({ categoryKey }) {
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
    apiRequest(`/budget/accounting?category=${categoryKey}`)
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

  const saveEntry = async ({ itemName, quantity, pricePerItem, note, files, removeProofIds }) => {
    setSaveError('')
    setIsSaving(true)
    const body = new FormData()
    body.set('category', categoryKey)
    body.set('itemName', itemName)
    body.set('quantity', quantity)
    body.set('pricePerItem', pricePerItem)
    body.set('note', note)
    files.forEach((file) => body.append('proofs[]', file))
    removeProofIds.forEach((id) => body.append('removeProofIds[]', String(id)))

    try {
      const path = editingEntry
        ? `/budget/accounting/${categoryKey}/${editingEntry.id}`
        : '/budget/accounting'
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
      await apiRequest(`/budget/accounting/${categoryKey}/${deleteTarget.id}`, { method: 'DELETE' })
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
          <h1>{category.label} Accounting</h1>
          <p>{category.description}</p>
        </div>
        <div className="page-heading-actions">
          <button type="button" className="budget-primary-button accounting-add-button" onClick={() => {
            setSaveError('')
            setEditingEntry(null)
            setShowModal(true)
          }}>
            <Icon name="plus" size={17} />
            Add Accounting
          </button>
        </div>
      </header>

      {loadError && <p className="budget-error" role="alert">{loadError}</p>}
      <article className="dashboard-panel accounting-ledger-panel">
        <div className="panel-title accounting-ledger-title">
          <h2>Accounting entries</h2>
          {!isLoading && !loadError && <span>{entries.length}</span>}
        </div>
        {isLoading ? (
          <p className="employee-directory-loading" role="status">Loading entries…</p>
        ) : loadError ? null : entries.length ? (
          <div className="accounting-table-wrap">
            <table className="accounting-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Quantity</th>
                  <th>Price / item</th>
                  <th>Total price</th>
                  <th>Proof</th>
                  <th>Created date &amp; time</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry) => (
                  <tr key={entry.id}>
                    <td data-label="Item">
                      <strong>{entry.itemName}</strong>
                      {entry.note && <small className="accounting-entry-note">{entry.note}</small>}
                    </td>
                    <td data-label="Quantity">{entry.quantity}</td>
                    <td data-label="Price / item">{currency(entry.pricePerItem)}</td>
                    <td data-label="Total price"><strong>{currency(entry.totalPrice)}</strong></td>
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
            <strong>No accounting entries yet</strong>
            <span>Add the first {category.label.toLowerCase()} entry.</span>
          </div>
        )}
      </article>

      {showModal && (
        <AccountingModal
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
          <section className="budget-modal accounting-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="accounting-delete-title">
            <header className="budget-modal-heading">
              <div>
                <span className="mini-label">{category.label} accounting</span>
                <h2 id="accounting-delete-title">Delete accounting entry?</h2>
                <p><strong>{deleteTarget.itemName}</strong> and its proof images will be permanently deleted.</p>
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
