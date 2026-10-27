import type { WilayaRef } from '@/api/wilayas'

/** Sentinel: Radix Select forbids an empty item value. */
export const ALL_WILAYAS = 'all'

export interface FilterablePartner {
  is_national: boolean
  wilayas: WilayaRef[]
}

/**
 * Does this partner operate in the chosen wilaya? A national partner always
 * does — that is the whole point of the flag.
 */
export function partnerCoversWilaya(partner: FilterablePartner, wilayaId: number | null) {
  if (wilayaId === null) return true

  return partner.is_national || partner.wilayas.some((wilaya) => wilaya.id === wilayaId)
}
