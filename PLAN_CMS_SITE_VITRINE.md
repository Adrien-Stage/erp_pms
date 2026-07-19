# Plan — CMS marketing (pms) + APIs opérationnelles (wetchah_app) + site vitrine (wetchah_site)

> Complète `PLAN_REALISATION_ARCHITECTURE.md` (Phase 3 — Module site web) avec le
> détail de la répartition CMS / données opérationnelles / consommation côté site.

---

## Principe directeur

Les chambres et le menu restaurant sont des **données opérationnelles** qui vivent
déjà dans la base de chaque établissement (modèles `Room`, `RoomType`,
`RestaurantMenuItem`...) côté `wetchah_app` — pas de duplication dans `pms`.

Le site vitrine (`wetchah_site`) consomme donc **deux APIs distinctes** :
- contenu marketing → depuis `pms`
- chambres / menu → depuis `wetchah_app` (l'instance de l'établissement lui-même)

---

## Partie 1 — `pms` : CMS marketing (contenu uniquement)

**Stockage** : nouvelle colonne JSON `site_content` sur la table `tenants`
(séparée de `settings`, pour ne pas surcharger ce champ déjà chargé —
thème/logo/pays/ville). Structure :

```json
{
  "hero": { "title": "...", "subtitle": "...", "cta_label": "...", "background_image": "site/hero-xxx.jpg" },
  "about": { "title": "...", "body": "..." },
  "contact": { "intro": "...", "hours": "..." },
  "gallery": ["site/img1.jpg", "site/img2.jpg"],
  "seo": { "title": "...", "description": "..." }
}
```

**UI admin** : nouvel onglet **"Contenu du site"** sur la fiche établissement —
visible uniquement si le module `website` est activé (réutilise le système de
modules déjà câblé). Formulaire par sections (Hero, À propos, Contact, Galerie
multi-upload, SEO), soumission POST classique — pas besoin de SSE.

**API publique en lecture** : `GET /api/public/establishments/{slug}/content` —
pas d'auth (contenu public), retourne le JSON ci-dessus avec les URLs de
galerie résolues en **URLs absolues** vers le storage de `pms`
(`config('app.url') . '/storage/...'`).

---

## Partie 2 — `wetchah_app` : API publique opérationnelle (chambres + menu)

Nouveau groupe de routes, **hors du middleware `auth`**, lecture seule :

- `GET /api/v1/room-types` — liste des types de chambres (nom, description,
  capacité, prix, photos) via une `RoomTypeResource` Laravel (n'expose que les
  champs pertinents publiquement — pas de stock, pas de statut interne)
- `GET /api/v1/room-types/{id}` — détail
- `GET /api/v1/restaurant/menu` — catégories + articles — **protégé par le
  middleware `module:restaurant`** déjà construit, donc automatiquement
  indisponible si le module est désactivé pour cet établissement

Les images restent servies localement (`asset('storage/...')`) — pas de
problème cross-container puisqu'elles appartiennent déjà à ce container,
cohérent avec la décision prise pour le logo applicatif (self-service manager,
pas de lien inter-conteneurs).

---

## Partie 3 — `wetchah_site` : consommation des deux APIs

**Variables d'env injectées au provisioning** (même mécanisme que
`TENANT_SLUG`/`TENANT_MODULES`) :
- `TENANT_SLUG`
- `CMS_API_URL` → hostname **interne Docker** de `pms` (ex: `http://MEKA_ERP-app`),
  pas `localhost:8080` — le fetch se fait côté serveur SvelteKit, dans le
  réseau `pms` partagé
- `TENANT_API_URL` → hostname interne du container applicatif de cet
  établissement (`http://meka-erp-{slug}-app`)

Utiliser `$env/dynamic/private` (pas `PUBLIC_`) — ces URLs ne doivent pas
fuiter côté navigateur.

**Implémentation SvelteKit** :
- `src/routes/+layout.server.ts` → fetch contenu CMS une fois, partagé à
  toutes les pages
- `src/routes/heb/+page.server.ts` → fetch `TENANT_API_URL/api/v1/room-types`
- `src/routes/resto/+page.server.ts` → fetch
  `TENANT_API_URL/api/v1/restaurant/menu`, gère gracieusement le 403 si module
  désactivé (masque la page/le lien de nav)

---

## Partie 4 — Intégration provisioning (`pms`, `TenantProvisioningService`)

- `generateDockerCompose()` ajoute un **3ᵉ service `web`** uniquement si
  `website` est dans `tenant.modules` — même logique on/off que le reste
- Nouveau champ `web_port` sur `Tenant` (même pattern que `app_port`/`db_port`)
- Injection des env vars `TENANT_SLUG`, `CMS_API_URL`, `TENANT_API_URL` dans ce
  service
- L'image du service `web` est **pull depuis un registre** (voir
  `PLAN_DOCKERISATION.md` dans `wetchah_site` + décision CI/CD), pas buildée
  localement — même architecture que `wetchah_app` (Phase 2bis)

---

## Ordre d'implémentation proposé

1. API `wetchah_app` (rooms + menu)
2. CMS `pms` (stockage + UI + API publique)
3. Intégration `wetchah_site` (fetch des deux APIs)
4. Provisioning : 3ᵉ container `web`, pull d'image CI (voir décision Docker
   ci-dessous)
