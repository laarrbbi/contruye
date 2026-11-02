/** Where the buy button goes, decided by the page: sign-up, checkout or the course. */
export interface HeroCta {
  to: string
  state?: { from: string }
  label: string
}
