# Módulo 11 — Entrega de llaves

[← El plan](../aprendizaje-3d.md) · [Todos los guiones](README.md)

| | |
|---|---|
| Duración | 115 min |
| Pasos | 30, en 5 lecciones |
| Resultado (curso) | «escrituras firmadas, préstamo promotor amortizado, comunidad constituida y actas de entrega.» |
| Lugares | Ayuntamiento, Banco, Catastro, Colegio profesional, comunidad_propietarios, Hacienda, Notaría, Registro de la Propiedad, Tu solar, Compañías suministradoras |

## En la promoción

At the end of the promotion, after the legalisation (Módulo 9) and the pre-delivery (Módulo 10): the course frames the whole operation as «**Duración típica:** 3-4 años desde la compra del suelo hasta la última escritura.» (Módulo 2, Lección 2.4), and Module 11 is those escrituras. Inside it: possession is handed over «El mismo día de la firma o con cita»; the community «**Nace** con la primera venta»; the promoter loan is cancelled or subrogated «con cada escritura» and «Al vender la última, debe quedar a cero.»; after each signing the plusvalía is self-assessed «en 30 días hábiles por cada venta» and the buyer's AJD «en el plazo autonómico (habitualmente 30 días hábiles)».

## El solar

**Al empezar:** End of Módulo 10. The EDIFICIO CONSTRUYE is finished on its plot: planta baja + 5 with the upper floors stepping back towards the street, 18 homes; excavation, foundation, structure, façades, roof, installations, finishes and the street are done (Módulo 6) and the crane, huts and fence are gone. The CONSTRUYE sales office stands beside it with the homes marked sold (Módulo 8: place.oficina_ventas, site.sold). It is legalised and split into its 18 homes (primera ocupación, obra nueva and división horizontal inscribed, Libro del Edificio: Módulo 9), snags closed, a delivery folder per home and the signing calendar set (Módulo 10). Daylight; the homes are dark and empty; the promoter loan (Módulo 5) still hangs over the building; there is no community yet.

**Al terminar:** The same building at dusk (course_world.php: dusk 0.6), every home lit in its owner's colour. Escrituras signed and paid; the promoter-loan bar at zero, each slice cancelled or subrogated (its lien over the plot still faintly drawn until Lección 12.4). The community is constituted: plaque at the portal with NIF, cuenta, fondo de reserva, presidente and a colegiado administrador, maintenance contracts on the lift, PCI, portal, garden and an insurance shield. Light, water, gas if any, and telecom connected per home and in the buyers' names; actas de entrega signed, keys and remotes handed over; each home registered to its buyer, Catastro updated, one plusvalía slip filed per sale; the three guarantee rings (1, 3, 10 años) around the building. The sales office and the sold markers fold away (course_world.php's end state for Module 11 already drops them).

## El guion, en una página

Opening: late-afternoon light on the finished EDIFICIO CONSTRUYE (start state); the 18 homes carry their sold tags and the promoter-loan bar hangs over the roof in 18 slices; the bottom timeline shows the five lessons (30 steps). 11.1 La escritura de compraventa (7 steps): the camera glides to the Notaría; a deed unrolls and the papers of earlier modules land on it as manifestaciones; a «Quién paga qué» board fills row by row with tokens flying to the buyer's or the promoter's column — IVA 10 % repercutido, AJD of the student's community to the buyer, the buyer's mortgage costs paid by the bank (hop to the bank), plusvalía to the promoter «no repercutible» (hop to the Ayuntamiento), cancellation, obra nueva y división horizontal and acometidas to the promoter; a cheque crosses the table; then a fast montage of the 18 signings shrinks the loan bar slice by slice to 0. 11.2 La comunidad de propietarios (7): the first sale raises the community plaque at the portal; the junta meets in the lobby (orden del día, presidente, cuotas por coeficiente); the reserve chest fills to 10 %; flights to Hacienda (modelo 036 → NIF) and the bank (cuenta); the Registro stamps the libro de actas; maintenance contracts snap onto lift, PCI, portal, garden and an insurance shield; a colegiado administrador walks over from the Colegio de Administradores de Fincas (partner cards: administración de fincas). 11.3 Suministros (5): the suppliers' building sends power, water, gas if any, and fibre through the street into the building; each home's meter blinks on; an amber warning at the transformer room recalls Módulo 6. 11.4 Entrega de la posesión (5): the camera dives into one home — final walk-through with snags green, the acta filling in, keys and remote changing hands, X-ray of cuadro, aerotermia and llaves de paso, meter readings copied, and the three guarantee rings settling around the building anchored to the reception of the works. 11.5 Trámites después de la firma (6): papers fan out over the town — modelo 600 and 303 to Hacienda, the copia electrónica to the Registro, the automatic Catastro update (link card), plusvalía slips with 30-day timers and the IBI owner change at the Ayuntamiento, meters re-tagged, folders to each home and to the administrador. Close: the sun sets (dusk 0.6), windows light one by one in the owners' colours, the sales office folds away, the checklist counter reaches 8/8. Places with no building yet in places.ts: comunidad_propietarios (use the building's portal on the site) and the Colegio de Administradores de Fincas (reuse colegio_profesional with that label).

