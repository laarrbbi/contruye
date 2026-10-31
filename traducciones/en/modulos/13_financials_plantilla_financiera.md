# Financials — the development's financial model

> **Objective:** be able to fill in, read and defend the course's professional financial model: cost by chapter, loan, management, payment schedule, cash flow and summary.
> **Duration:** 120 min · **Tool:** `PLANTILLA_LIBRE.xls` and its simplified version, the same ones the platform calculates · **Used in:** Modules 2, 5, 7 and 12.

## Where the model comes from

- It is a **model built by a professional in the sector**, and it is used in the course and on the platform **as it is**, with its formulas.
- It is designed for an **open-market housing development run as a cooperative** (*vivienda libre en régimen de cooperativa*): the members buy at cost, the management company (*gestora*) charges a management percentage, and the financing is a mortgage loan that the members take over (*subrogación*).
- **Two files:**
  - **PLANTILLA_LIBRE.xls**: the full model. 17 sheets and some 31.000 linked formulas.
  - **Simplified feasibility model** (*Plantilla simplificada de Análisis de Viabilidad*): the full model's summary sheet ("3ª Coste Total Promoción") with a real example: 1 home of 200 m².
- **Before opening it in Excel:** turn on iterative calculation (*File → Options → Formulas → Enable iterative calculation*, 100 iterations, maximum change 0,001). The model has deliberate circular references (Lesson F.7), and without iteration Excel does not calculate. On the platform this is already taken care of.

## Map of the model

| Order | Sheet | What it does |
|---|---|---|
| 1 | **Datos de Partida** (input data) | Everything you enter: areas, unit types, land, construction, external works, licences, loan, management, members |
| 2 | 2º Auxiliar | Intermediate calculations: areas and costs by element and by unit type |
| 3 | **3ª Terrenos** (land) | Cost of the land: price, taxes, notary, registry, guarantees, stamp duties, API (estate agent) |
| 3 | **3ª Construcción** (construction) | Monthly valuations (*certificaciones*) following the construction curve, retention, OCT and ten-year insurance |
| 3 | **3ª Urbanización** (external works) | The same as construction, for the external works |
| 3 | **3ª Proyectos** (design) | Fees: studies, basic and construction designs, building services, safety, site supervision, quality control |
| 3 | Direcciones | Spread over time of the site supervision (*direcciones facultativas*) and of safety |
| 3 | **3ª Licencias y Varios** (licences and sundries) | Fees, ICIO, first occupation (*primera ocupación*), legal costs, connections, insurance, audits, guarantees of advance payments (*cantidades a cuenta*) |
| 3 | **3ª Préstamo hipotecario** (mortgage loan) | Drawdowns, interest, retention and arrangement costs |
| 3 | **3ª Gestión** (management) | Management fees and recruitment of members |
| 3 | 3ª Pólizas de Crédito | Up to three external credit lines |
| 4 | **3ª Coste Total Promoción** (total development cost) | Cost summary: net, with IVA, IVA, %, unit costs, pass-through coefficient. **It is the simplified model** |
| 5 | Estudio Económico | The full economic study |
| 5 | 4º Auxiliar1 · **5º Forma de Pago** (payment schedule) | Each member's down payments, monthly instalments and loan |
| 6 | **6ª Cash-flow** (cash flow) | Receipts and payments over 48 months, by item and by year |
| 7 | **8º Resumen** (summary) | Cost, areas, down payment, monthly instalment and mortgage for each unit type |

- **Rule of use:** you fill in only the input data (and, if needed, the manual design amounts and the credit lines). Everything else is calculated.

## Lesson F.1 — Filling in the input data

