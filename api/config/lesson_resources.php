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
|   `links_pending`: drafted, not shown until each address has been checked.
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
            'links_pending' => [
                ['name' => 'Idealista · terrenos en venta', 'url' => 'https://www.idealista.com/venta-terrenos/', 'mention' => 'Idealista'],
                ['name' => 'Fotocasa', 'url' => 'https://www.fotocasa.es/', 'mention' => 'Fotocasa'],
                ['name' => 'Solvia', 'url' => 'https://www.solvia.es/', 'mention' => 'Solvia'],
                ['name' => 'Servihabitat', 'url' => 'https://www.servihabitat.com/', 'mention' => 'Servihabitat'],
                ['name' => 'Altamira Inmuebles', 'url' => 'https://www.altamirainmuebles.com/', 'mention' => 'Altamira'],
                ['name' => 'Haya Real Estate', 'url' => 'https://www.haya.es/', 'mention' => 'Haya'],
                ['name' => 'Aliseda Inmobiliaria', 'url' => 'https://www.alisedainmobiliaria.com/', 'mention' => 'Aliseda'],
                ['name' => 'SAREB', 'url' => 'https://www.sareb.es/', 'mention' => 'SAREB'],
                ['name' => 'Portal de Subastas del BOE', 'url' => 'https://subastas.boe.es/', 'mention' => 'Subastas: BOE'],
                ['name' => 'CASA47', 'url' => 'https://www.casa47.gob.es/', 'mention' => 'CASA47'],
                ['name' => 'Sede Electrónica del Catastro', 'url' => 'https://www.sedecatastro.gob.es/', 'mention' => 'Catastro'],
            ],
            'companies' => ['agencias_inmobiliarias'],
            'partners' => ['inmobiliaria'],
        ],
        '1.2' => [
            'links_pending' => [
                ['name' => 'Sistema Nacional de Cartografía de Zonas Inundables (SNCZI)', 'url' => 'https://sig.mapama.gob.es/snczi/', 'mention' => 'SNCZI'],
            ],
            'companies' => ['arquitectura'],
            'partners' => ['arquitectura'],
        ],
        '1.3' => [
            'links_pending' => [
                ['name' => 'Registradores de España · nota simple', 'url' => 'https://www.registradores.org/', 'mention' => 'Nota simple'],
                ['name' => 'Sede Electrónica del Catastro', 'url' => 'https://www.sedecatastro.gob.es/', 'mention' => 'Catastro'],
                ['name' => 'AESA · servidumbres aeronáuticas', 'url' => 'https://www.seguridadaerea.gob.es/', 'mention' => 'AESA'],
            ],
            'companies' => ['abogados', 'ingenieria'],
            'partners' => ['abogado', 'arquitectura'],
        ],
        '1.4' => [
            'links_pending' => [
                ['name' => 'Registro Mercantil Central · denominación', 'url' => 'https://www.rmc.es/', 'mention' => 'Registro Mercantil Central'],
                ['name' => 'Agencia Tributaria · sede electrónica (modelo 036)', 'url' => 'https://sede.agenciatributaria.gob.es/', 'mention' => 'modelo 036'],
                ['name' => 'CIRCE / PAE · crear la sociedad en línea', 'url' => 'https://paeelectronico.es/', 'mention' => 'CIRCE / PAE'],
            ],
            'companies' => ['abogados', 'asesoria_contable'],
            'partners' => ['notario', 'asesor_fiscal', 'abogado'],
        ],
        '2.2' => [
            'links_pending' => [
                ['name' => 'Registradores de España · Estadística Registral Inmobiliaria', 'url' => 'https://www.registradores.org/', 'mention' => 'Registradores'],
                ['name' => 'Portal Estadístico del Notariado', 'url' => 'https://penotariado.com/', 'mention' => 'Notarios'],
                ['name' => 'Tinsa', 'url' => 'https://www.tinsa.es/', 'mention' => 'Tinsa'],
                ['name' => 'Sociedad de Tasación', 'url' => 'https://www.st-tasacion.es/', 'mention' => 'Sociedad de Tasación'],
                ['name' => 'Gloval', 'url' => 'https://www.gloval.es/', 'mention' => 'Gloval'],
                ['name' => 'CBRE España', 'url' => 'https://www.cbre.es/', 'mention' => 'CBRE'],
                ['name' => 'JLL España', 'url' => 'https://www.jll.es/', 'mention' => 'JLL'],
                ['name' => 'Savills España', 'url' => 'https://www.savills.es/', 'mention' => 'Savills'],
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
            'links_pending' => [
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
            'links_pending' => [
                ['name' => 'Confederaciones Hidrográficas (MITECO)', 'url' => 'https://www.miteco.gob.es/', 'mention' => 'Confederación Hidrográfica'],
                ['name' => 'Costas (MITECO)', 'url' => 'https://www.miteco.gob.es/', 'mention' => 'Costas'],
                ['name' => 'ADIF', 'url' => 'https://www.adif.es/', 'mention' => 'ADIF'],
                ['name' => 'AESA · servidumbres aeronáuticas', 'url' => 'https://www.seguridadaerea.gob.es/', 'mention' => 'AESA'],
                ['name' => 'Ministerio de Defensa', 'url' => 'https://www.defensa.gob.es/', 'mention' => 'Ministerio de Defensa'],
            ],
        ],
        '4.3' => [
            'links_pending' => [
                ['name' => 'Registro de Empresas Acreditadas (REA)', 'url' => 'https://expinterweb.mites.gob.es/rea/', 'mention' => 'REA'],
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
            'links_pending' => [
                ['name' => 'Registradores de España · cuentas anuales (Registro Mercantil)', 'url' => 'https://www.registradores.org/', 'mention' => 'Registro Mercantil'],
                ['name' => 'Registro de Empresas Acreditadas (REA)', 'url' => 'https://expinterweb.mites.gob.es/rea/', 'mention' => 'REA'],
                ['name' => 'Agencia Tributaria · sede electrónica', 'url' => 'https://sede.agenciatributaria.gob.es/', 'mention' => 'AEAT'],
                ['name' => 'Seguridad Social · sede electrónica', 'url' => 'https://sede.seg-social.gob.es/', 'mention' => 'Seguridad Social'],
            ],
            'companies' => ['constructoras'],
            'partners' => ['constructora'],
        ],
        '5.4' => [
            'links_pending' => [
                ['name' => 'Registro de Empresas Acreditadas (REA)', 'url' => 'https://expinterweb.mites.gob.es/rea/', 'mention' => 'REA'],
                ['name' => 'Agencia Tributaria · sede electrónica', 'url' => 'https://sede.agenciatributaria.gob.es/', 'mention' => 'AEAT'],
                ['name' => 'Seguridad Social · sede electrónica', 'url' => 'https://sede.seg-social.gob.es/', 'mention' => 'TGSS'],
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
            'links_pending' => [
                ['name' => 'Bureau Veritas', 'url' => 'https://www.bureauveritas.es/', 'mention' => 'Bureau Veritas'],
                ['name' => 'Applus+', 'url' => 'https://www.applus.com/es/', 'mention' => 'Applus+'],
                ['name' => 'SGS', 'url' => 'https://www.sgs.com/es-es', 'mention' => 'SGS'],
                ['name' => 'Eurocontrol', 'url' => 'https://www.eurocontrol.es/', 'mention' => 'Eurocontrol'],
                ['name' => 'OCA Global', 'url' => 'https://www.ocaglobal.com/', 'mention' => 'OCA'],
            ],
            'companies' => ['control_tecnico'],
        ],
        '8.1' => [
            'links_pending' => [
                ['name' => 'Idealista', 'url' => 'https://www.idealista.com/', 'mention' => 'Idealista'],
                ['name' => 'Fotocasa', 'url' => 'https://www.fotocasa.es/', 'mention' => 'Fotocasa'],
                ['name' => 'pisos.com', 'url' => 'https://www.pisos.com/', 'mention' => 'pisos.com'],
                ['name' => 'Google Ads', 'url' => 'https://ads.google.com/', 'mention' => 'Google Ads'],
                ['name' => 'Meta Ads', 'url' => 'https://www.facebook.com/business/ads', 'mention' => 'Meta Ads'],
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
            'links_pending' => [
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
            'links_pending' => [
                ['name' => 'Agencia Tributaria · sede electrónica (NIF de la comunidad)', 'url' => 'https://sede.agenciatributaria.gob.es/', 'mention' => 'AEAT'],
            ],
            'companies' => ['administracion_fincas'],
        ],
        '11.5' => [
            'links_pending' => [
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
            'links_pending' => [
                ['name' => 'Registradores de España · Registro Mercantil', 'url' => 'https://www.registradores.org/', 'mention' => 'Registro Mercantil'],
                ['name' => 'Agencia Tributaria · sede electrónica (baja censal)', 'url' => 'https://sede.agenciatributaria.gob.es/', 'mention' => 'AEAT'],
            ],
            'companies' => ['asesoria_contable', 'abogados'],
            'partners' => ['asesor_fiscal', 'abogado', 'notario'],
        ],
    ],
];
