import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Building2, ChevronLeft, ChevronRight, Globe, Lock, Mail, MapPin, Phone, Search } from 'lucide-react'
import { useUser } from '@/api/auth'
import { useCompanies, useModuleCompanies, type DirectoryCompany, type DirectoryPage } from '@/api/directory'
import { PartnerProvinceFilter } from '@/components/player/PartnerProvinceFilter'
import { SectionLabel } from '@/components/inside/PageHead'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { cn } from '@/lib/utils'

/**
 * The companies a promoter works with, from the directory: searchable,
 * filtered to the student's province by default, by kind of company.
 *
 * In a module (`moduleId`), the kinds its lessons send the promoter to; on the
 * Partners page (no `moduleId`), every owned module's, with a module filter.
 */
export function CompanyDirectory({ slug, moduleId = null }: { slug: string; moduleId?: number | null }) {
  const { t } = useTranslation()
  const { data: user } = useUser()
  const [search, setSearch] = useState('')
  const q = useDebouncedValue(search.trim(), 350)
  const [category, setCategory] = useState<string | null>(null)
  const [provinceOverride, setProvinceOverride] = useState<{ id: number | null } | null>(null)
  const province = provinceOverride ? provinceOverride.id : (user?.province_id ?? null)
  const [module, setModule] = useState<number | null>(null)
  const [page, setPage] = useState(1)

  // A new filter starts again from the first page.
  useEffect(() => setPage(1), [q, category, province, module])

  const filters = { q, category, province, page, module: moduleId ? undefined : module }
  const forModule = useModuleCompanies(slug, moduleId, filters)
  const forAll = useCompanies(moduleId ? undefined : slug, filters)
  const result = moduleId ? forModule : forAll
  const data: DirectoryPage | undefined = result.data

  return (
    <section className="space-y-6">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
        <label className="relative flex-1">
          <span className="sr-only">{t('directory.search')}</span>
          <Search aria-hidden className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('directory.searchPlaceholder')} className="h-11 ps-9" />
        </label>
        {!moduleId && data?.phases && (
          <select
            value={module ?? ''}
            onChange={(e) => setModule(e.target.value ? Number(e.target.value) : null)}
            aria-label={t('directory.module')}
            className="h-11 rounded-md border border-input bg-background px-3 text-sm"
          >
            <option value="">{t('directory.allModules')}</option>
            {data.phases.map((phase) => (
              <option key={phase.module_id} value={phase.module_id} disabled={phase.is_locked}>
                {t('player.moduleLabel', { number: phase.phase })} · {phase.title}
                {phase.is_locked ? ` · ${t('player.locked')}` : ''}
              </option>
            ))}
          </select>
        )}
        <PartnerProvinceFilter value={province} onChange={(next) => setProvinceOverride({ id: next })} showLabel={false} />
      </div>

      {data && data.meta.categories.length > 1 && (
        <div className="flex flex-wrap gap-2" role="group" aria-label={t('directory.categories')}>
          <Chip active={category === null} onClick={() => setCategory(null)} label={t('directory.allCategories')} />
          {data.meta.categories
            .filter((c) => c.count > 0)
            .map((c) => (
              <Chip key={c.key} active={category === c.key} onClick={() => setCategory(c.key)} label={t(`directory.category.${c.key}`)} count={c.count} />
            ))}
        </div>
      )}

      {result.isError ? (
        <p className="flex items-center gap-2 rounded-2xl border bg-card p-6 text-sm text-muted-foreground">
          <Lock className="h-4 w-4" /> {t('directory.locked')}
        </p>
      ) : !data ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-40 rounded-2xl" />
          ))}
        </div>
      ) : data.data.length === 0 ? (
        <p className="rounded-2xl border bg-card p-8 text-center text-sm text-muted-foreground">{t('directory.empty')}</p>
      ) : (
        <>
          <SectionLabel>{t('directory.count', { formatted: data.meta.total.toLocaleString() })}</SectionLabel>
          <ul className={cn('grid gap-4 sm:grid-cols-2 xl:grid-cols-3', result.isFetching && 'opacity-60 transition-opacity')}>
            {data.data.map((company) => (
              <CompanyCard key={company.id} company={company} />
            ))}
          </ul>
          {data.meta.last_page > 1 && (
            <nav className="flex items-center justify-between gap-3" aria-label={t('directory.pages')}>
              <Button variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                <ChevronLeft className="h-4 w-4 rtl:-scale-x-100" /> {t('directory.previous')}
              </Button>
              <span className="text-sm tabular-nums text-muted-foreground">{t('directory.page', { page: data.meta.page, total: data.meta.last_page })}</span>
              <Button variant="outline" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
                {t('directory.next')} <ChevronRight className="h-4 w-4 rtl:-scale-x-100" />
              </Button>
            </nav>
          )}
        </>
      )}
    </section>
  )
}

