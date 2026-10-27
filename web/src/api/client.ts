import axios from 'axios'

export const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

export const api = axios.create({
  baseURL: `${API_URL}/api`,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

let csrfReady = false

/** Sanctum SPA auth: fetch the CSRF cookie once before any mutating request. */
export async function ensureCsrf() {
  if (csrfReady) return
  await axios.get(`${API_URL}/sanctum/csrf-cookie`, { withCredentials: true })
  csrfReady = true
}

api.interceptors.request.use(async (config) => {
  const method = (config.method ?? 'get').toLowerCase()
  if (method !== 'get' && method !== 'head') {
    await ensureCsrf()
  }
  const locale = localStorage.getItem('locale') ?? 'fr'
  config.headers['Accept-Language'] = locale
  return config
})

api.interceptors.response.use(
  (res) => res,
  (error) => {
    // Session expired mid-flight: drop the cached CSRF state so the next
    // attempt re-handshakes with the backend.
    if (error.response?.status === 419) {
      csrfReady = false
    }
    return Promise.reject(error)
  },
)
