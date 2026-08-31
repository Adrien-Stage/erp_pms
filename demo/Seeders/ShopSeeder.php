<?php

namespace Demo\Seeders;

/**
 * Boutique : rayons, articles et ventes.
 *
 * Comme le restaurant, conditionné au module correspondant.
 */
class ShopSeeder extends AbstractModuleSeeder
{
    public function module(): ?string
    {
        return 'shop';
    }

    public function label(): string
    {
        return 'Boutique et ventes';
    }

    public function seed(): int
    {
        if (!$this->tableExists('shop_products')) {
            return 0;
        }

        $s     = $this->data['shop'];
        $crees = 0;

        // --- Rayons ---
        $rayons = [];

        foreach ($s['categories'] as $rang => $cat) {
            $avant = $this->findId('shop_categories', ['name' => $cat['name']]);

            $rayons[$cat['name']] = $this->insertOnce(
                'shop_categories',
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

        // --- Articles ---
        $articles = [];

        foreach ($s['products'] as $rang => $produit) {
            $rayon = $rayons[$produit['category']] ?? null;
            if ($rayon === null) {
                continue;
            }

            $avant = $this->findId('shop_products', ['name' => $produit['name']]);

            $articles[] = [
                'id'    => $this->insertOnce(
                    'shop_products',
                    ['name' => $produit['name']],
                    array_merge([
                        'shop_category_id' => $rayon,
                        'price'            => $produit['price'],
                        'stock_quantity'   => $produit['stock'],
                        'sku'              => 'DEMO-' . str_pad((string) ($rang + 1), 4, '0', STR_PAD_LEFT),
                        'is_active'        => true,
                    ], $this->timestamps())
                ),
                'price' => $produit['price'],
            ];

            if ($avant === null) {
                $crees++;
            }
        }

        if (!$this->tableExists('shop_orders') || $articles === []) {
            return $crees;
        }

        return $crees + $this->ventes($s, $articles);
    }

    /**
     * Ventes récentes. `created_by` est obligatoire en base : sans employé dans
     * l'établissement, on s'abstient plutôt que d'échouer sur une contrainte.
     */
    private function ventes(array $s, array $articles): int
    {
        $operateur = $this->anyUserId();

        if ($operateur === null) {
            return 0;
        }

        $vise    = $this->volume['shop_orders'] ?? 20;
        $clients = $this->ids('customers', 'notes = ?', [CustomerSeeder::MARQUEUR]);
        $crees   = 0;

        for ($i = 0; $i < $vise; $i++) {
            $numero = 'DEMO-V' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            if ($this->findId('shop_orders', ['order_number' => $numero]) !== null) {
                continue;
            }

            $quand   = $this->jours(-($i % 21), sprintf('%02d:%02d:00', 9 + ($i % 11), ($i * 13) % 60));
            $methode = $this->cycle($s['payment_methods'], $i);

            $lignes = [];
            $total  = 0;
            $pieces = 0;

            for ($l = 0; $l < 1 + ($i % 3); $l++) {
                $article = $this->cycle($articles, $i * 2 + $l);
                $qte     = 1 + ($l % 2);

                $lignes[] = ['article' => $article, 'qte' => $qte];
                $total   += $article['price'] * $qte;
                $pieces  += $qte;
            }

            $commandeId = $this->insert('shop_orders', array_merge([
                'order_number'   => $numero,
                'customer_id'    => $clients === [] ? null : $this->cycle($clients, $i),
                'total_items'    => $pieces,
                'subtotal'       => $total,
                'tax_amount'     => 0,
                'total_amount'   => $total,
                'payment_status' => 'paid',
                'payment_method' => $methode,
                'paid_at'        => $quand,
                'created_by'     => $operateur,
                'notes'          => '[demo] Vente de démonstration',
            ], $this->timestamps($quand)));

            $crees++;

            foreach ($lignes as $ligne) {
                $this->insert('shop_order_items', array_merge([
                    'shop_order_id'   => $commandeId,
                    'shop_product_id' => $ligne['article']['id'],
                    'quantity'        => $ligne['qte'],
                    'unit_price'      => $ligne['article']['price'],
                    'item_total'      => $ligne['article']['price'] * $ligne['qte'],
                ], $this->timestamps($quand)));

                $crees++;
            }
        }

        return $crees;
    }
}
