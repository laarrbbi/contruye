import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, ChevronLeft, ChevronRight, ClipboardCheck, Eye, EyeOff, FileText, Home, Landmark, Layers, Lock, Minus, Pause, Play, Plus, Volume2, VolumeX } from 'lucide-react'
import { useToggleChecklistItem } from '@/api/checklists'
import { piecesAt, useWorldModule, useWorldOverview } from '@/api/world'
import { usePageTitle } from '@/hooks/usePageTitle'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import { ProtectedArea } from '@/components/protection/ProtectedArea'
import { StepPanel } from '@/components/world3d/hud/StepPanel'
import { CourseTimeline, StepTimeline } from '@/components/world3d/hud/Timelines'
import { plainText, useNarration } from '@/components/world3d/hud/useNarration'
import type { PlaceKey } from '@/components/world3d/places'
import type { WorldHandle } from '@/components/world3d/worldScene'
import { cn } from '@/lib/utils'

const CARD = 'rounded-2xl border border-white/60 bg-white/90 shadow-[0_10px_40px_-16px_rgb(20_26_34/0.45)] backdrop-blur-md'

/**
 * Aprendizaje 3D: the promotion built before the student's eyes, module after
 * module. The town and its institutions, the plot, the step the lesson is at
 * (where to go, which papers come back, which taxes, the checklist), the
 * lesson's video a tap away, and the whole course as a timeline: the building
 * the student has watched rise is the one they own at the end.
 */
