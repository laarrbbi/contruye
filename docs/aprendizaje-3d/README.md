# Aprendizaje 3D — los guiones, módulo a módulo

[← El plan](../aprendizaje-3d.md)

Los borradores del guion 3D de todo el curso: cada lección partida en pasos, y cada paso con su cita del curso, el lugar del mundo al que va la cámara, lo que se ve (la escena), los papeles, impuestos, personas y cifras que aparecen, y los ítems del checklist que marca. De ellos sale el guion de cada módulo en `api/config/course_world.php`, como el del Módulo 1.

## Cómo se leen

- Las **citas** (los bloques `>`) y el texto entre comillas españolas («…») son palabras del curso: el texto de los módulos, las transcripciones de los vídeos o las plantillas, sin cambiar nada. Los rótulos de la propia interfaz (p. ej. «Ver el vídeo», «Módulo 2 · Viabilidad») también van entre «».
- La **escena** es nuestro borrador de lo que se ve, en inglés; la escribirá en firme quien guionice el módulo.
- *En el mundo* dice qué edificio del mundo 3D sale; *falta en el mundo* marca los que aún no tiene (ver «Los lugares» más abajo).
- Los textos en MAYÚSCULAS de «Aspecto» son rótulos que se pintan en el edificio, no citas.

## Comprobado contra el curso

Con un script, sobre `curso/` (paquete v5), antes de escribir estos archivos:

- Las **372 citas** de los pasos están, palabra por palabra, en el archivo de su módulo.
- Los papeles, personas, cifras e impuestos de cada paso aparecen en el texto de su módulo (dos salen del vídeo y de una tabla del Módulo 2: «Poderes notariales», «Beneficio antes de impuestos | 15–25 %»).
- De las 785 expresiones entre «» de las escenas, todas son del curso o rótulos de la interfaz; seis que no lo eran (paráfrasis y el nombre del edificio) se han corregido o marcado.
- Los 77 importes, plazos y superficies de las escenas, y los 134 porcentajes, están en el paquete del curso.
- Los números de checklist existen en su módulo, y cada lección citada existe.

Cuando un módulo pase a `course_world.php`, `CourseWorldTest` vuelve a comprobar cada cita y cada nombre.

**Paquete 5.1 (20/11/2026).** Las correcciones del profesor cambiaron texto que citan 11 pasos de estos
borradores: en el Módulo 2, el encaje («mono»), los puntos 1 y 2 del precio de venta, «varía mucho por
provincia» y la exclusividad; en el 5, las preventas (ahora 70–90 %); en el 9, el párrafo quitado de la
primera ocupación y la cédula; en el 10, los dos pasos quitados de la notaría; en el 11, la plusvalía. Al
guionizar esos módulos, esas citas se cambian por el texto corregido (`curso/1_contenido/CAMBIOS.md`). Los
títulos de las lecciones 4.3, 9.2 y 9.3 también han cambiado.

**23/11/2026.** También han cambiado la tabla de costes de la 2.3 (estándar 1.050–1.250 €/m²,
media-alta 1.250–1.600, lujo desde 1.600, sótano 550–900), el precio de venta de la 2.2, la forma de pago
de la 2.5 y la plusvalía (11.5 y 12), y las cifras del ejemplo de Nicolás, que construye a 1.400 €/m².

## Los módulos

| Módulo | Lecciones | Pasos | Guion |
|---|---|---|---|
| Módulo 1 — Solar: búsqueda, análisis urbanístico, due diligence y adquisición | 4 | 22 | [modulo-01.md](modulo-01.md) |
| Módulo 2 — Análisis de viabilidad | 5 | 29 | [modulo-02.md](modulo-02.md) |
| Módulo 3 — Proyectos técnicos | 4 | 23 | [modulo-03.md](modulo-03.md) |
| Módulo 4 — Licencias y autorizaciones administrativas | 4 | 26 | [modulo-04.md](modulo-04.md) |
| Módulo 5 — Financiación y contratación | 4 | 28 | [modulo-05.md](modulo-05.md) |
| Módulo 6 — Construcción | 5 | 29 | [modulo-06.md](modulo-06.md) |
| Módulo 7 — Control de obra | 4 | 26 | [modulo-07.md](modulo-07.md) |
| Módulo 8 — Comercialización | 4 | 26 | [modulo-08.md](modulo-08.md) |
| Módulo 9 — Legalización | 5 | 28 | [modulo-09.md](modulo-09.md) |
| Módulo 10 — Preentrega | 3 | 18 | [modulo-10.md](modulo-10.md) |
| Módulo 11 — Entrega de llaves | 5 | 30 | [modulo-11.md](modulo-11.md) |
| Módulo 12 — Postventa y cierre de la promoción | 4 | 28 | [modulo-12.md](modulo-12.md) |
| Financials — Extra: la plantilla financiera profesional | 9 | 59 | [financials.md](financials.md) |
| **Total** | | **372** | |

Financials es el extra (de pago aparte): su mundo solo lo vería quien lo ha comprado.

## Los lugares

Cuántos pasos van a cada lugar, en todos los guiones:

| Lugar | Pasos | Módulos | En el motor |
|---|---|---|---|
| Oficina del promotor | 87 | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, F | sí |
| Tu solar | 77 | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, F | sí |
| Ayuntamiento | 27 | 1, 2, 4, 6, 9, 11, 12, F | sí |
| Banco | 26 | 4, 5, 7, 8, 10, 11, F | sí |
| Estudio de arquitectura | 21 | 1, 2, 3, 6, 7, 8, F | sí |
| Notaría | 21 | 1, 2, 8, 9, 10, 11, 12, F | sí |
| Oficina de ventas | 18 | 2, 6, 7, 8, 9, F | sí |
| Aseguradora | 13 | 2, 5, 7, 9, 12, F | sí |
| comunidad_propietarios | 13 | 9, 11, 12 | **no** |
| Hacienda | 11 | 1, 2, 5, 11, 12, F | sí |
| Compañías suministradoras | 9 | 1, 3, 4, 9, 11 | sí |
| Constructora | 9 | 2, 3, 5, 10, 12, F | sí |
| Colegio profesional | 7 | 3, 4, 6, 8, 9, 11 | sí |
| Laboratorio y OCT | 7 | 3, 5, 6, 7, 9 | sí |
| Registro de la Propiedad | 6 | 1, 2, 9, 11, 12 | sí |
| Organismos sectoriales | 4 | 1, 4, 6 | sí |
| Registro Mercantil | 3 | 1, 5, 12 | sí |
| Seguridad Social | 3 | 4, 5 | sí |
| Catastro | 2 | 1, 11 | sí |
| Estadística Registral (registradores.org) y Portal Estadístico del Notariado | 1 | 2 | **no** |
| Servicio de Industria de la comunidad | 1 | 4 | **no** |
| Gestor autorizado de residuos | 1 | 6 | **no** |
| Agencias colaboradoras | 1 | 8 | **no** |
| Registro autonómico de certificados energéticos | 1 | 9 | **no** |
| Servicio de Industria de la CCAA | 1 | 9 | **no** |
| Organismo autonómico o insular | 1 | 9 | **no** |
| Junta Arbitral de Consumo de la CCAA | 1 | 12 | **no** |

Lo que falta, y cómo meterlo sin llenar el tablero:

