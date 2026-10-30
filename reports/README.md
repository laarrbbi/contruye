# Reports

These reports were written about **Ebni Academy**, the Algerian edition of this platform, before
it was adapted for Spain as Construye Academy. They are kept because they are the evidence behind
the constitution (`.specify/memory/constitution.md`): the defects they describe, the IDs they give
(B12, C15, S6…) and the tests that close them are all still here.

Read them with the adaptation in mind:

| In the reports (Ebni) | Here (Construye) |
|---|---|
| Dinars (DZD) and euros, two prices per course | Euros only: `price_eur` |
| Chargily (CIB / Edahabia), Stripe for the diaspora | Stripe (bank card) |
| CCP and BaridiMob transfers with a receipt | Bank transfer and Bizum, with a receipt and the reference `CONSTRUYE-<order>` |
| 58 wilayas | 52 provinces (INE codes) |
| French authored, Arabic (RTL) and English translations | Spanish authored, English translation |
| Factures with RC, NIF, NIS, AI, TVA 19 %/9 %, `FrenchNumberSpeller` | Facturas with NIF and Registro Mercantil, IVA 21/10/4/0 %, `SpanishNumberSpeller` |
| Algiers business calendar | Madrid business calendar (`app.business_timezone`) |
| 13 modules, «fiche réflexe» in module 13.2 | 12 phases, «ficha de actuación» in lesson 7.4 |
| `/formations`, `/courses/…`, `/ar` | `/cursos`, `/cursos/…`, no `/ar` |

File paths, endpoints and behaviours otherwise match. `specs/001-go-live/` is the Ebni go-live
spec, kept for the same reason.
