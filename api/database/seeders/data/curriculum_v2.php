<?php

/**
 * Curriculum v2.0 — "La Promotion Immobilière en Algérie" (juillet 2026).
 *
 * Source of truth: the v2.0 programme handoff. Legal figures (échéancier
 * 20/15/35/25/5, plafonds 10 % et 20 %, 60 000 DA, amendes) are reproduced
 * verbatim and must never be reworded.
 *
 * Shape:
 *   modules[] = title, description, lessons[], blocks[]
 *   lessons[] = title, key_points[], content, blocks[]
 *   blocks[]  = ['type' => legal|partner_slot|expert_tip, 'body' => ..., 'partner_category' => ...]
 *
 * Arabic is intentionally absent: this pass is French-only, the AR keys are
 * created empty and a human translation follows.
 */

return [
    'course' => [
        'title_fr' => 'La Promotion Immobilière en Algérie',
        // Existing human translation of the (unchanged) course title.
        'title_ar' => 'الترقية العقارية في الجزائر',
        'description_fr' => 'De la création de la société à la remise des clés : le circuit réel, étape par étape. 13 modules concrets — agrément, foncier, permis de construire, chantier, vente sur plan, raccordements, livraison, pilotage financier et gestion des risques — avec les guichets, les délais réalistes, les coûts et les erreurs fréquentes. Une formation de praticien, adossée aux textes de loi algériens en vigueur (vérifiée en juillet 2026).',
        'format_note' => 'Format : 13 modules vidéo (15 à 40 min par leçon), 1 checklist PDF par module, modèles de documents en annexes, et un « circuit administratif » visuel (carte du parcours avec délais et guichets). Chaque module se termine par : documents à obtenir, où aller, combien ça coûte, délai réaliste, erreurs fréquentes.',
    ],

    'modules' => [
        // ------------------------------------------------------------------
        1 => [
            'title' => 'Le métier, le marché et le business model',
            'description' => "Ce qu'est réellement la promotion immobilière au sens de la loi, le business model d'un praticien, l'écosystème des acteurs et les deux modes de vente du promoteur.",
            'lessons' => [
                [
                    'title' => 'Le métier, le marché et le business model',
                    'key_points' => [
                        "Ce qu'est la promotion immobilière au sens de la loi (construction neuve, réhabilitation, rénovation, en vue de vendre ou louer) et ce qu'elle n'est pas (agent immobilier, entrepreneur BTP).",
                        "Le business model type communiqué par l'intervenant : terrain environ 25 % du coût (prévoir une marge de sécurité d'environ 10 % en plus sur ce poste), gestion et marketing 7 %, construction 68 % ; trésorerie de départ à sécuriser avant lancement (l'intervenant cite environ 35 % du chiffre d'affaires, ou un échéancier négocié avec le vendeur du terrain) ; marge brute cible annoncée autour de 50 %. À présenter comme ordres de grandeur d'un praticien, à valider par une étude de faisabilité par projet et par wilaya.",
                        "Les acteurs de l'écosystème : wilaya, APC, notaire, CNRC, impôts, FGCMPI, CTC, bureau d'études, géomètre-expert, banques, assureurs — et les quatre directions consultées sur chaque permis : urbanisme, santé, protection civile, hydraulique.",
                        "Les deux modes de vente du promoteur : la vente sur plan (VSP), entièrement encadrée par la loi 11-04 (agrément, FGCMPI, échéancier réglementé) ; et la vente d'un bien achevé, après achèvement complet et certificat de conformité — vente immobilière classique chez le notaire, sans échéancier VSP ni attestation de garantie. Détail au module 8.",
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'legal', 'body' => 'Loi 11-04 du 17/02/2011 (définition, art. 3 ; exercice réservé aux agréés, art. 4).'],
            ],
        ],

        // ------------------------------------------------------------------
        2 => [
            'title' => 'Créer la société (le véhicule juridique)',
            'description' => "ORDRE RÉEL DU PARCOURS (correction d'expert) : la promotion immobilière est une activité réglementée — on ne crée pas la société complète puis l'agrément, mais : 1) dénomination au CNRC ; 2) dossier d'agrément auprès de la wilaya (module 3) ; 3) statuts chez le notaire + registre de commerce ; 4) NIF / C20 ; 5) affiliation FGCMPI en dernier — le dossier officiel d'affiliation exige justement l'agrément, le RC, le NIF, les statuts et le RIB, preuve de cette chronologie. La pratique variant selon les wilayas, faire valider l'enchaînement exact par le consultant agrément partenaire.",
            'lessons' => [
                [
                    'title' => '2.1 Choisir la forme',
                    'key_points' => [
                        'EURL : 1 associé unique, capital libre, 1 gérant. Idéal pour démarrer seul.',
                        'SARL : 2 à 50 associés, capital libre fixé aux statuts, gérant(s) désigné(s) ; possibilité gérant + cogérant (utile en 50/50).',
                        "SPA : minimum 7 actionnaires, capital minimum 1 000 000 DA (5 000 000 DA si appel public à l'épargne), conseil d'administration + commissaire aux comptes obligatoire. Pour les gros projets et l'entrée d'investisseurs.",
                        'Conseil pratique de la classe : démarrer en EURL/SARL dédiée exclusivement à la promotion immobilière (activité exclusive), quitte à créer une société par projet plus tard (logique de cantonnement du risque).',
                    ],
                ],
                [
                    'title' => '2.2 Le circuit de création, dans l\'ordre',
                    'key_points' => [
                        'Dénomination sociale : proposer plusieurs noms par ordre de préférence au CNRC (en ligne via sidjilcom.cnrc.dz ou en antenne) et obtenir le certificat de dénomination.',
                        "Local / siège social : trouver le local, récupérer l'adresse exacte auprès du bailleur ; le bailleur fournit son acte de propriété, son acte de naissance et l'assurance CAT-NAT du local.",
                        "Chez le notaire : contrat de location au nom de la société (durée + loyer) puis rédaction des statuts. Contenu détaillé des statuts à enseigner : dénomination et siège, objet social (code d'activité de promotion immobilière), durée de la société, apports et capital, répartition des parts, désignation du ou des gérants et étendue de leurs pouvoirs, règles de cession des parts et de décision entre associés. Les associés signent en présence, munis d'actes de naissance et pièces d'identité.",
                        "Publication au BOAL (Bulletin Officiel des Annonces Légales) : le notaire s'en charge ; c'est la naissance officielle de la société.",
                        "Immatriculation au registre de commerce (CNRC) : statuts + BOAL + bail + dénomination + pièces des associés + agrément pour l'activité réglementée. Délai courant : quelques jours à deux semaines.",
                        "Impôts (DGI) : obtention du NIF et du certificat d'existence C20 (les services fiscaux peuvent vérifier physiquement l'adresse du siège). Le C20 porte le numéro d'article d'imposition.",
                        "NIS auprès de l'Office National des Statistiques (RC + statuts + BOAL + C20 + NIF + bail).",
                        "CNAS : ouverture du numéro d'employeur pour déclarer et cotiser pour les salariés.",
                        "Banque : ouverture du compte société (RC + statuts + pièces du gérant) et dépôt de l'intégralité du capital ; remise du chéquier. Aucun frais d'ouverture notable.",
                        'Cachet de la société chez un prestataire privé (sur présentation du RC).',
                    ],
                ],
                [
                    'title' => '2.3 Obligations récurrentes dès le jour 1',
                    'key_points' => [
                        'G50 : déclaration fiscale mensuelle avant le 20 du mois (TVA, IRG salaires, acomptes).',
                        'CNAS : cotisations sociales des salariés (versement trimestriel possible pour petits employeurs).',
                        'CASNOS : cotisation annuelle du gérant non salarié, calculée sur le revenu déclaré avec un minimum annuel — obligation oubliée dans 90 % des prévisionnels de débutants ; montant en vigueur à vérifier auprès de la CASNOS.',
                        'Comptabilité tenue obligatoirement ; bilan annuel ; commissaire aux comptes selon forme et seuils.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'notaire', 'body' => 'Notaire partenaire (création + tous les actes du parcours) ; expert-comptable partenaire (externalisation citée dans les notes autour de 600 EUR/mois — à re-tarifer en DZD selon volume) ; banque partenaire.'],
                ['type' => 'legal', 'body' => "Code de commerce (ord. 75-59 modifiée, loi 15-20) ; loi 04-08 sur l'exercice des activités commerciales ; décret 15-111 (immatriculation CNRC)."],
            ],
        ],

        // ------------------------------------------------------------------
        3 => [
            'title' => "L'agrément de promoteur immobilier (LE module bloquant)",
            'description' => "Nul ne peut exercer la promotion immobilière ni initier un projet sans agrément et inscription au Tableau National des Promoteurs Immobiliers (TNPI). L'agrément est délivré au niveau de la wilaya (transfert du ministère vers les wilayas par le décret 19-243 de 2019). Il est personnel : ni cessible ni transmissible ; tout changement de forme, de dénomination ou de gérant rend l'agrément caduc et impose son renouvellement.",
            'lessons' => [
                [
                    'title' => '3.1 Conditions',
                    'key_points' => [
                        'Personne physique : 25 ans minimum ; personne morale : société de droit algérien.',
                        'Bonne moralité (casier judiciaire), jouissance des droits civiques, aucune incapacité/interdiction (art. 20 et 21, loi 11-04).',
                        "Ressources financières suffisantes pour réaliser ses projets (justification encadrée par l'arrêté interministériel du 06/12/2012).",
                        "Capacités professionnelles : diplôme supérieur en bâtiment, travaux publics, architecture, droit, gestion ou économie — sinon, collaboration permanente et effective d'une personne qualifiée (un ingénieur ou diplômé à intégrer dans l'équipe).",
                        'Assurance responsabilité civile professionnelle souscrite.',
                        'Cahier des charges type des engagements du promoteur (décret 12-85) rempli et signé.',
                    ],
                ],
                [
                    'title' => "3.2 Après l'agrément",
                    'key_points' => [
                        "Délai réel constaté par les praticiens : jusqu'à un an entre le dépôt du dossier et l'agrément — prévoir un suivi actif et régulier du dossier auprès des services de la wilaya (relances documentées), et utiliser ce temps pour le sourcing foncier et le montage financier.",
                        "Inscription au TNPI (Tableau National des Promoteurs Immobiliers) : le registre officiel de la profession, tenu sous l'égide du ministère de l'Habitat. Règle officielle à retenir : l'attestation d'inscription au TNPI et l'affiliation au FGCMPI valent autorisation d'exercer la profession (source : FGCMPI).",
                        'Affiliation obligatoire au FGCMPI (Fonds de Garantie et de Caution Mutuelle de la Promotion Immobilière) — condition préalable à toute vente sur plan.',
                        "Dossier d'affiliation FGCMPI (formulaire officiel, modèle mis à jour le 11/07/2024, téléchargeable sur fgcmpi.org.dz) : l'imprimé officiel dûment renseigné (avec l'état des projets engagés ou à engager et leur source de financement : VSP, crédit bancaire ou autofinancement) ; copie de l'attestation d'agrément ; attestation d'immatriculation fiscale (NIF) ; copie des statuts (personnes morales) ; copie du registre de commerce ; copie de la domiciliation bancaire (RIB) ; copie des pièces d'identité du gérant et des associés ; droits d'affiliation de 60 000 DA par virement ou versement BDL.",
                        "Cotisation annuelle FGCMPI ensuite obligatoire pour conserver la qualité d'affilié : barème officiel par catégorie de chiffre d'affaires (A6 à A1, de moins de 10 MDA à plus de 1 000 MDA), dégressif avec l'ancienneté — de l'ordre de 10 000 DA/an pour un petit promoteur à 237 500 DA/an pour la catégorie la plus haute (barème joint au formulaire officiel).",
                        "L'activité est exclusive : la société agréée ne peut pas exercer une autre activité réglementée en parallèle — vérifié et confirmé par le cahier des charges type des engagements du promoteur (décret 12-85) ; on ne loge donc pas la promotion dans une société fourre-tout.",
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'consultant_agrement', 'body' => "Consultant/juriste spécialisé en montage de dossiers d'agrément auprès des wilayas (fort taux de rejet des dossiers incomplets) ; personne qualifiée « diplômé bâtiment/droit » à proposer en collaboration aux candidats qui n'ont pas le diplôme."],
                ['type' => 'legal', 'body' => 'Loi 11-04 (art. 4, 20-23, 55, 77) ; décret 12-84 modifié par 13-96 et 19-243 ; décret 12-85 ; arrêté interministériel du 06/12/2012.'],
            ],
        ],

        // ------------------------------------------------------------------
        4 => [
            'title' => 'Le foncier : trouver, vérifier et acheter le terrain',
            'description' => "Vérifier un titre, exiger le certificat négatif, lire un certificat d'urbanisme (COS/CES) et acheter au nom de la société — puis savoir où trouver le foncier.",
            'lessons' => [
                [
                    'title' => 'Vérifier et acheter le terrain',
                    'key_points' => [
                        "Vérification du titre : l'acte de propriété du vendeur est remis au notaire, qui vérifie la chaîne de propriété, les servitudes, les inscriptions à la conservation foncière.",
                        "Certificat négatif auprès de la conservation foncière (demande avec copie de l'acte) : il révèle tout ce qui grève le terrain — hypothèques, saisies, oppositions, inscriptions quelconques. Aucun achat sans ce document vierge ; le notaire le demande systématiquement, mais l'élève doit savoir l'exiger et le lire lui-même.",
                        "Certificat d'urbanisme auprès de l'APC du lieu du terrain — circuit réel : c'est le propriétaire (le vendeur, avant votre achat) qui le demande, avec copie de son acte et un extrait du plan cadastral retiré au cadastre / à la conservation foncière ; l'APC transmet à la direction de l'urbanisme, qui répond. Dossier en plusieurs exemplaires (le texte prévoit 2, la pratique en demande souvent 3) ; délai réglementaire de notification : 15 jours, délai réel constaté : un mois minimum ; recours auprès de la direction de l'urbanisme de la wilaya, puis du ministère.",
                        "Ce que dit le certificat : la typologie constructible (habitat individuel, semi-collectif type duplex, ou collectif R+1 à R+10 et plus), le gabarit, le COS (coefficient d'occupation du sol : surface de plancher constructible rapportée à la surface du terrain) et le CES (coefficient d'emprise au sol : emprise bâtie au sol rapportée à la surface du terrain). Ces deux coefficients déterminent directement combien de mètres carrés vendables le terrain peut produire — donc sa vraie valeur pour un promoteur.",
                        'Achat chez le notaire : acte au nom de la société. Les apports des associés (immédiats ou futurs) et tout arrangement entre associés se formalisent chez le notaire.',
                        "Alternative foncier public : concession convertible en cession de terrains du domaine privé de l'État pour projets de promotion à caractère commercial (décret 15-281).",
                        "Point clé enseigné : le POS décide de ce que vous pouvez construire (nombre d'étages, occupation du sol) — acheter le terrain AVANT le certificat d'urbanisme est l'erreur n°1 du débutant.",
                    ],
                    'blocks' => [
                        ['type' => 'expert_tip', 'body' => "Faire l'étude géotechnique AVANT d'acheter le terrain : si l'affaire ne se fait pas, vous perdez le prix d'une étude — si vous achetez sans, vous risquez de découvrir après coup un sol qui dévore votre marge en fondations spéciales. Petite perte certaine contre grosse perte possible : c'est le meilleur argent dépensé du projet."],
                    ],
                ],
                [
                    'title' => '4.5 Comment trouver le foncier (sourcing)',
                    'key_points' => [
                        'Le terrain ne se trouve pas derrière un écran : tourner physiquement dans les quartiers cibles, repérer les parcelles vides ou les bâtisses à démolir, interroger le voisinage et les commerçants — la moitié des bonnes affaires se conclut avant toute annonce.',
                        "Canaux complémentaires : notaires (successions et indivisions à dénouer — le meilleur gisement), agences immobilières agréées, annonces (Ouedkniss et similaires), ventes aux enchères judiciaires, services d'urbanisme et agences foncières de wilaya pour le foncier public (concession convertible en cession, décret 15-281).",
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'autre', 'body' => 'Agence(s) immobilière(s) agréée(s) orientées foncier, par région — sourcing de terrains qualifiés pour les élèves.'],
                ['type' => 'legal', 'body' => "Loi 90-29 (aménagement et urbanisme) ; décret 15-19 modifié (art. 2-6 : certificat d'urbanisme) ; loi 90-25 (orientation foncière) ; décret 15-281."],
            ],
        ],

        // ------------------------------------------------------------------
        5 => [
            'title' => 'Études techniques et permis de construire',
            'description' => "De l'étude de sol au permis obtenu : le bureau d'études, le guichet unique, les quatre services consultés, la déclaration d'ouverture de chantier et le permis modificatif.",
            'lessons' => [
                [
                    'title' => '5.1 Étude géotechnique',
                    'key_points' => [
                        "Laboratoire agréé pour l'étude de sol (sondages, portance, niveau du bon sol, présence d'eau). Sert de base au dimensionnement de l'infrastructure et de la superstructure. Délai indicatif cité par l'intervenant : jusqu'à 3 mois. Comment trouver : organisme étatique de contrôle, bouche-à-oreille, internet. Rappel du module 4 : idéalement, cette étude est faite AVANT l'achat du terrain.",
                    ],
                    'blocks' => [
                        ['type' => 'partner_slot', 'partner_category' => 'bureau_etudes', 'body' => 'Laboratoire(s) géotechnique(s) agréé(s) — 1 à 2 par région (Centre/Ouest/Est).'],
                    ],
                ],
                [
                    'title' => "5.2 Bureau d'études (maîtrise d'œuvre)",
                    'key_points' => [
                        "Bureau d'études/architecte agréé par l'État (Ordre des architectes). Reçoit l'étude géotechnique + le certificat d'urbanisme, produit l'esquisse, l'avant-projet (maquette 3D, nombre de logements/commerces/services selon les règles d'urbanisme), puis le dossier d'exécution.",
                        "Rémunération : barème réglementé pour la maîtrise d'œuvre ; l'intervenant cite une pratique de l'ordre de 2 % négociables sur le projet. Tout est négociable, mais formaliser par contrat de maîtrise d'œuvre.",
                    ],
                    'blocks' => [
                        ['type' => 'partner_slot', 'partner_category' => 'bureau_etudes', 'body' => "2 à 3 bureaux d'études agréés (architecture + génie civil + VRD) — c'est LE partenaire central de toute la classe : il monte le dossier de permis, produit le devis quantitatif, consulte les entreprises et assure le suivi."],
                    ],
                ],
                [
                    'title' => '5.3 Dépôt et instruction du permis de construire',
                    'key_points' => [
                        "Le bureau d'études dépose le dossier au guichet unique (APC ou wilaya selon l'envergure) : plans d'architecture, génie civil, hydraulique, protection civile, acte de propriété, registre de commerce. Nombre d'exemplaires selon le texte officiel : 3 pour l'habitat individuel, 8 pour les projets nécessitant la consultation des services publics (décret 15-19, art. 45) — la pratique varie selon les guichets (l'intervenant en a déposé 5) : se renseigner au dépôt. Un exemplaire reste à l'APC, les autres partent aux services consultés.",
                        "Ce que chaque service vérifie dans votre dossier — à anticiper dès la conception : l'urbanisme (respect du POS, accessibilité du projet) ; la protection civile (sorties de secours, sécurité incendie) ; la santé (superficies correctes des pièces, dimensions des fenêtres pour la lumière naturelle, aération et humidité) ; l'hydraulique (raccordements, diamètres des canalisations, évacuation des eaux vers le réseau). Un bureau d'études qui intègre ces exigences en amont évite 90 % des allers-retours d'instruction.",
                        "L'instruction recueille les avis : protection civile, santé, hydraulique, direction de l'urbanisme de la wilaya. La commission se réunit périodiquement ; en cas de lacune, elle contacte directement le bureau d'études. Délai indicatif global cité par l'intervenant : environ 5 mois études comprises. Les réformes 2020 et 2024 (guichets uniques renforcés + plateforme numérique de dépôt) visent des délais raccourcis — vérifier les délais actuels auprès du guichet unique de la wilaya concernée.",
                        "L'arrêté de permis mentionne tout : nombre de logements, parkings, délai de validité et de démarrage des travaux (caducité si non-respect ; renouvellement sans nouvelle instruction si les règles n'ont pas évolué). Frais à payer à la délivrance (taxe sur les permis immobiliers).",
                        "Convention CTC : à conclure immédiatement après la signature du contrat de maîtrise d'œuvre — le contrôle technique est obligatoire (génie civil / solidité / étanchéité) et conditionne la décennale.",
                    ],
                    'blocks' => [
                        ['type' => 'partner_slot', 'partner_category' => 'bureau_etudes', 'body' => 'CTC (organisme public — contact régional à fournir) ; éventuellement organismes de contrôle agréés.'],
                    ],
                ],
                [
                    'title' => "5.4 La déclaration d'ouverture de chantier (obligation souvent oubliée)",
                    'key_points' => [
                        "Pour tout chantier occupant plus de 9 travailleurs pendant plus d'une semaine, le maître d'ouvrage remplit le formulaire de déclaration d'ouverture de chantier (délivré par les agences CNAS), établi en 4 exemplaires, daté et signé.",
                        "À communiquer 10 jours AVANT l'ouverture du chantier à trois destinataires : la CNAS, l'OPREBATPH (organisme de prévention des risques professionnels du BTPH) et l'inspection du travail.",
                        "Pratique de guichet signalée par l'expert : déposer également une déclaration d'ouverture de chantier auprès de l'APC (certaines communes l'exigent ou la délivrent contre récépissé) — à confirmer localement au guichet unique, la date portée devant correspondre à celle du panneau.",
                        "Côté urbanisme : la date d'ouverture du chantier et la date prévue de fin des travaux figurent sur le panneau réglementaire (module 6). Ouvrir un chantier sans les formalités (clôture de protection, panneau signalétique) est sanctionné d'une amende de 5 000 à 10 000 DA, et ne pas démarrer les travaux dans les délais du permis de 10 000 à 30 000 DA (loi 08-15, art. 89-90).",
                    ],
                ],
                [
                    'title' => '5.5 Le permis de construire modificatif',
                    'key_points' => [
                        "Toute modification du projet en cours de réalisation touchant les éléments approuvés du permis — implantation, gabarit, vocation, façades (exactement ce que la commission de conformité vérifiera, art. 66 du décret 15-19) — ou la consistance du programme (nombre de logements, surfaces) doit être couverte par un permis modificatif AVANT exécution, instruit dans les mêmes formes et les mêmes délais que le permis initial, auprès du même guichet unique (délais d'instruction raccourcis par le décret 15-19 et ses réformes ; à confirmer auprès du guichet de la wilaya). Intégrer ce délai au planning : les travaux sur la partie modifiée ne peuvent pas démarrer avant l'obtention.",
                        "Construire hors permis = refus du certificat de conformité en fin de projet, donc blocage des actes définitifs des clients, plus les sanctions de la loi 08-15. Réflexe à enseigner : toute demande de modification (client ou technique) passe d'abord par le bureau d'études, qui tranche : simple adaptation d'exécution ou permis modificatif.",
                        "Si la modification change la division du projet (nombre ou consistance des unités), l'EDD doit être modifié en parallèle auprès de la conservation foncière (EDD modificatif, voir module 7).",
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'legal', 'body' => "Décret 15-19 modifié (permis de construire, art. 41 et s. ; conformité art. 64-66) ; décret du 22/11/2020 et réforme 2024 (plateforme numérique, guichets uniques) ; arrêté interministériel du 15/05/1988 (maîtrise d'œuvre, art. 18 : convention CTC) ; arrêté du 26/07/2008 (déclaration d'ouverture de chantiers BTPH) ; loi 08-15 (art. 89-90)."],
            ],
        ],

        // ------------------------------------------------------------------
        6 => [
            'title' => 'Installer et ouvrir le chantier',
            'description' => "Étape charnière ajoutée sur recommandation d'expert : entre le permis obtenu et le premier coup de pelle, l'installation de chantier conditionne la sécurité, la conformité et la productivité de tout le projet.",
            'lessons' => [
                [
                    'title' => "6.1 L'installation de chantier",
                    'key_points' => [
                        "Clôture de protection du terrain : obligatoire dès l'ouverture — son absence est une infraction sanctionnée (loi 08-15, art. 89).",
                        "Panneau réglementaire de chantier : rectangulaire, dimensions supérieures à 80 cm, visible de l'extérieur, conforme au modèle-type annexé au décret 15-19 ; il indique les références du permis de construire, la nature de la construction, sa hauteur, la surface du terrain, la date d'ouverture du chantier, la date prévue de fin des travaux et, s'il y a lieu, le maître d'ouvrage, le bureau d'études et l'entreprise de réalisation (décret 15-19, art. 60). Maintenu pendant toute la durée du chantier.",
                        "Base de vie : bureaux de chantier, vestiaires, sanitaires, réfectoire, loge gardien — dimensionnée selon l'effectif, conformément aux prescriptions d'hygiène du décret 05-12.",
                        'Alimentation provisoire en eau et en électricité : branchements de chantier à demander auprès des concessionnaires (Sonelgaz ; SEAAL à Alger, ADE ailleurs) — anticiper les délais, rien ne démarre sans.',
                        "Plan d'installation de chantier (PIC), établi avec le bureau d'études et l'entreprise : accès et circulations, zones de stockage, position de la grue et son rayon, base de vie, signalisation.",
                    ],
                ],
                [
                    'title' => '6.2 Les autorisations spécifiques',
                    'key_points' => [
                        "Occupation temporaire de la voie publique (emprise sur trottoir ou chaussée pour clôture, échafaudage, benne, stationnement d'engins) : autorisation de voirie à demander à l'APC, redevance selon la commune.",
                        "Fermeture temporaire de route si nécessaire (levages exceptionnels, livraisons hors gabarit) : accord préalable de l'APC et des services de sécurité, avec signalisation et itinéraire de déviation.",
                        'Grues et équipements de levage : implantation prévue au PIC, montage et vérifications périodiques par un organisme habilité, gestion du survol des propriétés voisines ; conserver les certificats de contrôle sur le chantier.',
                    ],
                ],
                [
                    'title' => '6.3 La gestion HSE (hygiène, sécurité, environnement)',
                    'key_points' => [
                        "Cadre légal : loi 88-07 (hygiène, sécurité et médecine du travail) et décret 05-12 (prescriptions particulières applicables aux chantiers du bâtiment, des travaux publics et de l'hydraulique).",
                        "Plan d'hygiène et de sécurité (PHS) du chantier, établi selon l'arrêté du 26/07/2008.",
                        "Équipements de sécurité : EPI fournis par l'employeur (casque, chaussures, gants, gilet, harnais pour travail en hauteur), protections collectives (garde-corps, filets), signalisation de sécurité, accueil sécurité de tout nouvel intervenant.",
                        "Organisation : commission paritaire d'hygiène et de sécurité (CPHS) obligatoire au-delà de 9 travailleurs (décret 05-09) ; comité inter-entreprises d'hygiène et de sécurité pour les chantiers multi-entreprises (décret 05-10) ; registre d'hygiène et de sécurité tenu sur le chantier ; médecine du travail pour les salariés.",
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'autre', 'body' => 'Consultant/organisme HSE (rédaction du PHS, formation des équipes) ; organisme habilité de vérification des appareils de levage.'],
                ['type' => 'legal', 'body' => "Loi 88-07 ; décrets exécutifs 05-09, 05-10, 05-11 et 05-12 du 08/01/2005 ; arrêtés du 26/07/2008 (PHS et déclaration d'ouverture de chantiers) ; décret 15-19 (art. 60) ; loi 08-15 (art. 89)."],
            ],
        ],

        // ------------------------------------------------------------------
        7 => [
            'title' => "Diviser le projet : l'EDD et le livret foncier par unité",
            'description' => 'Transformer un terrain unique en N unités vendables, chacune avec son livret foncier — la condition matérielle de toute vente.',
            'lessons' => [
                [
                    'title' => "L'État Descriptif de Division et les livrets fonciers",
                    'key_points' => [
                        "Engager un géomètre-expert agréé par l'État.",
                        "Le mettre en relation avec le bureau d'études pour établir l'État Descriptif de Division (EDD) : le terrain unique (un seul acte) devient N unités — exemple des notes : 200 logements, 40 services, 20 locaux + parkings.",
                        "L'EDD est transmis au cadastre / à la conservation foncière, qui crée un livret foncier et un numéro pour chaque unité issue de la division.",
                        'Préparer en parallèle le règlement de copropriété selon le modèle type (décret 14-99) — il sera annexé aux ventes.',
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'autre', 'body' => 'Géomètre(s)-expert(s) agréé(s) par région.'],
                ['type' => 'legal', 'body' => 'Décret 14-99 du 04/03/2014 (modèle de règlement de copropriété en promotion immobilière) ; réglementation cadastre/conservation foncière.'],
            ],
        ],

        // ------------------------------------------------------------------
        8 => [
            'title' => 'Vendre : les deux modes, et la vente sur plan (VSP) en détail',
            'description' => "Le cœur commercial et juridique du métier : choisir son mode de vente, réunir les préalables non négociables, appliquer l'échéancier réglementé et monter la machine commerciale.",
            'lessons' => [
                [
                    'title' => '8.0 Les deux modes de vente du promoteur',
                    'key_points' => [
                        "Vente sur plan (VSP) : le régime de la loi 11-04 — tout ce module. Elle exige la totalité des préalables ci-dessous et suit l'échéancier de paiement réglementé.",
                        "Vente d'un bien achevé : le promoteur construit jusqu'au bout sur ses fonds, obtient le certificat de conformité (l'expert parle d'attestation de bonne exécution), puis vend en vente immobilière classique chez le notaire — sans échéancier VSP ni attestation de garantie de vente sur plan. Plus simple juridiquement, mais tout le financement pèse sur le promoteur : c'est un arbitrage trésorerie contre simplicité à enseigner avec un cas chiffré.",
                    ],
                ],
                [
                    'title' => '8.1 Préalables non négociables avant de vendre le moindre mètre carré',
                    'key_points' => [
                        'Agrément + attestation TNPI.',
                        'Acte de propriété du terrain au nom de la société.',
                        'Permis de construire.',
                        "Attestation de garantie FGCMPI du projet (peut être partielle : on peut ne garantir et ne vendre sur plan qu'une partie du projet). Elle est obligatoirement annexée à chaque contrat VSP.",
                        'Convention CTC.',
                        'Règlement de copropriété.',
                    ],
                ],
                [
                    'title' => '8.2 Réservation vs vente sur plan',
                    'key_points' => [
                        "Contrat de réservation (modèle type) : réserve le bien, ce n'est pas une vente.",
                        "Contrat VSP (modèle type, acte notarié, enregistré et publié) : c'est la vente ; les versements de l'acquéreur sont des paiements, modulés selon l'avancement des travaux selon le barème officiel complet du décret 13-431 : 20 % du prix à la signature ; 15 % à l'achèvement des fondations ; 35 % à l'achèvement des gros œuvres (étanchéité et cloisons extérieures et intérieures comprises) ; 25 % à l'achèvement en tous corps d'état (raccordement aux VRD et aménagements extérieurs compris) ; solde de 5 % au procès-verbal de prise de possession.",
                        'Pénalité de retard à la charge du promoteur si la livraison dépasse le délai contractuel : le retard est constaté par huissier de justice, la pénalité est calculée par jour de retard selon la formule du contrat, plafonnée à 10 % du prix de vente, et déduite du solde (hors force majeure).',
                        "Révision du prix : uniquement si le contrat la prévoit (art. 38 de la loi 11-04), selon une formule basée sur les indices officiels des prix, matériaux et main-d'œuvre, plafonnée à 20 % du prix initial sauf circonstances exceptionnelles justifiées.",
                        "Contenu obligatoire du contrat VSP (source FGCMPI) : mentions d'enregistrement et de publicité foncière ; justification de la qualité de promoteur (agrément, TNPI, affiliation) ; justification de la propriété du terrain et permis de construire ; description détaillée du bien ; prix prévisionnel et échéancier conforme au modèle réglementaire ; garanties légales, décennale et de bonne exécution + attestation de garantie du Fonds ; conditions de résiliation ; modalités de révision du prix le cas échéant.",
                        "En cas de défaut de paiement de l'acquéreur — procédure réelle décrite par l'expert, dans le cadre du contrat type : mises en demeure par huissier de justice, espacées (il pratique trois mises en demeure à une semaine d'intervalle environ) ; si l'acquéreur ne régularise pas, action en justice en résolution de la vente — c'est un jugement qui prononce l'annulation de l'acte, pas le promoteur seul ; la retenue appliquée au client défaillant est celle prévue par le contrat type, et le remboursement du solde intervient après la revente du lot. Jamais de pratique informelle : chaque étape est écrite et datée.",
                    ],
                ],
                [
                    'title' => '8.3 Monter la machine commerciale',
                    'key_points' => [
                        "Équipe : responsable commercial + commerciaux + comptable/administrateur + caissier (encaissement) + gardiennage du site. Cette équipe peut être entièrement salariée en interne (contrats de travail, déclaration CNAS, ce que fait l'expert) ou partiellement externalisée — arbitrage coût fixe / commission à enseigner. Avant les ventes : le bureau d'études forme les commerciaux sur le projet (plans, prestations, délais).",
                        'Le marketing est piloté avec les commerciaux : plan de communication, maquettes 3D, bureau de vente, échéancier de paiement type à présenter au client.',
                        'Financement des acquéreurs : conventions avec les banques (crédits immobiliers des acheteurs) — argument de vente majeur.',
                    ],
                    'blocks' => [
                        ['type' => 'partner_slot', 'partner_category' => 'banque', 'body' => 'Banques partenaires (crédit acquéreur + crédit promoteur) ; agence marketing immobilier (production 3D, Meta Ads locales, landing pages) ; FGCMPI (organisme, contacts régionaux fournis en annexe).'],
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'legal', 'body' => 'Loi 11-04 (art. 25-27, 38, 52-58) ; décret 13-431 (modèles types, tranches 20/15/35/25/5, pénalités, révision) ; décrets 14-180/181/182 de 2014 (FGCMPI).'],
            ],
        ],

        // ------------------------------------------------------------------
        9 => [
            'title' => 'Construire : entreprises, chantier, assurances, aléas',
            'description' => 'Consulter et contractualiser les entreprises, suivre le chantier, couvrir les risques par les bonnes assurances, encaisser les aléas du réel et piloter les paiements.',
            'lessons' => [
                [
                    'title' => '9.1 Consultation et contractualisation des entreprises',
                    'key_points' => [
                        "Le bureau d'études produit le devis quantitatif complet (mètres, béton, acier, câbles… — « un livre entier ») puis demande des devis à au moins 3 entreprises par lot et met en relation les corps d'état entre eux.",
                        'Lots types : terrassement ; génie civil/bâtiment (fondations, infrastructure, superstructure) ; maçonnerie ; étanchéité (lancée après ~3 niveaux) ; électricité ; plomberie/chauffage ; menuiserie/boiserie ; vitrerie ; peinture ; revêtements sols et faïence ; cuisines équipées ; ascenseurs ; VRD/travaux publics (routes, trottoirs, eaux usées et pluviales).',
                        "Deux formules : fourniture + pose par l'entreprise, ou fournisseur séparé choisi avec le bureau d'études. Signature de conventions entre le promoteur et chaque entreprise. Délai indicatif pour boucler tous les lots : ~2 mois.",
                    ],
                    'blocks' => [
                        ['type' => 'partner_slot', 'partner_category' => 'autre', 'body' => "Annuaire partenaire par lot : 2-3 entreprises référencées par corps d'état et par région + fournisseurs (béton prêt à l'emploi, acier, câble) + ascensoristes. C'est la plus grosse valeur « contacts réels » de la formation."],
                    ],
                ],
                [
                    'title' => '9.2 Suivi de chantier',
                    'key_points' => [
                        "Suivi assuré par un bureau d'études (le même ou un bureau dédié au suivi, payé à part) : chef de projet (coordonne les sous-traitants), chef d'équipe (encadre ingénieurs/architectes), ingénieurs et architectes de suivi.",
                        "CTC : intervient sur le génie civil (qualité du béton) et l'étanchéité ; ses visas conditionnent les assurances.",
                    ],
                ],
                [
                    'title' => '9.3 Assurances du chantier (à enseigner précisément)',
                    'key_points' => [
                        "RC professionnelle de tous les intervenants : chacun doit pouvoir la justifier à l'ouverture du chantier ; elle court jusqu'à la réception définitive (art. 175-177).",
                        "Décennale (art. 178 + art. 554 code civil) : souscrite par les architectes, entrepreneurs et contrôleurs techniques ; prend effet à la réception définitive et couvre 10 ans ; elle bénéficie aux propriétaires successifs. Le maître d'ouvrage (promoteur) est tenu d'EXIGER contractuellement ces assurances auprès du même assureur et de vérifier leur souscription (art. 179) — et il reste responsable envers ses clients (loi 11-04, art. 49). Sanction du défaut : amende 5 000 à 100 000 DA.",
                        'CAT-NAT (ord. 03-12) sur les biens immobiliers.',
                        "Accidents du travail : la couverture des travailleurs relève de la CNAS — à condition qu'ils soient déclarés par leur employeur (chaque entreprise pour les siens, le promoteur pour ses propres salariés). Exiger contractuellement de chaque entreprise la déclaration CNAS de tous ses ouvriers, avec listes nominatives vérifiées ; des couvertures complémentaires chantier peuvent être souscrites auprès de l'assureur partenaire. La procédure complète en cas d'accident grave ou mortel est au module 13.",
                    ],
                    'blocks' => [
                        ['type' => 'partner_slot', 'partner_category' => 'autre', 'body' => "Compagnie(s) d'assurance partenaires (SAA, CAAT, CAAR, CIAR, GIG…) avec un interlocuteur « risques construction » dédié."],
                    ],
                ],
                [
                    'title' => "9.4 Les aléas du réel (retour d'expérience de l'intervenant)",
                    'key_points' => [
                        "Bon sol à 6 m d'un côté, 8 m de l'autre ; venues d'eau en creusant (ennemi n°1) ; béton non conforme à casser — à la charge de l'entreprise de réalisation ; météo (pluies qui arrêtent le chantier) ; prévoir un délai de grâce (~6 mois) dans le planning et les contrats.",
                        "Autres surprises budgétaires à provisionner : travaux de renforcement du sol au terrassement quand le bon sol est plus profond que prévu (blocage, substitution, pieux) — des coûts réels à intégrer au prévisionnel ; dommages aux constructions voisines (fissures chez les mitoyens) : c'est la RC qui joue, constat immédiat par huissier et déclaration à l'assureur.",
                    ],
                ],
                [
                    'title' => '9.5 Le suivi financier du chantier',
                    'key_points' => [
                        "Attachements : relevés contradictoires des quantités réellement exécutées, signés sur site par l'entreprise et la maîtrise d'œuvre de suivi — c'est la pièce de base de tout paiement.",
                        "Situations de travaux : états d'avancement périodiques (généralement mensuels) établis à partir des attachements et des prix de la convention, vérifiés et visés par le bureau de suivi avant tout règlement.",
                        "Paiements des entreprises : selon l'échéancier de la convention signée (avance éventuelle, règlement des situations, retenue de garantie libérée après levée des réserves — modalités à fixer contractuellement) ; rapprocher en permanence les décaissements chantier des encaissements VSP (pilotage de trésorerie).",
                        "Avenants : toute modification de consistance, de prix ou de délai fait l'objet d'un avenant écrit et signé AVANT exécution — jamais de travaux supplémentaires sur accord verbal. Si la modification touche aux éléments du permis, permis modificatif d'abord (module 5.5).",
                    ],
                ],
                [
                    'title' => '9.6 Les contrôles administratifs pendant les travaux',
                    'key_points' => [
                        'CTC : visites et visas techniques aux phases clés (fondations, élévations, étanchéité) — conserver tous les PV, ils conditionnent les assurances et la conformité finale.',
                        "Services de l'urbanisme (commune/daïra) : les agents habilités peuvent visiter le chantier et vérifier la conformité au permis ; faire établir par eux le PV de conformité de l'implantation, indispensable ensuite pour les raccordements définitifs (décret 15-19, art. 61).",
                        "Protection civile : vérification du respect des prescriptions de sécurité prévues au dossier (accès engins, moyens de lutte incendie selon le projet). Astuce d'expert : ne pas attendre la fin — solliciter officiellement des visites intermédiaires de la protection civile pendant le chantier, et missionner un consultant sécurité incendie qui suit le projet en continu, pour éviter la mauvaise surprise au certificat de conformité.",
                        "Inspection du travail et CNAS : contrôle des déclarations des travailleurs et du respect des règles d'hygiène et de sécurité (module 6).",
                        "Conservation foncière : sollicitée en cours de projet uniquement si l'EDD doit être modifié (changement du nombre ou de la consistance des unités vendues).",
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'legal', 'body' => 'Ord. 95-07 (art. 175-184) ; décret 95-414 ; ord. 03-12 ; ord. 71-85 (CTC) ; code civil art. 554.'],
            ],
        ],

        // ------------------------------------------------------------------
        10 => [
            'title' => 'Raccordements aux réseaux et réception des VRD',
            'description' => "Règle d'or fixée par le décret 15-19 (art. 61) : les branchements définitifs des constructions aux différentes utilités ne sont raccordés que sur présentation du permis de construire ET d'un procès-verbal attestant la conformité de l'implantation de la construction, établi par les agents habilités des services de l'urbanisme de la commune. Ce PV se prépare donc PENDANT le chantier, pas à la fin.",
            'lessons' => [
                [
                    'title' => '10.1 Électricité et gaz — Sonelgaz',
                    'key_points' => [
                        'Déposer la demande de raccordement auprès de la direction de distribution Sonelgaz de la wilaya, avec dossier technique : permis, plans, puissance demandée, nombre de compteurs (un par logement et par local).',
                        "Sonelgaz réalise une étude technique et établit un devis de raccordement ; pour les programmes collectifs, l'étude peut mettre à la charge du promoteur des ouvrages (poste transformateur, extension de réseau, colonnes montantes aux normes) — à anticiper dans le budget et le planning dès le devis quantitatif.",
                        "Prévoir les locaux techniques (poste, gaines, niches compteurs) dès la conception, avec le bureau d'études.",
                    ],
                ],
                [
                    'title' => '10.2 Eau potable et assainissement — SEAAL / ADE / ONA',
                    'key_points' => [
                        'À Alger : SEAAL (eau et assainissement). Dans les autres wilayas : ADE (eau potable) et ONA (assainissement). Même logique : demande de branchement avec dossier technique, étude, devis, puis travaux de branchement.',
                        'Compteurs individuels par unité selon la configuration ; bâche à eau et surpression selon le projet.',
                    ],
                ],
                [
                    'title' => '10.3 La réception des VRD avant livraison',
                    'key_points' => [
                        "Voiries, trottoirs, éclairage extérieur, réseaux d'eaux usées et pluviales réalisés par l'entreprise de travaux publics : procéder à une réception formelle des VRD (PV avec l'entreprise et le bureau de suivi, essais des réseaux) AVANT la livraison des logements.",
                        "Les VRD conditionnent le fonctionnement du programme (accès, évacuation des eaux) et la satisfaction client ; un programme livré sans VRD réceptionnés, c'est un contentieux garanti.",
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'autre', 'body' => "Interlocuteurs concessionnaires par région : direction de distribution Sonelgaz, SEAAL / ADE / ONA — contacts à fournir dans l'annuaire de la formation."],
                ['type' => 'legal', 'body' => 'Décret 15-19 (art. 61).'],
            ],
        ],

        // ------------------------------------------------------------------
        11 => [
            'title' => 'Livrer : conformité, réceptions, actes définitifs',
            'description' => 'La dernière ligne droite : certificat de conformité, réception provisoire puis définitive avec le client, et actes définitifs chez le notaire.',
            'lessons' => [
                [
                    'title' => 'Conformité, réceptions et actes définitifs',
                    'key_points' => [
                        "Certificat de conformité auprès de l'APC (service d'urbanisme) : une commission (APC + protection civile + subdivision urbanisme de la daïra) vérifie que le construit est conforme au permis — ni plus ni moins : implantation, gabarit (étages), vocation, façades ; également distances entre bâtiments, sous-sols, dimensions des ouvertures. Délai indicatif cité : ~2 mois. Si un point ne passe pas, on corrige et on représente jusqu'à obtention — le certificat vaut permis d'habiter et peut être délivré par tranche.",
                        "Réception provisoire : elle se fait directement entre le client et le promoteur (correction d'expert — pas via le bureau d'études). Le client visite son logement, compare avec ce qui lui a été présenté (logement témoin, descriptif) et consigne toutes ses réserves sur le formulaire de réception provisoire. Le promoteur transmet les réserves aux entreprises concernées, qui corrigent — délai courant : une quinzaine de jours — puis le client est rappelé.",
                        "Réception définitive : une fois les réserves levées, le client signe le PV de réception définitive — preuve qu'il a réceptionné un bien conforme à son achat. C'est ce PV qui part chez le notaire pour l'acte définitif.",
                        "Chez le notaire, pour établir les actes définitifs des clients : EDD + certificat de conformité + attestation d'assurance décennale + liste des clients + certificats de solde + PV de réception définitive. Les clients viennent signer leur acte de propriété.",
                        "Rappel enseigné : la décennale court à partir de la réception définitive — et elle court lot par lot : chaque unité vendue (logement, local, parking) fait courir ses 10 ans à partir de sa propre réception définitive. Le promoteur reste donc engagé jusqu'à 10 ans après la réception du DERNIER lot vendu — à intégrer dans la durée de vie de la société et de ses assurances.",
                    ],
                ],
            ],
            'blocks' => [],
        ],

        // ------------------------------------------------------------------
        12 => [
            'title' => 'Piloter les chiffres',
            'description' => "Le tableau de bord du promoteur : coût de revient au m², trésorerie, point mort et fiscalité — la partie qui décide si le projet gagne de l'argent.",
            'lessons' => [
                [
                    'title' => 'Tableau de bord, trésorerie et fiscalité',
                    'key_points' => [
                        "Tableau de bord projet : coût de revient au m² décomposé poste par poste — terrain, études (géotechnique, bureau d'études, géomètre), construction, marketing et commercialisation, assurances, frais opérationnels et de gestion, frais financiers — face au prix de vente au m² relevé par QUARTIER et par wilaya (deux quartiers de la même ville peuvent varier de 40 %) ; échéancier de trésorerie (encaissements VSP vs décaissements chantier) ; point mort.",
                        'Structure de coûts de référence (intervenant) : terrain 25 % (+ ~10 % de marge de sécurité sur ce poste) / gestion et marketing 7 % / construction 68 % ; trésorerie initiale ~35 % du CA ou échéancier avec le vendeur du terrain.',
                        "Fiscalité de l'acquisition du terrain — avantage clé du statut de promoteur cité par l'expert : exonération des droits d'enregistrement sur l'acquisition du terrain destiné à la promotion (il cite 2,5 %) ; restent dus la taxe de publicité foncière de 1 % à la charge de l'acheteur (art. 353-2 du code de l'enregistrement) et les honoraires du notaire selon le barème réglementé (de l'ordre de 0,5 à 1 %, dégressif). ATTENTION : la loi de finances 2026 a remanié la fiscalité immobilière (promesses de vente désormais enregistrées à 2,5 % ; exonération des droits d'enregistrement pour les acquéreurs de logements en promotion, circulaire DGI n°42/2026) — module à verrouiller avec le notaire et l'expert-comptable partenaires avant tournage, et à mettre à jour à chaque loi de finances.",
                        "Fiscalité courante du promoteur : G50 mensuelle, IBS et acomptes, TVA sur les ventes, droits d'enregistrement et publicité foncière — leçon co-animée avec l'expert-comptable partenaire (les taux et régimes évoluent avec chaque loi de finances).",
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'expert_comptable', 'body' => 'Expert-comptable / commissaire aux comptes spécialisé promotion immobilière (co-animation du module + offre de service aux élèves).'],
            ],
        ],

        // ------------------------------------------------------------------
        13 => [
            'title' => 'Gestion des risques et des crises',
            'description' => "Le module qui sépare les promoteurs qui durent de ceux qui disparaissent : chaque famille de risques est enseignée avec ses signaux d'alerte, sa prévention contractuelle et sa procédure de réaction.",
            'lessons' => [
                [
                    'title' => '13.1 Les familles de risques du métier',
                    'key_points' => [
                        "Fonciers : héritiers ou indivisaires qui surgissent après l'achat (successions non réglées), acte contesté, servitudes non déclarées découvertes au terrassement, terrain grevé (d'où le certificat négatif du module 4), occupation illégale pendant la phase études.",
                        "Administratifs : refus ou retard du permis (avis défavorable d'un service), recours des tiers contre le permis, révision du POS en cours de projet, retard d'agrément ou d'attestation FGCMPI qui bloque légalement les ventes, refus de conformité en fin de projet.",
                        "Techniques : étude de sol démentie par le terrain (bon sol à 6 m d'un côté, 8 m de l'autre), venues d'eau, béton refusé par le CTC (démolition à la charge de l'entreprise, retard pour tous), fissures chez les mitoyens, effondrement de fouille.",
                        "Entreprises : défaillance ou abandon de chantier d'un lot, malfaçons découvertes tard, sous-traitance en cascade non déclarée, vol de matériaux (d'où le gardiennage), corps d'état mal coordonnés qui se détruisent mutuellement le travail.",
                        "Financiers et commerciaux : effet ciseaux de trésorerie (décaissements chantier plus rapides que les encaissements VSP — la cause n°1 de mortalité des promoteurs débutants), clients qui cessent de payer (procédure du module 8), mévente, flambée des matériaux (encadrée par l'art. 38), retard de livraison qui déclenche les pénalités plafonnées à 10 %.",
                        'Externes : météo, pénuries, changements de réglementation ou de loi de finances en cours de projet.',
                    ],
                ],
                [
                    'title' => '13.2 Fiche réflexe — accident grave ou mortel sur le chantier',
                    'content' => 'À imprimer et afficher dans la base de vie de chaque chantier. Séquence complète :',
                    'is_reflex_sheet' => true,
                    'key_points' => [
                        'Secours immédiats : premiers soins, appel protection civile (14) / SAMU, évacuation de la victime.',
                        "Accident grave ou mortel : ne pas modifier les lieux ; prévenir la police ou la gendarmerie — un décès sur chantier est systématiquement déclaré et déclenche une enquête judiciaire — et informer l'inspection du travail. Consigner l'accident au registre d'hygiène et de sécurité du chantier.",
                        "Déclaration CNAS sous 48 heures par l'employeur du travailleur (l'entreprise du lot concerné, pas nécessairement le promoteur) ; la victime ou ses proches disposent de 24 h pour informer l'employeur (loi 83-13 relative aux accidents du travail).",
                        "Travailleur déclaré : prise en charge CNAS (soins, indemnités journalières, rente d'incapacité ; en cas de décès, rentes aux ayants droit et frais funéraires).",
                        "Travailleur NON déclaré — le scénario catastrophe : la CNAS prend en charge la victime puis récupère l'intégralité des prestations auprès de l'employeur, avec pénalités et sanctions pour travail dissimulé ; une rente à vie répercutée sur une petite société peut la détruire.",
                        "Volet pénal : si l'enquête établit un manquement aux règles de sécurité (loi 88-07, décret 05-12), poursuites pour homicide involontaire (art. 288 du code pénal) ou blessures involontaires (art. 289) visant d'abord l'employeur direct et son encadrement ; le promoteur peut être recherché si l'organisation générale du chantier était défaillante (pas de PHS, pas de comité inter-entreprises, coordination inexistante).",
                        "Conséquences chantier : arrêt possible ordonné par l'inspection du travail, gel de la zone pendant l'enquête, retards en cascade sur les délais VSP.",
                    ],
                    'blocks' => [
                        ['type' => 'expert_tip', 'body' => 'Ce qui protège le promoteur — le mantra du module : exiger par écrit de chaque entreprise la déclaration CNAS de tous ses ouvriers (listes nominatives vérifiées), le PHS appliqué, les EPI portés, les engins vérifiés — et archiver ces exigences et vérifications. Le promoteur qui a exigé et vérifié par écrit est dans une position radicalement différente de celui qui a fermé les yeux.'],
                    ],
                ],
            ],
            'blocks' => [
                ['type' => 'partner_slot', 'partner_category' => 'autre', 'body' => "Huissier de justice (constats chantier, mises en demeure, retards de livraison) ; avocat d'affaires immobilier (contentieux clients et entreprises)."],
                ['type' => 'legal', 'body' => 'Loi 83-13 (accidents du travail et maladies professionnelles) ; loi 88-07 et décret 05-12 ; code pénal art. 288-289 ; décret 13-431 (pénalités) ; loi 11-04 art. 38.'],
            ],
        ],
    ],

    /** ANNEXE — références légales citées dans la formation. */
    'legal_annex' => [
        "Loi n° 11-04 du 17/02/2011 fixant les règles régissant l'activité de promotion immobilière (JO n°14 du 06/03/2011).",
        'Décret exécutif n° 12-84 du 20/02/2012 (agrément + TNPI), modifié par les décrets 13-96 (26/02/2013) et 19-243 (08/09/2019 : délivrance au niveau wilaya).',
        'Décret exécutif n° 12-85 du 20/02/2012 : cahier des charges type des engagements du promoteur.',
        'Arrêté interministériel du 06/12/2012 : justification des ressources financières.',
        'Décret exécutif n° 13-431 du 18/12/2013 : modèles types des contrats de réservation et de VSP, échéancier 20/15/35/25/5, pénalités (plafond 10 %), révision (plafond 20 %).',
        'Décrets exécutifs n° 14-180, 14-181, 14-182 du 05/06/2014 : FGCMPI (création modifiée, subrogation, cotisations) ; décret 97-406 du 03/11/1997 (création du FGCMPI).',
        'Décret exécutif n° 14-99 du 04/03/2014 : modèle de règlement de copropriété.',
        'Décret exécutif n° 15-281 du 26/10/2015 : concession convertible en cession (foncier public).',
        "Loi n° 90-29 du 01/12/1990 (aménagement et urbanisme) ; décret exécutif n° 15-19 du 25/01/2015 (actes d'urbanisme, dont art. 45, 60, 61, 64-66), modifié (réformes 2020 et 2024 : guichets uniques, plateforme numérique).",
        'Ordonnance n° 95-07 du 25/01/1995 relative aux assurances (art. 175-184) ; décret 95-414 ; code civil art. 554 (décennale) ; ordonnance 03-12 (CAT-NAT).',
        'Ordonnance n° 75-59 (code de commerce) modifiée par la loi 15-20 du 30/12/2015 (SARL/EURL : capital libre, max 50 associés).',
        "Ordonnance n° 71-85 bis du 29/12/1971 (CTC) ; arrêté interministériel du 15/05/1988 (maîtrise d'œuvre).",
        "Loi n° 88-07 du 26/01/1988 (hygiène, sécurité et médecine du travail) ; décrets exécutifs n° 05-09, 05-10, 05-11 et 05-12 du 08/01/2005 (dont prescriptions particulières BTPH) ; arrêtés du 26/07/2008 (plan d'hygiène et de sécurité ; déclaration d'ouverture de chantiers BTPH).",
        'Loi n° 08-15 du 20/07/2008 (règles de mise en conformité des constructions et leur achèvement) — sanctions art. 89 à 91 : clôture et panneau de chantier, délais de démarrage, dépôts de matériaux sur la voie publique.',
        'Loi n° 83-13 du 02/07/1983 (accidents du travail et maladies professionnelles) ; code pénal art. 288-289.',
        "Code de l'enregistrement, art. 353-2 (taxe de publicité foncière de 1 %) ; loi de finances 2026 et circulaire DGI n°42/2026 (fiscalité immobilière remaniée — à suivre à chaque loi de finances).",
        "Formulaire officiel d'inscription au TNPI et d'affiliation au FGCMPI (modèle du 11/07/2024, fgcmpi.org.dz) : pièces, droits d'affiliation (60 000 DA) et barème des cotisations annuelles.",
    ],

    'disclaimer' => "Ce document a été vérifié par recherche documentaire en juillet 2026. Avant tournage et avant toute diffusion commerciale, faire relire les modules 2, 3, 8 et 12 par le notaire et le juriste partenaires — les lois de finances et les réformes de l'urbanisme évoluent chaque année.",
];
