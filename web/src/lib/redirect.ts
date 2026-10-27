/**
 * Where to send someone after they sign in or sign up.
 *
 * The sales page carries the buyer's intent in two ways — `?redirect=` on the
 * link and `state.from` on the navigation — because a CTA can be a plain <a>.
 * Both are read here so login and registration can never disagree about it,
 * which is how a first-time buyer used to be dropped on an empty dashboard
 * after clicking "Accéder à la formation complète".
 *
 * Only same-origin relative paths are honoured: a redirect parameter is
 * attacker-supplied, and an absolute URL would turn this into an open redirect.
 */
export function safeRedirect(
  search: string,
  state: unknown,
  fallback = '/dashboard',
): string {
  const fromState = (state as { from?: unknown } | null)?.from
  const fromQuery = new URLSearchParams(search).get('redirect')

  for (const candidate of [fromQuery, fromState]) {
    if (typeof candidate !== 'string') continue
    // A single leading slash, and no scheme or protocol-relative prefix.
    if (!candidate.startsWith('/') || candidate.startsWith('//')) continue
    if (/^\/\s*\\/.test(candidate)) continue
    return candidate
  }

  return fallback
}
