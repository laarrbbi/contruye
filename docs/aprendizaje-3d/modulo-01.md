# Módulo 1 — Solar: búsqueda, análisis urbanístico, due diligence y adquisición

[← El plan](../aprendizaje-3d.md) · [Todos los guiones](README.md)

> **Ya guionizado.** El Módulo 1 es la muestra: su guion de verdad está en `api/config/course_world.php` (23 pasos). Este borrador queda como referencia.

| | |
|---|---|
| Duración | 120 min |
| Pasos | 22, en 4 lecciones |
| Resultado (curso) | «solar escriturado a nombre de la SPE o con opción de compra firmada.» |
| Lugares | Ayuntamiento, Catastro, Estudio de arquitectura, Hacienda, Notaría, Organismos sectoriales, Oficina del promotor, Registro Mercantil, Registro de la Propiedad, Tu solar, Compañías suministradoras |

## En la promoción

Fase 1 of the 12 real phases, the first thing in a promotion: «| 1 | Solar | Solar comprado (u opcionado) sin sorpresas |» (Introducción, «Las 12 fases»); it starts before any search («**Antes de buscar, define qué vas a promover.**»). It overlaps Module 2 before signing: «Si pasa el filtro, afina la compra con la Plantilla 1 y la viabilidad con la Plantilla 2 (Módulo 2).» and checklist «CRP preliminar con precio máximo del suelo (Módulo 2)». The promotion's clock starts at the land purchase: «**Duración típica:** 3-4 años desde la compra del suelo hasta la última escritura.» (Lección 2.4). Plazos inside the module: nota simple «Inmediato online · ≈ 9 €»; certificación de dominio y cargas «2-10 días · según arancel»; cédula urbanística «1-3 meses»; certificado de IBI «1-15 días»; estudio de volumetría «(1-2 semanas)»; SPE «1-3 semanas»; opción de compra «por un plazo (6-24 meses)». The course gives no start month or total duration for the phase itself.

## El solar

**Al empezar:** Nothing of the student's promotion exists yet: this is the first of the 12 phases. The isometric town shows streets with their services (acceso rodado, agua, saneamiento, electricidad, telecomunicaciones), the institutions as buildings (Ayuntamiento, Registro de la Propiedad, Catastro, Notaría, Registro Mercantil, Hacienda, banco, estudio de arquitectura, compañías de suministros) and, around the edges, the features that carry servidumbres (river, coast, road, railway, airport). Candidate plots sit unclaimed: suelo urbano consolidado with alineaciones fijadas, a suelo urbano no consolidado o urbanizable plot on the edge, and an old house (casa vieja) in a zone with edificabilidad sin agotar. No owner flag, no outline, no volume, no papers; the dossier and the 16-box checklist are empty.

**Al terminar:** One plot belongs to the student's SPE: «solar escriturado a nombre de la SPE o con opción de compra firmada» (the 3D shows the option, inscribed). On it: a single green outline where Registro, Catastro and the topographic survey match, with corner stakes and the SPE flag; the PGOU envelope as a wireframe (edificabilidad, altura, ocupación, retranqueos) and inside it the architect's white volumetry showing how many viviendas fit; connection points for agua, saneamiento, electricidad and telecomunicaciones at the plot edge (and a transformer kiosk if the distribuidora asked for one); a geotech borehole marker if there are sótanos; the servitude bands and the flood layer checked; charge tags from the nota simple still hanging if there are any (they are cancelled in Lesson 2.5); a «precio máximo del suelo» tag from the CRP preliminar. Nothing is built or demolished: an old building, if any, stays until the derribo of Lección 6.1. The dossier holds ficha de producto, ficha del solar, números gordos, Plantilla 1, nota simple, certificación de dominio y cargas, certificación catastral, topográfico, cédula urbanística, estudio de volumetría, certificado de deudas de IBI y tasas, geotecnia preliminar, CRP preliminar, the SPE's escritura and NIF, and the signed option (or escritura); 16/16 ticks.

## El guion, en una página

