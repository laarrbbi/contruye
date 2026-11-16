import { useEffect, useMemo } from 'react'
import { Link, Navigate, useLocation, useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { isAxiosError } from 'axios'
import { ArrowLeft, ArrowRight, Boxes, Calculator, ClipboardCheck, CircleCheck, Download, FileDown, Lock, Video } from 'lucide-react'
import { useUser } from '@/api/auth'
import {
  attachmentUrl,
  reflexSheetUrl,
  sendProgress,
  useLearn,
  useLessonContent,
  useModuleContent,
  useToggleComplete,
  type LearnLesson,
} from '@/api/learn'
import { useChecklists } from '@/api/checklists'
import { useWorldOverview } from '@/api/world'
import { readMemory } from '@/components/world3d/hud/memory'
import { ModuleChecklist } from '@/components/player/ModuleChecklist'
import { Button } from '@/components/ui/button'
import { PlayerShell } from '@/components/player/PlayerShell'
import { ContentBlocks } from '@/components/player/ContentBlocks'
import { LessonResources } from '@/components/player/LessonResources'
import { ModuleOverview } from '@/components/player/ModuleOverview'
import { ProvinceGuidePanel } from '@/components/player/ProvinceGuidePanel'
import { VideoPlayer } from '@/components/player/VideoPlayer'
import { MarkdownContent } from '@/components/MarkdownContent'
import { ContentLanguageNote } from '@/components/player/ContentLanguageNote'
import { moduleLabel } from '@/lib/modules'
import { cn, formatDuration } from '@/lib/utils'
import { PlayerSkeleton } from '@/components/player/PlayerSkeleton'
import { apiErrorMessage } from '@/api/orders'

/** Still lines where the lesson's text is about to be, the second it takes to arrive. */
function LessonTextSkeleton() {
  return (
    <div aria-hidden className="space-y-3">
      {['w-11/12', 'w-full', 'w-10/12', 'w-9/12'].map((width) => (
        <div key={width} className={cn('h-3.5 rounded-full bg-foreground/[0.06]', width)} />
      ))}
    </div>
  )
}

const HEARTBEAT_INTERVAL_MS = 30_000

export function PlayerPage() {
  const { slug, lessonId } = useParams<{ slug: string; lessonId?: string }>()
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: user } = useUser()
  const { data, isLoading, isError, error } = useLearn(slug)
  const toggleComplete = useToggleComplete(slug)
  const checklists = useChecklists(slug)
  // Which modules have their 3D script: the lesson offers its steps in the 3D world.
  const worldOverview = useWorldOverview(slug)
  const worldModules = useMemo(() => new Set((worldOverview.data?.modules ?? []).filter((m) => m.steps > 0 && !m.is_locked).map((m) => m.id)), [worldOverview.data])
  // Came from the 3D page («Abrir la lección»): a way back to the very step.
  const location = useLocation()
  const from3dState = (location.state as { from3d?: unknown } | null)?.from3d
  const from3d = typeof from3dState === 'string' && from3dState.startsWith(`/learn/${slug}/mundo-3d`) ? from3dState : null
  // Where the way back goes: the step this browser last saw in that module (the 3D page moves
  // by replacing its address, so the one carried here can be older), else the address carried.
  const from3dQuery = new URLSearchParams(from3d?.split('?')[1] ?? '')
  const from3dMemory = from3d ? readMemory(user?.id, slug, Number(from3dQuery.get('modulo')) || null) : null
  const from3dStep = from3dMemory && from3dMemory.paso >= 1 ? from3dMemory.paso : from3d ? Number(from3dQuery.get('paso')) || null : null
  const back3d = from3d && from3dMemory && from3dMemory.paso >= 1 ? `/learn/${slug}/mundo-3d?modulo=${from3dQuery.get('modulo')}&paso=${from3dMemory.paso}` : from3d

  const flatLessons = useMemo<LearnLesson[]>(
    () => (data ? data.modules.flatMap((module) => module.lessons) : []),
    [data],
  )

  const currentLessonId = lessonId ? Number(lessonId) : null
  const currentIndex = flatLessons.findIndex((lesson) => lesson.id === currentLessonId)
  const currentLesson = currentIndex >= 0 ? flatLessons[currentIndex] : null
  usePageTitle(currentLesson?.title)

  // Module owning the current lesson — powers the locked-module upsell link.
  const currentModule = useMemo(
    () => data?.modules.find((module) => module.lessons.some((l) => l.id === currentLessonId)) ?? null,
    [data, currentLessonId],
  )

  // The texts of this page, each fetched on its own (the outline has none).
  const isLastOfModule = currentModule?.lessons[currentModule.lessons.length - 1]?.id === currentLessonId
  const lessonContent = useLessonContent(slug, currentLesson && !currentLesson.is_locked ? currentLesson.id : null)
  const moduleContent = useModuleContent(slug, currentModule && !currentModule.is_locked && isLastOfModule ? currentModule.id : null)
  const lessonText = lessonContent.data

  // Prev/next only visit playable lessons, so navigation skips locked modules.
  const previousLesson = useMemo(() => {
    if (currentIndex < 0) return null
    const before = flatLessons.slice(0, currentIndex).filter((l) => !l.is_locked)
    return before.length > 0 ? before[before.length - 1] : null
  }, [flatLessons, currentIndex])
  const nextLesson = useMemo(
    () => (currentIndex < 0 ? null : (flatLessons.slice(currentIndex + 1).find((l) => !l.is_locked) ?? null)),
    [flatLessons, currentIndex],
  )

  // Resume position, frozen per lesson so heartbeat refetches don't reload the iframe.
  const startAt = useMemo(
    () => (currentLesson && !currentLesson.is_completed ? currentLesson.last_position_seconds : 0),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [currentLesson?.id],
  )

  // Heartbeat: persist the watch position every 30s while the lesson is open.
  // Locked lessons never play, so they never heartbeat.
  const heartbeatLessonId =
    currentLesson && !currentLesson.is_locked && !currentLesson.is_completed ? currentLesson.id : null
  useEffect(() => {
    if (heartbeatLessonId == null || !currentLesson) return
    const initialPosition = currentLesson.last_position_seconds ?? 0
    const duration = currentLesson.duration_seconds
    const startedAt = Date.now()

    const timer = window.setInterval(() => {
      const elapsed = Math.round((Date.now() - startedAt) / 1000)
      const position = Math.min(initialPosition + elapsed, duration)
      sendProgress(heartbeatLessonId, position).catch(() => {
        // Heartbeats are best-effort; the next tick retries.
      })
    }, HEARTBEAT_INTERVAL_MS)

    return () => window.clearInterval(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [heartbeatLessonId])

  if (isLoading) {
    return <PlayerSkeleton />
  }

  if (isError || !data) {
    const status = isAxiosError(error) ? error.response?.status : undefined
    const notEnrolled = status === 403 || status === 404
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <Lock className="h-10 w-10 text-muted-foreground" aria-hidden />
        <h1 className="titre text-2xl">{notEnrolled ? t('player.notEnrolled') : t('common.error')}</h1>
        {/* Not enrolled: the sales page is the only way forward, so it is the primary action. */}
        <Button asChild variant={notEnrolled ? 'default' : 'outline'}>
          <Link to={notEnrolled ? `/cursos/${slug}` : '/dashboard'}>
            {notEnrolled ? t('player.goToCourse') : t('common.back')}
          </Link>
        </Button>
      </div>
    )
  }

  if (flatLessons.length === 0) {
    return (
      <div className="container flex min-h-[50vh] items-center justify-center text-muted-foreground">
        {t('player.noLessons')}
      </div>
    )
  }

  // No lesson in the URL (or a stale id): jump to where the student left off.
  if (!currentLesson) {
    const target = data.next_lesson_id ?? flatLessons[0].id
    return <Navigate to={`/learn/${data.course.slug}/lessons/${target}`} replace />
  }

  const handleToggleComplete = () => {
    toggleComplete.mutate({ lessonId: currentLesson.id, completed: !currentLesson.is_completed })
  }

  const isFirstOfModule = currentModule?.lessons[0]?.id === currentLesson.id
  // Aprendizaje 3D: where this browser left the module's steps, to carry on from the lesson.
  const world3d = currentModule && worldModules.has(currentModule.id) ? readMemory(user?.id, data.course.slug, currentModule.id) : null
  const world3dHere = world3d && world3d.paso >= 1 && world3d.lessonId === currentLesson.id ? world3d : null
  const world3dElsewhere = world3d && world3d.paso >= 1 && !world3dHere ? world3d : null
  const moduleChecklist = checklists.data?.modules.find(
    (m) => m.id === currentModule?.id && !m.is_locked && (m.items.length > 0 || m.custom_tasks.length > 0),
  )

  return (
    <PlayerShell data={data} location={{ lessonId: currentLesson.id }}>
      {back3d && (
        <div className="sticky top-16 z-20 -mt-1 mb-4">
          <Link
            to={back3d}
            className="inline-flex min-h-11 items-center gap-2 rounded-full bg-plume px-4 text-sm font-semibold text-white shadow-[0_10px_30px_-12px_rgb(11_26_46/0.7)] transition-transform duration-150 hover:-translate-y-0.5"
          >
            <ArrowLeft className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
            {from3dStep ? t('world3d.backTo3dStep', { index: from3dStep }) : t('world3d.backTo3d')}
          </Link>
        </div>
      )}
      {currentLesson.is_locked ? (
        /* Locked lesson: tease the title, sell the module. */
        <div className="rounded-xl border bg-secondary/40 p-8 text-center">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-background shadow-sm">
            <Lock className="h-7 w-7 text-muted-foreground" />
          </div>
          <p className="bidi-auto mt-4 text-sm text-muted-foreground">{currentLesson.title}</p>
          <h1 className="titre mt-2 text-[clamp(1.5rem,1.2rem+1.2vw,2.25rem)]">{t('player.lockedHeading')}</h1>
          <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">{t('player.lockedBody')}</p>
          {currentModule && currentModule.partners_count > 0 && (
            <p className="mt-2 text-sm font-medium text-primary">
              {t('partners.lockedTease', { count: currentModule.partners_count })}
            </p>
          )}
          <Button asChild className="mt-5">
            <Link to={`/checkout/${data.course.slug}?modules=${currentModule?.id ?? ''}`}>
              <Lock className="h-4 w-4" />
              {t('player.unlockModule')}
            </Link>
          </Button>
        </div>
      ) : (
        <>
          {currentLesson.has_video ? (
            <VideoPlayer
              key={currentLesson.id}
              lessonId={currentLesson.id}
              startAt={startAt}
              watermark={user?.email ?? null}
            />
          ) : (
            /* Written content ships before the video is shot — say so
               plainly rather than showing a playback error. */
            <div className="theme-night relative flex aspect-[21/9] flex-col items-center justify-center gap-2 overflow-hidden rounded-2xl bg-[radial-gradient(110%_90%_at_50%_10%,#12253F,#0B1A2E_55%,#070F1C)] text-center">
              <div aria-hidden className="absolute inset-0 bg-[linear-gradient(to_right,rgb(246_242_234/0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgb(246_242_234/0.04)_1px,transparent_1px)] bg-[size:40px_40px]" />
              <Video className="relative h-8 w-8 text-or" />
              <p className="titre relative text-lg">{t('player.videoSoon')}</p>
              <p className="relative max-w-sm px-6 text-xs text-muted-foreground">{t('player.videoSoonHint')}</p>
            </div>
          )}

          {/* Lesson header + actions */}
          <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              {currentModule && (
                <p className="etiquette flex items-center gap-3 text-foreground/60">
                  <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
                  <span>
                    {moduleLabel(currentModule, t)} · <bdi>{currentModule.title}</bdi>
                  </span>
                </p>
              )}
              <h1 className="bidi-auto titre mt-4 text-[clamp(1.6rem,1.25rem+1.2vw,2.35rem)]">{currentLesson.title}</h1>
              {formatDuration(currentLesson.duration_seconds, t) && (
                <p className="mt-2 text-sm text-muted-foreground">{formatDuration(currentLesson.duration_seconds, t)}</p>
              )}
            </div>
            <Button
              variant={currentLesson.is_completed ? 'secondary' : 'default'}
              className="shrink-0"
              // Not disabled while saving: the toggle is optimistic, so the
              // button already shows the new state; locking it for the
              // round trip only made it feel slow.
              onClick={handleToggleComplete}
            >
              <CircleCheck className={cn('h-4 w-4', currentLesson.is_completed ? 'text-success' : undefined)} />
              {currentLesson.is_completed ? t('player.markIncomplete') : t('player.markComplete')}
            </Button>
          </div>

          {/* Aprendizaje 3D: this lesson's steps in the town, for a module that has its script. */}
          {currentModule && worldModules.has(currentModule.id) && (
            <div className="space-y-2">
            <Link
              to={
                world3dHere
                  ? `/learn/${data.course.slug}/mundo-3d?modulo=${currentModule.id}&paso=${world3dHere.paso}`
                  : `/learn/${data.course.slug}/mundo-3d?modulo=${currentModule.id}&leccion=${currentLesson.id}`
              }
              className="group relative flex items-center gap-4 overflow-hidden rounded-2xl bg-[radial-gradient(120%_120%_at_85%_0%,#1F3A5F,#0B1A2E_60%)] p-4 text-white shadow-[0_24px_48px_-30px_rgb(11_26_46/0.8)] transition-transform duration-200 hover:-translate-y-0.5 sm:p-5"
            >
              <span aria-hidden className="absolute inset-0 bg-[linear-gradient(to_right,rgb(246_242_234/0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgb(246_242_234/0.06)_1px,transparent_1px)] bg-[size:28px_28px]" />
              <span aria-hidden className="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#006FFE]">
                <Boxes className="h-6 w-6" />
              </span>
              <span className="relative min-w-0 flex-1">
                <span className="block text-[0.65rem] font-semibold uppercase tracking-wider text-or">{t('world3d.title')}</span>
                <span className="block font-semibold">
                  {world3dHere ? t('world3d.ctaResume', { index: world3dHere.paso, total: world3dHere.total }) : t('world3d.cta')}
                </span>
                <span className="mt-0.5 block text-sm text-white/70">{world3dHere ? world3dHere.title : t('world3d.ctaText')}</span>
              </span>
              <ArrowRight aria-hidden className="relative h-5 w-5 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5 rtl:-scale-x-100" />
            </Link>
            {world3dElsewhere && (
              <Link
                to={`/learn/${data.course.slug}/mundo-3d?modulo=${currentModule.id}&paso=${world3dElsewhere.paso}`}
                className="inline-flex min-h-10 items-center gap-1.5 px-1 text-sm font-semibold text-primary hover:underline"
              >
                {t('world3d.ctaElsewhere', { index: world3dElsewhere.paso, lesson: world3dElsewhere.lesson ?? '' })}
                <ArrowRight aria-hidden className="h-4 w-4 rtl:-scale-x-100" />
              </Link>
            )}
            </div>
          )}
        </>
      )}

      {/* The lesson, as the instructor wrote it */}
      {!currentLesson.is_locked && lessonContent.isLoading && <LessonTextSkeleton />}
      {!currentLesson.is_locked && lessonContent.isError && (
        <p role="alert" className="rounded-2xl bg-foreground/[0.04] px-5 py-4 text-sm text-foreground/70">
          {apiErrorMessage(lessonContent.error) ?? t('common.error')}
        </p>
      )}
      {!currentLesson.is_locked && lessonText?.content_html && (
        <article aria-label={currentLesson.title} className="space-y-3">
          <ContentLanguageNote locale={lessonText.content_locale} />
          <MarkdownContent html={lessonText.content_html} lang={lessonText.content_locale} />
        </article>
      )}

      {/* Key points of the lesson */}
      {!currentLesson.is_locked && lessonText && lessonText.key_points.length > 0 && (
        <section className="rounded-2xl border border-foreground/[0.08] bg-card p-6 shadow-[0_24px_48px_-34px_rgb(20_26_34/0.38)] sm:p-8">
          <h2 className="etiquette flex items-center gap-3 text-foreground/60">
            <span aria-hidden className="h-px w-6 shrink-0 bg-or" />
            {t('player.keyPoints')}
          </h2>
          <ul className="mt-5 space-y-3">
            {lessonText.key_points.map((point, index) => (
              <li key={index} className="flex gap-3 text-[0.9375rem] leading-relaxed">
                <span className="mt-[0.6rem] h-px w-3 shrink-0 bg-or" aria-hidden />
                <span className="bidi-auto">{point}</span>
              </li>
            ))}
          </ul>
        </section>
      )}

      {/* Printable safety sheet */}
      {!currentLesson.is_locked && currentLesson.has_reflex_sheet && (
        <Button asChild variant="outline">
          <a href={reflexSheetUrl(currentLesson.id)}>
            <FileDown className="h-4 w-4" />
            {t('player.downloadReflexSheet')}
          </a>
        </Button>
      )}

      {/* Tagged blocks of the lesson */}
      {!currentLesson.is_locked && lessonText && <ContentBlocks blocks={lessonText.blocks} />}

      {/* Attachments */}
      {currentLesson.attachments.length > 0 && (
        <section className="rounded-lg border">
          <h2 className="border-b px-4 py-3 text-sm font-semibold">{t('player.attachments')}</h2>
          <ul className="divide-y">
            {currentLesson.attachments.map((attachment) => (
              <li key={attachment.index}>
                <button
                  type="button"
                  className="flex w-full items-center gap-3 px-4 py-3 text-start text-sm transition-colors hover:bg-secondary"
                  onClick={() => window.open(attachmentUrl(currentLesson.id, attachment.index))}
                >
                  <Download className="h-4 w-4 shrink-0 text-primary" />
                  <span className="bidi-auto flex-1 truncate">{attachment.name}</span>
                  <span className="shrink-0 text-xs text-muted-foreground">{t('player.download')}</span>
                </button>
              </li>
            ))}
          </ul>
        </section>
      )}

      {/* Under the text: the websites the lesson names and the partners it calls on. */}
      {!currentLesson.is_locked && currentModule && lessonText?.resources && (
        <LessonResources slug={data.course.slug} lessonId={currentLesson.id} module={currentModule} resources={lessonText.resources} />
      )}

      {/* The module's objective, laws and where to go: after the lesson, not
          before it, so the lesson is what the student reads first. */}
      {!currentLesson.is_locked && currentModule && !currentModule.is_locked && (
        <ModuleOverview key={currentModule.id} slug={data.course.slug} module={currentModule} defaultOpen={isFirstOfModule} />
      )}

      {/* What closes the module, after its last lesson: the province's
          partners, the checklist, the calculator and the quiz. */}
      {!currentLesson.is_locked && currentModule && !currentModule.is_locked && isLastOfModule && (
        <>
          <ContentBlocks blocks={(moduleContent.data?.blocks ?? []).filter((block) => block.type === 'section')} />

          {currentModule.kind === 'phase' && (
            <ProvinceGuidePanel slug={data.course.slug} phase={currentModule.order} showAnnexLink={data.course.full_access} />
          )}

          {moduleChecklist && <ModuleChecklist slug={slug} module={moduleChecklist} />}

          {currentModule.kind === 'financials' && (
            <Link
              to={`/learn/${data.course.slug}/financials`}
              className="flex items-center gap-4 rounded-lg border border-primary/30 bg-primary/5 p-4 transition-colors hover:bg-primary/10"
            >
              <Calculator className="h-8 w-8 shrink-0 text-primary" />
              <span className="flex-1">
                <span className="block text-sm font-semibold">{t('financials.ctaTitle')}</span>
                <span className="block text-sm text-muted-foreground">{t('financials.ctaBody')}</span>
              </span>
            </Link>
          )}

          {currentModule.quiz && (
            <Link
              to={`/learn/${data.course.slug}/quizzes/${currentModule.quiz.id}`}
              className="flex items-center gap-4 rounded-lg border p-4 transition-colors hover:bg-secondary"
            >
              <ClipboardCheck className={cn('h-8 w-8 shrink-0', currentModule.quiz.passed ? 'text-success' : 'text-primary')} />
              <span className="flex-1">
                <span className="block text-sm font-semibold">{currentModule.quiz.title}</span>
                <span className="block text-sm text-muted-foreground">
                  {currentModule.quiz.passed
                    ? t('quiz.ctaPassed', { score: currentModule.quiz.best_score, total: currentModule.quiz.questions_count })
                    : t('quiz.ctaBody', { pass: currentModule.quiz.pass_mark, total: currentModule.quiz.questions_count })}
                </span>
              </span>
            </Link>
          )}
        </>
      )}

      {/* Prev / next */}
      <div className="flex items-center justify-between gap-3 border-t pt-4">
        <Button
          variant="outline"
          disabled={!previousLesson}
          onClick={() => previousLesson && navigate(`/learn/${data.course.slug}/lessons/${previousLesson.id}`)}
        >
          {t('player.previousLesson')}
        </Button>
        <Button
          variant="outline"
          disabled={!nextLesson}
          onClick={() => nextLesson && navigate(`/learn/${data.course.slug}/lessons/${nextLesson.id}`)}
        >
          {t('player.nextLesson')}
        </Button>
      </div>
    </PlayerShell>
  )
}
