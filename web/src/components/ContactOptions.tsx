import { useTranslation } from 'react-i18next'
import { Mail, MessageCircle, Phone } from 'lucide-react'
import { useBusinessProfile } from '@/api/business'
import { IsolatedValue } from '@/components/IsolatedValue'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

/**
 * The ways to reach a human — WhatsApp first, because that is where an
 * Algerian buyer asks before spending 90 000 DA.
 *
 * Renders only the channels the owner has actually configured, and nothing at
 * all when there are none: a contact button that leads nowhere is worse than
 * no button.
 */
export function ContactOptions({
  prefill,
  tone = 'default',
  className,
  showHours = true,
  title,
}: {
  /** Pre-written WhatsApp message, e.g. with the order number. */
  prefill?: string
  /** 'onPrimary' for use on the brand-blue sections. */
  tone?: 'default' | 'onPrimary'
  className?: string
  showHours?: boolean
  /** A line above the buttons, shown only when there is a channel to show. */
  title?: string
}) {
  const { t } = useTranslation()
  const { data: profile } = useBusinessProfile()

  if (!profile || !(profile.whatsapp_url || profile.phone_tel || profile.email)) {
    return null
  }

  const whatsappHref = profile.whatsapp_url
    ? prefill
      ? `${profile.whatsapp_url}?text=${encodeURIComponent(prefill)}`
      : profile.whatsapp_url
    : null

  const secondary = tone === 'onPrimary' ? 'secondary' : 'outline'

  return (
    <div className={cn('space-y-2', className)}>
      {title && <p className="text-sm font-medium">{title}</p>}
      <div className="flex flex-wrap items-center gap-2">
        {whatsappHref && (
          <Button asChild size="sm" variant={tone === 'onPrimary' ? 'secondary' : 'default'}>
            <a href={whatsappHref} target="_blank" rel="noopener noreferrer">
              <MessageCircle className="h-4 w-4" />
              {t('contact.whatsapp')}
            </a>
          </Button>
        )}
        {profile.phone_tel && (
          <Button asChild size="sm" variant={secondary}>
            <a href={profile.phone_tel}>
              <Phone className="h-4 w-4" />
              {/* Numbers read left-to-right in every locale. */}
              <span dir="ltr">{profile.phone}</span>
            </a>
          </Button>
        )}
        {profile.email && (
          <Button asChild size="sm" variant={secondary}>
            <a href={`mailto:${profile.email}`}>
              <Mail className="h-4 w-4" />
              <span dir="ltr">{profile.email}</span>
            </a>
          </Button>
        )}
      </div>
      {showHours && profile.support_hours && (
        <p className={cn('text-xs', tone === 'onPrimary' ? 'text-primary-foreground/70' : 'text-muted-foreground')}>
          {/* Free text in the owner's words: French hours keep their order on
              the Arabic site. */}
          <IsolatedValue i18nKey="contact.hours" name="hours" value={profile.support_hours} />
        </p>
      )}
    </div>
  )
}
