<?php

/**
 * Programa — "La Promoción Inmobiliaria en España" (promoción de viviendas de
 * renta libre), 12 fases.
 *
 * Fuente: el resumen de fases enviado por el formador el 26/10/2026 (los doce
 * títulos y sus hitos se reproducen tal cual). El desarrollo de cada fase es
 * un BORRADOR redactado a partir de ese resumen y de la normativa estatal
 * vigente: el formador lo revisa capítulo a capítulo antes de grabar.
 *
 * Toda cifra, plazo o porcentaje que dependa de la práctica del formador, del
 * banco, de la aseguradora o de la comunidad autónoma lleva la mención
 * «pendiente de validar»: se muestra al administrador con la etiqueta
 * A VALIDAR y entra en el informe content-review.md. Nunca se borra para
 * dejar el texto más limpio.
 *
 * Forma:
 *   modules[] = title, description, lessons[], blocks[]
 *   lessons[] = title, key_points[], content, blocks[]
 *   blocks[]  = ['type' => legal|partner_slot|expert_tip, 'body' => ..., 'partner_category' => ...]
 *
 * El inglés no se incluye a propósito: los campos _en se crean vacíos y una
 * traducción humana vendrá después.
 */

return [
    'course' => [
        'title_es' => 'La Promoción Inmobiliaria en España',
        'description_es' => 'Del solar a la entrega de llaves: el recorrido real de una promoción de viviendas de renta libre, fase por fase. 12 fases concretas —solar, viabilidad, proyectos, licencias, financiación y contratación, construcción, control de obra, comercialización, legalización, preentrega, entrega de llaves y postventa— con los documentos, los agentes, los plazos, los costes y los errores frecuentes de cada hito. Una formación de profesional, apoyada en la normativa estatal vigente (LOE, Código Técnico de la Edificación, Ley de Suelo) y en las diferencias que marca cada comunidad autónoma.',
        'format_note' => 'Formato: 12 fases en vídeo, una checklist por fase, modelos de documentos en anexos y un «circuito administrativo» visual (mapa del recorrido con plazos y organismos). Cada fase termina con: documentos que obtener, a quién acudir, cuánto cuesta, plazo realista y errores frecuentes.',
    ],

    'modules' => [
        // ------------------------------------------------------------------
        1 => [
            'title' => 'Solar: búsqueda, análisis urbanístico, due diligence y adquisición',
            'description' => 'Encontrar el suelo, leer lo que el planeamiento permite construir en él, comprobar que no esconde cargas ni sorpresas, y comprarlo bien: a nombre de la sociedad promotora, con las condiciones adecuadas y con la financiación —propia o externa— resuelta antes de firmar.',
            'lessons' => [
                [
                    'title' => '1.1 El oficio de promotor y dónde se encuentra el suelo',
                    'key_points' => [
                        'El promotor es el agente que decide, impulsa, programa y financia la edificación, con recursos propios o ajenos, para venderla o cederla a terceros (art. 9 de la Ley 38/1999 de Ordenación de la Edificación, LOE). No es el constructor ni el agente inmobiliario: responde ante los compradores de todo el proceso.',
                        'La promoción se aloja en una sociedad dedicada (habitualmente una S.L. por promoción): separa el riesgo de cada proyecto, ordena la contabilidad y es lo que piden los bancos para el préstamo promotor. El solar se compra ya a nombre de esa sociedad.',
                        'Fuentes de suelo: recorrer físicamente la zona y localizar solares vacíos o edificios para derribar; notarios, administradores de fincas y abogados de herencias (proindivisos y herencias por resolver son la mejor cantera); agentes especializados en suelo; portales inmobiliarios; subastas judiciales y de la Agencia Tributaria; suelo público de ayuntamientos y comunidades autónomas por concurso; y la permuta con propietarios que prefieren cobrar en viviendas.',
                        'Primer filtro en 48 horas, antes de dedicar dinero: ubicación y demanda, superficie, edificabilidad aproximada según el plan, precio pedido frente al valor residual (fase 2) y si el propietario tiene capacidad real de vender (titularidad clara, herederos de acuerdo).',
                    ],
                ],
                [
                    'title' => '1.2 Análisis urbanístico: qué se puede construir',
                    'key_points' => [
                        'Clase de suelo: un solar es suelo urbano que ya reúne los servicios exigidos (acceso rodado, abastecimiento de agua, evacuación de aguas residuales, suministro eléctrico y alineaciones y rasantes señaladas). Un suelo urbano no consolidado o urbanizable obliga antes a gestionar y pagar la urbanización: otro negocio, otros plazos.',
                        'Planeamiento aplicable: Plan General de Ordenación Urbana (PGOU) o normas subsidiarias, y en su caso plan parcial, plan especial o estudio de detalle. La clasificación del suelo y su nombre exacto dependen de la ley autonómica (Ley de Suelo estatal, RDL 7/2015, más la ley urbanística de cada comunidad).',
                        'Parámetros que deciden el negocio: uso y tipología permitidos, edificabilidad (m² de techo por m² de suelo), ocupación máxima, altura y número de plantas, retranqueos y fondo edificable, densidad (viviendas por hectárea), plazas de aparcamiento obligatorias y cesiones o cargas urbanísticas pendientes. La edificabilidad multiplicada por la superficie da los metros cuadrados vendibles: el verdadero valor del solar.',
                        'Pedir siempre por escrito al ayuntamiento el certificado o cédula de información urbanística (el nombre varía por municipio y comunidad) y no basarse en lo que diga el vendedor ni en un visor web. Plazo habitual de respuesta: de uno a tres meses (pendiente de validar con el formador según municipio).',
                        'Afecciones sectoriales que reducen o bloquean lo edificable: carreteras, costas, dominio público hidráulico y zonas inundables (confederación hidrográfica), patrimonio histórico y arqueología, servidumbres aeronáuticas, vías pecuarias, líneas eléctricas.',
                    ],
                ],
                [
                    'title' => '1.3 Due diligence del solar',
                    'key_points' => [
                        'Registro de la Propiedad: nota simple (y certificación si hace falta) para conocer el titular, la superficie inscrita y las cargas: hipotecas, embargos, servidumbres, condiciones resolutorias, afecciones fiscales y anotaciones de demanda.',
                        'Catastro: certificación catastral descriptiva y gráfica. Comparar superficie y linderos con el Registro: las discrepancias se corrigen antes de comprar (coordinación Catastro-Registro de la Ley 13/2015), no después, cuando el proyecto ya depende de esa superficie.',
                        'Situación urbanística y económica: cuotas de urbanización o derramas de la junta de compensación pendientes, expedientes de disciplina urbanística, IBI y tasas al corriente, ocupantes o arrendatarios, contratos que vinculen el suelo.',
                        'Suelo y subsuelo: levantamiento topográfico, estudio geotécnico previo (aunque sea con menos sondeos que el definitivo), posibles restos arqueológicos y suelos contaminados: el vendedor debe declarar en la escritura si en la finca se ha realizado una actividad potencialmente contaminante (art. 98 de la Ley 7/2022 de residuos y suelos contaminados).',
                        'Acometidas: comprobar con las compañías de agua y electricidad la capacidad de la red. Un centro de transformación o una ampliación de red no previstos se comen el margen y retrasan la obra.',
                    ],
                    'blocks' => [
                        ['type' => 'expert_tip', 'body' => 'Encargar el estudio geotécnico y la topografía ANTES de comprar: si la operación no sale, se pierde el coste de dos estudios; si se compra sin ellos, un firme profundo o el nivel freático alto pueden obligar a una cimentación especial que se come el beneficio. Pérdida pequeña y segura frente a pérdida grande y posible.'],
                    ],
                ],
                [
                    'title' => '1.4 Adquisición y financiación de la compra',
                    'key_points' => [
                        'Herramientas para reservar el suelo mientras se completa la due diligence: contrato de arras (confirmatorias o penitenciales del art. 1454 del Código Civil), opción de compra con plazo y precio fijados, y compraventa con condición suspensiva (por ejemplo, obtener la información urbanística favorable o la licencia).',
                        'La permuta de solar por obra futura: el propietario entrega el suelo a cambio de viviendas o locales de la promoción. Reduce la inversión inicial, pero hay que garantizar al propietario su entrega (aval o condición resolutoria) y tenerla en cuenta en la financiación y en la fiscalidad.',
                        'Escritura pública ante notario e inscripción en el Registro de la Propiedad a nombre de la sociedad promotora. Gastos: notaría y registro según arancel, gestoría si la hay.',
                        'Impuestos de la compra: si vende un empresario y el suelo es edificable, IVA al 21 % más Actos Jurídicos Documentados (AJD); si vende un particular, Transmisiones Patrimoniales (ITP) al tipo de cada comunidad autónoma. La plusvalía municipal corresponde al vendedor. Confirmar cada operación con el asesor fiscal (pendiente de validar con el asesor fiscal).',
                        'Financiación de la compra: lo habitual es pagar el suelo con fondos propios de la sociedad y sus socios, porque los bancos financian poco suelo y con condiciones exigentes; alternativas: socios inversores, préstamo puente, financiación alternativa (deuda privada, plataformas de financiación participativa) o un calendario de pago aplazado con el vendedor. Porcentajes y condiciones actuales pendientes de validar con el formador.',
                        'Error número uno del principiante: comprar antes de tener por escrito la edificabilidad real y la ausencia de cargas, o firmar arras sin una salida si el ayuntamiento informa en contra.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'notario', 'body' => 'Notario colaborador (compraventa, arras, opción de compra, permutas) y abogado urbanista para la due diligence del solar.'],
                ['type' => 'partner_slot', 'partner_category' => 'otro', 'body' => 'Agentes especializados en suelo y topógrafos / laboratorios geotécnicos por provincia.'],
                ['type' => 'legal', 'body' => 'Ley 38/1999 de Ordenación de la Edificación (art. 9, el promotor) ; RDL 7/2015, texto refundido de la Ley de Suelo y Rehabilitación Urbana ; ley urbanística de la comunidad autónoma ; Código Civil (art. 1454, arras) ; Ley 13/2015 (coordinación Catastro-Registro) ; Ley 7/2022 de residuos y suelos contaminados (art. 98).'],
            ],
        ],

        // ------------------------------------------------------------------
        2 => [
            'title' => 'Análisis de viabilidad: estudios de mercado, costes, ingresos y rentabilidad',
            'description' => 'Antes de comprometerse con el solar y con el proyecto: qué producto pide el mercado, cuánto cuesta realmente hacerlo, cuánto se ingresará y cuándo, y si el margen compensa el riesgo. La viabilidad no se hace una vez: se actualiza en cada fase.',
            'lessons' => [
                [
                    'title' => '2.1 Estudio de mercado y definición del producto',
                    'key_points' => [
                        'Oferta y demanda de la zona: promociones de obra nueva en comercialización y su ritmo de venta, viviendas de segunda mano comparables, perfil del comprador (primera vivienda, reposición, inversor) y su capacidad de pago e hipoteca.',
                        'Precio de venta en euros por metro cuadrado construido, por calle y no por ciudad: testigos reales (promociones vecinas, tasaciones, estadística notarial y registral), no precios de anuncio.',
                        'Producto: número de dormitorios, superficies útiles y construidas, terrazas, garaje y trastero vinculados, zonas comunes, calidades y eficiencia energética. El producto se decide aquí y el arquitecto lo dibuja en la fase 3, no al revés.',
                    ],
                ],
                [
                    'title' => '2.2 Costes de la promoción',
                    'key_points' => [
                        'Suelo con todos sus gastos: precio, impuestos de la compra, notaría, registro, due diligence y, si es el caso, cargas de urbanización.',
                        'Construcción: Presupuesto de Ejecución Material (PEM) del proyecto y, sobre él, gastos generales y beneficio industrial del constructor (Presupuesto de Ejecución por Contrata). En contratación pública se usan de referencia un 13-17 % de gastos generales y un 6 % de beneficio industrial; en obra privada se negocia.',
                        'Honorarios técnicos: proyecto y dirección de obra (arquitecto), dirección de ejecución (arquitecto técnico), coordinación de seguridad y salud, ingenierías (estructura, instalaciones, telecomunicaciones), topografía, geotecnia, Organismo de Control Técnico (OCT) y laboratorio de control de calidad.',
                        'Licencias e impuestos municipales: Impuesto sobre Construcciones, Instalaciones y Obras (ICIO, con un máximo legal del 4 % del coste real de la obra, art. 102 del RDL 2/2004 de Haciendas Locales), tasa de licencia, fianza de residuos y, en su caso, aval de urbanización.',
                        'Seguros y garantías: seguro decenal, todo riesgo construcción, responsabilidad civil del promotor y la garantía de las cantidades a cuenta de los compradores (aval o seguro de caución).',
                        'Notaría y registro de la obra nueva y la división horizontal (con su AJD), comercialización (agencia, marketing, piso piloto u oficina de ventas), gestión, gastos financieros (intereses, comisiones, tasaciones) e imprevistos. Porcentajes orientativos de cada partida pendientes de validar con el formador.',
                    ],
                ],
                [
                    'title' => '2.3 Ingresos, rentabilidad y flujo de caja',
                    'key_points' => [
                        'Ingresos: tabla de precios por unidad (planta, orientación, terraza, vistas) para viviendas, garajes, trasteros y locales. Las viviendas tributan al IVA del 10 % (y los garajes y trasteros vendidos con ellas, hasta dos plazas, art. 91 de la Ley 37/1992); los locales, al 21 %. El IVA no es ingreso del promotor.',
                        'Método residual: valor máximo que se puede pagar por el suelo = ingresos previstos − todos los costes − beneficio exigido. Es el mismo método que usan las sociedades de tasación (Orden ECO/805/2003) y la herramienta para negociar el precio del solar.',
                        'Indicadores: margen sobre ventas, margen sobre costes, rentabilidad de los fondos propios (ROE), TIR y VAN del proyecto, y punto de equilibrio en ventas. El margen objetivo que suelen exigir promotores y financiadores está pendiente de validar con el formador.',
                        'Flujo de caja mes a mes: pagos del suelo y de los técnicos al principio, curva de obra después; cobros de reservas y entregas a cuenta de los compradores durante la obra (siempre garantizados y en cuenta especial, fase 5) y el grueso a la escritura. El préstamo promotor cubre el hueco: la viabilidad decide cuánto se necesita y cuándo.',
                        'Análisis de sensibilidad antes de comprar: qué pasa con un 10 % menos de precio, un 10 % más de coste de obra o seis meses de retraso en la licencia. Si el proyecto no aguanta los tres, no se compra el solar.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'asesor_fiscal', 'body' => 'Asesor fiscal y contable especializado en promoción (IVA de las ventas, AJD, impuesto sobre sociedades de la sociedad promotora) y sociedad de tasación homologada.'],
                ['type' => 'legal', 'body' => 'RDL 2/2004, texto refundido de la Ley Reguladora de las Haciendas Locales (art. 100-103, ICIO) ; Ley 37/1992 del IVA (art. 91, tipo reducido de viviendas) ; Orden ECO/805/2003 (valoración de inmuebles, método residual) ; RD 1098/2001 (art. 131, referencia de gastos generales y beneficio industrial).'],
            ],
        ],

        // ------------------------------------------------------------------
        3 => [
            'title' => 'Proyectos: estudios técnicos, anteproyecto, proyecto básico y de ejecución, visados y Plan de Seguridad y Salud',
            'description' => 'Del encargo a los técnicos al proyecto visado: estudios técnicos previos, anteproyecto, proyecto básico y proyecto de ejecución, el visado colegial y la seguridad y salud que se planifica ya en el papel.',
            'lessons' => [
                [
                    'title' => '3.1 El equipo técnico y los estudios previos',
                    'key_points' => [
                        'Proyectista: para edificios de uso residencial, la titulación habilitante es la de arquitecto (art. 10 LOE). Director de ejecución de la obra: arquitecto técnico (art. 13 LOE). Ingenierías para estructura, instalaciones y telecomunicaciones (proyecto de Infraestructura Común de Telecomunicaciones, ICT).',
                        'Encargo por escrito: hoja de encargo o contrato con alcance (anteproyecto, básico, ejecución, dirección de obra), honorarios por fases, plazos, número de revisiones incluidas y seguro de responsabilidad civil profesional de cada técnico.',
                        'Estudios técnicos previos: levantamiento topográfico, estudio geotécnico conforme al Documento Básico SE-C del Código Técnico de la Edificación (sondeos, nivel freático, tipo de cimentación recomendada), y estado de las medianeras y edificios colindantes.',
                    ],
                ],
                [
                    'title' => '3.2 Anteproyecto, proyecto básico y proyecto de ejecución',
                    'key_points' => [
                        'Anteproyecto: distribución, número y tipo de viviendas, superficies y cumplimiento urbanístico, con una estimación de coste. Es el momento de cerrar el producto con la viabilidad (fase 2) y con el equipo comercial: los cambios aquí son baratos; en obra, carísimos.',
                        'Proyecto básico: define las características generales de la obra y justifica el cumplimiento urbanístico. En muchos municipios basta para solicitar la licencia de obras (fase 4), pero no permite empezar a construir.',
                        'Proyecto de ejecución: desarrolla el básico con todo lo necesario para construir: memoria constructiva, cumplimiento de los Documentos Básicos del CTE (seguridad estructural, incendio, utilización y accesibilidad, salubridad, ruido, ahorro de energía), planos de detalle, pliego de condiciones, mediciones y presupuesto, plan de control de calidad, estudio de gestión de residuos y estudio de seguridad y salud (contenido del proyecto: Anejo I de la Parte I del CTE).',
                        'Certificado de eficiencia energética del proyecto (RD 390/2021): se necesita para comercializar sobre plano, porque la etiqueta debe figurar en la publicidad.',
                        'Las mediciones del proyecto de ejecución son la base para pedir precios a los constructores (fase 5). Un proyecto con mediciones incompletas se paga en la obra en forma de precios contradictorios.',
                    ],
                ],
                [
                    'title' => '3.3 Visados y Plan de Seguridad y Salud',
                    'key_points' => [
                        'Visado colegial obligatorio del proyecto de ejecución de edificación y del certificado final de obra (RD 1000/2010): lo emite el colegio profesional del técnico, que comprueba su identidad y habilitación y la integridad formal del documento. Cada colegio fija sus derechos de visado.',
                        'Estudio de Seguridad y Salud (o estudio básico, según el tamaño de la obra): lo encarga el promotor a un técnico competente y forma parte del proyecto. Es obligatorio el estudio completo cuando el presupuesto de ejecución por contrata es igual o superior a 450.759,08 €, la obra dura más de 30 días laborables con más de 20 trabajadores a la vez, o supera las 500 jornadas de trabajo (art. 4 del RD 1627/1997).',
                        'Plan de Seguridad y Salud: lo redacta cada contratista a partir del estudio, adaptándolo a sus medios y procedimientos, y lo aprueba el coordinador de seguridad y salud en fase de ejecución antes del inicio de la obra (art. 7 del RD 1627/1997). Sin plan aprobado no hay comunicación de apertura del centro de trabajo ni inicio de obra.',
                        'Coordinador de seguridad y salud: el promotor lo designa en fase de proyecto cuando intervienen varios proyectistas, y en fase de ejecución cuando interviene más de una empresa o trabajadores autónomos (art. 3 del RD 1627/1997), que en una promoción de viviendas es siempre.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'arquitectura', 'body' => 'Estudios de arquitectura e ingeniería colaboradores (proyecto, dirección de obra, dirección de ejecución, coordinación de seguridad y salud, ICT).'],
                ['type' => 'legal', 'body' => 'Ley 38/1999 LOE (art. 10 y 13) ; RD 314/2006, Código Técnico de la Edificación (Parte I, Anejo I ; DB SE-C) ; RD 1000/2010 sobre visado colegial obligatorio ; RD 1627/1997, seguridad y salud en las obras de construcción (art. 3, 4 y 7) ; RD 390/2021, certificación de la eficiencia energética de los edificios ; RD 105/2008, gestión de residuos de construcción y demolición.'],
            ],
        ],

        // ------------------------------------------------------------------
        4 => [
            'title' => 'Licencias: licencia de obras, autorizaciones, requisitos administrativos y otorgamiento',
            'description' => 'Conseguir el permiso para construir: qué se presenta, qué revisa el ayuntamiento y otros organismos, cuánto se paga, cuánto se tarda de verdad y qué condiciones trae la licencia una vez otorgada.',
            'lessons' => [
                [
                    'title' => '4.1 La solicitud de licencia de obras',
                    'key_points' => [
                        'Una obra de nueva planta de viviendas necesita licencia urbanística expresa: la ley estatal establece el silencio administrativo negativo para las obras de edificación de nueva planta (art. 11.4 del RDL 7/2015). Que pase el plazo sin respuesta no autoriza a construir.',
                        'Documentación habitual: solicitud, proyecto básico o básico y de ejecución (visado cuando corresponda), hojas de encargo de la dirección de obra y de la dirección de ejecución, estudio de gestión de residuos con su fianza, estudio de seguridad y salud, proyecto de ICT, justificación de aparcamientos, cuestionario de estadística de edificación y justificante de pago de tasas y autoliquidación del ICIO. Cada ayuntamiento publica su lista: pedirla antes de presentar.',
                        'Si el solar todavía necesita completar la urbanización, la ley autonómica suele permitir urbanizar y edificar a la vez con un aval que garantice la urbanización y el compromiso de no usar el edificio hasta terminarla.',
                    ],
                ],
                [
                    'title' => '4.2 Tramitación, informes y autorizaciones',
                    'key_points' => [
                        'El ayuntamiento emite informes técnico y jurídico; los requerimientos de subsanación paran el plazo. Responderlos rápido y completos es la mayor palanca del promotor sobre el calendario.',
                        'Informes sectoriales según la ubicación: carreteras, confederación hidrográfica, patrimonio, servidumbres aeronáuticas, costas. Se piden en paralelo cuando la normativa lo permite.',
                        'Plazo legal de resolución habitualmente de tres meses en la normativa autonómica; plazo real en grandes ciudades bastante mayor (pendiente de validar con el formador). Hay que llevarlo a la planificación y al flujo de caja: el suelo ya pagado cuesta intereses cada mes de espera.',
                        'Si se deniega o se retrasa sin motivo: recurso administrativo y, después, contencioso-administrativo. Antes de recurrir, reunión técnica con los servicios municipales: muchas denegaciones se resuelven con una modificación del proyecto.',
                    ],
                ],
                [
                    'title' => '4.3 El otorgamiento y lo que trae la licencia',
                    'key_points' => [
                        'La resolución de licencia fija las condiciones: plazos de inicio y de terminación (si se incumplen, la licencia puede caducar), prescripciones técnicas, fianzas y, en su caso, la obligación de presentar el proyecto de ejecución antes de empezar.',
                        'Con la licencia: cartel de obra exigido por la ordenanza municipal, licencia de ocupación de la vía pública para vallado, grúa o contenedores, autorización de la grúa torre (proyecto de instalación y puesta en servicio, ITC MIE-AEM-2 del RD 836/2003) y solicitud de acometidas provisionales de obra.',
                        'La licencia de obras es requisito para la garantía de las cantidades a cuenta y para el préstamo promotor (fase 5), y debe constar en la información que se da a los compradores (fase 8).',
                        'Cualquier cambio del proyecto que afecte a lo autorizado (número de viviendas, volumen, alturas, usos, fachadas) necesita una modificación de licencia antes de ejecutarse: lo que se construya fuera de licencia bloquea la licencia de primera ocupación (fase 9).',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'abogado', 'body' => 'Abogado urbanista (tramitación, recursos, convenios urbanísticos) y gestoría especializada en licencias por municipio.'],
                ['type' => 'legal', 'body' => 'RDL 7/2015, Ley de Suelo y Rehabilitación Urbana (art. 11) ; ley urbanística y de procedimiento de la comunidad autónoma ; ordenanzas municipales de tramitación de licencias, ICIO y tasas ; RD 836/2003 (ITC MIE-AEM-2, grúas torre).'],
            ],
        ],

        // ------------------------------------------------------------------
        5 => [
            'title' => 'Financiación y contratación: préstamo promotor, seguros, licitación y contratos de obra',
            'description' => 'Asegurar el dinero y a quien va a construir: el préstamo promotor y lo que el banco exige, los seguros y garantías obligatorios, cómo se licita la obra y qué debe decir el contrato con el constructor.',
            'lessons' => [
                [
                    'title' => '5.1 El préstamo promotor',
                    'key_points' => [
                        'Préstamo con garantía hipotecaria sobre el solar y la obra futura, que se va disponiendo contra certificaciones de obra revisadas por la sociedad de tasación. Al vender, la hipoteca se distribuye entre las viviendas y cada comprador se subroga en su parte o el promotor la cancela.',
                        'Lo que suele pedir el banco: solar pagado con fondos propios, licencia de obras, proyecto de ejecución, contrato de obra con un constructor solvente, tasación de una sociedad homologada, un porcentaje mínimo de preventas con entregas a cuenta, seguros (decenal, todo riesgo construcción) y la cuenta especial para las cantidades de los compradores. Porcentajes de financiación y de preventa exigidos hoy: pendiente de validar con el formador.',
                        'Costes: intereses, comisión de apertura, tasaciones y seguimientos de obra, notaría y registro de la hipoteca, y comisiones de subrogación o cancelación. Todos van a la viabilidad (fase 2).',
                        'Si los compradores se van a subrogar, el banco debe darles la información precontractual de la Ley 5/2019 de crédito inmobiliario como en cualquier hipoteca: preverlo con tiempo para no retrasar las escrituras.',
                    ],
                ],
                [
                    'title' => '5.2 Seguros y garantías obligatorios',
                    'key_points' => [
                        'Garantía de las cantidades a cuenta: quien promueve viviendas y cobra dinero de los compradores antes de entregarlas debe garantizar, desde la licencia, su devolución más el interés legal mediante seguro de caución o aval solidario de una entidad de crédito, y cobrarlo en una cuenta especial separada de cualquier otro fondo (disposición adicional primera de la LOE). Cada comprador recibe su certificado individual.',
                        'Seguro decenal de daños materiales (art. 19 LOE): obligatorio en edificios de viviendas, cubre durante diez años los daños estructurales por el 100 % del coste final de la obra. Se contrata antes de empezar —la aseguradora exige un Organismo de Control Técnico desde el proyecto— y entra en vigor con la recepción de la obra (fase 7).',
                        'Seguro de todo riesgo construcción (daños a la obra durante su ejecución) y responsabilidad civil del promotor: no siempre obligatorios por ley, pero los exige el banco y protegen frente a daños a terceros y a colindantes.',
                    ],
                ],
                [
                    'title' => '5.3 Licitación y contrato de obra',
                    'key_points' => [
                        'Modalidades: contratista general a precio cerrado sobre proyecto («llave en mano»), contratación por partidas o gremios coordinada por el promotor, o gestión de obra con honorario. Cada una reparte de forma distinta el riesgo de coste y de plazo.',
                        'Licitación: invitar al menos a tres constructoras con el proyecto de ejecución y sus mediciones, el plazo y el modelo de contrato; comparar ofertas partida a partida sobre las mismas mediciones (comparativo homogéneo), no por el total. Revisar solvencia: inscripción en el Registro de Empresas Acreditadas (REA), obras similares terminadas, situación financiera y seguros.',
                        'Contrato de obra: objeto y documentos contractuales (proyecto, pliego, oferta), precio y si es cerrado o por mediciones, forma de pago por certificaciones mensuales, retención de garantía de cada certificación, aval de fiel cumplimiento, plazo con hitos y penalizaciones por retraso, régimen de modificados y precios contradictorios (siempre por escrito y antes de ejecutar), recepción y plazo de garantía, subcontratación y seguros. Porcentajes habituales de retención y aval: pendiente de validar con el formador.',
                        'Subcontratación: la Ley 32/2006 limita las cadenas de subcontratación y exige el Libro de Subcontratación en obra. El promotor debe conocer quién trabaja en su obra.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'banco', 'body' => 'Entidades financieras con línea de préstamo promotor y avales de cantidades a cuenta ; sociedad de tasación homologada.'],
                ['type' => 'partner_slot', 'partner_category' => 'aseguradora', 'body' => 'Aseguradoras y corredores especializados en construcción (decenal, todo riesgo construcción, caución de cantidades a cuenta, responsabilidad civil).'],
                ['type' => 'partner_slot', 'partner_category' => 'constructora', 'body' => 'Constructoras de edificación residencial por provincia, con referencias de obra terminada.'],
                ['type' => 'legal', 'body' => 'Ley 38/1999 LOE (art. 19 y disposición adicional primera, redactada por la Ley 20/2015) ; Ley 5/2019 reguladora de los contratos de crédito inmobiliario ; Ley 32/2006 reguladora de la subcontratación en el sector de la construcción y RD 1109/2007 ; Código Civil (art. 1588-1600, contrato de obra).'],
            ],
        ],

        // ------------------------------------------------------------------
        6 => [
            'title' => 'Construcción: cimentación, estructura, cerramientos, instalaciones y acabados',
            'description' => 'La obra de principio a fin: el acta de replanteo que la pone en marcha, cada gran capítulo de construcción y lo que el promotor debe vigilar en él, hasta el acta final de obra.',
            'lessons' => [
                [
                    'title' => '6.1 Acta de replanteo e inicio de obra',
                    'key_points' => [
                        'Antes de empezar: licencia, proyecto de ejecución, Plan de Seguridad y Salud aprobado, comunicación de apertura del centro de trabajo presentada por el contratista ante la autoridad laboral, seguros contratados, coordinador de seguridad y salud nombrado y cartel de obra colocado.',
                        'Acta de replanteo (o de comienzo de obra): la firman el director de obra, el director de ejecución, el constructor y el promotor; comprueba sobre el terreno la geometría del proyecto y fija la fecha de inicio a efectos del contrato y de la licencia (art. 12 y 13 LOE).',
                        'Libros que deben estar en obra: Libro de Órdenes y Asistencias (CTE, Anejo II), Libro de Incidencias de seguridad y salud (art. 13 del RD 1627/1997) y Libro de Subcontratación (Ley 32/2006).',
                    ],
                ],
                [
                    'title' => '6.2 Movimiento de tierras, cimentación y estructura',
                    'key_points' => [
                        'Excavación y contención: vaciado, muros pantalla, pilotes o bataches según el estudio geotécnico y los colindantes. Acta notarial o informe pericial del estado de los edificios vecinos antes de excavar: protege frente a reclamaciones posteriores.',
                        'Cimentación (zapatas, losa o pilotes) conforme al geotécnico; si al excavar el terreno no coincide con el estudio, la dirección facultativa decide y se documenta en el Libro de Órdenes.',
                        'Estructura de hormigón armado: control de recepción del hormigón y del acero según el Código Estructural (RD 470/2021), ensayos de laboratorio y desencofrados en plazo. Es la partida que más condiciona el plazo y el seguro decenal.',
                    ],
                ],
                [
                    'title' => '6.3 Cerramientos, cubierta e instalaciones',
                    'key_points' => [
                        'Envolvente: fachadas, aislamiento térmico (DB HE), aislamiento acústico (DB HR), carpinterías exteriores y cubierta con prueba de estanqueidad. Las humedades son la reclamación más frecuente en postventa: se previenen aquí.',
                        'Instalaciones: electricidad (Reglamento Electrotécnico de Baja Tensión), fontanería y saneamiento, climatización y agua caliente con energías renovables exigidas por el DB HE (aerotermia o solar), ventilación (DB HS 3), telecomunicaciones (ICT), ascensores y protección contra incendios. Cada instalador habilitado emitirá su certificado o boletín: sin ellos no hay legalización (fase 9).',
                        'Coordinar los gremios: rozas y pasos de instalaciones antes de cerrar tabiques, y pruebas de presión y estanqueidad antes de tapar.',
                    ],
                ],
                [
                    'title' => '6.4 Acabados y acta final de obra',
                    'key_points' => [
                        'Acabados: tabiquería, solados y alicatados, carpintería interior, pinturas, sanitarios, cocinas y zonas comunes, conforme a la memoria de calidades vendida a los compradores: lo que se publicitó es exigible (fase 8).',
                        'Gestión de residuos durante toda la obra: el constructor entrega los certificados del gestor autorizado; sin ellos no se recupera la fianza de residuos.',
                        'Final de obra: el director de obra y el director de ejecución firman el certificado final de obra, que se visa en sus colegios (RD 1000/2010). Después, el promotor y el constructor firman el acta de recepción, con o sin reservas: desde esa fecha corren las garantías de la LOE (fase 7 y fase 12).',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'constructora', 'body' => 'Constructoras y gremios de confianza por provincia (estructura, fachadas, instalaciones, acabados).'],
                ['type' => 'legal', 'body' => 'Ley 38/1999 LOE (art. 6, 12 y 13) ; RD 314/2006, CTE (Parte I, Anejo II y Documentos Básicos) ; RD 470/2021, Código Estructural ; RD 1627/1997 (art. 13) ; Ley 32/2006 ; RD 842/2002, Reglamento Electrotécnico para Baja Tensión ; RD 1027/2007, RITE ; RD 513/2017, instalaciones de protección contra incendios ; RD 105/2008, residuos de construcción.'],
            ],
        ],

        // ------------------------------------------------------------------
        7 => [
            'title' => 'Control de la obra: dirección facultativa, OCT, calidad, presupuesto y planificación',
            'description' => 'Quién controla qué en la obra y cómo lo controla el promotor: la dirección facultativa, el Organismo de Control Técnico, el control de calidad, el presupuesto y el plazo, la contratación del seguro decenal y el protocolo ante un accidente grave.',
            'lessons' => [
                [
                    'title' => '7.1 Dirección facultativa y OCT',
                    'key_points' => [
                        'Dirección facultativa: el director de obra (arquitecto) dirige el desarrollo de la obra conforme al proyecto; el director de ejecución (arquitecto técnico) controla la ejecución material, la calidad de lo construido y las mediciones; el coordinador de seguridad y salud vigila la aplicación del plan. Sus órdenes quedan en el Libro de Órdenes.',
                        'Organismo de Control Técnico (OCT): entidad independiente que la aseguradora del decenal exige para asumir el riesgo. Revisa el proyecto antes de empezar, inspecciona la obra en sus fases críticas (cimentación, estructura, cubierta, fachadas) y emite un informe final favorable, requisito para que el seguro decenal entre en vigor. Nombres y número de informes según cada OCT: pendiente de validar con el formador.',
                        'Reunión semanal de obra con acta: promotor, dirección facultativa, constructor y, cuando proceda, OCT y coordinador. Lo que no está en un acta o en el Libro de Órdenes no se ha decidido.',
                    ],
                ],
                [
                    'title' => '7.2 Calidad, presupuesto y planificación',
                    'key_points' => [
                        'Control de calidad: plan de control del proyecto ejecutado por un laboratorio acreditado (hormigón, acero, pruebas de estanqueidad, pruebas de servicio de instalaciones). Los resultados se archivan para el Libro del Edificio (fase 9).',
                        'Presupuesto: certificaciones mensuales con relación valorada que comprueba el director de ejecución antes de pagar; seguimiento del coste comprometido frente al presupuesto y a la viabilidad; los modificados y precios contradictorios se aprueban por escrito antes de ejecutarlos.',
                        'Planificación: diagrama de barras con el camino crítico y los hitos (fin de estructura, cubierta, fachada, instalaciones, acabados); revisión cada mes del plazo previsto de entrega, porque condiciona el calendario de cobros, el préstamo y las fechas prometidas a los compradores.',
                    ],
                ],
                [
                    'title' => '7.3 La contratación del seguro decenal',
                    'key_points' => [
                        'Obligatorio en la edificación de viviendas (art. 19.1.c LOE): seguro de daños materiales durante diez años desde la recepción, por el 100 % del coste final de ejecución material, con una franquicia máxima del 1 % del capital asegurado.',
                        'Sin él, el notario no autoriza ni el Registro inscribe la escritura de declaración de obra nueva terminada de un edificio de viviendas (art. 20 LOE): no hay escrituras para los compradores ni subrogaciones.',
                        'Se solicita antes de empezar la obra, con el proyecto y el OCT designado; se paga una prima al inicio y la póliza se emite a la recepción, con el informe final del OCT y el acta de recepción. La única excepción de la ley es el autopromotor de una vivienda unifamiliar para su propio uso, que no es el caso del promotor que vende.',
                    ],
                ],
                [
                    'title' => '7.4 Ficha de actuación — accidente grave o mortal en obra',
                    'content' => 'Para imprimir y colocar en las casetas de obra. Secuencia completa (pendiente de validar con el coordinador de seguridad y salud):',
                    'is_reflex_sheet' => true,
                    'key_points' => [
                        'Auxilio inmediato: primeros auxilios, llamada al 112 y evacuación del herido. Proteger la zona para que no haya un segundo accidente.',
                        'No modificar el lugar del accidente salvo para auxiliar. Avisar al coordinador de seguridad y salud, a la dirección facultativa y al promotor.',
                        'El empresario del trabajador (la empresa de ese gremio, no necesariamente el promotor) comunica a la autoridad laboral en 24 horas los accidentes graves, muy graves o mortales, y presenta el parte de accidente de trabajo por el sistema Delt@ en el plazo de cinco días hábiles (Orden TAS/2926/2002).',
                        'Se anota en el Libro de Incidencias; la Inspección de Trabajo investigará y puede paralizar la obra o la zona afectada.',
                        'Trabajador dado de alta: la mutua o la Seguridad Social cubren asistencia, incapacidad y, si fallece, las prestaciones a sus familiares. Si hubo falta de medidas de seguridad, las prestaciones se recargan entre un 30 % y un 50 % a cargo del empresario responsable (art. 164 de la Ley General de la Seguridad Social), sin posibilidad de asegurarlo.',
                        'Responsabilidad penal: delitos contra los derechos de los trabajadores por no facilitar los medios de seguridad (art. 316 y 317 del Código Penal), además del homicidio o las lesiones por imprudencia. La responsabilidad alcanza a quien debía garantizar la seguridad; el promotor puede verse implicado si no designó coordinador o la organización de la obra era deficiente.',
                        'Consecuencias para la promoción: paralización, retrasos en cadena en el plazo prometido a los compradores y en las disposiciones del préstamo.',
                    ],
                    'blocks' => [
                        ['type' => 'expert_tip', 'body' => 'Lo que protege al promotor: designar coordinador de seguridad y salud, exigir por escrito a cada empresa el alta de todos sus trabajadores, la inscripción en el REA y el cumplimiento del Plan de Seguridad y Salud, y guardar esas comprobaciones. El promotor que exigió y comprobó por escrito está en una posición radicalmente distinta del que miró hacia otro lado.'],
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'aseguradora', 'body' => 'Aseguradoras de decenal y Organismos de Control Técnico (OCT) que trabajan con ellas ; laboratorios de control de calidad acreditados.'],
                ['type' => 'legal', 'body' => 'Ley 38/1999 LOE (art. 12, 13, 19 y 20 ; disposición adicional segunda) ; RD 314/2006, CTE (Parte I, Anejo II) ; RD 1627/1997 ; Ley 31/1995 de Prevención de Riesgos Laborales ; Orden TAS/2926/2002 (Delt@) ; RDL 8/2015, Ley General de la Seguridad Social (art. 164) ; Código Penal (art. 316 y 317).'],
            ],
        ],

        // ------------------------------------------------------------------
        8 => [
            'title' => 'Comercialización: marketing, reservas, contratos y seguimiento de compradores',
            'description' => 'Vender sobre plano dentro de la ley: la información que hay que dar al comprador, el marketing y la red de venta, el documento de reserva y el contrato de compraventa, y el acompañamiento del comprador hasta las escrituras.',
            'lessons' => [
                [
                    'title' => '8.1 Producto, precio y marketing',
                    'key_points' => [
                        'Material comercial: planos comerciales, infografías, memoria de calidades, tabla de precios y forma de pago, web de la promoción, oficina de ventas o piso piloto, y presencia en los portales inmobiliarios. La etiqueta de eficiencia energética del proyecto debe aparecer en la publicidad (RD 390/2021).',
                        'Lo que se publicita obliga: el contenido de la oferta, la promoción y la publicidad es exigible por el comprador aunque no se repita en el contrato (art. 61 del texto refundido de la Ley General para la Defensa de los Consumidores, RDL 1/2007). Infografías y memoria deben ser fieles al proyecto.',
                        'Información obligatoria a disposición del comprador (RD 515/1989 y normativa autonómica de consumo): planos de situación y de la vivienda, descripción de la vivienda y del edificio, memoria de calidades, precio total y forma de pago, datos de la licencia, de la inscripción registral y de la garantía de las cantidades a cuenta.',
                        'Red de venta propia o agencias: comisión, exclusiva, argumentario y formación del equipo comercial por el arquitecto sobre el proyecto.',
                    ],
                ],
                [
                    'title' => '8.2 Reserva y contrato de compraventa',
                    'key_points' => [
                        'Documento de reserva: identifica la vivienda, el precio y el importe de la reserva, y fija un plazo para firmar el contrato; aclara si la reserva se devuelve y en qué casos. Toda cantidad que entregue el comprador va a la cuenta especial y queda garantizada (fase 5).',
                        'Contrato privado de compraventa sobre plano: descripción de la vivienda y anejos, precio y calendario de pagos (entrada, aportaciones durante la obra y resto a la escritura), plazo de entrega, memoria de calidades, referencia a la licencia, garantía de las cantidades a cuenta con entrega del certificado individual, y causas de resolución. IVA del 10 % sobre cada cantidad a cuenta en el momento del cobro.',
                        'Cláusulas abusivas: al comprador consumidor no se le pueden repercutir los gastos que por ley corresponden al promotor —declaración de obra nueva, división horizontal, préstamo promotor y su cancelación— (art. 89 del RDL 1/2007). La plusvalía municipal de la venta la paga el promotor.',
                    ],
                ],
                [
                    'title' => '8.3 Seguimiento de compradores',
                    'key_points' => [
                        'Herramienta de seguimiento (CRM): estado de cada unidad (libre, reservada, contrato firmado, escriturada), calendario de cobros y documentación de cada comprador.',
                        'Comunicación durante la obra: informes periódicos de avance con fotos y visitas organizadas a obra con medidas de seguridad. Un comprador informado no se convierte en una reclamación.',
                        'Personalizaciones y cambios de acabados: catálogo cerrado, presupuesto firmado y plazo límite compatible con la planificación; nunca cambios que afecten a la licencia.',
                        'Financiación de cada comprador: tasación, oferta del banco o subrogación en el préstamo promotor, con tiempo suficiente para la fecha de escrituras (fase 10).',
                        'Impagos y desistimientos: requerimiento fehaciente, resolución conforme al contrato y devolución de lo que corresponda; las cantidades garantizadas se devuelven con la garantía cuando la promoción no llega a buen fin. Protección de datos (RGPD) en todo el proceso.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'marketing', 'body' => 'Agencias de comercialización de obra nueva, estudios de infografía 3D y marketing digital inmobiliario.'],
                ['type' => 'partner_slot', 'partner_category' => 'abogado', 'body' => 'Abogado de derecho inmobiliario y de consumo para los modelos de reserva y de contrato de compraventa.'],
                ['type' => 'legal', 'body' => 'RD 515/1989, información en la compraventa de viviendas ; RDL 1/2007, texto refundido de la Ley General para la Defensa de los Consumidores y Usuarios (art. 61 y 89) ; Ley 38/1999 LOE (disposición adicional primera) ; Ley 37/1992 del IVA (art. 75 y 91) ; RD 390/2021 ; Reglamento (UE) 2016/679 de protección de datos.'],
            ],
        ],

        // ------------------------------------------------------------------
        9 => [
            'title' => 'Legalización: final de obra, documentación técnica, licencia de primera ocupación y Registro',
            'description' => 'Convertir la obra terminada en viviendas que se pueden escriturar y habitar: la documentación técnica de final de obra, la licencia de primera ocupación, la escritura de obra nueva terminada y división horizontal y su inscripción en el Registro.',
            'lessons' => [
                [
                    'title' => '9.1 Final de obra y documentación técnica',
                    'key_points' => [
                        'Certificado final de obra visado, acta de recepción, certificado de eficiencia energética del edificio terminado registrado en la comunidad autónoma, y los certificados de cada instalación: electricidad, telecomunicaciones (boletín, protocolo de pruebas y certificado de fin de obra de la ICT), ascensores, protección contra incendios, instalaciones térmicas y gas.',
                        'Libro del Edificio (art. 7 LOE): el proyecto con sus modificaciones, el acta de recepción, la relación de agentes que han intervenido y las instrucciones de uso y mantenimiento del edificio y sus instalaciones. Muchas comunidades autónomas regulan su contenido. Se entrega a los usuarios finales (fase 11).',
                        'Certificados de gestión de residuos para recuperar la fianza, y alta catastral de la nueva construcción (pendiente de validar con el asesor si la comunicación notarial la sustituye en cada caso).',
                    ],
                ],
                [
                    'title' => '9.2 Licencia de primera ocupación',
                    'key_points' => [
                        'El ayuntamiento comprueba que lo construido se ajusta a la licencia y que el edificio puede usarse: según la comunidad autónoma es una licencia de primera ocupación, una declaración responsable de primera ocupación o, en algunas, una cédula de habitabilidad. Confirmar el régimen exacto del municipio (pendiente de validar con el formador).',
                        'Sin ella no se pueden contratar los suministros definitivos de agua, luz y gas a nombre de los compradores, ni, en muchos casos, otorgar la escritura de obra nueva terminada.',
                        'Inspección municipal: discrepancias con el proyecto aprobado, aparcamientos, accesibilidad y urbanización exterior terminada. Lo que no se ajuste se corrige o se legaliza con una modificación antes de volver a pedirla.',
                    ],
                ],
                [
                    'title' => '9.3 Obra nueva, división horizontal y Registro',
                    'key_points' => [
                        'Escritura de declaración de obra nueva (en construcción, al inicio, para el préstamo promotor; y terminada, al final) y de división horizontal: crea una finca registral por vivienda, garaje y trastero, con su cuota de participación, y los estatutos de la comunidad (Ley 49/1960 de Propiedad Horizontal, art. 5).',
                        'Para la obra nueva terminada el notario y el registrador exigen el certificado final de obra, la licencia o acto de conformidad que corresponda (art. 28 del RDL 7/2015), el seguro decenal (art. 20 LOE) y el Libro del Edificio, que se deposita en el Registro con las coordenadas de la parcela ocupada (art. 202 de la Ley Hipotecaria). Lista definitiva pendiente de validar con el notario.',
                        'Impuestos: AJD sobre la declaración de obra nueva y la división horizontal, a cargo del promotor, al tipo de cada comunidad autónoma.',
                        'Con la inscripción, cada vivienda tiene su finca registral: es lo que permite escriturar las ventas y distribuir la hipoteca del préstamo promotor.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'notario', 'body' => 'Notario colaborador (obra nueva, división horizontal, estatutos) y gestoría para la inscripción registral y el Catastro.'],
                ['type' => 'legal', 'body' => 'Ley 38/1999 LOE (art. 6, 7 y 20) ; RDL 7/2015 (art. 28) ; Ley Hipotecaria (art. 202) ; Ley 49/1960 de Propiedad Horizontal (art. 5) ; RD 390/2021 ; RDL 1/2004, Ley del Catastro Inmobiliario ; normativa autonómica de licencias de ocupación y habitabilidad.'],
            ],
        ],

        // ------------------------------------------------------------------
        10 => [
            'title' => 'Preentrega: inspecciones, repasos, documentación y coordinación notarial',
            'description' => 'Las semanas que deciden la satisfacción del comprador: revisar cada vivienda antes de enseñarla, corregir los repasos, preparar toda la documentación y coordinar las firmas con notaría y bancos.',
            'lessons' => [
                [
                    'title' => '10.1 Inspecciones y repasos',
                    'key_points' => [
                        'Revisión interna vivienda por vivienda con una lista de comprobación (carpinterías, sanitarios, instalaciones funcionando, acabados, limpieza) por el constructor y el equipo del promotor, antes de cualquier visita del comprador.',
                        'Visita de preentrega con el comprador: recorrido guiado, lista de repasos firmada por ambos y plazo comprometido para corregirlos. Una segunda visita confirma que están resueltos.',
                        'Los repasos los ejecuta y paga el constructor dentro de su contrato: la retención de garantía (fase 5) es lo que asegura que vuelva.',
                    ],
                ],
                [
                    'title' => '10.2 Documentación para el comprador',
                    'key_points' => [
                        'Carpeta de la vivienda: planos de lo construido, manual de uso y mantenimiento, garantías de electrodomésticos y equipos, certificado de eficiencia energética y copia de la licencia de primera ocupación o documento equivalente.',
                        'Preparar la documentación de suministros (boletines y certificados) para el cambio de titularidad, y el inventario de llaves y mandos de cada unidad.',
                    ],
                ],
                [
                    'title' => '10.3 Coordinación notarial',
                    'key_points' => [
                        'Calendario de firmas con la notaría y con los bancos de los compradores (o el banco del préstamo promotor si se subrogan). Las tasaciones y ofertas vinculantes deben estar listas antes de convocar.',
                        'Liquidación de cada comprador: precio total más IVA, menos las cantidades entregadas a cuenta, menos el importe en que se subroga, igual a lo que paga en la firma. Se envía por escrito con antelación junto con la convocatoria.',
                        'Lo que lleva el promotor a la notaría: escritura de obra nueva y división horizontal inscrita, licencia de primera ocupación, certificado de eficiencia energética, seguro decenal, certificados de estar al corriente con la comunidad y del IBI, y la carta de pago o cancelación parcial de la hipoteca del préstamo promotor para las viviendas que no se subrogan.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'notario', 'body' => 'Notario colaborador y bancos de los compradores (subrogaciones y nuevas hipotecas).'],
                ['type' => 'legal', 'body' => 'Ley 5/2019 de crédito inmobiliario (plazos de información precontractual) ; RDL 1/2007 (art. 89, gastos que no se repercuten al consumidor) ; Código Civil (art. 1455, gastos de la escritura).'],
            ],
        ],

        // ------------------------------------------------------------------
        11 => [
            'title' => 'Entrega de llaves: escrituras, libro del edificio, suministros, CP, cobro final, entrega de la posesión y del juego de llaves',
            'description' => 'El día de la firma y lo que la rodea: escrituras ante notario, cobro del precio pendiente, libro del edificio, suministros, constitución de la comunidad de propietarios y entrega formal de la posesión y de las llaves.',
            'lessons' => [
                [
                    'title' => '11.1 Escrituras y cobro final',
                    'key_points' => [
                        'Escritura pública de compraventa ante notario y, en su caso, de préstamo hipotecario o subrogación. El comprador paga el IVA del 10 % sobre lo pendiente y el AJD de su escritura al tipo de su comunidad autónoma.',
                        'Cobro final: comprobar antes de entregar las llaves que el pago del precio pendiente está hecho (cheque bancario o transferencia confirmada). Las llaves no se entregan contra una promesa.',
                        'Con la entrega de la vivienda, las garantías de las cantidades a cuenta de ese comprador se cancelan y el promotor puede solicitar su liberación al banco o a la aseguradora.',
                    ],
                ],
                [
                    'title' => '11.2 Libro del edificio, suministros y comunidad de propietarios',
                    'key_points' => [
                        'Libro del Edificio: se entrega a la comunidad de propietarios, y a cada comprador la parte que corresponde a su vivienda (art. 7 LOE).',
                        'Suministros: alta o cambio de titularidad de luz, agua y gas a nombre de cada comprador con los certificados de instalación y la licencia de primera ocupación; lectura de contadores en el acta de entrega.',
                        'Comunidad de propietarios (CP): junta constituyente, nombramiento de presidente y administrador, NIF de la comunidad, cuenta bancaria, seguro del edificio y contratos de mantenimiento (ascensor, protección contra incendios, limpieza). Mientras queden viviendas sin vender, el promotor paga sus cuotas (Ley 49/1960, art. 9).',
                    ],
                ],
                [
                    'title' => '11.3 Entrega de la posesión y del juego de llaves',
                    'key_points' => [
                        'Acta de entrega de llaves firmada por el comprador: fecha, vivienda y anejos, lecturas de contadores, inventario de llaves (vivienda, portal, buzón, trastero) y mandos del garaje, y repasos pendientes si los hubiera, con plazo.',
                        'Las garantías de la LOE no cuentan desde la entrega al comprador sino desde la recepción de la obra entre promotor y constructor (art. 17.1 LOE): explicarlo bien evita malentendidos en postventa.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'otro', 'body' => 'Administradores de fincas colegiados para constituir la comunidad de propietarios.'],
                ['type' => 'legal', 'body' => 'Ley 38/1999 LOE (art. 7 y 17) ; Ley 49/1960 de Propiedad Horizontal (art. 9, 13 y 14) ; Ley 37/1992 del IVA ; RDL 1/1993, texto refundido del Impuesto sobre Transmisiones Patrimoniales y Actos Jurídicos Documentados.'],
            ],
        ],

        // ------------------------------------------------------------------
        12 => [
            'title' => 'Postventa y cierre: información de la Comunidad de Propietarios, garantías, incidencias y liquidación de la promoción',
            'description' => 'Lo que el promotor sigue debiendo después de vender: información a la comunidad, las garantías legales, un sistema serio de incidencias y la liquidación económica, fiscal y societaria de la promoción.',
            'lessons' => [
                [
                    'title' => '12.1 Información a la comunidad y garantías',
                    'key_points' => [
                        'Información a la Comunidad de Propietarios: Libro del Edificio, plan de mantenimiento, contactos de postventa y del seguro decenal, garantías de los equipos comunes y el calendario de revisiones obligatorias de las instalaciones.',
                        'Responsabilidades de la LOE (art. 17), contadas desde la recepción de la obra: diez años por daños en la estructura (cimentación, soportes, vigas, forjados, muros de carga); tres años por defectos que afecten a la habitabilidad; un año por defectos de acabado, a cargo del constructor. El promotor responde solidariamente frente a los compradores con los demás agentes.',
                        'Plazo para reclamar: dos años desde que se producen los daños (art. 18 LOE), sin perjuicio de las acciones contractuales del Código Civil. Plazos y acciones a confirmar con el abogado en cada caso (pendiente de validar con el abogado).',
                    ],
                ],
                [
                    'title' => '12.2 Gestión de incidencias',
                    'key_points' => [
                        'Canal único de incidencias (formulario o correo), con registro, acuse de recibo, plazo de respuesta, visita técnica, asignación al gremio responsable y cierre firmado por el propietario.',
                        'Clasificar cada incidencia por garantía (acabado, habitabilidad, estructura) y responsable; los daños estructurales se comunican de inmediato a la aseguradora del decenal.',
                        'Las incidencias repetidas son información: se revisan con la dirección facultativa y se incorporan a la siguiente promoción.',
                    ],
                ],
                [
                    'title' => '12.3 Liquidación de la promoción',
                    'key_points' => [
                        'Cierre económico: coste real frente a presupuesto y viabilidad partida a partida, beneficio obtenido y rentabilidad de los fondos propios.',
                        'Cancelación del préstamo promotor y de las garantías pendientes; devolución al constructor de la retención y del aval de fiel cumplimiento al terminar el plazo de garantía del contrato; recuperación de fianzas municipales (residuos, urbanización).',
                        'Cierre fiscal y societario: IVA de las ventas, impuesto sobre sociedades (el resultado se reconoce con la entrega de las viviendas), reparto de beneficios y, si la sociedad era solo para esta promoción, su disolución y liquidación una vez prescritas o cubiertas las responsabilidades. Criterios a confirmar con el asesor fiscal (pendiente de validar con el asesor fiscal).',
                        'Memoria de lecciones aprendidas: qué falló en plazo, coste, calidad y venta, para la siguiente promoción.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'asesor_fiscal', 'body' => 'Asesor fiscal y contable para la liquidación de la sociedad promotora ; abogado para reclamaciones de postventa.'],
                ['type' => 'legal', 'body' => 'Ley 38/1999 LOE (art. 17, 18 y 19) ; Código Civil (art. 1591 y 1964) ; Ley 27/2014 del Impuesto sobre Sociedades ; RDL 1/2010, Ley de Sociedades de Capital (disolución y liquidación).'],
            ],
        ],
    ],

    /** ANEXO — normativa citada en la formación. */
    'legal_annex' => [
        'Ley 38/1999, de 5 de noviembre, de Ordenación de la Edificación (LOE): agentes, responsabilidades, garantías, Libro del Edificio y disposición adicional primera (cantidades a cuenta, redactada por la Ley 20/2015).',
        'Real Decreto Legislativo 7/2015, de 30 de octubre, texto refundido de la Ley de Suelo y Rehabilitación Urbana, y la ley urbanística de cada comunidad autónoma.',
        'Real Decreto 314/2006, de 17 de marzo, Código Técnico de la Edificación (CTE), y sus Documentos Básicos.',
        'Real Decreto 1000/2010, de 5 de agosto, sobre visado colegial obligatorio.',
        'Real Decreto 1627/1997, de 24 de octubre, disposiciones mínimas de seguridad y salud en las obras de construcción; Ley 31/1995 de Prevención de Riesgos Laborales.',
        'Ley 32/2006, de 18 de octubre, reguladora de la subcontratación en el sector de la construcción, y Real Decreto 1109/2007.',
        'Real Decreto 470/2021, de 29 de junio, Código Estructural.',
        'Real Decreto 390/2021, de 1 de junio, procedimiento básico para la certificación de la eficiencia energética de los edificios.',
        'Real Decreto 105/2008, de 1 de febrero, producción y gestión de los residuos de construcción y demolición; Ley 7/2022 de residuos y suelos contaminados.',
        'Real Decreto Legislativo 2/2004, texto refundido de la Ley Reguladora de las Haciendas Locales (ICIO y tasas).',
        'Ley 37/1992 del Impuesto sobre el Valor Añadido; Real Decreto Legislativo 1/1993, texto refundido del ITP y AJD.',
        'Real Decreto 515/1989, información en la compraventa de viviendas; Real Decreto Legislativo 1/2007, texto refundido de la Ley General para la Defensa de los Consumidores y Usuarios.',
        'Ley 5/2019, de 15 de marzo, reguladora de los contratos de crédito inmobiliario.',
        'Ley 49/1960, de 21 de julio, sobre Propiedad Horizontal; Ley Hipotecaria (art. 202).',
        'Orden ECO/805/2003, normas de valoración de bienes inmuebles.',
    ],

    'disclaimer' => 'Borrador redactado a partir del resumen de fases del formador (26/10/2026) y de la normativa estatal. Antes de grabar y antes de cualquier venta, el formador revisa cada fase, y las fases 1, 4, 5, 8, 9 y 12 se repasan además con el notario, el abogado y el asesor fiscal colaboradores: la normativa autonómica y municipal cambia de una comunidad a otra y de un año a otro.',
];
