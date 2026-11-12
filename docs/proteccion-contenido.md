# Protección del contenido del curso

Lo que impide llevarse el curso entero, y lo que no puede impedir. Ninguna página web evita que
alguien vuelva a escribir lo que ve en su pantalla; lo que sí se puede es que copiar sea lento,
que el curso no salga nunca de golpe y que cualquier copia diga de qué cuenta salió.

## En el servidor

- **El curso no sale entero en una respuesta.** `/learn` es solo el índice (títulos, candados,
  progreso). El texto de cada lección y de cada módulo se pide aparte, al abrir su página
  (`LearnContentController`), detrás de la misma puerta de acceso
  (`AuthorizesCourseAccess`). Antes, `/learn` traía todas las lecciones, dos veces (Markdown y
  HTML), en un solo JSON que se guardaba desde la pestaña «Red» del navegador.
- **Contador por alumno** (`MeterCourseContent`, alias `content`): en cada ruta que sirve
  material (lecciones, módulos, documentos, Anexo A por provincia, hojas de ruta, quizzes,
  descargas, vídeo). 40 páginas por minuto y 400 por hora como máximo; quien lee no se acerca, un
  script que recorre el curso sí, y recibe un 429 que explica por qué. Por encima de 120 páginas
  distintas en un día, el propietario recibe una entrada en el registro de auditoría
  (`content.unusual_reading`); no se bloquea a nadie solo por eso. Los límites están en
  `config/protection.php` (variables `CONTENT_PER_MINUTE`, `CONTENT_PER_HOUR`,
  `CONTENT_DAILY_ALERT`). Los administradores no cuentan.
- **Ninguna caché guarda el material**: cada respuesta va con `Cache-Control: private, no-store`
  y `X-Robots-Tag: noindex`.
- **Marca invisible del lector** (`ContentFingerprint`): en el HTML de cada lección, módulo y
  documento va el identificador del alumno, escrito con caracteres que no ocupan sitio
  (U+2061–U+2063) después del primer espacio de cada párrafo, elemento de lista y celda. No cambia
  nada de lo que se ve ni de cómo se corta una línea, y sobrevive a copiar y pegar. Para saber de
  quién es un texto encontrado fuera:

  ```bash
  docker compose exec -T php php artisan content:trace copia.txt
  ```

  Una marca dañada o inventada se ignora (lleva una comprobación con la clave de la aplicación).
  Se desactiva con `CONTENT_FINGERPRINT=false` (los tests lo hacen, salvo `ContentProtectionTest`).
- **Dos dispositivos por cuenta** (`AUTH_MAX_DEVICES`, `DeviceSessions`): al entrar en un tercero
  se cierra la sesión que lleva más tiempo sin usarse. El alumno ve y cierra sus dispositivos en
  «Mi cuenta › Seguridad».

## En el navegador

- **Zona protegida** (`ProtectedArea`, en la columna del reproductor y en la hoja de ruta): no se
  puede seleccionar el texto, ni copiarlo, cortarlo, arrastrarlo, ni abrir el menú contextual; los
  atajos de guardar, imprimir y ver el código no hacen nada. Los campos donde el alumno escribe
  (calculadoras, tareas) y el teléfono o el correo de una empresa sí se pueden copiar.
- **Impresión**: el material desaparece y queda un aviso; las plantillas y las fichas se
  descargan desde la plataforma.
- **Marca visible**: el correo del alumno, tenue e inclinado, en un patrón detrás del texto, para
  que una captura de pantalla lleve el nombre de su dueño. El vídeo lleva la suya, que se mueve.
- **Vídeo**: sin botón de descarga, sin imagen en imagen (la ventana flotante dejaría la marca
  atrás) y sin menú contextual. Las URL de Bunny están firmadas y caducan.

## Lo que no se puede impedir

Fotografiar la pantalla, o volver a escribir el texto. Para eso están las dos marcas: la visible
en cada captura y la invisible en cada copia.

## En la vista previa de Vercel

El contador usa la caché de la aplicación. En Vercel, cada función tiene la suya y es efímera:
allí el contador no acumula. En el servidor de producción (Docker) la caché es compartida y el
contador funciona como se describe.