## Dónde ir (la tabla del curso)

| Institución | En el mundo | Documento | Plazo | Para qué |
|---|---|---|---|---|
| Notaría | Notaría (`notaria`) | escritura de compraventa |  | Firma de la compraventa y, en su caso, hipoteca del comprador o cancelación parcial |
| Registro de la Propiedad | Registro de la Propiedad (`registro_propiedad`) | Inscripción a nombre del comprador |  | Inscripción a nombre del comprador (la tramita la notaría o la gestoría) |
| AEAT | Hacienda (`hacienda`) | NIF de la comunidad de propietarios (modelo 036) |  | NIF de la comunidad de propietarios (modelo 036) |
| Banco | Banco (`banco`) | Cuenta de la comunidad |  | Cuenta de la comunidad |
| Distribuidora y comercializadoras | Compañías suministradoras (`suministros`) | Altas de luz por vivienda |  | Altas de luz por vivienda (con CIE y primera ocupación o cédula) |
| Empresa de aguas | Compañías suministradoras (`suministros`) | Altas de agua por vivienda |  | Altas de agua por vivienda |
| Ayuntamiento | Ayuntamiento (`ayuntamiento`) | Autoliquidación de la plusvalía | 30 días hábiles desde cada venta | Autoliquidación de la plusvalía (30 días hábiles desde cada venta) y cambio de titular del IBI |
| Colegio de Administradores de Fincas | Colegio profesional (`colegio_profesional`) | Administrador para la comunidad |  | Administrador para la comunidad (a professional college, not a visado: label the colegio_profesional building «Colegio de Administradores de Fincas», or use other:Colegio de Administradores de Fincas) |

## Impuestos, tasas y garantías

- **IVA (IGIC en Canarias, IPSI en Ceuta y Melilla)** — In each escritura de compraventa: paid by the buyer, charged by the promoter («lo repercutes»), then declared in the promoter's modelo 303 (Lección 11.5). Legal parameter iva_reducido.

  > | IVA 10 % (IGIC en Canarias, IPSI en Ceuta y Melilla) | ✔ (lo repercutes) | — |

- **AJD de la compraventa** — Paid by the buyer, self-assessed with the modelo 600 of the community «en el plazo autonómico (habitualmente 30 días hábiles)» (Lección 11.5). Legal region rate ajd.

  > | AJD de la compraventa (Anexo B) | ✔ | — |

- **AJD, notaría, registro y gestoría de la hipoteca del comprador (los paga el banco); tasación y copias (el comprador)** — At the signing of the buyer's mortgage, at the Notaría (Dónde ir). Paid by the bank; the lesson gives no rate.

  > | Hipoteca del comprador | Tasación y copias | El banco paga AJD, notaría, registro y gestoría |

- **Plusvalía municipal** — Paid by the promoter, «no repercutible» (Lección 11.1); one self-assessment per sale, 30 días hábiles desde cada venta (Dónde ir).

  > **Plusvalía municipal:** autoliquídala en 30 días hábiles por cada venta (Módulo 12).

