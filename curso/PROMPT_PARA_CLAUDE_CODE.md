Te paso CONSTRUYE_para_Claude_Code_v3.zip. Es el contenido del curso CONSTRUYE (promoción inmobiliaria en España), sus plantillas financieras y los textos de la página principal, para la plataforma que estás montando a partir del código de Ebni Academy. Cómo programarlo lo decides tú, siguiendo la arquitectura que ya tienes. Aquí te digo qué tiene que hacer.

Empieza por LEEME.md del zip, y luego 2_plantilla_financiera/LEEME.md y ESPECIFICACION_TECNICA.md.

1. Contenido del curso (1_contenido/)
- Integra los textos tal como están, sin reescribirlos: introducción, 12 módulos, Financials, recursos, estrategia comercial y los dos anexos (modulos/), quizzes y examen (evaluacion/), partners y fiscalidad por comunidad (datos/).
- Sustituye la versión anterior del contenido que tengas cargada: han cambiado la introducción, los módulos 1, 2, 5, 7, 8 y 12, Financials, Recursos, la estrategia comercial, un quiz de Financials y los PDF.

2. Modelo del producto: plantillas pequeñas incluidas y la plantilla profesional como extra
- Las 5 plantillas pequeñas (2_plantilla_financiera/plantillas_pequenas/) van incluidas en el curso, cada una en su módulo: Plantilla 1 en el Módulo 1, 2 en el 2, 3 en el 5, 4 en el 7 y 5 en el 8 (indice.json). Para cada una: calculadora en la plataforma (formulario generado desde su .entradas.json y calculado con el motor) y descarga del .xlsx.
- La plantilla profesional completa (sección Financials) es un extra de pago, para alumnos de cualquier plan. Se ofrece al terminar el curso: al aprobar el examen final, en la página de fin de curso y en el área del alumno. El texto de la oferta está en 3_textos_web/pagina_principal.md, sección 9. El precio queda como [COMPLETAR].
- Quien no ha comprado el extra ve Financials como contenido bloqueado con la oferta. No puede usar la calculadora de la plantilla profesional ni descargar sus Excel.
- Quien lo ha comprado tiene la calculadora (plantilla/plantilla_libre.json con plantilla/entradas.json, en el servidor) y la descarga de excel_corregido/PLANTILLA_LIBRE_corregida.xlsx y Plantilla_simplificada_corregida.xlsx.
- Deja la venta del extra desactivada con un interruptor en el admin hasta que el profesional la autorice.

3. Parámetros legales editables desde el admin
- Lo que cambia con la ley está en la hoja «Parámetros legales» y en plantilla/parametros_legales.json. Son seis parámetros: IVA general, IVA reducido, Impuesto sobre las Primas de Seguros, recargo del Consorcio, interés legal del dinero y AJD del préstamo hipotecario. Además, el ITP y el AJD de las 19 comunidades autónomas.
- Haz una página en el admin para verlos y cambiarlos. Para cada parámetro, muestra la etiqueta, el valor en %, el valor del original de 2002, la base legal y dónde se usa. Para las comunidades, una tabla con el ITP, el AJD y la nota de cada una.
  - Valida que cada valor esté entre 0 y 100 %. Pide confirmación antes de guardar.
  - Guarda un historial de cada cambio: quién, cuándo, valor anterior y valor nuevo. Muestra la fecha de la última revisión.
- Guárdalos en la base de datos, con los valores de parametros_legales.json como valores iniciales. Son los mismos para todos los usuarios.
  - Antes de cada cálculo, de la plantilla grande o de cualquier pequeña, aplica los vigentes con motor.setParametros(...) (está en motor/plantillaEngine.js; el formato está en ESPECIFICACION_TECNICA.md, sección 10).
  - Un cambio en el admin se aplica a todas las plantillas de todos los usuarios la próxima vez que se calculen. No guardes los parámetros dentro de los proyectos de los usuarios: un proyecto guarda solo sus entradas.
- El usuario no edita los parámetros legales. Ve los que se aplican, en solo lectura, con su fecha de revisión. Elige la comunidad autónoma del solar: Datos de Partida D93 en la plantilla grande y la casilla de comunidad en las Plantillas 1 y 2. Es una lista con las opciones de entradas.json.
- En la descarga de Excel de un usuario, escribe sus entradas y los parámetros vigentes sobre una copia del .xlsx corregido.

4. Página principal (3_textos_web/pagina_principal.md)
- Pon el texto nuevo en la home y en sus metadatos SEO. No toques el diseño ni las animaciones del front-end: cambia solo los textos.
- Del profesor se muestra solo «Máster en Urbanismo», sin universidad ni año.
- Lo que está entre [COMPLETAR] (precios, garantía, trayectoria, testimonios, contacto) se queda así o se oculta hasta tener el dato real. No inventes datos ni testimonios.

5. Lo que no se toca
- No cambies las fórmulas ni los valores de las plantillas (plantilla/*.json, plantillas_pequenas/*.json, los .xlsx) ni los originales del profesional (originales/, plantilla_libre_original.json). Los parámetros legales se cambian solo con setParametros desde el admin.
- El motor va en el servidor (Node.js; HyperFormula, licencia GPLv3 o comercial: ver ESPECIFICACION_TECNICA.md).
- Antes de dar por terminado, ejecuta npm test en 2_plantilla_financiera/motor (39 pruebas, todas deben pasar) y comprueba en la plataforma que la plantilla grande, con el ejemplo de motor/ejemplo.js, da un total con IVA de 6.918.553,32 €. Comprueba también que cambiar el IVA general en el admin cambia ese total.
