# PLANTILLA_LIBRE · Especificación técnica para el software CONSTRUYE

> **Regla de este paquete:** la plantilla del profesional se usa **tal cual**. No se ha cambiado ninguna fórmula, ningún parámetro ni ninguna etiqueta. El software escribe solo en las celdas de entrada y lee los resultados. Todo lo que aquí se describe como "incidencia" o "parámetro antiguo" se documenta para que el profesional decida; **no se ha corregido**.

## 1. Contenido del paquete

| Carpeta / archivo | Qué es |
|---|---|
| `originales/PLANTILLA_LIBRE.xls` | El archivo original, byte a byte (MD5 `61de546ad0e5d84fbfd5a2ed2e652ab4`) |
| `originales/Plantilla_simplificada_Analisis_de_Viabilidad.xlsx` | El archivo simplificado original, byte a byte |
| `plantilla/plantilla_libre.json` | Las 17 hojas exportadas celda a celda: 30.795 fórmulas originales, valores, formatos de número y las 64 celdas de corte de ciclo |
| `plantilla/entradas.json` | Diccionario de las 275 celdas de entrada: hoja, celda, etiqueta, tipo, unidad, valor por defecto y nota |
| `plantilla/vista_simplificada.json` | Correspondencia entre el archivo simplificado y la hoja del modelo (420 celdas) |
| `motor/plantillaEngine.js` | Motor de cálculo (Node.js + HyperFormula 2.7.1) |
| `motor/ejemplo.js` | Ejemplo de uso con una promoción de 24 viviendas |
| `pruebas/validar.js` | Pruebas automáticas frente a Excel, LibreOffice y el ejemplo del profesional |

## 2. Los dos archivos

### PLANTILLA_LIBRE.xls
- 17 hojas, **30.795 fórmulas**, sin macros (comprobado con olevba).
- Modelo de promoción de vivienda libre en **régimen de cooperativa**: coste por capítulos, préstamo hipotecario, gestión, forma de pago de los socios, cash-flow de 48 meses y resumen por modelo de vivienda.
- Funciones usadas: `IF`, `SUM`, `AND`, `OR`, `INT`, `DATE`, `YEAR`, `MONTH`, `DAY`, `ISTEXT`, `TODAY`. Ninguna función exótica: cualquier motor de hoja de cálculo serio las soporta.
- Origen temporal: 2002 (fechas de ejemplo, conversiones a pesetas 166,386, IVA 7 % y 16 %).

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

275 celdas, agrupadas en: General, Superficies por elemento, Modelos de vivienda (24), Elementos vinculados por modelo, Suelo, Forma de pago del suelo, Construcción, Urbanización, Proyectos, Licencias y tributos, Gastos jurídicos, Acometidas, Varios, Préstamo hipotecario, Gestión, Ritmo de captación de socios (36 meses), Entradas y mensualidades, Reparto del coste por elemento, Proyectos (importes manuales) y Pólizas de crédito.

**Reglas de interpretación que el formulario del software debe respetar** (comprobadas leyendo las fórmulas):
- **Porcentajes** en tanto por uno, como en el original (0,05 = 5 %).
- **Fechas** como número de serie de Excel. El motor acepta `dd/mm/aaaa`.
- `D80`, `E86:E90`: texto exacto `SI` / `NO` (sin tilde).
- `D121`: `bloque` o `chalets`. Cambia las tarifas de OCT y seguro decenal.
- `E9:E16` se llama "PVP CONSTRUCCIÓN" en el original pero es el **coste de construcción por m²**, no el precio de venta.
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
  - **Consecuencia en Excel:** con datos reales, si la iteración no está activada, Excel avisa de "referencia circular" y no calcula. Hay que activarla en *Archivo → Opciones → Fórmulas → Habilitar cálculo iterativo*. El profesional la tendría activada en su Excel.
  - **LibreOffice no sirve como motor de esta plantilla:** con la iteración activada converge a un resultado incoherente (deja el préstamo a 0 € mientras cobra sus gastos).
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

## 7. Parámetros escritos dentro del modelo (sin modificar)

