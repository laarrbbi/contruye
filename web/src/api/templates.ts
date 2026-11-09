import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'
import type { InputValues, Sheet } from './financials'

/** One input cell of a template, from its own dictionary (entradas.json). */
export interface SmallTemplateInput {
  seccion: string
  hoja: string
  celda: string
  etiqueta: string
  tipo: string
  unidad: string
  defecto: number | string | null
  nota: string
  defecto_es_formula: boolean
  opciones?: string[]
}

/** The legal parameters every template applies: the same for everyone, set by the admin. Fractions (0.21 = 21 %). */
export interface LegalParametersView {
  parameters: {
    code: string
    label: string
    cell: string
    value: number
    original_value: number | null
    legal_basis: string
    /** legal_basis as inline HTML, its laws linked to their official texts. */
    legal_basis_html: string | null
    source: string | null
    used_in: string
  }[]
  regions: { name: string; itp: number; ajd: number; note: string | null; note_html: string | null }[]
  /** ISO date or date-time of the last review. */
  reviewed_at: string | null
}

export interface SmallTemplateSummary {
  number: number
  title: string
  module_id: number | null
  module_order: number
  is_locked: boolean
}

export interface SmallTemplate {
  number: number
  title: string
  module_id: number
  /** The file name of the template in Excel. */
  excel: string
  regla: string
  porcentajes: string
  entradas: SmallTemplateInput[]
  legal: LegalParametersView
}

export interface SmallTemplateResult {
  calculo: { iteraciones: number; convergido: boolean }
  parametros: Record<string, unknown>
  hoja: Sheet
}

/** The extra at the end of the course, as a student's page offers it. */
export interface ExtraView {
  on_sale: boolean
  price_eur: number | null
  owned: boolean
  module_id: number | null
}

export function useSmallTemplate(slug: string | undefined, number: number | undefined) {
  return useQuery<SmallTemplate>({
    queryKey: ['small-template', slug, number],
    enabled: !!slug && !!number,
    retry: false,
    staleTime: 5 * 60_000,
    queryFn: async () => {
      const { data } = await api.get(`/courses/${slug}/templates/${number}`)
      return data.data as SmallTemplate
    },
  })
}

export function useCalculateSmallTemplate(slug: string | undefined, number: number | undefined) {
  return useMutation({
    mutationFn: async (datos: InputValues) => {
      const { data } = await api.post(`/courses/${slug}/templates/${number}/calculate`, { datos })
      return data.data as SmallTemplateResult
    },
  })
}

export function useExtra(slug: string | undefined, enabled = true) {
  return useQuery<ExtraView>({
    queryKey: ['extra', slug],
    enabled: !!slug && enabled,
    retry: false,
    queryFn: async () => {
      const { data } = await api.get(`/courses/${slug}/extra`)
      return data.data as ExtraView
    },
  })
}

/** A template in Excel with the user's inputs and the legal parameters in force, saved by the browser. */
export async function downloadWorkbook(path: string, datos: InputValues, filename: string) {
  const response = await api.post(path, { datos }, { responseType: 'blob' })
  const url = URL.createObjectURL(response.data as Blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 10_000)
}

// ── Admin: the «Plantillas» page ────────────────────────────────────────────

export interface LegalChange {
  id: number
  subject: string
  field: 'value' | 'itp' | 'ajd'
  old_value: number
  new_value: number
  by: string | null
  at: string | null
}

export interface AdminTemplates {
  extra: { enabled: boolean; price_eur: number | null; on_sale: boolean; buyers: number }
  legal: LegalParametersView & { reviewed_by: string | null; history: LegalChange[] }
}

export function useAdminTemplates() {
  return useQuery<AdminTemplates>({
    queryKey: ['admin-templates'],
    queryFn: async () => {
      const { data } = await api.get('/admin/templates')
      return data.data as AdminTemplates
    },
  })
}

export function useUpdateExtra() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (body: { enabled: boolean; price_eur: number | null }) => {
      const { data } = await api.put('/admin/templates/extra', body)
      return data.data as AdminTemplates
    },
    onSuccess: (data) => qc.setQueryData(['admin-templates'], data),
  })
}

export function useUpdateLegal() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (body: {
      parameters: Record<string, number>
      regions: Record<string, { itp: number; ajd: number }>
    }) => {
      const { data } = await api.put('/admin/templates/legal', { ...body, confirmed: true })
      return data.data as AdminTemplates
    },
    onSuccess: (data) => qc.setQueryData(['admin-templates'], data),
  })
}
