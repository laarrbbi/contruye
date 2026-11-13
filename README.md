# Construye Academy — LMS

Plataforma de formación online (LMS) en **español**, con la interfaz también en **inglés**,
**francés**, **árabe** (de derecha a izquierda), **ruso** y **ucraniano** (`/en`, `/fr`, `/ar`,
`/ru`, `/uk`), para vender y difundir un curso de promoción inmobiliaria en España. Los textos
del curso se traducen en `traducciones/` (borradores sin revisar; el español es la referencia). Primer producto:
**CONSTRUYE · «Promoción de viviendas de renta libre en España»**, versión 5: 12 módulos (las
12 fases reales de una promoción, del solar a la postventa, 51 lecciones), 7 plantillas
incluidas (5 de CONSTRUYE en los módulos 1, 2, 5, 7 y 8, y 2 del profesional en el Módulo 1), un quiz por módulo, un examen final y el directorio de las 52
provincias. Al terminar se ofrece un extra de pago, para cualquier plan: Financials (9 lecciones y
la plantilla financiera profesional corregida, calculada en la plataforma).

Es la misma plataforma que Ebni Academy (la edición argelina), adaptada a España: contenidos,
idioma, moneda, medios de pago, provincias y facturación. Las funcionalidades no cambian.

## Arquitectura

Monorepo:

| Carpeta | Papel | Stack |
|---|---|---|
| `api/` | API REST | Laravel 12 · PHP 8.3 · MySQL 8 · Sanctum (cookies SPA) · colas database · Pest |
| `web/` | SPA | React 18 · Vite · TypeScript · Tailwind · UI estilo shadcn · TanStack Query · i18next (es/en/fr/ar/ru/uk) |
| `curso/` | El paquete del curso, **tal como lo entregó el formador** | Markdown, JSON, PDF, la plantilla financiera y su motor |
| `traducciones/` | Nuestras traducciones del curso, una carpeta por idioma, comprobadas contra el español | Markdown con la misma estructura que `curso/` |
| `financials/` | Servicio de la plantilla financiera | Node.js 22 + el motor del paquete (HyperFormula) |
| `docker/` | Imágenes de desarrollo y producción | php-fpm 8.3, nginx |

### Pagos

Todo en **euros**.

| Método | Flujo |
|---|---|
| **Stripe Checkout** (tarjeta bancaria) | Sesión alojada → webhook `Stripe-Signature` verificado con el SDK → job en cola → `Order::markPaidAndEnroll()` |
| **Transferencia bancaria** (manual) | El alumno ve el IBAN, el titular y la referencia `CONSTRUYE-{pedido}`, y sube el justificante (jpeg/png/pdf ≤ 5 MB, almacenamiento privado) → validación del admin (aprobar/rechazar + e-mail) |
| **Bizum** (manual) | Igual, con el número de Bizum |
| **Efectivo** | El admin registra un pedido pagado en efectivo, al precio que calcula `PricingService` |

La matrícula (`enrollments`) **solo** se crea cuando un pedido pasa a `paid`: transacción con
bloqueo de fila e idempotente (los webhooks pueden llegar dos veces).

### Vídeo (Bunny Stream)

`GET /api/lessons/{id}/playback` (vista previa gratuita pública; si no, alumno matriculado o admin)
devuelve una **URL de embed firmada y con caducidad** (`sha256(token_key + video_id + expires)`,
TTL 4 h). Marca de agua con el e-mail sobre el reproductor. Las URL directas nunca se exponen.

### Facturas

Facturas numeradas `FAC-AAAA-NNNNN`, sin huecos por año natural de Madrid, con los datos que exige
el Reglamento de facturación (RD 1619/2012): emisor (razón social, NIF, domicilio), cliente, base
imponible, tipo y cuota de IVA (21 %, 10 %, 4 % o 0 %) e importe en letra. PDF en español. Una
factura emitida es una instantánea: las correcciones parten de sus líneas guardadas y se auditan.
El cumplimiento de VeriFactu queda pendiente del propietario y su asesor.

