import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useFieldArray, useForm } from 'react-hook-form'
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { ArrowLeft, GripVertical, Handshake, Paperclip, Pencil, Plus, Trash2, Video } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
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
import { cn, formatDuration } from '@/lib/utils'
import {
  useAdminCourse,
  useCreateLesson,
  useCreateModule,
  useDeleteLesson,
  useDeleteModule,
  useReorderLessons,
  useReorderModules,
  useUpdateLesson,
  useUpdateModule,
  type AdminLesson,
  type AdminModule,
  type LessonPayload,
} from '@/api/admin'
import { applyServerErrors } from './lib'
import { ModulePartnersDialog } from './ModulePartnersDialog'

// ---------------------------------------------------------------------------
// Module form dialog
// ---------------------------------------------------------------------------

interface ModuleFormValues {
  title_fr: string
  title_ar: string
}

function ModuleFormDialog({
  open,
  module,
  courseId,
  onOpenChange,
}: {
  open: boolean
  module: AdminModule | null
  courseId: number
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const createModule = useCreateModule(courseId)
  const updateModule = useUpdateModule(courseId)
  const [genericError, setGenericError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<ModuleFormValues>({ defaultValues: { title_fr: '', title_ar: '' } })

  useEffect(() => {
    if (open) {
      reset({ title_fr: module?.title_fr ?? '', title_ar: module?.title_ar ?? '' })
      setGenericError(null)
    }
  }, [open, module, reset])

  const isPending = createModule.isPending || updateModule.isPending

  const onSubmit = (values: ModuleFormValues) => {
    setGenericError(null)
    // Empty means 'not translated yet', which is null — not an empty string
    // that would defeat the French fallback.
    const payload = {
      title_fr: values.title_fr.trim(),
      title_ar: values.title_ar.trim() || null,
    }
    const options = {
      onSuccess: () => onOpenChange(false),
      onError: (error: unknown) => {
        setGenericError(applyServerErrors(error, setError, t('common.error')))
      },
    }
    if (module) {
      updateModule.mutate({ id: module.id, ...payload }, options)
    } else {
      createModule.mutate(payload, options)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !isPending && onOpenChange(next)}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{module ? t('admin.content.editModule') : t('admin.content.addModule')}</DialogTitle>
          <DialogDescription>{t('admin.content.moduleFormSubtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <div className="space-y-2">
            <Label htmlFor="module-title-fr">{t('admin.courses.form.titleFr')}</Label>
            <Input id="module-title-fr" dir="ltr" {...register('title_fr', { required: t('validation.required') })} />
            {errors.title_fr && <p className="text-sm text-destructive">{errors.title_fr.message}</p>}
          </div>
          <div className="space-y-2">
            <Label htmlFor="module-title-ar">{t('admin.courses.form.titleAr')}</Label>
            <Input id="module-title-ar" dir="rtl" {...register('title_ar')} />
            {errors.title_ar && <p className="text-sm text-destructive">{errors.title_ar.message}</p>}
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

// ---------------------------------------------------------------------------
// Lesson form dialog
// ---------------------------------------------------------------------------

interface LessonFormValues {
  title_fr: string
  title_ar: string
  bunny_video_id: string
  duration_minutes: string
  is_free_preview: boolean
  content_fr: string
  content_ar: string
  attachments: { name: string; path: string }[]
}

const emptyLessonValues: LessonFormValues = {
  title_fr: '',
  title_ar: '',
  bunny_video_id: '',
  duration_minutes: '',
  is_free_preview: false,
  content_fr: '',
  content_ar: '',
  attachments: [],
}

function lessonToValues(lesson: AdminLesson): LessonFormValues {
  return {
    title_fr: lesson.title_fr,
    title_ar: lesson.title_ar,
    bunny_video_id: lesson.bunny_video_id ?? '',
    duration_minutes: lesson.duration_seconds ? String(Math.round(lesson.duration_seconds / 60)) : '',
    is_free_preview: lesson.is_free_preview,
    content_fr: lesson.content_fr ?? '',
    content_ar: lesson.content_ar ?? '',
    attachments: (lesson.attachments ?? []).map((a) => ({ name: a.name, path: a.path })),
  }
}

function LessonFormDialog({
  open,
  lesson,
  moduleId,
  courseId,
  onOpenChange,
}: {
  open: boolean
  lesson: AdminLesson | null
  moduleId: number | null
  courseId: number
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const createLesson = useCreateLesson(courseId)
  const updateLesson = useUpdateLesson(courseId)
  const [genericError, setGenericError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    control,
    setError,
    setValue,
    watch,
    formState: { errors },
  } = useForm<LessonFormValues>({ defaultValues: emptyLessonValues })

  const { fields, append, remove } = useFieldArray({ control, name: 'attachments' })
  const isFreePreview = watch('is_free_preview')

  useEffect(() => {
    if (open) {
      reset(lesson ? lessonToValues(lesson) : emptyLessonValues)
      setGenericError(null)
    }
  }, [open, lesson, reset])

  const isPending = createLesson.isPending || updateLesson.isPending

  const onSubmit = (values: LessonFormValues) => {
    setGenericError(null)
    const attachments = values.attachments
      .map((a) => ({ name: a.name.trim(), path: a.path.trim() }))
      .filter((a) => a.name !== '' || a.path !== '')
    const payload: LessonPayload = {
      title_fr: values.title_fr.trim(),
      title_ar: values.title_ar.trim() || null,
      bunny_video_id: values.bunny_video_id.trim() || null,
      duration_seconds:
        values.duration_minutes.trim() === '' ? null : Math.round(Number(values.duration_minutes) * 60),
      is_free_preview: values.is_free_preview,
      content_fr: values.content_fr.trim() || null,
      content_ar: values.content_ar.trim() || null,
      attachments: attachments.length > 0 ? attachments : null,
    }
    const options = {
      onSuccess: () => onOpenChange(false),
      onError: (error: unknown) => {
        setGenericError(applyServerErrors(error, setError, t('common.error')))
      },
    }
    if (lesson) {
      updateLesson.mutate({ id: lesson.id, ...payload }, options)
    } else if (moduleId !== null) {
      createLesson.mutate({ moduleId, ...payload }, options)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !isPending && onOpenChange(next)}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>{lesson ? t('admin.content.editLesson') : t('admin.content.addLesson')}</DialogTitle>
          <DialogDescription>{t('admin.content.lessonFormSubtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <div className="space-y-2">
            <Label htmlFor="lesson-title-fr">{t('admin.courses.form.titleFr')}</Label>
            <Input id="lesson-title-fr" dir="ltr" {...register('title_fr', { required: t('validation.required') })} />
            {errors.title_fr && <p className="text-sm text-destructive">{errors.title_fr.message}</p>}
          </div>
          <div className="space-y-2">
            <Label htmlFor="lesson-title-ar">{t('admin.courses.form.titleAr')}</Label>
            <Input id="lesson-title-ar" dir="rtl" {...register('title_ar')} />
            {errors.title_ar && <p className="text-sm text-destructive">{errors.title_ar.message}</p>}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="lesson-bunny">{t('admin.content.form.bunnyVideoId')}</Label>
              <Input id="lesson-bunny" dir="ltr" {...register('bunny_video_id')} />
              {errors.bunny_video_id && <p className="text-sm text-destructive">{errors.bunny_video_id.message}</p>}
            </div>
            <div className="space-y-2">
              <Label htmlFor="lesson-duration">{t('admin.content.form.durationMinutes')}</Label>
              <Input
                id="lesson-duration"
                type="number"
                min="0"
                step="1"
                dir="ltr"
                {...register('duration_minutes', {
                  validate: (value) =>
                    value.trim() === '' || Number(value) >= 0 || t('admin.form.invalidNumber'),
                })}
              />
              {errors.duration_minutes && (
                <p className="text-sm text-destructive">{errors.duration_minutes.message}</p>
              )}
            </div>
          </div>

          <div className="flex items-center justify-between rounded-md border p-3">
            <Label htmlFor="lesson-free-preview" className="cursor-pointer">
              {t('admin.content.form.freePreview')}
            </Label>
            <Switch
              id="lesson-free-preview"
              checked={isFreePreview}
              onCheckedChange={(checked) => setValue('is_free_preview', checked)}
            />
          </div>

          <Separator />

          {/* Written notes shown under the video, in the student's language */}
          <div className="space-y-3">
            <p className="text-sm font-semibold">{t('admin.content.form.lessonContent')}</p>
            <div className="space-y-2">
              <Label htmlFor="lesson-content-fr">{t('admin.content.form.contentFr')}</Label>
              <Textarea id="lesson-content-fr" rows={4} {...register('content_fr')} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="lesson-content-ar">{t('admin.content.form.contentAr')}</Label>
              <Textarea id="lesson-content-ar" rows={4} dir="rtl" {...register('content_ar')} />
            </div>
          </div>

          <Separator />

          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <Label>{t('admin.content.form.attachments')}</Label>
              <Button type="button" variant="outline" size="sm" onClick={() => append({ name: '', path: '' })}>
                <Plus className="h-4 w-4" />
                {t('admin.content.form.addAttachment')}
              </Button>
            </div>
            {fields.length === 0 && (
              <p className="text-sm text-muted-foreground">{t('admin.content.form.noAttachments')}</p>
            )}
            {fields.map((field, index) => (
              <div key={field.id} className="flex items-start gap-2">
                <div className="grid flex-1 gap-2 sm:grid-cols-2">
                  <Input
                    aria-label={t('admin.content.form.attachmentName')}
                    placeholder={t('admin.content.form.attachmentName')}
                    {...register(`attachments.${index}.name`)}
                  />
                  <Input
                    aria-label={t('admin.content.form.attachmentPath')}
                    placeholder={t('admin.content.form.attachmentPath')}
                    dir="ltr"
                    {...register(`attachments.${index}.path`)}
                  />
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label={t('common.delete')}
                  onClick={() => remove(index)}
                >
                  <Trash2 className="h-4 w-4 text-destructive" />
                </Button>
              </div>
            ))}
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

// ---------------------------------------------------------------------------
// Sortable rows / cards
// ---------------------------------------------------------------------------

function SortableLessonRow({
  lesson,
  onEdit,
  onDelete,
}: {
  lesson: AdminLesson
  onEdit: () => void
  onDelete: () => void
}) {
  const { t, i18n } = useTranslation()
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: lesson.id })

  const title = (i18n.language === 'ar' && lesson.title_ar) || lesson.title_fr
  const attachmentsCount = lesson.attachments?.length ?? 0

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(
        'flex items-center gap-2 rounded-md border bg-background px-2 py-2',
        isDragging && 'z-10 opacity-70 shadow-md',
      )}
    >
      <button
        type="button"
        className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-secondary active:cursor-grabbing"
        aria-label={t('admin.content.dragToReorder')}
        {...attributes}
        {...listeners}
      >
        <GripVertical className="h-4 w-4" />
      </button>

      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium">{title}</p>
        <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
          {lesson.bunny_video_id && (
            <span className="inline-flex items-center gap-1">
              <Video className="h-3 w-3" />
              <span dir="ltr">{lesson.bunny_video_id}</span>
            </span>
          )}
          {lesson.duration_seconds ? <span>{formatDuration(lesson.duration_seconds, t)}</span> : null}
          {attachmentsCount > 0 && (
            <span className="inline-flex items-center gap-1">
              <Paperclip className="h-3 w-3" />
              {attachmentsCount}
            </span>
          )}
        </div>
      </div>

      {lesson.is_free_preview && <Badge variant="success">{t('admin.content.freePreviewBadge')}</Badge>}

      <Button variant="ghost" size="icon" className="h-8 w-8" aria-label={t('admin.common.edit')} onClick={onEdit}>
        <Pencil className="h-4 w-4" />
      </Button>
      <Button
        variant="ghost"
        size="icon"
        className="h-8 w-8"
        aria-label={t('common.delete')}
        onClick={onDelete}
      >
        <Trash2 className="h-4 w-4 text-destructive" />
      </Button>
    </li>
  )
}

function SortableModuleCard({
  module,
  index,
  onEdit,
  onDelete,
  onAddLesson,
  onEditLesson,
  onDeleteLesson,
  onReorderLessons,
  onManagePartners,
}: {
  module: AdminModule
  index: number
  onEdit: () => void
  onDelete: () => void
  onAddLesson: () => void
  onManagePartners: () => void
  onEditLesson: (lesson: AdminLesson) => void
  onDeleteLesson: (lesson: AdminLesson) => void
  onReorderLessons: (lessonIds: number[]) => void
}) {
  const { t, i18n } = useTranslation()
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: module.id })

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const title = (i18n.language === 'ar' && module.title_ar) || module.title_fr

  // Admin-only editorial signal: how many passages of this module still await
  // a notary / jurist sign-off. Students never see this.
  const pendingValidation =
    (module.description_needs_validation ? 1 : 0) +
    (module.blocks ?? []).filter((b) => b.needs_validation).length +
    module.lessons.reduce(
      (total, lesson) => total + (lesson.key_points ?? []).filter((p) => p.needs_validation).length,
      0,
    )

  const handleLessonDragEnd = (event: DragEndEvent) => {
    const { active, over } = event
    if (!over || active.id === over.id) return
    const ids = module.lessons.map((l) => l.id)
    const oldIndex = ids.indexOf(Number(active.id))
    const newIndex = ids.indexOf(Number(over.id))
    if (oldIndex < 0 || newIndex < 0) return
    onReorderLessons(arrayMove(ids, oldIndex, newIndex))
  }

  return (
    <Card
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(isDragging && 'z-10 opacity-70 shadow-lg')}
    >
      <CardHeader className="flex-row items-center gap-2 space-y-0 p-4">
        <button
          type="button"
          className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-secondary active:cursor-grabbing"
          aria-label={t('admin.content.dragToReorder')}
          {...attributes}
          {...listeners}
        >
          <GripVertical className="h-5 w-5" />
        </button>
        <div className="min-w-0 flex-1">
          <h2 className="truncate font-semibold">
            <span className="text-muted-foreground">{index + 1}.</span> {title}
          </h2>
          {/* div, not p: the A VALIDER Badge renders a div and cannot nest in a <p>. */}
          <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            {t('admin.content.lessonsCount', { count: module.lessons.length })}
            {(module.partners?.length ?? 0) > 0 &&
              ` · ${t('admin.content.partners.count', { count: module.partners?.length ?? 0 })}`}
            {pendingValidation > 0 && (
              <Badge variant="warning" className="uppercase">
                {t('admin.content.needsValidation', { count: pendingValidation })}
              </Badge>
            )}
          </div>
        </div>
        <Button variant="ghost" size="icon" aria-label={t('admin.common.edit')} onClick={onEdit}>
          <Pencil className="h-4 w-4" />
        </Button>
        <Button variant="ghost" size="icon" aria-label={t('common.delete')} onClick={onDelete}>
          <Trash2 className="h-4 w-4 text-destructive" />
        </Button>
      </CardHeader>
      <CardContent className="p-4 pt-0">
        {module.lessons.length === 0 ? (
          <p className="rounded-md border border-dashed py-4 text-center text-sm text-muted-foreground">
            {t('admin.content.noLessons')}
          </p>
        ) : (
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleLessonDragEnd}>
            <SortableContext items={module.lessons.map((l) => l.id)} strategy={verticalListSortingStrategy}>
              <ul className="space-y-2">
                {module.lessons.map((lesson) => (
                  <SortableLessonRow
                    key={lesson.id}
                    lesson={lesson}
                    onEdit={() => onEditLesson(lesson)}
                    onDelete={() => onDeleteLesson(lesson)}
                  />
                ))}
              </ul>
            </SortableContext>
          </DndContext>
        )}
        <div className="mt-3 flex flex-wrap gap-2">
          <Button variant="outline" size="sm" onClick={onAddLesson}>
            <Plus className="h-4 w-4" />
            {t('admin.content.addLesson')}
          </Button>
          <Button variant="outline" size="sm" onClick={onManagePartners}>
            <Handshake className="h-4 w-4" />
            {t('admin.content.partners.manage')}
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function AdminCourseContentPage() {
  const { t, i18n } = useTranslation()
  const { id } = useParams()
  const courseId = Number(id)

  const { data: course, isLoading, isError } = useAdminCourse(courseId)
  const reorderModules = useReorderModules(courseId)
  const reorderLessons = useReorderLessons(courseId)
  const deleteModule = useDeleteModule(courseId)
  const deleteLesson = useDeleteLesson(courseId)

  const [moduleDialog, setModuleDialog] = useState<{ open: boolean; module: AdminModule | null }>({
    open: false,
    module: null,
  })
  const [partnersDialog, setPartnersDialog] = useState<{ open: boolean; module: AdminModule | null }>({
    open: false,
    module: null,
  })
  const [lessonDialog, setLessonDialog] = useState<{
    open: boolean
    moduleId: number | null
    lesson: AdminLesson | null
  }>({ open: false, moduleId: null, lesson: null })
  const [moduleToDelete, setModuleToDelete] = useState<AdminModule | null>(null)
  const [lessonToDelete, setLessonToDelete] = useState<AdminLesson | null>(null)

  const moduleSensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const handleModuleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event
    if (!over || active.id === over.id || !course) return
    const ids = course.modules.map((m) => m.id)
    const oldIndex = ids.indexOf(Number(active.id))
    const newIndex = ids.indexOf(Number(over.id))
    if (oldIndex < 0 || newIndex < 0) return
    reorderModules.mutate(arrayMove(ids, oldIndex, newIndex))
  }

  if (isLoading) {
    return <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
  }

  if (isError || !course) {
    return <p className="py-12 text-center text-destructive">{t('common.error')}</p>
  }

  const courseTitle = (i18n.language === 'ar' && course.title_ar) || course.title_fr

  return (
    <div className="space-y-6">
      <div>
        <Button variant="ghost" size="sm" asChild className="-ms-2 mb-2 text-muted-foreground">
          <Link to="/admin/courses">
            <ArrowLeft className="h-4 w-4 rtl:rotate-180" />
            {t('admin.content.back')}
          </Link>
        </Button>
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold">{courseTitle}</h1>
            <Badge variant={course.status === 'published' ? 'success' : 'secondary'}>
              {t(`admin.courses.status.${course.status}`)}
            </Badge>
          </div>
          <Button onClick={() => setModuleDialog({ open: true, module: null })}>
            <Plus className="h-4 w-4" />
            {t('admin.content.addModule')}
          </Button>
        </div>
        <p className="mt-1 text-sm text-muted-foreground">{t('admin.content.subtitle')}</p>
      </div>

      {course.modules.length === 0 ? (
        <div className="rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          <p>{t('admin.content.noModules')}</p>
          <Button
            variant="outline"
            className="mt-4"
            onClick={() => setModuleDialog({ open: true, module: null })}
          >
            <Plus className="h-4 w-4" />
            {t('admin.content.addModule')}
          </Button>
        </div>
      ) : (
        <DndContext sensors={moduleSensors} collisionDetection={closestCenter} onDragEnd={handleModuleDragEnd}>
          <SortableContext items={course.modules.map((m) => m.id)} strategy={verticalListSortingStrategy}>
            <div className="space-y-4">
              {course.modules.map((module, index) => (
                <SortableModuleCard
                  key={module.id}
                  module={module}
                  index={index}
                  onEdit={() => setModuleDialog({ open: true, module })}
                  onDelete={() => setModuleToDelete(module)}
                  onAddLesson={() => setLessonDialog({ open: true, moduleId: module.id, lesson: null })}
                  onEditLesson={(lesson) => setLessonDialog({ open: true, moduleId: module.id, lesson })}
                  onDeleteLesson={(lesson) => setLessonToDelete(lesson)}
                  onReorderLessons={(lessonIds) => reorderLessons.mutate({ moduleId: module.id, lessonIds })}
                  onManagePartners={() => setPartnersDialog({ open: true, module })}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      )}

      <ModulePartnersDialog
        open={partnersDialog.open}
        module={partnersDialog.module}
        courseId={courseId}
        onOpenChange={(open) => setPartnersDialog((state) => ({ ...state, open }))}
      />

      <ModuleFormDialog
        open={moduleDialog.open}
        module={moduleDialog.module}
        courseId={courseId}
        onOpenChange={(open) => setModuleDialog((state) => ({ ...state, open }))}
      />

      <LessonFormDialog
        open={lessonDialog.open}
        lesson={lessonDialog.lesson}
        moduleId={lessonDialog.moduleId}
        courseId={courseId}
        onOpenChange={(open) => setLessonDialog((state) => ({ ...state, open }))}
      />

      <AlertDialog open={moduleToDelete !== null} onOpenChange={(open) => !open && setModuleToDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admin.common.confirmDeleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('admin.content.deleteModuleConfirm', {
                title: ((i18n.language === 'ar' && moduleToDelete?.title_ar) || moduleToDelete?.title_fr) ?? '',
              })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteModule.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleteModule.isPending}
              onClick={(event) => {
                event.preventDefault()
                if (!moduleToDelete) return
                deleteModule.mutate(moduleToDelete.id, { onSettled: () => setModuleToDelete(null) })
              }}
            >
              {deleteModule.isPending ? t('common.loading') : t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={lessonToDelete !== null} onOpenChange={(open) => !open && setLessonToDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admin.common.confirmDeleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('admin.content.deleteLessonConfirm', {
                title: ((i18n.language === 'ar' && lessonToDelete?.title_ar) || lessonToDelete?.title_fr) ?? '',
              })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteLesson.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleteLesson.isPending}
              onClick={(event) => {
                event.preventDefault()
                if (!lessonToDelete) return
                deleteLesson.mutate(lessonToDelete.id, { onSettled: () => setLessonToDelete(null) })
              }}
            >
              {deleteLesson.isPending ? t('common.loading') : t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
