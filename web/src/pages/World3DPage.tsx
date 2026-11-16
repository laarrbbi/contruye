import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { Link, useLocation, useParams, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  ArrowLeft,
  Building2,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  CircleHelp,
  ClipboardCheck,
  Eye,
  EyeOff,
  FileText,
  Home,
  Landmark,
  Layers,
  ListOrdered,
  Minus,
  Pause,
  Play,
  Plus,
  Volume2,
  VolumeX,
} from 'lucide-react'
import { useUser } from '@/api/auth'
import { useToggleChecklistItem } from '@/api/checklists'
import { apiErrorMessage } from '@/api/orders'
import { lessonsOf, piecesAt, useWorldModule, useWorldOverview } from '@/api/world'
import { useMediaQuery } from '@/hooks/useMediaQuery'
import { usePageTitle } from '@/hooks/usePageTitle'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import { ProvinceWelcome } from '@/components/player/ProvinceWelcome'
import { VideoPlayer } from '@/components/player/VideoPlayer'
import { ProtectedArea } from '@/components/protection/ProtectedArea'
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { readFlag, readMemory, writeFlag, writeMemory } from '@/components/world3d/hud/memory'
import { HowItWorks, ModuleEndPanel, ModuleSoonPanel, ModuleStartPanel } from '@/components/world3d/hud/ModulePanels'
import { StepPanel } from '@/components/world3d/hud/StepPanel'
import { ModuleList, ModuleStrip, StepDots, StepList } from '@/components/world3d/hud/Timelines'
import { plainText, useNarration } from '@/components/world3d/hud/useNarration'
import type { PlaceKey } from '@/components/world3d/places'
import type { WorldHandle } from '@/components/world3d/worldScene'
import { cn } from '@/lib/utils'

const CARD = 'rounded-2xl border border-white/60 bg-white/[0.93] shadow-[0_10px_40px_-16px_rgb(20_26_34/0.45)] backdrop-blur-md'
// A bottom sheet on a phone, built on the dialog.
const SHEET =
  'bottom-0 left-0 top-auto max-h-[85svh] w-full max-w-none translate-x-0 translate-y-0 rounded-b-none rounded-t-2xl p-4 pb-[calc(1rem+env(safe-area-inset-bottom,0px))] sm:rounded-b-none sm:rounded-t-2xl'

/**
 * Aprendizaje 3D: the promotion built before the student's eyes, module after
 * module. The town and its institutions, the plot, the step the lesson is at
 * (where to go, what you ask for and what they give you, the taxes, the
 * checklist, the partners), the lesson's video a tap away, and the whole
 * course as twelve named modules: the building the student has watched rise
 * is the one they own at the end.
 *
 * The step lives in the address (?modulo=1&paso=9): the lesson and its video
 * are left and come back to the same step, and this browser remembers where
 * each module was left (hud/memory.ts).
 */
