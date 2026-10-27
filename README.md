# Ebni Academy — LMS

Plateforme de formation en ligne (LMS) bilingue **français / arabe (RTL)** pour la vente et la diffusion de formations vidéo au marché algérien. Premier produit : **« La Promotion Immobilière en Algérie »** (10 modules, 41 leçons).

## Architecture

Monorepo :

| Dossier | Rôle | Stack |
|---|---|---|
| `api/` | API REST | Laravel 12 · PHP 8.3 · MySQL 8 · Sanctum (SPA cookies) · queues database · Pest |
| `web/` | SPA | React 18 · Vite · TypeScript · Tailwind · shadcn-style UI · TanStack Query · i18next (fr/ar + RTL) |
| `docker/` | Dev & prod images | php-fpm 8.3, nginx |

> **Note version framework** : le cahier des charges demandait Laravel 11, mais toutes les versions 11.x sont bloquées par des avis de sécurité Composer (fin du support sécurité en mars 2026). Le projet utilise **Laravel 12** (structure applicative identique).

### Paiements

| Méthode | Devise | Flux |
|---|---|---|
| **Chargily Pay v2** (CIB / Edahabia) | DZD | Checkout hébergé → webhook signé (HMAC-SHA256, en-tête `signature`) → job en file → `Order::markPaidAndEnroll()` |
| **Stripe Checkout** (diaspora) | EUR | Session hébergée → webhook `Stripe-Signature` vérifié via SDK → même chemin |
| **CCP / BaridiMob** (manuel) | DZD | L'élève téléverse un reçu (jpeg/png/pdf ≤ 5 Mo, stockage privé) → validation admin (approve/reject + e-mail) |
| **Cash** | DZD | Bouton « accorder l'accès » côté admin |

L'inscription (`enrollments`) n'est créée **que** par la transition d'une commande vers `paid` — transaction DB verrouillée et idempotente (les webhooks peuvent être livrés deux fois).

### Vidéo (Bunny Stream)

`GET /api/lessons/{id}/playback` (aperçu gratuit public, sinon inscrit/admin) renvoie une **URL d'embed signée à durée limitée** (`sha256(token_key + video_id + expires)`, TTL 4 h). Filigrane e-mail superposé au lecteur. Les URL brutes ne sont jamais exposées.

## Démarrage local

Prérequis : Docker Desktop + Node 20+.

