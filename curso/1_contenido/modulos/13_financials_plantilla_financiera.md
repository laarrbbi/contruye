# FINANCIALS — LA PLANTILLA FINANCIERA DE LA PROMOCIÓN

> **Objetivo:** saber rellenar, leer y defender la plantilla financiera profesional del curso: coste por capítulos, préstamo, gestión, forma de pago, cash-flow y resumen.
> **Duración:** 120 min · **Herramienta:** `PLANTILLA_LIBRE.xls` y su versión simplificada, las mismas que se calculan en la plataforma · **Se usa en:** Módulos 2, 5, 7 y 12.

## De dónde sale la plantilla

- Es la **plantilla de un profesional del sector**, y se usa en el curso y en la plataforma **tal cual**, con sus fórmulas.
- Está hecha para una promoción de **vivienda libre en régimen de cooperativa**: los socios compran a coste, la gestora cobra un porcentaje de gestión y la financiación es un préstamo hipotecario que se subroga a los socios.
- **Dos archivos:**
  - **PLANTILLA_LIBRE.xls**: el modelo completo. 17 hojas y unas 31.000 fórmulas enlazadas.
  - **Plantilla simplificada de Análisis de Viabilidad**: la hoja de resumen del modelo completo ("3ª Coste Total Promoción") con un ejemplo real: 1 vivienda de 200 m².
- **Antes de abrirla en Excel:** activa el cálculo iterativo (*Archivo → Opciones → Fórmulas → Habilitar cálculo iterativo*, 100 iteraciones, cambio máximo 0,001). La plantilla tiene referencias circulares a propósito (Lección F.7) y sin iteración Excel no calcula. En la plataforma ya viene resuelto.

## Mapa de la plantilla

| Orden | Hoja | Qué hace |
|---|---|---|
| 1 | **Datos de Partida** | Todo lo que introduces: superficies, modelos de vivienda, suelo, construcción, urbanización, licencias, préstamo, gestión, socios |
| 2 | 2º Auxiliar | Cálculos intermedios: superficies y costes por elemento y por modelo |
| 3 | **3ª Terrenos** | Coste del suelo: precio, impuestos, notaría, registro, avales, timbres, API |
| 3 | **3ª Construcción** | Certificaciones mes a mes con la curva de obra, retención, OCT y seguro decenal |
| 3 | **3ª Urbanización** | Igual que construcción, para la urbanización |
| 3 | **3ª Proyectos** | Honorarios: estudios, proyectos básico y de ejecución, instalaciones, seguridad, direcciones, control de calidad |
| 3 | Direcciones | Reparto en el tiempo de las direcciones facultativas y la seguridad |
| 3 | **3ª Licencias y Varios** | Tasas, ICIO, primera ocupación, gastos jurídicos, acometidas, seguros, auditorías, avales de cantidades a cuenta |
| 3 | **3ª Préstamo hipotecario** | Disposiciones, intereses, retención y gastos de formalización |
| 3 | **3ª Gestión** | Honorarios de gestión y captación de socios |
| 3 | 3ª Pólizas de Crédito | Hasta tres pólizas de financiación externa |
| 4 | **3ª Coste Total Promoción** | Resumen del coste: neto, con IVA, IVA, %, repercusiones, coeficiente de paso. **Es la plantilla simplificada** |
| 5 | Estudio Económico | El estudio económico completo |
| 5 | 4º Auxiliar1 · **5º Forma de Pago** | Entradas, mensualidades y préstamo de cada socio |
| 6 | **6ª Cash-flow** | Cobros y pagos de 48 meses, por concepto y por año |
| 7 | **8º Resumen** | Coste, superficies, entrada, mensualidad e hipoteca por modelo de vivienda |

- **Regla de uso:** solo se rellenan los datos de partida (y, si hace falta, los importes manuales de proyectos y las pólizas). Todo lo demás se calcula.

## Lección F.1 — Rellenar los Datos de Partida

