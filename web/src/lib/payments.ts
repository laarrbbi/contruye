import type { TFunction } from 'i18next'
import type { CheckoutMethod } from '@/api/orders'

/** Display order: the card first, then the two ways to pay from a bank app. */
const ORDER: readonly CheckoutMethod[] = ['stripe', 'manual_transfer', 'manual_bizum']

/** Paid by transfer: access opens once the owner has checked the receipt. */
export const TRANSFER_METHODS: readonly CheckoutMethod[] = ['manual_transfer', 'manual_bizum']

/**
 * The complete payment offer planned for launch, named for a sentence:
 * "tarjeta bancaria (Visa, Mastercard), transferencia bancaria o Bizum". The
 * owner decided (constitution I, amendment inherited from Ebni) that the sales
 * page presents all three before every gateway is configured,
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
