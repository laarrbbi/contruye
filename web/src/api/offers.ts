import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/api/client'

/** The three moments of the course: a day after the purchase, the first milestone, the end. */
export const OFFER_STAGES = ['after_purchase', 'milestone', 'course_end'] as const
export type OfferStage = (typeof OFFER_STAGES)[number]

/** An offer as the student's app shows it. Only the open ones reach the client. */
export interface StudentOffer {
  stage: OfferStage
  title: string
  body_html: string
  cta_label: string | null
  /** The language the text is read in (the Spanish while a translation is missing). */
  lang: string
  course: { slug: string; title: string } | null
  url: string | null
  price_eur: number | null
  /** A real deadline (the purchase + 48 h…), or null: open until the student closes it. */
  closes_at: string | null
}

export function useOffers(enabled = true) {
  return useQuery<StudentOffer[]>({
    queryKey: ['offers'],
    queryFn: async () => (await api.get('/me/offers')).data.data,
    enabled,
    staleTime: 60_000,
  })
}

export function useOfferEvent() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ stage, action }: { stage: OfferStage; action: 'seen' | 'click' | 'dismiss' }) =>
      api.post(`/me/offers/${stage}/${action}`),
    onMutate: ({ stage, action }) => {
      // Closed: gone at once, not after the round trip.
      if (action === 'dismiss') {
        queryClient.setQueryData<StudentOffer[]>(['offers'], (offers) => offers?.filter((offer) => offer.stage !== stage))
      }
    },
  })
}

/** The fields the admin writes, one set per supported locale. */
export type OfferLocale = 'es' | 'en' | 'fr' | 'ar' | 'ru' | 'uk'
type LocalizedFields = {
  [K in `${'title' | 'body' | 'cta_label'}_${OfferLocale}`]: string | null
}

export interface AdminOfferPayload extends LocalizedFields {
  is_active: boolean
  course_id: number | null
  url: string | null
  price_eur: number | null
  delay_hours: number
  available_hours: number | null
  modules_required: number
  send_email: boolean
}

export interface AdminOffer extends AdminOfferPayload {
  id: number
  stage: OfferStage
  is_live: boolean
  activated_at: string | null
  stats: { reached: number; emailed: number; seen: number; clicked: number; dismissed: number }
}

export function useAdminOffers() {
  return useQuery<AdminOffer[]>({
    queryKey: ['admin', 'offers'],
    queryFn: async () => (await api.get('/admin/offers')).data.data,
  })
}

export function useUpdateOffer() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: AdminOfferPayload }) =>
      (await api.put(`/admin/offers/${id}`, payload)).data.data as AdminOffer,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'offers'] }),
  })
}