| Block | Cells | What you enter | Watch out for |
|---|---|---|---|
| Areas by element | C9:E16 | Units, average built m² and **construction cost per m²** for homes, garages, storerooms, terraces, commercial units, other | The column is called "PVP CONSTRUCCIÓN" but it is a **cost**, not a sale price |
| Unit types | B22:C45 | Up to 24 types: number of homes and usable m² | The number of homes must add up to C9. The model calculates the built m² itself |
| Linked elements | C51:E74 | m² of garage, storeroom and terrace for each type | They count towards the **fee band** of the design |
| Land | D79:D83 | Month zero, subject to IVA? (SI/NO), interest on deferred payment, price, API and sundries | **API and sundries is a % of the price**, not an amount |
| Land payment schedule | C86:E92 | Dates, amounts and whether they are guaranteed | Type SI / NO without an accent |
| Construction | D96:D101 | Start month, payment days, interest on deferral, retention, guarantee, construction months (1-24) | The construction months pick the valuation curve |
| External works | D108:D116 | Amount, start, payment, retention, guarantee, months, quality control and safety | |
| Building type | D121 | `bloque` or `chalets` (apartment block or houses) | It changes the OCT and ten-year insurance rates |
| Licences | D126:D131 | *Cédula* fee, trial pits/hoardings, licence fee, ICIO, first occupation, IAE | All are a **% of the construction amount** |
| Legal costs | D133 | AJD on the deed of new building and horizontal division | |
| Connections and sundries | D135:D142 | Water, electricity, gas, telephone, refuse, the cooperative's governing board (*consejo rector*) | They come with a default formula; you can type over it |
| Mortgage loan | D146:D154 | Loan %, rate, retention, first drawdown, dates, fee, AJD | The loan is a **% of the total cost** |
| Management | D157:D159 | Management %, months, start | |
| Members | C163:F180 | Members recruited each month (36 months) | |
| Down payments and instalments | C184:D186 | Down payment as a % **or** as a fixed amount, number of monthly instalments | Fill in one of the two down payments, not both |
| Cost allocation | C207:C214 | % of the cost going to the home, garage, storeroom… | Default in the model: home 90 %, garage 7 %, storeroom 3 % |

- **Percentages as decimals:** 0,05 = 5 %.

## Lesson F.2 — The cost chapters

- **3ª Terrenos:** price + notary and registry (scale fees) + tax on the purchase (AJD if IVA applies, ITP if not) + cost of guarantees and stamp duties on deferred payments + API and sundries.
- **3ª Construcción:** construction amount (areas × cost per m²) valued month by month following the **construction curve**.
  - The curve is a table of % per month for works lasting 1 to 24 months; each column adds up to 100. For example, over 18 months, 0,5 % is valued in month 1 and 9,5 % in each of months 10 to 13.
  - It adds the retention, the **OCT** (rate by scope and budget band) and the **ten-year insurance** (premium by band, surcharges for roofs and façades, CLEA and IPS).
- **3ª Urbanización:** the same as construction, with its own curve and its own quality and safety valuations.
- **3ª Proyectos:** fees by **floor-area band**.
  - The percentage (11,5 % up to 200 m², 9,5 % up to 800 m²…) is applied to the construction amount.
  - It is split: basic design 40 %, construction design 30 %, architect's site supervision 30 % and technical architect's (*aparejador*) site supervision 30 %.
  - It adds the studies (topographic, geotechnical, setting-out on the ground — *tira de cuerdas*), building services designs, safety, maintenance manual, technical reports and quality control (1,5 % of construction).
  - Subdivision (*parcelación*), compensation (*compensación*) and urbanisation projects are typed in by hand if there are any.
- **3ª Licencias y Varios:**
  - Fees, ICIO and first occupation (% of construction).
  - Legal costs (notary, registry and AJD on the deed of new building and horizontal division).
  - Connections, utilities, rentals, audits and insurance.
  - The **insurance of the members' advance payments** (*cantidades a cuenta*).
- **3ª Gestión:** management % on the cost, plus the recruitment of members.

## Lesson F.3 — The summary: the simplified model

