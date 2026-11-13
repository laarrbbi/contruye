import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/api/client'

/** A company of the directory, as a student sees it. */
export interface DirectoryCompany {
  id: number
  name: string
  category: string
  legal_form: string | null
  address: string | null
  postal_code: string | null
  city: string | null
  province: string | null
  phone: string | null
  email: string | null
  website: string | null
}

export interface DirectoryFilters {
  q?: string
  category?: string | null
  province?: number | null
  module?: number | null
  page?: number
}

export interface DirectoryPage {
  data: DirectoryCompany[]
  meta: { page: number; last_page: number; total: number; categories: { key: string; count: number }[] }
  /** The Partners page only: every phase, the student's open and the locked ones. */
  phases?: { module_id: number; phase: number; title: string; is_locked: boolean }[]
}

const params = (filters: DirectoryFilters) =>
  Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== null && value !== undefined && value !== ''))

/** A module's companies (the module must be owned). */
export function useModuleCompanies(slug: string | undefined, moduleId: number | null, filters: DirectoryFilters) {
  return useQuery<DirectoryPage>({
    queryKey: ['companies', slug, moduleId, filters],
    enabled: Boolean(slug && moduleId),
    placeholderData: keepPreviousData,
    queryFn: async () => (await api.get(`/courses/${slug}/modules/${moduleId}/companies`, { params: params(filters) })).data,
  })
}

/** The companies a lesson calls on (its module must be owned). */
export function useLessonCompanies(slug: string | undefined, lessonId: number | null, filters: DirectoryFilters) {
  return useQuery<DirectoryPage>({
    queryKey: ['companies', slug, 'lesson', lessonId, filters],
    enabled: Boolean(slug && lessonId),
    placeholderData: keepPreviousData,
    retry: false,
    queryFn: async () => (await api.get(`/courses/${slug}/lessons/${lessonId}/companies`, { params: params(filters) })).data,
  })
}

/** The Partners page: the companies of every owned module. */
export function useCompanies(slug: string | undefined, filters: DirectoryFilters) {
  return useQuery<DirectoryPage>({
    queryKey: ['companies', slug, 'all', filters],
    enabled: Boolean(slug),
    placeholderData: keepPreviousData,
    retry: false,
    queryFn: async () => (await api.get(`/courses/${slug}/companies`, { params: params(filters) })).data,
  })
}

// ── Admin ───────────────────────────────────────────────────────────────────

export interface AdminCompany extends Pick<DirectoryCompany, 'id' | 'name' | 'category' | 'city' | 'province' | 'phone' | 'website' | 'email'> {
  activity_code: string | null
  tax_id: string | null
  employees: number | null
  source: string
  is_visible: boolean
}

export interface AdminCompanyFilters {
  q?: string
  category?: string | null
  province?: number | null
  visible?: '0' | '1' | null
  page?: number
}

export interface AdminCompanyPage {
  data: AdminCompany[]
  meta: { page: number; last_page: number; total: number; visible: number; categories: string[] }
}

export function useAdminCompanies(filters: AdminCompanyFilters) {
  return useQuery<AdminCompanyPage>({
    queryKey: ['admin', 'companies', filters],
    placeholderData: keepPreviousData,
    queryFn: async () => (await api.get('/admin/companies', { params: params(filters) })).data,
  })
}

export function useSetCompanyVisibility() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, is_visible }: { id: number; is_visible: boolean }) => (await api.patch(`/admin/companies/${id}`, { is_visible })).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'companies'] }),
  })
}

/** Show or hide every company the filter finds. */
export function useBulkCompanyVisibility() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ filters, is_visible }: { filters: AdminCompanyFilters; is_visible: boolean }) => {
      // The whole filter, never the page: every company it finds changes.
      const rest = { ...filters, page: undefined }
      return (await api.post('/admin/companies/visibility', { ...params(rest), is_visible })).data as { data: { updated: number } }
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'companies'] }),
  })
}
