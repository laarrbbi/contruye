# PLANTILLA_LIBRE · Especificación técnica para el software CONSTRUYE

> **Versión corregida (v3, 08/11/2026).** La plantilla del profesional se ha revisado celda a celda y se ha corregido. Los originales siguen intactos en `originales/` y `plantilla/plantilla_libre_original.json`.
> - Errores corregidos y cada celda cambiada: `CAMBIOS.md` (en claro) y `plantilla/correcciones.json` (antes / después).
> - Los impuestos que estaban escritos con los valores de 2002 salen ahora de una hoja nueva, **«Parámetros legales»**. Los fija el administrador y se aplican a todas las plantillas: `plantilla/parametros_legales.json` y `setParametros()` del motor.
> - La receta exacta está en `herramientas/construir_plantilla_corregida.py`: aplicada al export original, da el mismo `plantilla_libre.json`.

## 1. Contenido del paquete

| Carpeta / archivo | Qué es |
|---|---|
| `originales/PLANTILLA_LIBRE.xls` | El archivo original, byte a byte (MD5 `61de546ad0e5d84fbfd5a2ed2e652ab4`) |
| `originales/Plantilla_simplificada_Analisis_de_Viabilidad.xlsx` | El archivo simplificado original, byte a byte |
| `excel_corregido/PLANTILLA_LIBRE_corregida.xlsx` | La plantilla corregida en Excel: 18 hojas, cálculo iterativo activado, lista de comunidades en Datos de Partida D93 |
| `excel_corregido/Guia_Analisis_de_Viabilidad_corregida.xlsx` | La guía del profesional (ejemplo de 22 viviendas y sus notas) sobre la plantilla corregida |
| `originales/Plantilla_Guia_Analisis_de_Viabilidad.xlsx`, `Plantilla_calculo_precio_solar.xlsx`, `Esquema_BBDD_Gestion_de_Suelo.xlsx` | Los 3 archivos que envió el profesional el 11/11/2026, sin tocar |
| `excel_corregido/Plantilla_simplificada_corregida.xlsx` | El simplificado con «Varios» con IVA y los totales con IVA corregidos (cifras del ejemplo de 2002) |
| `plantilla/plantilla_libre.json` | **La plantilla corregida** que usa el motor: 18 hojas, 31.065 fórmulas y las 64 celdas de corte de ciclo |
| `plantilla/plantilla_libre_original.json` | El export exacto del original (17 hojas, 30.795 fórmulas), sin ningún cambio |
| `plantilla/entradas.json` | Las 275 celdas de entrada del usuario en la plantilla corregida |
| `plantilla/entradas_original.json` | Las entradas del original (incluye D133 y D154, que ahora son parámetros legales) |
| `plantilla/parametros_legales.json` | Los parámetros legales: código, celda, valor vigente, valor de 2002, base legal, y los ITP/AJD de las 19 comunidades |
| `plantilla/correcciones.json` | Registro de las 6.469 celdas cambiadas, por grupos, con el antes y el después |
| `plantilla/vista_simplificada.json` | Correspondencia entre el archivo simplificado y la hoja del modelo (420 celdas) |
| `plantillas_pequenas/` | Las 5 plantillas pequeñas del curso, los números gordos del solar (Excel + JSON) y la base de datos de suelo (Excel), con `indice.json` |
| `pruebas/escenario_guia_profesional.json` | El ejemplo de la guía del profesional, pasado a las celdas de entrada de la plantilla |
| `motor/plantillaEngine.js` | Motor de cálculo (Node.js + HyperFormula 2.7.1) |
| `motor/ejemplo.js` | Ejemplo de uso con una promoción de 24 viviendas |
| `pruebas/validar.js` | 44 pruebas automáticas: original frente a Excel y LibreOffice, corregida, parámetros legales y plantillas pequeñas |
| `herramientas/` | Los scripts que generan la plantilla corregida, las entradas, el Excel corregido y las plantillas pequeñas, y el que comprueba el Excel en LibreOffice |

## 2. Los dos archivos