| Bloque | Celdas | Qué pones | Ojo con |
|---|---|---|---|
| Superficies por elemento | C9:E16 | Unidades, m² construidos medios y **coste de construcción por m²** de vivienda, garajes, trasteros, terrazas, locales, otros | La columna se llama "PVP CONSTRUCCIÓN" pero es **coste**, no precio de venta |
| Modelos de vivienda | B22:C45 | Hasta 24 modelos: nº de viviendas y m² útiles | La suma de viviendas debe ser igual a C9. Los m² construidos los calcula la plantilla |
| Elementos vinculados | C51:E74 | m² de garaje, trastero y terraza de cada modelo | Cuentan para el **tramo de honorarios** de proyecto |
| Suelo | D79:D83 | Mes cero, ¿lleva IVA? (SI/NO), interés del aplazamiento, precio, API y varios | **API y varios es un % del precio**, no un importe |
| Forma de pago del suelo | C86:E92 | Fechas, importes y si van avalados | Escribe SI / NO sin tilde |
| Construcción | D96:D101 | Mes de inicio, días de pago, interés de aplazamiento, retención, aval, meses de obra (1-24) | Los meses de obra eligen la curva de certificaciones |
| Urbanización | D108:D116 | Importe, inicio, pago, retención, aval, meses, control de calidad y seguridad | |
| Tipología | D121 | bloque o chalets | Cambia las tarifas del OCT y del seguro decenal |
| Licencias | D126:D131 | Tasa de cédula, calas/vallas, tasa de licencia, ICIO, primera ocupación, IAE | Todos son **% sobre el importe de construcción** |
| Gastos jurídicos | D133 | AJD de obra nueva y división horizontal | |
| Acometidas y varios | D135:D142 | Agua, luz, gas, teléfono, basuras, consejo rector | Traen fórmula por defecto; puedes escribir encima |
| Préstamo hipotecario | D146:D154 | % del préstamo, tipo, retención, primera disposición, fechas, comisión, AJD | El préstamo es un **% del coste total** |
| Gestión | D157:D159 | % de gestión, meses, inicio | |
| Socios | C163:F180 | Socios captados cada mes (36 meses) | |
| Entradas y mensualidades | C184:D186 | Entrada en % **o** en importe fijo, nº de mensualidades | Rellena una de las dos entradas, no las dos |
| Reparto del coste | C207:C214 | % del coste que va a vivienda, garaje, trastero… | Por defecto en la plantilla: vivienda 90 %, garaje 7 %, trastero 3 % |

- **Porcentajes en tanto por uno:** 0,05 = 5 %.

## Lección F.2 — Los capítulos de coste

- **3ª Terrenos:** precio + notaría y registro (aranceles por tramos) + impuesto de la compra (AJD si lleva IVA, ITP si no) + coste de avales y timbres de los pagos aplazados + API y varios.
- **3ª Construcción:** importe de construcción (superficies × coste por m²) certificado mes a mes con la **curva de obra**.
  - La curva es una tabla de % por mes para obras de 1 a 24 meses; cada columna suma 100. Por ejemplo, en 18 meses se certifica el 0,5 % el mes 1 y el 9,5 % los meses 10 a 13.
  - Suma la retención, el **OCT** (tarifa por misiones y tramos de presupuesto) y el **seguro decenal** (prima por tramos, recargos de cubiertas y fachadas, CLEA e IPS).
- **3ª Urbanización:** igual que construcción, con su propia curva y sus certificaciones de calidad y seguridad.
- **3ª Proyectos:** honorarios por **tramos de superficie**.
  - El porcentaje (11,5 % hasta 200 m², 9,5 % hasta 800 m²…) se aplica al importe de construcción.
  - Se reparte: básico 40 %, ejecución 30 %, dirección del arquitecto 30 % y dirección del aparejador 30 %.
  - Añade estudios (topográfico, geotécnico, tira de cuerdas), proyectos de instalaciones, seguridad, manual de mantenimiento, informes técnicos y control de calidad (1,5 % de la construcción).
  - Los proyectos de parcelación, compensación y urbanización se escriben a mano si los hay.
- **3ª Licencias y Varios:**
  - Tasas, ICIO y primera ocupación (% sobre construcción).
  - Gastos jurídicos (notaría, registro y AJD de obra nueva y división horizontal).
  - Acometidas, suministros, alquileres, auditorías y seguros.
  - El **seguro de las cantidades a cuenta** de los socios.
- **3ª Gestión:** % de gestión sobre el coste más la captación de socios.

## Lección F.3 — El resumen: la plantilla simplificada

La hoja "3ª Coste Total Promoción" (la plantilla simplificada) resume todo. Este es el **ejemplo real del profesional**: 1 vivienda de 200 m², suelo de 50.000 €.

