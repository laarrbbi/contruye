import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { Download, FileText, Lock } from 'lucide-react'
import { useUser } from '@/api/auth'
import { courseFileUrl, useCourseDocument, useLearn, useProvinceDirectory, type LearnData } from '@/api/learn'
import { provinceLabel, useProvinces } from '@/api/provinces'
import { usePageTitle } from '@/hooks/usePageTitle'
import { MarkdownContent } from '@/components/MarkdownContent'
import { PlayerShell } from '@/components/player/PlayerShell'
import { PlayerSkeleton } from '@/components/player/PlayerSkeleton'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'

/** The package files a student can download, with their gate. */
function Downloads({ data }: { data: LearnData }) {
  const { t } = useTranslation()

  return (
    <section className="rounded-lg border">
      <h2 className="border-b px-4 py-3 text-sm font-semibold">{t('documents.downloadsTitle')}</h2>
      <ul className="divide-y">
        {data.course.files.map((file) => (
          <li key={file.key} className="flex items-center gap-3 px-4 py-3 text-sm">
            {file.is_locked ? (
              <Lock className="h-4 w-4 shrink-0 text-muted-foreground" />
            ) : (
              <FileText className="h-4 w-4 shrink-0 text-primary" />
            )}
            <span className="min-w-0 flex-1">
              <span className="block truncate font-medium">{t(`documents.files.${file.key}`)}</span>
              <span className="block truncate text-xs text-muted-foreground">{file.name}</span>
            </span>
            {file.is_locked ? (
              <span className="shrink-0 text-xs text-muted-foreground">
                {file.access === 'full' ? t('documents.needsFullAccess') : t('documents.needsFinancials')}
              </span>
            ) : (
              <Button asChild size="sm" variant="outline">
                <a href={courseFileUrl(data.course.slug, file.key)}>
                  <Download className="h-4 w-4" />
                  {t('player.download')}
                </a>
              </Button>
            )}
          </li>
        ))}
      </ul>
      <p className="border-t px-4 py-3 text-xs text-muted-foreground">{t('documents.downloadsNote')}</p>
    </section>
  )
}

/** Anexo A, one province at a time: its section of the annex, as written. */
function ProvinceAnnex({ slug }: { slug: string }) {
  const { t } = useTranslation()
  const { data: user } = useUser()
  const { data: provinces } = useProvinces()
  const [chosen, setChosen] = useState<number | null>(null)
  const provinceId = chosen ?? user?.province_id ?? null
  const directory = useProvinceDirectory(slug, provinceId)

  return (
    <section className="space-y-4 rounded-lg border p-4">
      <Select value={provinceId === null ? undefined : String(provinceId)} onValueChange={(value) => setChosen(Number(value))}>
        <SelectTrigger className="w-full sm:w-[280px]" aria-label={t('directory.chooseProvince')}>
          <SelectValue placeholder={t('directory.chooseProvince')} />
        </SelectTrigger>
        <SelectContent>
          {(provinces ?? []).map((province) => (
            <SelectItem key={province.id} value={String(province.id)}>
              {provinceLabel(province)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {provinceId === null ? (
        <p className="text-sm text-muted-foreground">{t('directory.chooseHint')}</p>
      ) : directory.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : directory.data?.section_html ? (
        <>
          <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{directory.data.ccaa}</p>
          <MarkdownContent html={directory.data.section_html} />
        </>
      ) : (
        <p className="text-sm text-muted-foreground">{t('common.error')}</p>
      )}
    </section>
  )
}

/**
 * A course document from the instructor's package (introduction, resources,
 * annexes), rendered as written.
 */
export function CourseDocumentPage() {
  const { slug, documentSlug } = useParams<{ slug: string; documentSlug: string }>()
  const { t } = useTranslation()
  const learn = useLearn(slug)
  const document = useCourseDocument(slug, documentSlug)
  usePageTitle(document.data?.title)

  if (learn.isLoading || document.isLoading) return <PlayerSkeleton />

  if (!learn.data || document.isError || !document.data) {
    const status = isAxiosError(document.error) ? document.error.response?.status : undefined
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <Lock className="h-10 w-10 text-muted-foreground" aria-hidden />
        <h1 className="text-lg font-semibold">{status === 403 ? t('documents.locked') : t('common.error')}</h1>
        <Button asChild variant="outline">
          <Link to={slug ? `/learn/${slug}` : '/dashboard'}>{t('common.back')}</Link>
        </Button>
      </div>
    )
  }

  return (
    <PlayerShell data={learn.data} location={{ document: document.data.slug }}>
      <MarkdownContent html={document.data.body_html} />
      {document.data.slug === 'recursos' && <Downloads data={learn.data} />}
      {document.data.slug === 'anexo-a' && <ProvinceAnnex slug={learn.data.course.slug} />}
    </PlayerShell>
  )
}
