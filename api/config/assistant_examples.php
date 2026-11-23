<?php

/*
|--------------------------------------------------------------------------
| The course's running example in each template, for Nicolás IA
|--------------------------------------------------------------------------
|
| Nicolás, the promoter of the lessons' «Ejemplo» blocks, runs each template
| of the course with these inputs: the professional's example of «Números
| gordos del solar» (344 m² of urban land, 1.586,49 m² of floor area in B+5,
| 18 homes, land at 45 €/m²) in Alicante, with today's construction cost,
| 1.400 €/m² (Lesson 2.3, medium-high qualities). The results are what the
| platform's engine gave with the legal parameters of 23/11/2026, written as
| the lessons quote them: the index (Indexer) gives them to the assistant
| beside each template's inputs and formulas, so it can explain and redo
| the course's calculations. AssistantIndexTest checks every figure here is
| one the course text quotes. Cells are those of each template's sheet; a
| template's own example fills the cells not listed.
|
*/

return [
    'note' => 'Calculado con el motor de la plataforma y los parámetros legales vigentes el 23/11/2026, como lo cuentan las lecciones.',

    'templates' => [
        'numeros_gordos_solar' => [
            'lessons' => ['1.1'],
            'inputs' => ['C17' => 1400],
            'results' => ['C14' => '71.392,05 €', 'C23' => '2.606,50 €/m²', 'C22' => '234.585 €', 'C24' => '4.135.186,19 €'],
        ],

        '1' => [
            'lessons' => ['1.4'],
            'inputs' => ['B4' => 'Comunitat Valenciana', 'B5' => 71392.05, 'B6' => 'NO', 'B7' => 1586.49, 'B8' => 0.005, 'B9' => 0, 'B10' => 3000],
            'results' => ['B15' => '6.425,28 €', 'B16' => '356,96 €', 'B17' => '81.174,29 €', 'B19' => '51,17 €/m²'],
        ],

        '2' => [
            'lessons' => ['2.2', '2.3', '2.4'],
            'inputs' => [
                'B4' => 'Comunitat Valenciana', 'B5' => 'NO', 'B6' => 1586.49, 'B7' => 18, 'B8' => 2606.5, 'B9' => 1400,
                'B10' => 50000, 'B11' => 0.08, 'B12' => 0.05, 'B13' => 0.03, 'B14' => 0.03, 'B15' => 0.04, 'B16' => 0.02,
                'B17' => 0.18, 'B18' => 71392.05,
            ],
            'results' => [
                'B21' => '4.135.186,19 €', 'B22' => '2.221.086 €', 'B23' => '177.686,88 €', 'B24' => '111.054,30 €',
                'B25' => '66.632,58 €', 'B26' => '50.000 €', 'B27' => '124.055,59 €', 'B28' => '165.407,45 €',
                'B29' => '82.703,72 €', 'B30' => '2.998.626,52 €', 'B32' => '359.840,51 €', 'B33' => '77.817,33 €',
                'B34' => '3.076.443,85 €', 'B35' => '1.058.742,33 €', 'B36' => '26 %', 'B39' => '229.732,57 €',
                'B40' => '252.705,82 €',
            ],
            // Lesson 2.4: the same CRP with the sale price and the construction moved as its table says.
            'variants' => [
                ['label' => 'Escenario base (ventas al 95 %, obra +5 %)', 'inputs' => ['B8' => 2476.175, 'B9' => 1470], 'results' => ['B35' => '741.768,37 €', 'B36' => '19 %']],
                ['label' => 'Escenario pesimista (ventas al 90 %, obra +10 %)', 'inputs' => ['B8' => 2345.85, 'B9' => 1540], 'results' => ['B35' => '424.794,42 €', 'B36' => '11,4 %']],
                ['label' => 'El extremo de la tabla (ventas al 85 %, obra +15 %)', 'inputs' => ['B8' => 2215.525, 'B9' => 1610], 'results' => ['B35' => '107.820,46 €']],
            ],
        ],

        '3' => [
            'lessons' => ['5.1'],
            'inputs' => ['B4' => 3076443.85, 'B5' => 0.7, 'B6' => 0.045, 'B7' => 0.0075, 'B8' => 20, 'B9' => 4, 'B10' => 6000, 'B11' => 4000, 'B12' => 1.5],
            'results' => [
                'B15' => '2.153.510,70 €', 'B16' => '922.933,16 €', 'B17' => '16.151,33 €', 'B18' => '80.756,65 €',
                'B19' => '32.302,66 €', 'B21' => '10.000 €', 'B22' => '139.210,64 €', 'B23' => '5 %',
            ],
        ],

        '4' => [
            'lessons' => ['7.3'],
            'inputs' => [
                'A5' => 'Movimiento de tierras', 'B5' => 63460, 'C5' => 63460, 'D5' => 65575,
                'A6' => 'Cimentación', 'B6' => 190379, 'C6' => 190379, 'D6' => 196725,
                'A7' => 'Estructura', 'B7' => 444219, 'C7' => 401910, 'D7' => 444219,
                'A8' => 'Albañilería', 'B8' => 317297, 'C8' => 158650, 'D8' => 327874,
                'A9' => 'Cubiertas', 'B9' => 95189, 'C9' => 0, 'D9' => 95189,
                'A10' => 'Instalaciones', 'B10' => 401910, 'C10' => 63460, 'D10' => 401910,
                'A11' => 'Carpintería', 'B11' => 222108, 'C11' => 0, 'D11' => 222108,
                'A12' => 'Acabados', 'B12' => 349028, 'C12' => 0, 'D12' => 364893,
                'A13' => 'Urbanización interior', 'B13' => 74037, 'C13' => 0, 'D13' => 74037,
                'A14' => 'Seguridad y salud', 'B14' => 31729, 'C14' => 15865, 'D14' => 31729,
                'A15' => 'Gestión de residuos', 'B15' => 15865, 'C15' => 7403, 'D15' => 15865,
                'A16' => 'Control de calidad', 'B16' => 15865, 'C16' => 6347, 'D16' => 15865,
                'B19' => 0.05,
            ],
            'results' => [
                'B17' => '2.221.086 €', 'C17' => '907.474 €', 'D17' => '2.255.989 €', 'F17' => '34.903 €', 'G17' => '2 %',
                'F5' => '2.115 €', 'F6' => '6.346 €', 'F8' => '10.577 €', 'F12' => '15.865 €',
                'B20' => '45.373,70 €', 'B22' => '40 %', 'B24' => '952.847,70 €',
            ],
        ],

        '5' => [
            'lessons' => ['8.3', '12.3'],
            'inputs' => ['B4' => 18, 'B5' => 229732.57, 'B6' => 6000, 'B7' => 0.1, 'B8' => 0.1, 'B9' => 18, 'B10' => 2],
            'results' => [
                'B13' => '252.705,83 €', 'B15' => '19.270,58 €', 'B16' => '1.403,92 €', 'B17' => '202.164,66 €',
                'B18' => '50.541,17 €', 'B22' => '413.518,63 €', 'B24' => '909.740,98 €', 'B25' => '9',
            ],
        ],
    ],
];