| Capítulo | Neto | Con IVA | % del total |
|---|---|---|---|
| Terreno | 53.100,00 € | 61.116,00 € | 16,5 % |
| Construcción | 180.935,33 € | 193.552,31 € | 56,1 % |
| Urbanización | 0,00 € | 0,00 € | 0,0 % |
| Proyectos | 38.754,09 € | 44.954,75 € | 12,0 % |
| Licencias | 7.740,00 € | 7.740,00 € | 2,4 % |
| Varios | 18.484,05 € | 20.250,24 € | 5,7 % |
| Gestión | 15.358,89 € | 17.816,31 € | 4,8 % |
| Gastos préstamo hipotecario | 8.164,29 € | 9.073,02 € | 2,5 % |
| **Total** | **322.536,66 €** | **354.502,63 €** | **100 %** |

- **Cómo se lee cada capítulo** (columnas de detalle de la hoja):
  - **Terreno 53.100 €** = 50.000 € de suelo + 50 € de notaría + 50 € de registro + 500 € de AJD (1 %) + 2.500 € de API y varios (5 %).
  - **Construcción 180.935,33 €** = 163.400 € de importe + 8.600 € de retención + 3.606,07 € de OCT + 5.329,26 € de seguro decenal.
  - **Proyectos 38.754,09 €** = 9.015,18 € de estudios topográfico y geotécnico + 6.536 € de proyecto básico + 4.902 € de ejecución + 4.902 € + 4.902 € de direcciones + 4.597,74 € de informes técnicos + 2.580 € de control de calidad + 193,60 € de proyectos de instalaciones + 193,60 € de dirección del ingeniero de instalaciones + 865,85 € de seguridad e higiene + 66,11 € de manual de mantenimiento.
  - **Varios 18.484,05 €** = 6.696,75 € de gastos jurídicos + 240,40 € de acometidas + 48,08 € de suministros + 2.524,25 € de local de asambleas + 7.212,15 € de auditorías + 1.762,42 € de seguros.
  - **Gestión 15.358,89 €** = 13.853,89 € de gestión de la promoción + 1.505 € de captación de socios.
  - **Gastos del préstamo 8.164,29 €** = 6.969,71 € de formalización + 1.194,58 € de intereses de carencia.
- **Indicadores:**
  - **Repercusión** por vivienda: 322.536,66 €. Por m² construido: 1.612,68 €/m².
  - **Coeficiente de paso** = coste total ÷ (suelo + construcción + urbanización netos de gastos) = 322.536,66 ÷ 222.000 = **1,4529**. Por cada euro de suelo y obra hay 0,45 € de costes indirectos, gestión y financieros.
  - **Préstamo hipotecario** = 80 % del coste total = **258.029,33 €**.
- **Para comprobar tus cifras:** neto + IVA debe dar con IVA en cada capítulo. En la versión original de esta hoja, "Varios" con IVA no suma las acometidas (ver Lección F.8).

## Lección F.4 — Préstamo hipotecario y pólizas de crédito

- **Importe:** % del coste total (D146). En el ejemplo, 80 %.
- **Primera disposición:** por defecto, el 80 % del precio del suelo (D149). El resto se dispone con las certificaciones de obra.
- **Intereses:** se calculan por días sobre lo dispuesto y se pagan cada trimestre. Los **intereses de carencia** son coste de la promoción.
- **Retención (D148):** parte del préstamo que el banco retiene hasta el final de la obra.
- **Formalización:** notaría y registro (por aranceles), comisión de apertura, AJD de la garantía y tasaciones.
- **Pólizas de crédito:** hasta tres, con su límite, tipo, fechas y gastos. Sirven para cubrir los huecos de caja.

## Lección F.5 — Gestión, socios y forma de pago

- **Gestión:** el % de gestión (D157) se aplica sobre el coste y se reparte en los meses de gestión. La captación de socios se suma aparte.
- **Ritmo de captación:** cuántos socios entran cada mes (C163:F180). Determina cuándo entra el dinero.
- **Forma de pago del socio** (hoja 5º):
  - **entrada** (en % o importe fijo);
  - **mensualidades** hasta la entrega;
  - **hipoteca** subrogada en la escritura.
  - Las cantidades a cuenta se aseguran: el coste del seguro va a "Varios".