## Arranque local

Requisitos: Docker Desktop + Node 20+.

La primera vez, desde un clon nuevo: `make setup` (copia `api/.env`, construye y arranca los
contenedores, `composer install`, clave de la aplicación, base con los datos de demo,
`npm install`). Los contenedores PHP corren con tu usuario: en Linux, si `id -u` no vale 1000,
escribe antes `UID=…` y `GID=…` en un fichero `.env` en la raíz (`make setup` lo hace).
Sin `make` (Windows):

```bash
cp api/.env.example api/.env
docker compose up -d
docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
docker compose exec php php artisan db:seed --class=DemoShowcaseSeeder
npm --prefix web install
```

Estos pasos se ejecutan tal cual en GitHub, desde un checkout nuevo, cada semana
(`.github/workflows/setup.yml`). Después:

```bash
make up          # php-fpm, nginx (http://localhost:8000), mysql, mysql-test, redis, colas, plantilla financiera
make fresh       # migrate:fresh --seed (programa real + cuentas de demo)
make web         # servidor Vite → http://localhost:5174
make test        # suite Pest completa (base mysql-test aislada)
```

### Vista previa privada online (GitHub Codespaces)

El sitio completo en el navegador, sin instalar nada, visible solo para tu cuenta de GitHub:

1. En la página del repositorio: botón verde **Code** → pestaña **Codespaces** → **Create codespace on …** (la rama
   por defecto del repositorio, que lleva la última versión).
2. La primera vez tarda de 5 a 8 minutos; después el sitio se abre en una pestaña nueva.
   Si no: pestaña **Ports** abajo en el editor → fila «Construye Academy» (8080) → icono del globo.