- **Comunidad de propietarios** (Módulos 9, 11, 12): el portal del edificio terminado, en el propio solar.
- **Sociedad de tasación** (Módulos 2, 5, 7): un despacho junto al banco.
- **Una «Delegación de la comunidad autónoma»** con una puerta por organismo: Servicio de Industria, registro de certificados energéticos, oficina de la cédula de habitabilidad, Consejería de Cultura, órgano ambiental, oficina de consumo, Junta Arbitral de Consumo, autoridad laboral y la hacienda autonómica (modelo 600).
- **Gestor de residuos** (Módulos 6 y 9), **Fundación Laboral de la Construcción** (Módulo 6), **COAPI** (Módulos 1 y 8), **asociación de promotores** (Módulo 8), **SEPBLAC** (Módulos 8 y 10) y los **colegios** de abogados (1, 2 y 8), economistas (2) y administradores de fincas (11): pequeños edificios o puertas en la plaza.
- **Lo que es online** (Portal de Subastas del BOE, Estadística Registral y Portal Estadístico del Notariado): una pantalla en la oficina del promotor, no un edificio.
- **Lo que cambia con la provincia**, con `curso/1_contenido/datos/partners_por_provincia.json`: Costas solo donde hay costa, la oficina de la cédula donde existe, Hacienda «foral» donde lo es, y el nombre real de cada organismo en su ficha (p. ej. «Gerencia del Catastro de Almería»).
- **Marcas**: las que nombra el curso (portales, tasadoras, OCT, servicers…) van en el texto del paso, no como logotipos en los edificios. La marca CONSTRUYE va en la oficina del promotor, el vallado y la oficina de ventas.

## Las instituciones del mundo

Las 45 instituciones a las que el curso manda al promotor, con lo que se lleva de cada una y cómo se ven. «Papeles» cita el curso antes de « — »; después van el módulo y el plazo.

### Solar · obra

*En el mundo:* Tu solar (`site`) · *Módulos:* 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12

*Aspecto:* The plot in the centre of the board, between the plaza (Ayuntamiento, Notaría, Registro) and the streets of the other offices. Modules 1-2: a bare urban lot with a «SE VENDE» board, survey stakes, and the buildable envelope as a ghost volume (B+5, 18 homes in the course's own example). Module 3: a white blueprint on the ground. Modules 4-5: the site hoarding with the cartel de obra (licence number, promotor, técnicos, constructor, plazos) and the CONSTRUYE mark, site huts, the tower crane and the temporary connections. Module 6: the building rises from below: excavation, foundations, each level of structure in turn, façade, roof, installations, finishes, the street works. Modules 9-12: the finished, lit building with its entrance hall, mailboxes and keys.

*Papeles:*

- Levantamiento **topográfico** (superficie real, cotas, linderos) — M1 1.3
- Geotecnia preliminar si hay sótanos — M1 checklist
- Acta de replanteo firmada y cartel de obra colocado — M4 checklist
- Prueba de estanqueidad de cubiertas con acta — M6 checklist
- Acta de recepción firmada — M6 checklist
- Inspección final y puesta en servicio de instalaciones — M9 checklist
- Tres rondas de repasos cerradas — M10 checklist
- Acta de entrega de posesión — M11 11.4
- Actas de entrega firmadas — M11 checklist

### SPE (sociedad de propósito específico) · Project manager del promotor

*En el mundo:* Oficina del promotor (`promotor`) · *Módulos:* 1, 2, 5, 7, 8, 10, 12

*Aspecto:* A small office on a side street with a glass front, a brass plate «… S.L.» (the SPE, its name left generic) and the CONSTRUYE mark (good for marketing). Inside: a whiteboard with the CRP and the Gantt, a meeting table for the junta de socios, a filing cabinet labelled «EXPEDIENTE DE LA PROMOCIÓN», and a screen where the module's template opens. It is the camera's home base between trips.

*Papeles:*

- Ficha de producto definida (tipo, público, tipologías, calidades, precio objetivo) — M1 checklist
- Solar dado de alta en tu base de datos de gestión de suelo, con su propietario y su captador — M1 checklist
- Números gordos del solar hechos antes de dedicarle más tiempo — M1 checklist
- Plantilla 1 · Compra del solar (incluida) rellena con el precio, la comunidad autónoma y los gastos reales — M1 checklist
- SPE constituida — M1 checklist
- Plantilla 2 · Viabilidad rápida y método residual (incluida) rellena con precios de venta y costes contrastados — M2 checklist
- CRP con tres escenarios — M2 checklist
- Flujo de caja mensual con TIR y necesidades de fondos propios — M2 checklist
- Pacto de socios y estructura de la SPV — M2 checklist
- Memoria de inversión aprobada con hitos y criterios de salida — M2 checklist
- Plantilla 3 · Préstamo promotor (incluida) rellena con la oferta del banco — M5 checklist
- Plantilla 4 · Control de costes de obra (incluida) rellena actualizada con cada certificación — M7 checklist
- Coste presupuestado, comprometido y real conciliados — M7 checklist
- Plantilla 5 · Plan de ventas y cobros (incluida) rellena con la política de precios y de pagos — M8 checklist
- Notas de liquidación por comprador — M10 checklist
- Junta general: acuerdo de disolución y nombramiento de liquidador — M12 12.4
- Rentabilidad real comparada con la CRP aprobada — M12 checklist
- Informe de lecciones aprendidas — M12 checklist
- Expediente archivado con plazos de conservación — M12 checklist

### Ayuntamiento · Urbanismo / Ayuntamiento · Gestión tributaria

*En el mundo:* Ayuntamiento (`ayuntamiento`) · *Módulos:* 1, 2, 4, 6, 9, 10, 11, 12

*Aspecto:* The town hall (casa consistorial) on the plaza mayor: two or three storeys of stone with an arcade (soportales) at street level, a central balcony with flagpoles (Spain, the comunidad autónoma, the town), the municipal coat of arms over the door, and a clock with a bell in a small tower or espadaña on top, with the sign «AYUNTAMIENTO». Two doors, split as the course splits it: «URBANISMO» (cédula urbanística, building licence, first occupation) and «GESTIÓN TRIBUTARIA» (IBI, ICIO, fees, plusvalía). A municipal notice board (tablón de anuncios) at the door, and a local police car beside it for the street closures of Module 4.

*Papeles:*

- Informe o cédula urbanística / consulta previa — M1 Dónde ir · 1-3 meses
- Certificado de deudas de IBI y tasas — M1 Dónde ir · 1-15 días
- Ordenanza fiscal del ICIO y de tasas — M2 Dónde ir
- Licencia de obras mayor (o declaración responsable donde la ley lo permita) — M4 Dónde ir · 1-6 meses según municipio y ley autonómica
- Autoliquidación o liquidación provisional del ICIO y tasa — M4 Dónde ir
- fianza de residuos si la pide la ordenanza — M4 4.1
- Licencias de demolición, movimiento de tierras o tala si aplican — M4 checklist
- Ocupación de vía pública — M4 4.2 (tasa municipal)
- autorización municipal y policía local — M4 4.2, cortes de tráfico
- Cartel de obra — M4 4.3
- Comunicación de inicio al ayuntamiento si la ordenanza la pide — M4 4.3
- entrega al ayuntamiento de lo que sea dominio público, si la licencia lo exige — M6 6.4
- Licencia o declaración responsable de primera ocupación — M9 Dónde ir · 1-3 meses (la declaración responsable es inmediata en eficacia, con comprobación posterior)
- liquidación definitiva del ICIO — M9 9.3
- Valores catastrales y datos para la plusvalía de cada vivienda — M10 Dónde ir
- Autoliquidación de la plusvalía (30 días hábiles desde cada venta) y cambio de titular del IBI — M11 Dónde ir
- una autoliquidación por cada venta — M12 12.3, plusvalía municipal; IBI of unsold homes at 1 January

### Registro de la Propiedad (registradores.org)

*En el mundo:* Registro de la Propiedad (`registro_propiedad`) · *Módulos:* 1, 2, 4, 9, 10, 11, 12

*Aspecto:* A sober granite building on the plaza with tall barred windows that show floor-to-ceiling shelves of bound registry books (one spine per finca), the Spanish coat of arms and a bronze plaque «REGISTRO DE LA PROPIEDAD». Each paper leaves with a registry stamp. In Module 9 the plot's single finca splits on an open ledger into one finca per home (18 in the course's example); in Module 11 each finca's owner becomes a buyer.

*Papeles:*