- **IBI (cambio de titular)** — After each sale, at the Ayuntamiento.

  > | **Ayuntamiento** | Autoliquidación de la plusvalía (30 días hábiles desde cada venta) y cambio de titular del IBI |

- **Notaría de la compraventa** — At the escritura: the buyer pays his copy, the promoter the matriz.

  > | Notaría de la compraventa | Su copia | La matriz (art. 1455 CC, salvo pacto; con un consumidor no le impongas gastos que te corresponden) |

- **Registro de su compra** — Paid by the buyer, when the deed is registered (Lección 11.5).

  > | Registro de su compra | ✔ | — |

- **Cancelación de la hipoteca del promotor** — Paid by the promoter, with each escritura (Lección 11.1) and at the total registral cancellation (Lección 12.4).

  > | Cancelación de la hipoteca del promotor | — | ✔ |

- **Obra nueva y división horizontal** — Paid by the promoter (the deed itself is Módulo 9).

  > | Obra nueva y división horizontal | — | ✔ |

- **Acometidas generales de suministros** — Paid by the promoter.

  > | Acometidas generales de suministros | — | ✔ |

- **Cuotas de la comunidad de las viviendas sin vender** — From the first sale, for every home still unsold.

  > **Nace** con la primera venta: desde ese momento hay más de un propietario. Mientras tengas viviendas sin vender, pagas sus cuotas.

- **Fondo de reserva** — Approved at the junta constitutiva (Lección 11.2).

  > **Fondo de reserva** de al menos el 10 % del presupuesto ordinario.

- **Seguro del edificio** — Among the community's maintenance contracts (Lección 11.2).

  > Contratos de mantenimiento: ascensor, PCI, limpieza, jardinería, seguro del edificio.

## El checklist del módulo

| # | Ítem (curso) | Lección | En el mundo |
|---|---|---|---|
| 1 | Escrituras firmadas y cobro de cada vivienda | 11.1 | Notaría (`notaria`) |
| 2 | Parte del préstamo promotor cancelada o subrogada en cada firma | 11.1 | Banco (`banco`) |
| 3 | Plusvalía autoliquidada en plazo en cada venta | 11.5 | Ayuntamiento (`ayuntamiento`) |
| 4 | Junta constitutiva, NIF, cuenta y fondo de reserva | 11.2 | Comunidad de propietarios (`comunidad_propietarios`) — *falta en el mundo* |
| 5 | Contratos de mantenimiento de la comunidad | 11.2 | Comunidad de propietarios (`comunidad_propietarios`) — *falta en el mundo* |
| 6 | Altas de suministros | 11.3 | Compañías suministradoras (`suministros`) |
| 7 | Actas de entrega firmadas | 11.4 | Tu solar (`site`) |
| 8 | Escrituras presentadas en el Registro y suministros a nombre de cada comprador | 11.5 | Registro de la Propiedad (`registro_propiedad`) |

## Partners

`partner:` son las categorías de partners de la academia; `empresas:` los tipos del directorio de empresas; «Anexo A» los organismos de cada provincia.

- partner:notario (Lección 11.1, config/lesson_resources.php)
- partner:banco (Lección 11.1, config/lesson_resources.php)
- company:administracion_fincas (Lección 11.2, config/lesson_resources.php)
- anexo_a:distribuidora eléctrica (Partners en tu provincia, Fase 11)
- anexo_a:servicio de aguas (Partners en tu provincia, Fase 11)
- anexo_a:Colegio de Administradores de Fincas (Partners en tu provincia, Fase 11)
- anexo_a:ayuntamiento (Partners en tu provincia, Fase 11)

## Los pasos

### Lección 11.1 — La escritura de compraventa

#### 1. Lo que lleva la escritura

*En el mundo:* Notaría (`notaria`)

> **Contenido:** partes, finca registral y descripción, precio y forma de pago (con los pagos anteriores identificados), cargas (libre de cargas o con subrogación elegida por el comprador), manifestaciones (decenal, licencias, certificado energético, cédula), gastos.