export function World3DPage() {
  const { slug } = useParams<{ slug: string }>()
  const [params, setParams] = useSearchParams()
  const { t } = useTranslation()
  usePageTitle(t('world3d.title'))
  const reduced = usePrefersReducedMotion()

  const overview = useWorldOverview(slug)
  const requested = Number(params.get('modulo')) || null
  const moduleRow = useMemo(() => {
    const modules = overview.data?.modules ?? []
    return modules.find((m) => m.id === requested) ?? modules.find((m) => m.steps > 0 && !m.is_locked) ?? modules[0] ?? null
  }, [overview.data, requested])
  const scripted = moduleRow !== null && moduleRow.steps > 0 && !moduleRow.is_locked
  const world = useWorldModule(slug, scripted ? moduleRow.id : null)
  const steps = useMemo(() => world.data?.steps ?? [], [world.data])

  const [index, setIndex] = useState(-1)
  // The tour: the whole promotion, module after module (0 the plot for sale, n the end of module n).
  const [tour, setTour] = useState<number | null>(null)
  const [playing, setPlaying] = useState(false)
  const [voiceOn, setVoiceOn] = useState(false)
  const [labelsOn, setLabelsOn] = useState(true)
  // On a phone the step's sheet starts folded (its title shows), so the world stays in view.
  const [sheet, setSheet] = useState(() => typeof window === 'undefined' || window.innerWidth >= 768)
  const [barHeight, setBarHeight] = useState(0)
  const [checked, setChecked] = useState<Record<number, boolean>>({})
  const [ready, setReady] = useState(false)
  const [failed, setFailed] = useState(false)
  const narration = useNarration()
  const toggle = useToggleChecklistItem(slug, moduleRow?.id ?? 0)

  // Start at the lesson asked for (?leccion=), or at the module's beginning.
  const wantedLesson = Number(params.get('leccion')) || null
  useEffect(() => {
    if (!world.data) return
    const first = wantedLesson ? world.data.steps.findIndex((s) => s.lesson?.id === wantedLesson) : -1
    setIndex(first >= 0 ? first : -1)
    setPlaying(false)
    setChecked({})
  }, [world.data, wantedLesson])

  // ── The scene ──────────────────────────────────────────────────────────
  const canvas = useRef<HTMLCanvasElement>(null)
  const labelHost = useRef<HTMLDivElement>(null)
  const stage = useRef<HTMLDivElement>(null)
  const scene = useRef<WorldHandle | null>(null)
  const placeName = useCallback((key: PlaceKey) => t(`world3d.places.${key}`), [t])
  const placeNameRef = useRef(placeName)
  placeNameRef.current = placeName
  const pickRef = useRef<(key: PlaceKey) => void>(() => {})

  useEffect(() => {
    let disposed = false
    let handle: WorldHandle | null = null
    let observer: ResizeObserver | null = null
    void import('@/components/world3d/worldScene').then(({ createWorld }) => {
      if (disposed || !canvas.current || !labelHost.current) return
      handle = createWorld(canvas.current, labelHost.current, {
        reduced,
        maxPixelRatio: window.matchMedia('(pointer: coarse)').matches ? 1.75 : 2,
        placeName: (key) => placeNameRef.current(key),
        onPick: (key) => pickRef.current(key),
        onFirstFrame: () => setReady(true),
        onFail: () => setFailed(true),
      })
      scene.current = handle
      observer = new ResizeObserver(() => handle?.resize())
      if (stage.current) observer.observe(stage.current)
      setReady(true)
    })
    return () => {
      disposed = true
      observer?.disconnect()
      handle?.dispose()
      scene.current = null
    }
    // The scene is made once; its options read the latest values through refs.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // ── The panels' room: the scene centres what it shows in the free part ──
  const topBar = useRef<HTMLDivElement>(null)
  const panel = useRef<HTMLElement>(null)
  const bottomBar = useRef<HTMLDivElement>(null)
  useEffect(() => {
    const measure = () => {
      const wide = window.innerWidth >= 768
      const p = panel.current?.getBoundingClientRect()
      const b = bottomBar.current?.getBoundingClientRect()
      const top = topBar.current?.getBoundingClientRect()
      setBarHeight(Math.round(b?.height ?? 0))
      scene.current?.setInsets({
        right: wide && p ? p.width + 24 : 0,
        bottom: (b?.height ?? 0) + (!wide && p ? p.height + 4 : 0),
        top: wide ? (top?.height ?? 0) : 0,
      })
    }
    measure()
    const observer = new ResizeObserver(measure)
    for (const el of [panel.current, bottomBar.current, topBar.current]) if (el) observer.observe(el)
    window.addEventListener('resize', measure)
    return () => {
      observer.disconnect()
      window.removeEventListener('resize', measure)
    }
  }, [ready, sheet, world.data, moduleRow?.id])

  // ── What the scene shows: the step, or the module's start and end ──────
  const step = index >= 0 ? steps[index] : null
  useEffect(() => {
    const h = scene.current
    if (!h || !ready || !overview.data || !moduleRow) return
    h.setAutoRotate(tour !== null)
    if (tour !== null) {
      const done = tour === 0 ? null : overview.data.modules[tour - 1]
      h.setPieces(done?.end ?? overview.data.start)
      h.setMode({ cutaway: 0, dusk: done?.dusk ?? 0 })
      h.setActive([])
      h.focus('site')
      return
    }
    if (!world.data) {
      // A module without its script yet (or locked): how the plot stands once it is done.
      const done = overview.data.modules.find((m) => m.id === moduleRow.id)
      h.setPieces(done?.end ?? overview.data.start)
      h.setMode({ cutaway: 0, dusk: done?.dusk ?? 0 })
      h.setActive(['site'])
      h.focus('site')
      return
    }
    h.setPieces(piecesAt(world.data, index))
    if (step) {
      h.focus(step.place)
      h.setActive([step.place])
      h.setMode({ cutaway: step.cutaway ? 1 : 0, dusk: world.data.start_dusk })
      const timers = step.emit.map((kind, i) => window.setTimeout(() => h.emit(step.place, kind), 1300 + i * 450))
      return () => timers.forEach((id) => window.clearTimeout(id))
    }
    const finished = index >= steps.length
    h.focus('site')
    h.setActive(['site'])
    h.setMode({ cutaway: 0, dusk: finished ? (overview.data.modules.find((m) => m.id === moduleRow.id)?.dusk ?? 0) : world.data.start_dusk })
  }, [ready, overview.data, moduleRow, world.data, index, step, steps.length, tour])

  // The tour moves on by itself; the construction module takes longer to watch.
  useEffect(() => {
    if (tour === null || !overview.data) return
    const total = overview.data.modules.length
    if (tour >= total) {
      const id = window.setTimeout(() => setTour(null), 9000)
      return () => window.clearTimeout(id)
    }
    // The end of Module 6 is the building going up: it gets the time to be watched.
    const wait = overview.data.modules[tour - 1]?.order === 6 ? 15000 : 6500
    const id = window.setTimeout(() => setTour((t) => (t === null ? null : t + 1)), wait)
    return () => window.clearTimeout(id)
  }, [tour, overview.data])

  useEffect(() => {
    scene.current?.setLabels(labelsOn)
  }, [labelsOn, ready])

  // A tap on a building: the first step that happens there.
  pickRef.current = (key: PlaceKey) => {
    const found = steps.findIndex((s, i) => i >= Math.max(0, index) && s.place === key)
    const any = found >= 0 ? found : steps.findIndex((s) => s.place === key)
    if (any >= 0) setIndex(any)
    else scene.current?.focus(key)
  }

  // ── The pace of the lesson: read aloud, then the next step ────────────
  const locale = world.data?.content_locale ?? 'es'
  useEffect(() => {
    if (!step) {
      narration.stop()
      return
    }
    const text = `${step.title}. ${plainText(step.quote_html)}`
    if (voiceOn) {
      narration.speak(text, locale, () => {
        if (playing) window.setTimeout(() => setIndex((i) => Math.min(i + 1, steps.length)), 900)
      })
      return () => narration.stop()
    }
    narration.stop()
    if (!playing) return
    const id = window.setTimeout(() => setIndex((i) => Math.min(i + 1, steps.length)), 4500 + Math.min(9000, text.length * 45))
    return () => window.clearTimeout(id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [step, voiceOn, playing])
  useEffect(() => {
    if (index >= steps.length && playing) setPlaying(false)
  }, [index, steps.length, playing])

  // Arrow keys move along the steps.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.target as HTMLElement)?.closest('input, textarea, select')) return
      if (e.key === 'ArrowRight') setIndex((i) => Math.min(i + 1, steps.length))
      if (e.key === 'ArrowLeft') setIndex((i) => Math.max(i - 1, -1))
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [steps.length])

  // ── The folder: every paper and tax met so far in the module ──────────
  const upTo = steps.slice(0, Math.max(0, Math.min(index, steps.length - 1) + 1))
  const papers = useMemo(() => [...new Set(upTo.flatMap((s) => s.documents))], [upTo])
  const taxes = useMemo(() => [...new Set(upTo.flatMap((s) => s.taxes.map((x) => x.name)))], [upTo])
  const ticked = useMemo(() => {
    const all = new Map<number, boolean>()
    for (const s of steps) for (const item of s.checklist) all.set(item.id, checked[item.id] ?? item.checked)
    return [...all.values()].filter(Boolean).length
  }, [steps, checked])

  const selectModule = (id: number) => {
    narration.stop()
    setPlaying(false)
    setParams({ modulo: String(id) }, { replace: true })
  }
  const onToggle = (itemId: number, value: boolean) => {
    setChecked((c) => ({ ...c, [itemId]: value }))
    toggle.mutate({ itemId, checked: value })
  }

  const finished = world.data !== undefined && index >= steps.length
  const backTo = step?.lesson ? `/learn/${slug}/lessons/${step.lesson.id}` : `/learn/${slug}`

  return (
    <div ref={stage} className="relative h-[calc(100dvh-4rem)] w-full overflow-hidden bg-[#DCE6F2] text-plume">
      <canvas ref={canvas} className="absolute inset-0 h-full w-full touch-none select-none" aria-label={t('world3d.canvasLabel')} />
      {/* The labels' own stacking (CSS2DRenderer orders them by depth) stays under the panels. */}
      <div ref={labelHost} className="pointer-events-none absolute inset-0 isolate z-0" />

      {!ready && !failed && (
        <div className="absolute inset-0 flex items-center justify-center">
          <p className="rounded-full bg-white/80 px-4 py-2 text-sm text-muted-foreground shadow">{t('world3d.loading')}</p>
        </div>
      )}
      {failed && (
        <div className="absolute inset-0 flex items-center justify-center p-6">
          <p className={cn(CARD, 'max-w-sm p-5 text-center text-sm')}>{t('world3d.noWebgl')}</p>
        </div>
      )}

      {/* ── Top: back, the module, the counters ── */}
      <div ref={topBar} className="pointer-events-none absolute inset-x-0 top-0 z-10 flex flex-wrap items-start gap-2 p-3 sm:gap-3 sm:p-4">
        <div className={cn(CARD, 'pointer-events-auto flex min-w-0 items-center gap-2 py-1.5 pe-3 ps-1.5')}>
          <Link to={backTo} className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl hover:bg-muted" aria-label={t('world3d.back')} title={t('world3d.back')}>
            <ArrowLeft className="h-5 w-5 rtl:-scale-x-100" aria-hidden />
          </Link>
          <div className="min-w-0">
            <p className="flex items-center gap-1.5 text-[0.65rem] font-semibold uppercase tracking-wider text-[#006FFE]">
              <Layers className="h-3.5 w-3.5" aria-hidden />
              {t('world3d.title')}
            </p>
            <p className="max-w-[16rem] truncate text-sm font-semibold sm:max-w-[22rem]">
              {moduleRow ? `${t('world3d.module', { number: moduleRow.order })} · ${moduleRow.title}` : t('world3d.loading')}
            </p>
          </div>
        </div>

        {world.data && (
          <div className="pointer-events-auto hidden gap-2 md:flex">
            <Kpi label={t('world3d.kpiStep')} value={`${Math.min(Math.max(index + 1, 0), steps.length)}/${steps.length}`} />
            <Kpi label={t('world3d.kpiPapers')} value={String(papers.length)} icon={<FileText className="h-4 w-4 text-[#006FFE]" aria-hidden />} title={papers.join(' · ')} />
            <Kpi label={t('world3d.kpiTaxes')} value={String(taxes.length)} icon={<Landmark className="h-4 w-4 text-or-fonce" aria-hidden />} title={taxes.join(' · ')} />
            <Kpi label={t('world3d.kpiChecklist')} value={`${ticked}/${world.data.checklist_total}`} icon={<ClipboardCheck className="h-4 w-4 text-success" aria-hidden />} />
          </div>
        )}
      </div>

      {tour !== null && overview.data && (
        <aside className={cn(CARD, 'pointer-events-auto absolute end-4 top-24 z-10 hidden w-[22rem] p-5 md:block')}>
          <p className="text-[0.65rem] font-semibold uppercase tracking-wider text-[#006FFE]">{t('world3d.tour')}</p>
          {tour === 0 ? (
            <p key="start" className="w3d-enter mt-2 text-lg font-semibold">{t('world3d.tourStart')}</p>
          ) : (
            <div key={tour} className="w3d-enter mt-2">
              <p className="text-sm font-medium text-muted-foreground">{t('world3d.module', { number: overview.data.modules[tour - 1]?.order })}</p>
              <p className="text-lg font-semibold leading-snug">{overview.data.modules[tour - 1]?.title}</p>
            </div>
          )}
          <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-foreground/10">
            <div className="h-full rounded-full bg-[#006FFE] transition-[width] duration-700" style={{ width: `${(tour / overview.data.modules.length) * 100}%` }} />
          </div>
        </aside>
      )}

      {/* ── Right: the step (a bottom sheet on a phone) ── */}
      {world.data && tour === null && (
        <aside
          ref={panel}
          style={{ '--bar': `${barHeight}px` } as React.CSSProperties}
          className={cn(
            CARD,
            'pointer-events-auto absolute inset-x-2 bottom-[calc(var(--bar)+0.25rem)] z-10 overflow-y-auto overscroll-contain p-4 transition-[max-height] duration-300 sm:p-5',
            'md:inset-x-auto md:bottom-auto md:end-4 md:top-24 md:max-h-[calc(100%-20rem)] md:w-[24rem]',
            sheet ? 'max-md:max-h-[40svh]' : 'max-md:max-h-[6.5rem] max-md:overflow-hidden',
          )}
        >
          <button type="button" onClick={() => setSheet((s) => !s)} className="mx-auto -mt-2 mb-2 block h-1.5 w-12 rounded-full bg-foreground/15 md:hidden" aria-label={t('world3d.togglePanel')} />
          {!sheet && (
            <>
              <span aria-hidden className="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-10 rounded-b-2xl bg-gradient-to-t from-white to-transparent md:hidden" />
              <button type="button" onClick={() => setSheet(true)} className="absolute inset-0 z-20 md:hidden" aria-label={t('world3d.togglePanel')} />
            </>
          )}
          <ProtectedArea>
            {step && slug ? (
              <StepPanel slug={slug} step={step} index={index} total={steps.length} locale={locale} placeName={placeName(step.place)} checked={checked} onToggle={onToggle} />
            ) : finished ? (
              <ModuleEnd title={t('world3d.moduleDone')} text={t('world3d.moduleDoneText', { papers: papers.length, taxes: taxes.length })} />
            ) : (
              <ModuleEnd title={world.data.module.title} text={t('world3d.moduleStart', { steps: steps.length })} start onStart={() => setIndex(0)} startLabel={t('world3d.start')} />
            )}
          </ProtectedArea>
        </aside>
      )}

      {moduleRow && !world.data && overview.data && tour === null && (
        <aside ref={panel} style={{ '--bar': `${barHeight}px` } as React.CSSProperties} className={cn(CARD, 'pointer-events-auto absolute inset-x-2 bottom-[calc(var(--bar)+0.25rem)] z-10 p-5 md:inset-x-auto md:bottom-auto md:end-4 md:top-24 md:w-[22rem]')}>
          {moduleRow.is_locked ? (
            <>
              <p className="flex items-center gap-2 text-sm font-semibold"><Lock className="h-4 w-4" aria-hidden />{t('world3d.locked')}</p>
              <p className="mt-2 text-sm text-muted-foreground">{t('world3d.lockedText')}</p>
              <Link to={`/checkout/${slug}?modules=${moduleRow.id}`} className="mt-3 inline-flex min-h-10 items-center rounded-lg bg-primary px-3.5 text-sm font-semibold text-primary-foreground">{t('world3d.unlock')}</Link>
            </>
          ) : (
            <>
              <p className="text-sm font-semibold">{t('world3d.soon')}</p>
              <p className="mt-2 text-sm text-muted-foreground">{t('world3d.soonText')}</p>
            </>
          )}
        </aside>
      )}

      {/* ── Bottom: the step line, the controls, the promotion ── */}
      <div ref={bottomBar} className="pointer-events-none absolute inset-x-0 bottom-0 z-10 p-2 pb-[calc(0.5rem+env(safe-area-inset-bottom,0px))] sm:p-4">
        <div className={cn(CARD, 'pointer-events-auto mx-auto max-w-5xl p-2.5 sm:p-3')}>
          {world.data && steps.length > 0 && (
            <div className="flex items-center gap-2">
              <div className="flex shrink-0 items-center gap-1">
                <IconButton label={t('world3d.previous')} onClick={() => setIndex((i) => Math.max(i - 1, -1))}><ChevronLeft className="h-5 w-5 rtl:-scale-x-100" /></IconButton>
                <IconButton
                  label={playing ? t('world3d.pause') : t('world3d.play')}
                  primary
                  onClick={() => {
                    if (!playing && index >= steps.length) setIndex(0)
                    else if (!playing && index < 0) setIndex(0)
                    setPlaying((p) => !p)
                  }}
                >
                  {playing ? <Pause className="h-5 w-5" /> : <Play className="h-5 w-5" />}
                </IconButton>
                <IconButton label={t('world3d.next')} onClick={() => setIndex((i) => Math.min(i + 1, steps.length))}><ChevronRight className="h-5 w-5 rtl:-scale-x-100" /></IconButton>
              </div>
              <div className="min-w-0 flex-1">
                <StepTimeline steps={steps} index={index} onSelect={setIndex} />
              </div>
            </div>
          )}
          <div className={cn('flex flex-wrap items-end gap-3', world.data && steps.length > 0 && 'mt-2 border-t pt-2')}>
            {overview.data && (
              <div className="min-w-[14rem] flex-1">
                <CourseTimeline overview={overview.data} currentOrder={tour ?? moduleRow?.order ?? 0} onSelect={(id) => {
                  setTour(null)
                  selectModule(id)
                }} />
              </div>
            )}
            {overview.data && (
              <button
                type="button"
                onClick={() => {
                  narration.stop()
                  setPlaying(false)
                  setTour((t) => (t === null ? 0 : null))
                }}
                title={tour !== null ? t('world3d.tourStop') : t('world3d.tour')}
                className={cn(
                  'inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-semibold transition-colors',
                  tour !== null ? 'bg-or text-plume' : 'bg-plume text-white hover:bg-plume/90',
                )}
              >
                {tour !== null ? <Pause className="h-4 w-4" aria-hidden /> : <Play className="h-4 w-4" aria-hidden />}
                <span className="sm:hidden">{tour !== null ? t('world3d.tourStop') : t('world3d.tourShort')}</span>
                <span className="max-sm:hidden">{tour !== null ? t('world3d.tourStop') : t('world3d.tour')}</span>
              </button>
            )}
            <div className="ms-auto flex items-center gap-1">
              {narration.supported && (
                <IconButton label={voiceOn ? t('world3d.voiceOff') : t('world3d.voiceOn')} active={voiceOn} onClick={() => setVoiceOn((v) => !v)}>
                  {voiceOn ? <Volume2 className="h-5 w-5" /> : <VolumeX className="h-5 w-5" />}
                </IconButton>
              )}
              <IconButton label={labelsOn ? t('world3d.labelsOff') : t('world3d.labelsOn')} active={labelsOn} onClick={() => setLabelsOn((v) => !v)}>
                {labelsOn ? <Eye className="h-5 w-5" /> : <EyeOff className="h-5 w-5" />}
              </IconButton>
              <span className="w3d-fine-only flex items-center gap-1">
                <IconButton label={t('world3d.zoomIn')} onClick={() => scene.current?.zoom(0.7)}><Plus className="h-5 w-5" /></IconButton>
                <IconButton label={t('world3d.zoomOut')} onClick={() => scene.current?.zoom(1.4)}><Minus className="h-5 w-5" /></IconButton>
              </span>
              <IconButton label={t('world3d.overview')} onClick={() => scene.current?.overview()}><Home className="h-5 w-5" /></IconButton>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

function Kpi({ label, value, icon, title }: { label: string; value: string; icon?: React.ReactNode; title?: string }) {
  return (
    <div className={cn(CARD, 'min-w-[7rem] px-3.5 py-2')} title={title}>
      <p className="text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
      <p className="mt-0.5 flex items-center gap-1.5 text-xl font-semibold tabular-nums">
        {icon}
        <span key={value} className="w3d-pop">{value}</span>
      </p>
    </div>
  )
}

function IconButton({ label, onClick, children, primary, active }: { label: string; onClick(): void; children: React.ReactNode; primary?: boolean; active?: boolean }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      title={label}
      className={cn(
        'flex h-10 w-10 items-center justify-center rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring active:scale-95',
        primary ? 'bg-[#006FFE] text-white hover:bg-[#005FE0]' : active ? 'bg-[#006FFE]/10 text-[#006FFE]' : 'text-foreground/70 hover:bg-muted',
      )}
    >
      {children}
    </button>
  )
}

function ModuleEnd({ title, text, start, onStart, startLabel }: { title: string; text: string; start?: boolean; onStart?(): void; startLabel?: string }) {
  return (
    <div className="w3d-enter">
      <h2 className="text-lg font-semibold leading-snug">{title}</h2>
      <p className="mt-2 text-sm text-muted-foreground">{text}</p>
      {start && onStart && (
        <button type="button" onClick={onStart} className="mt-4 inline-flex min-h-10 items-center gap-2 rounded-lg bg-[#006FFE] px-4 text-sm font-semibold text-white hover:bg-[#005FE0]">
          <Play className="h-4 w-4" aria-hidden />
          {startLabel}
        </button>
      )}
    </div>
  )
}