- Nota simple de la finca — M1 Dónde ir · Inmediato online · ≈ 9 €
- Certificación de dominio y cargas — M1 Dónde ir · 2-10 días · según arancel
- Opción de compra o escritura firmada e inscrita — M1 checklist
- Precios escriturados por zona — M2 Dónde ir, Estadística Registral
- Inscripción en el Registro de la Propiedad — M2 2.5, adquisición del solar
- la necesitas para que el banco inscriba la hipoteca sobre el edificio futuro — M4 4.3, obra nueva en construcción
- Inscripción: cada vivienda pasa a ser una finca — M9 Dónde ir · 15 días hábiles (prorrogables)
- Escritura de obra nueva terminada y división horizontal firmada e inscrita — M9 checklist
- nota simple actualizada de la finca — M10 10.3
- Inscripción a nombre del comprador (la tramita la notaría o la gestoría) — M11 Dónde ir
- Libro de actas diligenciado en el Registro de la Propiedad — M11 11.2
- Préstamo promotor cancelado en el Registro — M12 checklist

### Registro Mercantil (Registro Mercantil Central; Registro Mercantil provincial)

*En el mundo:* Registro Mercantil (`registro_mercantil`) · *Módulos:* 1, 5, 12

*Aspecto:* A twin of the Registro de la Propiedad, for companies: a narrower office with a plaque «REGISTRO MERCANTIL», shelves of company folders with «S.L.» on their spines, and a counter where the SPE's founding deed is stamped (Module 1). In Module 12 its folder is closed, or moved to an «inactiva» shelf.

*Papeles:*

- denominación (Registro Mercantil Central) — M1 1.4
- Constitución de la SPE — M1 Dónde ir (Registro Mercantil + notaría + AEAT) → Escritura, NIF, inscripción · 1-3 semanas
- inscripción en el Registro Mercantil provincial — M1 1.4
- Vía rápida: CIRCE / PAE — M1 1.4
- Cuentas anuales de los tres últimos años (Registro Mercantil) — M5 5.3, of each builder invited
- Depósito de cuentas, disolución, extinción — M12 Dónde ir
- Inscripción en el Registro Mercantil — M12 12.4, of the extinción

### Catastro (sede.catastro.gob.es; en Navarra y País Vasco, el catastro foral) · Gerencia del Catastro

*En el mundo:* Catastro (`catastro`) · *Módulos:* 1, 2, 3, 9, 11

