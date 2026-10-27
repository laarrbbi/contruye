import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronLeft, ChevronRight, Search } from 'lucide-react'
import {
  useAdminStudent,
  useAdminStudents,
  type AdminStudentListItem,
} from '@/api/admin'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Progress } from '@/components/ui/progress'
import { formatPrice } from '@/lib/utils'
import { formatDate, useDebouncedValue, useMethodLabel } from './lib'
import { OrderStatusBadge } from './orderMeta'
import { CashPaymentSection } from './CashPaymentSection'

function StudentDetailDialog({
  studentId,
  onOpenChange,
}: {
  studentId: number | null
  onOpenChange: (open: boolean) => void
}) {
  const { t, i18n } = useTranslation()
  const methodLabel = useMethodLabel()
  const { data: student, isLoading } = useAdminStudent(studentId)

  return (
    <Dialog open={studentId !== null} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{student?.name ?? t('common.loading')}</DialogTitle>
          <DialogDescription dir="ltr" className="text-start">
            {student?.email}
          </DialogDescription>
        </DialogHeader>

        {isLoading || !student ? (
          <p className="py-8 text-center text-muted-foreground">{t('common.loading')}</p>
        ) : (
          <div className="space-y-6">
            {/* Contact */}
            <dl className="grid grid-cols-2 gap-3 text-sm">
              <div>
                <dt className="text-xs text-muted-foreground">{t('admin.students.detail.phone')}</dt>
                <dd dir="ltr" className="text-start">
                  {student.phone ?? '—'}
                </dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">{t('admin.students.detail.joined')}</dt>
                <dd>{formatDate(student.created_at, i18n.language)}</dd>
              </div>
            </dl>

            {/* Enrollments */}
            <section className="space-y-2">
              <h3 className="text-sm font-semibold">{t('admin.students.detail.enrollments')}</h3>
              {student.enrollments.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('admin.students.detail.noEnrollments')}</p>
              ) : (
                <ul className="space-y-3">
                  {student.enrollments.map((enrollment) => (
                    <li key={enrollment.id} className="rounded-lg border p-3">
                      <div className="flex items-center justify-between gap-2">
                        <span className="text-sm font-medium leading-snug">{enrollment.course.title}</span>
                        <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                          {enrollment.progress_percent}%
                        </span>
                      </div>
                      <Progress value={enrollment.progress_percent} className="mt-2 h-1.5" aria-label={enrollment.course.title} />
                      <p className="mt-1 text-xs text-muted-foreground">
                        {enrollment.full_access
                          ? t('admin.students.detail.fullAccess')
                          : t('admin.students.detail.modulesOwned', {
                              owned: enrollment.owned_modules_count,
                              total: enrollment.total_modules_count,
                            })}
                        {' · '}
                        {t('admin.students.detail.lessonsDone', {
                          done: enrollment.completed_lessons,
                          total: enrollment.total_lessons,
                        })}
                      </p>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            {/* Cash paid at the office */}
            <section className="space-y-2">
              <h3 className="text-sm font-semibold">{t('admin.cash.title')}</h3>
              <CashPaymentSection key={student.id} studentId={student.id} />
            </section>

            {/* Orders */}
            <section className="space-y-2">
              <h3 className="text-sm font-semibold">{t('admin.students.detail.orders')}</h3>
              {student.orders.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('admin.students.detail.noOrders')}</p>
              ) : (
                <ul className="divide-y rounded-lg border">
                  {student.orders.map((order) => (
                    <li key={order.id} className="flex items-center justify-between gap-3 p-3 text-sm">
                      <div className="min-w-0">
                        <div className="truncate">{order.course_title ?? '—'}</div>
                        <div className="text-xs text-muted-foreground">
                          {methodLabel(order.method)} · {formatDate(order.created_at, i18n.language)}
                        </div>
                      </div>
                      <div className="flex shrink-0 items-center gap-2">
                        <span className="tabular-nums">
                          {formatPrice(order.amount, order.currency, i18n.language)}
                        </span>
                        <OrderStatusBadge status={order.status} />
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}

export function AdminStudentsPage() {
  const { t, i18n } = useTranslation()
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [selectedId, setSelectedId] = useState<number | null>(null)

  const debouncedSearch = useDebouncedValue(search)
  const { data, isLoading, isError, isFetching } = useAdminStudents({
    search: debouncedSearch || undefined,
    page,
  })

  const students = data?.data ?? []
  const meta = data?.meta

  const onSearchChange = (value: string) => {
    setSearch(value)
    setPage(1)
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">{t('admin.students.title')}</h1>

      <div className="relative max-w-sm">
        <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-muted-foreground" />
        <Input
          type="search"
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder={t('admin.students.searchPlaceholder')}
          className="ps-9"
        />
      </div>

      {isLoading ? (
        <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
      ) : isError ? (
        <p className="py-12 text-center text-destructive">{t('common.error')}</p>
      ) : students.length === 0 ? (
        <div className="rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          {t('admin.students.empty')}
        </div>
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('admin.students.table.name')}</TableHead>
                  <TableHead>{t('admin.students.table.email')}</TableHead>
                  <TableHead>{t('admin.students.table.phone')}</TableHead>
                  <TableHead className="text-center">{t('admin.students.table.enrollments')}</TableHead>
                  <TableHead>{t('admin.students.table.joined')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {students.map((student: AdminStudentListItem) => (
                  <TableRow
                    key={student.id}
                    className="cursor-pointer"
                    onClick={() => setSelectedId(student.id)}
                  >
                    <TableCell className="font-medium">{student.name}</TableCell>
                    <TableCell dir="ltr" className="text-start text-muted-foreground">
                      {student.email}
                    </TableCell>
                    <TableCell dir="ltr" className="text-start text-muted-foreground">
                      {student.phone ?? '—'}
                    </TableCell>
                    <TableCell className="text-center">
                      <Badge variant="secondary">{student.enrollments_count}</Badge>
                    </TableCell>
                    <TableCell className="whitespace-nowrap text-muted-foreground">
                      {formatDate(student.created_at, i18n.language)}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {meta && meta.last_page > 1 && (
            <div className="flex items-center justify-between gap-3">
              <p className="text-sm text-muted-foreground">
                {t('admin.common.pageOf', { page: meta.current_page, total: meta.last_page })}
              </p>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={meta.current_page <= 1 || isFetching}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  <ChevronLeft className="h-4 w-4 rtl:rotate-180" />
                  {t('admin.common.previous')}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={meta.current_page >= meta.last_page || isFetching}
                  onClick={() => setPage((p) => p + 1)}
                >
                  {t('admin.common.next')}
                  <ChevronRight className="h-4 w-4 rtl:rotate-180" />
                </Button>
              </div>
            </div>
          )}
        </>
      )}

      <StudentDetailDialog studentId={selectedId} onOpenChange={(open) => !open && setSelectedId(null)} />
    </div>
  )
}
