# CONSTRUYE · Contenido, plantillas y textos de la web

Curso profesional de promoción de viviendas de renta libre en España. Versión 4.1, noviembre de 2026.

**Modelo del producto:** el curso incluye 5 plantillas pequeñas de cálculo (módulos 1, 2, 5, 7 y 8). La plantilla financiera profesional completa (`PLANTILLA_LIBRE`, sección Financials) es un **extra opcional de pago que se ofrece al terminar el curso**.

## 1_contenido · El curso

| Ruta | Qué es |
|---|---|
| `curso_completo.md` | El curso entero en un solo archivo, con quizzes y examen |
| `curso_sin_evaluacion.md` | El mismo contenido sin quizzes, sin examen y sin la estrategia comercial |
| `indice.json` | Orden de las secciones y su archivo |
| `modulos/00_introduccion.md` | Presentación, ficha, las 12 fases y las leyes generales |
| `modulos/01_…` a `12_…` | Los 12 módulos (51 lecciones). Cada uno con: leyes que aplican, dónde ir, lecciones, checklist y partners de la provincia. Los módulos 1, 2, 5, 7 y 8 indican su plantilla pequeña |
| `modulos/13_financials_plantilla_financiera.md` | Financials (9 lecciones): el extra del final del curso, la plantilla profesional corregida |
| `modulos/14_recursos_descargables.md` | Recursos para el alumno: 17 documentos, las 5 plantillas pequeñas y el extra |
| `modulos/15_estrategia_comercial.md` | Posicionamiento, precios y funcionamiento previsto del curso |
| `modulos/16_anexo_a_partners_por_provincia.md` | Directorio de las 52 provincias: institución o partner para cada fase |
| `modulos/17_anexo_b_fiscalidad_por_ccaa.md` | ITP, AJD, IVA, primera ocupación, cédula y ley urbanística por comunidad |
| `evaluacion/quizzes_por_modulo.json` | 13 quizzes (12 módulos + Financials), 126 preguntas, con la opción correcta marcada y la nota mínima |
| `evaluacion/examen_final.json` | Examen final: 40 preguntas, mínimo 32/40, 60 minutos |
| `datos/partners_por_provincia.json` / `.xlsx` | Los partners de cada provincia y fase en datos estructurados |
| `datos/fiscalidad_urbanismo_por_ccaa.json` | Fiscalidad y urbanismo por comunidad autónoma |
| `pdf/` | El curso maquetado en PDF, completo y sin evaluación |
| `referencia/pagina_resumen_curso.html` | Página de resumen del curso, solo como referencia visual |

- Los textos de `modulos/` son los de `curso_sin_evaluacion.md` separados por sección; los quizzes van en `evaluacion/`.
- Formato de cada pregunta: `{ numero, pregunta, opciones: [{ letra, texto, correcta }] }`.

## 2_plantilla_financiera · Las plantillas

- **Plantilla profesional corregida** (`excel_corregido/` y `plantilla/plantilla_libre.json`): la del profesional, revisada celda a celda, con los errores corregidos y los impuestos en la hoja **«Parámetros legales»**. Qué se ha cambiado: `2_plantilla_financiera/CAMBIOS.md`.
- **Parámetros legales** (`plantilla/parametros_legales.json`): IVA, ITP y AJD de las 19 comunidades, IPS, recargo del Consorcio, interés legal del dinero y AJD del préstamo. Los cambia el administrador y se aplican a todas las plantillas de todos los usuarios.
- **5 plantillas pequeñas** (`plantillas_pequenas/`): compra del solar, viabilidad y método residual, préstamo promotor, control de costes de obra, plan de ventas y cobros. Mismo motor, mismos parámetros legales.
- Los originales del profesional siguen sin tocar en `originales/`.
- Empieza por `2_plantilla_financiera/LEEME.md`. `cd 2_plantilla_financiera/motor && npm install && npm test` repite las 39 comprobaciones.

## 3_textos_web · La página principal

- `pagina_principal.md`: el texto completo de la home (SEO, hero, logros, profesor, oportunidad, programa, oferta, FAQ y cierre) y la oferta del extra de fin de curso. Lo que está entre [COMPLETAR] se deja así hasta tener el dato real.
