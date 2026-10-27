import type { TFunction } from 'i18next'
import type { CheckoutMethod } from '@/api/orders'

/** Display order: the Algerian methods first, the international card last. */
const ORDER: readonly CheckoutMethod[] = ['chargily', 'manual_ccp', 'manual_baridimob', 'stripe']

/** Paid by transfer: access opens once the owner has checked the receipt. */
export const TRANSFER_METHODS: readonly CheckoutMethod[] = ['manual_ccp', 'manual_baridimob']

/**
 * The complete payment offer planned for launch, named for a sentence:
 * "carte CIB ou Edahabia, versement CCP, BaridiMob ou carte internationale
 * (Visa, Mastercard)". The owner decided (constitution I, amended 2026-10-09)
 * that the sales page presents all four before every gateway is configured,
 * since the gateways are wired just before launch; the checkout still offers
 * only those that can take money (GET /checkout/methods). The server words
 * them the same way (App\Support\PaymentWording), so the page, its FAQ and
 * its structured data say the same thing.
 *
 * `only` narrows the list, e.g. to the transfer methods.
 */
export function paymentMethodList(
  t: TFunction,
  { set = 'names', only = ORDER }: { set?: 'names' | 'short'; only?: readonly CheckoutMethod[] } = {},
): string {
  const names = ORDER.filter((method) => only.includes(method)).map((method) => t(`payments.${set}.${method}`))
  if (names.length === 1) return names[0]
  return `${names.slice(0, -1).join(t('payments.separator'))} ${t('payments.or')} ${names[names.length - 1]}`
}
