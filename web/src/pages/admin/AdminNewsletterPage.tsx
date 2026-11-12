import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Download } from 'lucide-react'
import { NEWSLETTER_EXPORT_URL, useAdminNewsletter, type NewsletterStatus } from '@/api/newsletter'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { cn } from '@/lib/utils'
import { formatDate } from './lib'

const STATUSES: NewsletterStatus[] = ['confirmed', 'pending', 'unsubscribed']
const VARIANT: Record<NewsletterStatus, 'success' | 'outline' | 'secondary'> = { confirmed: 'success', pending: 'outline', unsubscribed: 'secondary' }

/**
 * The newsletter's subscribers: how many confirmed, who, from which page, and
 * the confirmed ones as a CSV (each with its unsubscribe link) for the tool
 * the owner sends with.
 */
export function AdminNewsletterPage() {
  const { t, i18n } = useTranslation()
  const [status, setStatus] = useState<NewsletterStatus | null>(null)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useAdminNewsletter({ status, search, page })
  const counts = data?.meta.counts ?? {}

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="max-w-2xl space-y-2">
          <h1 className="text-2xl font-bold">{t('admin.newsletter.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('admin.newsletter.lead')}</p>
        </div>
        <Button asChild variant="outline">
          <a href={NEWSLETTER_EXPORT_URL}>
            <Download className="h-4 w-4" />
            {t('admin.newsletter.export')}
          </a>
        </Button>
      </div>

      <div className="grid gap-3 sm:grid-cols-3">
        {STATUSES.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => {
              setStatus((current) => (current === key ? null : key))
              setPage(1)
            }}
            aria-pressed={status === key}
            className={cn('rounded-lg border bg-card p-4 text-start transition-colors hover:bg-muted', status === key && 'border-primary ring-1 ring-primary')}
          >
            <span className="block text-sm text-muted-foreground">{t(`admin.newsletter.${key}`)}</span>
            <span className="mt-1 block text-2xl font-semibold tabular-nums">{counts[key] ?? 0}</span>
          </button>
        ))}
      </div>

      <Input
        type="search"
        value={search}
        onChange={(event) => {
          setSearch(event.target.value)
          setPage(1)
        }}
        placeholder={t('admin.newsletter.search')}
        aria-label={t('admin.newsletter.search')}
        className="max-w-sm"
      />

      {isLoading ? (
        <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
      ) : isError ? (
        <p className="py-12 text-center text-destructive">{t('common.error')}</p>
      ) : !data || data.data.length === 0 ? (
        <div className="rounded-lg border border-dashed py-16 text-center text-muted-foreground">{t('admin.newsletter.empty')}</div>
      ) : (
        <div className="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('admin.newsletter.email')}</TableHead>
                <TableHead>{t('admin.newsletter.status')}</TableHead>
                <TableHead>{t('admin.newsletter.source')}</TableHead>
                <TableHead>{t('admin.newsletter.date')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.data.map((subscriber) => (
                <TableRow key={subscriber.id}>
                  <TableCell dir="ltr" className="text-start font-medium">{subscriber.email}</TableCell>
                  <TableCell>
                    <Badge variant={VARIANT[subscriber.status]}>{t(`admin.newsletter.${subscriber.status}`)}</Badge>
                  </TableCell>
                  <TableCell className="text-muted-foreground">
                    {subscriber.source ? t(`admin.newsletter.sources.${subscriber.source}`) : '—'}
                  </TableCell>
                  <TableCell className="tabular-nums text-muted-foreground">
                    {formatDate(subscriber.confirmed_at ?? subscriber.created_at, i18n.language)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            ‹
          </Button>
          <span className="text-sm tabular-nums text-muted-foreground">
            {data.meta.current_page} / {data.meta.last_page}
          </span>
          <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
            ›
          </Button>
        </div>
      )}
    </div>
  )
}
