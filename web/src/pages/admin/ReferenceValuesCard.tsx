import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { ChevronDown, History, RotateCcw, SlidersHorizontal } from 'lucide-react'
import { useUpdateReference, type ReferenceGroup, type ReferenceValue, type ReferenceValuesView } from '@/api/templates'
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
import { parseLocaleNumber } from '@/lib/sheet'
import { cn, intlLocale } from '@/lib/utils'

const UNIT: Record<ReferenceValue['type'], string> = { porcentaje: '%', importe: '€', superficie: 'm²', coeficiente: '×' }

/** What the admin types: a percentage as «0,75», anything else as written. */
const toText = (item: Pick<ReferenceValue, 'type'>, value: number) =>
  String(Math.round((item.type === 'porcentaje' ? value * 100 : value) * 1e8) / 1e8).replace('.', ',')

/** The typed text back to the stored value, if it is one within the item's bounds. */
function fromText(item: ReferenceValue, text: string): number | null {
  // «1.500» in an amount or a surface is fifteen hundred, as written in Spain.
  const clean = item.type !== 'porcentaje' && /^\d{1,3}(\.\d{3})+$/.test(text.trim()) ? text.trim().replace(/\./g, '') : text.trim()
  const typed = parseLocaleNumber(clean)
  if (typed === null) return null
  const value = item.type === 'porcentaje' ? typed / 100 : typed
  return value >= 0 && value <= item.max ? Math.round(value * 1e10) / 1e10 : null
}

function allItems(view: ReferenceValuesView): ReferenceValue[] {
  return [...view.tariffs, ...view.inputs].flatMap((group) => [...(group.table?.rows.flatMap((row) => row.cells) ?? []), ...group.items])
}

/**
 * The templates' reference values: the tariffs of the professional template
 * that the package says are of 2002 (OCT, ten-year insurance, guarantees,
 * fee tables) and the values the inputs are presented with. Each beside the
 * package's, the changed ones marked, each change kept.
 */
