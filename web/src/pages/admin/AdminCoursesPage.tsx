import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Controller, useForm } from 'react-hook-form'
import { Layers, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { formatPrice } from '@/lib/utils'
import {
  useAdminCourses,
  useCreateCourse,
  useDeleteCourse,
  useUpdateCourse,
  type AdminCourse,
  type CoursePayload,
  type CourseStatus,
} from '@/api/admin'
import { applyServerErrors, formatDate } from './lib'

interface CourseFormValues {
  title_es: string
  title_en: string
  description_es: string
  description_en: string
  slug: string
  price_eur: string
  status: CourseStatus
}

const emptyValues: CourseFormValues = {
  title_es: '',
  title_en: '',
  description_es: '',
  description_en: '',
  slug: '',
  price_eur: '',
  status: 'draft',
}

function courseToValues(course: AdminCourse): CourseFormValues {
  return {
    title_es: course.title_es,
    title_en: course.title_en ?? '',
    description_es: course.description_es ?? '',
    description_en: course.description_en ?? '',
    slug: course.slug,
    price_eur: String(course.price_eur),
    status: course.status,
  }
}

function CourseFormDialog({
  open,
  course,
  onOpenChange,
}: {
  open: boolean
  course: AdminCourse | null
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const createCourse = useCreateCourse()
  const updateCourse = useUpdateCourse()
  const [genericError, setGenericError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    control,
    reset,
    setError,
    formState: { errors },
  } = useForm<CourseFormValues>({ defaultValues: emptyValues })

  useEffect(() => {
    if (open) {
      reset(course ? courseToValues(course) : emptyValues)
      setGenericError(null)
    }
  }, [open, course, reset])

  const isPending = createCourse.isPending || updateCourse.isPending

  const onSubmit = (values: CourseFormValues) => {
    setGenericError(null)
    const payload: CoursePayload = {
      title_es: values.title_es.trim(),
      // Blank sends null: English visitors then read the Spanish title.
      title_en: values.title_en.trim() || null,
      description_es: values.description_es.trim() || null,
      description_en: values.description_en.trim() || null,
      price_eur: Number(values.price_eur),
      status: values.status,
    }
    const slug = values.slug.trim()
    if (slug) payload.slug = slug

    const options = {
      onSuccess: () => onOpenChange(false),
      onError: (error: unknown) => {
        setGenericError(applyServerErrors(error, setError, t('common.error')))
      },
    }

    if (course) {
      updateCourse.mutate({ id: course.id, ...payload }, options)
    } else {
      createCourse.mutate(payload, options)
    }
  }

  const esHasError = Boolean(errors.title_es || errors.description_es)
  const enHasError = Boolean(errors.title_en || errors.description_en)

  const fieldError = (message?: string) =>
    message ? <p className="text-sm text-destructive">{message}</p> : null

  return (
    <Dialog open={open} onOpenChange={(next) => !isPending && onOpenChange(next)}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>{course ? t('admin.courses.form.editTitle') : t('admin.courses.form.createTitle')}</DialogTitle>
          <DialogDescription>{t('admin.courses.form.subtitle')}</DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <Tabs defaultValue="es">
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="es">
                {t('admin.form.spanish')}
                {esHasError && <span className="h-2 w-2 rounded-full bg-destructive" aria-hidden />}
              </TabsTrigger>
              <TabsTrigger value="en">
                {t('admin.form.english')}
                {enHasError && <span className="h-2 w-2 rounded-full bg-destructive" aria-hidden />}
              </TabsTrigger>
            </TabsList>

            <TabsContent value="es" className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="course-title-es">{t('admin.courses.form.titleEs')}</Label>
                <Input
                  id="course-title-es"
                  dir="ltr"
                  {...register('title_es', { required: t('validation.required') })}
                />
                {fieldError(errors.title_es?.message)}
              </div>
              <div className="space-y-2">
                <Label htmlFor="course-description-es">{t('admin.courses.form.descriptionEs')}</Label>
                <Textarea id="course-description-es" dir="ltr" rows={4} {...register('description_es')} />
                {fieldError(errors.description_es?.message)}
              </div>
            </TabsContent>

            <TabsContent value="en" className="space-y-4">
              <p className="text-xs text-muted-foreground">{t('admin.form.englishOptional')}</p>
              <div className="space-y-2">
                <Label htmlFor="course-title-en">{t('admin.courses.form.titleEn')}</Label>
                <Input id="course-title-en" dir="ltr" {...register('title_en')} />
                {fieldError(errors.title_en?.message)}
              </div>
              <div className="space-y-2">
                <Label htmlFor="course-description-en">{t('admin.courses.form.descriptionEn')}</Label>
                <Textarea id="course-description-en" dir="ltr" rows={4} {...register('description_en')} />
                {fieldError(errors.description_en?.message)}
              </div>
            </TabsContent>
          </Tabs>

          <div className="space-y-2">
            <Label htmlFor="course-slug">{t('admin.courses.form.slug')}</Label>
            <Input id="course-slug" dir="ltr" placeholder={t('admin.courses.form.slugHint')} {...register('slug')} />
            {fieldError(errors.slug?.message)}
          </div>

          <div className="space-y-2">
            <Label htmlFor="course-price-eur">{t('admin.courses.form.priceEur')}</Label>
            <Input
              id="course-price-eur"
              type="number"
              min="0"
              step="1"
              dir="ltr"
              className="sm:max-w-[50%]"
              {...register('price_eur', {
                required: t('validation.required'),
                validate: (value) =>
                  (value.trim() !== '' && Number(value) >= 0) || t('admin.form.invalidNumber'),
              })}
            />
            {fieldError(errors.price_eur?.message)}
          </div>

          <div className="space-y-2">
            <Label htmlFor="course-status">{t('admin.courses.form.status')}</Label>
            <Controller
              control={control}
              name="status"
              render={({ field }) => (
                <Select value={field.value} onValueChange={field.onChange}>
                  <SelectTrigger id="course-status">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="draft">{t('admin.courses.status.draft')}</SelectItem>
                    <SelectItem value="published">{t('admin.courses.status.published')}</SelectItem>
                  </SelectContent>
                </Select>
              )}
            />
            {fieldError(errors.status?.message)}
          </div>

          {genericError && <p className="text-sm text-destructive">{genericError}</p>}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isPending}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={isPending}>
              {isPending ? t('common.loading') : t('common.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

export function AdminCoursesPage() {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const { data: courses, isLoading, isError } = useAdminCourses()
  const deleteCourse = useDeleteCourse()

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingCourse, setEditingCourse] = useState<AdminCourse | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<AdminCourse | null>(null)

  const openCreate = () => {
    setEditingCourse(null)
    setDialogOpen(true)
  }

  const openEdit = (course: AdminCourse) => {
    setEditingCourse(course)
    setDialogOpen(true)
  }

  const confirmDelete = () => {
    if (!deleteTarget) return
    deleteCourse.mutate(deleteTarget.id, {
      onSettled: () => setDeleteTarget(null),
    })
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-bold">{t('admin.courses.title')}</h1>
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4" />
          {t('admin.courses.new')}
        </Button>
      </div>

      {isLoading ? (
        <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
      ) : isError ? (
        <p className="py-12 text-center text-destructive">{t('common.error')}</p>
      ) : !courses || courses.length === 0 ? (
        <div className="rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          <p>{t('admin.courses.empty')}</p>
          <Button variant="outline" className="mt-4" onClick={openCreate}>
            <Plus className="h-4 w-4" />
            {t('admin.courses.new')}
          </Button>
        </div>
      ) : (
        <div className="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('admin.courses.table.title')}</TableHead>
                <TableHead>{t('admin.courses.table.price')}</TableHead>
                <TableHead>{t('admin.courses.table.status')}</TableHead>
                <TableHead className="text-center">{t('admin.courses.table.modules')}</TableHead>
                <TableHead className="text-center">{t('admin.courses.table.lessons')}</TableHead>
                <TableHead className="text-center">{t('admin.courses.table.enrollments')}</TableHead>
                <TableHead>{t('admin.courses.table.createdAt')}</TableHead>
                <TableHead className="w-12">
                  <span className="sr-only">{t('admin.common.actions')}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {courses.map((course) => (
                <TableRow
                  key={course.id}
                  className="cursor-pointer"
                  onClick={() => navigate(`/admin/courses/${course.id}/content`)}
                >
                  <TableCell className="font-medium">
                    <div>{(i18n.language === 'en' && course.title_en) || course.title_es}</div>
                    <div className="text-xs text-muted-foreground">
                      <span dir="ltr">/{course.slug}</span>
                    </div>
                  </TableCell>
                  <TableCell className="whitespace-nowrap">
                    {formatPrice(course.price_eur, 'EUR', i18n.language)}
                  </TableCell>
                  <TableCell>
                    <Badge variant={course.status === 'published' ? 'success' : 'secondary'}>
                      {t(`admin.courses.status.${course.status}`)}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-center">{course.modules_count}</TableCell>
                  <TableCell className="text-center">{course.lessons_count}</TableCell>
                  <TableCell className="text-center">{course.enrollments_count}</TableCell>
                  <TableCell className="whitespace-nowrap text-muted-foreground">
                    {formatDate(course.created_at, i18n.language)}
                  </TableCell>
                  <TableCell onClick={(event) => event.stopPropagation()}>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" aria-label={t('admin.common.actions')}>
                          <MoreHorizontal className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => navigate(`/admin/courses/${course.id}/content`)}>
                          <Layers className="h-4 w-4" />
                          {t('admin.courses.manageContent')}
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openEdit(course)}>
                          <Pencil className="h-4 w-4" />
                          {t('admin.common.edit')}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                          className="text-destructive focus:text-destructive"
                          onClick={() => setDeleteTarget(course)}
                        >
                          <Trash2 className="h-4 w-4" />
                          {t('common.delete')}
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <CourseFormDialog open={dialogOpen} course={editingCourse} onOpenChange={setDialogOpen} />

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admin.common.confirmDeleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('admin.courses.deleteConfirm', {
                title: ((i18n.language === 'en' && deleteTarget?.title_en) || deleteTarget?.title_es) ?? '',
              })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteCourse.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleteCourse.isPending}
              onClick={(event) => {
                event.preventDefault()
                confirmDelete()
              }}
            >
              {deleteCourse.isPending ? t('common.loading') : t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
