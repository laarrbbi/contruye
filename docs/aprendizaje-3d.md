# Aprendizaje 3D — el plan y la muestra

Un mundo 3D dentro de cada lección: una ciudad española en maqueta, con las instituciones a las que
el curso manda al promotor, y en el centro **tu solar**, donde la promoción se construye módulo a
módulo. Cada paso de la lección lleva la cámara al sitio donde ocurre (el Ayuntamiento, el Registro
de la Propiedad, la notaría…), hace salir los papeles que se traen de allí y los impuestos que se
pagan, marca el checklist y enseña los partners de la lección. Al acabar el curso, el alumno ha
visto levantarse el edificio entero, de la valla con «SE VENDE» a las llaves.

El edificio es el **ejemplo del propio curso** (la plantilla del profesional «Números gordos del
solar», Módulo 1): solar de 344 m², planta baja + 5, plantas de 316,04 · 316,04 · 316,04 · 294,64 ·
229,24 · 114,49 m² que se escalonan hacia la calle, 18 viviendas de las que 2 son áticos.

## La muestra (ya funciona)

`/learn/<curso>/mundo-3d` (botón «Ver esta lección en 3D» bajo el título de cada lección del Módulo 1).

- **El Módulo 1 entero en 23 pasos**, de la ficha de producto a «solar escriturado a nombre de la
  SPE»: oficina del promotor, Ayuntamiento (quién manda, cédula urbanística, IBI), el solar
  (edificabilidad: aparece el volumen edificable), estudio de arquitectura (volumetría),
  Confederación Hidrográfica (inundación), Registro de la Propiedad (nota simple y cargas), Catastro,
  topografía en el solar, servidumbres (el techo de AESA sobre el solar), compañías suministradoras,
  geotecnia, notaría (opción de compra y firma), Registro Mercantil (la SPE), Hacienda (IVA + AJD o
  ITP). Al firmar, el vallado de CONSTRUYE rodea el solar.
- **Cada paso**: la lección y su número, el lugar, **las palabras del curso tal como están escritas**
  (con la huella invisible del lector), los papeles, los impuestos **al tipo vigente** (ITP y AJD de
  la comunidad del alumno, IVA de los parámetros legales), quién interviene, las cifras, el plazo de la
  tabla «Dónde ir», los puntos del checklist (se marcan desde aquí y se guardan), tres **partners de
  la lección en la provincia del alumno**, y los botones **Ver el vídeo** y **Abrir la lección**.
- **Contadores** arriba (paso, papeles reunidos, impuestos vistos, checklist), **línea de pasos**
  abajo agrupada por lección, **reproducción automática** al ritmo del texto y **voz** (la del
  dispositivo, en el idioma del contenido), flechas del teclado, zoom, nombres on/off, vista general.
- **La promoción entera**: la línea de los 12 módulos (los bloqueados con candado) enseña cómo queda
  el solar al terminar cada uno, y **«Ver la promoción entera»** recorre los 12 con la cámara girando:
  vallado → maqueta → planos → licencia y casetas → grúa → excavación, cimentación, estructura planta
  a planta, fachadas, cubierta, instalaciones, acabados y calle → oficina de ventas y viviendas
  vendidas → atardecer con las ventanas encendidas y la llave de latón.
- **CONSTRUYE en la obra**: el vallado lleva la marca y el logotipo trazados del original, el portal
  dice «EDIFICIO CONSTRUYE», los colores son los de la marca, el final es la llave.

### Cómo está hecho

- `web/src/components/world3d/`: three.js (ya estaba en el proyecto), sin modelos descargados: todo
  se dibuja con primitivas. `town.ts` (tablero, calles, río, vía, árboles, farolas, vecinos),
  `places.ts` (17 instituciones reconocibles, cada una con su rótulo en español), `site.ts` (el solar y
  cada fase de la obra como «pieza» que aparece desde abajo con un pequeño rebote), `worldScene.ts`
  (cámara, vuelos, luz de día y de atardecer, sombras, etiquetas, papeles y monedas que salen de las
  puertas, render solo cuando algo se mueve).
- `web/src/pages/World3DPage.tsx` y `components/world3d/hud/`: el panel del paso, las líneas de
  tiempo, la voz.
