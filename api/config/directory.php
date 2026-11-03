<?php

/*
|--------------------------------------------------------------------------
| The company directory: categories, and the modules each one serves
|--------------------------------------------------------------------------
|
| A company's category comes from its activity code (CNAE / NACE Rev. 2, the
| four digits): the first prefix that matches, in the order below. `modules`
| are the phases (Module.order) whose lessons send the promoter to that kind
| of company, read from what each module teaches:
|
|  1 Solar: agencies, land dealers, lawyers, surveyors · 2 Viabilidad:
|  consultants, accountants · 3 Proyectos: architects, engineers · 4 Licencias:
|  architects, lawyers · 5 Financiación y contratación: banks, insurers,
|  builders · 6 Construcción: builders and every trade, materials, machinery,
|  waste · 7 Control de obra: technical control, engineering · 8
|  Comercialización: agencies, marketing · 9 Legalización: lawyers, engineers
|  · 10 Preentrega: finishing trades, cleaning · 11 Entrega: property
|  managers · 12 Postventa: property managers, maintenance, trades.
|
| A code that matches nothing lands in `otros`: kept, in no module.
*/

return [
    'categories' => [
        'promotoras' => ['codes' => ['4110', '6810'], 'modules' => [1]],
        'constructoras' => ['codes' => ['4120', '4121', '4122'], 'modules' => [5, 6]],
        'obra_civil' => ['codes' => ['42'], 'modules' => [6]],
        'demolicion_movimiento_tierras' => ['codes' => ['4311', '4312', '4313'], 'modules' => [6]],
        'instalaciones' => ['codes' => ['4321', '4322', '4329'], 'modules' => [6, 10]],
        'acabados' => ['codes' => ['4331', '4332', '4333', '4334', '4339'], 'modules' => [6, 10, 12]],
        'cubiertas_estructuras' => ['codes' => ['4391', '4399'], 'modules' => [6, 12]],
        'materiales' => ['codes' => ['4673', '4674', '2361', '2362', '2363', '2364', '2365', '2369', '2370', '2511', '2512', '1623', '0811', '0812', '2331', '2332', '2351', '2352', '2399'], 'modules' => [6]],
        'maquinaria' => ['codes' => ['7732', '7739', '4663'], 'modules' => [6]],
        'residuos' => ['codes' => ['3811', '3812', '3821', '3832', '3900'], 'modules' => [6]],
        'arquitectura' => ['codes' => ['7111'], 'modules' => [3, 4, 9]],
        'ingenieria' => ['codes' => ['7112'], 'modules' => [1, 3, 4, 7, 9]],
        'control_tecnico' => ['codes' => ['7120'], 'modules' => [7]],
        'diseno' => ['codes' => ['7410', '7490'], 'modules' => [3, 8]],
        'abogados' => ['codes' => ['6910'], 'modules' => [1, 4, 9, 11]],
        'asesoria_contable' => ['codes' => ['6920'], 'modules' => [2, 12]],
        'consultoria' => ['codes' => ['7022'], 'modules' => [2, 4]],
        'banca_financiacion' => ['codes' => ['6419', '6492', '6499', '6430'], 'modules' => [5]],
        'seguros' => ['codes' => ['6511', '6512', '6520', '6621', '6622', '6629'], 'modules' => [5, 6, 12]],
        'agencias_inmobiliarias' => ['codes' => ['6831'], 'modules' => [1, 8]],
        'administracion_fincas' => ['codes' => ['6832'], 'modules' => [11, 12]],
        'marketing' => ['codes' => ['7311', '7312', '7320'], 'modules' => [8]],
        'limpieza_mantenimiento' => ['codes' => ['8110', '8121', '8122', '8129', '8130'], 'modules' => [10, 12]],
        'otros' => ['codes' => [], 'modules' => []],
    ],
];