Camera leaves the finished EDIFICIO CONSTRUYE and glides to the Notaría. A deed (the «escritura de compraventa») unrolls on the notary's table and its parts stamp in as labels: partes, finca registral y descripción, precio y forma de pago, cargas, manifestaciones, gastos. For «manifestaciones» the papers collected in earlier modules fly in from where they were obtained and land on the deed: decenal (aseguradora), licencias (Ayuntamiento), certificado energético and cédula (Módulo 9). One home of the building, in its división-horizontal colour (site.units), is outlined as the finca registral being sold. Partner cards: notario.

**Papeles:** decenal · licencias · certificado energético · cédula  
**Personas:** comprador  
**Checklist:** 1

#### 2. IVA 10 %: lo repercutes

*En el mundo:* Notaría (`notaria`)

> | IVA 10 % (IGIC en Canarias, IPSI en Ceuta y Melilla) | ✔ (lo repercutes) | — |

Beside the deed a «Quién paga qué» board opens with the table's two columns, Comprador and Promotor. First row: an IVA token at the home rate in force (legal parameter iva_reducido; it reads IGIC if the student's province is in Canarias, IPSI in Ceuta or Melilla) leaves the buyer's figure and passes through the promoter's hands with the words «lo repercutes»: the promoter collects it for Hacienda (modelo 303, Lección 11.5).

**Impuestos:** IVA · IGIC · IPSI  
**Personas:** comprador · promotor  
**Cifras:** 10 %  
**Checklist:** 1

#### 3. AJD de la compraventa: el comprador

*En el mundo:* Notaría (`notaria`)

> | AJD de la compraventa (Anexo B) | ✔ | — |

Second row of the board: an AJD token at the student's community rate (Anexo B; legal region rate ajd) drops into the buyer's column. The next rows tick in beside it without a camera move: «Notaría de la compraventa» (Su copia → buyer, La matriz → promoter) and «Registro de su compra» (buyer).

**Impuestos:** AJD  
**Personas:** comprador

#### 4. La hipoteca del comprador: paga el banco

*En el mundo:* Banco (`banco`)

> | Hipoteca del comprador | Tasación y copias | El banco paga AJD, notaría, registro y gestoría |

Camera hops to the buyer's bank. A mortgage deed card appears; four tokens — AJD, notaría, registro, gestoría (name only: the lesson gives no rate here) — are paid by coins from the bank's vault, while the buyer's figure pays only a small «Tasación y copias» paper.

**Papeles:** Hipoteca del comprador · Tasación  
**Impuestos:** AJD  
**Personas:** comprador · banco · gestoría

#### 5. La plusvalía municipal: la pagas tú

*En el mundo:* Ayuntamiento (`ayuntamiento`)

> | Plusvalía municipal | — | ✔ (no repercutible) |

Camera to the Ayuntamiento: a «Plusvalía municipal» token falls into the promoter's column, stamped «no repercutible». The remaining promoter rows of the board tick in as small cost tags: «Cancelación de la hipoteca del promotor», «Obra nueva y división horizontal», «Acometidas generales de suministros».

**Impuestos:** Plusvalía municipal  
**Personas:** promotor

#### 6. El cobro, en la notaría

*En el mundo:* Notaría (`notaria`)

> **Cobro:** cheque bancario nominativo o transferencia confirmada en la notaría. Si el comprador se subroga, su parte del préstamo pasa a ser su hipoteca y el banco descuenta ese importe.

Back at the notary's table: a «cheque bancario nominativo» (or a transfer confirmed on a screen) crosses to the promoter and the home's tag over the building turns paid. If the buyer subrogates, one slice of the promoter-loan bar above the roof (proposed piece, from Módulo 5) detaches, takes the buyer's colour and becomes that home's mortgage, and the bank's coin stack shrinks by that amount.

**Papeles:** cheque bancario nominativo  
**Personas:** comprador · banco  
**Checklist:** 1

#### 7. El préstamo promotor, a cero

*En el mundo:* Banco (`banco`)

> **Préstamo promotor:** con cada escritura se cancela o subroga la parte de esa finca. Al vender la última, debe quedar a cero.

