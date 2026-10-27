import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Banknote, Check, CircleCheck } from 'lucide-react'
import { useAdminCourse, useAdminCourses, useCashOrder, type CashOrderPayload } from '@/api/admin'
import { apiErrorMessage } from '@/api/orders'
import { Button } from '@/components/ui/button'
import { cn, formatPrice } from '@/lib/utils'

/**
 * Cash paid at the office: the admin picks what the student bought, the
 * server prices it with checkout's rules, the admin confirms the amount they
 * collected. Never a price typed by hand.
 */
export function CashPaymentSection({ studentId }: { studentId: number }) {
  const { t, i18n } = useTranslation()
  const [open, setOpen] = useState(false)
  const [courseId, setCourseId] = useState<number | null>(null)
  const [scope, setScope] = useState<'full' | 'modules'>('full')
  const [moduleIds, setModuleIds] = useState<number[]>([])

  const { data: courses } = useAdminCourses()
  const { data: course } = useAdminCourse(courseId ?? 0)
  const { preview, record } = useCashOrder(studentId)

  // Default to the first course: there is one today.
  useEffect(() => {
    if (courseId === null && courses && courses.length > 0) setCourseId(courses[0].id)
  }, [courses, courseId])

  // Any change to the selection invalidates the amount shown.
  const resetQuote = () => {
    preview.reset()
    record.reset()
  }

  const payload: CashOrderPayload | null =
    courseId === null
      ? null
      : scope === 'full'
        ? { course_id: courseId, scope }
        : moduleIds.length > 0
          ? { course_id: courseId, scope, module_ids: moduleIds }
          : null

  if (record.isSuccess) {
    return (
      <div className="flex items-start gap-2 rounded-lg border border-success/40 bg-success/5 p-3 text-sm">
        <CircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-success" />
        <p>
          {t('admin.cash.recorded', {
            amount: formatPrice(record.data.amount, 'DZD', i18n.language),
          })}
        </p>
      </div>
    )
  }

  if (!open) {
    return (
      <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
        <Banknote className="h-4 w-4" />
        {t('admin.cash.open')}
      </Button>
    )
  }

  const error = apiErrorMessage(preview.error) ?? apiErrorMessage(record.error)

  return (
    <div className="space-y-4 rounded-lg border p-4">
      {courses && courses.length > 1 && (
        <select
          className="h-9 w-full rounded-md border bg-background px-2 text-sm"
          value={courseId ?? ''}
          onChange={(event) => {
            setCourseId(Number(event.target.value))
            setModuleIds([])
            resetQuote()
          }}
        >
          {courses.map((c) => (
            <option key={c.id} value={c.id}>
              {c.title_fr}
            </option>
          ))}
        </select>
      )}

      <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label={t('admin.cash.scope')}>
        {(['full', 'modules'] as const).map((value) => (
          <button
            key={value}
            type="button"
            role="radio"
            aria-checked={scope === value}
            onClick={() => {
              setScope(value)
              resetQuote()
            }}
            className={cn(
              'rounded-md border px-3 py-2 text-sm font-medium transition-colors',
              scope === value ? 'border-primary bg-primary/5 text-primary' : 'hover:border-primary/50',
            )}
          >
            {t(value === 'full' ? 'admin.cash.full' : 'admin.cash.modules')}
          </button>
        ))}
      </div>

      {scope === 'modules' && course && (
        <ul className="max-h-56 space-y-1 overflow-y-auto">
          {[...course.modules]
            .sort((a, b) => a.order - b.order)
            .map((module) => {
              const checked = moduleIds.includes(module.id)
              return (
                <li key={module.id}>
                  <button
                    type="button"
                    role="checkbox"
                    aria-checked={checked}
                    onClick={() => {
                      setModuleIds((prev) => (checked ? prev.filter((id) => id !== module.id) : [...prev, module.id]))
                      resetQuote()
                    }}
                    className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-start text-sm hover:bg-muted"
                  >
                    <span
                      className={cn(
                        'flex h-4 w-4 shrink-0 items-center justify-center rounded border',
                        checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input',
                      )}
                      aria-hidden
                    >
                      {checked && <Check className="h-3 w-3" />}
                    </span>
                    {module.order}. {module.title_fr}
                  </button>
                </li>
              )
            })}
        </ul>
      )}

      {preview.data && (
        <p className="rounded-md bg-muted p-3 text-sm">
          {t('admin.cash.toCollect')}{' '}
          <span className="font-bold tabular-nums">{formatPrice(preview.data.total, 'DZD', i18n.language)}</span>
        </p>
      )}

      {error && <p className="text-sm text-destructive">{error}</p>}

      <div className="flex justify-end gap-2">
        <Button variant="ghost" size="sm" onClick={() => setOpen(false)}>
          {t('common.cancel')}
        </Button>
        {preview.data ? (
          <Button size="sm" disabled={!payload || record.isPending} onClick={() => payload && record.mutate(payload)}>
            {t('admin.cash.confirm', { amount: formatPrice(preview.data.total, 'DZD', i18n.language) })}
          </Button>
        ) : (
          <Button size="sm" variant="outline" disabled={!payload || preview.isPending} onClick={() => payload && preview.mutate(payload)}>
            {t('admin.cash.price')}
          </Button>
        )}
      </div>
    </div>
  )
}
