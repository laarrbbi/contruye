import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { Check, Mail } from 'lucide-react'
import { useAdminCourses } from '@/api/admin'
import { useAdminOffers, useUpdateOffer, type AdminOffer, type AdminOfferPayload, type OfferLocale, type StudentOffer } from '@/api/offers'
import { OfferCard } from '@/components/offers/OfferCard'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { LOCALES, LOCALE_NAMES, isRtl } from '@/i18n/locales'
import { cn } from '@/lib/utils'

const selectClass = 'h-10 w-full rounded-md border border-input bg-background px-3 text-sm'

/**
 * The three offers of the course, in the order a student meets them: a day
 * after the purchase, at the first milestone, at the end (the big one). Each
 * slot stays off until the owner writes what it sells and switches it on.
 */
export function AdminOffersPage() {
  const { t } = useTranslation()
  const { data, isLoading } = useAdminOffers()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="titre text-2xl">{t('admin.offers.title')}</h1>
        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">{t('admin.offers.lead')}</p>
      </div>
      {isLoading ? (
        <Skeleton className="h-96 w-full rounded-2xl" />
      ) : (
        (data ?? []).map((offer, index) => <OfferEditor key={offer.id} offer={offer} number={index + 1} />)
      )}
    </div>
  )
}

/** The fields the form edits, out of the admin payload. */
function payloadOf(offer: AdminOffer): AdminOfferPayload {
  const payload: AdminOfferPayload = {
    is_active: offer.is_active,
    course_id: offer.course_id,
    url: offer.url,
    price_eur: offer.price_eur,
    delay_hours: offer.delay_hours,
    available_hours: offer.available_hours,
    modules_required: offer.modules_required,
    send_email: offer.send_email,
  } as AdminOfferPayload
  for (const locale of LOCALES) {
    payload[`title_${locale}`] = offer[`title_${locale}`]
    payload[`body_${locale}`] = offer[`body_${locale}`]
    payload[`cta_label_${locale}`] = offer[`cta_label_${locale}`]
  }
  return payload
}

function serverErrors(error: unknown): string[] {
  if (isAxiosError(error) && error.response?.status === 422) {
    const data = error.response.data as { message?: string; errors?: Record<string, string[]> }
    const messages = Object.values(data.errors ?? {}).map((list) => list[0])
    return messages.length ? messages : [data.message ?? '']
  }
  return []
}

/** Hours typed in a box: empty is null, anything else a whole number. */
const hours = (value: string): number | null => (value.trim() === '' ? null : Math.max(0, Math.round(Number(value))))