Première fois, depuis un clone neuf : `make setup` (copie `api/.env`, construit et démarre les
conteneurs, `composer install`, clé d'application, base avec les données de démo, `npm install`).
Les conteneurs PHP tournent avec votre utilisateur : sous Linux, si `id -u` ne vaut pas 1000,
écrivez d'abord `UID=…` et `GID=…` dans un fichier `.env` à la racine (`make setup` le fait).
Sans `make` (Windows) :

```bash
cp api/.env.example api/.env
docker compose up -d
docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
docker compose exec php php artisan db:seed --class=DemoShowcaseSeeder
npm --prefix web install
```

Ces étapes tournent telles quelles sur GitHub, depuis un checkout neuf, chaque semaine
(`.github/workflows/setup.yml`). Ensuite :

```bash
make up          # php-fpm, nginx (http://localhost:8000), mysql, mysql-test, redis, queue worker
make fresh       # migrate:fresh --seed (programme réel + comptes démo)
make web         # Vite dev server → http://localhost:5174
make test        # suite Pest complète (base mysql-test isolée)
```

### Aperçu privé en ligne (GitHub Codespaces)

Le site complet dans le navigateur, sans rien installer, visible de votre seul compte GitHub :

1. Sur la page du dépôt : bouton vert **Code** → onglet **Codespaces** → **Create codespace on main**.
2. La première fois, 5 à 8 minutes de construction ; le site s'ouvre ensuite dans un nouvel onglet.
   Sinon : onglet **Ports** en bas de l'éditeur → ligne « Ebni Academy » (8080) → icône du globe.
3. Le codespace s'arrête après 30 minutes d'inactivité ; il se relance depuis
   [github.com/codespaces](https://github.com/codespaces), données de démo comprises.

Ailleurs, `make preview` fait la même chose sur http://localhost:8080 : l'application construite et
Laravel sur une seule adresse, routés comme en production (`docker/nginx/preview.conf`).

Comptes seedés (développement uniquement) : admin `admin@example.dz` / `password` · étudiants `karim@example.dz` (inscrit), `amel@example.dz` / `password`.

## Variables d'environnement (`api/.env`)

| Variable | Rôle |
|---|---|
| `FRONTEND_URL` | Origine SPA (CORS/Sanctum + URLs de retour checkout) |
| `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` | Auth cookie SPA |
| `CHARGILY_API_KEY` / `CHARGILY_SECRET` | Clés Chargily Pay v2 (la `SECRET` signe les webhooks) |
| `CHARGILY_BASE_URL` | `https://pay.chargily.net/test/api/v2` (test) → retirer `/test` en prod |
| `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | Stripe (EUR diaspora) |
| `BUNNY_STREAM_LIBRARY_ID` / `BUNNY_TOKEN_KEY` / `BUNNY_EMBED_TTL` | Bunny Stream (Embed View Token Authentication) |
| `PAYMENT_CCP_NUMBER` / `PAYMENT_CCP_NAME` / `PAYMENT_BARIDIMOB_RIP` | Coordonnées affichées au checkout manuel |
| `MAIL_*` | Mailtrap en dev ; e-mails en file d'attente |

### Configuration des webhooks

- **Chargily** : dashboard → URL `https://votre-domaine/api/webhooks/chargily`. La signature HMAC est vérifiée sur le corps brut avant tout traitement ; chaque payload est journalisé.
- **Stripe** : `stripe listen --forward-to localhost:8000/api/webhooks/stripe` en dev ; en prod, endpoint + `whsec_…` dans `STRIPE_WEBHOOK_SECRET`.

## Déploiement

Voir **[`deploy/README.md`](deploy/README.md)** : installation du serveur, `deploy/deploy.sh` (qui
s'arrête avant de toucher au site si un secret manque ou si un réglage n'est pas celui de la
production), configuration Nginx et Supervisor, sauvegardes et exercice de restauration.

En production, le contenu et le compte admin s'installent avec `php artisan app:install` — jamais
avec `db:seed`, qui crée des comptes et des commandes de démonstration et refuse désormais de
s'exécuter en production.

## E-mails (file d'attente)

Trois Mailables bilingues (FR/AR selon la locale de l'utilisateur), tous `ShouldQueue` : bienvenue à l'inscription, confirmation de paiement (webhook ou validation admin), reçu rejeté (avec motif). Traductions dans `api/lang/fr.json` / `ar.json`. En dev, configurer Mailtrap dans `MAIL_*` ou laisser `MAIL_MAILER=log`.

## Administration

`/admin` (rôle `admin`) : tableau de bord (revenu du mois par devise, commandes par méthode, inscriptions, taux de complétion), gestion des formations/modules/leçons (drag-and-drop), commandes (filtres, aperçu du reçu, approuver/rejeter/accorder, **export CSV** `GET /api/admin/orders/export`), étudiants (recherche, progression par formation), coupons. Toutes les actions sur les commandes sont journalisées dans `audit_logs`.

## Tests

```bash
make test   # Pest, base MySQL isolée (mysql-test), HTTP fakés pour Chargily, signatures réelles pour les webhooks
```

La suite couvre : auth Sanctum, catalogue localisé, CRUD admin (403 non-admin sur toutes les routes), checkout (Chargily/Stripe/manuel, coupons), webhooks signés + idempotence (double livraison = une seule inscription), reçus (taille/type/propriétaire), lecture vidéo (aperçus gratuits invités, 403 non-inscrits, token Bunny recalculé), progression/reprise, stats admin, export CSV.

## Sécurité — points clés

- Webhooks : signature vérifiée sur le corps brut **avant** tout traitement ; payloads journalisés (prévoir une rédaction/rotation des logs en production).
- Inscriptions : uniquement via `Order::markPaidAndEnroll()` (transaction + verrou de ligne, idempotent).
- Reçus : disque privé, servis via une route admin authentifiée en streaming.
- Vidéos : URLs d'embed signées à TTL court, jamais d'URL brute ; filigrane e-mail sur le lecteur.
- Rate limiting : `throttle:auth` (10/min/IP) et `throttle:checkout` (10/min/utilisateur).
