import { useCallback, useEffect, useRef, useState } from 'react'

/** Invisible characters (the reader's fingerprint) and markup out: what a voice reads. */
export function plainText(html: string | null | undefined): string {
  if (!html) return ''
  return html
    .replace(/<[^>]+>/g, ' ')
    .replace(/[\u200B-\u200F\u2060-\u2064\uFEFF]/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&[a-z]+;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

const LANGS: Record<string, string> = { es: 'es-ES', en: 'en-GB', fr: 'fr-FR', ar: 'ar', ru: 'ru-RU', uk: 'uk-UA' }

/**
 * The step read aloud by the device's own voice (the Web Speech API): the
 * sample's narration, until the recorded voice of each step exists (the plan,
 * docs/aprendizaje-3d.md). Nothing leaves the device.
 */
export function useNarration() {
  const supported = typeof window !== 'undefined' && 'speechSynthesis' in window
  const [speaking, setSpeaking] = useState(false)
  const onEnd = useRef<(() => void) | null>(null)

  const stop = useCallback(() => {
    onEnd.current = null
    if (supported) window.speechSynthesis.cancel()
    setSpeaking(false)
  }, [supported])

  const speak = useCallback(
    (text: string, locale: string, done?: () => void) => {
      if (!supported || !text) {
        done?.()
        return
      }
      window.speechSynthesis.cancel()
      const utterance = new SpeechSynthesisUtterance(text)
      const lang = LANGS[locale] ?? locale
      utterance.lang = lang
      utterance.rate = 1.02
      const voice = window.speechSynthesis.getVoices().find((v) => v.lang.toLowerCase().startsWith(lang.slice(0, 2).toLowerCase()))
      if (voice) utterance.voice = voice
      onEnd.current = done ?? null
      utterance.onend = () => {
        setSpeaking(false)
        const next = onEnd.current
        onEnd.current = null
        next?.()
      }
      utterance.onerror = () => setSpeaking(false)
      setSpeaking(true)
      window.speechSynthesis.speak(utterance)
    },
    [supported],
  )

  useEffect(() => () => {
    if (supported) window.speechSynthesis.cancel()
  }, [supported])

  return { supported, speaking, speak, stop }
}