*Aspecto:* A «GERENCIA DEL CATASTRO» office whose front window is a large cadastral map (the town's lots outlined, each with its referencia catastral), with map drawers inside and a surveyor's tripod at the door. The plot's parcel lights up with its reference in Module 1; the new building's footprint and floors are drawn onto the map when the obra nueva is recorded (Module 9).

*Papeles:*

- Consulta y certificación catastral — M1 Dónde ir → Referencia catastral, superficie, plano · Inmediato · gratuito para el titular
- Referencia catastral y topográfico coincidentes — M1 checklist
- Valor de referencia de inmuebles de la zona — M2 Dónde ir → Referencia que usa Hacienda en ITP
- te sirve también para coordinar Catastro y Registro, Ley 13/2015 — M3 3.3, georeferenced survey
- Alta de la obra nueva (la comunican notario y registrador; si no, modelo 902-N) — M9 Dónde ir · 1-6 meses
- Catastro actualizado — M9 checklist
- notaría y registro comunican el cambio de titular; no hace falta presentar declaración — M11 11.5

### Notaría (notariado.org) · Colegio Notarial

*En el mundo:* Notaría (`notaria`) · *Módulos:* 1, 2, 4, 8, 9, 10, 11, 12

*Aspecto:* The ground floor of a stately 19th-century building on the plaza: a heavy wooden double door, a polished brass plate «NOTARÍA», and inside a long signing table with leather chairs, the bound protocolo on the shelves, a fountain pen and the notary's seal. Each deed rises as a cream sheet with a seal and flies to the Registro: option, purchase, obra nueva, división horizontal, each sale, the cancellation, the extinción.

*Papeles:*

- Opción de compra, arras o compraventa — M1 Dónde ir → Escritura pública · Según arancel (RD 1426/1989)
- escritura ante notario — M1 1.4, SPE
- Escritura pública de compraventa — M2 2.5
- **Poderes** notariales para quien gestiona — M2 2.5
- Escritura de declaración de obra nueva en construcción — M4 4.3 (Módulo 9)
- Revisión del modelo de contrato privado, plantilla de escrituras — M8 Dónde ir
- Escritura de declaración de obra nueva terminada y división horizontal — M9 Dónde ir · 1-3 semanas
- Calendario de firmas, minuta de escritura, provisión de fondos — M10 Dónde ir
- el comprador comparece ante notario para el acta previa al menos el día anterior a la firma — M10, Ley 5/2019
- Copia de la escritura (tras la firma) — M10 10.2
- Firma de la compraventa y, en su caso, hipoteca del comprador o cancelación parcial — M11 Dónde ir
- Escrituras de cancelación de hipoteca y de extinción de la sociedad — M12 Dónde ir

### AEAT o hacienda foral (Agencia Tributaria)

*En el mundo:* Hacienda (`hacienda`) · *Módulos:* 1, 5, 11, 12

*Aspecto:* A modern institutional block with the sign «AGENCIA TRIBUTARIA», the Spanish flag, a numbered-ticket queue and service counters. Tax forms leave by number: 036, 303, 390, 200, 202. In Navarra and País Vasco the same building is labelled as the hacienda foral (the province data carries `foral`).

*Papeles:*

- NIF provisional (AEAT, modelo 036) — M1 1.4
- NIF definitivo — M1 1.4
- IVA 21 % + AJD de la escritura (Anexo B) — M1 1.4, empresario vende terreno edificable
- subastas de AEAT y Seguridad Social — M1 Dónde ir
- Certificados del contratista de estar al corriente — M5 Dónde ir (AEAT y TGSS)
- NIF de la comunidad de propietarios (modelo 036) — M11 Dónde ir
- IVA repercutido en tu modelo 303 — M11 11.5
- IS (modelo 200), IVA (303 y 390), baja censal (036) — M12 Dónde ir
- pagos fraccionados (modelo 202) — M12 12.3

### hacienda de tu comunidad (modelo 600)

*En el mundo:* Hacienda (`hacienda`) · *Módulos:* 1, 2, 9, 11

*Aspecto:* A second counter in the Hacienda building, or a regional building flying the comunidad autónoma's flag, for the taxes the course sends to «el modelo 600 de tu comunidad»: ITP or AJD on the land, AJD on the obra nueva and división horizontal, and the buyer's AJD. Its rates are the student's region's (Anexo B / legal parameters), never written into the model.

*Papeles:*

- ITP de la comunidad (Anexo B). Sin AJD — M1 1.4, particular vende
- ITP o AJD con el modelo 600 de tu comunidad, en su plazo (habitualmente 30 días hábiles) — M2 2.5
- AJD (Anexo B) sobre el valor de la obra nueva y de la división horizontal — M9 9.2
- el comprador liquida el AJD con el modelo 600 de su comunidad en el plazo autonómico (habitualmente 30 días hábiles) — M11 11.5

### Banco (Anexo A: banca con presencia en tu provincia) · Banco del promotor · Bancos de los compradores

*En el mundo:* Banco (`banco`) · *Módulos:* 1, 2, 5, 7, 8, 10, 11, 12

*Aspecto:* A bank branch at the foot of the tallest office tower: a glass front, an ATM, a generic «BANCO» sign (no real brand), a glimpse of the vault door and a meeting room for the promoter. When the loan is signed, the préstamo promotor and the póliza de avales rise from it. During the works a stream of coins goes to the site after each certification; in Module 11 a bar shrinks to zero, deed by deed. A second, smaller branch can stand for «Bancos de los compradores» (Module 10).

*Papeles:*

- cuenta bancaria y aportación — M1 1.4, SPE
- Conversación previa de financiación — M2 Dónde ir → Condiciones orientativas y requisitos de preventas
- Préstamo promotor, línea de avales para anticipos — M5 Dónde ir → Oferta vinculante, póliza de avales
- Cuenta especial de cantidades a cuenta abierta — M5 checklist
- Por certificaciones de obra — M5 5.1, disposición
- Informe mensual (coste, plazo, ventas) enviado a socios y banco — M7 checklist
- Línea de avales individuales para anticipos y oferta hipotecaria para compradores — M8 Dónde ir
- Certificado de deuda por vivienda y carta de pago o de subrogación — M10 Dónde ir (Banco del promotor)
- Fecha de firma de cada hipoteca — M10 Dónde ir (Bancos de los compradores)
- Cuenta de la comunidad — M11 Dónde ir
- Parte del préstamo promotor cancelada o subrogada en cada firma — M11 checklist
- Préstamo promotor cancelado en el Registro — M12 checklist

### Sociedad de tasación homologada (registro del Banco de España) · Sociedad de tasación del banco · Tasador del banco

*En el mundo:* Sociedad de tasación homologada (registro del Banco de España) — *falta en el mundo* · *Módulos:* 2, 5, 7

*Aspecto:* A small office beside the bank with the sign «SOCIEDAD DE TASACIÓN». A valuer with a tablet and a measuring wheel walks to the site before each drawdown (Module 7), and values the land and the «edificio terminado» as a translucent finished building over the plot (Modules 2 and 5). It can share the bank's block: the course writes Sociedad de tasación del banco (M5) and Tasador del banco (M7).

*Papeles:*

- Tasación del suelo y del edificio terminado ("hipótesis de edificio terminado") — M2 Dónde ir → El valor que usará el banco
- Tasación en hipótesis de edificio terminado — M5 Dónde ir → Valor para el LTV
- Verificación de cada certificación antes de disponer del préstamo — M7 Dónde ir

### Aseguradora decenal (vía corredor) · Corredor de seguros (Colegio de Mediadores de tu provincia)

*En el mundo:* Aseguradora (`aseguradora`) · *Módulos:* 3, 5, 8, 9, 10, 12

*Aspecto:* An insurer's building with a generic shield-and-umbrella emblem and the sign «SEGUROS · DECENAL», with a broker's shopfront at street level («CORREDOR DE SEGUROS»). In Module 5 a translucent shield (the decenal) settles over the site; it locks at the reception and stays over the building through Module 12 (10 years).

*Papeles:*

- Prerrevisión del riesgo — M3 Dónde ir → Condiciones del seguro antes de cerrar el proyecto
- Decenal, TRC, RC, caución — M5 Dónde ir (corredor) → Pólizas comparadas
- Póliza decenal con OCT — M5 Dónde ir → Suplemento de entrada en riesgo y póliza definitiva
- seguro de caución o aval solidario de entidad autorizada — M5 5.2, cantidades a cuenta
- aseguradora de las cantidades a cuenta — M8 8.3
- Informe final del OCT y póliza decenal definitiva — M9 9.1
- Certificado de la póliza decenal — M10 10.2
- Partes de daños estructurales — M12 Dónde ir

### arquitecto · Dirección facultativa (director de obra + director de ejecución)

*En el mundo:* Estudio de arquitectura (`estudio_arquitectura`) · *Módulos:* 1, 2, 3, 4, 6, 7, 9

*Aspecto:* A small studio with a glass front: drafting tables, rolled plans, and a white model (maqueta) of the course's B+5 building in the window, with the sign «ESTUDIO DE ARQUITECTURA». The architect and the arquitecto técnico (the dirección facultativa) walk to the site with the libro de órdenes in Modules 4-7.

*Papeles:*

- estudio de volumetría — M1 1.2 · (1-2 semanas)
- Encaje (mono) del arquitecto — M2 checklist
- Mix de producto cerrado con el arquitecto — M2 checklist
- Contratos firmados con arquitecto, arquitecto técnico, CSS e ingenierías — M3 checklist
- Anteproyecto validado con el mix comercial — M3 checklist
- Proyecto básico (visado si lo exige el ayuntamiento) — M3 checklist
- Proyecto de ejecución visado con ESS, gestión de residuos, ICT y eficiencia energética — M3 checklist
- Coordinador designado y plan de seguridad aprobado — M4 checklist
- Libro de órdenes y asistencias — M4 4.3; M7 7.1
- Acta de comprobación del replanteo — M4 4.3
- Certificado final de obra (CFO) — M6 6.5
- Documentación de la obra ejecutada (art. 7 LOE) — M6 6.5
- Órdenes de obra, certificaciones mensuales, control de calidad — M7 Dónde ir
- Informe mensual al promotor — M7 7.1
- Relación de controles y ensayos — M9 9.1
- Planos de lo construido — M9 9.1

### Colegio Oficial de Arquitectos · Colegio de Aparejadores y Arquitectos Técnicos

*En el mundo:* Colegio profesional (`colegio_profesional`) · *Módulos:* 2, 3, 4, 6, 7, 9

*Aspecto:* An institutional building with a short portico and the sign «COLEGIO OFICIAL DE ARQUITECTOS», with a second plaque «COLEGIO DE APAREJADORES Y ARQUITECTOS TÉCNICOS». Its signature animation is a large «VISADO» stamp coming down on the proyecto de ejecución (Module 3) and on the CFO (Modules 6 and 9). In Module 4 it hands out the libro de órdenes y asistencias and the libro de incidencias.

*Papeles:*

- estadística de visados del colegio de arquitectos — M2 2.2
- Bolsa de arquitectos, visado del proyecto — M3 Dónde ir → Visado (comprueba identidad, habilitación, seguro y que el proyecto está completo)
- Director de ejecución y coordinador de seguridad y salud — M3 Dónde ir → Profesionales colegiados y visado de sus trabajos
- Libro de órdenes y asistencias, libro de incidencias — M4 Dónde ir · Al inicio
- Visado del certificado final de obra — M6 Dónde ir · Al terminar
- Visado del CFO — M9 Dónde ir · 1-2 semanas
- CFO visado — M6 and M9 checklists

### Constructor · constructoras · Constructor y agentes

*En el mundo:* Constructora (`constructora`) · *Módulos:* 2, 3, 4, 5, 6, 7, 10, 12

*Aspecto:* A builder's yard: a site-hut office with the sign «CONSTRUCTORA», pallets of bricks, bundles of rebar and formwork, an excavator, a concrete-mixer truck and a folded tower crane on a trailer. In Module 5 three to five yards light up for the tender (the course's «3-5 constructoras»); the winner's crane and trucks then drive to the site.

*Papeles:*

- Presupuesto de construcción contrastado con 2 constructoras locales — M2 checklist
- pide al OCT y a dos constructoras que revisen el proyecto — M3 3.3
- Plan de seguridad y salud — M4 4.3, del contratista
- Comunicación de apertura del centro de trabajo — M4 4.3, la presenta el contratista
- Pliego de licitación — M5 5.3
- Licitación con 3-5 constructoras y comparativo por capítulos — M5 checklist
- Contrato de obra firmado con retenciones, penalidades y régimen de modificados — M5 checklist
- aval de fiel cumplimiento — M5 5.3
- REA y certificados de AEAT y TGSS del contratista — M5 checklist
- Plan de gestión de residuos — M5 5.4, del contratista
- planos de lo construido, garantías de equipos, certificados de instaladores, certificados de gestión de residuos, manuales — M6 6.5
- Planning (Gantt) — M7 7.4
- Corrección de repasos en plazo (con cargo a la retención si no cumple) — M10 Dónde ir
- Reparaciones en garantía, con cargo a retenciones si no responden — M12 Dónde ir
- Liquidación final con el constructor — M12 checklist

### OCT · Organismo de Control Técnico (OCT)

*En el mundo:* Laboratorio y OCT (`laboratorio_oct`) · *Módulos:* 3, 5, 6, 7, 9

