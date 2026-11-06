# Guide fonctionnel — Garage Kagnan

Ce document décrit les fonctionnalités visibles et les principaux parcours de l’application Garage Kagnan. Il complète le [README](./README.md), qui présente surtout l’installation et l’architecture technique.

> Les menus et les droits dépendent du rôle connecté et, pour les opérations du garage, du siège concerné. Les libellés de statut ci-dessous décrivent les valeurs utilisées par l’application ; certains écrans peuvent les présenter avec une formulation plus lisible.

## 1. Vue d’ensemble

L’application centralise la gestion d’un garage : accueil des clients et véhicules, suivi des ordres de travail, diagnostic, devis, factures et règlements, gestion des pièces, suivi de la relation client et portail en ligne.

Elle comporte deux espaces distincts :

- **Espace de travail** : destiné au personnel, accessible après connexion.
- **Espace client** : portail indépendant permettant aux clients de consulter leurs dossiers et d’échanger avec le garage.

L’interface du personnel est une application Laravel, Vue 3 et Inertia. Les formulaires et pages communiquent avec les contrôleurs Laravel sans API publique séparée. Le portail client utilise également des routes JSON pour ses actions.

## 2. Rôles et menus

| Rôle | Principales rubriques proposées |
|---|---|
| **Administrateur (`admin`)** | Tableau de bord, utilisateurs, interactions, état des véhicules, chiffre d’affaires, missions terminées et historique par chauffeur. Il dispose d’une vue transversale pour plusieurs sièges. |
| **Réceptionniste (`receptionniste`)** | Tableau de bord, création d’une réception et parc de véhicules. |
| **Mécanicien (`mecanicien`)** | Tableau de bord et liste des véhicules à prendre en charge. |
| **Administratif (`administratif`)** | Dossiers, facturation, devis acceptés, historique, devis directs, factures et stocks. |
| **Chargé de clientèle (`charge_client`)** | Tableau de bord et fiches clients. Peut participer au suivi des devis, rendez-vous, demandes de dépannage/convoyage et échanges avec les clients de son siège. |
| **Magasinier (`magasinier`)** | Consultation et mise à jour des pièces, répartition par siège et historique des mouvements. |
| **Chauffeur (`chauffeur`)** | Demandes de dépannage et convoyage de son siège, mission active, partage GPS et historique personnel des missions terminées. |

Le tableau de bord redirige automatiquement les administrateurs vers la gestion des utilisateurs et les magasiniers vers les pièces. Les autres rôles voient les dossiers d’atelier correspondant à leur siège.

### Accès et compte

- Le personnel dispose d’une connexion, d’une inscription, de la vérification d’adresse e-mail, de la récupération et de la réinitialisation du mot de passe, ainsi que d’une page de profil.
- Le portail client a son propre identifiant et son propre mot de passe, distincts des comptes du personnel. Un client peut s’inscrire lui-même ou recevoir un accès provisoire du garage.
- Lorsqu’un mot de passe provisoire est imposé, le client doit le changer avant d’utiliser les autres fonctions du portail.
- Les comptes du personnel autres que les administrateurs sont rattachés à un siège. La configuration actuelle contient **SGK**, **ZGK** et **YGK** ; les noms sont définis dans `config/sieges.php`. Les demandes de rendez-vous et les missions proposées à un chauffeur sont limitées au siège correspondant.

## 3. Parcours d’un véhicule et d’un dossier

### 3.1 Réception

Le formulaire de réception est organisé en quatre étapes. Il permet de :

1. Rechercher un client existant (nom, prénom ou téléphone) ou saisir un nouveau client, particulier ou entreprise.
2. Associer un véhicule existant du client ou saisir un véhicule. Les informations disponibles comprennent l’immatriculation, la marque, le modèle, le VIN, le kilométrage et les dates d’expiration d’assurance et de SICTA.
3. Renseigner l’ordre de travail : siège, numéro OT, date de réception, kilométrage, personne à contacter, mécanicien éventuel et circuit de traitement.
4. Faire l’état du véhicule et joindre les photos.

L’état d’entrée comprend notamment les accessoires et éléments de bord (allume-cigare, équipements, essuie-glaces, rétroviseurs, cric, manivelle, roue de secours, trousse et pare-brise), les enjoliveurs, le niveau de carburant et les remarques.

