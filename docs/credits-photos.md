# Créditos: fotos, vídeo, modelos 3D, texturas, iluminación

El diseño de la página de venta, de las pantallas de cuenta y del pago es el de Hani Oulhadj para
Ebni Academy, adaptado a CONSTRUYE. Ebni usa las fotos de las obras de su formador; CONSTRUYE **no
tiene todavía ninguna foto de una promoción de su profesor** y no muestra ninguna como si lo fuera
(constitución, I). Todas las imágenes de la página son de ilustración, con licencia libre, y la
página lo dice donde aparecen. Cuando el profesor entregue sus fotos, sustituirán a estas.

| Fichero servido | Dónde | Origen | Autor | Licencia |
|---|---|---|---|---|
| `web/public/photos/illustration-residence-plein.webp`, `-haut.webp` | S2, capítulo «12 fases» | [View of Modern Apartment Buildings at Sunset](https://www.pexels.com/photo/view-of-modern-apartment-buildings-at-sunset-19332514/) (4 480 × 4 480, Corea del Sur) | [Jehyun An](https://www.pexels.com/@jehyun-an-806276469/) | [Licencia Pexels](https://www.pexels.com/license/) |
| `web/public/photos/residencia-ilustracion.webp` y su dibujo `web/public/croquis/residencia.webp` | S1, presentación | la misma foto, recortada al marco de S1 | Jehyun An | Licencia Pexels |
| `web/public/photos/illustration-chantier-plein.webp`, `-haut.webp` | S2, capítulo «52 provincias» | [Unfinished Concrete Building With Tower Crane](https://www.pexels.com/photo/unfinished-concrete-building-with-tower-crane-1402923/) (5 449 × 3 633) | [Jimmy Chan](https://www.pexels.com/@jimbear/) | [Licencia Pexels](https://www.pexels.com/license/) |
| `web/public/photos/obra-01.webp` … `obra-10.webp` | S5, el anillo de imágenes | un fotograma de cada clip del vídeo de obra (tabla de abajo), en el mismo orden | ver el vídeo | Pexels / Pixabay |

Cada foto lleva en la página la mención «Foto de ilustración» (`landing.s1.caption`,
`landing.parcours.illustration`); el anillo de S5 la frase `landing.gallery.attribution`: imágenes
de obras ajenas a CONSTRUYE, ninguna es una promoción del curso ni de su profesor.

La licencia Pexels permite el uso comercial gratuito, sin atribución obligatoria; prohíbe revender
la foto tal cual y dar a entender que las personas o marcas que aparecen respaldan el producto. Los
créditos se dan aquí y en el pie de página por cortesía.

Fuentes reducidas (3 200 y 3 600 px de ancho) en `web/photos-sources/`, fuera de la carpeta servida.
Etalonaje y encuadres: `web/scripts/grade-photos.mjs` (sombras azul noche, luces cálidas, grano
ligero). El recorte de S1 y su dibujo a lápiz: `web/scripts/make-sketches.mjs`. Los fotogramas del
anillo: `web/scripts/fotogramas-obra.sh`, uno por clip, tomado donde el clip se ve solo, sin fundido.

El edificio que sube en S3 (12 plantas, una por fase) es un modelo de la página, no una foto ni un
proyecto: `web/src/components/landing/hero/`. Su imagen fija para «movimiento reducido»
(`web/public/immeuble-termine.webp`) es un render de esa misma escena terminada, con la cámara algo
más lejos para que quepa el zócalo, recortado a su silueta.

## Escena final (S9): modelos 3D, madera, iluminación

Una línea «Créditos» al pie de la página recoge estos autores y licencias, en los seis idiomas
(`landing.pied.credits`).

| Elemento | Origen | Autor | Licencia | Cambios |
|---|---|---|---|---|
| Llave | [Door Key](https://sketchfab.com/3d-models/door-key-6538a6a21f5545e0b1dcf22bee5741a1) | [GUIBEL](https://sketchfab.com/Guibel) | [CC BY 4.0](http://creativecommons.org/licenses/by/4.0/) | acero pasado a latón (mapa de color), comprimida |
| Puerta | [Embellished front door 3](https://sketchfab.com/3d-models/embellished-front-door-3-6dc7928d6f6b475abced6ccf88ccf912) | [anDDDres](https://sketchfab.com/anDDDres) | [CC BY 4.0](http://creativecommons.org/licenses/by/4.0/) | vestida de nogal, normales recalculadas, aristas retiradas, hoja agrupada, comprimida |
| Madera | [Black Walnut Veneer 02](https://polyhaven.com/a/black_walnut_veneer_02) | Jenelle van Heerden | [CC0](https://polyhaven.com/license) | rugosidad pasada al canal verde, WebP de 1 024 px |
| Iluminación | [Lebombo](https://polyhaven.com/a/lebombo) (HDRI) | Greg Zaal | [CC0](https://polyhaven.com/license) | reducida de 1 024 a 512 px |

Fuentes en `web/modeles-sources/` (los dos .zip tal como se descargaron, con su `license.txt`, y los
ficheros de Poly Haven). Ficheros servidos, producidos por `web/scripts/modeles-3d.mjs`:
`web/public/modeles/`, `web/public/draco/`. Imágenes fijas de la escena (movimiento reducido,
móviles modestos): `web/public/fin/`, renderizadas desde la propia escena.

## Vídeo de las pantallas de cuenta y del pago

Un bucle de 27 s (`web/public/video/chantier.mp4`, `.webm`, póster `chantier-poster.webp`) cuenta
una obra en orden: diez clips reales distintos, cada uno usado una sola vez. Se ve en el acceso, el
registro, la contraseña olvidada, el restablecimiento y la cabecera del pago; sus fotogramas forman
el anillo de S5.

| # | Etapa | Clip | Autor | Licencia | Tramo |
|---|---|---|---|---|---|
| 1 | Solar y volquete (dron) | [Aerial video of a truck dumping soil](https://www.pexels.com/video/aerial-video-of-a-truck-dumping-soil-5407510/) | K | [Licencia Pexels](https://www.pexels.com/license/) | 10,0 → 13,4 s |
| 2 | Excavadora (contrapicado) | [Excavator in action at construction site](https://www.pexels.com/video/excavator-in-action-at-construction-site-35647503/) | Val А | [Licencia Pexels](https://www.pexels.com/license/) | 2,6 → 6,0 s |
| 3 | Losa, bomba y hormigonera (dron) | [Construction, crane, truck](https://pixabay.com/videos/construction-crane-truck-building-41826/) | [LadislavBur](https://pixabay.com/users/ladislavbur-16302054/) | [Licencia Pixabay](https://pixabay.com/service/license-summary/) | 1,0 → 4,4 s |
| 4 | Vertido del hormigón (de cerca) | [Concrete pouring at construction site](https://www.pexels.com/video/concrete-pouring-at-construction-site-39924253/) | Sergei Starostin | [Licencia Pexels](https://www.pexels.com/license/) | 0,6 → 4,0 s |
| 5 | Ferrallado, operarios desde arriba | [Drone footage of workers in a construction site](https://www.pexels.com/video/drone-footage-of-workers-in-a-contruction-site-14134278/) | utopia 36 | [Licencia Pexels](https://www.pexels.com/license/) | 6,0 → 9,4 s |
| 6 | Grúa que iza una carga (contrapicado) | [Video of metals hanging in the crane](https://www.pexels.com/video/video-of-metals-hanging-in-the-crane-9343478/) | 岳威 梁 | [Licencia Pexels](https://www.pexels.com/license/) | 4,0 → 7,4 s |
| 7 | La estructura sube (dron) | [Aerial view of construction site with crane](https://www.pexels.com/video/aerial-view-of-construction-site-with-crane-36396548/) | Val А | [Licencia Pexels](https://www.pexels.com/license/) | 2,0 → 5,4 s |
| 8 | Andamios y fachadas | [Um prédio em construção](https://www.pexels.com/video/um-predio-em-construcao-28128921/) | FÁBIO REIS DE ABREU (PORTUGA) | [Licencia Pexels](https://www.pexels.com/license/) | 12,0 → 15,4 s |
| 9 | Acristalamiento, al atardecer | [Glass panels window on the building exterior](https://www.pexels.com/video/glass-panels-window-on-the-building-exterior-7317305/) | MART PRODUCTION | [Licencia Pexels](https://www.pexels.com/license/) | 3,0 → 6,4 s |
| 10 | Residencia terminada, ventanas encendidas | [A building with lights on at night](https://www.pexels.com/video/a-building-with-lights-on-at-night-18029861/) | Ashwin Alok | [Licencia Pexels](https://www.pexels.com/license/) | 20,0 → 23,4 s |

Las dos licencias permiten el uso comercial gratuito, sin atribución obligatoria; los autores se
citan por cortesía, aquí y en la línea «Créditos» del pie de página.

Montaje: `web/scripts/video-chantier.sh` (las fuentes no están en el repositorio: el script las
descarga; tramos y encuadres están en él). Cada plano dura 3,4 s, con 0,7 s de fundido encadenado
a cada lado, es decir 2,7 s visto solo; el último fundido vuelve al principio del primero y el bucle
no salta. 1 080 × 1 080, 25 i/s, sin pista de audio: MP4 4,0 MB, WebM 3,7 MB, póster 110 KB.
