# Plan de Réalisation — Architecture ERP-per-database HotelixOS

> Projet `erp_pms` (console admin) + `villa_b` (template établissement)
> Objectif : transformer un PMS mono-site en plateforme on-premise multi-établissements.

---

## 🎯 Vision cible

> **1 établissement = 1 application = 1 base de données**, le tout orchestré depuis une console admin unique déployée chez le client.

L'admin n'est pas un hôtel : c'est une **usine à fabriquer et superviser des hôtels**. Chaque établissement vit dans son propre couple de containers Docker (`app` + `db`), totalement indépendant et isolé.

### Les deux faces de l'admin global

| | **TECH** (côté technique) | **BUSINESS** (côté propriétaire) |
|---|---|---|
| **Qui** | L'équipe technique / support | Le `owner` |
| **Responsabilité** | Fabriquer les établissements, monitorer les containers, gérer owners/rôles/modules, audits techniques, imports/exports, support | Vue d'ensemble *de ses* établissements : chiffres, employés, revenus, rapports périodiques, audit business graphique |
| **Données** | Stockées dans **une BD SQLite dédiée** *(déjà implémentée)* | **Aucune BD propre** → lit en direct dans les BD des établissements du propriétaire |

### Flux de déploiement

```
Chez le client, on installe UNE fois le projet admin (containerisé)
        ↓
TECH crée un établissement via le formulaire
        ↓
Script shell de provisioning → clone villa_b (branche main) → build image →
   lance 2 containers (app + db isolés) → migrations
        ↓
L'établissement tourne de façon autonome
        ↓
TECH active/désactive des modules (site web, API…) par établissement
```

### Règle des modules site/API