Quatre photos sont prévues pour les faces avant, arrière, gauche et droite. Jusqu’à six photos supplémentaires peuvent être jointes, soit dix au total. Le formulaire permet de choisir des fichiers et comporte un flux de prise de vue par caméra lorsque l’appareil le permet.

Deux circuits de travail existent :

- **Normal** : parcours avec suivi technique et diagnostic.
- **Devis direct** : parcours plus direct, utilisé notamment lorsqu’une panne est déjà identifiée.

Le numéro OT est associé au siège. Les photos sont stockées avec les fichiers de l’application.

### 3.2 Parc et transmission à l’atelier

Le parc présente les véhicules reçus et, séparément, ceux déjà passés à l’atelier ou en attente d’accord. La fiche d’un dossier donne accès aux informations du véhicule et du client.

La transmission à l’atelier demande l’affectation d’un mécanicien et la saisie de son rapport. Le statut du dossier passe alors à `atelier`. La réception peut corriger les informations du client et du véhicule ; la modification ou la suppression d’un dossier est réservée à la réception et à l’administration.

La suppression d’un dossier supprime également le devis, ses lignes, la facture et ses paiements éventuels, ainsi que ses photos. Le client et le véhicule ne sont pas supprimés.

### 3.3 Atelier et tableau de bord

Le tableau de bord du personnel permet de parcourir les dossiers par circuit (**normal** ou **direct**) et par statut :

- `reception`
- `atelier`
- `en_cours`
- `attente_accord`
- `accepte`

La recherche porte sur le numéro OT, l’immatriculation, la marque, le modèle ou le nom/prénom du client. Les listes sont paginées et des compteurs sont présentés par circuit et statut.

La vue mécanicien liste les dossiers au statut `reception`, avec leur client et leur véhicule. Le mécanicien affecte le dossier et rédige un rapport avant de le transmettre à l’atelier/administration.

### 3.4 Dossiers et devis

L’espace administratif suit les dossiers `atelier`, puis les dossiers en attente d’accord client. Depuis un dossier, le personnel autorisé peut créer ou modifier un devis.

Les lignes d’un devis peuvent contenir :

- une quantité, une désignation et, si nécessaire, une référence de pièce ;
- un prix unitaire net et une remise en pourcentage ;
- une famille et une sous-famille ;
- une option indiquant si la TVA ne doit pas s’appliquer à la ligne.

Le devis calcule les montants HT, les remises, la TVA et le TTC. Les **petites fournitures** peuvent être activées ou non ; leur montant peut être automatique (3 % du montant concerné) ou saisi manuellement.

Les fonctions de devis comprennent :

- devis rattachés à un dossier et **devis directs** ;
- recherche de pièces pour alimenter les lignes ;
- consultation des devis en attente, acceptés et de leur historique ;
- impression et téléchargement PDF des devis ;
- sélection des prestations/pièces acceptées, ligne par ligne, depuis le portail client ou l’écran administratif.

Après transmission de son choix, le client voit les lignes sélectionnées et le devis passer à l’état accepté. Un devis accepté peut encore être modifié dans certaines situations, mais son choix ne peut plus être changé lorsqu’un paiement a déjà été enregistré.

### 3.5 Facturation et paiements

L’espace de facturation présente les dossiers qui attendent l’accord du client et permet d’enregistrer le choix du client. Les factures ont une fiche dédiée et un historique de paiements.

Les règlements peuvent être suivis en plusieurs versements. Les statuts de facture utilisés sont notamment `impayee`, `partiellement_payee` et `payee`. L’application expose également l’export des factures et la suppression d’une facture depuis l’espace administratif.

## 4. Clients et relation client

L’espace **Suivi client** permet de rechercher des clients et d’ouvrir leur fiche. La fiche regroupe leurs véhicules, interventions, devis, factures et paiements, ainsi que les demandes et messages du portail client.

Pour les véhicules, les écrans de suivi proposent :

- les informations du véhicule ;
- les interventions associées et leurs statuts ;
- les relances ;
- l’enregistrement d’interactions avec le client.

