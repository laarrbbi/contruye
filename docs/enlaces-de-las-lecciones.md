# Las webs de cada lección

Debajo del texto de una lección, la plataforma enlaza las webs que esa lección nombra
(`api/config/lesson_resources.php`). Las leyes no van ahí: se enlazan dentro del propio texto
(`config/legal.php`, `docs/fuentes-oficiales.md`). El texto del curso no cambia: cada enlace tiene la
frase de la lección que lo nombra (`mention`) y `LessonResourcesTest` falla si la lección no la dice.

Comprobado el 13/11/2026, una a una, cargando la página (título visto entre comillas). Donde la web
tiene su propia protección anti-bots, solo se pudo comprobar que el dominio oficial responde: esas van
a su portada y están marcadas **(revisar a mano)**, para abrirlas una vez en un navegador normal.

| Lección | Nombre | Dirección | Comprobación |
|---|---|---|---|
| 1.1 | Idealista | https://www.idealista.com/ | Dominio oficial; protección anti-bots (DataDome) **(revisar a mano)** |
| 1.1 | Fotocasa · terrenos en venta | https://www.fotocasa.es/es/comprar/terrenos/espana/todas-las-zonas/l | «Terrenos en España \| fotocasa» |
| 1.1 | Solvia | https://www.solvia.es/ | «Solvia Inmobiliaria - Viviendas en venta y alquiler» |
| 1.1 | Servihabitat | https://www.servihabitat.com/es/ | «Inmuebles en venta en España - Servihabitat» |
| 1.1 | Altamira Inmuebles | https://www.altamirainmuebles.com/index.html | «Casas y Pisos de Bancos \| Altamira Inmuebles» |
| 1.1 | Aliseda Inmobiliaria | https://www.alisedainmobiliaria.com/ | «Inmuebles en venta en España \| Aliseda» |
| 1.1 | SAREB | https://www.sareb.es/ | Dominio oficial; protección anti-bots (Imperva) **(revisar a mano)** |
| 1.1 | Portal de Subastas del BOE | https://subastas.boe.es/ | «Portal de Subastas Electrónicas» |
| 1.1 | Agencia Tributaria · subastas | https://sede.agenciatributaria.gob.es/Sede/deudas-apremios-embargos-subastas/subastas/general.html | «Agencia Tributaria: General» (subastas de bienes embargados) |
| 1.1 | Seguridad Social · subastas | https://w6.seg-social.es/subastas/ | «Subastas de Bienes Embargados» |
| 1.1 | CASA47 | https://www.casa47.es/ | «Home - CASA47» (la antigua SEPES) |
| 1.1, 1.3, 3.3, 9.3, 11.5 | Sede Electrónica del Catastro | https://www.sedecatastro.gob.es/ | «Sede Electrónica del Catastro - Inicio» |
| 1.2 | SNCZI (MITECO) | https://www.miteco.gob.es/es/agua/temas/gestion-de-los-riesgos-de-inundacion/snczi.html | «Sistema Nacional de Cartografía de Zonas Inundables» |
| 1.3 | Registradores · nota simple | https://sede.registradores.org/sede/sede-corpme-web/registro-de-la-propiedad/publicidad/nota-simple-informativa | Entrada «Nota simple» del menú de la sede (aplicación web: carga, sin texto legible sin navegador) |
| 1.3, 4.2 | AESA · servidumbres aeronáuticas | https://www.seguridadaerea.gob.es/es/ambitos/servidumbres-aeronauticas | «Servidumbres aeronaúticas \| AESA» |
| 1.4 | Registro Mercantil Central · denominaciones | https://www.rmc.es/IntroDenominaciones.aspx | «Denominaciones Sociales - Solicitud, Consulta y/o Descargas \| Registro Mercantil Central» |
| 1.4, 5.3, 5.4, 11.2, 12.4 | Agencia Tributaria · sede | https://sede.agenciatributaria.gob.es/ | «Agencia Tributaria: Inicio» |
| 1.4 | CIRCE (PAE Electrónico) | https://paeelectronico.es/es-es/CreaEmpresaPorTiMismo/Paginas/CIRCE.aspx | «Qué es CIRCE y trámites que se pueden realizar con CIRCE» |
| 2.2 | Registradores · Estadística Registral Inmobiliaria | https://www.registradores.org/actualidad/portal-estadistico-registral/estadisticas-de-propiedad | «Estadísticas de la propiedad» |
| 2.2 | Portal Estadístico del Notariado | https://penotariado.com/ | Dominio oficial; cortafuegos propio **(revisar a mano)** |
| 2.2 | Tinsa · informes | https://www.tinsa.es/informes/ | «Informes Tinsa: datos y estudios del mercado inmobiliario» |
| 2.2 | Sociedad de Tasación | https://www.st-tasacion.es/ | Dominio oficial; protección anti-bots (Cloudflare) **(revisar a mano)** |
| 2.2 | Gloval · informes | https://www.gloval.es/informes/ | «Informes y estudios del mercado inmobiliario en España \| Gloval» |
| 2.2 | CBRE España | https://www.cbre.es/ | Dominio oficial; protección anti-bots (Cloudflare) **(revisar a mano)** |
| 2.2 | JLL España · estudios | https://www.jll.com/es-es/insights | «Perspectivas - Insights \| JLL España» |
| 2.2 | Savills España · research | https://www.savills.es/publicaciones/research.aspx?rc=Spain&p=&t=&f=date&q=&page=1 | «Savills España \| Research» |
| 2.2 | Colliers España | https://www.colliers.com/es-es | Dominio oficial; protección anti-bots (Cloudflare) **(revisar a mano)** |
| 4.2 | Confederaciones Hidrográficas (MITECO) | https://www.miteco.gob.es/es/ministerio/organizacion/organismos-publicos/confederaciones-hidrograficas.html | «Confederaciones Hidrográficas» (las 9, con su web) |
| 4.2 | Costas (MITECO) | https://www.miteco.gob.es/es/costas/temas/proteccion-costa/conociendo-litoral/zonas-litorales-espanolas/clasificacion-legal/litoral-en-legislacion.html | «Clasificación legal» (dominio público y servidumbres) |
| 4.2 | ADIF | https://www.adif.es/ | «Administrador de Infraestructuras Ferroviarias - Adif» |
| 4.3, 5.3, 5.4 | REA · consulta pública | https://expinterweb.mites.gob.es/rea/pub/consulta.htm | «Consulta de acreditación Registro de Empresas Acreditadas» |
| 5.3, 5.4 | Seguridad Social · certificado de estar al corriente | https://sede.seg-social.gob.es/wps/portal/sede/sede/EmpresasyProfesionales/recaudacion/n201736 | «Certificados de estar al corriente con la Seguridad Social e informes de deuda» |
| 5.3, 12.4 | Registradores de España | https://www.registradores.org/ | «Registradores de España» |
| 7.2 | Bureau Veritas | https://www.bureauveritas.es/ | «Home \| España» |
| 7.2 | Applus+ | https://www.applus.com/es/es/ | «Applus+ ESPAÑA» |
| 7.2 | SGS | https://www.sgs.com/es-es | «SGS Spain» |
| 7.2 | Eurocontrol | https://www.eurocontrol.es/ | Redirige (301) a spain.apave.com: hoy es Apave Spain. Destino no cargado **(revisar a mano)** |
| 8.1 | Idealista | https://www.idealista.com/ | Como en 1.1 **(revisar a mano)** |
| 8.1 | Fotocasa | https://www.fotocasa.es/es/ | «Fotocasa.es: Alquiler de pisos, compra y venta» |
| 8.1 | pisos.com | https://www.pisos.com/ | La lección escribe el dominio; no se pudo cargar desde aquí **(revisar a mano)** |

## Sin enlace, y por qué

| Lección | Nombre | Motivo |
|---|---|---|
| 1.1 | Haya | www.haya.es no responde (error 522 de Cloudflare, tres intentos). Según la prensa, Intrum integró Haya en Solvia en 2024: Solvia sí está enlazada |
| 4.2 | Ministerio de Defensa | www.defensa.gob.es cortó la conexión en todos los intentos |
| 7.2 | OCA | No se pudo cargar www.ocaglobal.com desde aquí |
| 8.1 | Google Ads, Meta Ads | No se pudieron cargar desde aquí |
| 8.2 | Registros autonómicos de agentes inmobiliarios (Cataluña, Comunitat Valenciana, Canarias) | No se encontró una consulta pública oficial de agentes inscritos |

Para añadir uno: su entrada en `config/lesson_resources.php`, con la frase exacta de la lección en
`mention`, y su fila aquí con lo que se vio al abrirlo.
