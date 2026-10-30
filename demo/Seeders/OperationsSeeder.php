<?php

namespace Demo\Seeders;

use Demo\Registre;

/**
 * L'exploitation : ménage, fournisseurs, stock, charges et partenaires.
 *
 * Regroupés dans une même étape parce qu'ils partagent le même socle — le parc
 * de chambres et les employés — et qu'aucun ne justifie à lui seul une étape
 * dans le journal d'installation.
 *
 * Chaque bloc vérifie l'existence de sa table : ces modules sont apparus au fil
 * des versions de wetchah_app, et un établissement en retard d'une version doit
 * recevoir le reste du jeu plutôt qu'une erreur.
 */
class OperationsSeeder extends AbstractModuleSeeder
{
    public function module(): ?string
    {
        return null;
    }

    public function label(): string
    {
        return 'Ménage, fournisseurs, stock et charges';
    }

    public function seed(): int
    {
        $o = $this->data['operations'];

        return $this->menage($o)
            + $this->achats($o)
            + $this->charges($o)
            + $this->partenaires($o);
    }

    /** Équipes de ménage et affectations sur les chambres. */
    private function menage(array $o): int
    {
        if (!$this->tableExists('housekeeping_teams')) {
            return 0;
        }

        $crees   = 0;
        $equipes = [];

        foreach ($o['housekeeping_teams'] as $equipe) {
            $avant = $this->findId('housekeeping_teams', ['name' => $equipe['name']]);

            $equipes[] = $this->insertOnce(
                'housekeeping_teams',
                ['name' => $equipe['name']],
                array_merge(['color' => $equipe['color'], 'is_active' => true], $this->timestamps())
            );

            if ($avant === null) {
                $crees++;
            }
        }

        if (!$this->tableExists('housekeeping_assignments') || $equipes === []) {
            return $crees;
        }

        $chambres = $this->ids('rooms');
        if ($chambres === []) {
            return $crees;
        }

        $repere = '[demo] Affectation de démonstration';
        $deja   = $this->count('housekeeping_assignments', 'notes = ?', [$repere]);
        $vise   = $this->volume['housekeeping_assignments'] ?? 20;
        $par    = $this->anyUserId();

        for ($i = $deja; $i < $vise; $i++) {
            $statut  = $this->cycle($o['assignment_statuses'], $i);
            $affecte = $this->jours(-($i % 10), '08:00:00');

            $this->insert('housekeeping_assignments', array_merge([
                'housekeeping_team_id' => $this->cycle($equipes, $i),
                'room_id'              => $this->cycle($chambres, $i),
                'assigned_by'          => $par,
                'status'               => $statut,
                'notes'                => $repere,
                'assigned_at'          => $affecte,
                'started_at'           => $statut === 'pending' ? null : $affecte,
                'completed_at'         => $statut === 'completed' ? $this->jours(-($i % 10), '10:30:00') : null,
            ], $this->timestamps($affecte)));

            $crees++;
        }

        return $crees;
    }