- **Site web activé** → API activée **automatiquement** (le site SvelteKit consomme l'API)
- **API activée** → site web **non obligatoire** (peut exister pour des intégrations tierces sans site public)

### Choix techniques arrêtés

- **Dépôt template** : `https://github.com/Adrien-Stage/villa_b.git` — **branche `main`**
- **Stockage données TECH** : **SQLite** *(déjà implémenté dans `erp_pms`)*
- **Cœur applicatif** : Laravel (villa_b)
- **Site web public** : SvelteKit (futur repo/dossier séparé)

---

## 🗺️ Vue d'ensemble du chantier

```
Phase 0  Fondations        → repartir sur une base saine (corrections)
Phase 1  Admin Docker      → containériser la console erp_pms
Phase 2  Provisioning fiable → tester/corriger le flux complet de création
Phase 3  Module site web   → ajouter le 3e container SvelteKit
Phase 4  Séparation TECH/BUSINESS → espace owner avec vue agrégée
Phase 5  Nettoyage final   → retirer le code hôtel de erp_pms + BelongsToTenant
```

---

## Phase 0 — Fondations & corrections *(court)*

**Objectif** : repartir sur une base saine avant d'étendre.

| Tâche | Détail | État |
|---|---|---|
| Restaurer `nginx.conf` + `supervisord.conf` villa_b | Les deux fichiers contenaient par erreur le script `entrypoint.sh` | ✅ Fait |
| Corriger le **fallback local hardcodé** | Dans `TenantProvisioningService::resolveGithub()`, le chemin `/c/Users/user/Herd/villab` est en dur → le paramétrer via une variable d'env (ex: `LOCAL_FALLBACK_PATH`) | ✅ Fait (`config/provisioning.php` + `.env` + `.env.docker`) |
| Corriger le **réseau `pms`** | Le compose admin déclare `pms` en réseau interne. Pour que les containers d'établissements le rejoignent, il faut créer un réseau Docker **externe partagé** et le déclarer `external: true` dans le compose admin | ✅ Fait (réseau `pms` créé sur l'hôte, `external: true` dans le compose, containers admin connectés sans coupure) |
| Vérifier le **clone GitHub branche `main`** | Le `git clone --depth=1` actuel clone déjà la branche par défaut (`main`) — confirmer que `main` est bien à jour côté dépôt distant | ✅ Validé (`main` distant = commit `53244ac`, Dockerfile + configs docker corrects, mergés via PR #60 depuis develop) |
| Commit + tag de version template villa_b | Marquer la version `villa_b` qui sert de référence (après restauration des configs docker) | ⏭️ Skip (choix utilisateur : pas de tag) |

**Livrable** : un provisioning de base qui clone `main` et fonctionne, avec un réseau partagé correct.

---

## Phase 1 — Containeriser la console admin *(déjà fait)*

**Objectif** : `erp_pms` tourne dans Docker, chez le client, sur le réseau `pms`.

> ✅ **Déjà fonctionnel.** L'admin tourne déjà dans Docker avec :
> - Container `MEKA_ERP-app` (Laravel + nginx, port `8080`)
> - Container `pms-db` (Postgres, port `5433`)
> - Container `pms-vite` (Vite dev server, port `5173`)
> - Socket Docker monté : `/var/run/docker.sock`
> - Volume Herd monté : `c:/Users/user/Herd:/c/Users/user/Herd`
>
> **Reste à finaliser** (traité en Phase 0) : convertir le réseau `pms` en réseau externe partagé pour que les établissements puissent le rejoindre.

Fichiers existants (ne pas recréer) :
- `docker-compose.yml`
- `docker/app/Dockerfile`
- `docker/app/nginx.conf`
- `docker/app/supervisord.conf`
- `.env.docker`

---

## Phase 2 — Fiabiliser le provisioning de bout en bout

**Objectif** : créer un établissement réel de A à Z, sans erreur.

| Tâche | Détail |
|---|---|
| Test complet source `local` | Création → build image → compose → migrate → seed → établissement accessible |
| Test complet source `github` (branche `main`) | Idem avec clone |
| Gestion d'erreurs robuste | Si une étape échoue, le `docker_status` passe à `error`, logs SSE clairs, container nettoyé |
| Re-provisioning | Pouvoir relancer le provisioning sur un tenant cassé |
| Validation du réseau `pms` | L'admin peut `docker exec` dans les containers des établissements |
| Health check automatique | Cron ou bouton « vérifier tous les établissements » |

**Livrable** : on peut créer plusieurs établissements de test, chacun isolé, tous supervisables depuis l'admin.

---

## Phase 3 — Module site web (SvelteKit)

**Objectif** : activer un site public par établissement, avec la règle API/site.

```
docker-compose d'un établissement (version étendue) :
  app    (Laravel)        ← toujours présent
  db     (Postgres)       ← toujours présent
  web    (SvelteKit)      ← UNIQUEMENT si module site activé
  api    (exposé via app) ← activé si site OU si api seul
```

| Tâche | Détail |
|---|---|
| Créer un repo/template `villa_b_web` (SvelteKit) | Ou un sous-dossier dans villa_b |
| Étendre `TenantProvisioningService` | Générer un compose à 2 ou 3 services selon les modules activés |
| Règle métier | `website_enabled` → force `api_enabled = true` ; `api_enabled` seul → pas de site |
| Port d'exposition du site | Un 3e port par établissement (ex: `app=8081`, `db=5434`, `web=3001`) |
| UI admin | Toggle « Site web » + « API » dans le formulaire/édition d'établissement, avec la règle ci-dessus |

**Livrable** : un établissement avec site public SvelteKit qui consomme l'API Laravel de ce même établissement.

---

## Phase 4 — Séparation TECH / BUSINESS

**Objectif** : l'espace `owner` devient une vraie console business agrégée.

**Côté TECH** *(déjà bien amorcé)* :
- Supervision containers, audit, gestion owners/rôles/modules, imports/exports
- Pas de changement majeur, juste des finitions

**Côté BUSINESS** *(nouveau chantier)* :

| Rubrique | Source des données |
|---|---|
| Vue d'ensemble (KPIs graphiques) | Lecture agrégée dans **chaque BD d'établissement** du propriétaire |
| Revenus par établissement | Connexion PDO ponctuelle à chaque BD (déjà partiellement fait dans `showTenant`) |
| Employés par établissement | Idem |
| Audit business graphique | Charts (Chart.js ou ApexCharts) |
| Rapport périodique (PDF) | DomPDF ou export Excel |

**Piège à anticiper** : la lecture croisée de N bases doit avoir des **timeouts courts** et du **cache** pour ne pas ralentir l'admin si un établissement est down.

**Livrable** : un `owner` se connecte et voit une dashboard consolidée de tous ses hôtels.

---

## Phase 5 — Nettoyage final *(à faire en tout dernier — irréversible)*

**Objectif** : `erp_pms` devient une console pure, `villa_b` devient un template propre.

| Tâche | Détail |
|---|---|
| Retirer le code hôtel de `erp_pms` | Supprimer `bookings/`, `rooms/`, `restaurant/`, `shop/`, `housekeeping/`, `discussions/` etc. **Sauvegarder une branche `legacy` avant** |
| Nettoyer le trait `BelongsToTenant` de `villa_b` | 36 fichiers concernés (Phase 2 du plan initial) |
| Alléger les migrations/seeders de `erp_pms` | Ne garder que `tenants`, `users`, `audit_logs`, etc. |
| Documentation finale | `README` de déploiement chez le client (install Docker → up admin → créer établissements) |

**Livrable** : deux dépôts propres et focalisés, prêts pour la production.

---

## 📅 Ordre recommandé & dépendances

```
Phase 0  ──►  Phase 1  ──►  Phase 2  ──►  Phase 4
                 │              │
                 └──────────────┴──►  Phase 3  (parallélisable avec la 4)
                                          │
                                          └──►  Phase 5  (en dernier, irreversible)
```

- **Phase 0 + 1 + 2** = le minimum pour avoir un produit fonctionnel déployable
- **Phase 3 + 4** = enrichissements (parallélisables)
- **Phase 5** = uniquement quand tout le reste est validé (irréversible)

---

## ✅ État courant de conformité (audit initial)

### `erp_pms` (admin) — conforme
- `app/Services/TenantProvisioningService.php` ✅
- `config/provisioning.php` ✅
- `app/Http/Controllers/AdminAuditController.php` (store/provision/stream/start/stop/restart/health) ✅
- Migration `add_source_to_tenants_table` ✅
- `app/Models/Tenant.php` (`$fillable`) ✅
- `routes/web.php` (route SSE) ✅
- `.env` (variables provisioning) ✅
- `create.blade.php` (champ source_type/source_path) ✅
- `show.blade.php` (widget SSE) ✅

### `villa_b` (template) — 2 fichiers restaurés
- `Dockerfile` ✅
- `docker/entrypoint.sh` ✅
- `docker/nginx.conf` ✅ *(restauré — contenait par erreur entrypoint.sh)*
- `docker/supervisord.conf` ✅ *(restauré — contenait par erreur entrypoint.sh)*
