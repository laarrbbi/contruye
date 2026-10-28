import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { Info, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  useAdminCampaigns,
  useAdminCourses,
  useCreateCampaign,
  useDeleteCampaign,
  useUpdateCampaign,
  type AdminCampaign,
  type CampaignPayload,
} from '@/api/admin'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
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
import { applyServerErrors, formatDate } from './lib'

interface CampaignFormValues {
  course_id: string
  label_es: string
  label_en: string
  percent_off: string
  starts_at: string
  ends_at: string
  is_active: boolean
}

/** `datetime-local` wants `YYYY-MM-DDTHH:mm` in local time. */
function toLocalInput(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const emptyValues: CampaignFormValues = {
  course_id: '',
  label_es: '',
  label_en: '',
  percent_off: '',
  starts_at: '',
  ends_at: '',
  is_active: true,
}

function CampaignFormDialog({
  open,
  campaign,
  onOpenChange,
}: {
  open: boolean
  campaign: AdminCampaign | null
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const { data: courses } = useAdminCourses()
  const createCampaign = useCreateCampaign()
  const updateCampaign = useUpdateCampaign()
  const [genericError, setGenericError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    setError,
    watch,
    setValue,
    formState: { errors },
  } = useForm<CampaignFormValues>({ defaultValues: emptyValues })

  useEffect(() => {
    if (!open) return
    setGenericError(null)
    reset(
      campaign
        ? {
            course_id: String(campaign.course.id),
            label_es: campaign.label_es,
            label_en: campaign.label_en ?? '',
            percent_off: String(campaign.percent_off),
            starts_at: toLocalInput(campaign.starts_at),
            ends_at: toLocalInput(campaign.ends_at),
            is_active: campaign.is_active,
          }
        : { ...emptyValues, course_id: courses?.[0] ? String(courses[0].id) : '' },
    )
  }, [open, campaign, courses, reset])

  const isPending = createCampaign.isPending || updateCampaign.isPending

  const onSubmit = (values: CampaignFormValues) => {
    setGenericError(null)
    const payload: CampaignPayload = {
      course_id: Number(values.course_id),
      label_es: values.label_es.trim(),
      // Blank sends null: English visitors then read the Spanish name.
      label_en: values.label_en.trim() || null,
      percent_off: Number(values.percent_off),
      // datetime-local has no timezone; send the real instant.
      starts_at: values.starts_at ? new Date(values.starts_at).toISOString() : null,
      ends_at: new Date(values.ends_at).toISOString(),
      is_active: values.is_active,
    }
    const options = {
      onSuccess: () => onOpenChange(false),
      onError: (error: unknown) => setGenericError(applyServerErrors(error, setError, t('common.error'))),
    }
    if (campaign) {
      updateCampaign.mutate({ id: campaign.id, ...payload }, options)
    } else {
      createCampaign.mutate(payload, options)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !isPending && onOpenChange(next)}>
      <DialogContent className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {campaign ? t('admin.campaigns.form.editTitle') : t('admin.campaigns.form.createTitle')}
          </DialogTitle>
          <DialogDescription>{t('admin.campaigns.form.subtitle')}</DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <div className="space-y-2">
            <Label htmlFor="campaign-course">{t('admin.campaigns.form.course')}</Label>
            <Select
              value={watch('course_id')}
              onValueChange={(value) => setValue('course_id', value, { shouldDirty: true })}
            >
              <SelectTrigger id="campaign-course">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {(courses ?? []).map((course) => (
                  <SelectItem key={course.id} value={String(course.id)}>
                    {course.title_es}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="campaign-label-es">{t('admin.campaigns.form.labelEs')}</Label>
              <Input id="campaign-label-es" {...register('label_es', { required: t('validation.required') })} />
              {errors.label_es && <p className="text-sm text-destructive">{errors.label_es.message}</p>}
            </div>
            <div className="space-y-2">
              <Label htmlFor="campaign-label-en">{t('admin.campaigns.form.labelEn')}</Label>
              <Input id="campaign-label-en" {...register('label_en')} />
              <p className="text-xs text-muted-foreground">{t('admin.campaigns.form.labelEnHint')}</p>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="campaign-percent">{t('admin.campaigns.form.percentOff')}</Label>
            <Input
              id="campaign-percent"
              type="number"
              min="1"
              max="90"
              step="1"
              dir="ltr"
              {...register('percent_off', {
                required: t('validation.required'),
                validate: (value) => {
                  const n = Number(value)
                  return (value.trim() !== '' && n >= 1 && n <= 90) || t('admin.campaigns.form.percentRange')
                },
              })}
            />
            {errors.percent_off && <p className="text-sm text-destructive">{errors.percent_off.message}</p>}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="campaign-starts">{t('admin.campaigns.form.startsAt')}</Label>
              <Input id="campaign-starts" type="datetime-local" dir="ltr" {...register('starts_at')} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="campaign-ends">{t('admin.campaigns.form.endsAt')}</Label>
              <Input
                id="campaign-ends"
                type="datetime-local"
                dir="ltr"
                {...register('ends_at', {
                  required: t('validation.required'),
                  validate: (value) =>
                    new Date(value).getTime() > Date.now() || t('admin.campaigns.form.endsInFuture'),
                })}
              />
              {errors.ends_at && <p className="text-sm text-destructive">{errors.ends_at.message}</p>}
            </div>
          </div>

          <p className="flex items-start gap-2 rounded-md bg-secondary/50 p-3 text-xs text-muted-foreground">
            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            {t('admin.campaigns.form.deadlineNote')}
          </p>

          <div className="flex items-center justify-between gap-3 rounded-md border p-3">
            <Label htmlFor="campaign-active" className="cursor-pointer">
              {t('admin.campaigns.form.isActive')}
            </Label>
            <Switch
              id="campaign-active"
              checked={watch('is_active')}
              onCheckedChange={(checked) => setValue('is_active', checked, { shouldDirty: true })}
            />
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

export function AdminCampaignsPage() {
  const { t, i18n } = useTranslation()
  const { data: campaigns, isLoading, isError } = useAdminCampaigns()
  const deleteCampaign = useDeleteCampaign()

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<AdminCampaign | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<AdminCampaign | null>(null)

  const openCreate = () => {
    setEditing(null)
    setDialogOpen(true)
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-bold">{t('admin.campaigns.title')}</h1>
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4" />
          {t('admin.campaigns.new')}
        </Button>
      </div>

      <p className="flex items-start gap-2 rounded-lg border bg-secondary/40 p-3 text-sm text-muted-foreground">
        <Info className="mt-0.5 h-4 w-4 shrink-0" />
        {t('admin.campaigns.intro')}
      </p>

      {isLoading ? (
        <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
      ) : isError ? (
        <p className="py-12 text-center text-destructive">{t('common.error')}</p>
      ) : !campaigns || campaigns.length === 0 ? (
        <div className="rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          <p>{t('admin.campaigns.empty')}</p>
          <Button variant="outline" className="mt-4" onClick={openCreate}>
            <Plus className="h-4 w-4" />
            {t('admin.campaigns.new')}
          </Button>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('admin.campaigns.table.label')}</TableHead>
                <TableHead>{t('admin.campaigns.table.course')}</TableHead>
                <TableHead>{t('admin.campaigns.table.discount')}</TableHead>
                <TableHead>{t('admin.campaigns.table.endsAt')}</TableHead>
                <TableHead>{t('admin.campaigns.table.state')}</TableHead>
                <TableHead className="w-24">
                  <span className="sr-only">{t('admin.common.actions')}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {campaigns.map((campaign) => (
                <TableRow key={campaign.id}>
                  <TableCell className="font-medium">{campaign.label_es}</TableCell>
                  <TableCell className="max-w-[200px] truncate text-muted-foreground">
                    {campaign.course.title}
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary" dir="ltr">
                      -{campaign.percent_off}%
                    </Badge>
                  </TableCell>
                  <TableCell className="whitespace-nowrap text-muted-foreground">
                    {formatDate(campaign.ends_at, i18n.language)}
                  </TableCell>
                  <TableCell>
                    <Badge variant={campaign.is_running ? 'success' : 'secondary'}>
                      {campaign.is_running
                        ? t('admin.campaigns.state.running')
                        : campaign.is_active
                          ? t('admin.campaigns.state.inactive')
                          : t('admin.campaigns.state.disabled')}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex justify-end gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('admin.common.edit')}
                        onClick={() => {
                          setEditing(campaign)
                          setDialogOpen(true)
                        }}
                      >
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('common.delete')}
                        onClick={() => setDeleteTarget(campaign)}
                      >
                        <Trash2 className="h-4 w-4 text-destructive" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <CampaignFormDialog open={dialogOpen} campaign={editing} onOpenChange={setDialogOpen} />

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admin.common.confirmDeleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('admin.campaigns.deleteConfirm', { label: deleteTarget?.label_es ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteCampaign.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleteCampaign.isPending}
              onClick={(event) => {
                event.preventDefault()
                if (!deleteTarget) return
                deleteCampaign.mutate(deleteTarget.id, { onSettled: () => setDeleteTarget(null) })
              }}
            >
              {deleteCampaign.isPending ? t('common.loading') : t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
