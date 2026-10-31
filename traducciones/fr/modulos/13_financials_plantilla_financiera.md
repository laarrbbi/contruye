# Financials — le modèle financier de la promotion

> **Objectif :** savoir remplir, lire et défendre le modèle financier professionnel de la formation : coût par chapitres, prêt, gestion, échéancier de paiement, cash-flow et synthèse.
> **Durée :** 120 min · **Outil :** `PLANTILLA_LIBRE.xls` et sa version simplifiée, les mêmes que celles calculées sur la plateforme · **Utilisé dans :** modules 2, 5, 7 et 12.

## D'où vient le modèle

- C'est le **modèle d'un professionnel du secteur**, utilisé dans le cours et sur la plateforme **tel quel**, avec ses formules.
- Il est conçu pour une promotion de **logement libre en régime de coopérative** (*vivienda libre en régimen de cooperativa*) : les associés achètent au coût, la société de gestion (*gestora*) perçoit un pourcentage de gestion et le financement est un prêt hypothécaire auquel les associés sont subrogés (*subrogación*).
- **Deux fichiers :**
  - **PLANTILLA_LIBRE.xls** : le modèle complet. 17 feuilles et environ 31.000 formules liées.
  - **Modèle simplifié d'analyse de faisabilité** (*Plantilla simplificada de Análisis de Viabilidad*) : la feuille de synthèse du modèle complet (« 3ª Coste Total Promoción ») avec un exemple réel : 1 logement de 200 m².
- **Avant de l'ouvrir dans Excel :** activez le calcul itératif (*Fichier → Options → Formules → Activer le calcul itératif*, 100 itérations, écart maximal 0,001). Le modèle contient des références circulaires voulues (leçon F.7) et, sans itération, Excel ne calcule pas. Sur la plateforme, c'est déjà réglé.

## Plan du modèle

| Ordre | Feuille | Ce qu'elle fait |
|---|---|---|
| 1 | **Datos de Partida** (données de départ) | Tout ce que vous saisissez : surfaces, types de logement, terrain, construction, viabilisation, licences, prêt, gestion, associés |
| 2 | 2º Auxiliar | Calculs intermédiaires : surfaces et coûts par élément et par type de logement |
| 3 | **3ª Terrenos** (terrain) | Coût du terrain : prix, impôts, notaire, registre, cautions, droits de timbre, API (agent immobilier) |
| 3 | **3ª Construcción** (construction) | Situations de travaux (*certificaciones*) mois par mois selon la courbe de chantier, retenue, OCT et assurance décennale |
| 3 | **3ª Urbanización** (viabilisation) | Comme la construction, pour la viabilisation |
| 3 | **3ª Proyectos** (études et projets) | Honoraires : études, projets de base et d'exécution, installations, sécurité, directions, contrôle qualité |
| 3 | Direcciones | Répartition dans le temps des directions de chantier (*direcciones facultativas*) et de la sécurité |
| 3 | **3ª Licencias y Varios** (licences et divers) | Taxes, ICIO, première occupation, frais juridiques, raccordements, assurances, audits, garanties des sommes versées d'avance (*cantidades a cuenta*) |
| 3 | **3ª Préstamo hipotecario** (prêt hypothécaire) | Tirages, intérêts, retenue et frais de constitution |
| 3 | **3ª Gestión** (gestion) | Honoraires de gestion et recrutement des associés |
| 3 | 3ª Pólizas de Crédito | Jusqu'à trois lignes de crédit de financement externe |
| 4 | **3ª Coste Total Promoción** (coût total de la promotion) | Synthèse du coût : HT, TTC, IVA, %, coûts unitaires, coefficient de passage. **C'est le modèle simplifié** |
| 5 | Estudio Económico | L'étude économique complète |
| 5 | 4º Auxiliar1 · **5º Forma de Pago** (échéancier de paiement) | Apports, mensualités et prêt de chaque associé |
| 6 | **6ª Cash-flow** | Encaissements et décaissements sur 48 mois, par poste et par année |
| 7 | **8º Resumen** (synthèse) | Coût, surfaces, apport, mensualité et hypothèque par type de logement |

- **Règle d'usage :** on ne remplit que les données de départ (et, si besoin, les montants manuels des projets et les lignes de crédit). Tout le reste est calculé.

## Leçon F.1 — Remplir les données de départ