Camera rises from the promoter's bank over the site: the loan bar hangs over the building in 18 slices, one per home. Fast montage of signings: with each escritura a slice is cancelled (fades) or subrogated (passes to that home's owner). After the last home the bar reads 0. The lien over the plot stays faintly drawn: its registral cancellation is Lección 12.4. Partner cards: banco.

**Papeles:** escritura  
**Cifras:** a cero  
**Checklist:** 2

### Lección 11.2 — La comunidad de propietarios

#### 8. Nace con la primera venta

*En el mundo:* Comunidad de propietarios (`comunidad_propietarios`) — *falta en el mundo*

> **Nace** con la primera venta: desde ese momento hay más de un propietario. Mientras tengas viviendas sin vender, pagas sus cuotas.

Camera to the building's portal (the EDIFICIO CONSTRUYE door of the 3D world). The first sold home lights in its buyer's colour and a «Comunidad de propietarios» plaque rises beside the door. Every home still unsold keeps the CONSTRUYE colour and carries a small «cuota» coin charged to the promoter.

**Impuestos:** cuotas  
**Personas:** propietario

#### 9. La junta constitutiva

*En el mundo:* Comunidad de propietarios (`comunidad_propietarios`) — *falta en el mundo*

> Convoca la junta constitutiva (con orden del día).

Inside the lobby a meeting table appears with the owners' figures; the «orden del día» is pinned. Following the lesson's steps 2 and 3, a «presidente» badge lands on one figure (secretario and administrador if they decide), and the «presupuesto ordinario» sheet splits into «cuotas por coeficiente», one strip per home.

**Papeles:** orden del día · presupuesto ordinario  
**Personas:** presidente · secretario · administrador  
**Checklist:** 4

#### 10. Fondo de reserva: al menos el 10 %

*En el mundo:* Comunidad de propietarios (`comunidad_propietarios`) — *falta en el mundo*

> **Fondo de reserva** de al menos el 10 % del presupuesto ordinario.

A chest labelled «Fondo de reserva» fills with coins up to a line marked 10 % of the presupuesto ordinario.

**Papeles:** presupuesto ordinario  
**Impuestos:** Fondo de reserva  
**Cifras:** 10 %  
**Checklist:** 4

#### 11. NIF de la comunidad y cuenta bancaria

*En el mundo:* Hacienda (`hacienda`)

> NIF de la comunidad en la AEAT y cuenta bancaria.

Camera to Hacienda (AEAT): a «modelo 036» form goes in and a «NIF de la comunidad» card comes out (link card under the step: Agencia Tributaria · sede electrónica (NIF de la comunidad)). Quick hop to the Banco, which hands back a «Cuenta de la comunidad» card (Dónde ir). Both cards fly to the portal plaque.

**Papeles:** NIF de la comunidad · modelo 036 · cuenta bancaria  
**Checklist:** 4

#### 12. Libro de actas diligenciado

*En el mundo:* Registro de la Propiedad (`registro_propiedad`)

> Libro de actas diligenciado en el Registro de la Propiedad.

Camera to the Registro de la Propiedad: a blank «Libro de actas» receives the registry's stamp and flies back to the portal.

**Papeles:** Libro de actas

#### 13. Contratos de mantenimiento

*En el mundo:* Comunidad de propietarios (`comunidad_propietarios`) — *falta en el mundo*

> Contratos de mantenimiento: ascensor, PCI, limpieza, jardinería, seguro del edificio.

Back at the building, in cutaway: five contract cards snap onto their parts — ascensor (lift shaft), PCI (fire equipment on each landing), limpieza (portal and stairs), jardinería (the green strip of the street, site.street) and seguro del edificio (a shield over the whole building).

**Papeles:** Contratos de mantenimiento  
**Impuestos:** seguro del edificio  
**Checklist:** 5

#### 14. Un administrador de fincas colegiado

*En el mundo:* Colegio profesional (`colegio_profesional`)

> **Administrador de fincas:** recomendable desde el primer día. Pide un colegiado.

Camera to the professional-college building, labelled «Colegio de Administradores de Fincas» (Dónde ir: «Administrador para la comunidad»). A figure with a «colegiado» badge walks to the portal and takes the community's folder. Partner cards pop up: directory companies of kind administracion_fincas in the student's province.

