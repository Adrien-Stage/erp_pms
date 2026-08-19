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

> Ne pas confondre avec les rôles de `wetchah_app` (`admin`, `manager`, `reception`,
> `cashier`, `econome`…). Ceux-là vivent dans la base de chaque établissement. La
> console ne les crée pas : elle les **documente** dans
> [`App\Support\TenantRoles`](../app/Support/TenantRoles.php) et compte leur
> répartition pour l'onglet Rôles du tableau de bord TECH.

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
| **Employés** | Modifier, activer/désactiver, supprimer les employés **dans la base de l'établissement** |
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

Un propriétaire peut aussi créer un compte gérant pour l'un de ses établissements.

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

| Compte | Où il vit | Conséquence |
|---|---|---|
| `tech_admin`, `owner`, `site_editor` | Base **de la console** (SQLite) | Géré nativement par Eloquent |
| Employés d'un établissement | Base **de l'établissement** (PostgreSQL) | Géré en PDO direct |

[`TenantUserController`](../app/Http/Controllers/TenantUserController.php) écrit
directement dans la base de l'établissement via
[`TenantDatabase`](../app/Services/TenantDatabase.php).

> **Il n'y a rien à synchroniser.** Écrire ici, c'est écrire dans la base que
> `wetchah_app` lit : une modification est visible immédiatement côté établissement,
> et inversement. Un `tech_admin` gère tous les établissements, un `owner` seulement
> les siens.

### Comptes éditeurs

Créés depuis la fiche d'un établissement (`POST /tech/establishments/{tenant}/editors`).
Ils vivent dans la base de la console — contrairement aux employés — mais restent
bornés à ce site par `tenant_id`.

### Actions sur les comptes

`POST /tech/users/{user}/toggle-active` et `POST /tech/users/{user}/reset-password`
permettent de désactiver un compte ou de forcer une réinitialisation de mot de passe.

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
- **`ASSISTANCE_SECRET` et `REPORTING_SECRET` sont des secrets partagés** entre la
  console et tous les établissements. Les changer impose de régénérer les Compose et
  de recréer les conteneurs concernés.
- **Les mots de passe de base sont stockés en clair** dans la table `tenants` — ils
  doivent être injectés en clair dans le Compose au provisioning. La base de la
  console est donc elle-même un secret à protéger.
- **La validation impose un mot de passe propriétaire d'au moins 4 caractères**
  (`min:4`). C'est très faible pour un compte donnant accès à des données
  financières — à durcir.

## Pour aller plus loin

- [CMS du site vitrine](cms-site-vitrine.md) — ce que l'éditeur peut modifier
- [Exploitation](exploitation.md) — mode assistance et audit
- [Architecture](architecture.md) — modèle de données