- `api/config/course_world.php`: **el guion**. Para cada módulo, qué queda en pie al terminarlo; para
  cada paso, la lección, el lugar, la cita, los papeles, impuestos, personas, cifras, plazo, checklist
  y qué se construye. `CourseWorldController` lo sirve **detrás de la puerta del módulo y con el
  contador de contenido**, como el texto de la lección.
- `tests/Feature/CourseWorldTest.php`: cada cita está **literalmente** en el módulo del curso, cada
  papel, persona, cifra, impuesto y plazo los nombra el curso, cada pieza y lugar existen en el motor
  3D, el guion de cada módulo termina donde dice que termina, y un alumno solo recibe los pasos de sus
  módulos.

## El plan, módulo a módulo

El mundo es acumulativo: cada módulo empieza donde terminó el anterior. La columna «Resultado» es la
del curso, palabra por palabra; las instituciones son las de su tabla «Dónde ir».

| Módulo | Resultado (curso) | La cámara va a | En el solar | Papeles e impuestos |
|---|---|---|---|---|
| 1 · Solar | solar escriturado a nombre de la SPE o con opción de compra firmada | Oficina del promotor, Ayuntamiento, Estudio de arquitectura, Confederación Hidrográfica, Registro de la Propiedad, Catastro, Compañías suministradoras, Notaría, Registro Mercantil, Hacienda | «SE VENDE», volumen edificable, topografía, geotecnia, acometidas, techo de AESA → vallado CONSTRUYE | Nota simple, certificación de cargas, cédula urbanística, certificado de IBI, escritura · IVA y AJD o ITP, IBI, plusvalía · **hecho** |
| 2 · Viabilidad | Cuenta de Resultado Promotor (CRP) con tres escenarios y decisión GO / NO GO | Sociedad de tasación, Banco, Estadística Registral y Notariado, Catastro (valor de referencia), Ayuntamiento (ICIO) | El edificio en maqueta blanca; los precios de la zona como chinchetas en la ciudad; GO / NO GO | Tasación, CRP · ICIO |
| 3 · Proyectos técnicos | proyecto básico y proyecto de ejecución, con estudio de seguridad y salud, geotecnia y gestión de residuos | Colegio Oficial de Arquitectos, Colegio de Aparejadores, Laboratorio de geotecnia, OCT, Aseguradora decenal | El edificio dibujado en líneas, que se traza solo; el sondeo geotécnico | Visado, estudio geotécnico, informe D0 |
| 4 · Licencias | licencia concedida, ICIO liquidado, centro de trabajo comunicado y acta de replanteo firmada | Ayuntamiento (licencia, ICIO), Confederación Hidrográfica, Costas, Carreteras, ADIF, AESA, Cultura, autoridad laboral | El cartel de la licencia en el vallado, las casetas, el replanteo marcado en el suelo | Licencia de obras, ICIO y tasa, comunicación de apertura, acta de replanteo |
| 5 · Financiación y contratación | préstamo promotor firmado, seguros contratados, cuenta especial de anticipos abierta y contrato de obra firmado | Banco, Sociedad de tasación, Corredor y Aseguradora decenal, AEAT y TGSS, Autoridad laboral, Constructora | Se monta la grúa torre, llega la constructora | Préstamo promotor, decenal, TRC, avales · AJD del préstamo |
| 6 · Construcción | obra terminada, certificado final de obra visado y acta de recepción firmada | La obra en cada lección; Distribuidora eléctrica, Empresa de aguas, Servicio de Industria, Gestor de residuos, Colegios | 6.1 excavación, cimentación y estructura planta a planta · 6.2 fachadas, carpinterías, cubierta · 6.3 instalaciones (vista en corte) · 6.4 acabados y calle · 6.5 se van grúa, casetas y vallado | Certificado final de obra, acta de recepción, registro de instalaciones |
| 7 · Control de obra | sistema de control con certificaciones, modificados firmados, informe mensual y documentación de calidad completa | Dirección facultativa, Laboratorio acreditado, OCT, Tasador del banco | La obra repasada: sellos de los ensayos sobre la estructura, certificaciones mes a mes | Certificaciones, ensayos, informes del OCT |
| 8 · Comercialización | plan comercial, documentación legal de venta, contratos privados con garantías y CRM | Agencias (COAPI), Asociación de promotores, Banco, Notaría, SEPBLAC, Consumo | Abre la oficina de ventas CONSTRUYE; las viviendas se venden una a una | Contratos privados, garantías de las cantidades a cuenta, prevención del blanqueo |
| 9 · Legalización | primera ocupación (y cédula donde se exige), escritura de obra nueva terminada y división horizontal inscritas, Libro del Edificio | Colegios, Industria, Registro de certificados energéticos, Ayuntamiento, Notaría, Registro de la Propiedad, Catastro | El edificio se parte en sus 18 viviendas (división horizontal), cada una de un color | Primera ocupación, cédula, escritura de obra nueva y DH, Libro del Edificio |
| 10 · Preentrega | hojas de repasos cerradas, documentación de entrega por vivienda y calendario de firmas | Constructor, Notaría, Banco del promotor, Bancos de los compradores, Ayuntamiento | Marcas de repasos en las viviendas, que se apagan al cerrarse | Hojas de repasos, documentación por vivienda |
| 11 · Entrega de llaves | escrituras firmadas, préstamo promotor amortizado, comunidad constituida y actas de entrega | Notaría, Registro, AEAT, Banco, Distribuidora, Aguas, Ayuntamiento, Colegio de Administradores | Cae la tarde y se encienden las casas; la llave | Escrituras, actas de entrega · plusvalía, IBI |
| 12 · Postventa y cierre | servicio postventa operativo, liquidación con el constructor, impuestos cerrados y SPE liquidada o en reserva | Constructor, Aseguradora decenal, AEAT, Registro Mercantil, Notaría, Junta Arbitral de Consumo | El edificio terminado de noche, con todas las luces y la llave de CONSTRUYE | IS, IVA, cancelaciones, extinción de la SPE |
| Financials | (el extra) | El despacho del promotor como sala de control | La promoción entera con sus números: cada capítulo de coste sobre la pieza de la obra que paga | — |