Set-up (module introduction): dawn over the isometric town, camera high. The timeline bar along the bottom shows the 12 phases of the Introducción («Las 12 fases») with «1 Solar» lit and the module's 120 min; the 16-box checklist docks on one side, the dossier (carpeta del solar) on the other. One sweep of the «Dónde ir» buildings, each lighting with its card (qué pides → qué te dan · plazo/coste): Registro de la Propiedad (nota simple, inmediato online · ≈ 9 €; certificación de dominio y cargas, 2-10 días · según arancel), Catastro (inmediato · gratuito para el titular), Ayuntamiento · Urbanismo (1-3 meses), Ayuntamiento · Gestión tributaria (1-15 días), Notaría (según arancel), Registro Mercantil + notaría + AEAT (1-3 semanas); the two online channels (Portal de Subastas del BOE; SAREB, servicers, CASA47 y entidad autonómica de suelo) float as screens above the office. Laws stay in the text, where they are linked, not in the 3D.
1.1 office → town → office: the ficha de producto fills; the map from above; services and alineaciones light up; eight channel beams; the base de datos; the números gordos gauge rejects one plot and pins the chosen one.
1.2 Ayuntamiento → plot → studio → Ayuntamiento → river: PGOU overlay; the envelope rises parameter by parameter; the white volumetry drops into it; the cédula after a 1-3 meses clock; flood layer and patrimony stamp.
1.3 Registro → Catastro → Ayuntamiento → plot → servitude bands → office → utility companies: every paper that comes back leaves a physical mark on the plot (charge chains, three outlines snapping into one, IBI receipts, borehole and water table, servitude bands, the CRP preliminar tag, connection points).
1.4 Notaría → Hacienda → office → six-stop SPE route → Notaría → Registro → plot: purchase-form cards, the who-sells tax board with the community's rate, the split bill into Plantilla 1, the SPE route (or the CIRCE shortcut), the signature in the SPE's name, the inscription, the SPE flag.
Grammar for every step: the camera flies to the step's place and eases in; the step title and its quote appear as a caption; the step's papers drop into the dossier; taxes pop as coin chips (green = se recupera, grey = no se recupera); people as small figures; partner pop-ups from config/lesson_resources.php (partner:<kind> = academy Partner category, empresas:<kind> = directory company kind); the lesson's links as small cards; the step's checklist box ticks. «Ver en el vídeo» opens the lesson video at the step's time (curso/4_videos/videos.json: CONSTRUYE_M1_L1.1 3:50, L1.2 3:02, L1.3 2:34, L1.4 3:06; mounted in the player by PreviewVideoSeeder, ids ending -v5). The step's voice can be the video's own narration cut at those times from the VTT in curso/4_videos/subtitulos: same voice, same words as the lesson. Zoom: town → plot → paper.
End card: «Resultado: solar escriturado a nombre de la SPE o con opción de compra firmada», 16/16, the student's province's Fase 1 partners from Anexo A (Registro de la Propiedad, Catastro, Ayuntamiento, Colegio Notarial, COAPI, entidad pública de suelo, Colegio de Abogados), then «Módulo 2 · Viabilidad». Story choice: the course allows a deed or an option here; the 3D shows the option, inscribed, so the deed is signed after the GO in Lesson 2.5, which is the order the course gives («viabilidad antes de comprar (Módulo 2)»).

## Dónde ir (la tabla del curso)

| Institución | En el mundo | Documento | Plazo | Para qué |
|---|---|---|---|---|
| Registro de la Propiedad (registradores.org) | Registro de la Propiedad (`registro_propiedad`) | Nota simple de la finca | Inmediato online · ≈ 9 € | Qué te dan: titular, superficie registral, cargas, servidumbres, condiciones (Lección 1.3, due diligence legal) |
| Registro de la Propiedad | Registro de la Propiedad (`registro_propiedad`) | Certificación de dominio y cargas | 2-10 días · según arancel | Documento con fe pública, pedido antes de firmar (Lección 1.3) |
| Catastro (sede.catastro.gob.es; en Navarra y País Vasco, el catastro foral) | Catastro (`catastro`) | Consulta y certificación catastral | Inmediato · gratuito para el titular | Referencia catastral, superficie, plano: contrastar Catastro–Registro–realidad (Lección 1.3); dirección de los propietarios para la búsqueda activa (Lección 1.1) |
| Ayuntamiento · Urbanismo | Ayuntamiento (`ayuntamiento`) | Informe o cédula urbanística / consulta previa | 1-3 meses | Clasificación, calificación, parámetros; si es vinculante depende de la ley autonómica (Lección 1.2) |
| Ayuntamiento · Gestión tributaria | Ayuntamiento (`ayuntamiento`) | Certificado de deudas de IBI y tasas | 1-15 días | Estado de pagos de la finca: la finca responde del IBI impagado (Lección 1.3) |
| Notaría (notariado.org) | Notaría (`notaria`) | Escritura pública | Según arancel (RD 1426/1989) | Qué pides: opción de compra, arras o compraventa (Lección 1.4) |
| Registro Mercantil + notaría + AEAT | Registro Mercantil (`registro_mercantil`) | Escritura, NIF, inscripción | 1-3 semanas | Constitución de la SPE: escritura ante notario (notaria), NIF provisional con el modelo 036 y NIF definitivo en la AEAT (hacienda), inscripción en el Registro Mercantil provincial (Lección 1.4) |
| Portal de Subastas del BOE (subastas.boe.es), subastas de AEAT y Seguridad Social | Portal de Subastas del BOE — *falta en el mundo* | Puja online | Según subasta | Qué pides: solares embargados. Canal 5 de la búsqueda (Lección 1.1); online |
| SAREB, servicers bancarios, CASA47 (antes SEPES) y entidad autonómica de suelo (Anexo A) | SAREB, servicers bancarios, CASA47 (antes SEPES) y entidad autonómica de suelo — *falta en el mundo* | Pliegos y precios | Según convocatoria | Qué pides: suelo en venta o concurso. Canales 4 y 6 de la búsqueda (Lección 1.1) |

## Impuestos, tasas y garantías

- **IVA 21 %** — Lección 1.4 · compra de terreno edificable a un empresario; se deduce

  > | Empresario vende **terreno edificable** (solar) | IVA 21 % + AJD de la escritura (Anexo B) | IVA sí, se deduce. AJD no |

