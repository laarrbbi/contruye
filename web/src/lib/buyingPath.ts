// The pages between the sales page and payment. Lazily loaded by App.tsx and
// prefetched by main.tsx once the browser is idle, so they are already there
// when a visitor clicks "Acceder al curso".
export const buyingPath = {
  register: () => import('@/pages/RegisterPage'),
  login: () => import('@/pages/LoginPage'),
  checkout: () => import('@/pages/CheckoutPage'),
  manualPayment: () => import('@/pages/ManualPaymentPage'),
}

export function prefetchBuyingPath() {
  Object.values(buyingPath).forEach((load) => void load().catch(() => undefined))
}
