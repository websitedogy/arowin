import { useEffect, useState } from 'react'
import { apiRequest } from '../../api'
import PackageIncentiveSettings from '../sales/PackageIncentiveSetupPage'

export default function SettingsPage() {
  const [onTimeBonusAmount, setOnTimeBonusAmount] = useState('0')
  const [teamTargetRewardAmount, setTeamTargetRewardAmount] = useState('0')
  const [teamLeaderIncentivePercent, setTeamLeaderIncentivePercent] = useState('0')
  const [platforms, setPlatforms] = useState([])
  const [dailyRates, setDailyRates] = useState({})
  const [dailyCapAmount, setDailyCapAmount] = useState('6')
  const [isAttendanceLoading, setIsAttendanceLoading] = useState(true)
  const [isDailyLoading, setIsDailyLoading] = useState(true)
  const [isTeamRewardLoading, setIsTeamRewardLoading] = useState(true)
  const [isTeamIncentiveLoading, setIsTeamIncentiveLoading] = useState(true)
  const [isAttendanceSaving, setIsAttendanceSaving] = useState(false)
  const [isDailySaving, setIsDailySaving] = useState(false)
  const [isTeamRewardSaving, setIsTeamRewardSaving] = useState(false)
  const [isTeamIncentiveSaving, setIsTeamIncentiveSaving] = useState(false)
  const [attendanceError, setAttendanceError] = useState('')
  const [dailyError, setDailyError] = useState('')
  const [teamRewardError, setTeamRewardError] = useState('')
  const [teamIncentiveError, setTeamIncentiveError] = useState('')
  const [attendanceNotice, setAttendanceNotice] = useState('')
  const [dailyNotice, setDailyNotice] = useState('')
  const [teamRewardNotice, setTeamRewardNotice] = useState('')
  const [teamIncentiveNotice, setTeamIncentiveNotice] = useState('')

  useEffect(() => {
    let active = true

    apiRequest('/attendance/settings')
      .then((result) => {
        if (active) setOnTimeBonusAmount(String(result.settings.onTimeBonusAmount ?? 0))
      })
      .catch((requestError) => {
        if (active) setAttendanceError(requestError.message)
      })
      .finally(() => {
        if (active) setIsAttendanceLoading(false)
      })

    apiRequest('/daily-activities/settings')
      .then((result) => {
        if (!active) return
        setPlatforms(result.settings.platforms)
        setDailyRates(Object.fromEntries(
          result.settings.platforms.map((platform) => [platform, String(result.settings.rates[platform] ?? 0)]),
        ))
        setDailyCapAmount(String(result.settings.dailyCapAmount ?? 0))
      })
      .catch((requestError) => {
        if (active) setDailyError(requestError.message)
      })
      .finally(() => {
        if (active) setIsDailyLoading(false)
      })

    apiRequest('/sales-targets/reward-settings')
      .then((result) => {
        if (active) setTeamTargetRewardAmount(String(result.settings.rewardAmount ?? 0))
      })
      .catch((requestError) => {
        if (active) setTeamRewardError(requestError.message)
      })
      .finally(() => {
        if (active) setIsTeamRewardLoading(false)
      })

    apiRequest('/sales-targets/team-grant-incentive-settings')
      .then((result) => {
        if (active) setTeamLeaderIncentivePercent(String(result.settings.leaderSharePercent ?? 0))
      })
      .catch((requestError) => {
        if (active) setTeamIncentiveError(requestError.message)
      })
      .finally(() => {
        if (active) setIsTeamIncentiveLoading(false)
      })

    return () => { active = false }
  }, [])

  const saveAttendanceBonus = async (event) => {
    event.preventDefault()
    setAttendanceError('')
    setAttendanceNotice('')
    setIsAttendanceSaving(true)

    try {
      const amount = Number(onTimeBonusAmount)
      if (onTimeBonusAmount.trim() === '' || !Number.isFinite(amount) || amount < 0 || amount > 99999999.99) {
        throw new Error('Enter an on-time attendance bonus between 0 and 99999999.99.')
      }

      const result = await apiRequest('/attendance/settings', {
        method: 'POST',
        body: { onTimeBonusAmount: amount },
      })
      setOnTimeBonusAmount(String(result.settings.onTimeBonusAmount ?? 0))
      setAttendanceNotice('On-time attendance bonus saved.')
    } catch (requestError) {
      setAttendanceError(requestError.message)
    } finally {
      setIsAttendanceSaving(false)
    }
  }

  const saveDailyActivityBonuses = async (event) => {
    event.preventDefault()
    setDailyError('')
    setDailyNotice('')
    setIsDailySaving(true)

    try {
      const rates = Object.fromEntries(platforms.map((platform) => {
        const rawAmount = dailyRates[platform] ?? ''
        const amount = Number(rawAmount)
        if (rawAmount.trim() === '' || !Number.isFinite(amount) || amount < 0 || amount > 99999999.99) {
          throw new Error(`Enter a valid amount for ${platform}.`)
        }
        return [platform, amount]
      }))
      const dailyCap = Number(dailyCapAmount)
      if (dailyCapAmount.trim() === '' || !Number.isFinite(dailyCap) || dailyCap < 0 || dailyCap > 99999999.99) {
        throw new Error('Enter a daily activity bonus maximum between 0 and 99999999.99.')
      }

      const result = await apiRequest('/daily-activities/settings', {
        method: 'POST',
        body: { rates, dailyCapAmount: dailyCap },
      })
      setDailyRates(Object.fromEntries(
        platforms.map((platform) => [platform, String(result.settings.rates[platform] ?? 0)]),
      ))
      setDailyCapAmount(String(result.settings.dailyCapAmount ?? 0))
      setDailyNotice('Daily activity bonus amounts saved.')
    } catch (requestError) {
      setDailyError(requestError.message)
    } finally {
      setIsDailySaving(false)
    }
  }

  const saveTeamTargetReward = async (event) => {
    event.preventDefault()
    setTeamRewardError('')
    setTeamRewardNotice('')
    setIsTeamRewardSaving(true)

    try {
      const amount = Number(teamTargetRewardAmount)
      if (teamTargetRewardAmount.trim() === '' || !Number.isFinite(amount) || amount < 0 || amount > 999999999999.99) {
        throw new Error('Enter a valid Team Leader monthly target reward between 0 and 999999999999.99 USDT.')
      }

      const result = await apiRequest('/sales-targets/reward-settings', {
        method: 'POST',
        body: { rewardAmount: amount },
      })
      setTeamTargetRewardAmount(String(result.settings.rewardAmount ?? 0))
      setTeamRewardNotice('Team Leader target reward saved.')
    } catch (requestError) {
      setTeamRewardError(requestError.message)
    } finally {
      setIsTeamRewardSaving(false)
    }
  }

  const saveTeamLeaderIncentivePercent = async (event) => {
    event.preventDefault()
    setTeamIncentiveError('')
    setTeamIncentiveNotice('')
    setIsTeamIncentiveSaving(true)

    try {
      const percent = Number(teamLeaderIncentivePercent)
      if (teamLeaderIncentivePercent.trim() === '' || !Number.isFinite(percent) || percent < 0 || percent > 100) {
        throw new Error('Enter a Team Leader incentive share between 0 and 100 percent.')
      }

      const result = await apiRequest('/sales-targets/team-grant-incentive-settings', {
        method: 'POST',
        body: { leaderSharePercent: percent },
      })
      setTeamLeaderIncentivePercent(String(result.settings.leaderSharePercent ?? 0))
      setTeamIncentiveNotice('Team Grant Incentives percentage saved.')
    } catch (requestError) {
      setTeamIncentiveError(requestError.message)
    } finally {
      setIsTeamIncentiveSaving(false)
    }
  }

  return (
    <section className="settings-page-content">
      <header className="page-heading settings-page-heading">
        <div className="page-heading-copy">
          <span className="mini-label">Workspace</span>
          <h1>Settings</h1>
          <p>Manage attendance rewards, activity bonuses, and sales incentives.</p>
        </div>
      </header>

      <article className="dashboard-panel settings-amount-card">
        <div className="settings-card-heading">
          <span className="settings-card-icon is-attendance" aria-hidden="true">01</span>
          <div>
            <span className="settings-card-eyebrow">ATTENDANCE</span>
            <h2>On-time check-in bonus</h2>
            <p>Added to an employee’s payable amount for check-in by 9:30 AM.</p>
          </div>
        </div>
        {attendanceError && <p className="budget-error" role="alert">{attendanceError}</p>}
        {attendanceNotice && <p className="support-success" role="status">{attendanceNotice}</p>}

        <form className="settings-inline-form" onSubmit={saveAttendanceBonus}>
          <label className="settings-amount-field">
            <span>Bonus per on-time check-in</span>
            <span className="settings-currency-input">
              <span aria-hidden="true">₹</span>
            <input
              type="number"
              min="0"
              max="99999999.99"
              step="0.01"
              value={onTimeBonusAmount}
              onChange={(event) => setOnTimeBonusAmount(event.target.value)}
              disabled={isAttendanceLoading || isAttendanceSaving}
              required
            />
            </span>
          </label>
          <button type="submit" className="settings-save-button" disabled={isAttendanceLoading || isAttendanceSaving}>
            {isAttendanceSaving ? 'Saving…' : 'Save changes'}
          </button>
        </form>
      </article>

      <article className="dashboard-panel settings-amount-card">
        <div className="settings-card-heading">
          <span className="settings-card-icon is-activity" aria-hidden="true">02</span>
          <div>
            <span className="settings-card-eyebrow">DAILY ACTIVITIES</span>
            <h2>Platform bonus rates</h2>
            <p>Choose the bonus for each approved submission. The daily maximum applies per employee.</p>
          </div>
        </div>
        {dailyError && <p className="budget-error" role="alert">{dailyError}</p>}
        {dailyNotice && <p className="support-success" role="status">{dailyNotice}</p>}

        <form onSubmit={saveDailyActivityBonuses}>
          <div className="settings-rate-grid">
            {platforms.map((platform) => (
              <label className="settings-amount-field" key={platform}>
                <span>{platform}</span>
                <span className="settings-currency-input">
                  <span aria-hidden="true">₹</span>
                  <input
                    type="number"
                    min="0"
                    max="99999999.99"
                    step="0.01"
                    value={dailyRates[platform] ?? ''}
                    onChange={(event) => setDailyRates((current) => ({
                      ...current,
                      [platform]: event.target.value,
                    }))}
                    disabled={isDailyLoading || isDailySaving}
                    required
                    aria-label={`${platform} bonus per approved activity in rupees`}
                  />
                </span>
              </label>
            ))}
          </div>
          <div className="settings-form-footer">
            <label className="settings-amount-field settings-daily-cap">
              <span>Maximum per employee, per day</span>
              <span className="settings-currency-input">
                <span aria-hidden="true">₹</span>
                <input
                  type="number"
                  min="0"
                  max="99999999.99"
                  step="0.01"
                  value={dailyCapAmount}
                  onChange={(event) => setDailyCapAmount(event.target.value)}
                  disabled={isDailyLoading || isDailySaving || platforms.length === 0}
                  required
                />
              </span>
            </label>
            <button type="submit" className="settings-save-button" disabled={isDailyLoading || isDailySaving || platforms.length === 0}>
              {isDailySaving ? 'Saving…' : 'Save activity rates'}
            </button>
          </div>
        </form>
      </article>

      <article className="dashboard-panel settings-amount-card">
        <div className="settings-card-heading">
          <span className="settings-card-icon is-sales" aria-hidden="true">03</span>
          <div>
            <span className="settings-card-eyebrow">TEAM TARGET REWARD</span>
            <h2>Team Leader monthly reward</h2>
            <p>Automatically grant this reward once the assigned team reaches its monthly sales target.</p>
          </div>
        </div>
        {teamRewardError && <p className="budget-error" role="alert">{teamRewardError}</p>}
        {teamRewardNotice && <p className="support-success" role="status">{teamRewardNotice}</p>}

        <form className="settings-inline-form" onSubmit={saveTeamTargetReward}>
          <label className="settings-amount-field">
            <span>Reward per completed monthly target</span>
            <span className="settings-currency-input">
              <span aria-hidden="true">USDT</span>
              <input
                type="number"
                min="0"
                max="999999999999.99"
                step="0.01"
                value={teamTargetRewardAmount}
                onChange={(event) => setTeamTargetRewardAmount(event.target.value)}
                disabled={isTeamRewardLoading || isTeamRewardSaving}
                required
              />
            </span>
          </label>
          <button type="submit" className="settings-save-button" disabled={isTeamRewardLoading || isTeamRewardSaving}>
            {isTeamRewardSaving ? 'Saving…' : 'Save reward'}
          </button>
        </form>
      </article>

      <article className="dashboard-panel settings-amount-card">
        <div className="settings-card-heading">
          <span className="settings-card-icon is-sales" aria-hidden="true">04</span>
          <div>
            <span className="settings-card-eyebrow">TEAM GRANT INCENTIVES</span>
            <h2>Team Leader share of team incentives</h2>
            <p>When every assigned Telecaller meets their equal share of the monthly target, this percentage of the team’s approved sale incentives is granted to the Team Leader.</p>
          </div>
        </div>
        {teamIncentiveError && <p className="budget-error" role="alert">{teamIncentiveError}</p>}
        {teamIncentiveNotice && <p className="support-success" role="status">{teamIncentiveNotice}</p>}

        <form className="settings-inline-form" onSubmit={saveTeamLeaderIncentivePercent}>
          <label className="settings-amount-field">
            <span>Team Leader share</span>
            <span className="settings-currency-input">
              <span aria-hidden="true">%</span>
              <input
                type="number"
                min="0"
                max="100"
                step="0.01"
                value={teamLeaderIncentivePercent}
                onChange={(event) => setTeamLeaderIncentivePercent(event.target.value)}
                disabled={isTeamIncentiveLoading || isTeamIncentiveSaving}
                required
              />
            </span>
          </label>
          <button type="submit" className="settings-save-button" disabled={isTeamIncentiveLoading || isTeamIncentiveSaving}>
            {isTeamIncentiveSaving ? 'Saving…' : 'Save percentage'}
          </button>
        </form>
      </article>

      <PackageIncentiveSettings />
    </section>
  )
}
