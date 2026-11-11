# Material del formador fuera del paquete

Plantillas que el formador ha entregado sueltas, después del paquete 4.1 (`curso/`). Se usan
**tal como llegaron**: ni una fórmula, valor o etiqueta cambiados. `curso/` sigue siendo el paquete
exacto; cuando llegue un paquete nuevo que las incluya, se borran de aquí y se sirven desde allí.

Cada archivo se ofrece en su módulo, en la sección «Plantilla», solo a quien tiene ese módulo
(`App\Support\Course\CourseFiles`, acceso `module`), o en Financials solo a quien ha comprado el
extra (acceso `financials`), y figura con su candado en las descargas del curso.
`CourseResourcesTest` comprueba que se descarga byte a byte igual al de esta carpeta.

| Archivo | Módulo | Qué es | Autor (metadatos del archivo) | Entregado | SHA-256 |
|---|---|---|---|---|---|
| `modulo_01/Esquema_BBDD_Gestion_de_Suelo.xlsx` | 1 · Solar | Los campos de una base de datos para llevar solares, propietarios, clientes y captadores (zona, clasificación y calificación del suelo, planeamiento, superficies, aprovechamiento, precio, impuestos) | Nicolás Cabañas Yuste | 2026-11-11 | `d2b7f3fca477431404261bcc15341913b8c813b1bc384326629d8f2936f3260f` |
| `modulo_01/Plantilla_calculo_precio_solar.xlsx` | 1 · Solar | «Números gordos»: precio del solar por repercusión €/m²t, costes, precio de venta y beneficio; el filtro rápido de la Lección 1.1 | Nicolás Cabañas Yuste | 2026-11-11 | `3f830fb6ba6e768875d8e6e2989585f9324ec42b23a424a277b637dbaa801996` |
| `financials/Plantilla_Guia_Analisis_de_Viabilidad.xlsx` | Financials (el extra) | La plantilla profesional en su versión original, rellena con un ejemplo (una cooperativa de 22 viviendas de 140 m²): la misma que el paquete corrige (impuestos de 2002, importes en pesetas, sin hoja «Parámetros legales»). Va con el extra y no en el Módulo 2 por decisión del propietario: en el Módulo 2 regalaría el modelo que el extra vende, sin corregir | Nicolás Cabañas Yuste (última modificación) | 2026-11-11 | `70a42bc9c02aafcc0a1b483016b4f78b3f69ee83965c143435c180680cae6a19` |
