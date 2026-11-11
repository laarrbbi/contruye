import type { CourseDetail } from '@/api/courses'
import { phaseModules } from '@/lib/modules'

/**
 * The course's price, the way a buyer reads it at a glance: the price, a
 * higher price struck out, the saving, and how long the price lasts.
 *
 * Every figure is real (constitution I):
 * - during a campaign, the struck price is the course's price and the
 *   countdown runs to the campaign's own end, the same for everyone;
 * - otherwise, the struck price is what the modules cost bought one by one
 *   (the sum of their prices), which is what full access saves.
 */
export function coursePrices(course: Pick<CourseDetail, 'price_eur' | 'campaign' | 'modules'>) {
  const campaign = course.campaign && new Date(course.campaign.ends_at).getTime() > Date.now() ? course.campaign : null
  const price = campaign ? Math.round((course.price_eur * (100 - campaign.percent_off)) / 100) : course.price_eur
  const itemized = phaseModules(course.modules).reduce((sum, module) => sum + (module.price_eur ?? 0), 0)
  const struck = campaign ? course.price_eur : itemized > course.price_eur ? itemized : null
  const percent = struck ? Math.round((1 - price / struck) * 100) : 0

  return { campaign, price, struck, percent }
}
