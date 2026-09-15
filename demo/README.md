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

L'alternative — un seeder embarqué dans `wetchah_app` — aurait figé les données
dans l'image : toute correction de contenu aurait imposé un build et une mise à
jour de chaque établissement.

## Structure

```
demo/
├── README.md          ce fichier
├── manifest.json      ordre d'exécution et module requis par étape
├── data/              le contenu, éditable sans toucher au code
│   ├── people.json        noms, villes, nationalités (clients)
│   ├── hotel.json         catégories et numéros de chambres
│   ├── restaurant.json    catégories et plats de la carte
│   ├── shop.json          rayons et articles de la boutique
│   └── operations.json    équipes, fournisseurs, stock, charges, partenaires
└── Seeders/           la logique, une classe par module
    ├── AbstractModuleSeeder.php
    ├── CustomerSeeder.php
    ├── HotelSeeder.php
    ├── BookingSeeder.php
    ├── RestaurantSeeder.php
    ├── ShopSeeder.php
    └── OperationsSeeder.php
```

Les classes sont autochargées en PSR-4 sous le préfixe `Demo\` (voir
`composer.json`).

## Contrat d'une classe de seeder

Chaque seeder étend `AbstractModuleSeeder` et implémente :

- `module(): ?string` — le module qui doit être actif pour que l'étape tourne.
  `null` = socle, toujours installé.
- `label(): string` — ce qui s'affiche dans le journal d'installation.
- `seed(): int` — insère et renvoie le nombre d'enregistrements créés.

Les insertions passent par les aides du parent (`insert()`, `exists()`,
`table()`), qui utilisent des requêtes préparées.

## Idempotence

Rien n'est jamais inséré deux fois. Chaque enregistrement de démonstration porte
un marqueur reconnaissable dans une colonne naturelle :

| Table | Marqueur |
|---|---|
| `customers` | `notes` commence par `[demo]` |
| `bookings` | `booking_number` préfixé `DEMO-` |
| autres | `name` / `label` présent dans le jeu de référence |

Relancer l'installation complète les manques sans créer de doublon. C'est ce qui
permet de rejouer l'action après l'ajout d'un module.

## Volume

Environ **20 enregistrements par module**, réglable dans `manifest.json`
(`volume`). Les relations sont respectées : une réservation pointe vers un client
et une chambre réellement créés, une ligne de folio vers sa réservation.

## Ce que le jeu ne crée jamais

- **Aucun utilisateur ni rôle** : les comptes viennent de la console, et un compte
  fictif capable de se connecter serait une porte d'entrée.
- **Aucune écriture comptable** (`journal_entries`, `fiscal_years`) : ces tables
  portent des soldes que l'application calcule elle-même. Y injecter des lignes
  fausserait les états financiers sans moyen simple de revenir en arrière.
