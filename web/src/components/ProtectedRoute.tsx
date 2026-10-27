import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useUser } from '@/api/auth'

export function ProtectedRoute({ requireAdmin = false }: { requireAdmin?: boolean }) {
  const { data: user, isLoading } = useUser()
  const location = useLocation()
  const { t } = useTranslation()

  if (isLoading) {
    // Deliberately renders no visible placeholder. This guard resolves the
    // session, not the page — showing a centred line here means every
    // protected page flashes it and then swaps to its own skeleton, which is
    // the jump the skeletons exist to remove. Screen readers still hear it.
    return (
      <span role="status" aria-live="polite" className="sr-only">
        {t('common.loading')}
      </span>
    )
  }

  if (!user) {
    return <Navigate to="/login" state={{ from: location.pathname }} replace />
  }

  if (requireAdmin && user.role !== 'admin') {
    return <Navigate to="/dashboard" replace />
  }

  return <Outlet />
}
