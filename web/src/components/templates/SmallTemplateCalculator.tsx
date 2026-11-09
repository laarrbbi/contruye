import { useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { Download, RotateCcw } from 'lucide-react'
import type { InputValues } from '@/api/financials'
import {
  downloadWorkbook,
  useCalculateSmallTemplate,
  useSmallTemplate,
  type SmallTemplateInput,
  type SmallTemplateResult,
} from '@/api/templates'
import { LegalParametersPanel } from '@/components/templates/LegalParametersPanel'
import { Button } from '@/components/ui/button'
import { decodeAddress, formatCellValue, parseLocaleNumber } from '@/lib/sheet'
import { cn } from '@/lib/utils'

/** Rows 1 and 2 of each template are its title and its instructions: the page says both. */
const FIRST_ROW = 3

const COMMUNITIES = 'lista: comunidades'

/** What the form shows for a value: percentages as a percentage, decimals with a comma. */
function toRaw(input: SmallTemplateInput, value: number | string | null): string {
  if (value === null || value === undefined) return ''
  if (typeof value !== 'number') return String(value)
  const shown = input.tipo === 'porcentaje' ? Math.round(value * 100 * 1e8) / 1e8 : value
  return String(shown).replace('.', ',')
}

function storageKey(slug: string, number: number) {
  return `construye.template.${slug}.${number}`
}

function readSaved(slug: string, number: number): Record<string, string> | null {
  try {
    const raw = localStorage.getItem(storageKey(slug, number))
    return raw ? (JSON.parse(raw) as Record<string, string>) : null
  } catch {
    return null
  }
}

function save(slug: string, number: number, values: Record<string, string>) {
  try {
    localStorage.setItem(storageKey(slug, number), JSON.stringify(values))
  } catch {
    // A private window: the inputs simply are not remembered.
  }
}

function InputCell({
  input,
  value,
  invalid,
  onChange,
}: {
  input: SmallTemplateInput
  value: string
  invalid: boolean
  onChange: (value: string) => void
}) {
  const { t } = useTranslation()
  const base =
    'h-9 w-full rounded-md border border-or/50 bg-or/[0.08] px-2 text-sm text-foreground outline-none transition-colors focus:border-or focus:ring-2 focus:ring-or/30'

  if (input.tipo === 'SI/NO' || input.tipo.startsWith('lista')) {
    const options = input.tipo === 'SI/NO' ? ['SI', 'NO'] : (input.opciones ?? [])
    return (
      <select className={cn(base, 'min-w-[8rem] pe-7 sm:min-w-[10rem]')} value={value} aria-label={input.etiqueta} onChange={(e) => onChange(e.target.value)}>
        {options.map((option) => (
          <option key={option} value={option}>
            {option}
          </option>
        ))}
      </select>
    )
  }

  const numeric = input.tipo === 'numero' || input.tipo === 'porcentaje'
  return (
    <span className="relative block">
      <input
        className={cn(base, numeric ? 'min-w-[6rem] text-end tabular-nums sm:min-w-[6.5rem]' : 'min-w-[10rem] sm:min-w-[12rem]', input.tipo === 'porcentaje' && 'pe-7', invalid && 'border-destructive focus:ring-destructive/30')}
        inputMode={numeric ? 'decimal' : 'text'}
        value={value}
        aria-label={input.etiqueta}
        aria-invalid={invalid}
        title={invalid ? t('templates.notANumber') : input.nota || undefined}
        maxLength={numeric ? 24 : 120}
        onChange={(e) => onChange(e.target.value)}
      />
      {input.tipo === 'porcentaje' && (
        <span aria-hidden className="pointer-events-none absolute inset-y-0 end-2.5 flex items-center text-xs text-muted-foreground">
          %
        </span>
      )}
    </span>
  )
}

/**
 * One of the five small templates of the course, on the platform: its own
 * sheet, laid out as in Excel, with its input cells to fill (highlighted) and
 * everything else calculated by the template's formulas on the server, with
 * the legal parameters in force. Recalculates as the student types, and
 * downloads the same template in Excel with their inputs.
 */
export function SmallTemplateCalculator({ slug, number }: { slug: string; number: number }) {
  const { t } = useTranslation()
  const template = useSmallTemplate(slug, number)
  const calculate = useCalculateSmallTemplate(slug, number)
  const [values, setValues] = useState<Record<string, string> | null>(null)
  const [result, setResult] = useState<SmallTemplateResult | null>(null)
  const [downloading, setDownloading] = useState(false)
  const [downloadError, setDownloadError] = useState(false)

  const inputs = useMemo(() => new Map((template.data?.entradas ?? []).map((input) => [input.celda, input])), [template.data])
  const defaults = useMemo(() => {
    const out: Record<string, string> = {}
    for (const input of template.data?.entradas ?? []) out[input.celda] = toRaw(input, input.defecto)
    return out
  }, [template.data])

  // The example of the template, or what this student left last time.
  useEffect(() => {
    if (template.data && values === null) setValues({ ...defaults, ...(readSaved(slug, number) ?? {}) })
  }, [template.data, defaults, values, slug, number])

  const invalid = useMemo(() => {
    const bad = new Set<string>()
    for (const input of template.data?.entradas ?? []) {
      const raw = (values?.[input.celda] ?? '').trim()
      if ((input.tipo === 'numero' || input.tipo === 'porcentaje') && raw !== '' && parseLocaleNumber(raw) === null) bad.add(input.celda)
    }
    return bad
  }, [template.data, values])

  /** What the template receives: only what is filled in, percentages as a fraction. */
  const datos = useMemo<InputValues>(() => {
    const cells: Record<string, number | string> = {}
    for (const input of template.data?.entradas ?? []) {
      const raw = (values?.[input.celda] ?? '').trim()
      if (raw === '' || invalid.has(input.celda)) continue
      if (input.tipo === 'numero') cells[input.celda] = parseLocaleNumber(raw) as number
      else if (input.tipo === 'porcentaje') cells[input.celda] = Math.round(((parseLocaleNumber(raw) as number) / 100) * 1e10) / 1e10
      else cells[input.celda] = raw
    }
    return { [template.data?.entradas[0]?.hoja ?? 'Calculadora']: cells }
  }, [template.data, values, invalid])

  // Recalculate a moment after the last keystroke.
  const datosKey = JSON.stringify(datos)
  const { mutate } = calculate
  const latest = useRef(0)
  useEffect(() => {
    if (values === null || invalid.size > 0) return
    save(slug, number, values)
    const ticket = ++latest.current
    const timer = window.setTimeout(() => {
      mutate(JSON.parse(datosKey) as InputValues, { onSuccess: (data) => ticket === latest.current && setResult(data) })
    }, 350)
    return () => window.clearTimeout(timer)
    // `values` is saved with the inputs it produced; datosKey decides the calculation.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [datosKey, invalid.size, mutate, slug, number])

  const grid = useMemo(() => {
    if (!result) return null
    let maxRow = 0
    let maxCol = 0
    const cells = new Map<string, { address: string; value: unknown; nf?: string }>()
    for (const [address, cell] of Object.entries(result.hoja.celdas)) {
      const { row, col } = decodeAddress(address)
      if (row < FIRST_ROW) continue
      cells.set(`${row}:${col}`, { address, value: cell.v, nf: cell.nf })
      maxRow = Math.max(maxRow, row)
      maxCol = Math.max(maxCol, col)
    }
    for (const input of inputs.values()) {
      const { row, col } = decodeAddress(input.celda)
      maxRow = Math.max(maxRow, row)
      maxCol = Math.max(maxCol, col)
    }
    return { cells, rows: Array.from({ length: maxRow - FIRST_ROW + 1 }, (_, i) => i + FIRST_ROW), cols: Array.from({ length: maxCol }, (_, i) => i + 1) }
  }, [result, inputs])

  if (template.isError) {
    const status = isAxiosError(template.error) ? template.error.response?.status : undefined
    return <p className="text-sm text-muted-foreground">{status === 403 ? t('templates.locked') : t('common.error')}</p>
  }

  const communityInput = template.data?.entradas.find((input) => input.tipo === COMMUNITIES)
  const community = communityInput ? (values?.[communityInput.celda] ?? null) : null
  const calcError = calculate.error && isAxiosError(calculate.error)
    ? (calculate.error.response?.data?.errors?.datos?.[0] ?? calculate.error.response?.data?.message ?? t('templates.error'))
    : null

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-3">
        <Button
          variant="outline"
          size="sm"
          disabled={!template.data || invalid.size > 0 || downloading}
          onClick={async () => {
            if (!template.data) return
            setDownloading(true)
            setDownloadError(false)
            try {
              await downloadWorkbook(`/courses/${slug}/templates/${number}/excel`, datos, template.data.excel)
            } catch {
              setDownloadError(true)
            } finally {
              setDownloading(false)
            }
          }}
        >
          <Download className="h-4 w-4" />
          {t('templates.download')}
        </Button>
        <Button variant="ghost" size="sm" disabled={!template.data} onClick={() => setValues({ ...defaults })}>
          <RotateCcw className="h-4 w-4" />
          {t('templates.reset')}
        </Button>
        <span className="text-xs text-muted-foreground" aria-live="polite">
          {calculate.isPending ? t('templates.calculating') : ''}
        </span>
      </div>
      {downloadError && <p className="text-sm text-destructive">{t('templates.downloadError')}</p>}
      {calcError && <p className="text-sm text-destructive">{calcError}</p>}

      <div className={cn('overflow-x-auto rounded-2xl border border-foreground/[0.08] bg-card transition-opacity', calculate.isPending && 'opacity-80')} lang="es">
        {!grid || values === null ? (
          <div className="h-64 animate-pulse" />
        ) : (
          <table className="w-full border-separate border-spacing-0 text-sm">
            <tbody>
              {grid.rows.map((row) => {
                const present = grid.cols.map((col) => ({ col, cell: grid.cells.get(`${row}:${col}`) })).filter((x) => x.cell && formatCellValue(x.cell.value, x.cell.nf) !== '')
                const rowInputs = grid.cols.filter((col) => inputs.has(`${String.fromCharCode(64 + col)}${row}`))
                if (present.length === 0 && rowInputs.length === 0) {
                  return <tr key={row} aria-hidden><td colSpan={grid.cols.length} className="h-3" /></tr>
                }
                // A row with one text and nothing else is a heading («RESULTADO»).
                if (rowInputs.length === 0 && present.length === 1 && present[0].col === 1 && typeof present[0].cell?.value === 'string') {
                  return (
                    <tr key={row}>
                      <th colSpan={grid.cols.length} scope="colgroup" className="etiquette border-b border-foreground/[0.08] px-4 pb-2 pt-4 text-start text-or-fonce">
                        {String(present[0].cell?.value)}
                      </th>
                    </tr>
                  )
                }
                // A row of labels only is the header of a table (Capítulo, Presupuesto…).
                const header = rowInputs.length === 0 && present.length > 1 && present.every((x) => typeof x.cell?.value === 'string')
                return (
                  <tr key={row} className="align-middle">
                    {grid.cols.map((col) => {
                      const address = `${String.fromCharCode(64 + col)}${row}`
                      const input = inputs.get(address)
                      const cell = grid.cells.get(`${row}:${col}`)
                      const text = cell ? formatCellValue(cell.value, cell.nf) : ''
                      const numeric = typeof cell?.value === 'number'
                      const Tag = header ? 'th' : 'td'
                      return (
                        <Tag
                          key={col}
                          scope={header ? 'col' : undefined}
                          className={cn(
                            'border-b border-foreground/[0.06] px-2 py-2',
                            col === 1 && 'min-w-[8.5rem] ps-4 sm:min-w-[10rem]',
                            header && 'whitespace-nowrap text-start text-xs font-medium text-muted-foreground',
                            !header && !input && numeric && 'whitespace-nowrap text-end font-medium tabular-nums',
                            !header && !input && !numeric && col > 1 && 'min-w-[10rem] text-xs text-muted-foreground',
                          )}
                        >
                          {input ? (
                            <InputCell
                              input={input}
                              value={values[address] ?? ''}
                              invalid={invalid.has(address)}
                              onChange={(value) => setValues((prev) => ({ ...(prev ?? {}), [address]: value }))}
                            />
                          ) : (
                            text
                          )}
                        </Tag>
                      )
                    })}
                  </tr>
                )
              })}
            </tbody>
          </table>
        )}
      </div>
      <p className="text-xs text-muted-foreground">{t('templates.howTo')}</p>

      {template.data && <LegalParametersPanel legal={template.data.legal} community={community} />}
    </div>
  )
}
