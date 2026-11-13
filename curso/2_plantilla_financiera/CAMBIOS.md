# Qué se ha cambiado en la plantilla de Nicolás

Versión corregida del 08/11/2026. Los archivos originales siguen intactos en `originales/`. La plantilla corregida está en `excel_corregido/PLANTILLA_LIBRE_corregida.xlsx` y, para la plataforma, en `plantilla/plantilla_libre.json`. Cada celda cambiada, con su fórmula de antes y de después, está en `plantilla/correcciones.json`.

## 1. Errores que cambiaban importes

1. **«Varios» con IVA no sumaba las acometidas.**
   - *3ª Coste Total Promoción* E16 y W45, y su copia en *Estudio Económico* CQ45.
   - Ahora suman lo mismo que el neto. En el ejemplo del simplificado, Varios con IVA pasa de 20.250,24 € a 20.529,11 €, y el total con IVA de 354.502,63 € a 354.781,50 €.
2. **El aval del 5º pago del suelo leía una fila de títulos.**
   - *3ª Terrenos* J31 leía la fila 91 de *Datos de Partida* en vez de la 90. Ahora lee la 90, como hacen los pagos 1 a 4.
3. **El IVA de captación usaba el tipo de la gestión.**
   - *3ª Gestión* D66 multiplicaba por B67 en vez de por B66. Hoy no cambia nada (los dos son IVA general), pero fallaría si algún día fueran distintos.
4. **El cash-flow perdía pagos que caían en el mismo mes.**
   - Las tablas de fechas del *6ª Cash-flow* (filas 92 a 414, 5.760 celdas) solo cogían el primer pago de cada mes. Si dos pagos caían en el mismo mes, por ejemplo la primera disposición del préstamo y la de construcción, el segundo desaparecía.
   - Ahora se suman todos los del mes. En una promoción de 24 viviendas, el original perdía 28.928 € de gastos del préstamo en el cash-flow.
5. **El total de pagos de los 4 años no sumaba las acometidas** (*6ª Cash-flow* BO104 y BO211). Ahora el total coincide con la suma de los cuatro años.
6. **El ITP y el AJD del suelo en el cash-flow solo salían si el tipo era exactamente el 8 % o el 1 %** (filas 87 y 88, y el 8 % se partía en 7 % + 1 %). Ahora pagan lo que calcula *3ª Terrenos* D34, con el tipo que sea.
7. **La liquidación del IVA del cash-flow estaba enlazada a mano** a cuatro celdas (V34, AC209, AI209, BG209), pensadas para el calendario del ejemplo. Con otro calendario, el IVA cobrado y pagado no cuadraba.
   - Ahora es automática, en las filas 259 a 262: cada trimestre (o cada mes si C256 = SI), el saldo positivo se paga el mes siguiente; el negativo se compensa y, lo que quede en diciembre, se pide en devolución y se cobra seis meses después. Lo pendiente se liquida en el último mes.

## 2. Errores visibles que no cambiaban importes

- **#VALUE!** en las etiquetas de *2º Auxiliar* B151:B169 y *4º Auxiliar1* B136:B154 (sumaba 1 a «MOD 24»). Ahora siguen el patrón de las filas de arriba.
- **#DIV/0!** en *3ª Proyectos* (12 celdas) cuando no hay datos. Ahora dan 0; con datos, el mismo resultado.
- **Coeficientes del resumen mal enlazados:** *8º Resumen* F22 (garajes) mostraba el de viviendas y F30 (no vinculados) el de locales. *2º Auxiliar* I20 usaba solo el primer modelo e I68 solo los garajes libres. Ahora cada uno muestra el suyo.
- **144 controles «O.K.»** comparaban importes con igualdad exacta y fallaban por decimales. Ahora admiten una diferencia de menos de 1 céntimo (o de 1 € donde ya usaban INT).

## 3. Etiquetas y un dato que estaba escondido

- *Datos de Partida* E7: «PVP CONSTRUCCIÓN» pasa a «COSTE CONSTRUCCIÓN»; es coste por m², no precio de venta.
- *Datos de Partida* C115: «CONTROL CALIDAD OBRA» pasa a «… URBANIZACIÓN», que es donde se usa. Errata corregida en C116 («URBANIZACCIÓN»).
- **Control de calidad de la obra:** estaba fijo al 1,5 % dentro de *3ª Proyectos* D53. Ahora es un dato en *Datos de Partida* D102 (1,5 % por defecto).
- El archivo corregido trae **el cálculo iterativo activado** (100 iteraciones, 0,001).

## 4. Impuestos de 2002 → hoja nueva «Parámetros legales»

Todo lo que fija la ley y estaba escrito dentro de las fórmulas sale ahora de una hoja nueva. CONSTRUYE la mantiene al día y, en la plataforma, se aplica sola a todas las plantillas.

