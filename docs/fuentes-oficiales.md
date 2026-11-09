# Fuentes oficiales de las leyes del curso

Cada ley que el curso nombra enlaza a su texto oficial, y cada comunidad tiene en la plataforma sus
páginas oficiales (ley de urbanismo, hacienda autonómica para el ITP y el AJD, precios máximos de la
vivienda protegida). Los datos están en `api/config/legal.php`; este documento los lista para revisarlos.

## Cómo se ponen los enlaces

- **Sin tocar el texto del formador.** Los enlaces se añaden al mostrar el texto
  (`App\Support\Legal\LegalLinker`, sobre el Markdown ya leído): las palabras son las mismas, solo
  envueltas en un enlace que abre la web oficial en otra pestaña. `CourseContentTest` sigue
  reconstruyendo cada archivo del paquete desde la base de datos, sin cambios.
- **Cada vez que aparece una ley**, una vez por párrafo o celda: si están la sigla y el número
  («TRLSRU (RDL 7/2015)»), el enlace va en el número. Las traducciones mantienen las referencias en
  español (Ley 38/1999, RD 1627/1997…), así que también quedan enlazadas.
- **Dos leyes con el mismo número** se distinguen por el párrafo: «Ley 13/2015» es la reforma de la Ley
  Hipotecaria y del Catastro, salvo donde se habla de Murcia (su ley de urbanismo); «Decreto Legislativo
  1/2010» es el TRLOTAU de Castilla-La Mancha o la Ley de urbanismo de Cataluña según la fila.
- **Comprobado:** las 348 referencias numeradas de los módulos y anexos quedan enlazadas, a 96 páginas
  oficiales, y las de las cinco traducciones también, con cualquier grafía («RD-ley», «RDL», «Real
  Decreto-ley»…). El test `LegalLinksTest` falla si una ley numerada nueva no tiene su enlace, y solo se
  enlaza a webs oficiales, por https.
- **Fuera de las lecciones**, lo mismo: los pasos de las checklists, la base legal de cada parámetro de
  las plantillas (y las notas por comunidad), y las respuestas de las preguntas frecuentes de la página
  principal («Ley 18/2022»). La web lee estas últimas de `web/src/content/legal-links.json`, que escribe
  `php artisan legal:export-web` desde `api/config/legal.php`; el test avisa si se separan. Los cuestionarios
  y el examen no llevan enlaces: el alumno no debe salir a buscar la respuesta en el BOE.

## Cómo se comprobó cada enlace

El 8 y el 9 de noviembre de 2026, uno por uno, con el título que la propia web oficial da a la página (el
identificador del BOE y el nombre de la norma). El entorno de trabajo no podía abrir boe.es ni las webs
autonómicas directamente, así que conviene que el propietario ejecute, desde el servidor:

```bash
php artisan legal:check-links
```

Abre cada enlace, comprueba que responde y que la página muestra el número de la ley, y lista los que
no. Las webs autonómicas cambian de sitio sus tablas de precios cada año: ese comando dice cuáles
actualizar.

Mientras se comprobaban, 21 de los 74 identificadores del BOE que cabía suponer resultaron ser de otra
norma; todos se corrigieron con el identificador que da el BOE.

## Para el formador: normas citadas que han cambiado

El texto del curso no se toca (constitución, II): estas correcciones son suyas, en un paquete nuevo.

- RD 88/2013 (ITC AEM 1 Ascensores), citado en los módulos 00 y 06: derogado por el Real Decreto 355/2024, de 2 de abril, nueva ITC AEM 1 «Ascensores» (BOE-A-2024-7258). La DA 6.ª mantiene las reglas previas para ascensores puestos en servicio antes.
- RD 297/2013 (servidumbres aeronáuticas), citado en el módulo 04: modificó el Decreto 584/1972, que el Real Decreto 369/2023, de 16 de mayo, de servidumbres aeronáuticas de protección de la navegación aérea (BOE-A-2023-13092), deroga. La norma vigente de servidumbres es el RD 369/2023 (junto con la Ley 48/1960).
- Anexo B, País Vasco, ITP «4 % (hasta 7 % en bases altas, según territorio)»: las tres haciendas forales publican 4 % para viviendas (2,5 % reducido en vivienda habitual con requisitos) y 7 % para los demás inmuebles; no por bases altas. Álava: web.araba.eus/es/hacienda/transmisiones-y-actos-juridicos-documentados · Bizkaia: tarifa ITP en bizkaia.eus · Gipuzkoa: gipuzkoa.eus/es/web/ogasuna/impuestos/modelo/60t/tipos-impositivos. A confirmar por el formador.

## Leyes del curso

