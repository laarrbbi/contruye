import { useTranslation } from 'react-i18next'
import { ScrollReveal } from '@/components/ScrollReveal'
import { cn } from '@/lib/utils'

/**
 * The trainer's own operations, in the order a promotion actually happens:
 * plan → render → structure → finishes → equipment → delivered.
 *
 * Every photograph here was supplied by him. Nothing is stock imagery and
 * nothing is generated — the whole point of the section is that the person
 * teaching the course built these.
 */
// The spans fill every row: 16 cells in four rows of four on a wide screen,
// ten cells in five rows of two on a phone (the plan spans both columns).
// Nothing sits alone at the end.
const SHOTS = [
  { src: '/plan-f4-duplex.webp', key: 'plan', span: 'col-span-2' },
  { src: '/projet-colombes.webp', key: 'render', span: 'sm:col-span-2' },
  { src: '/projet-rotonde.webp', key: 'rotonde', span: 'sm:col-span-2 sm:row-span-2' },
  { src: '/chantier-coffrage.webp', key: 'coffrage', span: '' },
  { src: '/chantier-dalle.webp', key: 'dalle', span: '' },
  { src: '/projet-sejour.webp', key: 'sejour', span: '' },
  { src: '/projet-piscine.webp', key: 'piscine', span: '' },
  { src: '/projet-parking.webp', key: 'parking', span: 'sm:col-span-2' },
  { src: '/projet-facades.webp', key: 'facades', span: 'sm:col-span-2' },
] as const

export function ProjectGallery() {
  const { t } = useTranslation()

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {SHOTS.map((shot, index) => (
          <ScrollReveal
            key={shot.src}
            // 60ms apart: enough to cascade, short enough that the last tile
            // is not still waiting when the reader has moved on.
            delay={Math.min(index, 6) * 60}
            className={cn('min-w-0', shot.span)}
          >
            <figure className="group h-full overflow-hidden rounded-xl border bg-background">
              <img
                src={shot.src}
                alt={t(`landing.gallery.${shot.key}`)}
                loading="lazy"
                decoding="async"
                className={cn(
                  // The ratio reserves each tile's height before its lazy image
                  // arrives. Alone on its row, the plan had none on a phone: it
                  // grew on load and pushed the page down under the reader.
                  'aspect-[4/3] h-full w-full object-cover',
                  // Contain the plan: cropping a floor plan destroys it.
                  shot.key === 'plan' && 'bg-white object-contain p-2',
                  'transition-transform duration-500 ease-out-strong',
                  'motion-safe:group-hover:scale-[1.03]',
                )}
              />
              <figcaption className="border-t px-3 py-2 text-xs leading-snug text-muted-foreground">
                {t(`landing.gallery.${shot.key}`)}
              </figcaption>
            </figure>
          </ScrollReveal>
        ))}
      </div>

      <p className="text-xs text-muted-foreground">{t('landing.gallery.attribution')}</p>
    </div>
  )
}
