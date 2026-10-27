import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
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
  useAdminCoupons,
  useCreateCoupon,
  useDeleteCoupon,
  useUpdateCoupon,
  type AdminCoupon,
  type CouponPayload,
} from '@/api/admin'
import { applyServerErrors, formatDate, toDateInputValue } from './lib'

interface CouponFormValues {
  code: string
  percent_off: string
  max_uses: string
  expires_at: string
}

const emptyValues: CouponFormValues = {
  code: '',
  percent_off: '',
  max_uses: '',
  expires_at: '',
}

function couponToValues(coupon: AdminCoupon): CouponFormValues {
  return {
    code: coupon.code,
    percent_off: String(coupon.percent_off),
    max_uses: coupon.max_uses === null ? '' : String(coupon.max_uses),
    expires_at: toDateInputValue(coupon.expires_at),
  }
}

function CouponFormDialog({
  open,
  coupon,
  onOpenChange,
}: {
  open: boolean
  coupon: AdminCoupon | null
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const createCoupon = useCreateCoupon()
  const updateCoupon = useUpdateCoupon()
  const [genericError, setGenericError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<CouponFormValues>({ defaultValues: emptyValues })

  useEffect(() => {
    if (open) {
      reset(coupon ? couponToValues(coupon) : emptyValues)
      setGenericError(null)
    }
  }, [open, coupon, reset])

  const isPending = createCoupon.isPending || updateCoupon.isPending

  const onSubmit = (values: CouponFormValues) => {
    setGenericError(null)
    const payload: CouponPayload = {
      code: values.code.trim(),
      percent_off: Number(values.percent_off),
      max_uses: values.max_uses.trim() === '' ? null : Number(values.max_uses),
      expires_at: values.expires_at.trim() === '' ? null : values.expires_at,
    }
    const options = {
      onSuccess: () => onOpenChange(false),
      onError: (error: unknown) => {
        setGenericError(applyServerErrors(error, setError, t('common.error')))
      },
    }
    if (coupon) {
      updateCoupon.mutate({ id: coupon.id, ...payload }, options)
    } else {
      createCoupon.mutate(payload, options)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !isPending && onOpenChange(next)}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{coupon ? t('admin.coupons.form.editTitle') : t('admin.coupons.form.createTitle')}</DialogTitle>
          <DialogDescription>{t('admin.coupons.form.subtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <div className="space-y-2">
            <Label htmlFor="coupon-code">{t('admin.coupons.form.code')}</Label>
            <Input
              id="coupon-code"
              dir="ltr"
              className="uppercase"
              {...register('code', { required: t('validation.required') })}
            />
            {errors.code && <p className="text-sm text-destructive">{errors.code.message}</p>}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="coupon-percent">{t('admin.coupons.form.percentOff')}</Label>
              <Input
                id="coupon-percent"
                type="number"
                min="1"
                max="100"
                step="1"
                dir="ltr"
                {...register('percent_off', {
                  required: t('validation.required'),
                  validate: (value) => {
                    const n = Number(value)
                    return (value.trim() !== '' && n >= 1 && n <= 100) || t('admin.coupons.form.percentRange')
                  },
                })}
              />
              {errors.percent_off && <p className="text-sm text-destructive">{errors.percent_off.message}</p>}
            </div>
            <div className="space-y-2">
              <Label htmlFor="coupon-max-uses">{t('admin.coupons.form.maxUses')}</Label>
              <Input
                id="coupon-max-uses"
                type="number"
                min="1"
                step="1"
                dir="ltr"
                placeholder={t('admin.coupons.noLimit')}
                {...register('max_uses', {
                  validate: (value) =>
                    value.trim() === '' || Number(value) >= 1 || t('admin.form.invalidNumber'),
                })}
              />
              {errors.max_uses && <p className="text-sm text-destructive">{errors.max_uses.message}</p>}
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="coupon-expires">{t('admin.coupons.form.expiresAt')}</Label>
            <Input id="coupon-expires" type="date" dir="ltr" {...register('expires_at')} />
            {errors.expires_at && <p className="text-sm text-destructive">{errors.expires_at.message}</p>}
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

export function AdminCouponsPage() {
  const { t, i18n } = useTranslation()
  const { data: coupons, isLoading, isError } = useAdminCoupons()
  const deleteCoupon = useDeleteCoupon()

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingCoupon, setEditingCoupon] = useState<AdminCoupon | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<AdminCoupon | null>(null)

  const openCreate = () => {
    setEditingCoupon(null)
    setDialogOpen(true)
  }

  const openEdit = (coupon: AdminCoupon) => {
    setEditingCoupon(coupon)
    setDialogOpen(true)
  }

  const isExpired = (coupon: AdminCoupon) =>
    coupon.expires_at !== null && new Date(coupon.expires_at).getTime() < Date.now()

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-bold">{t('admin.coupons.title')}</h1>
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4" />
          {t('admin.coupons.new')}
        </Button>
      </div>

      {isLoading ? (
        <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
      ) : isError ? (
        <p className="py-12 text-center text-destructive">{t('common.error')}</p>
      ) : !coupons || coupons.length === 0 ? (
        <div className="rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          <p>{t('admin.coupons.empty')}</p>
          <Button variant="outline" className="mt-4" onClick={openCreate}>
            <Plus className="h-4 w-4" />
            {t('admin.coupons.new')}
          </Button>
        </div>
      ) : (
        <div className="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('admin.coupons.table.code')}</TableHead>
                <TableHead>{t('admin.coupons.table.percentOff')}</TableHead>
                <TableHead>{t('admin.coupons.table.uses')}</TableHead>
                <TableHead>{t('admin.coupons.table.expiresAt')}</TableHead>
                <TableHead className="w-24">
                  <span className="sr-only">{t('admin.common.actions')}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {coupons.map((coupon) => (
                <TableRow key={coupon.id}>
                  <TableCell>
                    <span className="font-mono font-semibold uppercase" dir="ltr">
                      {coupon.code}
                    </span>
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary" dir="ltr">
                      -{coupon.percent_off}%
                    </Badge>
                  </TableCell>
                  <TableCell>
                    {coupon.max_uses != null ? (
                      <span dir="ltr">
                        {coupon.uses} / {coupon.max_uses}
                      </span>
                    ) : (
                      <>
                        <span dir="ltr">{coupon.uses}</span> / {t('admin.coupons.noLimit')}
                      </>
                    )}
                  </TableCell>
                  <TableCell>
                    {coupon.expires_at ? (
                      <span className={isExpired(coupon) ? 'text-destructive' : undefined}>
                        {formatDate(coupon.expires_at, i18n.language)}
                        {isExpired(coupon) && (
                          <Badge variant="destructive" className="ms-2">
                            {t('admin.coupons.expired')}
                          </Badge>
                        )}
                      </span>
                    ) : (
                      <span className="text-muted-foreground">{t('admin.coupons.never')}</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <div className="flex justify-end gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('admin.common.edit')}
                        onClick={() => openEdit(coupon)}
                      >
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('common.delete')}
                        onClick={() => setDeleteTarget(coupon)}
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

      <CouponFormDialog open={dialogOpen} coupon={editingCoupon} onOpenChange={setDialogOpen} />

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admin.common.confirmDeleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('admin.coupons.deleteConfirm', { code: deleteTarget?.code ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteCoupon.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleteCoupon.isPending}
              onClick={(event) => {
                event.preventDefault()
                if (!deleteTarget) return
                deleteCoupon.mutate(deleteTarget.id, { onSettled: () => setDeleteTarget(null) })
              }}
            >
              {deleteCoupon.isPending ? t('common.loading') : t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
