import { useMutation, useQuery } from '@tanstack/react-query'
import { api } from './client'

/** What the public calculator offers: the template's communities and defaults, and the rates in force. */
export interface SolarOptions {
  template: { number: number; title: string }
  communities: string[]
  defaults: { community: string; price: number; seller_company: boolean; buildable_m2: number }
  /** Notaría, plusvalía and due diligence: the template's defaults, which the full template lets you change. */
  defaulted: { label: string; value: number; type: string }[]
  vat: number
  regions: { name: string; itp: number; ajd: number; note: string | null }[]
  reviewed_at: string | null
}

export interface SolarInput {
  community: string
  price: number
  seller_company: boolean
  buildable_m2: number
}

/** Three results of Plantilla 1; the rest of it is Module 1's. */
export interface SolarResult {
  land_cost: number
  per_m2: number
  tax_rate: number
  tax: 'ITP' | 'AJD'
}

export function useSolarOptions() {
  return useQuery<SolarOptions>({
    queryKey: ['solar-options'],
    staleTime: 10 * 60 * 1000,
    queryFn: async () => {
      const { data } = await api.get('/public/solar-calculator')
      return data.data as SolarOptions
    },
  })
}

export function useSolarCalculation() {
  return useMutation({
    mutationFn: async (input: SolarInput) => {
      const { data } = await api.post('/public/solar-calculator', input)
      return data.data as SolarResult
    },
  })
}
