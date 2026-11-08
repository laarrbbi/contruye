<?php

/*
|--------------------------------------------------------------------------
| Official sources of the laws the course cites
|--------------------------------------------------------------------------
|
| Every law the course text names, linked to its official text (the BOE's
| consolidated text where there is one, the regional bulletin otherwise),
| and, per autonomous community, the official pages a promoter checks: the
| regional tax agency (ITP and AJD) and the protected-housing maximum prices.
|
| Links are added when the text is displayed (App\Support\Legal\LegalLinker):
| the instructor's words are never changed. Each URL and title here was
| checked against the official page title on 2026-11-08 (docs/fuentes-oficiales.md);
| `php artisan legal:check-links` re-checks them from a machine with internet.
|
| `match`: the references as the course writes them (case-sensitive, whole
| words). `when`: a pattern the surrounding paragraph or table cell must
| contain for this entry to apply, for numbers two laws share (Ley 13/2015).
| A regional link's `lang` is the language of its official title (es or ca).
*/

return [

    'laws' => [
        'urbanismo_murcia' => [
            'title' => 'Ley 13/2015, de 30 de marzo, de ordenación territorial y urbanística de la Región de Murcia.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-4790',
            'match' => ['Ley 13/2015'],
            'when' => 'Murcia',
        ],
        'trlotau' => [
            'title' => 'Decreto Legislativo 1/2010, de 18 de mayo, por el que se aprueba el texto refundido de la Ley de Ordenación del Territorio y de la Actividad Urbanística.',
            'url' => 'https://www.boe.es/buscar/act.php?id=DOCM-q-2010-90043',
            'match' => ['Decreto Legislativo 1/2010', 'TRLOTAU'],
            'when' => 'TRLOTAU|Castilla-La Mancha',
        ],
        'urbanismo_cataluna' => [
            'title' => 'Decreto Legislativo 1/2010, de 3 de agosto, por el que se aprueba el texto refundido de la Ley de urbanismo.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2010-13883',
            'match' => ['Decreto Legislativo 1/2010'],
            'when' => 'Catalu(?:ña|nya)|Catalonia|Catalogne|Каталон|كتالونيا',
        ],
        'lista' => [
            'title' => 'Ley 7/2021, de 1 de diciembre, de impulso para la sostenibilidad del territorio de Andalucía.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2021-20916',
            'match' => ['Ley 7/2021', 'LISTA'],
        ],
        'urbanismo_aragon' => [
            'title' => 'Decreto-Legislativo 1/2014, de 8 de julio, del Gobierno de Aragón, por el que se aprueba el texto refundido de la Ley de Urbanismo de Aragón.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOA-d-2014-90410',
            'match' => ['Decreto Legislativo 1/2014'],
        ],
        'trotu' => [
            'title' => 'Decreto Legislativo 1/2004, de 22 de abril, por el que se aprueba el texto refundido de las disposiciones legales vigentes en materia de ordenación del territorio y urbanismo.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2004-10070',
            'match' => ['Decreto Legislativo 1/2004', 'TROTU'],
        ],
        'luib' => [
            'title' => 'Ley 12/2017, de 29 de diciembre, de urbanismo de las Illes Balears.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2018-806',
            'match' => ['Ley 12/2017', 'LUIB'],
        ],
        'suelo_canarias' => [
            'title' => 'Ley 4/2017, de 13 de julio, del Suelo y de los Espacios Naturales Protegidos de Canarias.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2017-10295',
            'match' => ['Ley 4/2017'],
        ],
        'agentes_inmobiliarios_canarias' => [
            'title' => 'BOC 2022/21, lunes 31 de enero de 2022, anuncio 319: Decreto 11/2022, de 20 de enero, Registro de Agentes Inmobiliarios de Canarias.',
            'url' => 'https://www.gobiernodecanarias.org/boc/2022/021/001.html',
            'match' => ['Decreto 11/2022'],
        ],
        'urbanismo_cantabria' => [
            'title' => 'Ley 5/2022, de 15 de julio, de Ordenación del Territorio y Urbanismo de Cantabria.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-13844',
            'match' => ['Ley 5/2022'],
        ],
        'urbanismo_cyl' => [
            'title' => 'Ley 5/1999, de 8 de abril, de Urbanismo de Castilla y León.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1999-12599',
            'match' => ['Ley 5/1999'],
        ],
        'reglamento_urbanismo_cyl' => [
            'title' => 'Decreto 22/2004, de 29 de enero, por el que se aprueba el Reglamento de Urbanismo de Castilla y León.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOCL-h-2004-90152',
            'match' => ['Decreto 22/2004'],
        ],
        'trlotup' => [
            'title' => 'Decreto Legislativo 1/2021, de 18 de junio, del Consell de aprobación del texto refundido de la Ley de ordenación del territorio, urbanismo y paisaje.',
            'url' => 'https://www.boe.es/buscar/act.php?id=DOGV-r-2021-90283',
            'match' => ['Decreto Legislativo 1/2021', 'TRLOTUP'],
        ],
        'medidas_fiscales_valencia_2025' => [
            'title' => 'Ley 5/2025, de 30 de mayo, de medidas fiscales, de gestión administrativa y financiera, y de organización de la Generalitat.',
            'url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2025-11959',
            'match' => ['Ley 5/2025'],
        ],
        'lotus' => [
            'title' => 'Ley 11/2018, de 21 de diciembre, de ordenación territorial y urbanística sostenible de Extremadura.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2019-1790',
            'match' => ['Ley 11/2018', 'LOTUS'],
        ],
        'suelo_galicia' => [
            'title' => 'Ley 2/2016, de 10 de febrero, del suelo de Galicia.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2016-3191',
            'match' => ['Ley 2/2016'],
        ],
        'suelo_madrid' => [
            'title' => 'Ley 9/2001, de 17 de julio, del Suelo, de la Comunidad de Madrid.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2001-18984',
            'match' => ['Ley 9/2001'],
        ],
        'reforma_suelo_madrid_2020' => [
            'title' => 'Ley 1/2020, de 8 de octubre, por la que se modifica la Ley 9/2001, de 17 de julio, del Suelo de la Comunidad de Madrid, para el impulso y reactivación de la actividad urbanística.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2021-3494',
            'match' => ['Ley 1/2020'],
        ],
        'urbanismo_navarra' => [
            'title' => 'Decreto Foral Legislativo 1/2017, de 26 de julio, por el que se aprueba el Texto Refundido de la Ley Foral de Ordenación del Territorio y Urbanismo.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2017-12907',
            'match' => ['Decreto Foral Legislativo 1/2017'],
        ],
        'suelo_pais_vasco' => [
            'title' => 'Ley 2/2006, de 30 de junio, de Suelo y Urbanismo.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2011-17400',
            'match' => ['Ley 2/2006'],
        ],
        'urbanismo_rioja' => [
            'title' => 'Ley 5/2006, de 2 de mayo, de Ordenación del Territorio y Urbanismo de La Rioja.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-9008',
            'match' => ['Ley 5/2006'],
        ],
        'trlsru' => [
            'title' => 'Real Decreto Legislativo 7/2015, de 30 de octubre, por el que se aprueba el texto refundido de la Ley de Suelo y Rehabilitación Urbana.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-11723',
            'match' => ['RDL 7/2015', 'TRLSRU'],
        ],
        'loe' => [
            'title' => 'Ley 38/1999, de 5 de noviembre, de Ordenación de la Edificación.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1999-21567',
            'match' => ['Ley 38/1999', 'LOE'],
        ],
        'cte' => [
            'title' => 'Real Decreto 314/2006, de 17 de marzo, por el que se aprueba el Código Técnico de la Edificación.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-5515',
            'match' => ['RD 314/2006', 'CTE', 'Código Técnico de la Edificación'],
        ],
        'cte_2019' => [
            'title' => 'Real Decreto 732/2019, de 20 de diciembre, por el que se modifica el Código Técnico de la Edificación.',
            'url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2019-18528',
            'match' => ['RD 732/2019'],
        ],
        'cte_2022' => [
            'title' => 'Real Decreto 450/2022, de 14 de junio, por el que se modifica el Código Técnico de la Edificación.',
            'url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2022-9848',
            'match' => ['RD 450/2022'],
        ],
        'codigo_estructural' => [
            'title' => 'Real Decreto 470/2021, de 29 de junio, por el que se aprueba el Código Estructural.',
            'url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2021-13681',
            'match' => ['RD 470/2021', 'Código Estructural'],
        ],
        'ncse' => [
            'title' => 'Real Decreto 997/2002, de 27 de septiembre, por el que se aprueba la norma de construcción sismorresistente: parte general y edificación (NCSR-02).',
            'url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2002-19687',
            'match' => ['RD 997/2002', 'NCSE-02'],
        ],
        'seguridad_obras' => [
            'title' => 'Real Decreto 1627/1997, de 24 de octubre, por el que se establecen disposiciones mínimas de seguridad y de salud en las obras de construcción.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1997-22614',
            'match' => ['RD 1627/1997'],
        ],
        'prevencion_riesgos' => [
            'title' => 'Ley 31/1995, de 8 de noviembre, de Prevención de Riesgos Laborales.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1995-24292',
            'match' => ['Ley 31/1995'],
        ],
        'subcontratacion' => [
            'title' => 'Ley 32/2006, de 18 de octubre, reguladora de la subcontratación en el Sector de la Construcción.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-18205',
            'match' => ['Ley 32/2006'],
        ],
        'subcontratacion_reglamento' => [
            'title' => 'Real Decreto 1109/2007, de 24 de agosto, por el que se desarrolla la Ley 32/2006, de 18 de octubre, reguladora de la subcontratación en el Sector de la Construcción.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-15766',
            'match' => ['RD 1109/2007'],
        ],
        'rcd' => [
            'title' => 'Real Decreto 105/2008, de 1 de febrero, por el que se regula la producción y gestión de los residuos de construcción y demolición.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2008-2486',
            'match' => ['RD 105/2008'],
        ],
        'residuos' => [
            'title' => 'Ley 7/2022, de 8 de abril, de residuos y suelos contaminados para una economía circular.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-5809',
            'match' => ['Ley 7/2022'],
        ],
        'tierras_excavadas' => [
            'title' => 'Orden APM/1007/2017, de 10 de octubre, sobre normas generales de valorización de materiales naturales excavados para su utilización en operaciones de relleno y obras distintas a aquéllas en las que se generaron.',
            'url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2017-12043',
            'match' => ['Orden APM/1007/2017', 'APM/1007/2017'],
        ],
        'amianto' => [
            'title' => 'Real Decreto 396/2006, de 31 de marzo, por el que se establecen las disposiciones mínimas de seguridad y salud aplicables a los trabajos con riesgo de exposición al amianto.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-6474',
            'match' => ['RD 396/2006'],
        ],
        'suelos_contaminados' => [
            'title' => 'Real Decreto 9/2005, de 14 de enero, relación de actividades potencialmente contaminantes del suelo y criterios y estándares para la declaración de suelos contaminados.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2005-895',
            'match' => ['RD 9/2005'],
        ],
        'rebt' => [
            'title' => 'Real Decreto 842/2002, de 2 de agosto, por el que se aprueba el Reglamento electrotécnico para baja tensión.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2002-18099',
            'match' => ['RD 842/2002', 'REBT'],
        ],
        'itc_bt_52' => [
            'title' => 'Real Decreto 1053/2014, de 12 de diciembre, ITC BT 52 (recarga de vehículos eléctricos) del Reglamento electrotécnico para baja tensión.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-13681',
            'match' => ['RD 1053/2014'],
        ],
        'rite' => [
            'title' => 'Real Decreto 1027/2007, de 20 de julio, por el que se aprueba el Reglamento de Instalaciones Térmicas en los Edificios.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-15820',
            'match' => ['RD 1027/2007', 'RITE'],
        ],
        'ict' => [
            'title' => 'Real Decreto 346/2011, de 11 de marzo, Reglamento regulador de las infraestructuras comunes de telecomunicaciones.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2011-5834',
            'match' => ['RD 346/2011', 'ICT'],
        ],
        'ripci' => [
            'title' => 'Real Decreto 513/2017, de 22 de mayo, por el que se aprueba el Reglamento de instalaciones de protección contra incendios.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2017-6606',
            'match' => ['RD 513/2017', 'RIPCI'],
        ],
        'gas' => [
            'title' => 'Real Decreto 919/2006, de 28 de julio, Reglamento técnico de distribución y utilización de combustibles gaseosos y sus ITC ICG 01 a 11.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-15345',
            'match' => ['RD 919/2006'],
        ],
        'ascensores_itc' => [
            'title' => 'Real Decreto 88/2013, de 8 de febrero, ITC AEM 1 «Ascensores». DEROGADO por el Real Decreto 355/2024 (BOE-A-2024-7258).',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2013-1969',
            'match' => ['RD 88/2013'],
            'note' => 'Derogado por el Real Decreto 355/2024 (BOE-A-2024-7258); el curso lo cita: lo corrige el formador.',
        ],
        'ascensores_ce' => [
            'title' => 'Real Decreto 203/2016, de 20 de mayo, requisitos esenciales de seguridad para la comercialización de ascensores y componentes de seguridad para ascensores.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2016-4953',
            'match' => ['RD 203/2016'],
        ],
        'gruas_torre' => [
            'title' => 'Real Decreto 836/2003, de 27 de junio, nueva ITC «MIE-AEM-2» del Reglamento de aparatos de elevación y manutención, referente a grúas torre para obras u otras aplicaciones.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2003-14326',
            'match' => ['RD 836/2003'],
        ],
        'piscinas' => [
            'title' => 'Real Decreto 742/2013, de 27 de septiembre, por el que se establecen los criterios técnico-sanitarios de las piscinas.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2013-10580',
            'match' => ['RD 742/2013'],
        ],
        'eficiencia_energetica' => [
            'title' => 'Real Decreto 390/2021, de 1 de junio, por el que se aprueba el procedimiento básico para la certificación de la eficiencia energética de los edificios.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2021-9176',
            'match' => ['RD 390/2021'],
        ],
        'control_calidad' => [
            'title' => 'Real Decreto 410/2010, de 31 de marzo, requisitos exigibles a las entidades de control de calidad de la edificación y a los laboratorios de ensayos.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2010-6368',
            'match' => ['RD 410/2010'],
        ],
        'libro_ordenes' => [
            'title' => 'Decreto 462/1971, de 11 de marzo, por el que se dictan normas sobre la redacción de proyectos y la dirección de obras de edificación.',
            'url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-1971-418',
            'match' => ['Decreto 462/1971'],
        ],
        'visado' => [
            'title' => 'Real Decreto 1000/2010, de 5 de agosto, sobre visado colegial obligatorio.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2010-12618',
            'match' => ['RD 1000/2010'],
        ],
        'propiedad_horizontal' => [
            'title' => 'Ley 49/1960, de 21 de julio, sobre propiedad horizontal.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1960-10906',
            'match' => ['Ley 49/1960', 'LPH'],
        ],
        'consumidores' => [
            'title' => 'Real Decreto Legislativo 1/2007, de 16 de noviembre, texto refundido de la Ley General para la Defensa de los Consumidores y Usuarios y otras leyes complementarias.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-20555',
            'match' => ['RDL 1/2007', 'TRLGDCU'],
        ],
        'informacion_vivienda' => [
            'title' => 'Real Decreto 515/1989, de 21 de abril, sobre protección de los consumidores en cuanto a la información a suministrar en la compra-venta y arrendamiento de viviendas.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1989-11181',
            'match' => ['RD 515/1989'],
        ],
        'credito_inmobiliario' => [
            'title' => 'Ley 5/2019, de 15 de marzo, reguladora de los contratos de crédito inmobiliario.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2019-3814',
            'match' => ['Ley 5/2019'],
        ],
        'blanqueo' => [
            'title' => 'Ley 10/2010, de 28 de abril, de prevención del blanqueo de capitales y de la financiación del terrorismo.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2010-6737',
            'match' => ['Ley 10/2010'],
        ],
        'iva' => [
            'title' => 'Ley 37/1992, de 28 de diciembre, del Impuesto sobre el Valor Añadido.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'match' => ['Ley 37/1992', 'LIVA'],
        ],
        'impuesto_sociedades' => [
            'title' => 'Ley 27/2014, de 27 de noviembre, del Impuesto sobre Sociedades.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
            'match' => ['Ley 27/2014', 'LIS'],
        ],
        'ley7_2024' => [
            'title' => 'Ley 7/2024, de 20 de diciembre, Impuesto Complementario, Impuesto sobre el margen de intereses y comisiones, Impuesto sobre los líquidos para cigarrillos electrónicos, y se modifican otras normas tributarias.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2024-26694',
            'match' => ['Ley 7/2024'],
        ],
        'itpajd' => [
            'title' => 'Real Decreto Legislativo 1/1993, de 24 de septiembre, Texto refundido de la Ley del Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359',
            'match' => ['RDL 1/1993'],
        ],
        'haciendas_locales' => [
            'title' => 'Real Decreto Legislativo 2/2004, de 5 de marzo, texto refundido de la Ley Reguladora de las Haciendas Locales.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2004-4214',
            'match' => ['RDL 2/2004', 'TRLRHL'],
        ],
        'plusvalia_2021' => [
            'title' => 'Real Decreto-ley 26/2021, de 8 de noviembre, por el que se adapta el TRLRHL a la jurisprudencia del Tribunal Constitucional respecto del IIVTNU.',
            'url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2021-18276',
            'match' => ['RDL 26/2021'],
        ],
        'sociedades_capital' => [
            'title' => 'Real Decreto Legislativo 1/2010, de 2 de julio, texto refundido de la Ley de Sociedades de Capital.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2010-10544',
            'match' => ['RDL 1/2010', 'LSC'],
        ],
        'crecimiento_empresas' => [
            'title' => 'Ley 18/2022, de 28 de septiembre, de creación y crecimiento de empresas.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-15818',
            'match' => ['Ley 18/2022'],
        ],
        'prestamo_participativo' => [
            'title' => 'Real Decreto-ley 7/1996, de 7 de junio, sobre medidas urgentes de carácter fiscal y de fomento y liberalización de la actividad económica.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1996-13002',
            'match' => ['RDL 7/1996'],
        ],
        'vivienda' => [
            'title' => 'Ley 12/2023, de 24 de mayo, por el derecho a la vivienda.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2023-12203',
            'match' => ['Ley 12/2023'],
        ],
        'arancel_notarios' => [
            'title' => 'Real Decreto 1426/1989, de 17 de noviembre, por el que se aprueba el Arancel de los Notarios.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1989-28111',
            'match' => ['RD 1426/1989'],
        ],
        'reforma_hipotecaria_catastral' => [
            'title' => 'Ley 13/2015, de 24 de junio, de Reforma de la Ley Hipotecaria y del texto refundido de la Ley de Catastro Inmobiliario.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-7046',
            'match' => ['Ley 13/2015'],
        ],
        'valoracion_hipotecaria' => [
            'title' => 'Orden ECO/805/2003, de 27 de marzo, sobre normas de valoración de bienes inmuebles y de determinados derechos para ciertas finalidades financieras.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2003-7253',
            'match' => ['Orden ECO/805/2003', 'ECO/805/2003'],
        ],
        'valoraciones_suelo' => [
            'title' => 'Real Decreto 1492/2011, de 24 de octubre, por el que se aprueba el Reglamento de valoraciones de la Ley de Suelo.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2011-17629',
            'match' => ['RD 1492/2011'],
        ],
        'contratos_publicos_reglamento' => [
            'title' => 'Real Decreto 1098/2001, de 12 de octubre, por el que se aprueba el Reglamento general de la Ley de Contratos de las Administraciones Públicas.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2001-19995',
            'match' => ['RD 1098/2001'],
        ],
        'procedimiento_administrativo' => [
            'title' => 'Ley 39/2015, de 1 de octubre, del Procedimiento Administrativo Común de las Administraciones Públicas.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-10565',
            'match' => ['Ley 39/2015'],
        ],
        'evaluacion_ambiental' => [
            'title' => 'Ley 21/2013, de 9 de diciembre, de evaluación ambiental.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2013-12913',
            'match' => ['Ley 21/2013'],
        ],
        'dominio_publico_hidraulico' => [
            'title' => 'Real Decreto 849/1986, de 11 de abril, por el que se aprueba el Reglamento del Dominio Público Hidráulico.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1986-10638',
            'match' => ['RD 849/1986'],
        ],
        'costas' => [
            'title' => 'Ley 22/1988, de 28 de julio, de Costas.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1988-18762',
            'match' => ['Ley 22/1988'],
        ],
        'carreteras' => [
            'title' => 'Ley 37/2015, de 29 de septiembre, de carreteras.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-10439',
            'match' => ['Ley 37/2015'],
        ],
        'ferroviario' => [
            'title' => 'Ley 38/2015, de 29 de septiembre, del sector ferroviario.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-10440',
            'match' => ['Ley 38/2015'],
        ],
        'navegacion_aerea' => [
            'title' => 'Ley 48/1960, de 21 de julio, sobre Navegación Aérea.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1960-10905',
            'match' => ['Ley 48/1960'],
        ],
        'servidumbres_aeronauticas_2013' => [
            'title' => 'Real Decreto 297/2013, de 26 de abril, por el que se modifica el Decreto 584/1972 de Servidumbres Aeronáuticas y el Real Decreto 2591/1998.',
            'url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2013-5166',
            'match' => ['RD 297/2013'],
            'note' => 'Modificó el Decreto 584/1972, derogado por el Real Decreto 369/2023 (BOE-A-2023-13092); el curso lo cita: lo corrige el formador.',
        ],
        'patrimonio_historico' => [
            'title' => 'Ley 16/1985, de 25 de junio, del Patrimonio Histórico Español.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1985-12534',
            'match' => ['Ley 16/1985'],
        ],
        'apertura_centro_trabajo' => [
            'title' => 'Orden TIN/1071/2010, de 27 de abril, sobre los requisitos y datos que deben reunir las comunicaciones de apertura o de reanudación de actividades en los centros de trabajo.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2010-6871',
            'match' => ['Orden TIN/1071/2010', 'TIN/1071/2010'],
        ],
        'seguros_solvencia' => [
            'title' => 'Ley 20/2015, de 14 de julio, de ordenación, supervisión y solvencia de las entidades aseguradoras y reaseguradoras.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-7897',
            'match' => ['Ley 20/2015'],
        ],
        'morosidad' => [
            'title' => 'Ley 3/2004, de 29 de diciembre, por la que se establecen medidas de lucha contra la morosidad en las operaciones comerciales.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2004-21830',
            'match' => ['Ley 3/2004'],
        ],
        'pagos_efectivo' => [
            'title' => 'Ley 7/2012, de 29 de octubre, de modificación de la normativa tributaria y presupuestaria y de adecuación de la normativa financiera para la intensificación de las actuaciones en la prevención y lucha contra el fraude.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-13416',
            'match' => ['Ley 7/2012'],
        ],
        'fraude_fiscal' => [
            'title' => 'Ley 11/2021, de 9 de julio, de medidas de prevención y lucha contra el fraude fiscal.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2021-11473',
            'match' => ['Ley 11/2021'],
        ],
        'inscripcion_urbanistica' => [
            'title' => 'Real Decreto 1093/1997, de 4 de julio, normas complementarias al Reglamento Hipotecario sobre inscripción en el Registro de la Propiedad de actos de naturaleza urbanística.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1997-16469',
            'match' => ['RD 1093/1997'],
        ],
        'catastro' => [
            'title' => 'Real Decreto Legislativo 1/2004, de 5 de marzo, por el que se aprueba el texto refundido de la Ley del Catastro Inmobiliario.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2004-4163',
            'match' => ['RDL 1/2004', 'TRLCI'],
        ],
        'rehabilitacion_urbana' => [
            'title' => 'Ley 8/2013, de 26 de junio, de rehabilitación, regeneración y renovación urbanas.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2013-6938',
            'match' => ['Ley 8/2013'],
        ],
        'ley_general_tributaria' => [
            'title' => 'Ley 58/2003, de 17 de diciembre, General Tributaria.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2003-23186',
            'match' => ['Ley General Tributaria', 'LGT'],
        ],
        'codigo_civil' => [
            'title' => 'Real Decreto de 24 de julio de 1889 por el que se publica el Código Civil.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1889-4763',
            'match' => ['Código Civil'],
        ],
        'codigo_comercio' => [
            'title' => 'Real Decreto de 22 de agosto de 1885 por el que se publica el Código de Comercio.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1885-6627',
            'match' => ['Código de Comercio'],
        ],
        'ley_hipotecaria' => [
            'title' => 'Decreto de 8 de febrero de 1946 por el que se aprueba la nueva redacción oficial de la Ley Hipotecaria.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1946-2453',
            'match' => ['Ley Hipotecaria'],
        ],
        'reglamento_hipotecario' => [
            'title' => 'Decreto de 14 de febrero de 1947 por el que se aprueba el Reglamento Hipotecario.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1947-3843',
            'match' => ['Reglamento Hipotecario'],
        ],
        'estatuto_trabajadores' => [
            'title' => 'Real Decreto Legislativo 2/2015, de 23 de octubre, por el que se aprueba el texto refundido de la Ley del Estatuto de los Trabajadores.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-11430',
            'match' => ['Estatuto de los Trabajadores'],
        ],
        'reglamento_notarial' => [
            'title' => 'Decreto de 2 de junio de 1944 por el que se aprueba con carácter definitivo el Reglamento de la organización y régimen del Notariado.',
            'url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1944-6578',
            'match' => ['Reglamento Notarial'],
        ],
    ],

    // Per autonomous community (the course's own keys: datos/partners_por_provincia.json).
    'regions' => [
        'AND' => [
            'name' => 'Andalucía',
            'planning_law' => 'lista',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.juntadeandalucia.es/organismos/fomentoarticulaciondelterritorioyvivienda/areas/vivienda-rehabilitacion/planes-instrumentos/paginas/plan-vive-precios-maximo-vp.html', 'title' => 'Precios máximos de las Viviendas Protegidas - Junta de Andalucía', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://www.juntadeandalucia.es/organismos/economiahaciendayfondoseuropeos/areas/tributos-juego/tributos/paginas/impuestos-cedidos-transmisiones.html', 'title' => 'Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados - Junta de Andalucía', 'lang' => 'es'],
            ],
        ],
        'ARA' => [
            'name' => 'Aragón',
            'planning_law' => 'urbanismo_aragon',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.aragon.es/-/otras-gestiones-en-materia-de-vivienda', 'title' => 'Vivienda protegida: promoción, alquiler y venta. Gobierno de Aragón', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://www.aragon.es/administracion-tributaria-de-aragon/impuestos/transmisiones-patrimoniales-y-actos-juridicos-documentados', 'title' => 'Transmisiones patrimoniales y actos jurídicos documentados. Gobierno de Aragón (Administración Tributaria de Aragón)', 'lang' => 'es'],
            ],
        ],
        'AST' => [
            'name' => 'Principado de Asturias',
            'planning_law' => 'trotu',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.asturias.es/temas/vivienda-territorio/vivienda-publica-y-protegida/venta-vivienda-protegida', 'title' => 'Venta de vivienda protegida - Principado de Asturias', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://sede.tributasenasturias.es/sites/sede/default/es_ES/Que-quieres-hacer/Transmisiones-Patrimoniales-y-AJD', 'title' => 'Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados - Sede electrónica de Servicios Tributarios del Principado de Asturias', 'lang' => 'es'],
            ],
        ],
        'BAL' => [
            'name' => 'Illes Balears',
            'planning_law' => 'luib',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.caib.es/sites/habitatgesprotegits/ca/preus_maxims_venda_i_arrendament/', 'title' => 'DG.HAB - Informació per realitzar tràmits d\'Habitatges Protegits - Preus màxims venda i arrendament (Govern de les Illes Balears)', 'lang' => 'ca'],
                ['kind' => 'taxes', 'url' => 'https://www.atib.es/TA/contenido.aspx?Id=9854&lang=es', 'title' => 'Agència Tributària de les Illes Balears (ATIB) · Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados', 'lang' => 'es'],
            ],
        ],
        'CAN' => [
            'name' => 'Canarias',
            'planning_law' => 'suelo_canarias',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://sede.gobiernodecanarias.org/sede/tramites/3903', 'title' => 'Información relativa al precio máximo de venta y renta aplicable a viviendas protegidas de promoción privada - Sede electrónica del Gobierno de Canarias', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://www3.gobiernodecanarias.org/tributos/atc/estatico/asistencia_contribuyente/guias/pdf/guia_completa_itp_y_ajd.pdf', 'title' => 'Agencia Tributaria Canaria · Guía completa del ITP y AJD', 'lang' => 'es'],
            ],
        ],
        'CNT' => [
            'name' => 'Cantabria',
            'planning_law' => 'urbanismo_cantabria',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.cantabria.es/web/comunicados/w/el-ejecutivo-actualiza-el-m%C3%B3dulo-de-vpo-para-ejecutar-el-plan-de-construcci%C3%B3n-de-vivienda-p%C3%BAblica-m%C3%A1s-ambicioso-de-la-historia-de-cantabria-', 'title' => 'El Ejecutivo actualiza el módulo de VPO (Gobierno de Cantabria, comunicado: módulo básico autonómico y precios máximos por zona)', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://sede.cantabria.es/sede/catalogo-de-tramites/tramite/impuesto-sobre-transmisiones-patrimoniales-y-actos-juridicos-documentados/530', 'title' => 'Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados - Sede electrónica del Gobierno de Cantabria', 'lang' => 'es'],
            ],
        ],
        'CLM' => [
            'name' => 'Castilla-La Mancha',
            'planning_law' => 'trlotau',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://vivienda.castillalamancha.es/compraventa-de-vivienda-protegida', 'title' => 'Compraventa de vivienda protegida (Vivienda Castilla-La Mancha) — con calculadora de precio máximo de venta', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://portaltributario.jccm.es/modelo-600-instrucciones-para-su-cumplimentacion', 'title' => 'Modelo 600 - Instrucciones para su cumplimentación (Portal Tributario de Castilla-La Mancha, con la tabla de tipos)', 'lang' => 'es'],
            ],
        ],
        'CYL' => [
            'name' => 'Castilla y León',
            'planning_law' => 'urbanismo_cyl',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://vivienda.jcyl.es/web/jcyl/binarios/168/773/PRECIOS%20MAXIMOS%20VENTA%202025%20.pdf?blobheader=application/pdf%3Bcharset%3DUTF-8&blobnocache=true', 'title' => 'Junta de Castilla y León · precios máximos de venta 2025 (módulo básico estatal 758 €)', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://tributos.jcyl.es/web/es/informacion-tributaria/transmisiones-patrimoniales-onerosas.html', 'title' => 'Transmisiones Patrimoniales Onerosas (Tributos, Junta de Castilla y León)', 'lang' => 'es'],
            ],
        ],
        'CAT' => [
            'name' => 'Cataluña',
            'planning_law' => 'urbanismo_cataluna',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://habitatge.gencat.cat/ca/ambits/preus-ingressos-i-zones/preus-maxims-de-venda-i-renda-dels-habitatges-protegits/habitatges-nous/', 'title' => 'Preus d\'habitatges nous. Habitatge (Generalitat de Catalunya)', 'lang' => 'ca'],
                ['kind' => 'housing', 'url' => 'https://habitatge.gencat.cat/ca/ambits/preus-ingressos-i-zones/preus-maxims-de-venda-i-renda-dels-habitatges-protegits/preus-de_venda_dhabitatges-en-segones-i-posteriors-transmissions/', 'title' => 'Preus de venda d\'habitatges en segones i posteriors transmissions. Habitatge (Generalitat de Catalunya)', 'lang' => 'ca'],
                ['kind' => 'taxes', 'url' => 'https://atc.gencat.cat/ca/tributs/itpajd/tpo/tarifes-tipus/', 'title' => 'Tarifes, tipus impositius i quota. Agència Tributària de Catalunya', 'lang' => 'ca'],
            ],
        ],
        'VAL' => [
            'name' => 'Comunitat Valenciana',
            'planning_law' => 'trlotup',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://habitatge.gva.es/es/web/vivienda-y-calidad-en-la-edificacion/preus-maxims-habitatge-protegit-segona-o-posteriors-transmissions', 'title' => 'Generalitat Valenciana · Vivienda y Calidad en la Edificación: precios máximos de vivienda protegida en segunda o posteriores transmisiones', 'lang' => 'es'],
                ['kind' => 'housing', 'url' => 'https://sede.gva.es/es/detall-tramit?id_proc=113', 'title' => 'Solicitud de información de los precios máximos de venta y renta de viviendas protegidas y del plazo de protección - Sede electrónica - Generalitat Valenciana', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://atv.gva.es/es/itpajd', 'title' => 'Impuesto sobre transmisiones patrimoniales onerosas y actos jurídicos documentados - ATV - Generalitat Valenciana', 'lang' => 'es'],
            ],
        ],
        'EXT' => [
            'name' => 'Extremadura',
            'planning_law' => 'lotus',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://doe.juntaex.es/pdfs/doe/2026/160o/26060174.pdf', 'title' => 'DOE núm. 16, lunes 26 de enero de 2026: actualización de los precios máximos de vivienda protegida para 2026', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://portaltributario.juntaex.es/PortalTributario/web/guest/transmisiones-patrimoniales-onerosas', 'title' => 'Portal Tributario (Junta de Extremadura) · Transmisiones patrimoniales onerosas', 'lang' => 'es'],
            ],
        ],
        'GAL' => [
            'name' => 'Galicia',
            'planning_law' => 'suelo_galicia',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.xunta.gal/dog/Publicados/2026/20260518/AnuncioC3Q2-070526-0001_es.html', 'title' => 'DOG 90 del 18/5/2026 - Resolución de 6 de mayo de 2026 (Acuerdo del Consello de la Xunta de 4 de mayo de 2026): ingresos para acceder a las viviendas protegidas y sus precios máximos de venta y renta', 'lang' => 'es'],
                ['kind' => 'housing', 'url' => 'https://igvs.xunta.gal/es/vivienda-protegida/informacion-de-interes-sobre-vivienda-protegida', 'title' => 'Información de interés sobre vivienda protegida (IGVS, Xunta de Galicia)', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://www.atriga.gal/es_ES/transmisiones-patrimoniales-onerosas', 'title' => 'Transmisiones patrimoniales onerosas - Axencia Tributaria de Galicia (ATRIGA)', 'lang' => 'es'],
            ],
        ],
        'MAD' => [
            'name' => 'Comunidad de Madrid',
            'planning_law' => 'suelo_madrid',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.comunidad.madrid/docs/assets/2026/02/03/viv_2026_precios_max_privada_d74_09.pdf', 'title' => 'Comunidad de Madrid · PRECIOS 2026 VPPB Y VPPL (precios máximos de venta por zona)', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://www.comunidad.madrid/atencion-contribuyente/transmisiones-patrimoniales-onerosas', 'title' => 'Transmisiones Patrimoniales Onerosas (Comunidad de Madrid, atención al contribuyente)', 'lang' => 'es'],
            ],
        ],
        'MUR' => [
            'name' => 'Región de Murcia',
            'planning_law' => 'urbanismo_murcia',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.carm.es/web/descarga?ARCHIVO=Orden+de+29+de+febrero+de+2024+determinan+los+precios+m%C3%A1ximos+aplicables+a+las+viviendas+protegidas+energ%C3%A9ticamente+eficientes+.pdf&ALIAS=ARCH&IDCONTENIDO=189256&IDTIPO=60&RASTRO=c468$m37247', 'title' => 'BORM núm. 54, 5 de marzo de 2024 · Orden de 29 de febrero de 2024: precios máximos aplicables a las viviendas protegidas energéticamente eficientes', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://agenciatributaria.carm.es/en/-/novedades-en-materia-de-tributos-aprobadas-en-la-ley-de-presupuestos-generales-de-la-comunidad-autonoma-de-la-region-de-murcia-para-el-ano-2025', 'title' => 'Novedades en materia de tributos aprobadas en la Ley de Presupuestos Generales de la Región de Murcia para 2025 - Agencia Tributaria de la Región de Murcia (ATRM)', 'lang' => 'es'],
            ],
        ],
        'NAV' => [
            'name' => 'Comunidad Foral de Navarra',
            'planning_law' => 'urbanismo_navarra',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.navarra.es/es/tramites/on/-/line/calcular-los-precios-maximos-de-venta-y-alquiler-y-la-duracion-del-regimen-de-proteccion-de-las-viviendas-protegidas', 'title' => 'Calcular los precios máximos de venta y alquiler, y la duración del régimen de protección de las viviendas protegidas (navarra.es)', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://www.navarra.es/home_es/Gobierno+de+Navarra/Organigrama/Los+departamentos/Economia+y+Hacienda/Organigrama/Estructura+Organica/Hacienda/Informacion+Fiscal/Preguntas+mas+frecuentes/Impuesto+sobre+transmisiones+patrimoniales+y+AJD/Preguntas+comunes/resumen.htm', 'title' => 'Preguntas comunes. Resumen modelos de impresos y tipos de gravamen - Hacienda Foral de Navarra (navarra.es)', 'lang' => 'es'],
            ],
        ],
        'PVA' => [
            'name' => 'País Vasco',
            'planning_law' => 'suelo_pais_vasco',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.euskadi.eus/gobierno-vasco/-/etxe-precios-maximos-cas/', 'title' => 'Resumen de precios máximos del año 2026 - Gobierno Vasco - Euskadi.eus', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://web.araba.eus/es/hacienda/transmisiones-y-actos-juridicos-documentados', 'title' => 'Transmisiones y actos jurídicos documentados - Hacienda, Diputación Foral de Álava', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://www.bizkaia.eus/documents/1135059/1352576/ITP+Tarifa.pdf/c4388ca6-7611-2afd-d3a8-c06f1a0806fd', 'title' => 'Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados · tarifa (Diputación Foral de Bizkaia)', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://www.gipuzkoa.eus/es/web/ogasuna/impuestos/modelo/60t/tipos-impositivos', 'title' => 'Tipos impositivos - Hacienda - Diputación Foral de Gipuzkoa', 'lang' => 'es'],
            ],
        ],
        'RIO' => [
            'name' => 'La Rioja',
            'planning_law' => 'urbanismo_rioja',
            'links' => [
                ['kind' => 'housing', 'url' => 'https://www.larioja.org/vivienda/es/proteccion-oficial/proteccion-oficial/precio-maximo-vpo', 'title' => 'Precio Máximo VPO - Vivienda - Portal del Gobierno de La Rioja', 'lang' => 'es'],
                ['kind' => 'taxes', 'url' => 'https://www.larioja.org/tributos/es/tributos-cedidos/impuesto-transmisiones-patrimoniales-actos-juridicos-docume/informacion/transmisiones-patrimoniales-onerosas', 'title' => 'Transmisiones Patrimoniales Onerosas - Tributos - Portal del Gobierno de La Rioja', 'lang' => 'es'],
            ],
        ],
        'CEU' => [
            'name' => 'Ciudad Autónoma de Ceuta',
            'planning_law' => 'trlsru',
            'links' => [
                ['kind' => 'taxes', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/declaraciones-informativas-otros-impuestos-tasas/impuesto-sobre-transmisiones-patrimoniales-actos-juridicos/itp-ajd-transmisiones-derechos-reales-fianzas.html', 'title' => 'Agencia Tributaria: ITP y AJD - Transmisiones (modelo 600), con la bonificación del 50 % en Ceuta y Melilla (art. 57 bis)', 'lang' => 'es'],
            ],
        ],
        'MEL' => [
            'name' => 'Ciudad Autónoma de Melilla',
            'planning_law' => 'trlsru',
            'links' => [
                ['kind' => 'taxes', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/declaraciones-informativas-otros-impuestos-tasas/impuesto-sobre-transmisiones-patrimoniales-actos-juridicos/itp-ajd-transmisiones-derechos-reales-fianzas.html', 'title' => 'Agencia Tributaria: ITP y AJD - Transmisiones (modelo 600), con la bonificación del 50 % en Ceuta y Melilla (art. 57 bis)', 'lang' => 'es'],
            ],
        ],
    ],
];