- **Resumen por modelo** (hoja 8º):
  - coste de cada modelo de vivienda con sus elementos vinculados, según el reparto de C207:C214;
  - con entrada, mensualidad e hipoteca de cada socio.

## Lección F.6 — Cash-flow y estudio económico

- **6ª Cash-flow:** 48 meses de cobros (entradas, mensualidades, préstamo, IVA) y pagos (suelo, urbanización, acometidas, proyectos, construcción, licencias, varios, gestión, financieros).
  - Totales por año y acumulado. Cada fila lleva su cuenta del plan contable.
- **Cómo leerlo:**
  - El **acumulado** no debe ser negativo. Si lo es, hace falta una póliza de crédito o más entrada de los socios.
  - Fíjate en el mes de la compra del suelo y en los meses punta de certificación.
  - Comprueba que el préstamo se dispone cuando hay obra.
- **Estudio Económico:** reúne todos los capítulos con sus importes, financieros e IVA en una sola vista.

## Lección F.7 — Por qué la plantilla necesita cálculo iterativo

- **En una promoción, algunos costes dependen del coste total:**
  - el **préstamo** es un % del coste total, y el coste incluye los gastos del préstamo;
  - la **gestión** es un % del coste, y forma parte del coste;
  - el **coste de cada vivienda** fija los pagos de los socios, que fijan el coste de su seguro, que forma parte del coste.
- **Eso son referencias circulares.** Excel las resuelve iterando: calcula, vuelve a calcular con el resultado y repite hasta que el cambio es menor de 0,001 €.
- En la plantilla, el resultado se estabiliza en unas 20 iteraciones.
- **Si Excel avisa de "referencia circular", no está mal: activa el cálculo iterativo.**

## Lección F.8 — Lo que debes revisar antes de usarla en 2026

La plantilla calcula con los parámetros con que se construyó, alrededor de 2002. Para estudiar una promoción actual, comprueba con tu asesor:

| Parámetro de la plantilla | Valor en la plantilla | Referencia actual (Módulos 2 y 11, Anexo B) |
|---|---|---|
| IVA de la obra de vivienda | 7 % | 10 % |
| IVA general (honorarios, notaría, OCT…) | 16 % | 21 % |
| Impuesto de la compra del suelo | AJD 1 % o ITP 8 % | Según tu comunidad autónoma (Anexo B) |
| Aranceles de notaría y registro, timbres, OCT, decenal, honorarios | Tablas de la época, en pesetas | Pide presupuesto actual |
| Partidas de cooperativa | Captación de socios, consejo rector, local de asambleas | No aplican a un promotor |

**Dos detalles del original que conviene conocer:**
- **"Varios" con IVA no suma las acometidas** (en el ejemplo, 278,87 €). El total con IVA correcto del ejemplo sería 354.781,50 €. El neto es correcto.
- **El quinto pago del suelo** no calcula el coste de su aval si va avalado. Los pagos 1 a 4 sí.

## Lección F.9 — De la plantilla a la decisión de un promotor

- La plantilla da el **coste total** y el **calendario de caja**. En una cooperativa, eso es el precio del socio.
- **Si eres promotor**, lleva el coste total a la **Cuenta de Resultado Promotor** del Módulo 2:
  - ventas sin IVA − coste total − comercialización − Impuesto sobre Sociedades = beneficio;
  - margen mínimo del 15 % sobre ventas;
  - método residual para el suelo.
- **Usa la plantilla en cada fase:**
  - viabilidad antes de comprar (Módulo 2);
  - presentación al banco (Módulo 5);
  - seguimiento mensual con datos reales (Módulo 7);
  - cierre y rentabilidad real (Módulo 12).

## Checklist de Financials

- [ ] Cálculo iterativo activado en Excel (en la plataforma ya viene)
- [ ] Superficies, modelos y elementos vinculados rellenos y cuadrados con la ficha de producto
- [ ] Coste de construcción por m² con presupuestos reales
- [ ] Suelo, licencias y préstamo con sus unidades correctas (importes y porcentajes)
- [ ] Calendario: fechas de compra, obra, urbanización, disposiciones y gestión
- [ ] Resumen revisado: capítulos, repercusiones, coeficiente de paso y préstamo
- [ ] Cash-flow sin acumulado negativo (o póliza prevista)
- [ ] Parámetros de la plantilla revisados para la fiscalidad actual (Lección F.8)



