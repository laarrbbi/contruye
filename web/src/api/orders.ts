import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
  type UseQueryOptions,
} from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { api } from './client'
import type { Campaign, CourseDetail, DiscountTier, PublicModule } from './courses'

export type { DiscountTier }

export type CheckoutMethod = 'stripe' | 'manual_transfer' | 'manual_bizum'
export type OrderMethod = CheckoutMethod | 'cash'
export type OrderScope = 'full' | 'modules'
export type OrderStatus = 'pending' | 'awaiting_review' | 'paid' | 'failed' | 'refunded'
export type Currency = 'EUR'

/**
 * GET /courses/{slug} already carries per-module prices, discount tiers and
 * (for authenticated viewers) ownership flags — see {@link CourseDetail}. These
 * aliases are kept for the checkout page's pricing-focused call sites.
 */
export type PricedModule = PublicModule
export type PricedCourse = CourseDetail

export interface QuoteItem {
  module_id: number
  title: string
  unit_price: number
}

export interface UpgradeHint {
  modules_missing: number
  price_delta: number
}

/** POST /checkout/quote response — the single source of truth for pricing. */
export interface Quote {
  scope: OrderScope
  currency: Currency
  items: QuoteItem[]
  subtotal: number
  tier_percent: number
  tier_discount: number
  /** Running promotion applied to this quote, or null. */
  campaign: Campaign | null
  campaign_percent: number
  campaign_discount: number
  coupon_code: string | null
  coupon_percent: number
  coupon_discount: number
  total: number
  full_price: number
  full_price_anchor: number
  savings_vs_itemized: number
  upgrade_hint: UpgradeHint | null
  owned_module_ids: number[]
  upgraded_to_full: boolean
}

export interface QuotePayload {
  course_id: number
  scope: OrderScope
  module_ids?: number[]
  method?: CheckoutMethod
  coupon_code?: string
}

export function useQuote(payload: QuotePayload | null) {
  return useQuery<Quote>({
    queryKey: ['quote', payload],
    enabled: payload !== null,
    retry: false,
    placeholderData: keepPreviousData,
    queryFn: async () => {
      const { data } = await api.post('/checkout/quote', payload)
      return data.data as Quote
    },
  })
}

export interface PaymentInstructions {
  method: 'manual_transfer' | 'manual_bizum'
  amount: number
  currency: Currency
  /** The concept to write on the transfer or the Bizum, e.g. "CONSTRUYE-42". */
  reference: string
  iban: string | null
  holder: string | null
  bizum_phone: string | null
}

export interface OrderModuleLine {
  id: number
  title: string | null
  unit_price: number
}

export interface Order {
  id: number
  course: {
    id: number
    slug: string
    title: string
    cover_image: string | null
  }
  amount: number
  currency: Currency
  method: OrderMethod
  scope: OrderScope
  discount_percent: number
  modules?: OrderModuleLine[]
  status: OrderStatus
  admin_note: string | null
  paid_at: string | null
  created_at: string
  has_receipt: boolean
  /** Present once the admin has issued a factura; `url` is cookie-auth navigable. */
  invoice?: { number: string; issued_at: string; url: string } | null
  /** Present only for manual orders that are still actionable. */
  payment_instructions?: PaymentInstructions
}

export interface CheckoutPayload {
  course_id: number
  scope: OrderScope
  module_ids?: number[]
  method: CheckoutMethod
  coupon_code?: string
}

export interface CheckoutResponse {
  order: Order
  checkout_url?: string
  payment_instructions?: PaymentInstructions
}

/**
 * GET /checkout/methods — the methods that can take money right now. A gateway
 * without credentials is simply absent, so the page never offers it.
 */
export function useCheckoutMethods() {
  return useQuery<CheckoutMethod[]>({
    queryKey: ['checkout-methods'],
    queryFn: async () => {
      const { data } = await api.get('/checkout/methods')
      return data.data as CheckoutMethod[]
    },
    staleTime: 5 * 60 * 1000,
  })
}

export function useCheckout() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (payload: CheckoutPayload) => {
      const { data } = await api.post('/checkout', payload)
      return data.data as CheckoutResponse
    },
    onSuccess: (res) => {
      // Pre-seed the order cache so the manual-payment page renders instantly.
      qc.setQueryData(['order', res.order.id], res.order)
      qc.invalidateQueries({ queryKey: ['orders'] })
    },
  })
}

export function useOrders() {
  return useQuery<Order[]>({
    queryKey: ['orders'],
    queryFn: async () => {
      const { data } = await api.get('/orders')
      return data.data as Order[]
    },
  })
}

export function useOrder(
  orderId: number | null,
  refetchInterval?: UseQueryOptions<Order>['refetchInterval'],
) {
  return useQuery<Order>({
    queryKey: ['order', orderId],
    enabled: orderId !== null,
    refetchInterval,
    queryFn: async () => {
      const { data } = await api.get(`/orders/${orderId}`)
      return data.data as Order
    },
  })
}

export function useUploadReceipt(orderId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (file: File) => {
      const form = new FormData()
      form.append('receipt', file)
      const { data } = await api.post(`/orders/${orderId}/receipt`, form)
      return data.data as Order
    },
    onSuccess: (order) => {
      qc.setQueryData(['order', orderId], order)
      qc.invalidateQueries({ queryKey: ['orders'] })
    },
  })
}

interface ApiValidationBody {
  message?: string
  errors?: Record<string, string[]>
}

/** First validation message for a given field of a Laravel 422 response. */
export function apiFieldError(error: unknown, field: string): string | undefined {
  if (!isAxiosError(error)) return undefined
  const body = error.response?.data as ApiValidationBody | undefined
  return body?.errors?.[field]?.[0]
}

/** Top-level `message` of an API error response, when present. */
export function apiErrorMessage(error: unknown): string | undefined {
  if (!isAxiosError(error)) return undefined
  const body = error.response?.data as ApiValidationBody | undefined
  return body?.message
}
