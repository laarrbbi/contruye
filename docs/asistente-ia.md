# Nicolás IA, el asistente del curso

Un asistente de inteligencia artificial especializado en promoción y construcción que responde con
el contenido del curso, calcula con sus plantillas y contesta en el idioma de la plataforma. Está
**preparado y cerrado**: el menú lo muestra como «Nicolás IA · Pronto», la página `/nicolas-ia`
dice lo que hará y recoge correos para el boletín (fuente `assistant`), y no responde a ningún
alumno hasta que se abre (abajo, «Cómo se abre»).

Nicolás es una IA y lo dice: no se presenta como el profesor del curso ni habla en su nombre. Si el
nombre es el del profesor, hace falta su consentimiento para usarlo (`ASSISTANT_NAME` lo cambia).

## Cómo responde

```
pregunta → disponibilidad → una pregunta a la vez → filtro de entrada → caché de preguntas frecuentes
        → créditos → enrutador → búsqueda en el curso (solo lo que el alumno puede leer)
        → cascada de modelos (pequeño → mediano → grande), cada uno con sus herramientas y su presupuesto
        → filtro de salida → fuentes citadas → créditos gastados → rastro
```

- **El curso, troceado** (`Indexer`, `Chunker`, `php artisan assistant:index`): las lecciones, los
  textos de cada módulo (leyes, dónde ir, partners de la provincia), los checklists y los documentos
  del curso, en los seis idiomas, cada trozo con quién puede leerlo. **Nunca** los cuestionarios ni
  el examen final, ni la estrategia comercial. Se reconstruye en cada despliegue (`deploy/deploy.sh`,
  `deploy/vercel/build.sh`). Cada trozo lleva consigo los títulos bajo los que está (una lista
  cortada repite su encabezado, una tabla su cabecera), para que un detalle no llegue sin su
  tema. Además:
  - **Las siete plantillas del curso** (`TemplateKnowledge`), en español y detrás de la puerta de
    su módulo: qué calcula cada una, sus casillas de entrada (con el ejemplo de la plantilla y la
    nota de la casilla), cada resultado con su fórmula y lo que lee cada casilla de la fórmula, y
    el ejemplo del curso: lo que Nicolás, el promotor de los «Ejemplo» de las lecciones, mete en
    ella y lo que le sale (`config/assistant_examples.php`; los escenarios de la Lección 2.4 en la
    Plantilla 2). Esas cifras son las que dan las lecciones: `AssistantIndexTest` falla si una no
    está en la lección que el ejemplo nombra. La base de datos de suelo es solo una descarga.
  - **La plantilla profesional (el extra)**, detrás de la puerta de Financials: sus 275 casillas de
    entrada por sección, con su etiqueta, tipo, unidad, valor de la plantilla y nota, lo que devuelve
    (el coste por partidas, el préstamo, el coeficiente de paso, el calendario y su aviso E79) y el
    ejemplo de 24 viviendas que carga su calculadora, con el resumen que dio el motor. El asistente
    la explica; la calcula el alumno en la página Financials.
  - **Las oficinas de cada provincia, fase a fase** (Anexo A, `province_guides`), en cada idioma
    traducido: cada trozo es de una provincia y solo llega a los alumnos de esa provincia
    (la que eligieron en su primera visita) y de ese módulo; quien no tiene provincia, el
    administrador incluido, no recibe ninguna. Al modelo se le dice la provincia del alumno
    (`<alumno … provincia="…">`).
  - **El glosario** (`config/glossary.php`, `lang/<idioma>/glossary.php`): los impuestos y
    organismos que el curso nombra, como los explica la «i» del reproductor, en los seis idiomas.
    Es lo primero que pregunta un alumno («¿qué es el ICIO?») y cabe en dos líneas.
  - **Las páginas web oficiales de cada lección** (`config/lesson_resources.php`, las que el
    reproductor lista bajo la lección), en español, para quien puede leer la lección: con ellas en
    los documentos, el asistente puede dar la dirección comprobada (el filtro de salida solo deja
    pasar enlaces que los documentos traen).
