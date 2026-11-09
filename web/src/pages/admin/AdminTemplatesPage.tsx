import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { Check, History, Landmark, Sparkles } from 'lucide-react'
import { useAdminTemplates, useUpdateExtra, useUpdateLegal, type AdminTemplates } from '@/api/templates'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { parseLocaleNumber } from '@/lib/sheet'
import { cn, formatPercent, intlLocale } from '@/lib/utils'

/** A fraction as the percentage the admin types: 0.0325 → «3,25». */
const toPercentText = (value: number) => String(Math.round(value * 100 * 1e6) / 1e6).replace('.', ',')

function apiError(error: unknown): string | null {
  if (!isAxiosError(error)) return null
  const errors = error.response?.data?.errors as Record<string, string[]> | undefined
  return (errors && Object.values(errors)[0]?.[0]) ?? error.response?.data?.message ?? null
}

/**
 * The templates: the extra at the end of the course (the professional
 * template) on sale or not, and the legal parameters every template
 * calculates with, kept up to date when the law changes, each change kept.
 */
export function AdminTemplatesPage() {
  const { t } = useTranslation()
  const { data, isLoading } = useAdminTemplates()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="titre text-2xl">{t('admin.templates.title')}</h1>
        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">{t('admin.templates.lead')}</p>
      </div>
      {isLoading || !data ? (
        <Skeleton className="h-96 w-full rounded-2xl" />
      ) : (
        <>
          <ExtraCard extra={data.extra} />
          <LegalCard legal={data.legal} />
          <HistoryCard legal={data.legal} />
        </>
      )}
    </div>
  )
}

function ExtraCard({ extra }: { extra: AdminTemplates['extra'] }) {
  const { t } = useTranslation()
  const update = useUpdateExtra()
  const [enabled, setEnabled] = useState(extra.enabled)
  const [price, setPrice] = useState(extra.price_eur === null ? '' : String(extra.price_eur))

  const parsed = price.trim() === '' ? null : Number(price)
  const priceValid = parsed === null || (Number.isInteger(parsed) && parsed >= 1)
  const changed = enabled !== extra.enabled || parsed !== extra.price_eur

  return (
    <section className="space-y-5 rounded-2xl border bg-card p-6">
      <div className="flex flex-wrap items-center gap-3">
        <Sparkles className="h-5 w-5 text-or-fonce" />
        <h2 className="text-lg font-semibold">{t('admin.templates.extraTitle')}</h2>
        <Badge variant={extra.on_sale ? 'default' : 'outline'}>{extra.on_sale ? t('admin.templates.onSale') : t('admin.templates.offSale')}</Badge>
        <span className="text-xs text-muted-foreground">{t('admin.templates.buyers', { count: extra.buyers })}</span>
      </div>
      <p className="max-w-3xl text-sm text-muted-foreground">{t('admin.templates.extraLead')}</p>
      <p className="max-w-3xl rounded-md border border-warning/40 bg-warning/10 px-3 py-2 text-xs">{t('admin.templates.extraAuthorise')}</p>
      <div className="flex flex-wrap items-end gap-6">
        <div className="flex items-center gap-3">
          <Switch id="extra-enabled" checked={enabled} onCheckedChange={setEnabled} />
          <Label htmlFor="extra-enabled">{t('admin.templates.enabled')}</Label>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="extra-price">{t('admin.templates.price')}</Label>
          <Input
            id="extra-price"
            className="w-36"
            inputMode="numeric"
            value={price}
            placeholder="[COMPLETAR]"
            aria-invalid={!priceValid}
            onChange={(e) => setPrice(e.target.value)}
          />
        </div>
        <Button
          disabled={!changed || !priceValid || (enabled && parsed === null) || update.isPending}
          onClick={() => update.mutate({ enabled, price_eur: parsed })}
        >
          {update.isSuccess && !changed ? <Check className="h-4 w-4" /> : null}
          {t('admin.templates.save')}
        </Button>
      </div>
      {enabled && parsed === null && <p className="text-sm text-destructive">{t('admin.templates.priceRequired')}</p>}
      {update.isError && <p className="text-sm text-destructive">{apiError(update.error) ?? t('common.error')}</p>}
    </section>
  )
}

type Draft = { parameters: Record<string, string>; regions: Record<string, { itp: string; ajd: string }> }

function draftOf(legal: AdminTemplates['legal']): Draft {
  return {
    parameters: Object.fromEntries(legal.parameters.map((p) => [p.code, toPercentText(p.value)])),
    regions: Object.fromEntries(legal.regions.map((r) => [r.name, { itp: toPercentText(r.itp), ajd: toPercentText(r.ajd) }])),
  }
}

/** A percentage typed by the admin, if it is one from 0 to under 100. */
function percentOf(text: string): number | null {
  const value = parseLocaleNumber(text.trim())
  return value !== null && value >= 0 && value < 100 ? value : null
}

