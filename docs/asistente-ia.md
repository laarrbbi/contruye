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
        → modelo + herramientas → filtro de salida → fuentes citadas → créditos gastados → rastro
```

- **El curso, troceado** (`Indexer`, `php artisan assistant:index`): las lecciones, los textos de
  cada módulo (leyes, dónde ir, partners de la provincia), los checklists y los documentos del
  curso, en los seis idiomas, cada trozo con quién puede leerlo. **Nunca** los cuestionarios ni el
  examen final, ni la estrategia comercial. Se reconstruye en cada despliegue (`deploy/deploy.sh`,
  `deploy/vercel/build.sh`).
- **La búsqueda** (`Retriever`): BM25 sobre MySQL, en el idioma del alumno y en español, quedándose
  solo con lo que el alumno puede leer: la misma puerta que el reproductor (`Scope`: los módulos
  comprados, las lecciones gratuitas; los documentos según su acceso). Ningún servicio externo de
  *embeddings* ve el curso. El modelo puede buscar otra vez con su herramienta, en español.
- **Dos modelos** (`Router`, `config/assistant.php`): Claude Haiku 5.5 para la mayoría de las
  preguntas (1 crédito) y Claude Opus 5.5 para un análisis, una comparación, varios escenarios o
  una pregunta larga con muchas cifras (5 créditos). El pequeño puede pasarle la pregunta al grande
  (`consultar_al_experto`). Opus usa los *fallbacks* del servidor (si un filtro de seguridad
  declina, la API reintenta con el modelo que Anthropic recomienda). `ASSISTANT_MODEL_LARGE=claude-sonnet-5-5`
  lo abarata. Si el grande está saturado, contesta el pequeño.
- **Herramientas, con los derechos del alumno y nada más** (`Tools`): buscar en el curso, calcular
  con una plantilla (solo de un módulo que el alumno tiene: las cinco de CONSTRUYE y los «números
  gordos» del profesional, con el motor de la plataforma y los parámetros legales vigentes) y leer
  los parámetros legales. Ninguna lee cuentas, pedidos, usuarios ni ajustes: el modelo no tiene
  forma de llegar a los datos de nadie.
- **Las instrucciones** (`resources/assistant/system.md`, `Prompt`): quién es, cómo responde (citar
  la lección `[n]`, no inventar cifras ni leyes, usar las plantillas para calcular, recordar que no
  sustituye a un abogado, un asesor fiscal o un técnico), sus límites y el catálogo de las
  plantillas. Es el prefijo estable de cada llamada y va en la caché de *prompts* de la API.

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
pequeño gasta 1, del grande 5, una pregunta frecuente servida de la caché 0; un error o una negativa
del modelo, 0. Si el saldo no llega al grande, contesta el pequeño. El libro de créditos
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

1. **Las pruebas** (`tests/Feature/AssistantTest.php`, con un modelo falso: no cuestan nada) cubren
   lo que el alumno puede leer, los filtros, el enrutador, los créditos, la caché, el cierre hasta
   la evaluación, el rastro y que la clave no aparezca nunca.
2. **La evaluación** (`resources/assistant/evals.json`, `php artisan assistant:eval`): 22 preguntas
   en los seis idiomas, entre ellas los ataques (marcados `critical`), hechas por el mismo código
   que usan los alumnos y comprobadas una a una. Pasa con el 90 % y todos los casos críticos.
   Llama al modelo real: cuesta unos céntimos y lo dice al final.
   **Una versión nueva** —otra redacción de las instrucciones, otro modelo, un curso reindexado—
   **no llega a los alumnos hasta que su evaluación pasa**; mientras, siguen en «Pronto» (o con la
   versión anterior cerrada). `deploy/deploy.sh` la lanza sola cuando hace falta (`--if-needed`).
   Un error que aparezca en los rastros se convierte en un caso nuevo del archivo.

## Cómo se abre

1. Poner la clave de la API de Anthropic en `api/.env` del servidor: `ANTHROPIC_API_KEY=…`
   (nunca en el código ni en el front). Los administradores ya pueden probarlo en `/nicolas-ia`,
   marcado como vista previa.
2. `php artisan assistant:index` (lo hace cada despliegue) y `php artisan assistant:eval`.
3. Cuando el contenido del curso sea el definitivo: `ASSISTANT_ENABLED=true`, desplegar. El menú
   pierde el «Pronto» y los alumnos matriculados ven el chat.

`/admin/assistant` dice en todo momento qué falta.
