import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import { formatUsdt } from '../../utils/formatters'

const packageAmounts = ['50.00', '150.00', '350.00', '750.00', '1550.00', '3150.00', '6350.00', '12750.00']

export default function PackageIncentiveSettings() {
  const [packages, setPackages] = useState([])
  const [incentives, setIncentives] = useState({})
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    let active = true
    apiRequest('/sales/packages')
      .then((result) => {
        if (!active) return
        setPackages(result.packages)
        setIncentives(Object.fromEntries(
          result.packages.map((item) => [item.packageAmount, item.incentiveAmount ?? '']),
        ))
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

  const save = async (event) => {
    event.preventDefault()
    setError('')
    setNotice('')
    setIsSaving(true)
    try {
      const result = await apiRequest('/sales/packages/incentives', {
        method: 'POST',
        body: { incentives },
      })
      setPackages(result.packages)
      setIncentives(Object.fromEntries(
        result.packages.map((item) => [item.packageAmount, item.incentiveAmount ?? '']),
      ))
      setNotice('Package incentives saved. Sale submissions will be enabled after Arowin Global lookup is connected.')
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <article className="dashboard-panel settings-amount-card">
      <div className="settings-card-heading">
        <span className="settings-card-icon is-sales" aria-hidden="true">05</span>
        <div>
          <span className="settings-card-eyebrow">PACKAGE SALES</span>
          <h2>Sales incentive rates</h2>
          <p>USDT incentives are credited after a package sale is approved.</p>
        </div>
      </div>
      {error && <p className="dashboard-data-error" role="alert">{error}</p>}
      {notice && <p className="sales-success-message" role="status">{notice}</p>}
      <form className="dashboard-panel package-incentive-form" onSubmit={save}>
        {isLoading ? (
          <p>Loading package settings…</p>
        ) : (
          <>
            <div className="package-incentive-grid">
              {packageAmounts.map((packageAmount) => (
                <label className="budget-form-field" key={packageAmount}>
                  <span>${Number(packageAmount).toLocaleString('en-US')} Package · Incentive (USDT)</span>
                  <div className="budget-amount-input">
                    <span>USDT</span>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={incentives[packageAmount] ?? ''}
                      onChange={(event) => setIncentives((current) => ({
                        ...current,
                        [packageAmount]: event.target.value,
                      }))}
                      placeholder="Enter incentive"
                      required
                    />
                  </div>
                </label>
              ))}
            </div>
            <div className="package-incentive-footer">
              <p>Incentive is credited only when a Sub Editor approves the sale.</p>
              <button type="submit" className="budget-primary-button" disabled={isSaving || packages.length !== 8}>
                {isSaving ? 'Saving…' : 'Save Package Incentives'}
              </button>
            </div>
            <div className="package-incentive-current" aria-label="Current configured incentives">
              {packages.filter((item) => item.isConfigured).map((item) => (
                <span key={item.packageAmount}>
                  ${Number(item.packageAmount).toLocaleString('en-US')}: {formatUsdt(item.incentiveAmount)}
                </span>
              ))}
            </div>
          </>
        )}
      </form>
    </article>
  )
}