Estos valores forman parte del trabajo del profesional y **siguen tal cual**. Se listan porque reflejan normativa y tarifas de 2002, y la decisión de actualizarlos es suya.

| Parámetro | Dónde | Valor en el original |
|---|---|---|
| IVA de la obra | 3ª Construcción D11; 3ª Préstamo B69; 5º Forma de Pago AX58; 6ª Cash-flow C37, D213; fórmulas `*1.07` en 3ª Licencias y Varios | 7 % |
| IVA general | 3ª Terrenos C56 y D12; 3ª Urbanización E11; 3ª Proyectos E3 y E53; 3ª Licencias C66:C71; 3ª Préstamo B72:B77; 3ª Gestión B66:B67; 5º Forma de Pago AX59; 6ª Cash-flow D37, C213 | 16 % |
| Impuesto de la compra del suelo | 3ª Terrenos C34 | AJD 1 % con IVA; ITP 8 % sin IVA |
| Conversión a pesetas | 115 fórmulas | 166,386 pts/€ |
| Aranceles de notario y registro (por tramos en pesetas) | 3ª Terrenos D32:D33; 3ª Préstamo E52:E53; 3ª Pólizas D56; 3ª Licencias D25:D31 | Tramos de 100 M pts, 0,1 % y 0,03 % |
| Timbre de letras de cambio (14 clases) | 3ª Terrenos J2:L15 | Escala en pesetas |
| Interés del aval del suelo | 3ª Terrenos J24:M24 | 0,75 % trimestral; 90 y 180 días |
| Tarifas del OCT (misiones E, I, F, OS) y del seguro decenal (básicas, cubiertas, fachadas, indexación, renuncia de recursos, CLEA 0,5 %, IPS 6 %, prima mínima 250.000 pts) | 3ª Construcción P30:AJ102 | Tablas por tramos de PEM |
| Curva de certificaciones por duración de obra (1-24 meses) | 3ª Construcción y 3ª Urbanización P2:AN27 | Tabla de %, cada columna suma 100 |
| Honorarios de proyecto por tramos de m² | 3ª Proyectos AM9:AN18 | 11,5 % hasta 200 m² … 4 % |
| Reparto de honorarios | 3ª Proyectos AJ28:AJ31 | Básico 40 %, ejecución 30 %, dirección 30 % + 30 % |
| Estudio de seguridad y control de calidad | 3ª Proyectos AI32 y D53 | 0,5034 % y 1,5 % del importe de construcción |
| Tablas de honorarios de cooperativa y coeficientes minoradores | 3ª Proyectos L4:AD34, N23:Y28 | Tablas originales |
| Captación de socios | 3ª Gestión D27… | 1.505 € por vivienda |
| Consejo rector | 3ª Licencias y Varios E55 | 5 consejeros × 24 meses |
| Auditorías, alquiler de local, suministros, tasación | 3ª Licencias y Varios, 3ª Préstamo | Importes en pesetas |
| Seguro individual de cantidades a cuenta | 3ª Licencias y Varios L4:L5, K10:K11 | 0,7 % anual; interés garantizado 6 % |
| Tipos de las 3 pólizas de crédito | 3ª Pólizas C7, K7, S7 | 6 %, 5 %, 10 % |
| Cuentas contables del cash-flow | 6ª Cash-flow columna A | Códigos del Plan General Contable (4310, 6010…) |
| Fechas de ejemplo | 5º Forma de Pago B12:B13; 6ª Cash-flow B252:B255 | 1998 |

## 8. Incidencias encontradas en el original (no corregidas)

1. **IVA de "Varios" sin acometidas.** En `3ª Coste Total Promoción`:
   - el neto de Varios (`C16`) incluye las acometidas (`'3ª Licencias y Varios'!E33`);
   - el con IVA (`E16`) no las incluye (`=G23+SUM(G47:G55)`, falta `G33`);
   - lo mismo pasa en el resumen derecho: `U45 =SUM(U31:U44)` frente a `W45 =SUM(W35:W43)`.
   - Efecto: el total con IVA se queda corto en el importe de las acometidas con IVA. En el ejemplo del profesional, 278,87 € (354.502,63 € en lugar de 354.781,50 €). En una promoción de 24 viviendas, "Varios" con IVA sale menor que sin IVA.