| Referencia en el curso | Texto oficial |
|---|---|
| Ley 13/2015 (según el párrafo) | [Ley 13/2015, de 30 de marzo, de ordenación territorial y urbanística de la Región de Murcia](https://www.boe.es/buscar/act.php?id=BOE-A-2015-4790) |
| Decreto Legislativo 1/2010 · TRLOTAU (según el párrafo) | [Decreto Legislativo 1/2010, de 18 de mayo, por el que se aprueba el texto refundido de la Ley de Ordenación del Territorio y de la Actividad Urbanística](https://www.boe.es/buscar/act.php?id=DOCM-q-2010-90043) |
| Decreto Legislativo 1/2010 (según el párrafo) | [Decreto Legislativo 1/2010, de 3 de agosto, por el que se aprueba el texto refundido de la Ley de urbanismo](https://www.boe.es/buscar/act.php?id=BOE-A-2010-13883) |
| Ley 7/2021 · LISTA | [Ley 7/2021, de 1 de diciembre, de impulso para la sostenibilidad del territorio de Andalucía](https://www.boe.es/buscar/act.php?id=BOE-A-2021-20916) |
| Decreto Legislativo 1/2014 | [Decreto-Legislativo 1/2014, de 8 de julio, del Gobierno de Aragón, por el que se aprueba el texto refundido de la Ley de Urbanismo de Aragón](https://www.boe.es/buscar/act.php?id=BOA-d-2014-90410) |
| Decreto Legislativo 1/2004 · TROTU | [Decreto Legislativo 1/2004, de 22 de abril, por el que se aprueba el texto refundido de las disposiciones legales vigentes en materia de ordenación del territorio y urbanismo](https://www.boe.es/buscar/act.php?id=BOE-A-2004-10070) |
| Ley 12/2017 · LUIB | [Ley 12/2017, de 29 de diciembre, de urbanismo de las Illes Balears](https://www.boe.es/buscar/act.php?id=BOE-A-2018-806) |
| Ley 4/2017 | [Ley 4/2017, de 13 de julio, del Suelo y de los Espacios Naturales Protegidos de Canarias](https://www.boe.es/buscar/act.php?id=BOE-A-2017-10295) |
| Decreto 11/2022 | [BOC 2022/21, lunes 31 de enero de 2022, anuncio 319: Decreto 11/2022, de 20 de enero, Registro de Agentes Inmobiliarios de Canarias](https://www.gobiernodecanarias.org/boc/2022/021/001.html) |
| Ley 5/2022 | [Ley 5/2022, de 15 de julio, de Ordenación del Territorio y Urbanismo de Cantabria](https://www.boe.es/buscar/act.php?id=BOE-A-2022-13844) |
| Ley 5/1999 | [Ley 5/1999, de 8 de abril, de Urbanismo de Castilla y León](https://www.boe.es/buscar/act.php?id=BOE-A-1999-12599) |
| Decreto 22/2004 | [Decreto 22/2004, de 29 de enero, por el que se aprueba el Reglamento de Urbanismo de Castilla y León](https://www.boe.es/buscar/act.php?id=BOCL-h-2004-90152) |
| Decreto Legislativo 1/2021 · TRLOTUP | [Decreto Legislativo 1/2021, de 18 de junio, del Consell de aprobación del texto refundido de la Ley de ordenación del territorio, urbanismo y paisaje](https://www.boe.es/buscar/act.php?id=DOGV-r-2021-90283) |
| Ley 5/2025 | [Ley 5/2025, de 30 de mayo, de medidas fiscales, de gestión administrativa y financiera, y de organización de la Generalitat](https://www.boe.es/buscar/doc.php?id=BOE-A-2025-11959) |
| Ley 11/2018 · LOTUS | [Ley 11/2018, de 21 de diciembre, de ordenación territorial y urbanística sostenible de Extremadura](https://www.boe.es/buscar/act.php?id=BOE-A-2019-1790) |
| Ley 2/2016 | [Ley 2/2016, de 10 de febrero, del suelo de Galicia](https://www.boe.es/buscar/act.php?id=BOE-A-2016-3191) |
| Ley 9/2001 | [Ley 9/2001, de 17 de julio, del Suelo, de la Comunidad de Madrid](https://www.boe.es/buscar/act.php?id=BOE-A-2001-18984) |
| Ley 1/2020 | [Ley 1/2020, de 8 de octubre, por la que se modifica la Ley 9/2001, de 17 de julio, del Suelo de la Comunidad de Madrid, para el impulso y reactivación de la actividad urbanística](https://www.boe.es/buscar/act.php?id=BOE-A-2021-3494) |
| Decreto Foral Legislativo 1/2017 | [Decreto Foral Legislativo 1/2017, de 26 de julio, por el que se aprueba el Texto Refundido de la Ley Foral de Ordenación del Territorio y Urbanismo](https://www.boe.es/buscar/act.php?id=BOE-A-2017-12907) |
| Ley 2/2006 | [Ley 2/2006, de 30 de junio, de Suelo y Urbanismo](https://www.boe.es/buscar/act.php?id=BOE-A-2011-17400) |
| Ley 5/2006 | [Ley 5/2006, de 2 de mayo, de Ordenación del Territorio y Urbanismo de La Rioja](https://www.boe.es/buscar/act.php?id=BOE-A-2006-9008) |
| RDL 7/2015 · TRLSRU | [Real Decreto Legislativo 7/2015, de 30 de octubre, por el que se aprueba el texto refundido de la Ley de Suelo y Rehabilitación Urbana](https://www.boe.es/buscar/act.php?id=BOE-A-2015-11723) |
| Ley 38/1999 · LOE | [Ley 38/1999, de 5 de noviembre, de Ordenación de la Edificación](https://www.boe.es/buscar/act.php?id=BOE-A-1999-21567) |
| RD 314/2006 · CTE · Código Técnico de la Edificación | [Real Decreto 314/2006, de 17 de marzo, por el que se aprueba el Código Técnico de la Edificación](https://www.boe.es/buscar/act.php?id=BOE-A-2006-5515) |
| RD 732/2019 | [Real Decreto 732/2019, de 20 de diciembre, por el que se modifica el Código Técnico de la Edificación](https://www.boe.es/buscar/doc.php?id=BOE-A-2019-18528) |
| RD 450/2022 | [Real Decreto 450/2022, de 14 de junio, por el que se modifica el Código Técnico de la Edificación](https://www.boe.es/buscar/doc.php?id=BOE-A-2022-9848) |
| RD 470/2021 · Código Estructural | [Real Decreto 470/2021, de 29 de junio, por el que se aprueba el Código Estructural](https://www.boe.es/buscar/doc.php?id=BOE-A-2021-13681) |
| RD 997/2002 · NCSE-02 | [Real Decreto 997/2002, de 27 de septiembre, por el que se aprueba la norma de construcción sismorresistente: parte general y edificación (NCSR-02)](https://www.boe.es/buscar/doc.php?id=BOE-A-2002-19687) |
| RD 1627/1997 | [Real Decreto 1627/1997, de 24 de octubre, por el que se establecen disposiciones mínimas de seguridad y de salud en las obras de construcción](https://www.boe.es/buscar/act.php?id=BOE-A-1997-22614) |
| Ley 31/1995 | [Ley 31/1995, de 8 de noviembre, de Prevención de Riesgos Laborales](https://www.boe.es/buscar/act.php?id=BOE-A-1995-24292) |
| Ley 32/2006 | [Ley 32/2006, de 18 de octubre, reguladora de la subcontratación en el Sector de la Construcción](https://www.boe.es/buscar/act.php?id=BOE-A-2006-18205) |
| RD 1109/2007 | [Real Decreto 1109/2007, de 24 de agosto, por el que se desarrolla la Ley 32/2006, de 18 de octubre, reguladora de la subcontratación en el Sector de la Construcción](https://www.boe.es/buscar/act.php?id=BOE-A-2007-15766) |
| RD 105/2008 | [Real Decreto 105/2008, de 1 de febrero, por el que se regula la producción y gestión de los residuos de construcción y demolición](https://www.boe.es/buscar/act.php?id=BOE-A-2008-2486) |
| Ley 7/2022 | [Ley 7/2022, de 8 de abril, de residuos y suelos contaminados para una economía circular](https://www.boe.es/buscar/act.php?id=BOE-A-2022-5809) |
| Orden APM/1007/2017 · APM/1007/2017 | [Orden APM/1007/2017, de 10 de octubre, sobre normas generales de valorización de materiales naturales excavados para su utilización en operaciones de relleno y obras distintas a aquéllas en las que se generaron](https://www.boe.es/buscar/doc.php?id=BOE-A-2017-12043) |
| RD 396/2006 | [Real Decreto 396/2006, de 31 de marzo, por el que se establecen las disposiciones mínimas de seguridad y salud aplicables a los trabajos con riesgo de exposición al amianto](https://www.boe.es/buscar/act.php?id=BOE-A-2006-6474) |
| RD 9/2005 | [Real Decreto 9/2005, de 14 de enero, relación de actividades potencialmente contaminantes del suelo y criterios y estándares para la declaración de suelos contaminados](https://www.boe.es/buscar/act.php?id=BOE-A-2005-895) |
| RD 842/2002 · REBT | [Real Decreto 842/2002, de 2 de agosto, por el que se aprueba el Reglamento electrotécnico para baja tensión](https://www.boe.es/buscar/act.php?id=BOE-A-2002-18099) |
| RD 1053/2014 | [Real Decreto 1053/2014, de 12 de diciembre, ITC BT 52 (recarga de vehículos eléctricos) del Reglamento electrotécnico para baja tensión](https://www.boe.es/buscar/act.php?id=BOE-A-2014-13681) |
| RD 1027/2007 · RITE | [Real Decreto 1027/2007, de 20 de julio, por el que se aprueba el Reglamento de Instalaciones Térmicas en los Edificios](https://www.boe.es/buscar/act.php?id=BOE-A-2007-15820) |
| RD 346/2011 · ICT | [Real Decreto 346/2011, de 11 de marzo, Reglamento regulador de las infraestructuras comunes de telecomunicaciones](https://www.boe.es/buscar/act.php?id=BOE-A-2011-5834) |
| RD 513/2017 · RIPCI | [Real Decreto 513/2017, de 22 de mayo, por el que se aprueba el Reglamento de instalaciones de protección contra incendios](https://www.boe.es/buscar/act.php?id=BOE-A-2017-6606) |
| RD 919/2006 | [Real Decreto 919/2006, de 28 de julio, Reglamento técnico de distribución y utilización de combustibles gaseosos y sus ITC ICG 01 a 11](https://www.boe.es/buscar/act.php?id=BOE-A-2006-15345) |
| RD 88/2013 | [Real Decreto 88/2013, de 8 de febrero, ITC AEM 1 «Ascensores». DEROGADO por el Real Decreto 355/2024 (BOE-A-2024-7258)](https://www.boe.es/buscar/act.php?id=BOE-A-2013-1969) — *Derogado por el Real Decreto 355/2024 (BOE-A-2024-7258); el curso lo cita: lo corrige el formador.* |
| RD 203/2016 | [Real Decreto 203/2016, de 20 de mayo, requisitos esenciales de seguridad para la comercialización de ascensores y componentes de seguridad para ascensores](https://www.boe.es/buscar/act.php?id=BOE-A-2016-4953) |
| RD 836/2003 | [Real Decreto 836/2003, de 27 de junio, nueva ITC «MIE-AEM-2» del Reglamento de aparatos de elevación y manutención, referente a grúas torre para obras u otras aplicaciones](https://www.boe.es/buscar/act.php?id=BOE-A-2003-14326) |
| RD 742/2013 | [Real Decreto 742/2013, de 27 de septiembre, por el que se establecen los criterios técnico-sanitarios de las piscinas](https://www.boe.es/buscar/act.php?id=BOE-A-2013-10580) |
| RD 390/2021 | [Real Decreto 390/2021, de 1 de junio, por el que se aprueba el procedimiento básico para la certificación de la eficiencia energética de los edificios](https://www.boe.es/buscar/act.php?id=BOE-A-2021-9176) |
| RD 410/2010 | [Real Decreto 410/2010, de 31 de marzo, requisitos exigibles a las entidades de control de calidad de la edificación y a los laboratorios de ensayos](https://www.boe.es/buscar/act.php?id=BOE-A-2010-6368) |
| Decreto 462/1971 | [Decreto 462/1971, de 11 de marzo, por el que se dictan normas sobre la redacción de proyectos y la dirección de obras de edificación](https://www.boe.es/diario_boe/txt.php?id=BOE-A-1971-418) |
| RD 1000/2010 | [Real Decreto 1000/2010, de 5 de agosto, sobre visado colegial obligatorio](https://www.boe.es/buscar/act.php?id=BOE-A-2010-12618) |
| Ley 49/1960 · LPH | [Ley 49/1960, de 21 de julio, sobre propiedad horizontal](https://www.boe.es/buscar/act.php?id=BOE-A-1960-10906) |
| RDL 1/2007 · TRLGDCU | [Real Decreto Legislativo 1/2007, de 16 de noviembre, texto refundido de la Ley General para la Defensa de los Consumidores y Usuarios y otras leyes complementarias](https://www.boe.es/buscar/act.php?id=BOE-A-2007-20555) |
| RD 515/1989 | [Real Decreto 515/1989, de 21 de abril, sobre protección de los consumidores en cuanto a la información a suministrar en la compra-venta y arrendamiento de viviendas](https://www.boe.es/buscar/act.php?id=BOE-A-1989-11181) |
| Ley 5/2019 | [Ley 5/2019, de 15 de marzo, reguladora de los contratos de crédito inmobiliario](https://www.boe.es/buscar/act.php?id=BOE-A-2019-3814) |
| Ley 10/2010 | [Ley 10/2010, de 28 de abril, de prevención del blanqueo de capitales y de la financiación del terrorismo](https://www.boe.es/buscar/act.php?id=BOE-A-2010-6737) |
| Ley 37/1992 · LIVA | [Ley 37/1992, de 28 de diciembre, del Impuesto sobre el Valor Añadido](https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740) |
| Ley 27/2014 · LIS | [Ley 27/2014, de 27 de noviembre, del Impuesto sobre Sociedades](https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328) |
| Ley 7/2024 | [Ley 7/2024, de 20 de diciembre, Impuesto Complementario, Impuesto sobre el margen de intereses y comisiones, Impuesto sobre los líquidos para cigarrillos electrónicos, y se modifican otras normas tributarias](https://www.boe.es/buscar/act.php?id=BOE-A-2024-26694) |
| RDL 1/1993 | [Real Decreto Legislativo 1/1993, de 24 de septiembre, Texto refundido de la Ley del Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados](https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359) |
| RDL 2/2004 · TRLRHL | [Real Decreto Legislativo 2/2004, de 5 de marzo, texto refundido de la Ley Reguladora de las Haciendas Locales](https://www.boe.es/buscar/act.php?id=BOE-A-2004-4214) |
| RDL 26/2021 | [Real Decreto-ley 26/2021, de 8 de noviembre, por el que se adapta el TRLRHL a la jurisprudencia del Tribunal Constitucional respecto del IIVTNU](https://www.boe.es/buscar/doc.php?id=BOE-A-2021-18276) |
| RDL 1/2010 · LSC | [Real Decreto Legislativo 1/2010, de 2 de julio, texto refundido de la Ley de Sociedades de Capital](https://www.boe.es/buscar/act.php?id=BOE-A-2010-10544) |
| Ley 18/2022 | [Ley 18/2022, de 28 de septiembre, de creación y crecimiento de empresas](https://www.boe.es/buscar/act.php?id=BOE-A-2022-15818) |
| RD-ley 17/2018 · RDL 17/2018 · Real Decreto-ley 17/2018 | [Real Decreto-ley 17/2018, de 8 de noviembre, por el que se modifica el Texto refundido de la Ley del Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados, aprobado por el Real Decreto Legislativo 1/1993, de 24 de septiembre](https://www.boe.es/diario_boe/txt.php?id=BOE-A-2018-15344) |
| Ley 13/1996 | [Ley 13/1996, de 30 de diciembre, de Medidas Fiscales, Administrativas y del Orden Social](https://www.boe.es/buscar/act.php?id=BOE-A-1996-29117) |
| Ley 3/2017 | [Ley 3/2017, de 27 de junio, de Presupuestos Generales del Estado para el año 2017](https://www.boe.es/buscar/act.php?id=BOE-A-2017-7387) |
| Estatuto legal del Consorcio de Compensación de Seguros · Estatuto Legal del Consorcio de Compensación de Seguros | [Real Decreto Legislativo 7/2004, de 29 de octubre, por el que se aprueba el texto refundido del Estatuto Legal del Consorcio de Compensación de Seguros](https://www.boe.es/buscar/act.php?id=BOE-A-2004-18910) |
| RDL 7/1996 | [Real Decreto-ley 7/1996, de 7 de junio, sobre medidas urgentes de carácter fiscal y de fomento y liberalización de la actividad económica](https://www.boe.es/buscar/act.php?id=BOE-A-1996-13002) |
| Ley 12/2023 | [Ley 12/2023, de 24 de mayo, por el derecho a la vivienda](https://www.boe.es/buscar/act.php?id=BOE-A-2023-12203) |
| RD 1426/1989 | [Real Decreto 1426/1989, de 17 de noviembre, por el que se aprueba el Arancel de los Notarios](https://www.boe.es/buscar/act.php?id=BOE-A-1989-28111) |
| Ley 13/2015 | [Ley 13/2015, de 24 de junio, de Reforma de la Ley Hipotecaria y del texto refundido de la Ley de Catastro Inmobiliario](https://www.boe.es/buscar/act.php?id=BOE-A-2015-7046) |
| Orden ECO/805/2003 · ECO/805/2003 | [Orden ECO/805/2003, de 27 de marzo, sobre normas de valoración de bienes inmuebles y de determinados derechos para ciertas finalidades financieras](https://www.boe.es/buscar/act.php?id=BOE-A-2003-7253) |
| RD 1492/2011 | [Real Decreto 1492/2011, de 24 de octubre, por el que se aprueba el Reglamento de valoraciones de la Ley de Suelo](https://www.boe.es/buscar/act.php?id=BOE-A-2011-17629) |
| RD 1098/2001 | [Real Decreto 1098/2001, de 12 de octubre, por el que se aprueba el Reglamento general de la Ley de Contratos de las Administraciones Públicas](https://www.boe.es/buscar/act.php?id=BOE-A-2001-19995) |
| Ley 39/2015 | [Ley 39/2015, de 1 de octubre, del Procedimiento Administrativo Común de las Administraciones Públicas](https://www.boe.es/buscar/act.php?id=BOE-A-2015-10565) |
| Ley 21/2013 | [Ley 21/2013, de 9 de diciembre, de evaluación ambiental](https://www.boe.es/buscar/act.php?id=BOE-A-2013-12913) |
| RD 849/1986 | [Real Decreto 849/1986, de 11 de abril, por el que se aprueba el Reglamento del Dominio Público Hidráulico](https://www.boe.es/buscar/act.php?id=BOE-A-1986-10638) |
| Ley 22/1988 | [Ley 22/1988, de 28 de julio, de Costas](https://www.boe.es/buscar/act.php?id=BOE-A-1988-18762) |
| Ley 37/2015 | [Ley 37/2015, de 29 de septiembre, de carreteras](https://www.boe.es/buscar/act.php?id=BOE-A-2015-10439) |
| Ley 38/2015 | [Ley 38/2015, de 29 de septiembre, del sector ferroviario](https://www.boe.es/buscar/act.php?id=BOE-A-2015-10440) |
| Ley 48/1960 | [Ley 48/1960, de 21 de julio, sobre Navegación Aérea](https://www.boe.es/buscar/act.php?id=BOE-A-1960-10905) |
| RD 297/2013 | [Real Decreto 297/2013, de 26 de abril, por el que se modifica el Decreto 584/1972 de Servidumbres Aeronáuticas y el Real Decreto 2591/1998](https://www.boe.es/buscar/doc.php?id=BOE-A-2013-5166) — *Modificó el Decreto 584/1972, derogado por el Real Decreto 369/2023 (BOE-A-2023-13092); el curso lo cita: lo corrige el formador.* |
| Ley 16/1985 | [Ley 16/1985, de 25 de junio, del Patrimonio Histórico Español](https://www.boe.es/buscar/act.php?id=BOE-A-1985-12534) |
| Orden TIN/1071/2010 · TIN/1071/2010 | [Orden TIN/1071/2010, de 27 de abril, sobre los requisitos y datos que deben reunir las comunicaciones de apertura o de reanudación de actividades en los centros de trabajo](https://www.boe.es/buscar/act.php?id=BOE-A-2010-6871) |
| Ley 20/2015 | [Ley 20/2015, de 14 de julio, de ordenación, supervisión y solvencia de las entidades aseguradoras y reaseguradoras](https://www.boe.es/buscar/act.php?id=BOE-A-2015-7897) |
| Ley 3/2004 | [Ley 3/2004, de 29 de diciembre, por la que se establecen medidas de lucha contra la morosidad en las operaciones comerciales](https://www.boe.es/buscar/act.php?id=BOE-A-2004-21830) |
| Ley 7/2012 | [Ley 7/2012, de 29 de octubre, de modificación de la normativa tributaria y presupuestaria y de adecuación de la normativa financiera para la intensificación de las actuaciones en la prevención y lucha contra el fraude](https://www.boe.es/buscar/act.php?id=BOE-A-2012-13416) |
| Ley 11/2021 | [Ley 11/2021, de 9 de julio, de medidas de prevención y lucha contra el fraude fiscal](https://www.boe.es/buscar/act.php?id=BOE-A-2021-11473) |
| RD 1093/1997 | [Real Decreto 1093/1997, de 4 de julio, normas complementarias al Reglamento Hipotecario sobre inscripción en el Registro de la Propiedad de actos de naturaleza urbanística](https://www.boe.es/buscar/act.php?id=BOE-A-1997-16469) |
| RDL 1/2004 · TRLCI | [Real Decreto Legislativo 1/2004, de 5 de marzo, por el que se aprueba el texto refundido de la Ley del Catastro Inmobiliario](https://www.boe.es/buscar/act.php?id=BOE-A-2004-4163) |
| Ley 8/2013 | [Ley 8/2013, de 26 de junio, de rehabilitación, regeneración y renovación urbanas](https://www.boe.es/buscar/act.php?id=BOE-A-2013-6938) |
| Ley General Tributaria · LGT | [Ley 58/2003, de 17 de diciembre, General Tributaria](https://www.boe.es/buscar/act.php?id=BOE-A-2003-23186) |
| Código Civil | [Real Decreto de 24 de julio de 1889 por el que se publica el Código Civil](https://www.boe.es/buscar/act.php?id=BOE-A-1889-4763) |
| Código de Comercio | [Real Decreto de 22 de agosto de 1885 por el que se publica el Código de Comercio](https://www.boe.es/buscar/act.php?id=BOE-A-1885-6627) |
| Ley Hipotecaria | [Decreto de 8 de febrero de 1946 por el que se aprueba la nueva redacción oficial de la Ley Hipotecaria](https://www.boe.es/buscar/act.php?id=BOE-A-1946-2453) |
| Reglamento Hipotecario | [Decreto de 14 de febrero de 1947 por el que se aprueba el Reglamento Hipotecario](https://www.boe.es/buscar/act.php?id=BOE-A-1947-3843) |
| Estatuto de los Trabajadores | [Real Decreto Legislativo 2/2015, de 23 de octubre, por el que se aprueba el texto refundido de la Ley del Estatuto de los Trabajadores](https://www.boe.es/buscar/act.php?id=BOE-A-2015-11430) |
| Reglamento Notarial | [Decreto de 2 de junio de 1944 por el que se aprueba con carácter definitivo el Reglamento de la organización y régimen del Notariado](https://www.boe.es/buscar/act.php?id=BOE-A-1944-6578) |

## Páginas oficiales de cada comunidad

En la plataforma: en cada módulo, en «Tu provincia en esta fase»; y bajo el Anexo B, todas.

| Comunidad | Página | Enlace |
|---|---|---|
| Andalucía | Ley de urbanismo | [Ley 7/2021, de 1 de diciembre, de impulso para la sostenibilidad del territorio de Andalucía](https://www.boe.es/buscar/act.php?id=BOE-A-2021-20916) |
|  | Vivienda protegida: precios máximos | [Precios máximos de las Viviendas Protegidas - Junta de Andalucía](https://www.juntadeandalucia.es/organismos/fomentoarticulaciondelterritorioyvivienda/areas/vivienda-rehabilitacion/planes-instrumentos/paginas/plan-vive-precios-maximo-vp.html) |
|  | ITP y AJD (hacienda) | [Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados - Junta de Andalucía](https://www.juntadeandalucia.es/organismos/economiahaciendayfondoseuropeos/areas/tributos-juego/tributos/paginas/impuestos-cedidos-transmisiones.html) |
| Aragón | Ley de urbanismo | [Decreto-Legislativo 1/2014, de 8 de julio, del Gobierno de Aragón, por el que se aprueba el texto refundido de la Ley de Urbanismo de Aragón](https://www.boe.es/buscar/act.php?id=BOA-d-2014-90410) |
|  | Vivienda protegida: precios máximos | [Vivienda protegida: promoción, alquiler y venta. Gobierno de Aragón](https://www.aragon.es/-/otras-gestiones-en-materia-de-vivienda) |
|  | ITP y AJD (hacienda) | [Transmisiones patrimoniales y actos jurídicos documentados. Gobierno de Aragón (Administración Tributaria de Aragón)](https://www.aragon.es/administracion-tributaria-de-aragon/impuestos/transmisiones-patrimoniales-y-actos-juridicos-documentados) |
| Principado de Asturias | Ley de urbanismo | [Decreto Legislativo 1/2004, de 22 de abril, por el que se aprueba el texto refundido de las disposiciones legales vigentes en materia de ordenación del territorio y urbanismo](https://www.boe.es/buscar/act.php?id=BOE-A-2004-10070) |
|  | Vivienda protegida: precios máximos | [Venta de vivienda protegida - Principado de Asturias](https://www.asturias.es/temas/vivienda-territorio/vivienda-publica-y-protegida/venta-vivienda-protegida) |
|  | ITP y AJD (hacienda) | [Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados - Sede electrónica de Servicios Tributarios del Principado de Asturias](https://sede.tributasenasturias.es/sites/sede/default/es_ES/Que-quieres-hacer/Transmisiones-Patrimoniales-y-AJD) |
| Illes Balears | Ley de urbanismo | [Ley 12/2017, de 29 de diciembre, de urbanismo de las Illes Balears](https://www.boe.es/buscar/act.php?id=BOE-A-2018-806) |
|  | Vivienda protegida: precios máximos | [DG.HAB - Informació per realitzar tràmits d'Habitatges Protegits - Preus màxims venda i arrendament (Govern de les Illes Balears)](https://www.caib.es/sites/habitatgesprotegits/ca/preus_maxims_venda_i_arrendament/) |
|  | ITP y AJD (hacienda) | [Agència Tributària de les Illes Balears (ATIB) · Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados](https://www.atib.es/TA/contenido.aspx?Id=9854&lang=es) |
| Canarias | Ley de urbanismo | [Ley 4/2017, de 13 de julio, del Suelo y de los Espacios Naturales Protegidos de Canarias](https://www.boe.es/buscar/act.php?id=BOE-A-2017-10295) |
|  | Vivienda protegida: precios máximos | [Información relativa al precio máximo de venta y renta aplicable a viviendas protegidas de promoción privada - Sede electrónica del Gobierno de Canarias](https://sede.gobiernodecanarias.org/sede/tramites/3903) |
|  | ITP y AJD (hacienda) | [Agencia Tributaria Canaria · Guía completa del ITP y AJD](https://www3.gobiernodecanarias.org/tributos/atc/estatico/asistencia_contribuyente/guias/pdf/guia_completa_itp_y_ajd.pdf) |
| Cantabria | Ley de urbanismo | [Ley 5/2022, de 15 de julio, de Ordenación del Territorio y Urbanismo de Cantabria](https://www.boe.es/buscar/act.php?id=BOE-A-2022-13844) |
|  | Vivienda protegida: precios máximos | [El Ejecutivo actualiza el módulo de VPO (Gobierno de Cantabria, comunicado: módulo básico autonómico y precios máximos por zona)](https://www.cantabria.es/web/comunicados/w/el-ejecutivo-actualiza-el-m%C3%B3dulo-de-vpo-para-ejecutar-el-plan-de-construcci%C3%B3n-de-vivienda-p%C3%BAblica-m%C3%A1s-ambicioso-de-la-historia-de-cantabria-) |
|  | ITP y AJD (hacienda) | [Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados - Sede electrónica del Gobierno de Cantabria](https://sede.cantabria.es/sede/catalogo-de-tramites/tramite/impuesto-sobre-transmisiones-patrimoniales-y-actos-juridicos-documentados/530) |
| Castilla-La Mancha | Ley de urbanismo | [Decreto Legislativo 1/2010, de 18 de mayo, por el que se aprueba el texto refundido de la Ley de Ordenación del Territorio y de la Actividad Urbanística](https://www.boe.es/buscar/act.php?id=DOCM-q-2010-90043) |
|  | Vivienda protegida: precios máximos | [Compraventa de vivienda protegida (Vivienda Castilla-La Mancha) — con calculadora de precio máximo de venta](https://vivienda.castillalamancha.es/compraventa-de-vivienda-protegida) |
|  | ITP y AJD (hacienda) | [Modelo 600 - Instrucciones para su cumplimentación (Portal Tributario de Castilla-La Mancha, con la tabla de tipos)](https://portaltributario.jccm.es/modelo-600-instrucciones-para-su-cumplimentacion) |
| Castilla y León | Ley de urbanismo | [Ley 5/1999, de 8 de abril, de Urbanismo de Castilla y León](https://www.boe.es/buscar/act.php?id=BOE-A-1999-12599) |
|  | Vivienda protegida: precios máximos | [Junta de Castilla y León · precios máximos de venta 2025 (módulo básico estatal 758 €)](https://vivienda.jcyl.es/web/jcyl/binarios/168/773/PRECIOS%20MAXIMOS%20VENTA%202025%20.pdf?blobheader=application/pdf%3Bcharset%3DUTF-8&blobnocache=true) |
|  | ITP y AJD (hacienda) | [Transmisiones Patrimoniales Onerosas (Tributos, Junta de Castilla y León)](https://tributos.jcyl.es/web/es/informacion-tributaria/transmisiones-patrimoniales-onerosas.html) |
| Cataluña | Ley de urbanismo | [Decreto Legislativo 1/2010, de 3 de agosto, por el que se aprueba el texto refundido de la Ley de urbanismo](https://www.boe.es/buscar/act.php?id=BOE-A-2010-13883) |
|  | Vivienda protegida: precios máximos | [Preus d'habitatges nous. Habitatge (Generalitat de Catalunya)](https://habitatge.gencat.cat/ca/ambits/preus-ingressos-i-zones/preus-maxims-de-venda-i-renda-dels-habitatges-protegits/habitatges-nous/) |
|  | Vivienda protegida: precios máximos | [Preus de venda d'habitatges en segones i posteriors transmissions. Habitatge (Generalitat de Catalunya)](https://habitatge.gencat.cat/ca/ambits/preus-ingressos-i-zones/preus-maxims-de-venda-i-renda-dels-habitatges-protegits/preus-de_venda_dhabitatges-en-segones-i-posteriors-transmissions/) |
|  | ITP y AJD (hacienda) | [Tarifes, tipus impositius i quota. Agència Tributària de Catalunya](https://atc.gencat.cat/ca/tributs/itpajd/tpo/tarifes-tipus/) |
| Comunitat Valenciana | Ley de urbanismo | [Decreto Legislativo 1/2021, de 18 de junio, del Consell de aprobación del texto refundido de la Ley de ordenación del territorio, urbanismo y paisaje](https://www.boe.es/buscar/act.php?id=DOGV-r-2021-90283) |
|  | Vivienda protegida: precios máximos | [Generalitat Valenciana · Vivienda y Calidad en la Edificación: precios máximos de vivienda protegida en segunda o posteriores transmisiones](https://habitatge.gva.es/es/web/vivienda-y-calidad-en-la-edificacion/preus-maxims-habitatge-protegit-segona-o-posteriors-transmissions) |
|  | Vivienda protegida: precios máximos | [Solicitud de información de los precios máximos de venta y renta de viviendas protegidas y del plazo de protección - Sede electrónica - Generalitat Valenciana](https://sede.gva.es/es/detall-tramit?id_proc=113) |
|  | ITP y AJD (hacienda) | [Impuesto sobre transmisiones patrimoniales onerosas y actos jurídicos documentados - ATV - Generalitat Valenciana](https://atv.gva.es/es/itpajd) |
| Extremadura | Ley de urbanismo | [Ley 11/2018, de 21 de diciembre, de ordenación territorial y urbanística sostenible de Extremadura](https://www.boe.es/buscar/act.php?id=BOE-A-2019-1790) |
|  | Vivienda protegida: precios máximos | [DOE núm. 16, lunes 26 de enero de 2026: actualización de los precios máximos de vivienda protegida para 2026](https://doe.juntaex.es/pdfs/doe/2026/160o/26060174.pdf) |
|  | ITP y AJD (hacienda) | [Portal Tributario (Junta de Extremadura) · Transmisiones patrimoniales onerosas](https://portaltributario.juntaex.es/PortalTributario/web/guest/transmisiones-patrimoniales-onerosas) |
| Galicia | Ley de urbanismo | [Ley 2/2016, de 10 de febrero, del suelo de Galicia](https://www.boe.es/buscar/act.php?id=BOE-A-2016-3191) |
|  | Vivienda protegida: precios máximos | [DOG 90 del 18/5/2026 - Resolución de 6 de mayo de 2026 (Acuerdo del Consello de la Xunta de 4 de mayo de 2026): ingresos para acceder a las viviendas protegidas y sus precios máximos de venta y renta](https://www.xunta.gal/dog/Publicados/2026/20260518/AnuncioC3Q2-070526-0001_es.html) |
|  | Vivienda protegida: precios máximos | [Información de interés sobre vivienda protegida (IGVS, Xunta de Galicia)](https://igvs.xunta.gal/es/vivienda-protegida/informacion-de-interes-sobre-vivienda-protegida) |
|  | ITP y AJD (hacienda) | [Transmisiones patrimoniales onerosas - Axencia Tributaria de Galicia (ATRIGA)](https://www.atriga.gal/es_ES/transmisiones-patrimoniales-onerosas) |
| Comunidad de Madrid | Ley de urbanismo | [Ley 9/2001, de 17 de julio, del Suelo, de la Comunidad de Madrid](https://www.boe.es/buscar/act.php?id=BOE-A-2001-18984) |
|  | Vivienda protegida: precios máximos | [Comunidad de Madrid · PRECIOS 2026 VPPB Y VPPL (precios máximos de venta por zona)](https://www.comunidad.madrid/docs/assets/2026/02/03/viv_2026_precios_max_privada_d74_09.pdf) |
|  | ITP y AJD (hacienda) | [Transmisiones Patrimoniales Onerosas (Comunidad de Madrid, atención al contribuyente)](https://www.comunidad.madrid/atencion-contribuyente/transmisiones-patrimoniales-onerosas) |
| Región de Murcia | Ley de urbanismo | [Ley 13/2015, de 30 de marzo, de ordenación territorial y urbanística de la Región de Murcia](https://www.boe.es/buscar/act.php?id=BOE-A-2015-4790) |
|  | Vivienda protegida: precios máximos | [BORM núm. 54, 5 de marzo de 2024 · Orden de 29 de febrero de 2024: precios máximos aplicables a las viviendas protegidas energéticamente eficientes](https://www.carm.es/web/descarga?ARCHIVO=Orden+de+29+de+febrero+de+2024+determinan+los+precios+m%C3%A1ximos+aplicables+a+las+viviendas+protegidas+energ%C3%A9ticamente+eficientes+.pdf&ALIAS=ARCH&IDCONTENIDO=189256&IDTIPO=60&RASTRO=c468$m37247) |
|  | ITP y AJD (hacienda) | [Novedades en materia de tributos aprobadas en la Ley de Presupuestos Generales de la Región de Murcia para 2025 - Agencia Tributaria de la Región de Murcia (ATRM)](https://agenciatributaria.carm.es/en/-/novedades-en-materia-de-tributos-aprobadas-en-la-ley-de-presupuestos-generales-de-la-comunidad-autonoma-de-la-region-de-murcia-para-el-ano-2025) |
| Comunidad Foral de Navarra | Ley de urbanismo | [Decreto Foral Legislativo 1/2017, de 26 de julio, por el que se aprueba el Texto Refundido de la Ley Foral de Ordenación del Territorio y Urbanismo](https://www.boe.es/buscar/act.php?id=BOE-A-2017-12907) |
|  | Vivienda protegida: precios máximos | [Calcular los precios máximos de venta y alquiler, y la duración del régimen de protección de las viviendas protegidas (navarra.es)](https://www.navarra.es/es/tramites/on/-/line/calcular-los-precios-maximos-de-venta-y-alquiler-y-la-duracion-del-regimen-de-proteccion-de-las-viviendas-protegidas) |
|  | ITP y AJD (hacienda) | [Preguntas comunes. Resumen modelos de impresos y tipos de gravamen - Hacienda Foral de Navarra (navarra.es)](https://www.navarra.es/home_es/Gobierno+de+Navarra/Organigrama/Los+departamentos/Economia+y+Hacienda/Organigrama/Estructura+Organica/Hacienda/Informacion+Fiscal/Preguntas+mas+frecuentes/Impuesto+sobre+transmisiones+patrimoniales+y+AJD/Preguntas+comunes/resumen.htm) |
| País Vasco | Ley de urbanismo | [Ley 2/2006, de 30 de junio, de Suelo y Urbanismo](https://www.boe.es/buscar/act.php?id=BOE-A-2011-17400) |
|  | Vivienda protegida: precios máximos | [Resumen de precios máximos del año 2026 - Gobierno Vasco - Euskadi.eus](https://www.euskadi.eus/gobierno-vasco/-/etxe-precios-maximos-cas/) |
|  | ITP y AJD (hacienda) | [Transmisiones y actos jurídicos documentados - Hacienda, Diputación Foral de Álava](https://web.araba.eus/es/hacienda/transmisiones-y-actos-juridicos-documentados) |
|  | ITP y AJD (hacienda) | [Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados · tarifa (Diputación Foral de Bizkaia)](https://www.bizkaia.eus/documents/1135059/1352576/ITP+Tarifa.pdf/c4388ca6-7611-2afd-d3a8-c06f1a0806fd) |
|  | ITP y AJD (hacienda) | [Tipos impositivos - Hacienda - Diputación Foral de Gipuzkoa](https://www.gipuzkoa.eus/es/web/ogasuna/impuestos/modelo/60t/tipos-impositivos) |
| La Rioja | Ley de urbanismo | [Ley 5/2006, de 2 de mayo, de Ordenación del Territorio y Urbanismo de La Rioja](https://www.boe.es/buscar/act.php?id=BOE-A-2006-9008) |
|  | Vivienda protegida: precios máximos | [Precio Máximo VPO - Vivienda - Portal del Gobierno de La Rioja](https://www.larioja.org/vivienda/es/proteccion-oficial/proteccion-oficial/precio-maximo-vpo) |
|  | ITP y AJD (hacienda) | [Transmisiones Patrimoniales Onerosas - Tributos - Portal del Gobierno de La Rioja](https://www.larioja.org/tributos/es/tributos-cedidos/impuesto-transmisiones-patrimoniales-actos-juridicos-docume/informacion/transmisiones-patrimoniales-onerosas) |
| Ciudad Autónoma de Ceuta | Ley de urbanismo | [Real Decreto Legislativo 7/2015, de 30 de octubre, por el que se aprueba el texto refundido de la Ley de Suelo y Rehabilitación Urbana](https://www.boe.es/buscar/act.php?id=BOE-A-2015-11723) |
|  | ITP y AJD (hacienda) | [Agencia Tributaria: ITP y AJD - Transmisiones (modelo 600), con la bonificación del 50 % en Ceuta y Melilla (art. 57 bis)](https://sede.agenciatributaria.gob.es/Sede/declaraciones-informativas-otros-impuestos-tasas/impuesto-sobre-transmisiones-patrimoniales-actos-juridicos/itp-ajd-transmisiones-derechos-reales-fianzas.html) |
| Ciudad Autónoma de Melilla | Ley de urbanismo | [Real Decreto Legislativo 7/2015, de 30 de octubre, por el que se aprueba el texto refundido de la Ley de Suelo y Rehabilitación Urbana](https://www.boe.es/buscar/act.php?id=BOE-A-2015-11723) |
|  | ITP y AJD (hacienda) | [Agencia Tributaria: ITP y AJD - Transmisiones (modelo 600), con la bonificación del 50 % en Ceuta y Melilla (art. 57 bis)](https://sede.agenciatributaria.gob.es/Sede/declaraciones-informativas-otros-impuestos-tasas/impuesto-sobre-transmisiones-patrimoniales-actos-juridicos/itp-ajd-transmisiones-derechos-reales-fianzas.html) |

Ceuta y Melilla no publican precios máximos de vivienda protegida propios: no se enlaza ninguno. Su ITP
lo gestiona la Agencia Tributaria del Estado, con la bonificación del 50 % del artículo 57 bis.
El enlace de la Comunitat Valenciana para precios máximos en segundas transmisiones es el que dio el propietario.