*Aspecto:* An inspection agency office with a magnifier-and-tick emblem and the sign «OCT · CONTROL TÉCNICO», with no brand logo (the course's examples are named only in the text). An inspector with a clipboard and a hard hat visits the site at the points the course lists: the project (D0), the foundations, the rebar before each pour, the roofs and basements, and the final report.

*Papeles:*

- Revisión del proyecto — M3 Dónde ir → Informe D0 para la aseguradora decenal
- Revisión del proyecto por el OCT (informe D0) — M3 checklist
- Decenal (con OCT), TRC y RC contratados — M5 checklist
- Informes de inspección y el informe final que la aseguradora necesita — M7 Dónde ir
- Informes del OCT al día — M7 checklist
- Informe final del OCT y póliza decenal definitiva — M9 9.1

### Laboratorio de geotecnia inscrito en el registro de la CCAA · Laboratorio acreditado

*En el mundo:* Laboratorio y OCT (`laboratorio_oct`) · *Módulos:* 1, 3, 5, 6, 7

*Aspecto:* A low lab building with the sign «LABORATORIO · CONTROL DE CALIDAD», with racks of concrete test cylinders (probetas) and a compression press visible through the window. A geotechnical drilling rig and core boxes stand on the plot in Modules 1 and 3; sample cylinders are carried away from each concrete pour in Modules 6-7.

*Papeles:*

- Geotecnia preliminar si hay sótanos — M1 checklist
- Estudio geotécnico (CTE DB SE-C) — M3 Dónde ir → Tipo de cimentación, nivel freático, tensiones admisibles
- Laboratorio y control de calidad contratados — M5 5.4
- Certificados del hormigón, acero y ensayos archivados — M6 checklist
- Ensayos de hormigón, acero, estanqueidad — M7 Dónde ir
- Plan de control de calidad aprobado — M7 checklist
- Ensayos del laboratorio archivados — M7 checklist

### Confederación Hidrográfica o agencia autonómica del agua (Anexo A)

*En el mundo:* Organismos sectoriales (`organismo_sectorial`) · *Módulos:* 1, 4

*Aspecto:* A government office by a river or barranco, with a weir, a water-level gauge, a water-drop emblem and the sign «CONFEDERACIÓN HIDROGRÁFICA». On the plot, a blue band 100 m wide along the stream appears only when the plot falls in the zona de policía; in Module 1 the SNCZI flood layer appears too.

*Papeles:*

- Servidumbres de cauces, costas, carreteras, ferrocarril y aeropuertos (servidumbres aeronáuticas, AESA) — M1 1.3
- Sistema Nacional de Cartografía de Zonas Inundables (SNCZI, MITECO) — M1 1.2, riesgo de inundación
- Autorización en zona de policía — M4 Dónde ir · 2-6 meses
- Zona de policía de 100 m a cada lado de un cauce — M4 leyes, RD 849/1986

### Demarcación o Servicio Provincial de Costas (MITECO) y CCAA

*En el mundo:* Organismos sectoriales (`organismo_sectorial`) · *Módulos:* 1, 4

*Aspecto:* An office on the seafront with a small lighthouse and the sign «DEMARCACIÓN DE COSTAS», with the protection strip (100 m, or 20 m) drawn inland from the shore. Shown only for coastal provinces (the Anexo A data has `costa`).

*Papeles:*

- Informe y autorización en servidumbre de protección — M4 Dónde ir · 2-6 meses
- Servidumbre de protección (100 m; 20 m en suelos que eran urbanos en 1988) y de tránsito — M4 leyes, Ley 22/1988

### Demarcación de Carreteras del Estado / CCAA / Diputación

*En el mundo:* Organismos sectoriales (`organismo_sectorial`) · *Módulos:* 1, 4

*Aspecto:* A roads office beside a highway with a kilometre post and a blue road sign («DEMARCACIÓN DE CARRETERAS»). The línea límite de edificación is drawn along the road when the plot touches it.

*Papeles:*

- Autorización en zona de protección de carretera — M4 Dónde ir · 1-3 meses
- Zonas de protección y línea límite de edificación — M4 leyes, Ley 37/2015

### ADIF

*En el mundo:* Organismos sectoriales (`organismo_sectorial`) · *Módulos:* 1, 4

*Aspecto:* A small station building with a railway track and overhead catenary at the edge of the board, named «ADIF» in plain lettering (no logo). The protection zone along the track lights up when it applies.

*Papeles:*

- Autorización en zona de protección ferroviaria — M4 Dónde ir · 1-3 meses

### AESA

*En el mundo:* Organismos sectoriales (`organismo_sectorial`) · *Módulos:* 1, 4

*Aspecto:* A control tower and windsock at the edge of town, with a plane crossing overhead. When the plot is near an airport, a translucent ceiling (the maximum height under the servidumbres aeronáuticas) appears over the building envelope.

*Papeles:*

- Informe de servidumbres aeronáuticas — M4 Dónde ir · 1-3 meses
- Alturas máximas cerca de aeropuertos: informe de AESA — M4 leyes, Ley 48/1960 y RD 297/2013

### Consejería de Cultura de la CCAA

*En el mundo:* Organismos sectoriales (`organismo_sectorial`) · *Módulos:* 1, 4

*Aspecto:* A neoclassical, museum-like building with a pediment and the sign «CONSEJERÍA DE CULTURA». On the plot, a grid of archaeological trenches marked out with strings, shown only when heritage protection applies.

*Papeles:*

- Revisión de servidumbres sectoriales (cauces, costas, carreteras, patrimonio) — M1 checklist
- Arbolado protegido y yacimientos arqueológicos — M1 1.3
- Informe de patrimonio o intervención arqueológica — M4 Dónde ir · 1-3 meses
- Bienes de interés cultural y zonas arqueológicas — M4 leyes, Ley 16/1985

### órgano ambiental de la CCAA

*En el mundo:* Organismos sectoriales (`organismo_sectorial`) · *Módulos:* 4

*Aspecto:* A green-roofed regional office with a leaf emblem («ÓRGANO AMBIENTAL»), shown only if the project passes the environmental thresholds.

*Papeles:*

- Evaluación de impacto ambiental (órgano ambiental de la CCAA) — M4 4.2, por encima de umbrales ambientales

### Ministerio de Defensa

*En el mundo:* Organismos sectoriales (`organismo_sectorial`) · *Módulos:* 4

*Aspecto:* A fenced gate with a sentry box at the edge of the board, shown only when the plot is near a military installation.

*Papeles:*

- Autorización del Ministerio de Defensa — M4 4.2, cerca de una instalación militar

### las compañías (agua, saneamiento, electricidad, telecomunicaciones) · Distribuidora eléctrica (Anexo A) · Empresa municipal de aguas · Distribuidora y comercializadoras

*En el mundo:* Compañías suministradoras (`suministros`) · *Módulos:* 1, 3, 4, 6, 9, 11

*Aspecto:* A utilities compound at the edge of town: a transformer kiosk (centro de transformación) with a high-voltage sign («DISTRIBUIDORA ELÉCTRICA»), a water depot with blue pipes («EMPRESA MUNICIPAL DE AGUAS») and a telecom mast with a fibre cabinet. Animated cables and pipes run under the street to the plot: the capacity check (Module 1), the temporary connections at the start of the works (Module 6), the definitive ones (Module 9), and each home's lights coming on with its supply contract (Module 11).

*Papeles:*

- Capacidad de acometidas confirmada con las compañías — M1 checklist
- centro de transformación o refuerzo de red — M1 1.3
- Consulta a las compañías sobre redes de agua, saneamiento, electricidad, gas y telecomunicaciones — M3 3.3
- solicitudes de suministro y punto de conexión a la distribuidora eléctrica, a la empresa de aguas y al resto de compañías — M4 4.2
- Punto de conexión y suministro de obra; luego acometida definitiva — M6 Dónde ir · Al inicio: los plazos de conexión son largos
- Acometida de obra y definitiva — M6 Dónde ir (Empresa municipal de aguas) · Al inicio
- Suministros de obra y punto de conexión pedidos al inicio — M6 checklist
- Contratos y suministros definitivos — M9 9.1
- certificado de las acometidas — M9 9.3
- Altas de luz por vivienda (con CIE y primera ocupación o cédula) — M11 Dónde ir
- Altas de agua por vivienda — M11 Dónde ir
- CUPS de cada vivienda — M11 11.3
- Altas de suministros — M11 checklist

### Autoridad laboral de la CCAA (Anexo A)

*En el mundo:* Seguridad Social (`autoridad_laboral`) · *Módulos:* 4, 5

*Aspecto:* A regional labour office with a hard-hat-and-shield emblem and the sign «AUTORIDAD LABORAL». Before the first lorry (Module 4), a counter stamps the «Comunicación de apertura del centro de trabajo» and hands over the libro de subcontratación; a REA lookup screen serves Module 5.

*Papeles:*

- Comunicación de apertura (la presenta el contratista), REA, habilitación del libro de subcontratación — M4 Dónde ir · Antes de empezar
- Comunicación de apertura, REA y libro de subcontratación — M4 checklist
- Consulta del REA — M5 Dónde ir → Comprobación de cada subcontrata

### TGSS · Seguridad Social

*En el mundo:* Seguridad Social (`autoridad_laboral`) · *Módulos:* 1, 5

*Aspecto:* A «SEGURIDAD SOCIAL · TGSS» office front next to the labour authority, where the builder's certificate of being up to date comes from (Module 5).

*Papeles:*

- subastas de AEAT y Seguridad Social — M1 Dónde ir
- Certificados de estar al corriente con AEAT y Seguridad Social — M5 5.3
- certificados de AEAT y TGSS de cada subcontrata — M5 5.4

### Servicio de Industria de la CCAA

*En el mundo:* Servicio de Industria de la CCAA — *falta en el mundo* · *Módulos:* 4, 6, 9

*Aspecto:* A regional «SERVICIO DE INDUSTRIA» office with a gear-and-lightning emblem. As each installation is finished, its certificate (CIE, RITE, PCI, ascensor, ICT) is stamped and returned; the office also registers the tower crane (Module 4).

*Papeles:*

- registro en el Servicio de Industria de la comunidad (ITC MIE-AEM 2, RD 836/2003) — M4 4.2, grúa torre
- Registro de las instalaciones (electricidad, térmicas, PCI, ascensores) — M6 Dónde ir · Al terminar cada instalación
- Certificados de instaladores registrados en Industria (eléctrica, térmica, PCI, ascensor, ICT) — M6 checklist
- Registro de las instalaciones (CIE, RITE, PCI, ascensor) — M9 Dónde ir · 1-4 semanas
- Instalaciones registradas en Industria — M9 checklist

### Registro autonómico de certificados energéticos

*En el mundo:* Registro autonómico de certificados energéticos — *falta en el mundo* · *Módulos:* 9

*Aspecto:* A counter, which can share the regional building with Industria, with a large A-G energy-label poster. The finished building receives its label plaque beside the entrance (Module 9).

*Papeles:*

- Inscripción del certificado del edificio terminado — M9 Dónde ir · 1-4 semanas
- Certificado energético registrado — M9 checklist

### Organismo autonómico o insular (en las 8 CCAA que la mantienen)

*En el mundo:* Organismo autonómico o insular (en las 8 CCAA que la mantienen) — *falta en el mundo* · *Módulos:* 9, 10, 11

*Aspecto:* A regional or island housing office («CÉDULA DE HABITABILIDAD») that appears only when the student's comunidad keeps the cédula (the Anexo A data has `cedula`); each home receives its cédula.

*Papeles:*

- Cédula de habitabilidad de cada vivienda — M9 Dónde ir · 1-2 meses
- Cédula de habitabilidad (si tu CCAA la exige) — M9 checklist

### Gestor autorizado de residuos

*En el mundo:* Gestor autorizado de residuos — *falta en el mundo* · *Módulos:* 6, 9

*Aspecto:* A recycling yard with skips of rubble (RCD), a weighbridge, and a lorry shuttling from the site for the whole of the works; a certificate goes back with each load.

*Papeles:*

- Contrato y certificados de gestión — M6 Dónde ir · Durante toda la obra
- Certificados de gestión de residuos — M6 checklist; M9 9.1

### Fundación Laboral de la Construcción

*En el mundo:* Fundación Laboral de la Construcción — *falta en el mundo* · *Módulos:* 6

*Aspecto:* A training centre with hard hats on hooks and a classroom; workers leave with a card (the Tarjeta Profesional de la Construcción) before they enter the site.

*Papeles:*

- Formación y Tarjeta Profesional de la Construcción — M6 Dónde ir · Antes de que entren los trabajadores

### oficina de ventas · piso piloto (físico o virtual)

*En el mundo:* Oficina de ventas (`oficina_ventas`) · *Módulos:* 8

*Aspecto:* A glass sales pavilion against the site hoarding: large renders of the building, a «VENTA SOBRE PLANO» banner with the CONSTRUYE mark, the energy-label poster (required in the advertising), a show-flat module (piso piloto) and a desk with the contrato privado. «VENDIDO» tags appear on the homes as the bank's pre-sales fill.

*Papeles:*

- renders, planos acotados por tipología, memoria de calidades, dossier PDF, vídeo, tour virtual, piso piloto (físico o virtual) — M8 8.1
- Documentación del RD 515/1989 en la oficina de ventas — M8 checklist
- Calificación energética en la publicidad — M8 checklist
- Plazo para firmar el contrato privado: 7–30 días — M8 8.3, reserva
- Contrato privado de compraventa — M8 8.3
- Datos de la garantía — M8 8.3, aval o seguro de caución individual y cuenta especial
- Línea de avales y cuenta especial operativas antes de cobrar la primera reserva — M8 checklist
- CRM con seguimiento de cada comprador — M8 checklist
- Expediente individual de cada comprador — M8 checklist
- Preventas exigidas por el banco alcanzadas — M8 checklist

### COAPI de tu provincia y registro autonómico de agentes (Anexo A)

*En el mundo:* COAPI de tu provincia y registro autonómico de agentes (Anexo A) — *falta en el mundo* · *Módulos:* 1, 8

*Aspecto:* A row of estate-agency shopfronts near the sales office with home cards in their windows, one with a «COAPI» colegio plaque. In Cataluña, Comunitat Valenciana and Canarias the agencies display their regional registration. It also appears in Module 1 (the partners: «Agentes especializados en suelo», Anexo A).

*Papeles:*

- Agencias colaboradoras inscritas — M8 Dónde ir
- registro autonómico obligatorio — M8 8.2: Cataluña, Comunitat Valenciana y Canarias
- Contratos de colaboración con agencias inscritas — M8 checklist

### Asociación de promotores de tu zona (Anexo A)

*En el mundo:* Asociación de promotores de tu zona (Anexo A) — *falta en el mundo* · *Módulos:* 8

*Aspecto:* An upper-floor office on the promoter's street with the plaque «ASOCIACIÓN DE PROMOTORES» and a meeting room with market charts.

*Papeles:*

- Datos de mercado, modelos de contrato, contactos — M8 Dónde ir

### SEPBLAC (Banco de España)

*En el mundo:* SEPBLAC (Banco de España) — *falta en el mundo* · *Módulos:* 8, 10

*Aspecto:* A columned institutional building with the plaque «SEPBLAC». Before each reservation and each deed, a magnifier passes over the buyer's file (DNI/NIE, titular real, origen de los fondos).

*Papeles:*

- Manual de prevención de blanqueo y comunicación de operaciones sospechosas — M8 Dónde ir
- Procedimiento de prevención de blanqueo — M8 checklist
- Comprobación de blanqueo de cada comprador — M10 checklist

### Oficina de consumo de la CCAA / OMIC

*En el mundo:* Oficina de consumo de la CCAA / OMIC — *falta en el mundo* · *Módulos:* 8

*Aspecto:* A small consumer-information counter (OMIC) under the Ayuntamiento's arcade, with the complaint forms (hojas de reclamaciones).

*Papeles:*

- Hojas de reclamaciones y requisitos autonómicos — M8 Dónde ir

### comunidad de propietarios (presidente o administrador)

*En el mundo:* Comunidad de propietarios (`comunidad_propietarios`) — *falta en el mundo* · *Módulos:* 9, 10, 11, 12

*Aspecto:* Not a separate building but the finished building's entrance hall (portal): mailboxes, a community notice board, and a meeting room with chairs for the founding meeting (junta constitutiva). The president receives the Libro del Edificio and the keys of the common areas (Modules 9-12). The 3D engine has no PlaceKey for it yet.

*Papeles:*

- Libro del Edificio completo — M9 checklist; entregado a la comunidad (9.4)
- Datos de la comunidad — M10 10.2: Estatutos, cuota, administrador
- certificado de la comunidad de estar al corriente — M10 10.3
- Junta constitutiva, NIF, cuenta y fondo de reserva — M11 checklist
- Contratos de mantenimiento de la comunidad — M11 checklist
- Acta de traspaso a la comunidad — M12 checklist

### Colegio de Administradores de Fincas

*En el mundo:* Colegio de Administradores de Fincas — *falta en el mundo* · *Módulos:* 11

*Aspecto:* An office with the sign «ADMINISTRACIÓN DE FINCAS» and a colegio plaque; its administrator walks to the building's entrance hall for the junta (Module 11).

*Papeles:*

- Administrador para la comunidad — M11 Dónde ir

### Junta Arbitral de Consumo de la CCAA

*En el mundo:* Junta Arbitral de Consumo de la CCAA — *falta en el mundo* · *Módulos:* 12

*Aspecto:* A small regional office with a balance emblem («JUNTA ARBITRAL DE CONSUMO»), lit only in Module 12.

*Papeles:*

- Arbitraje de reclamaciones de compradores — M12 Dónde ir

### Portal de Subastas del BOE (subastas.boe.es), subastas de AEAT y Seguridad Social

*En el mundo:* Portal de Subastas del BOE (subastas.boe.es), subastas de AEAT y Seguridad Social — *falta en el mundo* · *Módulos:* 1

*Aspecto:* Online: an auction screen in the promoter's office with a countdown and a plot card, with no logo.

*Papeles:*

- Solares embargados — M1 Dónde ir → Puja online

### SAREB, servicers bancarios, CASA47 (antes SEPES) y entidad autonómica de suelo (Anexo A)

*En el mundo:* SAREB, servicers bancarios, CASA47 (antes SEPES) y entidad autonómica de suelo (Anexo A) — *falta en el mundo* · *Módulos:* 1

*Aspecto:* «SE VENDE» boards on several plots around town (bank-owned and public land) and a public land-agency counter for the concurso, shown in Lección 1.1 among the channels for finding land.

*Papeles:*

- Suelo en venta o concurso — M1 Dónde ir → Pliegos y precios

### Colegio de Abogados de tu provincia

*En el mundo:* Colegio de Abogados de tu provincia — *falta en el mundo* · *Módulos:* 1, 2, 8

*Aspecto:* A building with the plaque «COLEGIO DE ABOGADOS». An urban-planning lawyer joins the due diligence (Module 1) and reviews the contrato privado (Module 8). It is a partner from the module's Partners en tu provincia section, not a Dónde ir institution.

*Papeles:*

- Abogado urbanista para la due diligence — Anexo A, fase 1
- Modelo de contrato privado revisado por abogado o notario — M8 checklist

### Colegio de Economistas

*En el mundo:* Colegio de Economistas — *falta en el mundo* · *Módulos:* 2

*Aspecto:* A plaque «COLEGIO DE ECONOMISTAS» on an office building near the bank, listed among Module 2's partners.

*Papeles:*

- Modelo financiero y fiscal de la operación — Anexo A, fase 2

## El calendario de la promoción

El único plazo total del curso: **Duración típica:** 3-4 años desde la compra del suelo hasta la última escritura. (Módulo 2, Lección 2.4).

**El curso no trae el Gantt.** «Recursos descargables» anuncia «Planning tipo de una promoción | Gantt de las 12 fases» (punto 7), pero el paquete v5 no lo incluye. Este calendario está **calculado** con los plazos que da el curso: en pantalla irá marcado como orientativo, o se sustituye por el del profesor cuando lo mande.

Mes 0 = la escritura del solar. Con 24 meses de obra:

| Módulo | Del mes | Al mes | Cómo se ha calculado (en inglés, citas del curso) |
|---|---|---|---|
| 1 · Solar | -3 | 0 | derived: the course gives no time for the search itself. The slowest paper to get before buying is the Ayuntamiento's «Informe o cédula urbanística / consulta previa» («1-3 meses», Dónde ir); everything else fits inside it: nota simple «Inmediato online · ≈ 9 €», certificación de dominio y cargas «2-10 días · según arancel», certificado de IBI «1-15 días», SPE «1-3 semanas», estudio de volumetría «(1-2 semanas)». It ends at month 0 with the deed. With an option the deed moves later by the option's term («pagas una prima (3-10 %) por un plazo (6-24 meses)»), and Modules 2-4 can run inside that term (compra «sujeta a obtener licencia»). **Overlaps:** Module 2, stated: Module 1's checklist has «CRP preliminar con precio máximo del suelo (Módulo 2)». Module 8 starts at the purchase: «\| Compra del suelo \| Marca, web en construcción, captación de interesados \|». |
| 2 · Viabilidad | -3 | 1 | derived: Module 1's checklist puts the CRP before the purchase. Lección 2.5 ends with «Adquisición del solar»: the deed, then «Liquidación de impuestos: ITP o AJD con el modelo 600 de tu comunidad, en su plazo (habitualmente 30 días hábiles); IVA repercutido por el vendedor si tributa por IVA.» and «Inscripción en el Registro de la Propiedad», so the module ends about one month after the deed. Total for the whole promotion, the same lesson: «**Duración típica:** 3-4 años desde la compra del suelo hasta la última escritura.» **Overlaps:** Module 1, the same weeks. Module 5 starts here (Dónde ir: «Conversación previa de financiación» → «Condiciones orientativas y requisitos de preventas»). Repeated in Module 12 with real data: «Vuelve a calcular la Plantilla 2 con los datos reales». |
| 3 · Proyectos técnicos | 0 | 8 | derived: Lección 3.3 gives the drafting times, «**Estudio previo / anteproyecto** (2-4 semanas)», básico «(4-8 semanas)», ejecución «(6-12 semanas más)», 3 to 6 months in all, plus the visado, the OCT's D0 report and the decenal insurer's prerrevisión. The professional's calendar note in the Guía de análisis de viabilidad (extra) counts the months from the land deed: «+ 3 meses proyecto básico, licencias y tasa de licencia de obra; + 5 meses proyecto de ejecución». That gives the básico in months 0-3 and the ejecución in months 3-8. **Overlaps:** Module 4: the licence is applied for with the básico («Con él se pide la licencia en la mayoría de municipios (algunos piden el de ejecución)»). Module 5: the tender is the proyecto de ejecución («Pliego de licitación»). Module 8: «\| Proyecto básico \| Lanzamiento suave: lista de interesados, sin cobrar \|». |
| 4 · Licencias | 3 | 10 | derived: the application goes in with the básico, about month 3. The licence takes «1-6 meses según municipio y ley autonómica», so it is granted between months 4 and 9. The sectoral authorisations take 1-6 months and run alongside («Pídelas en paralelo: no esperes a que te las reclame el ayuntamiento.»). The opening of the works (Lección 4.3) ends with the acta de replanteo. The professional adds «+ 1 mes notaría y división horizontal; + 1 mes comienzo de obra y acta de replanteo», reaching «Total 10 meses aprox. desde la escritura de compraventa del suelo», so the acta is at about month 10. This stays inside the licence's start term, «El que fije la licencia o la ley autonómica (habitual: 6 meses a 1 año)». **Overlaps:** Module 3: the ejecución is drafted while the licence is processed. Module 5: «**Consejo:** pide la licencia cuando tengas la financiación encarrilada.» Module 8: «Licencia solicitada» brings the official launch and «\| Licencia concedida + avales listos \| **Contratos privados con anticipos garantizados** \|». Module 9: the obra nueva en construcción deed of Lección 4.3, about month 9. |
| 5 · Financiación y contratación | 6 | 10 | derived: the tender needs the proyecto de ejecución (months 6-8). The bank signs once it has «Licencia, proyecto de ejecución, contrato de obra, presupuesto, planning y CRP» and «**Preventas:** 30–50 % de las viviendas con contrato privado y anticipo.» The mortgage needs the obra nueva en construcción deed, at about month 9 (the professional's «+ 1 mes notaría y división horizontal»). Everything is signed before the acta de replanteo at month 10 (Lección 5.4, «Preparación del inicio de obra»). The loan then lives on: plazo «24–48 meses hasta entrega, más el período de subrogación», drawn «Por certificaciones de obra» during Module 6, and cancelled or subrogated deed by deed in Module 11. **Overlaps:** Modules 3, 4 and 8 before the works. Modules 6 and 7 for the monthly drawdowns, each checked by the bank's tasador. Module 11 for cancellation or subrogation. The bank conversation starts in Module 2. |
| 6 · Construcción | 10 | 34 | derived: the works start at the acta de replanteo (month 10) and last as many months as the works take. The course's examples are 24 months in the professional's guide («Meses de ejecución: de 1 a 24.», value 24), 20 as the Plantilla 3 default («Meses de obra (el préstamo se dispone poco a poco)») and 18 in the Lección F.2 curve («en 18 meses»). The licence's limit is «El de la licencia (habitual: 2-3 años)». The works end with the CFO and the acta de recepción, within 30 days: month 34 with 24 months of works, month 30 with 20. **Overlaps:** Module 7 all along. Module 5: the monthly drawdowns. Module 8: «Inicio de obra» («obra en marcha»), «Estructura terminada» (visits in hard hats, a second wave of sales) and «3-4 meses antes de acabados: elección de opciones.» Module 9: each installation is registered with Industria «Al terminar cada instalación», and the definitive acometidas start now («Empieza con la distribuidora en el Módulo 6.»). |
| 7 · Control de obra | 10 | 34 | course: it runs alongside the works, with monthly certification («**Cada mes:**»: measure, check against the budget, 5 % retention, the bank's tasador, payment within 60 days), weekly planning and a monthly report. The months are taken from Module 6. Cash control lasts until the end: «Mantén el control de liquidez hasta la liquidación final de la promoción.» **Overlaps:** Module 6, the same months. Module 5: each certification is checked by the «Tasador del banco» before the drawdown. Module 8: the KPI «Unidades vendidas y cobradas frente al plan» and «Coordina el calendario de entregas con el comercial.» Module 12: cash control until the final liquidation. |
| 8 · Comercialización | 0 | 38 | course: Lección 8.1's milestone table ties sales to the promotion: «Compra del suelo» (brand, website, interested buyers) → «Proyecto básico» (soft launch, no money taken) → «Licencia solicitada» (official launch, sales office) → «Licencia concedida + avales listos» (private contracts with guaranteed down payments) → «Inicio de obra» → «Estructura terminada» (second wave) → «Últimas unidades» (price or product adjustment). The 30–50 % pre-sales the bank requires come before the loan (about month 9), the rest before handover (about month 38). The months come from the other modules. Unsold «Últimas unidades» can run on to the last deed (month 48 at most). **Overlaps:** Modules 2 to 7 and 9 to 11: it runs under the whole promotion. Module 5: pre-sales for the bank, and «Línea de avales y cuenta especial operativas antes de cobrar la primera reserva». Modules 10 and 11: «1 mes antes de escriturar: fecha y documentación.» |
| 9 · Legalización | 34 | 38 | derived from the Dónde ir plazos after the CFO: «Visado del CFO» «1-2 semanas»; Industria «1-4 semanas»; energy register «1-4 semanas»; first occupation «1-3 meses (la declaración responsable es inmediata en eficacia, con comprobación posterior)»; the cédula «1-2 meses» in the 8 communities that keep it; the notary «1-3 semanas»; the Registro «15 días hábiles (prorrogables)»; the Catastro «1-6 meses» (notary and registrar report it, so it does not hold anything up). The critical path is about 2 to 4 months; the Plantilla 3 default «Meses desde el fin de obra hasta las escrituras de venta» is 4. Its first act comes at the start of the works, at about month 9: the obra nueva en construcción deed. **Overlaps:** Module 6: installation registrations, definitive acometidas, and the obra nueva en construcción deed at the start. Module 10: the snagging rounds begin 6-8 weeks before the deeds. Module 5: the definitive decenal policy after the OCT's final report. |
| 10 · Preentrega | 36 | 38 | course, counted back from the signing day: Ronda 1 «(6-8 semanas antes)», «Ronda 2 · comprador (3-4 semanas antes)», «Ronda 3 · verificación:» before the signing, the notary «3-4 semanas antes». The months come from putting the first signing at month 38. The round is repeated before each later deed, up to the last. **Overlaps:** Module 9: first occupation and the obra nueva and división horizontal are finished during the rounds. Module 8: «1 mes antes de escriturar: fecha y documentación.» Module 11: Ronda 3 and the final visit on the signing day. |
| 11 · Entrega de llaves | 38 | 48 | derived: the first deeds come once the obra nueva terminada and the división horizontal are inscribed and first occupation is granted, about month 38. The last deed falls where the course's total puts it, «3-4 años desde la compra del suelo hasta la última escritura» (months 36-48); 48 is also the length of the professional's cash-flow («Cobros y pagos de 48 meses»). The loan reaches zero with the last sale, the plusvalía is self-assessed within «30 días hábiles desde cada venta», and the community «**Nace** con la primera venta». **Overlaps:** Module 8: the last units. Module 10: before each deed. Module 12: after-sales starts with the first handovers. Module 5: the loan is cancelled or subrogated at each signing. |
| 12 · Postventa y cierre | 38 | 48 | derived: after-sales starts with the first handovers. The guarantees run from the reception (about month 34): the 5 % retention lasts «hasta un año después de la recepción» (about month 46), then come 3 years, and 10 for structure (the decenal, to about month 154), plus «2 años desde que se producen los daños» to claim. The economic, tax and company closing follows the last deed (month 48 or later): «Cancelación registral total del préstamo promotor.» (Lección 12.4, «Antes de liquidar»). The SPE often outlives it: «Muchos promotores mantienen la SPE inactiva hasta que pasan los plazos, o la reutilizan para la siguiente promoción.» **Overlaps:** Module 11: handovers continue while after-sales begins. Module 7: cash control until the final liquidation. The guarantee tail runs years beyond the 48-month bar; show it as a fading tail. |

En resumen:

- Meses −3 a 0: due diligence y viabilidad; mes 0, la escritura (con opción de compra, más tarde: «6-24 meses»).
- 0–3: anteproyecto y básico; mes 3, se pide la licencia y empieza la venta oficial.
- 3–8: proyecto de ejecución, visado, OCT; la licencia llega entre los meses 4 y 9.
- 6–10: concurso de obra, préstamo, seguros y contrato de obra.
- Mes 10: acta de replanteo; 10–34: la obra.
- Mes 34: certificado final y recepción (empiezan las garantías de 1, 3 y 10 años); 34–38: legalización y preentrega.
- Mes 38: primeras escrituras y llaves; mes 48: la última escritura (el techo de los «3-4 años»).
- Con 20 meses de obra (Plantilla 3) todo lo que va después del mes 10 se adelanta 4 meses.

Dos fuentes que no son texto de lección: la nota del profesional en la guía de viabilidad (el extra, de pago: no se cita en el mundo de un alumno que no lo ha comprado) y los valores por defecto de las Plantillas 3 y 5 («Meses de obra» 20; «Meses de pago aplazado» 18), que el administrador puede cambiar y por eso se leen al pintar, no se fijan en el código.
