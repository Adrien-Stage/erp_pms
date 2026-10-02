# Rôles et accès

## Trois rôles, trois espaces

La console n'a que **trois rôles**, définis comme constantes sur
[`App\Models\User`](../app/Models/User.php). Chacun ouvre un espace, et un seul.

| Rôle | Constante | Espace | Portée |
|---|---|---|---|
| `tech_admin` | `User::ROLE_TECH_ADMIN` | `/tech/*` | Toute la plateforme |
| `owner` | `User::ROLE_OWNER` | `/business/*` | Ses propres établissements |
| `site_editor` | `User::ROLE_SITE_EDITOR` | `/espace-editeur` | Le contenu marketing d'**un seul** site |

Un utilisateur porte **un seul rôle**, dans une colonne `role` (chaîne). Il existe
bien un modèle `Role` avec une relation many-to-many, mais il n'est pas utilisé pour
l'autorisation dans la console — `hasRole()` et `hasAnyRole()` comparent la colonne.

> Ne pas confondre avec les rôles de `wetchah_app` (`admin`, `manager`,
> `reception_chief`, `reception`, `econome`…). Ceux-là vivent dans le code et la
> base de chaque établissement. La console n'en garde aucune copie : l'onglet Rôles
> du tableau de bord TECH les **lit en direct** dans chaque établissement, par son
> API (voir [Droits & rôles d'un établissement](#droits--rôles-dun-établissement)).

## Le middleware `role`

[`EnsureRoleAccess`](../app/Http/Middleware/EnsureRoleAccess.php), aliasé `role`
dans [`bootstrap/app.php`](../bootstrap/app.php), fait deux choses.

**1. Vérifier le rôle.** `role:tech_admin`, ou plusieurs séparés par des virgules.
En cas de refus, il enregistre un `AuditLog` de catégorie `security` **et** un
`Log::warning`, puis :

- requête AJAX ou `X-Expect-Popup: true` → JSON 403 `{ access_denied: true, … }`,
  affiché par le composant [`access-denied-popup`](../resources/views/components/access-denied-popup.blade.php) ;
- sinon, redirection vers la page précédente avec un message flash.

S'il n'y a pas de page précédente, la redirection cible le tableau de bord du rôle
de l'utilisateur. Cet ERP n'a **pas de route `dashboard` unique** — la nommer en dur
produisait une erreur 500 à la place du refus.

**2. Vérifier l'isolation multi-tenant.** Pour tout compte qui n'est pas
`tech_admin`, si la requête porte un `tenant` (paramètre de route ou `tenant_id`),
`canViewTenant()` vérifie que l'établissement lui appartient. Sinon : `403` et
journalisation d'une « Multi-tenant Access Violation ».

## Deux portes d'entrée

La console expose **deux pages de connexion distinctes**, volontairement.

### `/login` — administration

Pour `tech_admin` et `owner`
([`AuthenticatedSessionController`](../app/Http/Controllers/Auth/AuthenticatedSessionController.php)).
Un compte désactivé est refusé avec un message explicite. Après connexion, la
redirection dépend du rôle : `tech.dashboard` ou `business.dashboard`.

### `/espace-editeur/connexion` — éditeurs

Pour `site_editor` uniquement
([`SiteEditorController`](../app/Http/Controllers/SiteEditorController.php)), avec
sa propre vue, **sans aucun marquage ERP**.

Trois différences de traitement, chacune délibérée :

- **Message d'erreur unique** pour tous les échecs — identifiant inconnu, mot de
  passe faux, compte désactivé, compte d'un autre rôle. Les distinguer révélerait
  quels comptes existent, et surtout que cette adresse sert aussi à autre chose.
- **Limitation de débit** : `throttle:5,1` (5 tentatives par minute).
- **Rattachement obligatoire** : un éditeur sans `tenant_id` est déconnecté
  immédiatement — son compte n'a aucun site à éditer.

> La séparation d'URL **réduit la découverte fortuite** de la console, elle ne la
> protège pas. La protection réelle vient du refus de connexion des autres rôles et
> du cloisonnement par `tenant_id`.

## Espace TECH

Réservé à `tech_admin`. C'est le seul espace sans restriction de portée.

| Rubrique | Ce qu'on y fait |
|---|---|
| **Tableau de bord** | Santé des conteneurs, statistiques globales, répartition des rôles |
| **Établissements** | Créer, configurer, provisionner, démarrer/arrêter, mettre à jour, supprimer |
| **Propriétaires** | Registre des `owner` — une entrée par personne, avec accès direct à ses établissements |
| **Employés** | Consulter le personnel d'un établissement ; donner l'accès au portail GRC d'un contrôleur de gestion |
| **Droits & rôles** | Matrice des droits (couche de la console), comptes administrateurs, exceptions, alertes, historique |
| **Éditeurs** | Créer et gérer les comptes `site_editor` d'un établissement |
| **Sauvegardes** | Créer, restaurer, importer, télécharger, planifier |
| **Support** | Journaux applicatifs, interventions, diagnostic, mode assistance |
| **Exports** | Export de la supervision |

Plusieurs méthodes doublent le middleware d'un `if (!$user->isTechAdmin()) abort(403)`
explicite — ceinture et bretelles, notamment pour les actions destructrices.

## Espace BUSINESS

Réservé à `owner`, borné à **ses** établissements (`owner_id`).

Le propriétaire y consulte une vue consolidée : vue d'ensemble 360°, revenus,
statistiques, clients, employés, et un rapport exportable en Excel ou PDF.

> **Cet espace n'a aucune base de données propre.** Chaque écran agrège en direct N
> appels HTTP vers l'API de reporting de N établissements, via
> [`BusinessReportingClient`](../app/Services/BusinessReportingClient.php). Les
> données affichées sont donc toujours celles des établissements — jamais une copie.

Les écrans chargent leurs chiffres en AJAX (`/business/*/data`) : la page s'affiche
immédiatement, les données arrivent ensuite. C'est ce qui évite qu'un établissement
lent bloque tout l'écran.

Un propriétaire règle aussi les **Droits & rôles** de ses établissements : la
matrice et les comptes administrateurs.

## Espace ÉDITEUR

Réservé à `site_editor`, rattaché à un établissement par `tenant_id`.

Une seule page : le formulaire de contenu du site vitrine. L'éditeur n'a accès à rien
d'autre — ni aux autres établissements, ni au reste de la console.

Le cloisonnement tient à un détail d'implémentation qui mérite d'être explicite :

> `update()` réutilise `AdminAuditController::updateSiteContent()` plutôt que d'en
> maintenir une seconde version, **mais l'établissement vient du compte connecté,
> jamais de la requête**. Un éditeur ne peut donc pas viser le site d'un autre, même
> en forgeant sa requête.

`updateSiteContent()` autorise trois profils, et vérifie le troisième explicitement :

```php
$autorise = $user->isTechAdmin()
    || $tenant->owner_id === $user->id
    || ($user->isSiteEditor() && $user->tenant_id === $tenant->id);
```

## Gestion des comptes

### Où vit quel compte

C'est la distinction la plus importante à saisir :

| Compte | Où il vit | Qui le crée |
|---|---|---|
| `tech_admin`, `owner`, `site_editor` | Base **de la console** (SQLite) | La console, nativement (Eloquent) |
| Administrateur d'un établissement (`admin`) | Base **de l'établissement** | La console **seule**, par l'API de l'établissement |
| Tous les autres employés, managers compris | Base **de l'établissement** | L'administrateur de l'établissement, dans `wetchah_app` |
| Accès au portail GRC d'un contrôleur | Base **du GRC** | La console, depuis la fiche de l'employé |

L'administrateur d'un établissement est son **service informatique** : il crée et
tient tous les autres comptes, attribue les rôles et règle la configuration. Ses
propres comptes ne se créent que depuis la console : personne, dans l'établissement,
n'accorde un niveau égal au sien. Tant qu'un établissement n'a pas d'administrateur,
son manager gère les comptes du personnel.

> **La console n'écrit plus dans la base d'un établissement.** Les écritures SQL
> directes (création d'employés, de managers, de contrôleurs, départements)
> contournaient l'application, qui ne pouvait ni les valider ni les tracer. Tout
> passe désormais par l'API d'orchestration de l'établissement
> ([`EtablissementApi`](../app/Services/EtablissementApi.php),
> [`TenantDirectoryClient`](../app/Services/TenantDirectoryClient.php)). La console
> lit encore le personnel en direct ([`TenantDatabase`](../app/Services/TenantDatabase.php)),
> en lecture seule.

### Comptes administrateurs

Onglet **Comptes administrateurs** de « Droits & rôles » : créer (mot de passe saisi,
ou tiré au hasard et montré une seule fois), réinitialiser le mot de passe,
désactiver, réactiver. Mots de passe de **8 caractères au moins**.

### Départements

Créés, modifiés et supprimés depuis la fiche de l'établissement, par son API
(`/api/departements`). Supprimer un département détache ses employés, sans les
supprimer. Les modules d'un département sont un héritage : ils ne donnent plus de
droits.

### Comptes éditeurs

Créés depuis la fiche d'un établissement (`POST /tech/establishments/{tenant}/editors`).
Ils vivent dans la base de la console — contrairement aux employés — mais restent
bornés à ce site par `tenant_id`.

### Actions sur les comptes de la console

`POST /tech/users/{user}/toggle-active` et `POST /tech/users/{user}/reset-password`
permettent de désactiver un compte de la console ou de forcer une réinitialisation de
mot de passe.

## Droits & rôles d'un établissement

Écran [`establishments/permissions-v2`](../resources/views/establishments/permissions-v2.blade.php),
ouvert au `tech_admin` et au propriétaire de l'établissement
([`TenantPermissionMatrixController`](../app/Http/Controllers/TenantPermissionMatrixController.php)).
Le catalogue des droits vit dans le code de l'application : la console le demande
(`GET /api/permissions/matrice`), ne renvoie que des écarts, et l'application refuse
tout droit qu'aucune route n'applique.

### Les couches

Un droit se lit en couches. Un **refus**, quelle que soit sa couche, l'emporte.

| Couche | Posée par | Modifiable depuis la console |
|---|---|---|
| Modèle | Le code de l'application (`PermissionCatalog`) | Non |
| Console | Cet écran (`origin = erp`) | **Oui — la seule** |
| Hôtel | L'administrateur de l'établissement (`origin = etablissement`) | Non, montrée (badge **H**) |
| Exceptions nominatives | L'établissement, sur une personne, avec échéance possible | Non, montrées (badge **N**) |

Un enregistrement **remplace toute la couche de la console** et elle seule : les
réglages de l'hôtel ne sont jamais écrasés.

### La matrice

- Colonnes groupées par service et par niveau (1 administration, 2 direction,
  3 chefs, 4 membres, transversal), avec le nombre de comptes actifs par rôle.
- Le manager se règle ; l'administrateur est montré, **figé** : il consulte tout et
  n'écrit que la configuration et les comptes.
- Une **portée** (ses données, son département, l'établissement) n'est proposée que
  sur les droits dont un écran borne vraiment les données.
- **Cumuls interdits** : cocher une écriture qui ferait exercer à un rôle une
  fonction incompatible (encaisser et enregistrer, détenir le stock et tenir les
  livres, contrôler et participer…) colore la case en rouge et liste le motif.
  Enregistrer exige alors une **dérogation explicite et motivée** ; l'établissement
  la refuse sans elle et la trace dans son journal.
- **Aperçu obligatoire** avant d'enregistrer : l'établissement calcule, compte par
  compte, qui gagne ou perd quel droit — sans rien enregistrer.
- **Motif obligatoire**, recopié sur chaque écart nouveau ou modifié.
- **Concurrence** : l'écran envoie l'empreinte de la couche qu'il a ouverte ; si un
  autre opérateur l'a modifiée entre-temps, rien n'est enregistré.

### Les onglets

| Onglet | Contenu |
|---|---|
| Matrice | Réglage de la couche de la console |
| Comptes administrateurs | Création, réinitialisation, désactivation |
| Interventions | Interventions de l'administrateur de l'établissement dans son exploitation, transmises par l'établissement (badge **Tardive** si la console était injoignable) |
| Exceptions | Exceptions nominatives, restrictions de service, écarts de rôle posés par l'hôtel |
| Alertes | Revue des comptes de l'établissement (cumuls, rôles retirés, comptes sans rôle, pas de comptable, pas d'administrateur), dérogations en vigueur, exceptions échues |
| Historique | Versions de la couche de la console, différences, retour arrière |

### Historique et retour arrière

Chaque enregistrement réussi crée une **version** (table `permission_matrix_versions`
de la console) : l'état complet de la couche, l'auteur, la date, le motif, les
dérogations. Avant le tout premier enregistrement, l'état trouvé dans l'établissement
est conservé comme **version 0**. Revenir à une version la renvoie telle quelle et
l'inscrit comme une **nouvelle** version : l'histoire ne se réécrit pas.

### Compatibilité

Un établissement dont l'application n'annonce pas la version 2 de l'API garde
l'**ancien écran**. Les comptes administrateurs et les départements, eux, exigent la
version à jour : la console le dit plutôt que d'écrire dans la base.

## Interventions des administrateurs d'établissement

L'administrateur d'un établissement consulte tout et n'écrit que la configuration et
les comptes. Encaisser, valider, comptabiliser : il ne le fait que pendant une
**intervention** qu'il déclare dans l'application — motif, durée, services. Son
manager en est prévenu, chaque action est marquée au journal de l'établissement.

L'établissement en transmet la trace à la console
(`POST /api/etablissements/{slug}/interventions`, [`routes/api.php`](../routes/api.php)),
authentifié par **son** secret d'orchestration : aucun établissement n'écrit au nom
d'un autre. Ouverture, clôture et rejeu mettent à jour la même ligne
(`tenant_interventions`). Si la console était injoignable, l'intervention a eu lieu
quand même : la trace arrive après coup et reste marquée **tardive**.

Le `tech_admin` et le propriétaire les consultent dans l'onglet **Interventions** de
« Droits & rôles ».

## Audit

Toute action sensible passe par `AuditLog::record($userId, $action, $description,
$category, $context)`. Catégories utilisées : `auth`, `security`, `tech_admin`,
`support`, `site_editor`.

Le journal alimente l'onglet Support → Interventions de l'espace TECH.

Le middleware [`TrackUserOnlineStatus`](../app/Http/Middleware/TrackUserOnlineStatus.php),
appliqué à tout le groupe `web`, maintient un marqueur en cache exploité par
`User::isOnline()`.

## Considérations de sécurité

- **Le conteneur de la console monte le socket Docker.** Un `tech_admin` compromis
  équivaut à un accès root sur l'hôte. C'est le point le plus sensible de toute
  l'architecture : réserver ce rôle à des comptes de confiance et déployer la console
  sur une machine dédiée.
- **`ASSISTANCE_SECRET`, `REPORTING_SECRET` et `ORCHESTRATION_SECRET` sont propres
  à chaque établissement.** Ils vivent dans son Compose, tirés au hasard à la première
  génération. Lire l'environnement d'un établissement ne donne donc accès à aucun
  autre. Un établissement encore sur l'ancien secret commun le quitte à sa prochaine
  mise à jour ou application des modules.
- **`ORCHESTRATION_SECRET` n'est remis qu'à l'application**, jamais au GRC. Il ouvre
  la matrice des droits, les comptes administrateurs et les départements. Le GRC reçoit
  `REPORTING_SECRET` pour lire les chiffres : avec le même jeton, il aurait pu se créer
  un compte administrateur. Une application antérieure à ce secret est jointe avec
  `REPORTING_SECRET`, sur la matrice seulement.
- **Les mots de passe de base sont stockés en clair** dans la table `tenants` — ils
  doivent être injectés en clair dans le Compose au provisioning. La base de la
  console est donc elle-même un secret à protéger.
- **Mots de passe de 8 caractères au moins** pour tous les comptes créés depuis la
  console (propriétaires, éditeurs, administrateurs d'établissement, accès GRC). Le
  mot de passe proposé à la création d'un propriétaire est tiré au hasard.

## Pour aller plus loin

- [CMS du site vitrine](cms-site-vitrine.md) — ce que l'éditeur peut modifier
- [Exploitation](exploitation.md) — mode assistance et audit
- [Architecture](architecture.md) — modèle de données
