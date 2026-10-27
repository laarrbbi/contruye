import { useQuery } from '@tanstack/react-query'
import { api } from './client'

/**
 * The business behind the site, as the public may see it. Every field is null
 * until the owner fills it in the admin — and the UI renders nothing for a
 * null, never a placeholder.
 */
export interface BusinessProfile {
  business_name: string | null
  legal_form: string | null
  capital: string | null
  address: string | null
  rc: string | null
  nif: string | null
  phone: string | null
  whatsapp: string | null
  email: string | null
  support_hours: string | null
  /** https://wa.me/213…, ready to use. */
  whatsapp_url: string | null
  /** tel:+213…, ready to use. */
  phone_tel: string | null
}

export function useBusinessProfile() {
  return useQuery<BusinessProfile>({
    queryKey: ['business'],
    queryFn: async () => {
      const { data } = await api.get('/business')
      return data.data as BusinessProfile
    },
    // Changes rarely; one fetch serves every page of a visit.
    staleTime: 5 * 60 * 1000,
  })
}

/** True when at least one way to reach a human has been configured. */
export function hasContactChannel(profile: BusinessProfile | undefined): boolean {
  return Boolean(profile?.whatsapp_url || profile?.phone_tel || profile?.email)
}