**Personas:** Administrador de fincas · colegiado

### Lección 11.3 — Suministros y cobro final

#### 15. Electricidad: CIE, CUPS y primera ocupación

*En el mundo:* Compañías suministradoras (`suministros`)

> | Electricidad | CIE registrado, CUPS de cada vivienda, primera ocupación o cédula | Cada comprador con su comercializadora (el promotor puede dejarlo preparado) |

Camera to the suppliers' building (distribuidora y comercializadoras). The promoter's papers go in: CIE registrado, a CUPS for each home, primera ocupación or cédula. A power line lights from the street connection into the building and each home's meter blinks on as its buyer signs with a comercializadora.

**Papeles:** CIE registrado · CUPS de cada vivienda · primera ocupación · cédula  
**Personas:** comprador · comercializadora · promotor  
**Checklist:** 6

#### 16. Agua: contador individual

*En el mundo:* Compañías suministradoras (`suministros`)

> | Agua | Primera ocupación o cédula, contador individual | Comprador con la empresa municipal |

The water company (empresa municipal): blue pipes fill from the street main into the building; each home gets its «contador individual» icon.

**Papeles:** Primera ocupación · cédula  
**Personas:** Comprador · empresa municipal  
**Checklist:** 6

#### 17. Gas, si hay

*En el mundo:* Compañías suministradoras (`suministros`)

> | Gas (si hay) | Certificado de la instalación | Comprador |

An optional row, shown greyed out unless the building has gas: a «Certificado de la instalación» paper and a yellow line to the homes.

**Papeles:** Certificado de la instalación  
**Personas:** Comprador  
**Checklist:** 6

#### 18. Telecomunicaciones: ICT certificada

*En el mundo:* Compañías suministradoras (`suministros`)

> | Telecomunicaciones | ICT certificada | Comprador con su operador |

A fibre line lights from the street to the building's telecom room (ICT certificada) and on to each home.

**Papeles:** ICT certificada  
**Personas:** Comprador · operador  
**Checklist:** 6

#### 19. Ojo: las altas de luz pueden retrasarse

*En el mundo:* Tu solar (`site`)

> **Ojo:** las altas de luz pueden retrasarse semanas si la acometida definitiva o el centro de transformación no están legalizados. Empieza con la distribuidora en el Módulo 6.

Camera down to street level at the plot: the acometida definitiva and the centro de transformación (built in Módulo 6) pulse amber, a clock reads «semanas», and a dotted line runs back to the distribuidora with a «Módulo 6» flag on the course timeline.

**Personas:** distribuidora  
**Cifras:** semanas

### Lección 11.4 — Entrega de la posesión

#### 20. Visita final con el comprador

*En el mundo:* Tu solar (`site`)

> Visita final con el comprador y comprobación de repasos.

Overlay: «El mismo día de la firma o con cita». The camera dives into one home (cutaway of its floor); the promoter's and the buyer's figures walk through and the snag marks left from Módulo 10 (site.snags) show closed, one by one.

**Personas:** comprador

#### 21. Acta de entrega de posesión

*En el mundo:* Tu solar (`site`)

> **Acta de entrega de posesión** firmada (fecha, estado, lecturas de contadores, llaves y mandos entregados, documentación).

The acta appears and its fields fill in order — fecha, estado, lecturas de contadores, llaves y mandos entregados, documentación. A key ring and a garage remote pass from the promoter to the buyer, the buyer signs, and that home's windows light up (dusk begins).

**Papeles:** Acta de entrega de posesión  
**Checklist:** 7

#### 22. Explica la vivienda

*En el mundo:* Tu solar (`site`)

> Explicación de la vivienda: cuadro eléctrico, aerotermia, llaves de paso, mantenimiento.

X-ray view of the home (site.mep cutaway): the cuadro eléctrico, the aerotermia unit and the llaves de paso glow with labels as the promoter points at each; a «mantenimiento» leaflet slides to the buyer.

#### 23. Contadores e incidencias pendientes

*En el mundo:* Tu solar (`site`)

