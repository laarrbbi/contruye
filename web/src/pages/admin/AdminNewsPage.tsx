import { useId, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { ExternalLink, ImagePlus, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  useAdminNews,
  useDeleteNews,
  useNewsImage,
  useSaveNews,
  type AdminNewsFields,
  type AdminNewsPayload,
  type AdminNewsPost,
} from '@/api/news'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
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
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { LOCALES, LOCALE_NAMES, isRtl, type Locale } from '@/i18n/locales'
import { NEWS_PATH } from '@/i18n/paths'
import { formatDate, toDateInputValue } from './lib'

type Form = AdminNewsPayload & AdminNewsFields

function emptyForm(): Form {
  const form = { slug: null, is_published: false, published_at: null } as Form
  for (const locale of LOCALES) {
    form[`title_${locale}`] = null
    form[`summary_${locale}`] = null
    form[`body_${locale}`] = null
  }
  return form
}

function toForm(post: AdminNewsPost): Form {
  const form = { slug: post.slug, is_published: post.is_published, published_at: toDateInputValue(post.published_at) || null } as Form
  for (const locale of LOCALES) {
    form[`title_${locale}`] = post[`title_${locale}`]
    form[`summary_${locale}`] = post[`summary_${locale}`]
    form[`body_${locale}`] = post[`body_${locale}`]
  }
  return form
}

/** The first message of each field a 422 names, or a generic one. */
function errorList(error: unknown, fallback: string): string[] {
  if (isAxiosError(error) && error.response?.status === 422) {
    const data = error.response.data as { message?: string; errors?: Record<string, string[]> }
    const messages = Object.values(data.errors ?? {}).map((list) => list[0])
    return messages.length > 0 ? messages : [data.message ?? fallback]
  }
  return error ? [fallback] : []
}

type Status = 'draft' | 'scheduled' | 'published'

function statusOf(post: AdminNewsPost): Status {
  if (!post.is_published) return 'draft'
  return post.is_visible ? 'published' : 'scheduled'
}

const STATUS_VARIANT: Record<Status, 'secondary' | 'outline' | 'success'> = {
  draft: 'secondary',
  scheduled: 'outline',
  published: 'success',
}

/**
 * The news, written by the owner: Spanish first, the other languages when
 * there is a translation (the reader sees the Spanish otherwise), a date, a
 * picture. Nothing shows on /noticias until it is published and its date has
 * come.
 */
export function AdminNewsPage() {
  const { t, i18n } = useTranslation()
  const { data: posts, isLoading, isError } = useAdminNews()
  const remove = useDeleteNews()
  const [editing, setEditing] = useState<AdminNewsPost | 'new' | null>(null)
  const [deleting, setDeleting] = useState<AdminNewsPost | null>(null)

  // The open piece, kept current after a save or an upload.
  const current = editing === 'new' || editing === null ? editing : (posts?.find((post) => post.id === editing.id) ?? editing)

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="max-w-2xl space-y-1">
          <h1 className="text-2xl font-bold">{t('admin.news.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('admin.news.lead')}</p>
        </div>
        <Button onClick={() => setEditing('new')}>
          <Plus className="h-4 w-4" />
          {t('admin.news.new')}
        </Button>
      </div>

      {isLoading ? (
        <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
      ) : isError ? (
        <p className="py-12 text-center text-destructive">{t('common.error')}</p>
      ) : !posts || posts.length === 0 ? (
        <div className="rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          <p>{t('admin.news.empty')}</p>
          <Button variant="outline" className="mt-4" onClick={() => setEditing('new')}>
            <Plus className="h-4 w-4" />
            {t('admin.news.new')}
          </Button>
        </div>
      ) : (
        <div className="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('admin.news.table.title')}</TableHead>
                <TableHead>{t('admin.news.table.status')}</TableHead>
                <TableHead>{t('admin.news.table.date')}</TableHead>
                <TableHead className="w-32">
                  <span className="sr-only">{t('admin.common.actions')}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {posts.map((post) => {
                const status = statusOf(post)
                return (
                  <TableRow key={post.id}>
                    <TableCell>
                      <div className="font-medium leading-snug">{post.title_es}</div>
                      <div className="text-xs text-muted-foreground" dir="ltr">
                        {NEWS_PATH}/{post.slug}
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge variant={STATUS_VARIANT[status]}>{t(`admin.news.status.${status}`)}</Badge>
                    </TableCell>
                    <TableCell className="whitespace-nowrap text-muted-foreground">
                      {post.published_at ? formatDate(post.published_at, i18n.language) : '—'}
                    </TableCell>
                    <TableCell>
                      <div className="flex justify-end gap-1">
                        {post.is_visible && (
                          <Button variant="ghost" size="icon" asChild>
                            <Link to={`${NEWS_PATH}/${post.slug}`} target="_blank" rel="noopener" aria-label={t('admin.news.view')}>
                              <ExternalLink className="h-4 w-4" />
                            </Link>
                          </Button>
                        )}
                        <Button variant="ghost" size="icon" aria-label={t('admin.common.edit')} onClick={() => setEditing(post)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" aria-label={t('common.delete')} onClick={() => setDeleting(post)}>
                          <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        </div>
      )}

      <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
        <DialogContent className="max-w-3xl">
          {editing !== null && (
            <NewsEditor
              // A new form for each piece opened.
              key={current === 'new' ? 'new' : current?.id}
              post={current === 'new' ? null : current}
              onSaved={(post) => setEditing(post)}
            />
          )}
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admin.common.confirmDeleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('admin.news.deleteConfirm', { title: deleting?.title_es ?? '' })}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={remove.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={remove.isPending}
              onClick={(event) => {
                event.preventDefault()
                if (deleting) remove.mutate(deleting.id, { onSettled: () => setDeleting(null) })
              }}
            >
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

function NewsEditor({ post, onSaved }: { post: AdminNewsPost | null; onSaved(post: AdminNewsPost): void }) {
  const { t } = useTranslation()
  const id = useId()
  const save = useSaveNews()
  const image = useNewsImage()
  const fileInput = useRef<HTMLInputElement>(null)
  const [form, setForm] = useState<Form>(() => (post ? toForm(post) : emptyForm()))
  const [tab, setTab] = useState<Locale>('es')
  const [saved, setSaved] = useState(false)

  const set = <K extends keyof Form>(key: K, value: Form[K]) => {
    setSaved(false)
    setForm((previous) => ({ ...previous, [key]: value }))
  }

  const submit = () => {
    save.mutate(
      { id: post?.id ?? null, payload: { ...form, slug: form.slug || null, published_at: form.published_at || null } },
      {
        onSuccess: (result) => {
          setSaved(true)
          onSaved(result)
        },
      },
    )
  }

  const errors = errorList(save.error, t('common.error'))

  return (
    <div className="space-y-6">
      <DialogHeader>
        <DialogTitle>{post ? t('admin.news.editTitle') : t('admin.news.createTitle')}</DialogTitle>
        <DialogDescription>{t('admin.news.lead')}</DialogDescription>
      </DialogHeader>

      <Tabs value={tab} onValueChange={(value) => setTab(value as Locale)}>
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
            <TabsContent key={locale} value={locale} className="space-y-4 pt-3" lang={locale}>
              <p className="text-xs text-muted-foreground">
                {locale === 'es' ? t('admin.news.spanishRequired') : t('admin.news.fallback', { language: LOCALE_NAMES[locale] })}
              </p>
              <div className="space-y-1.5">
                <Label htmlFor={`${id}-title-${locale}`}>{t('admin.news.titleField')}</Label>
                <Input
                  id={`${id}-title-${locale}`}
                  dir={dir}
                  maxLength={160}
                  value={form[`title_${locale}`] ?? ''}
                  onChange={(event) => set(`title_${locale}`, event.target.value || null)}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor={`${id}-summary-${locale}`}>{t('admin.news.summary')}</Label>
                <Textarea
                  id={`${id}-summary-${locale}`}
                  dir={dir}
                  rows={2}
                  maxLength={300}
                  value={form[`summary_${locale}`] ?? ''}
                  onChange={(event) => set(`summary_${locale}`, event.target.value || null)}
                />
                <p className="text-xs text-muted-foreground">{t('admin.news.summaryHint')}</p>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor={`${id}-body-${locale}`}>{t('admin.news.body')}</Label>
                <Textarea
                  id={`${id}-body-${locale}`}
                  dir={dir}
                  rows={12}
                  maxLength={50000}
                  value={form[`body_${locale}`] ?? ''}
                  onChange={(event) => set(`body_${locale}`, event.target.value || null)}
                />
                <p className="text-xs text-muted-foreground">{t('admin.news.bodyHint')}</p>
              </div>
            </TabsContent>
          )
        })}
      </Tabs>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor={`${id}-slug`}>{t('admin.news.slug')}</Label>
          <Input
            id={`${id}-slug`}
            dir="ltr"
            maxLength={160}
            placeholder={post ? undefined : 'nueva-ley-de-vivienda'}
            value={form.slug ?? ''}
            onChange={(event) => set('slug', event.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-') || null)}
          />
          <p className="text-xs text-muted-foreground">{t('admin.news.slugHint')}</p>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor={`${id}-date`}>{t('admin.news.publishedAt')}</Label>
          <Input id={`${id}-date`} type="date" dir="ltr" value={form.published_at ?? ''} onChange={(event) => set('published_at', event.target.value || null)} />
          <p className="text-xs text-muted-foreground">{t('admin.news.publishedAtHint')}</p>
        </div>
      </div>

      <div className="flex items-center gap-3">
        <Switch id={`${id}-published`} checked={form.is_published} onCheckedChange={(value) => set('is_published', value)} />
        <Label htmlFor={`${id}-published`}>{t('admin.news.published')}</Label>
      </div>

      <fieldset className="space-y-3">
        <legend className="text-sm font-medium">{t('admin.news.image')}</legend>
        {post ? (
          <div className="flex flex-wrap items-center gap-4">
            {post.image_url && <img src={post.image_url} alt="" className="h-24 w-40 rounded-lg object-cover ring-1 ring-border" />}
            <input
              ref={fileInput}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="sr-only"
              onChange={(event) => {
                const file = event.target.files?.[0]
                if (file) image.mutate({ id: post.id, file })
                event.target.value = ''
              }}
            />
            <Button type="button" variant="outline" disabled={image.isPending} onClick={() => fileInput.current?.click()}>
              <ImagePlus className="h-4 w-4" />
              {t('admin.news.imageUpload')}
            </Button>
            {post.image_url && (
              <Button type="button" variant="ghost" disabled={image.isPending} onClick={() => image.mutate({ id: post.id, file: null })}>
                {t('admin.news.imageRemove')}
              </Button>
            )}
            <p className="basis-full text-xs text-muted-foreground">{t('admin.news.imageHint')}</p>
            {image.isError && <p className="basis-full text-sm text-destructive">{t('admin.news.imageError')}</p>}
          </div>
        ) : (
          <p className="text-xs text-muted-foreground">{t('admin.news.imageAfterSave')}</p>
        )}
      </fieldset>

      {errors.length > 0 && (
        <ul role="alert" className="space-y-1 text-sm text-destructive">
          {errors.map((message) => (
            <li key={message}>{message}</li>
          ))}
        </ul>
      )}

      <div className="flex items-center gap-3">
        <Button onClick={submit} disabled={save.isPending}>
          {save.isPending ? t('common.loading') : t('common.save')}
        </Button>
        <span aria-live="polite" className="text-sm text-muted-foreground">
          {saved ? t('admin.news.saved') : ''}
        </span>
      </div>
    </div>
  )
}