Las piezas de la obra de los módulos 2 a 12 ya están en el motor (las enseña «Ver la promoción
entera»); lo que falta para cada módulo es su **guion**: los pasos con las citas del curso, como el del
Módulo 1.

**Los borradores de todos los guiones están en [`aprendizaje-3d/`](aprendizaje-3d/README.md)**: 372
pasos, lección a lección, de los 12 módulos y de Financials. Cada paso lleva su cita del curso, el lugar
al que va la cámara, lo que se ve, los papeles, impuestos, personas y cifras, y los ítems del checklist
que marca; cada módulo, su tabla «Dónde ir», su checklist con el lugar de cada ítem, sus partners y cómo
queda el solar al empezar y al acabar. Las 372 citas están comprobadas palabra por palabra contra el
texto del módulo (el detalle de la comprobación, en el índice).

| Guion | Pasos |
|---|---|
| [Módulo 1](aprendizaje-3d/modulo-01.md) (ya hecho: la muestra) | 22 |
| [Módulo 2](aprendizaje-3d/modulo-02.md) | 29 |
| [Módulo 3](aprendizaje-3d/modulo-03.md) | 23 |
| [Módulo 4](aprendizaje-3d/modulo-04.md) | 26 |
| [Módulo 5](aprendizaje-3d/modulo-05.md) | 28 |
| [Módulo 6](aprendizaje-3d/modulo-06.md) | 29 |
| [Módulo 7](aprendizaje-3d/modulo-07.md) | 26 |
| [Módulo 8](aprendizaje-3d/modulo-08.md) | 26 |
| [Módulo 9](aprendizaje-3d/modulo-09.md) | 28 |
| [Módulo 10](aprendizaje-3d/modulo-10.md) | 18 |
| [Módulo 11](aprendizaje-3d/modulo-11.md) | 30 |
| [Módulo 12](aprendizaje-3d/modulo-12.md) | 28 |
| [Financials](aprendizaje-3d/financials.md) (el extra) | 59 |

