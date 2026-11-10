<?php

namespace Demo\Seeders;

use Demo\Registre;

/**
 * Boutique : rayons, articles (prix en centimes et 2 alertes de seuil) et ventes.
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
        $posId = $this->boutiqueId();
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

        // --- Articles (prix en centimes FCFA, seuil de réassort pour les alertes) ---
        $articles = [];

        foreach ($s['products'] as $rang => $produit) {
            $rayon = $rayons[$produit['category']] ?? null;
            if ($rayon === null) {
                continue;
            }

            $sku          = 'DEMO-' . str_pad((string) ($rang + 1), 4, '0', STR_PAD_LEFT);
            $prixCentimes = (int) $produit['price'] * 100;
            $seuil        = (int) ($produit['reorder_level'] ?? 5);
            $avant        = $this->findId('shop_products', ['sku' => $sku])
                ?? $this->findId('shop_products', ['name' => $produit['name']]);

            if ($avant === null) {
                $id = $this->insert('shop_products', array_merge([
                    'shop_category_id' => $rayon,
                    'name'             => $produit['name'],
                    'price'            => $prixCentimes,
                    'stock_quantity'   => (int) $produit['stock'],
                    'reorder_level'    => $seuil,
                    'sku'              => $sku,
                    'is_active'        => true,
                ], $this->timestamps()));
                $crees++;
            } else {
                $id = $avant;
                if (Registre::contient($this->pdo, 'shop_products', $id)) {
                    $this->pdo->prepare(
                        'UPDATE shop_products SET price = ?, reorder_level = ?, stock_quantity = ? WHERE id = ?'
                    )->execute([$prixCentimes, $seuil, (int) $produit['stock'], $id]);
                }
            }

            $articles[] = [
                'id'    => $id,
                'name'  => $produit['name'],
                'price' => $prixCentimes,
            ];
        }

        if (!$this->tableExists('shop_orders') || $articles === []) {
            return $crees;
        }

        return $crees + $this->ventes($s, $articles, $posId);
    }

    /** Ventes récentes (3 datées J0 dont 1 mise sur note chambre, 17 sur les 20 derniers jours). */
    private function ventes(array $s, array $articles, ?int $posId): int
    {
        $caissiers = array_values(array_filter([
            $this->userIdByEmail('ulrich.nana@' . StaffSeeder::DOMAINE, false),
            $this->userIdByEmail('prisca.feudjio@' . StaffSeeder::DOMAINE, false),
            $this->userIdByEmail('sylvie.tchamba@' . StaffSeeder::DOMAINE, false),
        ]));
        $operateurDefaut = $caissiers[0] ?? $this->anyUserId();

        if ($operateurDefaut === null) {
            return 0;
        }

        $vise          = $this->volume['shop_orders'] ?? 20;
        $clients       = $this->ids('customers', 'notes = ?', [CustomerSeeder::MARQUEUR]);
        $sejourSuite   = $this->sejourEnCours('DEMO-0010') ?? $this->sejourEnCours();
        $crees         = 0;

        for ($i = 0; $i < $vise; $i++) {
            $numero = 'DEMO-V' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            if ($this->findId('shop_orders', ['order_number' => $numero]) !== null) {
                continue;
            }

            // 3 ventes d'aujourd'hui (J0) : espèces, mobile_money, et 1 room_charge sur DEMO-0010.
            $decalage = $i < 3 ? 0 : -(($i - 2) % 20 + 1);
            $heure    = $i < 3
                ? sprintf('%02d:%02d:00', 9 + $i, 15 + $i * 10)
                : sprintf('%02d:%02d:00', 9 + ($i % 11), ($i * 13) % 60);
            $quand    = $this->jours($decalage, $heure);

            $methode = match ($i) {
                0       => 'cash',
                1       => 'mobile_money',
                2       => $sejourSuite !== null ? 'room_charge' : 'card',
                default => $this->cycle(['cash', 'mobile_money', 'card'], $i),
            };

            if ($methode === 'room_charge' && $sejourSuite === null) {
                $methode = 'cash';
            }

            $lignes = [];
            $total  = 0;
            $pieces = 0;

            for ($l = 0; $l < 1 + ($i % 3); $l++) {
                $article  = $this->cycle($articles, $i * 2 + $l);
                $qte      = 1 + ($l % 2);
                $lignes[] = ['article' => $article, 'qte' => $qte];
                $total   += $article['price'] * $qte;
                $pieces  += $qte;
            }

            $estMiseEnChambre = ($methode === 'room_charge' && $sejourSuite !== null);
            $bookingId        = $estMiseEnChambre ? (int) $sejourSuite['id'] : null;
            $customerId       = $estMiseEnChambre
                ? (int) $sejourSuite['customer_id']
                : ($clients === [] ? null : $this->cycle($clients, $i));
            $operateur        = $caissiers !== [] ? $this->cycle($caissiers, $i) : $operateurDefaut;

            $commandeId = $this->insert('shop_orders', array_merge([
                'order_number'     => $numero,
                'point_of_sale_id' => $posId,
                'booking_id'       => $bookingId,
                'customer_id'      => $customerId,
                'customer_name'    => $estMiseEnChambre ? $sejourSuite['customer_name'] : null,
                'total_items'      => $pieces,
                'subtotal'         => $total,
                'tax_amount'       => 0,
                'total_amount'     => $total,
                'payment_status'   => 'paid',
                'payment_method'   => $methode,
                'paid_at'          => $quand,
                'created_by'       => $operateur,
                'notes'            => '[demo] Vente de démonstration',
            ], $this->timestamps($quand)));
            $crees++;

            if ($estMiseEnChambre && $this->tableExists('folio_items')) {
                $folioId = $this->insert('folio_items', array_merge([
                    'booking_id'       => $bookingId,
                    'customer_id'      => $customerId,
                    'type'             => 'shop',
                    'description'      => "Boutique — Vente {$numero}",
                    'quantity'         => 1,
                    'unit_price'       => $total,
                    'total_price'      => $total,
                    'is_complimentary' => false,
                    'earns_points'     => true,
                    'recorded_by'      => $operateur,
                    'occurred_at'      => $quand,
                ], $this->timestamps($quand)));
                $crees++;

                $this->pdo->prepare('UPDATE shop_orders SET folio_item_id = ? WHERE id = ?')
                    ->execute([$folioId, $commandeId]);
                $this->pdo->prepare(
                    'UPDATE bookings
                     SET extras_amount = extras_amount + ?,
                         total_amount  = total_amount + ?,
                         balance_due   = balance_due + ?
                     WHERE id = ?'
                )->execute([$total, $total, $total, $bookingId]);
            }

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

    private function boutiqueId(): ?int
    {
        if (!$this->tableExists('points_of_sale')) {
            return null;
        }

        $id = $this->pdo->query(
            "SELECT id FROM points_of_sale WHERE kind = 'boutique' AND is_active = true ORDER BY sort_order, id LIMIT 1"
        )->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @return array{id:int, customer_id:int, customer_name:string}|null */
    private function sejourEnCours(?string $numero = null): ?array
    {
        if (!$this->tableExists('bookings')) {
            return null;
        }

        $sql = "SELECT b.id, b.customer_id, CONCAT(c.first_name, ' ', c.last_name) AS customer_name
                FROM bookings b
                JOIN customers c ON c.id = b.customer_id
                WHERE b.status = 'checked_in' AND b.booking_number LIKE ?
                " . ($numero !== null ? 'AND b.booking_number = ? ' : '') . '
                ORDER BY b.id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($numero !== null ? [BookingSeeder::PREFIXE . '%', $numero] : [BookingSeeder::PREFIXE . '%']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