    /** Fournisseurs, familles de stock et articles. */
    private function achats(array $o): int
    {
        $crees  = 0;
        $parNom = [];

        if ($this->tableExists('suppliers')) {
            foreach ($o['suppliers'] as $f) {
                $avant = $this->findId('suppliers', ['name' => $f['name']]);

                $parNom[$f['name']] = $this->insertOnce(
                    'suppliers',
                    ['name' => $f['name']],
                    array_merge([
                        'contact_name'  => $f['contact'],
                        'contact_phone' => $f['phone'],
                        'contact_email' => $f['email'],
                        'phone'         => $f['phone'],
                        'email'         => $f['email'],
                        'category'      => $f['category'],
                        'city'          => $f['city'] ?? null,
                        'payment_terms' => $f['payment_terms'] ?? null,
                        'is_active'     => true,
                    ], $this->timestamps())
                );

                if ($avant === null) {
                    $crees++;
                } elseif (Registre::contient($this->pdo, 'suppliers', $avant)) {
                    // Un fournisseur du jeu, d'une installation antérieure :
                    // sa catégorie devient une clé que l'application connaît.
                    $this->pdo->prepare('UPDATE suppliers SET category = ? WHERE id = ?')->execute([$f['category'], $avant]);
                }
            }
        }

        if (!$this->tableExists('stock_items')) {
            return $crees;
        }

        $familles = [];

        foreach ($o['stock_categories'] as $rang => $cat) {
            $avant = $this->findId('stock_categories', ['name' => $cat['name']]);

            $familles[$cat['name']] = $this->insertOnce(
                'stock_categories',
                ['name' => $cat['name']],
                array_merge(['sort_order' => $rang, 'is_active' => true], $this->timestamps())
            );

            if ($avant === null) {
                $crees++;
            }
        }

        // Les zones de rangement du magasin : chaque famille a la sienne
        // (viandes et poissons en chambre froide 1, eaux et sirops en cave…).
        // Le « Magasin central » que l'application pose est repris tel quel.
        $zoneDeFamille = [];

        if ($this->tableExists('storage_zones')) {
            foreach ($o['storage_zones'] ?? [] as $rang => $zone) {
                $avant = $this->findId('storage_zones', ['name' => $zone['name']]);
                $zoneId = $avant ?? $this->insert('storage_zones', array_merge(
                    ['name' => $zone['name'], 'sort_order' => $rang, 'is_active' => true],
                    $this->timestamps()
                ));

                foreach ($zone['categories'] as $famille) {
                    $zoneDeFamille[$famille] = $zoneId;
                }

                if ($avant === null) {
                    $crees++;
                }
            }
        }

        foreach ($o['stock_items'] as $rang => $article) {
            $tarifs = array_values(array_filter(
                $article['suppliers'] ?? [],
                fn (array $t) => isset($parNom[$t[0]])
            ));
            $existant = $this->findId('stock_items', ['name' => $article['name']]);

            if ($existant !== null) {
                // Un article du jeu reçoit les tarifs qui lui manquent ; celui
                // de l'établissement, homonyme, n'est pas touché.
                if (Registre::contient($this->pdo, 'stock_items', $existant)) {
                    $this->tarifs($existant, $tarifs, $parNom);

                    // Rangé, d'une installation antérieure, au magasin central par défaut.
                    if (isset($zoneDeFamille[$article['category']])) {
                        $this->pdo->prepare('UPDATE stock_items SET storage_zone_id = ? WHERE id = ?')
                            ->execute([$zoneDeFamille[$article['category']], $existant]);
                    }
                }
                continue;
            }

            $itemId = $this->insert('stock_items', array_merge([
                'stock_category_id'   => $familles[$article['category']] ?? null,
                'storage_zone_id'     => $zoneDeFamille[$article['category']] ?? null,
                'name'                => $article['name'],
                'reference'           => 'DEMO-S' . str_pad((string) ($rang + 1), 4, '0', STR_PAD_LEFT),
                'unit'                => $this->unite($article['unit']),
                'current_stock'       => $article['min'] * 2,
                'min_stock'           => $article['min'],
                // Les prix du jeu sont en FCFA ; l'application compte en centimes.
                'average_cost'        => $article['price'] * 100,
                'last_purchase_price' => $article['price'] * 100,
                // Le fournisseur habituel : le premier de la liste.
                'supplier_id'         => isset($tarifs[0]) ? $parNom[$tarifs[0][0]] : null,
                'is_active'           => true,
            ], $this->timestamps()));

            $this->tarifs($itemId, $tarifs, $parNom);
            $crees++;
        }

        return $crees;
    }

    /** @var array<string, string> unité du jeu => symbole de l'établissement */
    private array $unites = [];

