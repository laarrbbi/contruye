import { lazy, type ReactNode } from 'react'
import { buyingPath } from '@/lib/buyingPath'
import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { DirectionProvider } from '@radix-ui/react-direction'
import { useTranslation } from 'react-i18next'
import { Layout } from '@/components/layout/Layout'
import { ProtectedRoute } from '@/components/ProtectedRoute'
import { ErrorBoundary } from '@/components/ErrorBoundary'
import { PageTitleProvider } from '@/components/PageTitleProvider'
import { PublicLocale } from '@/components/PublicLocale'
import type { Locale } from '@/i18n'
import { CourseLandingPage } from '@/pages/CourseLandingPage'

// Only the sales page is in the main bundle: it is what a visitor from a
// shared link opens first, often on a mobile connection. Every other page is
// its own chunk; the ones on the way to paying (sign-up, login, checkout, the
// CCP screen) are prefetched as soon as the browser is idle, so the click on
// "Accéder à la formation" still opens instantly. The admin and the player are
// never downloaded by a visitor at all.
const RegisterPage = lazy(() => buyingPath.register().then((m) => ({ default: m.RegisterPage })))
const LoginPage = lazy(() => buyingPath.login().then((m) => ({ default: m.LoginPage })))
const CheckoutPage = lazy(() => buyingPath.checkout().then((m) => ({ default: m.CheckoutPage })))
const ManualPaymentPage = lazy(() => buyingPath.manualPayment().then((m) => ({ default: m.ManualPaymentPage })))
const NotFoundPage = lazy(() => import('@/pages/NotFoundPage').then((m) => ({ default: m.NotFoundPage })))
const HomePage = lazy(() => import('@/pages/HomePage').then((m) => ({ default: m.HomePage })))
const ForgotPasswordPage = lazy(() => import('@/pages/ForgotPasswordPage').then((m) => ({ default: m.ForgotPasswordPage })))
const ResetPasswordPage = lazy(() => import('@/pages/ResetPasswordPage').then((m) => ({ default: m.ResetPasswordPage })))
const DashboardPage = lazy(() => import('@/pages/DashboardPage').then((m) => ({ default: m.DashboardPage })))
const CheckoutSuccessPage = lazy(() => import('@/pages/CheckoutSuccessPage').then((m) => ({ default: m.CheckoutSuccessPage })))
const CheckoutFailurePage = lazy(() => import('@/pages/CheckoutFailurePage').then((m) => ({ default: m.CheckoutFailurePage })))
const MyOrdersPage = lazy(() => import('@/pages/MyOrdersPage').then((m) => ({ default: m.MyOrdersPage })))
const PlayerPage = lazy(() => import('@/pages/PlayerPage').then((m) => ({ default: m.PlayerPage })))
const ChecklistsPage = lazy(() => import('@/pages/ChecklistsPage').then((m) => ({ default: m.ChecklistsPage })))
const AdminLayout = lazy(() => import('@/pages/admin/AdminLayout').then((m) => ({ default: m.AdminLayout })))
const AdminDashboardPage = lazy(() => import('@/pages/admin/AdminDashboardPage').then((m) => ({ default: m.AdminDashboardPage })))
const AdminCoursesPage = lazy(() => import('@/pages/admin/AdminCoursesPage').then((m) => ({ default: m.AdminCoursesPage })))
const AdminCourseContentPage = lazy(() => import('@/pages/admin/AdminCourseContentPage').then((m) => ({ default: m.AdminCourseContentPage })))
const AdminCouponsPage = lazy(() => import('@/pages/admin/AdminCouponsPage').then((m) => ({ default: m.AdminCouponsPage })))
const AdminOrdersPage = lazy(() => import('@/pages/admin/AdminOrdersPage').then((m) => ({ default: m.AdminOrdersPage })))
const AdminStudentsPage = lazy(() => import('@/pages/admin/AdminStudentsPage').then((m) => ({ default: m.AdminStudentsPage })))
const AdminReviewsPage = lazy(() => import('@/pages/admin/AdminReviewsPage').then((m) => ({ default: m.AdminReviewsPage })))
const AdminInvoicesPage = lazy(() => import('@/pages/admin/AdminInvoicesPage').then((m) => ({ default: m.AdminInvoicesPage })))
const AdminBusinessPage = lazy(() => import('@/pages/admin/AdminBusinessPage').then((m) => ({ default: m.AdminBusinessPage })))
const AdminCampaignsPage = lazy(() => import('@/pages/admin/AdminCampaignsPage').then((m) => ({ default: m.AdminCampaignsPage })))
const AdminPartnersPage = lazy(() => import('@/pages/admin/AdminPartnersPage').then((m) => ({ default: m.AdminPartnersPage })))

