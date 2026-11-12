# Qué hay que mantener al día en las plantillas, y dónde se cambia

Análisis de las seis plantillas del paquete 4.1 (la profesional, `PLANTILLA_LIBRE`, y las cinco
pequeñas): cada valor que no calcula una fórmula ni escribe el alumno, y que se queda viejo. Hecho
celda a celda sobre `curso/2_plantilla_financiera` (sin tocarlo) y sobre lo que dice el propio
paquete en `ESPECIFICACION_TECNICA.md` §7: *«Lo que no fija la ley sigue con los valores del
original […]. Son de 2002 y conviene que el profesional los actualice.»*

## 1. Parámetros legales — ya en el admin

`/admin/templates › Parámetros legales`. Iguales para las seis plantillas, con historial.

| Qué | Ámbito |
|---|---|
| IVA general y reducido, IPS, CLEA, interés legal del dinero, AJD del préstamo | Nacional |
| ITP y AJD de la compra del suelo | **Por comunidad autónoma** (las 19) |

Son los únicos valores que cambian por comunidad en las plantillas. El ICIO, las tasas de licencia
o la plusvalía dependen del **municipio**: son entradas que escribe el alumno (sección 3).

## 2. Tarifas del original (2002) — nuevas en el admin

`/admin/templates › Valores de referencia › Tarifas del original`. Valores fijos de la plantilla
profesional: ni fórmulas ni entradas. 193 celdas, todas nacionales. Se aplican en el siguiente
cálculo y en la descarga en Excel; el valor del paquete queda al lado y a un clic.

| Grupo | Hoja y celdas | Qué es | Valor del paquete |
|---|---|---|---|
| Control técnico (OCT) | 3ª Construcción R39:S53, V39:W53, AC39:AC53 | % sobre el PEM de cada misión (E, I, F+(F+), OS, PH+TH) en 15 tramos | 1,19 % … 0,05 % |
| | Y37, AA37 | Bonificación de los paquetes de misiones | 5 %, 7 % |
| | AA51, Y52, Y53 | Globales escritos a mano en el original | 0,59 %, 0,54 %, 0,51 % |
| Seguro decenal | 3ª Construcción AI78, AI79 | Tasa básica (bloque) por debajo y por encima de 90.151.815,66 € | 0,5225 %, 0,5 % |
| | AH82, AH85 | Recargo por impermeabilización de cubiertas / fachadas | 16 %, 9 % |
| | AH87 | Indexación de la suma asegurada | 3,64 % |
| | AH90, AH93 | Renuncia a recurso (contratistas / proyectistas) | 12,5 % cada una |
| | AH98 | Franquicia máxima | 1 % |
| | AF59, AF60, AF62, AF63 | Forma de pago de la prima y del IPS | 25 % / 75 % |
| Avales | 3ª Terrenos J24 | Interés del aval del suelo (trimestral) | 0,75 % |
| | 3ª Licencias y Varios L4, K10 | Aval y seguro de las cantidades a cuenta (anual) | 0,7 %, 0,7 % |
| Honorarios por superficie | 3ª Proyectos S4:S34 (bloque), AD4:AD34 (chalets) | % según m² construidos, 31 tramos | 10 % … 3,4 % |
| Trabajos de edificación | 3ª Proyectos AM9:AM17, AN9:AN18 | Límites (m²) y % por superficie de actuación | 200 m² 11,5 % … 4 % |
| Coeficientes de honorarios | 3ª Proyectos L21, N23:N28, Y23:Y28 | Módulo básico (Mb) y coeficiente por uso | 42.333; 1,4 … 0,7 |
| | AJ28:AJ31 | Reparto de honorarios por fase | 40 / 30 / 30 / 30 % |

**Quién da los valores nuevos:** las tarifas del OCT y del seguro decenal, la aseguradora o el
OCT con que trabaje el profesional; los honorarios, el colegio de arquitectos o el propio
profesional (están liberalizados desde 1997: son referencias, no tarifas oficiales).

**Lo que no se ha puesto, a propósito:**
- La curva de certificaciones de obra (3ª Construcción y 3ª Urbanización, filas 3 a 26): es el
  método del profesional para repartir la obra en el tiempo, no un precio que caduque.
- Los códigos de cuenta de la hoja 6ª Cash-flow (4310, 6010…): son el plan contable, no valores.

## 3. Valores con que se presentan las entradas — nuevos en el admin