    /**
     * L'unité de l'établissement qui correspond à celle du jeu, par son nom
     * (« pièce », « Kilogramme ») ou son symbole (« kg », « pcs ») ; l'article
     * porte son symbole, comme ceux que l'application crée. Une unité
     * inconnue (plateau…) est ajoutée à la liste pour rester choisissable.
     */
    private function unite(string $unite): string
    {
        if (isset($this->unites[$unite]) || !$this->tableExists('stock_units')) {
            return $this->unites[$unite] ?? $unite;
        }

        $avecSymbole = (bool) $this->pdo->query(
            "SELECT 1 FROM information_schema.columns WHERE table_name = 'stock_units' AND column_name = 'short_name'"
        )->fetchColumn();
        $colonnes = $avecSymbole ? "name, COALESCE(NULLIF(short_name, ''), name)" : 'name, name';
        $condition = $avecSymbole ? 'lower(name) = lower(?) OR lower(short_name) = lower(?)' : 'lower(name) = lower(?)';

        $stmt = $this->pdo->prepare("SELECT {$colonnes} FROM stock_units WHERE {$condition} ORDER BY id LIMIT 1");
        $stmt->execute($avecSymbole ? [$unite, $unite] : [$unite]);
        $trouvee = $stmt->fetch(\PDO::FETCH_NUM);

        if ($trouvee !== false) {
            return $this->unites[$unite] = $trouvee[1];
        }

        $this->insert('stock_units', array_merge([
            'name'       => mb_strtoupper(mb_substr($unite, 0, 1)) . mb_substr($unite, 1),
            'short_name' => $unite,
            'sort_order' => 100,
            'is_active'  => true,
        ], $this->timestamps()));

        return $this->unites[$unite] = $avecSymbole ? $unite : mb_strtoupper(mb_substr($unite, 0, 1)) . mb_substr($unite, 1);
    }

    /**
     * Le prix de l'article chez chacun de ses fournisseurs, dans son unité de
     * stock, en centimes.
     *
     * @param  list<array{0: string, 1: int}>  $tarifs  [nom du fournisseur, prix en FCFA]
     * @param  array<string, int>  $parNom
     */
    private function tarifs(int $itemId, array $tarifs, array $parNom): void
    {
        if (!$this->tableExists('stock_item_supplier')) {
            return;
        }

        foreach ($tarifs as [$fournisseur, $prix]) {
            $this->insertOnce(
                'stock_item_supplier',
                ['stock_item_id' => $itemId, 'supplier_id' => $parNom[$fournisseur]],
                array_merge(['purchase_price' => $prix * 100, 'priced_at' => $this->jours(-20)], $this->timestamps())
            );
        }
    }

    /** Charges d'exploitation des derniers mois. */
    private function charges(array $o): int
    {
        if (!$this->tableExists('expenses')) {
            return 0;
        }

        $par   = $this->anyUserId();
        $crees = 0;

        foreach ($o['expenses'] as $i => $charge) {
            if ($this->findId('expenses', ['label' => $charge['label']]) !== null) {
                continue;
            }

            $this->insert('expenses', array_merge([
                'occurred_at'    => $this->jours(-($i * 4 + 2), '10:00:00'),
                'category'       => $charge['category'],
                'label'          => $charge['label'],
                'amount'         => $charge['amount'],
                'payment_method' => $this->cycle(['cash', 'transfer', 'mobile_money'], $i),
                'recorded_by'    => $par,
                'notes'          => '[demo] Charge de démonstration',
            ], $this->timestamps()));

            $crees++;
        }

        return $crees;
    }

    /** Conventions et organisations partenaires. */
    private function partenaires(array $o): int
    {
        if (!$this->tableExists('partner_organizations')) {
            return 0;
        }

        $crees = 0;

        foreach ($o['partner_organizations'] as $rang => $p) {
            if ($this->findId('partner_organizations', ['name' => $p['name']]) !== null) {
                continue;
            }

            $this->insert('partner_organizations', array_merge([
                'name'                        => $p['name'],
                'code'                        => 'DEMO-P' . str_pad((string) ($rang + 1), 3, '0', STR_PAD_LEFT),
                'type'                        => $p['type'],
                'contact_name'                => $p['contact'],
                'contact_phone'               => $p['phone'],
                'valid_from'                  => $this->date(-180),
                'valid_until'                 => $this->date(185),
                'is_active'                   => true,
                'room_discount_type'          => 'percent',
                'room_discount_value'         => $p['discount'],
                'restaurant_discount_percent' => max(0, $p['discount'] - 5),
                'shop_discount_percent'       => max(0, $p['discount'] - 5),
                'notes'                       => '[demo] Convention de démonstration',
            ], $this->timestamps()));

            $crees++;
        }

        return $crees;
    }
}