3. El codespace se detiene tras 30 minutos de inactividad; se vuelve a lanzar desde
   [github.com/codespaces](https://github.com/codespaces), con los datos de demo.

En otro sitio, `make preview` hace lo mismo en http://localhost:8080: la aplicación compilada y
Laravel en una sola dirección, enrutados como en producción (`docker/nginx/preview.conf`).

Cuentas sembradas (solo desarrollo): admin `admin@example.es` / `password` · alumnos
`javier@example.es` (matriculado), `lucia@example.es` / `password`.

## Variables de entorno (`api/.env`)

| Variable | Papel |
|---|---|
| `FRONTEND_URL` | Origen de la SPA (CORS/Sanctum + URL de vuelta del checkout) |
| `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` | Autenticación por cookie de la SPA |
| `STRIPE_ENABLED` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | Stripe (tarjeta, EUR). `STRIPE_ENABLED=false` lo desactiva a propósito |
| `PAYMENT_TRANSFER_IBAN` / `PAYMENT_TRANSFER_HOLDER` | Datos de la transferencia mostrados en el checkout manual |
| `PAYMENT_BIZUM_PHONE` | Número de Bizum mostrado en el checkout manual |
| `BUNNY_STREAM_LIBRARY_ID` / `BUNNY_TOKEN_KEY` / `BUNNY_EMBED_TTL` | Bunny Stream (Embed View Token Authentication) |
| `APP_BUSINESS_TIMEZONE` | Calendario del negocio: `Europe/Madrid` (estadísticas del mes, año de las facturas) |
| `PLANTILLA_URL` | Servicio de la plantilla financiera (`http://127.0.0.1:3100`; en Docker, `http://financials:3100`) |
| `MAIL_*` | Mailtrap en desarrollo; e-mails en cola |

Un método de pago cuyos datos están vacíos no se ofrece en el checkout. Las credenciales son
marcadores de posición: conectarlas es el último paso del proyecto.

### Configuración del webhook

- **Stripe**: `stripe listen --forward-to localhost:8000/api/webhooks/stripe` en desarrollo; en
  producción, el endpoint `https://tu-dominio/api/webhooks/stripe` y su `whsec_…` en
  `STRIPE_WEBHOOK_SECRET`. La firma se verifica sobre el cuerpo en bruto antes de cualquier
  tratamiento, junto con el importe y la moneda.

## Despliegue

Ver **[`deploy/README.md`](deploy/README.md)**: instalación del servidor, `deploy/deploy.sh` (que
se detiene antes de tocar el sitio si falta un secreto o un ajuste no es el de producción),
configuración de Nginx y Supervisor, copias de seguridad y simulacro de restauración.

En producción, el contenido y la cuenta de admin se instalan con `php artisan app:install`, nunca
con `db:seed`, que crea cuentas y pedidos de demostración y se niega a ejecutarse en producción.

### Vista previa en Vercel

Para enseñar el sitio, no para venderlo: Vercel lo construye desde GitHub (`vercel.json`), sin
servidor propio. La web es estática; la API es una función PHP (`api/vercel.php`, runtime
`vercel-php`), que también arranca el motor de las plantillas (`financials/cli.js`).
`deploy/vercel/build.sh` instala las dependencias y construye **una base de datos de demostración
(SQLite)** con el curso, sus traducciones, las 52 provincias y alumnos, pedidos y empresas `[DEMO]`:
ningún dato real, ni la exportación de Orbis. Lo que un visitante cambia dura mientras la función está
caliente y se pierde después. Los accesos de demostración (`javier@example.es`, `admin@example.es`,
contraseña `password`) son públicos: una vista previa de Vercel solo la abre quien tiene acceso al
proyecto. La Lección 1.1 lleva la película del Módulo 1 en español (`web/public/video`); las demás
irán a Bunny Stream cuando el contenido sea definitivo.

## E-mails (en cola)

Cuatro Mailables en el idioma del usuario (es, en, fr, ar —de derecha a izquierda—, ru, uk), todos `ShouldQueue`: bienvenida al
registrarse, confirmación de pago (webhook o validación del admin), justificante rechazado (con el
motivo) y factura. Traducciones en `api/lang/es.json` / `en.json`. En desarrollo, configurar
Mailtrap en `MAIL_*` o dejar `MAIL_MAILER=log`.

## Administración

`/admin` (rol `admin`): panel (ingresos del mes, pedidos por método, matrículas, tasa de
finalización), gestión de cursos/fases/lecciones (arrastrar y soltar), pedidos (filtros, vista del
justificante, aprobar/rechazar/conceder, **exportación CSV** `GET /api/admin/orders/export`),
alumnos (búsqueda, progreso por curso), cupones, campañas, ofertas, colaboradores por provincia, directorio de
empresas y facturas.
Todas las acciones sobre los pedidos quedan registradas en `audit_logs`.

## Ofertas: las tres ventas del recorrido

Tres ofertas acompañan al alumno, cada una en su momento (`/admin/offers`):

| Oferta | Cuándo | Para |
|---|---|---|
| 1 · Después de la compra | De las 24 a las 48 h después del primer pago | Algo pequeño, al día siguiente |
| 2 · Primer hito | Al terminar su primer módulo (todas sus lecciones; se puede pedir más de uno) | Algo intermedio |
| 3 · Final del curso | Al aprobar el examen final o completar todas las lecciones | La grande, p. ej. la formación Excel |

- **Vacías hasta que el propietario las escribe.** Cada una vende un curso de la plataforma (el botón
  lleva a su página, con su precio, y no se muestra a quien ya lo tiene completo) o un enlace (con
  el precio que escribas). Título, texto (Markdown) y botón en español, y en los otros idiomas si
  quieres: sin traducción, el alumno ve el español. Las horas de espera y de visibilidad se cambian
  en la misma página.
- **Dónde se ven:** en «Mis cursos» (grande) y encima de cada página del curso (en una línea). Si hay
  varias abiertas se ve la más avanzada. El alumno puede cerrarla («Ahora no») y no vuelve.
- **El momento queda guardado** con su hora real (`user_offers`), así que el plazo de una oferta no
  se mueve nunca: «disponible hasta» es una fecha de verdad, no una cuenta atrás que vuelve a empezar.
- **Correo (opcional por oferta):** `php artisan offers:send` corre cada 10 minutos (el programador:
  servicio `scheduler` en Docker, `deploy/supervisor/construye-scheduler.conf` en producción). Un
  correo por alumno y oferta cuando se abre su momento, con enlace para darse de baja (también en la
  cabecera List-Unsubscribe). Nunca a quien se dio de baja, ni por un momento de hace más de 3 días:
  activar una oferta no escribe a toda la escuela. La LSSI (art. 21) permite escribir a clientes
  sobre productos parecidos a los que compraron si pueden negarse al dar sus datos y en cada correo:
  el registro debería decirlo; es decisión del propietario con su asesor.
- **Cifras:** en cada oferta, cuántos alumnos llegaron al momento, recibieron el correo, la vieron,
  hicieron clic y la cerraron.

## Directorio de empresas (Partners)

Un listado de empresas españolas (constructoras, arquitectos, bancos, abogados…) que el alumno ve en
dos sitios: en cada módulo, en su sección **Partners del módulo**, las empresas de los tipos que ese
módulo enseña a contratar; y en la página **Partners** (`/partners`), todas las de los módulos que
tiene, con búsqueda por nombre, tipo, provincia y módulo. Las empresas vienen con los módulos: las de
un módulo bloqueado no se sirven.

- **Importar:** `php artisan directory:import fichero.xlsx [más ficheros…] [--source=orbis] [--hidden]`.
  Lee la hoja de datos de una exportación de Orbis (cabeceras en francés, inglés o español), saca la
  provincia del código postal y el tipo del código CNAE. Volver a importar actualiza cada empresa
  sin tocar si está visible u oculta. `--hidden` las deja todas ocultas hasta que el admin elija.
- **Tipos y módulos:** `api/config/directory.php`, cada tipo con sus prefijos CNAE y sus módulos. Las
  empresas de tipo «otras» no aparecen en ningún módulo.
- **Elegir qué se ve:** `/admin/companies`, una a una o todas las que encuentra un filtro. Cada cambio
  queda en `audit_logs`.
- **Derechos sobre los datos:** una exportación de Orbis hecha con una licencia académica (de
  universidad) no permite su uso comercial ni su difusión. Antes de importar a producción, el
  propietario confirma que tiene una licencia que cubre publicarla en un curso de pago. Los datos no
  se versionan: el repositorio solo lleva el importador y 16 empresas `[DEMO]` (`DemoShowcaseSeeder`).

## Contenido del curso

El curso es el paquete del formador, versión 5, en `curso/` (empieza por `curso/LEEME.md`). La
secuencia de fases sigue el documento de Nicolás Cabañas Yuste. **Los textos se integran tal como
están:** los seeders (`CurriculumV2Seeder`, `ChecklistSeeder`, `ProvinceGuideSeeder`) leen el
paquete y guardan cada pieza sin reescribirla, y `CourseContentTest` reconstruye cada archivo
desde la base de datos y lo compara línea a línea.

**Un paquete nuevo** se copia entero en `curso/` (el anterior sale entero) y se aplica con
`db:seed --class=CurriculumV2Seeder`, `--class=ChecklistSeeder`, `--class=ProvinceGuideSeeder` y
`--class=CourseTranslationSeeder`, en ese orden (las traducciones también traducen las filas de cada
provincia del Anexo A):
sobre un curso instalado, los textos en español se ponen al día en su sitio (módulo a módulo,
lección a lección) y las checklists se casan por su texto, así que el progreso y las casillas
marcadas se conservan. Un módulo que cambia de forma (otro número de lecciones) se deja como está
y se avisa. `traducciones/` se pone al día con el español (`app:check-translations` lo exige).

| Del paquete | En la plataforma |
|---|---|
| `modulos/01…12` | Los 12 módulos: objetivo, «Leyes que aplican» y «Dónde ir» al abrir el módulo; las lecciones; la checklist; «Partners en tu provincia». Tras el quiz, cada módulo tiene además sus secciones Tareas (la checklist), Leyes y Partners |
| `modulos/13_financials…` | Financials, el extra del final del curso: sus 9 lecciones y la calculadora de la plantilla profesional |
| `2_plantilla_financiera/plantillas_pequenas/` | Las 5 plantillas de CONSTRUYE, cada una en su módulo (sección «Plantilla N»): calculadora y descarga en Excel. Y las 2 del profesional, en la sección «Plantilla» del Módulo 1: los números gordos del solar (calculadora y Excel) y la base de datos de gestión de suelo (descarga) |
| `excel_corregido/Guia_Analisis_de_Viabilidad_corregida.xlsx`, `pruebas/escenario_guia_profesional.json` | En el extra: la guía de análisis de viabilidad del profesional (descarga, con los parámetros vigentes) y su ejemplo, que se carga en la calculadora de la plantilla profesional |
| `4_videos/` | Los vídeos de las lecciones de los Módulos 1 y 2 (copias en `web/public/video`, `PreviewVideoSeeder`) |
| `modulos/00`, `14`, `16`, `17` | Documentos del curso: introducción, recursos, Anexo A (una provincia cada vez), Anexo B |
| `evaluacion/*.json` | Un quiz por módulo (13) y el examen final (40 preguntas, 60 minutos), corregidos en el servidor |
| `datos/partners_por_provincia.json` | En cada módulo, las instituciones y partners de la provincia del alumno para esa fase |
| `pdf/curso_sin_evaluacion.pdf`, `datos/*.xlsx`, `excel_corregido/*.xlsx` | Descargas, detrás de su acceso (la plantilla profesional y las pequeñas, con los datos del alumno) |
| `3_textos_web/pagina_principal.md` | Los textos de la página principal (`landing.*`, `seo.home`, preguntas frecuentes) |
| `modulos/15_estrategia_comercial.md` | No se publica: es del propietario |

Las lecciones son texto; las de los Módulos 1 y 2 traen además su vídeo (`4_videos/` del paquete v5). El material en inglés está vacío: un
lector en inglés ve el texto en español. `make review` regenera `api/content-review.md`.

## Leyes enlazadas a su fuente oficial

Cada ley que nombra el curso (las 95: estatales y autonómicas) enlaza a su texto oficial, el texto
consolidado del BOE o el boletín autonómico, sin cambiar una palabra del formador: los enlaces se
añaden al mostrar el texto (`App\Support\Legal\LegalLinker`, datos en `api/config/legal.php`). Cada
comunidad tiene además sus páginas oficiales (ley de urbanismo, ITP y AJD en su hacienda, precios
máximos de la vivienda protegida) en «Tu provincia en esta fase» y bajo el Anexo B. La lista completa,
cómo se comprobó y lo que el formador debe revisar: `docs/fuentes-oficiales.md`.
`php artisan legal:check-links` vuelve a abrir cada enlace (las webs oficiales cambian de sitio sus páginas).

## Las plantillas de cálculo y los parámetros legales

`curso/2_plantilla_financiera` trae la plantilla del profesional **corregida** (`PLANTILLA_LIBRE_corregida.xlsx`,
18 hojas, unas 31.000 fórmulas; qué se corrigió: `CAMBIOS.md`), sus originales sin tocar, las **5
plantillas pequeñas** del curso y el motor de cálculo. **Se usan tal como vienen: ni una fórmula,
valor ni etiqueta cambian.** `financials/` es un pequeño servicio Node.js que reutiliza ese motor (lo
construye una vez y lo devuelve a su estado inicial antes de cada cálculo) y que solo llama la API.

- **Plantillas pequeñas** (incluidas): en los módulos 1, 2, 5, 7 y 8, para quien tiene el módulo. La
  hoja de la plantilla tal como es en Excel, con sus casillas de entrada; el resto lo calcula el motor.
- **Plantilla profesional** (Financials, el extra): no entra en ningún plan del curso ni en el acceso
  completo; se compra aparte, a su precio y sin cupones, solo cuando el administrador la pone a la
  venta (`/admin/templates`). **Sale desactivada:** el profesional tiene que revisar la plantilla
  corregida y autorizar su venta. Se ofrece al aprobar el examen final, en el área del alumno que ha
  terminado y en Financials. Quien la compra la calcula, guarda escenarios y descarga su Excel y el
  simplificado.
- **Parámetros legales** (IVA general y reducido, IPS, recargo del Consorcio, interés legal, AJD del
  préstamo e ITP y AJD de las 19 comunidades): en la base de datos, desde
  `plantilla/parametros_legales.json`. Los revisa el administrador en `/admin/templates` (de 0 a
  menos de 100 %, con confirmación); cada cambio queda con quién, cuándo y el valor anterior. **Cada
  cálculo de cada plantilla lleva los vigentes** (`setParametros` del motor): un cambio llega a todos
  los alumnos en su próximo cálculo. Un proyecto solo guarda sus entradas. El alumno los ve en solo
  lectura y elige la comunidad del solar.
- **Descarga en Excel:** las entradas del alumno y los parámetros vigentes escritos sobre una copia
  del `.xlsx` del paquete (`App\Support\Financials\XlsxFiller`); las fórmulas viajan intactas y el
  cálculo iterativo, activado.

```bash
cd curso/2_plantilla_financiera/motor && npm ci && npm test   # las 44 comprobaciones del paquete
cd financials && npm test                                     # el servicio: parámetros, pequeñas, ningún resultado heredado
```

Con el ejemplo del paquete (24 viviendas en la Comunitat Valenciana) la plantilla profesional da
**6.918.553,32 €** con IVA; con el IVA general al 22 % en el administrador, 6.943.595,13 €.

HyperFormula, el motor de hojas de cálculo, es GPLv3: corre en el servidor y no se distribuye al
navegador (`ESPECIFICACION_TECNICA.md`, punto 10).

## Tests

```bash
make test   # Pest, base MySQL aislada (mysql-test), Stripe simulado, firmas reales para los webhooks
```

La suite cubre: el contenido (cada archivo del paquete reconstruido línea a línea), autenticación
Sanctum, catálogo localizado, CRUD del admin (403 para no admins en todas las rutas), checkout
(Stripe, transferencia, Bizum, efectivo, cupones, campañas), webhooks firmados + idempotencia,
justificantes, reproducción de vídeo, progreso, quizzes y examen (corrección en el servidor,
respuestas nunca enviadas antes, reloj del examen), directorio por provincia y descargas detrás
de su acceso, Financials (paso al servicio, escenarios), facturas, estadísticas y exportación CSV.
Las pruebas del motor y del servicio de la plantilla son de Node (arriba).

## Seguridad — puntos clave

- Webhook: firma verificada sobre el cuerpo en bruto **antes** de cualquier tratamiento; el log
  solo guarda lo que traza el pago, nunca el nombre, e-mail ni datos de tarjeta del comprador.
- Matrículas: solo vía `Order::markPaidAndEnroll()` (transacción + bloqueo de fila, idempotente).
- Justificantes: disco privado, servidos por una ruta de admin autenticada.
- Vídeos: URL de embed firmadas con TTL corto, nunca URL directas; marca de agua con el e-mail.
- Límites de peticiones: `throttle:auth` (10/min/IP) y `throttle:checkout` (10/min/usuario).