`/admin/templates › Valores de referencia › Valores con que se presentan las entradas`. El alumno
los encuentra escritos al abrir la plantilla y los puede cambiar; si deja uno vacío, se calcula
con este. 30 entradas:

- **Plantilla 1 · Compra del solar:** notaría y registro (0,5 % del precio), due diligence (3.000 €).
  También los usa la calculadora gratuita de la página principal.
- **Plantilla 2 · Viabilidad:** coste de construcción (1.200 €/m²), honorarios técnicos (8 %),
  licencias, ICIO y tasas (5 %), seguros y OCT (3 %), comercialización (3 %), gastos financieros
  (4 %), gastos generales (2 %).
- **Plantilla 3 · Préstamo promotor:** % financiado (70 %), tipo de interés (4,5 %), comisión de
  apertura (0,75 %).
- **Plantilla 4 · Control de costes:** retención de garantía (5 %).
- **Plantilla profesional:** control de calidad (1,5 %), certificaciones de la urbanización (1 %,
  1 %), tasa de licencia (1,5 %), ICIO (4 %), primera ocupación (1,25 %), IAE (0,5 %), préstamo
  hipotecario (interés 3,5 %, retención 15 %, comisión 0,5 %), gestión (12 %), pólizas de crédito
  (interés 6 %, 5 %, 10 % y comisión 0,5 %).

No se han puesto las entradas que son del proyecto de cada alumno (precio de venta, superficies,
modelos, calendario, margen objetivo…): no hay un valor «actual» que mantener.

## 4. Lo que no se puede cambiar desde el admin

Van **dentro de fórmulas**, convertidos de pesetas en 2002. Cambiarlos sería cambiar la fórmula, y
la plantilla es del profesional: llegan con un paquete nuevo.

| Qué | Dónde |
|---|---|
| Notaría y registro de la compra del suelo, de la obra nueva y división horizontal, del préstamo y de las pólizas (simplificación del profesional: un tipo por mil hasta 601.012,10 €, otro por encima, con mínimo) | 3ª Terrenos D32:D33; 3ª Licencias y Varios D27, D31; 3ª Préstamo hipotecario E52:E53; 3ª Pólizas de Crédito D56, L56, T56 |
| Notaría de la cooperativa: constitución (540,91 €) y acuerdos sociales (1.803,04 €) | 3ª Licencias y Varios D25, D29 |
| Gastos de la cooperativa: suministros (48,08 € al mes), alquiler del local de asambleas (360,61 € × 7), auditorías (2.404,05 € × 3) | 3ª Licencias y Varios E47, E49, E51 |
| Honorarios fijos de proyecto: tira de cuerdas y estudio topográfico (901,52 € cada uno), estudio geotécnico (7.212,15 €), y €/vivienda de las instalaciones (electricidad 33,13; fontanería 64,91; calefacción 59,50; telecomunicaciones 36,06; dirección de ingeniería 193,60…) | 3ª Proyectos AI38:AI40, AH45:AH59, AI61 |
| Límites de los tramos del OCT y del seguro decenal | 3ª Construcción Q38:Q53, AG78:AH79, AH100:AI102 |
| Prima mínima del seguro decenal (250.000 pesetas, 1.502,53 €) | 3ª Construcción AH74 |
| Gastos por letra avalada (30,05 €) | 3ª Terrenos J20 |

La escala de timbres, según el paquete, coincide con la vigente en euros: no hace falta tocarla.

Conviene pedírselo al profesional junto con su revisión de la plantilla.

## Cómo funciona

- La lista de celdas está en `financials/valores_de_referencia.json`. Sirve a la vez de lista
  blanca: el servicio de cálculo (`financials/calculadora.js`) solo escribe esas celdas, rechaza
  cualquier otra y, antes de cada cálculo, las devuelve al valor del paquete. `curso/` no se toca:
  sus 39 comprobaciones siguen pasando.
- Los valores del admin se guardan en `template_reference_values` (solo los cambiados) y cada
  cambio en `template_reference_changes`, con quién y cuándo.
- Pruebas: `financials/test/calculadora.test.js` (las tarifas cambian el resultado y no se
  quedan para el cálculo siguiente; ninguna fórmula ni entrada se puede escribir),
  `api/tests/Feature/TemplatesTest.php` (admin, historial, cálculo, Excel) y
  `SolarCalculatorTest` (la calculadora gratuita parte de los valores del admin).
