import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'
import type { Locale } from '@/i18n/locales'
import { intlLocale } from '@/lib/utils'

/** A piece of news as a list shows it, in the reader's language or in Spanish. */
export interface NewsCard {
  slug: string
  title: string
  title_lang: string
  summary: string | null
  published_at: string
  image_url: string | null
}

export interface NewsPost extends NewsCard {
  body_html: string | null
  body_lang: string
}

/** «12 de noviembre de 2026», in the reader's language. */
export function newsDate(iso: string, locale: Locale): string {
  return new Intl.DateTimeFormat(intlLocale(locale), { dateStyle: 'long' }).format(new Date(iso))
}

export function useNews(page: number) {
  return useQuery({
    queryKey: ['news', page],
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const { data } = await api.get('/news', { params: { page } })
      return data as { data: NewsCard[]; meta: { current_page: number; last_page: number; total: number } }
    },
  })
}

export function useNewsPost(slug: string | undefined) {
  return useQuery({
    queryKey: ['news', 'post', slug],
    enabled: !!slug,
    retry: false,
    queryFn: async () => (await api.get(`/news/${slug}`)).data.data as NewsPost,
  })
}

type Localized<K extends string> = { [P in `${K}_${Locale}`]: string | null }

export type AdminNewsFields = Localized<'title'> & Localized<'summary'> & Localized<'body'>

export interface AdminNewsPost extends AdminNewsFields {
  id: number
  slug: string
  is_published: boolean
  published_at: string | null
  is_visible: boolean
  image_url: string | null
  updated_at: string | null
}

export type AdminNewsPayload = Partial<AdminNewsFields> & {
  slug?: string | null
  is_published: boolean
  published_at: string | null
}

const ADMIN_KEY = ['admin', 'news']

export function useAdminNews() {
  return useQuery<AdminNewsPost[]>({
    queryKey: ADMIN_KEY,
    queryFn: async () => (await api.get('/admin/news')).data.data,
  })
}

/** Create (no id) or update a piece; the list and the public pages refresh. */
export function useSaveNews() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, payload }: { id: number | null; payload: AdminNewsPayload }) =>
      (id === null ? await api.post('/admin/news', payload) : await api.put(`/admin/news/${id}`, payload)).data
        .data as AdminNewsPost,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ADMIN_KEY })
      void queryClient.invalidateQueries({ queryKey: ['news'] })
    },
  })
}

export function useDeleteNews() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => api.delete(`/admin/news/${id}`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ADMIN_KEY })
      void queryClient.invalidateQueries({ queryKey: ['news'] })
    },
  })
}

/** Upload (a file) or remove (null) the picture of a saved piece. */
export function useNewsImage() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, file }: { id: number; file: File | null }) => {
      if (file === null) return (await api.delete(`/admin/news/${id}/image`)).data.data as AdminNewsPost
      const form = new FormData()
      form.append('image', file)
      return (await api.post(`/admin/news/${id}/image`, form)).data.data as AdminNewsPost
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ADMIN_KEY })
      void queryClient.invalidateQueries({ queryKey: ['news'] })
    },
  })
}