> **Lectura de contadores** en el acta, y registro de incidencias pendientes con su compromiso de resolución.

Meter dials zoom in and their readings copy onto the acta; any snag still open becomes a numbered card in the registro de incidencias pendientes, with its compromiso de resolución — it carries over to the postventa of Módulo 12.

**Papeles:** acta  
**Checklist:** 7

#### 24. Las garantías corren desde la recepción

*En el mundo:* Tu solar (`site`)

> **Garantías:** los plazos de 1, 3 y 10 años de la LOE corren **desde la recepción de la obra sin reservas, o desde la subsanación de las reservas** (art. 17.1 LOE), no desde la entrega a cada comprador. Explícaselo por escrito.

Three translucent rings settle around the building, labelled 1, 3 y 10 años. A timeline under the plot anchors them at the reception of the works (the acta de recepción of Módulo 6), not at today's delivery; a written note goes to the buyer («Explícaselo por escrito»).

**Personas:** comprador  
**Cifras:** 1, 3 y 10 años

### Lección 11.5 — Trámites después de la firma

#### 25. Impuestos de la compraventa

*En el mundo:* Hacienda (`hacienda`)

> **Impuestos de la compraventa:** el comprador liquida el AJD con el modelo 600 de su comunidad en el plazo autonómico (habitualmente 30 días hábiles); muchas notarías lo gestionan. Tú declaras el IVA repercutido en tu modelo 303.

Camera to Hacienda (the regional tax office for the AJD, the AEAT for the IVA): two lanes. The buyer's «modelo 600» with the AJD token of the student's community and a 30-día-hábil countdown (a notary figure often carries it); the promoter's «modelo 303» with the IVA repercutido.

**Papeles:** modelo 600 · modelo 303  
**Impuestos:** AJD · IVA  
**Personas:** comprador · notarías  
**Cifras:** 30 días hábiles

#### 26. Inscripción a nombre del comprador

*En el mundo:* Registro de la Propiedad (`registro_propiedad`)

> **Registro:** la notaría envía copia electrónica al Registro de la Propiedad el mismo día; después se presenta la copia con el impuesto liquidado y se inscribe a nombre del comprador.

A light packet «copia electrónica» shoots from the Notaría to the Registro de la Propiedad the same day; later the paper copy arrives with the tax receipt; the registry book flips a page and the home's owner name changes to the buyer's.

**Papeles:** copia electrónica  
**Personas:** comprador  
**Cifras:** el mismo día  
**Checklist:** 8

#### 27. Catastro: sin declaración

*En el mundo:* Catastro (`catastro`)

> **Catastro:** notaría y registro comunican el cambio de titular; no hace falta presentar declaración.

Dotted lines run from the Notaría and the Registro to the Catastro; its map tile of the building updates the owner by itself; a crossed-out declaration form shows there is nothing to file (link card: Sede Electrónica del Catastro).

#### 28. Plusvalía: 30 días hábiles por venta

*En el mundo:* Ayuntamiento (`ayuntamiento`)

> **Plusvalía municipal:** autoliquídala en 30 días hábiles por cada venta (Módulo 12).

Camera to the Ayuntamiento: one autoliquidación slip pops per sale, each with its own 30-day countdown, stamped as it is filed. From the Dónde ir row, the «cambio de titular del IBI» flips each sold home's IBI tag to its buyer.

**Impuestos:** Plusvalía municipal · IBI  
**Cifras:** 30 días hábiles  
**Checklist:** 3

#### 29. Suministros a nombre del comprador

*En el mundo:* Compañías suministradoras (`suministros`)

> **Suministros:** altas o cambios de titularidad a nombre del comprador.

The supply meters of each home change their tag from the promoter to the buyer.

**Personas:** comprador  
**Checklist:** 6 · 8

#### 30. La documentación, a su sitio

*En el mundo:* Comunidad de propietarios (`comunidad_propietarios`) — *falta en el mundo*

> **Documentación:** entrega lo que corresponda al comprador y al administrador de la comunidad.

Folders fly from the promoter's office: one into each home, one to the administrador at the portal.

**Personas:** comprador · administrador de la comunidad
