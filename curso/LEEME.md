# CONSTRUYE · Contenido del curso y plantilla financiera

Curso profesional de promoción de viviendas de renta libre en España. Versión 4.0, octubre de 2026.

## 1_contenido · El curso

| Ruta | Qué es |
|---|---|
| `curso_completo.md` | El curso entero en un solo archivo, con quizzes y examen |
| `curso_sin_evaluacion.md` | El mismo contenido sin quizzes, sin examen y sin la estrategia comercial |
| `indice.json` | Orden de las secciones y su archivo |
| `modulos/00_introduccion.md` | Presentación, ficha, las 12 fases y las leyes generales |
| `modulos/01_…` a `12_…` | Los 12 módulos (51 lecciones). Cada uno con: leyes que aplican, dónde ir, lecciones, checklist y partners de la provincia |
| `modulos/13_financials_plantilla_financiera.md` | Sección Financials (9 lecciones): cómo usar la plantilla financiera de `2_plantilla_financiera` |
| `modulos/14_recursos_descargables.md` | Lista de recursos para el alumno |
| `modulos/15_estrategia_comercial.md` | Posicionamiento, precios (297 / 597 / 997 / 2.497 €) y funcionamiento previsto del curso |
| `modulos/16_anexo_a_partners_por_provincia.md` | Directorio de las 52 provincias: institución o partner para cada fase |
| `modulos/17_anexo_b_fiscalidad_por_ccaa.md` | ITP, AJD, IVA, primera ocupación, cédula y ley urbanística por comunidad |
| `evaluacion/quizzes_por_modulo.json` | 13 quizzes (12 módulos + Financials), 126 preguntas, con la opción correcta marcada y la nota mínima |
| `evaluacion/examen_final.json` | Examen final: 40 preguntas, mínimo 32/40, 60 minutos |
| `datos/partners_por_provincia.json` / `.xlsx` | Los partners de cada provincia y fase en datos estructurados |
| `datos/fiscalidad_urbanismo_por_ccaa.json` | Fiscalidad y urbanismo por comunidad autónoma |
| `pdf/` | El curso maquetado en PDF, completo y sin evaluación |
| `referencia/pagina_resumen_curso.html` | Página de resumen del curso, solo como referencia visual |

- Los textos de `modulos/` son exactamente los de `curso_completo.md`, separados por sección y sin los quizzes, que van en `evaluacion/`.
- Formato de cada pregunta: `{ numero, pregunta, opciones: [{ letra, texto, correcta }] }`.

## 2_plantilla_financiera · La plantilla del profesional

La plantilla financiera del curso (`PLANTILLA_LIBRE.xls` y su versión simplificada) con su motor de cálculo.

- **Se usa tal cual:** no se cambia ninguna fórmula, valor ni etiqueta.
- Empieza por `2_plantilla_financiera/LEEME.md` y `ESPECIFICACION_TECNICA.md`.
- `cd 2_plantilla_financiera/motor && npm install && npm test` comprueba que da los mismos resultados que el Excel original.