- **AJD** — Lección 1.4 · con la escritura cuando la compra va con IVA (terreno edificable o renuncia a la exención); no se recupera; tipo por comunidad en el Anexo B

  > | Empresario vende **edificación usada** para derribar | Segunda entrega exenta → ITP, o renuncia a la exención: IVA + AJD (algunas CCAA tienen AJD incrementado por renuncia) | Con renuncia, IVA sí |

- **ITP** — Lección 1.4 · compra a un particular, o a un empresario de suelo rústico o de edificación usada sin renuncia a la exención; no se recupera

  > | **Particular** vende | ITP de la comunidad (Anexo B). Sin AJD | No |

- **IVA con inversión del sujeto pasivo** — Lección 1.4 · suelo rústico de un empresario que renuncia a la exención

  > | Empresario vende terreno **no edificable** (rústico) | Exento de IVA → ITP. Puede renunciar a la exención: IVA con inversión del sujeto pasivo + AJD | Con renuncia, IVA sí |

- **IVA en la permuta de solar por obra futura** — Lección 1.4 · si el solar se paga con pisos futuros

  > - **Permuta de solar por obra futura:** el propietario recibe pisos. Tributa por IVA en ambas entregas: mira bien la fiscalidad con tu asesor.

- **IVA 4 % en protegida de régimen especial** — Lección 1.1 · al definir el producto (IVA de la venta)

  > - **Tipo de promoción:** vivienda libre, protegida (régimen de tu comunidad autónoma), asequible incentivada (Ley 12/2023) o mixta. Cambia el precio máximo, el IVA (4 % en protegida de régimen especial), la financiación y el comprador.

- **IBI** — Lección 1.3 · due diligence fiscal, antes de comprar (Ayuntamiento · Gestión tributaria, 1-15 días)

  > - **IBI:** la finca responde del IBI impagado (afección real, art. 64 TRLRHL). Pide certificado de deudas.

- **Tasas y cuotas de urbanización pendientes** — Lección 1.3 · due diligence fiscal

  > - Tasas y cuotas de urbanización pendientes.

- **Cargas de urbanización pendientes (cuotas de reparcelación o juntas de compensación)** — Lección 1.3 · due diligence legal

  > - Cargas de urbanización pendientes (cuotas de reparcelación o juntas de compensación).

- **Plusvalía municipal (IIVTNU)** — Lección 1.4 · gastos de la compra; la paga el vendedor

  > - Plusvalía municipal (IIVTNU): la paga el vendedor.

- **Notaría · según arancel** — Lección 1.4 · al firmar; la matriz el vendedor y la copia el comprador, salvo pacto

  > | **Notaría** (notariado.org) | Opción de compra, arras o compraventa | Escritura pública | Según arancel (RD 1426/1989) |

- **Registro** — Lección 1.4 · al inscribir; lo paga el comprador

  > - Registro: comprador.

- **Nota simple · ≈ 9 €** — Lección 1.3 · due diligence legal

  > | **Registro de la Propiedad** (registradores.org) | Nota simple de la finca | Titular, superficie registral, cargas, servidumbres, condiciones | Inmediato online · ≈ 9 € |

- **Certificación de dominio y cargas · según arancel** — Lección 1.3 · antes de firmar

  > | **Registro de la Propiedad** | Certificación de dominio y cargas | Documento con fe pública | 2-10 días · según arancel |

- **Certificación catastral · gratuito para el titular** — Lección 1.3

  > | **Catastro** (sede.catastro.gob.es; en Navarra y País Vasco, el catastro foral) | Consulta y certificación catastral | Referencia catastral, superficie, plano | Inmediato · gratuito para el titular |

- **IS e IVA de la SPE (hacienda foral en Navarra y País Vasco)** — Lección 1.4 · al constituir la SPE

  > - En Navarra y País Vasco, el IS y el IVA los gestiona la hacienda foral.

## El checklist del módulo

| # | Ítem (curso) | Lección | En el mundo |
|---|---|---|---|
| 1 | Solar dado de alta en tu base de datos de gestión de suelo, con su propietario y su captador | 1.1 | Oficina del promotor (`promotor`) |
| 2 | Números gordos del solar hechos antes de dedicarle más tiempo | 1.1 | Oficina del promotor (`promotor`) |
| 3 | Plantilla 1 · Compra del solar (incluida) rellena con el precio, la comunidad autónoma y los gastos reales | 1.4 | Oficina del promotor (`promotor`) |
| 4 | Ficha de producto definida (tipo, público, tipologías, calidades, precio objetivo) | 1.1 | Oficina del promotor (`promotor`) |
| 5 | Nota simple y certificación de cargas actualizadas | 1.3 | Registro de la Propiedad (`registro_propiedad`) |
| 6 | Referencia catastral y topográfico coincidentes | 1.3 | Catastro (`catastro`) |
| 7 | Informe o cédula urbanística del ayuntamiento por escrito | 1.2 | Ayuntamiento (`ayuntamiento`) |
| 8 | Estudio de volumetría del arquitecto | 1.2 | Estudio de arquitectura (`estudio_arquitectura`) |
| 9 | Certificado de deudas de IBI y tasas | 1.3 | Ayuntamiento (`ayuntamiento`) |
| 10 | Geotecnia preliminar si hay sótanos | 1.3 | Tu solar (`site`) |
| 11 | Capacidad de acometidas confirmada con las compañías | 1.3 | Compañías suministradoras (`suministros`) |
| 12 | Riesgo de inundación y protección patrimonial revisados | 1.2 | Organismos sectoriales (`organismo_sectorial`) |
| 13 | Revisión de servidumbres sectoriales (cauces, costas, carreteras, patrimonio) | 1.3 | Organismos sectoriales (`organismo_sectorial`) |
| 14 | CRP preliminar con precio máximo del suelo (Módulo 2) | 1.3 | Oficina del promotor (`promotor`) |
| 15 | SPE constituida | 1.4 | Registro Mercantil (`registro_mercantil`) |
| 16 | Opción de compra o escritura firmada e inscrita | 1.4 | Notaría (`notaria`) |