**Lugares que faltan.** El mundo tiene 18 lugares; los guiones piden algunos más: la comunidad de
propietarios (el portal del edificio, Módulos 9, 11 y 12), la sociedad de tasación (Módulos 2, 5 y 7),
una «Delegación de la comunidad autónoma» con una puerta por organismo (Industria, registro de
certificados energéticos, cédula, Cultura, órgano ambiental, consumo, Junta Arbitral, autoridad
laboral, hacienda autonómica), el gestor de residuos (Módulos 6 y 9), la Fundación Laboral (Módulo 6),
COAPI, asociación de promotores y SEPBLAC (Módulo 8 sobre todo) y los colegios de abogados,
economistas y administradores de fincas. Lo que es online (subastas del BOE, estadística registral) va en una
pantalla de la oficina del promotor. Costas, la oficina de la cédula y la Hacienda foral solo salen en
las provincias que los tienen. La lista completa, con el aspecto de cada edificio, en el
[índice](aprendizaje-3d/README.md#los-lugares).

## La línea de tiempo

- Hoy: cada paso enseña el **plazo** que da el curso en su tabla «Dónde ir» (p. ej. «1-3 meses» la
  cédula urbanística); la línea de abajo enseña la promoción en sus 12 módulos.
- Siguiente: el **calendario de la promoción** como diagrama de barras bajo el mundo, enseñando lo
  que corre en paralelo (la venta sobre plano durante la obra, el control durante la construcción).
- **El curso no trae el Gantt**: «Recursos descargables» anuncia «Planning tipo de una promoción |
  Gantt de las 12 fases», pero el paquete v5 no lo incluye. El único plazo total es «**Duración
  típica:** 3-4 años desde la compra del suelo hasta la última escritura» (Lección 2.4). Los
  borradores traen un calendario **calculado** con los plazos del curso (mes 0 = la escritura del
  solar; obra del mes 10 al 34; primeras llaves en el 38; última escritura en el 48), módulo a módulo
  y con cómo se ha calculado cada tramo: [el calendario](aprendizaje-3d/README.md#el-calendario-de-la-promoción).
  En pantalla irá marcado como orientativo, salvo que el profesor mande su Gantt.

## La voz

- Hoy: la voz del dispositivo lee el título y la cita de cada paso (botón del altavoz), y la
  reproducción automática pasa al siguiente cuando termina.
- Siguiente: **una narración grabada por paso**, en los seis idiomas, con el guion del paso (la cita
  del curso), en `web/public/audio/3d/<idioma>/<paso>.mp3`; el reproductor ya sabe esperar a que
  termine. La voz la elige el propietario (la del profesor, o una voz sintética aprobada).

## Idiomas

La interfaz está en los seis idiomas. Las citas salen hoy en español (el curso se escribe en español y
la página lo indica); sus traducciones van con las del curso (`traducciones/`), comprobadas con
`CourseTranslation` (cada cifra y cada sigla se conserva).

## Calidad, protección y rendimiento

- Contenido de pago detrás de la puerta del módulo, con el contador y la huella; la geometría no es
  contenido del curso y va en el código de la web.
- Nada inventado: cada texto del guion es del curso (lo comprueba la batería de pruebas).
- Movimiento reducido: sin vuelos ni rebotes. Sin WebGL: un aviso y la lección de siempre.
- Teléfono: el panel del paso es una hoja inferior; controles de 40 px; el canvas no hace zoom en la
  página. Render solo cuando algo se mueve, sombras a 1024 px en pantallas táctiles.

## Fases

1. **Muestra** — hecha: el mundo, el Módulo 1 completo, la promoción entera en piezas, la entrada
   desde la lección, pruebas; y los **borradores de los guiones** de todos los módulos
   ([`aprendizaje-3d/`](aprendizaje-3d/README.md)).
2. **Guiones de los Módulos 2 a 4**: viabilidad, proyecto, licencias.
3. **Módulos 5 y 6**: la financiación y la obra, el corazón del mundo, lección a lección.
4. **Módulos 7 a 12** y el final de noche.
5. **Voz grabada** por paso en seis idiomas y traducción de los guiones.
6. **Calendario** de la promoción y pulido en teléfonos reales; un avance público del recorrido para
   la página de venta (con las piezas, nunca con el contenido de pago).

## Lo que decide el propietario

- El aspecto: colores, nivel de detalle, día o noche.
- La voz: la del profesor o una sintética, y en qué idiomas primero.
- El nombre del edificio (hoy «EDIFICIO CONSTRUYE») y si el recorrido se enseña en la página de venta.
- El calendario: pedir al profesor el «Planning tipo de una promoción» que anuncia Recursos, o enseñar
  el calculado, marcado como orientativo.
- Financials en 3D: su borrador existe (59 pasos); si se hace, solo lo ve quien ha comprado el extra.