- **La búsqueda** (`Retriever`, `Tokenizer`): BM25 sobre MySQL (y sobre el SQLite de la vista
  previa), en el idioma del alumno y en español, quedándose solo con lo que el alumno puede leer:
  la misma puerta que el reproductor (`Scope`: los módulos comprados, las lecciones gratuitas; los
  documentos según su acceso; las oficinas, su provincia). Ningún servicio externo de *embeddings*
  ve el curso. El modelo puede buscar otra vez con su herramienta, en español. Lo que hace que los
  seis trozos que acompañan a la pregunta sean los buenos:
  - las palabras pesan según lo que **ese** alumno puede leer (las oficinas de las otras 51
    provincias no cuentan, o «registro» y «ayuntamiento» no pesarían nada para nadie);
  - las palabras de relleno de una pregunta («cómo», «puedo», «necesito»…) no cuentan, en los seis
    idiomas, y los plurales y las formas de una misma palabra se buscan juntos;
  - una palabra del título, de un encabezado o del arranque en negrita de un trozo vale por varias;
  - no más de tres trozos de la misma lección: el resto de los sitios va a otras fuentes;
  - la plantilla que la pregunta nombra («plantilla 2») va primero entre las fichas de plantillas;
  - en «¿a qué **oficina** voy para…?» la palabra del lugar apenas pesa (lo que se pregunta es el
    resto), y el alumno con provincia recibe siempre el trozo de sus oficinas más cercano a la
    pregunta cuando pregunta a dónde ir o a quién acudir.
- **Tres modelos en cascada** (`Router`, `config/assistant.php`): Claude Haiku 5.5 (1 crédito),
  Claude Sonnet 5.5 (3 créditos) y Claude Opus 5.5 (5 créditos). Lo que cuesta una pregunta es,
  sobre todo, qué modelo la contesta, así que contesta el más barato que puede. Abajo, «Lo que
  cuesta una respuesta».
- **Herramientas, con los derechos del alumno y nada más** (`Tools`): buscar en el curso, ver las
  casillas de una plantilla y calcular con ella (solo de un módulo que el alumno tiene: las cinco
  de CONSTRUYE y los «números gordos» del profesional, con el motor de la plataforma y los
  parámetros legales vigentes), leer los parámetros legales y, salvo el último modelo de la
  cascada, pasar la pregunta al siguiente. Ninguna lee cuentas, pedidos, usuarios ni ajustes: el
  modelo no tiene forma de llegar a los datos de nadie.
- **Las instrucciones** (`resources/assistant/system.md`, `Prompt`): quién es, cómo responde (citar
  la lección `[n]`, no inventar cifras ni leyes, usar las plantillas para calcular, dar una web solo
  si un documento la trae, de tres a seis frases salvo que se pida un análisis, recordar que no
  sustituye a un abogado, un asesor fiscal o un técnico), sus límites y la lista de las plantillas
  (una línea por plantilla; sus casillas llegan cuando hacen falta). Es el prefijo estable de cada
  llamada y va en la caché de *prompts* de la API.

## Lo que cuesta una respuesta

Cada pieza está puesta para que una pregunta gaste solo los *tokens* que necesita, en el modelo que
necesita. Por millón de *tokens* (precios de lista de la API de Claude, comprobados el 25/11/2026;
`config/assistant.php` los tiene para el presupuesto y los rastros):

| Nivel | Modelo | Entrada | Salida | Leído de la caché | Créditos |
|---|---|---|---|---|---|
| pequeño | Claude Haiku 5.5 | 0,10 $ | 0,50 $ | 0,01 $ | 1 |
| mediano | Claude Sonnet 5.5 | 2 $ | 10 $ | 0,10 $ | 3 |
| grande | Claude Opus 5.5 | 4 $ | 20 $ | 0,20 $ | 5 |

El mediano cuesta veinte veces el pequeño y el grande, cuarenta. De ahí el orden:

