<?php

/*
|--------------------------------------------------------------------------
| The taxes and institutions the course names, for the «i» beside them
|--------------------------------------------------------------------------
|
| Each entry: the spellings the course text uses (exact case, whole words),
| matched in every language since the translations keep the Spanish names
| and acronyms. The name in full and the explanation are in
| lang/<locale>/glossary.php under the same key. Laws are not here: they
| link to their official text (config/legal.php).
|
*/

return [
    'terms' => [
        // Taxes
        'itp' => ['match' => ['ITP']],
        'ajd' => ['match' => ['AJD']],
        'iva' => ['match' => ['IVA']],
        'igic' => ['match' => ['IGIC']],
        'ipsi' => ['match' => ['IPSI']],
        'icio' => ['match' => ['ICIO']],
        'iivtnu' => ['match' => ['IIVTNU', 'plusvalía municipal', 'Plusvalía municipal']],
        'ibi' => ['match' => ['IBI']],
        'is' => ['match' => ['Impuesto sobre Sociedades']],
        'iae' => ['match' => ['IAE']],

        // Institutions
        'aeat' => ['match' => ['AEAT', 'Agencia Tributaria']],
        'hacienda_foral' => ['match' => ['Hacienda Foral', 'Diputación Foral']],
        'seguridad_social' => ['match' => ['Seguridad Social']],
        'tgss' => ['match' => ['TGSS']],
        'registro_propiedad' => ['match' => ['Registro de la Propiedad']],
        'registro_mercantil' => ['match' => ['Registro Mercantil']],
        'catastro' => ['match' => ['Catastro']],
        'notaria' => ['match' => ['Notaría', 'notaría', 'Notario', 'notario']],
        'ayuntamiento' => ['match' => ['Ayuntamiento', 'ayuntamiento']],
        'colegio_arquitectos' => ['match' => ['Colegio de Arquitectos']],
        'colegio_aparejadores' => ['match' => ['Colegio de Aparejadores', 'COAAT']],
        'coapi' => ['match' => ['COAPI']],
        'colegio_notarial' => ['match' => ['Colegio Notarial']],
        'casa47' => ['match' => ['CASA47', 'SEPES']],
        'sareb' => ['match' => ['SAREB', 'Sareb']],
        'banco_espana' => ['match' => ['Banco de España']],
        'sepblac' => ['match' => ['SEPBLAC', 'Sepblac']],
        'junta_arbitral' => ['match' => ['Junta Arbitral de Consumo']],
        'confederacion' => ['match' => ['Confederación Hidrográfica', 'organismo de cuenca']],
        'oct' => ['match' => ['OCT']],
        'rea' => ['match' => ['REA']],
        'autoridad_laboral' => ['match' => ['autoridad laboral', 'Autoridad laboral']],
    ],
];
