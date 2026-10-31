import { useMemo } from 'react'
import type { Sheet } from '@/api/financials'
import { columnName, decodeAddress, formatCellValue } from '@/lib/sheet'
import { cn } from '@/lib/utils'

/**
 * One sheet of the template, laid out like the workbook: its cells at their
 * addresses, with the values the platform calculated and the original number
 * formats. Read only.
 */
export function SheetGrid({ sheet, className }: { sheet: Sheet; className?: string }) {
  const { rows, cols, cells } = useMemo(() => {
    const cells = new Map<string, { text: string; numeric: boolean }>()
    let maxRow = 0
    let maxCol = 0
    for (const [address, cell] of Object.entries(sheet.celdas)) {
      const text = formatCellValue(cell.v, cell.nf)
      if (text === '') continue
      const { row, col } = decodeAddress(address)
      cells.set(`${row}:${col}`, { text, numeric: typeof cell.v === 'number' })
      maxRow = Math.max(maxRow, row)
      maxCol = Math.max(maxCol, col)
    }
    return {
      cells,
      rows: Array.from({ length: maxRow }, (_, i) => i + 1),
      cols: Array.from({ length: maxCol }, (_, i) => i + 1),
    }
  }, [sheet])

  return (
    <div className={cn('max-h-[70vh] overflow-auto rounded-lg border', className)}>
      <table className="border-separate border-spacing-0 text-xs">
        <thead>
          <tr>
            <th className="sticky start-0 top-0 z-20 border-b border-e bg-secondary px-2 py-1" />
            {cols.map((col) => (
              <th key={col} className="sticky top-0 z-10 border-b bg-secondary px-2 py-1 font-medium text-muted-foreground">
                {columnName(col)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row}>
              <th className="sticky start-0 z-10 border-e bg-secondary px-2 py-1 text-end font-medium text-muted-foreground">{row}</th>
              {cols.map((col) => {
                const cell = cells.get(`${row}:${col}`)
                return (
                  <td
                    key={col}
                    className={cn(
                      'whitespace-nowrap border-b border-border/60 px-2 py-1',
                      cell?.numeric ? 'text-end tabular-nums' : 'text-start',
                    )}
                  >
                    {cell?.text ?? ''}
                  </td>
                )
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
