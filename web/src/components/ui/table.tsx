import * as React from 'react'
import { cn } from '@/lib/utils'

/**
 * The name of each column, written on its cells (data-label), so that on a
 * phone, where a row is shown as a card (table-stack in index.css), every
 * value says what it is. A column whose heading is only for screen readers
 * (the actions) gets an empty name and its cell spans the card.
 */
function labelCells(table: HTMLTableElement) {
  const heads = table.tHead?.rows[0]?.cells
  if (!heads) return
  const labels = Array.from(heads).map((th) => {
    const copy = th.cloneNode(true) as HTMLElement
    copy.querySelectorAll('.sr-only').forEach((node) => node.remove())
    return (copy.textContent ?? '').trim()
  })
  for (const body of Array.from(table.tBodies)) {
    for (const row of Array.from(body.rows)) {
      Array.from(row.cells).forEach((cell, index) => {
        const label = labels[index] ?? ''
        if (cell.dataset.label !== label) cell.dataset.label = label
      })
    }
  }
}

const Table = React.forwardRef<
  HTMLTableElement,
  React.HTMLAttributes<HTMLTableElement> & {
    /** A row becomes a card under 640px. Off for a table that already fits. */
    stack?: boolean
  }
>(({ className, stack = true, ...props }, ref) => {
  const own = React.useRef<HTMLTableElement | null>(null)
  const setRef = React.useCallback(
    (node: HTMLTableElement | null) => {
      own.current = node
      if (typeof ref === 'function') ref(node)
      else if (ref) ref.current = node
    },
    [ref],
  )

  React.useLayoutEffect(() => {
    const table = own.current
    if (!stack || !table) return
    labelCells(table)
    // Rows arrive with the data and change with filters and pages.
    const observer = new MutationObserver(() => labelCells(table))
    observer.observe(table, { childList: true, subtree: true, characterData: true })
    return () => observer.disconnect()
  }, [stack])

  return (
    // Focusable, so a keyboard user can scroll a table wider than the screen.
    <div
      tabIndex={0}
      className="relative w-full overflow-auto rounded-[inherit] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      <table
        ref={setRef}
        className={cn('w-full caption-bottom text-sm', stack && 'table-stack', className)}
        {...props}
      />
    </div>
  )
})
Table.displayName = 'Table'

const TableHeader = React.forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
  ({ className, ...props }, ref) => <thead ref={ref} className={cn('[&_tr]:border-b', className)} {...props} />,
)
TableHeader.displayName = 'TableHeader'

const TableBody = React.forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
  ({ className, ...props }, ref) => (
    <tbody ref={ref} className={cn('[&_tr:last-child]:border-0', className)} {...props} />
  ),
)
TableBody.displayName = 'TableBody'

const TableFooter = React.forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
  ({ className, ...props }, ref) => (
    <tfoot ref={ref} className={cn('border-t bg-muted/50 font-medium [&>tr]:last:border-b-0', className)} {...props} />
  ),
)
TableFooter.displayName = 'TableFooter'

const TableRow = React.forwardRef<HTMLTableRowElement, React.HTMLAttributes<HTMLTableRowElement>>(
  ({ className, ...props }, ref) => (
    <tr
      ref={ref}
      className={cn('border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted', className)}
      {...props}
    />
  ),
)
TableRow.displayName = 'TableRow'

const TableHead = React.forwardRef<HTMLTableCellElement, React.ThHTMLAttributes<HTMLTableCellElement>>(
  ({ className, ...props }, ref) => (
    <th
      ref={ref}
      className={cn(
        'h-12 px-4 text-start align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pe-0',
        className,
      )}
      {...props}
    />
  ),
)
TableHead.displayName = 'TableHead'

const TableCell = React.forwardRef<HTMLTableCellElement, React.TdHTMLAttributes<HTMLTableCellElement>>(
  ({ className, children, ...props }, ref) => (
    <td ref={ref} className={cn('p-4 align-middle [&:has([role=checkbox])]:pe-0', className)} {...props}>
      {/* No box of its own on a wide screen (display: contents); the value's
          column of the card on a phone (table-stack). */}
      <div className="table-cell-body contents">{children}</div>
    </td>
  ),
)
TableCell.displayName = 'TableCell'

const TableCaption = React.forwardRef<HTMLTableCaptionElement, React.HTMLAttributes<HTMLTableCaptionElement>>(
  ({ className, ...props }, ref) => (
    <caption ref={ref} className={cn('mt-4 text-sm text-muted-foreground', className)} {...props} />
  ),
)
TableCaption.displayName = 'TableCaption'

export { Table, TableHeader, TableBody, TableFooter, TableHead, TableRow, TableCell, TableCaption }
