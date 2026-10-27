import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, Search, X } from 'lucide-react'
import { matchesProvinceSearch, useProvinces, provinceLabel } from '@/api/provinces'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { cn } from '@/lib/utils'

interface ProvinceCoveragePickerProps {
  /** Selected province ids — ignored while `isNational` is on. */
  value: number[]
  /**
   * Receives an updater rather than a list: the parent applies it against its
   * own freshest state, so two quick toggles cannot overwrite each other.
   */
  onChange: (updater: (previous: number[]) => number[]) => void
  isNational: boolean
  onNationalChange: (isNational: boolean) => void
}

/**
 * Where a partner operates: either the whole country, or an explicit set of
 * provinces picked from the official list. Searchable by name or by matricule,
 * because that is how an admin reading a business card thinks ("16" or
 * "Alger").
 */
export function ProvinceCoveragePicker({
  value,
  onChange,
  isNational,
  onNationalChange,
}: ProvinceCoveragePickerProps) {
  const { t } = useTranslation()
  const { data: provinces, isLoading } = useProvinces()
  const [search, setSearch] = useState('')

  const selected = useMemo(() => new Set(value), [value])
  const visible = useMemo(
    () => (provinces ?? []).filter((province) => matchesProvinceSearch(province, search)),
    [provinces, search],
  )

  const toggle = (id: number) => {
    onChange((previous) =>
      previous.includes(id) ? previous.filter((current) => current !== id) : [...previous, id],
    )
  }

  return (
    <div className="space-y-3 rounded-md border p-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium">{t('admin.partners.coverage.title')}</p>
          <p className="text-xs text-muted-foreground">{t('admin.partners.coverage.help')}</p>
        </div>
        <div className="flex items-center gap-2">
          <Label htmlFor="partner-national" className="cursor-pointer text-sm">
            {t('admin.partners.coverage.national')}
          </Label>
          <Switch id="partner-national" checked={isNational} onCheckedChange={onNationalChange} />
        </div>
      </div>

      {isNational ? (
        <p className="rounded-md bg-secondary/60 px-3 py-2 text-sm text-muted-foreground">
          {t('admin.partners.coverage.nationalHint')}
        </p>
      ) : (
        <>
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-sm font-medium" aria-live="polite">
              {t('admin.partners.coverage.selected', { count: value.length })}
            </span>
            {value.length > 0 && (
              <Button type="button" variant="ghost" size="sm" onClick={() => onChange(() => [])}>
                <X className="h-3.5 w-3.5" />
                {t('admin.partners.coverage.clear')}
              </Button>
            )}
          </div>

          <div className="relative">
            <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-muted-foreground" />
            <Input
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              // This box lives inside the partner form: Enter must filter the
              // list, never submit a half-finished partner.
              onKeyDown={(event) => event.key === 'Enter' && event.preventDefault()}
              placeholder={t('admin.partners.coverage.searchPlaceholder')}
              className="ps-9"
            />
          </div>

          {isLoading ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('common.loading')}</p>
          ) : visible.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">
              {t('admin.partners.coverage.noMatch')}
            </p>
          ) : (
            <ul
              role="group"
              aria-label={t('admin.partners.coverage.title')}
              className="grid max-h-64 grid-cols-1 gap-1 overflow-y-auto sm:grid-cols-2"
            >
              {visible.map((province) => {
                const isOn = selected.has(province.id)
                return (
                  <li key={province.id}>
                    <button
                      type="button"
                      role="checkbox"
                      aria-checked={isOn}
                      onClick={() => toggle(province.id)}
                      className={cn(
                        'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm transition-colors hover:bg-secondary',
                        isOn && 'bg-primary/5 font-medium text-primary',
                      )}
                    >
                      <span
                        className={cn(
                          'flex h-4 w-4 shrink-0 items-center justify-center rounded border',
                          isOn ? 'border-primary bg-primary text-primary-foreground' : 'border-input',
                        )}
                      >
                        {isOn && <Check className="h-3 w-3" />}
                      </span>
                      {provinceLabel(province)}
                    </button>
                  </li>
                )
              })}
            </ul>
          )}
        </>
      )}
    </div>
  )
}

/**
 * Compact read-only rendering of a partner's coverage, for table rows.
 */
export function ProvinceCoverageBadges({
  provinces,
  isNational,
  max = 3,
}: {
  provinces: { id: number; name: string }[]
  isNational: boolean
  max?: number
}) {
  const { t } = useTranslation()

  if (isNational) {
    return <Badge variant="secondary">{t('admin.partners.coverage.national')}</Badge>
  }

  if (provinces.length === 0) {
    return <span className="text-sm text-muted-foreground">{t('admin.partners.coverage.none')}</span>
  }

  const shown = provinces.slice(0, max)
  const rest = provinces.length - shown.length

  return (
    <div className="flex flex-wrap gap-1">
      {shown.map((province) => (
        <Badge key={province.id} variant="secondary">
          {province.name}
        </Badge>
      ))}
      {rest > 0 && (
        <Badge variant="secondary" title={provinces.map((w) => w.name).join(', ')}>
          +{rest}
        </Badge>
      )}
    </div>
  )
}
