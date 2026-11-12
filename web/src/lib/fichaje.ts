/**
 * Construye Fichaje: Empleo (construction jobs) and Obras (building work),
 * a separate site with its own accounts, in the same design
 * (github.com/laarrbbi/contruye_fichaje). The menu links to it; nothing of
 * it runs here. Its address is set at build time (web/.env.*).
 */
const base = (import.meta.env.VITE_FICHAJE_URL || 'https://construye-fichaje.vercel.app').replace(/\/+$/, '')

export const FICHAJE_EMPLEO_URL = `${base}/empleo`
export const FICHAJE_OBRAS_URL = `${base}/obras`