### PLANTILLA_LIBRE.xls
- 17 hojas, **30.795 fórmulas**, sin macros (comprobado con olevba).
- Modelo de promoción de vivienda libre en **régimen de cooperativa**: coste por capítulos, préstamo hipotecario, gestión, forma de pago de los socios, cash-flow de 48 meses y resumen por modelo de vivienda.
- Funciones usadas: `IF`, `SUM`, `AND`, `OR`, `INT`, `DATE`, `YEAR`, `MONTH`, `DAY`, `ISTEXT`, `TODAY`. Ninguna función exótica: cualquier motor de hoja de cálculo serio las soporta.
- Origen temporal: 2002 (fechas de ejemplo, conversiones a pesetas 166,386, IVA 7 % y 16 %). En la versión corregida los impuestos salen de «Parámetros legales» y los importes en pesetas están en euros.

### Plantilla simplificada
- Es **la hoja `3ª Coste Total Promoción` del modelo completo pegada como valores**, desplazada una fila y una columna (B2 del simplificado = A1 del modelo).
- Comprobado: las 129 etiquetas coinciden en posición y los 291 números corresponden a celdas con fórmula del modelo.
- **No tiene fórmulas.** En el software, la vista simplificada se genera leyendo esa hoja del modelo completo (`vista_simplificada.json`); no hace falta programarla aparte.
- Contiene un ejemplo real del profesional: 1 vivienda de 200 m², suelo 50.000 €, coste neto 322.536,66 €.

## 3. Estructura y flujo de cálculo

| Hoja | Fórmulas | Constantes | Lee de |
|---|---|---|---|
| Datos de Partida | 255 | 230 | 2º Auxiliar · 3ª Gestión · 3ª Proyectos |
| 2º Auxiliar | 1.936 | 106 | Datos de Partida · 3ª Coste Total Promoción |
| 3ª Terrenos | 196 | 8 | Datos de Partida · 2º Auxiliar |
| 3ª Construcción | 831 | 399 | 3ª Terrenos · 2º Auxiliar · 3ª Proyectos · Datos de Partida · 3ª Licencias y Varios |
| 3ª Urbanización | 508 | 292 | 3ª Terrenos · 3ª Construcción · Datos de Partida · 2º Auxiliar |
| 3ª Proyectos | 482 | 115 | 2º Auxiliar · 3ª Construcción · 3ª Urbanización · Datos de Partida |
| Direcciones | 510 | 0 | 3ª Construcción · 3ª Proyectos · 2º Auxiliar · Datos de Partida |
| 3ª Licencias y Varios | 185 | 12 | 5º Forma de Pago · 3ª Construcción · Datos de Partida · 3ª Terrenos · 2º Auxiliar · 3ª Urbanización · 3ª Préstamo hipotecario |
| 3ª Préstamo hipotecario | 273 | 12 | 3ª Construcción · Datos de Partida · 2º Auxiliar · 3ª Coste Total Promoción · 3ª Terrenos |
| 3ª Gestión | 200 | 3 | Datos de Partida · 5º Forma de Pago · 3ª Pólizas de Crédito y resto de capítulos |
| 3ª Pólizas de Crédito | 767 | 35 | 2º Auxiliar · 3ª Terrenos · 3ª Préstamo hipotecario |
| **3ª Coste Total Promoción** | 344 | 0 | Todos los capítulos |
| Estudio Económico | 2.730 | 0 | Todos los capítulos |
| 4º Auxiliar1 | 2.953 | 6 | 2º Auxiliar · Datos de Partida · 3ª Préstamo hipotecario |
| 5º Forma de Pago | 2.200 | 118 | 4º Auxiliar1 · 3ª Gestión · 3ª Terrenos · 2º Auxiliar · 3ª Préstamo hipotecario · 3ª Coste Total Promoción |
| 6ª Cash-flow | 15.601 | 113 | Todos los capítulos · 5º Forma de Pago · Datos de Partida |
| 8º Resumen | 824 | 0 | 4º Auxiliar1 · 2º Auxiliar · 5º Forma de Pago · 3ª Coste Total Promoción |
| **Parámetros legales** (nueva) | 3 | 150 | Datos de Partida (D93, comunidad del solar) |

