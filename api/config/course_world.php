<?php

/*
|--------------------------------------------------------------------------
| The 3D world of the course (Aprendizaje 3D)
|--------------------------------------------------------------------------
|
| An isometric town where the student's promotion is built module after
| module: the plot, the institutions the course sends the promoter to, the
| papers, taxes and people of each step, the module's checklist. The world
| is the course's own example (the professional's «números gordos del
| solar», Module 1): a 344 m² plot, ground floor plus five, 18 homes.
|
| `modules` gives, for each module (by order), what stands on the plot once
| it is done: the pieces of the 3D world (web/src/components/world3d/site.ts
| names them; CourseWorldTest checks every key exists there) and the light.
| The world is cumulative: a module starts where the one before ended, and
| the twelfth ends with the finished building at dusk, its homes lit.
|
| `steps` holds a module's script, step after step, in the lessons' order.
| Every text is the instructor's: `quote` is a line of the lesson copied as
| written, `documents`, `people` and `figures` are names and numbers the
| lesson writes (CourseWorldTest fails if the lesson does not contain them),
| `checklist` points to the module's checklist by order. `place` is where
| the camera flies; `adds`/`removes` change what stands from this step on,
| `show` only during it; `emit` makes papers or taxes rise from the place.
| Taxes are codes of the legal parameters in force (`legal_parameters` and,
| for ITP and AJD, the student's region), never rates written here.
|
| Served behind the module's gate (CourseWorldController), metered like the
| lesson's text. Everything else — the geometry — is in the web bundle and
| is not course content.
*/

