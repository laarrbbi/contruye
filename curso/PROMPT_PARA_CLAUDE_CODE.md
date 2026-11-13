Te paso el paquete CONSTRUYE v5 en 5 zips (CONSTRUYE_v5_parte_1_de_5.zip a CONSTRUYE_v5_parte_5_de_5.zip), porque cada archivo tiene que pesar menos de 30 MB. Descomprímelos todos en la misma carpeta: juntos forman la carpeta CONSTRUYE_para_Claude_Code/ completa. La parte 1 lleva el contenido, las plantillas, los textos de la web y los subtítulos, portadas y transcripciones de los vídeos; las partes 2 a 5, los vídeos. Antes de empezar, comprueba que tienes los 9 vídeos que lista 4_videos/videos.json.

Es el mismo contenido del v4 (curso, plantillas y textos de la web, sin cambios) más una carpeta nueva, 4_videos/, con los vídeos de las lecciones de los Módulos 1 y 2. Esta vez solo tienes que montar los vídeos en la plataforma.

Empieza por 4_videos/LEEME.md y 4_videos/videos.json.

Si todavía no has integrado el v4 (contenido, plantillas pequeñas, extra de pago, parámetros legales en el admin y textos de la home), hazlo primero siguiendo PROMPT_v4_contenido_y_plantillas.md. Si ya lo hiciste, no lo repitas.

1. Qué hay en 4_videos/
- 9 vídeos: lecciones 1.1, 1.2, 1.3 y 1.4 (modulo_1/) y 2.1, 2.2, 2.3, 2.4 y 2.5 (modulo_2/). MP4 H.264, 1920×1080, 30 fps, audio AAC 48 kHz, preparados para streaming (faststart). Entre 7 y 15 MB cada uno.
- subtitulos/: los subtítulos en español de cada vídeo, en .vtt (para el reproductor web) y en .srt (la misma información).
- portadas/: una imagen .jpg por vídeo, para usarla como póster del reproductor.
- transcripciones/: el texto de la locución de cada vídeo.
- videos.json: la lista con, para cada vídeo, el módulo, la lección, el título, el archivo de contenido y el encabezado exacto de la lección (por ejemplo «## Lección 1.2 — Análisis urbanístico: qué puedes construir»), las rutas del vídeo, los subtítulos, la portada y la transcripción, la duración en segundos, el tamaño y la acción que hay que hacer.

2. Lección 1.1: sustituye el vídeo que ya está publicado
- La lección 1.1 ya tiene un vídeo en la plataforma. Sustitúyelo por 4_videos/modulo_1/CONSTRUYE_M1_L1.1.mp4. Esta versión tiene la voz corregida: cada frase arranca limpia, sin el chasquido ni la primera sílaba comida, y con pausas naturales entre frases. Dura 3:50 (la anterior era más corta).
- Súbelo con un nombre de archivo o una versión nueva, para que ni el navegador ni la CDN sigan sirviendo el vídeo anterior. Comprueba en la página de la lección que se reproduce el nuevo: la duración tiene que ser 3:50. Si la plataforma guarda la duración del vídeo, actualízala.
- Borra el archivo anterior del almacenamiento solo cuando hayas comprobado que el nuevo funciona.

3. Lecciones 1.2 a 2.5: añade los vídeos
- Pon cada vídeo en su lección según videos.json (campo encabezado o leccion). Usa el mismo sistema que ya usas para el vídeo de la 1.1: mismo almacenamiento, mismo reproductor, misma posición en la página de la lección.
- Si alguna lección no tiene todavía hueco para vídeo, créalo igual que el de la 1.1.

4. Subtítulos, portada y transcripción
- Añade los subtítulos .vtt al reproductor como pista de subtítulos en español (kind="subtitles", srclang="es", label="Español"), desactivados por defecto y activables por el alumno.
- Usa la imagen de portadas/ como póster del vídeo.
- Si la página de la lección tiene sitio para el texto de la clase o una transcripción, usa el de transcripciones/. Si no lo tiene, no lo añadas.

5. Lo que no se toca
- No toques el diseño ni las animaciones del front-end. Solo se añaden los vídeos, sus subtítulos y sus portadas a las lecciones.
- No vuelvas a codificar los vídeos ni los recortes: ya van en el formato final.
- No cambies los textos de las lecciones, las plantillas ni nada del resto del zip.

6. Antes de dar por terminado
- Abre las 9 lecciones (1.1 a 1.4 y 2.1 a 2.5) y comprueba que cada una muestra su vídeo, con su portada, que se reproduce con sonido y que la duración coincide con videos.json.
- Comprueba que los subtítulos se activan y van sincronizados con la voz, y que la 1.1 ya no muestra el vídeo antiguo.
- Comprueba que funciona en móvil (vídeo a ancho completo, sin scroll horizontal) y en Safari.
- El resto de lecciones (Módulo 3 en adelante) siguen sin vídeo de momento. Déjalas como están.