- **Entrada:** `Datos de Partida` (y algunas celdas de 3ª Proyectos y 3ª Pólizas de Crédito).
- **Cálculo por capítulos:** las hojas `3ª …`.
- **Salida principal:** `3ª Coste Total Promoción` (es la vista simplificada), `Estudio Económico`, `5º Forma de Pago`, `6ª Cash-flow`, `8º Resumen`.
- **Hojas auxiliares:** `2º Auxiliar`, `4º Auxiliar1`, `Direcciones`.

### Celdas de resultado más usadas (hoja `3ª Coste Total Promoción`)

| Concepto | Neto | Con IVA | IVA | % |
|---|---|---|---|---|
| Terreno | C6 | E6 | G6 | I6 |
| Construcción | C8 | E8 | G8 | I8 |
| Urbanización | C10 | E10 | G10 | I10 |
| Proyectos | C12 | E12 | G12 | I12 |
| Licencias | C14 | E14 | G14 | I14 |
| Varios | C16 | E16 | G16 | I16 |
| Gestión | C18 | E18 | G18 | I18 |
| Gastos préstamo hipotecario | C20 | E20 | G20 | I20 |
| Gastos financiación externa | C22 | E22 | G22 | I22 |
| **Total** | **C24** | **E24** | **G24** | |
| Préstamo hipotecario | C28 | | | |
| Repercusión por vivienda / por m² | C34:C44 / E34:E44 | | | |
| Coeficiente de paso | C50 | | | |

## 4. Entradas (`entradas.json`)

275 celdas en la versión corregida (salen D133 y D154, que ahora son parámetros legales; entran D93, comunidad autónoma del solar, y D102, control de calidad de la obra), agrupadas en: General, Superficies por elemento, Modelos de vivienda (24), Elementos vinculados por modelo, Suelo, Forma de pago del suelo, Construcción, Urbanización, Proyectos, Licencias y tributos, Gastos jurídicos, Acometidas, Varios, Préstamo hipotecario, Gestión, Ritmo de captación de socios (36 meses), Entradas y mensualidades, Reparto del coste por elemento, Proyectos (importes manuales) y Pólizas de crédito.

**Reglas de interpretación que el formulario del software debe respetar** (comprobadas leyendo las fórmulas):
- **Porcentajes** en tanto por uno, como en el original (0,05 = 5 %).
- **Fechas** como número de serie de Excel. El motor acepta `dd/mm/aaaa`.
- `D80`, `E86:E90`: texto exacto `SI` / `NO` (sin tilde).
- `D93`: nombre exacto de una de las 19 comunidades de `parametros_legales.json` (lista `opciones` en `entradas.json`).
- `D121`: `bloque` o `chalets`. Cambia las tarifas de OCT y seguro decenal.
- `E9:E16` es el **coste de construcción por m²** (en el original la columna se llamaba "PVP CONSTRUCCIÓN"; corregido a "COSTE CONSTRUCCIÓN").
- `D83` "API, VARIOS" es un **porcentaje sobre el precio del suelo**, no un importe.
- `D126:D131` (tasas, ICIO, primera ocupación, IAE) son **porcentajes sobre el importe de construcción**.
- `C51:E74` (m² de garaje, trastero y terraza por modelo) cuentan para el **tramo de honorarios** de proyecto (hoja 3ª Proyectos AH17).
- `D142` es € por consejero y mes; la plantilla lo multiplica por 5 consejeros y 24 meses.
- `C184` (entrada en %) **o** `D184` (entrada en cuantía fija): se rellena una de las dos.
- `D135:D139`, `D142`, `D149` y algunas de `C207:C214` traen una **fórmula por defecto** en el original. Son editables (el profesional puede escribir encima), y el diccionario las marca con `defecto_es_formula`.

## 5. Requisito crítico: referencias circulares y cálculo iterativo

- La plantilla tiene **referencias circulares intencionadas**, propias de un modelo de promoción:
  - el préstamo hipotecario es un % del coste total, y el coste total incluye los gastos del préstamo;
  - la gestión es un % del coste, y forma parte del coste;
  - el coste definitivo por elemento determina los pagos de los socios, que determinan el coste de avales y seguros, que forma parte del coste.
