import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { CheckCircle2, Circle } from 'lucide-react'
import { useAdminAssistant, useAdminAssistantTraces, useGrantAssistantCredits } from '@/api/assistant'
import { apiErrorMessage } from '@/api/orders'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { cn, intlLocale } from '@/lib/utils'

const ROUTES = ['small', 'large', 'escalated', 'cache', 'blocked', 'refused', 'unavailable'] as const

function money(value: number, locale: string) {
  return new Intl.NumberFormat(intlLocale(locale), { style: 'currency', currency: 'USD', maximumFractionDigits: 4 }).format(value)
}

function dateTime(iso: string | null, locale: string) {
  return iso ? new Date(iso).toLocaleString(intlLocale(locale), { dateStyle: 'short', timeStyle: 'short' }) : '—'
}

/**
 * Nicolás for the owner: what is missing before students get it, what it
 * costs, each evaluation run, the trace of every question (never its text,
 * never the key), and credits for a student.
 */
export function AdminAssistantPage() {
  const { t, i18n } = useTranslation()
  const { data, isLoading, isError } = useAdminAssistant()
  const [route, setRoute] = useState<string | null>(null)
  const [flagged, setFlagged] = useState(false)
  const [page, setPage] = useState(1)
  const traces = useAdminAssistantTraces({ route, flagged, page })

  if (isLoading) return <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
  if (isError || !data) return <p className="py-12 text-center text-destructive">{t('common.error')}</p>

  const state = data.launched ? 'launched' : data.configured ? 'preview' : 'off'
  const checks = [
    { done: data.configured, label: t('admin.assistant.checks.key') },
    { done: data.enabled, label: t('admin.assistant.checks.enabled') },
    { done: data.index.chunks > 0, label: t('admin.assistant.checks.index', { chunks: data.index.chunks }) },
    { done: data.eval.passed, label: t('admin.assistant.checks.eval') },
    {
      done: data.budget.spent_today_usd < data.budget.daily_usd,
      label: t('admin.assistant.checks.budget', { spent: data.budget.spent_today_usd.toFixed(2), budget: data.budget.daily_usd.toFixed(0) }),
    },
  ]

  return (
    <div className="space-y-8">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="max-w-2xl space-y-2">
          <h1 className="text-2xl font-bold">{t('admin.assistant.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('admin.assistant.lead')}</p>
        </div>
        <Badge variant={state === 'launched' ? 'success' : state === 'preview' ? 'outline' : 'secondary'}>{t(`admin.assistant.state.${state}`)}</Badge>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <section className="rounded-lg border bg-card p-5">
          <h2 className="font-semibold">{t('admin.assistant.readiness')}</h2>
          <ul className="mt-3 space-y-2 text-sm">
            {checks.map((check) => (
              <li key={check.label} className="flex items-start gap-2">
                {check.done ? <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden /> : <Circle className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />}
                <span className={cn(!check.done && 'text-muted-foreground')}>{check.label}</span>
              </li>
            ))}
          </ul>
          <p className="mt-4 text-xs text-muted-foreground">{t('admin.assistant.howTo')}</p>
        </section>

        <section className="rounded-lg border bg-card p-5">
          <h2 className="font-semibold">{t('admin.assistant.models')}</h2>
          <dl className="mt-3 space-y-3 text-sm">
            {(['small', 'large'] as const).map((tier) => (
              <div key={tier}>
                <dt className="text-muted-foreground">{t(`admin.assistant.${tier}`)}</dt>
                <dd className="font-medium">
                  <span dir="ltr">{data.models[tier].id}</span> · {data.models[tier].effort} · {t('admin.assistant.creditsEach', { count: data.models[tier].credits })}
                </dd>
              </div>
            ))}
          </dl>
          <p className="mt-4 text-xs text-muted-foreground" dir="ltr">
            {t('admin.assistant.version', { prompt: data.prompt_version, index: data.index.version ?? '—' })}
          </p>
        </section>
      </div>

      <section className="space-y-3">
        <h2 className="font-semibold">{t('admin.assistant.last30')}</h2>
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
          <Stat label={t('admin.assistant.questions')} value={String(data.last_30_days.questions)} />
          <Stat label={t('admin.assistant.cost')} value={money(data.last_30_days.cost_usd, i18n.language)} />
          {data.last_30_days.by_route
            .filter((row) => row.route === 'cache' || row.route === 'large')
            .map((row) => (
              <Stat key={row.route} label={t(`admin.assistant.routes.${row.route}`, row.route)} value={String(row.count)} />
            ))}
        </div>
      </section>

      <section className="space-y-3">
        <h2 className="font-semibold">{t('admin.assistant.evals')}</h2>
        {data.eval.last === null ? (
          <p className="text-sm text-muted-foreground">{t('admin.assistant.evalNever')}</p>
        ) : (
          <p className="text-sm">
            <Badge variant={data.eval.last.passed ? 'success' : 'destructive'}>{data.eval.last.passed ? t('admin.assistant.evalPassed') : t('admin.assistant.evalFailed')}</Badge>{' '}
            {t('admin.assistant.evalScore', { passed: data.eval.last.passed_count, total: data.eval.last.total })} · {dateTime(data.eval.last.ran_at, i18n.language)}
            {data.eval.last.failures.length > 0 && <span className="block pt-1 text-muted-foreground">{t('admin.assistant.failures', { cases: data.eval.last.failures.join(', ') })}</span>}
          </p>
        )}
      </section>

      <section className="space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="font-semibold">{t('admin.assistant.traces')}</h2>
          <div className="flex flex-wrap items-center gap-3">
            <select
              value={route ?? ''}
              onChange={(event) => {
                setRoute(event.target.value || null)
                setPage(1)
              }}
              aria-label={t('admin.assistant.route')}
              className="h-10 rounded-md border border-input bg-background px-3 text-sm"
            >
              <option value="">{t('admin.assistant.allRoutes')}</option>
              {ROUTES.map((key) => (
                <option key={key} value={key}>
                  {t(`admin.assistant.routes.${key}`)}
                </option>
              ))}
            </select>
            <label className="flex items-center gap-2 text-sm">
              <Switch
                checked={flagged}
                onCheckedChange={(value) => {
                  setFlagged(value)
                  setPage(1)
                }}
              />
              {t('admin.assistant.flaggedOnly')}
            </label>
          </div>
        </div>

        {!traces.data || traces.data.data.length === 0 ? (
          <div className="rounded-lg border border-dashed py-12 text-center text-muted-foreground">{t('admin.assistant.noTraces')}</div>
        ) : (
          <div className="rounded-lg border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('admin.assistant.date')}</TableHead>
                  <TableHead>{t('admin.assistant.student')}</TableHead>
                  <TableHead>{t('admin.assistant.route')}</TableHead>
                  <TableHead>{t('admin.assistant.model')}</TableHead>
                  <TableHead>{t('admin.assistant.tokens')}</TableHead>
                  <TableHead>{t('admin.assistant.cost')}</TableHead>
                  <TableHead>{t('admin.assistant.credits')}</TableHead>
                  <TableHead>{t('admin.assistant.time')}</TableHead>
                  <TableHead>{t('admin.assistant.flags')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {traces.data.data.map((trace) => (
                  <TableRow key={trace.id}>
                    <TableCell className="whitespace-nowrap tabular-nums text-muted-foreground">{dateTime(trace.created_at, i18n.language)}</TableCell>
                    <TableCell dir="ltr" className="text-start">{trace.user?.email ?? '—'}</TableCell>
                    <TableCell>
                      {t(`admin.assistant.routes.${trace.route}`, trace.route)}
                      {trace.status !== 'ok' && <span className="block text-xs text-muted-foreground">{t(`admin.assistant.statuses.${trace.status}`, trace.status)}</span>}
                    </TableCell>
                    <TableCell dir="ltr" className="text-start text-xs">{trace.model ?? '—'}</TableCell>
                    <TableCell dir="ltr" className="text-start tabular-nums text-xs">
                      {trace.tokens.input} / {trace.tokens.output} / {trace.tokens.cache_read}
                    </TableCell>
                    <TableCell className="tabular-nums">{money(trace.cost_usd, i18n.language)}</TableCell>
                    <TableCell className="tabular-nums">{trace.credits}</TableCell>
                    <TableCell className="tabular-nums">{(trace.latency_ms / 1000).toFixed(1)} s</TableCell>
                    <TableCell dir="ltr" className="text-start text-xs text-muted-foreground">
                      {[...(trace.error ? [trace.error] : []), ...trace.flags.filter((flag) => !flag.startsWith('route:')), ...trace.tools.map((tool) => tool.name)].join(', ') || '—'}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
        {traces.data && traces.data.meta.last_page > 1 && (
          <div className="flex items-center justify-end gap-2">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              ‹
            </Button>
            <span className="text-sm tabular-nums text-muted-foreground">
              {traces.data.meta.current_page} / {traces.data.meta.last_page}
            </span>
            <Button variant="outline" size="sm" disabled={page >= traces.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
              ›
            </Button>
          </div>
        )}
      </section>

      <GrantCredits />
    </div>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0 rounded-lg border bg-card p-3 sm:p-4">
      <span className="block truncate text-xs text-muted-foreground sm:text-sm">{label}</span>
      <span className="mt-1 block text-2xl font-semibold tabular-nums">{value}</span>
    </div>
  )
}

function GrantCredits() {
  const { t } = useTranslation()
  const grant = useGrantAssistantCredits()
  const [email, setEmail] = useState('')
  const [credits, setCredits] = useState('')
  const [note, setNote] = useState('')

  const submit = (event: FormEvent) => {
    event.preventDefault()
    grant.mutate({ email: email.trim(), credits: Number(credits), note: note.trim() }, { onSuccess: () => setCredits('') })
  }

  return (
    <section className="rounded-lg border bg-card p-5">
      <h2 className="font-semibold">{t('admin.assistant.grant')}</h2>
      <p className="mt-1 text-sm text-muted-foreground">{t('admin.assistant.grantLead')}</p>
      <form onSubmit={submit} className="mt-4 grid gap-3 sm:grid-cols-[minmax(0,2fr)_8rem_minmax(0,2fr)_auto] sm:items-end">
        <div className="space-y-1.5">
          <Label htmlFor="grant-email">{t('admin.assistant.grantEmail')}</Label>
          <Input id="grant-email" type="email" required value={email} onChange={(event) => setEmail(event.target.value)} autoCapitalize="none" autoCorrect="off" />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="grant-credits">{t('admin.assistant.grantCredits')}</Label>
          <Input id="grant-credits" type="number" inputMode="numeric" required value={credits} onChange={(event) => setCredits(event.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="grant-note">{t('admin.assistant.grantNote')}</Label>
          <Input id="grant-note" required maxLength={160} value={note} onChange={(event) => setNote(event.target.value)} />
        </div>
        <Button type="submit" disabled={grant.isPending || !email || !credits || !note}>
          {t('admin.assistant.grantSubmit')}
        </Button>
      </form>
      {grant.isSuccess && (
        <p className="mt-3 text-sm text-emerald-700">
          {t('admin.assistant.granted', { credits: grant.data.credits, email: grant.data.user.email, balance: grant.data.balance })}
        </p>
      )}
      {grant.isError && <p className="mt-3 text-sm text-destructive">{apiErrorMessage(grant.error) ?? t('common.error')}</p>}
    </section>
  )
}