const PUBLIC_LOCALES: { prefix: string; locale: Locale }[] = [
  { prefix: '/', locale: 'fr' },
  { prefix: '/ar', locale: 'ar' },
  { prefix: '/en', locale: 'en' },
]

/**
 * Keeps Radix primitives (Tabs, DropdownMenu, Select…) in sync with the
 * active language: their internal keyboard/alignment logic follows this
 * dir, not the <html> attribute. Re-renders on i18n.changeLanguage.
 */
function AppDirectionProvider({ children }: { children: ReactNode }) {
  const { i18n } = useTranslation()

  return <DirectionProvider dir={i18n.language === 'ar' ? 'rtl' : 'ltr'}>{children}</DirectionProvider>
}

export default function App() {
  return (
    <ErrorBoundary>
      <AppDirectionProvider>
        <BrowserRouter>
          <PageTitleProvider>
          <Routes>
            <Route element={<Layout />}>
              {/* The pages search engines index, once per language: French at
                  the root, Arabic under /ar, English under /en
                  (src/i18n/paths.ts). The sales page IS the home page;
                  /courses/:slug still resolves to it. The server renders these
                  same URLs with their own head (api/routes/seo.php). */}
              {PUBLIC_LOCALES.map(({ prefix, locale }) => (
                <Route key={locale} path={prefix} element={<PublicLocale locale={locale} />}>
                  <Route index element={<CourseLandingPage />} />
                  <Route path="formations" element={<HomePage />} />
                  <Route path="courses/:slug" element={<CourseLandingPage />} />
                </Route>
              ))}
              {/* The app's own screens. deploy/nginx/ebni.conf lists them too:
                  served with 200, anything unknown answers 404. */}
              <Route path="/login" element={<LoginPage />} />
              <Route path="/register" element={<RegisterPage />} />
              <Route path="/forgot-password" element={<ForgotPasswordPage />} />
              <Route path="/reset-password" element={<ResetPasswordPage />} />

              <Route element={<ProtectedRoute />}>
                <Route path="/dashboard" element={<DashboardPage />} />
                <Route path="/learn/:slug" element={<PlayerPage />} />
                <Route path="/learn/:slug/lessons/:lessonId" element={<PlayerPage />} />
                <Route path="/learn/:slug/checklists" element={<ChecklistsPage />} />
                <Route path="/checkout/success" element={<CheckoutSuccessPage />} />
                <Route path="/checkout/failure" element={<CheckoutFailurePage />} />
                <Route path="/checkout/:slug" element={<CheckoutPage />} />
                <Route path="/orders" element={<MyOrdersPage />} />
                <Route path="/orders/:id/pay" element={<ManualPaymentPage />} />
              </Route>

              <Route element={<ProtectedRoute requireAdmin />}>
                <Route path="/admin" element={<AdminLayout />}>
                  <Route index element={<AdminDashboardPage />} />
                  <Route path="courses" element={<AdminCoursesPage />} />
                  <Route path="courses/:id/content" element={<AdminCourseContentPage />} />
                  <Route path="orders" element={<AdminOrdersPage />} />
                  <Route path="invoices" element={<AdminInvoicesPage />} />
                  <Route path="business" element={<AdminBusinessPage />} />
                  <Route path="students" element={<AdminStudentsPage />} />
                  <Route path="reviews" element={<AdminReviewsPage />} />
                  <Route path="campaigns" element={<AdminCampaignsPage />} />
                  <Route path="partners" element={<AdminPartnersPage />} />
                  <Route path="coupons" element={<AdminCouponsPage />} />
                </Route>
              </Route>

              <Route path="*" element={<NotFoundPage />} />
            </Route>
          </Routes>
          </PageTitleProvider>
        </BrowserRouter>
      </AppDirectionProvider>
    </ErrorBoundary>
  )
}
