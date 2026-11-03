import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronLeft, ChevronRight, Eye, EyeOff, Search } from 'lucide-react'
import { useAdminCompanies, useBulkCompanyVisibility, useSetCompanyVisibility, type AdminCompanyFilters } from '@/api/directory'
import { provinceLabel, useProvinces } from '@/api/provinces'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useDebouncedValue } from './lib'

const selectClass = 'h-10 rounded-md border border-input bg-background px-3 text-sm'

/**
 * The company directory, for the admin: find companies, and choose which ones
 * students see — one switch per company, or every company the filter finds
 * at once.
 */
export function AdminCompaniesPage() {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const q = useDebouncedValue(search.trim())
  const [category, setCategory] = useState<string | null>(null)
  const [province, setProvince] = useState<number | null>(null)
  const [visible, setVisible] = useState<'0' | '1' | null>(null)
  const [page, setPage] = useState(1)
  useEffect(() => setPage(1), [q, category, province, visible])

  const filters: AdminCompanyFilters = { q, category, province, visible, page }
  const { data, isFetching } = useAdminCompanies(filters)
  const { data: provinces } = useProvinces()
  const setOne = useSetCompanyVisibility()
  const bulk = useBulkCompanyVisibility()
  const [notice, setNotice] = useState<string | null>(null)

  const applyAll = (is_visible: boolean) => {
    const total = data?.meta.total ?? 0
    if (!window.confirm(t(is_visible ? 'admin.companies.confirmShow' : 'admin.companies.confirmHide', { total: total.toLocaleString() }))) return
    bulk.mutate({ filters, is_visible }, { onSuccess: (r) => setNotice(t('admin.companies.updated', { total: r.data.updated.toLocaleString() })) })
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="titre text-2xl">{t('admin.companies.title')}</h1>
        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">{t('admin.companies.lead')}</p>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <label className="relative min-w-[16rem] flex-1">
          <span className="sr-only">{t('directory.search')}</span>
          <Search aria-hidden className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('admin.companies.searchPlaceholder')} className="ps-9" />
        </label>
        <select value={category ?? ''} onChange={(e) => setCategory(e.target.value || null)} className={selectClass} aria-label={t('directory.categories')}>
          <option value="">{t('directory.allCategories')}</option>
          {(data?.meta.categories ?? []).map((key) => (
            <option key={key} value={key}>
              {t(`directory.category.${key}`)}
            </option>
          ))}
        </select>
        <select value={province ?? ''} onChange={(e) => setProvince(e.target.value ? Number(e.target.value) : null)} className={selectClass} aria-label={t('admin.companies.province')}>
          <option value="">{t('admin.companies.allProvinces')}</option>
          {(provinces ?? []).map((p) => (
            <option key={p.id} value={p.id}>
              {provinceLabel(p)}
            </option>
          ))}
        </select>
        <select value={visible ?? ''} onChange={(e) => setVisible((e.target.value || null) as '0' | '1' | null)} className={selectClass} aria-label={t('admin.companies.visibility')}>
          <option value="">{t('admin.companies.allStates')}</option>
          <option value="1">{t('admin.companies.shown')}</option>
          <option value="0">{t('admin.companies.hidden')}</option>
        </select>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-card px-4 py-3">
        <p className="text-sm">
          {data
            ? t('admin.companies.summary', { total: data.meta.total.toLocaleString(), visible: data.meta.visible.toLocaleString() })
            : t('common.loading')}
        </p>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" disabled={!data?.meta.total || bulk.isPending} onClick={() => applyAll(true)}>
            <Eye className="h-4 w-4" /> {t('admin.companies.showAll')}
          </Button>
          <Button variant="outline" size="sm" disabled={!data?.meta.total || bulk.isPending} onClick={() => applyAll(false)}>
            <EyeOff className="h-4 w-4" /> {t('admin.companies.hideAll')}
          </Button>
        </div>
      </div>
      {notice && <p className="text-sm text-success">{notice}</p>}

      <div className={isFetching ? 'opacity-60 transition-opacity' : undefined}>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-20">{t('admin.companies.visibleColumn')}</TableHead>
              <TableHead>{t('admin.companies.name')}</TableHead>
              <TableHead>{t('admin.companies.category')}</TableHead>
              <TableHead>{t('admin.companies.place')}</TableHead>
              <TableHead>{t('admin.companies.contact')}</TableHead>
              <TableHead className="text-end">{t('admin.companies.employees')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {(data?.data ?? []).map((c) => (
              <TableRow key={c.id}>
                <TableCell>
                  <Switch
                    checked={c.is_visible}
                    onCheckedChange={(next) => setOne.mutate({ id: c.id, is_visible: next })}
                    aria-label={t(c.is_visible ? 'admin.companies.hideOne' : 'admin.companies.showOne', { name: c.name })}
                  />
                </TableCell>
                <TableCell className="font-medium">
                  <bdi>{c.name}</bdi>
                  {c.tax_id && <span className="block text-xs text-muted-foreground" dir="ltr">{c.tax_id}</span>}
                </TableCell>
                <TableCell className="text-sm">
                  {t(`directory.category.${c.category}`)}
                  {c.activity_code && <span className="block text-xs text-muted-foreground">CNAE {c.activity_code}</span>}
                </TableCell>
                <TableCell className="text-sm">{[c.city, c.province].filter(Boolean).join(' · ')}</TableCell>
                <TableCell className="text-sm" dir="ltr">
                  {c.phone}
                  {c.website && (
                    <a href={c.website} target="_blank" rel="noopener noreferrer" className="block truncate text-xs text-primary hover:underline">
                      {c.website.replace(/^https?:\/\//, '')}
                    </a>
                  )}
                </TableCell>
                <TableCell className="text-end text-sm tabular-nums">{c.employees?.toLocaleString() ?? '—'}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {data && data.meta.last_page > 1 && (
        <nav className="flex items-center justify-between gap-3">
          <Button variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            <ChevronLeft className="h-4 w-4 rtl:-scale-x-100" /> {t('directory.previous')}
          </Button>
          <span className="text-sm tabular-nums text-muted-foreground">{t('directory.page', { page: data.meta.page, total: data.meta.last_page })}</span>
          <Button variant="outline" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
            {t('directory.next')} <ChevronRight className="h-4 w-4 rtl:-scale-x-100" />
          </Button>
        </nav>
      )}
    </div>
  )
}
