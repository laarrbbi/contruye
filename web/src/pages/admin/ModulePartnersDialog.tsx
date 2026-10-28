import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ArrowDown, ArrowUp, Plus, X } from 'lucide-react'
import {
  useAdminPartners,
  useSyncModulePartners,
  type AdminModule,
  type ModulePartnerInput,
} from '@/api/admin'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { apiErrorMessage } from '@/api/orders'

interface Row extends ModulePartnerInput {
  name: string
}

/**
 * Attach vetted partners to a module, in the order students will see them.
 * The list is sent as a whole (sync), so removing a row detaches that partner.
 */
export function ModulePartnersDialog({
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
  const { data: partnerList } = useAdminPartners()
  const partners = partnerList?.data
  const sync = useSyncModulePartners(courseId)

  const [rows, setRows] = useState<Row[]>([])
  const [toAdd, setToAdd] = useState<string>('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    setError(null)
    setToAdd('')
    setRows(
      (module?.partners ?? [])
        .slice()
        .sort((a, b) => a.order - b.order)
        .map((p) => ({ partner_id: p.partner_id, name: p.name, note_es: p.note_es, note_en: p.note_en })),
    )
  }, [open, module])

  const available = (partners ?? []).filter((p) => !rows.some((r) => r.partner_id === p.id))

  const addPartner = () => {
    const id = Number(toAdd)
    const partner = (partners ?? []).find((p) => p.id === id)
    if (!partner) return
    setRows((prev) => [...prev, { partner_id: partner.id, name: partner.name, note_es: '', note_en: '' }])
    setToAdd('')
  }

  const move = (index: number, delta: number) => {
    setRows((prev) => {
      const next = [...prev]
      const target = index + delta
      if (target < 0 || target >= next.length) return prev
      ;[next[index], next[target]] = [next[target], next[index]]
      return next
    })
  }

  const submit = () => {
    if (!module) return
    setError(null)
    sync.mutate(
      {
        moduleId: module.id,
        partners: rows.map((r) => ({
          partner_id: r.partner_id,
          note_es: r.note_es?.trim() || null,
          note_en: r.note_en?.trim() || null,
        })),
      },
      {
        onSuccess: () => onOpenChange(false),
        onError: (err) => setError(apiErrorMessage(err) ?? t('common.error')),
      },
    )
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !sync.isPending && onOpenChange(next)}>
      <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('admin.content.partners.title')}</DialogTitle>
          <DialogDescription>
            {module?.title_es} — {t('admin.content.partners.subtitle')}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="flex items-end gap-2">
            <div className="flex-1 space-y-2">
              <Label htmlFor="add-partner">{t('admin.content.partners.add')}</Label>
              <Select value={toAdd} onValueChange={setToAdd}>
                <SelectTrigger id="add-partner">
                  <SelectValue placeholder={t('admin.content.partners.choose')} />
                </SelectTrigger>
                <SelectContent>
                  {available.length === 0 ? (
                    <SelectItem value="none" disabled>
                      {t('admin.content.partners.noneAvailable')}
                    </SelectItem>
                  ) : (
                    available.map((p) => (
                      <SelectItem key={p.id} value={String(p.id)}>
                        {p.name} — {t(`partners.category.${p.category}`)}
                      </SelectItem>
                    ))
                  )}
                </SelectContent>
              </Select>
            </div>
            <Button type="button" variant="outline" onClick={addPartner} disabled={!toAdd || toAdd === 'none'}>
              <Plus className="h-4 w-4" />
            </Button>
          </div>

          {rows.length === 0 ? (
            <p className="rounded-lg border border-dashed py-8 text-center text-sm text-muted-foreground">
              {t('admin.content.partners.empty')}
            </p>
          ) : (
            <ul className="space-y-3">
              {rows.map((row, index) => (
                <li key={row.partner_id} className="space-y-2 rounded-lg border p-3">
                  <div className="flex items-center gap-2">
                    <Badge variant="secondary">{index + 1}</Badge>
                    <span className="min-w-0 flex-1 truncate font-medium">{row.name}</span>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      aria-label={t('admin.content.partners.moveUp')}
                      disabled={index === 0}
                      onClick={() => move(index, -1)}
                    >
                      <ArrowUp className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      aria-label={t('admin.content.partners.moveDown')}
                      disabled={index === rows.length - 1}
                      onClick={() => move(index, 1)}
                    >
                      <ArrowDown className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      aria-label={t('common.delete')}
                      onClick={() => setRows((prev) => prev.filter((_, i) => i !== index))}
                    >
                      <X className="h-4 w-4 text-destructive" />
                    </Button>
                  </div>
                  <div className="grid gap-2 sm:grid-cols-2">
                    <Input
                      placeholder={t('admin.content.partners.noteEs')}
                      value={row.note_es ?? ''}
                      onChange={(e) =>
                        setRows((prev) =>
                          prev.map((r, i) => (i === index ? { ...r, note_es: e.target.value } : r)),
                        )
                      }
                    />
                    <Input
                      placeholder={t('admin.content.partners.noteEn')}
                      value={row.note_en ?? ''}
                      onChange={(e) =>
                        setRows((prev) =>
                          prev.map((r, i) => (i === index ? { ...r, note_en: e.target.value } : r)),
                        )
                      }
                    />
                  </div>
                </li>
              ))}
            </ul>
          )}

          {error && <p className="text-sm text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={sync.isPending}>
            {t('common.cancel')}
          </Button>
          <Button onClick={submit} disabled={sync.isPending}>
            {sync.isPending ? t('common.loading') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