| Bloc | Cellules | Ce que vous saisissez | Attention |
|---|---|---|---|
| Surfaces par élément | C9:E16 | Unités, m² construits moyens et **coût de construction au m²** des logements, garages, débarras, terrasses, locaux, autres | La colonne s'appelle « PVP CONSTRUCCIÓN » mais c'est un **coût**, pas un prix de vente |
| Types de logement | B22:C45 | Jusqu'à 24 types : nombre de logements et m² utiles | La somme des logements doit être égale à C9. Le modèle calcule lui-même les m² construits |
| Éléments liés | C51:E74 | m² de garage, de débarras et de terrasse de chaque type | Ils comptent pour la **tranche d'honoraires** du projet |
| Terrain | D79:D83 | Mois zéro, soumis à l'IVA ? (SI/NO), intérêt du paiement différé, prix, API et divers | **API et divers est un % du prix**, pas un montant |
| Échéancier du terrain | C86:E92 | Dates, montants et s'ils sont garantis par une caution | Écrivez SI / NO sans accent |
| Construction | D96:D101 | Mois de début, délai de paiement en jours, intérêt du paiement différé, retenue, caution, mois de chantier (1-24) | Les mois de chantier choisissent la courbe des situations de travaux |
| Viabilisation | D108:D116 | Montant, début, paiement, retenue, caution, mois, contrôle qualité et sécurité | |
| Typologie | D121 | `bloque` ou `chalets` (immeuble collectif ou maisons) | Change les tarifs de l'OCT et de l'assurance décennale |
| Licences | D126:D131 | Taxe de *cédula*, sondages/palissades, taxe de licence, ICIO, première occupation, IAE | Tous sont un **% du montant de construction** |
| Frais juridiques | D133 | AJD de l'acte de construction neuve et de division horizontale | |
| Raccordements et divers | D135:D142 | Eau, électricité, gaz, téléphone, ordures, conseil d'administration de la coopérative (*consejo rector*) | Une formule est proposée par défaut ; vous pouvez la remplacer |
| Prêt hypothécaire | D146:D154 | % du prêt, taux, retenue, premier tirage, dates, commission, AJD | Le prêt est un **% du coût total** |
| Gestion | D157:D159 | % de gestion, mois, début | |
| Associés | C163:F180 | Associés recrutés chaque mois (36 mois) | |
| Apports et mensualités | C184:D186 | Apport en % **ou** en montant fixe, nombre de mensualités | Remplissez l'un des deux apports, pas les deux |
| Répartition du coût | C207:C214 | % du coût affecté au logement, au garage, au débarras… | Par défaut dans le modèle : logement 90 %, garage 7 %, débarras 3 % |

- **Pourcentages en valeur décimale :** 0,05 = 5 %.

## Leçon F.2 — Les chapitres de coût

- **3ª Terrenos :** prix + notaire et registre (barèmes par tranches) + impôt sur l'achat (AJD s'il y a IVA, ITP sinon) + coût des cautions et des droits de timbre des paiements différés + API et divers.
- **3ª Construcción :** montant de construction (surfaces × coût au m²) facturé en situations de travaux mois par mois selon la **courbe de chantier**.
  - La courbe est un tableau de % par mois pour des chantiers de 1 à 24 mois ; chaque colonne totalise 100. Par exemple, sur 18 mois, on facture 0,5 % le mois 1 et 9,5 % les mois 10 à 13.
  - S'y ajoutent la retenue, l'**OCT** (tarif par missions et tranches de budget) et l'**assurance décennale** (prime par tranches, majorations pour toitures et façades, CLEA et IPS).
- **3ª Urbanización :** comme la construction, avec sa propre courbe et ses situations de contrôle qualité et de sécurité.
- **3ª Proyectos :** honoraires par **tranches de surface**.
  - Le pourcentage (11,5 % jusqu'à 200 m², 9,5 % jusqu'à 800 m²…) s'applique au montant de construction.
  - Il se répartit ainsi : projet de base 40 %, exécution 30 %, direction de l'architecte 30 % et direction de l'architecte technique (*aparejador*) 30 %.
  - S'y ajoutent les études (topographique, géotechnique, implantation — *tira de cuerdas*), les projets d'installations, la sécurité, le manuel d'entretien, les rapports techniques et le contrôle qualité (1,5 % de la construction).
  - Les projets de lotissement (*parcelación*), de compensation (*compensación*) et de viabilisation se saisissent à la main s'il y en a.