## Partners

`partner:` son las categorías de partners de la academia; `empresas:` los tipos del directorio de empresas; «Anexo A» los organismos de cada provincia.

- partner:inmobiliaria (Lección 1.1)
- partner:arquitectura (Lecciones 1.2, 1.3)
- partner:abogado (Lecciones 1.3, 1.4)
- partner:notario (Lección 1.4)
- partner:asesor_fiscal (Lección 1.4)
- empresas:agencias_inmobiliarias (Lección 1.1)
- empresas:arquitectura (Lección 1.2)
- empresas:abogados (Lecciones 1.3, 1.4)
- empresas:ingenieria (Lección 1.3)
- empresas:asesoria_contable (Lección 1.4)
- Anexo A · Fase 1: Registro de la Propiedad
- Anexo A · Fase 1: Catastro
- Anexo A · Fase 1: Ayuntamiento
- Anexo A · Fase 1: Colegio Notarial
- Anexo A · Fase 1: COAPI
- Anexo A · Fase 1: entidad pública de suelo
- Anexo A · Fase 1: Colegio de Abogados de tu provincia

## Los pasos

### Lección 1.1 — Define el producto y encuentra el solar

#### 1. Define qué vas a promover: la ficha de producto

*En el mundo:* Oficina del promotor (`promotor`)

> - Resultado: una **ficha de producto** de una página.

Open on the promoter's desk; the timeline bar at the bottom lights «1 Solar». A blank one-page card slides in and fills field by field with the voice: Tipo de promoción (vivienda libre · protegida · asequible incentivada · mixta; an IVA chip «4 % en protegida de régimen especial» chip clips onto «protegida»), Público objetivo (primera vivienda, reposición, inversor, comprador extranjero, segunda residencia, as small buyer figures), número de viviendas, tipologías y superficies, calidades (garajes, trasteros, piscina, jardín, gimnasio, coworking icons), precio objetivo de venta (€/m²) and presupuesto máximo de suelo with a link chip «método residual, Módulo 2». The card stamps «ficha de producto» and drops into the dossier; checklist 4 ticks. «Ver en el vídeo»: CONSTRUYE_M1_L1.1 at 00:11.

**Papeles:** ficha de producto  
**Impuestos:** IVA  
**Personas:** inversor · comprador extranjero · el comprador  
**Cifras:** 4 % en protegida de régimen especial  
**Checklist:** 4

#### 2. Analiza la ubicación: suelo urbano consolidado, con servicios

*En el mundo:* Tu solar (`site`)

> - **Qué buscas:** suelo urbano consolidado, con servicios (acceso rodado, agua, saneamiento, electricidad) y con alineaciones fijadas.

The camera lifts from the office to a top-down view of the town; candidate plots glow. A location overlay draws comunicaciones, transporte, servicios, equipamientos (colegios, sanidad, comercio) and a soft heat layer for demanda residencial. Under one plot the four services light up in turn (acceso rodado, agua, saneamiento, electricidad) and the alineaciones draw as a white line on the street front: badge «permite pedir licencia sin urbanizar antes». A cheaper plot on the edge (suelo urbano no consolidado o urbanizable) shows «reparcelación, cesiones y urbanización · plazos de años · no es para tu primera promoción» and greys out. Vídeo: L1.1 at 00:55 («¿Qué suelo buscas?» at 01:10).

#### 3. Canales, de más a menos rentables

*En el mundo:* Oficina del promotor (`promotor`)

> - **Canales, de más a menos rentables:**

From the office eight ranked beams fan out over the town, brightest first: 1 red propia (notario, abogado, administrador de fincas and agente de suelo figures; an old house pulses where edificabilidad sin agotar); 2 búsqueda activa en el PGOU (blocks with unused edificabilidad pulse, a letter flies to the owner's address taken from the Catastro building); 3 portales (Idealista, Fotocasa sección suelo, Solvia, Servihabitat, Altamira, Haya, Aliseda as screen cards); 4 SAREB y servicers bancarios; 5 subastas (BOE, AEAT, Seguridad Social, ayuntamientos: a gavel and «Puja online»); 6 suelo público (CASA47 and the community's land entity, Anexo A); 7 fondos de inversión y entidades financieras; 8 fuera de mercado (permuta por viviendas futuras, aportación del suelo a la sociedad). The lesson's links pop beside their channel (config/lesson_resources.php '1.1'). Partner pop-up: partner:inmobiliaria + empresas:agencias_inmobiliarias. Vídeo: L1.1 at 01:33.

**Personas:** notarios (herencias) · abogados · administradores de fincas · agentes de suelo · propietarios de casas viejas