return [

    // Before the course: a plot for sale.
    'start' => ['plot.ground', 'plot.forsale'],

    'modules' => [
        1 => ['end' => ['plot.ground', 'plot.fence']],
        2 => ['end' => ['plot.ground', 'plot.fence', 'site.ghost']],
        3 => ['end' => ['plot.ground', 'plot.fence', 'site.blueprint']],
        4 => ['end' => ['plot.ground', 'plot.fence', 'site.blueprint', 'site.notice', 'site.huts', 'site.setout']],
        5 => ['end' => ['plot.ground', 'plot.fence', 'site.blueprint', 'site.notice', 'site.huts', 'site.setout', 'site.crane']],
        6 => ['end' => ['site.excavation', 'site.foundation', 'site.structure.0', 'site.structure.1', 'site.structure.2', 'site.structure.3', 'site.structure.4', 'site.structure.5', 'site.facade.0', 'site.facade.1', 'site.facade.2', 'site.facade.3', 'site.facade.4', 'site.facade.5', 'site.roof', 'site.mep', 'site.finishes', 'site.street']],
        7 => ['end' => ['site.excavation', 'site.foundation', 'site.structure.0', 'site.structure.1', 'site.structure.2', 'site.structure.3', 'site.structure.4', 'site.structure.5', 'site.facade.0', 'site.facade.1', 'site.facade.2', 'site.facade.3', 'site.facade.4', 'site.facade.5', 'site.roof', 'site.mep', 'site.finishes', 'site.street']],
        8 => ['end' => ['site.excavation', 'site.foundation', 'site.structure.0', 'site.structure.1', 'site.structure.2', 'site.structure.3', 'site.structure.4', 'site.structure.5', 'site.facade.0', 'site.facade.1', 'site.facade.2', 'site.facade.3', 'site.facade.4', 'site.facade.5', 'site.roof', 'site.mep', 'site.finishes', 'site.street', 'place.oficina_ventas', 'site.sold']],
        9 => ['end' => ['site.excavation', 'site.foundation', 'site.structure.0', 'site.structure.1', 'site.structure.2', 'site.structure.3', 'site.structure.4', 'site.structure.5', 'site.facade.0', 'site.facade.1', 'site.facade.2', 'site.facade.3', 'site.facade.4', 'site.facade.5', 'site.roof', 'site.mep', 'site.finishes', 'site.street', 'place.oficina_ventas', 'site.sold']],
        10 => ['end' => ['site.excavation', 'site.foundation', 'site.structure.0', 'site.structure.1', 'site.structure.2', 'site.structure.3', 'site.structure.4', 'site.structure.5', 'site.facade.0', 'site.facade.1', 'site.facade.2', 'site.facade.3', 'site.facade.4', 'site.facade.5', 'site.roof', 'site.mep', 'site.finishes', 'site.street', 'place.oficina_ventas', 'site.sold']],
        11 => ['end' => ['site.excavation', 'site.foundation', 'site.structure.0', 'site.structure.1', 'site.structure.2', 'site.structure.3', 'site.structure.4', 'site.structure.5', 'site.facade.0', 'site.facade.1', 'site.facade.2', 'site.facade.3', 'site.facade.4', 'site.facade.5', 'site.roof', 'site.mep', 'site.finishes', 'site.street'], 'dusk' => 0.6],
        12 => ['end' => ['site.excavation', 'site.foundation', 'site.structure.0', 'site.structure.1', 'site.structure.2', 'site.structure.3', 'site.structure.4', 'site.structure.5', 'site.facade.0', 'site.facade.1', 'site.facade.2', 'site.facade.3', 'site.facade.4', 'site.facade.5', 'site.roof', 'site.mep', 'site.finishes', 'site.street', 'site.keys'], 'dusk' => 1],
    ],

    // Each module's script, by order. Module 1 is the first written; the
    // others follow the plan in docs/aprendizaje-3d.md.
    'steps' => [
        1 => [
            // ── Lección 1.1 — Define el producto y encuentra el solar ──
            ['lesson' => '1.1', 'place' => 'promotor', 'title' => 'Define qué vas a promover',
                'quote' => '**Antes de buscar, define qué vas a promover.** Es el filtro con el que mirarás cada solar.',
                'documents' => ['ficha de producto'], 'checklist' => [4], 'emit' => ['paper']],
            ['lesson' => '1.1', 'place' => 'site', 'title' => 'Qué buscas: suelo urbano consolidado',
                'quote' => '**Qué buscas:** suelo urbano consolidado, con servicios (acceso rodado, agua, saneamiento, electricidad) y con alineaciones fijadas. Es lo único que permite pedir licencia sin urbanizar antes.',
                'show' => ['plot.services']],
            ['lesson' => '1.1', 'place' => 'promotor', 'title' => 'Los canales para encontrarlo',
                'quote' => 'Red propia: notarios (herencias), abogados, administradores de fincas, agentes de suelo, propietarios de casas viejas en zonas con edificabilidad sin agotar.',
                'people' => ['notarios', 'abogados', 'administradores de fincas', 'agentes de suelo']],
            ['lesson' => '1.1', 'place' => 'promotor', 'title' => 'Tu cartera de suelo, en una base de datos',
                'quote' => '**Lleva tu cartera de suelo en una base de datos** desde el primer solar: cada solar con su zona, clasificación, calificación, situación urbanística, superficies, edificabilidad, precio y forma de pago, y quién es su propietario y quién te lo ha traído.',
                'documents' => ['Base de datos de gestión de suelo'], 'checklist' => [1], 'emit' => ['paper']],
            ['lesson' => '1.1', 'place' => 'site', 'title' => 'El filtro de 10 minutos',
                'quote' => 'Edificabilidad × precio de venta de la zona ÷ precio pedido → si el suelo pasa del 30 % de los ingresos, descártalo salvo zona prime.',
                'documents' => ['Números gordos del solar'], 'figures' => ['30 %'], 'checklist' => [2]],

            // ── Lección 1.2 — Análisis urbanístico: qué puedes construir ──
            ['lesson' => '1.2', 'place' => 'ayuntamiento', 'title' => 'Quién manda',
                'quote' => '**Quién manda:** la comunidad autónoma hace la ley de urbanismo y el ayuntamiento aplica su PGOU y sus ordenanzas.',
                'documents' => ['PGOU']],
            ['lesson' => '1.2', 'place' => 'site', 'title' => 'Lo que puedes construir: la edificabilidad',
                'quote' => '**Edificabilidad (m²t/m²s):** m² de techo por m² de suelo. Ejemplo: 1.000 m² × 2 m²t/m²s = 2.000 m² de techo.',
                'figures' => ['1.000 m² × 2 m²t/m²s = 2.000 m² de techo'], 'adds' => ['plot.envelope']],
            ['lesson' => '1.2', 'place' => 'estudio_arquitectura', 'title' => 'El estudio de volumetría',
                'quote' => 'Pide a tu arquitecto un **estudio de volumetría** (1-2 semanas): cuántas viviendas caben de verdad.',
                'documents' => ['estudio de volumetría'], 'people' => ['arquitecto'], 'plazo' => '1-2 semanas', 'checklist' => [8], 'emit' => ['paper']],
            ['lesson' => '1.2', 'place' => 'ayuntamiento', 'title' => 'El informe o cédula urbanística',
                'quote' => 'Pide al ayuntamiento el **informe o cédula urbanística** por escrito. Su valor vinculante depende de la ley autonómica: no lo des por vinculante sin comprobarlo.',
                'documents' => ['Informe o cédula urbanística'], 'plazo' => '1-3 meses', 'checklist' => [7], 'emit' => ['paper']],
            ['lesson' => '1.2', 'place' => 'organismo_sectorial', 'title' => 'El riesgo de inundación',
                'quote' => '**Riesgo de inundación** en el visor del Sistema Nacional de Cartografía de Zonas Inundables (SNCZI, MITECO).',
                'checklist' => [12]],

            // ── Lección 1.3 — Due diligence: comprar sin sorpresas ──
            ['lesson' => '1.3', 'place' => 'registro_propiedad', 'title' => 'Nota simple y certificación de cargas',
                'quote' => 'Nota simple → titular, cargas (hipotecas, embargos), servidumbres, condiciones resolutorias, afecciones urbanísticas.',
                'documents' => ['Nota simple', 'Certificación de dominio y cargas'], 'plazo' => 'Inmediato online · ≈ 9 €', 'checklist' => [5], 'emit' => ['paper', 'paper']],
            ['lesson' => '1.3', 'place' => 'catastro', 'title' => 'Catastro, Registro y realidad',
                'quote' => '**Coincidencia Catastro–Registro–realidad.** Si no coinciden, encarga levantamiento topográfico y regulariza (procedimientos de la Ley 13/2015 de reforma hipotecaria y catastral) antes de comprar o condiciona la compra.',
                'documents' => ['Consulta y certificación catastral'], 'checklist' => [6], 'emit' => ['paper']],
            ['lesson' => '1.3', 'place' => 'site', 'title' => 'El levantamiento topográfico',
                'quote' => 'Levantamiento **topográfico** (superficie real, cotas, linderos).',
                'checklist' => [6], 'adds' => ['plot.survey']],
            ['lesson' => '1.3', 'place' => 'ayuntamiento', 'title' => 'El IBI y las tasas pendientes',
                'quote' => '**IBI:** la finca responde del IBI impagado (afección real, art. 64 TRLRHL). Pide certificado de deudas.',
                'documents' => ['Certificado de deudas de IBI y tasas'], 'taxes' => [['name' => 'IBI']], 'plazo' => '1-15 días', 'checklist' => [9], 'emit' => ['tax', 'paper']],
            ['lesson' => '1.3', 'place' => 'site', 'title' => 'Las servidumbres sectoriales',
                'quote' => 'Servidumbres de cauces, costas, carreteras, ferrocarril y aeropuertos (servidumbres aeronáuticas, AESA).',
                'people' => ['AESA'], 'checklist' => [13], 'show' => ['plot.easement']],
            ['lesson' => '1.3', 'place' => 'suministros', 'title' => 'Acometidas y servicios',
                'quote' => '**Acometidas y servicios:** confirma con las compañías (agua, saneamiento, electricidad, telecomunicaciones) que hay capacidad y dónde está el punto de conexión.',
                'people' => ['distribuidora'], 'checklist' => [11], 'show' => ['plot.services']],
            ['lesson' => '1.3', 'place' => 'site', 'title' => 'La geotecnia preliminar',
                'quote' => '**Geotecnia preliminar** si vas a hacer sótanos: el nivel freático cambia la cuenta.',
                'checklist' => [10], 'show' => ['site.geotech']],

            // ── Lección 1.4 — La compra: forma, fiscalidad y sociedad ──
            ['lesson' => '1.4', 'place' => 'notaria', 'title' => 'La opción de compra',
                'quote' => '**Opción de compra:** pagas una prima (3-10 %) por un plazo (6-24 meses). Inscríbela en el Registro para que valga frente a terceros.',
                'documents' => ['Escritura pública'], 'figures' => ['3-10 %', '6-24 meses'], 'emit' => ['paper']],
            ['lesson' => '1.4', 'place' => 'registro_mercantil', 'title' => 'La SPE: una sociedad por promoción',
                'quote' => 'Una SL por promoción: aísla riesgos, ordena la contabilidad, facilita la entrada de socios y el cierre.',
                'documents' => ['Constitución de la SPE', 'NIF provisional (AEAT, modelo 036)'], 'plazo' => '1-3 semanas', 'checklist' => [15], 'emit' => ['stamp']],
            ['lesson' => '1.4', 'place' => 'hacienda', 'title' => 'Si vende un empresario: IVA y AJD',
                'quote' => '| Empresario vende **terreno edificable** (solar) | IVA 21 % + AJD de la escritura (Anexo B) | IVA sí, se deduce. AJD no |',
                'taxes' => [['code' => 'iva_general', 'name' => 'IVA'], ['code' => 'ajd', 'name' => 'AJD']], 'checklist' => [3], 'emit' => ['tax', 'tax']],
            ['lesson' => '1.4', 'place' => 'hacienda', 'title' => 'Si vende un particular: ITP',
                'quote' => '| **Particular** vende | ITP de la comunidad (Anexo B). Sin AJD | No |',
                'taxes' => [['code' => 'itp', 'name' => 'ITP']], 'checklist' => [3], 'emit' => ['tax']],
            ['lesson' => '1.4', 'place' => 'notaria', 'title' => 'La firma ante notario',
                'quote' => 'Notaría: por ley la escritura matriz la paga el vendedor y la copia el comprador (art. 1455 CC), salvo pacto. Entre empresarios se pacta.',
                'documents' => ['Escritura pública'], 'taxes' => [['name' => 'Plusvalía municipal (IIVTNU)']], 'checklist' => [16], 'emit' => ['stamp']],
            ['lesson' => '1.4', 'place' => 'site', 'title' => 'Solar escriturado',
                'quote' => 'solar escriturado a nombre de la SPE o con opción de compra firmada.',
                'adds' => ['plot.fence'], 'removes' => ['plot.forsale', 'plot.survey', 'plot.envelope'], 'emit' => ['stamp']],
        ],
    ],
];
