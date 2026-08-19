# CMS du site vitrine

Chaque établissement peut avoir un **site public**, servi par un troisième conteneur
(`wetchah_site`, SvelteKit). Son contenu marketing est saisi dans la console et
consommé par le site à l'exécution.

## Où vit quelle donnée

C'est la clé pour comprendre le module. Le site n'a **aucune base de données** : il
assemble deux sources distinctes.

| Donnée | Source | Modifiée par |
|---|---|---|
| Textes, images, SEO, sections | `Tenant::site_content` — **base de la console** | Console (TECH, propriétaire, éditeur) |
| Chambres, types, tarifs, menu | Base de **l'établissement** | Le personnel, dans `wetchah_app` |
| Logo, adresse, téléphone, e-mail | Fiche `Tenant` — base de la console | Console |

Concrètement : le titre de la page Hébergements se modifie dans la console, mais la
grille des chambres se remplit toute seule depuis l'application. La réception n'a
jamais à ressaisir quoi que ce soit pour le site.

```
       wetchah_erp                      wetchah_app
   (contenu marketing)              (chambres, menu, tarifs)
            │                                │
   CMS_API_URL │                    │ TENANT_API_URL
            └───────────┬────────────┘
                        ▼
                   wetchah_site
              (assemble et rend la page)
```

## Activer le module

Cocher **`website`** dans les modules de l'établissement.

> Cocher `website` active **automatiquement** `api` : le site consomme l'API de
> l'établissement, l'activer sans elle produirait un site vide. L'inverse est libre —
> `api` seul est utile pour une intégration tierce.

L'activation ajoute un conteneur `meka-erp-{slug}-web` au Compose de
l'établissement, sur le port `web_port` (par défaut `app_port + 1000`).

La désactivation supprime ce conteneur. Le contenu saisi, lui, **reste en base** :
réactiver le module le retrouve intact.

## Qui peut éditer

Trois profils, vérifiés dans
[`updateSiteContent()`](../app/Http/Controllers/AdminAuditController.php) :

| Profil | Portée |
|---|---|
| `tech_admin` | Tous les établissements |
| `owner` | Ses propres établissements |
| `site_editor` | **Uniquement** l'établissement de son `tenant_id` |

L'éditeur passe par un espace dédié (`/espace-editeur`) avec sa propre page de
connexion — voir [Rôles et accès](roles-et-acces.md).

## Le schéma de contenu

[`App\Support\SiteContentSchema`](../app/Support/SiteContentSchema.php) est la
**source de vérité unique**. Trois consommateurs itèrent ce même schéma :

- le formulaire d'édition (onglets) ;
- la persistance et ses règles de validation, **générées** depuis le schéma ;
- l'API publique.

> Ajouter un champ au schéma le rend automatiquement éditable, validé et exposé. Il
> n'y a pas de second endroit à modifier — c'est tout l'intérêt du dispositif.

### Structure

`pages → sections → champs`. Chaque section porte un drapeau `enabled` : le site
n'affiche que les sections actives. Un hôtel sans vidéo décoche simplement la
section vidéo.

| Page | Sections |
|---|---|
| **Accueil** (`home`) | Hero, Notre philosophie, Nos équipements, Nos hébergements, Témoignages, Vidéo, Nos offres, Restaurant, Découverte, Galerie Instagram, Newsletter, Formulaire de contact |
| **Hébergements** (`heb`) | Bannière *(la grille des chambres vient de l'application)* |
| **Restaurant** (`resto`) | Bannière, L'expérience, Galerie photos *(la carte vient de l'application)* |
| **À propos** (`about`) | Bannière, Bienvenue, Nos installations |
| **Contact** (`contact`) | Bannière, Infos pratiques, Carte |

### Types de champs

| Type | Saisie | Stockage |
|---|---|---|
| `text` | Champ court, 255 max | Chaîne |
| `textarea` | Texte long, 8000 max | Chaîne |
| `image` | Upload unique, 4 Mo max | Chemin `site/…` |
| `images` | Galerie multi-upload | Tableau de chemins |
| `items` | Liste structurée en textarea | Tableau d'objets |

Le type `items` mérite une explication : une ligne par élément, colonnes séparées
par ` | ` selon les `keys` de la section. Pour un champ à deux clés
(`title`, `description`) :

```
Wi-Fi haut débit | Connexion fibre dans tout l'établissement
Parking privé | Gratuit et sécurisé
```

