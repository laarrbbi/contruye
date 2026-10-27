import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { Globe, Mail, Pencil, Phone, Plus, Search, Trash2 } from 'lucide-react'
import {
  PARTNER_CATEGORIES,
  useAdminPartners,
  useCreatePartner,
  useDeletePartner,
  useUpdatePartner,
  type AdminPartner,
  type PartnerCategory,
  type PartnerPayload,
} from '@/api/admin'
import { useWilayas, wilayaLabel } from '@/api/wilayas'
import { WilayaCoverageBadges, WilayaCoveragePicker } from '@/components/admin/WilayaCoveragePicker'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
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
import { applyServerErrors, useDebouncedValue } from './lib'

/** Sentinel for the "no wilaya filter" option (Radix forbids an empty value). */
const ALL = 'all'

interface PartnerFormValues {
  name: string
  category: PartnerCategory
  description_fr: string
  description_ar: string
  website: string
  phone: string
  email: string
  is_active: boolean
  is_national: boolean
  wilaya_ids: number[]
}

const emptyValues: PartnerFormValues = {
  name: '',
  category: 'notaire',
  description_fr: '',
  description_ar: '',
  website: '',
  phone: '',
  email: '',
  is_active: true,
  is_national: false,
  wilaya_ids: [],
}

function PartnerFormDialog({
  open,
  partner,
  onOpenChange,
}: {
  open: boolean
  partner: AdminPartner | null
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const createPartner = useCreatePartner()
  const updatePartner = useUpdatePartner()
  const [genericError, setGenericError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    setError,
    watch,
    setValue,
    getValues,
    formState: { errors },
  } = useForm<PartnerFormValues>({ defaultValues: emptyValues })

  useEffect(() => {
    if (!open) return
    setGenericError(null)
    reset(
      partner
        ? {
            name: partner.name,
            category: partner.category,
            description_fr: partner.description_fr ?? '',
            description_ar: partner.description_ar ?? '',
            website: partner.website ?? '',
            phone: partner.phone ?? '',
            email: partner.email ?? '',
            is_active: partner.is_active,
            is_national: partner.is_national,
            wilaya_ids: partner.wilayas.map((wilaya) => wilaya.id),
          }
        : emptyValues,
    )
  }, [open, partner, reset])

  const isPending = createPartner.isPending || updatePartner.isPending

  const onSubmit = (values: PartnerFormValues) => {
    setGenericError(null)
    const blankToNull = (value: string) => (value.trim() === '' ? null : value.trim())
    const payload: PartnerPayload = {
      name: values.name.trim(),
      category: values.category,
      description_fr: blankToNull(values.description_fr),
      description_ar: blankToNull(values.description_ar),
      website: blankToNull(values.website),
      phone: blankToNull(values.phone),
      email: blankToNull(values.email),
      is_active: values.is_active,
      is_national: values.is_national,
      wilaya_ids: values.is_national ? [] : values.wilaya_ids,
    }
    const options = {
      onSuccess: () => onOpenChange(false),
      onError: (error: unknown) => setGenericError(applyServerErrors(error, setError, t('common.error'))),
    }
    if (partner) {
      updatePartner.mutate({ id: partner.id, ...payload }, options)
    } else {
      createPartner.mutate(payload, options)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !isPending && onOpenChange(next)}>
      <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {partner ? t('admin.partners.form.editTitle') : t('admin.partners.form.createTitle')}
          </DialogTitle>
          <DialogDescription>{t('admin.partners.form.subtitle')}</DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="partner-name">{t('admin.partners.form.name')}</Label>
              <Input id="partner-name" {...register('name', { required: t('validation.required') })} />
              {errors.name && <p className="text-sm text-destructive">{errors.name.message}</p>}
            </div>
            <div className="space-y-2">
              <Label htmlFor="partner-category">{t('admin.partners.form.category')}</Label>
              <Select
                value={watch('category')}
                onValueChange={(value) => setValue('category', value as PartnerCategory, { shouldDirty: true })}
              >
                <SelectTrigger id="partner-category">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {PARTNER_CATEGORIES.map((category) => (
                    <SelectItem key={category} value={category}>
                      {t(`partners.category.${category}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="partner-desc-fr">{t('admin.partners.form.descriptionFr')}</Label>
            <Textarea id="partner-desc-fr" rows={2} {...register('description_fr')} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="partner-desc-ar">{t('admin.partners.form.descriptionAr')}</Label>
            <Textarea id="partner-desc-ar" rows={2} dir="rtl" {...register('description_ar')} />
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="partner-phone">{t('admin.partners.form.phone')}</Label>
              <Input id="partner-phone" dir="ltr" {...register('phone')} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="partner-email">{t('admin.partners.form.email')}</Label>
              <Input id="partner-email" type="email" dir="ltr" {...register('email')} />
              {errors.email && <p className="text-sm text-destructive">{errors.email.message}</p>}
            </div>
            <div className="space-y-2">
              <Label htmlFor="partner-website">{t('admin.partners.form.website')}</Label>
              <Input id="partner-website" dir="ltr" placeholder="https://" {...register('website')} />
              {errors.website && <p className="text-sm text-destructive">{errors.website.message}</p>}
            </div>
          </div>

          <WilayaCoveragePicker
            value={watch('wilaya_ids')}
            onChange={(updater) =>
              setValue('wilaya_ids', updater(getValues('wilaya_ids') ?? []), { shouldDirty: true })
            }
            isNational={watch('is_national')}
            onNationalChange={(next) => setValue('is_national', next, { shouldDirty: true })}
          />

          <div className="flex items-center justify-between gap-3 rounded-md border p-3">
            <Label htmlFor="partner-active" className="cursor-pointer">
              {t('admin.partners.form.isActive')}
            </Label>
            <Switch
              id="partner-active"
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

export function AdminPartnersPage() {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const [wilayaFilter, setWilayaFilter] = useState<string>(ALL)
  const debouncedSearch = useDebouncedValue(search)
  const wilayaId = wilayaFilter === ALL ? undefined : Number(wilayaFilter)
  const { data, isLoading, isError, isFetching } = useAdminPartners(debouncedSearch || undefined, wilayaId)
  const partners = data?.data
  // Kept previous data while refetching means `gaps` can still describe the
  // wilaya we just navigated away from — don't attribute it to the new one.
  const gaps = isFetching ? [] : (data?.meta?.gaps ?? [])
  const { data: wilayas } = useWilayas()
  const deletePartner = useDeletePartner()

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<AdminPartner | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<AdminPartner | null>(null)

  const openCreate = () => {
    setEditing(null)
    setDialogOpen(true)
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-bold">{t('admin.partners.title')}</h1>
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4" />
          {t('admin.partners.new')}
        </Button>
      </div>

      <p className="rounded-lg border bg-secondary/40 p-3 text-sm text-muted-foreground">
        {t('admin.partners.intro')}
      </p>

      <div className="flex flex-wrap items-center gap-3">
        <div className="relative max-w-sm flex-1">
          <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-muted-foreground" />
          <Input
            type="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={t('admin.partners.searchPlaceholder')}
            className="ps-9"
          />
        </div>
        <Select value={wilayaFilter} onValueChange={setWilayaFilter}>
          <SelectTrigger className="w-[220px]" aria-label={t('admin.partners.filterWilaya')}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>{t('admin.partners.allWilayas')}</SelectItem>
            {(wilayas ?? []).map((wilaya) => (
              <SelectItem key={wilaya.id} value={String(wilaya.id)}>
                {wilayaLabel(wilaya)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {wilayaId !== undefined && gaps.length > 0 && (
        <div className="rounded-lg border border-dashed border-amber-500/50 bg-amber-500/5 p-3 text-sm">
          <p className="font-medium">{t('admin.partners.gapsTitle')}</p>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {gaps.map((category) => (
              <Badge key={category} variant="secondary">
                {t(`partners.category.${category}`)}
              </Badge>
            ))}
          </div>
        </div>
      )}

      {isLoading ? (
        <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
      ) : isError ? (
        <p className="py-12 text-center text-destructive">{t('common.error')}</p>
      ) : !partners || partners.length === 0 ? (
        <div className="rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          <p>{t('admin.partners.empty')}</p>
          <Button variant="outline" className="mt-4" onClick={openCreate}>
            <Plus className="h-4 w-4" />
            {t('admin.partners.new')}
          </Button>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('admin.partners.table.name')}</TableHead>
                <TableHead>{t('admin.partners.table.category')}</TableHead>
                <TableHead>{t('admin.partners.table.contact')}</TableHead>
                <TableHead>{t('admin.partners.table.wilaya')}</TableHead>
                <TableHead className="text-center">{t('admin.partners.table.modules')}</TableHead>
                <TableHead className="w-24">
                  <span className="sr-only">{t('admin.common.actions')}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {partners.map((partner) => (
                <TableRow key={partner.id} className={partner.is_active ? undefined : 'opacity-60'}>
                  <TableCell>
                    <div className="font-medium leading-tight">{partner.name}</div>
                    {!partner.is_active && (
                      <Badge variant="secondary" className="mt-1">
                        {t('admin.partners.inactive')}
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary">{t(`partners.category.${partner.category}`)}</Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-col gap-0.5 text-xs text-muted-foreground" dir="ltr">
                      {partner.phone && (
                        <span className="flex items-center gap-1">
                          <Phone className="h-3 w-3 shrink-0" />
                          {partner.phone}
                        </span>
                      )}
                      {partner.email && (
                        <span className="flex items-center gap-1">
                          <Mail className="h-3 w-3 shrink-0" />
                          {partner.email}
                        </span>
                      )}
                      {partner.website && (
                        <span className="flex items-center gap-1">
                          <Globe className="h-3 w-3 shrink-0" />
                          {partner.website.replace(/^https?:\/\//, '')}
                        </span>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    <WilayaCoverageBadges wilayas={partner.wilayas} isNational={partner.is_national} />
                  </TableCell>
                  <TableCell className="text-center">
                    <Badge variant="secondary">{partner.modules_count}</Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex justify-end gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('admin.common.edit')}
                        onClick={() => {
                          setEditing(partner)
                          setDialogOpen(true)
                        }}
                      >
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('common.delete')}
                        onClick={() => setDeleteTarget(partner)}
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

      <PartnerFormDialog open={dialogOpen} partner={editing} onOpenChange={setDialogOpen} />

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admin.common.confirmDeleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('admin.partners.deleteConfirm', { name: deleteTarget?.name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deletePartner.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deletePartner.isPending}
              onClick={(event) => {
                event.preventDefault()
                if (!deleteTarget) return
                deletePartner.mutate(deleteTarget.id, { onSettled: () => setDeleteTarget(null) })
              }}
            >
              {deletePartner.isPending ? t('common.loading') : t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
