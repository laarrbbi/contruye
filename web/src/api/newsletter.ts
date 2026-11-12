import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { API_URL, api } from './client'

export type NewsletterSource = 'home' | 'calculator'
export type NewsletterStatus = 'pending' | 'confirmed' | 'unsubscribed'

export function useNewsletterSignup() {
  return useMutation({
    mutationFn: async (payload: { email: string; locale: string; source: NewsletterSource; website: string }) => {
      const { data } = await api.post('/newsletter', payload)
      return data as { message: string }
    },
  })
}

/** Confirm or unsubscribe with the token of the e-mail's link. */
export function useNewsletterToken(action: 'confirm' | 'unsubscribe') {
  return useMutation({
    mutationFn: async (token: string) => {
      const { data } = await api.post(`/newsletter/${action}`, { token })
      return data.data as { status: NewsletterStatus; email: string }
    },
  })
}

export interface AdminSubscriber {
  id: number
  email: string
  locale: string
  source: NewsletterSource | null
  status: NewsletterStatus
  created_at: string
  confirmed_at: string | null
  unsubscribed_at: string | null
}

export function useAdminNewsletter(filters: { status: NewsletterStatus | null; search: string; page: number }) {
  return useQuery({
    queryKey: ['admin-newsletter', filters],
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const { data } = await api.get('/admin/newsletter', {
        params: { status: filters.status ?? undefined, search: filters.search || undefined, page: filters.page },
      })
      return data as {
        data: AdminSubscriber[]
        meta: { current_page: number; last_page: number; total: number; counts: Partial<Record<NewsletterStatus, number>> }
      }
    },
  })
}

/** The confirmed addresses as a CSV: plain navigation, so the session cookie rides along. */
export const NEWSLETTER_EXPORT_URL = `${API_URL}/api/admin/newsletter/export`