1. **Sin modelo, gratis:** el filtro de entrada contesta él mismo los intentos de ataque, y la
   caché de preguntas frecuentes, la pregunta que otro alumno con el mismo acceso ya hizo.
2. **El enrutador** (`Router`) decide por dónde entra la pregunta, sin llamar a ningún modelo.
   Casi todo entra por el pequeño. Sube al mediano lo que pide un análisis sin rodeos («analiza»,
   «compara», «simula») o suma dos señales (un tema de análisis, tres cifras o más, tres preguntas
   en una, un texto largo), y al grande solo lo que las junta casi todas. Nombrar un tema no es
   pedir un estudio: «¿qué es la TIR?» es una definición y la contesta el pequeño. Los umbrales son
   `assistant.router` (`medium_at` 2, `large_at` 5), y cada rastro guarda por qué entró donde entró.
3. **El relevo** (`consultar_al_experto`): un modelo que ve que la pregunta le queda grande no
   contesta, la pasa al siguiente, que empieza de cero con la misma pregunta y los mismos
   documentos. Probar primero con el pequeño cuesta unas centésimas de céntimo. **El alumno paga
   los créditos del modelo que contestó, nunca los de los intentos.**
4. **Si un modelo está saturado**, contesta el de debajo, una vez. Si al alumno no le llegan los
   créditos para el nivel que tocaba, la pregunta entra por el que sí puede pagar.

Y dentro de cada llamada:

- **El prefijo estable en caché** (las herramientas y las instrucciones, unos 8.800 caracteres):
  dentro de los cinco minutos siguientes se relee a la décima o la vigésima parte del precio.
- **Las casillas de las plantillas, fuera de las instrucciones.** Antes viajaban en cada pregunta
  (6.800 caracteres, más de la mitad de las instrucciones); ahora el modelo las pide con
  `ver_plantilla` cuando va a calcular, o le llegan como documento si la pregunta trata de una
  plantilla.
- **Seis trozos del curso, bien elegidos** (unos 5.500 caracteres con la pregunta), en vez de más
  trozos por si acaso: de ahí el trabajo en la búsqueda.
- **Un presupuesto por nivel** (`assistant.models.<nivel>`): cuánta conversación acompaña a la
  pregunta (`history_chars`: el último intercambio siempre entero, los anteriores mientras quepan;
  una conversación larga no encarece cada pregunta), cuántas veces puede usar sus herramientas
  (`tool_rounds`; agotadas, se le pide que conteste con lo que tiene, sin gastar una llamada en
  pedir otra herramienta), el techo de salida (`max_tokens`) y el esfuerzo (`effort`: bajo en el
  pequeño, medio en los otros dos).
- **La pregunta y sus documentos en caché entre rondas**, solo en el mediano y el grande
  (`cache_turn`): cada ronda de herramientas reenvía todo, y ahí sí compensa. En el pequeño, que
  casi siempre contesta en una llamada, sería un recargo sin retorno.
- **Respuestas cortas por defecto**: la salida es lo más caro de cada modelo.

**Estimación por respuesta**, a precios de lista y con los tamaños medidos sobre el curso (son
estimaciones: sin clave no se han podido contar *tokens* ni hacer llamadas reales; los rastros
darán las cifras de verdad):

| Contesta | Coste aproximado | Por 1 $ |
|---|---|---|
| filtro o caché | 0 | — |
| pequeño | 0,0004 – 0,0007 $ | unas 1.500 – 2.500 respuestas |
| mediano (con una ronda de herramientas) | 0,02 – 0,03 $ | unas 40 |
| grande (con una ronda de herramientas) | 0,04 – 0,08 $ | unas 15 – 25 |

Con un reparto supuesto de 90 % pequeño, 8 % mediano y 2 % grande, cien preguntas costarían unos
0,40 $ (el reparto real lo dirán los rastros). El tope por alumno lo ponen los créditos: 300 al mes
gastados enteros en el modelo grande son 60 respuestas, unos 3 a 5 $; gastados en el pequeño, menos
de 0,20 $. Y el tope de toda la plataforma, el presupuesto diario (`ASSISTANT_DAILY_BUDGET_USD`,
20 $).

