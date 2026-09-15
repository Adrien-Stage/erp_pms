<?php

namespace Demo\Seeders;

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
        $crees       = 0;
        $fournisseurs = [];

        if ($this->tableExists('suppliers')) {
            foreach ($o['suppliers'] as $f) {
                $avant = $this->findId('suppliers', ['name' => $f['name']]);

                $fournisseurs[] = $this->insertOnce(
                    'suppliers',
                    ['name' => $f['name']],
                    array_merge([
                        'contact_name'  => $f['contact'],
                        'contact_phone' => $f['phone'],
                        'contact_email' => $f['email'],
                        'phone'         => $f['phone'],
                        'email'         => $f['email'],
                        'category'      => $f['category'],
                        'is_active'     => true,
                    ], $this->timestamps())
                );

                if ($avant === null) {
                    $crees++;
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

        foreach ($o['stock_items'] as $rang => $article) {
            if ($this->findId('stock_items', ['name' => $article['name']]) !== null) {
                continue;
            }

            $this->insert('stock_items', array_merge([
                'stock_category_id'   => $familles[$article['category']] ?? null,
                'name'                => $article['name'],
                'reference'           => 'DEMO-S' . str_pad((string) ($rang + 1), 4, '0', STR_PAD_LEFT),
                'unit'                => $article['unit'],
                'current_stock'       => $article['min'] * 2,
                'min_stock'           => $article['min'],
                'average_cost'        => $article['price'],
                'last_purchase_price' => $article['price'],
                'supplier_id'         => $fournisseurs === [] ? null : $this->cycle($fournisseurs, $rang),
                'is_active'           => true,
            ], $this->timestamps()));

            $crees++;
        }

        return $crees;
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
