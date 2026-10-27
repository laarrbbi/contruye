import type { ProvinceRef } from '@/api/provinces'

/** Sentinel: Radix Select forbids an empty item value. */
export const ALL_PROVINCES = 'all'

export interface FilterablePartner {
  is_national: boolean
  provinces: ProvinceRef[]
}

/**
 * Does this partner operate in the chosen province? A national partner always
 * does — that is the whole point of the flag.
 */
export function partnerCoversProvince(partner: FilterablePartner, provinceId: number | null) {
  if (provinceId === null) return true

  return partner.is_national || partner.provinces.some((province) => province.id === provinceId)
}
