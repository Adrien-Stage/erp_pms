# Jeu de données — création d'un établissement

Données prêtes à saisir dans le formulaire **TECH → Établissements → Nouvel
établissement** (`http://localhost:8080/tech/establishments/create`).

Toutes les personnes et sociétés citées ici sont **fictives**. Les valeurs
respectent les règles de validation de `AdminAuditController::storeTenant()`.

---

## Profil A — établissement complet (tous modules + site vitrine)

### Étape 1 · Propriétaire

Choisir **« Nouveau propriétaire »** (`owner_type = new`).

| Champ | Valeur | Contrainte |
|---|---|---|
| Nom complet | `Nadège Etoundi` | requis, 255 max |
| E-mail | `n.etoundi@palmiers-kribi.cm` | requis, **unique dans `users`** |
| Société | `Palmiers Hospitality SARL` | optionnel |
| Nationalité | `Camerounaise` | requis |
| Téléphone | `+237 699 45 21 08` | optionnel, 30 max |
| Mot de passe | `Palmiers2026` | requis, **4 caractères minimum** |

> Le propriétaire se connectera ensuite sur `http://localhost:8080/login` avec
> son **e-mail** comme identifiant, et arrivera sur l'espace `/business`.

### Étape 2 · Configuration technique

| Champ | Valeur | Contrainte |
|---|---|---|
| Nom de la base | `palmiers_kribi_db` | requis, **unique dans `tenants`** |
| Port d'écoute local | `8081` | requis, entier, **unique dans `tenants`** |
| Utilisateur PostgreSQL | `pms` | défaut du formulaire |
| Mot de passe PostgreSQL | `secret` | défaut du formulaire |
| Port DB | `5432` | défaut du formulaire |