function OfferEditor({ offer, number }: { offer: AdminOffer; number: number }) {
  const { t } = useTranslation()
  const { data: courses } = useAdminCourses()
  const update = useUpdateOffer()
  const [form, setForm] = useState<AdminOfferPayload>(() => payloadOf(offer))
  const [target, setTarget] = useState<'course' | 'url'>(offer.course_id ? 'course' : 'url')
  const [tab, setTab] = useState<OfferLocale>('es')
  const [errors, setErrors] = useState<string[]>([])
  const [saved, setSaved] = useState(false)
  const id = `offer-${offer.stage}`

  const set = <K extends keyof AdminOfferPayload>(key: K, value: AdminOfferPayload[K]) => {
    setForm((current) => ({ ...current, [key]: value }))
    setSaved(false)
  }

  const save = () => {
    const payload: AdminOfferPayload = {
      ...form,
      course_id: target === 'course' ? form.course_id : null,
      url: target === 'url' ? form.url || null : null,
      price_eur: target === 'url' ? form.price_eur : null,
    }
    update.mutate(
      { id: offer.id, payload },
      {
        onSuccess: () => {
          setErrors([])
          setSaved(true)
        },
        onError: (error) => {
          const messages = serverErrors(error)
          setErrors(messages.length ? messages : [t('admin.offers.saveFailed')])
        },
      },
    )
  }

  // The card as a student would see it, in the language of the open tab.
  const pick = (field: 'title' | 'body' | 'cta_label') => form[`${field}_${tab}`] || form[`${field}_es`] || ''
  const course = courses?.find((c) => c.id === form.course_id)
  const preview: StudentOffer = {
    stage: offer.stage,
    title: pick('title') || t('admin.offers.previewTitle'),
    body_html: escapeLines(pick('body')),
    cta_label: pick('cta_label') || null,
    lang: form[`title_${tab}`] ? tab : 'es',
    course: target === 'course' && course ? { slug: course.slug, title: course.title_es } : null,
    url: target === 'url' ? form.url : null,
    price_eur: target === 'url' ? form.price_eur : null,
    closes_at: null,
  }

  return (
    <section aria-labelledby={`${id}-title`} className="rounded-2xl border bg-card">
      <header className="flex flex-wrap items-start justify-between gap-4 border-b p-5 sm:p-6">
        <div className="max-w-2xl">
          <p className="etiquette text-or-fonce">{t('admin.offers.slot', { number })}</p>
          <h2 id={`${id}-title`} className="titre mt-2 text-xl">
            {t(`admin.offers.stage.${offer.stage}.name`)}
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">{t(`admin.offers.stage.${offer.stage}.when`)}</p>
        </div>
        <div className="flex flex-col items-end gap-2">
          <Badge variant={offer.is_live ? 'success' : 'secondary'}>{offer.is_live ? t('admin.offers.live') : t('admin.offers.off')}</Badge>
          <p className="text-end text-xs text-muted-foreground">
            {t('admin.offers.stats', {
              reached: offer.stats.reached,
              emailed: offer.stats.emailed,
              seen: offer.stats.seen,
              clicked: offer.stats.clicked,
              dismissed: offer.stats.dismissed,
            })}
          </p>
        </div>
      </header>

      <div className="grid gap-8 p-5 sm:p-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div className="space-y-6">
          <div className="flex items-center gap-3">
            <Switch id={`${id}-active`} checked={form.is_active} onCheckedChange={(v) => set('is_active', v)} />
            <Label htmlFor={`${id}-active`}>{t('admin.offers.active')}</Label>
          </div>

          <fieldset className="space-y-3">
            <legend className="text-sm font-semibold">{t('admin.offers.timing')}</legend>
            <div className="grid gap-3 sm:grid-cols-3">
              {offer.stage === 'milestone' && (
                <div className="space-y-1.5">
                  <Label htmlFor={`${id}-modules`}>{t('admin.offers.modulesRequired')}</Label>
                  <Input id={`${id}-modules`} type="number" min={1} max={13} value={form.modules_required} onChange={(e) => set('modules_required', Math.max(1, Number(e.target.value) || 1))} />
                </div>
              )}
              <div className="space-y-1.5">
                <Label htmlFor={`${id}-delay`}>{t('admin.offers.delay')}</Label>
                <Input id={`${id}-delay`} type="number" min={0} value={form.delay_hours} onChange={(e) => set('delay_hours', hours(e.target.value) ?? 0)} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor={`${id}-available`}>{t('admin.offers.available')}</Label>
                <Input id={`${id}-available`} type="number" min={1} value={form.available_hours ?? ''} placeholder={t('admin.offers.untilClosed')} onChange={(e) => set('available_hours', hours(e.target.value))} />
              </div>
            </div>
            <p className="text-xs text-muted-foreground">
              {form.available_hours
                ? t('admin.offers.windowFixed', { from: form.delay_hours, to: form.delay_hours + form.available_hours })
                : t('admin.offers.windowOpen', { from: form.delay_hours })}
            </p>
          </fieldset>

          <fieldset className="space-y-3">
            <legend className="text-sm font-semibold">{t('admin.offers.sells')}</legend>
            <div role="radiogroup" className="inline-flex rounded-md border p-1">
              {(['url', 'course'] as const).map((kind) => (
                <button
                  key={kind}
                  type="button"
                  role="radio"
                  aria-checked={target === kind}
                  onClick={() => {
                    setTarget(kind)
                    setSaved(false)
                  }}
                  className={cn('rounded px-3 py-1.5 text-sm transition-colors', target === kind ? 'bg-foreground text-background' : 'text-muted-foreground hover:text-foreground')}
                >
                  {t(`admin.offers.target.${kind}`)}
                </button>
              ))}
            </div>
            {target === 'course' ? (
              <div className="space-y-1.5">
                <Label htmlFor={`${id}-course`}>{t('admin.offers.course')}</Label>
                <select id={`${id}-course`} className={selectClass} value={form.course_id ?? ''} onChange={(e) => set('course_id', e.target.value ? Number(e.target.value) : null)}>
                  <option value="">{t('admin.offers.chooseCourse')}</option>
                  {(courses ?? []).map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.title_es}
                    </option>
                  ))}
                </select>
                <p className="text-xs text-muted-foreground">{t('admin.offers.courseHelp')}</p>
              </div>
            ) : (
              <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem]">
                <div className="space-y-1.5">
                  <Label htmlFor={`${id}-url`}>{t('admin.offers.url')}</Label>
                  <Input id={`${id}-url`} type="url" dir="ltr" placeholder="https://" value={form.url ?? ''} onChange={(e) => set('url', e.target.value || null)} />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor={`${id}-price`}>{t('admin.offers.price')}</Label>
                  <Input id={`${id}-price`} type="number" min={0} value={form.price_eur ?? ''} onChange={(e) => set('price_eur', hours(e.target.value))} />
                </div>
              </div>
            )}
          </fieldset>

          <fieldset className="space-y-3">
            <legend className="text-sm font-semibold">{t('admin.offers.text')}</legend>
            <Tabs value={tab} onValueChange={(v) => setTab(v as OfferLocale)}>
              <TabsList className="h-auto flex-wrap">
                {LOCALES.map((locale) => (
                  <TabsTrigger key={locale} value={locale} className="gap-1.5">
                    {locale.toUpperCase()}
                    {form[`title_${locale}`] && <span aria-hidden className="h-1.5 w-1.5 rounded-full bg-or" />}
                  </TabsTrigger>
                ))}
              </TabsList>
              {LOCALES.map((locale) => {
                const dir = isRtl(locale) ? 'rtl' : 'ltr'
                return (
                  <TabsContent key={locale} value={locale} className="space-y-3 pt-2" lang={locale}>
                    <p className="text-xs text-muted-foreground">
                      {locale === 'es' ? t('admin.offers.spanishRequired') : t('admin.offers.fallback', { language: LOCALE_NAMES[locale] })}
                    </p>
                    <div className="space-y-1.5">
                      <Label htmlFor={`${id}-title-${locale}`}>{t('admin.offers.titleField')}</Label>
                      <Input id={`${id}-title-${locale}`} dir={dir} maxLength={160} value={form[`title_${locale}`] ?? ''} onChange={(e) => set(`title_${locale}`, e.target.value || null)} />
                    </div>
                    <div className="space-y-1.5">
                      <Label htmlFor={`${id}-body-${locale}`}>{t('admin.offers.bodyField')}</Label>
                      <Textarea id={`${id}-body-${locale}`} dir={dir} rows={5} maxLength={5000} value={form[`body_${locale}`] ?? ''} onChange={(e) => set(`body_${locale}`, e.target.value || null)} />
                      <p className="text-xs text-muted-foreground">{t('admin.offers.markdown')}</p>
                    </div>
                    <div className="space-y-1.5">
                      <Label htmlFor={`${id}-cta-${locale}`}>{t('admin.offers.ctaField')}</Label>
                      <Input id={`${id}-cta-${locale}`} dir={dir} maxLength={80} placeholder={t('offers.cta')} value={form[`cta_label_${locale}`] ?? ''} onChange={(e) => set(`cta_label_${locale}`, e.target.value || null)} />
                    </div>
                  </TabsContent>
                )
              })}
            </Tabs>
          </fieldset>

          <div className="space-y-2">
            <div className="flex items-center gap-3">
              <Switch id={`${id}-email`} checked={form.send_email} onCheckedChange={(v) => set('send_email', v)} />
              <Label htmlFor={`${id}-email`} className="flex items-center gap-2">
                <Mail className="h-4 w-4 text-muted-foreground" />
                {t('admin.offers.sendEmail')}
              </Label>
            </div>
            <p className="text-xs text-muted-foreground">{t('admin.offers.sendEmailHelp')}</p>
          </div>

          {errors.length > 0 && (
            <ul role="alert" className="space-y-1 text-sm text-destructive">
              {errors.map((message) => (
                <li key={message}>{message}</li>
              ))}
            </ul>
          )}
          <div className="flex items-center gap-3">
            <Button onClick={save} disabled={update.isPending}>
              {t('common.save')}
            </Button>
            {saved && (
              <span role="status" className="flex items-center gap-1.5 text-sm text-success">
                <Check className="h-4 w-4" /> {t('admin.offers.saved')}
              </span>
            )}
          </div>
        </div>

        <div className="space-y-3">
          <p className="text-sm font-semibold">{t('admin.offers.preview')}</p>
          <OfferCard offer={preview} variant="full" stacked />
          <OfferCard offer={preview} variant="compact" />
          <p className="text-xs text-muted-foreground">{t('admin.offers.previewHelp')}</p>
        </div>
      </div>
    </section>
  )
}

/** The preview has no Markdown renderer: the text, escaped, line by line. */
function escapeLines(text: string): string {
  const escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  return escaped ? `<p>${escaped.replace(/\n/g, '<br>')}</p>` : ''
}
