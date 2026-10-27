import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api } from './client'

/** A province as embedded in a partner or user payload. */
export interface ProvinceRef {
  id: number
  code: number
  name: string
}

export interface Province extends ProvinceRef {
  name_es: string
  name_en: string
}

/**
 * The 58 provinces. Reference data that never changes within a session, so it is
 * fetched once and kept — the picker must open instantly.
 */
export function useProvinces(enabled = true) {
  const { i18n } = useTranslation()

  return useQuery<Province[]>({
    // `name` comes back localized, so the language belongs in the key.
    queryKey: ['provinces', i18n.language],
    enabled,
    staleTime: Infinity,
    gcTime: Infinity,
    queryFn: async () => {
      const { data } = await api.get('/provinces')
      return data.data as Province[]
    },
  })
}

/**
 * "Alger (16)" — the matricule stays visible, but the name leads so that
 * type-ahead in a native/Radix Select actually finds the province.
 */
export function provinceLabel(province: Pick<Province, 'code' | 'name'>) {
  return `${province.name} (${String(province.code).padStart(2, '0')})`
}

/** Accent- and case-insensitive match, so "bejaia" finds "Béjaïa". */
export function matchesProvinceSearch(province: Province, search: string) {
  const normalize = (value: string) =>
    value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()

  const needle = normalize(search.trim())
  if (needle === '') return true

  return (
    normalize(province.name_es).includes(needle) ||
    province.name_en.includes(search.trim()) ||
    String(province.code) === needle ||
    String(province.code).padStart(2, '0') === needle
  )
}

/**
 * Join province names for display. Arabic uses its own comma (U+060C) — the
 * Latin one reads as a stray glyph inside an RTL run.
 */
export function joinProvinceNames(provinces: Pick<ProvinceRef, 'name'>[], locale: string) {
  return provinces.map((province) => province.name).join(locale === 'ar' ? '، ' : ', ')
}
