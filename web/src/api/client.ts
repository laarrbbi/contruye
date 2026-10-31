import axios from 'axios'
import i18n from '@/i18n'
import { isLocale, storedLocale } from '@/i18n/locales'

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
  // The language on screen, not only the stored choice: a first visit to an
  // app page takes the browser's language without storing it, and course
  // text asked for in another language would come back as Spanish under an
  // English (or French…) interface, labelled "not translated yet".
  config.headers['Accept-Language'] = isLocale(i18n.language) ? i18n.language : (storedLocale() ?? 'es')
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
