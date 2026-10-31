import { useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { Calculator, Download, Lock, Save, Trash2 } from 'lucide-react'
import { courseFileUrl, useLearn } from '@/api/learn'
import {
  TEMPLATE_SHEETS,
  useCalculate,
  useDeleteScenario,
  useSaveScenario,
  useScenarios,
  useTemplateInputs,
  type CalculationResult,
  type InputValues,
  type TemplateInput,
} from '@/api/financials'
import { usePageTitle } from '@/hooks/usePageTitle'
import { PlayerShell } from '@/components/player/PlayerShell'
import { PlayerSkeleton } from '@/components/player/PlayerSkeleton'
import { SheetGrid } from '@/components/financials/SheetGrid'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { formatCellValue, parseLocaleNumber, serialToIso } from '@/lib/sheet'

/** The summary sheet: it is the simplified template, cell for cell. */
const SUMMARY_SHEET = '3ª Coste Total Promoción'

const keyOf = (input: Pick<TemplateInput, 'hoja' | 'celda'>) => `${input.hoja}!${input.celda}`

/** What the form shows for a value of the template (or of a saved scenario). */
function toRaw(input: TemplateInput, value: number | string | null | undefined): string {
  if (value === null || value === undefined) return ''
  if (input.tipo === 'fecha' && typeof value === 'number') return serialToIso(value)
  if (typeof value === 'number') return String(value).replace('.', ',')
  return value
}

function defaultLabel(input: TemplateInput, formulaLabel: string): string {
  if (input.defecto_es_formula) return formulaLabel
  if (input.defecto === null || input.defecto === '') return ''
  if (input.tipo === 'fecha' && typeof input.defecto === 'number') {
    return input.defecto === 0 ? '' : formatCellValue(input.defecto, 'dd/mm/yyyy')
  }
  return typeof input.defecto === 'number' ? formatCellValue(input.defecto, undefined) : String(input.defecto)
}

function Field({
  input,
  value,
  invalid,
  onChange,
}: {
  input: TemplateInput
  value: string
  invalid: boolean
  onChange: (value: string) => void
}) {
  const { t } = useTranslation()
  const id = `in-${input.hoja}-${input.celda}`.replace(/[^a-zA-Z0-9-]/g, '_')
  const placeholder = defaultLabel(input, t('financials.formulaDefault'))
  const percent = input.tipo === 'porcentaje' ? parseLocaleNumber(value) : null

  let control
  if (input.tipo === 'SI/NO' || input.tipo === 'lista: bloque/chalets') {
    const options = input.tipo === 'SI/NO' ? ['SI', 'NO'] : ['bloque', 'chalets']
    control = (
      <Select value={value === '' ? undefined : value} onValueChange={onChange}>
        <SelectTrigger id={id} className="h-9">
          <SelectValue placeholder={placeholder || '—'} />
        </SelectTrigger>
        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option} value={option}>
              {option}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    )
  } else if (input.tipo === 'fecha') {
    control = <Input id={id} type="date" className="h-9" value={value} onChange={(e) => onChange(e.target.value)} />
  } else {
    control = (
      <Input
        id={id}
        className="h-9"
        inputMode={input.tipo === 'texto' ? 'text' : 'decimal'}
        value={value}
        placeholder={placeholder}
        aria-invalid={invalid}
        onChange={(e) => onChange(e.target.value)}
      />
    )
  }

  return (
    <div className="space-y-1">
      <label htmlFor={id} className="flex items-baseline justify-between gap-2 text-xs font-medium">
        <span>{input.etiqueta}</span>
        <span className="shrink-0 font-mono text-[10px] text-muted-foreground">{input.celda}</span>
      </label>
      {control}
      <p className="text-[11px] leading-snug text-muted-foreground">
        {[input.unidad, percent !== null ? `= ${formatCellValue(percent, '0.00%')}` : null, input.nota].filter(Boolean).join(' · ')}
      </p>
      {invalid && <p className="text-[11px] text-destructive">{t('financials.notANumber')}</p>}
    </div>
  )
}