#### 4. Lleva tu cartera de suelo en una base de datos

*En el mundo:* Oficina del promotor (`promotor`)

> Usa la **Plantilla del profesional · Base de datos de gestión de suelo** (incluida): trae las listas desplegables y una ficha del solar lista para enviar por email.

Back at the desk a spreadsheet panel opens (Plantilla del profesional · Base de datos de gestión de suelo). The glowing plot is dragged into a new row: zona, clasificación, calificación, situación urbanística, superficies, edificabilidad, precio y forma de pago, propietario and «quién te lo ha traído». The owner's and the captador's avatars tie to the plot with thin lines; clientes and captadores sit in their own tabs. A «ficha del solar» card prints and flies off as an e-mail. Checklist 1 ticks. Download chip: the template on Module 1's «Plantilla» page. Vídeo: L1.1 at 02:16.

**Papeles:** Plantilla del profesional · Base de datos de gestión de suelo · ficha del solar  
**Personas:** propietario · clientes · captadores  
**Checklist:** 1

#### 5. Filtro de 10 minutos: números gordos del solar

*En el mundo:* Oficina del promotor (`promotor`)

> - Edificabilidad × precio de venta de la zona ÷ precio pedido → si el suelo pasa del 30 % de los ingresos, descártalo salvo zona prime.

At the desk the «Números gordos del solar» calculator opens (techo edificable, repercusión del suelo, coste de construcción, beneficio → precio del solar, precio de venta que necesitas, beneficio); the camera cuts to the candidate plot with a gauge above it, red line at 30 % of ingresos. The lesson video's worked example plays on the gauge (narration of CONSTRUYE_M1_L1.1, curso/4_videos/transcripciones; not in the module file): «mil seiscientos metros de techo, a dos mil quinientos euros, son cuatro millones de ingresos. Si piden un millón cuatrocientos mil euros por el solar, es un treinta y cinco por ciento. Descartado.» The needle passes 30 %, the plot flashes red and sinks back into the map; a second candidate passes and gets a pin. Two yes/no tiles flip: «¿Es vivienda libre o tiene reserva de protegida?», «¿Hay que derribar o desalojar a alguien?». Chips: «afina la compra con la Plantilla 1» and «la viabilidad con la Plantilla 2 (Módulo 2)». Checklist 2 ticks. Errores típicos card: enamorarse de la ubicación sin calcular; comprar suelo "urbanizable" pensando que es edificable; no mirar si el ayuntamiento está modificando el plan. Vídeo: L1.1 at 02:41.

**Papeles:** Plantilla del profesional · Números gordos del solar · Plantilla 1 · Plantilla 2  
**Cifras:** 10 minutos · 30 % de los ingresos  
**Checklist:** 2

### Lección 1.2 — Análisis urbanístico: qué puedes construir

#### 6. Quién manda y documentos que debes leer

*En el mundo:* Ayuntamiento (`ayuntamiento`)

> - **PGOU / PGM:** clasificación (urbano, urbanizable, rústico) y calificación (residencial, terciario, dotacional).

Camera flies to the Ayuntamiento (Urbanismo). Caption: «la comunidad autónoma hace la ley de urbanismo y el ayuntamiento aplica su PGOU y sus ordenanzas». The PGOU unrolls from the town hall (downloaded from the visor municipal o autonómico de planeamiento) and lays over the whole town as a translucent plan: first clasificación (urbano, urbanizable, rústico), then calificación (residencial, terciario, dotacional). The plot's «Ficha de zona u ordenanza» card pops; if the plot sits in an ámbito, a «plan parcial, plan especial o estudio de detalle» overlay; the «Catálogo de patrimonio» book checks any existing building (protected = cannot be demolished). Vídeo: CONSTRUYE_M1_L1.2 at 00:10.

**Papeles:** PGOU / PGM · Ficha de zona u ordenanza · Plan parcial, plan especial o estudio de detalle · Catálogo de patrimonio  
**Personas:** la comunidad autónoma · el ayuntamiento

#### 7. Parámetros que tienes que sacar

*En el mundo:* Tu solar (`site`)

> - **Edificabilidad (m²t/m²s):** m² de techo por m² de suelo. Ejemplo: 1.000 m² × 2 m²t/m²s = 2.000 m² de techo.

