# Couverture des partenaires par wilaya

## Le problème

Avant ce changement, un partenaire avait **un seul champ texte libre** `wilaya`.
Quatre conséquences, toutes bloquantes pour un réseau de partenaires réel :

1. **Un partenaire n'opère pas dans une seule wilaya.** Un notaire d'Alger
   travaille couramment sur Blida et Tipaza. Le champ unique obligeait à choisir,
   ou à écrire « Alger, Blida » — une chaîne qu'aucun filtre ne sait exploiter.
2. **Le texte libre dérive.** « Alger », « alger », « ALGER », « 16 », « Algiers »
   désignent la même wilaya sans qu'aucun regroupement soit possible.
3. **Aucun filtre côté apprenant.** Un promoteur à Oran voyait la liste complète
   sans moyen de savoir qui intervient chez lui.
4. **Pas de notion de couverture nationale.** Une banque ou un assureur couvre les
   58 wilayas ; l'exprimer demandait 58 saisies ou un champ vide trompeur.

## Le modèle retenu

| Objet | Rôle |
| --- | --- |
| `wilayas` | Table de référence : les 58 wilayas officielles (code 1-58, nom FR + AR), issues du découpage de 2019. Jamais modifiable depuis l'admin. |
| `partner_wilaya` | Les wilayas où le partenaire intervient réellement. |
| `partners.is_national` | Couverture nationale. Le partenaire répond à **tous** les filtres, et n'a alors **aucune** ligne dans le pivot — les deux ne peuvent pas se contredire. |
| `users.wilaya_id` | La wilaya de l'apprenant. Choisie une fois, elle pré-filtre toutes les listes de partenaires, sur tous ses appareils. |

La règle de couverture est centralisée dans `Partner::scopeInWilaya()` et
`Partner::coversWilaya()` : *les partenaires qui couvrent explicitement cette
wilaya, **plus** tous les nationaux*. Aucun appelant ne réimplémente cette règle.

### Migration des données existantes

Les anciennes valeurs texte sont reprises automatiquement : comparaison sans
accents ni casse contre les noms officiels, découpage sur `,` `;` `/` `|` pour
les saisies multiples. Une valeur qui ne correspond à rien **n'est pas devinée** :
le partenaire se retrouve sans couverture, ce que l'admin voit et corrige.

## Côté admin

- **Éditeur de couverture** dans la fiche partenaire : interrupteur « Couverture
  nationale », sinon liste des 58 wilayas à cocher, cherchable **par nom ou par
  matricule** (« bejaia » trouve Béjaïa, « 19 » trouve Sétif) — c'est ainsi qu'on
  lit une carte de visite ou un en-tête administratif.
- **Colonne « Wilayas d'intervention »** dans la liste, en badges (3 puis « +N »).
- **Filtre par wilaya** dans la barre d'outils.
- **Analyse des manques** : quand un filtre wilaya est actif, un encart nomme les
  métiers **sans aucun partenaire actif** dans cette wilaya. C'est la question que
  se pose vraiment l'équipe qui construit le réseau : « qui me manque à Sétif ? »

## Côté apprenant

- Sélecteur « Ma wilaya » sur le **tableau de bord** et au-dessus de chaque liste
  de partenaires, **enregistré sur le profil** (`PATCH /me`) : réglé une fois, il
  s'applique partout et sur tous les appareils.
- Un partenaire national est affiché comme tel, pas comme « sans wilaya ».
- **Jamais de vide silencieux** : si le filtre masque tout, le message le dit et
  propose « Voir les N partenaires de toutes les wilayas ». Le sélecteur reste
  visible dès qu'il masque quelque chose, même pour un slot à un seul partenaire.

## Ce qui n'a pas changé

Les coordonnées des partenaires restent un **avantage payant** : elles ne sont
sérialisées que pour les modules possédés. Le filtre wilaya s'applique **après**
ce verrouillage, jamais à sa place.

Au passage, une faille de ce verrouillage a été corrigée : un bloc `partner_slot`
placé sur une leçon en **aperçu gratuit** d'un module **non acheté** livrait les
téléphones et e-mails. Les contacts suivent désormais la propriété du module, et
le slot annonce seulement **combien** de contacts il débloque — sans jamais les
confondre avec « aucun partenaire pour l'instant », qui reste réservé aux slots
réellement vides.

## Reste à faire

- Les noms de wilayas sont fournis en FR et AR ; le reste de l'interface arabe
  attend toujours la traduction humaine.
- Aucun contact réel n'est saisi : les partenaires actuels sont des `[DÉMO]`.
