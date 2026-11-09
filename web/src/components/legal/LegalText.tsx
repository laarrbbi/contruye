import type { ReactNode } from 'react'
import webLinks from '@/content/legal-links.json'

/**
 * A plain string from the app's own copy (an FAQ answer) with the laws it
 * names linked to their official texts, as the server links the course: the
 * same list (config/legal.php, exported by `php artisan legal:export-web`),
 * the same boundaries (no letter, digit or slash glued to either side, never
 * "Decreto…" inside "Real Decreto…"), one link per law.
 */
const escape = (literal: string) => literal.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&')

const byText = new Map(webLinks.links.map((link) => [link.text, link]))

const pattern =
  byText.size > 0
    ? new RegExp(
        `(?<![\\p{L}\\p{N}/])(?<!Real )(?:${[...byText.keys()]
          .sort((a, b) => b.length - a.length)
          .map(escape)
          .join('|')})(?![\\p{L}\\p{N}/]|-[\\p{L}\\p{N}])`,
        'gu',
      )
    : null

export function LegalText({ text }: { text: string }) {
  if (pattern === null) return <>{text}</>

  const parts: ReactNode[] = []
  const linked = new Set<string>()
  let from = 0
  for (const match of text.matchAll(pattern)) {
    const link = byText.get(match[0])
    const at = match.index ?? 0
    if (link === undefined || linked.has(link.url)) continue
    linked.add(link.url)
    parts.push(text.slice(from, at))
    parts.push(
      <a key={at} className="ley" href={link.url} title={link.title} target="_blank" rel="noopener noreferrer">
        {match[0]}
      </a>,
    )
    from = at + match[0].length
  }
  parts.push(text.slice(from))

  return <span className="md-inline">{parts}</span>
}
