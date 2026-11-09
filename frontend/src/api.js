const API_HOST = typeof window === 'undefined' ? 'localhost' : window.location.hostname
const API_BASE_URL = import.meta.env.VITE_API_URL || `http://${API_HOST}:8000/api`
const pendingGetRequests = new Map()
const TAB_SESSION_KEY = 'arowin.tab-session'

async function sendRequest(path, { body, responseType, ...options } = {}) {
  let response
  const headers = new Headers(options.headers)
  const tabSession = window.sessionStorage.getItem(TAB_SESSION_KEY)
  if (tabSession) headers.set('X-Arowin-Session', tabSession)
  const isFormData = typeof FormData !== 'undefined' && body instanceof FormData
  if (body !== undefined && !isFormData) headers.set('Content-Type', 'application/json')

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...options,
      credentials: 'omit',
      headers,
      ...(body === undefined ? {} : { body: isFormData ? body : JSON.stringify(body) }),
    })
  } catch (error) {
    if (error instanceof TypeError) {
      throw new Error('Cannot reach the AROWIN API. Start the PHP backend on port 8000 and make sure MySQL is running.')
    }

    throw error
  }

  const nextTabSession = response.headers.get('X-Arowin-Session')
  if (nextTabSession) window.sessionStorage.setItem(TAB_SESSION_KEY, nextTabSession)

  if (responseType === 'blob') {
    if (!response.ok) {
      let result
      try {
        result = await response.json()
      } catch {
        throw new Error('The backend returned an invalid response.')
      }
      const error = new Error(result.message || `Request failed (${response.status}).`)
      error.status = response.status
      throw error
    }

    return response.blob()
  }

  let result
  try {
    result = await response.json()
  } catch {
    throw new Error('The backend returned an invalid response.')
  }

  if (!response.ok || !result.success) {
    const error = new Error(result.message || `Request failed (${response.status}).`)
    error.status = response.status
    throw error
  }

  if (path === '/auth/logout') window.sessionStorage.removeItem(TAB_SESSION_KEY)

  return result
}

export function apiRequest(path, options = {}) {
  if ((options.method || 'GET').toUpperCase() !== 'GET') {
    return sendRequest(path, options)
  }

  const requestKey = `${API_BASE_URL}${path}:${options.responseType || 'json'}`
  const existingRequest = pendingGetRequests.get(requestKey)
  if (existingRequest) return existingRequest

  const request = sendRequest(path, options)
  pendingGetRequests.set(requestKey, request)
  request.then(
    () => pendingGetRequests.delete(requestKey),
    () => pendingGetRequests.delete(requestKey),
  )

  return request
}