On the plot the regulatory envelope builds as a glowing wireframe, one parameter per beat: edificabilidad counter (the lesson's example 1.000 m² × 2 m²t/m²s = 2.000 m² de techo), altura máxima in plantas and metros (example PB+4), ocupación máxima (footprint % shaded), retranqueos a fachada, linderos y fondo (setback lines slide in), usos permitidos y compatibles (icons), plazas de aparcamiento (ghost parking slots, «habitual: 1 por vivienda o por cada 100 m²; depende de la ordenanza»), densidad máxima (a cap on the unit counter) and «qué computa»: terrazas, sótanos, áticos blink with a question mark. Vídeo: L1.2 at 00:47.

**Cifras:** 1.000 m² × 2 m²t/m²s = 2.000 m² de techo · PB+4 · 1 por vivienda o por cada 100 m²

#### 8. Estudio de volumetría del arquitecto

*En el mundo:* Estudio de arquitectura (`estudio_arquitectura`)

> 2. Pide a tu arquitecto un **estudio de volumetría** (1-2 semanas): cuántas viviendas caben de verdad.

Camera flies to the architect's studio: the architect at a table with a white massing model, a «1-2 semanas» clock running. Cut back to the plot: inside the wireframe envelope a solid white volumetry rises floor by floor while a counter shows how many viviendas really fit. This white volume stays on the plot from now on as the ghost of the future building. Partner pop-up: partner:arquitectura + empresas:arquitectura. Checklist 8 ticks. Vídeo: L1.2 at 01:48.

**Papeles:** estudio de volumetría  
**Personas:** arquitecto  
**Cifras:** 1-2 semanas  
**Checklist:** 8

#### 9. Informe o cédula urbanística por escrito

*En el mundo:* Ayuntamiento (`ayuntamiento`)

> 3. Pide al ayuntamiento el **informe o cédula urbanística** por escrito.

Back to the Ayuntamiento · Urbanismo counter: the request goes in and a «1-3 meses» clock (Dónde ir) starts on the timeline. The written «informe o cédula urbanística» comes back stamped with clasificación, calificación and parámetros and a yellow note: «Su valor vinculante depende de la ley autonómica: no lo des por vinculante sin comprobarlo». It drops into the dossier; checklist 7 ticks. Errores típicos card: calcular con la superficie catastral en vez de la topográfica; olvidar que los sótanos o áticos computan; no comprobar si hay un plan en tramitación que suspende licencias. Vídeo: L1.2 at 01:55.

**Papeles:** informe o cédula urbanística  
**Cifras:** 1-3 meses  
**Checklist:** 7

#### 10. Antes de comprar: riesgo de inundación y protección patrimonial

*En el mundo:* Organismos sectoriales (`organismo_sectorial`)

> - **Riesgo de inundación** en el visor del Sistema Nacional de Cartografía de Zonas Inundables (SNCZI, MITECO).

A blue flood layer from the SNCZI visor (MITECO) washes along the river; the plot stays dry (green) or turns blue (red flag). The patrimony stamp checks the building and its surroundings (catálogo municipal). Two more tiles flip: «instrumento de planeamiento o gestión adicional (estudio de detalle, reparcelación, proyecto de urbanización) · cada uno añade meses» and «alineaciones, patios, densidad y dotaciones exigidas». Link pop: SNCZI (config '1.2'). Checklist 12 ticks. Vídeo: L1.2 at 02:05.

**Papeles:** catálogo municipal · estudio de detalle, reparcelación, proyecto de urbanización  
**Personas:** MITECO  
**Checklist:** 12

### Lección 1.3 — Due diligence: comprar sin sorpresas

#### 11. Legal: nota simple y certificación de dominio y cargas

*En el mundo:* Registro de la Propiedad (`registro_propiedad`)

> - Nota simple → titular, cargas (hipotecas, embargos), servidumbres, condiciones resolutorias, afecciones urbanísticas.

Camera flies to the Registro de la Propiedad. A «Nota simple» prints (inmediato online · ≈ 9 €): titular, cargas (hipotecas, embargos), servidumbres, condiciones resolutorias, afecciones urbanísticas. Each item drops onto the plot as a tag: a red chain for each hipoteca or embargo, a dotted strip for each servidumbre. Then the «Certificación de dominio y cargas» with a seal («Documento con fe pública», 2-10 días · según arancel). The abogado partner pops and runs the rest of the legal list on the plot: arrendatarios u ocupantes (contratos, plazos, coste de salida), litigios urbanísticos o expedientes de disciplina abiertos, cargas de urbanización pendientes, contratos vigentes (arrendamientos, opciones de terceros, derechos de tanteo y retracto). Link pop: Registradores · nota simple. Partner pop-up: partner:abogado + empresas:abogados. Checklist 5 ticks. Vídeo: CONSTRUYE_M1_L1.3 at 00:11.

**Papeles:** Nota simple · Certificación de dominio y cargas  
**Personas:** Arrendatarios u ocupantes  
**Cifras:** Inmediato online · ≈ 9 € · 2-10 días · según arancel  
**Checklist:** 5

#### 12. Coincidencia Catastro–Registro–realidad

*En el mundo:* Catastro (`catastro`)

> - **Coincidencia Catastro–Registro–realidad.** Si no coinciden, encarga levantamiento topográfico y regulariza (procedimientos de la Ley 13/2015 de reforma hipotecaria y catastral) antes de comprar o condiciona la compra.

Camera to the Catastro: the certificación catastral returns referencia catastral, superficie y plano (inmediato · gratuito para el titular). On the plot three outlines stack: registral (from the nota simple), catastral (from the Catastro plan) and real (the levantamiento topográfico: stakes and a total station; superficie real, cotas, linderos). Where they differ the gap flashes red with two buttons, «regulariza antes de comprar» / «condiciona la compra»; when they match they snap into one green outline that stays for the rest of the course («Superficie registral, catastral y real: contrasta las tres»). Link pop: Sede Electrónica del Catastro. Checklist 6 ticks. Vídeo: L1.3 at 00:40.

**Papeles:** Consulta y certificación catastral · levantamiento topográfico  
**Cifras:** Inmediato · gratuito para el titular  
**Checklist:** 6

#### 13. Fiscal: certificado de deudas de IBI

*En el mundo:* Ayuntamiento (`ayuntamiento`)

> - **IBI:** la finca responde del IBI impagado (afección real, art. 64 TRLRHL). Pide certificado de deudas.

Camera to the Ayuntamiento · Gestión tributaria window. The «Certificado de deudas de IBI y tasas» slides out (1-15 días): the estado de pagos de la finca. Any unpaid IBI becomes a red receipt clipped to the plot («la finca responde del IBI impagado»), and so do pending tasas y cuotas de urbanización. A clean certificate burns the receipts off. Checklist 9 ticks. Vídeo: L1.3 at 00:52.

**Papeles:** certificado de deudas  
**Impuestos:** IBI · Tasas y cuotas de urbanización pendientes  
**Cifras:** 1-15 días  
**Checklist:** 9

#### 14. Técnica: topográfico, geotecnia, derribo y suelos contaminados

*En el mundo:* Tu solar (`site`)

> - **Geotecnia preliminar** si vas a hacer sótanos: el nivel freático cambia la cuenta.

On the plot a small rig drills a geotechnical borehole and a nivel freático line appears under the future basement; if the water is high the basement block of the white volumetry turns blue («el nivel freático cambia la cuenta»). If there is a building to demolish: an amianto scan glows on its roof, then «proyecto de derribo» and «gestión de residuos» cards (the derribo itself comes in Lección 6.1, «Trabajos previos»). If the plot had an antiguo uso industrial o gasolinera, a soil probe for suelos contaminados. Arbolado protegido and yacimiento arqueológico markers appear where relevant. Partner pop-up: partner:arquitectura + empresas:ingenieria. Checklist 10 ticks. Vídeo: L1.3 at 01:06.

**Papeles:** proyecto de derribo · gestión de residuos  
**Checklist:** 10

#### 15. Servidumbres de cauces, costas, carreteras, ferrocarril y aeropuertos

*En el mundo:* Organismos sectoriales (`organismo_sectorial`)

> - Servidumbres de cauces, costas, carreteras, ferrocarril y aeropuertos (servidumbres aeronáuticas, AESA).

Wide shot: protection bands extrude from each feature around town, along the river (cauces), the coastline (costas), the road (carreteras), the railway (ferrocarril), and an inverted cone over the airport (servidumbres aeronáuticas, AESA). The camera follows each band to the plot; any band that touches it lights up and pins its organism's badge (the authorisations themselves come in Module 4). The patrimony check from Lesson 1.2 joins the list. Link pop: AESA · servidumbres aeronáuticas. Checklist 13 ticks. Vídeo: L1.3 at 01:30.

**Personas:** AESA  
**Checklist:** 13

#### 16. Económica: CRP preliminar

*En el mundo:* Oficina del promotor (`promotor`)

> - **Económica:** €/m²t pagado frente a mercado, CRP preliminar (Módulo 2).

At the desk the asking price divides by the m² de techo and shows as €/m²t against a market band. A rough CRP preliminar waterfall flickers in (the full CRP is Module 2) and returns a «precio máximo del suelo» tag that floats over the plot next to the asking price. Checklist 14 ticks. Errores típicos card: fiarse solo de la nota simple antigua del vendedor; no pedir el certificado de IBI; comprar con ocupantes sin plan de salida. Vídeo: L1.3 at 01:57.

**Papeles:** CRP preliminar  
**Checklist:** 14

#### 17. Acometidas y servicios

*En el mundo:* Compañías suministradoras (`suministros`)

> - **Acometidas y servicios:** confirma con las compañías (agua, saneamiento, electricidad, telecomunicaciones) que hay capacidad y dónde está el punto de conexión.

Camera visits the utility companies (agua, saneamiento, electricidad, telecomunicaciones). Coloured lines run from the street mains to the plot's edge and end in a connection-point marker for each. If the distribuidora asks for a centro de transformación o refuerzo de red, a transformer kiosk drops at the corner with a tag «decenas de miles de euros y meses de plazo». Checklist 11 ticks. Vídeo: L1.3 at 01:39 (the video tells this before the económica part).

**Personas:** las compañías (agua, saneamiento, electricidad, telecomunicaciones) · la distribuidora  
**Cifras:** decenas de miles de euros y meses de plazo  
**Checklist:** 11

### Lección 1.4 — La compra: forma, fiscalidad y sociedad

#### 18. Formas de comprar

*En el mundo:* Notaría (`notaria`)

> - **Opción de compra:** pagas una prima (3-10 %) por un plazo (6-24 meses). Inscríbela en el Registro para que valga frente a terceros.

Camera flies to the Notaría. Five cards fan out on the notary's table: Compraventa directa · Opción de compra · Compra con condición suspensiva (p. ej., sujeta a obtener licencia; fija plazo máximo y qué pasa con lo pagado) · Arras penitenciales (te echas atrás: pierdes las arras; se echa atrás el vendedor: te devuelve el doble) · Permuta de solar por obra futura (el propietario recibe pisos; «Tributa por IVA en ambas entregas: mira bien la fiscalidad con tu asesor»). The option card lifts: a coin stack sized prima (3-10 %), a 6-24 month clock, and an arrow to the Registro de la Propiedad: «Inscríbela en el Registro para que valga frente a terceros». Fee chip from Dónde ir: «Según arancel (RD 1426/1989)». Vídeo: CONSTRUYE_M1_L1.4 at 00:11.

**Papeles:** Opción de compra · Arras penitenciales · Escritura pública  
**Impuestos:** IVA  
**Personas:** el vendedor · el propietario · tu asesor  
**Cifras:** prima (3-10 %) · plazo (6-24 meses) · el doble

#### 19. Fiscalidad de la compra del solar

*En el mundo:* Hacienda (`hacienda`)

> | Empresario vende **terreno edificable** (solar) | IVA 21 % + AJD de la escritura (Anexo B) | IVA sí, se deduce. AJD no |

Camera to Hacienda (AEAT or the regional tax office). A four-row board answers who sells and what kind of land: empresario + terreno edificable → IVA 21 % (green arrow back: se deduce) + AJD de la escritura (grey: no se recupera); empresario + no edificable (rústico) → exento de IVA → ITP, or renuncia: IVA con inversión del sujeto pasivo + AJD; empresario + edificación usada para derribar → ITP, or renuncia: IVA + AJD (algunas CCAA: AJD incrementado por renuncia); particular → ITP de la comunidad, sin AJD, no recuperable. The plot's comunidad autónoma is picked on a map of Spain and its Anexo B rate fills in (Plantilla 1 · Compra del solar: ITP o IVA + AJD por comunidad). Partner pop-up: partner:asesor_fiscal. Vídeo: L1.4 at 00:51.

**Papeles:** escritura  
**Impuestos:** IVA 21 % · AJD · ITP · IVA con inversión del sujeto pasivo · AJD incrementado por renuncia  
**Personas:** Empresario · Particular  
**Cifras:** IVA 21 %

#### 20. Gastos de la compra

*En el mundo:* Oficina del promotor (`promotor`)

> - Notaría: por ley la escritura matriz la paga el vendedor y la copia el comprador (art. 1455 CC), salvo pacto. Entre empresarios se pacta.

At the office a bill splits between two figures: vendedor (la escritura matriz de la notaría, Plusvalía municipal (IIVTNU)) and comprador (la copia, el Registro), with an «Entre empresarios se pacta» toggle. The amounts pour into the Plantilla 1 · Compra del solar panel with the price and the comunidad autónoma from the previous step; the coste del solar and the repercusión appear. Checklist 3 ticks. Vídeo: L1.4 at 01:28.

**Papeles:** la escritura matriz · la copia · Plantilla 1 · Compra del solar  
**Impuestos:** Plusvalía municipal (IIVTNU)  
**Personas:** el vendedor · el comprador  
**Checklist:** 3

#### 21. La SPE: una SL por promoción

*En el mundo:* Registro Mercantil (`registro_mercantil`)

> - Pasos: denominación (Registro Mercantil Central) → cuenta bancaria y aportación → escritura ante notario → NIF provisional (AEAT, modelo 036) → inscripción en el Registro Mercantil provincial → NIF definitivo. Vía rápida: CIRCE / PAE.

A six-stop route animates across town, one paper per stop: Registro Mercantil Central (denominación) → banco (cuenta bancaria y aportación: coins in) → Notaría (escritura ante notario) → AEAT (NIF provisional, modelo 036) → Registro Mercantil provincial (inscripción) → AEAT (NIF definitivo); a «Vía rápida: CIRCE / PAE» shortcut arc skims the route; «1-3 semanas» on the timeline. Over the new company: «Capital mínimo de la SL: 1 €», and until 3.000 € a «reserva legal del 20 % del beneficio» jar; warning «Un banco no financiará una SL sin fondos propios reales». Footnote: «En Navarra y País Vasco, el IS y el IVA los gestiona la hacienda foral». Links pop: Registro Mercantil Central · denominaciones, AEAT (modelo 036), CIRCE / PAE. Partner pop-ups: partner:notario, partner:abogado, partner:asesor_fiscal; empresas:abogados, empresas:asesoria_contable. Checklist 15 ticks. Vídeo: L1.4 at 01:42 (the six steps at 02:08).

**Papeles:** denominación · escritura · NIF provisional · modelo 036 · NIF definitivo  
**Impuestos:** IS · IVA  
**Personas:** notario · socios · Un banco  
**Cifras:** 1 € · 3.000 € · reserva legal del 20 % del beneficio · 1-3 semanas  
**Checklist:** 15

#### 22. Compra a nombre de la SPE, no a nombre personal

*En el mundo:* Notaría (`notaria`)

> **Errores típicos:** comprar a nombre personal y luego aportar a la sociedad (doble tributación); pagar ITP cuando el vendedor podía renunciar a la exención y repercutir IVA deducible.

Back at the Notaría the SPE, not the promoter in person (a crossed-out «doble tributación» ghost shows the other path), signs the chosen form: the option, or the compraventa. The signed paper flies to the Registro de la Propiedad and is inscribed. On the plot the SPE's flag is planted on the corner stake of the green outline. Second warning chip: «pagar ITP cuando el vendedor podía renunciar a la exención y repercutir IVA deducible». Checklist 16 ticks; the module closes on its result, «solar escriturado a nombre de la SPE o con opción de compra firmada». Vídeo: L1.4 at 02:34.

**Papeles:** Opción de compra · Escritura pública  
**Impuestos:** ITP · IVA deducible  
**Checklist:** 16