L’administration peut consulter les interactions, l’activité d’un chargé client et enregistrer une consigne concernant un client.

Pour un client du portail, le personnel autorisé peut aussi réinitialiser l’accès avec un mot de passe provisoire, consulter les messages et répondre. Une demande de rendez-vous peut être acceptée depuis la fiche client.

## 5. Portail client

Le portail est accessible depuis la page publique « Espace client ». L’inscription demande le prénom, le nom, le téléphone, le siège et un mot de passe confirmé d’au moins dix caractères. L’e-mail est facultatif. L’inscription peut aussi enregistrer un premier véhicule (marque, modèle et immatriculation).

Une fois connecté, le client dispose des sections suivantes :

| Section | Fonction |
|---|---|
| **Tableau de bord** | Résumé des véhicules, interventions en cours, devis en attente et interventions terminées ; raccourcis vers les principales rubriques. |
| **Suivi atelier** | Liste des interventions, OT, dates, statuts et rapports disponibles. |
| **Mes devis** | Consultation des lignes et montants par famille ; choix des lignes à retenir lorsqu’il est disponible. |
| **Historique** | Consultation de l’historique des interventions, factures et paiements. |
| **Rendez-vous** | Demande liée éventuellement à un véhicule, avec prestation, date souhaitée et précisions. |
| **Dépannage** | Demande indiquant le lieu du véhicule et une description du besoin ; point GPS ou emplacement choisi sur la carte, avec suivi du chauffeur affecté. |
| **Convoyage** | Demande précisant le lieu de départ et d’arrivée, les positions pouvant être choisies sur la carte ; le client peut suivre le chauffeur pendant la mission. |
| **Messages** | Conversation entre le client et l’équipe du garage. |
| **Notifications** | Vue des devis et demandes nécessitant l’attention du client. Des badges indiquent les éléments en attente. |
| **Paramètres** | Gestion du profil, du mot de passe et des véhicules associés au compte. |

Les prestations proposées par défaut pour une demande de rendez-vous sont : révision/vidange, freinage, diagnostic électronique, climatisation, carrosserie ou autre. Le véhicule est facultatif pour une demande. Les demandes sont créées en attente de confirmation ; leur statut peut ensuite être affiché comme en attente, acceptée ou refusée.

Le formulaire de rendez-vous n’accepte pas de date passée. Les champs descriptifs et d’adresse ont des limites de longueur. La liste de demandes est actualisée périodiquement dans l’interface.

Pour le dépannage et le convoyage, le suivi du chauffeur est disponible lorsque la demande est acceptée ou en cours. Les positions sont interrogées régulièrement et l’heure de dernière réception est affichée.

### Dépannage, convoyage et suivi chauffeur

Ces demandes nécessitent une position du véhicule et le consentement explicite du client au partage GPS. Le point peut être obtenu depuis l’appareil ou choisi sur la carte. Pour un convoyage, le client renseigne aussi le point d’arrivée. La demande peut associer un véhicule, une adresse et des précisions.

Le fonctionnement est le suivant :

1. La demande est proposée aux chauffeurs du même siège. Un chauffeur peut l’accepter ou la refuser ; un refus ne retire pas la demande aux autres chauffeurs.
2. Un chauffeur ne peut avoir qu’une mission acceptée ou en cours à la fois. Il active le GPS pour démarrer et partager sa position.
3. Le chauffeur voit le point client, la destination éventuelle, un itinéraire routier et un raccourci pour ouvrir la navigation Google Maps. Sa position et le tracé sont actualisés pendant le déplacement ; le suivi nécessite que la page reste ouverte et que la géolocalisation soit autorisée.
4. Le client voit le chauffeur et le trajet sur la carte pendant la mission. Le suivi est aussi accessible au personnel autorisé depuis la fiche client.
5. À la clôture, la dernière position enregistrée et les coordonnées précises du client et de la destination sont supprimées. L’historique conserve les informations de mission utiles (client, chauffeur, dates, type et tarif), mais pas ces coordonnées GPS.

