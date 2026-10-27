// The preview as a visitor's browser gets it: the sales page renders, a demo
// student signs in and reaches the course, and nothing in the console says
// otherwise (a CSP refusal, a failed request). Run by .github/workflows/setup.yml.
import { chromium } from 'playwright'

const BASE = process.env.PREVIEW_URL ?? 'http://localhost:8080'
const browser = await chromium.launch()
const page = await browser.newPage()
const errors = []
page.on('pageerror', (error) => errors.push(`page error: ${error}`))
page.on('console', (message) => {
  // A guest's session check answers 401: expected.
  if (message.type() === 'error' && !message.text().includes('401')) errors.push(`console: ${message.text()}`)
})

await page.goto(`${BASE}/`, { waitUntil: 'networkidle' })
await page.getByRole('heading', { level: 1 }).first().waitFor()

await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' })
await page.fill('input[type=email]', 'karim@example.dz')
await page.fill('input[type=password]', 'password')
await page.click('button[type=submit]')
await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15000 })

await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' })
await page.getByText(/Promotion Immobili/i).first().waitFor({ timeout: 15000 })

await browser.close()
if (errors.length > 0) {
  console.error(errors.join('\n'))
  process.exit(1)
}
console.log('The sales page renders; karim@example.dz signs in and reaches the course.')
