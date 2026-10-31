import type { TFunction } from 'i18next'

/**
 * "Módulo 3" for a phase of the promotion, "Financials" for the financial
 * section, which is sold and played like a module but is not a phase.
 */
export function moduleLabel(module: { kind?: string; order: number }, t: TFunction): string {
  return module.kind === 'financials' ? t('player.financialsLabel') : t('player.moduleLabel', { number: module.order })
}

/** The phases of the promotion only: what "12 módulos" counts. */
export function phaseModules<T extends { kind?: string }>(modules: T[]): T[] {
  return modules.filter((module) => module.kind !== 'financials')
}
