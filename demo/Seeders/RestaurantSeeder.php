<?php

namespace Demo\Seeders;

use Demo\Registre;

/**
 * Carte du restaurant, commandes, garde-manger, fiches recettes et banquets.
 *
 * Tous les prix, coûts et acomptes des JSON sont en FCFA et convertis en
 * centimes (* 100) à l'insertion. Chaque article de menu et du garde-manger
 * est rattaché au restaurant actif (ex. « Le Baleng » sur Hôtel Zingana).
 */
class RestaurantSeeder extends AbstractModuleSeeder
{
    public const REPERE_COMMANDE = '[demo] Commande de démonstration';

    public function module(): ?string
    {
        return 'restaurant';
    }

    public function label(): string
    {
        return 'Restaurant : carte, commandes, garde-manger, recettes et banquets';
    }

    public function seed(): int
    {
        if (!$this->tableExists('restaurant_menu_items')) {
            return 0;
        }

        $r     = $this->data['restaurant'];
        $posId = $this->restaurantId();
        $crees = 0;

        // --- Catégories de la carte (scopées par point de vente) ---
        $categories = [];

        foreach ($r['categories'] as $rang => $cat) {
            $reperes = $posId !== null
                ? ['point_of_sale_id' => $posId, 'name' => $cat['name']]
                : ['name' => $cat['name']];

            $avant = $this->findId('restaurant_menu_categories', $reperes);

            $categories[$cat['name']] = $this->insertOnce(
                'restaurant_menu_categories',
                $reperes,
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

        // --- Plats et boissons ---
        $plats        = [];
        $platsParNom  = [];

        foreach ($r['items'] as $rang => $item) {
            $reperes = $posId !== null
                ? ['point_of_sale_id' => $posId, 'name' => $item['name']]
                : ['name' => $item['name']];

            $prixCentimes = (int) $item['price'] * 100;
            $typeItem     = $item['type'] ?? 'food';
            $avant        = $this->findId('restaurant_menu_items', $reperes);

            if ($avant === null) {
                $id = $this->insert('restaurant_menu_items', array_merge($reperes, [
                    'restaurant_menu_category_id' => $categories[$item['category']] ?? null,
                    'price'                       => $prixCentimes,
                    'type'                        => $typeItem,
                    'description'                 => null,
                    'meal_services'               => $this->json($item['meals']),
                    'sort_order'                  => $rang,
                    'is_active'                   => true,
                ], $this->timestamps()));
                $crees++;
            } else {
                $id = $avant;
                if (Registre::contient($this->pdo, 'restaurant_menu_items', $id)) {
                    $this->pdo->prepare(
                        'UPDATE restaurant_menu_items SET price = ?, type = ? WHERE id = ?'
                    )->execute([$prixCentimes, $typeItem, $id]);
                }
            }

            $info = [
                'id'    => $id,
                'name'  => $item['name'],
                'price' => $prixCentimes,
                'type'  => $typeItem,
            ];
            $plats[]                     = $info;
            $platsParNom[$item['name']]  = $info;
        }

        // --- Commandes clients (dont imputations séjour et flux du jour J0) ---
        if ($this->tableExists('restaurant_customer_orders') && $plats !== []) {
            $crees += $this->commandes($r, $plats, $platsParNom, $posId);
        }

        // --- Garde-manger & fiches techniques / préparations maison ---
        $crees += $this->gardeMangerEtRecettes($r, $platsParNom, $posId);

        // --- Fiches boissons du Bar (doses, cocktails, mocktails, bouteilles) ---
        $crees += $this->barDrinks($r, $platsParNom, $posId);

        // --- Banquets (1 confirmé avec acompte, 1 devis à transformer) ---
        if ($posId !== null) {
            $crees += $this->banquets($r, $platsParNom, $posId);
        }

        return $crees;
    }

    /** Des commandes réparties sur J0 et les 14 derniers jours. */
    private function commandes(array $r, array $plats, array $platsParNom, ?int $posId): int
    {
        $vise   = $this->volume['restaurant_orders'] ?? 20;
        $deja   = $this->count('restaurant_customer_orders', 'notes = ?', [self::REPERE_COMMANDE]);
        $crees  = 0;

        $serveurs = array_values(array_filter([
            $this->userIdByEmail('kevin.wandji@' . StaffSeeder::DOMAINE, false),
            $this->userIdByEmail('laure.mbouda@' . StaffSeeder::DOMAINE, false),
            $this->userIdByEmail('arnaud.simo@' . StaffSeeder::DOMAINE, false),
        ]));
        $caissier  = $this->userIdByEmail('carine.moukoko@' . StaffSeeder::DOMAINE) ?? $this->anyUserId();
        $chefResto = $this->userIdByEmail('serge.djomo@' . StaffSeeder::DOMAINE) ?? $caissier;

        // Séjour en cours DEMO-0009 (Ch. 204) pour illustrer l'imputation sur note d'hôtel.
        $sejourEnCours = $this->sejourEnCours('DEMO-0009') ?? $this->sejourEnCours();

        for ($i = $deja; $i < $vise; $i++) {
            // Les 5 premières commandes sont datées d'aujourd'hui (J0) pour tester
            // cuisine, bar, service en salle, caisse et mise en compte chambre.
            $decalage = $i < 5 ? 0 : -(($i - 4) % 14 + 1);
            $heure    = $i < 5
                ? sprintf('%02d:%02d:00', 8 + $i, 10 + $i * 8)
                : sprintf('%02d:%02d:00', 11 + ($i % 10), ($i * 7) % 60);
            $quand    = $this->jours($decalage, $heure);

            $statut = match ($i) {
                0, 1    => 'served',
                2       => 'ready',
                3       => 'preparing',
                4       => 'pending',
                default => $this->cycle($r['order_statuses'], $i),
            };

            $estMiseEnChambre = ($i === 1 && $sejourEnCours !== null);
            $paye             = $statut === 'served';
            $methode          = $estMiseEnChambre
                ? 'room_charge'
                : ($paye ? $this->cycle(['cash', 'mobile_money', 'card'], $i) : null);

            $lignes = [];
            $total  = 0;

            if ($i === 0) {
                $choisis = ['Poulet DG', 'Jus de gingembre'];
            } elseif ($i === 1) {
                $choisis = ['Ndolè aux crevettes', 'Bière locale', 'Gâteau au chocolat'];
            } elseif ($i === 2) {
                $choisis = ['Poisson braisé', 'Eau minérale 1,5 L'];
            } elseif ($i === 3) {
                $choisis = ['Côtes de porc grillées', 'Verre de rhum (5 cl)'];
            } elseif ($i === 4) {
                $choisis = ['Poulet braisé entier', 'Cocktail Daiquiri'];
            } else {
                $choisis = [];
            }

            if ($choisis !== []) {
                foreach ($choisis as $nomPlat) {
                    $plat = $platsParNom[$nomPlat] ?? $plats[0];
                    $qte  = $i === 1 && $nomPlat === 'Bière locale' ? 2 : 1;
                    $lignes[] = ['plat' => $plat, 'qte' => $qte];
                    $total   += $plat['price'] * $qte;
                }
            } else {
                for ($l = 0; $l < 2 + ($i % 2); $l++) {
                    $plat     = $this->cycle($plats, $i * 3 + $l);
                    $qte      = 1 + ($l % 2);
                    $lignes[] = ['plat' => $plat, 'qte' => $qte];
                    $total   += $plat['price'] * $qte;
                }
            }

            $serveur   = $serveurs !== [] ? $this->cycle($serveurs, $i) : $caissier;
            $table     = $this->cycle($r['tables'], $i);
            $bookingId = $estMiseEnChambre ? (int) $sejourEnCours['id'] : null;

            $commandeId = $this->insert('restaurant_customer_orders', array_merge([
                'point_of_sale_id'   => $posId,
                'table_number'       => $table,
                'covers'             => 1 + ($i % 3),
                'order_type'         => 'standard',
                'is_complimentary'   => false,
                'customer_name'      => $estMiseEnChambre ? $sejourEnCours['customer_name'] : null,
                'booking_id'         => $bookingId,
                'status'             => $statut,
                'total_amount'       => $total,
                'notes'              => self::REPERE_COMMANDE,
                'placed_at'          => $quand,
                'source'             => 'dine_in',
                'created_by'         => $serveur ?? $chefResto,
                'assigned_server_id' => $statut === 'pending' ? null : $serveur,
                'assigned_at'        => $statut === 'pending' ? null : $quand,
                'sent_to_kitchen_at' => $statut === 'pending' ? null : $quand,
                'ready_at'           => in_array($statut, ['ready', 'served'], true) ? $quand : null,
                'served_at'          => $statut === 'served' ? $quand : null,
                'payment_status'     => $paye ? 'paid' : 'unpaid',
                'payment_method'     => $methode,
                'amount_paid'        => $paye ? $total : 0,
                'paid_at'            => $paye ? $quand : null,
                'paid_by'            => $paye ? $caissier : null,
            ], $this->timestamps($quand)));
            $crees++;

            if ($estMiseEnChambre && $this->tableExists('folio_items')) {
                $folioId = $this->insert('folio_items', array_merge([
                    'booking_id'       => $bookingId,
                    'customer_id'      => (int) $sejourEnCours['customer_id'],
                    'type'             => 'restaurant',
                    'description'      => "Restaurant ({$table}) — Commande #{$commandeId}",
                    'quantity'         => 1,
                    'unit_price'       => $total,
                    'total_price'      => $total,
                    'is_complimentary' => false,
                    'earns_points'     => true,
                    'recorded_by'      => $caissier,
                    'occurred_at'      => $quand,
                ], $this->timestamps($quand)));
                $crees++;

                $this->pdo->prepare('UPDATE restaurant_customer_orders SET folio_item_id = ? WHERE id = ?')
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
                $isBeverage = ($ligne['plat']['type'] ?? 'food') === 'drink';
                $this->insert('restaurant_customer_order_items', array_merge([
                    'restaurant_customer_order_id' => $commandeId,
                    'menu_item_id'                 => $ligne['plat']['id'],
                    'item_name'                    => $ligne['plat']['name'],
                    'quantity'                     => $ligne['qte'],
                    'unit_price'                   => $ligne['plat']['price'],
                    'total_price'                  => $ligne['plat']['price'] * $ligne['qte'],
                    'station'                      => $isBeverage ? 'bar' : 'cuisine',
                    'is_beverage'                  => $isBeverage,
                    'ready_at'                     => in_array($statut, ['ready', 'served'], true) ? $quand : null,
                ], $this->timestamps($quand)));
                $crees++;
            }
        }

        return $crees;
    }

    /** Garde-manger cuisine et fiches recettes / préparations de base. */
    private function gardeMangerEtRecettes(array $r, array $platsParNom, ?int $posId): int
    {
        if (!$this->tableExists('restaurant_pantry_items') || !$this->tableExists('restaurant_pantry_categories')) {
            return 0;
        }

        $crees = 0;

        $cats = [];
        foreach ($r['pantry_categories'] ?? [] as $rang => $nomCat) {
            $avant = $this->findId('restaurant_pantry_categories', ['name' => $nomCat]);
            $cats[$nomCat] = $this->insertOnce(
                'restaurant_pantry_categories',
                ['name' => $nomCat],
                array_merge(['sort_order' => $rang, 'is_active' => true], $this->timestamps())
            );
            if ($avant === null) {
                $crees++;
            }
        }

        $stockParNom = [];
        if ($this->tableExists('stock_items')) {
            foreach ($this->pdo->query('SELECT id, name FROM stock_items')->fetchAll(\PDO::FETCH_ASSOC) as $s) {
                $stockParNom[$s['name']] = (int) $s['id'];
            }
        }

        $pantryParNom = [];
        foreach ($r['pantry_items'] ?? [] as $item) {
            $reperes = $posId !== null
                ? ['point_of_sale_id' => $posId, 'name' => $item['name']]
                : ['name' => $item['name']];

            $avant        = $this->findId('restaurant_pantry_items', $reperes);
            $costCentimes = (int) ($item['cost_price'] ?? 0) * 100;
            $purchPrice   = isset($item['purchase_price']) ? (int) $item['purchase_price'] * 100 : null;
            $stockId      = isset($item['stock_item']) ? ($stockParNom[$item['stock_item']] ?? null) : null;

            $id = $this->insertOnce(
                'restaurant_pantry_items',
                $reperes,
                array_merge([
                    'restaurant_pantry_category_id' => $cats[$item['category']] ?? null,
                    'stock_item_id'                 => $stockId,
                    'unit'                          => $item['unit'],
                    'is_prepared'                   => (bool) ($item['is_prepared'] ?? false),
                    'purchase_conversion'           => $item['purchase_conversion'] ?? 1,
                    'purchase_price'                => $purchPrice,
                    'current_stock'                 => $item['current_stock'],
                    'min_stock'                     => $item['min_stock'],
                    'cost_price'                    => $costCentimes,
                    'average_cost'                  => $costCentimes,
                    'is_active'                     => true,
                ], $this->timestamps())
            );

            $pantryParNom[$item['name']] = $id;
            if ($avant === null) {
                $crees++;
            }
        }

        if (!$this->tableExists('restaurant_recipes') || !$this->tableExists('restaurant_recipe_lines')) {
            return $crees;
        }

        foreach ($r['recipes'] ?? [] as $recette) {
            $type     = $recette['type'] ?? 'dish';
            $menuId   = isset($recette['menu_item']) ? ($platsParNom[$recette['menu_item']]['id'] ?? null) : null;
            $prodId   = isset($recette['produces_pantry_item']) ? ($pantryParNom[$recette['produces_pantry_item']] ?? null) : null;

            $reperes = $type === 'prep'
                ? ['produces_pantry_item_id' => $prodId]
                : ['restaurant_menu_item_id' => $menuId];

            if (reset($reperes) === null) {
                continue;
            }

            $avant     = $this->findId('restaurant_recipes', $reperes);
            $recetteId = $this->insertOnce(
                'restaurant_recipes',
                $reperes,
                array_merge([
                    'point_of_sale_id' => $posId,
                    'name'             => $recette['name'],
                    'type'             => $type,
                    'yield_quantity'   => $recette['yield_quantity'] ?? 1,
                    'notes'            => $recette['notes'] ?? null,
                    'is_active'        => true,
                ], $this->timestamps())
            );

            if ($avant === null) {
                $crees++;
            }

            foreach ($recette['lines'] ?? [] as $ligne) {
                $ingId = $pantryParNom[$ligne['pantry_item']] ?? null;
                if ($ingId === null) {
                    continue;
                }

                $ligneAvant = $this->findId('restaurant_recipe_lines', [
                    'restaurant_recipe_id'      => $recetteId,
                    'restaurant_pantry_item_id' => $ingId,
                ]);

                $this->insertOnce(
                    'restaurant_recipe_lines',
                    ['restaurant_recipe_id' => $recetteId, 'restaurant_pantry_item_id' => $ingId],
                    array_merge([
                        'quantity'      => $ligne['quantity'],
                        'waste_percent' => $ligne['waste_percent'] ?? 0,
                    ], $this->timestamps())
                );

                if ($ligneAvant === null) {
                    $crees++;
                }
            }
        }

        return $crees;
    }

    /** Fiches techniques des boissons du bar (bouteilles entières, verres de 5 cl, cocktails et mocktails). */
    private function barDrinks(array $r, array $platsParNom, ?int $posId): int
    {
        if (!$this->tableExists('restaurant_bar_drinks') || empty($r['bar_drinks'])) {
            return 0;
        }

        $stockParNom = [];
        if ($this->tableExists('stock_items')) {
            foreach ($this->pdo->query('SELECT id, name FROM stock_items')->fetchAll(\PDO::FETCH_ASSOC) as $s) {
                $stockParNom[$s['name']] = (int) $s['id'];
            }
        }

        $crees = 0;

        foreach ($r['bar_drinks'] as $bd) {
            $plat = $platsParNom[$bd['menu_item']] ?? null;
            if ($plat === null) {
                continue;
            }

            $menuItemId = (int) $plat['id'];
            $format     = $bd['format'];
            $recipe     = in_array($format, ['cocktail', 'mocktail'], true);

            $ingredients = [];

            if ($recipe) {
                foreach ($bd['ingredients'] ?? [] as $ing) {
                    $stockId = $stockParNom[$ing['stock_item']] ?? null;
                    if ($stockId === null) {
                        continue;
                    }
                    $ingredients[] = [
                        'stock_item_id' => $stockId,
                        'measure'       => $ing['measure'] ?? 'ml',
                        'quantity'      => (float) $ing['quantity'],
                    ];
                }
            } else {
                $stockId = isset($bd['stock_item']) ? ($stockParNom[$bd['stock_item']] ?? null) : null;
                if ($stockId !== null) {
                    $ingredients[] = [
                        'stock_item_id' => $stockId,
                        'measure'       => $format === 'glass' ? 'ml' : 'stock',
                        'quantity'      => $format === 'glass' ? 50 : 1,
                    ];
                }
            }

            if ($ingredients === []) {
                continue;
            }

            $avant = $this->findId('restaurant_bar_drinks', ['restaurant_menu_item_id' => $menuItemId]);

            $this->insertOnce(
                'restaurant_bar_drinks',
                ['restaurant_menu_item_id' => $menuItemId],
                array_merge([
                    'format'       => $format,
                    'ingredients'  => $this->json($ingredients),
                    'instructions' => $bd['instructions'] ?? null,
                ], $this->timestamps())
            );

            if ($avant === null) {
                $crees++;
            }
        }

        return $crees;
    }

    /** Dossiers de banquet : 1 confirmé avec acompte et 1 devis à transformer. */
    private function banquets(array $r, array $platsParNom, int $posId): int
    {
        if (!$this->tableExists('restaurant_banquets')) {
            return 0;
        }

        $crees     = 0;
        $spaceId   = $this->espaceId($posId);
        $chefResto = $this->userIdByEmail('serge.djomo@' . StaffSeeder::DOMAINE) ?? $this->anyUserId();
        $caissier  = $this->userIdByEmail('carine.moukoko@' . StaffSeeder::DOMAINE) ?? $chefResto;
        $clients   = $this->ids('customers', 'notes = ?', [CustomerSeeder::MARQUEUR]);

        foreach ($r['banquets'] ?? [] as $rang => $bq) {
            if ($this->findId('restaurant_banquets', ['reference' => $bq['reference']]) !== null) {
                continue;
            }

            $prixCouvert  = (int) $bq['price_per_cover'] * 100;
            $extras       = (int) ($bq['extras_amount'] ?? 0) * 100;
            $total        = (int) $bq['covers'] * $prixCouvert + $extras;
            $acompteExige = (int) ($bq['deposit_required'] ?? 0) * 100;
            $estConfirme  = ($bq['status'] ?? 'devis') === 'confirme';

            $menuItems = array_map(function (array $m) use ($platsParNom): array {
                $plat = $platsParNom[$m['name']] ?? null;
                return [
                    'course'       => $m['course'],
                    'menu_item_id' => $plat['id'] ?? null,
                    'name'         => $m['name'],
                    'unit_price'   => (int) ($m['unit_price'] ?? 0) * 100,
                    'notes'        => $m['notes'] ?? null,
                ];
            }, $bq['menu_items'] ?? []);

            $creeQuand = $this->jours(-3, '11:00:00');
            $banquetId = $this->insert('restaurant_banquets', array_merge([
                'point_of_sale_id' => $posId,
                'reference'        => $bq['reference'],
                'title'            => $bq['title'],
                'customer_id'      => $clients[$rang] ?? ($clients[0] ?? null),
                'client_name'      => $bq['client_name'],
                'client_phone'     => $bq['client_phone'] ?? null,
                'client_email'     => $bq['client_email'] ?? null,
                'event_date'       => $this->date((int) ($bq['event_date_offset'] ?? 5)),
                'start_time'       => $bq['start_time'] ?? null,
                'end_time'         => $bq['end_time'] ?? null,
                'space_id'         => $spaceId,
                'covers'           => (int) $bq['covers'],
                'price_per_cover'  => $prixCouvert,
                'extras_amount'    => $extras,
                'total_amount'     => $total,
                'deposit_required' => $acompteExige,
                'menu'             => $bq['menu'] ?? null,
                'menu_items'       => $this->json($menuItems),
                'status'           => $bq['status'] ?? 'devis',
                'source'           => $bq['source'] ?? 'restaurant',
                'notes'            => $bq['notes'] ?? '[demo] Banquet de démonstration',
                'created_by'       => $chefResto,
                'confirmed_at'     => $estConfirme ? $this->jours(-2, '15:00:00') : null,
            ], $this->timestamps($creeQuand)));
            $crees++;

            $payeCentimes = (int) ($bq['paid_amount'] ?? 0) * 100;
            if ($payeCentimes > 0 && $this->tableExists('restaurant_banquet_payments')) {
                $this->insert('restaurant_banquet_payments', array_merge([
                    'restaurant_banquet_id' => $banquetId,
                    'reference'             => 'BQT-PAY-' . $bq['reference'],
                    'kind'                  => 'acompte',
                    'amount'                => $payeCentimes,
                    'payment_method'        => $bq['payment_method'] ?? 'transfer',
                    'paid_by'               => $caissier,
                    'paid_at'               => $this->jours(-2, '15:00:00'),
                    'notes'                 => '[demo] Acompte banquet confirmé',
                ], $this->timestamps($this->jours(-2, '15:00:00'))));
                $crees++;
            }
        }

        return $crees;
    }

    private function restaurantId(): ?int
    {
        if (!$this->tableExists('points_of_sale')) {
            return null;
        }

        $id = $this->pdo->query(
            "SELECT id FROM points_of_sale WHERE kind = 'restauration' AND is_active = true ORDER BY sort_order, id LIMIT 1"
        )->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function espaceId(int $posId): ?int
    {
        if (!$this->tableExists('spaces')) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM spaces
             WHERE is_active = true AND (point_of_sale_id = ? OR point_of_sale_id IS NULL)
             ORDER BY point_of_sale_id NULLS LAST, sort_order, id
             LIMIT 1'
        );
        $stmt->execute([$posId]);
        $id = $stmt->fetchColumn();

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