Les cartes de mission utilisent Google Maps JavaScript et Google Routes. La clé publique est configurée localement avec `VITE_GOOGLE_MAPS_KEY`. Google Maps reçoit les positions nécessaires à l’affichage et au calcul d’itinéraire. Les API Maps JavaScript et Routes ainsi que la facturation doivent être configurées côté Google Cloud ; la clé doit être restreinte aux domaines et API nécessaires. Les demandes peuvent aussi contenir une estimation tarifaire ; les montants configurés pour le dépannage de voiture particulière à SGK/ZGK sont à revalider avant de les traiter comme tarifs réglementaires actuels.

## 6. Gestion des pièces et des stocks

Les pages de stock permettent de rechercher les pièces par désignation, référence, marque, modèle, famille ou véhicule correspondant, selon l’écran.

Une fiche de pièce peut inclure les données véhicule/pièce (marque, modèle, génération, moteur, catégorie, famille, sous-famille, désignation, référence et périodicité), un fournisseur, des informations de prix et des quantités.

Les opérations disponibles comprennent :

- création, modification et suppression d’une pièce selon les droits ;
- import et export de données de stock ;
- répartition des quantités attribuées par siège ;
- enregistrement de mouvements d’entrée et de sortie avec motif ;
- consultation de l’historique, filtrable par type de mouvement et siège ;
- annulation d’une sortie, qui restitue la quantité au siège ;
- écran de réapprovisionnement et génération d’un bon de commande ;
- pour le magasinier, filtre « nouveautés » portant sur les pièces créées dans les dernières 48 heures.

Le magasinier renseigne les quantités attribuées et sorties par siège. Le total attribué ne peut pas dépasser la quantité initiale du lot.

## 7. Administration et indicateurs

L’espace d’administration propose :

- la création, la modification et la suppression de comptes du personnel ;
- l’affectation d’un rôle parmi administrateur, réceptionniste, mécanicien, administratif, chargé client, magasinier et chauffeur ;
- l’affectation des employés à un siège et la sélection du siège actif par l’administrateur ;
- l’état des véhicules ;
- le chiffre d’affaires ;
- l’historique des interactions et l’activité des chargés client ;
- la saisie de consignes liées à un client.

L’administration dispose de deux consultations liées aux missions, accessibles depuis son tableau de bord et son menu :

- **Missions terminées** : liste paginée de tous les dépannages et convoyages clôturés, avec le chauffeur responsable, le client, le véhicule, le siège, les détails, l’estimation tarifaire et les dates de demande, d’acceptation, de début et de fin. La liste peut être filtrée par chauffeur.
- **Historique chauffeurs** : vue distincte regroupant les chauffeurs, leur siège, le nombre de missions terminées, le total des estimations et la date de leur dernière mission. Ouvrir un chauffeur affiche ses missions terminées.

Ces historiques ne contiennent pas les coordonnées GPS précises, lesquelles sont supprimées à la clôture de la mission.

La configuration actuelle des sièges est `SGK` (Siège Garage Kagnan), `ZGK` (Zone Industrielle Garage Kagnan) et `YGK` (Yamoussoukro Garage Kagnan).

## 8. Composants d’interface réutilisables

Les principaux composants partagés de l’interface Vue sont dans `resources/js/Components/` :

| Composant | Usage |
|---|---|
| `AuthenticatedLayout` | Enveloppe des pages du personnel : identité, navigation par rôle, profil et déconnexion. |
| `GuestLayout` | Mise en page des écrans d’authentification. |
| `FlashToast` | Affichage des retours temporaires (succès/erreur) transmis par l’application. |
| `MissionMap` | Carte Google Maps réutilisée pour choisir des positions, afficher client/chauffeur/destination et tracer l’itinéraire routier. |
| `MissionTracking` | Interroge périodiquement la position du chauffeur et alimente la carte côté client ou chargé de clientèle. |
| `Modal` | Fenêtre modale réutilisable. |
| `SiegeSwitcher` / `Siegefilter` | Sélection ou filtrage par siège selon l’écran. |
| `VehiculeEntete` | En-tête commun d’une fiche véhicule. |
| `PrimaryButton`, `SecondaryButton`, `DangerButton` | Boutons d’action standardisés. |
| `TextInput`, `InputLabel`, `InputError`, `Checkbox` | Contrôles de formulaire et affichage des erreurs. |
| `Dropdown`, `DropdownLink`, `NavLink`, `ResponsiveNavLink` | Menus déroulants et liens de navigation. |
| `ApplicationLogo` | Logo de l’application. |

