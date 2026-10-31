import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'

/** One input cell of the template, from its own dictionary (entradas.json). */
export interface TemplateInput {
  seccion: string
  hoja: string
  celda: string
  etiqueta: string
  tipo: 'numero' | 'porcentaje' | 'fecha' | 'texto' | 'SI/NO' | 'lista: bloque/chalets'
  unidad: string
  defecto: number | string | null
  nota: string
  defecto_es_formula: boolean
}

export interface TemplateInputs {
  plantilla: string
  regla: string
  fechas: string
  porcentajes: string
  entradas: TemplateInput[]
  ejemplos: { key: string; datos: InputValues }[]
}

/** { 'Datos de Partida': { C9: 24, D79: '2027-01-01', … } } */
export type InputValues = Record<string, Record<string, number | string | null>>

export interface SheetCell {
  v: number | string | boolean | null
  nf?: string
}

export interface Sheet {
  nombre: string
  dimension: string
  celdas: Record<string, SheetCell>
}

export interface CalculationResult {
  calculo: { iteraciones: number; cambio_maximo: number; convergido: boolean }
  resumen: { concepto: string; neto: number | string | null; con_iva?: number | string | null; iva?: number | string | null; pct?: number | string | null }[]
  vista: { celda: string; texto: string | null; valor?: number | string | null }[]
  hojas: Record<string, Sheet>
}

export interface Scenario {
  id: number
  name: string
  inputs: InputValues
  updated_at: string
}

/** The 17 sheets of PLANTILLA_LIBRE.xls, in the workbook's order. */
export const TEMPLATE_SHEETS = [
  'Datos de Partida',
  '2º Auxiliar',
  '3ª Terrenos',
  '3ª Construcción',
  '3ª Urbanización',
  '3ª Proyectos',
  'Direcciones',
  '3ª Licencias y Varios',
  '3ª Préstamo hipotecario',
  '3ª Gestión',
  '3ª Pólizas de Crédito',
  '3ª Coste Total Promoción',
  'Estudio Económico',
  '4º Auxiliar1',
  '5º Forma de Pago',
  '6ª Cash-flow',
  '8º Resumen',
] as const

export function useTemplateInputs(slug: string | undefined) {
  return useQuery<TemplateInputs>({
    queryKey: ['financials-inputs', slug],
    enabled: !!slug,
    retry: false,
    staleTime: Infinity,
    queryFn: async () => {
      const { data } = await api.get(`/courses/${slug}/financials/inputs`)
      return data.data as TemplateInputs
    },
  })
}

export function useCalculate(slug: string | undefined) {
  return useMutation({
    mutationFn: async ({ datos, hojas = [] }: { datos: InputValues; hojas?: string[] }) => {
      const { data } = await api.post(`/courses/${slug}/financials/calculate`, { datos, hojas })
      return data.data as CalculationResult
    },
  })
}

export function useScenarios(slug: string | undefined) {
  return useQuery<Scenario[]>({
    queryKey: ['financials-scenarios', slug],
    enabled: !!slug,
    queryFn: async () => {
      const { data } = await api.get(`/courses/${slug}/financials/scenarios`)
      return data.data as Scenario[]
    },
  })
}

export function useSaveScenario(slug: string | undefined) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, name, inputs }: { id?: number; name: string; inputs: InputValues }) => {
      const { data } = id
        ? await api.put(`/courses/${slug}/financials/scenarios/${id}`, { name, inputs })
        : await api.post(`/courses/${slug}/financials/scenarios`, { name, inputs })
      return data.data as Scenario
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['financials-scenarios', slug] }),
  })
}

export function useDeleteScenario(slug: string | undefined) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/courses/${slug}/financials/scenarios/${id}`)
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['financials-scenarios', slug] }),
  })
}
