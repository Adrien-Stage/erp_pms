# Données de démonstration

Source de vérité du jeu de données fictif installé dans un établissement.

## Pourquoi ici, et pas dans `wetchah_app`

Chaque établissement a **sa propre base**, dans son propre conteneur. L'ERP y écrit
déjà directement en PDO (`App\Services\TenantDatabase`) pour gérer les employés :
la même voie sert ici.

Conséquence pratique : enrichir le jeu de démonstration ne demande **ni nouvelle
image applicative, ni redéploiement**. On modifie un JSON de ce dossier, et
l'installation suivante en tient compte — y compris sur un établissement déjà en
service.

## Structure

```
demo/
├── README.md          ce fichier
├── manifest.json      ordre d'exécution et module requis par étape
├── data/              le contenu, éditable sans toucher au code
│   ├── people.json        noms, villes, nationalités (clients), sources
│   ├── hotel.json         catégories, chambres, prestations, formules, fiches coûts
│   ├── restaurant.json    carte, garde-manger, fiches recettes et banquets
│   ├── shop.json          rayons, articles et seuils de réassort boutique
│   ├── operations.json    équipes, fournisseurs, 77 articles stock, dépôts, mini-bars, charges, partenaires, budget
│   └── staff.json         personnel de l'hôtel : équipe, poste, rôle, département, mot de passe
├── Registre.php       ce que le jeu a créé, que la purge retire
├── Purger.php         la purge
└── Seeders/           la logique, une classe par module
    ├── AbstractModuleSeeder.php
    ├── StaffSeeder.php
    ├── CustomerSeeder.php
    ├── HotelSeeder.php
    ├── BookingSeeder.php
    ├── RestaurantSeeder.php
    ├── ShopSeeder.php
    └── OperationsSeeder.php
```

Les classes sont autochargées en PSR-4 sous le préfixe `Demo\` (voir
`composer.json`).

## Contrat monétaire

Tous les montants saisis dans `demo/data/*.json` (tarifs chambres, prestations,
formules, carte restaurant, garde-manger, banquets, boutique, coûts d'articles,
fiches coûts, charges et budget) sont exprimés **en FCFA**. Les seeders les
convertissent systématiquement en **centimes** (`* 100`) à l'insertion, en
conformité avec les conventions de `wetchah_app`.

## Idempotence et registre

Rien n'est jamais inséré deux fois : chaque seeder cherche d'abord la ligne
(`insertOnce`). Relancer l'installation complète les manques sans créer de
doublon.

Chaque ligne que le jeu **crée** est inscrite dans la table `demo_records` de
l'établissement (table, id), dans la même transaction (voir `Demo\Registre`).
Les entités préexistantes de l'hôtel portant le même code ou numéro (ex. catégorie
`STD` « Standard » ou chambres existantes) ne sont jamais écrasées ni inscrites.

| Table | Marqueur |
|---|---|
| `customers` | `notes` = `[demo] Client de démonstration` |
| `bookings` | `booking_number` préfixé `DEMO-` |
| `group_bookings` | `group_code` préfixé `DEMO-GRP-` |
| `shop_orders` | `order_number` préfixé `DEMO-V` |
| `restaurant_banquets` | `reference` préfixée `DEMO-BQ-` |
| `maintenance_requests` | `reference` préfixée `DIT-DEMO-` |
| `stock_requisitions` | `number` préfixé `DM-DEMO-` |
| `purchase_requests` | `number` préfixé `DA-DEMO-` |
| `purchase_orders` | `number` préfixé `BC-DEMO-` |
| commandes restaurant, affectations, charges | `notes` commence par `[demo]` |
| `stock_items`, `shop_products`, `partner_organizations` | référence/SKU/code préfixé `DEMO-` |
| `users` | adresse sur le domaine réservé `demo.wetchah.test` |

## Purge

La purge retire tout ce que le jeu a créé, et rien d'autre (`Demo\Purger`) :

1. le transactionnel marqué et ce qui en dérive (ventes, commandes & banquets,
   interventions techniques, demandes/bons d'achat, réservations & groupe,
   folios, paiements, factures, affectations de ménage, charges, discussions) ;
2. les comptes du personnel (`@demo.wetchah.test`), avec leurs rôles, équipes,
   quarts et spécialités ;
3. tout le registre `demo_records`, à rebours de l'installation (lignes mini-bar,
   recettes, garde-manger, dépôts, fiches coûts, budget, stock, catalogue,
   clients, chambres).

Une ligne du jeu que des données réelles citent encore (par exemple un fournisseur
ou un article de stock repris sur un bon de commande réel) reste en place et
reste inscrite jusqu'à ne plus être citée.

## Ce que le jeu ne crée jamais

- **Aucun rôle, aucun administrateur** : les rôles viennent du référentiel de
  l'application, l'administrateur du provisioning.
- **Aucune écriture comptable** (`journal_entries`, `fiscal_years`) : ces tables
  portent des soldes que l'application calcule elle-même.

## Le personnel de démonstration

`staff.json` décrit le personnel de l'hôtel (45 comptes sur `@demo.wetchah.test`,
mot de passe commun `Test@2026`, planifié sur la semaine en cours pour les
services à quarts).