- **3ª Licencias y Varios :**
  - Taxes, ICIO et première occupation (% de la construction).
  - Frais juridiques (notaire, registre et AJD de l'acte de construction neuve et de division horizontale).
  - Raccordements, fournitures, loyers, audits et assurances.
  - L'**assurance des sommes versées d'avance** (*cantidades a cuenta*) par les associés.
- **3ª Gestión :** % de gestion sur le coût, plus le recrutement des associés.

## Leçon F.3 — La synthèse : le modèle simplifié

La feuille « 3ª Coste Total Promoción » (le modèle simplifié) résume tout. Voici l'**exemple réel du professionnel** : 1 logement de 200 m², terrain de 50.000 €.

| Chapitre | HT | TTC | % du total |
|---|---|---|---|
| Terrain (*Terreno*) | 53.100,00 € | 61.116,00 € | 16,5 % |
| Construction (*Construcción*) | 180.935,33 € | 193.552,31 € | 56,1 % |
| Viabilisation (*Urbanización*) | 0,00 € | 0,00 € | 0,0 % |
| Études et projets (*Proyectos*) | 38.754,09 € | 44.954,75 € | 12,0 % |
| Licences (*Licencias*) | 7.740,00 € | 7.740,00 € | 2,4 % |
| Divers (*Varios*) | 18.484,05 € | 20.250,24 € | 5,7 % |
| Gestion (*Gestión*) | 15.358,89 € | 17.816,31 € | 4,8 % |
| Frais du prêt hypothécaire | 8.164,29 € | 9.073,02 € | 2,5 % |
| **Total** | **322.536,66 €** | **354.502,63 €** | **100 %** |

- **Comment lire chaque chapitre** (colonnes de détail de la feuille) :
  - **Terrain 53.100 €** = 50.000 € de terrain + 50 € de notaire + 50 € de registre + 500 € d'AJD (1 %) + 2.500 € d'API et divers (5 %).
  - **Construction 180.935,33 €** = 163.400 € de montant + 8.600 € de retenue + 3.606,07 € d'OCT + 5.329,26 € d'assurance décennale.
  - **Études et projets 38.754,09 €** = 9.015,18 € d'études topographique et géotechnique + 6.536 € de projet de base + 4.902 € d'exécution + 4.902 € + 4.902 € de directions + 4.597,74 € de rapports techniques + 2.580 € de contrôle qualité + 193,60 € de projets d'installations + 193,60 € de direction de l'ingénieur des installations + 865,85 € de sécurité et hygiène + 66,11 € de manuel d'entretien.
  - **Divers 18.484,05 €** = 6.696,75 € de frais juridiques + 240,40 € de raccordements + 48,08 € de fournitures + 2.524,25 € de salle d'assemblées + 7.212,15 € d'audits + 1.762,42 € d'assurances.
  - **Gestion 15.358,89 €** = 13.853,89 € de gestion de la promotion + 1.505 € de recrutement des associés.
  - **Frais du prêt 8.164,29 €** = 6.969,71 € de constitution + 1.194,58 € d'intérêts de différé.
- **Indicateurs :**
  - **Coût unitaire** par logement : 322.536,66 €. Par m² construit : 1.612,68 €/m².
  - **Coefficient de passage** = coût total ÷ (terrain + construction + viabilisation, hors frais) = 322.536,66 ÷ 222.000 = **1,4529**. Pour chaque euro de terrain et de travaux, il y a 0,45 € de coûts indirects, de gestion et financiers.
  - **Prêt hypothécaire** = 80 % du coût total = **258.029,33 €**.
- **Pour vérifier vos chiffres :** HT + IVA doit donner le TTC dans chaque chapitre. Dans la version originale de cette feuille, le TTC de « Divers » n'additionne pas les raccordements (voir leçon F.8).

## Leçon F.4 — Prêt hypothécaire et lignes de crédit

- **Montant :** % du coût total (D146). Dans l'exemple, 80 %.
- **Premier tirage :** par défaut, 80 % du prix du terrain (D149). Le reste se tire au rythme des situations de travaux.
- **Intérêts :** calculés au jour sur les sommes tirées et payés chaque trimestre. Les **intérêts de différé** (*intereses de carencia*) sont un coût de la promotion.
- **Retenue (D148) :** part du prêt que la banque retient jusqu'à la fin du chantier.
- **Constitution :** notaire et registre (selon barèmes), commission d'ouverture, AJD de la garantie et expertises.
- **Lignes de crédit** (*pólizas de crédito*) : jusqu'à trois, avec leur plafond, leur taux, leurs dates et leurs frais. Elles couvrent les creux de trésorerie.

## Leçon F.5 — Gestion, associés et échéancier de paiement

- **Gestion :** le % de gestion (D157) s'applique au coût et se répartit sur les mois de gestion. Le recrutement des associés s'ajoute à part.
- **Rythme de recrutement :** combien d'associés entrent chaque mois (C163:F180). Il détermine quand l'argent rentre.
- **Échéancier de l'associé** (feuille 5º) :
  - **apport** (en % ou en montant fixe) ;
  - **mensualités** jusqu'à la livraison ;
  - **hypothèque** à laquelle il est subrogé dans l'acte.
  - Les sommes versées d'avance sont assurées : le coût de l'assurance va dans « Divers » (*Varios*).
- **Synthèse par type** (feuille 8º) :
  - coût de chaque type de logement avec ses éléments liés, selon la répartition de C207:C214 ;
  - avec l'apport, la mensualité et l'hypothèque de chaque associé.

## Leçon F.6 — Cash-flow et étude économique

- **6ª Cash-flow :** 48 mois d'encaissements (apports, mensualités, prêt, IVA) et de décaissements (terrain, viabilisation, raccordements, projets, construction, licences, divers, gestion, frais financiers).
  - Totaux par année et cumulés. Chaque ligne porte son compte du plan comptable (*plan contable*).
- **Comment le lire :**
  - Le **cumul** ne doit pas être négatif. S'il l'est, il faut une ligne de crédit ou un apport plus élevé des associés.
  - Regardez le mois de l'achat du terrain et les mois de pointe des situations de travaux.
  - Vérifiez que le prêt est tiré quand il y a des travaux.
- **Estudio Económico :** réunit tous les chapitres, avec leurs montants, leurs frais financiers et l'IVA, dans une seule vue.

## Leçon F.7 — Pourquoi le modèle a besoin du calcul itératif

- **Dans une promotion, certains coûts dépendent du coût total :**
  - le **prêt** est un % du coût total, et le coût inclut les frais du prêt ;
  - la **gestion** est un % du coût, et fait partie du coût ;
  - le **coût de chaque logement** fixe les paiements des associés, qui fixent le coût de leur assurance, qui fait partie du coût.
- **Ce sont des références circulaires.** Excel les résout par itération : il calcule, recalcule avec le résultat et recommence jusqu'à ce que l'écart soit inférieur à 0,001 €.
- Dans ce modèle, le résultat se stabilise en 20 itérations environ.
- **Si Excel signale une « référence circulaire », ce n'est pas une erreur : activez le calcul itératif.**

## Leçon F.8 — Ce qu'il faut revoir avant de l'utiliser en 2026

Le modèle calcule avec les paramètres de sa construction, vers 2002. Pour étudier une promotion actuelle, vérifiez avec votre conseiller :

| Paramètre du modèle | Valeur dans le modèle | Référence actuelle (modules 2 et 11, annexe B) |
|---|---|---|
| IVA des travaux de logement | 7 % | 10 % |
| IVA général (honoraires, notaire, OCT…) | 16 % | 21 % |
| Impôt sur l'achat du terrain | AJD 1 % ou ITP 8 % | Selon votre communauté autonome (annexe B) |
| Barèmes de notaire et de registre, droits de timbre, OCT, décennale, honoraires | Tableaux de l'époque, en pesetas | Demandez un devis actuel |
| Postes propres à la coopérative | Recrutement des associés, conseil d'administration, salle d'assemblées | Ne s'appliquent pas à un promoteur |

**Deux détails de l'original à connaître :**
- **Le TTC de « Divers » n'additionne pas les raccordements** (dans l'exemple, 278,87 €). Le total TTC correct de l'exemple serait de 354.781,50 €. Le HT est correct.
- **Le cinquième paiement du terrain** ne calcule pas le coût de sa caution s'il est garanti. Les paiements 1 à 4, si.