/**
 * Financials: the professional's template (PLANTILLA_LIBRE.xls), calculated
 * by the platform with its own formulas. The form is the template's input
 * cells, by section, as its dictionary describes them; whatever is left empty
 * keeps the template's own default. Results are the template's sheets.
 */
export function FinancialsPage() {
  const { slug } = useParams<{ slug: string }>()
  const { t } = useTranslation()
  usePageTitle(t('financials.title'))
  const learn = useLearn(slug)
  const inputs = useTemplateInputs(slug)
  const calculate = useCalculate(slug)
  const scenarios = useScenarios(slug)
  const saveScenario = useSaveScenario(slug)
  const deleteScenario = useDeleteScenario(slug)

  const [values, setValues] = useState<Record<string, string>>({})
  const [scenarioId, setScenarioId] = useState<number | null>(null)
  const [scenarioName, setScenarioName] = useState('')
  const [result, setResult] = useState<CalculationResult | null>(null)
  const [sheetName, setSheetName] = useState<string>('6ª Cash-flow')

  const sections = useMemo(() => {
    const groups = new Map<string, TemplateInput[]>()
    for (const input of inputs.data?.entradas ?? []) {
      groups.set(input.seccion, [...(groups.get(input.seccion) ?? []), input])
    }
    return [...groups.entries()]
  }, [inputs.data])

  const invalid = useMemo(() => {
    const bad = new Set<string>()
    for (const input of inputs.data?.entradas ?? []) {
      const raw = values[keyOf(input)] ?? ''
      if ((input.tipo === 'numero' || input.tipo === 'porcentaje') && raw.trim() !== '' && parseLocaleNumber(raw) === null) {
        bad.add(keyOf(input))
      }
    }
    return bad
  }, [inputs.data, values])

  /** Only what was filled in: the rest keeps the template's defaults. */
  const datos = useMemo<InputValues>(() => {
    const out: InputValues = {}
    for (const input of inputs.data?.entradas ?? []) {
      const raw = (values[keyOf(input)] ?? '').trim()
      if (raw === '') continue
      const value = input.tipo === 'numero' || input.tipo === 'porcentaje' ? parseLocaleNumber(raw) : raw
      if (value === null) continue
      out[input.hoja] = { ...(out[input.hoja] ?? {}), [input.celda]: value }
    }
    return out
  }, [inputs.data, values])

  const load = (data: InputValues) => {
    const next: Record<string, string> = {}
    for (const input of inputs.data?.entradas ?? []) {
      const value = data[input.hoja]?.[input.celda]
      if (value !== undefined) next[keyOf(input)] = toRaw(input, value)
    }
    setValues(next)
    setResult(null)
  }

  const run = (sheet = sheetName) =>
    calculate.mutate(
      { datos, hojas: [SUMMARY_SHEET, sheet].filter((name, i, all) => all.indexOf(name) === i) },
      { onSuccess: setResult },
    )

  if (learn.isLoading || inputs.isLoading) return <PlayerSkeleton />

  if (!learn.data || inputs.isError || !inputs.data) {
    const status = isAxiosError(inputs.error) ? inputs.error.response?.status : undefined
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <Lock className="h-10 w-10 text-muted-foreground" aria-hidden />
        <h1 className="text-lg font-semibold">{status === 403 ? t('financials.locked') : t('common.error')}</h1>
        <Button asChild variant="outline">
          <Link to={slug ? `/learn/${slug}` : '/dashboard'}>{t('common.back')}</Link>
        </Button>
      </div>
    )
  }

  const data = learn.data
  const lessonF8 = data.modules.flatMap((m) => m.lessons).find((lesson) => lesson.title.startsWith('Lección F.8'))
  const calcError = calculate.error && isAxiosError(calculate.error)
    ? (calculate.error.response?.data?.errors?.datos?.[0] ?? calculate.error.response?.data?.message)
    : null
  const files = data.course.files.filter((file) => file.access === 'financials' && !file.is_locked)

  return (
    <PlayerShell data={data} location={{ financials: true }}>
      <header className="space-y-2">
        <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          <Calculator className="h-4 w-4 text-primary" />
          {t('player.financialsLabel')}
        </p>
        <h1 className="text-xl font-bold leading-snug sm:text-2xl">{t('financials.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('financials.lead')}</p>
        <p className="rounded-md border border-warning/40 bg-warning/10 px-3 py-2 text-xs leading-relaxed">
          {t('financials.paramsNotice')}{' '}
          {lessonF8 && (
            <Link to={`/learn/${data.course.slug}/lessons/${lessonF8.id}`} className="font-medium underline underline-offset-2">
              {lessonF8.title}
            </Link>
          )}
        </p>
      </header>

      {/* Scenarios */}
      <section className="space-y-3 rounded-lg border p-4">
        <div className="flex flex-wrap items-center gap-2">
          <Select
            value={scenarioId === null ? undefined : String(scenarioId)}
            onValueChange={(value) => {
              const scenario = scenarios.data?.find((s) => s.id === Number(value))
              if (!scenario) return
              setScenarioId(scenario.id)
              setScenarioName(scenario.name)
              load(scenario.inputs)
            }}
          >
            <SelectTrigger className="h-9 w-full sm:w-[240px]" aria-label={t('financials.scenarios')}>
              <SelectValue placeholder={t('financials.scenarios')} />
            </SelectTrigger>
            <SelectContent>
              {(scenarios.data ?? []).map((scenario) => (
                <SelectItem key={scenario.id} value={String(scenario.id)}>
                  {scenario.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setScenarioId(null)
              setScenarioName('')
              load(inputs.data!.ejemplos[0]?.datos ?? {})
            }}
          >
            {t('financials.loadExample')}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setScenarioId(null)
              setScenarioName('')
              load({})
            }}
          >
            {t('financials.reset')}
          </Button>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Input
            className="h-9 w-full sm:w-[240px]"
            value={scenarioName}
            placeholder={t('financials.scenarioName')}
            maxLength={120}
            onChange={(e) => setScenarioName(e.target.value)}
          />
          <Button
            size="sm"
            variant="outline"
            disabled={scenarioName.trim() === '' || saveScenario.isPending || invalid.size > 0}
            onClick={() =>
              saveScenario.mutate(
                { id: scenarioId ?? undefined, name: scenarioName.trim(), inputs: datos },
                { onSuccess: (saved) => setScenarioId(saved.id) },
              )
            }
          >
            <Save className="h-4 w-4" />
            {scenarioId ? t('financials.update') : t('financials.save')}
          </Button>
          {scenarioId !== null && (
            <Button
              size="sm"
              variant="ghost"
              onClick={() =>
                deleteScenario.mutate(scenarioId, {
                  onSuccess: () => {
                    setScenarioId(null)
                    setScenarioName('')
                  },
                })
              }
            >
              <Trash2 className="h-4 w-4" />
              {t('financials.delete')}
            </Button>
          )}
          {saveScenario.isSuccess && <span className="text-xs text-success">{t('financials.saved')}</span>}
        </div>
      </section>

      {/* The template's input cells, section by section */}
      <section className="space-y-2">
        <h2 className="text-base font-semibold">{t('financials.inputsTitle')}</h2>
        <p className="text-xs text-muted-foreground">{inputs.data.porcentajes} · {t('financials.emptyKeepsDefault')}</p>
        <Accordion type="multiple" defaultValue={sections.slice(0, 2).map(([name]) => name)} className="rounded-lg border">
          {sections.map(([name, fields]) => (
            <AccordionItem key={name} value={name} className="px-4">
              <AccordionTrigger className="text-sm">
                {name} <span className="ms-2 text-xs font-normal text-muted-foreground">({fields.length})</span>
              </AccordionTrigger>
              <AccordionContent>
                <div className="grid gap-x-4 gap-y-3 pb-2 sm:grid-cols-2 lg:grid-cols-3">
                  {fields.map((field) => (
                    <Field
                      key={keyOf(field)}
                      input={field}
                      value={values[keyOf(field)] ?? ''}
                      invalid={invalid.has(keyOf(field))}
                      onChange={(value) => setValues((prev) => ({ ...prev, [keyOf(field)]: value }))}
                    />
                  ))}
                </div>
              </AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>
      </section>

      <div className="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
        <Button onClick={() => run()} disabled={calculate.isPending || invalid.size > 0}>
          <Calculator className="h-4 w-4" />
          {calculate.isPending ? t('financials.calculating') : t('financials.calculate')}
        </Button>
        {result && (
          <span className="text-xs text-muted-foreground">
            {result.calculo.convergido
              ? t('financials.converged', { count: result.calculo.iteraciones })
              : t('financials.notConverged', { count: result.calculo.iteraciones })}
          </span>
        )}
        {calcError && <p className="w-full text-sm text-destructive">{calcError}</p>}
      </div>

      {result && (
        <Tabs defaultValue="summary" className="space-y-3">
          <TabsList>
            <TabsTrigger value="summary">{t('financials.tabSummary')}</TabsTrigger>
            <TabsTrigger value="simplified">{t('financials.tabSimplified')}</TabsTrigger>
            <TabsTrigger value="sheets">{t('financials.tabSheets')}</TabsTrigger>
          </TabsList>

          <TabsContent value="summary">
            <div className="md-content">
              <table>
                <thead>
                  <tr>
                    <th>{t('financials.chapter')}</th>
                    <th className="text-end">{t('financials.net')}</th>
                    <th className="text-end">{t('financials.withVat')}</th>
                    <th className="text-end">{t('financials.vat')}</th>
                    <th className="text-end">%</th>
                  </tr>
                </thead>
                <tbody>
                  {result.resumen.map((row) => {
                    const isCoefficient = row.concepto === 'Coeficiente de paso'
                    return (
                      <tr key={row.concepto} className={row.concepto === 'TOTAL' ? 'font-semibold' : undefined}>
                        <td>{row.concepto}</td>
                        <td className="text-end tabular-nums">{formatCellValue(row.neto, isCoefficient ? '0.0000' : '#,##0.00 €')}</td>
                        <td className="text-end tabular-nums">{formatCellValue(row.con_iva, '#,##0.00 €')}</td>
                        <td className="text-end tabular-nums">{formatCellValue(row.iva, '#,##0.00 €')}</td>
                        <td className="text-end tabular-nums">{formatCellValue(row.pct, '0.0%')}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </TabsContent>

          <TabsContent value="simplified" className="space-y-2">
            <p className="text-xs text-muted-foreground">{t('financials.simplifiedHint')}</p>
            {result.hojas[SUMMARY_SHEET] && <SheetGrid sheet={result.hojas[SUMMARY_SHEET]} />}
          </TabsContent>

          <TabsContent value="sheets" className="space-y-2">
            <Select
              value={sheetName}
              onValueChange={(name) => {
                setSheetName(name)
                run(name)
              }}
            >
              <SelectTrigger className="h-9 w-full sm:w-[280px]" aria-label={t('financials.sheet')}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {TEMPLATE_SHEETS.map((name) => (
                  <SelectItem key={name} value={name}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {calculate.isPending ? (
              <p className="text-sm text-muted-foreground">{t('financials.calculating')}</p>
            ) : (
              result.hojas[sheetName] && <SheetGrid sheet={result.hojas[sheetName]} />
            )}
          </TabsContent>
        </Tabs>
      )}

      {files.length > 0 && (
        <section className="rounded-lg border">
          <h2 className="border-b px-4 py-3 text-sm font-semibold">{t('financials.originals')}</h2>
          <ul className="divide-y">
            {files.map((file) => (
              <li key={file.key} className="flex items-center gap-3 px-4 py-3 text-sm">
                <span className="min-w-0 flex-1 truncate">{file.name}</span>
                <Button asChild size="sm" variant="outline">
                  <a href={courseFileUrl(data.course.slug, file.key)}>
                    <Download className="h-4 w-4" />
                    {t('player.download')}
                  </a>
                </Button>
              </li>
            ))}
          </ul>
          <p className="border-t px-4 py-3 text-xs text-muted-foreground">{t('financials.originalsNote')}</p>
        </section>
      )}
    </PlayerShell>
  )
}