- El propio archivo guarda la configuración de iteración: **100 iteraciones, cambio máximo 0,001**. Pero la guarda **desactivada** (`ITERATION = 0`).
  - **Consecuencia en Excel:** con datos reales, si la iteración no está activada, Excel avisa de "referencia circular" y no calcula. El Excel corregido (`excel_corregido/`) ya la trae **activada**.
  - **LibreOffice:** con una sola pasada de recálculo no llega a converger. Con unas 40 pasadas (`herramientas/comprobar_excel_en_libreoffice.py`) da exactamente lo mismo que el motor en el Excel corregido. No se usa como motor.
- **Cómo lo resuelve el motor:**
  - Se identificaron las **64 celdas** que rompen todos los ciclos (52 en 5º Forma de Pago; el resto en 2º Auxiliar, 3ª Coste Total Promoción, 3ª Préstamo hipotecario, 3ª Gestión y 6ª Cash-flow).
  - La fórmula original de cada una se evalúa en una hoja auxiliar y su valor se copia a la celda en cada iteración, hasta que el cambio máximo baja de 0,001 o se llega a 100 iteraciones, igual que Excel.
  - El conjunto de cortes depende de la estructura de fórmulas, no de los datos. Es el mismo en todos los escenarios probados y viene guardado en `plantilla_libre.json`.
  - Converge en 16-22 iteraciones.

## 6. Calendario de Excel

- La plantilla calcula fechas incluso a partir de **fechas vacías** (día 0).
- El motor reproduce exactamente el sistema de fechas 1900 de Excel:
  - el día 1 es el 01/01/1900;
  - existe el 29/02/1900 ficticio;
  - el día 0 es el "0 de enero de 1900" (`YEAR(0)=1900`, `MONTH(0)=1`, `DAY(0)=0`).
- Sin esto, 122 celdas daban un resultado distinto de Excel. Con esto, ninguna.

## 7. Parámetros legales (hoja nueva «Parámetros legales»)

Lo que fija la ley ya no está escrito dentro de las fórmulas: sale de esta hoja. El usuario no la toca (el motor rechaza `setDatos` sobre ella); la cambia el administrador con `setParametros()` y se aplica a todas las plantillas, la grande y las 5 pequeñas, que comparten las mismas celdas.

| Código | Celda | Vigente | Original (2002) | Base legal | Dónde se usa |
|---|---|---|---|---|---|
| `IVA_GENERAL` | C5 | 21 % | 16 % | Ley 37/1992, art. 90 | Suelo con IVA, honorarios, notaría, registro, tasaciones, urbanización, control de calidad, gestión, varios, cash-flow (59 celdas) |
| `IVA_REDUCIDO` | C6 | 10 % | 7 % | Ley 37/1992, art. 91.Uno | Certificaciones de obra, cantidades a cuenta, ingresos de la cooperativa, cash-flow (43 celdas) |
| `IPS` | C7 | 8 % | 6 % | Ley 13/1996, art. 12 | Seguro decenal |
| `CLEA` | C8 | 0,15 % | 0,5 % | Estatuto del Consorcio de Compensación de Seguros | Seguro decenal y de cantidades a cuenta |
| `INTERES_LEGAL` | C9 | 3,25 % | 6 % | LOE, disp. adicional 1.ª; Ley de Presupuestos | Seguro de cantidades a cuenta |
| `AJD_PRESTAMO` | C10 | 0 % | dato (0 %) | RDL 17/2018: lo paga el banco | Préstamo hipotecario (Datos de Partida D154) |
| ITP y AJD de 19 comunidades | B15:C33 | Anexo B del curso | 8 % y 1 % | Leyes autonómicas | Compra del suelo (3ª Terrenos C34), AJD de obra nueva y división horizontal (D133) |

