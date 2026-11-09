import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiRequest } from './api.js'

describe('apiRequest', () => {
  beforeEach(() => {
    Object.defineProperty(window, 'sessionStorage', {
      value: {
        getItem: vi.fn(() => null),
        setItem: vi.fn(),
        removeItem: vi.fn(),
      },
      configurable: true,
    })
  })

  it('returns successful JSON payloads and stores the backend session header', async () => {
    const response = {
      ok: true,
      headers: { get: vi.fn(() => 'tab-session-123') },
      json: vi.fn().mockResolvedValue({ success: true, user: { id: 7 } }),
    }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response))

    const result = await apiRequest('/auth/me')

    expect(result).toEqual({ success: true, user: { id: 7 } })
    expect(window.sessionStorage.setItem).toHaveBeenCalledWith('arowin.tab-session', 'tab-session-123')
  })

  it('throws a clear message when the backend is unreachable', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    await expect(apiRequest('/auth/me')).rejects.toThrow(
      'Cannot reach the AROWIN API. Start the PHP backend on port 8000 and make sure MySQL is running.',
    )
  })
})
