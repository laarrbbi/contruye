import { useTranslation } from 'react-i18next'
import { AnimatedCounter } from '@/components/AnimatedCounter'
import { ConstruyeMark } from '@/components/brand/ConstruyeMark'
import { ScrollReveal } from '@/components/ScrollReveal'
import { cn } from '@/lib/utils'

/**
 * The instructor's track record as a bento grid.
 *
 * Figures are shown as words rather than a bare "1" — a lone digit reads as a
 * placeholder at this size, while "Un centre commercial" reads as a building.
 *
 * Each tile takes an optional background photograph. Until the real project
 * photos arrive, tiles fall back to a tinted gradient with the Construye mark as a
 * watermark: no stock imagery is used, so nothing implies a project that is
 * not his.
 */
export interface TrackRecordTile {
  id: string
  /** Numeric figure — animates as a counter. Mutually exclusive with `word`. */
  count?: number
  prefix?: string
  /** Spelled-out figure, for values that look poor as a digit. */
  word?: string
  labelKey: string
  eyebrowKey?: string
  /** Public path of the background photograph, when one exists. */
  image?: string
  className?: string
}

const TILES: TrackRecordTile[] = [
  {
    id: 'logements',
    count: 2100,
    prefix: '+',
    labelKey: 'landing.trackRecord.logements',
    // Tours de logements en construction — photo de chantier fournie par le
    // client. Décoratif : le chiffre reste l'information.
    image: '/tours-logements.webp',
    className: 'lg:row-span-2',
  },
  {
    id: 'years',
    count: 16,
    labelKey: 'landing.trackRecord.years',
    eyebrowKey: 'landing.trackRecord.eyebrowExperience',
    image: '/chantier-aerien.webp',
  },
  {
    id: 'cliniques',
    count: 2,
    labelKey: 'landing.trackRecord.cliniques',
  },
  {
    id: 'centre',
    word: 'landing.trackRecord.one',
    labelKey: 'landing.trackRecord.centre',
    eyebrowKey: 'landing.trackRecord.eyebrowDelivered',
    image: '/render-complexe.webp',
  },
  {
    id: 'parc',
    word: 'landing.trackRecord.one',
    labelKey: 'landing.trackRecord.parc',
    eyebrowKey: 'landing.trackRecord.eyebrowDelivered',
  },
]

function Tile({ tile, locale, index }: { tile: TrackRecordTile; locale: string; index: number }) {
  const { t } = useTranslation()

  return (
    // 50ms apart and capped: past the fifth tile they land together, so
    // the grid never takes a second to finish arriving.
    <ScrollReveal delay={Math.min(index, 5) * 50} className={cn('h-full', tile.className)}>
      <article className="relative isolate flex h-full min-h-[180px] flex-col justify-between overflow-hidden rounded-3xl border border-white/10 bg-white/[0.04] p-6">
        {/* Background: the real photograph when we have one… */}
        {tile.image ? (
          <>
            <img
              src={tile.image}
              alt=""
              aria-hidden="true"
              loading="lazy"
              className="pointer-events-none absolute inset-0 -z-10 h-full w-full object-cover"
            />
            {/* Voile uniquement en pied de tuile : la photo reste entièrement
                lisible, le chiffre garde son assise sombre. */}
            <div
              className="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-t from-primary via-primary/30 to-transparent"
              aria-hidden="true"
            />
          </>
        ) : (
          /* …otherwise the brand mark, never a stock photo. */
          <>
            <div
              className="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-br from-white/[0.07] to-transparent"
              aria-hidden="true"
            />
            <ConstruyeMark
              className="pointer-events-none absolute -bottom-6 -end-6 -z-10 h-40 w-40 text-white/[0.05]"
            />
          </>
        )}

        {tile.eyebrowKey && (
          <span className="self-end text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-white/80 drop-shadow-[0_1px_4px_rgba(0,0,0,0.5)]">
            {t(tile.eyebrowKey)}
          </span>
        )}

        <div className="mt-auto pt-8">
          <p className="text-4xl font-extrabold leading-none drop-shadow-[0_2px_10px_rgba(0,0,0,0.55)] sm:text-5xl">
            {tile.word ? (
              t(tile.word)
            ) : (
              <AnimatedCounter end={tile.count ?? 0} prefix={tile.prefix} locale={locale} />
            )}
          </p>
          <p className="mt-2 text-sm font-medium text-white/90 drop-shadow-[0_1px_6px_rgba(0,0,0,0.5)]">
            {t(tile.labelKey)}
          </p>
        </div>
      </article>
    </ScrollReveal>
  )
}

export function TrackRecordBento({ locale }: { locale: string }) {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {TILES.map((tile, index) => (
        <Tile key={tile.id} tile={tile} locale={locale} index={index} />
      ))}
    </div>
  )
}
