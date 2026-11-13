<?php

/*
|--------------------------------------------------------------------------
| What each lesson sends the student to, under its text
|--------------------------------------------------------------------------
|
| Keyed by the lesson's number ('1.1', 'F.3'), as its heading says.
|
| - `links`: the websites the lesson names, each at its official address
|   (checked one by one: docs/enlaces-de-las-lecciones.md). `mention` is the
|   lesson's own words for it: LessonResourcesTest fails if the text does not
|   say them, so a link never stands for something the course does not name.
|   Laws are not here: they are linked inside the text (config/legal.php).
| - `companies`: the kinds of company of the directory the lesson calls on
|   (config/directory.php), listed under the links behind the module's gate.
| - `partners`: the kinds of the academy's own partners (Partner::CATEGORIES)
|   attached to the module that the lesson calls on.
|
| The course text, its videos and its order are the instructor's and do not
| change: this only adds what to open and whom to call, below the lesson.
*/

return [
    'lessons' => [
        '1.1' => [
            'links' => [
                ['name' => 'Idealista', 'url' => 'https://www.idealista.com/', 'mention' => 'Idealista'],
                ['name' => 'Fotocasa · terrenos en venta', 'url' => 'https://www.fotocasa.es/es/comprar/terrenos/espana/todas-las-zonas/l', 'mention' => 'Fotocasa'],
                ['name' => 'Solvia', 'url' => 'https://www.solvia.es/', 'mention' => 'Solvia'],
                ['name' => 'Servihabitat', 'url' => 'https://www.servihabitat.com/es/', 'mention' => 'Servihabitat'],
                ['name' => 'Altamira Inmuebles', 'url' => 'https://www.altamirainmuebles.com/index.html', 'mention' => 'Altamira'],
                ['name' => 'Aliseda Inmobiliaria', 'url' => 'https://www.alisedainmobiliaria.com/', 'mention' => 'Aliseda'],
                ['name' => 'SAREB', 'url' => 'https://www.sareb.es/', 'mention' => 'SAREB'],
                ['name' => 'Portal de Subastas del BOE', 'url' => 'https://subastas.boe.es/', 'mention' => 'Subastas: BOE'],
                ['name' => 'Agencia Tributaria · subastas', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/deudas-apremios-embargos-subastas/subastas/general.html', 'mention' => 'AEAT'],
                ['name' => 'Seguridad Social · subastas de bienes embargados', 'url' => 'https://w6.seg-social.es/subastas/', 'mention' => 'Seguridad Social'],
                ['name' => 'CASA47', 'url' => 'https://www.casa47.es/', 'mention' => 'CASA47'],
                ['name' => 'Sede Electrónica del Catastro', 'url' => 'https://www.sedecatastro.gob.es/', 'mention' => 'Catastro'],
            ],
            'companies' => ['agencias_inmobiliarias'],
            'partners' => ['inmobiliaria'],
        ],
        '1.2' => [
            'links' => [
                ['name' => 'Sistema Nacional de Cartografía de Zonas Inundables (SNCZI, MITECO)', 'url' => 'https://www.miteco.gob.es/es/agua/temas/gestion-de-los-riesgos-de-inundacion/snczi.html', 'mention' => 'SNCZI'],
            ],
            'companies' => ['arquitectura'],
            'partners' => ['arquitectura'],
        ],
        '1.3' => [
            'links' => [
                ['name' => 'Registradores de España · nota simple', 'url' => 'https://sede.registradores.org/sede/sede-corpme-web/registro-de-la-propiedad/publicidad/nota-simple-informativa', 'mention' => 'Nota simple'],
                ['name' => 'Sede Electrónica del Catastro', 'url' => 'https://www.sedecatastro.gob.es/', 'mention' => 'Catastro'],
                ['name' => 'AESA · servidumbres aeronáuticas', 'url' => 'https://www.seguridadaerea.gob.es/es/ambitos/servidumbres-aeronauticas', 'mention' => 'AESA'],
            ],
            'companies' => ['abogados', 'ingenieria'],
            'partners' => ['abogado', 'arquitectura'],
        ],
        '1.4' => [
            'links' => [
                ['name' => 'Registro Mercantil Central · denominaciones sociales', 'url' => 'https://www.rmc.es/IntroDenominaciones.aspx', 'mention' => 'Registro Mercantil Central'],
                ['name' => 'Agencia Tributaria · sede electrónica (modelo 036)', 'url' => 'https://sede.agenciatributaria.gob.es/', 'mention' => 'modelo 036'],
                ['name' => 'CIRCE · crear la sociedad en línea (PAE Electrónico)', 'url' => 'https://paeelectronico.es/es-es/CreaEmpresaPorTiMismo/Paginas/CIRCE.aspx', 'mention' => 'CIRCE / PAE'],
            ],
            'companies' => ['abogados', 'asesoria_contable'],
            'partners' => ['notario', 'asesor_fiscal', 'abogado'],
        ],
        '2.2' => [
            'links' => [
                ['name' => 'Registradores · Estadística Registral Inmobiliaria', 'url' => 'https://www.registradores.org/actualidad/portal-estadistico-registral/estadisticas-de-propiedad', 'mention' => 'Registradores'],
                ['name' => 'Portal Estadístico del Notariado', 'url' => 'https://penotariado.com/', 'mention' => 'Notarios'],
                ['name' => 'Tinsa · informes', 'url' => 'https://www.tinsa.es/informes/', 'mention' => 'Tinsa'],
                ['name' => 'Sociedad de Tasación', 'url' => 'https://www.st-tasacion.es/', 'mention' => 'Sociedad de Tasación'],
                ['name' => 'Gloval · informes', 'url' => 'https://www.gloval.es/informes/', 'mention' => 'Gloval'],
                ['name' => 'CBRE España', 'url' => 'https://www.cbre.es/', 'mention' => 'CBRE'],
                ['name' => 'JLL España · estudios', 'url' => 'https://www.jll.com/es-es/insights', 'mention' => 'JLL'],
                ['name' => 'Savills España · research', 'url' => 'https://www.savills.es/publicaciones/research.aspx?rc=Spain&p=&t=&f=date&q=&page=1', 'mention' => 'Savills'],
                ['name' => 'Colliers España', 'url' => 'https://www.colliers.com/es-es', 'mention' => 'Colliers'],
            ],
            'companies' => ['arquitectura'],
            'partners' => ['arquitectura'],
        ],
        '2.5' => [
            'companies' => ['abogados'],
            'partners' => ['abogado', 'notario', 'banco'],
        ],
        '3.1' => [
            'companies' => ['arquitectura', 'ingenieria'],
            'partners' => ['arquitectura'],
        ],
        '3.3' => [
            'links' => [
                ['name' => 'Sede Electrónica del Catastro', 'url' => 'https://www.sedecatastro.gob.es/', 'mention' => 'Catastro'],
            ],
            'companies' => ['arquitectura', 'ingenieria'],
            'partners' => ['arquitectura'],
        ],
        '4.1' => [
            'companies' => ['arquitectura', 'abogados'],
            'partners' => ['arquitectura', 'abogado'],
        ],
        '4.2' => [
            'links' => [
                ['name' => 'Confederaciones Hidrográficas (MITECO)', 'url' => 'https://www.miteco.gob.es/es/ministerio/organizacion/organismos-publicos/confederaciones-hidrograficas.html', 'mention' => 'Confederación Hidrográfica'],
                ['name' => 'Costas · zonas legales del litoral (MITECO)', 'url' => 'https://www.miteco.gob.es/es/costas/temas/proteccion-costa/conociendo-litoral/zonas-litorales-espanolas/clasificacion-legal/litoral-en-legislacion.html', 'mention' => 'Costas'],
                ['name' => 'ADIF', 'url' => 'https://www.adif.es/', 'mention' => 'ADIF'],
                ['name' => 'AESA · servidumbres aeronáuticas', 'url' => 'https://www.seguridadaerea.gob.es/es/ambitos/servidumbres-aeronauticas', 'mention' => 'AESA'],
            ],
        ],
        '4.3' => [
            'links' => [
                ['name' => 'Registro de Empresas Acreditadas (REA) · consulta pública', 'url' => 'https://expinterweb.mites.gob.es/rea/pub/consulta.htm', 'mention' => 'REA'],
            ],
        ],
        '5.1' => [
            'companies' => ['banca_financiacion'],
            'partners' => ['banco'],
        ],
        '5.2' => [
            'companies' => ['seguros'],
            'partners' => ['aseguradora'],
        ],
        '5.3' => [
            'links' => [
                ['name' => 'Registradores de España · Registro Mercantil (cuentas anuales)', 'url' => 'https://www.registradores.org/', 'mention' => 'Registro Mercantil'],
                ['name' => 'Registro de Empresas Acreditadas (REA) · consulta pública', 'url' => 'https://expinterweb.mites.gob.es/rea/pub/consulta.htm', 'mention' => 'REA'],
                ['name' => 'Agencia Tributaria · sede electrónica', 'url' => 'https://sede.agenciatributaria.gob.es/', 'mention' => 'AEAT'],
                ['name' => 'Seguridad Social · certificado de estar al corriente', 'url' => 'https://sede.seg-social.gob.es/wps/portal/sede/sede/EmpresasyProfesionales/recaudacion/n201736', 'mention' => 'Seguridad Social'],
            ],
            'companies' => ['constructoras'],
            'partners' => ['constructora'],
        ],
        '5.4' => [
            'links' => [
                ['name' => 'Registro de Empresas Acreditadas (REA) · consulta pública', 'url' => 'https://expinterweb.mites.gob.es/rea/pub/consulta.htm', 'mention' => 'REA'],
                ['name' => 'Agencia Tributaria · sede electrónica', 'url' => 'https://sede.agenciatributaria.gob.es/', 'mention' => 'AEAT'],
                ['name' => 'Seguridad Social · certificado de estar al corriente', 'url' => 'https://sede.seg-social.gob.es/wps/portal/sede/sede/EmpresasyProfesionales/recaudacion/n201736', 'mention' => 'TGSS'],
            ],
            'companies' => ['constructoras', 'abogados'],
            'partners' => ['constructora', 'abogado'],
        ],
        '6.1' => [
            'companies' => ['demolicion_movimiento_tierras', 'constructoras'],
            'partners' => ['constructora'],
        ],
        '6.2' => [
            'companies' => ['cubiertas_estructuras', 'acabados'],
        ],
        '6.3' => [
            'companies' => ['instalaciones'],
        ],
        '6.4' => [
            'companies' => ['acabados', 'obra_civil'],
        ],
        '7.1' => [
            'companies' => ['arquitectura', 'ingenieria'],
            'partners' => ['arquitectura'],
        ],
        '7.2' => [
            'links' => [
                ['name' => 'Bureau Veritas', 'url' => 'https://www.bureauveritas.es/', 'mention' => 'Bureau Veritas'],
                ['name' => 'Applus+', 'url' => 'https://www.applus.com/es/es/', 'mention' => 'Applus+'],
                ['name' => 'SGS', 'url' => 'https://www.sgs.com/es-es', 'mention' => 'SGS'],
                ['name' => 'Eurocontrol', 'url' => 'https://www.eurocontrol.es/', 'mention' => 'Eurocontrol'],
            ],
            'companies' => ['control_tecnico'],
        ],
        '8.1' => [
            'links' => [
                ['name' => 'Idealista', 'url' => 'https://www.idealista.com/', 'mention' => 'Idealista'],
                ['name' => 'Fotocasa', 'url' => 'https://www.fotocasa.es/es/', 'mention' => 'Fotocasa'],
                ['name' => 'pisos.com', 'url' => 'https://www.pisos.com/', 'mention' => 'pisos.com'],
            ],
            'companies' => ['marketing', 'agencias_inmobiliarias'],
            'partners' => ['marketing', 'inmobiliaria'],
        ],
        '8.2' => [
            'companies' => ['agencias_inmobiliarias'],
            'partners' => ['inmobiliaria'],
        ],
        '8.3' => [
            'companies' => ['abogados'],
            'partners' => ['abogado', 'notario'],
        ],
        '9.2' => [
            'companies' => ['abogados'],
            'partners' => ['notario', 'abogado'],
        ],
        '9.3' => [
            'links' => [
                ['name' => 'Sede Electrónica del Catastro', 'url' => 'https://www.sedecatastro.gob.es/', 'mention' => 'Catastro'],
            ],
        ],
        '10.1' => [
            'companies' => ['acabados', 'limpieza_mantenimiento'],
        ],
        '10.3' => [
            'partners' => ['notario'],
        ],
        '11.1' => [
            'partners' => ['notario', 'banco'],
        ],
        '11.2' => [
            'links' => [
                ['name' => 'Agencia Tributaria · sede electrónica (NIF de la comunidad)', 'url' => 'https://sede.agenciatributaria.gob.es/', 'mention' => 'AEAT'],
            ],
            'companies' => ['administracion_fincas'],
        ],
        '11.5' => [
            'links' => [
                ['name' => 'Sede Electrónica del Catastro', 'url' => 'https://www.sedecatastro.gob.es/', 'mention' => 'Catastro'],
            ],
        ],
        '12.1' => [
            'companies' => ['administracion_fincas'],
        ],
        '12.2' => [
            'companies' => ['limpieza_mantenimiento', 'acabados', 'instalaciones'],
        ],
        '12.3' => [
            'companies' => ['asesoria_contable'],
            'partners' => ['asesor_fiscal'],
        ],
        '12.4' => [
            'links' => [
                ['name' => 'Registradores de España · Registro Mercantil', 'url' => 'https://www.registradores.org/', 'mention' => 'Registro Mercantil'],
                ['name' => 'Agencia Tributaria · sede electrónica (baja censal)', 'url' => 'https://sede.agenciatributaria.gob.es/', 'mention' => 'AEAT'],
            ],
            'companies' => ['asesoria_contable', 'abogados'],
            'partners' => ['asesor_fiscal', 'abogado', 'notario'],
        ],
    ],
];