- La comunidad la elige el usuario en `Datos de Partida` D93. ITP y AJD aplicados: `Parámetros legales` B36 y B37.
- Para ITP progresivos se usa el tipo general (el primer tramo); la nota de cada comunidad dice el resto. Canarias (IGIC), Ceuta y Melilla (IPSI): la plantilla calcula con IVA, avisado en la nota.
- **Lo que no fija la ley sigue con los valores del original, ya en euros:** aranceles de notaría y registro (simplificación del profesional), escala de timbres (coincide con la vigente en euros), tarifas del OCT y del seguro decenal, curva de certificaciones, honorarios por tramos, captación de socios, consejo rector, auditorías, tasaciones, interés del aval del suelo, prima del seguro de cantidades a cuenta (0,7 %), tipos de las pólizas. Son de 2002 y conviene que el profesional los actualice.
- Quedan 10 fórmulas con `*166.386`: solo **muestran** el equivalente en pesetas (Datos de Partida E82, F184, F207:F212; 3ª Proyectos E1; 5º Forma de Pago D9).

## 8. Errores del original y cómo se han corregido

Detalle en `CAMBIOS.md`. Resumen:

| # | Error | Corrección | Efecto |
|---|---|---|---|
| 1 | «Varios» con IVA no sumaba las acometidas (E16, W45 y su copia Estudio Económico CQ45) | Suman lo mismo que el neto | Total con IVA del ejemplo: 354.781,50 € (antes 354.502,63 €) |
| 2 | Aval del 5º pago del suelo leía la fila 91 (3ª Terrenos J31) | Lee la fila 90 | El aval del 5º pago se calcula |
| 3 | IVA de captación usaba el tipo de gestión (3ª Gestión D66) | Usa el suyo (B66) | Sin efecto hoy (mismo tipo) |
| 4 | Cash-flow: si dos pagos con fecha caían en el mismo mes, solo contaba el primero (5.760 celdas de las tablas de fechas) | Suma todos los del mes | En 24 viviendas el original perdía 28.928 € de gastos del préstamo |
| 5 | Cash-flow: total de 4 años sin acometidas (BO104, BO211) | Las suma | Total de pagos = suma de los años |
| 6 | Cash-flow: ITP/AJD del suelo solo si el tipo era exactamente 8 % o 1 % (filas 87 y 88) | Pagan lo que calcula 3ª Terrenos D34 | Con cualquier comunidad cuadra |
| 7 | Liquidación del IVA del cash-flow enlazada a mano a 4 celdas para el calendario del ejemplo (V34, AC209, AI209, BG209) | Liquidación automática trimestral (filas 34, 209 y 259-262) | Cobros = pagos en cualquier calendario |
| 8 | #VALUE! en etiquetas (2º Auxiliar B151:B169, 4º Auxiliar1 B136:B154) y #DIV/0! en 3ª Proyectos (12 celdas) | Etiqueta del bloque y división protegida | 0 errores |
| 9 | Coeficientes construida/útil del resumen mal enlazados (8º Resumen F22, F30; 2º Auxiliar I20, I68) | Cada fila muestra el suyo | Solo presentación |
| 10b | Un pago con fecha fuera de los 48 meses del cash-flow desaparecía de la caja | Aviso nuevo en Datos de Partida E79 | El usuario ve si su calendario cuadra |
| 10 | 144 controles «O.K.» con igualdad exacta | Tolerancia de 1 céntimo (1 € donde usaban INT) | Todos en O.K. con datos coherentes |
| 11 | Control de calidad de obra fijo al 1,5 % (3ª Proyectos D53) | Dato nuevo en Datos de Partida D102 (1,5 % por defecto) | Editable |
| 12 | Etiquetas: «PVP CONSTRUCCIÓN», «CONTROL CALIDAD OBRA» en D115, errata en C116 | Corregidas | Solo texto |
| 13 | Iteración desactivada en el archivo | Activada en el Excel corregido | Excel calcula sin avisos |

## 9. Validación

`cd motor && npm install && npm test` (unos 60 s). 44 pruebas:

