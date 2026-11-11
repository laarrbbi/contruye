import { useTranslation } from 'react-i18next'
import { Download, FileSpreadsheet } from 'lucide-react'
import { courseFileUrl, type CourseFile } from '@/api/learn'
import { Button } from '@/components/ui/button'

/**
 * The instructor's own templates for this module, downloaded as he delivered
 * them (materiales/): the module's owners only, as the API checks again.
 */
export function ModuleFiles({ slug, files }: { slug: string; files: CourseFile[] }) {
  const { t } = useTranslation()

  if (files.length === 0) return null

  return (
    <section className="rounded-2xl border border-foreground/[0.08] bg-card">
      <div className="border-b px-5 py-4">
        <h2 className="text-sm font-semibold">{t('templates.moreTitle')}</h2>
        <p className="mt-1 text-sm text-muted-foreground">{t('templates.moreLead')}</p>
      </div>
      <ul className="divide-y">
        {files.map((file) => (
          <li key={file.key} className="flex items-center gap-3 px-5 py-3 text-sm">
            <FileSpreadsheet className="h-4 w-4 shrink-0 text-primary" />
            <span className="min-w-0 flex-1">
              <span className="block font-medium">{t(`documents.files.${file.key}`)}</span>
              <span className="block truncate text-xs text-muted-foreground" dir="ltr">
                {file.name}
              </span>
            </span>
            <Button asChild size="sm" variant="outline">
              <a href={courseFileUrl(slug, file.key)}>
                <Download className="h-4 w-4" />
                {t('player.download')}
              </a>
            </Button>
          </li>
        ))}
      </ul>
    </section>
  )
}