2. **Quinto pago del suelo.** `3ª Terrenos J31 =IF('Datos de Partida'!E91="SI",'Datos de Partida'!C91,0)` lee la fila 91, que son etiquetas, en lugar de la 90. El importe (`L31`) sí lee la fila 90. Efecto: si un quinto pago va avalado, su coste de aval no se calcula. Los pagos 1 a 4 son correctos.
3. **Iteración desactivada** en el archivo (ver punto 5).
4. **Etiquetas con error `#VALUE!`** en `2º Auxiliar` B151:B169 y `4º Auxiliar1` (numeración `"MOD 24"+1` por debajo del modelo 24). No afectan a ningún importe.
5. **Etiquetas engañosas**: "PVP CONSTRUCCIÓN" (es coste) y "CERTIFICACIÓN CONTROL CALIDAD OBRA" en `D115` (la usa la urbanización).

## 9. Validación

| Prueba | Resultado |
|---|---|
| Plantilla vacía, tal como viene, frente a los valores que guardó Excel | **26.858 de 26.858 celdas iguales** |
| Escenario de 24 viviendas: celdas fuera de los ciclos frente a LibreOffice calculando el .xls original | **24.613 de 24.613 iguales** |
| Escenario de 24 viviendas: celdas dentro de los ciclos | Convergen (cambio < 0,001) y cumplen las fórmulas del original: total = suma de capítulos; préstamo = 80 % del total |
| Ejemplo del profesional (archivo simplificado), reconstruyendo sus datos de entrada | **75 de 95 importes iguales al céntimo** |
| Tiempo de cálculo | 3 s de carga + 2-4 s de cálculo por escenario (Node.js, un núcleo) |

- **Ejemplo del profesional, lo que coincide al céntimo:** terreno, construcción (con OCT y decenal), proyectos, licencias, gastos jurídicos, acometidas, auditorías, captación de socios y la suma de suelo y obra.
- **Lo que no:** los 20 importes restantes son los intereses de carencia, el seguro de cantidades a cuenta y los totales que dependen de ellos. Dependen del calendario y del plan de pagos que usó el profesional, que no aparecen en el resumen.
- **Para cerrar la comprobación al 100 %:** basta con su hoja `Datos de Partida` rellena.
- **Cómo repetir las pruebas:** `cd motor && npm install && npm test`.

## 10. Integración en el software

- **Dónde ejecutarlo:** en el **servidor** (Node.js).
  - **Licencia de HyperFormula:** GPLv3 o comercial (Handsontable). Usarla en el servidor no distribuye el código. Si se empaqueta en el navegador de un producto cerrado, hace falta la licencia comercial.
- **API mínima:**
  - `POST /plantilla/calcular` con `{ datos: { 'Datos de Partida': { C9: 24, D79: '01/01/2027', … } } }`
  - devuelve `{ calculo: { iteraciones, convergido }, resumen: motor.resumenCoste(), vista: motor.vistaSimplificada(vista) }`.
  - Las hojas completas, bajo demanda: `motor.hoja('6ª Cash-flow')`.
- **Rendimiento:** construir el motor tarda unos 3 s. Para varios cálculos seguidos del mismo usuario, reutiliza la instancia y llama a `setDatos` + `calcular`.
- **Formulario:** se genera desde `entradas.json` (sección, etiqueta, tipo, unidad, nota).
  - El motor **rechaza** cualquier celda que no esté en el diccionario, así que nadie puede sobrescribir una fórmula del profesional por error.
- **Vista simplificada:** `vista_simplificada.json` indica, celda a celda, qué celda del modelo va en cada posición del formato simplificado.
- **Presentación:**
  - una celda con fórmula que apunta a una vacía se muestra como 0, como en Excel (`motor.valor` ya lo hace);
  - los errores se muestran como en Excel (`#VALUE!`).
- **Descarga en Excel:** si se quiere ofrecer el .xls relleno, se escriben las entradas sobre una copia del original. Las fórmulas viajan intactas y el usuario debe tener activado el cálculo iterativo.