| Prueba | Resultado |
|---|---|
| Original vacío frente a los valores que guardó Excel | **26.858 de 26.858 celdas iguales** |
| Original, 24 viviendas, celdas fuera de los ciclos frente a LibreOffice | **24.597 de 24.597 iguales** (más 16 que muestran la fecha de hoy, `TODAY()`) |
| Original, ejemplo del profesional reconstruido | **75 de 95 importes iguales al céntimo** (el resto depende de su calendario, que no tenemos) |
| Corregida con los impuestos de 2002 frente al original | Neto e IVA de cada capítulo iguales (diferencia máxima 0,48 € en 6 M€, por pasar pesetas a euros al céntimo); el total con IVA sube exactamente las acometidas que faltaban |
| Corregida con los impuestos vigentes, 5 variantes de 24 viviendas (Valencia, Madrid sin IVA, Cataluña, 5º pago avalado, disposiciones en meses distintos) | Convergen, 0 errores, **todos los controles en O.K.**, neto + IVA = con IVA, pagos sin IVA del cash-flow = coste neto, cobros = pagos |
| Corregida, ejemplo del profesional | 0 errores; solo avisan los 4 controles del plan de pagos de los socios (su plan no lo tenemos) |
| Excel corregido abierto en LibreOffice con 24 viviendas | 0 errores, todos los controles en O.K. y **los mismos importes que el motor** |
| Parámetros legales | Cambiar IVA o AJD desde `setParametros` cambia los resultados; el usuario no puede escribirlos; valores mal escritos (21 en vez de 0,21) se rechazan |
| 5 plantillas pequeñas | 0 errores, resultados del ejemplo comprobados a mano y en LibreOffice |
| Guía del profesional | Con su ejemplo, el original da lo mismo que su Excel (5.832 celdas); sobre la corregida, 0 errores y salta el aviso de calendario (su ejemplo tiene pagos antes del mes cero) |
| Números gordos y base de datos de suelo | Mismos resultados que el original salvo el beneficio corregido; 0 errores en LibreOffice; la ficha de la base de datos funciona |

## 10. Integración en el software

- **Dónde ejecutarlo:** en el **servidor** (Node.js).
  - **Licencia de HyperFormula:** GPLv3 o comercial (Handsontable). Usarla en el servidor no distribuye el código. Si se empaqueta en el navegador de un producto cerrado, hace falta la licencia comercial.
- **Parámetros legales (iguales para todos los usuarios):**
  - Se guardan en la base de datos (una fila por código y una por comunidad, con fecha de vigencia y quién lo cambió). Los valores iniciales están en `parametros_legales.json`.
  - Antes de cada cálculo: `motor.setParametros(valoresVigentes)`. El mismo objeto sirve para la plantilla grande y para las 5 pequeñas.
  - `motor.parametrosVigentes()` devuelve lo que está usando la plantilla.
- **API mínima:**
  - `POST /plantilla/calcular` con `{ datos: { 'Datos de Partida': { C9: 24, D79: '01/01/2027', D93: 'Comunitat Valenciana', … } } }`
  - devuelve `{ calculo: { iteraciones, convergido }, resumen: motor.resumenCoste(), vista: motor.vistaSimplificada(vista), parametros: motor.parametrosVigentes() }`.
  - Las hojas completas, bajo demanda: `motor.hoja('6ª Cash-flow')`.
- **Rendimiento:** construir el motor tarda unos 3 s y calcular 2-4 s. Para varios cálculos seguidos, reutiliza la instancia: `setParametros` + `setDatos` + `calcular`.
- **Formulario:** se genera desde `entradas.json` (sección, etiqueta, tipo, unidad, nota; `opciones` para la lista de comunidades).
  - El motor **rechaza** cualquier celda que no esté en el diccionario, así que nadie puede sobrescribir una fórmula ni un parámetro legal.
- **Plantillas pequeñas:** `PlantillaEngine.desdeArchivos(dirPlantillasPequenas, 'N_nombre.json', 'N_nombre.entradas.json')`. Sin ciclos: calculan en milisegundos.
- **Vista simplificada:** `vista_simplificada.json` indica, celda a celda, qué celda del modelo va en cada posición del formato simplificado.
- **Presentación:**
  - una celda con fórmula que apunta a una vacía se muestra como 0, como en Excel (`motor.valor` ya lo hace);
  - los controles devuelven «O.K.»; si alguno no lo da, conviene avisar al usuario (suele ser un dato incoherente, por ejemplo un plan de pagos de socios que no cubre el coste).
- **Descarga en Excel:** se escriben las entradas del usuario y los parámetros vigentes sobre una copia de `excel_corregido/PLANTILLA_LIBRE_corregida.xlsx`. Las fórmulas viajan intactas y el cálculo iterativo ya va activado.