The "3ª Coste Total Promoción" sheet (the simplified model) summarises everything. This is the **professional's real example**: 1 home of 200 m², land at 50.000 €.

| Chapter | Net | With IVA | % of total |
|---|---|---|---|
| Land (*Terreno*) | 53.100,00 € | 61.116,00 € | 16,5 % |
| Construction (*Construcción*) | 180.935,33 € | 193.552,31 € | 56,1 % |
| External works (*Urbanización*) | 0,00 € | 0,00 € | 0,0 % |
| Design (*Proyectos*) | 38.754,09 € | 44.954,75 € | 12,0 % |
| Licences (*Licencias*) | 7.740,00 € | 7.740,00 € | 2,4 % |
| Sundries (*Varios*) | 18.484,05 € | 20.250,24 € | 5,7 % |
| Management (*Gestión*) | 15.358,89 € | 17.816,31 € | 4,8 % |
| Mortgage loan costs | 8.164,29 € | 9.073,02 € | 2,5 % |
| **Total** | **322.536,66 €** | **354.502,63 €** | **100 %** |

- **How to read each chapter** (the sheet's detail columns):
  - **Land 53.100 €** = 50.000 € of land + 50 € of notary + 50 € of registry + 500 € of AJD (1 %) + 2.500 € of API and sundries (5 %).
  - **Construction 180.935,33 €** = 163.400 € of construction amount + 8.600 € of retention + 3.606,07 € of OCT + 5.329,26 € of ten-year insurance.
  - **Design 38.754,09 €** = 9.015,18 € of topographic and geotechnical studies + 6.536 € of basic design + 4.902 € of construction design + 4.902 € + 4.902 € of site supervision + 4.597,74 € of technical reports + 2.580 € of quality control + 193,60 € of building services designs + 193,60 € of the services engineer's supervision + 865,85 € of health and safety + 66,11 € of maintenance manual.
  - **Sundries 18.484,05 €** = 6.696,75 € of legal costs + 240,40 € of connections + 48,08 € of utilities + 2.524,25 € of meeting room + 7.212,15 € of audits + 1.762,42 € of insurance.
  - **Management 15.358,89 €** = 13.853,89 € of development management + 1.505 € of member recruitment.
  - **Loan costs 8.164,29 €** = 6.969,71 € of arrangement + 1.194,58 € of interest during the grace period.
- **Indicators:**
  - **Unit cost** per home: 322.536,66 €. Per built m²: 1.612,68 €/m².
  - **Pass-through coefficient** = total cost ÷ (land + construction + external works, net of costs) = 322.536,66 ÷ 222.000 = **1,4529**. For every euro of land and construction there is 0,45 € of indirect, management and finance costs.
  - **Mortgage loan** = 80 % of the total cost = **258.029,33 €**.
- **To check your figures:** net + IVA must equal the amount with IVA in each chapter. In the original version of this sheet, "Sundries" with IVA leaves out the connections (see Lesson F.8).

## Lesson F.4 — Mortgage loan and credit lines

- **Amount:** % of the total cost (D146). In the example, 80 %.
- **First drawdown:** by default, 80 % of the land price (D149). The rest is drawn against the construction valuations.
- **Interest:** calculated daily on the amount drawn and paid every quarter. The **interest during the grace period** (*intereses de carencia*) is a cost of the development.
- **Retention (D148):** the part of the loan the bank holds back until the works are finished.
- **Arrangement:** notary and registry (per scale), arrangement fee, AJD on the security and valuations.
- **Credit lines** (*pólizas de crédito*): up to three, each with its limit, rate, dates and costs. They cover cash gaps.

## Lesson F.5 — Management, members and payment schedule

- **Management:** the management % (D157) is applied to the cost and spread over the management months. Member recruitment is added separately.
- **Recruitment pace:** how many members join each month (C163:F180). It decides when the money comes in.
- **The member's payment schedule** (sheet 5º):
  - **down payment** (as a % or a fixed amount);
  - **monthly instalments** until handover;
  - **mortgage** taken over in the deed.
  - Advance payments are insured: the cost of the insurance goes to "Sundries" (*Varios*).
- **Summary by unit type** (sheet 8º):
  - cost of each unit type with its linked elements, according to the allocation in C207:C214;
  - with each member's down payment, monthly instalment and mortgage.

## Lesson F.6 — Cash flow and economic study

- **6ª Cash-flow:** 48 months of receipts (down payments, instalments, loan, IVA) and payments (land, external works, connections, design, construction, licences, sundries, management, finance costs).
  - Totals by year and running total. Each row carries its account from the chart of accounts (*plan contable*).
- **How to read it:**
  - The **running total** must not go negative. If it does, you need a credit line or larger down payments from the members.
  - Look at the month of the land purchase and the peak valuation months.
  - Check that the loan is drawn when there is construction going on.
- **Estudio Económico:** brings all the chapters together, with their amounts, finance costs and IVA, in a single view.

## Lesson F.7 — Why the model needs iterative calculation

- **In a development, some costs depend on the total cost:**
  - the **loan** is a % of the total cost, and the cost includes the loan costs;
  - **management** is a % of the cost, and is part of the cost;
  - the **cost of each home** sets the members' payments, which set the cost of their insurance, which is part of the cost.
- **Those are circular references.** Excel solves them by iterating: it calculates, recalculates with the result and repeats until the change is less than 0,001 €.
- In this model, the result settles in about 20 iterations.
- **If Excel warns of a "circular reference", nothing is wrong: turn on iterative calculation.**

## Lesson F.8 — What to check before using it in 2026

The model calculates with the parameters it was built with, around 2002. To study a current development, check with your adviser:

| Model parameter | Value in the model | Current reference (Modules 2 and 11, Annex B) |
|---|---|---|
| IVA on residential construction | 7 % | 10 % |
| General IVA (fees, notary, OCT…) | 16 % | 21 % |
| Tax on the land purchase | AJD 1 % or ITP 8 % | Depends on your autonomous community (Annex B) |
| Notary and registry scales, stamp duties, OCT, ten-year insurance, fees | Tables of the time, in pesetas | Ask for a current quote |
| Cooperative items | Member recruitment, governing board, meeting room | They do not apply to a developer |

**Two details of the original worth knowing:**
- **"Sundries" with IVA leaves out the connections** (278,87 € in the example). The correct total with IVA for the example would be 354.781,50 €. The net figure is correct.
- **The fifth land payment** does not calculate the cost of its guarantee if it is guaranteed. Payments 1 to 4 do.

## Lesson F.9 — From the model to a developer's decision

- The model gives you the **total cost** and the **cash calendar**. In a cooperative, that is the member's price.
- **If you are a developer**, take the total cost into the **developer's profit and loss account** (CRP) of Module 2:
  - sales without IVA − total cost − sales and marketing − corporate income tax (*Impuesto sobre Sociedades*) = profit;
  - minimum margin of 15 % on sales;
  - residual method for the land.
- **Use the model at every phase:**
  - feasibility before buying (Module 2);
  - presentation to the bank (Module 5);
  - monthly monitoring with actual figures (Module 7);
  - close and actual return (Module 12).

## Financials checklist

- [ ] Iterative calculation turned on in Excel (already on in the platform)
- [ ] Areas, unit types and linked elements filled in and reconciled with the product sheet
- [ ] Construction cost per m² from real quotes
- [ ] Land, licences and loan in the right units (amounts and percentages)
- [ ] Calendar: dates of purchase, works, external works, drawdowns and management
- [ ] Summary checked: chapters, unit costs, pass-through coefficient and loan
- [ ] Cash flow with no negative running total (or a credit line planned)
- [ ] Model parameters reviewed for current taxation (Lesson F.8)