`/admin/assistant` enseña lo que pasa de verdad: los tres niveles con su modelo y su precio, y por
cada ruta de los últimos 30 días, cuántas preguntas, lo que costaron, el coste medio por
respuesta, los *tokens* de entrada (y qué parte salió de la caché) y de salida, y cuántas preguntas
subieron de nivel. Con eso se ajustan los umbrales del enrutador o el modelo de un nivel
(`ASSISTANT_MODEL_SMALL` / `_MEDIUM` / `_LARGE`, `ASSISTANT_EFFORT_…`); una cascada de dos niveles
es quitar uno de `assistant.cascade`. Cualquiera de esos cambios es una versión nueva (abajo).

## Las protecciones

- **La clave no sale del servidor.** El navegador solo habla con esta API; la clave
  (`ANTHROPIC_API_KEY`) no se registra, no se devuelve, no está en ningún rastro.
  `app:check-secrets --strict` falla si el asistente está activado sin clave.
- **Filtro de entrada** (`Guard::question`): limpia caracteres invisibles y de dirección, pliega
  las letras parecidas (NFKC), impide que una pregunta cierre las etiquetas en que viaja, y contesta
  él mismo —sin llamar al modelo y sin gastar créditos— los intentos claros: saltarse las
  instrucciones, sacar el *prompt*, pedir claves, datos de otros alumnos o del administrador.
  Lo más sutil llega al modelo, cuyas instrucciones tratan la pregunta y los documentos como datos,
  y queda marcado en el rastro.
- **Filtro de salida** (`Guard::answer`): una respuesta con el código canario de las instrucciones,
  una clave o un pasaje de las instrucciones no se entrega; los correos que el curso no contiene se
  ocultan; un enlace a un sitio que ningún documento ni herramienta dio pierde su dirección.
- **Abuso**: 6 preguntas por minuto y 120 al día por alumno, una a la vez, 2.000 caracteres por
  pregunta, y un presupuesto diario de toda la plataforma (`ASSISTANT_DAILY_BUDGET_USD`, 20 $):
  alcanzado, Nicolás descansa para los alumnos hasta el día siguiente. La ruta lleva el contador
  del material del curso (`content`) y cada respuesta, la huella invisible del lector.

## Créditos

Como Claude, ChatGPT o Lovable: un saldo al mes por plan (`ASSISTANT_CREDITS_FULL` 300 para el
curso entero, `ASSISTANT_CREDITS_MODULES` 100 para quien tiene módulos, 2.000 para un
administrador), que se renueva el día 1 (mes de Madrid) y no se acumula. Una respuesta del modelo
pequeño gasta 1, del mediano 3, del grande 5, una pregunta frecuente servida de la caché 0; un error
o una negativa del modelo, 0. Si el saldo no llega al nivel que tocaba, contesta uno que sí alcanza;
el chat dice lo que cuesta cada uno. El libro de créditos
(`assistant_credit_ledger`) solo admite líneas nuevas, como las facturas. El administrador da o
corrige créditos en `/admin/assistant` o con `php artisan assistant:credits correo@… 50 --note="…"`.

**Preguntas frecuentes** (`AnswerCache`): la primera pregunta de una conversación, repetida palabra
por palabra (sin contar mayúsculas, acentos ni signos), en el mismo idioma, por un alumno que puede
leer exactamente la misma parte del curso, recibe la respuesta ya dada. La clave incluye las
instrucciones, los modelos, el índice y los parámetros legales: si algo cambia, no se sirve nada
viejo. Nunca para una continuación ni para un cálculo con las cifras del alumno.

## El rastro