| Parámetro | Antes (2002) | Ahora | Celdas que lo usan |
|---|---|---|---|
| IVA general | 16 % | 21 % | 59 celdas: suelo con IVA, honorarios, notaría, registro, tasaciones, urbanización, control de calidad, gestión, varios, cash-flow |
| IVA reducido (obra de vivienda, cantidades a cuenta) | 7 % | 10 % | 43 celdas: certificaciones, seguro de cantidades a cuenta, ingresos, cash-flow |
| ITP / AJD de la compra del suelo | 8 % / 1 % fijos | Los de la comunidad elegida en *Datos de Partida* D93 (19 comunidades) | *3ª Terrenos* C34 |
| AJD de obra nueva y división horizontal | 1 % (dato) | AJD de la comunidad | *Datos de Partida* D133 |
| AJD del préstamo hipotecario | dato (0 %) | 0 %: desde 2018 lo paga el banco | *Datos de Partida* D154 |
| Impuesto sobre las Primas de Seguros | 6 % | 8 % | Seguro decenal |
| Recargo del Consorcio (CLEA) | 0,5 % | 0,15 % | Seguro decenal y de cantidades a cuenta |
| Interés de las cantidades a cuenta | 6 % | Interés legal del dinero (3,25 %) | *3ª Licencias y Varios* K11 |
| Importes en pesetas | `N/166,386` en 102 fórmulas | El mismo importe en euros, al céntimo | Aranceles, timbres, tasas, tasaciones, honorarios fijos |

- Las cuentas cuadran: con los impuestos de 2002, la plantilla corregida da los mismos importes por capítulo que el original (diferencia máxima 0,48 € en una promoción de 6 M€, por pasar las pesetas a euros al céntimo), salvo las acometidas que faltaban.
- La escala de timbres en euros coincide con la vigente.

## 5. Las 3 plantillas que envió el 11/11/2026

Sus archivos siguen intactos en `originales/`.

- **Guía de análisis de viabilidad** (`Plantilla_Guia_Analisis_de_Viabilidad.xlsx`).
  - Es el mismo modelo de PLANTILLA_LIBRE: mismas fórmulas, con la hoja *Datos de Partida* reordenada y un ejemplo de 22 viviendas. Con su ejemplo, el modelo original da lo mismo que su Excel en 5.832 de 5.832 celdas comprobadas.
  - Va en el extra, sobre la plantilla corregida: `excel_corregido/Guia_Analisis_de_Viabilidad_corregida.xlsx`, con sus datos y sus notas como comentarios en las casillas.
  - Su ejemplo tiene **fechas que no cuadran con el cash-flow**. El suelo y la primera disposición del préstamo están en el mes cero, y los proyectos se pagan antes (la obra empieza 6 meses después de la compra; su propia nota dice unos 10). El cash-flow empieza el mes siguiente al mes cero, así que esos pagos se quedan fuera: le faltan 1,8 M€ de pagos sin IVA. En su Excel pasa lo mismo y sus controles salen en FALSO.
  - Además capta 21 socios para 22 viviendas, y el plan de pagos de los socios no cubre el coste.
  - Para que nadie vuelva a perder pagos sin enterarse, la plantilla corregida tiene un **aviso de calendario nuevo** junto al mes cero (*Datos de Partida* E79).
- **Cálculo del precio del solar («números gordos»)** → `plantillas_pequenas/numeros_gordos_solar.xlsx` (y su calculadora).
  - Los porcentajes llevaban un ×100 dentro de las fórmulas, así que se escribía 1,40 % para decir «coste × 1,4» y 1,30 % para «coste × 1,3». Ahora son 40 % y 30 %, con el mismo resultado.
  - El **beneficio industrial** era el 30 % de las ventas (599.550 € en su ejemplo). Ahora es ventas − coste: 461.193 € (un 23,1 % sobre ventas).
  - Techo residencial ahora es =C6 (estaba repetido a mano), y la edificabilidad terciaria es un coeficiente m²t/m²s sin ×100.
  - Nuevas filas: margen sobre ventas y coste total.
- **Esquema de la base de datos de gestión de suelo** → `plantillas_pequenas/base_datos_suelo.xlsx`. Hecha a partir de su esquema: hojas de solares, propietarios, clientes y captadores, sus listas desplegables y una ficha del solar para enviar por email. Las zonas de su lista eran de Castellón-Valencia-Alicante; se han dejado generales y se pueden ampliar.

## 6. Lo que necesita a Nicolás

1. **Revisar y dar el visto bueno** a estas correcciones, y autorizar la venta de la plantilla como extra del curso.
2. **Valores de mercado de 2002** que la ley no fija y siguen como en el original (ya en euros): tarifas del OCT y del seguro decenal, honorarios por tramos, tasaciones, captación de socios (1.505 € por vivienda), consejo rector, auditorías, local de asambleas, prima del seguro de cantidades a cuenta (0,7 %), interés del aval del suelo (0,75 % trimestral) y aranceles de notaría y registro simplificados. ¿Tiene valores actuales?
3. **Seguro de cantidades a cuenta** (*3ª Licencias y Varios* K15:K51): la fórmula reparte la prima en 82,85 % con recargo y 14,78 % con un 16 % que hemos tratado como IVA general. ¿Es IVA o es otro concepto? Los seguros no llevan IVA.
4. **Cooperativa o promotora:** la plantilla es de cooperativa. ¿Tiene versión para promotora con SL (precio de venta y margen)?
5. **Las fechas de su guía:** ¿ajustamos su ejemplo a su propia nota de calendario (obra unos 10 meses después de la compra del suelo y mes cero un mes antes del primer pago)? Así el cash-flow recoge todos los pagos y los controles salen en O.K.
6. **Su ejemplo del simplificado:** la hoja *Datos de Partida* rellena del ejemplo del simplificado, para cerrar la comprobación al 100 %. Con los datos reconstruidos, el plan de pagos de los socios recauda 2.260 € más que el coste y los controles del plan lo señalan.
