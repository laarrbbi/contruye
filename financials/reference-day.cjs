// The day the instructor's package was checked: runs its own test on that day's clock.
//
// curso/2_plantilla_financiera/pruebas/validar.js compares the 24-home scenario with values
// LibreOffice calculated over the original .xls on 31 October 2026. Sixteen cells of
// "6ª Cash-flow" hold today's date (=HOY()), so from 1 November they differ by the days elapsed
// and the test fails, though the engine is right. curso/ is the instructor's and is never edited
// (a correction comes as a new package), so the clock is set from outside instead:
//
//   NODE_OPTIONS="--require $PWD/financials/reference-day.cjs" npm --prefix curso/2_plantilla_financiera/motor test
//
// Only for that test. The service and the platform keep the real date.
const REFERENCE = Date.UTC(2026, 9, 31, 12) // 31 October 2026, midday UTC: the same day in Madrid
const RealDate = Date

class ReferenceDate extends RealDate {
  constructor(...args) {
    super(...(args.length > 0 ? args : [REFERENCE]))
  }

  static now() {
    return REFERENCE
  }
}

globalThis.Date = ReferenceDate
