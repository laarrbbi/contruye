import { z } from 'zod'

// Zod compiles object validators with `new Function` when it is allowed to.
// The site's Content-Security-Policy forbids that (script-src 'self', no
// eval), and Zod's probe for it, although caught, is reported as a policy
// violation on every page that loads a form. Plain validation is all these
// forms need. Imported instead of 'zod' so the setting always comes first,
// and Zod stays out of the sales page's bundle.
z.config({ jitless: true })

export { z }
