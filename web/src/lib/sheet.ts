/**
 * Showing the template's cells the way Excel shows them: each value through
 * its own number format from the original workbook ("#,##0.00", "0.00%",
 * "dd/mm/yy"…). A light reading of Excel's format codes — enough for the ones
 * PLANTILLA_LIBRE.xls uses.
 */

export function decodeAddress(address: string): { row: number; col: number } {
  const match = /^([A-Z]{1,3})(\d+)$/.exec(address)
  if (!match) return { row: 0, col: 0 }
  let col = 0
  for (const ch of match[1]) col = col * 26 + (ch.charCodeAt(0) - 64)
  return { row: Number(match[2]), col }
}

export function columnName(col: number): string {
  let name = ''
  let c = col
  while (c > 0) {
    const r = (c - 1) % 26
    name = String.fromCharCode(65 + r) + name
    c = Math.floor((c - 1) / 26)
  }
  return name
}

/** Excel's 1900 date system: serial 1 is 01/01/1900, with the fictitious 29/02/1900. */
export function serialToDate(serial: number): Date {
  return new Date(Date.UTC(1899, 11, 30) + Math.round(serial) * 86_400_000)
}

export function dateToSerial(iso: string): number {
  const [y, m, d] = iso.split('-').map(Number)
  return Math.round((Date.UTC(y, m - 1, d) - Date.UTC(1899, 11, 30)) / 86_400_000)
}

export function serialToIso(serial: number): string {
  return serialToDate(serial).toISOString().slice(0, 10)
}

function decimalsIn(section: string): number {
  const match = /0\.(0+)/.exec(section)
  return match ? match[1].length : 0
}

/** A format section without what only affects layout: quoted text, [locale] tags, escapes, padding. */
function cleanSection(section: string): string {
  return section
    .replace(/"[^"]*"/g, '')
    .replace(/\[[^\]]*\]/g, '')
    .replace(/_./g, '')
    .replace(/\*./g, '')
    .replace(/\\(.)/g, '$1')
}

export function formatCellValue(value: unknown, format: string | undefined, locale = 'es-ES'): string {
  if (value === null || value === undefined) return ''
  if (typeof value === 'boolean') return value ? 'VERDADERO' : 'FALSO'
  if (typeof value === 'string') return value
  if (typeof value !== 'number' || !Number.isFinite(value)) return String(value)

  const nf = format ?? 'General'
  if (nf === 'General' || nf === '@') return value.toLocaleString(locale, { maximumFractionDigits: 4 })

  const sections = nf.split(';')
  // Accounting formats print zero as a dash in their third section.
  if (value === 0 && sections[2] !== undefined && /"-|\\-/.test(sections[2])) return '-'

  const section = cleanSection(value < 0 && sections[1] ? sections[1] : sections[0])
  const lower = section.toLowerCase()

  // Dates: day, month and year codes, no digit placeholders.
  if (/[dmy]/.test(lower) && !/[0#]/.test(lower)) {
    if (value === 0) return ''
    const date = serialToDate(value)
    return lower.includes('d')
      ? date.toLocaleDateString(locale, { timeZone: 'UTC' })
      : date.toLocaleDateString(locale, { timeZone: 'UTC', month: 'short', year: 'numeric' })
  }

  const digits = decimalsIn(section)
  if (lower.includes('%')) {
    return `${(value * 100).toLocaleString(locale, { minimumFractionDigits: digits, maximumFractionDigits: digits })} %`
  }

  // "0000" pads account codes; "#,##0" groups thousands.
  const pad = /^0{2,}$/.exec(section.trim())
  if (pad) return String(Math.round(value)).padStart(pad[0].length, '0')

  const text = Math.abs(value).toLocaleString(locale, {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
    useGrouping: section.includes(','),
  })
  // "(1,234)" formats show negatives in brackets.
  if (value < 0) return sections[1] && sections[1].includes('(') ? `(${text})` : `-${text}`
  return text
}

/** "1.300.000,50" or "1300000.5" → 1300000.5; null when it is not a number. */
export function parseLocaleNumber(input: string): number | null {
  const text = input.trim().replace(/\s/g, '')
  if (text === '') return null
  const normalized = text.includes(',') ? text.replace(/\./g, '').replace(',', '.') : text
  const number = Number(normalized)
  return Number.isFinite(number) ? number : null
}