Les pages fonctionnelles sont regroupées dans `resources/js/Pages/` par domaine : `Reception`, `Mecanicien`, `Administration`, `Admin`, `ChargeClient`, `EspaceClient`, `Profile` et `Auth`. Les sections du portail sont dans `resources/js/Pages/EspaceClient/sections/`.

## 9. Données principales

Les données sont liées autour des objets métier suivants :

- **Client** — coordonnées, type de client, siège et accès éventuel au portail.
- **Véhicule** — propriétaire, immatriculation, caractéristiques, VIN et dates d’échéance.
- **Intervention** — ordre de travail, siège, réception, état, circuit, affectation et rapport.
- **Devis** et **LigneDevis** — montants, options fiscales, familles et choix acceptés.
- **Facture** et **Paiement** — facture d’une intervention et règlements associés.
- **Stock**, **StockSiege** et **MvtStock** — catalogue de pièces, répartition par siège et mouvements.
- **InteractionClient** et **Consigne** — suivi des échanges et instructions internes.
- **ClientPortalRequest** et **ClientPortalMessage** — demandes et conversations du portail.
- **ClientPortalRequestRejection** — refus d’une proposition par un chauffeur.
- **ClientPortalRequestPosition** — dernière position de mission, effacée à la clôture.
- **User** — compte de personnel, rôle et siège.

Une intervention est rattachée à un véhicule ; un véhicule appartient à un client. Une intervention peut avoir un devis et une facture, un devis contient plusieurs lignes et une facture peut recevoir plusieurs paiements.

## 10. Principaux statuts

| Objet | Statuts/étapes rencontrés |
|---|---|
| Intervention | Réception (`reception`), atelier (`atelier`), en cours (`en_cours`), attente d’accord (`attente_accord`), accepté (`accepte`), terminé (`termine`), facturé (`facture`) ou annulé (`annule`). Certains écrans reconnaissent également `diagnostic`. |
| Devis | En attente (`en_attente`), accepté (`accepte`), refusé (`refuse`) ; les libellés affichés au client sont traduits. |
| Facture | Impayée (`impayee`), partiellement payée (`partiellement_payee`) ou payée (`payee`). |
| Demande du portail | En attente (`en_attente`), acceptée (`accepte`), en cours (`en_cours`), terminée (`terminee`) ou refusée (`refuse`). |
| Mouvement de stock | Entrée (`entree`) ou sortie (`sortie`). |

## 11. Architecture et repères dans le dépôt

| Emplacement | Contenu |
|---|---|
| `routes/web.php`, `routes/auth.php` | Routes des espaces et de l’authentification. |
| `app/Http/Controllers/` | Traitement des formulaires, règles métier et préparation des pages. |
| `app/Models/` | Modèles Eloquent et relations de données. |
| `resources/js/Pages/` | Pages Vue affichées par Inertia. |
| `resources/js/Components/`, `resources/js/Layouts/` | Composants partagés et mises en page. |
| `config/depannage.php` | Horaires et estimations tarifaires configurées pour le dépannage. |
| `database/migrations/`, `database/seeders/` | Schéma de données et données initiales. |
| `config/sieges.php` | Codes et noms des sièges. |
| `storage/` | Fichiers et photos téléversés, journaux et données locales. |

## 12. Exploitation et maintenance

L’application requiert PHP 8.2+, Composer et Node.js avec npm. Les dépendances PHP et JavaScript sont installées respectivement avec `composer install` et `npm install`. Les migrations se lancent avec `php artisan migrate`; les ressources de production sont compilées avec `npm run build`.

Pour le développement, `composer run dev` lance les services définis dans Composer, ou les serveurs peuvent être démarrés séparément avec `php artisan serve` et `npm run dev`. Les tests automatisés sont lancés avec `php artisan test`.

Le dépôt contient également une configuration Docker et Nginx. Avant une mise en production, il faut notamment configurer l’environnement et la base de données, désactiver le mode debug, activer HTTPS et sauvegarder la base ainsi que les photos.
