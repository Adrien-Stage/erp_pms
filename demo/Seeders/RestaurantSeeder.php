<?php

namespace Demo\Seeders;

/**
 * Carte du restaurant et commandes clients.
 *
 * N'est installé que si le module « restaurant » est actif : peupler la carte
 * d'un établissement qui n'a pas de restaurant encombrerait ses écrans sans
 * qu'aucun ne soit accessible.
 */
class RestaurantSeeder extends AbstractModuleSeeder
{
    public function module(): ?string
    {
        return 'restaurant';
    }

    public function label(): string
    {
        return 'Carte et commandes restaurant';
    }

    public function seed(): int
    {
        if (!$this->tableExists('restaurant_menu_items')) {
            return 0;
        }

        $r     = $this->data['restaurant'];
        $crees = 0;

        // --- Catégories de la carte ---
        $categories = [];

        foreach ($r['categories'] as $rang => $cat) {
            $avant = $this->findId('restaurant_menu_categories', ['name' => $cat['name']]);

            $categories[$cat['name']] = $this->insertOnce(
                'restaurant_menu_categories',
                ['name' => $cat['name']],
                array_merge([
                    'description' => $cat['description'],
                    'sort_order'  => $rang,
                    'is_active'   => true,
                ], $this->timestamps())
            );

            if ($avant === null) {
                $crees++;
            }
        }

        // --- Plats ---
        $plats = [];

        foreach ($r['items'] as $rang => $item) {
            $avant = $this->findId('restaurant_menu_items', ['name' => $item['name']]);

            $plats[] = [
                'id'    => $this->insertOnce(
                    'restaurant_menu_items',
                    ['name' => $item['name']],
                    array_merge([
                        'restaurant_menu_category_id' => $categories[$item['category']] ?? null,
                        'price'         => $item['price'],
                        'description'   => null,
                        'meal_services' => json_encode($item['meals']),
                        'sort_order'    => $rang,
                        'is_active'     => true,
                    ], $this->timestamps())
                ),
                'name'  => $item['name'],
                'price' => $item['price'],
            ];

            if ($avant === null) {
                $crees++;
            }
        }

        if (!$this->tableExists('restaurant_customer_orders') || $plats === []) {
            return $crees;
        }

        // --- Commandes ---
        $crees += $this->commandes($r, $plats);

        return $crees;
    }

    /** Des commandes réparties sur les derniers jours, avec leurs lignes. */
    private function commandes(array $r, array $plats): int
    {
        $vise      = $this->volume['restaurant_orders'] ?? 20;
        $operateur = $this->anyUserId();
        $crees     = 0;

        // Le repère d'idempotence : les commandes de démonstration portent une
        // note dédiée, la table n'ayant pas de numéro naturel.
        $repere = '[demo] Commande de démonstration';
        $deja   = $this->count('restaurant_customer_orders', 'notes = ?', [$repere]);

        for ($i = $deja; $i < $vise; $i++) {
            $quand  = $this->jours(-($i % 14), sprintf('%02d:%02d:00', 11 + ($i % 10), ($i * 7) % 60));
            $statut = $this->cycle($r['order_statuses'], $i);
            $paye   = $statut === 'served';

            // Deux à trois plats par commande.
            $lignes = [];
            $total  = 0;

            for ($l = 0; $l < 2 + ($i % 2); $l++) {
                $plat = $this->cycle($plats, $i * 3 + $l);
                $qte  = 1 + ($l % 2);

                $lignes[] = ['plat' => $plat, 'qte' => $qte];
                $total   += $plat['price'] * $qte;
            }

            $commandeId = $this->insert('restaurant_customer_orders', array_merge([
                'table_number'   => $this->cycle($r['tables'], $i),
                'customer_name'  => null,
                'status'         => $statut,
                'total_amount'   => $total,
                'notes'          => $repere,
                'placed_at'      => $quand,
                'source'         => 'dine_in',
                'created_by'     => $operateur,
                'payment_status' => $paye ? 'paid' : 'unpaid',
                'payment_method' => $paye ? $this->cycle(['cash', 'mobile_money', 'card'], $i) : null,
                'amount_paid'    => $paye ? $total : 0,
                'paid_at'        => $paye ? $quand : null,
                'paid_by'        => $paye ? $operateur : null,
            ], $this->timestamps($quand)));

            $crees++;

            foreach ($lignes as $ligne) {
                $this->insert('restaurant_customer_order_items', array_merge([
                    'restaurant_customer_order_id' => $commandeId,
                    'restaurant_menu_item_id'      => $ligne['plat']['id'],
                    'item_name'                    => $ligne['plat']['name'],
                    'quantity'                     => $ligne['qte'],
                    'unit_price'                   => $ligne['plat']['price'],
                    'total_price'                  => $ligne['plat']['price'] * $ligne['qte'],
                ], $this->timestamps($quand)));

                $crees++;
            }
        }

        return $crees;
    }
}