export function World3DPage() {
  const { slug } = useParams<{ slug: string }>()
  const [params, setParams] = useSearchParams()
  const location = useLocation()
  const { t, i18n } = useTranslation()
  usePageTitle(t('world3d.title'))
  const reduced = usePrefersReducedMotion()
  const wideQuery = useMediaQuery('(min-width: 1024px)')
  const wide = wideQuery ?? (typeof window !== 'undefined' && window.innerWidth >= 1024)
  const { data: user } = useUser()

  const overview = useWorldOverview(slug)
  const modules = useMemo(() => overview.data?.modules ?? [], [overview.data])
  const requested = Number(params.get('modulo')) || null
  const moduleRow = useMemo(() => modules.find((m) => m.id === requested) ?? modules.find((m) => m.steps > 0 && !m.is_locked) ?? modules[0] ?? null, [modules, requested])
  const firstScripted = useMemo(() => modules.find((m) => m.steps > 0 && !m.is_locked) ?? null, [modules])
  const scripted = moduleRow !== null && moduleRow.steps > 0 && !moduleRow.is_locked
  const world = useWorldModule(slug, scripted ? moduleRow.id : null)
  const steps = useMemo(() => world.data?.steps ?? [], [world.data])
  const total = steps.length
  const lessons = useMemo(() => lessonsOf(steps), [steps])

  // ── The step, in the address ───────────────────────────────────────────
  const pasoParam = params.get('paso')
  const index = useMemo(() => {
    if (!world.data || pasoParam === null) return -1
    if (pasoParam === 'fin') return total
    const n = Number(pasoParam)
    return Number.isInteger(n) && n >= 1 ? Math.min(n, total) - 1 : -1
  }, [world.data, pasoParam, total])
  const indexRef = useRef(index)
  indexRef.current = index
  const step = index >= 0 ? (steps[index] ?? null) : null

  const [resumed, setResumed] = useState(false)
  const setStep = useCallback(
    (i: number, options?: { keepResumed?: boolean }) => {
      if (!moduleRow) return
      if (!options?.keepResumed) setResumed(false)
      setParams(
        (prev) => {
          const next = new URLSearchParams(prev)
          next.set('modulo', String(moduleRow.id))
          next.delete('leccion')
          if (i < 0) next.delete('paso')
          else next.set('paso', i >= total ? 'fin' : String(i + 1))
          return next
        },
        { replace: true },
      )
    },
    [moduleRow, total, setParams],
  )
  const focusTitle = () => window.setTimeout(() => document.getElementById('w3d-step-title')?.focus({ preventScroll: true }), 60)

  const [tour, setTour] = useState<number | null>(null)
  const [playing, setPlaying] = useState(false)
  const [voiceOn, setVoiceOn] = useState(false)
  const [labelsOn, setLabelsOn] = useState(true)
  const [sheetOpen, setSheetOpen] = useState(false)
  const [modulesOpen, setModulesOpen] = useState(false)
  const [stepsOpen, setStepsOpen] = useState(false)
  const [video, setVideo] = useState<{ id: number; title: string } | null>(null)
  const [howOpen, setHowOpen] = useState(() => !readFlag('help'))
  const [provinceOpen, setProvinceOpen] = useState(false)
  const [barHeight, setBarHeight] = useState(0)
  const [checked, setChecked] = useState<Record<number, boolean>>({})
  const [visited, setVisited] = useState<Set<number>>(() => new Set())
  const [ready, setReady] = useState(false)
  const [failed, setFailed] = useState(false)
  const narration = useNarration()
  const toggle = useToggleChecklistItem(slug, moduleRow?.id ?? 0)

  const provinceId = user?.province_id ?? null
  const shownProvince = useRef(provinceId)
  useEffect(() => {
    if (shownProvince.current === provinceId) return
    shownProvince.current = provinceId
    if (world.data) void world.refetch()
    // Only a change of province matters.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [provinceId])

  // ── Coming back: the address first, then the lesson asked for, then where this browser left it ──
  const restoredFor = useRef<number | null>(null)
  useEffect(() => {
    if (!world.data || !moduleRow || restoredFor.current === moduleRow.id) return
    restoredFor.current = moduleRow.id
    setChecked({})
    const memory = readMemory(user?.id, slug, moduleRow.id)
    setVisited(new Set((memory?.visited ?? []).filter((i) => i >= 0 && i < total)))
    if (params.get('paso') !== null) {
      setResumed(false)
      return
    }
    // A stored step is trusted only if it is still in the lesson it was in (a new script renumbers).
    const remembered = memory && memory.paso >= 1 && memory.paso <= total && steps[memory.paso - 1]?.lesson?.id === memory.lessonId ? memory.paso - 1 : null
    const lessonId = Number(params.get('leccion')) || null
    if (lessonId) {
      const first = steps.findIndex((s) => s.lesson?.id === lessonId)
      if (remembered !== null && steps[remembered].lesson?.id === lessonId) {
        setStep(remembered, { keepResumed: true })
        setResumed(remembered > first)
      } else setStep(first)
      return
    }
    if (remembered !== null) {
      setStep(remembered, { keepResumed: true })
      setResumed(remembered > 0)
    }
    // Once per module: the address is read when the script arrives.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [world.data, moduleRow?.id])

  // Seen steps, and where the module was left, kept for next time.
  useEffect(() => {
    if (!world.data || !moduleRow || restoredFor.current !== moduleRow.id || index < 0 || index >= total) return
    setVisited((v) => (v.has(index) ? v : new Set(v).add(index)))
  }, [index, world.data, moduleRow, total])
  useEffect(() => {
    if (!world.data || !moduleRow || restoredFor.current !== moduleRow.id || index < 0) return
    const at = steps[Math.min(index, total - 1)]
    writeMemory(user?.id, slug, moduleRow.id, {
      paso: index >= total ? 0 : index + 1,
      visited: [...visited],
      lessonId: at?.lesson?.id ?? null,
      lesson: at?.lesson?.number ?? null,
      total,
      title: at?.title ?? '',
    })
  }, [index, visited, world.data, moduleRow, steps, total, slug, user?.id])

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
  const header = useRef<HTMLDivElement>(null)
  const panel = useRef<HTMLElement>(null)
  const bar = useRef<HTMLDivElement>(null)
  useEffect(() => {
    const measure = () => {
      const isWide = window.innerWidth >= 1024
      const b = bar.current?.getBoundingClientRect()
      const h = header.current?.getBoundingClientRect()
      const p = panel.current?.getBoundingClientRect()
      setBarHeight(Math.round(b?.height ?? 0))
      const rtl = document.documentElement.dir === 'rtl'
      scene.current?.setInsets({
        right: isWide && p ? (rtl ? -1 : 1) * (p.width + 32) : 0,
        bottom: (b?.height ?? 0) + 16,
        top: h ? h.height + 16 : 0,
      })
    }
    measure()
    const observer = new ResizeObserver(measure)
    for (const el of [header.current, panel.current, bar.current]) if (el) observer.observe(el)
    window.addEventListener('resize', measure)
    return () => {
      observer.disconnect()
      window.removeEventListener('resize', measure)
    }
  }, [ready, wide, sheetOpen, world.data, moduleRow?.id, tour, index, i18n.language])

  // ── What the scene shows: the step, or the module's start and end ──────
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
    const finished = index >= total
    h.focus('site')
    h.setActive(['site'])
    h.setMode({
      cutaway: 0,
      dusk: finished ? (overview.data.modules.find((m) => m.id === moduleRow.id)?.dusk ?? 0) : world.data.start_dusk,
    })
  }, [ready, overview.data, moduleRow, world.data, index, step, total, tour])

  // The tour moves on by itself; the construction module takes longer to watch.
  useEffect(() => {
    if (tour === null || !overview.data) return
    const count = overview.data.modules.length
    if (tour >= count) {
      const id = window.setTimeout(() => setTour(null), 9000)
      return () => window.clearTimeout(id)
    }
    // The end of Module 6 is the building going up: it gets the time to be watched.
    const wait = overview.data.modules[tour - 1]?.order === 6 ? 15000 : 6500
    const id = window.setTimeout(() => setTour((v) => (v === null ? null : v + 1)), wait)
    return () => window.clearTimeout(id)
  }, [tour, overview.data])

  useEffect(() => {
    scene.current?.setLabels(labelsOn)
  }, [labelsOn, ready])

  // A tap on a building: the next step that happens there.
  pickRef.current = (key: PlaceKey) => {
    if (tour !== null || !world.data) {
      scene.current?.focus(key)
      return
    }
    const found = steps.findIndex((s, i) => i >= Math.max(0, index) && s.place === key)
    const any = found >= 0 ? found : steps.findIndex((s) => s.place === key)
    if (any >= 0) setStep(any)
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
    const advance = () => setStep(Math.min(indexRef.current + 1, total))
    if (voiceOn) {
      let pending = 0
      narration.speak(text, locale, () => {
        if (playing) pending = window.setTimeout(advance, 900)
      })
      return () => {
        window.clearTimeout(pending)
        narration.stop()
      }
    }
    narration.stop()
    if (!playing) return
    const id = window.setTimeout(advance, 4500 + Math.min(9000, text.length * 45))
    return () => window.clearTimeout(id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [step, voiceOn, playing])
  useEffect(() => {
    if (index >= total) setPlaying(false)
  }, [index, total])

  // Keys: ← → along the steps (mirrored right to left), Home and End, Esc stops the tour.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.altKey || e.metaKey || e.ctrlKey || e.shiftKey) return
      if ((e.target as HTMLElement | null)?.closest('input, textarea, select, [contenteditable="true"]')) return
      if (document.querySelector('[role="dialog"], [role="menu"]')) return
      if (e.key === 'Escape' && tour !== null) {
        setTour(null)
        return
      }
      if (!world.data || tour !== null || total === 0) return
      const rtl = document.documentElement.dir === 'rtl'
      const forward = rtl ? 'ArrowLeft' : 'ArrowRight'
      const backward = rtl ? 'ArrowRight' : 'ArrowLeft'
      if (e.key === forward) setStep(Math.min(indexRef.current + 1, total))
      else if (e.key === backward) setStep(Math.max(indexRef.current - 1, -1))
      else if (e.key === 'Home') setStep(0)
      else if (e.key === 'End') setStep(total - 1)
      else return
      e.preventDefault()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [world.data, tour, total, setStep])

  // ── The folder: what the student has gathered on the steps they have seen ──
  const seen = useMemo(() => [...visited].filter((i) => i < total).sort((a, b) => a - b), [visited, total])
  const papers = useMemo(() => {
    const found = new Map<string, number>()
    for (const i of seen) for (const doc of steps[i].documents) if (!found.has(doc)) found.set(doc, i)
    return [...found.entries()]
  }, [seen, steps])
  const taxes = useMemo(() => {
    const found = new Map<string, number>()
    for (const i of seen) for (const tax of steps[i].taxes) if (!found.has(tax.name)) found.set(tax.name, i)
    return [...found.entries()]
  }, [seen, steps])
  const checklistItems = useMemo(() => {
    const found = new Map<
      number,
      {
        id: number
        order: number
        text: string
        checked: boolean
        step: number
      }
    >()
    steps.forEach((s, i) =>
      s.checklist.forEach(
        (item) =>
          found.has(item.id) ||
          found.set(item.id, {
            id: item.id,
            order: item.order,
            text: plainText(item.text_html),
            checked: item.checked,
            step: i,
          }),
      ),
    )
    return [...found.values()].sort((a, b) => a.order - b.order)
  }, [steps])
  const ticked = (world.data?.checklist ?? []).filter((item) => checked[item.id] ?? item.checked).length

  const selectModule = (id: number) => {
    narration.stop()
    setPlaying(false)
    setTour(null)
    setModulesOpen(false)
    if (id === moduleRow?.id) return
    setParams({ modulo: String(id) }, { replace: true })
  }
  const startTour = () => {
    narration.stop()
    setPlaying(false)
    setModulesOpen(false)
    setSheetOpen(false)
    setTour((v) => (v === null ? 0 : null))
  }
  const onToggle = (itemId: number, value: boolean) => {
    setPlaying(false)
    setChecked((c) => ({ ...c, [itemId]: value }))
    toggle.mutate({ itemId, checked: value })
  }
  const togglePlay = () => {
    if (!playing && (index < 0 || index >= total)) setStep(0)
    setPlaying((p) => !p)
  }
  const openVideo = () => {
    if (!step?.lesson) return
    narration.stop()
    setPlaying(false)
    setVideo({ id: step.lesson.id, title: step.lesson.title })
  }
  const closeHow = () => {
    writeFlag('help')
    setHowOpen(false)
  }

  const from3d = `${location.pathname}${location.search}`
  const backLesson = step?.lesson ?? (lessons[0] ? { id: lessons[0].id, number: lessons[0].number } : null)
  const backTo = backLesson ? `/learn/${slug}/lessons/${backLesson.id}` : `/learn/${slug}`
  const backLabel = backLesson ? t('world3d.backToLesson', { number: backLesson.number }) : t('world3d.backToCourse')
  const tourModule = tour !== null && tour > 0 ? (modules[tour - 1] ?? null) : null
  const nextModule = moduleRow ? (modules.find((m) => m.order === moduleRow.order + 1) ?? null) : null
  const memoryNow = useMemo(() => (index < 0 && moduleRow ? readMemory(user?.id, slug, moduleRow.id) : null), [index, moduleRow, slug, user?.id])
  const resumeAt = memoryNow && memoryNow.paso >= 1 && memoryNow.paso <= total ? memoryNow.paso - 1 : null

  // ── What the step panel holds ──────────────────────────────────────────
  let panelContent: ReactNode = null
  if (moduleRow && tour === null) {
    if (!scripted) {
      panelContent = <ModuleSoonPanel slug={slug ?? ''} module={moduleRow} total={modules.length} firstScripted={firstScripted} onSelectModule={selectModule} onTour={startTour} />
    } else if (world.isError) {
      panelContent = (
        <div role="alert" className="space-y-3 p-5 text-sm">
          <p>{apiErrorMessage(world.error) ?? t('common.error')}</p>
          <button type="button" onClick={() => void world.refetch()} className="inline-flex min-h-10 items-center rounded-lg border px-3.5 font-semibold hover:bg-muted">
            {t('world3d.retry')}
          </button>
        </div>
      )
    } else if (!world.data) {
      panelContent = <p className="p-5 text-sm text-muted-foreground">{t('world3d.loading')}</p>
    } else if (step) {
      panelContent = (
        <StepPanel
          slug={slug ?? ''}
          moduleId={moduleRow.id}
          step={step}
          index={index}
          total={total}
          locale={locale}
          placeName={placeName(step.place)}
          province={world.data.province}
          checked={checked}
          checklistDone={ticked}
          checklistTotal={world.data.checklist_total}
          onToggle={onToggle}
          resumed={resumed}
          onRestart={() => {
            setStep(0)
            focusTitle()
          }}
          next={steps[index + 1] ?? null}
          onNext={() => setStep(Math.min(index + 1, total))}
          onPrev={() => setStep(Math.max(index - 1, -1))}
          onVideo={openVideo}
          from3d={from3d}
          compact={!wide}
          onChooseProvince={() => setProvinceOpen(true)}
        />
      )
    } else if (index >= total) {
      panelContent = (
        <ModuleEndPanel
          slug={slug ?? ''}
          world={world.data}
          total={modules.length}
          phase={moduleRow.phase}
          papers={papers.map(([name]) => name)}
          taxes={taxes.map(([name]) => name)}
          checklistDone={ticked}
          seen={seen.length}
          next={nextModule}
          onSelectModule={selectModule}
          onRestart={() => {
            setVisited(new Set())
            setStep(0)
            focusTitle()
          }}
        />
      )
    } else {
      panelContent = (
        <ModuleStartPanel
          world={world.data}
          total={modules.length}
          lessons={lessons}
          resumeAt={resumeAt}
          onStart={(i) => {
            setStep(i)
            focusTitle()
          }}
        />
      )
    }
  }

  const playLabel = playing ? t('world3d.pause') : t('world3d.play')
  const tools = (
    <>
      <ToolButton label={t('world3d.overview')} onClick={() => scene.current?.overview()}>
        <Home className="h-5 w-5" />
      </ToolButton>
      <ToolButton label={t('world3d.zoomIn')} onClick={() => scene.current?.zoom(0.7)} className="w3d-fine-only">
        <Plus className="h-5 w-5" />
      </ToolButton>
      <ToolButton label={t('world3d.zoomOut')} onClick={() => scene.current?.zoom(1.4)} className="w3d-fine-only">
        <Minus className="h-5 w-5" />
      </ToolButton>
      <ToolButton label={labelsOn ? t('world3d.labelsOff') : t('world3d.labelsOn')} pressed={labelsOn} onClick={() => setLabelsOn((v) => !v)}>
        {labelsOn ? <Eye className="h-5 w-5" /> : <EyeOff className="h-5 w-5" />}
      </ToolButton>
      {narration.supported && (
        <ToolButton label={voiceOn ? t('world3d.voiceOff') : t('world3d.voiceOn')} pressed={voiceOn} onClick={() => setVoiceOn((v) => !v)}>
          {voiceOn ? <Volume2 className="h-5 w-5" /> : <VolumeX className="h-5 w-5" />}
        </ToolButton>
      )}
      <ToolButton label={t('world3d.howTitle')} onClick={() => setHowOpen(true)}>
        <CircleHelp className="h-5 w-5" />
      </ToolButton>
    </>
  )

  const moduleHeading = moduleRow && (
    <>
      <p className="text-[0.7rem] font-bold uppercase tracking-wider text-[#006FFE]">
        {tour !== null
          ? t('world3d.tourAt', { index: tour, total: modules.length })
          : t('world3d.moduleOf', {
              number: moduleRow.order,
              total: modules.length,
            })}
      </p>
      {tour !== null ? (
        <>
          <h1 key={tour} className="w3d-enter mt-0.5 text-lg font-bold leading-snug">
            {tourModule ? `${t('world3d.module', { number: tourModule.order })} · ${tourModule.title}` : t('world3d.tourStart')}
          </h1>
          {tourModule?.phase && <p className="mt-1 text-[0.82rem] leading-snug text-foreground/70">{tourModule.phase.outcome}</p>}
          <span aria-hidden className="mt-3 block h-1.5 overflow-hidden rounded-full bg-foreground/10">
            <span
              className="block h-full rounded-full bg-[#006FFE] transition-[width] duration-700"
              style={{
                width: `${(tour / Math.max(1, modules.length)) * 100}%`,
              }}
            />
          </span>
        </>
      ) : (
        <>
          <h1 className="mt-0.5 line-clamp-2 text-lg font-bold leading-snug" title={moduleRow.title}>
            {moduleRow.title}
          </h1>
          {moduleRow.phase && (
            <p className="mt-1 text-[0.82rem] leading-snug text-foreground/70">
              <span className="font-semibold text-foreground/90">{t('world3d.outcomeLabel')}:</span> {moduleRow.phase.outcome}
            </p>
          )}
        </>
      )}
    </>
  )

  return (
    <div ref={stage} data-plein className="relative h-[calc(100dvh-4rem)] w-full overflow-hidden bg-[#DCE6F2] text-plume">
      <canvas ref={canvas} className="absolute inset-0 h-full w-full touch-none select-none" aria-label={t('world3d.canvasLabel')} />
      {/* The labels' own stacking (CSS2DRenderer orders them by depth) stays under the panels. */}
      <div ref={labelHost} className="pointer-events-none absolute inset-0 isolate z-0" />
      <p className="sr-only" aria-live="polite">
        {step
          ? t('world3d.stepLive', {
              index: index + 1,
              total,
              title: step.title,
              place: placeName(step.place),
            })
          : ''}
      </p>

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

      {wide ? (
        <>
          {/* ── Top: back to the lesson, the module, the folder ── */}
          <div ref={header} className="pointer-events-none absolute start-4 top-4 z-10 w-[25rem] max-w-[calc(100%-2rem)]">
            <div className={cn(CARD, 'pointer-events-auto p-4')}>
              <div className="flex items-center justify-between gap-2">
                <Link to={backTo} state={{ from3d }} className="-ms-1.5 inline-flex min-h-9 items-center gap-1.5 rounded-lg px-1.5 text-sm font-semibold text-foreground/80 hover:bg-muted">
                  <ArrowLeft className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                  {backLabel}
                </Link>
                <span className="inline-flex items-center gap-1.5 text-[0.65rem] font-bold uppercase tracking-wider text-[#006FFE]">
                  <Layers className="h-3.5 w-3.5" aria-hidden />
                  {t('world3d.title')}
                </span>
              </div>
              <div className="mt-2.5">{moduleHeading}</div>
              {world.data && tour === null && (
                <div className="mt-3 flex flex-wrap items-center gap-1.5">
                  <span className="me-0.5 text-[0.7rem] font-bold uppercase tracking-wider text-foreground/50">{t('world3d.folderTitle')}</span>
                  <Folder
                    icon={<FileText className="h-4 w-4 text-[#006FFE]" aria-hidden />}
                    label={t('world3d.kpiPapers')}
                    count={String(papers.length)}
                    title={t('world3d.folderPapers')}
                    empty={t('world3d.folderEmpty')}
                    items={papers.map(([name, i]) => ({
                      key: name,
                      label: name,
                      step: i,
                    }))}
                    onPick={setStep}
                  />
                  <Folder
                    icon={<Landmark className="h-4 w-4 text-or-fonce" aria-hidden />}
                    label={t('world3d.kpiTaxes')}
                    count={String(taxes.length)}
                    title={t('world3d.folderTaxes')}
                    empty={t('world3d.folderEmpty')}
                    items={taxes.map(([name, i]) => ({
                      key: name,
                      label: name,
                      step: i,
                    }))}
                    onPick={setStep}
                  />
                  <Folder
                    icon={<ClipboardCheck className="h-4 w-4 text-success" aria-hidden />}
                    label={t('world3d.kpiChecklist')}
                    count={`${ticked}/${world.data.checklist_total}`}
                    title={t('world3d.checklistTitle', {
                      done: ticked,
                      total: world.data.checklist_total,
                    })}
                    empty={t('world3d.folderEmpty')}
                    items={checklistItems.map((item) => ({
                      key: String(item.id),
                      label: item.text,
                      step: item.step,
                      done: checked[item.id] ?? item.checked,
                    }))}
                    onPick={setStep}
                  />
                </div>
              )}
            </div>
          </div>

          {/* ── The tools, beside the scene ── */}
          <div className="pointer-events-auto absolute start-4 z-10 flex flex-col gap-1.5" style={{ bottom: `calc(${barHeight}px + 1.75rem)` }}>
            {tools}
          </div>

          {/* ── The step (or the module's start, end, «pronto») ── */}
          {panelContent && (
            <aside
              ref={panel}
              className={cn(CARD, 'pointer-events-auto absolute end-4 top-4 z-10 flex w-[26rem] flex-col overflow-hidden xl:w-[28rem]')}
              style={{ bottom: `calc(${barHeight}px + 1.75rem)` }}
            >
              <ProtectedArea className="flex min-h-0 flex-1 flex-col">{panelContent}</ProtectedArea>
            </aside>
          )}

          {/* ── Bottom: the module's steps, the twelve modules ── */}
          <div ref={bar} className={cn(CARD, 'pointer-events-auto absolute inset-x-4 bottom-4 z-10 px-4 py-3')}>
            {world.data && tour === null && total > 0 && (
              <div className="flex items-end gap-4">
                <div className="flex shrink-0 items-center gap-1.5">
                  <IconButton label={t('world3d.previous')} onClick={() => setStep(Math.max(index - 1, -1))}>
                    <ChevronLeft className="h-5 w-5 rtl:-scale-x-100" />
                  </IconButton>
                  <button
                    type="button"
                    onClick={togglePlay}
                    aria-pressed={playing}
                    title={t('world3d.playHint')}
                    className={cn(
                      'inline-flex h-10 items-center gap-2 rounded-xl border px-3.5 text-sm font-bold transition-colors active:scale-95',
                      playing ? 'border-[#006FFE] bg-[#006FFE]/10 text-[#006FFE]' : 'border-[#006FFE]/40 bg-white text-[#006FFE] hover:bg-[#006FFE]/[0.06]',
                    )}
                  >
                    {playing ? <Pause className="h-4 w-4" aria-hidden /> : <Play className="h-4 w-4" aria-hidden />}
                    {playLabel}
                  </button>
                  <IconButton label={t('world3d.next')} onClick={() => setStep(Math.min(index + 1, total))}>
                    <ChevronRight className="h-5 w-5 rtl:-scale-x-100" />
                  </IconButton>
                </div>
                <div className="min-w-0 flex-1 overflow-x-auto overscroll-contain pb-0.5 [scrollbar-width:thin]">
                  <StepDots steps={steps} index={index} visited={visited} onSelect={setStep} />
                </div>
                {overview.data && <TourButton touring={false} onClick={startTour} />}
              </div>
            )}
            {(!world.data || tour !== null || total === 0) && overview.data && (
              <div className="mb-2.5 flex justify-end">
                <TourButton touring={tour !== null} onClick={startTour} />
              </div>
            )}
            {overview.data && (
              <div className={cn(world.data && tour === null && total > 0 && 'mt-3 border-t border-foreground/[0.08] pt-3')}>
                <ModuleStrip overview={overview.data} currentOrder={tour !== null ? (tourModule?.order ?? 0) : (moduleRow?.order ?? 0)} onSelect={selectModule} />
              </div>
            )}
          </div>
        </>
      ) : (
        <>
          {/* ── Phone: the module, and the twelve behind «Módulos» ── */}
          <div ref={header} className="pointer-events-none absolute inset-x-2 top-2 z-10">
            <div className={cn(CARD, 'pointer-events-auto flex items-center gap-1.5 p-1.5')}>
              <Link to={backTo} state={{ from3d }} aria-label={backLabel} title={backLabel} className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl hover:bg-muted">
                <ArrowLeft className="h-5 w-5 rtl:-scale-x-100" aria-hidden />
              </Link>
              <div className="min-w-0 flex-1">
                <p className="truncate text-[0.62rem] font-bold uppercase tracking-wider text-[#006FFE]">
                  {moduleRow &&
                    (tour !== null
                      ? t('world3d.tourAt', {
                          index: tour,
                          total: modules.length,
                        })
                      : t('world3d.moduleOf', {
                          number: moduleRow.order,
                          total: modules.length,
                        }))}
                </p>
                <p className="truncate text-[0.95rem] font-bold leading-tight">
                  {tour !== null ? (tourModule ? `${tourModule.order} · ${tourModule.phase?.name ?? tourModule.title}` : t('world3d.tourStart')) : (moduleRow?.phase?.name ?? moduleRow?.title)}
                </p>
              </div>
              {overview.data && (
                <button type="button" onClick={() => setModulesOpen(true)} className="inline-flex h-10 shrink-0 items-center gap-1 rounded-xl bg-foreground/[0.06] px-3 text-sm font-semibold">
                  {t('world3d.modulesButton')}
                  <ChevronDown className="h-4 w-4" aria-hidden />
                </button>
              )}
            </div>
          </div>

          <div className="pointer-events-auto absolute end-2 top-[4.25rem] z-10 flex flex-col gap-1.5">{tools}</div>

          {/* ── Phone: one dock with the step (folded or open) and ‹ ▶ › ── */}
          <div
            ref={bar}
            className={cn(CARD, 'pointer-events-auto absolute inset-x-2 bottom-[calc(0.5rem+env(safe-area-inset-bottom,0px))] z-10 flex max-h-[min(60svh,calc(100%-5rem))] flex-col overflow-hidden')}
          >
            {tour !== null ? (
              <div className="flex items-center gap-3 p-2.5">
                <p className="min-w-0 flex-1 text-sm font-semibold leading-snug">{tourModule?.phase?.outcome ?? t('world3d.tourStart')}</p>
                <TourButton touring onClick={startTour} />
              </div>
            ) : (
              <>
                {sheetOpen ? (
                  <>
                    <button
                      type="button"
                      onClick={() => setSheetOpen(false)}
                      className="flex h-10 w-full shrink-0 items-center justify-center gap-1.5 border-b border-foreground/[0.08] text-xs font-semibold text-muted-foreground"
                    >
                      {t('world3d.hideStep')}
                      <ChevronDown className="h-4 w-4" aria-hidden />
                    </button>
                    <ProtectedArea className="flex min-h-0 flex-1 flex-col">{panelContent}</ProtectedArea>
                  </>
                ) : (
                  <button type="button" onClick={() => setSheetOpen(true)} className="flex w-full items-center gap-3 px-3.5 py-2.5 text-start">
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-[0.72rem] font-semibold text-[#006FFE]">
                        {step?.lesson?.title ??
                          (moduleRow
                            ? t('world3d.moduleOf', {
                                number: moduleRow.order,
                                total: modules.length,
                              })
                            : '')}
                      </span>
                      <span className="block truncate text-[0.95rem] font-bold leading-snug">
                        {step
                          ? step.title
                          : index >= total && world.data
                            ? t('world3d.moduleDoneTitle', {
                                number: moduleRow?.order,
                              })
                            : world.data
                              ? t('world3d.peekStart', { count: total })
                              : moduleRow?.title}
                      </span>
                    </span>
                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-[#006FFE]/10 px-2.5 py-1 text-xs font-semibold text-[#006FFE]">
                      {t('world3d.showStep')}
                      <ChevronUp className="h-3.5 w-3.5" aria-hidden />
                    </span>
                  </button>
                )}
                {world.data && total > 0 && (
                  <div className="flex shrink-0 items-center gap-1.5 border-t border-foreground/[0.08] px-2 py-2">
                    {index >= 0 && (
                      <IconButton label={t('world3d.previous')} onClick={() => setStep(Math.max(index - 1, -1))}>
                        <ChevronLeft className="h-5 w-5 rtl:-scale-x-100" />
                      </IconButton>
                    )}
                    <button
                      type="button"
                      onClick={() => {
                        if (index < 0) {
                          setStep(0)
                          setSheetOpen(true)
                        } else if (index >= total) setSheetOpen(true)
                        else setStep(index + 1)
                      }}
                      className="flex min-h-11 min-w-0 flex-1 items-center justify-between gap-2 rounded-xl bg-[#006FFE] px-3.5 py-1 text-start text-white active:scale-[0.99]"
                    >
                      <span className="min-w-0">
                        <span className="block truncate text-sm font-bold">
                          {index < 0
                            ? t('world3d.startSteps', { count: total })
                            : index >= total
                              ? t('world3d.finishSummary')
                              : index === total - 1
                                ? t('world3d.finishModule')
                                : t('world3d.nextStep')}
                        </span>
                        {index >= 0 && index < total - 1 && <span className="block truncate text-[0.7rem] text-white/80">{steps[index + 1]?.title}</span>}
                      </span>
                      <ChevronRight className="h-5 w-5 shrink-0 rtl:-scale-x-100" aria-hidden />
                    </button>
                    <IconButton label={playLabel} active={playing} onClick={togglePlay}>
                      {playing ? <Pause className="h-5 w-5" /> : <Play className="h-5 w-5" />}
                    </IconButton>
                    <button
                      type="button"
                      onClick={() => setStepsOpen(true)}
                      aria-label={t('world3d.stepsTitle')}
                      className="inline-flex h-10 shrink-0 items-center gap-1 rounded-xl bg-foreground/[0.06] px-2.5 text-sm font-semibold tabular-nums"
                    >
                      <ListOrdered className="h-4 w-4" aria-hidden />
                      {index >= 0 && index < total ? `${index + 1}/${total}` : null}
                    </button>
                  </div>
                )}
              </>
            )}
          </div>

          <Dialog open={modulesOpen} onOpenChange={setModulesOpen}>
            <DialogContent className={SHEET}>
              <DialogTitle className="pe-8 text-base font-bold">{t('world3d.modulesTitle', { count: modules.length })}</DialogTitle>
              <div className="min-h-0 space-y-3 overflow-y-auto overscroll-contain">
                <TourButton touring={tour !== null} onClick={startTour} wide />
                {overview.data && <ModuleList overview={overview.data} currentOrder={moduleRow?.order ?? 0} onSelect={selectModule} />}
              </div>
            </DialogContent>
          </Dialog>
          <Dialog open={stepsOpen} onOpenChange={setStepsOpen}>
            <DialogContent className={SHEET}>
              <DialogTitle className="pe-8 text-base font-bold">{t('world3d.stepsTitle')}</DialogTitle>
              <div className="min-h-0 overflow-y-auto overscroll-contain">
                <ProtectedArea>
                  <StepList
                    steps={steps}
                    index={index}
                    visited={visited}
                    placeName={placeName}
                    onSelect={(i) => {
                      setStepsOpen(false)
                      setSheetOpen(true)
                      setStep(i)
                    }}
                  />
                </ProtectedArea>
              </div>
            </DialogContent>
          </Dialog>
        </>
      )}

      {/* The lesson's video over the scene: nothing to leave, nothing to come back to. */}
      <Dialog open={video !== null} onOpenChange={(open) => !open && setVideo(null)}>
        <DialogContent className="max-w-3xl gap-3 p-3 sm:p-4">
          <DialogTitle className="pe-10 text-base font-bold leading-snug">{video?.title}</DialogTitle>
          {video && <VideoPlayer key={video.id} lessonId={video.id} watermark={user?.email ?? null} />}
        </DialogContent>
      </Dialog>

      <ProvinceWelcome auto={false} forceOpen={provinceOpen} onClose={() => setProvinceOpen(false)} />

      <Dialog open={howOpen && ready && !failed} onOpenChange={(open) => !open && closeHow()}>
        <DialogContent className="max-w-md border-0 bg-transparent p-0 text-white shadow-none">
          <DialogTitle className="sr-only">{t('world3d.howTitle')}</DialogTitle>
          <HowItWorks onDone={closeHow} phone={!wide} className="rounded-2xl p-5" />
        </DialogContent>
      </Dialog>
    </div>
  )
}

/** A counter of the folder that opens its list: each paper, tax or task, and the step it comes from. */
function Folder({
  icon,
  label,
  count,
  title,
  empty,
  items,
  onPick,
}: {
  icon: ReactNode
  label: string
  count: string
  title: string
  empty: string
  items: { key: string; label: string; step: number; done?: boolean }[]
  onPick(step: number): void
}) {
  const { t } = useTranslation()
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-foreground/[0.05] px-2.5 text-xs font-semibold text-foreground/80 hover:bg-foreground/[0.09] data-[state=open]:bg-foreground/[0.09]"
        >
          {icon}
          {label}:
          <span key={count} className="w3d-pop text-sm font-bold tabular-nums text-plume">
            {count}
          </span>
          <ChevronDown className="h-3.5 w-3.5 opacity-60" aria-hidden />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="max-h-[22rem] w-80 overflow-y-auto">
        <DropdownMenuLabel>{title}</DropdownMenuLabel>
        {items.length === 0 ? (
          <p className="px-2 pb-2 text-sm text-muted-foreground">{empty}</p>
        ) : (
          items.map((item) => (
            <DropdownMenuItem key={item.key} onSelect={() => onPick(item.step)} className="items-start gap-2">
              {item.done !== undefined && (
                <span className={cn('mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border', item.done ? 'border-success bg-success text-white' : 'border-foreground/25')}>
                  {item.done && <Check className="h-3 w-3" aria-hidden />}
                </span>
              )}
              <span className="min-w-0 flex-1 leading-snug">{item.label}</span>
              <span className="shrink-0 text-xs tabular-nums text-muted-foreground">{t('world3d.stepShort', { index: item.step + 1 })}</span>
            </DropdownMenuItem>
          ))
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

function TourButton({ touring, onClick, wide }: { touring: boolean; onClick(): void; wide?: boolean }) {
  const { t } = useTranslation()
  return (
    <button
      type="button"
      onClick={onClick}
      title={touring ? t('world3d.tourStop') : t('world3d.tourHint')}
      className={cn(
        'inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold transition-colors',
        touring ? 'bg-or text-plume' : 'bg-plume text-white hover:bg-plume/90',
        wide && 'w-full',
      )}
    >
      {touring ? <Pause className="h-4 w-4" aria-hidden /> : <Building2 className="h-4 w-4" aria-hidden />}
      {touring ? t('world3d.tourStop') : t('world3d.tour')}
    </button>
  )
}

function ToolButton({ label, onClick, children, pressed, className }: { label: string; onClick(): void; children: ReactNode; pressed?: boolean; className?: string }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      aria-pressed={pressed}
      className={cn(
        CARD,
        'group relative flex h-10 w-10 items-center justify-center rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
        pressed ? 'text-[#006FFE]' : 'text-foreground/70 hover:text-foreground',
        className,
      )}
    >
      {children}
      <span className="pointer-events-none absolute top-1/2 z-20 -translate-y-1/2 whitespace-nowrap rounded-md bg-plume px-2 py-1 text-xs font-semibold text-white opacity-0 shadow transition-opacity duration-150 group-focus-visible:opacity-100 max-lg:end-full max-lg:me-2 lg:start-full lg:ms-2 [@media(hover:hover)]:group-hover:opacity-100">
        {label}
      </span>
    </button>
  )
}

function IconButton({ label, onClick, children, primary, active }: { label: string; onClick(): void; children: ReactNode; primary?: boolean; active?: boolean }) {
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
