# Construye Academy — LMS

Plataforma de formación online (LMS) en **español**, con la interfaz también en **inglés**,
**francés**, **árabe** (de derecha a izquierda), **ruso** y **ucraniano** (`/en`, `/fr`, `/ar`,
`/ru`, `/uk`), para vender y difundir un curso de promoción inmobiliaria en España. Los textos
del curso se traducen en `traducciones/` (borradores sin revisar; el español es la referencia). Primer producto:
**CONSTRUYE · «Promoción de viviendas de renta libre en España»**, versión 4.0: 12 módulos (las
12 fases reales de una promoción, del solar a la postventa, 51 lecciones), la sección Financials
(9 lecciones y la plantilla financiera del profesional, calculada en la plataforma), un quiz por
módulo, un examen final y el directorio de las 52 provincias.

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

1. En la página del repositorio: botón verde **Code** → pestaña **Codespaces** → **Create codespace on main**.
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

## E-mails (en cola)

Cuatro Mailables en el idioma del usuario (es, en, fr, ar —de derecha a izquierda—, ru, uk), todos `ShouldQueue`: bienvenida al
registrarse, confirmación de pago (webhook o validación del admin), justificante rechazado (con el
motivo) y factura. Traducciones en `api/lang/es.json` / `en.json`. En desarrollo, configurar
Mailtrap en `MAIL_*` o dejar `MAIL_MAILER=log`.

## Administración

`/admin` (rol `admin`): panel (ingresos del mes, pedidos por método, matrículas, tasa de
finalización), gestión de cursos/fases/lecciones (arrastrar y soltar), pedidos (filtros, vista del
justificante, aprobar/rechazar/conceder, **exportación CSV** `GET /api/admin/orders/export`),
alumnos (búsqueda, progreso por curso), cupones, campañas, colaboradores por provincia y facturas.
Todas las acciones sobre los pedidos quedan registradas en `audit_logs`.

## Contenido del curso

El curso es el paquete del formador, versión 4.0, en `curso/` (empieza por `curso/LEEME.md`). La
secuencia de fases sigue el documento de Nicolás Cabañas Yuste. **Los textos se integran tal como
están:** los seeders (`CurriculumV2Seeder`, `ChecklistSeeder`, `ProvinceGuideSeeder`) leen el
paquete y guardan cada pieza sin reescribirla, y `CourseContentTest` reconstruye cada archivo
desde la base de datos y lo compara línea a línea.

| Del paquete | En la plataforma |
|---|---|
| `modulos/01…12` | Los 12 módulos: objetivo, «Leyes que aplican» y «Dónde ir» al abrir el módulo; las lecciones; la checklist; «Partners en tu provincia» |
| `modulos/13_financials…` | El módulo Financials, con la calculadora de la plantilla |
| `modulos/00`, `14`, `16`, `17` | Documentos del curso: introducción, recursos, Anexo A (una provincia cada vez), Anexo B |
| `evaluacion/*.json` | Un quiz por módulo (13) y el examen final (40 preguntas, 60 minutos), corregidos en el servidor |
| `datos/partners_por_provincia.json` | En cada módulo, las instituciones y partners de la provincia del alumno para esa fase |
| `pdf/curso_sin_evaluacion.pdf`, `datos/*.xlsx`, `originales/*.xls(x)` | Descargas, detrás de su acceso |
| `modulos/15_estrategia_comercial.md` | No se publica: es del propietario |

Las lecciones son texto (los vídeos se añaden después). El material en inglés está vacío: un
lector en inglés ve el texto en español. `make review` regenera `api/content-review.md`.

## Financials: la plantilla financiera

`curso/2_plantilla_financiera` es la plantilla del profesional (`PLANTILLA_LIBRE.xls`, 17 hojas,
30.795 fórmulas) exportada celda a celda con su motor de cálculo. **Se usa tal cual: no se cambia
ninguna fórmula, valor ni etiqueta.** `financials/` es un pequeño servicio Node.js que reutiliza
ese motor (lo construye una vez y lo devuelve a su estado inicial antes de cada cálculo) y que
solo llama la API. El alumno de Financials rellena los datos de partida (el formulario sale del
diccionario de entradas de la plantilla), calcula, ve el resumen, la plantilla simplificada y
cualquiera de las 17 hojas, y guarda escenarios.

```bash
cd curso/2_plantilla_financiera/motor && npm ci && npm test   # el motor da lo mismo que Excel
cd financials && npm test                                     # el servicio no cambia ningún resultado
```

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
