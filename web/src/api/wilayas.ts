import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api } from './client'

/** A wilaya as embedded in a partner or user payload. */
export interface WilayaRef {
  id: number
  code: number
  name: string
}

export interface Wilaya extends WilayaRef {
  name_fr: string
  name_ar: string
}

/**
 * The 58 wilayas. Reference data that never changes within a session, so it is
 * fetched once and kept — the picker must open instantly.
 */
export function useWilayas(enabled = true) {
  const { i18n } = useTranslation()

  return useQuery<Wilaya[]>({
    // `name` comes back localized, so the language belongs in the key.
    queryKey: ['wilayas', i18n.language],
    enabled,
    staleTime: Infinity,
    gcTime: Infinity,
    queryFn: async () => {
      const { data } = await api.get('/wilayas')
      return data.data as Wilaya[]
    },
  })
}

/**
 * "Alger (16)" — the matricule stays visible, but the name leads so that
 * type-ahead in a native/Radix Select actually finds the wilaya.
 */
export function wilayaLabel(wilaya: Pick<Wilaya, 'code' | 'name'>) {
  return `${wilaya.name} (${String(wilaya.code).padStart(2, '0')})`
}

/** Accent- and case-insensitive match, so "bejaia" finds "Béjaïa". */
export function matchesWilayaSearch(wilaya: Wilaya, search: string) {
  const normalize = (value: string) =>
    value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()

  const needle = normalize(search.trim())
  if (needle === '') return true

  return (
    normalize(wilaya.name_fr).includes(needle) ||
    wilaya.name_ar.includes(search.trim()) ||
    String(wilaya.code) === needle ||
    String(wilaya.code).padStart(2, '0') === needle
  )
}

/**
 * Join wilaya names for display. Arabic uses its own comma (U+060C) — the
 * Latin one reads as a stray glyph inside an RTL run.
 */
export function joinWilayaNames(wilayas: Pick<WilayaRef, 'name'>[], locale: string) {
  return wilayas.map((wilaya) => wilaya.name).join(locale === 'ar' ? '، ' : ', ')
}