function LegalCard({ legal }: { legal: AdminTemplates['legal'] }) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language
  const update = useUpdateLegal()
  const [draft, setDraft] = useState<Draft>(() => draftOf(legal))
  const [confirming, setConfirming] = useState(false)

  // A save returns the stored values: the form shows them.
  useEffect(() => setDraft(draftOf(legal)), [legal])

  const invalid = useMemo(() => {
    const bad = new Set<string>()
    for (const [code, text] of Object.entries(draft.parameters)) if (percentOf(text) === null) bad.add(code)
    for (const [name, rates] of Object.entries(draft.regions)) {
      if (percentOf(rates.itp) === null) bad.add(`${name}.itp`)
      if (percentOf(rates.ajd) === null) bad.add(`${name}.ajd`)
    }
    return bad
  }, [draft])

  /** What the save would change, before and after, for the confirmation. */
  const changes = useMemo(() => {
    const out: { label: string; before: number; after: number }[] = []
    for (const p of legal.parameters) {
      const after = percentOf(draft.parameters[p.code] ?? '')
      if (after !== null && Math.abs(after / 100 - p.value) > 1e-9) out.push({ label: p.label, before: p.value, after: after / 100 })
    }
    for (const r of legal.regions) {
      for (const field of ['itp', 'ajd'] as const) {
        const after = percentOf(draft.regions[r.name]?.[field] ?? '')
        if (after !== null && Math.abs(after / 100 - r[field]) > 1e-9) out.push({ label: `${r.name} · ${field.toUpperCase()}`, before: r[field], after: after / 100 })
      }
    }
    return out
  }, [draft, legal])

  const reviewed = legal.reviewed_at
    ? new Intl.DateTimeFormat(intlLocale(locale), { dateStyle: 'long' }).format(new Date(legal.reviewed_at))
    : null

  const save = () =>
    update.mutate(
      {
        parameters: Object.fromEntries(Object.entries(draft.parameters).map(([code, text]) => [code, percentOf(text) as number])),
        regions: Object.fromEntries(
          Object.entries(draft.regions).map(([name, rates]) => [name, { itp: percentOf(rates.itp) as number, ajd: percentOf(rates.ajd) as number }]),
        ),
      },
      { onSettled: () => setConfirming(false) },
    )

  const cell = (key: string, value: string, onChange: (value: string) => void, label: string) => (
    <span className="relative inline-block">
      <Input
        className={cn('h-8 w-24 pe-6 text-end tabular-nums', invalid.has(key) && 'border-destructive')}
        inputMode="decimal"
        value={value}
        aria-label={label}
        aria-invalid={invalid.has(key)}
        onChange={(e) => onChange(e.target.value)}
      />
      <span aria-hidden className="pointer-events-none absolute inset-y-0 end-2 flex items-center text-xs text-muted-foreground">%</span>
    </span>
  )

  return (
    <section className="space-y-5 rounded-2xl border bg-card p-6">
      <div className="flex flex-wrap items-center gap-3">
        <Landmark className="h-5 w-5 text-or-fonce" />
        <h2 className="text-lg font-semibold">{t('admin.templates.legalTitle')}</h2>
        {reviewed && (
          <span className="text-xs text-muted-foreground">
            {legal.reviewed_by ? t('admin.templates.reviewedBy', { date: reviewed, name: legal.reviewed_by }) : t('legalParams.reviewed', { date: reviewed })}
          </span>
        )}
      </div>
      <p className="max-w-3xl text-sm text-muted-foreground">{t('admin.templates.legalLead')}</p>

      <div className="overflow-x-auto rounded-lg border">
        <table className="w-full text-sm" lang="es">
          <thead className="bg-muted/50 text-xs text-muted-foreground">
            <tr>
              <th className="px-3 py-2 text-start font-medium">{t('admin.templates.parameter')}</th>
              <th className="px-3 py-2 text-start font-medium">{t('admin.templates.value')}</th>
              <th className="px-3 py-2 text-start font-medium">{t('admin.templates.original')}</th>
              <th className="px-3 py-2 text-start font-medium">{t('admin.templates.basis')}</th>
              <th className="px-3 py-2 text-start font-medium">{t('admin.templates.usedIn')}</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {legal.parameters.map((p) => (
              <tr key={p.code} className="align-top">
                <td className="px-3 py-2">
                  <span className="font-medium">{p.label}</span>
                  <span className="block font-mono text-[11px] text-muted-foreground">{p.code} · {p.cell}</span>
                </td>
                <td className="px-3 py-2">
                  {cell(p.code, draft.parameters[p.code] ?? '', (value) => setDraft((d) => ({ ...d, parameters: { ...d.parameters, [p.code]: value } })), p.label)}
                </td>
                <td className="whitespace-nowrap px-3 py-2 tabular-nums text-muted-foreground">
                  {p.original_value === null ? '—' : formatPercent(p.original_value, locale)}
                </td>
                <td className="min-w-[12rem] px-3 py-2 text-xs">
                  {p.legal_basis}
                  {p.source && <span className="block text-muted-foreground">{p.source}</span>}
                </td>
                <td className="min-w-[14rem] px-3 py-2 text-xs text-muted-foreground">{p.used_in}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <h3 className="text-sm font-semibold">{t('admin.templates.regionsTitle')}</h3>
      <div className="overflow-x-auto rounded-lg border">
        <table className="w-full text-sm" lang="es">
          <thead className="bg-muted/50 text-xs text-muted-foreground">
            <tr>
              <th className="px-3 py-2 text-start font-medium">{t('admin.templates.region')}</th>
              <th className="px-3 py-2 text-start font-medium">ITP</th>
              <th className="px-3 py-2 text-start font-medium">AJD</th>
              <th className="px-3 py-2 text-start font-medium">{t('admin.templates.note')}</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {legal.regions.map((r) => (
              <tr key={r.name} className="align-top">
                <td className="whitespace-nowrap px-3 py-2 font-medium">{r.name}</td>
                <td className="px-3 py-2">
                  {cell(`${r.name}.itp`, draft.regions[r.name]?.itp ?? '', (value) => setDraft((d) => ({ ...d, regions: { ...d.regions, [r.name]: { ...d.regions[r.name], itp: value } } })), `${r.name} ITP`)}
                </td>
                <td className="px-3 py-2">
                  {cell(`${r.name}.ajd`, draft.regions[r.name]?.ajd ?? '', (value) => setDraft((d) => ({ ...d, regions: { ...d.regions, [r.name]: { ...d.regions[r.name], ajd: value } } })), `${r.name} AJD`)}
                </td>
                <td className="min-w-[16rem] px-3 py-2 text-xs text-muted-foreground">{r.note ?? ''}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {invalid.size > 0 && <p className="text-sm text-destructive">{t('admin.templates.range')}</p>}
      {update.isError && <p className="text-sm text-destructive">{apiError(update.error) ?? t('common.error')}</p>}
      <div className="flex flex-wrap items-center gap-3">
        <Button disabled={invalid.size > 0 || update.isPending} onClick={() => setConfirming(true)}>
          {changes.length > 0 ? t('admin.templates.saveChanges', { count: changes.length }) : t('admin.templates.markReviewed')}
        </Button>
        {changes.length > 0 && (
          <Button variant="ghost" onClick={() => setDraft(draftOf(legal))}>
            {t('admin.templates.discard')}
          </Button>
        )}
        {update.isSuccess && changes.length === 0 && <span className="text-sm text-success">{t('admin.templates.saved')}</span>}
      </div>

      <AlertDialog open={confirming} onOpenChange={setConfirming}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{changes.length > 0 ? t('admin.templates.confirmTitle') : t('admin.templates.confirmReviewTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('admin.templates.confirmBody')}</AlertDialogDescription>
          </AlertDialogHeader>
          {changes.length > 0 && (
            <ul className="max-h-60 space-y-1 overflow-y-auto text-sm" lang="es">
              {changes.map((change) => (
                <li key={change.label} className="flex justify-between gap-4">
                  <span>{change.label}</span>
                  <span className="shrink-0 tabular-nums">
                    {formatPercent(change.before, locale)} → <strong>{formatPercent(change.after, locale)}</strong>
                  </span>
                </li>
              ))}
            </ul>
          )}
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={(event) => {
                event.preventDefault()
                save()
              }}
              disabled={update.isPending}
            >
              {t('admin.templates.confirm')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </section>
  )
}

function HistoryCard({ legal }: { legal: AdminTemplates['legal'] }) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language
  const when = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat(intlLocale(locale), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) : ''

  return (
    <section className="space-y-4 rounded-2xl border bg-card p-6">
      <div className="flex items-center gap-3">
        <History className="h-5 w-5 text-or-fonce" />
        <h2 className="text-lg font-semibold">{t('admin.templates.historyTitle')}</h2>
      </div>
      {legal.history.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('admin.templates.historyEmpty')}</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-xs text-muted-foreground">
              <tr>
                <th className="px-3 py-2 text-start font-medium">{t('admin.templates.when')}</th>
                <th className="px-3 py-2 text-start font-medium">{t('admin.templates.who')}</th>
                <th className="px-3 py-2 text-start font-medium">{t('admin.templates.what')}</th>
                <th className="px-3 py-2 text-end font-medium">{t('admin.templates.before')}</th>
                <th className="px-3 py-2 text-end font-medium">{t('admin.templates.after')}</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {legal.history.map((change) => (
                <tr key={change.id}>
                  <td className="whitespace-nowrap px-3 py-2 text-muted-foreground">{when(change.at)}</td>
                  <td className="px-3 py-2">{change.by ?? '—'}</td>
                  <td className="px-3 py-2" lang="es">
                    {legal.parameters.find((p) => p.code === change.subject)?.label ?? change.subject}
                    {change.field !== 'value' && ` · ${change.field.toUpperCase()}`}
                  </td>
                  <td className="whitespace-nowrap px-3 py-2 text-end tabular-nums text-muted-foreground">{formatPercent(change.old_value, locale)}</td>
                  <td className="whitespace-nowrap px-3 py-2 text-end font-medium tabular-nums">{formatPercent(change.new_value, locale)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
