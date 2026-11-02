import { useTranslation } from 'react-i18next'
import { useLessonPreview, type PublicLesson } from '@/api/courses'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { MarkdownContent } from '@/components/MarkdownContent'
import { ContentLanguageNote } from '@/components/player/ContentLanguageNote'

/**
 * A free-preview lesson as its buyer will read it: the written text, with
 * the line that says which language it is in. Readable by any visitor
 * (GET /lessons/{id}/preview serves free previews only). The lessons have no
 * video yet: the text is the sample.
 */
export function LessonPreviewDialog({ lesson, onClose }: { lesson: PublicLesson | null; onClose: () => void }) {
  const { t } = useTranslation()
  const preview = useLessonPreview(lesson?.id ?? null)

  return (
    <Dialog open={lesson !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-h-[85svh] max-w-3xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="bidi-auto pe-6">{preview.data?.title ?? lesson?.title}</DialogTitle>
          <DialogDescription>{t('course.freePreview')}</DialogDescription>
        </DialogHeader>
        {preview.isLoading ? (
          <div aria-busy="true" className="space-y-3 motion-safe:animate-pulse">
            <p className="sr-only">{t('common.loading')}</p>
            <div className="h-4 rounded bg-muted" />
            <div className="h-4 w-11/12 rounded bg-muted" />
            <div className="h-4 w-4/5 rounded bg-muted" />
          </div>
        ) : preview.isError || !preview.data ? (
          <p className="text-sm text-muted-foreground">{t('common.error')}</p>
        ) : (
          <div className="space-y-4">
            <ContentLanguageNote locale={preview.data.content_locale} />
            <MarkdownContent html={preview.data.content_html} lang={preview.data.content_locale} />
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