export function ReferenceValuesCard({ reference }: { reference: ReferenceValuesView }) {
  const { t, i18n } = useTranslation()
  const update = useUpdateReference()
  const items = useMemo(() => allItems(reference), [reference])
  const byKey = useMemo(() => new Map(items.map((item) => [item.key, item])), [items])
  const initial = useMemo(() => Object.fromEntries(items.map((item) => [item.key, toText(item, item.value)])), [items])
  const [draft, setDraft] = useState<Record<string, string>>(initial)
  const [confirming, setConfirming] = useState(false)

  useEffect(() => setDraft(initial), [initial])

  const invalid = useMemo(() => new Set(items.filter((item) => fromText(item, draft[item.key] ?? '') === null).map((item) => item.key)), [items, draft])
  // A change is what the admin typed differently from what was shown, so a
  // value with more decimals than the field shows never counts as changed.
  const changes = useMemo(
    () =>
      items
        .filter((item) => (draft[item.key] ?? '') !== initial[item.key])
        .map((item) => ({ item, after: fromText(item, draft[item.key] ?? '') }))
        .filter((c): c is { item: ReferenceValue; after: number } => c.after !== null),
    [items, draft, initial],
  )

  const locale = intlLocale(i18n.language)
  const show = (item: ReferenceValue, value: number) =>
    item.type === 'porcentaje'
      ? new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 4 }).format(value)
      : `${new Intl.NumberFormat(locale, { maximumFractionDigits: 4 }).format(value)} ${UNIT[item.type]}`
  const reviewed = reference.reviewed_at ? new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(new Date(reference.reviewed_at)) : null

  const field = (item: ReferenceValue, label: string) => {
    const changed = (draft[item.key] ?? '') !== toText(item, item.package)
    return (
      <span className="inline-flex items-center gap-1">
        <span className="relative inline-block">
          <Input
            className={cn('h-8 w-28 pe-7 text-end tabular-nums', changed && 'border-or bg-or/10', invalid.has(item.key) && 'border-destructive')}
            inputMode="decimal"
            value={draft[item.key] ?? ''}
            aria-label={label}
            aria-invalid={invalid.has(item.key)}
            title={`${item.cell} · ${t('admin.reference.package')}: ${show(item, item.package)}`}
            onChange={(e) => setDraft((d) => ({ ...d, [item.key]: e.target.value }))}
          />
          <span aria-hidden className="pointer-events-none absolute inset-y-0 end-2 flex items-center text-xs text-muted-foreground">
            {UNIT[item.type]}
          </span>
        </span>
        {changed ? (
          <button
            type="button"
            onClick={() => setDraft((d) => ({ ...d, [item.key]: toText(item, item.package) }))}
            className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
            aria-label={t('admin.reference.reset', { value: show(item, item.package) })}
            title={t('admin.reference.reset', { value: show(item, item.package) })}
          >
            <RotateCcw className="h-3.5 w-3.5" />
          </button>
        ) : (
          <span className="w-[1.375rem]" />
        )}
      </span>
    )
  }

  const group = (g: ReferenceGroup) => {
    const own = [...(g.table?.rows.flatMap((r) => r.cells) ?? []), ...g.items]
    const changedCount = own.filter((item) => item.changed).length
    return (
      <details key={g.group} className="group rounded-xl border">
        <summary className="flex cursor-pointer list-none items-center gap-3 px-4 py-3 [&::-webkit-details-marker]:hidden">
          <span className="flex-1 font-medium">{g.title}</span>
          <span className="text-xs text-muted-foreground">{t('admin.reference.count', { count: own.length })}</span>
          {changedCount > 0 && <Badge variant="secondary">{t('admin.reference.changed', { count: changedCount })}</Badge>}
          <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
        </summary>
        <div className="space-y-4 border-t px-4 py-4">
          <p className="max-w-3xl text-sm text-muted-foreground">{g.description}</p>
          {g.table && (
            <div className="overflow-x-auto">
              <table className="text-sm">
                <thead>
                  <tr className="text-xs text-muted-foreground">
                    <th className="px-2 py-1.5 text-start font-medium" />
                    {g.table.columns.map((column) => (
                      <th key={column} className="px-2 py-1.5 text-start font-medium">{column}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {g.table.rows.map((row) => (
                    <tr key={row.label} className="border-t border-border/60">
                      {/* Stays in view while a phone scrolls the tariff sideways. */}
                      <th scope="row" className="sticky start-0 z-[1] whitespace-nowrap bg-card px-2 py-1 text-start font-normal tabular-nums text-muted-foreground">{row.label}</th>
                      {row.cells.map((cell, i) => (
                        <td key={cell.key} className="px-2 py-1">{field(cell, `${row.label} · ${g.table!.columns[i]}`)}</td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          {g.items.length > 0 && (
            <ul className="divide-y divide-border/60">
              {g.items.map((item) => (
                <li key={item.key} className="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 py-2">
                  <span className="min-w-0 flex-1 text-sm">
                    {item.label}
                    <span className="ms-2 text-xs text-muted-foreground">
                      {item.template === 'profesional' ? item.cell : t('admin.reference.smallTemplate', { number: item.template })} ·{' '}
                      {t('admin.reference.package')}: {show(item, item.package)}
                    </span>
                  </span>
                  {field(item, item.label)}
                </li>
              ))}
            </ul>
          )}
        </div>
      </details>
    )
  }

  const error = isAxiosError(update.error)
    ? ((Object.values(update.error.response?.data?.errors ?? {})[0] as string[] | undefined)?.[0] ?? update.error.response?.data?.message)
    : null

  return (
    <>
      <section className="space-y-5 rounded-2xl border bg-card p-6">
        <div className="flex flex-wrap items-center gap-3">
          <SlidersHorizontal className="h-5 w-5 text-or-fonce" />
          <h2 className="text-lg font-semibold">{t('admin.reference.title')}</h2>
          {reviewed && (
            <span className="text-xs text-muted-foreground">
              {reference.reviewed_by ? t('admin.templates.reviewedBy', { date: reviewed, name: reference.reviewed_by }) : reviewed}
            </span>
          )}
        </div>
        <p className="max-w-3xl text-sm text-muted-foreground">{t('admin.reference.lead')}</p>

        <div className="space-y-2">
          <h3 className="text-sm font-semibold">{t('admin.reference.tariffsTitle')}</h3>
          <p className="max-w-3xl text-xs text-muted-foreground">{t('admin.reference.tariffsLead')}</p>
          {reference.tariffs.map(group)}
        </div>
        <div className="space-y-2">
          <h3 className="text-sm font-semibold">{t('admin.reference.inputsTitle')}</h3>
          <p className="max-w-3xl text-xs text-muted-foreground">{t('admin.reference.inputsLead')}</p>
          {reference.inputs.map(group)}
        </div>
        <p className="max-w-3xl rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground">{t('admin.reference.formulas')}</p>

        {error && <p className="text-sm text-destructive">{error}</p>}
        <div className="flex flex-wrap items-center gap-3">
          <Button disabled={invalid.size > 0 || changes.length === 0 || update.isPending} onClick={() => setConfirming(true)}>
            {t('admin.reference.save', { count: changes.length })}
          </Button>
          {changes.length > 0 && (
            <Button variant="ghost" onClick={() => setDraft(initial)}>
              {t('admin.reference.discard')}
            </Button>
          )}
        </div>

        <AlertDialog open={confirming} onOpenChange={setConfirming}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('admin.reference.confirmTitle')}</AlertDialogTitle>
              <AlertDialogDescription>{t('admin.reference.confirmBody')}</AlertDialogDescription>
            </AlertDialogHeader>
            <ul className="max-h-64 space-y-1 overflow-y-auto text-sm">
              {changes.map(({ item, after }) => (
                <li key={item.key} className="flex justify-between gap-4">
                  <span className="min-w-0 truncate">{item.label}</span>
                  <span className="shrink-0 tabular-nums">
                    {show(item, item.value)} → <strong>{show(item, after)}</strong>
                  </span>
                </li>
              ))}
            </ul>
            <AlertDialogFooter>
              <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
              <AlertDialogAction
                onClick={(e) => {
                  e.preventDefault()
                  update.mutate(Object.fromEntries(changes.map(({ item, after }) => [item.key, after])), { onSettled: () => setConfirming(false) })
                }}
              >
                {t('admin.templates.confirm')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </section>

      {reference.history.length > 0 && (
        <section className="space-y-4 rounded-2xl border bg-card p-6">
          <div className="flex items-center gap-3">
            <History className="h-5 w-5 text-muted-foreground" />
            <h2 className="text-lg font-semibold">{t('admin.reference.historyTitle')}</h2>
          </div>
          <ul className="divide-y text-sm">
            {reference.history.map((change, i) => {
              const item = byKey.get(change.key)
              const format = (v: number) => (item ? show(item, v) : String(v))
              return (
                <li key={i} className="flex flex-wrap justify-between gap-x-6 gap-y-1 py-2">
                  <span className="min-w-0">{change.label}</span>
                  <span className="tabular-nums text-muted-foreground">
                    {format(change.old_value)} → <strong className="text-foreground">{format(change.new_value)}</strong> · {change.by ?? '—'} ·{' '}
                    {new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(change.at))}
                  </span>
                </li>
              )
            })}
          </ul>
        </section>
      )}
    </>
  )
}