**Sur les ports.** `8081` est le port suggéré par le formulaire (`max(app_port) + 1`,
avec 8080 comme plancher) et il est libre sur votre machine — seuls 8080 (l'ERP),
5433 (PostgreSQL de l'ERP) et 9000 (Portainer) sont occupés. Le port du site
vitrine n'est pas demandé : il vaut `app_port + 1000`, donc **9081**, et il doit
être libre lui aussi. Le port DB, lui, n'est jamais publié sur l'hôte — c'est une
métadonnée, aucun risque de collision avec le 5433 de l'ERP.

**Les deux champs « Infrastructure Docker » : laissez-les vides.** Le contrôleur
écrase ce que vous y mettez et impose toujours `meka-erp-{slug}-app` et
`meka-erp-{slug}-db` ([AdminAuditController.php:213](app/Http/Controllers/AdminAuditController.php:213)).

### Étape 3 · Informations de l'établissement

| Champ | Valeur | Contrainte |
|---|---|---|
| Nom | `Résidence Les Palmiers Kribi` | requis |
| Slug | `palmiers-kribi` | requis, **unique dans `tenants`** |
| Pays | `Cameroun` | optionnel |
| Ville | `Kribi` | optionnel |
| Devise | `XAF` | requis, **3 caractères max** |
| Adresse | `Route de la Lobé, quartier Mpalla` | optionnel |
| Téléphone | `+237 233 46 12 90` | optionnel |
| E-mail de contact | `contact@palmiers-kribi.cm` | optionnel |
| Logo | *(facultatif)* | image, **2 Mo max** |

> Le slug est généré automatiquement depuis le nom — il donnerait ici
> `residence-les-palmiers-kribi`. Écrasez-le par `palmiers-kribi` : il sert à la
> fois d'URL locale (`http://palmiers-kribi.localhost:8080`) et de préfixe aux
> noms de conteneurs, donc plus il est court, plus la supervision est lisible.

### Thème — palette bord de mer

Chaque valeur doit correspondre à `^#[0-9A-Fa-f]{6}$` (6 chiffres hexadécimaux,
pas de forme courte à 3).

| Rôle | Couleur |
|---|---|
| Primaire | `#0E4D45` |
| Secondaire | `#7FB3A8` |
| Accent | `#E8C98A` |
| Fond sombre | `#061F1C` |
| Surface sombre | `#10322D` |
| Texte sur clair | `#0E4D45` |
| Texte sur sombre | `#E8F1EE` |

*(La palette par défaut du formulaire est brun/or : `#391F0E`, `#CCAB87`,
`#EED4A3`, `#0F0201`, `#2C1810`, `#391F0E`, `#CCAB87`.)*

### Modules

Cochez tout **sauf l'IA** — c'est la sélection par défaut du formulaire :

| Module | État | Note |
|---|---|---|
| Hôtel | ✅ | Core |
| Restaurant | ✅ | |
| Boutique / Point de vente | ✅ | |
| Housekeeping | ✅ | |
| Discussions | ✅ | |
| Analytics | ✅ | |
| Comptabilité | ✅ | Core |
| Comptabilité avancée | ✅ | |
| Intelligence artificielle (Mistral) | ❌ | nécessite une clé API |
| API d'intégration | ✅ | |
| Site web vitrine (SvelteKit) | ✅ | |

> **Dépendance implicite :** cocher « Site web vitrine » active l'API même si vous
> la décochez — `applyModuleDependencies()` l'ajoute d'office
> ([AdminAuditController.php:490](app/Http/Controllers/AdminAuditController.php:490)).

---

## Profil B — petit hôtel, sans site vitrine

Variante plus légère, utile pour vérifier qu'un établissement sans module
`website` ne déclenche pas le conteneur web. **Tous les champs uniques diffèrent
du profil A** — vous pouvez donc créer les deux à la suite.

| Champ | Valeur |
|---|---|
| Propriétaire (nouveau) | `Serge Ondoua` — `s.ondoua@aubergemontfebe.cm` |
| Société / Nationalité / Téléphone | `Ondoua Frères SARL` · `Camerounaise` · `+237 677 12 84 30` |
| Mot de passe | `Febe2026` |
| Nom de la base | `mont_febe_db` |
| Port d'écoute local | `8082` |
| Nom | `Auberge du Mont Fébé` |
| Slug | `mont-febe` |
| Ville / Pays / Devise | `Yaoundé` · `Cameroun` · `XAF` |
| Adresse | `Boulevard du Mont Fébé, quartier Bastos` |
| Téléphone / E-mail | `+237 222 21 40 15` · `reception@aubergemontfebe.cm` |
| Modules | Hôtel, Housekeeping, Comptabilité, Discussions **uniquement** |

Le reste (identifiants PostgreSQL, thème) peut garder les valeurs par défaut.

---

## Champs remplis automatiquement — ne rien saisir

| Colonne | Valeur imposée |
|---|---|
| `docker_app_container` | `meka-erp-{slug}-app` |
| `docker_db_container` | `meka-erp-{slug}-db` |
| `docker_status` | `creating`, puis suivi par le provisioning |
| `web_port` | `app_port + 1000` |
| `is_active` | `true` |
| `settings` | assemblé à partir de pays / ville / logo / thème |

---

## Avant de cliquer sur « Provisionner »

La création en base et le provisioning Docker sont **deux étapes distinctes** :
le formulaire enregistre l'établissement avec le statut `creating`, le
provisioning qui suit télécharge les images et démarre les conteneurs.

Trois points à vérifier sur cette machine Ubuntu fraîchement installée :

1. **Le réseau Docker partagé existe** — c'est un prérequis du `docker-compose.yml` :
   ```bash
   docker network create pms
   ```
   (Il est déjà là si l'ERP démarre, mais autant le confirmer.)

2. **Les ports 8081 et 9081 sont libres** — le provisioning s'arrête net sinon
   (`assertHostPortFree`, [TenantProvisioningService.php:635](app/Services/TenantProvisioningService.php:635)) :
   ```bash
   ss -ltn | grep -E ':(8081|9081)\b' || echo 'ports libres'
   ```

3. **Les images GHCR se téléchargent** — elles sont publiques, donc aucune
   authentification n'est requise, mais le premier `pull` est long :
   ```bash
   docker pull ghcr.io/adrien-stage/villa_b:latest
   ```

Le dossier hôte `TENANTS_BASE_PATH` (`/var/wetchah/tenants`) existe déjà et est
monté dans le conteneur.

---

## Rappel des contraintes d'unicité

Si vous inventez vos propres valeurs, ces quatre champs doivent être uniques,
sans quoi la validation échoue avant toute création :

- `owner_email` → unique dans la table `users`
- `db_name` → unique dans la table `tenants`
- `app_port` → unique dans la table `tenants`
- `slug` → unique dans la table `tenants`

Le slug `demo-resort` et la base `demo_resort_db` sont déjà pris par
l'établissement de démonstration créé par le seeder.