function Chip({ active, onClick, label, count }: { active: boolean; onClick(): void; label: string; count?: number }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        'inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm transition-colors',
        active ? 'border-foreground bg-foreground text-background' : 'border-foreground/15 bg-card hover:border-foreground/40',
      )}
    >
      {label}
      {count !== undefined && <span className={cn('tabular-nums text-xs', active ? 'text-background/70' : 'text-muted-foreground')}>{count.toLocaleString()}</span>}
    </button>
  )
}

function CompanyCard({ company }: { company: DirectoryCompany }) {
  const { t } = useTranslation()
  const place = [company.city, company.province].filter(Boolean).join(' · ')
  const host = company.website?.replace(/^https?:\/\//, '').replace(/\/$/, '')

  return (
    <li className="flex flex-col gap-4 rounded-2xl border border-foreground/[0.08] bg-card p-5 shadow-[0_24px_48px_-38px_rgb(20_26_34/0.4)]">
      <div className="flex items-start gap-3">
        <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-secondary">
          <Building2 className="h-4 w-4 text-foreground/70" />
        </span>
        <div className="min-w-0">
          <p className="etiquette text-or-fonce">{t(`directory.category.${company.category}`)}</p>
          <h3 className="titre mt-1 text-base leading-snug">
            <bdi>{company.name}</bdi>
          </h3>
        </div>
      </div>
      <dl className="space-y-1.5 text-sm text-foreground/75">
        {place && (
          <div className="flex items-start gap-2">
            <dt className="sr-only">{t('directory.where')}</dt>
            <MapPin aria-hidden className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <dd>
              <bdi>{place}</bdi>
            </dd>
          </div>
        )}
        {company.phone && (
          <div className="flex items-center gap-2">
            <dt className="sr-only">{t('directory.phone')}</dt>
            <Phone aria-hidden className="h-3.5 w-3.5 shrink-0" />
            <dd>
              <a href={`tel:${company.phone.replace(/[^\d+]/g, '')}`} dir="ltr" className="hover:underline">
                {company.phone}
              </a>
            </dd>
          </div>
        )}
        {company.website && (
          <div className="flex items-center gap-2">
            <dt className="sr-only">{t('directory.website')}</dt>
            <Globe aria-hidden className="h-3.5 w-3.5 shrink-0" />
            <dd className="min-w-0 truncate">
              <a href={company.website} target="_blank" rel="noopener noreferrer" dir="ltr" className="hover:underline">
                {host}
              </a>
            </dd>
          </div>
        )}
        {company.email && (
          <div className="flex items-center gap-2">
            <dt className="sr-only">{t('directory.email')}</dt>
            <Mail aria-hidden className="h-3.5 w-3.5 shrink-0" />
            <dd className="min-w-0 truncate">
              <a href={`mailto:${company.email}`} dir="ltr" className="hover:underline">
                {company.email}
              </a>
            </dd>
          </div>
        )}
      </dl>
    </li>
  )
}
