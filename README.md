# Garage Kagnan

Application web de **gestion de garage** : réception des véhicules, suivi des interventions, devis, facturation, stock, relation client et pilotage administratif, avec prise en charge de **plusieurs sièges**.

[![Architecture diagram of soibiyi/garage-kagnan](https://gitdiagram.com/soibiyi/garage-kagnan/diagram.png)](https://gitdiagram.com/soibiyi/garage-kagnan?utm_source=readme&utm_medium=picture)

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Stack technique](#stack-technique)
- [Architecture](#architecture)
- [Modèle de données](#modèle-de-données)
- [Installation](#installation)
- [Lancement avec Docker](#lancement-avec-docker)
- [Tests](#tests)
- [Workflow Git](#workflow-git)
- [Structure du projet](#structure-du-projet)

## Fonctionnalités

| Module | Description |
|---|---|
| **Authentification** | Connexion, inscription, réinitialisation du mot de passe, vérification d'e-mail, profil (Laravel Breeze). |
| **Réception** | Enregistrement du client et du véhicule, création de l'intervention, photos (y compris photos supplémentaires), gestion du parc. |
| **Mécanicien** | Vue dédiée aux interventions en cours. |
| **Chargé client** | Fiches clients et véhicules, historique des interventions, relances, suivi des interactions. |
| **Devis** | Devis directs, modification, acceptation (ligne par ligne), historique, petites fournitures et remises. |
| **Facturation** | Factures, paiements, export natif des factures. |
| **Stock** | Articles, familles et sous-familles, contrôle du stock. |
| **Administration** | Gestion des utilisateurs, tableau de bord, chiffre d'affaires par zone. |
| **Multi-sièges** | Filtre et sélecteur de siège, données rattachées à un siège (`config/sieges.php`). |

## Stack technique

- **Backend** : Laravel (PHP), Eloquent, migrations, seeders
- **Frontend** : Vue.js 3 avec Inertia.js, Tailwind CSS, Vite
- **Authentification** : Laravel Breeze
- **Base de données** : MySQL
- **Routes côté JS** : Ziggy
- **Déploiement** : Docker (PHP + Nginx), configuration de production incluse
- **Qualité** : PHPUnit, StyleCI, GitHub Actions (tests, changelog)

## Architecture

Chaque contrôleur renvoie une page Inertia : Laravel gère la logique et les données, Vue gère l'affichage, sans API séparée.

```mermaid
flowchart TD

subgraph group_access["Access and interface"]
  node_routes["Web routes<br/>[web.php]"]
  node_auth["Authentication"]
  node_registration["Registration"]
  node_profile["User profile"]
  node_inertia["Inertia bridge"]
  node_vue["Vue pages"]
end

subgraph group_workshop["Workshop operations"]
  node_reception["Vehicle reception"]
  node_fleet["Vehicle fleet"]
  node_mechanic["Mechanic workflow"]
  node_dossiers["Intervention dossiers"]
end

subgraph group_customer["Customer relations"]
  node_clientwork["Client relations"]
  node_interactions["Client interactions"]
end

subgraph group_admin["Administration"]
  node_quotes["Quotes and lines"]
  node_smallparts["Small-supply pricing"]
  node_billing["Invoicing and payments"]
  node_stock["Stock management"]
  node_users["User administration<br/>[UserController.php]"]
end

subgraph group_domain["Domain data"]
  node_clients[("Clients<br/>[Client.php]")]
  node_vehicles[("Vehicles<br/>[Vehicule.php]")]
  node_interventions[("Interventions<br/>[Intervention.php]")]
  node_devis[("Quotes<br/>[Devis.php]")]
  node_quote_lines[("Quote lines<br/>[LigneDevis.php]")]
  node_invoices[("Invoices<br/>[Facture.php]")]
  node_payments[("Payments<br/>[Paiement.php]")]
  node_stockdata[("Stock records<br/>[Stock.php]")]
  node_usersdata[("User accounts<br/>[User.php]")]
end

node_browser(("Staff browser"))

node_browser -->|"requests"| node_routes
node_routes -->|"routes to"| node_auth
node_routes -->|"routes to"| node_registration
node_routes -.->|"routes to"| node_profile
node_routes -->|"routes to"| node_reception
node_routes -->|"routes to"| node_fleet
node_routes -->|"routes to"| node_mechanic
node_routes -->|"routes to"| node_clientwork
node_routes -->|"routes to"| node_dossiers
node_routes -->|"routes to"| node_users
node_routes -->|"routes to"| node_quotes
node_routes -->|"routes to"| node_billing
node_routes -->|"routes to"| node_stock
node_routes -->|"uses middleware"| node_inertia
node_auth -->|"renders through"| node_inertia
node_registration -->|"creates account"| node_usersdata
node_reception -->|"creates and reads"| node_clients
node_reception -->|"creates and reads"| node_vehicles
node_reception -->|"creates dossier"| node_interventions
node_mechanic -->|"reads assignments"| node_interventions
node_fleet -->|"reads dossiers"| node_interventions
node_dossiers -->|"updates dossiers"| node_interventions
node_dossiers -->|"creates and updates"| node_devis
node_dossiers -->|"creates lines"| node_quote_lines
node_dossiers -->|"calculates charges"| node_smallparts
node_dossiers -->|"checks invoices"| node_invoices
node_dossiers -->|"checks payments"| node_payments
node_quotes -->|"manages quotes"| node_devis
node_quotes -->|"manages lines"| node_quote_lines
node_quotes -->|"applies pricing"| node_smallparts
node_clientwork -->|"reads client records"| node_clients
node_clientwork -->|"reads vehicle records"| node_vehicles
node_clientwork -->|"reads and updates"| node_interventions
node_clientwork -->|"records interactions"| node_interactions
node_users -->|"manages accounts"| node_usersdata
node_users -->|"queries revenue"| node_invoices
node_users -->|"aggregates revenue"| node_payments
node_billing -->|"manages invoices"| node_invoices
node_billing -->|"records payments"| node_payments
node_stock -.->|"manages stock"| node_stockdata
node_inertia -->|"renders pages"| node_vue
node_vehicles -->|"belongs to"| node_clients
node_vehicles -->|"has many"| node_interventions
node_interventions -->|"has quote"| node_devis
node_devis -->|"contains"| node_quote_lines
node_interventions -->|"has invoice"| node_invoices
node_invoices -->|"has payments"| node_payments

classDef toneBlue fill:#dbeafe,stroke:#2563eb,stroke-width:1.5px,color:#172554
classDef toneAmber fill:#fef3c7,stroke:#d97706,stroke-width:1.5px,color:#78350f
classDef toneMint fill:#dcfce7,stroke:#16a34a,stroke-width:1.5px,color:#14532d
classDef toneRose fill:#ffe4e6,stroke:#e11d48,stroke-width:1.5px,color:#881337
classDef toneIndigo fill:#e0e7ff,stroke:#4f46e5,stroke-width:1.5px,color:#312e81
class node_routes,node_auth,node_registration,node_profile,node_inertia,node_vue,node_browser toneBlue
class node_reception,node_fleet,node_mechanic,node_dossiers toneAmber
class node_clientwork,node_interactions toneMint
class node_quotes,node_smallparts,node_billing,node_stock,node_users toneRose
class node_clients,node_vehicles,node_interventions,node_devis,node_quote_lines,node_invoices,node_payments,node_stockdata,node_usersdata toneIndigo
```

## Modèle de données

```mermaid
erDiagram
  CLIENT ||--o{ VEHICULE : possede
  CLIENT ||--o{ INTERACTION_CLIENT : a
  VEHICULE ||--o{ INTERVENTION : subit
  INTERVENTION ||--o| DEVIS : a
  DEVIS ||--o{ LIGNE_DEVIS : contient
  INTERVENTION ||--o| FACTURE : a
  FACTURE ||--o{ PAIEMENT : recoit
```

Modèles : `Client`, `Vehicule`, `Intervention`, `Devis`, `LigneDevis`, `Facture`, `Paiement`, `Stock`, `InteractionClient`, `User`.

> Ce schéma est un résumé. Les relations exactes sont définies dans `app/Models` et `database/migrations`.

## Installation

### Prérequis

- PHP 8.2 ou plus récent, avec Composer
- Node.js 18 ou plus récent, avec npm
- MySQL

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/soibiyi/garage-kagnan.git
cd garage-kagnan

# 2. Installer les dépendances
composer install
npm install

# 3. Configurer l'environnement
cp .env.example .env
php artisan key:generate
```

Renseigne ensuite dans `.env` les accès à ta base MySQL (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

```bash
# 4. Créer les tables et le compte administrateur
php artisan migrate --seed

# 5. Lier le stockage public (photos)
php artisan storage:link

# 6. Lancer l'application (deux terminaux)
php artisan serve
npm run dev
```

L'application est disponible sur `http://localhost:8000`.

Le compte administrateur est créé par `database/seeders/AdminUserSeeder.php`. **Change son mot de passe dès la première connexion.**

## Lancement avec Docker

Le dépôt contient un `Dockerfile`, un `docker-compose.yml` et la configuration Nginx (`docker/nginx/conf.d/app.conf`).

```bash
cp .env.example .env
# Adapter le .env pour la production (APP_ENV=production, APP_DEBUG=false, accès base de données)

docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force --seed
```

> Adapte le nom du service (`app`) à celui défini dans ton `docker-compose.yml`.

Pour la production, pense à :
- mettre `APP_DEBUG=false`
- compiler les assets avec `npm run build`
- activer HTTPS devant Nginx
- sauvegarder régulièrement la base de données et le dossier des photos

## Tests

```bash
php artisan test
```

Les tests sont exécutés automatiquement par GitHub Actions (`.github/workflows/tests.yml`).

## Workflow Git

- `main` : branche stable, déployée
- `feature/<nom-de-la-tache>` : une branche par fonctionnalité, fusionnée par pull request

Convention de messages de commit conseillée :

```
feat: ajout du bouton Modifier sur les devis
fix: correction du nommage PSR-4 de PetiteFourniture
refactor: renommage de Vehiculeentete.vue en VehiculeEntete.vue
docs: mise à jour du README
```

Le fichier `CHANGELOG.md` est mis à jour automatiquement par GitHub Actions.

## Structure du projet

```
app/
  Http/
    Controllers/
      Admin/            Gestion des utilisateurs
      Administrative/   Dossiers, factures, stock
      Auth/             Authentification (Breeze)
      Mecanicien/       Vue mécanicien
      Reception/        Réception et parc
    Middleware/         AdminMiddleware, HandleInertiaRequests
  Models/               Client, Vehicule, Intervention, Devis, Facture, Stock...
  Support/              PetiteFourniture
config/sieges.php       Configuration des sièges
database/               Migrations et seeders
docker/nginx/           Configuration Nginx
resources/js/
  Components/           Composants Vue réutilisables
  Layouts/              Mises en page
  Pages/                Pages Inertia (Admin, Administration, ChargeClient, ...)
routes/                 web.php, auth.php
tests/                  Tests Feature et Unit
```

## Auteurs

Projet développé par l'équipe **soibiyi** : [@soibiyi](https://github.com/soibiyi),[@cheick](https://github.com/suuuperhackerhumann), ange836 et Ayo Mark.

## Licence

Projet privé développé pour le garage Kagnan. Tous droits réservés.