## Leçon F.9 — Du modèle à la décision d'un promoteur

- Le modèle donne le **coût total** et le **calendrier de trésorerie**. Dans une coopérative, c'est le prix de l'associé.
- **Si vous êtes promoteur**, reportez le coût total dans le **compte de résultat du promoteur** (CRP) du module 2 :
  - ventes hors IVA − coût total − commercialisation − impôt sur les sociétés (*Impuesto sobre Sociedades*) = bénéfice ;
  - marge minimale de 15 % des ventes ;
  - méthode résiduelle pour le terrain.
- **Utilisez le modèle à chaque phase :**
  - faisabilité avant l'achat (module 2) ;
  - présentation à la banque (module 5) ;
  - suivi mensuel avec les données réelles (module 7) ;
  - clôture et rentabilité réelle (module 12).

## Checklist de Financials

- [ ] Calcul itératif activé dans Excel (déjà actif sur la plateforme)
- [ ] Surfaces, modèles et éléments rattachés remplis et cohérents avec la fiche produit
- [ ] Coût de construction par m² issu de devis réels
- [ ] Terrain, permis et prêt avec les bonnes unités (montants et pourcentages)
- [ ] Calendrier : dates d'achat, de chantier, de viabilisation, de tirages et de gestion
- [ ] Synthèse vérifiée : chapitres, incidences, coefficient de passage et prêt
- [ ] Cash-flow sans cumul négatif (ou ligne de crédit prévue)
- [ ] Paramètres du modèle revus selon la fiscalité actuelle (Leçon F.8)