`parseItems()` et `itemsToRaw()` font l'aller-retour entre cette saisie et le JSON
stocké. C'est un compromis assumé : moins souple qu'un éditeur de liste, mais
saisissable sans formation.

### Hydratation et rétrocompatibilité

`hydrate()` reconstruit la structure complète à partir du JSON stocké, en comblant
les valeurs manquantes par des valeurs par défaut.

Elle assure aussi une **migration douce** depuis l'ancien format à plat
(`hero`, `about`, `contact`, `gallery` au premier niveau) tant que `pages` n'a jamais
été enregistré. Un établissement créé avant l'arrivée du format à onglets continue
donc de fonctionner, et bascule au premier enregistrement.

## L'API publique

```
GET /api/public/establishments/{slug}/content
```

**Sans authentification** — c'est du contenu destiné à être public. Route déclarée
hors de tout groupe protégé dans [`routes/web.php`](../routes/web.php).

Réponse :

```json
{
  "name": "…",
  "logo": "http://…/storage/logos/…",
  "hero":    { "title": "…", "subtitle": "…", "cta_label": "…", "background_image": "…" },
  "about":   { "title": "…", "body": "…" },
  "contact": { "intro": "…", "hours": "…", "address": "…", "phone": "…", "email": "…" },
  "gallery": ["…"],
  "seo":     { "title": "…", "description": "…" },
  "pages":   { "home": { "hero": { … } }, "heb": { … } }
}
```

Deux points d'implémentation :

- **Les clés à plat (`hero`, `about`, `contact`, `gallery`) sont conservées** pour
  les versions du site déjà déployées. Le format à jour est `pages`.
- **Toutes les images sont résolues en URLs absolues**, préfixées par
  `config('app.url')`. Le storage vit dans la console, pas dans le conteneur du
  site : un chemin relatif y serait introuvable.

> Conséquence : **`APP_URL` doit être une adresse joignable depuis le navigateur du
> visiteur**, pas un hostname Docker interne. Sinon les images du site public seront
> cassées.

## Ce que le site récupère ailleurs

Le conteneur web reçoit deux URLs au provisioning :

| Variable | Valeur | Fournit |
|---|---|---|
| `CMS_API_URL` | `http://{CMS_CONTAINER_NAME}` | Le contenu marketing ci-dessus |
| `TENANT_API_URL` | `http://meka-erp-{slug}-app` | Chambres, types, tarifs, menu |

Côté établissement, l'API publique consommée est `/api/v1/*` : `rooms`,
`room-types`, `restaurant/menu`, et un `POST /bookings` limité en débit pour les
demandes de réservation depuis le site.

## Mettre à jour le site

Deux choses distinctes, à ne pas confondre :

| Action | Effet | Redémarrage ? |
|---|---|---|
| **Modifier le contenu** | Écrit dans `site_content` | Non — le site le relit à l'exécution |
| **Mettre à jour l'image** | Réépingle `web_image_tag` et recrée le conteneur | Oui |

> Le contenu ne demande **aucune action Docker**. C'est la raison pour laquelle un
> éditeur non technique peut travailler sans risque : il ne touche jamais à
> l'infrastructure.

La mise à jour de l'image se fait par `POST /tech/establishments/{tenant}/update-website`,
avec journaux en direct comme pour l'application.

## Onglet Identité du site

Le formulaire expose aussi quelques champs qui **modifient la fiche du tenant**, pas
le contenu : logo, téléphone, e-mail, adresse. Ils alimentent l'en-tête et le pied
de page du site.

L'adresse, le téléphone et l'e-mail de la page Contact viennent donc de là — la
section « Infos pratiques » ne porte que l'introduction et les horaires.

## Points d'attention

| Symptôme | Cause probable |
|---|---|
| Images cassées sur le site | `APP_URL` n'est pas joignable depuis le navigateur du visiteur |
| Le contenu ne s'affiche pas | `CMS_CONTAINER_NAME` ne correspond pas au conteneur réel de la console |
| Formulaires rejetés en « cross-site » | `ORIGIN` incorrect dans le conteneur web — le `web_port` a changé sans régénération du Compose |
| Une section n'apparaît pas | Son drapeau `enabled` est décoché |
| Chambres absentes | Le module `api` est désactivé, ou le conteneur app ne répond pas |

## Pour aller plus loin

- [Provisioning](provisioning.md) — le conteneur web et ses variables
- [Rôles et accès](roles-et-acces.md) — comptes éditeurs
