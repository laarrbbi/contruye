# Material del formador fuera del paquete

Aquí van las plantillas que el formador entregue sueltas, fuera de un paquete (`curso/`). Se usan
**tal como llegan**: ni una fórmula, valor o etiqueta cambiados. `curso/` sigue siendo el paquete
exacto; cuando llega un paquete nuevo que las incluye, se borran de aquí y se sirven desde allí.

Cada archivo se ofrece en su módulo, en la sección «Plantilla», solo a quien tiene ese módulo
(`App\Support\Course\CourseFiles`, acceso `module` y `source` `materiales`), o en Financials solo a
quien ha comprado el extra (acceso `financials`), y figura con su candado en las descargas del
curso. Una prueba en `CourseResourcesTest` debe comprobar que se descarga byte a byte igual al de
esta carpeta.

**Ahora no hay ninguno.** Los tres que llegaron sueltos el 11/11/2026 los trae el paquete v5:

| Llegó aquí como | Ahora, en el paquete v5 | Qué se sirve |
|---|---|---|
| `modulo_01/Esquema_BBDD_Gestion_de_Suelo.xlsx` | `curso/2_plantilla_financiera/originales/` (sin tocar) | `plantillas_pequenas/base_datos_suelo.xlsx`, la base de datos hecha a partir de su esquema (Módulo 1, descarga) |
| `modulo_01/Plantilla_calculo_precio_solar.xlsx` | `curso/2_plantilla_financiera/originales/` (sin tocar) | `plantillas_pequenas/numeros_gordos_solar.xlsx`, corregida (Módulo 1, calculadora y descarga) |
| `financials/Plantilla_Guia_Analisis_de_Viabilidad.xlsx` | `curso/2_plantilla_financiera/originales/` (sin tocar) | `excel_corregido/Guia_Analisis_de_Viabilidad_corregida.xlsx`, sobre la plantilla corregida (el extra) |

Los tres originales de `curso/…/originales/` son byte a byte los que estaban aquí (SHA-256
`d2b7f3fc…`, `3f830fb6…` y `70a42bc9…`). Qué se ha corregido en cada uno:
`curso/2_plantilla_financiera/CAMBIOS.md`, apartado 5.