Cada pregunta deja una línea en `assistant_traces`, pase lo que pase (bloqueada, de la caché,
rechazada, fallida, sin créditos): quién, la ruta, el modelo que contestó, la versión de las
instrucciones y del índice, los *tokens*, el coste, los créditos, los trozos del curso usados, las
herramientas, los avisos de los filtros y el tiempo. Nunca la clave; la pregunta y la respuesta
están en la conversación del alumno, que puede borrarla. `/admin/assistant` lo muestra, con un
filtro «solo con avisos». `php artisan assistant:prune` (cada día) borra los rastros de más de 180
días.

## Cada cambio se prueba antes de salir

1. **Las pruebas** (`tests/Feature/Assistant*Test.php`, `tests/Unit/AssistantTextTest.php`, con un
   modelo falso: no cuestan nada) cubren lo que el alumno puede leer, los filtros, el enrutador, la
   cascada y sus presupuestos, los créditos, la caché, el cierre hasta la evaluación, el rastro y
   que la clave no aparezca nunca. `AssistantCourseSearchTest` indexa **el curso de verdad** y
   comprueba que está entero en el índice, en los seis idiomas (cada lección, cada texto de módulo,
   cada checklist, cada documento, las 52 provincias en las 12 fases, el glosario; las plantillas y
   las webs de las lecciones en español), y que la búsqueda pasa su comprobación.
2. **La comprobación de la búsqueda** (`resources/assistant/retrieval.json`,
   `php artisan assistant:retrieval`): un centenar de preguntas de alumno, en los seis idiomas, cada
   una con el trozo del curso que la búsqueda tiene que traer (una lección, el texto de un módulo,
   la ficha de una plantilla, las oficinas de su provincia) y con lo que **no** puede traer (un
   módulo que no tiene, otra provincia, la plantilla del extra). No llama a ningún modelo ni gasta
   nada: se pasa después de cada índice (los dos despliegues la lanzan) y pasa con el 90 % y
   ninguna fuga. `--show` enseña los documentos de cada pregunta; `--failed`, solo los fallos.
3. **La evaluación** (`resources/assistant/evals.json`, `php artisan assistant:eval`): 29 preguntas
   en los seis idiomas, entre ellas los ataques (marcados `critical`), hechas por el mismo código
   que usan los alumnos y comprobadas una a una. Pasa con el 90 %, todos los casos críticos y **los
   tres modelos contestando lo suyo**: hay al menos un caso que entra por cada nivel, y si uno no
   responde (o contesta por él el de debajo), esa pasada no vale. Antes de gastar nada lanza la
   comprobación de la búsqueda; si falla, no llama al modelo. Si un caso sale como «model not
   reached», el motivo que dio la API (tipo de error, mensaje e identificador de la petición) está
   en el registro del servidor (`storage/logs`), sin la petición ni la clave.
   Llama al modelo real: por la estimación de arriba, en torno a 0,20 $; al final dice lo que
   costó de verdad.
   **Una versión nueva** —otra redacción de las instrucciones o de una herramienta, otro modelo u
   otro esfuerzo, otro presupuesto de un nivel, otros umbrales del enrutador, otros ajustes de la
   búsqueda, un curso reindexado— **no llega a los alumnos hasta que su evaluación pasa**; mientras,
   siguen en «Pronto» (o con la versión anterior cerrada). `deploy/deploy.sh` la lanza sola cuando
   hace falta (`--if-needed`). Un error que aparezca en los rastros se convierte en un caso nuevo
   del archivo que toque: la evaluación si falló la respuesta, la búsqueda si faltó el documento.

## Cómo se abre

1. Poner la clave de la API de Anthropic en `api/.env` del servidor: `ANTHROPIC_API_KEY=…`
   (nunca en el código ni en el front). Los administradores ya pueden probarlo en `/nicolas-ia`,
   marcado como vista previa.
2. `php artisan assistant:index` y `php artisan assistant:retrieval` (los hace cada despliegue) y
   `php artisan assistant:eval`.
3. Cuando el contenido del curso sea el definitivo: `ASSISTANT_ENABLED=true`, desplegar. El menú
   pierde el «Pronto» y los alumnos matriculados ven el chat.

`/admin/assistant` dice en todo momento qué falta.
